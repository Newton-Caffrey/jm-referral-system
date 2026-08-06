<?php

namespace JMReferral\Scheduling;

use JMReferral\CareTeam\CareTeamService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;

class ScheduleController
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_schedule_form_';

    public function __construct(
        private ScheduleService $schedule_service,
        private ScheduleGenerationService $generation_service,
        private ReferralRepository $referral_repository,
        private AccessPolicy $access_policy,
        private CareTeamService $care_team_service,
        private UserProvider $user_provider
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_save']);
        add_action('admin_init', [$this, 'handle_generate']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    public function handle_save(): void
    {
        if (! isset($_POST['jmrs_save_schedule'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_SCHEDULES)) {
            wp_die(esc_html__('You do not have permission to manage schedules.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        $schedule_id = isset($_POST['jmrs_schedule_id']) ? absint($_POST['jmrs_schedule_id']) : 0;

        check_admin_referer('jmrs_save_schedule_' . $referral_id, 'jmrs_schedule_nonce');

        $result = $this->attempt_save($referral_id, $_POST, $schedule_id);

        if (! $result['success']) {
            if (! empty($result['forbidden']) || ! empty($result['not_found'])) {
                wp_die(esc_html__('You do not have permission to manage schedules for this referral.', 'jm-referral-system'));
            }

            $this->persist_form_state($referral_id, $result['data'], $result['errors'], $schedule_id);
            $this->redirect_after_save($referral_id, $schedule_id, false);
        }

        $this->redirect_to_view($referral_id, true, ! empty($result['created']));
    }

    public function handle_generate(): void
    {
        if (! isset($_POST['jmrs_generate_schedule_visits'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_SCHEDULES)) {
            wp_die(esc_html__('You do not have permission to generate visits from schedules.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        $schedule_id = isset($_POST['jmrs_schedule_id']) ? absint($_POST['jmrs_schedule_id']) : 0;

        check_admin_referer('jmrs_generate_schedule_visits_' . $schedule_id, 'jmrs_generate_schedule_nonce');

        $start_date = isset($_POST['generation_start_date'])
            ? sanitize_text_field(wp_unslash($_POST['generation_start_date']))
            : '';
        $end_date = isset($_POST['generation_end_date'])
            ? sanitize_text_field(wp_unslash($_POST['generation_end_date']))
            : '';

        $result = $this->attempt_generate($referral_id, $schedule_id, $start_date, $end_date);

        if (! $result['success']) {
            if (! empty($result['forbidden']) || ! empty($result['not_found'])) {
                wp_die(esc_html__('You do not have permission to generate visits for this referral.', 'jm-referral-system'));
            }

            $this->persist_form_state($referral_id, $result['data'], $result['errors'], $schedule_id);
            $this->redirect_to_view($referral_id, false);
        }

        $args = [
            'page'                         => 'jm-referrals-view',
            'referral_id'                  => $referral_id,
            'jmrs_schedule_visits_created' => (string) absint($result['created']),
            'jmrs_schedule_visits_skipped' => (string) absint($result['skipped_duplicates']),
            'jmrs_schedule_visits_outside' => (string) absint($result['skipped_outside_range']),
        ];

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Shared sanitize → ScheduleService::save() pipeline for admin and portal.
     *
     * Callers must verify capability and nonce before invoking.
     *
     * @param array<string, mixed> $raw_input
     * @return array{
     *     success: bool,
     *     data: array<string, mixed>,
     *     errors: array<string, string>,
     *     created: bool,
     *     not_found: bool,
     *     forbidden: bool
     * }
     */
    public function attempt_save(int $referral_id, array $raw_input, int $schedule_id): array
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
        $result = $this->schedule_service->save($referral_id, $data, $schedule_id);

        if (false === $result) {
            return [
                'success'   => false,
                'data'      => $data,
                'errors'    => [
                    'general' => __('Unable to save the schedule. Please try again.', 'jm-referral-system'),
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
     * Shared date range → ScheduleGenerationService::generate() pipeline for admin and portal.
     *
     * Callers must verify capability and nonce before invoking.
     *
     * @return array{
     *     success: bool,
     *     errors: array<string, string>,
     *     created: int,
     *     skipped_duplicates: int,
     *     skipped_outside_range: int,
     *     not_found: bool,
     *     forbidden: bool,
     *     data: array<string, string>
     * }
     */
    public function attempt_generate(int $referral_id, int $schedule_id, string $start_date, string $end_date): array
    {
        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral) {
            return [
                'success'               => false,
                'errors'                => [],
                'created'               => 0,
                'skipped_duplicates'    => 0,
                'skipped_outside_range' => 0,
                'not_found'             => true,
                'forbidden'             => false,
                'data'                  => [
                    'generation_start_date' => $start_date,
                    'generation_end_date'   => $end_date,
                ],
            ];
        }

        if (! $this->access_policy->can_edit_referral($referral)) {
            return [
                'success'               => false,
                'errors'                => [],
                'created'               => 0,
                'skipped_duplicates'    => 0,
                'skipped_outside_range' => 0,
                'not_found'             => false,
                'forbidden'             => true,
                'data'                  => [
                    'generation_start_date' => $start_date,
                    'generation_end_date'   => $end_date,
                ],
            ];
        }

        $data   = [
            'generation_start_date' => $start_date,
            'generation_end_date'   => $end_date,
        ];
        $result = $this->generation_service->generate($referral_id, $schedule_id, $start_date, $end_date);

        if (isset($result['errors']) && is_array($result['errors'])) {
            return [
                'success'               => false,
                'errors'                => $result['errors'],
                'created'               => 0,
                'skipped_duplicates'    => 0,
                'skipped_outside_range' => 0,
                'not_found'             => false,
                'forbidden'             => false,
                'data'                  => $data,
            ];
        }

        return [
            'success'               => true,
            'errors'                => [],
            'created'               => absint($result['created'] ?? 0),
            'skipped_duplicates'    => absint($result['skipped_duplicates'] ?? 0),
            'skipped_outside_range' => absint($result['skipped_outside_range'] ?? 0),
            'not_found'             => false,
            'forbidden'             => false,
            'data'                  => $data,
        ];
    }

    /**
     * Persist validation errors for PRG redisplay (admin and portal).
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $errors
     */
    public function persist_form_state(int $referral_id, array $data, array $errors, int $schedule_id = 0, string $channel = 'admin'): void
    {
        $this->store_form_state($referral_id, $data, $errors, $schedule_id, $channel);
    }

    public function render_edit(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SCHEDULES)) {
            wp_die(esc_html__('You do not have permission to manage schedules.', 'jm-referral-system'));
        }

        $schedule_id = isset($_GET['schedule_id']) ? absint($_GET['schedule_id']) : 0;
        $prepared    = $this->schedule_service->prepare_edit($schedule_id);

        if (isset($prepared['errors']) && is_array($prepared['errors'])) {
            $message = (string) reset($prepared['errors']);
            wp_die(esc_html('' !== $message ? $message : __('Unable to edit this schedule.', 'jm-referral-system')));
        }

        $schedule    = $prepared['schedule'];
        $referral    = $prepared['referral'];
        $referral_id = absint($referral['id'] ?? 0);

        $form_state = self::get_form_state($referral_id);
        $errors     = $form_state['errors'];

        if (! empty($form_state['data']) && absint($form_state['schedule_id'] ?? 0) === $schedule_id) {
            $schedule_data = array_merge(ScheduleService::empty_form_data(), $form_state['data']);
        } else {
            $schedule_data = ScheduleService::map_to_form_data($schedule);
        }

        $repeat_labels  = ScheduleService::repeat_type_labels();
        $status_labels  = ScheduleService::status_labels();
        $weekday_labels = ScheduleService::weekday_labels();
        $team_options   = $this->build_team_assignment_options($referral_id);
        $back_url       = add_query_arg(
            [
                'page'        => 'jm-referrals-view',
                'referral_id' => $referral_id,
            ],
            admin_url('admin.php')
        );

        include JMRS_PLUGIN_PATH . 'templates/schedules/edit.php';
    }

    public function render_notices(): void
    {
        if (! $this->is_view_or_edit_screen()) {
            return;
        }

        if (isset($_GET['jmrs_schedule_saved'])) {
            $created = isset($_GET['jmrs_schedule_created']) && '1' === $_GET['jmrs_schedule_created'];
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html(
                $created
                    ? __('Schedule created successfully.', 'jm-referral-system')
                    : __('Schedule updated successfully.', 'jm-referral-system')
            );
            echo '</p></div>';
        }

        if (isset($_GET['jmrs_schedule_visits_created']) || isset($_GET['jmrs_schedule_visits_skipped'])) {
            $created = isset($_GET['jmrs_schedule_visits_created']) ? absint($_GET['jmrs_schedule_visits_created']) : 0;
            $skipped = isset($_GET['jmrs_schedule_visits_skipped']) ? absint($_GET['jmrs_schedule_visits_skipped']) : 0;
            $outside = isset($_GET['jmrs_schedule_visits_outside']) ? absint($_GET['jmrs_schedule_visits_outside']) : 0;

            $parts = [];
            $parts[] = sprintf(
                /* translators: %d: number of visits created */
                _n('%d visit generated.', '%d visits generated.', $created, 'jm-referral-system'),
                $created
            );

            if ($skipped > 0) {
                $parts[] = sprintf(
                    /* translators: %d: number of duplicate visits skipped */
                    _n('%d existing visit skipped.', '%d existing visits skipped.', $skipped, 'jm-referral-system'),
                    $skipped
                );
            }

            if ($outside > 0) {
                $parts[] = sprintf(
                    /* translators: %d: number of occurrences outside schedule range */
                    _n('%d occurrence outside the schedule range skipped.', '%d occurrences outside the schedule range skipped.', $outside, 'jm-referral-system'),
                    $outside
                );
            }

            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html(implode(' ', $parts));
            echo '</p></div>';
        }

        $referral_id = isset($_GET['referral_id']) ? absint($_GET['referral_id']) : 0;
        if ($referral_id <= 0 || 'jm-referrals-schedule-edit' === $this->current_page()) {
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
     * @return array{data: array<string, mixed>, errors: array<string, string>, schedule_id: int}
     */
    public static function get_form_state(int $referral_id, bool $consume = true, string $channel = 'admin'): array
    {
        $key   = self::FORM_TRANSIENT_PREFIX . $channel . '_' . get_current_user_id() . '_' . $referral_id;
        $state = get_transient($key);

        if (! is_array($state)) {
            return [
                'data'        => [],
                'errors'      => [],
                'schedule_id' => 0,
            ];
        }

        if ($consume) {
            delete_transient($key);
        }

        return [
            'data'        => is_array($state['data'] ?? null) ? $state['data'] : [],
            'errors'      => is_array($state['errors'] ?? null) ? $state['errors'] : [],
            'schedule_id' => absint($state['schedule_id'] ?? 0),
        ];
    }

    public static function get_edit_url(int $schedule_id): string
    {
        return add_query_arg(
            [
                'page'        => 'jm-referrals-schedule-edit',
                'schedule_id' => $schedule_id,
            ],
            admin_url('admin.php')
        );
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    public function build_team_assignment_options(int $referral_id): array
    {
        $options = [];

        foreach ($this->care_team_service->get_members_for_referral($referral_id) as $member) {
            if ('active' !== (string) ($member['assignment_status'] ?? '')) {
                continue;
            }

            $assignment_id = absint($member['id'] ?? 0);
            $user_id       = absint($member['user_id'] ?? 0);
            if ($assignment_id <= 0 || $user_id <= 0) {
                continue;
            }

            $role_key   = (string) ($member['team_role'] ?? '');
            $role_label = CareTeamService::role_labels()[$role_key] ?? $role_key;
            $staff_name = $this->user_provider->get_display_name($user_id);

            if ('' === $staff_name) {
                continue;
            }

            $options[] = [
                'id'    => $assignment_id,
                'label' => $staff_name . ' (' . $role_label . ')',
            ];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function sanitize_input(array $input): array
    {
        $repeat_type = isset($input['jmrs_schedule_repeat_type'])
            ? sanitize_key(wp_unslash($input['jmrs_schedule_repeat_type']))
            : ScheduleService::REPEAT_WEEKLY;

        if (! in_array($repeat_type, ScheduleService::allowed_repeat_types(), true)) {
            $repeat_type = ScheduleService::REPEAT_WEEKLY;
        }

        $status = isset($input['jmrs_schedule_status'])
            ? sanitize_key(wp_unslash($input['jmrs_schedule_status']))
            : ScheduleService::STATUS_ACTIVE;

        if (! in_array($status, ScheduleService::allowed_statuses(), true)) {
            $status = ScheduleService::STATUS_ACTIVE;
        }

        $days_raw = [];
        if (isset($input['jmrs_schedule_days_of_week']) && is_array($input['jmrs_schedule_days_of_week'])) {
            foreach ($input['jmrs_schedule_days_of_week'] as $day) {
                $day = sanitize_key(wp_unslash($day));
                if (in_array($day, ScheduleService::allowed_weekday_keys(), true)) {
                    $days_raw[] = $day;
                }
            }
            $days_raw = ScheduleService::normalize_weekday_list($days_raw);
        }

        return [
            'schedule_name'      => isset($input['jmrs_schedule_name'])
                ? sanitize_text_field(wp_unslash($input['jmrs_schedule_name']))
                : '',
            'start_date'         => isset($input['jmrs_schedule_start_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_schedule_start_date']))
                : '',
            'end_date'           => isset($input['jmrs_schedule_end_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_schedule_end_date']))
                : '',
            'repeat_type'        => $repeat_type,
            'repeat_interval'    => isset($input['jmrs_schedule_repeat_interval'])
                ? (string) max(1, absint(wp_unslash($input['jmrs_schedule_repeat_interval'])))
                : '1',
            'days_of_week'       => $days_raw,
            'start_time'         => isset($input['jmrs_schedule_start_time'])
                ? sanitize_text_field(wp_unslash($input['jmrs_schedule_start_time']))
                : '',
            'end_time'           => isset($input['jmrs_schedule_end_time'])
                ? sanitize_text_field(wp_unslash($input['jmrs_schedule_end_time']))
                : '',
            'visit_type'         => isset($input['jmrs_schedule_visit_type'])
                ? sanitize_text_field(wp_unslash($input['jmrs_schedule_visit_type']))
                : '',
            'team_assignment_id' => isset($input['jmrs_schedule_team_assignment_id'])
                ? (string) absint(wp_unslash($input['jmrs_schedule_team_assignment_id']))
                : '',
            'status'             => $status,
            'notes'              => isset($input['jmrs_schedule_notes'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_schedule_notes']))
                : '',
            'care_plan_id'       => isset($input['jmrs_schedule_care_plan_id'])
                ? (string) absint(wp_unslash($input['jmrs_schedule_care_plan_id']))
                : '',
        ];
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $errors
     */
    private function store_form_state(int $referral_id, array $data, array $errors, int $schedule_id = 0, string $channel = 'admin'): void
    {
        set_transient(
            self::FORM_TRANSIENT_PREFIX . $channel . '_' . get_current_user_id() . '_' . $referral_id,
            [
                'data'        => $data,
                'errors'      => $errors,
                'schedule_id' => $schedule_id,
            ],
            MINUTE_IN_SECONDS * 5
        );
    }

    private function redirect_after_save(int $referral_id, int $schedule_id, bool $success): void
    {
        if ($success) {
            $this->redirect_to_view($referral_id, true, false);
        }

        if ($schedule_id > 0) {
            wp_safe_redirect(self::get_edit_url($schedule_id));
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
            $args['jmrs_schedule_saved'] = '1';
            if ($created) {
                $args['jmrs_schedule_created'] = '1';
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

        return in_array($this->current_page(), ['jm-referrals-view', 'jm-referrals-schedule-edit'], true);
    }

    private function current_page(): string
    {
        return isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    }
}
