<?php

namespace JMReferral\Frontend;

/**
 * Settings for the public referral intake form.
 *
 * Stored as a single option array (imperative Settings UI, not WP Settings API).
 */
class PublicReferralSettings
{
    public const OPTION_KEY = 'jmrs_public_referral_settings';

    public const DEFAULT_CONSENT_VERSION = '1.0';
    public const DEFAULT_MAX_UPLOAD_COUNT = 3;
    public const DEFAULT_MAX_UPLOAD_SIZE_MB = 10;

    /**
     * @return array{
     *   enabled: bool,
     *   privacy_notice_url: string,
     *   consent_version: string,
     *   notification_email: string,
     *   success_message: string,
     *   allow_uploads: bool,
     *   max_upload_count: int,
     *   max_upload_size_mb: int
     * }
     */
    public static function defaults(): array
    {
        return [
            'enabled'             => false,
            'privacy_notice_url'  => '',
            'consent_version'     => self::DEFAULT_CONSENT_VERSION,
            'notification_email'  => '',
            'success_message'     => __(
                'Thank you. Your referral has been received. Our team will review it and contact you if further information is needed.',
                'jm-referral-system'
            ),
            'allow_uploads'       => false,
            'max_upload_count'    => self::DEFAULT_MAX_UPLOAD_COUNT,
            'max_upload_size_mb'  => self::DEFAULT_MAX_UPLOAD_SIZE_MB,
        ];
    }

    /**
     * @return array{
     *   enabled: bool,
     *   privacy_notice_url: string,
     *   consent_version: string,
     *   notification_email: string,
     *   success_message: string,
     *   allow_uploads: bool,
     *   max_upload_count: int,
     *   max_upload_size_mb: int
     * }
     */
    public static function all(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        return self::sanitize_settings(array_merge(self::defaults(), $stored));
    }

    public static function is_enabled(): bool
    {
        return ! empty(self::all()['enabled']);
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function update(array $input): bool
    {
        $current = self::all();
        $merged  = array_merge($current, $input);

        return update_option(self::OPTION_KEY, self::sanitize_settings($merged));
    }

    public static function max_upload_bytes(): int
    {
        $mb = absint(self::all()['max_upload_size_mb'] ?? self::DEFAULT_MAX_UPLOAD_SIZE_MB);

        return max(1, $mb) * 1048576;
    }

    public static function notification_email(): string
    {
        $email = (string) (self::all()['notification_email'] ?? '');
        if ('' !== $email && is_email($email)) {
            return $email;
        }

        $admin = (string) get_option('admin_email');

        return is_email($admin) ? $admin : '';
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{
     *   enabled: bool,
     *   privacy_notice_url: string,
     *   consent_version: string,
     *   notification_email: string,
     *   success_message: string,
     *   allow_uploads: bool,
     *   max_upload_count: int,
     *   max_upload_size_mb: int
     * }
     */
    private static function sanitize_settings(array $settings): array
    {
        $email = isset($settings['notification_email'])
            ? sanitize_email((string) $settings['notification_email'])
            : '';

        $privacy = isset($settings['privacy_notice_url'])
            ? esc_url_raw((string) $settings['privacy_notice_url'])
            : '';

        $consent = isset($settings['consent_version'])
            ? sanitize_text_field((string) $settings['consent_version'])
            : self::DEFAULT_CONSENT_VERSION;
        if ('' === $consent) {
            $consent = self::DEFAULT_CONSENT_VERSION;
        }

        $success = isset($settings['success_message'])
            ? sanitize_textarea_field((string) $settings['success_message'])
            : self::defaults()['success_message'];
        if ('' === trim($success)) {
            $success = self::defaults()['success_message'];
        }

        $max_count = absint($settings['max_upload_count'] ?? self::DEFAULT_MAX_UPLOAD_COUNT);
        if ($max_count < 1) {
            $max_count = 1;
        }
        if ($max_count > 10) {
            $max_count = 10;
        }

        $max_mb = absint($settings['max_upload_size_mb'] ?? self::DEFAULT_MAX_UPLOAD_SIZE_MB);
        if ($max_mb < 1) {
            $max_mb = 1;
        }
        if ($max_mb > 20) {
            $max_mb = 20;
        }

        return [
            'enabled'            => ! empty($settings['enabled']),
            'privacy_notice_url' => $privacy,
            'consent_version'    => $consent,
            'notification_email' => $email,
            'success_message'    => $success,
            'allow_uploads'      => ! empty($settings['allow_uploads']),
            'max_upload_count'   => $max_count,
            'max_upload_size_mb' => $max_mb,
        ];
    }
}
