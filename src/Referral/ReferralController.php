<?php

namespace JMReferral\Referral;

use JMReferral\Permissions\Capabilities;

class ReferralController
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_referral_form_';

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
        private ReferralValidator $validator
    ) {
    }

    /**
     * Registers controller hooks.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_create']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    /**
     * Handles Add Referral form submission.
     */
    public function handle_create(): void
    {
        if (! isset($_POST['jmrs_submit_referral'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::CREATE_REFERRALS)) {
            wp_die(esc_html__('You do not have permission to create referrals.', 'jm-referral-system'));
        }

        check_admin_referer('jmrs_add_referral', 'jmrs_add_referral_nonce');

        $data   = $this->sanitize_input($_POST);
        $errors = $this->validator->validate($data);

        if (! empty($errors)) {
            $this->store_form_state($data, $errors);
            return;
        }

        $result = $this->service->create($data);

        if (false === $result) {
            $this->store_form_state(
                $data,
                [
                    'general' => __('Unable to save the referral. Please try again.', 'jm-referral-system'),
                ]
            );
            return;
        }

        $redirect_url = add_query_arg(
            [
                'page'            => 'jm-referrals-add',
                'jmrs_created'    => '1',
                'referral_number' => $result['referral_number'],
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Renders success and error admin notices.
     */
    public function render_notices(): void
    {
        if (! $this->is_add_referral_screen()) {
            return;
        }

        if (isset($_GET['jmrs_created']) && '1' === $_GET['jmrs_created']) {
            $referral_number = isset($_GET['referral_number'])
                ? sanitize_text_field(wp_unslash($_GET['referral_number']))
                : '';

            echo '<div class="notice notice-success is-dismissible"><p>';
            if ('' !== $referral_number) {
                echo esc_html(
                    sprintf(
                        /* translators: %s: referral number */
                        __('Referral %s created successfully.', 'jm-referral-system'),
                        $referral_number
                    )
                );
            } else {
                echo esc_html__('Referral created successfully.', 'jm-referral-system');
            }
            echo '</p></div>';
        }

        $state  = self::get_form_state(false);
        $errors = $state['errors'];

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
     * Returns sticky form data/errors for the current user.
     *
     * @param bool $consume Whether to delete the transient after reading.
     * @return array{data: array<string, string>, errors: array<string, string>}
     */
    public static function get_form_state(bool $consume = true): array
    {
        $key   = self::FORM_TRANSIENT_PREFIX . get_current_user_id();
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
            'priority'         => $priority,
            'status'           => $status,
            'referrer_name'    => isset($input['jmrs_referrer_name'])
                ? sanitize_text_field(wp_unslash($input['jmrs_referrer_name']))
                : '',
            'referrer_email'   => isset($input['jmrs_referrer_email'])
                ? sanitize_email(wp_unslash($input['jmrs_referrer_email']))
                : '',
            'notes'            => isset($input['jmrs_notes'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_notes']))
                : '',
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
    private function store_form_state(array $data, array $errors): void
    {
        set_transient(
            self::FORM_TRANSIENT_PREFIX . get_current_user_id(),
            [
                'data'   => $data,
                'errors' => $errors,
            ],
            MINUTE_IN_SECONDS * 5
        );
    }

    private function is_add_referral_screen(): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        return 'jm-referrals-add' === $page;
    }
}
