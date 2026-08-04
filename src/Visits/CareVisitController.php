<?php

namespace JMReferral\Visits;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Scheduling\ScheduleRepository;
use JMReferral\Users\UserProvider;

class CareVisitController
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_care_visit_form_';
    private const EXECUTION_TRANSIENT_PREFIX = 'jmrs_visit_execution_form_';

    public function __construct(
        private CareVisitService $visit_service,
        private VisitExecutionService $execution_service,
        private ReferralRepository $referral_repository,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider,
        private ScheduleRepository $schedule_repository
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_save']);
        add_action('admin_init', [$this, 'handle_execute']);
        add_action('admin_init', [$this, 'handle_review']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    public function handle_save(): void
    {
        if (! isset($_POST['jmrs_save_care_visit'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_VISITS)) {
            wp_die(esc_html__('You do not have permission to manage visits.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        $visit_id    = isset($_POST['jmrs_visit_id']) ? absint($_POST['jmrs_visit_id']) : 0;

        check_admin_referer('jmrs_save_care_visit_' . $referral_id, 'jmrs_care_visit_nonce');

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_edit_referral($referral)) {
            wp_die(esc_html__('You do not have permission to manage visits for this referral.', 'jm-referral-system'));
        }

        $data   = $this->sanitize_input($_POST);
        $result = $this->visit_service->save($referral_id, $data, $visit_id);

        if (false === $result) {
            $this->store_form_state(
                $referral_id,
                $data,
                [
                    'general' => __('Unable to save the care visit. Please try again.', 'jm-referral-system'),
                ],
                $visit_id
            );
            $this->redirect_after_save($referral_id, $visit_id, false);
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_form_state($referral_id, $data, $result['errors'], $visit_id);
            $this->redirect_after_save($referral_id, $visit_id, false);
        }

        $this->redirect_to_view($referral_id, true, ! empty($result['created']));
    }

    public function handle_execute(): void
    {
        if (! isset($_POST['jmrs_execute_care_visit'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::EXECUTE_VISITS)) {
            wp_die(esc_html__('You do not have permission to execute visits.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        $visit_id    = isset($_POST['jmrs_visit_id']) ? absint($_POST['jmrs_visit_id']) : 0;

        check_admin_referer('jmrs_execute_care_visit_' . $visit_id, 'jmrs_execute_visit_nonce');

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_view_referral($referral)) {
            wp_die(esc_html__('You do not have permission to execute visits for this referral.', 'jm-referral-system'));
        }

        $data   = $this->sanitize_execution_input($_POST);
        $result = $this->execution_service->execute($referral_id, $visit_id, $data);

        if (false === $result) {
            $this->store_execution_form_state(
                $referral_id,
                $data,
                [
                    'general' => __('Unable to complete the visit. Please try again.', 'jm-referral-system'),
                ],
                $visit_id
            );
            $this->redirect_to_view($referral_id, false);
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_execution_form_state($referral_id, $data, $result['errors'], $visit_id);
            $this->redirect_to_view($referral_id, false);
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'                 => 'jm-referrals-view',
                    'referral_id'          => $referral_id,
                    'jmrs_visit_executed'  => '1',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function handle_review(): void
    {
        if (! isset($_POST['jmrs_review_care_visit'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_VISITS)) {
            wp_die(esc_html__('You do not have permission to review visits.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        $visit_id    = isset($_POST['jmrs_visit_id']) ? absint($_POST['jmrs_visit_id']) : 0;

        check_admin_referer('jmrs_review_care_visit_' . $visit_id, 'jmrs_review_visit_nonce');

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_edit_referral($referral)) {
            wp_die(esc_html__('You do not have permission to review visits for this referral.', 'jm-referral-system'));
        }

        $data   = $this->sanitize_review_input($_POST);
        $result = $this->execution_service->review($referral_id, $visit_id, $data);

        if (false === $result) {
            $this->store_execution_form_state(
                $referral_id,
                $data,
                [
                    'general' => __('Unable to save the visit review. Please try again.', 'jm-referral-system'),
                ],
                $visit_id
            );
            $this->redirect_to_view($referral_id, false);
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_execution_form_state($referral_id, $data, $result['errors'], $visit_id);
            $this->redirect_to_view($referral_id, false);
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'                => 'jm-referrals-view',
                    'referral_id'         => $referral_id,
                    'jmrs_visit_reviewed' => '1',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Renders the hidden visit edit screen.
     */
    public function render_edit(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_VISITS)) {
            wp_die(esc_html__('You do not have permission to manage visits.', 'jm-referral-system'));
        }

        $visit_id = isset($_GET['visit_id']) ? absint($_GET['visit_id']) : 0;
        $prepared = $this->visit_service->prepare_edit($visit_id);

        if (isset($prepared['errors']) && is_array($prepared['errors'])) {
            $message = (string) reset($prepared['errors']);
            wp_die(esc_html('' !== $message ? $message : __('Unable to edit this care visit.', 'jm-referral-system')));
        }

        $visit    = $prepared['visit'];
        $referral = $prepared['referral'];
        $referral_id = absint($referral['id'] ?? 0);

        $form_state = self::get_form_state($referral_id);
        $errors     = $form_state['errors'];

        if (! empty($form_state['data']) && absint($form_state['visit_id'] ?? 0) === $visit_id) {
            $visit_data = array_merge(CareVisitService::empty_form_data(), $form_state['data']);
        } else {
            $visit_data = CareVisitService::map_to_form_data($visit);
        }

        $assignable_users = $this->visit_service->get_assignable_staff_for_referral($referral_id);
        $status_labels    = CareVisitService::status_labels();
        $schedule_source_label = '';
        $schedule_id = absint($visit['schedule_id'] ?? 0);
        if ($schedule_id > 0) {
            $schedule = $this->schedule_repository->find($schedule_id);
            $schedule_name = is_array($schedule) ? (string) ($schedule['schedule_name'] ?? '') : '';
            $schedule_source_label = '' !== $schedule_name
                ? sprintf(
                    /* translators: %s: schedule name */
                    __('Schedule: %s', 'jm-referral-system'),
                    $schedule_name
                )
                : __('Schedule', 'jm-referral-system');
        }
        $back_url         = add_query_arg(
            [
                'page'        => 'jm-referrals-view',
                'referral_id' => $referral_id,
            ],
            admin_url('admin.php')
        );

        include JMRS_PLUGIN_PATH . 'templates/visits/edit.php';
    }

    public function render_notices(): void
    {
        if (! $this->is_view_or_edit_screen()) {
            return;
        }

        if (isset($_GET['jmrs_visit_saved'])) {
            $created = isset($_GET['jmrs_visit_created']) && '1' === $_GET['jmrs_visit_created'];
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html(
                $created
                    ? __('Care visit scheduled successfully.', 'jm-referral-system')
                    : __('Care visit updated successfully.', 'jm-referral-system')
            );
            echo '</p></div>';
        }

        if (isset($_GET['jmrs_visit_executed']) && '1' === $_GET['jmrs_visit_executed']) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Visit completed by staff successfully.', 'jm-referral-system');
            echo '</p></div>';
        }

        if (isset($_GET['jmrs_visit_reviewed']) && '1' === $_GET['jmrs_visit_reviewed']) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Visit reviewed by manager successfully.', 'jm-referral-system');
            echo '</p></div>';
        }

        $referral_id = isset($_GET['referral_id']) ? absint($_GET['referral_id']) : 0;
        if ($referral_id <= 0 && isset($_GET['visit_id'])) {
            // Edit screen uses visit_id; errors are loaded in render_edit via get_form_state.
            return;
        }

        $state  = self::get_form_state($referral_id, false);
        $errors = $state['errors'];

        $execution_state  = self::get_execution_form_state($referral_id, false);
        $execution_errors = $execution_state['errors'];

        if ('jm-referrals-visit-edit' === $this->current_page()) {
            return;
        }

        $all_errors = array_merge($errors, $execution_errors);
        if (empty($all_errors)) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Please fix the following errors:', 'jm-referral-system');
        echo '</p><ul>';

        foreach ($all_errors as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }

        echo '</ul></div>';
    }

    /**
     * @return array{data: array<string, string>, errors: array<string, string>, visit_id: int}
     */
    public static function get_form_state(int $referral_id, bool $consume = true): array
    {
        $key   = self::FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $referral_id;
        $state = get_transient($key);

        if (! is_array($state)) {
            return [
                'data'     => [],
                'errors'   => [],
                'visit_id' => 0,
            ];
        }

        if ($consume) {
            delete_transient($key);
        }

        return [
            'data'     => is_array($state['data'] ?? null) ? $state['data'] : [],
            'errors'   => is_array($state['errors'] ?? null) ? $state['errors'] : [],
            'visit_id' => absint($state['visit_id'] ?? 0),
        ];
    }

    /**
     * @return array{data: array<string, string>, errors: array<string, string>, visit_id: int}
     */
    public static function get_execution_form_state(int $referral_id, bool $consume = true): array
    {
        $key   = self::EXECUTION_TRANSIENT_PREFIX . get_current_user_id() . '_' . $referral_id;
        $state = get_transient($key);

        if (! is_array($state)) {
            return [
                'data'     => [],
                'errors'   => [],
                'visit_id' => 0,
            ];
        }

        if ($consume) {
            delete_transient($key);
        }

        return [
            'data'     => is_array($state['data'] ?? null) ? $state['data'] : [],
            'errors'   => is_array($state['errors'] ?? null) ? $state['errors'] : [],
            'visit_id' => absint($state['visit_id'] ?? 0),
        ];
    }

    public static function get_edit_url(int $visit_id): string
    {
        return add_query_arg(
            [
                'page'     => 'jm-referrals-visit-edit',
                'visit_id' => $visit_id,
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
        $status = isset($input['jmrs_visit_status'])
            ? sanitize_key(wp_unslash($input['jmrs_visit_status']))
            : CareVisitService::STATUS_SCHEDULED;

        if (! in_array($status, CareVisitService::allowed_statuses(), true)) {
            $status = CareVisitService::STATUS_SCHEDULED;
        }

        return [
            'visit_date'       => isset($input['jmrs_visit_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_visit_date']))
                : '',
            'start_time'       => isset($input['jmrs_visit_start_time'])
                ? sanitize_text_field(wp_unslash($input['jmrs_visit_start_time']))
                : '',
            'end_time'         => isset($input['jmrs_visit_end_time'])
                ? sanitize_text_field(wp_unslash($input['jmrs_visit_end_time']))
                : '',
            'assigned_user_id' => isset($input['jmrs_visit_assigned_user_id'])
                ? (string) absint(wp_unslash($input['jmrs_visit_assigned_user_id']))
                : '',
            'visit_type'       => isset($input['jmrs_visit_type'])
                ? sanitize_text_field(wp_unslash($input['jmrs_visit_type']))
                : '',
            'visit_status'     => $status,
            'tasks'            => isset($input['jmrs_visit_tasks'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_visit_tasks']))
                : '',
            'notes'            => isset($input['jmrs_visit_notes'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_visit_notes']))
                : '',
            'care_plan_id'     => isset($input['jmrs_visit_care_plan_id'])
                ? (string) absint(wp_unslash($input['jmrs_visit_care_plan_id']))
                : '',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function sanitize_execution_input(array $input): array
    {
        $outcome = isset($input['jmrs_visit_outcome'])
            ? sanitize_key(wp_unslash($input['jmrs_visit_outcome']))
            : '';

        if (! in_array($outcome, VisitExecutionService::allowed_outcomes(), true)) {
            $outcome = '';
        }

        return [
            'arrival_time'           => isset($input['jmrs_visit_arrival_time'])
                ? sanitize_text_field(wp_unslash($input['jmrs_visit_arrival_time']))
                : '',
            'departure_time'         => isset($input['jmrs_visit_departure_time'])
                ? sanitize_text_field(wp_unslash($input['jmrs_visit_departure_time']))
                : '',
            'visit_outcome'          => $outcome,
            'tasks_completed'        => isset($input['jmrs_visit_tasks_completed'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_visit_tasks_completed']))
                : '',
            'tasks_not_completed'    => isset($input['jmrs_visit_tasks_not_completed'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_visit_tasks_not_completed']))
                : '',
            'client_response'        => isset($input['jmrs_visit_client_response'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_visit_client_response']))
                : '',
            'wellbeing_observations' => isset($input['jmrs_visit_wellbeing_observations'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_visit_wellbeing_observations']))
                : '',
            'incident_report'        => isset($input['jmrs_visit_incident_report'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_visit_incident_report']))
                : '',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function sanitize_review_input(array $input): array
    {
        return [
            'manager_review_notes' => isset($input['jmrs_visit_manager_review_notes'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_visit_manager_review_notes']))
                : '',
        ];
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $errors
     */
    private function store_form_state(int $referral_id, array $data, array $errors, int $visit_id = 0): void
    {
        set_transient(
            self::FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $referral_id,
            [
                'data'     => $data,
                'errors'   => $errors,
                'visit_id' => $visit_id,
            ],
            MINUTE_IN_SECONDS * 5
        );
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $errors
     */
    private function store_execution_form_state(int $referral_id, array $data, array $errors, int $visit_id = 0): void
    {
        set_transient(
            self::EXECUTION_TRANSIENT_PREFIX . get_current_user_id() . '_' . $referral_id,
            [
                'data'     => $data,
                'errors'   => $errors,
                'visit_id' => $visit_id,
            ],
            MINUTE_IN_SECONDS * 5
        );
    }

    private function redirect_after_save(int $referral_id, int $visit_id, bool $success): void
    {
        if ($success) {
            $this->redirect_to_view($referral_id, true, false);
        }

        if ($visit_id > 0) {
            wp_safe_redirect(self::get_edit_url($visit_id));
            exit;
        }

        $this->redirect_to_view($referral_id, false);
    }

    private function redirect_to_view(int $referral_id, bool $success = false, bool $created = false): void
    {
        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];

        if ($success) {
            $args['jmrs_visit_saved'] = '1';
            if ($created) {
                $args['jmrs_visit_created'] = '1';
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function is_view_or_edit_screen(): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = $this->current_page();

        return in_array($page, ['jm-referrals-view', 'jm-referrals-visit-edit'], true);
    }

    private function current_page(): string
    {
        return isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    }
}
