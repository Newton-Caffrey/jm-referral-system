<?php

namespace JMReferral\Portal;

/**
 * Portal URL helpers.
 */
class PortalUrls
{
    public static function home(): string
    {
        return home_url('/' . PortalSettings::base_path() . '/');
    }

    public static function dashboard(): string
    {
        return self::home();
    }

    public static function referrals(): string
    {
        return home_url('/' . PortalSettings::base_path() . '/referrals/');
    }

    public static function referral(int $referral_id): string
    {
        return home_url('/' . PortalSettings::base_path() . '/referrals/' . max(0, $referral_id) . '/');
    }

    /**
     * @param array<string, scalar> $args
     */
    public static function referrals_with_args(array $args): string
    {
        return add_query_arg($args, self::referrals());
    }

    public static function is_portal_url(string $url): bool
    {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || '' === $path) {
            return false;
        }

        $base = '/' . trim(PortalSettings::base_path(), '/') . '/';

        return str_starts_with(trailingslashit($path), $base)
            || rtrim($path, '/') === rtrim($base, '/');
    }
}
