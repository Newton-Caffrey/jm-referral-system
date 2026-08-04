<?php

namespace JMReferral\Alerts;

use DateTimeImmutable;
use JMReferral\Assessment\ReferralAssessmentRepository;
use JMReferral\CarePlan\ReferralCarePlanRepository;
use JMReferral\CarePlan\ReferralCarePlanReviewRepository;
use JMReferral\CareTeam\CareTeamRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Referral\ReferralViewController;
use JMReferral\Scheduling\ScheduleController;
use JMReferral\Scheduling\ScheduleRepository;
use JMReferral\Visits\CareVisitRepository;
use JMReferral\Visits\VisitTaskRepository;
use JMReferral\Medication\MedicationAdministrationRepository;
use JMReferral\Medication\MedicationAdministrationService;

class OperationalAlertService
{
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_INFORMATION = 'information';

    public const TYPE_HIGH_PRIORITY_UNASSIGNED = 'high_priority_unassigned';
    public const TYPE_AWAITING_ASSESSMENT = 'referral_awaiting_assessment';
    public const TYPE_NO_ACTIVE_CARE_PLAN = 'no_active_care_plan';
    public const TYPE_CARE_PLAN_REVIEW_OVERDUE = 'care_plan_review_overdue';
    public const TYPE_NO_CARE_TEAM = 'active_client_no_care_team';
    public const TYPE_SCHEDULE_NO_TEAM = 'schedule_no_active_team';
    public const TYPE_HIGH_PRIORITY_NO_VISIT = 'high_priority_no_upcoming_visit';
    public const TYPE_VISIT_OVERDUE = 'visit_overdue';
    public const TYPE_VISIT_AWAITING_REVIEW = 'visit_awaiting_review';
    public const TYPE_TASK_EXCEPTIONS = 'visit_task_exceptions';
    public const TYPE_MEDICATION_EXCEPTION = 'medication_administration_exception';

    private const QUERY_LIMIT = 200;

    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralAssessmentRepository $assessment_repository,
        private ReferralCarePlanRepository $care_plan_repository,
        private ReferralCarePlanReviewRepository $care_plan_review_repository,
        private CareTeamRepository $care_team_repository,
        private ScheduleRepository $schedule_repository,
        private CareVisitRepository $visit_repository,
        private VisitTaskRepository $visit_task_repository,
        private AccessPolicy $access_policy,
        private ?MedicationAdministrationRepository $medication_administration_repository = null
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function severity_labels(): array
    {
        return [
            self::SEVERITY_CRITICAL    => __('Critical', 'jm-referral-system'),
            self::SEVERITY_WARNING     => __('Warning', 'jm-referral-system'),
            self::SEVERITY_INFORMATION => __('Information', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function type_labels(): array
    {
        return [
            self::TYPE_HIGH_PRIORITY_UNASSIGNED => __('High-priority referral unassigned', 'jm-referral-system'),
            self::TYPE_AWAITING_ASSESSMENT      => __('Referral awaiting assessment', 'jm-referral-system'),
            self::TYPE_NO_ACTIVE_CARE_PLAN      => __('No active care plan', 'jm-referral-system'),
            self::TYPE_CARE_PLAN_REVIEW_OVERDUE => __('Care plan review overdue', 'jm-referral-system'),
            self::TYPE_NO_CARE_TEAM             => __('Active client has no care team', 'jm-referral-system'),
            self::TYPE_SCHEDULE_NO_TEAM         => __('Schedule has no active care-team member', 'jm-referral-system'),
            self::TYPE_HIGH_PRIORITY_NO_VISIT   => __('High-priority referral has no upcoming visit', 'jm-referral-system'),
            self::TYPE_VISIT_OVERDUE            => __('Care visit overdue', 'jm-referral-system'),
            self::TYPE_VISIT_AWAITING_REVIEW    => __('Completed visit awaiting review', 'jm-referral-system'),
            self::TYPE_TASK_EXCEPTIONS          => __('Visit has care-task exceptions', 'jm-referral-system'),
            self::TYPE_MEDICATION_EXCEPTION     => __('Medication administration exception', 'jm-referral-system'),
        ];
    }

    public function current_user_can_view(): bool
    {
        return Capabilities::current_user_can(Capabilities::VIEW_OPERATIONAL_ALERTS);
    }

    /**
     * @param array{severity?: string, type?: string, search?: string} $filters
     * @return array{
     *   alerts: array<int, array<string, mixed>>,
     *   grouped: array{critical: array<int, array<string, mixed>>, warning: array<int, array<string, mixed>>, information: array<int, array<string, mixed>>},
     *   counts: array{critical: int, warning: int, information: int, total: int},
     *   type_labels: array<string, string>,
     *   severity_labels: array<string, string>
     * }
     */
    public function get_alerts(array $filters = []): array
    {
        if (! $this->current_user_can_view()) {
            return $this->empty_result();
        }

        $alerts = $this->calculate_alerts();
        $alerts = $this->apply_filters($alerts, $filters);
        $alerts = $this->sort_alerts($alerts);

        return [
            'alerts'          => $alerts,
            'grouped'         => $this->group_by_severity($alerts),
            'counts'          => $this->count_by_severity($alerts),
            'type_labels'     => self::type_labels(),
            'severity_labels' => self::severity_labels(),
        ];
    }

    /**
     * @return array{
     *   alerts: array<int, array<string, mixed>>,
     *   grouped: array{critical: array<int, array<string, mixed>>, warning: array<int, array<string, mixed>>, information: array<int, array<string, mixed>>},
     *   counts: array{critical: int, warning: int, information: int, total: int},
     *   view_all_url: string
     * }|null
     */
    public function get_dashboard_alerts(): ?array
    {
        if (! $this->current_user_can_view()) {
            return null;
        }

        $result  = $this->get_alerts();
        $grouped = $result['grouped'];

        $grouped['critical']    = array_slice($grouped['critical'], 0, 10);
        $grouped['warning']     = array_slice($grouped['warning'], 0, 10);
        $grouped['information'] = array_slice($grouped['information'], 0, 5);

        return [
            'alerts'       => array_merge(
                $grouped['critical'],
                $grouped['warning'],
                $grouped['information']
            ),
            'grouped'      => $grouped,
            'counts'       => $result['counts'],
            'view_all_url' => self::get_alerts_page_url(),
        ];
    }

    public static function get_alerts_page_url(array $args = []): string
    {
        return add_query_arg(
            array_merge(
                ['page' => 'jm-referrals-operational-alerts'],
                $args
            ),
            admin_url('admin.php')
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function calculate_alerts(): array
    {
        $access = $this->access_policy->get_assigned_user_constraint();
        $today  = current_time('Y-m-d');
        $now    = current_time('H:i:s');
        $cutoff = $this->days_ago_mysql(2);
        $alerts = [];

        foreach ($this->referral_repository->find_unassigned_high_priority(self::QUERY_LIMIT, $access) as $row) {
            if (! $this->can_view_joined_referral($row)) {
                continue;
            }

            $priority    = (string) ($row['priority'] ?? '');
            $severity    = 'urgent' === $priority ? self::SEVERITY_CRITICAL : self::SEVERITY_WARNING;
            $client      = (string) ($row['client_name'] ?? '');
            $number      = (string) ($row['referral_number'] ?? '');
            $referral_id = absint($row['id'] ?? 0);

            $alerts[] = $this->make_alert([
                'type'                 => self::TYPE_HIGH_PRIORITY_UNASSIGNED,
                'severity'             => $severity,
                'title'                => __('High-priority referral unassigned', 'jm-referral-system'),
                'description'          => sprintf(
                    /* translators: 1: priority, 2: client name, 3: referral number */
                    __('The %1$s priority referral for %2$s (%3$s) has no assignee.', 'jm-referral-system'),
                    $priority,
                    $client,
                    $number
                ),
                'entity_type'          => 'referral',
                'entity_id'            => $referral_id,
                'referral_id'          => $referral_id,
                'occurred_or_due_date' => (string) ($row['created_at'] ?? ''),
                'action_url'           => ReferralViewController::get_view_url($referral_id),
                'action_label'         => __('View Referral', 'jm-referral-system'),
                'client_name'          => $client,
                'referral_number'      => $number,
            ]);
        }

        foreach ($this->referral_repository->find_without_assessment($cutoff, self::QUERY_LIMIT, $access) as $row) {
            if (! $this->can_view_joined_referral($row)) {
                continue;
            }

            $client      = (string) ($row['client_name'] ?? '');
            $number      = (string) ($row['referral_number'] ?? '');
            $referral_id = absint($row['id'] ?? 0);

            $alerts[] = $this->make_alert([
                'type'                 => self::TYPE_AWAITING_ASSESSMENT,
                'severity'             => self::SEVERITY_WARNING,
                'title'                => __('Referral awaiting assessment', 'jm-referral-system'),
                'description'          => sprintf(
                    /* translators: 1: client name, 2: referral number */
                    __('Referral for %1$s (%2$s) is more than 2 days old and has no assessment.', 'jm-referral-system'),
                    $client,
                    $number
                ),
                'entity_type'          => 'referral',
                'entity_id'            => $referral_id,
                'referral_id'          => $referral_id,
                'occurred_or_due_date' => (string) ($row['created_at'] ?? ''),
                'action_url'           => ReferralViewController::get_view_url($referral_id),
                'action_label'         => __('View Referral', 'jm-referral-system'),
                'client_name'          => $client,
                'referral_number'      => $number,
            ]);
        }

        foreach ($this->referral_repository->find_without_active_care_plan(self::QUERY_LIMIT, $access) as $row) {
            if (! $this->can_view_joined_referral($row)) {
                continue;
            }

            $client      = (string) ($row['client_name'] ?? '');
            $number      = (string) ($row['referral_number'] ?? '');
            $referral_id = absint($row['id'] ?? 0);

            $alerts[] = $this->make_alert([
                'type'                 => self::TYPE_NO_ACTIVE_CARE_PLAN,
                'severity'             => self::SEVERITY_WARNING,
                'title'                => __('No active care plan', 'jm-referral-system'),
                'description'          => sprintf(
                    /* translators: 1: client name, 2: referral number */
                    __('Referral for %1$s (%2$s) has an assessment but no active care plan.', 'jm-referral-system'),
                    $client,
                    $number
                ),
                'entity_type'          => 'referral',
                'entity_id'            => $referral_id,
                'referral_id'          => $referral_id,
                'occurred_or_due_date' => (string) ($row['created_at'] ?? ''),
                'action_url'           => ReferralViewController::get_view_url($referral_id),
                'action_label'         => __('View Referral', 'jm-referral-system'),
                'client_name'          => $client,
                'referral_number'      => $number,
            ]);
        }

        foreach ($this->care_plan_repository->find_overdue_care_plan_reviews($today, self::QUERY_LIMIT, $access) as $row) {
            if (! $this->can_view_joined_referral($row)) {
                continue;
            }

            $client      = (string) ($row['client_name'] ?? '');
            $number      = (string) ($row['referral_number'] ?? '');
            $referral_id = absint($row['referral_id'] ?? 0);
            $plan_id     = absint($row['id'] ?? 0);

            $alerts[] = $this->make_alert([
                'type'                 => self::TYPE_CARE_PLAN_REVIEW_OVERDUE,
                'severity'             => self::SEVERITY_WARNING,
                'title'                => __('Care plan review overdue', 'jm-referral-system'),
                'description'          => sprintf(
                    /* translators: 1: client name, 2: referral number, 3: review date */
                    __('The active care plan for %1$s (%2$s) was due for review on %3$s.', 'jm-referral-system'),
                    $client,
                    $number,
                    (string) ($row['review_date'] ?? '')
                ),
                'entity_type'          => 'care_plan',
                'entity_id'            => $plan_id,
                'referral_id'          => $referral_id,
                'occurred_or_due_date' => (string) ($row['review_date'] ?? ''),
                'action_url'           => ReferralViewController::get_view_url($referral_id),
                'action_label'         => __('View Care Plan', 'jm-referral-system'),
                'client_name'          => $client,
                'referral_number'      => $number,
            ]);
        }

        foreach ($this->care_plan_repository->find_active_without_care_team(self::QUERY_LIMIT, $access) as $row) {
            if (! $this->can_view_joined_referral($row)) {
                continue;
            }

            $client      = (string) ($row['client_name'] ?? '');
            $number      = (string) ($row['referral_number'] ?? '');
            $referral_id = absint($row['referral_id'] ?? 0);
            $plan_id     = absint($row['id'] ?? 0);

            $alerts[] = $this->make_alert([
                'type'                 => self::TYPE_NO_CARE_TEAM,
                'severity'             => self::SEVERITY_WARNING,
                'title'                => __('Active client has no care team', 'jm-referral-system'),
                'description'          => sprintf(
                    /* translators: 1: client name, 2: referral number */
                    __('%1$s (%2$s) has an active care plan but no active care-team member.', 'jm-referral-system'),
                    $client,
                    $number
                ),
                'entity_type'          => 'care_plan',
                'entity_id'            => $plan_id,
                'referral_id'          => $referral_id,
                'occurred_or_due_date' => '',
                'action_url'           => ReferralViewController::get_view_url($referral_id),
                'action_label'         => __('View Care Team', 'jm-referral-system'),
                'client_name'          => $client,
                'referral_number'      => $number,
            ]);
        }

        foreach ($this->schedule_repository->find_active_schedules_without_team(self::QUERY_LIMIT, $access) as $row) {
            if (! $this->can_view_joined_referral($row)) {
                continue;
            }

            $client      = (string) ($row['client_name'] ?? '');
            $number      = (string) ($row['referral_number'] ?? '');
            $schedule    = (string) ($row['schedule_name'] ?? '');
            $referral_id = absint($row['referral_id'] ?? 0);
            $schedule_id = absint($row['id'] ?? 0);

            $alerts[] = $this->make_alert([
                'type'                 => self::TYPE_SCHEDULE_NO_TEAM,
                'severity'             => self::SEVERITY_WARNING,
                'title'                => __('Schedule has no active care-team member', 'jm-referral-system'),
                'description'          => sprintf(
                    /* translators: 1: schedule name, 2: client name, 3: referral number */
                    __('Schedule "%1$s" for %2$s (%3$s) has no valid active care-team assignment.', 'jm-referral-system'),
                    $schedule,
                    $client,
                    $number
                ),
                'entity_type'          => 'schedule',
                'entity_id'            => $schedule_id,
                'referral_id'          => $referral_id,
                'occurred_or_due_date' => (string) ($row['start_date'] ?? ''),
                'action_url'           => ScheduleController::get_edit_url($schedule_id),
                'action_label'         => __('View Schedule', 'jm-referral-system'),
                'client_name'          => $client,
                'referral_number'      => $number,
            ]);
        }

        foreach ($this->referral_repository->find_high_priority_without_upcoming_visits($today, self::QUERY_LIMIT, $access) as $row) {
            if (! $this->can_view_joined_referral($row)) {
                continue;
            }

            $client      = (string) ($row['client_name'] ?? '');
            $number      = (string) ($row['referral_number'] ?? '');
            $priority    = (string) ($row['priority'] ?? '');
            $referral_id = absint($row['id'] ?? 0);

            $alerts[] = $this->make_alert([
                'type'                 => self::TYPE_HIGH_PRIORITY_NO_VISIT,
                'severity'             => self::SEVERITY_CRITICAL,
                'title'                => __('High-priority referral has no upcoming visit', 'jm-referral-system'),
                'description'          => sprintf(
                    /* translators: 1: priority, 2: client name, 3: referral number */
                    __('The %1$s priority referral for %2$s (%3$s) has no upcoming visit.', 'jm-referral-system'),
                    $priority,
                    $client,
                    $number
                ),
                'entity_type'          => 'referral',
                'entity_id'            => $referral_id,
                'referral_id'          => $referral_id,
                'occurred_or_due_date' => (string) ($row['created_at'] ?? ''),
                'action_url'           => ReferralViewController::get_view_url($referral_id),
                'action_label'         => __('View Referral', 'jm-referral-system'),
                'client_name'          => $client,
                'referral_number'      => $number,
            ]);
        }

        foreach ($this->visit_repository->find_overdue_visits($today, $now, self::QUERY_LIMIT, $access) as $row) {
            if (! $this->can_view_joined_referral($row)) {
                continue;
            }

            $client      = (string) ($row['client_name'] ?? '');
            $number      = (string) ($row['referral_number'] ?? '');
            $start       = $this->format_time_short((string) ($row['start_time'] ?? ''));
            $referral_id = absint($row['referral_id'] ?? 0);
            $visit_id    = absint($row['id'] ?? 0);
            $due         = trim((string) ($row['visit_date'] ?? '') . ' ' . (string) ($row['start_time'] ?? ''));

            $alerts[] = $this->make_alert([
                'type'                 => self::TYPE_VISIT_OVERDUE,
                'severity'             => self::SEVERITY_CRITICAL,
                'title'                => __('Care visit overdue', 'jm-referral-system'),
                'description'          => sprintf(
                    /* translators: 1: start time, 2: client name */
                    __('The %1$s visit for %2$s has not been completed.', 'jm-referral-system'),
                    '' !== $start ? $start : __('scheduled', 'jm-referral-system'),
                    $client
                ),
                'entity_type'          => 'visit',
                'entity_id'            => $visit_id,
                'referral_id'          => $referral_id,
                'occurred_or_due_date' => $due,
                'action_url'           => ReferralViewController::get_view_url($referral_id),
                'action_label'         => __('View Visit', 'jm-referral-system'),
                'client_name'          => $client,
                'referral_number'      => $number,
            ]);
        }

        foreach ($this->visit_repository->find_visits_awaiting_review(self::QUERY_LIMIT, $access) as $row) {
            if (! $this->can_view_joined_referral($row)) {
                continue;
            }

            $client      = (string) ($row['client_name'] ?? '');
            $number      = (string) ($row['referral_number'] ?? '');
            $referral_id = absint($row['referral_id'] ?? 0);
            $visit_id    = absint($row['id'] ?? 0);

            $alerts[] = $this->make_alert([
                'type'                 => self::TYPE_VISIT_AWAITING_REVIEW,
                'severity'             => self::SEVERITY_WARNING,
                'title'                => __('Completed visit awaiting review', 'jm-referral-system'),
                'description'          => sprintf(
                    /* translators: 1: client name, 2: referral number */
                    __('A completed visit for %1$s (%2$s) is awaiting manager review.', 'jm-referral-system'),
                    $client,
                    $number
                ),
                'entity_type'          => 'visit',
                'entity_id'            => $visit_id,
                'referral_id'          => $referral_id,
                'occurred_or_due_date' => (string) ($row['completed_at'] ?? $row['visit_date'] ?? ''),
                'action_url'           => ReferralViewController::get_view_url($referral_id),
                'action_label'         => __('Review Visit', 'jm-referral-system'),
                'client_name'          => $client,
                'referral_number'      => $number,
            ]);
        }

        foreach ($this->visit_task_repository->find_visits_with_task_exceptions(self::QUERY_LIMIT, $access) as $row) {
            if (! $this->can_view_joined_referral($row)) {
                continue;
            }

            $client      = (string) ($row['client_name'] ?? '');
            $number      = (string) ($row['referral_number'] ?? '');
            $count       = absint($row['exception_count'] ?? 0);
            $referral_id = absint($row['referral_id'] ?? 0);
            $visit_id    = absint($row['visit_id'] ?? 0);

            $alerts[] = $this->make_alert([
                'type'                 => self::TYPE_TASK_EXCEPTIONS,
                'severity'             => self::SEVERITY_WARNING,
                'title'                => __('Visit has care-task exceptions', 'jm-referral-system'),
                'description'          => sprintf(
                    /* translators: 1: exception count, 2: client name, 3: referral number */
                    _n(
                        '%1$d refused or incomplete task was recorded for %2$s (%3$s).',
                        '%1$d refused or incomplete tasks were recorded for %2$s (%3$s).',
                        $count,
                        'jm-referral-system'
                    ),
                    $count,
                    $client,
                    $number
                ),
                'entity_type'          => 'visit',
                'entity_id'            => $visit_id,
                'referral_id'          => $referral_id,
                'occurred_or_due_date' => trim((string) ($row['visit_date'] ?? '') . ' ' . (string) ($row['start_time'] ?? '')),
                'action_url'           => ReferralViewController::get_view_url($referral_id),
                'action_label'         => __('View Visit', 'jm-referral-system'),
                'client_name'          => $client,
                'referral_number'      => $number,
            ]);
        }

        if ($this->medication_administration_repository instanceof MedicationAdministrationRepository) {
            foreach ($this->medication_administration_repository->get_exceptions_for_date($today, self::QUERY_LIMIT, $access) as $row) {
                if (! $this->can_view_joined_referral($row)) {
                    continue;
                }

                $status      = (string) ($row['administration_status'] ?? '');
                $severity    = MedicationAdministrationService::STATUS_ERROR === $status
                    ? self::SEVERITY_CRITICAL
                    : self::SEVERITY_WARNING;
                $client      = (string) ($row['client_name'] ?? '');
                $number      = (string) ($row['referral_number'] ?? '');
                $med_name    = (string) ($row['medication_name'] ?? '');
                $status_label = MedicationAdministrationService::status_labels()[$status] ?? $status;
                $referral_id = absint($row['referral_id'] ?? 0);
                $visit_id    = absint($row['visit_id'] ?? 0);
                $due         = (string) ($row['administered_time'] ?? $row['created_at'] ?? '');

                $alerts[] = $this->make_alert([
                    'type'                 => self::TYPE_MEDICATION_EXCEPTION,
                    'severity'             => $severity,
                    'title'                => __('Medication administration exception', 'jm-referral-system'),
                    'description'          => sprintf(
                        /* translators: 1: medication name, 2: status, 3: client name, 4: referral number */
                        __('Medication %1$s was recorded as %2$s for %3$s (%4$s).', 'jm-referral-system'),
                        $med_name,
                        $status_label,
                        $client,
                        $number
                    ),
                    'entity_type'          => 'visit',
                    'entity_id'            => $visit_id,
                    'referral_id'          => $referral_id,
                    'occurred_or_due_date' => $due,
                    'action_url'           => ReferralViewController::get_view_url($referral_id),
                    'action_label'         => __('View Visit', 'jm-referral-system'),
                    'client_name'          => $client,
                    'referral_number'      => $number,
                ]);
            }
        }

        return $alerts;
    }

    /**
     * @param array<string, mixed> $row Joined row including assigned_to from referrals.
     */
    private function can_view_joined_referral(array $row): bool
    {
        return $this->access_policy->can_view_referral([
            'id'          => absint($row['referral_id'] ?? $row['id'] ?? 0),
            'assigned_to' => absint($row['assigned_to'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function make_alert(array $data): array
    {
        $due = trim((string) ($data['occurred_or_due_date'] ?? ''));
        $referral_id = absint($data['referral_id'] ?? 0);

        return [
            'type'                 => (string) ($data['type'] ?? ''),
            'severity'             => (string) ($data['severity'] ?? self::SEVERITY_INFORMATION),
            'title'                => (string) ($data['title'] ?? ''),
            'description'          => (string) ($data['description'] ?? ''),
            'entity_type'          => (string) ($data['entity_type'] ?? ''),
            'entity_id'            => absint($data['entity_id'] ?? 0),
            'referral_id'          => $referral_id > 0 ? $referral_id : null,
            'occurred_or_due_date' => '' !== $due ? $due : null,
            'action_url'           => (string) ($data['action_url'] ?? ''),
            'action_label'         => (string) ($data['action_label'] ?? __('View', 'jm-referral-system')),
            'client_name'          => (string) ($data['client_name'] ?? ''),
            'referral_number'      => (string) ($data['referral_number'] ?? ''),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     * @param array{severity?: string, type?: string, search?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    private function apply_filters(array $alerts, array $filters): array
    {
        $severity = sanitize_key((string) ($filters['severity'] ?? ''));
        $type     = sanitize_key((string) ($filters['type'] ?? ''));
        $search   = strtolower(trim((string) ($filters['search'] ?? '')));

        return array_values(
            array_filter(
                $alerts,
                static function (array $alert) use ($severity, $type, $search): bool {
                    if ('' !== $severity && (string) ($alert['severity'] ?? '') !== $severity) {
                        return false;
                    }

                    if ('' !== $type && (string) ($alert['type'] ?? '') !== $type) {
                        return false;
                    }

                    if ('' !== $search) {
                        $haystack = strtolower(
                            (string) ($alert['client_name'] ?? '') . ' ' .
                            (string) ($alert['referral_number'] ?? '') . ' ' .
                            (string) ($alert['title'] ?? '') . ' ' .
                            (string) ($alert['description'] ?? '')
                        );

                        if (! str_contains($haystack, $search)) {
                            return false;
                        }
                    }

                    return true;
                }
            )
        );
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     * @return array<int, array<string, mixed>>
     */
    private function sort_alerts(array $alerts): array
    {
        $rank = [
            self::SEVERITY_CRITICAL    => 0,
            self::SEVERITY_WARNING     => 1,
            self::SEVERITY_INFORMATION => 2,
        ];

        usort(
            $alerts,
            static function (array $a, array $b) use ($rank): int {
                $sa = $rank[(string) ($a['severity'] ?? '')] ?? 9;
                $sb = $rank[(string) ($b['severity'] ?? '')] ?? 9;
                if ($sa !== $sb) {
                    return $sa <=> $sb;
                }

                $da = (string) ($a['occurred_or_due_date'] ?? '');
                $db = (string) ($b['occurred_or_due_date'] ?? '');
                if ('' === $da && '' !== $db) {
                    return 1;
                }
                if ('' !== $da && '' === $db) {
                    return -1;
                }
                if ($da !== $db) {
                    return $da <=> $db;
                }

                return absint($a['entity_id'] ?? 0) <=> absint($b['entity_id'] ?? 0);
            }
        );

        return $alerts;
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     * @return array{critical: array<int, array<string, mixed>>, warning: array<int, array<string, mixed>>, information: array<int, array<string, mixed>>}
     */
    private function group_by_severity(array $alerts): array
    {
        $grouped = [
            self::SEVERITY_CRITICAL    => [],
            self::SEVERITY_WARNING     => [],
            self::SEVERITY_INFORMATION => [],
        ];

        foreach ($alerts as $alert) {
            $severity = (string) ($alert['severity'] ?? '');
            if (isset($grouped[$severity])) {
                $grouped[$severity][] = $alert;
            }
        }

        return $grouped;
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     * @return array{critical: int, warning: int, information: int, total: int}
     */
    private function count_by_severity(array $alerts): array
    {
        $counts = [
            self::SEVERITY_CRITICAL    => 0,
            self::SEVERITY_WARNING     => 0,
            self::SEVERITY_INFORMATION => 0,
            'total'                    => 0,
        ];

        foreach ($alerts as $alert) {
            $severity = (string) ($alert['severity'] ?? '');
            if (isset($counts[$severity])) {
                ++$counts[$severity];
            }
            ++$counts['total'];
        }

        return $counts;
    }

    /**
     * @return array{
     *   alerts: array<int, array<string, mixed>>,
     *   grouped: array{critical: array<int, array<string, mixed>>, warning: array<int, array<string, mixed>>, information: array<int, array<string, mixed>>},
     *   counts: array{critical: int, warning: int, information: int, total: int},
     *   type_labels: array<string, string>,
     *   severity_labels: array<string, string>
     * }
     */
    private function empty_result(): array
    {
        return [
            'alerts'          => [],
            'grouped'         => [
                self::SEVERITY_CRITICAL    => [],
                self::SEVERITY_WARNING     => [],
                self::SEVERITY_INFORMATION => [],
            ],
            'counts'          => [
                self::SEVERITY_CRITICAL    => 0,
                self::SEVERITY_WARNING     => 0,
                self::SEVERITY_INFORMATION => 0,
                'total'                    => 0,
            ],
            'type_labels'     => self::type_labels(),
            'severity_labels' => self::severity_labels(),
        ];
    }

    private function days_ago_mysql(int $days): string
    {
        $now = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', current_time('mysql'));
        if (false === $now) {
            return gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        }

        return $now->modify('-' . $days . ' days')->format('Y-m-d H:i:s');
    }

    private function format_time_short(string $time): string
    {
        $time = trim($time);
        if ('' === $time) {
            return '';
        }

        if (preg_match('/^(\d{2}:\d{2})/', $time, $matches) === 1) {
            return $matches[1];
        }

        return $time;
    }
}
