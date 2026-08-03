<?php

namespace JMReferral\Referral;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;
use JMReferral\Workflow\WorkflowStageService;

class ReferralViewController
{
    public function __construct(
        private ReferralRepository $repository,
        private ReferralActivityRepository $activity_repository,
        private ReferralNoteRepository $note_repository,
        private UserProvider $user_provider,
        private ServiceTypeService $service_type_service,
        private WorkflowStageService $workflow_stage_service,
        private ReferralService $referral_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Registers view-related hooks.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_stage_change']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    /**
     * Renders the referral details page.
     */
    public function render(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_REFERRALS)) {
            wp_die(esc_html__('You do not have permission to view referrals.', 'jm-referral-system'));
        }

        $referral_id = isset($_GET['referral_id']) ? absint($_GET['referral_id']) : 0;
        $referral    = $this->repository->find($referral_id);

        if (null === $referral) {
            wp_die(esc_html__('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->access_policy->can_view_referral($referral)) {
            wp_die(esc_html__('You do not have permission to view this referral.', 'jm-referral-system'));
        }

        $activities       = $this->activity_repository->get_by_referral_id($referral_id);
        $assigned_to      = absint($referral['assigned_to'] ?? 0);
        $assigned_to_name = $assigned_to > 0
            ? $this->user_provider->get_display_name($assigned_to)
            : '';

        $service_type_id = absint($referral['service_type_id'] ?? 0);
        $service_name    = (string) ($referral['service_required'] ?? '');
        if ($service_type_id > 0) {
            $service_type = $this->service_type_service->find($service_type_id);
            if (null !== $service_type) {
                $service_name = (string) ($service_type['name'] ?? $service_name);
            }
        }

        $workflow_stage_id   = absint($referral['workflow_stage_id'] ?? 0);
        $workflow_stage_name = '';
        if ($workflow_stage_id > 0) {
            $workflow_stage = $this->workflow_stage_service->find($workflow_stage_id);
            if (null !== $workflow_stage) {
                $workflow_stage_name = (string) ($workflow_stage['name'] ?? '');
            }
        }

        $workflow_stages = $this->workflow_stage_service->get_options_for_referral($workflow_stage_id);

        $notes = [];
        foreach ($this->note_repository->get_by_referral_id($referral_id) as $note_row) {
            $author_id               = absint($note_row['user_id'] ?? 0);
            $note_row['author_name'] = $author_id > 0
                ? $this->user_provider->get_display_name($author_id)
                : '';
            $notes[] = $note_row;
        }

        $note_form_state = ReferralNoteController::get_form_state($referral_id);
        $note_value      = $note_form_state['note'];
        $note_errors     = $note_form_state['errors'];

        include JMRS_PLUGIN_PATH . 'templates/referrals/view.php';
    }

    /**
     * Handles workflow stage changes from the view page.
     */
    public function handle_stage_change(): void
    {
        if (! isset($_POST['jmrs_update_workflow_stage'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)) {
            wp_die(esc_html__('You do not have permission to update referrals.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;

        check_admin_referer('jmrs_update_workflow_stage_' . $referral_id, 'jmrs_update_workflow_stage_nonce');

        $referral = $this->repository->find($referral_id);

        if (null === $referral || ! $this->access_policy->can_edit_referral($referral)) {
            wp_die(esc_html__('You do not have permission to edit this referral.', 'jm-referral-system'));
        }

        $workflow_stage_id = isset($_POST['jmrs_workflow_stage_id'])
            ? absint(wp_unslash($_POST['jmrs_workflow_stage_id']))
            : 0;

        $updated = $this->referral_service->change_workflow_stage($referral_id, $workflow_stage_id);

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'               => 'jm-referrals-view',
                    'referral_id'        => $referral_id,
                    'jmrs_stage_updated' => $updated ? '1' : '0',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Renders stage-update notices on the view screen.
     */
    public function render_notices(): void
    {
        if (! $this->is_view_screen()) {
            return;
        }

        if (! isset($_GET['jmrs_stage_updated'])) {
            return;
        }

        $updated = sanitize_text_field(wp_unslash($_GET['jmrs_stage_updated']));

        if ('1' === $updated) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Workflow stage updated successfully.', 'jm-referral-system');
            echo '</p></div>';
            return;
        }

        echo '<div class="notice notice-error is-dismissible"><p>';
        echo esc_html__('Unable to update the workflow stage.', 'jm-referral-system');
        echo '</p></div>';
    }

    /**
     * Builds the view screen URL for a referral.
     */
    public static function get_view_url(int $referral_id): string
    {
        return add_query_arg(
            [
                'page'        => 'jm-referrals-view',
                'referral_id' => $referral_id,
            ],
            admin_url('admin.php')
        );
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
