<?php

namespace JMReferral\CareTeam;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;

class CareTeamController
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_care_team_form_';

    public function __construct(
        private CareTeamService $care_team_service,
        private ReferralRepository $referral_repository,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_save']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    public function handle_save(): void
    {
        if (! isset($_POST['jmrs_save_care_team'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_CARE_TEAM)) {
            wp_die(esc_html__('You do not have permission to manage the care team.', 'jm-referral-system'));
        }

        $referral_id   = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        $assignment_id = isset($_POST['jmrs_care_team_id']) ? absint($_POST['jmrs_care_team_id']) : 0;

        check_admin_referer('jmrs_save_care_team_' . $referral_id, 'jmrs_care_team_nonce');

        $result = $this->attempt_save($referral_id, $_POST, $assignment_id);

        if (! $result['success']) {
            if (! empty($result['forbidden']) || ! empty($result['not_found'])) {
                wp_die(esc_html__('You do not have permission to manage the care team for this referral.', 'jm-referral-system'));
            }

            $this->persist_form_state($referral_id, $result['data'], $result['errors'], $assignment_id);
            $this->redirect_after_save($referral_id, $assignment_id, false);
        }

        $this->redirect_to_view($referral_id, true, ! empty($result['created']));
    }

    /**
     * Shared sanitize → CareTeamService::save() pipeline for admin and portal.
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
    public function attempt_save(int $referral_id, array $raw_input, int $assignment_id): array
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

        if (! $this->access_policy->can_edit_referral($referral)) {
            return [
                'success'   => false,
                'data'      => [],
                'errors'    => [],
                'created'   => false,
                'not_found' => false,
                'forbidden' => true,
            ];
        }

        $data   = $this->sanitize_input($raw_input);
        $result = $this->care_team_service->save($referral_id, $data, $assignment_id);

        if (false === $result) {
            return [
                'success'   => false,
                'data'      => $data,
                'errors'    => [
                    'general' => __('Unable to save the care team assignment. Please try again.', 'jm-referral-system'),
                ],
                'created'   => false,
                'not_found' => false,
                'forbidden' => false,
            ];
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            return [
                'success'   => false,
                'data'      => $data,
                'errors'    => $result['errors'],
                'created'   => false,
                'not_found' => false,
                'forbidden' => false,
            ];
        }

        return [
            'success'   => true,
            'data'      => $data,
            'errors'    => [],
            'created'   => ! empty($result['created']),
            'not_found' => false,
            'forbidden' => false,
        ];
    }

    /**
     * Persist validation errors for PRG redisplay (admin and portal).
     *
     * @param array<string, string> $data
     * @param array<string, string> $errors
     */
    public function persist_form_state(int $referral_id, array $data, array $errors, int $assignment_id = 0, string $channel = 'admin'): void
    {
        $this->store_form_state($referral_id, $data, $errors, $assignment_id, $channel);
    }

    /**
     * Renders the hidden care team assignment edit screen.
     */
    public function render_edit(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_CARE_TEAM)) {
            wp_die(esc_html__('You do not have permission to manage the care team.', 'jm-referral-system'));
        }

        $assignment_id = isset($_GET['assignment_id']) ? absint($_GET['assignment_id']) : 0;
        $prepared      = $this->care_team_service->prepare_edit($assignment_id);

        if (isset($prepared['errors']) && is_array($prepared['errors'])) {
            $message = (string) reset($prepared['errors']);
            wp_die(esc_html('' !== $message ? $message : __('Unable to edit this care team assignment.', 'jm-referral-system')));
        }

        $assignment  = $prepared['assignment'];
        $referral    = $prepared['referral'];
        $referral_id = absint($referral['id'] ?? 0);

        $form_state = self::get_form_state($referral_id);
        $errors     = $form_state['errors'];

        if (! empty($form_state['data']) && absint($form_state['assignment_id'] ?? 0) === $assignment_id) {
            $assignment_data = array_merge(CareTeamService::empty_form_data(), $form_state['data']);
        } else {
            $assignment_data = CareTeamService::map_to_form_data($assignment);
        }

        $assignable_users = $this->user_provider->get_assignable_users();
        $role_labels      = CareTeamService::role_labels();
        $status_labels    = CareTeamService::status_labels();
        $back_url         = add_query_arg(
            [
                'page'        => 'jm-referrals-view',
                'referral_id' => $referral_id,
            ],
            admin_url('admin.php')
        );

        include JMRS_PLUGIN_PATH . 'templates/care-team/edit.php';
    }

    public function render_notices(): void
    {
        if (! $this->is_view_or_edit_screen()) {
            return;
        }

        if (isset($_GET['jmrs_care_team_saved'])) {
            $created = isset($_GET['jmrs_care_team_created']) && '1' === $_GET['jmrs_care_team_created'];
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html(
                $created
                    ? __('Care team member assigned successfully.', 'jm-referral-system')
                    : __('Care team assignment updated successfully.', 'jm-referral-system')
            );
            echo '</p></div>';
        }

        $referral_id = isset($_GET['referral_id']) ? absint($_GET['referral_id']) : 0;
        if ($referral_id <= 0 || 'jm-referrals-care-team-edit' === $this->current_page()) {
            return;
        }

        $state  = self::get_form_state($referral_id, false);
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
     * @return array{data: array<string, string>, errors: array<string, string>, assignment_id: int}
     */
    public static function get_form_state(int $referral_id, bool $consume = true, string $channel = 'admin'): array
    {
        $key   = self::FORM_TRANSIENT_PREFIX . $channel . '_' . get_current_user_id() . '_' . $referral_id;
        $state = get_transient($key);

        if (! is_array($state)) {
            return [
                'data'          => [],
                'errors'        => [],
                'assignment_id' => 0,
            ];
        }

        if ($consume) {
            delete_transient($key);
        }

        return [
            'data'          => is_array($state['data'] ?? null) ? $state['data'] : [],
            'errors'        => is_array($state['errors'] ?? null) ? $state['errors'] : [],
            'assignment_id' => absint($state['assignment_id'] ?? 0),
        ];
    }

    public static function get_edit_url(int $assignment_id): string
    {
        return add_query_arg(
            [
                'page'          => 'jm-referrals-care-team-edit',
                'assignment_id' => $assignment_id,
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
        $team_role = isset($input['jmrs_care_team_role'])
            ? sanitize_key(wp_unslash($input['jmrs_care_team_role']))
            : '';

        if (! in_array($team_role, CareTeamService::allowed_roles(), true)) {
            $team_role = '';
        }

        $status = isset($input['jmrs_care_team_status'])
            ? sanitize_key(wp_unslash($input['jmrs_care_team_status']))
            : CareTeamService::STATUS_ACTIVE;

        if (! in_array($status, CareTeamService::allowed_statuses(), true)) {
            $status = CareTeamService::STATUS_ACTIVE;
        }

        return [
            'user_id'           => isset($input['jmrs_care_team_user_id'])
                ? (string) absint(wp_unslash($input['jmrs_care_team_user_id']))
                : '',
            'team_role'         => $team_role,
            'is_primary'        => isset($input['jmrs_care_team_is_primary']) ? '1' : '0',
            'assignment_status' => $status,
            'start_date'        => isset($input['jmrs_care_team_start_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_care_team_start_date']))
                : '',
            'end_date'          => isset($input['jmrs_care_team_end_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_care_team_end_date']))
                : '',
            'notes'             => isset($input['jmrs_care_team_notes'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_care_team_notes']))
                : '',
            'care_plan_id'      => isset($input['jmrs_care_team_care_plan_id'])
                ? (string) absint(wp_unslash($input['jmrs_care_team_care_plan_id']))
                : '',
        ];
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $errors
     */
    private function store_form_state(int $referral_id, array $data, array $errors, int $assignment_id = 0, string $channel = 'admin'): void
    {
        set_transient(
            self::FORM_TRANSIENT_PREFIX . $channel . '_' . get_current_user_id() . '_' . $referral_id,
            [
                'data'          => $data,
                'errors'        => $errors,
                'assignment_id' => $assignment_id,
            ],
            MINUTE_IN_SECONDS * 5
        );
    }

    private function redirect_after_save(int $referral_id, int $assignment_id, bool $success): void
    {
        if ($success) {
            $this->redirect_to_view($referral_id, true, false);
        }

        if ($assignment_id > 0) {
            wp_safe_redirect(self::get_edit_url($assignment_id));
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
            $args['jmrs_care_team_saved'] = '1';
            if ($created) {
                $args['jmrs_care_team_created'] = '1';
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

        return in_array($this->current_page(), ['jm-referrals-view', 'jm-referrals-care-team-edit'], true);
    }

    private function current_page(): string
    {
        return isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    }
}
