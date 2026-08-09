<?php

namespace JMReferral\Portal;

/**
 * Rewrite rules and query vars for the staff portal.
 */
class PortalRouter
{
    public const REWRITE_VERSION = '1.2.1';

    public const QV_PORTAL = 'jmrs_portal';
    public const QV_ROUTE = 'jmrs_portal_route';
    public const QV_ID = 'jmrs_portal_id';
    public const QV_ENTITY = 'jmrs_portal_entity';

    /** @var PortalController|null */
    private static $controller = null;

    public static function set_controller(PortalController $controller): void
    {
        self::$controller = $controller;
    }

    public static function register(): void
    {
        add_action('init', [self::class, 'register_rewrites']);
        add_action('init', [self::class, 'maybe_flush_on_version_change'], 99);
        add_filter('query_vars', [self::class, 'filter_query_vars']);
        add_action('template_redirect', [self::class, 'maybe_dispatch'], 0);
    }

    public static function register_rewrites(): void
    {
        if (! PortalSettings::is_enabled()) {
            return;
        }

        $base = preg_quote(PortalSettings::base_path(), '/');

        $rules = [
            ['/?$', 'dashboard', null, null],
            ['/referrals/?$', 'referrals', null, null],
            ['/referrals/([0-9]+)/edit/?$', 'referral_edit', '$matches[1]', null],
            ['/referrals/([0-9]+)/assessment/?$', 'referral_assessment', '$matches[1]', null],
            ['/referrals/([0-9]+)/care-plan/review/?$', 'care_plan_review', '$matches[1]', null],
            ['/referrals/([0-9]+)/care-plan/?$', 'referral_care_plan', '$matches[1]', null],
            ['/referrals/([0-9]+)/medications/new/?$', 'medication_new', '$matches[1]', null],
            ['/referrals/([0-9]+)/medications/([0-9]+)/edit/?$', 'medication_edit', '$matches[1]', '$matches[2]'],
            ['/referrals/([0-9]+)/care-team/new/?$', 'care_team_new', '$matches[1]', null],
            ['/referrals/([0-9]+)/care-team/([0-9]+)/edit/?$', 'care_team_edit', '$matches[1]', '$matches[2]'],
            ['/referrals/([0-9]+)/schedules/new/?$', 'schedule_new', '$matches[1]', null],
            ['/referrals/([0-9]+)/schedules/([0-9]+)/edit/?$', 'schedule_edit', '$matches[1]', '$matches[2]'],
            ['/referrals/([0-9]+)/schedules/([0-9]+)/generate/?$', 'schedule_generate', '$matches[1]', '$matches[2]'],
            ['/referrals/([0-9]+)/visits/new/?$', 'visit_new', '$matches[1]', null],
            ['/referrals/([0-9]+)/visits/([0-9]+)/edit/?$', 'visit_edit', '$matches[1]', '$matches[2]'],
            ['/referrals/([0-9]+)/visits/([0-9]+)/execute/?$', 'visit_execute', '$matches[1]', '$matches[2]'],
            ['/referrals/([0-9]+)/visits/([0-9]+)/review/?$', 'visit_review', '$matches[1]', '$matches[2]'],
            ['/referrals/([0-9]+)/?$', 'referral', '$matches[1]', null],
            ['/homes/?$', 'homes', null, null],
            ['/homes/new/?$', 'home_new', null, null],
            ['/homes/([0-9]+)/edit/?$', 'home_edit', '$matches[1]', null],
            ['/homes/([0-9]+)/bedrooms/new/?$', 'bedroom_new', '$matches[1]', null],
            ['/homes/([0-9]+)/bedrooms/([0-9]+)/edit/?$', 'bedroom_edit', '$matches[1]', '$matches[2]'],
            ['/homes/([0-9]+)/?$', 'home', '$matches[1]', null],
            ['/occupancy/?$', 'occupancy', null, null],
            ['/occupancy/place/?$', 'occupancy_place', null, null],
            ['/occupancy/([0-9]+)/transfer/?$', 'occupancy_transfer', '$matches[1]', null],
            ['/occupancy/([0-9]+)/end/?$', 'occupancy_end', '$matches[1]', null],
        ];

        foreach ($rules as [$pattern, $route, $id, $entity]) {
            $query = self::QV_PORTAL . '=1&' . self::QV_ROUTE . '=' . $route;
            if (null !== $id) {
                $query .= '&' . self::QV_ID . '=' . $id;
            }
            if (null !== $entity) {
                $query .= '&' . self::QV_ENTITY . '=' . $entity;
            }

            add_rewrite_rule('^' . $base . $pattern, 'index.php?' . $query, 'top');
        }
    }

    public static function maybe_flush_on_version_change(): void
    {
        if (! PortalSettings::is_enabled()) {
            return;
        }

        if (! PortalSettings::needs_rewrite_flush()) {
            return;
        }

        self::flush_rules();
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
        $vars[] = self::QV_ENTITY;

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
