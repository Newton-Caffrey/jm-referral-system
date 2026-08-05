<?php

namespace JMReferral\Admin;

/**
 * Shared admin CSS/JS for all JM Referral System screens.
 */
class AdminAssets
{
    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
        add_filter('admin_body_class', [self::class, 'body_class']);
    }

    /**
     * @param string $classes Space-separated body classes.
     */
    public static function body_class(string $classes): string
    {
        if (self::is_plugin_screen()) {
            $classes .= ' jmrs-admin';
        }

        return $classes;
    }

    public static function enqueue(string $hook_suffix): void
    {
        if (! self::is_plugin_screen($hook_suffix)) {
            return;
        }

        $css_path = JMRS_PLUGIN_PATH . 'assets/css/admin.css';
        $js_path  = JMRS_PLUGIN_PATH . 'assets/js/admin.js';
        $css_ver  = is_readable($css_path) ? (string) filemtime($css_path) : JMRS_VERSION;
        $js_ver   = is_readable($js_path) ? (string) filemtime($js_path) : JMRS_VERSION;

        wp_enqueue_style(
            'jmrs-admin',
            JMRS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            $css_ver
        );

        wp_enqueue_script(
            'jmrs-admin',
            JMRS_PLUGIN_URL . 'assets/js/admin.js',
            [],
            $js_ver,
            true
        );

        wp_localize_script(
            'jmrs-admin',
            'jmrsAdmin',
            [
                'i18n' => [
                    'saving'          => __('Saving...', 'jm-referral-system'),
                    'generating'      => __('Generating...', 'jm-referral-system'),
                    'uploading'       => __('Uploading...', 'jm-referral-system'),
                    'completing'      => __('Completing Visit...', 'jm-referral-system'),
                    'reviewing'       => __('Reviewing...', 'jm-referral-system'),
                    'archiving'       => __('Archiving...', 'jm-referral-system'),
                    'restoring'       => __('Restoring...', 'jm-referral-system'),
                    'exporting'       => __('Exporting...', 'jm-referral-system'),
                    'working'         => __('Working...', 'jm-referral-system'),
                    'confirmDefault'  => __('Are you sure you want to continue?', 'jm-referral-system'),
                ],
            ]
        );
    }

    private static function is_plugin_screen(string $hook_suffix = ''): bool
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';

        if ('' !== $page && 0 === strpos($page, 'jm-referrals')) {
            return true;
        }

        return is_string($hook_suffix) && false !== strpos($hook_suffix, 'jm-referrals');
    }
}
