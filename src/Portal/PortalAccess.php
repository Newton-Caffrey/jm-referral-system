<?php

namespace JMReferral\Portal;

use JMReferral\Permissions\Capabilities;
use JMReferral\Permissions\Roles;

/**
 * Portal eligibility, login redirects, wp-admin restriction, admin bar.
 */
class PortalAccess
{
    /**
     * Capabilities that grant portal entry.
     *
     * @return array<int, string>
     */
    public static function portal_entry_capabilities(): array
    {
        return [
            Capabilities::VIEW_DASHBOARD,
            Capabilities::VIEW_REFERRALS,
            Capabilities::VIEW_VISITS,
            Capabilities::VIEW_CARE_PLANS,
            Capabilities::VIEW_REPORTS,
            Capabilities::VIEW_OPERATIONAL_ALERTS,
            Capabilities::VIEW_MEDICATIONS,
            Capabilities::VIEW_CARE_TEAM,
            Capabilities::VIEW_SCHEDULES,
        ];
    }

    public static function current_user_can_access_portal(): bool
    {
        $user = wp_get_current_user();
        if (! ($user instanceof \WP_User) || $user->ID <= 0) {
            return false;
        }

        return self::user_can_access_portal($user);
    }

    public static function user_can_access_portal(\WP_User $user): bool
    {
        if (user_can($user, 'manage_options')) {
            return true;
        }

        foreach (self::portal_entry_capabilities() as $cap) {
            if (user_can($user, $cap)) {
                return true;
            }
        }

        return false;
    }

    public static function is_wordpress_administrator(?\WP_User $user = null): bool
    {
        $user = $user ?: wp_get_current_user();
        if (! ($user instanceof \WP_User) || $user->ID <= 0) {
            return false;
        }

        return user_can($user, 'manage_options')
            || in_array('administrator', (array) $user->roles, true);
    }

    /**
     * JMRS staff who are not WP Administrators (candidates for wp-admin redirect).
     */
    public static function is_restricted_jmrs_staff(?\WP_User $user = null): bool
    {
        $user = $user ?: wp_get_current_user();
        if (! ($user instanceof \WP_User) || $user->ID <= 0) {
            return false;
        }

        if (self::is_wordpress_administrator($user)) {
            return false;
        }

        if (! self::user_can_access_portal($user)) {
            return false;
        }

        $jm_roles = Roles::slugs();
        foreach ((array) $user->roles as $role) {
            if (in_array($role, $jm_roles, true)) {
                return true;
            }
        }

        // Capability-bearing users without JM role slug still restricted when setting on.
        return true;
    }

    public static function register(): void
    {
        add_filter('show_admin_bar', [self::class, 'filter_admin_bar'], 20);
        add_action('admin_init', [self::class, 'maybe_redirect_wp_admin'], 1);
        add_filter('login_redirect', [self::class, 'filter_login_redirect'], 20, 3);
        add_filter('logout_redirect', [self::class, 'filter_logout_redirect'], 20, 3);
    }

    /**
     * @param bool $show
     */
    public static function filter_admin_bar($show): bool
    {
        if (! PortalSettings::is_enabled()) {
            return (bool) $show;
        }

        if (self::is_wordpress_administrator()) {
            return (bool) $show;
        }

        if (self::current_user_can_access_portal()) {
            return false;
        }

        return (bool) $show;
    }

    public static function maybe_redirect_wp_admin(): void
    {
        if (! PortalSettings::is_enabled() || ! PortalSettings::redirect_wp_admin()) {
            return;
        }

        if (! is_user_logged_in() || self::is_wordpress_administrator()) {
            return;
        }

        if (! self::is_restricted_jmrs_staff()) {
            return;
        }

        if (self::is_allowed_admin_request()) {
            return;
        }

        wp_safe_redirect(PortalUrls::dashboard());
        exit;
    }

    public static function is_allowed_admin_request(): bool
    {
        if (wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON)) {
            return true;
        }

        $script = isset($_SERVER['PHP_SELF'])
            ? basename((string) wp_unslash($_SERVER['PHP_SELF']))
            : '';

        if (in_array($script, ['admin-ajax.php', 'admin-post.php', 'async-upload.php'], true)) {
            return true;
        }

        // Secure document downloads / CSV export / migration tools that hit admin.php.
        if (isset($_GET['jmrs_download_document']) || isset($_GET['jmrs_export']) || isset($_POST['jmrs_migrate_legacy_documents'])) {
            return true;
        }

        // Allow profile edits when needed.
        if (in_array($script, ['profile.php', 'user-edit.php'], true)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $redirect_to
     * @param string $requested_redirect_to
     * @param \WP_User|\WP_Error $user
     */
    public static function filter_login_redirect($redirect_to, $requested_redirect_to, $user)
    {
        if (! PortalSettings::is_enabled()) {
            return $redirect_to;
        }

        if (! ($user instanceof \WP_User) || $user->ID <= 0) {
            return $redirect_to;
        }

        if (! self::user_can_access_portal($user)) {
            return $redirect_to;
        }

        $requested = is_string($requested_redirect_to) ? $requested_redirect_to : '';
        if ('' !== $requested && PortalUrls::is_portal_url($requested)) {
            return $requested;
        }

        if (self::is_wordpress_administrator($user)) {
            // Keep normal admin redirect unless they asked for a portal URL.
            return $redirect_to;
        }

        $custom = (string) (PortalSettings::all()['login_redirect_url'] ?? '');
        if ('' !== $custom) {
            return $custom;
        }

        return PortalUrls::dashboard();
    }

    /**
     * @param string $redirect_to
     * @param string $requested
     * @param \WP_User $user
     */
    public static function filter_logout_redirect($redirect_to, $requested, $user)
    {
        if (! PortalSettings::is_enabled()) {
            return $redirect_to;
        }

        return home_url('/');
    }

    public static function require_login_redirect(): void
    {
        $target = (is_ssl() ? 'https://' : 'http://')
            . ($_SERVER['HTTP_HOST'] ?? '')
            . ($_SERVER['REQUEST_URI'] ?? '/');

        wp_safe_redirect(wp_login_url($target));
        exit;
    }
}
