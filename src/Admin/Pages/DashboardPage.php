<?php

namespace JMReferral\Admin\Pages;

use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralService;

class DashboardPage
{
    public function __construct(
        private ReferralService $service
    ) {
    }

    public function render(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_DASHBOARD)) {
            wp_die(esc_html__('You do not have permission to view the dashboard.', 'jm-referral-system'));
        }

        $dashboard           = $this->service->get_dashboard_data(5);
        $stats               = $dashboard['stats'];
        $pipeline            = $dashboard['pipeline'] ?? [];
        $recent              = $dashboard['recent'];
        $scoped_to_assigned  = ! empty($dashboard['scoped_to_assigned']);

        include JMRS_PLUGIN_PATH . 'templates/dashboard/index.php';
    }
}
