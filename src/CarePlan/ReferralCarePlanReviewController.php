<?php

namespace JMReferral\CarePlan;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;

class ReferralCarePlanReviewController
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_care_plan_review_form_';

    public function __construct(
        private ReferralCarePlanReviewService $review_service,
        private ReferralRepository $referral_repository,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_review']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    public function handle_review(): void
    {
        if (! isset($_POST['jmrs_submit_care_plan_review'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::REVIEW_CARE_PLANS)) {
            wp_die(esc_html__('You do not have permission to review care plans.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        check_admin_referer('jmrs_care_plan_review_' . $referral_id, 'jmrs_care_plan_review_nonce');

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_edit_referral($referral)) {
            wp_die(esc_html__('You do not have permission to review this care plan.', 'jm-referral-system'));
        }

        $data   = $this->sanitize_input($_POST);
        $result = $this->review_service->add_review($referral_id, $data);

        if (false === $result) {
            $this->store_form_state(
                $referral_id,
                $data,
                [
                    'general' => __('Unable to save the care plan review. Please try again.', 'jm-referral-system'),
                ]
            );
            $this->redirect_to_view($referral_id);
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_form_state($referral_id, $data, $result['errors']);
            $this->redirect_to_view($referral_id);
        }

        $this->redirect_to_view($referral_id, true);
    }

    /**
     * Renders a read-only historical care plan snapshot.
     */
    public function render_version(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_CARE_PLANS)) {
            wp_die(esc_html__('You do not have permission to view care plans.', 'jm-referral-system'));
        }

        $version_id = isset($_GET['version_id']) ? absint($_GET['version_id']) : 0;
        $prepared   = $this->review_service->prepare_version_view($version_id);

        if (isset($prepared['errors']) && is_array($prepared['errors'])) {
            $message = (string) reset($prepared['errors']);
            wp_die(esc_html('' !== $message ? $message : __('Unable to view this care plan version.', 'jm-referral-system')));
        }

        $version    = $prepared['version'];
        $care_plan  = $prepared['care_plan'];
        $referral   = $prepared['referral'];
        $snapshot   = $prepared['snapshot'];
        $created_by = absint($version['created_by'] ?? 0);
        $created_by_name = $created_by > 0
            ? $this->user_provider->get_display_name($created_by)
            : '';
        $status_labels = ReferralCarePlanService::status_labels();
        $back_url = add_query_arg(
            [
                'page'        => 'jm-referrals-view',
                'referral_id' => absint($referral['id'] ?? 0),
            ],
            admin_url('admin.php')
        );

        include JMRS_PLUGIN_PATH . 'templates/care-plans/version.php';
    }

    public function render_notices(): void
    {
        if (! $this->is_view_screen()) {
            return;
        }

        if (isset($_GET['jmrs_care_plan_reviewed']) && '1' === $_GET['jmrs_care_plan_reviewed']) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Care plan review recorded successfully.', 'jm-referral-system');
            echo '</p></div>';
        }

        $referral_id = isset($_GET['referral_id']) ? absint($_GET['referral_id']) : 0;
        $state       = self::get_form_state($referral_id, false);
        $errors      = $state['errors'];

        if (empty($errors)) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Please fix the following errors:', 'jm-referral-system');
        echo '</p><ul>';

        foreach ($errors as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }

        echo '</ul></div>';
    }

    /**
     * @return array{data: array<string, string>, errors: array<string, string>}
     */
    public static function get_form_state(int $referral_id, bool $consume = true): array
    {
        $key   = self::FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $referral_id;
        $state = get_transient($key);

        if (! is_array($state)) {
            return [
                'data'   => [],
                'errors' => [],
            ];
        }

        if ($consume) {
            delete_transient($key);
        }

        return [
            'data'   => is_array($state['data'] ?? null) ? $state['data'] : [],
            'errors' => is_array($state['errors'] ?? null) ? $state['errors'] : [],
        ];
    }

    public static function get_version_url(int $version_id): string
    {
        return add_query_arg(
            [
                'page'       => 'jm-referrals-care-plan-version',
                'version_id' => $version_id,
            ],
            admin_url('admin.php')
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function sanitize_input(array $input): array
    {
        $outcome = isset($input['jmrs_care_plan_review_outcome'])
            ? sanitize_key(wp_unslash($input['jmrs_care_plan_review_outcome']))
            : '';

        if (! in_array($outcome, ReferralCarePlanReviewService::allowed_outcomes(), true)) {
            $outcome = '';
        }

        return [
            'review_date'      => isset($input['jmrs_care_plan_review_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_care_plan_review_date']))
                : '',
            'outcome'          => $outcome,
            'notes'            => isset($input['jmrs_care_plan_review_notes'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_care_plan_review_notes']))
                : '',
            'next_review_date' => isset($input['jmrs_care_plan_review_next_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_care_plan_review_next_date']))
                : '',
        ];
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $errors
     */
    private function store_form_state(int $referral_id, array $data, array $errors): void
    {
        set_transient(
            self::FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $referral_id,
            [
                'data'   => $data,
                'errors' => $errors,
            ],
            MINUTE_IN_SECONDS * 5
        );
    }

    private function redirect_to_view(int $referral_id, bool $success = false): void
    {
        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];

        if ($success) {
            $args['jmrs_care_plan_reviewed'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
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
