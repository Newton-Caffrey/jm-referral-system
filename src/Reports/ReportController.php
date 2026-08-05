<?php

namespace JMReferral\Reports;

use JMReferral\Permissions\Capabilities;

class ReportController
{
    public const PAGE_SLUG = 'jm-referrals-reports';

    public function __construct(
        private ReportService $report_service
    ) {
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(string $hook_suffix): void
    {
        if (! $this->is_reports_screen($hook_suffix)) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::VIEW_REPORTS)) {
            return;
        }

        $css_path = JMRS_PLUGIN_PATH . 'assets/css/reports.css';
        $js_path  = JMRS_PLUGIN_PATH . 'assets/js/reports.js';

        wp_enqueue_style(
            'jmrs-reports',
            JMRS_PLUGIN_URL . 'assets/css/reports.css',
            [],
            file_exists($css_path) ? (string) filemtime($css_path) : JMRS_VERSION
        );

        $chart_deps = $this->enqueue_chart_js();

        wp_enqueue_script(
            'jmrs-reports',
            JMRS_PLUGIN_URL . 'assets/js/reports.js',
            $chart_deps,
            file_exists($js_path) ? (string) filemtime($js_path) : JMRS_VERSION,
            true
        );
    }

    public function render(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_REPORTS)) {
            wp_die(esc_html__('You do not have permission to view reports.', 'jm-referral-system'));
        }

        $filters = $this->filters_from_request();
        $result  = $this->report_service->get_report_data($filters);

        $kpis          = $result['kpis'];
        $sections      = $result['sections'] ?? [];
        $range_labels  = $result['range_labels'];
        $filter_range  = $result['range'];
        $filter_start  = $result['start_date'];
        $filter_end    = $result['end_date'];
        $filter_errors = $result['errors'];
        $reports_url   = ReportService::get_reports_page_url();
        $alerts_url    = admin_url('admin.php?page=jm-referrals-operational-alerts');

        $export_filters = [
            'range'      => $filter_range,
            'start_date' => $filter_start,
            'end_date'   => $filter_end,
        ];
        $full_export_url = ReportExportController::get_full_export_url($export_filters);

        $section_export_urls = [];
        foreach (is_array($sections) ? $sections : [] as $section) {
            $section_id = (string) ($section['id'] ?? '');
            if ('' === $section_id) {
                continue;
            }
            $section_export_urls[$section_id] = ReportExportController::get_section_export_url(
                $section_id,
                $export_filters
            );
        }

        $charts = ReportService::charts_for_script(is_array($sections) ? $sections : []);
        wp_add_inline_script(
            'jmrs-reports',
            'window.jmrsReportsData = ' . wp_json_encode(
                ['charts' => $charts],
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ) . ';',
            'before'
        );

        include JMRS_PLUGIN_PATH . 'templates/reports/index.php';
    }

    /**
     * @return array{range: string, start_date: string, end_date: string}
     */
    private function filters_from_request(): array
    {
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

        return [
            'range'      => $range,
            'start_date' => $start_date,
            'end_date'   => $end_date,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function enqueue_chart_js(): array
    {
        $local = JMRS_PLUGIN_PATH . 'assets/vendor/chart.umd.min.js';
        $handle = 'jmrs-chartjs';

        if (file_exists($local)) {
            wp_enqueue_script(
                $handle,
                JMRS_PLUGIN_URL . 'assets/vendor/chart.umd.min.js',
                [],
                '4.4.6',
                true
            );
        } else {
            // Pinned Chart.js 4.4.6 via jsDelivr when a local vendor build is not present.
            wp_enqueue_script(
                $handle,
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js',
                [],
                '4.4.6',
                true
            );
        }

        return [$handle];
    }

    private function is_reports_screen(string $hook_suffix): bool
    {
        if (isset($_GET['page']) && self::PAGE_SLUG === (string) $_GET['page']) {
            return true;
        }

        return false !== strpos($hook_suffix, self::PAGE_SLUG);
    }
}
