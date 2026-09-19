<?php

namespace JMReferral\Settings;

use JMReferral\Frontend\PublicReferralSettings;
use JMReferral\Portal\PortalSettings;

/**
 * Organisation and branding settings — single source of truth for client-facing identity.
 *
 * Precedence for presentation:
 * 1. Saved jmrs_organisation_settings (when option exists and field non-empty)
 * 2. Legacy PortalSettings / PublicReferralSettings values (upgrade compatibility)
 * 3. Built-in JM Healthcare defaults (existing production look without wizard)
 *
 * Technical identifiers (jmrs_*, JMReferral\, capabilities, routes) are unchanged.
 */
class OrganisationSettings
{
    public const OPTION_KEY = 'jmrs_organisation_settings';
    public const SCHEMA_OPTION_KEY = 'jmrs_organisation_settings_schema';
    public const SCHEMA_VERSION = 1;

    public const DEFAULT_DISPLAY_NAME = 'JM Healthcare';
    public const DEFAULT_PORTAL_TITLE = 'JM Healthcare Portal';
    public const DEFAULT_PRIMARY = '#0b5f4b';
    public const DEFAULT_SECONDARY = '#1a3a32';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'display_name'           => self::DEFAULT_DISPLAY_NAME,
            'legal_name'             => '',
            'trading_name'           => '',
            'logo_attachment_id'     => 0,
            'contact_email'          => '',
            'contact_phone'          => '',
            'website'                => '',
            'address'                => '',
            'portal_title'           => self::DEFAULT_PORTAL_TITLE,
            'primary_colour'         => self::DEFAULT_PRIMARY,
            'secondary_colour'       => self::DEFAULT_SECONDARY,
            'email_sender_name'      => '',
        ];
    }

    /**
     * Effective settings for UI (merged with legacy fallbacks). Never returns raw option alone.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::sanitize_settings(self::resolved_raw());
    }

    public static function display_name(): string
    {
        $name = trim((string) (self::all()['display_name'] ?? ''));

        return '' !== $name ? $name : self::DEFAULT_DISPLAY_NAME;
    }

    public static function legal_name(): string
    {
        return trim((string) (self::all()['legal_name'] ?? ''));
    }

    public static function trading_name(): string
    {
        return trim((string) (self::all()['trading_name'] ?? ''));
    }

    public static function portal_title(): string
    {
        $title = trim((string) (self::all()['portal_title'] ?? ''));

        return '' !== $title ? $title : self::DEFAULT_PORTAL_TITLE;
    }

    public static function contact_email(): string
    {
        return (string) (self::all()['contact_email'] ?? '');
    }

    public static function contact_phone(): string
    {
        return (string) (self::all()['contact_phone'] ?? '');
    }

    public static function website(): string
    {
        return (string) (self::all()['website'] ?? '');
    }

    public static function address(): string
    {
        return (string) (self::all()['address'] ?? '');
    }

    public static function primary_colour(): string
    {
        return (string) (self::all()['primary_colour'] ?? self::DEFAULT_PRIMARY);
    }

    public static function secondary_colour(): string
    {
        return (string) (self::all()['secondary_colour'] ?? self::DEFAULT_SECONDARY);
    }

    /**
     * Display name used in outgoing notification subjects / template company labels.
     */
    public static function email_sender_name(): string
    {
        $name = trim((string) (self::all()['email_sender_name'] ?? ''));

        return '' !== $name ? $name : self::display_name();
    }

    public static function logo_attachment_id(): int
    {
        return absint(self::all()['logo_attachment_id'] ?? 0);
    }

    /**
     * Resolved logo URL for presentation (empty when none).
     */
    public static function logo_url(): string
    {
        $id = self::logo_attachment_id();
        if ($id > 0) {
            $url = wp_get_attachment_image_url($id, 'full');
            if (is_string($url) && '' !== $url) {
                return $url;
            }
        }

        // Legacy portal URL fallback (pre–attachment-ID installs).
        $portal = get_option(PortalSettings::OPTION_KEY, []);
        if (! is_array($portal)) {
            return '';
        }

        $legacy = esc_url_raw((string) ($portal['logo_url'] ?? ''));

        return is_string($legacy) ? $legacy : '';
    }

    /**
     * WordPress site timezone display (informational; WP remains authoritative).
     */
    public static function effective_timezone_label(): string
    {
        if (function_exists('wp_timezone_string')) {
            $tz = wp_timezone_string();
            if (is_string($tz) && '' !== $tz) {
                return $tz;
            }
        }

        return (string) get_option('timezone_string', 'UTC');
    }

    /**
     * @param array<string, mixed> $input Allowlisted fields only.
     * @return array{ok: bool, errors: array<string, string>}
     */
    public static function update(array $input): array
    {
        $current = self::all();
        $allow   = [
            'display_name',
            'legal_name',
            'trading_name',
            'logo_attachment_id',
            'contact_email',
            'contact_phone',
            'website',
            'address',
            'portal_title',
            'primary_colour',
            'secondary_colour',
            'email_sender_name',
        ];

        $merged = $current;
        foreach ($allow as $key) {
            if (array_key_exists($key, $input)) {
                $merged[$key] = $input[$key];
            }
        }

        $sanitized = self::sanitize_settings($merged);
        $errors    = self::validate_for_save($sanitized, $input);

        if ([] !== $errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        update_option(self::OPTION_KEY, $sanitized, false);
        update_option(self::SCHEMA_OPTION_KEY, self::SCHEMA_VERSION, false);

        self::sync_legacy_presentation($sanitized);

        return ['ok' => true, 'errors' => []];
    }

    /**
     * Keep portal/public option mirrors in sync so older readers stay coherent.
     *
     * @param array<string, mixed> $org
     */
    private static function sync_legacy_presentation(array $org): void
    {
        $logo_url = '';
        $logo_id  = absint($org['logo_attachment_id'] ?? 0);
        if ($logo_id > 0) {
            $resolved = wp_get_attachment_image_url($logo_id, 'full');
            if (is_string($resolved)) {
                $logo_url = $resolved;
            }
        }

        PortalSettings::update([
            'portal_name'      => (string) ($org['portal_title'] ?? self::DEFAULT_PORTAL_TITLE),
            'company_name'     => (string) ($org['display_name'] ?? self::DEFAULT_DISPLAY_NAME),
            'logo_url'         => $logo_url,
            'primary_colour'   => (string) ($org['primary_colour'] ?? self::DEFAULT_PRIMARY),
            'secondary_colour' => (string) ($org['secondary_colour'] ?? self::DEFAULT_SECONDARY),
        ]);

        $public = PublicReferralSettings::all();
        PublicReferralSettings::update([
            'company_name'   => (string) ($org['display_name'] ?? self::DEFAULT_DISPLAY_NAME),
            'primary_colour' => (string) ($org['primary_colour'] ?? self::DEFAULT_PRIMARY),
            'contact_email'  => (string) ($org['contact_email'] ?? ($public['contact_email'] ?? '')),
            'contact_phone'  => (string) ($org['contact_phone'] ?? ($public['contact_phone'] ?? '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolved_raw(): array
    {
        $stored = get_option(self::OPTION_KEY, null);
        $has_org = is_array($stored);

        $base = self::defaults();
        if ($has_org) {
            return array_merge($base, $stored);
        }

        // Read legacy option arrays directly to avoid circular calls through
        // PortalSettings::all() / PublicReferralSettings::all() → PublicBranding.
        $portal = get_option(PortalSettings::OPTION_KEY, []);
        if (! is_array($portal)) {
            $portal = [];
        }

        $public = get_option(PublicReferralSettings::OPTION_KEY, []);
        if (! is_array($public)) {
            $public = [];
        }

        if ('' !== trim((string) ($portal['company_name'] ?? ''))) {
            $base['display_name'] = (string) $portal['company_name'];
        } elseif ('' !== trim((string) ($public['company_name'] ?? ''))) {
            $base['display_name'] = (string) $public['company_name'];
        }

        if ('' !== trim((string) ($portal['portal_name'] ?? ''))) {
            $base['portal_title'] = (string) $portal['portal_name'];
        }

        if ('' !== trim((string) ($portal['primary_colour'] ?? ''))) {
            $base['primary_colour'] = (string) $portal['primary_colour'];
        } elseif ('' !== trim((string) ($public['primary_colour'] ?? ''))) {
            $base['primary_colour'] = (string) $public['primary_colour'];
        }

        if ('' !== trim((string) ($portal['secondary_colour'] ?? ''))) {
            $base['secondary_colour'] = (string) $portal['secondary_colour'];
        }

        if ('' !== trim((string) ($public['contact_email'] ?? ''))) {
            $base['contact_email'] = (string) $public['contact_email'];
        } elseif ('' !== trim((string) ($portal['support_email'] ?? ''))) {
            $base['contact_email'] = (string) $portal['support_email'];
        }

        if ('' !== trim((string) ($public['contact_phone'] ?? ''))) {
            $base['contact_phone'] = (string) $public['contact_phone'];
        } elseif ('' !== trim((string) ($portal['support_phone'] ?? ''))) {
            $base['contact_phone'] = (string) $portal['support_phone'];
        }

        return $base;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function sanitize_settings(array $settings): array
    {
        $defaults = self::defaults();

        $display = sanitize_text_field((string) ($settings['display_name'] ?? $defaults['display_name']));
        if ('' === trim($display)) {
            $display = self::DEFAULT_DISPLAY_NAME;
        }

        $portal_title = sanitize_text_field((string) ($settings['portal_title'] ?? $defaults['portal_title']));
        if ('' === trim($portal_title)) {
            $portal_title = self::DEFAULT_PORTAL_TITLE;
        }

        $email = sanitize_email((string) ($settings['contact_email'] ?? ''));
        if ('' !== $email && ! is_email($email)) {
            $email = '';
        }

        $website = esc_url_raw((string) ($settings['website'] ?? ''));
        if ('' !== $website && ! self::is_safe_http_url($website)) {
            $website = '';
        }

        $logo_id = absint($settings['logo_attachment_id'] ?? 0);
        if ($logo_id > 0 && ! self::is_valid_logo_attachment($logo_id)) {
            $logo_id = 0;
        }

        return [
            'display_name'       => $display,
            'legal_name'         => sanitize_text_field((string) ($settings['legal_name'] ?? '')),
            'trading_name'       => sanitize_text_field((string) ($settings['trading_name'] ?? '')),
            'logo_attachment_id' => $logo_id,
            'contact_email'      => $email,
            'contact_phone'      => sanitize_text_field((string) ($settings['contact_phone'] ?? '')),
            'website'            => $website,
            'address'            => sanitize_textarea_field((string) ($settings['address'] ?? '')),
            'portal_title'       => $portal_title,
            'primary_colour'     => self::sanitize_hex_colour(
                (string) ($settings['primary_colour'] ?? self::DEFAULT_PRIMARY),
                self::DEFAULT_PRIMARY
            ),
            'secondary_colour'   => self::sanitize_hex_colour(
                (string) ($settings['secondary_colour'] ?? self::DEFAULT_SECONDARY),
                self::DEFAULT_SECONDARY
            ),
            'email_sender_name'  => sanitize_text_field((string) ($settings['email_sender_name'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $sanitized
     * @param array<string, mixed> $raw_input
     * @return array<string, string>
     */
    private static function validate_for_save(array $sanitized, array $raw_input): array
    {
        $errors = [];

        foreach (['display_name', 'legal_name', 'trading_name', 'portal_title', 'email_sender_name', 'contact_phone', 'address'] as $text_key) {
            if (! array_key_exists($text_key, $raw_input)) {
                continue;
            }
            if (is_array($raw_input[$text_key])) {
                $errors[$text_key] = __('Invalid value.', 'jm-referral-system');
            }
        }

        if (array_key_exists('contact_email', $raw_input)) {
            $raw_email = $raw_input['contact_email'];
            if (is_array($raw_email)) {
                $errors['contact_email'] = __('Invalid email address.', 'jm-referral-system');
            } else {
                $raw_email = trim((string) $raw_email);
                if ('' !== $raw_email && ! is_email(sanitize_email($raw_email))) {
                    $errors['contact_email'] = __('Invalid email address.', 'jm-referral-system');
                }
            }
        }

        if (array_key_exists('website', $raw_input)) {
            $raw_web = $raw_input['website'];
            if (is_array($raw_web)) {
                $errors['website'] = __('Invalid website URL.', 'jm-referral-system');
            } else {
                $raw_web = trim((string) $raw_web);
                if ('' !== $raw_web) {
                    $normalized = esc_url_raw($raw_web);
                    if ('' === $normalized || ! self::is_safe_http_url($normalized)) {
                        $errors['website'] = __('Invalid website URL.', 'jm-referral-system');
                    }
                }
            }
        }

        foreach (['primary_colour', 'secondary_colour'] as $colour_key) {
            if (! array_key_exists($colour_key, $raw_input)) {
                continue;
            }
            if (is_array($raw_input[$colour_key])) {
                $errors[$colour_key] = __('Invalid colour.', 'jm-referral-system');
                continue;
            }
            $raw = strtolower(trim((string) $raw_input[$colour_key]));
            if ('' !== $raw && ! preg_match('/^#[0-9a-f]{6}$/', $raw)) {
                $errors[$colour_key] = __('Colour must be a 6-digit hex value such as #17365D.', 'jm-referral-system');
            }
        }

        if (array_key_exists('logo_attachment_id', $raw_input)) {
            if (is_array($raw_input['logo_attachment_id'])) {
                $errors['logo_attachment_id'] = __('Invalid logo selection.', 'jm-referral-system');
            } else {
                $id = absint($raw_input['logo_attachment_id']);
                if ($id > 0 && ! self::is_valid_logo_attachment($id)) {
                    $errors['logo_attachment_id'] = __('Selected media is not a valid image.', 'jm-referral-system');
                }
            }
        }

        return $errors;
    }

    public static function sanitize_hex_colour(string $colour, string $fallback): string
    {
        $colour = strtolower(trim($colour));
        if (preg_match('/^#[0-9a-f]{6}$/', $colour)) {
            return $colour;
        }

        return $fallback;
    }

    public static function is_safe_http_url(string $url): bool
    {
        if ('' === $url) {
            return false;
        }

        $parts = wp_parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);

        return in_array($scheme, ['http', 'https'], true);
    }

    public static function is_valid_logo_attachment(int $attachment_id): bool
    {
        if ($attachment_id <= 0) {
            return false;
        }

        $post = get_post($attachment_id);
        if (! $post || 'attachment' !== $post->post_type) {
            return false;
        }

        $mime = (string) get_post_mime_type($attachment_id);

        return str_starts_with($mime, 'image/');
    }
}
