<?php

namespace JMReferral\CarePlan;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralRepository;

class ReferralCarePlanController
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_referral_care_plan_form_';

    public function __construct(
        private ReferralCarePlanService $care_plan_service,
        private ReferralRepository $referral_repository,
        private AccessPolicy $access_policy
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_generate']);
        add_action('admin_init', [$this, 'handle_blank']);
        add_action('admin_init', [$this, 'handle_save']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    public function handle_generate(): void
    {
        if (! isset($_POST['jmrs_generate_care_plan'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_CARE_PLANS)) {
            wp_die(esc_html__('You do not have permission to manage care plans.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        check_admin_referer('jmrs_generate_care_plan_' . $referral_id, 'jmrs_generate_care_plan_nonce');

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_edit_referral($referral)) {
            wp_die(esc_html__('You do not have permission to manage a care plan for this referral.', 'jm-referral-system'));
        }

        $result = $this->care_plan_service->generate_from_assessment($referral_id);

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_form_state($referral_id, [], $result['errors'], false);
            $this->redirect_to_view($referral_id);
        }

        $this->store_form_state($referral_id, $result['data'] ?? [], [], true);
        $this->redirect_to_view($referral_id, '', true);
    }

    public function handle_blank(): void
    {
        if (! isset($_POST['jmrs_blank_care_plan'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_CARE_PLANS)) {
            wp_die(esc_html__('You do not have permission to manage care plans.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        check_admin_referer('jmrs_blank_care_plan_' . $referral_id, 'jmrs_blank_care_plan_nonce');

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_edit_referral($referral)) {
            wp_die(esc_html__('You do not have permission to manage a care plan for this referral.', 'jm-referral-system'));
        }

        $result = $this->care_plan_service->prepare_blank($referral_id);

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_form_state($referral_id, [], $result['errors'], false);
            $this->redirect_to_view($referral_id);
        }

        $this->store_form_state($referral_id, $result['data'] ?? [], [], true);
        $this->redirect_to_view($referral_id, '', true);
    }

    public function handle_save(): void
    {
        if (! isset($_POST['jmrs_save_care_plan'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_CARE_PLANS)) {
            wp_die(esc_html__('You do not have permission to manage care plans.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        check_admin_referer('jmrs_save_care_plan_' . $referral_id, 'jmrs_save_care_plan_nonce');

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_edit_referral($referral)) {
            wp_die(esc_html__('You do not have permission to manage a care plan for this referral.', 'jm-referral-system'));
        }

        $data   = $this->sanitize_input($_POST);
        $result = $this->care_plan_service->save($referral_id, $data);

        if (false === $result) {
            $this->store_form_state(
                $referral_id,
                $data,
                [
                    'general' => __('Unable to save the care plan. Please try again.', 'jm-referral-system'),
                ]
            );
            $this->redirect_to_view($referral_id, '', true);
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_form_state($referral_id, $data, $result['errors']);
            $this->redirect_to_view($referral_id, '', true);
        }

        $created = ! empty($result['created']);
        $this->redirect_to_view($referral_id, $created ? 'created' : 'updated');
    }

    public function render_notices(): void
    {
        if (! $this->is_view_screen()) {
            return;
        }

        if (isset($_GET['jmrs_care_plan_saved'])) {
            $status = sanitize_key(wp_unslash($_GET['jmrs_care_plan_saved']));

            if ('created' === $status) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Care plan created successfully.', 'jm-referral-system');
                echo '</p></div>';
            } elseif ('updated' === $status) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Care plan updated successfully.', 'jm-referral-system');
                echo '</p></div>';
            }
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
     * @param bool $consume
     * @return array{data: array<string, string>, errors: array<string, string>, drafting: bool}
     */
    public static function get_form_state(int $referral_id, bool $consume = true): array
    {
        $key   = self::FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $referral_id;
        $state = get_transient($key);

        if (! is_array($state)) {
            return [
                'data'     => [],
                'errors'   => [],
                'drafting' => false,
            ];
        }

        if ($consume) {
            delete_transient($key);
        }

        return [
            'data'     => is_array($state['data'] ?? null) ? $state['data'] : [],
            'errors'   => is_array($state['errors'] ?? null) ? $state['errors'] : [],
            'drafting' => ! empty($state['drafting']),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function sanitize_input(array $input): array
    {
        $status = isset($input['jmrs_care_plan_status'])
            ? sanitize_key(wp_unslash($input['jmrs_care_plan_status']))
            : ReferralCarePlanService::STATUS_DRAFT;

        if (! in_array($status, ReferralCarePlanService::allowed_statuses(), true)) {
            $status = ReferralCarePlanService::STATUS_DRAFT;
        }

        $data = [
            'plan_status'   => $status,
            'start_date'    => isset($input['jmrs_care_plan_start_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_care_plan_start_date']))
                : '',
            'review_date'   => isset($input['jmrs_care_plan_review_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_care_plan_review_date']))
                : '',
            'assessment_id' => isset($input['jmrs_care_plan_assessment_id'])
                ? (string) absint(wp_unslash($input['jmrs_care_plan_assessment_id']))
                : '',
        ];

        foreach (ReferralCarePlanService::LONGTEXT_FIELDS as $field) {
            $key = 'jmrs_care_plan_' . $field;
            $data[$field] = isset($input[$key])
                ? sanitize_textarea_field(wp_unslash($input[$key]))
                : '';
        }

        foreach (ReferralCarePlanService::SHORTTEXT_FIELDS as $field) {
            $key = 'jmrs_care_plan_' . $field;
            $data[$field] = isset($input[$key])
                ? sanitize_text_field(wp_unslash($input[$key]))
                : '';
        }

        return $data;
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $errors
     */
    private function store_form_state(int $referral_id, array $data, array $errors, bool $drafting = true): void
    {
        set_transient(
            self::FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $referral_id,
            [
                'data'     => $data,
                'errors'   => $errors,
                'drafting' => $drafting,
            ],
            MINUTE_IN_SECONDS * 30
        );
    }

    private function redirect_to_view(int $referral_id, string $saved = '', bool $edit = false): void
    {
        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];

        if ('' !== $saved) {
            $args['jmrs_care_plan_saved'] = $saved;
        }

        if ($edit) {
            $args['jmrs_care_plan_edit'] = '1';
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
