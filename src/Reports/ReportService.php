<?php

namespace JMReferral\Reports;

use DateTimeImmutable;
use JMReferral\Alerts\OperationalAlertService;
use JMReferral\Medication\MedicationAdministrationService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
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

    public function __construct(
        private ReportRepository $report_repository,
        private AccessPolicy $access_policy,
        private OperationalAlertService $alert_service,
        private UserProvider $user_provider
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
     *     kpis: array<string, int|float|null>,
     *     sections: array<int, array<string, mixed>>,
     *     errors: array<string, string>
     * }
     */
    public function get_report_data(array $filters = []): array
    {
        $parsed = $this->resolve_date_range($filters);

        if (! empty($parsed['errors'])) {
            return [
                'range'        => (string) ($parsed['range'] ?? self::RANGE_THIS_MONTH),
                'start_date'   => (string) ($parsed['start_date'] ?? ''),
                'end_date'     => (string) ($parsed['end_date'] ?? ''),
                'range_labels' => self::range_labels(),
                'kpis'         => $this->empty_kpis(),
                'sections'     => [],
                'errors'       => $parsed['errors'],
            ];
        }

        $start  = (string) $parsed['start_date'];
        $end    = (string) $parsed['end_date'];
        $access = $this->access_policy->get_assigned_user_constraint();

        $alert_result = $this->alert_service->get_alerts();
        $alert_total  = absint($alert_result['counts']['total'] ?? 0);

        $scheduled = $this->report_repository->count_visits_scheduled_in_range($start, $end, $access);
        $completed = $this->report_repository->count_visits_completed_in_range($start, $end, $access);
        $missed    = $this->report_repository->count_visits_missed_in_range($start, $end, $access);

        $completion_base = $completed + $missed;
        $completion_pct  = $completion_base > 0
            ? round(($completed / $completion_base) * 100, 1)
            : null;

        $avg_duration = $this->report_repository->get_average_visit_duration_minutes($start, $end, $access);
        $avg_turnaround = $this->report_repository->get_average_assessment_turnaround_days($start, $end, $access);

        $task_rows = $this->report_repository->get_visit_tasks_by_status($start, $end, $access);
        $task_counts = $this->index_counts_by_key($task_rows);

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
                'visits_scheduled'           => $scheduled,
                'visits_completed'           => $completed,
                'visits_missed'              => $missed,
                'medication_administrations' => $this->report_repository->count_medication_administrations_in_range($start, $end, $access),
                'medication_exceptions'      => $this->report_repository->count_medication_exceptions_in_range($start, $end, $access),
                'operational_alerts'         => $alert_total,
            ],
            'sections'     => $this->build_analytics_sections(
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
                $alert_result
            ),
            'errors'       => [],
        ];
    }

    /**
     * @return array{referrals_total: int, visits_completed: int, operational_alerts: int, reports_url: string}|null
     */
    public function get_dashboard_summary(): ?array
    {
        if (! $this->current_user_can_view()) {
            return null;
        }

        $range  = $this->resolve_date_range(['range' => self::RANGE_THIS_MONTH]);
        $start  = (string) $range['start_date'];
        $end    = (string) $range['end_date'];
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
        array $alert_result
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

        $visits_completed_staff = $this->report_repository->get_visits_completed_per_staff($start, $end, $access);
        $meds_per_staff         = $this->report_repository->get_medication_administrations_per_staff($start, $end, $access);
        $staff_names            = $this->resolve_staff_names(
            array_merge(
                array_column($visits_completed_staff, 'user_id'),
                array_column($meds_per_staff, 'user_id')
            )
        );

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
                'datasets' => [
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
                        $this->report_repository->get_visits_by_type($start, $end, $access)
                    ),
                    $this->dataset_from_labelled_rows(
                        'visits_by_status_detail',
                        __('Visits by Status', 'jm-referral-system'),
                        $this->relabel_rows(
                            $this->report_repository->get_visits_by_status_in_range($start, $end, $access),
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
                            $this->report_repository->get_visit_tasks_by_status($start, $end, $access),
                            $task_status_labels
                        )
                    ),
                    $this->dataset_from_labelled_rows(
                        'top_task_exception_types',
                        __('Top Task Exception Types', 'jm-referral-system'),
                        $this->report_repository->get_top_task_exception_types($start, $end, $access, 10)
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
                        $this->report_repository->count_outstanding_manager_reviews($access),
                        __('Snapshot of completed visits awaiting manager review.', 'jm-referral-system')
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
            'referrals_by_month'                     => ['type' => 'line'],
            'referrals_by_service_type'              => ['type' => 'bar'],
            'referrals_by_workflow_stage'            => ['type' => 'doughnut'],
            'referrals_by_priority'                  => ['type' => 'bar'],
            'visits_by_status_detail'                => ['type' => 'doughnut'],
            'visits_status_comparison'               => ['type' => 'doughnut'],
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
                $charts[] = [
                    'id'        => (string) ($dataset['id'] ?? ''),
                    'title'     => (string) ($dataset['title'] ?? ''),
                    'type'      => (string) ($dataset['chart_type'] ?? 'bar'),
                    'indexAxis' => (string) ($dataset['chart_index_axis'] ?? 'x'),
                    'labels'    => is_array($chart['labels'] ?? null) ? $chart['labels'] : [],
                    'values'    => is_array($chart['values'] ?? null) ? $chart['values'] : [],
                    'max'       => $chart['max'] ?? 0,
                ];
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
}
