<?php

namespace JMReferral\Reports;

use JMReferral\Permissions\Capabilities;

class ReportController
{
    public function __construct(
        private ReportService $report_service
    ) {
    }

    public function render(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_REPORTS)) {
            wp_die(esc_html__('You do not have permission to view reports.', 'jm-referral-system'));
        }

        $range = isset($_GET['jmrs_report_range'])
            ? sanitize_key(wp_unslash((string) $_GET['jmrs_report_range']))
            : ReportService::RANGE_THIS_MONTH;

        $start_date = isset($_GET['jmrs_report_start'])
            ? sanitize_text_field(wp_unslash((string) $_GET['jmrs_report_start']))
            : '';
        $end_date = isset($_GET['jmrs_report_end'])
            ? sanitize_text_field(wp_unslash((string) $_GET['jmrs_report_end']))
            : '';

        if (! in_array($range, ReportService::allowed_ranges(), true)) {
            $range = ReportService::RANGE_THIS_MONTH;
        }

        $result = $this->report_service->get_report_data(
            [
                'range'      => $range,
                'start_date' => $start_date,
                'end_date'   => $end_date,
            ]
        );

        $kpis          = $result['kpis'];
        $sections      = $result['sections'] ?? [];
        $range_labels  = $result['range_labels'];
        $filter_range  = $result['range'];
        $filter_start  = $result['start_date'];
        $filter_end    = $result['end_date'];
        $filter_errors = $result['errors'];
        $reports_url   = ReportService::get_reports_page_url();
        $alerts_url    = admin_url('admin.php?page=jm-referrals-operational-alerts');

        include JMRS_PLUGIN_PATH . 'templates/reports/index.php';
    }
}
