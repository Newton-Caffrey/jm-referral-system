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
        private AccessPolicy $access_policy,
        private ReferralRetentionService $retention_service
    ) {
    }

    /**
     * Registers list-related hooks.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_delete']);
        add_action('admin_init', [$this, 'handle_archive']);
        add_action('admin_init', [$this, 'handle_restore']);
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

        global $wpdb;
        $queries_before = (int) $wpdb->num_queries;

        $filters            = $this->filters->from_request();
        $pagination         = $this->filters->pagination_from_request();
        $per_page           = $pagination['per_page'];
        $page               = $pagination['page'];
        $access_assigned_to = $this->access_policy->get_assigned_user_constraint();
        $scope_to_assigned  = null !== $access_assigned_to;
        $assignable_users   = $scope_to_assigned ? [] : $this->user_provider->get_assignable_users();
        $query_result       = $this->repository->query($filters, $per_page, $page, $access_assigned_to);

        $total       = absint($query_result['total'] ?? 0);
        $total_pages = $per_page > 0 ? (int) max(1, (int) ceil($total / $per_page)) : 1;
        if ($page > $total_pages) {
            $page         = $total_pages;
            $query_result = $this->repository->query($filters, $per_page, $page, $access_assigned_to);
        }

        $service_type_ids   = [];
        $workflow_stage_ids = [];
        $assignee_ids       = [];

        foreach ($query_result['items'] as $referral) {
            $service_type_id = absint($referral['service_type_id'] ?? 0);
            if ($service_type_id > 0) {
                $service_type_ids[] = $service_type_id;
            }

            $workflow_stage_id = absint($referral['workflow_stage_id'] ?? 0);
            if ($workflow_stage_id > 0) {
                $workflow_stage_ids[] = $workflow_stage_id;
            }

            $assigned_to = absint($referral['assigned_to'] ?? 0);
            if ($assigned_to > 0) {
                $assignee_ids[] = $assigned_to;
            }
        }

        $service_names = $this->service_type_service->get_names_by_ids($service_type_ids);
        $stage_names   = $this->workflow_stage_service->get_names_by_ids($workflow_stage_ids);
        $assignee_names = $this->user_provider->get_display_names_by_ids($assignee_ids);
        $referrals     = [];

        foreach ($query_result['items'] as $referral) {
            $assigned_to                  = absint($referral['assigned_to'] ?? 0);
            $referral['assigned_to_name'] = $assigned_to > 0
                ? (string) ($assignee_names[$assigned_to] ?? '')
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

            $is_archived = $this->retention_service->is_archived($referral);
            $referral['is_archived'] = $is_archived;

            $referral['can_edit'] = ! $is_archived
                && Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)
                && $this->access_policy->can_edit_referral($referral);

            // Permanent delete is View-only (retention COUNTs once there). List never runs dependency summary.
            $referral['can_delete'] = false;

            $referral['can_archive'] = ! $is_archived
                && Capabilities::current_user_can(Capabilities::ARCHIVE_REFERRALS)
                && $this->access_policy->can_edit_referral($referral);

            $referral['can_restore'] = $is_archived
                && Capabilities::current_user_can(Capabilities::RESTORE_REFERRALS)
                && $this->access_policy->can_view_referral($referral);

            $referrals[] = $referral;
        }

        $export_url = ReferralExportController::get_export_url($filters);

        $from = 0 === $total ? 0 : (($page - 1) * $per_page) + 1;
        $to   = min($page * $per_page, $total);

        $list_base_args = ReferralFilters::list_query_args($filters, $per_page);
        $list_base_url  = add_query_arg($list_base_args, admin_url('admin.php'));
        $pagination_links = '';
        if ($total_pages > 1) {
            $pagination_links = paginate_links(
                [
                    'base'      => esc_url_raw($list_base_url) . '%_%',
                    'format'    => '&paged=%#%',
                    'current'   => $page,
                    'total'     => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'type'      => 'plain',
                ]
            );
        }

        $this->maybe_log_query_count('referral list', $queries_before);

        include JMRS_PLUGIN_PATH . 'templates/referrals/list.php';
    }

    /**
     * Development-only query count log (no SQL, no PHI).
     */
    private function maybe_log_query_count(string $label, int $queries_before): void
    {
        if (! defined('WP_DEBUG') || ! WP_DEBUG) {
            return;
        }

        global $wpdb;
        $delta = (int) $wpdb->num_queries - $queries_before;
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated by WP_DEBUG.
        error_log(sprintf('[JMRS] %s query count: %d', $label, max(0, $delta)));
    }

    /**
     * Handles single referral permanent delete requests.
     */
    public function handle_delete(): void
    {
        if (! $this->is_list_screen() && ! $this->is_view_screen()) {
            return;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';

        if ('delete' !== $action) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::DELETE_REFERRALS)) {
            wp_die(esc_html__('You do not have permission.', 'jm-referral-system'));
        }

        $referral_id = isset($_REQUEST['referral_id']) ? absint($_REQUEST['referral_id']) : 0;

        check_admin_referer('jmrs_delete_referral_' . $referral_id);

        $result = $this->retention_service->permanently_delete($referral_id);

        $args = [
            'page' => 'jm-referrals-list',
        ];

        if (! empty($result['success'])) {
            $args['jmrs_deleted'] = '1';
        } elseif (! empty($result['blocked'])) {
            $args['jmrs_delete_blocked'] = '1';
            $args['page']                = 'jm-referrals-view';
            $args['referral_id']         = $referral_id;
        } else {
            $args['jmrs_deleted'] = '0';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles archive form posts.
     */
    public function handle_archive(): void
    {
        if (! is_admin()) {
            return;
        }

        if (! isset($_POST['jmrs_archive_referral'])) {
            return;
        }

        $referral_id = isset($_POST['referral_id']) ? absint($_POST['referral_id']) : 0;
        check_admin_referer('jmrs_archive_referral_' . $referral_id, 'jmrs_archive_nonce');

        $reason = isset($_POST['archive_reason'])
            ? sanitize_textarea_field(wp_unslash($_POST['archive_reason']))
            : '';

        $result = $this->retention_service->archive($referral_id, $reason);

        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];

        if (! empty($result['success'])) {
            $args['jmrs_archived'] = '1';
        } else {
            $args['jmrs_archive_error'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles restore requests.
     */
    public function handle_restore(): void
    {
        if (! is_admin()) {
            return;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';

        if ('restore' !== $action && ! isset($_POST['jmrs_restore_referral'])) {
            return;
        }

        $referral_id = isset($_REQUEST['referral_id']) ? absint($_REQUEST['referral_id']) : 0;
        check_admin_referer('jmrs_restore_referral_' . $referral_id, 'jmrs_restore_nonce');

        $result = $this->retention_service->restore($referral_id);

        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];

        if (! empty($result['success'])) {
            $args['jmrs_restored'] = '1';
        } else {
            $args['jmrs_restore_error'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Renders result notices on list/view screens.
     */
    public function render_notices(): void
    {
        if (! $this->is_list_screen() && ! $this->is_view_screen()) {
            return;
        }

        if (isset($_GET['jmrs_deleted'])) {
            $deleted = sanitize_text_field(wp_unslash($_GET['jmrs_deleted']));

            if ('1' === $deleted) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Referral deleted successfully.', 'jm-referral-system');
                echo '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo esc_html__('Unable to delete the referral.', 'jm-referral-system');
                echo '</p></div>';
            }
        }

        if (isset($_GET['jmrs_delete_blocked'])) {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo esc_html__(
                'This referral contains linked records and cannot be permanently deleted. Archive it instead.',
                'jm-referral-system'
            );
            echo '</p></div>';
        }

        if (isset($_GET['jmrs_archived'])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Referral archived successfully.', 'jm-referral-system');
            echo '</p></div>';
        }

        if (isset($_GET['jmrs_archive_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo esc_html__('Unable to archive the referral.', 'jm-referral-system');
            echo '</p></div>';
        }

        if (isset($_GET['jmrs_restored'])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Referral restored successfully.', 'jm-referral-system');
            echo '</p></div>';
        }

        if (isset($_GET['jmrs_restore_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo esc_html__('Unable to restore the referral.', 'jm-referral-system');
            echo '</p></div>';
        }
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

    /**
     * Builds a nonce-protected restore URL for a referral.
     */
    public static function get_restore_url(int $referral_id): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page'        => 'jm-referrals-view',
                    'action'      => 'restore',
                    'referral_id' => $referral_id,
                ],
                admin_url('admin.php')
            ),
            'jmrs_restore_referral_' . $referral_id,
            'jmrs_restore_nonce'
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

    private function is_view_screen(): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        return 'jm-referrals-view' === $page;
    }
}
