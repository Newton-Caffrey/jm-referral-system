<?php

namespace JMReferral\Reports;

use JMReferral\Permissions\Capabilities;
use JMReferral\Support\CsvExportHelper;

class ReportExportController
{
    public function __construct(
        private ReportService $report_service
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_export']);
    }

    public function handle_export(): void
    {
        if (! $this->is_export_request()) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::VIEW_REPORTS)) {
            wp_die(esc_html__('You do not have permission.', 'jm-referral-system'));
        }

        $mode = sanitize_key((string) ($_GET['jmrs_report_export'] ?? ''));
        if (! in_array($mode, ['full', 'section'], true)) {
            wp_die(esc_html__('The requested action could not be completed.', 'jm-referral-system'));
        }

        $section_id = sanitize_key((string) ($_GET['jmrs_report_section'] ?? ''));
        if ('section' === $mode) {
            check_admin_referer('jmrs_export_report_section_' . $section_id);
        } else {
            check_admin_referer('jmrs_export_report_full');
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

        if (! empty($result['errors'])) {
            wp_die(esc_html__('The requested action could not be completed.', 'jm-referral-system'));
        }

        $start = (string) ($result['start_date'] ?? '');
        $end   = (string) ($result['end_date'] ?? '');

        if ('section' === $mode) {
            $this->stream_section_csv($result, $section_id, $start, $end);
            return;
        }

        $this->stream_full_csv($result, $start, $end);
    }

    /**
     * @param array{range?: string, start_date?: string, end_date?: string} $filters
     */
    public static function get_full_export_url(array $filters): string
    {
        $args = [
            'page'               => 'jm-referrals-reports',
            'jmrs_report_export' => 'full',
            'jmrs_report_range'  => (string) ($filters['range'] ?? ReportService::RANGE_THIS_MONTH),
        ];

        if (! empty($filters['start_date'])) {
            $args['jmrs_report_start'] = (string) $filters['start_date'];
        }
        if (! empty($filters['end_date'])) {
            $args['jmrs_report_end'] = (string) $filters['end_date'];
        }

        return wp_nonce_url(
            add_query_arg($args, admin_url('admin.php')),
            'jmrs_export_report_full'
        );
    }

    /**
     * @param array{range?: string, start_date?: string, end_date?: string} $filters
     */
    public static function get_section_export_url(string $section_id, array $filters): string
    {
        $section_id = sanitize_key($section_id);
        $args       = [
            'page'                => 'jm-referrals-reports',
            'jmrs_report_export'  => 'section',
            'jmrs_report_section' => $section_id,
            'jmrs_report_range'   => (string) ($filters['range'] ?? ReportService::RANGE_THIS_MONTH),
        ];

        if (! empty($filters['start_date'])) {
            $args['jmrs_report_start'] = (string) $filters['start_date'];
        }
        if (! empty($filters['end_date'])) {
            $args['jmrs_report_end'] = (string) $filters['end_date'];
        }

        return wp_nonce_url(
            add_query_arg($args, admin_url('admin.php')),
            'jmrs_export_report_section_' . $section_id
        );
    }

    private function is_export_request(): bool
    {
        if (! is_admin()) {
            return false;
        }

        if (! isset($_GET['page'], $_GET['jmrs_report_export'])) {
            return false;
        }

        return 'jm-referrals-reports' === (string) $_GET['page'];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function stream_full_csv(array $result, string $start, string $end): void
    {
        $filename = sprintf('jmrs-report-%s-to-%s.csv', $start, $end);
        $output   = $this->open_csv($filename);

        CsvExportHelper::put_row($output, ['Section', 'Metric', 'Value']);
        CsvExportHelper::put_row($output, ['Report Period', 'Start Date', $start]);
        CsvExportHelper::put_row($output, ['Report Period', 'End Date', $end]);
        CsvExportHelper::put_row($output, ['Report Period', 'Preset', (string) ($result['range'] ?? '')]);
        CsvExportHelper::put_row($output, []);

        CsvExportHelper::put_row($output, ['KPI Summary', 'Metric', 'Value']);
        $kpis = is_array($result['kpis'] ?? null) ? $result['kpis'] : [];
        foreach ($kpis as $metric => $value) {
            CsvExportHelper::put_row($output, ['KPI Summary', $this->humanize_key((string) $metric), $value]);
        }
        CsvExportHelper::put_row($output, []);

        $sections = is_array($result['sections'] ?? null) ? $result['sections'] : [];
        foreach ($sections as $section) {
            $section_title = (string) ($section['title'] ?? '');
            $datasets      = is_array($section['datasets'] ?? null) ? $section['datasets'] : [];

            CsvExportHelper::put_row($output, [$section_title, 'Metric', 'Value']);
            foreach ($datasets as $dataset) {
                $dataset_title = (string) ($dataset['title'] ?? '');
                $rows          = is_array($dataset['export']['rows'] ?? null)
                    ? $dataset['export']['rows']
                    : [];

                if ([] === $rows) {
                    CsvExportHelper::put_row($output, [$section_title, $dataset_title, '']);
                    continue;
                }

                foreach ($rows as $row) {
                    $label = (string) ($row[0] ?? '');
                    $value = $row[1] ?? '';
                    CsvExportHelper::put_row(
                        $output,
                        [
                            $section_title,
                            $dataset_title . ' — ' . $label,
                            $value,
                        ]
                    );
                }
            }
            CsvExportHelper::put_row($output, []);
        }

        fclose($output);
        exit;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function stream_section_csv(array $result, string $section_id, string $start, string $end): void
    {
        $sections = is_array($result['sections'] ?? null) ? $result['sections'] : [];
        $section  = null;

        foreach ($sections as $candidate) {
            if ((string) ($candidate['id'] ?? '') === $section_id) {
                $section = $candidate;
                break;
            }
        }

        if (! is_array($section)) {
            wp_die(esc_html__('The requested action could not be completed.', 'jm-referral-system'));
        }

        $slug     = str_replace('_', '-', $section_id);
        $filename = sprintf('jmrs-%s-%s-to-%s.csv', $slug, $start, $end);
        $output   = $this->open_csv($filename);

        CsvExportHelper::put_row($output, ['Section', 'Metric', 'Value']);
        CsvExportHelper::put_row($output, ['Report Period', 'Start Date', $start]);
        CsvExportHelper::put_row($output, ['Report Period', 'End Date', $end]);
        CsvExportHelper::put_row($output, []);

        $section_title = (string) ($section['title'] ?? $section_id);
        $datasets      = is_array($section['datasets'] ?? null) ? $section['datasets'] : [];

        foreach ($datasets as $dataset) {
            $dataset_title = (string) ($dataset['title'] ?? '');
            $rows          = is_array($dataset['export']['rows'] ?? null)
                ? $dataset['export']['rows']
                : [];

            if ([] === $rows) {
                CsvExportHelper::put_row($output, [$section_title, $dataset_title, '']);
                continue;
            }

            foreach ($rows as $row) {
                CsvExportHelper::put_row(
                    $output,
                    [
                        $section_title,
                        $dataset_title . ' — ' . (string) ($row[0] ?? ''),
                        $row[1] ?? '',
                    ]
                );
            }
            CsvExportHelper::put_row($output, []);
        }

        fclose($output);
        exit;
    }

    /**
     * @return resource
     */
    private function open_csv(string $filename)
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        if (false === $output) {
            wp_die(esc_html__('The requested action could not be completed.', 'jm-referral-system'));
        }

        fwrite($output, "\xEF\xBB\xBF");

        return $output;
    }

    private function humanize_key(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
