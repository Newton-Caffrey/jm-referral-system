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

class DashboardPage
{
    public function __construct(
        private ReferralService $service,
        private CareVisitService $visit_service,
        private UserProvider $user_provider,
        private ReferralRepository $referral_repository,
        private CareTeamService $care_team_service,
        private AccessPolicy $access_policy,
        private ScheduleService $schedule_service
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

        include JMRS_PLUGIN_PATH . 'templates/dashboard/index.php';
    }
}
