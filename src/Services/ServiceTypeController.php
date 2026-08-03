<?php

namespace JMReferral\Services;

use JMReferral\Permissions\Capabilities;

class ServiceTypeController
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_service_type_form_';

    private const ALLOWED_STATUSES = [
        'active',
        'inactive',
    ];

    public function __construct(
        private ServiceTypeService $service
    ) {
    }

    /**
     * Registers controller hooks.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_create']);
        add_action('admin_init', [$this, 'handle_update']);
        add_action('admin_init', [$this, 'handle_delete']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    /**
     * Renders the service types list page.
     */
    public function render_list(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SERVICE_TYPES)) {
            wp_die(esc_html__('You do not have permission to manage service types.', 'jm-referral-system'));
        }

        $service_types = $this->service->all();

        include JMRS_PLUGIN_PATH . 'templates/services/list.php';
    }

    /**
     * Renders the add service type page.
     */
    public function render_create(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SERVICE_TYPES)) {
            wp_die(esc_html__('You do not have permission to manage service types.', 'jm-referral-system'));
        }

        $form_state = self::get_form_state('create');
        $data       = ! empty($form_state['data'])
            ? $form_state['data']
            : [
                'name'        => '',
                'description' => '',
                'status'      => 'active',
            ];
        $errors = $form_state['errors'];

        include JMRS_PLUGIN_PATH . 'templates/services/create.php';
    }

    /**
     * Renders the edit service type page.
     */
    public function render_edit(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SERVICE_TYPES)) {
            wp_die(esc_html__('You do not have permission to manage service types.', 'jm-referral-system'));
        }

        $service_type_id = $this->get_request_service_type_id();
        $service_type    = $this->service->find($service_type_id);

        if (null === $service_type) {
            wp_die(esc_html__('Service type not found.', 'jm-referral-system'));
        }

        $form_state = self::get_form_state('edit_' . $service_type_id);
        $errors     = $form_state['errors'];
        $data       = ! empty($form_state['data'])
            ? $form_state['data']
            : [
                'name'        => (string) ($service_type['name'] ?? ''),
                'description' => (string) ($service_type['description'] ?? ''),
                'status'      => (string) ($service_type['status'] ?? 'active'),
            ];

        include JMRS_PLUGIN_PATH . 'templates/services/edit.php';
    }

    /**
     * Handles create form submission.
     */
    public function handle_create(): void
    {
        if (! isset($_POST['jmrs_submit_service_type'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_SERVICE_TYPES)) {
            wp_die(esc_html__('You do not have permission to manage service types.', 'jm-referral-system'));
        }

        check_admin_referer('jmrs_add_service_type', 'jmrs_add_service_type_nonce');

        $data   = $this->sanitize_input($_POST);
        $result = $this->service->create($data);

        if (false === $result) {
            $this->store_form_state(
                'create',
                $data,
                [
                    'general' => __('Unable to save the service type. Please try again.', 'jm-referral-system'),
                ]
            );
            return;
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_form_state('create', $data, $result['errors']);
            return;
        }

        $redirect_url = add_query_arg(
            [
                'page'         => 'jm-referrals-service-types',
                'jmrs_created' => '1',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Handles edit form submission.
     */
    public function handle_update(): void
    {
        if (! isset($_POST['jmrs_update_service_type'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_SERVICE_TYPES)) {
            wp_die(esc_html__('You do not have permission to manage service types.', 'jm-referral-system'));
        }

        $service_type_id = isset($_POST['jmrs_service_type_id']) ? absint($_POST['jmrs_service_type_id']) : 0;

        check_admin_referer('jmrs_edit_service_type_' . $service_type_id, 'jmrs_edit_service_type_nonce');

        if (null === $this->service->find($service_type_id)) {
            wp_die(esc_html__('Service type not found.', 'jm-referral-system'));
        }

        $data   = $this->sanitize_input($_POST);
        $result = $this->service->update($service_type_id, $data);

        if (false === $result) {
            $this->store_form_state(
                'edit_' . $service_type_id,
                $data,
                [
                    'general' => __('Unable to update the service type. Please try again.', 'jm-referral-system'),
                ]
            );
            return;
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_form_state('edit_' . $service_type_id, $data, $result['errors']);
            return;
        }

        $redirect_url = add_query_arg(
            [
                'page'            => 'jm-referrals-service-types-edit',
                'service_type_id' => $service_type_id,
                'jmrs_updated'    => '1',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Handles delete requests from the list page.
     */
    public function handle_delete(): void
    {
        if (! $this->is_list_screen()) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';

        if ('delete' !== $action) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_SERVICE_TYPES)) {
            wp_die(esc_html__('You do not have permission to manage service types.', 'jm-referral-system'));
        }

        $service_type_id = isset($_GET['service_type_id']) ? absint($_GET['service_type_id']) : 0;

        check_admin_referer('jmrs_delete_service_type_' . $service_type_id);

        $result = $this->service->delete($service_type_id);

        $args = [
            'page' => 'jm-referrals-service-types',
        ];

        if (false === $result) {
            $args['jmrs_deleted'] = '0';
        } elseif (isset($result['errors'])) {
            $args['jmrs_deleted'] = 'blocked';
        } else {
            $args['jmrs_deleted'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Renders admin notices for service type screens.
     */
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
            if (isset($_GET['jmrs_updated']) && '1' === $_GET['jmrs_updated']) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Service type updated successfully.', 'jm-referral-system');
                echo '</p></div>';
            }

            $service_type_id = $this->get_request_service_type_id();
            $this->render_form_errors('edit_' . $service_type_id);
        }
    }

    /**
     * Builds the edit screen URL for a service type.
     */
    public static function get_edit_url(int $service_type_id): string
    {
        return add_query_arg(
            [
                'page'            => 'jm-referrals-service-types-edit',
                'service_type_id' => $service_type_id,
            ],
            admin_url('admin.php')
        );
    }

    /**
     * Builds a nonce-protected delete URL for a service type.
     */
    public static function get_delete_url(int $service_type_id): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page'            => 'jm-referrals-service-types',
                    'action'          => 'delete',
                    'service_type_id' => $service_type_id,
                ],
                admin_url('admin.php')
            ),
            'jmrs_delete_service_type_' . $service_type_id
        );
    }

    /**
     * @param bool $consume Whether to delete the transient after reading.
     * @return array{data: array<string, string>, errors: array<string, string>}
     */
    public static function get_form_state(string $key, bool $consume = true): array
    {
        $transient_key = self::FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $key;
        $state         = get_transient($transient_key);

        if (! is_array($state)) {
            return [
                'data'   => [],
                'errors' => [],
            ];
        }

        if ($consume) {
            delete_transient($transient_key);
        }

        return [
            'data'   => is_array($state['data'] ?? null) ? $state['data'] : [],
            'errors' => is_array($state['errors'] ?? null) ? $state['errors'] : [],
        ];
    }

    private function render_list_notices(): void
    {
        if (isset($_GET['jmrs_created']) && '1' === $_GET['jmrs_created']) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Service type created successfully.', 'jm-referral-system');
            echo '</p></div>';
        }

        if (! isset($_GET['jmrs_deleted'])) {
            return;
        }

        $deleted = sanitize_text_field(wp_unslash($_GET['jmrs_deleted']));

        if ('1' === $deleted) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Service type deleted successfully.', 'jm-referral-system');
            echo '</p></div>';
            return;
        }

        if ('blocked' === $deleted) {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo esc_html__('This service type cannot be deleted because it is used by one or more referrals.', 'jm-referral-system');
            echo '</p></div>';
            return;
        }

        echo '<div class="notice notice-error is-dismissible"><p>';
        echo esc_html__('Unable to delete the service type.', 'jm-referral-system');
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
            echo '<li>' . esc_html($message) . '</li>';
        }

        echo '</ul></div>';
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function sanitize_input(array $input): array
    {
        $status = isset($input['jmrs_status'])
            ? sanitize_text_field(wp_unslash($input['jmrs_status']))
            : 'active';

        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            $status = 'active';
        }

        return [
            'name'        => isset($input['jmrs_name'])
                ? sanitize_text_field(wp_unslash($input['jmrs_name']))
                : '',
            'description' => isset($input['jmrs_description'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_description']))
                : '',
            'status'      => $status,
        ];
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $errors
     */
    private function store_form_state(string $key, array $data, array $errors): void
    {
        set_transient(
            self::FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $key,
            [
                'data'   => $data,
                'errors' => $errors,
            ],
            MINUTE_IN_SECONDS * 5
        );
    }

    private function get_request_service_type_id(): int
    {
        if (isset($_POST['jmrs_service_type_id'])) {
            return absint($_POST['jmrs_service_type_id']);
        }

        return isset($_GET['service_type_id']) ? absint($_GET['service_type_id']) : 0;
    }

    private function is_list_screen(): bool
    {
        return $this->is_screen('jm-referrals-service-types');
    }

    private function is_create_screen(): bool
    {
        return $this->is_screen('jm-referrals-service-types-add');
    }

    private function is_edit_screen(): bool
    {
        return $this->is_screen('jm-referrals-service-types-edit');
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
