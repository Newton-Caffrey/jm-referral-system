<?php

namespace JMReferral\Referral;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;
use JMReferral\Workflow\WorkflowStageService;

class ReferralEditController
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_referral_edit_form_';

    private const ALLOWED_PRIORITIES = [
        'low',
        'medium',
        'high',
        'urgent',
    ];

    private const ALLOWED_STATUSES = [
        'new',
        'in_progress',
        'completed',
        'cancelled',
    ];

    public function __construct(
        private ReferralService $service,
        private ReferralValidator $validator,
        private ReferralRepository $repository,
        private UserProvider $user_provider,
        private ServiceTypeService $service_type_service,
        private WorkflowStageService $workflow_stage_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Registers edit-related hooks.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_update']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    /**
     * Renders the edit referral page.
     */
    public function render(): void
    {
        if (! Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)) {
            wp_die(esc_html__('You do not have permission to edit referrals.', 'jm-referral-system'));
        }

        $referral_id = $this->get_request_referral_id();
        $referral    = $this->repository->find($referral_id);

        if (null === $referral) {
            wp_die(esc_html__('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->access_policy->can_mutate_referral($referral)) {
            wp_die(esc_html__('You do not have permission to edit this referral.', 'jm-referral-system'));
        }

        $form_state        = self::get_form_state($referral_id);
        $errors            = $form_state['errors'];
        $data              = ! empty($form_state['data'])
            ? $form_state['data']
            : $this->map_referral_to_form_data($referral);
        $assignable_users  = $this->user_provider->get_assignable_users();
        $service_types     = $this->service_type_service->get_options_for_referral(
            absint($data['service_type_id'] ?? 0)
        );
        $workflow_stages   = $this->workflow_stage_service->get_options_for_referral(
            absint($data['workflow_stage_id'] ?? 0)
        );

        include JMRS_PLUGIN_PATH . 'templates/referrals/edit.php';
    }

    /**
     * Handles edit form submission.
     */
    public function handle_update(): void
    {
        if (! isset($_POST['jmrs_update_referral'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)) {
            wp_die(esc_html__('You do not have permission to edit referrals.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;

        check_admin_referer('jmrs_edit_referral_' . $referral_id, 'jmrs_edit_referral_nonce');

        $existing = $this->repository->find($referral_id);

        if (null === $existing) {
            wp_die(esc_html__('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->access_policy->can_mutate_referral($existing)) {
            wp_die(esc_html__('You do not have permission to edit this referral.', 'jm-referral-system'));
        }

        $data                            = $this->sanitize_input($_POST);
        $data['current_service_type_id'] = (string) absint($existing['service_type_id'] ?? 0);
        $data['current_workflow_stage_id'] = (string) absint($existing['workflow_stage_id'] ?? 0);
        $errors                          = $this->validator->validate($data);

        if (! empty($errors)) {
            $this->store_form_state($referral_id, $data, $errors);
            return;
        }

        $updated = $this->service->update($referral_id, $data);

        if (! $updated) {
            $this->store_form_state(
                $referral_id,
                $data,
                [
                    'general' => __('Unable to update the referral. Please try again.', 'jm-referral-system'),
                ]
            );
            return;
        }

        $redirect_url = add_query_arg(
            [
                'page'        => 'jm-referrals-edit',
                'referral_id' => $referral_id,
                'jmrs_updated' => '1',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Renders success and error notices on the edit screen.
     */
    public function render_notices(): void
    {
        if (! $this->is_edit_screen()) {
            return;
        }

        if (isset($_GET['jmrs_updated']) && '1' === $_GET['jmrs_updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Referral updated successfully.', 'jm-referral-system');
            echo '</p></div>';
        }

        $referral_id = $this->get_request_referral_id();
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
     * Builds the edit screen URL for a referral.
     */
    public static function get_edit_url(int $referral_id): string
    {
        return add_query_arg(
            [
                'page'        => 'jm-referrals-edit',
                'referral_id' => $referral_id,
            ],
            admin_url('admin.php')
        );
    }

    /**
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
     * @param array<string, mixed> $referral
     * @return array<string, string>
     */
    private function map_referral_to_form_data(array $referral): array
    {
        return [
            'client_name'      => (string) ($referral['client_name'] ?? ''),
            'client_email'     => (string) ($referral['client_email'] ?? ''),
            'client_phone'             => (string) ($referral['client_phone'] ?? ''),
            'service_type_id'          => (string) absint($referral['service_type_id'] ?? 0),
            'current_service_type_id'  => (string) absint($referral['service_type_id'] ?? 0),
            'workflow_stage_id'        => (string) absint($referral['workflow_stage_id'] ?? 0),
            'current_workflow_stage_id'=> (string) absint($referral['workflow_stage_id'] ?? 0),
            'priority'                 => (string) ($referral['priority'] ?? 'medium'),
            'referrer_name'    => (string) ($referral['referrer_name'] ?? ''),
            'referrer_email'   => (string) ($referral['referrer_email'] ?? ''),
            'notes'            => (string) ($referral['notes'] ?? ''),
            'status'           => (string) ($referral['status'] ?? 'new'),
            'assigned_to'              => (string) absint($referral['assigned_to'] ?? 0),
            'referral_source'          => (string) ($referral['referral_source'] ?? ''),
            'care_start_date'          => (string) ($referral['care_start_date'] ?? ''),
            'preferred_contact_method' => (string) ($referral['preferred_contact_method'] ?? ''),
            'care_requirements'        => (string) ($referral['care_requirements'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $input Raw request data.
     * @return array<string, string>
     */
    private function sanitize_input(array $input): array
    {
        $priority = isset($input['jmrs_priority'])
            ? sanitize_text_field(wp_unslash($input['jmrs_priority']))
            : 'medium';

        if (! in_array($priority, self::ALLOWED_PRIORITIES, true)) {
            $priority = 'medium';
        }

        $status = isset($input['jmrs_status'])
            ? sanitize_text_field(wp_unslash($input['jmrs_status']))
            : 'new';

        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            $status = 'new';
        }

        $referral_source = isset($input['jmrs_referral_source'])
            ? sanitize_text_field(wp_unslash($input['jmrs_referral_source']))
            : '';
        if ('' !== $referral_source && ! ReferralSources::is_valid($referral_source)) {
            $referral_source = '';
        }

        $preferred_contact_method = isset($input['jmrs_preferred_contact_method'])
            ? sanitize_text_field(wp_unslash($input['jmrs_preferred_contact_method']))
            : '';
        if ('' !== $preferred_contact_method && ! PreferredContactMethods::is_valid($preferred_contact_method)) {
            $preferred_contact_method = '';
        }

        return [
            'client_name'      => isset($input['jmrs_client_name'])
                ? sanitize_text_field(wp_unslash($input['jmrs_client_name']))
                : '',
            'client_email'     => isset($input['jmrs_client_email'])
                ? sanitize_email(wp_unslash($input['jmrs_client_email']))
                : '',
            'client_phone'     => isset($input['jmrs_client_phone'])
                ? sanitize_text_field(wp_unslash($input['jmrs_client_phone']))
                : '',
            'service_type_id'  => isset($input['jmrs_service_type_id'])
                ? (string) absint(wp_unslash($input['jmrs_service_type_id']))
                : '0',
            'workflow_stage_id'=> isset($input['jmrs_workflow_stage_id'])
                ? (string) absint(wp_unslash($input['jmrs_workflow_stage_id']))
                : '0',
            'priority'         => $priority,
            'referrer_name'    => isset($input['jmrs_referrer_name'])
                ? sanitize_text_field(wp_unslash($input['jmrs_referrer_name']))
                : '',
            'referrer_email'   => isset($input['jmrs_referrer_email'])
                ? sanitize_email(wp_unslash($input['jmrs_referrer_email']))
                : '',
            'notes'            => isset($input['jmrs_notes'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_notes']))
                : '',
            'status'           => $status,
            'assigned_to'      => isset($input['jmrs_assigned_to'])
                ? (string) absint(wp_unslash($input['jmrs_assigned_to']))
                : '0',
            'referral_source'          => $referral_source,
            'care_start_date'          => isset($input['jmrs_care_start_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_care_start_date']))
                : '',
            'preferred_contact_method' => $preferred_contact_method,
            'care_requirements'        => isset($input['jmrs_care_requirements'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_care_requirements']))
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

    private function get_request_referral_id(): int
    {
        if (isset($_POST['jmrs_referral_id'])) {
            return absint($_POST['jmrs_referral_id']);
        }

        return isset($_GET['referral_id']) ? absint($_GET['referral_id']) : 0;
    }

    private function is_edit_screen(): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        return 'jm-referrals-edit' === $page;
    }
}
