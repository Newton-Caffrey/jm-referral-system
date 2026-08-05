<?php

namespace JMReferral\Frontend;

/**
 * Registers [jmrs_public_referral_form] and loads frontend assets only when used.
 */
class PublicReferralShortcode
{
    private bool $assets_needed = false;

    public function __construct(
        private PublicReferralController $controller
    ) {
    }

    public function register(): void
    {
        add_shortcode('jmrs_public_referral_form', [$this, 'render']);
        add_filter('the_posts', [$this, 'detect_shortcode_in_posts'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
    }

    /**
     * Marks assets for enqueue when the shortcode appears in queried content.
     *
     * @param array<int, \WP_Post>|null $posts
     * @return array<int, \WP_Post>|null
     */
    public function detect_shortcode_in_posts($posts, $query = null)
    {
        if ($this->assets_needed || ! is_array($posts) || empty($posts)) {
            return $posts;
        }

        foreach ($posts as $post) {
            if (! ($post instanceof \WP_Post)) {
                continue;
            }

            if (has_shortcode((string) $post->post_content, 'jmrs_public_referral_form')) {
                $this->assets_needed = true;
                break;
            }
        }

        return $posts;
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function render($atts = []): string
    {
        $this->assets_needed = true;
        $this->enqueue_assets();

        // Shortcodes often run after wp_enqueue_scripts; print late if needed.
        if (did_action('wp_enqueue_scripts')) {
            if (wp_style_is('jmrs-public-referral', 'enqueued') && ! wp_style_is('jmrs-public-referral', 'done')) {
                wp_print_styles('jmrs-public-referral');
            }
            if (wp_script_is('jmrs-public-referral', 'enqueued') && ! wp_script_is('jmrs-public-referral', 'done')) {
                wp_print_scripts('jmrs-public-referral');
            }
        }

        $context = $this->controller->get_form_context();

        ob_start();

        if (($context['mode'] ?? '') === 'success') {
            $template = JMRS_PLUGIN_PATH . 'templates/frontend/public-referral-success.php';
        } else {
            $template = JMRS_PLUGIN_PATH . 'templates/frontend/public-referral-form.php';
        }

        if (is_readable($template)) {
            // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- scoped template vars.
            extract($context, EXTR_SKIP);
            include $template;
        }

        return (string) ob_get_clean();
    }

    public function maybe_enqueue_assets(): void
    {
        if (! $this->assets_needed) {
            return;
        }

        $this->enqueue_assets();
    }

    private function enqueue_assets(): void
    {
        if (wp_style_is('jmrs-public-referral', 'registered') || wp_style_is('jmrs-public-referral', 'enqueued')) {
            if (! wp_style_is('jmrs-public-referral', 'enqueued')) {
                wp_enqueue_style('jmrs-public-referral');
            }
            if (! wp_script_is('jmrs-public-referral', 'enqueued')) {
                wp_enqueue_script('jmrs-public-referral');
            }

            return;
        }

        $css_path = JMRS_PLUGIN_PATH . 'assets/css/public-referral.css';
        $js_path  = JMRS_PLUGIN_PATH . 'assets/js/public-referral.js';

        wp_register_style(
            'jmrs-public-referral',
            JMRS_PLUGIN_URL . 'assets/css/public-referral.css',
            [],
            is_readable($css_path) ? (string) filemtime($css_path) : JMRS_VERSION
        );
        wp_register_script(
            'jmrs-public-referral',
            JMRS_PLUGIN_URL . 'assets/js/public-referral.js',
            [],
            is_readable($js_path) ? (string) filemtime($js_path) : JMRS_VERSION,
            true
        );

        wp_enqueue_style('jmrs-public-referral');
        wp_enqueue_script('jmrs-public-referral');
    }
}
