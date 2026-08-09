<?php

namespace JMReferral\Reports;

use DateTimeImmutable;
use JMReferral\Alerts\OperationalAlertService;
use JMReferral\Homes\OccupancyRepository;
use JMReferral\Homes\OccupancyService;
use JMReferral\Medication\MedicationAdministrationService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\CareSetting;
use JMReferral\Users\UserProvider;
use JMReferral\Visits\CareVisitService;
use JMReferral\Visits\VisitTaskService;

class ReportService
{
    public const RANGE_TODAY = 'today';
    public const RANGE_THIS_WEEK = 'this_week';
    public const RANGE_THIS_MONTH = 'this_month';
    public const RANGE_THIS_YEAR = 'this_year';
    public const RANGE_CUSTOM = 'custom';

    public const SECTION_SUPPORTED_LIVING = 'supported_living_snapshot';
    public const SECTION_VACANCY = 'supported_living_vacancies';
    public const SECTION_PLACEMENT_MOVEMENTS = 'placement_movements';

    /** UI detail cap; CSV uses the uncapped export query. */
    public const PLACEMENT_MOVEMENTS_UI_LIMIT = 100;

    public function __construct(
        private ReportRepository $report_repository,
        private AccessPolicy $access_policy,
        private OperationalAlertService $alert_service,
        private UserProvider $user_provider,
        private OccupancyRepository $occupancy_repository
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
     * @param array{
     *     range?: string,
     *     start_date?: string,
     *     end_date?: string,
     *     home_id?: int|string,
     *     visit_care_context?: string,
     *     visit_home_id?: int|string
     * } $filters
     * @return array{
     *     range: string,
     *     start_date: string,
     *     end_date: string,
     *     range_labels: array<string, string>,
     *     kpis: array<string, int|float|null>,
     *     supported_living: array<string, mixed>,
     *     vacancy: array<string, mixed>,
     *     visit_filters: array<string, mixed>,
     *     sections: array<int, array<string, mixed>>,
     *     errors: array<string, string>
     * }
     */
    public function get_report_data(array $filters = []): array
    {
        $parsed = $this->resolve_date_range($filters);
        $home   = $this->resolve_home_filter($filters);

        if (! empty($parsed['errors'])) {
            return [
                'range'               => (string) ($parsed['range'] ?? self::RANGE_THIS_MONTH),
                'start_date'          => (string) ($parsed['start_date'] ?? ''),
                'end_date'            => (string) ($parsed['end_date'] ?? ''),
                'range_labels'        => self::range_labels(),
                'kpis'                => $this->empty_kpis(),
                'supported_living'    => $this->empty_supported_living_snapshot(),
                'vacancy'             => $this->empty_vacancy_report($home),
                'placement_movements' => $this->empty_placement_movements(),
                'visit_filters'       => $this->empty_visit_filters(),
                'sections'            => [],
                'errors'              => $parsed['errors'],
            ];
        }

        $start         = (string) $parsed['start_date'];
        $end           = (string) $parsed['end_date'];
        $access        = $this->access_policy->get_assigned_user_constraint();
        $visit_filters = $this->resolve_visit_filters($filters, $start, $end);
        $visit_sql     = $visit_filters['sql'];

        $alert_result = $this->alert_service->get_alerts();
        $alert_total  = absint($alert_result['counts']['total'] ?? 0);

        $scheduled = $this->report_repository->count_visits_scheduled_in_range($start, $end, $access, $visit_sql);
        $completed = $this->report_repository->count_visits_completed_in_range($start, $end, $access, $visit_sql);
        $missed    = $this->report_repository->count_visits_missed_in_range($start, $end, $access, $visit_sql);

        $completion_base = $completed + $missed;
        $completion_pct  = $completion_base > 0
            ? round(($completed / $completion_base) * 100, 1)
            : null;

        $avg_duration = $this->report_repository->get_average_visit_duration_minutes(
            $start,
            $end,
            $access,
            $visit_sql
        );
        $avg_turnaround = $this->report_repository->get_average_assessment_turnaround_days($start, $end, $access);

        $task_rows = $this->report_repository->get_visit_tasks_by_status($start, $end, $access, $visit_sql);
        $task_counts = $this->index_counts_by_key($task_rows);

        $supported_living    = $this->build_supported_living_snapshot($access);
        $vacancy             = $this->build_vacancy_report($supported_living, $home);
        $placement_movements = $this->build_placement_movements($start, $end, $access);

        $delivery_summary = $this->report_repository->count_visits_by_delivery_context_in_range(
            $start,
            $end,
            $access,
            $visit_sql
        );

        return [
            'range'            => (string) $parsed['range'],
            'start_date'       => $start,
            'end_date'         => $end,
            'range_labels'     => self::range_labels(),
            'kpis'             => [
                'referrals_total'            => $this->report_repository->count_referrals_in_range($start, $end, $access),
                'referrals_new'              => $this->report_repository->count_new_referrals_in_range($start, $end, $access),
                'active_clients'             => $this->report_repository->count_active_clients($access),
                'assessments_completed'      => $this->report_repository->count_assessments_completed_in_range($start, $end, $access),
                'care_plans_active'          => $this->report_repository->count_active_care_plans($access),
                'visits_scheduled'           => $scheduled,
                'visits_completed'           => $completed,
                'visits_missed'              => $missed,
                'medication_administrations' => $this->report_repository->count_medication_administrations_in_range($start, $end, $access),
                'medication_exceptions'      => $this->report_repository->count_medication_exceptions_in_range($start, $end, $access),
                'operational_alerts'         => $alert_total,
            ],
            'supported_living'    => $supported_living,
            'vacancy'             => $vacancy,
            'placement_movements' => $placement_movements,
            'visit_filters'       => $visit_filters,
            'sections'            => array_merge(
                [
                    $this->build_supported_living_section($supported_living),
                    $this->build_vacancy_section($vacancy),
                    $this->build_placement_movements_section($placement_movements),
                ],
                $this->build_analytics_sections(
                    $start,
                    $end,
                    $access,
                    $scheduled,
                    $completed,
                    $missed,
                    $completion_pct,
                    $avg_duration,
                    $avg_turnaround,
                    $task_counts,
                    $alert_result,
                    $visit_sql,
                    $delivery_summary
                )
            ),
            'errors'           => [],
        ];
    }

    /**
     * @param array{critical?: int, warning?: int, information?: int, total?: int}|null $precomputed_alert_counts
     *        When provided (same request as dashboard alerts), skips a second alert-engine run.
     * @return array{referrals_total: int, visits_completed: int, operational_alerts: int, reports_url: string}|null
     */
    public function get_dashboard_summary(?array $precomputed_alert_counts = null): ?array
    {
        if (! $this->current_user_can_view()) {
            return null;
        }

        $range  = $this->resolve_date_range(['range' => self::RANGE_THIS_MONTH]);
        $start  = (string) $range['start_date'];
        $end    = (string) $range['end_date'];
        $access = $this->access_policy->get_assigned_user_constraint();

        if (null !== $precomputed_alert_counts) {
            $alert_counts = $precomputed_alert_counts;
        } else {
            $alert_counts = $this->alert_service->get_alerts()['counts'] ?? [];
        }

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
                $week  = $this->week_bounds($today);
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
                if (empty($errors) && $start > $end) {
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
     * @param array<string, int>                                                                 $task_counts
     * @param array{alerts?: array<int, array<string, mixed>>, type_labels?: array<string, string>} $alert_result
     * @param array{care_context: string, home_id: int, is_active: bool}|null                    $visit_sql
     * @param array{supported_living: int, own_home: int, unresolved: int}                       $delivery_summary
     * @return array<int, array<string, mixed>>
     */
    private function build_analytics_sections(
        string $start,
        string $end,
        ?int $access,
        int $scheduled,
        int $completed,
        int $missed,
        ?float $completion_pct,
        ?float $avg_duration,
        ?float $avg_turnaround,
        array $task_counts,
        array $alert_result,
        ?array $visit_sql = null,
        array $delivery_summary = []
    ): array {
        $visit_status_labels = CareVisitService::status_labels();
        $med_status_labels   = MedicationAdministrationService::status_labels();
        $med_reason_labels   = MedicationAdministrationService::reason_labels();
        $task_status_labels  = VisitTaskService::status_labels();
        $priority_labels     = [
            'low'    => __('Low', 'jm-referral-system'),
            'medium' => __('Medium', 'jm-referral-system'),
            'high'   => __('High', 'jm-referral-system'),
            'urgent' => __('Urgent', 'jm-referral-system'),
        ];

        $visits_completed_staff = $this->report_repository->get_visits_completed_per_staff(
            $start,
            $end,
            $access,
            $visit_sql
        );
        $meds_per_staff         = $this->report_repository->get_medication_administrations_per_staff($start, $end, $access);
        $staff_names            = $this->resolve_staff_names(
            array_merge(
                array_column($visits_completed_staff, 'user_id'),
                array_column($meds_per_staff, 'user_id')
            )
        );

        $delivery_dataset = $this->build_dataset(
            'visits_by_delivery_context',
            __('Visit Delivery Context', 'jm-referral-system'),
            [
                __('Supported Living', 'jm-referral-system')                 => (int) ($delivery_summary['supported_living'] ?? 0),
                (string) (CareSetting::options()[CareSetting::OWN_HOME] ?? __("Client's Own Home", 'jm-referral-system'))
                    => (int) ($delivery_summary['own_home'] ?? 0),
                __('Unresolved / Location Not Recorded', 'jm-referral-system') => (int) ($delivery_summary['unresolved'] ?? 0),
            ]
        );
        $delivery_dataset['note'] = __(
            'Uses the same classification as the Care Delivery filter: executed visits use the recorded snapshot; open visits use current service location; legacy/missed/cancelled without a snapshot are Location Not Recorded.',
            'jm-referral-system'
        );
        if (null !== $visit_sql) {
            $delivery_dataset['empty_message'] = __(
                'No visits match the selected care-delivery filters.',
                'jm-referral-system'
            );
        }

        return [
            [
                'id'       => 'referral_analytics',
                'title'    => __('Referral Analytics', 'jm-referral-system'),
                'datasets' => [
                    $this->dataset_from_month_rows(
                        'referrals_by_month',
                        __('Referrals by Month', 'jm-referral-system'),
                        $this->report_repository->get_referrals_by_month($start, $end, $access)
                    ),
                    $this->dataset_from_labelled_rows(
                        'referrals_by_service_type',
                        __('Referrals by Service Type', 'jm-referral-system'),
                        $this->report_repository->get_referrals_by_service_type($start, $end, $access)
                    ),
                    $this->dataset_from_labelled_rows(
                        'referrals_by_workflow_stage',
                        __('Referrals by Workflow Stage', 'jm-referral-system'),
                        $this->report_repository->get_referrals_by_workflow_stage($start, $end, $access)
                    ),
                    $this->dataset_from_labelled_rows(
                        'referrals_by_priority',
                        __('Referrals by Priority', 'jm-referral-system'),
                        $this->relabel_rows(
                            $this->report_repository->get_referrals_by_priority($start, $end, $access),
                            $priority_labels
                        )
                    ),
                ],
            ],
            [
                'id'       => 'visit_analytics',
                'title'    => __('Visit Analytics', 'jm-referral-system'),
                'notes'    => [
                    __(
                        'Executed visits are reported against the service location recorded when care was delivered. Upcoming visits use the client\'s current service location.',
                        'jm-referral-system'
                    ),
                    __(
                        'Legacy, missed or cancelled visits without a recorded historical location may appear as Location Not Recorded.',
                        'jm-referral-system'
                    ),
                ],
                'datasets' => [
                    $delivery_dataset,
                    $this->build_dataset(
                        'visits_status_comparison',
                        __('Scheduled vs Completed vs Missed', 'jm-referral-system'),
                        [
                            __('Scheduled', 'jm-referral-system') => $scheduled,
                            __('Completed', 'jm-referral-system') => $completed,
                            __('Missed', 'jm-referral-system')    => $missed,
                        ]
                    ),
                    $this->build_metric_dataset(
                        'visit_completion_percentage',
                        __('Visit Completion Percentage', 'jm-referral-system'),
                        __('Completion %', 'jm-referral-system'),
                        null !== $completion_pct ? $completion_pct : 0,
                        null !== $completion_pct
                            ? sprintf(
                                /* translators: %s: percentage */
                                __('%s%% of completed + missed visits', 'jm-referral-system'),
                                (string) $completion_pct
                            )
                            : __('No completed or missed visits in range.', 'jm-referral-system')
                    ),
                    $this->build_metric_dataset(
                        'average_visit_duration',
                        __('Average Visit Duration', 'jm-referral-system'),
                        __('Average minutes', 'jm-referral-system'),
                        null !== $avg_duration ? $avg_duration : 0,
                        null !== $avg_duration
                            ? sprintf(
                                /* translators: %s: minutes */
                                __('%s minutes', 'jm-referral-system'),
                                (string) $avg_duration
                            )
                            : __('No completed visits with duration in range.', 'jm-referral-system')
                    ),
                    $this->dataset_from_labelled_rows(
                        'visits_by_type',
                        __('Visits by Visit Type', 'jm-referral-system'),
                        $this->report_repository->get_visits_by_type($start, $end, $access, $visit_sql)
                    ),
                    $this->dataset_from_labelled_rows(
                        'visits_by_status_detail',
                        __('Visits by Status', 'jm-referral-system'),
                        $this->relabel_rows(
                            $this->report_repository->get_visits_by_status_in_range($start, $end, $access, $visit_sql),
                            $visit_status_labels
                        )
                    ),
                ],
            ],
            [
                'id'       => 'medication_analytics',
                'title'    => __('Medication Analytics', 'jm-referral-system'),
                'datasets' => [
                    $this->dataset_from_labelled_rows(
                        'administrations_by_status',
                        __('Administrations by Status', 'jm-referral-system'),
                        $this->relabel_rows(
                            $this->report_repository->get_medication_administrations_by_status($start, $end, $access),
                            $med_status_labels
                        )
                    ),
                    $this->dataset_from_labelled_rows(
                        'exceptions_by_reason',
                        __('Medication Exceptions by Reason Code', 'jm-referral-system'),
                        $this->relabel_rows(
                            $this->report_repository->get_medication_exceptions_by_reason($start, $end, $access),
                            $med_reason_labels
                        )
                    ),
                    $this->dataset_from_month_rows(
                        'exception_trend_by_month',
                        __('Exception Trend by Month', 'jm-referral-system'),
                        $this->report_repository->get_medication_exceptions_by_month($start, $end, $access)
                    ),
                ],
            ],
            [
                'id'       => 'task_analytics',
                'title'    => __('Task Analytics', 'jm-referral-system'),
                'datasets' => [
                    $this->build_dataset(
                        'task_status_summary',
                        __('Task Status Summary', 'jm-referral-system'),
                        [
                            __('Completed', 'jm-referral-system')   => (int) ($task_counts[VisitTaskService::STATUS_COMPLETED] ?? 0),
                            __('Refused', 'jm-referral-system')     => (int) ($task_counts[VisitTaskService::STATUS_REFUSED] ?? 0),
                            __('Outstanding', 'jm-referral-system') => (int) ($task_counts[VisitTaskService::STATUS_PENDING] ?? 0)
                                + (int) ($task_counts[VisitTaskService::STATUS_NOT_COMPLETED] ?? 0),
                        ]
                    ),
                    $this->dataset_from_labelled_rows(
                        'tasks_by_status',
                        __('Tasks by Status', 'jm-referral-system'),
                        $this->relabel_rows(
                            $this->report_repository->get_visit_tasks_by_status($start, $end, $access, $visit_sql),
                            $task_status_labels
                        )
                    ),
                    $this->dataset_from_labelled_rows(
                        'top_task_exception_types',
                        __('Top Task Exception Types', 'jm-referral-system'),
                        $this->report_repository->get_top_task_exception_types($start, $end, $access, 10, $visit_sql)
                    ),
                ],
            ],
            [
                'id'       => 'staff_analytics',
                'title'    => __('Staff Analytics', 'jm-referral-system'),
                'datasets' => [
                    $this->dataset_from_staff_rows(
                        'visits_completed_per_staff',
                        __('Visits Completed per Staff Member', 'jm-referral-system'),
                        $visits_completed_staff,
                        $staff_names
                    ),
                    $this->dataset_from_staff_rows(
                        'medication_administrations_per_staff',
                        __('Medication Administrations per Staff Member', 'jm-referral-system'),
                        $meds_per_staff,
                        $staff_names
                    ),
                    $this->build_metric_dataset(
                        'outstanding_manager_reviews',
                        __('Outstanding Manager Reviews', 'jm-referral-system'),
                        __('Awaiting review', 'jm-referral-system'),
                        $this->report_repository->count_outstanding_manager_reviews($access, $visit_sql),
                        __('Snapshot of completed visits awaiting manager review. Visit Care Delivery filters use historical snapshot context when applied.', 'jm-referral-system')
                    ),
                    $this->build_metric_dataset(
                        'active_care_team_assignments',
                        __('Active Care-Team Assignments', 'jm-referral-system'),
                        __('Active assignments', 'jm-referral-system'),
                        $this->report_repository->count_active_care_team_assignments($access),
                        __('Snapshot of active care-team assignment rows.', 'jm-referral-system')
                    ),
                ],
            ],
            [
                'id'       => 'compliance_analytics',
                'title'    => __('Compliance Analytics', 'jm-referral-system'),
                'datasets' => [
                    $this->build_metric_dataset(
                        'care_plan_reviews_overdue',
                        __('Care-Plan Reviews Overdue', 'jm-referral-system'),
                        __('Overdue reviews', 'jm-referral-system'),
                        $this->report_repository->count_overdue_care_plan_reviews($access),
                        __('Snapshot of active care plans past their review date.', 'jm-referral-system')
                    ),
                    $this->dataset_from_alert_types(
                        'operational_alerts_by_type',
                        __('Operational Alerts by Type', 'jm-referral-system'),
                        $alert_result
                    ),
                    $this->build_metric_dataset(
                        'high_priority_referrals',
                        __('High-Priority Referrals', 'jm-referral-system'),
                        __('Open high/urgent', 'jm-referral-system'),
                        $this->report_repository->count_high_priority_referrals($access),
                        __('Snapshot of open high or urgent priority referrals.', 'jm-referral-system')
                    ),
                    $this->build_metric_dataset(
                        'assessment_turnaround_time',
                        __('Assessment Turnaround Time', 'jm-referral-system'),
                        __('Average days', 'jm-referral-system'),
                        null !== $avg_turnaround ? $avg_turnaround : 0,
                        null !== $avg_turnaround
                            ? sprintf(
                                /* translators: %s: days */
                                __('Average %s days from referral creation to assessment completion.', 'jm-referral-system'),
                                (string) $avg_turnaround
                            )
                            : __('No completed assessments in range.', 'jm-referral-system')
                    ),
                ],
            ],
        ];
    }

    /**
     * @param array<int, array{month: string, count: int}> $rows
     * @return array<string, mixed>
     */
    private function dataset_from_month_rows(string $id, string $title, array $rows): array
    {
        $pairs = [];
        foreach ($rows as $row) {
            $month = (string) ($row['month'] ?? '');
            if ('' === $month) {
                continue;
            }
            $pairs[$month] = (int) ($row['count'] ?? 0);
        }

        return $this->build_dataset($id, $title, $pairs);
    }

    /**
     * @param array<int, array{key: string, label: string, count: int}> $rows
     * @return array<string, mixed>
     */
    private function dataset_from_labelled_rows(string $id, string $title, array $rows): array
    {
        $pairs = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? $row['key'] ?? ''));
            if ('' === $label) {
                $label = __('Unknown', 'jm-referral-system');
            }
            $pairs[$label] = (int) ($row['count'] ?? 0);
        }

        return $this->build_dataset($id, $title, $pairs);
    }

    /**
     * @param array<int, array{user_id: int, count: int}> $rows
     * @param array<int, string>                          $staff_names
     * @return array<string, mixed>
     */
    private function dataset_from_staff_rows(string $id, string $title, array $rows, array $staff_names): array
    {
        $pairs = [];
        foreach ($rows as $row) {
            $user_id = (int) ($row['user_id'] ?? 0);
            $label   = $staff_names[$user_id] ?? sprintf(
                /* translators: %d: user ID */
                __('User #%d', 'jm-referral-system'),
                $user_id
            );
            $pairs[$label] = (int) ($row['count'] ?? 0);
        }

        return $this->build_dataset($id, $title, $pairs);
    }

    /**
     * @param array{alerts?: array<int, array<string, mixed>>, type_labels?: array<string, string>} $alert_result
     * @return array<string, mixed>
     */
    private function dataset_from_alert_types(string $id, string $title, array $alert_result): array
    {
        $type_labels = is_array($alert_result['type_labels'] ?? null) ? $alert_result['type_labels'] : [];
        $alerts      = is_array($alert_result['alerts'] ?? null) ? $alert_result['alerts'] : [];
        $counts      = [];

        foreach ($alerts as $alert) {
            $type = (string) ($alert['type'] ?? '');
            if ('' === $type) {
                continue;
            }
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        $pairs = [];
        foreach ($counts as $type => $count) {
            $label         = $type_labels[$type] ?? $type;
            $pairs[$label] = $count;
        }
        arsort($pairs);

        return $this->build_dataset($id, $title, $pairs);
    }

    /**
     * Chart configuration keyed by dataset id.
     *
     * @return array<string, array{type: string, indexAxis?: string, limit?: int}>
     */
    public static function chart_definitions(): array
    {
        return [
            'care_delivery_setting'                  => ['type' => 'doughnut'],
            'occupancy_by_home'                      => ['type' => 'bar'],
            'placement_movements_chart'              => ['type' => 'bar'],
            'referrals_by_month'                     => ['type' => 'line'],
            'referrals_by_service_type'              => ['type' => 'bar'],
            'referrals_by_workflow_stage'            => ['type' => 'doughnut'],
            'referrals_by_priority'                  => ['type' => 'bar'],
            'visits_by_status_detail'                => ['type' => 'doughnut'],
            'visits_status_comparison'               => ['type' => 'doughnut'],
            'visits_by_delivery_context'             => ['type' => 'doughnut'],
            'visits_by_type'                         => ['type' => 'bar'],
            'administrations_by_status'              => ['type' => 'doughnut'],
            'exception_trend_by_month'               => ['type' => 'line'],
            'tasks_by_status'                        => ['type' => 'doughnut'],
            'top_task_exception_types'               => ['type' => 'bar'],
            'visits_completed_per_staff'             => ['type' => 'bar', 'indexAxis' => 'y', 'limit' => 10],
            'medication_administrations_per_staff'   => ['type' => 'bar', 'indexAxis' => 'y', 'limit' => 10],
            'active_care_team_assignments'           => ['type' => 'bar', 'indexAxis' => 'y'],
            'operational_alerts_by_type'             => ['type' => 'bar'],
            'high_priority_referrals'                => ['type' => 'bar'],
        ];
    }

    /**
     * Builds a chart payload for wp_localize_script from report sections.
     *
     * @param array<int, array<string, mixed>> $sections
     * @return array<int, array<string, mixed>>
     */
    public static function charts_for_script(array $sections): array
    {
        $charts = [];

        foreach ($sections as $section) {
            $datasets = is_array($section['datasets'] ?? null) ? $section['datasets'] : [];
            foreach ($datasets as $dataset) {
                if (empty($dataset['chart_enabled'])) {
                    continue;
                }

                $chart = is_array($dataset['chart'] ?? null) ? $dataset['chart'] : [];
                $payload = [
                    'id'        => (string) ($dataset['id'] ?? ''),
                    'title'     => (string) ($dataset['title'] ?? ''),
                    'type'      => (string) ($dataset['chart_type'] ?? 'bar'),
                    'indexAxis' => (string) ($dataset['chart_index_axis'] ?? 'x'),
                    'labels'    => is_array($chart['labels'] ?? null) ? $chart['labels'] : [],
                    'values'    => is_array($chart['values'] ?? null) ? $chart['values'] : [],
                    'max'       => $chart['max'] ?? 0,
                ];

                if (isset($chart['series']) && is_array($chart['series'])) {
                    $payload['series'] = $chart['series'];
                }

                $charts[] = $payload;
            }
        }

        return $charts;
    }

    /**
     * @param array<string, int|float> $pairs label => value
     * @return array<string, mixed>
     */
    private function build_dataset(string $id, string $title, array $pairs): array
    {
        $labels = [];
        $values = [];
        $rows   = [];
        $export = [];
        $max    = 0.0;

        foreach ($pairs as $label => $value) {
            $numeric  = is_numeric($value) ? (float) $value : 0.0;
            $labels[] = (string) $label;
            $values[] = $numeric;
            $rows[]   = [
                'label' => (string) $label,
                'value' => $numeric,
            ];
            $export[] = [(string) $label, $numeric];
            if ($numeric > $max) {
                $max = $numeric;
            }
        }

        $definitions = self::chart_definitions();
        $definition  = $definitions[$id] ?? null;
        $chart_labels = $labels;
        $chart_values = $values;
        $chart_max    = $max;

        if (is_array($definition) && isset($definition['limit'])) {
            $limited = $this->limit_chart_pairs($pairs, (int) $definition['limit']);
            $chart_labels = $limited['labels'];
            $chart_values = $limited['values'];
            $chart_max    = $limited['max'];
        }

        $has_chart_data = false;
        foreach ($chart_values as $chart_value) {
            if ((float) $chart_value > 0) {
                $has_chart_data = true;
                break;
            }
        }

        return [
            'id'               => $id,
            'title'            => $title,
            'note'             => '',
            'rows'             => $rows,
            'chart_enabled'    => null !== $definition,
            'chart_has_data'   => $has_chart_data,
            'chart_type'       => is_array($definition) ? (string) ($definition['type'] ?? 'bar') : '',
            'chart_index_axis' => is_array($definition) ? (string) ($definition['indexAxis'] ?? 'x') : 'x',
            'chart'            => [
                'labels' => $chart_labels,
                'values' => $chart_values,
                'max'    => $chart_max,
            ],
            'export'           => [
                'columns' => [
                    __('Label', 'jm-referral-system'),
                    __('Value', 'jm-referral-system'),
                ],
                'rows'    => $export,
            ],
        ];
    }

    /**
     * @param array<string, int|float> $pairs
     * @return array{labels: array<int, string>, values: array<int, float>, max: float}
     */
    private function limit_chart_pairs(array $pairs, int $limit): array
    {
        $limit = max(1, $limit);
        arsort($pairs, SORT_NUMERIC);
        $pairs = array_slice($pairs, 0, $limit, true);

        $labels = [];
        $values = [];
        $max    = 0.0;

        foreach ($pairs as $label => $value) {
            $numeric    = is_numeric($value) ? (float) $value : 0.0;
            $labels[]   = (string) $label;
            $values[]   = $numeric;
            if ($numeric > $max) {
                $max = $numeric;
            }
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'max'    => $max,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function build_metric_dataset(string $id, string $title, string $label, int|float $value, string $note): array
    {
        $dataset         = $this->build_dataset($id, $title, [$label => $value]);
        $dataset['note'] = $note;

        return $dataset;
    }

    /**
     * @param array<int, array{key: string, label: string, count: int}> $rows
     * @param array<string, string>                                    $labels
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function relabel_rows(array $rows, array $labels): array
    {
        foreach ($rows as $idx => $row) {
            $key = (string) ($row['key'] ?? '');
            if (isset($labels[$key])) {
                $rows[$idx]['label'] = $labels[$key];
            } else {
                $rows[$idx]['label'] = ucwords(str_replace('_', ' ', $key));
            }
        }

        return $rows;
    }

    /**
     * @param array<int, array{key: string, label: string, count: int}> $rows
     * @return array<string, int>
     */
    private function index_counts_by_key(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) ($row['key'] ?? '')] = (int) ($row['count'] ?? 0);
        }

        return $indexed;
    }

    /**
     * @param array<int, int|string> $user_ids
     * @return array<int, string>
     */
    private function resolve_staff_names(array $user_ids): array
    {
        $names = [];
        foreach (array_unique(array_map('absint', $user_ids)) as $user_id) {
            if ($user_id <= 0) {
                continue;
            }
            $name = $this->user_provider->get_display_name($user_id);
            $names[$user_id] = '' !== $name
                ? $name
                : sprintf(
                    /* translators: %d: user ID */
                    __('User #%d', 'jm-referral-system'),
                    $user_id
                );
        }

        return $names;
    }

    /**
     * @return array{start: string, end: string}
     */
    private function week_bounds(DateTimeImmutable $today): array
    {
        $start_of_week = (int) get_option('start_of_week', 1);
        $weekday       = (int) $today->format('w');
        $offset        = ($weekday - $start_of_week + 7) % 7;
        $start         = $today->modify('-' . $offset . ' days');
        $end           = $start->modify('+6 days');

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

    /**
     * Current Supported Living estate + care-delivery snapshot (not date-range dependent).
     *
     * Active care records reuse ReportRepository::count_active_clients() semantics:
     * archived_at IS NULL AND status NOT IN ('completed','cancelled').
     *
     * Estate capacity/occupied/vacant reuse OccupancyRepository::estate_summary()
     * (same source as Homes List / Vacancy Board / Home Dashboard).
     *
     * @return array<string, mixed>
     */
    private function build_supported_living_snapshot(?int $access): array
    {
        $estate      = $this->occupancy_repository->estate_summary();
        $capacity    = (int) ($estate['capacity'] ?? 0);
        $occupied    = (int) ($estate['occupied'] ?? 0);
        $metrics     = OccupancyService::compute_metrics($capacity, $occupied);
        $by_setting  = $this->report_repository->count_active_clients_by_care_setting($access);
        $homes_rows  = $this->occupancy_repository->occupancy_metrics_by_active_homes();

        return [
            'active_homes'           => $this->occupancy_repository->count_active_homes(),
            'capacity'               => $metrics['capacity'],
            'occupied'               => $metrics['occupied'],
            'vacant'                 => $metrics['vacant'],
            'occupancy_percent'      => $metrics['occupancy_pct'],
            'supported_living'       => (int) ($by_setting['supported_living'] ?? 0),
            'awaiting_placement'     => $this->report_repository->count_supported_living_awaiting_placement($access),
            'own_home'               => (int) ($by_setting['own_home'] ?? 0),
            'not_specified'          => (int) ($by_setting['not_specified'] ?? 0),
            'homes'                  => $homes_rows,
            'active_rule'            => 'archived_at IS NULL AND status NOT IN (\'completed\',\'cancelled\')',
            'is_date_range_bound'    => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty_supported_living_snapshot(): array
    {
        return [
            'active_homes'        => 0,
            'capacity'            => 0,
            'occupied'            => 0,
            'vacant'              => 0,
            'occupancy_percent'   => 0.0,
            'supported_living'    => 0,
            'awaiting_placement'  => 0,
            'own_home'            => 0,
            'not_specified'       => 0,
            'homes'               => [],
            'active_rule'         => 'archived_at IS NULL AND status NOT IN (\'completed\',\'cancelled\')',
            'is_date_range_bound' => false,
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private function build_supported_living_section(array $snapshot): array
    {
        $care_options = CareSetting::options();
        $care_pairs   = [
            (string) ($care_options[CareSetting::SUPPORTED_LIVING] ?? __('Supported Living', 'jm-referral-system'))
                => (int) ($snapshot['supported_living'] ?? 0),
            (string) ($care_options[CareSetting::OWN_HOME] ?? __("Client's Own Home", 'jm-referral-system'))
                => (int) ($snapshot['own_home'] ?? 0),
            __('Not Specified', 'jm-referral-system')
                => (int) ($snapshot['not_specified'] ?? 0),
        ];

        $summary = $this->build_dataset(
            'supported_living_summary',
            __('Supported Living Summary', 'jm-referral-system'),
            [
                __('Active Homes', 'jm-referral-system')              => (int) ($snapshot['active_homes'] ?? 0),
                __('Capacity', 'jm-referral-system')                  => (int) ($snapshot['capacity'] ?? 0),
                __('Occupied', 'jm-referral-system')                  => (int) ($snapshot['occupied'] ?? 0),
                __('Vacant', 'jm-referral-system')                    => (int) ($snapshot['vacant'] ?? 0),
                __('Occupancy %', 'jm-referral-system')               => (float) ($snapshot['occupancy_percent'] ?? 0),
                __('Supported Living Clients', 'jm-referral-system')  => (int) ($snapshot['supported_living'] ?? 0),
                __('Awaiting Placement', 'jm-referral-system')        => (int) ($snapshot['awaiting_placement'] ?? 0),
                (string) ($care_options[CareSetting::OWN_HOME] ?? __("Client's Own Home", 'jm-referral-system'))
                    => (int) ($snapshot['own_home'] ?? 0),
                __('Not Specified', 'jm-referral-system')             => (int) ($snapshot['not_specified'] ?? 0),
            ]
        );
        $summary['note'] = __(
            'Current estate and care-delivery position as of today. Not filtered by the report date range.',
            'jm-referral-system'
        );
        $summary['chart_enabled'] = false;
        $summary['ui_hidden']     = true;

        $care_dataset = $this->build_dataset(
            'care_delivery_setting',
            __('Care Delivery Setting', 'jm-referral-system'),
            $care_pairs
        );
        $care_dataset['note'] = __(
            'Active care records only (archived excluded; completed and cancelled excluded).',
            'jm-referral-system'
        );

        return [
            'id'       => self::SECTION_SUPPORTED_LIVING,
            'title'    => __('Supported Living — Current Snapshot', 'jm-referral-system'),
            'note'     => __(
                'Current estate and care-delivery position as of today. These figures are not affected by the selected report date range.',
                'jm-referral-system'
            ),
            'datasets' => [
                $summary,
                $care_dataset,
                $this->build_occupancy_by_home_dataset(
                    is_array($snapshot['homes'] ?? null) ? $snapshot['homes'] : []
                ),
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $homes
     * @return array<string, mixed>
     */
    private function build_occupancy_by_home_dataset(array $homes): array
    {
        $labels          = [];
        $occupied_values = [];
        $vacant_values   = [];
        $table_rows      = [];
        $export_rows     = [];
        $max             = 0.0;

        foreach ($homes as $home) {
            $name     = trim((string) ($home['home_name'] ?? ''));
            if ('' === $name) {
                $name = sprintf(
                    /* translators: %d: home ID */
                    __('Home #%d', 'jm-referral-system'),
                    (int) ($home['home_id'] ?? 0)
                );
            }
            $capacity = (int) ($home['capacity'] ?? 0);
            $occupied = (int) ($home['occupied'] ?? 0);
            $vacant   = max(0, (int) ($home['vacant'] ?? max(0, $capacity - $occupied)));
            $pct      = (float) ($home['occupancy_percent'] ?? ($capacity > 0
                ? round(($occupied / $capacity) * 100, 1)
                : 0.0));

            $labels[]          = $name;
            $occupied_values[] = (float) $occupied;
            $vacant_values[]   = (float) $vacant;
            $max               = max($max, (float) $occupied, (float) $vacant);

            $pct_label = rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.') . '%';
            if ('%' === $pct_label) {
                $pct_label = '0%';
            }

            $table_rows[] = [
                $name,
                $capacity,
                $occupied,
                $vacant,
                $pct_label,
            ];
            $export_rows[] = [$name . ' — ' . __('Capacity', 'jm-referral-system'), $capacity];
            $export_rows[] = [$name . ' — ' . __('Occupied', 'jm-referral-system'), $occupied];
            $export_rows[] = [$name . ' — ' . __('Vacant', 'jm-referral-system'), $vacant];
            $export_rows[] = [$name . ' — ' . __('Occupancy %', 'jm-referral-system'), $pct];
        }

        $definitions = self::chart_definitions();
        $definition  = $definitions['occupancy_by_home'] ?? null;
        $has_data    = [] !== $homes;

        return [
            'id'               => 'occupancy_by_home',
            'title'            => __('Occupancy by Home', 'jm-referral-system'),
            'note'             => __('Active homes only. Capacity counts active bedrooms.', 'jm-referral-system'),
            'rows'             => [],
            'table_columns'    => [
                __('Home', 'jm-referral-system'),
                __('Capacity', 'jm-referral-system'),
                __('Occupied', 'jm-referral-system'),
                __('Vacant', 'jm-referral-system'),
                __('Occupancy %', 'jm-referral-system'),
            ],
            'table_rows'       => $table_rows,
            'empty_message'    => __('No active Supported Living homes have been added.', 'jm-referral-system'),
            'chart_enabled'    => null !== $definition,
            'chart_has_data'   => $has_data,
            'chart_type'       => is_array($definition) ? (string) ($definition['type'] ?? 'bar') : 'bar',
            'chart_index_axis' => is_array($definition) ? (string) ($definition['indexAxis'] ?? 'x') : 'x',
            'chart'            => [
                'labels' => $labels,
                'values' => $occupied_values,
                'max'    => $max,
                'series' => [
                    [
                        'label'  => __('Occupied', 'jm-referral-system'),
                        'values' => $occupied_values,
                    ],
                    [
                        'label'  => __('Vacant', 'jm-referral-system'),
                        'values' => $vacant_values,
                    ],
                ],
            ],
            'export'           => [
                'columns' => [
                    __('Label', 'jm-referral-system'),
                    __('Value', 'jm-referral-system'),
                ],
                'rows'    => $export_rows,
            ],
        ];
    }

    /**
     * Validates Supported Living home filter (active homes only).
     *
     * @param array{home_id?: int|string} $filters
     * @return array{home_id: int, home_name: string, homes_options: array<int, array{id: int, name: string}>, invalid: bool}
     */
    public function resolve_home_filter(array $filters): array
    {
        $options = $this->occupancy_repository->list_active_home_options();
        $raw     = absint($filters['home_id'] ?? 0);

        if ($raw <= 0) {
            return [
                'home_id'       => 0,
                'home_name'     => '',
                'homes_options' => $options,
                'invalid'       => false,
            ];
        }

        $home = $this->occupancy_repository->find_active_home($raw);
        if (null === $home || ($home['id'] ?? 0) <= 0) {
            return [
                'home_id'       => 0,
                'home_name'     => '',
                'homes_options' => $options,
                'invalid'       => true,
            ];
        }

        return [
            'home_id'       => (int) $home['id'],
            'home_name'     => (string) $home['name'],
            'homes_options' => $options,
            'invalid'       => false,
        ];
    }

    /**
     * Visit Care Delivery filters (Phase 2G.4). Separate from vacancy `jmrs_report_home`.
     *
     * @param array{visit_care_context?: string, visit_home_id?: int|string} $filters
     * @return array{
     *     care_context: string,
     *     home_id: int,
     *     home_name: string,
     *     care_context_label: string,
     *     homes_options: array<int, array{id: int, name: string, status: string, is_inactive: bool}>,
     *     care_context_options: array<string, string>,
     *     invalid_home: bool,
     *     is_active: bool,
     *     sql: array{care_context: string, home_id: int, is_active: bool}|null
     * }
     */
    public function resolve_visit_filters(array $filters, string $start_date, string $end_date): array
    {
        $normalized = VisitDeliveryContext::normalize([
            'care_context' => (string) ($filters['visit_care_context'] ?? VisitDeliveryContext::ALL),
            'home_id'      => $filters['visit_home_id'] ?? 0,
        ]);

        $homes_options = $this->report_repository->list_visit_home_filter_options($start_date, $end_date);
        $home_id       = (int) $normalized['home_id'];
        $invalid_home  = false;
        $home_name     = '';

        if ($home_id > 0) {
            $found = false;
            foreach ($homes_options as $option) {
                if ((int) ($option['id'] ?? 0) === $home_id) {
                    $found     = true;
                    $home_name = (string) ($option['name'] ?? '');
                    if (! empty($option['is_inactive'])) {
                        $home_name .= ' ' . __('(Inactive)', 'jm-referral-system');
                    }
                    break;
                }
            }
            if (! $found) {
                $home_id      = 0;
                $invalid_home = true;
            }
        }

        $sql = VisitDeliveryContext::normalize([
            'care_context' => $normalized['care_context'],
            'home_id'      => $home_id,
        ]);

        $labels = VisitDeliveryContext::care_context_labels();

        return [
            'care_context'         => $sql['care_context'],
            'home_id'              => $home_id,
            'home_name'            => $home_name,
            'care_context_label'   => (string) ($labels[$sql['care_context']] ?? $labels[VisitDeliveryContext::ALL]),
            'homes_options'        => $homes_options,
            'care_context_options' => $labels,
            'invalid_home'         => $invalid_home,
            'is_active'            => $sql['is_active'],
            'sql'                  => $sql['is_active'] ? $sql : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty_visit_filters(): array
    {
        $labels = VisitDeliveryContext::care_context_labels();

        return [
            'care_context'         => VisitDeliveryContext::ALL,
            'home_id'              => 0,
            'home_name'            => '',
            'care_context_label'   => $labels[VisitDeliveryContext::ALL],
            'homes_options'        => [],
            'care_context_options' => $labels,
            'invalid_home'         => false,
            'is_active'            => false,
            'sql'                  => null,
        ];
    }

    public function current_user_can_view_vacancy_detail(): bool
    {
        return Capabilities::current_user_can(Capabilities::VIEW_REPORTS)
            && Capabilities::current_user_can(Capabilities::VIEW_HOMES);
    }

    /**
     * @param array<string, mixed>                                                                 $snapshot
     * @param array{home_id: int, home_name: string, homes_options: array<int, array{id: int, name: string}>, invalid: bool} $home
     * @return array<string, mixed>
     */
    private function build_vacancy_report(array $snapshot, array $home): array
    {
        $can_view = $this->current_user_can_view_vacancy_detail();
        $home_id  = (int) ($home['home_id'] ?? 0);
        $metrics  = $this->vacancy_scope_metrics($snapshot, $home_id);

        if (! $can_view) {
            return array_merge(
                $this->empty_vacancy_report($home),
                [
                    'can_view_detail' => false,
                    'metrics'         => $metrics,
                ]
            );
        }

        $rows = $this->report_repository->list_current_vacancies($home_id > 0 ? $home_id : null);
        $active_homes = (int) ($snapshot['active_homes'] ?? 0);

        if ($active_homes <= 0) {
            $empty = __('No active Supported Living homes are available.', 'jm-referral-system');
        } elseif ($home_id > 0) {
            $empty = __('No vacant bedrooms are currently available at this home.', 'jm-referral-system');
        } else {
            $empty = __('No vacant bedrooms are currently available.', 'jm-referral-system');
        }

        $display_rows = [];
        foreach ($rows as $row) {
            $display_rows[] = $this->format_vacancy_row($row);
        }

        return [
            'home_id'         => $home_id,
            'home_name'       => (string) ($home['home_name'] ?? ''),
            'homes_options'   => is_array($home['homes_options'] ?? null) ? $home['homes_options'] : [],
            'home_invalid'    => ! empty($home['invalid']),
            'can_view_detail' => true,
            'metrics'         => $metrics,
            'rows'            => $display_rows,
            'empty_message'   => $empty,
            'is_date_range_bound' => false,
        ];
    }

    /**
     * @param array{home_id: int, home_name: string, homes_options: array<int, array{id: int, name: string}>, invalid?: bool} $home
     * @return array<string, mixed>
     */
    private function empty_vacancy_report(array $home = []): array
    {
        return [
            'home_id'             => (int) ($home['home_id'] ?? 0),
            'home_name'           => (string) ($home['home_name'] ?? ''),
            'homes_options'       => is_array($home['homes_options'] ?? null)
                ? $home['homes_options']
                : $this->occupancy_repository->list_active_home_options(),
            'home_invalid'        => ! empty($home['invalid']),
            'can_view_detail'     => $this->current_user_can_view_vacancy_detail(),
            'metrics'             => [
                'capacity'          => 0,
                'occupied'          => 0,
                'vacant'            => 0,
                'occupancy_percent' => 0.0,
            ],
            'rows'                => [],
            'empty_message'       => __('No active Supported Living homes are available.', 'jm-referral-system'),
            'is_date_range_bound' => false,
        ];
    }

    /**
     * Reuse 2G.1 estate/home metrics — do not recalculate independently.
     *
     * @param array<string, mixed> $snapshot
     * @return array{capacity: int, occupied: int, vacant: int, occupancy_percent: float}
     */
    private function vacancy_scope_metrics(array $snapshot, int $home_id): array
    {
        if ($home_id <= 0) {
            return [
                'capacity'          => (int) ($snapshot['capacity'] ?? 0),
                'occupied'          => (int) ($snapshot['occupied'] ?? 0),
                'vacant'            => (int) ($snapshot['vacant'] ?? 0),
                'occupancy_percent' => (float) ($snapshot['occupancy_percent'] ?? 0),
            ];
        }

        $homes = is_array($snapshot['homes'] ?? null) ? $snapshot['homes'] : [];
        foreach ($homes as $home) {
            if ((int) ($home['home_id'] ?? 0) === $home_id) {
                return [
                    'capacity'          => (int) ($home['capacity'] ?? 0),
                    'occupied'          => (int) ($home['occupied'] ?? 0),
                    'vacant'            => (int) ($home['vacant'] ?? 0),
                    'occupancy_percent' => (float) ($home['occupancy_percent'] ?? 0),
                ];
            }
        }

        return [
            'capacity'          => 0,
            'occupied'          => 0,
            'vacant'            => 0,
            'occupancy_percent' => 0.0,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function format_vacancy_row(array $row): array
    {
        $city     = trim((string) ($row['home_city'] ?? ''));
        $postcode = trim((string) ($row['home_postcode'] ?? ''));
        $location = $this->format_home_location($city, $postcode);
        $has_hist = ! empty($row['has_occupancy_history']);
        $move_out = isset($row['latest_move_out_date']) ? (string) $row['latest_move_out_date'] : '';

        if (! $has_hist) {
            $vacant_since_label = __('Never occupied', 'jm-referral-system');
            $vacant_since_csv   = 'Never occupied';
        } elseif ('' !== $move_out) {
            $vacant_since_label = $this->format_report_date($move_out);
            $vacant_since_csv   = $move_out;
        } else {
            $vacant_since_label = __('—', 'jm-referral-system');
            $vacant_since_csv   = '';
        }

        $status = __('Vacant', 'jm-referral-system');

        return [
            'home_id'              => (int) ($row['home_id'] ?? 0),
            'home_name'            => (string) ($row['home_name'] ?? ''),
            'home_city'            => $city,
            'home_postcode'        => $postcode,
            'location'             => $location,
            'bedroom_id'           => (int) ($row['bedroom_id'] ?? 0),
            'room_label'           => (string) ($row['room_label'] ?? ''),
            'latest_move_out_date' => '' !== $move_out ? $move_out : null,
            'has_occupancy_history'=> $has_hist,
            'vacant_since_label'   => $vacant_since_label,
            'vacant_since_csv'     => $vacant_since_csv,
            'status_label'         => $status,
            'status_csv'           => 'Vacant',
        ];
    }

    private function format_home_location(string $city, string $postcode): string
    {
        $parts = array_values(array_filter([$city, $postcode], static fn (string $v): bool => '' !== $v));

        return implode(' · ', $parts);
    }

    private function format_report_date(string $ymd): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
        if (! $dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $ymd) {
            return $ymd;
        }

        return date_i18n(get_option('date_format'), $dt->getTimestamp());
    }

    /**
     * @param array<string, mixed> $vacancy
     * @return array<string, mixed>
     */
    private function build_vacancy_section(array $vacancy): array
    {
        $metrics = is_array($vacancy['metrics'] ?? null) ? $vacancy['metrics'] : [];
        $rows    = is_array($vacancy['rows'] ?? null) ? $vacancy['rows'] : [];

        $summary = $this->build_dataset(
            'vacancy_summary',
            __('Vacancy Summary', 'jm-referral-system'),
            [
                __('Capacity', 'jm-referral-system')    => (int) ($metrics['capacity'] ?? 0),
                __('Occupied', 'jm-referral-system')    => (int) ($metrics['occupied'] ?? 0),
                __('Vacant', 'jm-referral-system')      => (int) ($metrics['vacant'] ?? 0),
                __('Occupancy %', 'jm-referral-system') => (float) ($metrics['occupancy_percent'] ?? 0),
            ]
        );
        $summary['chart_enabled'] = false;
        $summary['ui_hidden']     = true;
        $summary['note']          = __(
            'Current vacancy snapshot. Not filtered by the report date range. Home filter applies.',
            'jm-referral-system'
        );

        $table_rows  = [];
        $export_rows = [];
        foreach ($rows as $row) {
            $table_rows[] = [
                (string) ($row['home_name'] ?? ''),
                (string) ($row['room_label'] ?? ''),
                (string) ($row['location'] ?? ''),
                (string) ($row['vacant_since_label'] ?? ''),
                (string) ($row['status_label'] ?? __('Vacant', 'jm-referral-system')),
            ];
            $export_rows[] = [
                (string) ($row['home_name'] ?? ''),
                (string) ($row['room_label'] ?? ''),
                (string) ($row['home_city'] ?? ''),
                (string) ($row['home_postcode'] ?? ''),
                (string) ($row['vacant_since_csv'] ?? ''),
                (string) ($row['status_csv'] ?? 'Vacant'),
            ];
        }

        $detail = [
            'id'             => 'vacancy_detail',
            'title'          => __('Current Vacancies', 'jm-referral-system'),
            'note'           => __(
                'Vacant Since is the most recent recorded occupancy end date for the bedroom. Bedrooms with no occupancy history show “Never occupied”.',
                'jm-referral-system'
            ),
            'rows'           => [],
            'table_columns'  => [
                __('Home', 'jm-referral-system'),
                __('Bedroom', 'jm-referral-system'),
                __('Location', 'jm-referral-system'),
                __('Vacant Since', 'jm-referral-system'),
                __('Status', 'jm-referral-system'),
            ],
            'table_rows'     => $table_rows,
            'empty_message'  => (string) ($vacancy['empty_message'] ?? ''),
            'chart_enabled'  => false,
            'chart_has_data' => false,
            'export'         => [
                'format'  => 'tabular',
                'columns' => [
                    'Home',
                    'Bedroom',
                    'City',
                    'Postcode',
                    'Vacant Since',
                    'Status',
                ],
                'rows'    => $export_rows,
            ],
        ];

        return [
            'id'       => self::SECTION_VACANCY,
            'title'    => __('Vacancy Report — Current Snapshot', 'jm-referral-system'),
            'note'     => __(
                'Current vacant bedrooms in active homes. Not affected by the selected report date range.',
                'jm-referral-system'
            ),
            'export_filename_prefix' => 'supported-living-vacancies',
            'datasets' => [
                $summary,
                $detail,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function placement_movement_event_labels(): array
    {
        return [
            ReportRepository::ACTION_PLACEMENT_STARTED     => __('New Placement', 'jm-referral-system'),
            ReportRepository::ACTION_PLACEMENT_TRANSFERRED => __('Transfer', 'jm-referral-system'),
            ReportRepository::ACTION_PLACEMENT_ENDED       => __('Placement Ended', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function build_placement_movements(string $start, string $end, ?int $access): array
    {
        $counts = $this->report_repository->count_placement_movements_in_range($start, $end, $access);
        $started     = (int) ($counts[ReportRepository::ACTION_PLACEMENT_STARTED] ?? 0);
        $transferred = (int) ($counts[ReportRepository::ACTION_PLACEMENT_TRANSFERRED] ?? 0);
        $ended       = (int) ($counts[ReportRepository::ACTION_PLACEMENT_ENDED] ?? 0);

        $raw_rows = $this->report_repository->list_placement_movements_in_range(
            $start,
            $end,
            $access,
            self::PLACEMENT_MOVEMENTS_UI_LIMIT
        );
        $export_rows = $this->report_repository->list_placement_movements_in_range(
            $start,
            $end,
            $access,
            null
        );

        return [
            'start_date'          => $start,
            'end_date'            => $end,
            'new_placements'      => $started,
            'transfers'           => $transferred,
            'placements_ended'    => $ended,
            'total_events'        => $started + $transferred + $ended,
            'rows'                => array_map([$this, 'format_placement_movement_row'], $raw_rows),
            'export_rows'         => array_map([$this, 'format_placement_movement_row'], $export_rows),
            'ui_limited'          => count($export_rows) > count($raw_rows),
            'ui_limit'            => self::PLACEMENT_MOVEMENTS_UI_LIMIT,
            'empty_message'       => __('No placement movements were recorded during this period.', 'jm-referral-system'),
            'is_date_range_bound' => true,
            'home_filter_applied' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty_placement_movements(): array
    {
        return [
            'start_date'          => '',
            'end_date'            => '',
            'new_placements'      => 0,
            'transfers'           => 0,
            'placements_ended'    => 0,
            'total_events'        => 0,
            'rows'                => [],
            'export_rows'         => [],
            'ui_limited'          => false,
            'ui_limit'            => self::PLACEMENT_MOVEMENTS_UI_LIMIT,
            'empty_message'       => __('No placement movements were recorded during this period.', 'jm-referral-system'),
            'is_date_range_bound' => true,
            'home_filter_applied' => false,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function format_placement_movement_row(array $row): array
    {
        $labels = self::placement_movement_event_labels();
        $action = (string) ($row['action'] ?? '');
        $event  = $labels[$action] ?? ucwords(str_replace('_', ' ', $action));

        $client = trim(
            (string) ($row['client_first_name'] ?? '') . ' ' . (string) ($row['client_last_name'] ?? '')
        );
        if ('' === $client) {
            $client = trim((string) ($row['client_name'] ?? ''));
        }
        if ('' === $client) {
            $client = __('—', 'jm-referral-system');
        }

        $created = (string) ($row['created_at'] ?? '');
        $details = trim((string) ($row['description'] ?? ''));
        if ('' === $details) {
            $details = $event;
        }

        return [
            'id'                 => (int) ($row['id'] ?? 0),
            'referral_id'        => (int) ($row['referral_id'] ?? 0),
            'action'             => $action,
            'event_label'        => $event,
            'event_label_csv'    => $this->placement_event_label_csv($action),
            'referral_number'    => (string) ($row['referral_number'] ?? ''),
            'client_name'        => $client,
            'details'            => $details,
            'created_at'         => $created,
            'recorded_date_label'=> $this->format_report_datetime($created),
            'recorded_date_csv'  => $created,
        ];
    }

    private function placement_event_label_csv(string $action): string
    {
        return match ($action) {
            ReportRepository::ACTION_PLACEMENT_STARTED     => 'New Placement',
            ReportRepository::ACTION_PLACEMENT_TRANSFERRED => 'Transfer',
            ReportRepository::ACTION_PLACEMENT_ENDED       => 'Placement Ended',
            default => ucwords(str_replace('_', ' ', $action)),
        };
    }

    private function format_report_datetime(string $mysql_datetime): string
    {
        $mysql_datetime = trim($mysql_datetime);
        if ('' === $mysql_datetime) {
            return '';
        }

        $ts = strtotime($mysql_datetime);
        if (false === $ts) {
            return $mysql_datetime;
        }

        $format = get_option('date_format') . ' ' . get_option('time_format');

        return date_i18n($format, $ts);
    }

    /**
     * @param array<string, mixed> $movements
     * @return array<string, mixed>
     */
    private function build_placement_movements_section(array $movements): array
    {
        $started     = (int) ($movements['new_placements'] ?? 0);
        $transferred = (int) ($movements['transfers'] ?? 0);
        $ended       = (int) ($movements['placements_ended'] ?? 0);
        $total       = (int) ($movements['total_events'] ?? ($started + $transferred + $ended));

        $summary = $this->build_dataset(
            'placement_movements_summary',
            __('Placement Movement Summary', 'jm-referral-system'),
            [
                __('New Placements', 'jm-referral-system')   => $started,
                __('Transfers', 'jm-referral-system')        => $transferred,
                __('Placements Ended', 'jm-referral-system') => $ended,
                __('Total Placement Events', 'jm-referral-system') => $total,
            ]
        );
        $summary['chart_enabled'] = false;
        $summary['ui_hidden']     = true;
        $summary['note']          = __(
            'Movement figures are based on placement events recorded in JMRS during the selected period.',
            'jm-referral-system'
        );

        $chart = $this->build_dataset(
            'placement_movements_chart',
            __('Placement Movements', 'jm-referral-system'),
            [
                __('New Placements', 'jm-referral-system')   => $started,
                __('Transfers', 'jm-referral-system')        => $transferred,
                __('Placements Ended', 'jm-referral-system') => $ended,
            ]
        );
        $chart['note'] = __(
            'Counts use activity.created_at (when the event was recorded), not backdated move-in/out dates.',
            'jm-referral-system'
        );

        $display_rows = is_array($movements['rows'] ?? null) ? $movements['rows'] : [];
        $export_src   = is_array($movements['export_rows'] ?? null) ? $movements['export_rows'] : $display_rows;
        $table_rows   = [];
        $export_rows  = [];

        foreach ($display_rows as $row) {
            $table_rows[] = [
                (string) ($row['recorded_date_label'] ?? ''),
                (string) ($row['event_label'] ?? ''),
                (string) ($row['referral_number'] ?? ''),
                (string) ($row['client_name'] ?? ''),
                (string) ($row['details'] ?? ''),
            ];
        }

        foreach ($export_src as $row) {
            $export_rows[] = [
                (string) ($row['recorded_date_csv'] ?? ''),
                (string) ($row['event_label_csv'] ?? ''),
                (string) ($row['referral_number'] ?? ''),
                (string) ($row['client_name'] ?? ''),
                (string) ($row['details'] ?? ''),
            ];
        }

        $detail = [
            'id'            => 'placement_movements_detail',
            'title'         => __('Placement Movements — Selected Period', 'jm-referral-system'),
            'note'          => __(
                'Transfer events count once. Current occupancy is never used to rewrite historical movement detail.',
                'jm-referral-system'
            ),
            'rows'          => [],
            'table_columns' => [
                __('Recorded Date', 'jm-referral-system'),
                __('Event', 'jm-referral-system'),
                __('Referral Number', 'jm-referral-system'),
                __('Client', 'jm-referral-system'),
                __('Details', 'jm-referral-system'),
            ],
            'table_rows'    => $table_rows,
            'empty_message' => (string) ($movements['empty_message'] ?? ''),
            'chart_enabled' => false,
            'chart_has_data'=> false,
            'export'        => [
                'format'  => 'tabular',
                'columns' => [
                    'Recorded Date',
                    'Event',
                    'Referral Number',
                    'Client',
                    'Details',
                ],
                'rows'    => $export_rows,
            ],
        ];

        return [
            'id'                     => self::SECTION_PLACEMENT_MOVEMENTS,
            'title'                  => __('Placement Movements — Selected Period', 'jm-referral-system'),
            'note'                   => __(
                'Movement figures are based on placement events recorded in JMRS during the selected period.',
                'jm-referral-system'
            ),
            'export_filename_prefix' => 'placement-movements',
            'datasets'               => [
                $summary,
                $chart,
                $detail,
            ],
        ];
    }
}
