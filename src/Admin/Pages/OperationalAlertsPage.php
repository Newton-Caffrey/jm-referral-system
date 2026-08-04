<?php

namespace JMReferral\Admin\Pages;

use JMReferral\Alerts\OperationalAlertService;
use JMReferral\Permissions\Capabilities;

class OperationalAlertsPage
{
    public function __construct(
        private OperationalAlertService $alert_service
    ) {
    }

    public function render(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_OPERATIONAL_ALERTS)) {
            wp_die(esc_html__('You do not have permission to view operational alerts.', 'jm-referral-system'));
        }

        $severity = isset($_GET['jmrs_alert_severity'])
            ? sanitize_key(wp_unslash((string) $_GET['jmrs_alert_severity']))
            : '';
        $type = isset($_GET['jmrs_alert_type'])
            ? sanitize_key(wp_unslash((string) $_GET['jmrs_alert_type']))
            : '';
        $search = isset($_GET['jmrs_alert_search'])
            ? sanitize_text_field(wp_unslash((string) $_GET['jmrs_alert_search']))
            : '';

        if (! isset(OperationalAlertService::severity_labels()[$severity])) {
            $severity = '';
        }

        if (! isset(OperationalAlertService::type_labels()[$type])) {
            $type = '';
        }

        $result = $this->alert_service->get_alerts(
            [
                'severity' => $severity,
                'type'     => $type,
                'search'   => $search,
            ]
        );

        $alerts           = $result['alerts'];
        $counts           = $result['counts'];
        $type_labels      = $result['type_labels'];
        $severity_labels  = $result['severity_labels'];
        $filter_severity  = $severity;
        $filter_type      = $type;
        $filter_search    = $search;
        $alerts_page_url  = OperationalAlertService::get_alerts_page_url();

        include JMRS_PLUGIN_PATH . 'templates/alerts/index.php';
    }
}
