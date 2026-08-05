<?php

namespace JMReferral\Admin\Pages;

use JMReferral\CareTeam\CareTeamService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Referral\ReferralService;
use JMReferral\Scheduling\ScheduleService;
use JMReferral\Users\UserProvider;
use JMReferral\Visits\CareVisitService;
use JMReferral\Visits\VisitExecutionService;
use JMReferral\Visits\VisitTaskService;
use JMReferral\Alerts\OperationalAlertService;
use JMReferral\Medication\MedicationAdministrationService;
use JMReferral\Reports\ReportService;

class DashboardPage
{
    public function __construct(
        private ReferralService $service,
        private CareVisitService $visit_service,
        private UserProvider $user_provider,
        private ReferralRepository $referral_repository,
        private CareTeamService $care_team_service,
        private AccessPolicy $access_policy,
        private ScheduleService $schedule_service,
        private VisitExecutionService $visit_execution_service,
        private VisitTaskService $visit_task_service,
        private OperationalAlertService $alert_service,
        private MedicationAdministrationService $medication_administration_service,
        private ReportService $report_service
    ) {
    }

    public function render(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_DASHBOARD)) {
            wp_die(esc_html__('You do not have permission to view the dashboard.', 'jm-referral-system'));
        }

        global $wpdb;
        $queries_before = (int) $wpdb->num_queries;

        $dashboard          = $this->service->get_dashboard_data(5);
        $stats              = $dashboard['stats'];
        $pipeline           = $dashboard['pipeline'] ?? [];
        $recent             = $dashboard['recent'];
        $scoped_to_assigned = ! empty($dashboard['scoped_to_assigned']);

        $can_view_visits      = Capabilities::current_user_can(Capabilities::VIEW_VISITS);
        $visit_status_labels  = CareVisitService::status_labels();
        $visit_outcome_labels = VisitExecutionService::outcome_labels();
        $upcoming_visits      = [];

        if ($can_view_visits) {
            $upcoming_visits = $this->enrich_dashboard_visits(
                $this->visit_service->get_upcoming_for_dashboard(10),
                $visit_outcome_labels,
                false
            );
        }

        $show_my_active_clients = $this->access_policy->should_scope_to_assigned()
            && Capabilities::current_user_can(Capabilities::VIEW_CARE_TEAM);
        $my_active_clients_count = 0;

        if ($show_my_active_clients) {
            $my_active_clients_count = $this->care_team_service->count_active_clients_for_user(
                get_current_user_id()
            );
        }

        $show_active_schedules  = Capabilities::current_user_can(Capabilities::MANAGE_SCHEDULES);
        $active_schedules_count = 0;

        if ($show_active_schedules) {
            $active_schedules_count = $this->schedule_service->count_active_schedules();
        }

        $show_awaiting_review = Capabilities::current_user_can(Capabilities::MANAGE_VISITS)
            && ! $this->access_policy->should_scope_to_assigned();
        $awaiting_review_visits = [];

        if ($show_awaiting_review) {
            $awaiting_review_visits = $this->enrich_dashboard_visits(
                $this->visit_execution_service->get_awaiting_review_for_dashboard(10),
                $visit_outcome_labels,
                true
            );
        }

        $show_todays_completed = $this->access_policy->should_scope_to_assigned()
            && Capabilities::current_user_can(Capabilities::EXECUTE_VISITS);
        $todays_completed_visits = [];

        if ($show_todays_completed) {
            $todays_completed_visits = $this->enrich_dashboard_visits(
                $this->visit_execution_service->get_todays_completed_for_current_user(10),
                $visit_outcome_labels,
                true
            );
        }

        $show_top_outstanding_tasks = Capabilities::current_user_can(Capabilities::MANAGE_VISITS)
            && ! $this->access_policy->should_scope_to_assigned();
        $top_outstanding_task_types = [];

        if ($show_top_outstanding_tasks) {
            $top_outstanding_task_types = $this->visit_task_service->get_top_outstanding_task_types(10);
        }

        $show_todays_outstanding_tasks = $this->access_policy->should_scope_to_assigned()
            && Capabilities::current_user_can(Capabilities::EXECUTE_VISITS);
        $todays_outstanding_tasks = [];

        if ($show_todays_outstanding_tasks) {
            foreach ($this->visit_task_service->get_todays_outstanding_for_user(get_current_user_id(), 10) as $task_row) {
                $referral_id = absint($task_row['referral_id'] ?? 0);
                $task_row['client_name'] = (string) ($task_row['client_name'] ?? '');
                $task_row['referral_url'] = $referral_id > 0
                    ? add_query_arg(
                        [
                            'page'        => 'jm-referrals-view',
                            'referral_id' => $referral_id,
                        ],
                        admin_url('admin.php')
                    )
                    : '';
                $todays_outstanding_tasks[] = $task_row;
            }
        }

        // Calculate alerts once per dashboard request; reuse counts for reports shortcut.
        $show_operational_alerts = false;
        $operational_alerts      = null;
        $shared_alert_counts     = null;

        if (Capabilities::current_user_can(Capabilities::VIEW_OPERATIONAL_ALERTS)) {
            $alert_result = $this->alert_service->get_alerts();
            $shared_alert_counts = $alert_result['counts'] ?? null;
            $operational_alerts  = $this->alert_service->format_dashboard_alerts($alert_result);
            $show_operational_alerts = is_array($operational_alerts);
        }

        $show_medication_exceptions = Capabilities::current_user_can(Capabilities::MANAGE_VISITS)
            && ! $this->access_policy->should_scope_to_assigned();
        $medication_exceptions_today = 0;
        if ($show_medication_exceptions) {
            $medication_exceptions_today = $this->medication_administration_service->count_exceptions_today_for_managers();
        }

        $show_my_medication_exceptions = $this->access_policy->should_scope_to_assigned()
            && Capabilities::current_user_can(Capabilities::ADMINISTER_MEDICATIONS);
        $my_medication_exceptions_today = 0;
        if ($show_my_medication_exceptions) {
            $my_medication_exceptions_today = $this->medication_administration_service->count_my_exceptions_today();
        }

        $show_reports_shortcut = false;
        $reports_summary       = null;
        if (Capabilities::current_user_can(Capabilities::VIEW_REPORTS)) {
            $reports_summary = $this->report_service->get_dashboard_summary($shared_alert_counts);
            $show_reports_shortcut = is_array($reports_summary);
        }

        $this->maybe_log_query_count('dashboard', $queries_before);

        include JMRS_PLUGIN_PATH . 'templates/dashboard/index.php';
    }

    /**
     * Enriches dashboard visit rows without per-row referral finds.
     * Expects client_name from repository JOIN when available.
     *
     * @param array<int, array<string, mixed>> $visits
     * @param array<string, string>            $outcome_labels
     * @return array<int, array<string, mixed>>
     */
    private function enrich_dashboard_visits(array $visits, array $outcome_labels, bool $include_outcome): array
    {
        $user_ids = [];
        foreach ($visits as $visit_row) {
            $assigned_id = absint($visit_row['assigned_user_id'] ?? 0);
            if ($assigned_id > 0) {
                $user_ids[] = $assigned_id;
            }
        }

        $names = $this->user_provider->get_display_names_by_ids($user_ids);
        $enriched = [];

        foreach ($visits as $visit_row) {
            $assigned_id = absint($visit_row['assigned_user_id'] ?? 0);
            $visit_row['assigned_staff_name'] = $assigned_id > 0
                ? (string) ($names[$assigned_id] ?? '')
                : '';

            $referral_id = absint($visit_row['referral_id'] ?? 0);
            $visit_row['client_name'] = (string) ($visit_row['client_name'] ?? '');
            $visit_row['referral_url'] = $referral_id > 0
                ? add_query_arg(
                    [
                        'page'        => 'jm-referrals-view',
                        'referral_id' => $referral_id,
                    ],
                    admin_url('admin.php')
                )
                : '';

            if ($include_outcome) {
                $outcome_key = (string) ($visit_row['visit_outcome'] ?? '');
                $visit_row['outcome_label'] = isset($outcome_labels[$outcome_key])
                    ? $outcome_labels[$outcome_key]
                    : $outcome_key;
            }

            $enriched[] = $visit_row;
        }

        return $enriched;
    }

    /**
     * Development-only query count log (no SQL, no PHI).
     */
    private function maybe_log_query_count(string $label, int $queries_before): void
    {
        if (! defined('WP_DEBUG') || ! WP_DEBUG) {
            return;
        }

        global $wpdb;
        $delta = (int) $wpdb->num_queries - $queries_before;
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated by WP_DEBUG.
        error_log(sprintf('[JMRS] %s query count: %d', $label, max(0, $delta)));
    }
}
