<?php

namespace JMReferral\Frontend;

use JMReferral\Documents\ReferralDocumentService;
use JMReferral\Notifications\NotificationService;
use JMReferral\Referral\PreferredContactMethods;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Referral\ReferralService;
use JMReferral\Services\ServiceTypeService;

/**
 * Validates and transforms public intake submissions into ReferralService::create().
 */
class PublicReferralService
{
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;
    private const MIN_COMPLETION_SECONDS = 3;
    private const GENERIC_ERROR = 'Unable to submit your referral. Please try again.';

    public function __construct(
        private ReferralService $referral_service,
        private ReferralRepository $referral_repository,
        private ServiceTypeService $service_type_service,
        private ReferralDocumentService $document_service,
        private NotificationService $notification_service
    ) {
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{
     *   ok: true,
     *   referral_id: int,
     *   referral_number: string,
     *   upload_partial: bool
     * }|array{
     *   ok: false,
     *   errors: array<string, string>,
     *   values: array<string, string>,
     *   silent: bool
     * }
     */
    public function submit(array $post, array $files = []): array
    {
        if (! PublicReferralSettings::is_enabled()) {
            return $this->fail(
                ['form' => __('Public referral submissions are currently unavailable.', 'jm-referral-system')],
                $this->empty_values()
            );
        }

        $values = $this->sanitize($post);

        if ($this->honeypot_triggered($post)) {
            $this->debug_log('honeypot triggered');

            return $this->fail(
                ['form' => __(self::GENERIC_ERROR, 'jm-referral-system')],
                $values,
                true
            );
        }

        if ($this->submitted_too_quickly($post)) {
            $this->debug_log('too-fast submission');

            return $this->fail(
                ['form' => __(self::GENERIC_ERROR, 'jm-referral-system')],
                $values
            );
        }

        if ($this->is_rate_limited()) {
            $this->debug_log('rate-limited');

            return $this->fail(
                ['form' => __(self::GENERIC_ERROR, 'jm-referral-system')],
                $values
            );
        }

        $errors = $this->validate($values);

        if (! empty($errors)) {
            $this->debug_log('validation failed');

            return $this->fail($errors, $values);
        }

        $create_input = $this->map_to_referral_input($values);
        $created      = $this->referral_service->create($create_input);

        if (false === $created) {
            $this->debug_log('create failed');

            return $this->fail(
                ['form' => __(self::GENERIC_ERROR, 'jm-referral-system')],
                $values
            );
        }

        $this->bump_rate_limit();
        $this->debug_log('submission accepted');

        $referral_id     = absint($created['id'] ?? 0);
        $referral_number = (string) ($created['referral_number'] ?? '');
        $upload_partial  = false;

        $settings = PublicReferralSettings::all();
        if (! empty($settings['allow_uploads']) && $referral_id > 0) {
            $upload_result  = $this->handle_uploads($referral_id, $files, $settings);
            $upload_partial = ! empty($upload_result['partial']);
        }

        $referral = $this->referral_repository->find($referral_id);
        if (is_array($referral)) {
            $this->notification_service->notify_public_referral_received($referral);
            $this->notification_service->notify_public_referral_confirmation($referral);
        }

        return [
            'ok'              => true,
            'referral_id'     => $referral_id,
            'referral_number' => $referral_number,
            'upload_partial'  => $upload_partial,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function empty_values(): array
    {
        return [
            'referrer_type'            => '',
            'referrer_name'            => '',
            'referrer_organisation'    => '',
            'referrer_email'           => '',
            'referrer_phone'           => '',
            'relationship_to_client'   => '',
            'client_first_name'        => '',
            'client_last_name'         => '',
            'client_email'             => '',
            'client_phone'             => '',
            'client_date_of_birth'     => '',
            'address_line_1'           => '',
            'address_line_2'           => '',
            'city'                     => '',
            'postcode'                 => '',
            'service_type_id'          => '',
            'care_start_date'          => '',
            'preferred_contact_method' => '',
            'priority'                 => 'routine',
            'care_requirements'        => '',
            'additional_information'   => '',
            'consent_permission'       => '',
            'consent_assessment'       => '',
            'consent_privacy'          => '',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, string>
     */
    private function sanitize(array $post): array
    {
        $values = $this->empty_values();

        $values['referrer_type'] = isset($post['jmrs_referrer_type'])
            ? sanitize_key(wp_unslash((string) $post['jmrs_referrer_type']))
            : '';
        $values['referrer_name'] = isset($post['jmrs_referrer_name'])
            ? sanitize_text_field(wp_unslash((string) $post['jmrs_referrer_name']))
            : '';
        $values['referrer_organisation'] = isset($post['jmrs_referrer_organisation'])
            ? sanitize_text_field(wp_unslash((string) $post['jmrs_referrer_organisation']))
            : '';
        $values['referrer_email'] = isset($post['jmrs_referrer_email'])
            ? sanitize_email(wp_unslash((string) $post['jmrs_referrer_email']))
            : '';
        $values['referrer_phone'] = isset($post['jmrs_referrer_phone'])
            ? sanitize_text_field(wp_unslash((string) $post['jmrs_referrer_phone']))
            : '';
        $values['relationship_to_client'] = isset($post['jmrs_relationship_to_client'])
            ? sanitize_text_field(wp_unslash((string) $post['jmrs_relationship_to_client']))
            : '';
        $values['client_first_name'] = isset($post['jmrs_client_first_name'])
            ? sanitize_text_field(wp_unslash((string) $post['jmrs_client_first_name']))
            : '';
        $values['client_last_name'] = isset($post['jmrs_client_last_name'])
            ? sanitize_text_field(wp_unslash((string) $post['jmrs_client_last_name']))
            : '';
        $values['client_email'] = isset($post['jmrs_client_email'])
            ? sanitize_email(wp_unslash((string) $post['jmrs_client_email']))
            : '';
        $values['client_phone'] = isset($post['jmrs_client_phone'])
            ? sanitize_text_field(wp_unslash((string) $post['jmrs_client_phone']))
            : '';
        $values['client_date_of_birth'] = isset($post['jmrs_client_date_of_birth'])
            ? sanitize_text_field(wp_unslash((string) $post['jmrs_client_date_of_birth']))
            : '';
        $values['address_line_1'] = isset($post['jmrs_address_line_1'])
            ? sanitize_text_field(wp_unslash((string) $post['jmrs_address_line_1']))
            : '';
        $values['address_line_2'] = isset($post['jmrs_address_line_2'])
            ? sanitize_text_field(wp_unslash((string) $post['jmrs_address_line_2']))
            : '';
        $values['city'] = isset($post['jmrs_city'])
            ? sanitize_text_field(wp_unslash((string) $post['jmrs_city']))
            : '';
        $values['postcode'] = isset($post['jmrs_postcode'])
            ? sanitize_text_field(wp_unslash((string) $post['jmrs_postcode']))
            : '';
        $values['service_type_id'] = isset($post['jmrs_service_type_id'])
            ? (string) absint(wp_unslash((string) $post['jmrs_service_type_id']))
            : '';
        $values['care_start_date'] = isset($post['jmrs_care_start_date'])
            ? sanitize_text_field(wp_unslash((string) $post['jmrs_care_start_date']))
            : '';
        $values['preferred_contact_method'] = isset($post['jmrs_preferred_contact_method'])
            ? sanitize_key(wp_unslash((string) $post['jmrs_preferred_contact_method']))
            : '';
        $priority = isset($post['jmrs_priority'])
            ? sanitize_key(wp_unslash((string) $post['jmrs_priority']))
            : 'routine';
        $values['priority'] = in_array($priority, ['routine', 'urgent'], true) ? $priority : 'routine';
        $values['care_requirements'] = isset($post['jmrs_care_requirements'])
            ? sanitize_textarea_field(wp_unslash((string) $post['jmrs_care_requirements']))
            : '';
        $values['additional_information'] = isset($post['jmrs_additional_information'])
            ? sanitize_textarea_field(wp_unslash((string) $post['jmrs_additional_information']))
            : '';
        $values['consent_permission'] = ! empty($post['jmrs_consent_permission']) ? '1' : '';
        $values['consent_assessment'] = ! empty($post['jmrs_consent_assessment']) ? '1' : '';
        $values['consent_privacy']    = ! empty($post['jmrs_consent_privacy']) ? '1' : '';

        return $values;
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private function validate(array $values): array
    {
        $errors = [];

        if ('' === $values['referrer_type'] || ! ReferrerTypes::is_valid($values['referrer_type'])) {
            $errors['referrer_type'] = __('Please select who is making this referral.', 'jm-referral-system');
        }

        if ('' === trim($values['referrer_name'])) {
            $errors['referrer_name'] = __('Please enter your name.', 'jm-referral-system');
        }

        if ('' === $values['referrer_email'] && '' === trim($values['referrer_phone'])) {
            $errors['referrer_contact'] = __('Please provide an email address or phone number.', 'jm-referral-system');
        }

        if ('' !== $values['referrer_email'] && ! is_email($values['referrer_email'])) {
            $errors['referrer_email'] = __('Please enter a valid email address.', 'jm-referral-system');
        }

        if ('' === trim($values['client_first_name'])) {
            $errors['client_first_name'] = __('Please enter the client’s first name.', 'jm-referral-system');
        }

        if ('' === trim($values['client_last_name'])) {
            $errors['client_last_name'] = __('Please enter the client’s last name.', 'jm-referral-system');
        }

        if ('' !== $values['client_email'] && ! is_email($values['client_email'])) {
            $errors['client_email'] = __('Please enter a valid client email address.', 'jm-referral-system');
        }

        if ('' !== $values['client_date_of_birth'] && ! $this->is_valid_date($values['client_date_of_birth'])) {
            $errors['client_date_of_birth'] = __('Please enter a valid date of birth.', 'jm-referral-system');
        }

        $service_type_id = absint($values['service_type_id']);
        if ($service_type_id <= 0 || ! $this->service_type_service->is_selectable($service_type_id)) {
            $errors['service_type_id'] = __('Please select a service type.', 'jm-referral-system');
        }

        if ('' !== $values['care_start_date'] && ! $this->is_valid_date($values['care_start_date'])) {
            $errors['care_start_date'] = __('Please enter a valid care start date.', 'jm-referral-system');
        }

        if (
            '' !== $values['preferred_contact_method']
            && ! PreferredContactMethods::is_valid($values['preferred_contact_method'])
        ) {
            $errors['preferred_contact_method'] = __('Please select a valid preferred contact method.', 'jm-referral-system');
        }

        if (! in_array($values['priority'], ['routine', 'urgent'], true)) {
            $errors['priority'] = __('Please select a priority.', 'jm-referral-system');
        }

        if ('' === trim($values['care_requirements'])) {
            $errors['care_requirements'] = __('Please describe the care requirements.', 'jm-referral-system');
        }

        if ('1' !== $values['consent_permission']) {
            $errors['consent_permission'] = __('Please confirm you have permission to share this information.', 'jm-referral-system');
        }

        if ('1' !== $values['consent_assessment']) {
            $errors['consent_assessment'] = __('Please confirm you understand how this information will be used.', 'jm-referral-system');
        }

        if ('1' !== $values['consent_privacy']) {
            $errors['consent_privacy'] = __('Please agree to the privacy notice.', 'jm-referral-system');
        }

        return $errors;
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private function map_to_referral_input(array $values): array
    {
        $settings = PublicReferralSettings::all();
        $first    = trim($values['client_first_name']);
        $last     = trim($values['client_last_name']);
        $client   = trim($first . ' ' . $last);

        $internal_priority = 'urgent' === $values['priority'] ? 'urgent' : 'medium';

        return [
            'client_name'              => $client,
            'client_email'             => $values['client_email'],
            'client_phone'             => $values['client_phone'],
            'service_type_id'          => $values['service_type_id'],
            'priority'                 => $internal_priority,
            'status'                   => 'new',
            'referrer_name'            => $values['referrer_name'],
            'referrer_email'           => $values['referrer_email'],
            'notes'                    => $values['additional_information'],
            'assigned_to'              => '0',
            'referral_source'          => 'website',
            'care_start_date'          => $values['care_start_date'],
            'preferred_contact_method' => $values['preferred_contact_method'],
            'care_requirements'        => $values['care_requirements'],
            'client_first_name'        => $first,
            'client_last_name'         => $last,
            'client_date_of_birth'     => $values['client_date_of_birth'],
            'address_line_1'           => $values['address_line_1'],
            'address_line_2'           => $values['address_line_2'],
            'city'                     => $values['city'],
            'postcode'                 => $values['postcode'],
            'referrer_type'            => $values['referrer_type'],
            'referrer_organisation'    => $values['referrer_organisation'],
            'referrer_phone'           => $values['referrer_phone'],
            'relationship_to_client'   => $values['relationship_to_client'],
            'submission_channel'       => SubmissionChannels::PUBLIC_WEBSITE,
            'public_consent_at'        => current_time('mysql'),
            'public_consent_version'   => (string) ($settings['consent_version'] ?? PublicReferralSettings::DEFAULT_CONSENT_VERSION),
        ];
    }

    /**
     * @param array<string, mixed> $files
     * @param array<string, mixed> $settings
     * @return array{partial: bool, uploaded: int, failed: int}
     */
    private function handle_uploads(int $referral_id, array $files, array $settings): array
    {
        $uploaded = 0;
        $failed   = 0;
        $max      = absint($settings['max_upload_count'] ?? 3);
        $max_bytes = PublicReferralSettings::max_upload_bytes();

        $file_entries = $this->normalize_files($files['jmrs_public_documents'] ?? null);

        if ([] === $file_entries) {
            return ['partial' => false, 'uploaded' => 0, 'failed' => 0];
        }

        if (count($file_entries) > $max) {
            $file_entries = array_slice($file_entries, 0, $max);
            $failed      += 1;
        }

        foreach ($file_entries as $file) {
            if (UPLOAD_ERR_NO_FILE === (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)) {
                continue;
            }

            $result = $this->document_service->upload_for_public_intake($referral_id, $file, $max_bytes);

            if (is_array($result) && isset($result['id'])) {
                ++$uploaded;
            } else {
                ++$failed;
            }
        }

        return [
            'partial'  => $failed > 0,
            'uploaded' => $uploaded,
            'failed'   => $failed,
        ];
    }

    /**
     * @param mixed $files_field
     * @return array<int, array<string, mixed>>
     */
    private function normalize_files(mixed $files_field): array
    {
        if (! is_array($files_field) || ! isset($files_field['name'])) {
            return [];
        }

        if (! is_array($files_field['name'])) {
            return [$files_field];
        }

        $out   = [];
        $count = count($files_field['name']);

        for ($i = 0; $i < $count; $i++) {
            $out[] = [
                'name'     => $files_field['name'][$i] ?? '',
                'type'     => $files_field['type'][$i] ?? '',
                'tmp_name' => $files_field['tmp_name'][$i] ?? '',
                'error'    => $files_field['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files_field['size'][$i] ?? 0,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function honeypot_triggered(array $post): bool
    {
        $hp = isset($post['jmrs_website'])
            ? trim((string) wp_unslash($post['jmrs_website']))
            : '';

        return '' !== $hp;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function submitted_too_quickly(array $post): bool
    {
        $started = isset($post['jmrs_form_started'])
            ? absint(wp_unslash($post['jmrs_form_started']))
            : 0;

        if ($started <= 0) {
            return true;
        }

        $now = time();

        if ($started > $now) {
            return true;
        }

        return ($now - $started) < self::MIN_COMPLETION_SECONDS;
    }

    private function rate_limit_key(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? (string) wp_unslash($_SERVER['REMOTE_ADDR'])
            : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT'])
            ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT'])
            : '';

        return 'jmrs_pub_rl_' . hash('sha256', $ip . '|' . $ua . '|jmrs_public_referral');
    }

    private function is_rate_limited(): bool
    {
        $count = absint(get_transient($this->rate_limit_key()));

        return $count >= self::RATE_LIMIT_MAX;
    }

    private function bump_rate_limit(): void
    {
        $key   = $this->rate_limit_key();
        $count = absint(get_transient($key));
        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);
    }

    private function is_valid_date(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $parts = explode('-', $date);
        if (3 !== count($parts)) {
            return false;
        }

        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $values
     * @return array{ok: false, errors: array<string, string>, values: array<string, string>, silent: bool}
     */
    private function fail(array $errors, array $values, bool $silent = false): array
    {
        return [
            'ok'     => false,
            'errors' => $errors,
            'values' => $values,
            'silent' => $silent,
        ];
    }

    private function debug_log(string $event): void
    {
        if (! defined('WP_DEBUG') || ! WP_DEBUG) {
            return;
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated; no PHI.
        error_log('[JMRS] public referral: ' . $event);
    }
}
