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
        private MedicationAdministrationService $medication_administration_service
    ) {
    }

    public function render(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_DASHBOARD)) {
            wp_die(esc_html__('You do not have permission to view the dashboard.', 'jm-referral-system'));
        }

        $dashboard          = $this->service->get_dashboard_data(5);
        $stats              = $dashboard['stats'];
        $pipeline           = $dashboard['pipeline'] ?? [];
        $recent             = $dashboard['recent'];
        $scoped_to_assigned = ! empty($dashboard['scoped_to_assigned']);

        $can_view_visits     = Capabilities::current_user_can(Capabilities::VIEW_VISITS);
        $visit_status_labels = CareVisitService::status_labels();
        $visit_outcome_labels = VisitExecutionService::outcome_labels();
        $upcoming_visits     = [];

        if ($can_view_visits) {
            foreach ($this->visit_service->get_upcoming_for_dashboard(10) as $visit_row) {
                $assigned_id = absint($visit_row['assigned_user_id'] ?? 0);
                $visit_row['assigned_staff_name'] = $assigned_id > 0
                    ? $this->user_provider->get_display_name($assigned_id)
                    : '';

                $referral_id = absint($visit_row['referral_id'] ?? 0);
                $referral    = $referral_id > 0 ? $this->referral_repository->find($referral_id) : null;
                $visit_row['client_name'] = is_array($referral)
                    ? (string) ($referral['client_name'] ?? '')
                    : '';

                $upcoming_visits[] = $visit_row;
            }
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
            foreach ($this->visit_execution_service->get_awaiting_review_for_dashboard(10) as $visit_row) {
                $awaiting_review_visits[] = $this->enrich_dashboard_visit($visit_row, $visit_outcome_labels);
            }
        }

        $show_todays_completed = $this->access_policy->should_scope_to_assigned()
            && Capabilities::current_user_can(Capabilities::EXECUTE_VISITS);
        $todays_completed_visits = [];

        if ($show_todays_completed) {
            foreach ($this->visit_execution_service->get_todays_completed_for_current_user(10) as $visit_row) {
                $todays_completed_visits[] = $this->enrich_dashboard_visit($visit_row, $visit_outcome_labels);
            }
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
                $referral    = $referral_id > 0 ? $this->referral_repository->find($referral_id) : null;
                $task_row['client_name'] = is_array($referral)
                    ? (string) ($referral['client_name'] ?? '')
                    : '';
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

        $show_operational_alerts = false;
        $operational_alerts      = null;

        if (Capabilities::current_user_can(Capabilities::VIEW_OPERATIONAL_ALERTS)) {
            $operational_alerts = $this->alert_service->get_dashboard_alerts();
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

        include JMRS_PLUGIN_PATH . 'templates/dashboard/index.php';
    }

    /**
     * @param array<string, mixed>  $visit_row
     * @param array<string, string> $outcome_labels
     * @return array<string, mixed>
     */
    private function enrich_dashboard_visit(array $visit_row, array $outcome_labels): array
    {
        $assigned_id = absint($visit_row['assigned_user_id'] ?? 0);
        $visit_row['assigned_staff_name'] = $assigned_id > 0
            ? $this->user_provider->get_display_name($assigned_id)
            : '';

        $referral_id = absint($visit_row['referral_id'] ?? 0);
        $referral    = $referral_id > 0 ? $this->referral_repository->find($referral_id) : null;
        $visit_row['client_name'] = is_array($referral)
            ? (string) ($referral['client_name'] ?? '')
            : '';
        $visit_row['referral_url'] = $referral_id > 0
            ? add_query_arg(
                [
                    'page'        => 'jm-referrals-view',
                    'referral_id' => $referral_id,
                ],
                admin_url('admin.php')
            )
            : '';

        $outcome_key = (string) ($visit_row['visit_outcome'] ?? '');
        $visit_row['outcome_label'] = isset($outcome_labels[$outcome_key])
            ? $outcome_labels[$outcome_key]
            : $outcome_key;

        return $visit_row;
    }
}
