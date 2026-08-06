<?php

namespace JMReferral\Portal\Clinical;

use JMReferral\Medication\MedicationController;
use JMReferral\Medication\MedicationService;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\PortalUrls;

/**
 * Portal handler for medication create/edit forms.
 */
class MedicationHandler
{
    public function __construct(
        private PortalViewHost $view_host,
        private ClinicalAccess $clinical_access,
        private MedicationController $medication_controller,
        private MedicationService $medication_service
    ) {
    }

    public function handle_new(int $referral_id): void
    {
        $this->handle($referral_id, 0);
    }

    public function handle_edit(int $referral_id, int $medication_id): void
    {
        $this->handle($referral_id, $medication_id);
    }

    private function handle(int $referral_id, int $medication_id): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_MEDICATIONS)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $access = $this->clinical_access->require_referral($referral_id, true, true);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return;
        }
        $referral = $access['referral'];

        if (! $this->clinical_access->can_manage_medications($referral)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $medication = null;
        if ($medication_id > 0) {
            $prepared = $this->medication_service->prepare_edit($medication_id);
            if (isset($prepared['errors'])) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }

            $medication = $prepared['medication'];
            if (absint($medication['referral_id'] ?? 0) !== $referral_id) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }
        }

        if (isset($_POST['jmrs_save_medication'])) {
            $this->handle_post($referral_id, $medication_id);

            return;
        }

        $form_state = MedicationController::get_form_state($referral_id, true, 'portal');
        $errors     = $form_state['errors'];
        $data       = (! empty($form_state['data']) && absint($form_state['medication_id'] ?? 0) === $medication_id)
            ? array_merge(MedicationService::empty_form_data(), $form_state['data'])
            : MedicationService::map_to_form_data($medication);

        $page_title = null === $medication
            ? __('Add Medication', 'jm-referral-system')
            : __('Edit Medication', 'jm-referral-system');

        $form_action = $medication_id > 0
            ? PortalUrls::medication_edit($referral_id, $medication_id)
            : PortalUrls::medication_new($referral_id);

        $view = [
            'referral'      => $referral,
            'medication'    => $medication,
            'medication_id' => $medication_id,
            'data'          => $data,
            'errors'        => $errors,
            'status_labels' => MedicationService::status_labels(),
            'route_labels'  => MedicationService::route_labels(),
            'form_action'   => $form_action,
            'cancel_url'    => PortalUrls::referral($referral_id),
            'is_create'     => null === $medication,
        ];

        $this->view_host->render_portal_page(
            'referrals/medication-form',
            $page_title,
            'referral',
            $this->clinical_access->referral_breadcrumbs($referral, $page_title),
            $view
        );
    }

    private function handle_post(int $referral_id, int $medication_id): void
    {
        $nonce = isset($_POST['jmrs_medication_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_medication_nonce']))
            : '';

        if (
            ! wp_verify_nonce($nonce, 'jmrs_save_medication_' . $referral_id)
            || absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id
        ) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $posted_medication_id = isset($_POST['jmrs_medication_id']) ? absint($_POST['jmrs_medication_id']) : 0;
        if ($posted_medication_id !== $medication_id) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->medication_controller->attempt_save($referral_id, $_POST, $medication_id);

        if (! empty($result['not_found']) || ! empty($result['forbidden'])) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $redirect_url = $medication_id > 0
            ? PortalUrls::medication_edit($referral_id, $medication_id)
            : PortalUrls::medication_new($referral_id);

        if (! $result['success']) {
            $this->medication_controller->persist_form_state(
                $referral_id,
                $result['data'],
                $result['errors'],
                $medication_id,
                'portal'
            );
            wp_safe_redirect($redirect_url);
            exit;
        }

        wp_safe_redirect(
            add_query_arg('jmrs_medication_saved', '1', PortalUrls::referral($referral_id))
        );
        exit;
    }
}
