<?php

namespace JMReferral\Assessment;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralRepository;

class ReferralAssessmentController
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_referral_assessment_form_';

    public function __construct(
        private ReferralAssessmentService $assessment_service,
        private ReferralRepository $referral_repository,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Registers assessment save and notice hooks.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_save']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    /**
     * Handles assessment create/update submissions from the referral view page.
     */
    public function handle_save(): void
    {
        if (! isset($_POST['jmrs_save_assessment'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)) {
            wp_die(esc_html__('You do not have permission to save assessments.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;

        check_admin_referer('jmrs_save_assessment_' . $referral_id, 'jmrs_save_assessment_nonce');

        $result = $this->attempt_save($referral_id, $_POST);

        if (! $result['success']) {
            if (! empty($result['forbidden']) || ! empty($result['not_found'])) {
            if (! empty($result['completed'])) {
                wp_die(esc_html__('This assessment has been completed and is read-only.', 'jm-referral-system'));
            }
            wp_die(esc_html__('You do not have permission to save an assessment for this referral.', 'jm-referral-system'));
        }

            $this->store_form_state($referral_id, $result['data'], $result['errors']);
            $this->redirect_to_view($referral_id);
        }

        $this->redirect_to_view(
            $referral_id,
            ! empty($result['created']) ? 'created' : 'updated',
            ! empty($result['pipeline_advanced'])
        );
    }

    /**
     * Shared sanitize → ReferralAssessmentService::save() pipeline for admin and portal.
     *
     * Callers must verify capability and nonce before invoking.
     *
     * @param array<string, mixed> $raw_input
     * @return array{
     *     success: bool,
     *     data: array<string, string>,
     *     errors: array<string, string>,
     *     created: bool,
     *     not_found: bool,
     *     forbidden: bool
     * }
     */
    public function attempt_save(int $referral_id, array $raw_input): array
    {
        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral) {
            return [
                'success'   => false,
                'data'      => [],
                'errors'    => [],
                'created'   => false,
                'not_found' => true,
                'forbidden' => false,
            ];
        }

        if (! $this->access_policy->can_mutate_referral($referral)) {
            return [
                'success'   => false,
                'data'      => [],
                'errors'    => [],
                'created'   => false,
                'not_found' => false,
                'forbidden' => true,
            ];
        }

        $existing = $this->assessment_service->get_for_referral($referral_id);
        if (ReferralAssessmentService::is_completed_assessment($existing)) {
            return [
                'success'   => false,
                'data'      => [],
                'errors'    => [
                    'completed' => __('This assessment has been completed and is read-only.', 'jm-referral-system'),
                ],
                'created'   => false,
                'not_found' => false,
                'forbidden' => true,
                'completed' => true,
            ];
        }

        $data   = $this->sanitize_input($raw_input);
        $result = $this->assessment_service->save($referral_id, $data);

        if (false === $result) {
            return [
                'success'   => false,
                'data'      => $data,
                'errors'    => [
                    'general' => __('Unable to save the assessment. Please try again.', 'jm-referral-system'),
                ],
                'created'   => false,
                'not_found' => false,
                'forbidden' => false,
            ];
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $completed = isset($result['errors']['completed']);

            return [
                'success'   => false,
                'data'      => $completed ? [] : $data,
                'errors'    => $result['errors'],
                'created'   => false,
                'not_found' => false,
                'forbidden' => $completed,
                'completed' => $completed,
            ];
        }

        return [
            'success'            => true,
            'data'               => $data,
            'errors'             => [],
            'created'            => ! empty($result['created']),
            'pipeline_advanced'  => ! empty($result['pipeline_advanced']),
            'not_found'          => false,
            'forbidden'          => false,
        ];
    }

    /**
     * Persist validation errors for PRG redisplay (admin and portal).
     *
     * @param array<string, string> $data
     * @param array<string, string> $errors
     */
    public function persist_form_state(int $referral_id, array $data, array $errors): void
    {
        $this->store_form_state($referral_id, $data, $errors);
    }

    /**
     * Renders assessment success and validation notices on the view screen.
     */
    public function render_notices(): void
    {
        if (! $this->is_view_screen()) {
            return;
        }

        if (isset($_GET['jmrs_assessment_saved'])) {
            $status = sanitize_key(wp_unslash($_GET['jmrs_assessment_saved']));

            if ('created' === $status) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Assessment created successfully.', 'jm-referral-system');
                echo '</p></div>';
            } else            if ('updated' === $status) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Assessment updated successfully.', 'jm-referral-system');
                echo '</p></div>';
            }
        }

        if (isset($_GET['jmrs_assessment_pipeline']) && '1' === sanitize_text_field(wp_unslash($_GET['jmrs_assessment_pipeline']))) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Assessment completed. Next action: Prepare and send package cost.', 'jm-referral-system');
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
     * Returns sticky assessment form data/errors for the current user.
     *
     * @param bool $consume Whether to delete the transient after reading.
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

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function sanitize_input(array $input): array
    {
        $outcome = isset($input['jmrs_assessment_outcome'])
            ? sanitize_key(wp_unslash($input['jmrs_assessment_outcome']))
            : ReferralAssessmentService::OUTCOME_PENDING;

        if (! in_array($outcome, ReferralAssessmentService::allowed_outcomes(), true)) {
            $outcome = ReferralAssessmentService::OUTCOME_PENDING;
        }

        $data = [
            'assessment_date'  => isset($input['jmrs_assessment_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_assessment_date']))
                : '',
            'outcome'          => $outcome,
            'next_review_date' => isset($input['jmrs_assessment_next_review_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_assessment_next_review_date']))
                : '',
        ];

        foreach (ReferralAssessmentService::LONGTEXT_FIELDS as $field) {
            $key = 'jmrs_assessment_' . $field;
            $data[$field] = isset($input[$key])
                ? sanitize_textarea_field(wp_unslash($input[$key]))
                : '';
        }

        foreach (ReferralAssessmentService::SHORTTEXT_FIELDS as $field) {
            $key = 'jmrs_assessment_' . $field;
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

    private function redirect_to_view(int $referral_id, string $saved = '', bool $pipeline_advanced = false): void
    {
        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];

        if ('' !== $saved) {
            $args['jmrs_assessment_saved'] = $saved;
        }

        if ($pipeline_advanced) {
            $args['jmrs_assessment_pipeline'] = '1';
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
