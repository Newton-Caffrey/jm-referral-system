<?php

namespace JMReferral\Settings;

/**
 * Central helpers for module-disabled denials (presentation + mutation).
 */
class ModuleGate
{
    /**
     * Soft deny payload for portal/service callers (no activity written).
     *
     * @return array{ok: false, module_disabled: true, error: string, errors: array{general: string}}
     */
    public static function deny_result(string $module): array
    {
        $message = self::unavailable_message($module);

        return [
            'ok'              => false,
            'module_disabled' => true,
            'error'           => $message,
            'errors'          => [
                'general' => $message,
            ],
        ];
    }

    public static function unavailable_message(string $module): string
    {
        $labels = ModuleSettings::labels();
        $label  = (string) ($labels[$module] ?? __('This module', 'jm-referral-system'));

        return sprintf(
            /* translators: %s: module name */
            __('%s is disabled for this installation. Historical records remain available where shown; new actions are not permitted.', 'jm-referral-system'),
            $label
        );
    }

    /**
     * Hard stop for admin mutation handlers.
     */
    public static function wp_die_if_disabled(string $module): void
    {
        if (ModuleSettings::is_enabled($module)) {
            return;
        }

        wp_die(esc_html(self::unavailable_message($module)), '', ['response' => 403]);
    }

    /**
     * Whether a portal route belongs to a disabled module.
     */
    public static function portal_module_for_route(string $route): ?string
    {
        if (str_starts_with($route, 'referral_meeting')) {
            return ModuleSettings::MEETINGS;
        }

        if (in_array($route, ['homes', 'home', 'home_new', 'home_edit', 'bedroom_new', 'bedroom_edit'], true)) {
            return ModuleSettings::SUPPORTED_LIVING;
        }

        if (str_starts_with($route, 'occupancy')) {
            return ModuleSettings::SUPPORTED_LIVING;
        }

        if ('referral_assessment' === $route) {
            return ModuleSettings::ASSESSMENTS;
        }

        if ('management' === $route) {
            return ModuleSettings::MANAGEMENT_DASHBOARD;
        }

        return null;
    }
}
