<?php

namespace JMReferral\LocalAuthority;

use JMReferral\Permissions\Capabilities;
use JMReferral\Settings\TerminologySettings;

class LocalAuthorityController
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_local_authority_form_';

    private const ALLOWED_STATUSES = ['active', 'inactive'];

    private const ALLOWED_RULE_TYPES = [
        SenderRuleRepository::TYPE_EXACT_EMAIL,
        SenderRuleRepository::TYPE_DOMAIN,
    ];

    public function __construct(
        private LocalAuthorityService $service
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_create']);
        add_action('admin_init', [$this, 'handle_update']);
        add_action('admin_init', [$this, 'handle_status']);
        add_action('admin_init', [$this, 'handle_add_rule']);
        add_action('admin_init', [$this, 'handle_rule_status']);
        add_action('admin_init', [$this, 'handle_delete_rule']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    public function render_list(): void
    {
        $this->require_capability();

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        if (! in_array($status, ['', 'active', 'inactive'], true)) {
            $status = '';
        }

        $args = [];
        if ('' !== $search) {
            $args['search'] = $search;
        }
        if ('' !== $status) {
            $args['status'] = $status;
        }

        $authorities = $this->service->list($args);
        $la_plural   = TerminologySettings::local_authority_plural();
        $la_singular = TerminologySettings::local_authority_singular();

        include JMRS_PLUGIN_PATH . 'templates/local-authorities/list.php';
    }

    public function render_create(): void
    {
        $this->require_capability();

        $form_state = self::get_form_state('create');
        $data       = ! empty($form_state['data'])
            ? $form_state['data']
            : [
                'name'          => '',
                'status'        => 'active',
                'contact_name'  => '',
                'contact_email' => '',
                'contact_phone' => '',
                'website'       => '',
                'notes'         => '',
            ];
        $errors      = $form_state['errors'];
        $la_singular = TerminologySettings::local_authority_singular();
        $la_plural   = TerminologySettings::local_authority_plural();

        include JMRS_PLUGIN_PATH . 'templates/local-authorities/create.php';
    }

    public function render_edit(): void
    {
        $this->require_capability();

        $authority_id = $this->get_request_authority_id();
        $authority    = $this->service->find($authority_id);

        if (null === $authority) {
            wp_die(
                esc_html(
                    sprintf(
                        /* translators: %s: local authority singular label */
                        __('%s not found.', 'jm-referral-system'),
                        TerminologySettings::local_authority_singular()
                    )
                )
            );
        }

        $form_state = self::get_form_state('edit_' . $authority_id);
        $errors     = $form_state['errors'];
        $data       = ! empty($form_state['data'])
            ? $form_state['data']
            : [
                'name'          => (string) ($authority['name'] ?? ''),
                'status'        => (string) ($authority['status'] ?? 'active'),
                'contact_name'  => (string) ($authority['contact_name'] ?? ''),
                'contact_email' => (string) ($authority['contact_email'] ?? ''),
                'contact_phone' => (string) ($authority['contact_phone'] ?? ''),
                'website'       => (string) ($authority['website'] ?? ''),
                'notes'         => (string) ($authority['notes'] ?? ''),
            ];

        $rules         = $this->service->list_rules($authority_id);
        $rule_form     = self::get_form_state('rule_' . $authority_id);
        $rule_data     = ! empty($rule_form['data'])
            ? $rule_form['data']
            : [
                'rule_type'  => SenderRuleRepository::TYPE_EXACT_EMAIL,
                'rule_value' => '',
            ];
        $rule_errors   = $rule_form['errors'];
        $rule_warnings = is_array($rule_form['warnings'] ?? null) ? $rule_form['warnings'] : [];
        $la_singular   = TerminologySettings::local_authority_singular();
        $la_plural     = TerminologySettings::local_authority_plural();

        include JMRS_PLUGIN_PATH . 'templates/local-authorities/edit.php';
    }

    public function handle_create(): void
    {
        if (! isset($_POST['jmrs_submit_local_authority'])) {
            return;
        }

        $this->require_capability();
        check_admin_referer('jmrs_add_local_authority', 'jmrs_add_local_authority_nonce');

        $data   = $this->sanitize_authority_input($_POST);
        $result = $this->service->create($data);

        if (false === $result) {
            $this->store_form_state(
                'create',
                $data,
                [
                    'general' => __('Unable to save. Please try again.', 'jm-referral-system'),
                ]
            );
            return;
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_form_state('create', $data, $result['errors']);
            return;
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'         => 'jm-referrals-local-authorities',
                    'jmrs_created' => '1',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function handle_update(): void
    {
        if (! isset($_POST['jmrs_update_local_authority'])) {
            return;
        }

        $this->require_capability();

        $authority_id = isset($_POST['jmrs_local_authority_id']) ? absint($_POST['jmrs_local_authority_id']) : 0;
        check_admin_referer('jmrs_edit_local_authority_' . $authority_id, 'jmrs_edit_local_authority_nonce');

        if (null === $this->service->find($authority_id)) {
            wp_die(esc_html__('Organisation not found.', 'jm-referral-system'));
        }

        $data   = $this->sanitize_authority_input($_POST);
        $result = $this->service->update($authority_id, $data);

        if (false === $result) {
            $this->store_form_state(
                'edit_' . $authority_id,
                $data,
                [
                    'general' => __('Unable to update. Please try again.', 'jm-referral-system'),
                ]
            );
            return;
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_form_state('edit_' . $authority_id, $data, $result['errors']);
            return;
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'                => 'jm-referrals-local-authorities-edit',
                    'local_authority_id'  => $authority_id,
                    'jmrs_updated'        => '1',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function handle_status(): void
    {
        if (! $this->is_list_screen()) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        if (! in_array($action, ['activate', 'deactivate'], true)) {
            return;
        }

        $this->require_capability();

        $authority_id = isset($_GET['local_authority_id']) ? absint($_GET['local_authority_id']) : 0;
        check_admin_referer('jmrs_la_status_' . $action . '_' . $authority_id);

        $result = 'activate' === $action
            ? $this->service->activate($authority_id)
            : $this->service->deactivate($authority_id);

        $args = [
            'page' => 'jm-referrals-local-authorities',
        ];

        if (false === $result || isset($result['errors'])) {
            $args['jmrs_status'] = '0';
        } else {
            $args['jmrs_status'] = $action;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public function handle_add_rule(): void
    {
        if (! isset($_POST['jmrs_submit_sender_rule'])) {
            return;
        }

        $this->require_capability();

        $authority_id = isset($_POST['jmrs_local_authority_id']) ? absint($_POST['jmrs_local_authority_id']) : 0;
        check_admin_referer('jmrs_add_sender_rule_' . $authority_id, 'jmrs_add_sender_rule_nonce');

        if (null === $this->service->find($authority_id)) {
            wp_die(esc_html__('Organisation not found.', 'jm-referral-system'));
        }

        $data   = $this->sanitize_rule_input($_POST);
        $result = $this->service->add_sender_rule($authority_id, $data);

        if (false === $result) {
            $this->store_form_state(
                'rule_' . $authority_id,
                $data,
                [
                    'general' => __('Unable to add sender rule. Please try again.', 'jm-referral-system'),
                ]
            );
            wp_safe_redirect(self::get_edit_url($authority_id));
            exit;
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_form_state('rule_' . $authority_id, $data, $result['errors']);
            wp_safe_redirect(self::get_edit_url($authority_id));
            exit;
        }

        $warnings = is_array($result['warnings'] ?? null) ? $result['warnings'] : [];
        if (! empty($warnings)) {
            $this->store_form_state('rule_' . $authority_id, [], [], $warnings);
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'               => 'jm-referrals-local-authorities-edit',
                    'local_authority_id' => $authority_id,
                    'jmrs_rule_added'    => '1',
                    'jmrs_rule_warn'     => ! empty($warnings) ? '1' : '0',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function handle_rule_status(): void
    {
        if (! $this->is_edit_screen()) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        if (! in_array($action, ['activate_rule', 'deactivate_rule'], true)) {
            return;
        }

        $this->require_capability();

        $authority_id = isset($_GET['local_authority_id']) ? absint($_GET['local_authority_id']) : 0;
        $rule_id      = isset($_GET['rule_id']) ? absint($_GET['rule_id']) : 0;
        check_admin_referer('jmrs_rule_status_' . $action . '_' . $rule_id);

        $result = 'activate_rule' === $action
            ? $this->service->activate_rule($rule_id, $authority_id)
            : $this->service->deactivate_rule($rule_id, $authority_id);

        $args = [
            'page'               => 'jm-referrals-local-authorities-edit',
            'local_authority_id' => $authority_id,
        ];

        if (false === $result || isset($result['errors'])) {
            $args['jmrs_rule_status'] = '0';
        } else {
            $args['jmrs_rule_status'] = $action;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public function handle_delete_rule(): void
    {
        if (! $this->is_edit_screen()) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        if ('delete_rule' !== $action) {
            return;
        }

        $this->require_capability();

        $authority_id = isset($_GET['local_authority_id']) ? absint($_GET['local_authority_id']) : 0;
        $rule_id      = isset($_GET['rule_id']) ? absint($_GET['rule_id']) : 0;
        check_admin_referer('jmrs_delete_sender_rule_' . $rule_id);

        $result = $this->service->delete_rule($rule_id, $authority_id);

        $args = [
            'page'               => 'jm-referrals-local-authorities-edit',
            'local_authority_id' => $authority_id,
        ];

        if (false === $result || isset($result['errors'])) {
            $args['jmrs_rule_deleted'] = '0';
        } else {
            $args['jmrs_rule_deleted'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public function render_notices(): void
    {
        if ($this->is_list_screen()) {
            $this->render_list_notices();
            return;
        }

        if ($this->is_create_screen()) {
            $this->render_form_errors('create');
            return;
        }

        if ($this->is_edit_screen()) {
            $authority_id = $this->get_request_authority_id();

            if (isset($_GET['jmrs_updated']) && '1' === $_GET['jmrs_updated']) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Saved successfully.', 'jm-referral-system');
                echo '</p></div>';
            }

            if (isset($_GET['jmrs_rule_added']) && '1' === $_GET['jmrs_rule_added']) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Sender rule added.', 'jm-referral-system');
                echo '</p></div>';
            }

            if (isset($_GET['jmrs_rule_status'])) {
                $status = sanitize_key(wp_unslash($_GET['jmrs_rule_status']));
                if ('0' === $status) {
                    echo '<div class="notice notice-error is-dismissible"><p>';
                    echo esc_html__('Unable to update sender rule status.', 'jm-referral-system');
                    echo '</p></div>';
                } else {
                    echo '<div class="notice notice-success is-dismissible"><p>';
                    echo esc_html__('Sender rule status updated.', 'jm-referral-system');
                    echo '</p></div>';
                }
            }

            if (isset($_GET['jmrs_rule_deleted'])) {
                $deleted = sanitize_key(wp_unslash($_GET['jmrs_rule_deleted']));
                echo '<div class="notice ' . ('1' === $deleted ? 'notice-success' : 'notice-error') . ' is-dismissible"><p>';
                echo esc_html(
                    '1' === $deleted
                        ? __('Sender rule deleted.', 'jm-referral-system')
                        : __('Unable to delete sender rule.', 'jm-referral-system')
                );
                echo '</p></div>';
            }

            $this->render_form_errors('edit_' . $authority_id);
            $this->render_form_errors('rule_' . $authority_id);
            $this->render_rule_warnings('rule_' . $authority_id);
        }
    }

    public static function get_edit_url(int $authority_id): string
    {
        return add_query_arg(
            [
                'page'               => 'jm-referrals-local-authorities-edit',
                'local_authority_id' => $authority_id,
            ],
            admin_url('admin.php')
        );
    }

    public static function get_status_url(int $authority_id, string $action): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page'               => 'jm-referrals-local-authorities',
                    'action'             => $action,
                    'local_authority_id' => $authority_id,
                ],
                admin_url('admin.php')
            ),
            'jmrs_la_status_' . $action . '_' . $authority_id
        );
    }

    public static function get_rule_status_url(int $authority_id, int $rule_id, string $action): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page'               => 'jm-referrals-local-authorities-edit',
                    'local_authority_id' => $authority_id,
                    'action'             => $action,
                    'rule_id'            => $rule_id,
                ],
                admin_url('admin.php')
            ),
            'jmrs_rule_status_' . $action . '_' . $rule_id
        );
    }

    public static function get_delete_rule_url(int $authority_id, int $rule_id): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page'               => 'jm-referrals-local-authorities-edit',
                    'local_authority_id' => $authority_id,
                    'action'             => 'delete_rule',
                    'rule_id'            => $rule_id,
                ],
                admin_url('admin.php')
            ),
            'jmrs_delete_sender_rule_' . $rule_id
        );
    }

    /**
     * @param bool $consume Whether to delete the transient after reading.
     * @return array{data: array<string, string>, errors: array<string, string>, warnings: array<int, string>}
     */
    public static function get_form_state(string $key, bool $consume = true): array
    {
        $transient_key = self::FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $key;
        $state         = get_transient($transient_key);

        if (! is_array($state)) {
            return [
                'data'     => [],
                'errors'   => [],
                'warnings' => [],
            ];
        }

        if ($consume) {
            delete_transient($transient_key);
        }

        return [
            'data'     => is_array($state['data'] ?? null) ? $state['data'] : [],
            'errors'   => is_array($state['errors'] ?? null) ? $state['errors'] : [],
            'warnings' => is_array($state['warnings'] ?? null) ? $state['warnings'] : [],
        ];
    }

    private function require_capability(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to manage Local Authority settings.', 'jm-referral-system'));
        }
    }

    private function render_list_notices(): void
    {
        if (isset($_GET['jmrs_created']) && '1' === $_GET['jmrs_created']) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Created successfully.', 'jm-referral-system');
            echo '</p></div>';
        }

        if (! isset($_GET['jmrs_status'])) {
            return;
        }

        $status = sanitize_key(wp_unslash($_GET['jmrs_status']));
        if ('0' === $status) {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo esc_html__('Unable to update status.', 'jm-referral-system');
            echo '</p></div>';
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>';
        echo esc_html__('Status updated.', 'jm-referral-system');
        echo '</p></div>';
    }

    private function render_form_errors(string $key): void
    {
        $state  = self::get_form_state($key, false);
        $errors = $state['errors'];

        if (empty($errors)) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Please fix the following errors:', 'jm-referral-system');
        echo '</p><ul>';

        foreach ($errors as $message) {
            echo '<li>' . esc_html((string) $message) . '</li>';
        }

        echo '</ul></div>';
    }

    private function render_rule_warnings(string $key): void
    {
        $state    = self::get_form_state($key, false);
        $warnings = $state['warnings'];

        if (empty($warnings)) {
            return;
        }

        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo esc_html__('Overlap notice:', 'jm-referral-system');
        echo '</p><ul>';

        foreach ($warnings as $message) {
            echo '<li>' . esc_html((string) $message) . '</li>';
        }

        echo '</ul></div>';
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function sanitize_authority_input(array $input): array
    {
        $status = isset($input['jmrs_status'])
            ? sanitize_text_field(wp_unslash($input['jmrs_status']))
            : 'active';

        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            $status = 'active';
        }

        $scalar = static function (mixed $value): string {
            if (is_array($value)) {
                return '';
            }

            return sanitize_text_field(wp_unslash((string) $value));
        };

        $notes_raw = $input['jmrs_notes'] ?? '';
        if (is_array($notes_raw)) {
            $notes = '';
        } else {
            $notes = sanitize_textarea_field(wp_unslash((string) $notes_raw));
        }

        return [
            'name'          => $scalar($input['jmrs_name'] ?? ''),
            'status'        => $status,
            'contact_name'  => $scalar($input['jmrs_contact_name'] ?? ''),
            'contact_email' => $scalar($input['jmrs_contact_email'] ?? ''),
            'contact_phone' => $scalar($input['jmrs_contact_phone'] ?? ''),
            'website'       => $scalar($input['jmrs_website'] ?? ''),
            'notes'         => $notes,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function sanitize_rule_input(array $input): array
    {
        $rule_type = isset($input['jmrs_rule_type'])
            ? sanitize_key(wp_unslash($input['jmrs_rule_type']))
            : '';

        if (! in_array($rule_type, self::ALLOWED_RULE_TYPES, true)) {
            $rule_type = '';
        }

        $raw_value = $input['jmrs_rule_value'] ?? '';
        if (is_array($raw_value)) {
            $rule_value = '';
        } else {
            $rule_value = sanitize_text_field(wp_unslash((string) $raw_value));
        }

        return [
            'rule_type'  => $rule_type,
            'rule_value' => $rule_value,
            'status'     => 'active',
        ];
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $errors
     * @param array<int, string>    $warnings
     */
    private function store_form_state(string $key, array $data, array $errors, array $warnings = []): void
    {
        set_transient(
            self::FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $key,
            [
                'data'     => $data,
                'errors'   => $errors,
                'warnings' => $warnings,
            ],
            MINUTE_IN_SECONDS * 5
        );
    }

    private function get_request_authority_id(): int
    {
        if (isset($_POST['jmrs_local_authority_id'])) {
            return absint($_POST['jmrs_local_authority_id']);
        }

        return isset($_GET['local_authority_id']) ? absint($_GET['local_authority_id']) : 0;
    }

    private function is_list_screen(): bool
    {
        return $this->is_screen('jm-referrals-local-authorities');
    }

    private function is_create_screen(): bool
    {
        return $this->is_screen('jm-referrals-local-authorities-add');
    }

    private function is_edit_screen(): bool
    {
        return $this->is_screen('jm-referrals-local-authorities-edit');
    }

    private function is_screen(string $page_slug): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        return $page_slug === $page;
    }
}
