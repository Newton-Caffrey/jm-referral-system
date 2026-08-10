<?php

namespace JMReferral\Notifications;

/**
 * Resolves email template files from a single canonical directory.
 *
 * Canonical path: src/Notifications/Templates/ (case-sensitive on Linux).
 */
class EmailTemplateResolver
{
    /**
     * Allowed template basenames (without .php).
     *
     * @var array<int, string>
     */
    private const ALLOWED_TEMPLATES = [
        'referral-created',
        'referral-assigned',
        'status-changed',
        'public-referral-received',
        'public-referral-confirmation',
        'interest-expressed',
        'package-cost-sent',
    ];

    /**
     * Relative directory under the plugin root (forward slashes).
     */
    private const TEMPLATE_DIRECTORY = 'src/Notifications/Templates';

    /**
     * Resolves an absolute readable template path, or null if unavailable.
     *
     * Template names must match the allowlist exactly. sanitize_file_name() is
     * intentionally not used — WordPress filters on that hook can mutate names
     * (e.g. referral-assigned) and break legitimate templates.
     */
    public function resolve(string $template): ?string
    {
        if (! in_array($template, self::ALLOWED_TEMPLATES, true)) {
            $this->log_generic_failure();

            return null;
        }

        if (! defined('JMRS_PLUGIN_PATH')) {
            $this->log_generic_failure();

            return null;
        }

        // Use the allowlisted string only (never a mutated user/filter value).
        $path = trailingslashit(JMRS_PLUGIN_PATH) . self::TEMPLATE_DIRECTORY . '/' . $template . '.php';

        if (! is_readable($path)) {
            $this->log_generic_failure();

            return null;
        }

        return $path;
    }

    /**
     * @return array<int, string>
     */
    public static function allowed_templates(): array
    {
        return self::ALLOWED_TEMPLATES;
    }

    /**
     * Logs a generic failure without filesystem paths or template names.
     */
    private function log_generic_failure(): void
    {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional generic operational signal only.
        error_log('JM Referral System: an email template could not be loaded.');
    }
}
