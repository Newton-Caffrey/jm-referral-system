<?php

namespace JMReferral\Portal;

/**
 * Portal CSS/JS — loaded only on portal routes.
 */
class PortalAssets
{
    public static function enqueue(): void
    {
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
}
