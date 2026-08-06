<?php

namespace JMReferral\Portal\Clinical;

use JMReferral\CareTeam\CareTeamController;
use JMReferral\CareTeam\CareTeamService;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\PortalUrls;
use JMReferral\Users\UserProvider;

/**
 * Portal handler for care team assignment create/edit forms.
 */
class CareTeamHandler
{
    public function __construct(
        private PortalViewHost $view_host,
        private ClinicalAccess $clinical_access,
        private CareTeamController $care_team_controller,
        private CareTeamService $care_team_service,
        private UserProvider $user_provider
    ) {
    }

    public function handle_new(int $referral_id): void
    {
        $this->handle($referral_id, 0);
    }

    public function handle_edit(int $referral_id, int $assignment_id): void
    {
        $this->handle($referral_id, $assignment_id);
    }

    private function handle(int $referral_id, int $assignment_id): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_CARE_TEAM)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $access = $this->clinical_access->require_referral($referral_id, true, true);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return;
        }
        $referral = $access['referral'];

        if (! $this->clinical_access->can_manage_care_team($referral)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $assignment = null;
        if ($assignment_id > 0) {
            $prepared = $this->care_team_service->prepare_edit($assignment_id);
            if (isset($prepared['errors'])) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }

            $assignment = $prepared['assignment'];
            if (absint($assignment['referral_id'] ?? 0) !== $referral_id) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }
        }

        if (isset($_POST['jmrs_save_care_team'])) {
            $this->handle_post($referral_id, $assignment_id);

            return;
        }

        $form_state = CareTeamController::get_form_state($referral_id, true, 'portal');
        $errors     = $form_state['errors'];
        $data       = (! empty($form_state['data']) && absint($form_state['assignment_id'] ?? 0) === $assignment_id)
            ? array_merge(CareTeamService::empty_form_data(), $form_state['data'])
            : CareTeamService::map_to_form_data($assignment);

        $page_title = null === $assignment
            ? __('Assign Care Team Member', 'jm-referral-system')
            : __('Edit Care Team Assignment', 'jm-referral-system');

        $form_action = $assignment_id > 0
            ? PortalUrls::care_team_edit($referral_id, $assignment_id)
            : PortalUrls::care_team_new($referral_id);

        $view = [
            'referral'         => $referral,
            'assignment'       => $assignment,
            'assignment_id'    => $assignment_id,
            'data'             => $data,
            'errors'           => $errors,
            'assignable_users' => $this->user_provider->get_assignable_users(),
            'role_labels'      => CareTeamService::role_labels(),
            'status_labels'    => CareTeamService::status_labels(),
            'form_action'      => $form_action,
            'cancel_url'       => PortalUrls::referral($referral_id),
            'is_create'        => null === $assignment,
        ];

        $this->view_host->render_portal_page(
            'referrals/care-team-form',
            $page_title,
            'referral',
            $this->clinical_access->referral_breadcrumbs($referral, $page_title),
            $view
        );
    }

    private function handle_post(int $referral_id, int $assignment_id): void
    {
        $nonce = isset($_POST['jmrs_care_team_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_care_team_nonce']))
            : '';

        if (
            ! wp_verify_nonce($nonce, 'jmrs_save_care_team_' . $referral_id)
            || absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id
        ) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $posted_assignment_id = isset($_POST['jmrs_care_team_id']) ? absint($_POST['jmrs_care_team_id']) : 0;
        if ($posted_assignment_id !== $assignment_id) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->care_team_controller->attempt_save($referral_id, $_POST, $assignment_id);

        if (! empty($result['not_found']) || ! empty($result['forbidden'])) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $redirect_url = $assignment_id > 0
            ? PortalUrls::care_team_edit($referral_id, $assignment_id)
            : PortalUrls::care_team_new($referral_id);

        if (! $result['success']) {
            $this->care_team_controller->persist_form_state(
                $referral_id,
                $result['data'],
                $result['errors'],
                $assignment_id,
                'portal'
            );
            wp_safe_redirect($redirect_url);
            exit;
        }

        wp_safe_redirect(
            add_query_arg('jmrs_care_team_saved', '1', PortalUrls::referral($referral_id))
        );
        exit;
    }
}
