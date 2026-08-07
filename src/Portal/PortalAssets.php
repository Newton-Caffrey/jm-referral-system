<?php

namespace JMReferral\Portal;

/**
 * Portal CSS/JS — loaded only on portal routes.
 *
 * Theme styles are dequeued so the portal shell does not inherit host-theme
 * typography, form, or layout rules. Admin bar assets are retained.
 */
class PortalAssets
{
    /** @var list<string> */
    private const KEEP_STYLE_HANDLES = [
        'admin-bar',
        'dashicons',
        'jmrs-portal',
    ];

    public static function enqueue(): void
    {
        self::dequeue_theme_styles();

        $css = JMRS_PLUGIN_PATH . 'assets/css/portal.css';
        $js  = JMRS_PLUGIN_PATH . 'assets/js/portal.js';

        wp_enqueue_style(
            'jmrs-portal',
            JMRS_PLUGIN_URL . 'assets/css/portal.css',
            [],
            file_exists($css) ? (string) filemtime($css) : JMRS_VERSION
        );

        wp_enqueue_script(
            'jmrs-portal',
            JMRS_PLUGIN_URL . 'assets/js/portal.js',
            [],
            file_exists($js) ? (string) filemtime($js) : JMRS_VERSION,
            true
        );

        $branding = PortalSettings::branding();
        $css_vars = sprintf(
            '.jmrs-portal{--jmrs-portal-primary:%1$s;--jmrs-portal-secondary:%2$s;}',
            esc_attr($branding['primary_colour']),
            esc_attr($branding['secondary_colour'])
        );
        wp_add_inline_style('jmrs-portal', $css_vars);
    }

    /**
     * Remove theme / builder / global stylesheet influence on the portal document.
     * Called immediately before wp_head() prints styles (template_redirect path).
     */
    public static function dequeue_theme_styles(): void
    {
        global $wp_styles;

        // Common WordPress / block / theme-json form and typography injectors.
        $always_dequeue = [
            'global-styles',
            'classic-theme-styles',
            'wp-block-library',
            'wp-block-library-theme',
            'core-block-supports',
            'wp-webfonts',
        ];

        foreach ($always_dequeue as $handle) {
            wp_dequeue_style($handle);
        }

        $theme = wp_get_theme();
        if ($theme->exists()) {
            wp_dequeue_style($theme->get_stylesheet());
            wp_dequeue_style($theme->get_template());
            if ($theme->parent()) {
                wp_dequeue_style($theme->parent()->get_stylesheet());
                wp_dequeue_style($theme->parent()->get_template());
            }
        }

        if (! ($wp_styles instanceof \WP_Styles)) {
            return;
        }

        foreach ((array) $wp_styles->queue as $handle) {
            $handle = (string) $handle;
            if ('' === $handle || self::should_keep_style($handle)) {
                continue;
            }

            // Keep WordPress core admin-bar related assets only.
            if (0 === strpos($handle, 'admin-bar')) {
                continue;
            }

            wp_dequeue_style($handle);
        }
    }

    private static function should_keep_style(string $handle): bool
    {
        return in_array($handle, self::KEEP_STYLE_HANDLES, true);
    }
}
