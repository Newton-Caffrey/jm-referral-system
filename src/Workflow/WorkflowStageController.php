<?php

namespace JMReferral\Workflow;

use JMReferral\Permissions\Capabilities;

class WorkflowStageController
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_workflow_stage_form_';

    private const ALLOWED_STATUSES = [
        'active',
        'inactive',
    ];

    public function __construct(
        private WorkflowStageService $service
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_create']);
        add_action('admin_init', [$this, 'handle_update']);
        add_action('admin_init', [$this, 'handle_delete']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    public function render_list(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_WORKFLOW_STAGES)) {
            wp_die(esc_html__('You do not have permission to manage workflow stages.', 'jm-referral-system'));
        }

        $workflow_stages = $this->service->all();

        include JMRS_PLUGIN_PATH . 'templates/workflow/list.php';
    }

    public function render_create(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_WORKFLOW_STAGES)) {
            wp_die(esc_html__('You do not have permission to manage workflow stages.', 'jm-referral-system'));
        }

        $form_state = self::get_form_state('create');
        $data       = ! empty($form_state['data'])
            ? $form_state['data']
            : [
                'name'        => '',
                'description' => '',
                'stage_order' => '0',
                'status'      => 'active',
            ];
        $errors = $form_state['errors'];

        include JMRS_PLUGIN_PATH . 'templates/workflow/create.php';
    }

    public function render_edit(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_WORKFLOW_STAGES)) {
            wp_die(esc_html__('You do not have permission to manage workflow stages.', 'jm-referral-system'));
        }

        $workflow_stage_id = $this->get_request_workflow_stage_id();
        $workflow_stage    = $this->service->find($workflow_stage_id);

        if (null === $workflow_stage) {
            wp_die(esc_html__('Workflow stage not found.', 'jm-referral-system'));
        }

        $form_state = self::get_form_state('edit_' . $workflow_stage_id);
        $errors     = $form_state['errors'];
        $data       = ! empty($form_state['data'])
            ? $form_state['data']
            : [
                'name'        => (string) ($workflow_stage['name'] ?? ''),
                'description' => (string) ($workflow_stage['description'] ?? ''),
                'stage_order' => (string) absint($workflow_stage['stage_order'] ?? 0),
                'status'      => (string) ($workflow_stage['status'] ?? 'active'),
            ];

        include JMRS_PLUGIN_PATH . 'templates/workflow/edit.php';
    }

    public function handle_create(): void
    {
        if (! isset($_POST['jmrs_submit_workflow_stage'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_WORKFLOW_STAGES)) {
            wp_die(esc_html__('You do not have permission to manage workflow stages.', 'jm-referral-system'));
        }

        check_admin_referer('jmrs_add_workflow_stage', 'jmrs_add_workflow_stage_nonce');

        $data   = $this->sanitize_input($_POST);
        $result = $this->service->create($data);

        if (false === $result) {
            $this->store_form_state(
                'create',
                $data,
                [
                    'general' => __('Unable to save the workflow stage. Please try again.', 'jm-referral-system'),
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
                    'page'         => 'jm-referrals-workflow-stages',
                    'jmrs_created' => '1',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function handle_update(): void
    {
        if (! isset($_POST['jmrs_update_workflow_stage'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_WORKFLOW_STAGES)) {
            wp_die(esc_html__('You do not have permission to manage workflow stages.', 'jm-referral-system'));
        }

        $workflow_stage_id = isset($_POST['jmrs_workflow_stage_id']) ? absint($_POST['jmrs_workflow_stage_id']) : 0;

        check_admin_referer('jmrs_edit_workflow_stage_' . $workflow_stage_id, 'jmrs_edit_workflow_stage_nonce');

        if (null === $this->service->find($workflow_stage_id)) {
            wp_die(esc_html__('Workflow stage not found.', 'jm-referral-system'));
        }

        $data   = $this->sanitize_input($_POST);
        $result = $this->service->update($workflow_stage_id, $data);

        if (false === $result) {
            $this->store_form_state(
                'edit_' . $workflow_stage_id,
                $data,
                [
                    'general' => __('Unable to update the workflow stage. Please try again.', 'jm-referral-system'),
                ]
            );
            return;
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_form_state('edit_' . $workflow_stage_id, $data, $result['errors']);
            return;
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'              => 'jm-referrals-workflow-stages-edit',
                    'workflow_stage_id' => $workflow_stage_id,
                    'jmrs_updated'      => '1',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function handle_delete(): void
    {
        if (! $this->is_list_screen()) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';

        if ('delete' !== $action) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_WORKFLOW_STAGES)) {
            wp_die(esc_html__('You do not have permission to manage workflow stages.', 'jm-referral-system'));
        }

        $workflow_stage_id = isset($_GET['workflow_stage_id']) ? absint($_GET['workflow_stage_id']) : 0;

        check_admin_referer('jmrs_delete_workflow_stage_' . $workflow_stage_id);

        $result = $this->service->delete($workflow_stage_id);

        $args = [
            'page' => 'jm-referrals-workflow-stages',
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
                echo esc_html__('Workflow stage updated successfully.', 'jm-referral-system');
                echo '</p></div>';
            }

            $this->render_form_errors('edit_' . $this->get_request_workflow_stage_id());
        }
    }

    public static function get_edit_url(int $workflow_stage_id): string
    {
        return add_query_arg(
            [
                'page'              => 'jm-referrals-workflow-stages-edit',
                'workflow_stage_id' => $workflow_stage_id,
            ],
            admin_url('admin.php')
        );
    }

    public static function get_delete_url(int $workflow_stage_id): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'page'              => 'jm-referrals-workflow-stages',
                    'action'            => 'delete',
                    'workflow_stage_id' => $workflow_stage_id,
                ],
                admin_url('admin.php')
            ),
            'jmrs_delete_workflow_stage_' . $workflow_stage_id
        );
    }

    /**
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
            echo esc_html__('Workflow stage created successfully.', 'jm-referral-system');
            echo '</p></div>';
        }

        if (! isset($_GET['jmrs_deleted'])) {
            return;
        }

        $deleted = sanitize_text_field(wp_unslash($_GET['jmrs_deleted']));

        if ('1' === $deleted) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Workflow stage deleted successfully.', 'jm-referral-system');
            echo '</p></div>';
            return;
        }

        if ('blocked' === $deleted) {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo esc_html__('This workflow stage cannot be deleted because it is used by one or more referrals.', 'jm-referral-system');
            echo '</p></div>';
            return;
        }

        echo '<div class="notice notice-error is-dismissible"><p>';
        echo esc_html__('Unable to delete the workflow stage.', 'jm-referral-system');
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
            'stage_order' => isset($input['jmrs_stage_order'])
                ? (string) absint(wp_unslash($input['jmrs_stage_order']))
                : '0',
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

    private function get_request_workflow_stage_id(): int
    {
        if (isset($_POST['jmrs_workflow_stage_id'])) {
            return absint($_POST['jmrs_workflow_stage_id']);
        }

        return isset($_GET['workflow_stage_id']) ? absint($_GET['workflow_stage_id']) : 0;
    }

    private function is_list_screen(): bool
    {
        return $this->is_screen('jm-referrals-workflow-stages');
    }

    private function is_create_screen(): bool
    {
        return $this->is_screen('jm-referrals-workflow-stages-add');
    }

    private function is_edit_screen(): bool
    {
        return $this->is_screen('jm-referrals-workflow-stages-edit');
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
