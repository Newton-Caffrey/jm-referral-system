<?php

namespace JMReferral\Portal\Clinical;

use JMReferral\CarePlan\ReferralCarePlanRepository;
use JMReferral\CarePlan\ReferralCarePlanReviewController;
use JMReferral\CarePlan\ReferralCarePlanReviewService;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\PortalUrls;

/**
 * Portal handler for the care plan review form.
 */
class CarePlanReviewHandler
{
    public function __construct(
        private PortalViewHost $view_host,
        private ClinicalAccess $clinical_access,
        private ReferralCarePlanReviewController $review_controller,
        private ReferralCarePlanRepository $care_plan_repository
    ) {
    }

    public function handle(int $referral_id): void
    {
        if (! Capabilities::current_user_can(Capabilities::REVIEW_CARE_PLANS)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $access = $this->clinical_access->require_referral($referral_id, true, true);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return;
        }
        $referral = $access['referral'];

        if (! $this->clinical_access->can_review_care_plan($referral)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $care_plan = $this->care_plan_repository->find_by_referral($referral_id);
        if (null === $care_plan) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (isset($_POST['jmrs_submit_care_plan_review'])) {
            $this->handle_post($referral_id);

            return;
        }

        $empty_data = [
            'review_date'      => '',
            'outcome'          => '',
            'notes'            => '',
            'next_review_date' => '',
        ];

        $form_state = ReferralCarePlanReviewController::get_form_state($referral_id, true, 'portal');
        $errors     = $form_state['errors'];
        $data       = ! empty($form_state['data'])
            ? array_merge($empty_data, $form_state['data'])
            : $empty_data;

        $page_title = __('Review Care Plan', 'jm-referral-system');

        $view = [
            'referral'        => $referral,
            'data'            => $data,
            'errors'          => $errors,
            'outcome_options' => ReferralCarePlanReviewService::outcome_labels(),
            'form_action'     => PortalUrls::care_plan_review($referral_id),
            'cancel_url'      => PortalUrls::referral($referral_id),
        ];

        $this->view_host->render_portal_page(
            'referrals/care-plan-review',
            $page_title,
            'referral',
            $this->clinical_access->referral_breadcrumbs($referral, $page_title),
            $view
        );
    }

    private function handle_post(int $referral_id): void
    {
        $nonce = isset($_POST['jmrs_care_plan_review_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_care_plan_review_nonce']))
            : '';

        if (
            ! wp_verify_nonce($nonce, 'jmrs_care_plan_review_' . $referral_id)
            || absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id
        ) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->review_controller->attempt_review($referral_id, $_POST);

        if (! empty($result['not_found']) || ! empty($result['forbidden'])) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (! $result['success']) {
            $this->review_controller->persist_form_state($referral_id, $result['data'], $result['errors'], 'portal');
            wp_safe_redirect(PortalUrls::care_plan_review($referral_id));
            exit;
        }

        wp_safe_redirect(
            add_query_arg('jmrs_care_plan_reviewed', '1', PortalUrls::referral($referral_id))
        );
        exit;
    }
}
