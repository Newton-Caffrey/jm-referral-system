<?php

namespace JMReferral\Frontend;

use JMReferral\Referral\PreferredContactMethods;
use JMReferral\Services\ServiceTypeService;

/**
 * Handles public referral form POST + shortcode render data.
 */
class PublicReferralController
{
    public const NONCE_ACTION = 'jmrs_public_referral_submit';
    public const NONCE_FIELD = 'jmrs_public_referral_nonce';
    public const RECEIPT_QUERY = 'jmrs_receipt';

    /** @var array<string, string>|null */
    private ?array $errors = null;

    /** @var array<string, string>|null */
    private ?array $values = null;

    private bool $focus_errors = false;

    public function __construct(
        private PublicReferralService $public_referral_service,
        private ServiceTypeService $service_type_service
    ) {
    }

    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybe_handle_submission'], 5);
    }

    public function maybe_handle_submission(): void
    {
        if (! isset($_POST['jmrs_public_referral_submit'])) {
            return;
        }

        if (! PublicReferralSettings::is_enabled()) {
            $this->errors = [
                'form' => __('Public referral submissions are currently unavailable.', 'jm-referral-system'),
            ];
            $this->values = $this->public_referral_service->empty_values();
            $this->focus_errors = true;

            return;
        }

        if (
            ! isset($_POST[self::NONCE_FIELD])
            || ! wp_verify_nonce(
                sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_FIELD])),
                self::NONCE_ACTION
            )
        ) {
            $this->errors = [
                'form' => __('Unable to submit your referral. Please try again.', 'jm-referral-system'),
            ];
            $this->values = $this->public_referral_service->empty_values();
            $this->focus_errors = true;

            return;
        }

        $result = $this->public_referral_service->submit($_POST, $_FILES);

        if (! empty($result['ok'])) {
            $token = $this->store_receipt(
                (string) ($result['referral_number'] ?? ''),
                ! empty($result['upload_partial'])
            );

            $redirect = remove_query_arg([self::RECEIPT_QUERY], wp_get_referer() ?: home_url('/'));
            $redirect = add_query_arg(self::RECEIPT_QUERY, $token, $redirect);

            wp_safe_redirect($redirect);
            exit;
        }

        $this->errors       = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        $this->values       = is_array($result['values'] ?? null) ? $result['values'] : $this->public_referral_service->empty_values();
        $this->focus_errors = true;
    }

    /**
     * @return array<string, mixed>
     */
    public function get_form_context(): array
    {
        $settings = PublicReferralSettings::all();
        $receipt  = $this->consume_receipt_from_query();

        if (null !== $receipt) {
            return [
                'mode'             => 'success',
                'referral_number'  => (string) ($receipt['referral_number'] ?? ''),
                'upload_partial'   => ! empty($receipt['upload_partial']),
                'success_message'  => (string) ($settings['success_message'] ?? ''),
                'settings'         => $settings,
            ];
        }

        return [
            'mode'             => 'form',
            'enabled'          => ! empty($settings['enabled']),
            'settings'         => $settings,
            'values'           => $this->values ?? $this->public_referral_service->empty_values(),
            'errors'           => $this->errors ?? [],
            'focus_errors'     => $this->focus_errors,
            'service_types'    => $this->service_type_service->get_active(),
            'referrer_types'   => ReferrerTypes::options(),
            'contact_methods'  => PreferredContactMethods::options(),
            'form_started'     => time(),
            'nonce_action'     => self::NONCE_ACTION,
            'nonce_field'      => self::NONCE_FIELD,
        ];
    }

    private function store_receipt(string $referral_number, bool $upload_partial): string
    {
        $token = wp_generate_password(32, false, false);
        set_transient(
            'jmrs_pub_receipt_' . $token,
            [
                'referral_number' => $referral_number,
                'upload_partial'  => $upload_partial,
            ],
            30 * MINUTE_IN_SECONDS
        );

        return $token;
    }

    /**
     * @return array{referral_number: string, upload_partial: bool}|null
     */
    private function consume_receipt_from_query(): ?array
    {
        if (! isset($_GET[self::RECEIPT_QUERY])) {
            return null;
        }

        $token = sanitize_text_field(wp_unslash((string) $_GET[self::RECEIPT_QUERY]));
        if ('' === $token || ! preg_match('/^[A-Za-z0-9]{16,64}$/', $token)) {
            return null;
        }

        $key  = 'jmrs_pub_receipt_' . $token;
        $data = get_transient($key);

        if (! is_array($data) || empty($data['referral_number'])) {
            return null;
        }

        // Keep for refresh of success page within TTL; do not delete so refresh is safe.
        return [
            'referral_number' => (string) $data['referral_number'],
            'upload_partial'  => ! empty($data['upload_partial']),
        ];
    }
}
