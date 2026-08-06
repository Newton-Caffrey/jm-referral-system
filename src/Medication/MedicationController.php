<?php

namespace JMReferral\Medication;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Referral\ReferralViewController;

class MedicationController
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_medication_form_';

    public function __construct(
        private MedicationService $medication_service,
        private ReferralRepository $referral_repository,
        private AccessPolicy $access_policy
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_save']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    public function handle_save(): void
    {
        if (! isset($_POST['jmrs_save_medication'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_MEDICATIONS)) {
            wp_die(esc_html__('You do not have permission to manage medications.', 'jm-referral-system'));
        }

        $referral_id   = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        $medication_id = isset($_POST['jmrs_medication_id']) ? absint($_POST['jmrs_medication_id']) : 0;

        check_admin_referer('jmrs_save_medication_' . $referral_id, 'jmrs_medication_nonce');

        $result = $this->attempt_save($referral_id, $_POST, $medication_id);

        if (! $result['success']) {
            if (! empty($result['forbidden']) || ! empty($result['not_found'])) {
                wp_die(esc_html__('You do not have permission to manage medications for this referral.', 'jm-referral-system'));
            }

            $this->persist_form_state($referral_id, $result['data'], $result['errors'], $medication_id);
            $this->redirect_after_save($referral_id, $medication_id, false);
        }

        $this->redirect_to_view($referral_id, true, ! empty($result['created']));
    }

    /**
     * Shared sanitize → MedicationService::save() pipeline for admin and portal.
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
    public function attempt_save(int $referral_id, array $raw_input, int $medication_id): array
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
        $result = $this->medication_service->save($referral_id, $data, $medication_id);

        if (false === $result) {
            return [
                'success'   => false,
                'data'      => $data,
                'errors'    => [
                    'general' => __('Unable to save the medication. Please try again.', 'jm-referral-system'),
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
    public function persist_form_state(int $referral_id, array $data, array $errors, int $medication_id = 0, string $channel = 'admin'): void
    {
        $this->store_form_state($referral_id, $data, $errors, $medication_id, $channel);
    }

    public function render_edit(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_MEDICATIONS)) {
            wp_die(esc_html__('You do not have permission to manage medications.', 'jm-referral-system'));
        }

        $medication_id = isset($_GET['medication_id']) ? absint($_GET['medication_id']) : 0;
        $prepared      = $this->medication_service->prepare_edit($medication_id);

        if (isset($prepared['errors']) && is_array($prepared['errors'])) {
            $message = (string) reset($prepared['errors']);
            wp_die(esc_html('' !== $message ? $message : __('Unable to edit this medication.', 'jm-referral-system')));
        }

        $medication  = $prepared['medication'];
        $referral    = $prepared['referral'];
        $referral_id = absint($referral['id'] ?? 0);

        $form_state = self::get_form_state($referral_id);
        $errors     = $form_state['errors'];

        if (! empty($form_state['data']) && absint($form_state['medication_id'] ?? 0) === $medication_id) {
            $medication_data = array_merge(MedicationService::empty_form_data(), $form_state['data']);
        } else {
            $medication_data = MedicationService::map_to_form_data($medication);
        }

        $status_labels = MedicationService::status_labels();
        $route_labels  = MedicationService::route_labels();
        $back_url      = ReferralViewController::get_view_url($referral_id);

        include JMRS_PLUGIN_PATH . 'templates/medications/edit.php';
    }

    public function render_notices(): void
    {
        if (! $this->is_view_or_edit_screen()) {
            return;
        }

        if (isset($_GET['jmrs_medication_saved'])) {
            $created = isset($_GET['jmrs_medication_created']) && '1' === (string) $_GET['jmrs_medication_created'];
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html(
                $created
                    ? __('Medication created successfully.', 'jm-referral-system')
                    : __('Medication updated successfully.', 'jm-referral-system')
            );
            echo '</p></div>';
        }

        $referral_id = isset($_GET['referral_id']) ? absint($_GET['referral_id']) : 0;
        if ($referral_id <= 0) {
            return;
        }

        $state = self::get_form_state($referral_id, false);
        if (empty($state['errors'])) {
            return;
        }

        echo '<div class="notice notice-error is-dismissible"><p>';
        echo esc_html__('Please fix the following errors:', 'jm-referral-system');
        echo '</p><ul>';
        foreach ($state['errors'] as $message) {
            echo '<li>' . esc_html((string) $message) . '</li>';
        }
        echo '</ul></div>';
    }

    /**
     * @return array{data: array<string, string>, errors: array<string, string>, medication_id: int}
     */
    public static function get_form_state(int $referral_id, bool $consume = true, string $channel = 'admin'): array
    {
        $empty = [
            'data'          => [],
            'errors'        => [],
            'medication_id' => 0,
        ];

        if ($referral_id <= 0) {
            return $empty;
        }

        $key  = self::FORM_TRANSIENT_PREFIX . $channel . '_' . get_current_user_id() . '_' . $referral_id;
        $raw  = get_transient($key);

        if (! is_array($raw)) {
            return $empty;
        }

        if ($consume) {
            delete_transient($key);
        }

        return [
            'data'          => is_array($raw['data'] ?? null) ? $raw['data'] : [],
            'errors'        => is_array($raw['errors'] ?? null) ? $raw['errors'] : [],
            'medication_id' => absint($raw['medication_id'] ?? 0),
        ];
    }

    public static function get_edit_url(int $medication_id): string
    {
        return add_query_arg(
            [
                'page'          => 'jm-referrals-medications-edit',
                'medication_id' => $medication_id,
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
        $status = isset($input['jmrs_medication_status'])
            ? sanitize_key(wp_unslash($input['jmrs_medication_status']))
            : MedicationStatuses::ACTIVE;
        if (! in_array($status, MedicationStatuses::all(), true)) {
            $status = MedicationStatuses::ACTIVE;
        }

        $route = isset($input['jmrs_medication_route'])
            ? sanitize_key(wp_unslash($input['jmrs_medication_route']))
            : '';
        if (! in_array($route, MedicationService::allowed_routes(), true)) {
            $route = '';
        }

        return [
            'medication_name'    => isset($input['jmrs_medication_name'])
                ? sanitize_text_field(wp_unslash($input['jmrs_medication_name']))
                : '',
            'strength'           => isset($input['jmrs_medication_strength'])
                ? sanitize_text_field(wp_unslash($input['jmrs_medication_strength']))
                : '',
            'dosage'             => isset($input['jmrs_medication_dosage'])
                ? sanitize_text_field(wp_unslash($input['jmrs_medication_dosage']))
                : '',
            'route'              => $route,
            'frequency'          => isset($input['jmrs_medication_frequency'])
                ? sanitize_text_field(wp_unslash($input['jmrs_medication_frequency']))
                : '',
            'instructions'       => isset($input['jmrs_medication_instructions'])
                ? sanitize_textarea_field(wp_unslash($input['jmrs_medication_instructions']))
                : '',
            'start_date'         => isset($input['jmrs_medication_start_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_medication_start_date']))
                : '',
            'end_date'           => isset($input['jmrs_medication_end_date'])
                ? sanitize_text_field(wp_unslash($input['jmrs_medication_end_date']))
                : '',
            'medication_status'  => $status,
            'prescribing_source' => isset($input['jmrs_medication_prescribing_source'])
                ? sanitize_text_field(wp_unslash($input['jmrs_medication_prescribing_source']))
                : '',
        ];
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $errors
     */
    private function store_form_state(int $referral_id, array $data, array $errors, int $medication_id, string $channel = 'admin'): void
    {
        set_transient(
            self::FORM_TRANSIENT_PREFIX . $channel . '_' . get_current_user_id() . '_' . $referral_id,
            [
                'data'          => $data,
                'errors'        => $errors,
                'medication_id' => $medication_id,
            ],
            5 * MINUTE_IN_SECONDS
        );
    }

    private function redirect_after_save(int $referral_id, int $medication_id, bool $success): void
    {
        if ($medication_id > 0) {
            wp_safe_redirect(self::get_edit_url($medication_id));
            exit;
        }

        $this->redirect_to_view($referral_id, $success, false);
    }

    private function redirect_to_view(int $referral_id, bool $saved, bool $created): void
    {
        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];

        if ($saved) {
            $args['jmrs_medication_saved'] = '1';
            if ($created) {
                $args['jmrs_medication_created'] = '1';
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function is_view_or_edit_screen(): bool
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';

        return in_array($page, ['jm-referrals-view', 'jm-referrals-medications-edit'], true);
    }
}
