<?php

namespace JMReferral\Reports;

use DateTimeImmutable;
use JMReferral\Alerts\OperationalAlertService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;

class ReportService
{
    public const RANGE_TODAY = 'today';
    public const RANGE_THIS_WEEK = 'this_week';
    public const RANGE_THIS_MONTH = 'this_month';
    public const RANGE_THIS_YEAR = 'this_year';
    public const RANGE_CUSTOM = 'custom';

    public function __construct(
        private ReportRepository $report_repository,
        private AccessPolicy $access_policy,
        private OperationalAlertService $alert_service
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function range_labels(): array
    {
        return [
            self::RANGE_TODAY      => __('Today', 'jm-referral-system'),
            self::RANGE_THIS_WEEK  => __('This Week', 'jm-referral-system'),
            self::RANGE_THIS_MONTH => __('This Month', 'jm-referral-system'),
            self::RANGE_THIS_YEAR  => __('This Year', 'jm-referral-system'),
            self::RANGE_CUSTOM     => __('Custom Range', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowed_ranges(): array
    {
        return array_keys(self::range_labels());
    }

    public static function get_reports_page_url(array $args = []): string
    {
        return add_query_arg(
            array_merge(['page' => 'jm-referrals-reports'], $args),
            admin_url('admin.php')
        );
    }

    public function current_user_can_view(): bool
    {
        return Capabilities::current_user_can(Capabilities::VIEW_REPORTS);
    }

    /**
     * @param array{range?: string, start_date?: string, end_date?: string} $filters
     * @return array{
     *     range: string,
     *     start_date: string,
     *     end_date: string,
     *     range_labels: array<string, string>,
     *     kpis: array<string, int>,
     *     errors: array<string, string>
     * }
     */
    public function get_report_data(array $filters = []): array
    {
        $parsed = $this->resolve_date_range($filters);

        if (! empty($parsed['errors'])) {
            return [
                'range'         => (string) ($parsed['range'] ?? self::RANGE_THIS_MONTH),
                'start_date'    => (string) ($parsed['start_date'] ?? ''),
                'end_date'      => (string) ($parsed['end_date'] ?? ''),
                'range_labels'  => self::range_labels(),
                'kpis'          => $this->empty_kpis(),
                'errors'        => $parsed['errors'],
            ];
        }

        $start  = (string) $parsed['start_date'];
        $end    = (string) $parsed['end_date'];
        $access = $this->access_policy->get_assigned_user_constraint();

        $alert_counts = $this->alert_service->get_alerts()['counts'] ?? [];
        $alert_total  = absint($alert_counts['total'] ?? 0);

        return [
            'range'        => (string) $parsed['range'],
            'start_date'   => $start,
            'end_date'     => $end,
            'range_labels' => self::range_labels(),
            'kpis'         => [
                'referrals_total'            => $this->report_repository->count_referrals_in_range($start, $end, $access),
                'referrals_new'              => $this->report_repository->count_new_referrals_in_range($start, $end, $access),
                'active_clients'             => $this->report_repository->count_active_clients($access),
                'assessments_completed'      => $this->report_repository->count_assessments_completed_in_range($start, $end, $access),
                'care_plans_active'          => $this->report_repository->count_active_care_plans($access),
                'visits_scheduled'           => $this->report_repository->count_visits_scheduled_in_range($start, $end, $access),
                'visits_completed'           => $this->report_repository->count_visits_completed_in_range($start, $end, $access),
                'visits_missed'              => $this->report_repository->count_visits_missed_in_range($start, $end, $access),
                'medication_administrations' => $this->report_repository->count_medication_administrations_in_range($start, $end, $access),
                'medication_exceptions'      => $this->report_repository->count_medication_exceptions_in_range($start, $end, $access),
                'operational_alerts'         => $alert_total,
            ],
            'errors'       => [],
        ];
    }

    /**
     * Lightweight dashboard teaser for users with report access.
     *
     * @return array{referrals_total: int, visits_completed: int, operational_alerts: int, reports_url: string}|null
     */
    public function get_dashboard_summary(): ?array
    {
        if (! $this->current_user_can_view()) {
            return null;
        }

        $range = $this->resolve_date_range(['range' => self::RANGE_THIS_MONTH]);
        $start = (string) $range['start_date'];
        $end   = (string) $range['end_date'];
        $access = $this->access_policy->get_assigned_user_constraint();

        $alert_counts = $this->alert_service->get_alerts()['counts'] ?? [];

        return [
            'referrals_total'    => $this->report_repository->count_referrals_in_range($start, $end, $access),
            'visits_completed'   => $this->report_repository->count_visits_completed_in_range($start, $end, $access),
            'operational_alerts' => absint($alert_counts['total'] ?? 0),
            'reports_url'        => self::get_reports_page_url(['jmrs_report_range' => self::RANGE_THIS_MONTH]),
        ];
    }

    /**
     * @param array{range?: string, start_date?: string, end_date?: string} $filters
     * @return array{range: string, start_date: string, end_date: string, errors: array<string, string>}
     */
    public function resolve_date_range(array $filters): array
    {
        $errors = [];
        $range  = sanitize_key((string) ($filters['range'] ?? self::RANGE_THIS_MONTH));

        if (! in_array($range, self::allowed_ranges(), true)) {
            $range = self::RANGE_THIS_MONTH;
        }

        $today = DateTimeImmutable::createFromFormat('Y-m-d', current_time('Y-m-d'));
        if (! $today instanceof DateTimeImmutable) {
            $today = new DateTimeImmutable('today');
        }

        $start = '';
        $end   = '';

        switch ($range) {
            case self::RANGE_TODAY:
                $start = $today->format('Y-m-d');
                $end   = $start;
                break;

            case self::RANGE_THIS_WEEK:
                $week = $this->week_bounds($today);
                $start = $week['start'];
                $end   = $week['end'];
                break;

            case self::RANGE_THIS_MONTH:
                $start = $today->format('Y-m-01');
                $end   = $today->format('Y-m-t');
                break;

            case self::RANGE_THIS_YEAR:
                $start = $today->format('Y-01-01');
                $end   = $today->format('Y-12-31');
                break;

            case self::RANGE_CUSTOM:
                $start = trim((string) ($filters['start_date'] ?? ''));
                $end   = trim((string) ($filters['end_date'] ?? ''));

                if ('' === $start || ! $this->is_valid_date($start)) {
                    $errors['start_date'] = __('Please enter a valid start date.', 'jm-referral-system');
                }
                if ('' === $end || ! $this->is_valid_date($end)) {
                    $errors['end_date'] = __('Please enter a valid end date.', 'jm-referral-system');
                }
                if (
                    empty($errors)
                    && $start > $end
                ) {
                    $errors['end_date'] = __('End date cannot be earlier than start date.', 'jm-referral-system');
                }
                break;
        }

        return [
            'range'      => $range,
            'start_date' => $start,
            'end_date'   => $end,
            'errors'     => $errors,
        ];
    }

    /**
     * @return array{start: string, end: string}
     */
    private function week_bounds(DateTimeImmutable $today): array
    {
        $start_of_week = (int) get_option('start_of_week', 1);
        $weekday       = (int) $today->format('w'); // 0 = Sunday

        $offset = ($weekday - $start_of_week + 7) % 7;
        $start  = $today->modify('-' . $offset . ' days');
        $end    = $start->modify('+6 days');

        return [
            'start' => $start->format('Y-m-d'),
            'end'   => $end->format('Y-m-d'),
        ];
    }

    private function is_valid_date(string $value): bool
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $value;
    }

    /**
     * @return array<string, int>
     */
    private function empty_kpis(): array
    {
        return [
            'referrals_total'            => 0,
            'referrals_new'              => 0,
            'active_clients'             => 0,
            'assessments_completed'      => 0,
            'care_plans_active'          => 0,
            'visits_scheduled'           => 0,
            'visits_completed'           => 0,
            'visits_missed'              => 0,
            'medication_administrations' => 0,
            'medication_exceptions'      => 0,
            'operational_alerts'         => 0,
        ];
    }
}
