<?php

namespace JMReferral\Referral;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;
use JMReferral\Workflow\WorkflowStageService;

class ReferralExportController
{
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
     * Streams a CSV download of the currently filtered referrals.
     */
    public function handle_export(): void
    {
        if (! $this->is_export_request()) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::EXPORT_REFERRALS)) {
            wp_die(esc_html__('You do not have permission to export referrals.', 'jm-referral-system'));
        }

        check_admin_referer('jmrs_export_referrals');

        $filters            = $this->filters->from_request();
        $access_assigned_to = $this->access_policy->get_assigned_user_constraint();
        $referrals          = $this->repository->find_by_filters($filters, null, 0, $access_assigned_to);
        $filename           = 'referrals-export-' . current_time('Y-m-d') . '.csv';

        $service_type_ids   = [];
        $workflow_stage_ids = [];
        foreach ($referrals as $referral) {
            $service_type_id = absint($referral['service_type_id'] ?? 0);
            if ($service_type_id > 0) {
                $service_type_ids[] = $service_type_id;
            }

            $workflow_stage_id = absint($referral['workflow_stage_id'] ?? 0);
            if ($workflow_stage_id > 0) {
                $workflow_stage_ids[] = $workflow_stage_id;
            }
        }
        $service_names = $this->service_type_service->get_names_by_ids($service_type_ids);
        $stage_names   = $this->workflow_stage_service->get_names_by_ids($workflow_stage_ids);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        if (false === $output) {
            wp_die(esc_html__('Unable to start CSV export.', 'jm-referral-system'));
        }

        // UTF-8 BOM helps Excel open the file correctly.
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv(
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
                'Created Date',
                'Updated Date',
            ]
        );

        foreach ($referrals as $referral) {
            $assigned_to = absint($referral['assigned_to'] ?? 0);
            $assigned_to_name = $assigned_to > 0
                ? $this->user_provider->get_display_name($assigned_to)
                : '';

            $contact_method = (string) ($referral['preferred_contact_method'] ?? '');
            $contact_label  = '' !== $contact_method
                ? PreferredContactMethods::label($contact_method)
                : '';

            $service_type_id = absint($referral['service_type_id'] ?? 0);
            $service_name    = (string) ($referral['service_required'] ?? '');
            if ($service_type_id > 0 && isset($service_names[$service_type_id])) {
                $service_name = $service_names[$service_type_id];
            }

            $workflow_stage_id = absint($referral['workflow_stage_id'] ?? 0);
            $stage_name        = ($workflow_stage_id > 0 && isset($stage_names[$workflow_stage_id]))
                ? $stage_names[$workflow_stage_id]
                : '';

            fputcsv(
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
                    (string) ($referral['created_at'] ?? ''),
                    (string) ($referral['updated_at'] ?? ''),
                ]
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
