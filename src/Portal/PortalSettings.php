<?php

namespace JMReferral\Portal;

/**
 * Staff portal settings (imperative option array).
 */
class PortalSettings
{
    public const OPTION_KEY = 'jmrs_staff_portal_settings';
    public const REWRITE_VERSION_OPTION = 'jmrs_portal_rewrite_version';

    public const DEFAULT_BASE_PATH = 'staff-portal';
    public const DEFAULT_PORTAL_NAME = 'JM Healthcare Portal';
    public const DEFAULT_COMPANY_NAME = 'JM Healthcare';
    public const DEFAULT_PRIMARY = '#0b5f4b';
    public const DEFAULT_SECONDARY = '#1a3a32';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled'              => false,
            'portal_name'          => self::DEFAULT_PORTAL_NAME,
            'company_name'         => self::DEFAULT_COMPANY_NAME,
            'base_path'            => self::DEFAULT_BASE_PATH,
            'logo_url'             => '',
            'primary_colour'       => self::DEFAULT_PRIMARY,
            'secondary_colour'     => self::DEFAULT_SECONDARY,
            'support_email'        => '',
            'support_phone'        => '',
            'login_redirect_url'   => '',
            'redirect_wp_admin'    => false,
        ];
    }

    /**
     * @return array<string, mixed>
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

    public static function redirect_wp_admin(): bool
    {
        return ! empty(self::all()['redirect_wp_admin']);
    }

    public static function base_path(): string
    {
        return (string) (self::all()['base_path'] ?? self::DEFAULT_BASE_PATH);
    }

    /**
     * Branding subset for templates and CSS variables.
     *
     * @return array{
     *     portal_name: string,
     *     company_name: string,
     *     logo_url: string,
     *     primary_colour: string,
     *     secondary_colour: string,
     *     support_email: string,
     *     support_phone: string
     * }
     */
    public static function branding(): array
    {
        $all = self::all();

        return [
            'portal_name'      => (string) $all['portal_name'],
            'company_name'     => (string) $all['company_name'],
            'logo_url'         => (string) $all['logo_url'],
            'primary_colour'   => (string) $all['primary_colour'],
            'secondary_colour' => (string) $all['secondary_colour'],
            'support_email'    => (string) $all['support_email'],
            'support_phone'    => (string) $all['support_phone'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, path_changed: bool, conflict: string}
     */
    public static function update(array $input): array
    {
        $current      = self::all();
        $merged       = array_merge($current, $input);
        $sanitized    = self::sanitize_settings($merged);
        $path_changed = ($sanitized['base_path'] !== $current['base_path'])
            || ((bool) $sanitized['enabled'] !== (bool) $current['enabled']);

        $conflict = self::detect_path_conflict((string) $sanitized['base_path']);

        update_option(self::OPTION_KEY, $sanitized);

        if ($path_changed || self::needs_rewrite_flush()) {
            PortalRouter::register_rewrites();
            flush_rewrite_rules(false);
            update_option(self::REWRITE_VERSION_OPTION, PortalRouter::REWRITE_VERSION);
        }

        return [
            'ok'           => true,
            'path_changed' => $path_changed,
            'conflict'     => $conflict,
        ];
    }

    public static function needs_rewrite_flush(): bool
    {
        $installed = (string) get_option(self::REWRITE_VERSION_OPTION, '');

        return $installed !== PortalRouter::REWRITE_VERSION;
    }

    public static function detect_path_conflict(string $base_path): string
    {
        $slug = trim($base_path, '/');
        if ('' === $slug) {
            return '';
        }

        $page = get_page_by_path($slug);
        if ($page instanceof \WP_Post) {
            return sprintf(
                /* translators: %s: page title */
                __('Warning: a WordPress page already uses the path “%s”. Choose a different portal base path.', 'jm-referral-system'),
                $slug
            );
        }

        return '';
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private static function sanitize_settings(array $settings): array
    {
        $base = isset($settings['base_path'])
            ? sanitize_title((string) $settings['base_path'])
            : self::DEFAULT_BASE_PATH;
        $base = trim($base, '/');
        if ('' === $base) {
            $base = self::DEFAULT_BASE_PATH;
        }

        $primary = self::sanitize_hex((string) ($settings['primary_colour'] ?? ''), self::DEFAULT_PRIMARY);
        $secondary = self::sanitize_hex((string) ($settings['secondary_colour'] ?? ''), self::DEFAULT_SECONDARY);

        $portal_name = sanitize_text_field((string) ($settings['portal_name'] ?? ''));
        if ('' === $portal_name) {
            $portal_name = self::DEFAULT_PORTAL_NAME;
        }

        $company = sanitize_text_field((string) ($settings['company_name'] ?? ''));
        if ('' === $company) {
            $company = self::DEFAULT_COMPANY_NAME;
        }

        $logo = isset($settings['logo_url']) ? esc_url_raw((string) $settings['logo_url']) : '';
        $support_email = isset($settings['support_email'])
            ? sanitize_email((string) $settings['support_email'])
            : '';
        $support_phone = isset($settings['support_phone'])
            ? sanitize_text_field((string) $settings['support_phone'])
            : '';
        $login_redirect = isset($settings['login_redirect_url'])
            ? esc_url_raw((string) $settings['login_redirect_url'])
            : '';

        return [
            'enabled'            => ! empty($settings['enabled']),
            'portal_name'        => $portal_name,
            'company_name'       => $company,
            'base_path'          => $base,
            'logo_url'           => $logo,
            'primary_colour'     => $primary,
            'secondary_colour'   => $secondary,
            'support_email'      => $support_email,
            'support_phone'      => $support_phone,
            'login_redirect_url' => $login_redirect,
            'redirect_wp_admin'  => ! empty($settings['redirect_wp_admin']),
        ];
    }

    private static function sanitize_hex(string $colour, string $default): string
    {
        $colour = strtolower(trim($colour));
        if (preg_match('/^#[0-9a-f]{6}$/', $colour)) {
            return $colour;
        }

        return $default;
    }
}
