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

    /** Query arg carrying a non-PII receipt token (PRG). */
    public const RECEIPT_QUERY = 'jmrs_referral_receipt';

    /** Legacy query arg still accepted when reading receipts. */
    private const RECEIPT_QUERY_LEGACY = 'jmrs_receipt';

    /** Generic success flag when a receipt token could not be stored. */
    public const RECEIVED_QUERY = 'jmrs_referral_received';

    private const RECEIPT_TTL = 20 * MINUTE_IN_SECONDS;

    private const TRANSIENT_PREFIX = 'jmrs_pub_rcpt_';

    /** @var array<string, string>|null */
    private ?array $errors = null;

    /** @var array<string, string>|null */
    private ?array $values = null;

    private bool $focus_errors = false;

    private int $initial_step = 0;

    public function __construct(
        private PublicReferralService $public_referral_service,
        private ServiceTypeService $service_type_service
    ) {
    }

    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybe_handle_submission'], 5);
        add_action('template_redirect', [$this, 'maybe_send_receipt_nocache_headers'], 0);
    }

    /**
     * Avoid page-cache serving a stale form HTML when a receipt token is present.
     */
    public function maybe_send_receipt_nocache_headers(): void
    {
        if ($this->request_has_receipt_token() || $this->request_has_generic_received_flag()) {
            nocache_headers();
        }
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
            $this->values       = $this->public_referral_service->empty_values();
            $this->focus_errors = true;
            $this->initial_step = 5;

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
            $this->values       = $this->public_referral_service->empty_values();
            $this->focus_errors = true;
            $this->initial_step = 5;

            return;
        }

        $result = $this->public_referral_service->submit($_POST, $_FILES);

        if (! empty($result['ok'])) {
            $this->redirect_after_success(
                (string) ($result['referral_number'] ?? ''),
                ! empty($result['upload_partial'])
            );
        }

        $this->errors       = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        $this->values       = is_array($result['values'] ?? null) ? $result['values'] : $this->public_referral_service->empty_values();
        $this->focus_errors = true;
        $this->initial_step = PublicBranding::earliest_error_step($this->errors);
    }

    /**
     * @return array<string, mixed>
     */
    public function get_form_context(): array
    {
        $settings = PublicReferralSettings::all();
        $branding = PublicBranding::all();

        // Receipt / success state must be resolved before any form context.
        $receipt = $this->load_receipt_from_request();

        if (null !== $receipt) {
            nocache_headers();

            $success_message = trim((string) ($settings['success_message'] ?? ''));
            if ('' === $success_message) {
                $success_message = (string) PublicReferralSettings::defaults()['success_message'];
            }

            return [
                'mode'             => 'success',
                'referral_number'  => (string) ($receipt['referral_number'] ?? ''),
                'upload_partial'   => ! empty($receipt['upload_partial']),
                'success_message'  => $success_message,
                'settings'         => $settings,
                'branding'         => $branding,
                'generic_success'  => ! empty($receipt['generic']),
            ];
        }

        $errors = $this->errors ?? [];

        return [
            'mode'                    => 'form',
            'enabled'                 => ! empty($settings['enabled']),
            'settings'                => $settings,
            'branding'                => $branding,
            'values'                  => $this->values ?? $this->public_referral_service->empty_values(),
            'errors'                  => $errors,
            'focus_errors'            => $this->focus_errors,
            'initial_step'            => $this->focus_errors ? $this->initial_step : 0,
            'service_types'           => $this->service_type_service->get_active(),
            'referrer_types'          => ReferrerTypes::options(),
            'contact_methods'         => PreferredContactMethods::options(),
            'form_started'            => time(),
            'nonce_action'            => self::NONCE_ACTION,
            'nonce_field'             => self::NONCE_FIELD,
            'org_referrer_types'      => ['hospital', 'gp', 'social_worker', 'local_authority', 'care_provider', 'other'],
            'personal_referrer_types' => ['self', 'family', 'friend'],
        ];
    }

    /**
     * PRG redirect after create. Prefer a receipt token; fall back to generic flag.
     */
    private function redirect_after_success(string $referral_number, bool $upload_partial): void
    {
        $base = $this->resolve_form_page_url();

        $token = $this->store_receipt($referral_number, $upload_partial);

        nocache_headers();

        if (is_string($token) && '' !== $token) {
            $redirect = add_query_arg(self::RECEIPT_QUERY, $token, $base);
            wp_safe_redirect($redirect);
            exit;
        }

        $this->debug_log('receipt storage failed; using generic success redirect');

        $redirect = add_query_arg(self::RECEIVED_QUERY, '1', $base);
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Current page permalink (preferred) so the shortcode re-renders with the token.
     */
    private function resolve_form_page_url(): string
    {
        $post_id = get_queried_object_id();
        if ($post_id > 0) {
            $permalink = get_permalink($post_id);
            if (is_string($permalink) && '' !== $permalink) {
                return $this->strip_receipt_args($permalink);
            }
        }

        if (! empty($_SERVER['REQUEST_URI'])) {
            $uri = (string) wp_unslash($_SERVER['REQUEST_URI']);
            $url = home_url(explode('?', $uri)[0]);

            return $this->strip_receipt_args($url);
        }

        $referer = wp_get_referer();
        if (is_string($referer) && '' !== $referer) {
            return $this->strip_receipt_args($referer);
        }

        return home_url('/');
    }

    private function strip_receipt_args(string $url): string
    {
        return remove_query_arg(
            [
                self::RECEIPT_QUERY,
                self::RECEIPT_QUERY_LEGACY,
                self::RECEIVED_QUERY,
            ],
            $url
        );
    }

    /**
     * @return string|null Token on success, null if storage failed.
     */
    private function store_receipt(string $referral_number, bool $upload_partial): ?string
    {
        try {
            $token = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            $token = wp_generate_password(32, false, false);
        }

        if (! is_string($token) || ! preg_match('/^[a-f0-9]{32}$|^[A-Za-z0-9]{16,64}$/', $token)) {
            return null;
        }

        $payload = [
            'referral_number' => $referral_number,
            'upload_partial'  => $upload_partial,
            'company_name'    => PublicBranding::company_name(),
            'created_at'      => time(),
        ];

        $stored = set_transient(self::TRANSIENT_PREFIX . $token, $payload, self::RECEIPT_TTL);

        if (! $stored) {
            // Confirm whether a concurrent write already exists.
            $existing = get_transient(self::TRANSIENT_PREFIX . $token);
            if (! is_array($existing) || empty($existing['referral_number'])) {
                return null;
            }
        }

        return $token;
    }

    /**
     * @return array{
     *   referral_number: string,
     *   upload_partial: bool,
     *   generic?: bool
     * }|null
     */
    private function load_receipt_from_request(): ?array
    {
        $token = $this->read_receipt_token_from_request();

        if (is_string($token) && '' !== $token) {
            $data = get_transient(self::TRANSIENT_PREFIX . $token);

            if (is_array($data) && ! empty($data['referral_number'])) {
                // Keep transient until TTL so refresh/print still work.
                return [
                    'referral_number' => (string) $data['referral_number'],
                    'upload_partial'  => ! empty($data['upload_partial']),
                    'generic'         => false,
                ];
            }

            // Invalid/expired token: fall through to form (no internal error).
            return null;
        }

        if ($this->request_has_generic_received_flag()) {
            return [
                'referral_number' => '',
                'upload_partial'  => false,
                'generic'         => true,
            ];
        }

        return null;
    }

    private function read_receipt_token_from_request(): ?string
    {
        $raw = null;

        if (isset($_GET[self::RECEIPT_QUERY])) {
            $raw = wp_unslash((string) $_GET[self::RECEIPT_QUERY]);
        } elseif (isset($_GET[self::RECEIPT_QUERY_LEGACY])) {
            $raw = wp_unslash((string) $_GET[self::RECEIPT_QUERY_LEGACY]);
        }

        if (null === $raw) {
            return null;
        }

        $token = sanitize_text_field($raw);

        if ('' === $token || ! preg_match('/^[A-Za-z0-9]{16,64}$/', $token)) {
            return null;
        }

        return $token;
    }

    private function request_has_receipt_token(): bool
    {
        return null !== $this->read_receipt_token_from_request();
    }

    private function request_has_generic_received_flag(): bool
    {
        return isset($_GET[self::RECEIVED_QUERY])
            && '1' === sanitize_text_field(wp_unslash((string) $_GET[self::RECEIVED_QUERY]));
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
