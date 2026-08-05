<?php

namespace JMReferral\Referral;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Support\CsvExportHelper;
use JMReferral\Users\UserProvider;
use JMReferral\Workflow\WorkflowStageService;

class ReferralExportController
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private ReferralRepository $repository,
        private ReferralFilters $filters,
        private UserProvider $user_provider,
        private ServiceTypeService $service_type_service,
        private WorkflowStageService $workflow_stage_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Registers export hooks.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_export']);
    }

    /**
     * Streams a CSV download of the currently filtered referrals in chunks.
     */
    public function handle_export(): void
    {
        if (! $this->is_export_request()) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::EXPORT_REFERRALS)) {
            wp_die(esc_html__('You do not have permission.', 'jm-referral-system'));
        }

        check_admin_referer('jmrs_export_referrals');

        global $wpdb;
        $queries_before = defined('WP_DEBUG') && WP_DEBUG ? (int) $wpdb->num_queries : 0;
        $chunks_processed = 0;

        $filters            = $this->filters->from_request();
        $access_assigned_to = $this->access_policy->get_assigned_user_constraint();
        $filename           = 'referrals-export-' . current_time('Y-m-d') . '.csv';

        // Export always streams the full filtered set (ignores list paged / jmrs_per_page).

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $output = fopen('php://output', 'w');

        if (false === $output) {
            wp_die(esc_html__('The requested action could not be completed.', 'jm-referral-system'));
        }

        // UTF-8 BOM helps Excel open the file correctly.
        fwrite($output, "\xEF\xBB\xBF");

        CsvExportHelper::put_row(
            $output,
            [
                'Referral Number',
                'Client Name',
                'Client Email',
                'Client Phone',
                'Service Required',
                'Workflow Stage',
                'Priority',
                'Status',
                'Assigned To',
                'Care Start Date',
                'Preferred Contact Method',
                'Care Requirements',
                'Archived',
                'Archived At',
                'Archive Reason',
                'Created Date',
                'Updated Date',
            ]
        );

        $offset            = 0;
        $service_name_cache = [];
        $stage_name_cache   = [];
        $assignee_cache     = [];

        do {
            $referrals = $this->repository->find_by_filters(
                $filters,
                self::CHUNK_SIZE,
                $offset,
                $access_assigned_to
            );

            $batch_count = count($referrals);
            if (0 === $batch_count) {
                break;
            }

            ++$chunks_processed;

            $service_type_ids   = [];
            $workflow_stage_ids = [];
            $assignee_ids       = [];

            foreach ($referrals as $referral) {
                $service_type_id = absint($referral['service_type_id'] ?? 0);
                if ($service_type_id > 0 && ! isset($service_name_cache[$service_type_id])) {
                    $service_type_ids[] = $service_type_id;
                }

                $workflow_stage_id = absint($referral['workflow_stage_id'] ?? 0);
                if ($workflow_stage_id > 0 && ! isset($stage_name_cache[$workflow_stage_id])) {
                    $workflow_stage_ids[] = $workflow_stage_id;
                }

                $assigned_to = absint($referral['assigned_to'] ?? 0);
                if ($assigned_to > 0 && ! isset($assignee_cache[$assigned_to])) {
                    $assignee_ids[] = $assigned_to;
                }
            }

            if ([] !== $service_type_ids) {
                foreach ($this->service_type_service->get_names_by_ids($service_type_ids) as $id => $name) {
                    $service_name_cache[(int) $id] = $name;
                }
            }
            if ([] !== $workflow_stage_ids) {
                foreach ($this->workflow_stage_service->get_names_by_ids($workflow_stage_ids) as $id => $name) {
                    $stage_name_cache[(int) $id] = $name;
                }
            }
            if ([] !== $assignee_ids) {
                foreach ($this->user_provider->get_display_names_by_ids($assignee_ids) as $id => $name) {
                    $assignee_cache[(int) $id] = $name;
                }
            }

            foreach ($referrals as $referral) {
                $assigned_to = absint($referral['assigned_to'] ?? 0);
                $assigned_to_name = $assigned_to > 0
                    ? (string) ($assignee_cache[$assigned_to] ?? '')
                    : '';

                $contact_method = (string) ($referral['preferred_contact_method'] ?? '');
                $contact_label  = '' !== $contact_method
                    ? PreferredContactMethods::label($contact_method)
                    : '';

                $service_type_id = absint($referral['service_type_id'] ?? 0);
                $service_name    = (string) ($referral['service_required'] ?? '');
                if ($service_type_id > 0 && isset($service_name_cache[$service_type_id])) {
                    $service_name = $service_name_cache[$service_type_id];
                }

                $workflow_stage_id = absint($referral['workflow_stage_id'] ?? 0);
                $stage_name        = ($workflow_stage_id > 0 && isset($stage_name_cache[$workflow_stage_id]))
                    ? $stage_name_cache[$workflow_stage_id]
                    : '';

                $archived_at = (string) ($referral['archived_at'] ?? '');
                $is_archived = '' !== $archived_at;

                CsvExportHelper::put_row(
                    $output,
                    [
                        (string) ($referral['referral_number'] ?? ''),
                        (string) ($referral['client_name'] ?? ''),
                        (string) ($referral['client_email'] ?? ''),
                        (string) ($referral['client_phone'] ?? ''),
                        $service_name,
                        $stage_name,
                        (string) ($referral['priority'] ?? ''),
                        (string) ($referral['status'] ?? ''),
                        $assigned_to_name,
                        (string) ($referral['care_start_date'] ?? ''),
                        $contact_label,
                        (string) ($referral['care_requirements'] ?? ''),
                        $is_archived ? 'Yes' : 'No',
                        $archived_at,
                        (string) ($referral['archive_reason'] ?? ''),
                        (string) ($referral['created_at'] ?? ''),
                        (string) ($referral['updated_at'] ?? ''),
                    ]
                );
            }

            $offset += $batch_count;
            unset($referrals);
        } while ($batch_count === self::CHUNK_SIZE);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $delta = (int) $wpdb->num_queries - $queries_before;
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated by WP_DEBUG.
            error_log(
                sprintf(
                    '[JMRS] referral export query count: %d; chunks processed: %d',
                    max(0, $delta),
                    $chunks_processed
                )
            );
        }

        fclose($output);
        exit;
    }

    /**
     * Builds a nonce-protected export URL that preserves list filters.
     *
     * @param array{
     *     search?: string,
     *     status?: string,
     *     priority?: string,
     *     assigned_to?: int
     * } $filters
     */
    public static function get_export_url(array $filters = []): string
    {
        $args = [
            'page'        => 'jm-referrals-list',
            'jmrs_export' => 'csv',
        ];

        if (! empty($filters['search'])) {
            $args['jmrs_search'] = (string) $filters['search'];
        }

        if (! empty($filters['status'])) {
            $args['jmrs_status'] = (string) $filters['status'];
        }

        if (! empty($filters['priority'])) {
            $args['jmrs_priority'] = (string) $filters['priority'];
        }

        if (! empty($filters['assigned_to'])) {
            $args['jmrs_assigned_to'] = absint($filters['assigned_to']);
        }

        if (! empty($filters['archive_scope']) && 'active' !== $filters['archive_scope']) {
            $args['jmrs_archive_scope'] = (string) $filters['archive_scope'];
        }

        return wp_nonce_url(
            add_query_arg($args, admin_url('admin.php')),
            'jmrs_export_referrals'
        );
    }

    private function is_export_request(): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $export = isset($_GET['jmrs_export']) ? sanitize_key(wp_unslash($_GET['jmrs_export'])) : '';

        return 'jm-referrals-list' === $page && 'csv' === $export;
    }
}
