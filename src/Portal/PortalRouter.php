<?php

namespace JMReferral\Portal;

/**
 * Rewrite rules and query vars for the staff portal.
 */
class PortalRouter
{
    public const REWRITE_VERSION = '6.2.0';

    public const QV_PORTAL = 'jmrs_portal';
    public const QV_ROUTE = 'jmrs_portal_route';
    public const QV_ID = 'jmrs_portal_id';

    /** @var PortalController|null */
    private static $controller = null;

    public static function set_controller(PortalController $controller): void
    {
        self::$controller = $controller;
    }

    public static function register(): void
    {
        add_action('init', [self::class, 'register_rewrites']);
        add_filter('query_vars', [self::class, 'filter_query_vars']);
        add_action('template_redirect', [self::class, 'maybe_dispatch'], 0);
    }

    public static function register_rewrites(): void
    {
        if (! PortalSettings::is_enabled()) {
            return;
        }

        $base = preg_quote(PortalSettings::base_path(), '/');

        add_rewrite_rule(
            '^' . $base . '/?$',
            'index.php?' . self::QV_PORTAL . '=1&' . self::QV_ROUTE . '=dashboard',
            'top'
        );

        add_rewrite_rule(
            '^' . $base . '/referrals/?$',
            'index.php?' . self::QV_PORTAL . '=1&' . self::QV_ROUTE . '=referrals',
            'top'
        );

        add_rewrite_rule(
            '^' . $base . '/referrals/([0-9]+)/?$',
            'index.php?' . self::QV_PORTAL . '=1&' . self::QV_ROUTE . '=referral&' . self::QV_ID . '=$matches[1]',
            'top'
        );
    }

    /**
     * @param array<int, string> $vars
     * @return array<int, string>
     */
    public static function filter_query_vars(array $vars): array
    {
        $vars[] = self::QV_PORTAL;
        $vars[] = self::QV_ROUTE;
        $vars[] = self::QV_ID;

        return $vars;
    }

    public static function maybe_dispatch(): void
    {
        if ('1' !== (string) get_query_var(self::QV_PORTAL)) {
            return;
        }

        if (null === self::$controller) {
            status_header(503);
            wp_die(esc_html__('Staff portal is unavailable.', 'jm-referral-system'), '', ['response' => 503]);
        }

        self::$controller->dispatch();
        exit;
    }

    public static function flush_rules(): void
    {
        self::register_rewrites();
        flush_rewrite_rules(false);
        update_option(PortalSettings::REWRITE_VERSION_OPTION, self::REWRITE_VERSION);
    }
}
