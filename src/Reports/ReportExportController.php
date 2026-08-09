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
        $home_id = isset($_GET['jmrs_report_home'])
            ? absint(wp_unslash((string) $_GET['jmrs_report_home']))
            : 0;
        $visit_care_context = isset($_GET['jmrs_visit_care_context'])
            ? sanitize_key(wp_unslash((string) $_GET['jmrs_visit_care_context']))
            : VisitDeliveryContext::ALL;
        $visit_home_id = isset($_GET['jmrs_visit_home'])
            ? absint(wp_unslash((string) $_GET['jmrs_visit_home']))
            : 0;

        if (! in_array($range, ReportService::allowed_ranges(), true)) {
            $range = ReportService::RANGE_THIS_MONTH;
        }

        $result = $this->report_service->get_report_data(
            [
                'range'              => $range,
                'start_date'         => $start_date,
                'end_date'           => $end_date,
                'home_id'            => $home_id,
                'visit_care_context' => $visit_care_context,
                'visit_home_id'      => $visit_home_id,
            ]
        );

        if (! empty($result['errors'])) {
            wp_die(esc_html__('The requested action could not be completed.', 'jm-referral-system'));
        }

        if (
            ReportService::SECTION_VACANCY === $section_id
            && ! $this->report_service->current_user_can_view_vacancy_detail()
        ) {
            wp_die(esc_html__('You do not have permission.', 'jm-referral-system'));
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
     * @param array{
     *     range?: string,
     *     start_date?: string,
     *     end_date?: string,
     *     home_id?: int,
     *     visit_care_context?: string,
     *     visit_home_id?: int
     * } $filters
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
        if (! empty($filters['home_id'])) {
            $args['jmrs_report_home'] = (int) $filters['home_id'];
        }
        if (
            ! empty($filters['visit_care_context'])
            && VisitDeliveryContext::ALL !== (string) $filters['visit_care_context']
        ) {
            $args['jmrs_visit_care_context'] = (string) $filters['visit_care_context'];
        }
        if (! empty($filters['visit_home_id'])) {
            $args['jmrs_visit_home'] = (int) $filters['visit_home_id'];
        }

        return wp_nonce_url(
            add_query_arg($args, admin_url('admin.php')),
            'jmrs_export_report_full'
        );
    }

    /**
     * @param array{
     *     range?: string,
     *     start_date?: string,
     *     end_date?: string,
     *     home_id?: int,
     *     visit_care_context?: string,
     *     visit_home_id?: int
     * } $filters
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
        if (! empty($filters['home_id'])) {
            $args['jmrs_report_home'] = (int) $filters['home_id'];
        }
        if (
            ! empty($filters['visit_care_context'])
            && VisitDeliveryContext::ALL !== (string) $filters['visit_care_context']
        ) {
            $args['jmrs_visit_care_context'] = (string) $filters['visit_care_context'];
        }
        if (! empty($filters['visit_home_id'])) {
            $args['jmrs_visit_home'] = (int) $filters['visit_home_id'];
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

        $visit_filters = is_array($result['visit_filters'] ?? null) ? $result['visit_filters'] : [];
        if (! empty($visit_filters['is_active'])) {
            $ctx_label  = (string) ($visit_filters['care_context_label'] ?? 'All');
            $home_label = trim((string) ($visit_filters['home_name'] ?? ''));
            CsvExportHelper::put_row($output, ['Visit Care Delivery Filter', 'Care Delivery Context', $ctx_label]);
            CsvExportHelper::put_row($output, [
                'Visit Care Delivery Filter',
                'Supported Living Home',
                '' !== $home_label ? $home_label : 'All Homes',
            ]);
            CsvExportHelper::put_row($output, [
                'Visit Care Delivery Filter',
                'Note',
                'Applies to Visit KPIs, Visit Analytics, visit-linked Task metrics, and visit-completed Staff metrics in this export. Vacancy Home filter remains separate.',
            ]);
            CsvExportHelper::put_row($output, []);
        }

        $sl = is_array($result['supported_living'] ?? null) ? $result['supported_living'] : [];
        if ([] !== $sl) {
            CsvExportHelper::put_row($output, ['Supported Living — Current Snapshot', 'Metric', 'Value']);
            CsvExportHelper::put_row($output, [
                'Supported Living — Current Snapshot',
                'Note',
                'Current estate and care-delivery position as of today (not date-range filtered).',
            ]);
            $sl_metrics = [
                'Active Homes'               => $sl['active_homes'] ?? 0,
                'Capacity'                   => $sl['capacity'] ?? 0,
                'Occupied'                   => $sl['occupied'] ?? 0,
                'Vacant'                     => $sl['vacant'] ?? 0,
                'Occupancy %'                => $sl['occupancy_percent'] ?? 0,
                'Supported Living Clients'   => $sl['supported_living'] ?? 0,
                'Awaiting Placement'         => $sl['awaiting_placement'] ?? 0,
                "Client's Own Home"          => $sl['own_home'] ?? 0,
                'Not Specified'              => $sl['not_specified'] ?? 0,
            ];
            foreach ($sl_metrics as $metric => $value) {
                CsvExportHelper::put_row($output, ['Supported Living — Current Snapshot', $metric, $value]);
            }
            CsvExportHelper::put_row($output, []);
        }

        // Vacancy bedroom rows stay section-specific (tabular). Full export only notes the summary.
        $vacancy = is_array($result['vacancy'] ?? null) ? $result['vacancy'] : [];
        if ([] !== $vacancy && ! empty($vacancy['can_view_detail'])) {
            $metrics = is_array($vacancy['metrics'] ?? null) ? $vacancy['metrics'] : [];
            CsvExportHelper::put_row($output, ['Vacancy Report — Current Snapshot', 'Metric', 'Value']);
            CsvExportHelper::put_row($output, [
                'Vacancy Report — Current Snapshot',
                'Note',
                'Current vacancy snapshot (not date-range filtered). Bedroom-level rows: Export Section CSV.',
            ]);
            $home_name = trim((string) ($vacancy['home_name'] ?? ''));
            CsvExportHelper::put_row($output, [
                'Vacancy Report — Current Snapshot',
                'Home filter',
                '' !== $home_name ? $home_name : 'All Active Homes',
            ]);
            foreach (
                [
                    'Capacity'    => $metrics['capacity'] ?? 0,
                    'Occupied'    => $metrics['occupied'] ?? 0,
                    'Vacant'      => $metrics['vacant'] ?? 0,
                    'Occupancy %' => $metrics['occupancy_percent'] ?? 0,
                ] as $metric => $value
            ) {
                CsvExportHelper::put_row($output, ['Vacancy Report — Current Snapshot', $metric, $value]);
            }
            CsvExportHelper::put_row($output, []);
        }

        $movements = is_array($result['placement_movements'] ?? null) ? $result['placement_movements'] : [];
        if ([] !== $movements) {
            CsvExportHelper::put_row($output, ['Placement Movements — Selected Period', 'Metric', 'Value']);
            CsvExportHelper::put_row($output, [
                'Placement Movements — Selected Period',
                'Note',
                'Based on activity.created_at (events recorded in JMRS during the period). Detail rows: Export Section CSV.',
            ]);
            foreach (
                [
                    'New Placements'         => $movements['new_placements'] ?? 0,
                    'Transfers'              => $movements['transfers'] ?? 0,
                    'Placements Ended'       => $movements['placements_ended'] ?? 0,
                    'Total Placement Events' => $movements['total_events'] ?? 0,
                ] as $metric => $value
            ) {
                CsvExportHelper::put_row($output, ['Placement Movements — Selected Period', $metric, $value]);
            }
            CsvExportHelper::put_row($output, []);
        }

        $sections = is_array($result['sections'] ?? null) ? $result['sections'] : [];
        foreach ($sections as $section) {
            $section_id = (string) ($section['id'] ?? '');
            // Skip tabular datasets in the Metric/Value full export loop.
            if (
                ReportService::SECTION_VACANCY === $section_id
                || ReportService::SECTION_PLACEMENT_MOVEMENTS === $section_id
            ) {
                continue;
            }

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

        if (ReportService::SECTION_VACANCY === $section_id) {
            $this->stream_vacancy_section_csv($section, $result);
            return;
        }

        if (ReportService::SECTION_PLACEMENT_MOVEMENTS === $section_id) {
            $this->stream_placement_movements_section_csv($section, $result);
            return;
        }

        $slug     = str_replace('_', '-', $section_id);
        $filename = sprintf('jmrs-%s-%s-to-%s.csv', $slug, $start, $end);
        $output   = $this->open_csv($filename);

        CsvExportHelper::put_row($output, ['Section', 'Metric', 'Value']);
        CsvExportHelper::put_row($output, ['Report Period', 'Start Date', $start]);
        CsvExportHelper::put_row($output, ['Report Period', 'End Date', $end]);
        CsvExportHelper::put_row($output, []);

        if ('visit_analytics' === $section_id) {
            $visit_filters = is_array($result['visit_filters'] ?? null) ? $result['visit_filters'] : [];
            $ctx_label     = (string) ($visit_filters['care_context_label'] ?? 'All');
            $home_label    = trim((string) ($visit_filters['home_name'] ?? ''));
            CsvExportHelper::put_row($output, [
                'Visit Care Delivery',
                'Care Delivery Context',
                $ctx_label,
            ]);
            CsvExportHelper::put_row($output, [
                'Visit Care Delivery',
                'Supported Living Home',
                '' !== $home_label ? $home_label : 'All Homes',
            ]);
            CsvExportHelper::put_row($output, [
                'Visit Care Delivery',
                'Note',
                'Executed visits use recorded service-location snapshot; open visits use current service location; legacy/missed/cancelled without snapshot are Location Not Recorded.',
            ]);
            CsvExportHelper::put_row($output, []);
        }

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
     * Tabular vacancy CSV (Home, Bedroom, City, Postcode, Vacant Since, Status).
     *
     * @param array<string, mixed> $section
     * @param array<string, mixed> $result
     */
    private function stream_vacancy_section_csv(array $section, array $result): void
    {
        $today    = current_time('Y-m-d');
        $filename = sprintf('jmrs-supported-living-vacancies-%s.csv', $today);
        $output   = $this->open_csv($filename);

        $vacancy = is_array($result['vacancy'] ?? null) ? $result['vacancy'] : [];
        $metrics = is_array($vacancy['metrics'] ?? null) ? $vacancy['metrics'] : [];
        $home_name = trim((string) ($vacancy['home_name'] ?? ''));

        CsvExportHelper::put_row($output, ['Vacancy Report — Current Snapshot']);
        CsvExportHelper::put_row($output, ['Generated', $today]);
        CsvExportHelper::put_row($output, [
            'Home filter',
            '' !== $home_name ? $home_name : 'All Active Homes',
        ]);
        CsvExportHelper::put_row($output, ['Capacity', $metrics['capacity'] ?? 0]);
        CsvExportHelper::put_row($output, ['Occupied', $metrics['occupied'] ?? 0]);
        CsvExportHelper::put_row($output, ['Vacant', $metrics['vacant'] ?? 0]);
        CsvExportHelper::put_row($output, ['Occupancy %', $metrics['occupancy_percent'] ?? 0]);
        CsvExportHelper::put_row($output, []);

        $datasets = is_array($section['datasets'] ?? null) ? $section['datasets'] : [];
        $detail   = null;
        foreach ($datasets as $dataset) {
            if ('vacancy_detail' === (string) ($dataset['id'] ?? '')) {
                $detail = $dataset;
                break;
            }
        }

        $columns = is_array($detail['export']['columns'] ?? null)
            ? $detail['export']['columns']
            : ['Home', 'Bedroom', 'City', 'Postcode', 'Vacant Since', 'Status'];
        $rows = is_array($detail['export']['rows'] ?? null) ? $detail['export']['rows'] : [];

        CsvExportHelper::put_row($output, $columns);
        foreach ($rows as $row) {
            CsvExportHelper::put_row($output, is_array($row) ? $row : []);
        }

        fclose($output);
        exit;
    }

    /**
     * Tabular placement movement CSV.
     *
     * @param array<string, mixed> $section
     * @param array<string, mixed> $result
     */
    private function stream_placement_movements_section_csv(array $section, array $result): void
    {
        $today    = current_time('Y-m-d');
        $filename = sprintf('jmrs-placement-movements-%s.csv', $today);
        $output   = $this->open_csv($filename);

        $movements = is_array($result['placement_movements'] ?? null) ? $result['placement_movements'] : [];
        $start     = (string) ($result['start_date'] ?? '');
        $end       = (string) ($result['end_date'] ?? '');

        CsvExportHelper::put_row($output, ['Placement Movements — Selected Period']);
        CsvExportHelper::put_row($output, ['Generated', $today]);
        CsvExportHelper::put_row($output, ['Period Start', $start]);
        CsvExportHelper::put_row($output, ['Period End', $end]);
        CsvExportHelper::put_row($output, [
            'Note',
            'Based on activity.created_at (when the event was recorded in JMRS), not backdated move-in/out dates.',
        ]);
        CsvExportHelper::put_row($output, ['New Placements', $movements['new_placements'] ?? 0]);
        CsvExportHelper::put_row($output, ['Transfers', $movements['transfers'] ?? 0]);
        CsvExportHelper::put_row($output, ['Placements Ended', $movements['placements_ended'] ?? 0]);
        CsvExportHelper::put_row($output, ['Total Placement Events', $movements['total_events'] ?? 0]);
        CsvExportHelper::put_row($output, []);

        $datasets = is_array($section['datasets'] ?? null) ? $section['datasets'] : [];
        $detail   = null;
        foreach ($datasets as $dataset) {
            if ('placement_movements_detail' === (string) ($dataset['id'] ?? '')) {
                $detail = $dataset;
                break;
            }
        }

        $columns = is_array($detail['export']['columns'] ?? null)
            ? $detail['export']['columns']
            : ['Recorded Date', 'Event', 'Referral Number', 'Client', 'Details'];
        $rows = is_array($detail['export']['rows'] ?? null) ? $detail['export']['rows'] : [];

        CsvExportHelper::put_row($output, $columns);
        foreach ($rows as $row) {
            CsvExportHelper::put_row($output, is_array($row) ? $row : []);
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
