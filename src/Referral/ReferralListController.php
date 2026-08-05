<?php

namespace JMReferral\Referral;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;
use JMReferral\Workflow\WorkflowStageService;

class ReferralListController
{
    public function __construct(
        private ReferralRepository $repository,
        private UserProvider $user_provider,
        private ReferralFilters $filters,
        private ServiceTypeService $service_type_service,
        private WorkflowStageService $workflow_stage_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Registers list-related hooks.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_delete']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    /**
     * Renders the referrals listing page.
     */
    public function render(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_REFERRALS)) {
            wp_die(esc_html__('You do not have permission to view referrals.', 'jm-referral-system'));
        }

        $filters            = $this->filters->from_request();
        $access_assigned_to = $this->access_policy->get_assigned_user_constraint();
        $scope_to_assigned  = null !== $access_assigned_to;
        $assignable_users   = $scope_to_assigned ? [] : $this->user_provider->get_assignable_users();
        $query_result       = $this->repository->query($filters, null, 1, $access_assigned_to);
        $service_type_ids   = [];
        $workflow_stage_ids = [];

        foreach ($query_result['items'] as $referral) {
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
        $referrals     = [];

        foreach ($query_result['items'] as $referral) {
            $assigned_to                  = absint($referral['assigned_to'] ?? 0);
            $referral['assigned_to_name'] = $assigned_to > 0
                ? $this->user_provider->get_display_name($assigned_to)
                : '';

            $service_type_id = absint($referral['service_type_id'] ?? 0);
            if ($service_type_id > 0 && isset($service_names[$service_type_id])) {
                $referral['service_name'] = $service_names[$service_type_id];
            } else {
                $referral['service_name'] = (string) ($referral['service_required'] ?? '');
            }

            $workflow_stage_id = absint($referral['workflow_stage_id'] ?? 0);
            $referral['workflow_stage_name'] = ($workflow_stage_id > 0 && isset($stage_names[$workflow_stage_id]))
                ? $stage_names[$workflow_stage_id]
                : '';

            $referrals[] = $referral;
        }

        $total      = $query_result['total'];
        $export_url = ReferralExportController::get_export_url($filters);

        include JMRS_PLUGIN_PATH . 'templates/referrals/list.php';
    }

    /**
     * Handles single referral delete requests.
     */
    public function handle_delete(): void
    {
        if (! $this->is_list_screen()) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';

        if ('delete' !== $action) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::DELETE_REFERRALS)) {
            wp_die(esc_html__('You do not have permission.', 'jm-referral-system'));
        }

        $referral_id = isset($_GET['referral_id']) ? absint($_GET['referral_id']) : 0;

        check_admin_referer('jmrs_delete_referral_' . $referral_id);

        $referral = $this->repository->find($referral_id);

        if (null === $referral || ! $this->access_policy->can_edit_referral($referral)) {
            wp_die(esc_html__('You do not have permission.', 'jm-referral-system'));
        }

        $deleted = $this->repository->delete($referral_id);

        $redirect_url = add_query_arg(
            [
                'page'         => 'jm-referrals-list',
                'jmrs_deleted' => $deleted ? '1' : '0',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Renders delete result notices on the list screen.
     */
    public function render_notices(): void
    {
        if (! $this->is_list_screen()) {
            return;
        }

        if (! isset($_GET['jmrs_deleted'])) {
            return;
        }

        $deleted = sanitize_text_field(wp_unslash($_GET['jmrs_deleted']));

        if ('1' === $deleted) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Referral deleted successfully.', 'jm-referral-system');
            echo '</p></div>';
            return;
        }

        echo '<div class="notice notice-error is-dismissible"><p>';
        echo esc_html__('Unable to delete the referral.', 'jm-referral-system');
        echo '</p></div>';
    }

    /**
     * Builds a nonce-protected delete URL for a referral.
     */
    public static function get_delete_url(int $referral_id): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page'        => 'jm-referrals-list',
                    'action'      => 'delete',
                    'referral_id' => $referral_id,
                ],
                admin_url('admin.php')
            ),
            'jmrs_delete_referral_' . $referral_id
        );
    }

    private function is_list_screen(): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        return 'jm-referrals-list' === $page;
    }
}
