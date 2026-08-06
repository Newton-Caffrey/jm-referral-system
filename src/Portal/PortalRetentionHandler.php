<?php

namespace JMReferral\Portal;

use JMReferral\Referral\ReferralRetentionService;

/**
 * Portal archive/restore POST handling.
 *
 * Delegates to ReferralRetentionService — no duplicated retention rules.
 */
class PortalRetentionHandler
{
    public function __construct(
        private ReferralRetentionService $retention_service
    ) {
    }

    /**
     * Processes portal archive/restore posts when present.
     * Redirects and exits on handled requests.
     */
    public function maybe_handle(): void
    {
        if (isset($_POST['jmrs_archive_referral'])) {
            $this->handle_archive();
        }

        if (isset($_POST['jmrs_restore_referral'])) {
            $this->handle_restore();
        }
    }

    private function handle_archive(): void
    {
        $referral_id = isset($_POST['referral_id']) ? absint($_POST['referral_id']) : 0;
        $nonce       = isset($_POST['jmrs_archive_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_archive_nonce']))
            : '';

        if ($referral_id <= 0 || ! wp_verify_nonce($nonce, 'jmrs_archive_referral_' . $referral_id)) {
            $this->redirect_notice(PortalUrls::referrals(), 'jmrs_archive_error', '1');
        }

        $reason = isset($_POST['archive_reason'])
            ? sanitize_textarea_field(wp_unslash($_POST['archive_reason']))
            : '';

        $result = $this->retention_service->archive($referral_id, $reason);

        $redirect = $this->resolve_redirect_url($referral_id);

        if (! empty($result['success'])) {
            $this->redirect_notice($redirect, 'jmrs_archived', '1');
        }

        $this->redirect_notice($redirect, 'jmrs_archive_error', '1');
    }

    private function handle_restore(): void
    {
        $referral_id = isset($_POST['referral_id']) ? absint($_POST['referral_id']) : 0;
        $nonce       = isset($_POST['jmrs_restore_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_restore_nonce']))
            : '';

        if ($referral_id <= 0 || ! wp_verify_nonce($nonce, 'jmrs_restore_referral_' . $referral_id)) {
            $this->redirect_notice(PortalUrls::referrals(), 'jmrs_restore_error', '1');
        }

        $result   = $this->retention_service->restore($referral_id);
        $redirect = PortalUrls::referral($referral_id);

        if (! empty($result['success'])) {
            $this->redirect_notice($redirect, 'jmrs_restored', '1');
        }

        $this->redirect_notice($redirect, 'jmrs_restore_error', '1');
    }

    private function resolve_redirect_url(int $referral_id): string
    {
        $redirect_to = isset($_POST['jmrs_portal_redirect'])
            ? esc_url_raw(wp_unslash($_POST['jmrs_portal_redirect']))
            : '';

        if ('' !== $redirect_to && PortalUrls::is_portal_url($redirect_to)) {
            return $redirect_to;
        }

        return PortalUrls::referral($referral_id);
    }

    /**
     * @return never
     */
    private function redirect_notice(string $url, string $arg, string $value): void
    {
        wp_safe_redirect(add_query_arg($arg, $value, $url));
        exit;
    }
}
