<?php

namespace JMReferral\Settings;

/**
 * Installation-wide operational module switches.
 *
 * Disabled = hide navigation / block new mutations. Historical data is preserved.
 * Existing installations default all known modules ON when the option is absent.
 */
class ModuleSettings
{
    public const OPTION_KEY = 'jmrs_module_settings';
    public const SCHEMA_OPTION_KEY = 'jmrs_module_settings_schema';
    public const SCHEMA_VERSION = 1;

    public const MEETINGS = 'meetings';
    public const ASSESSMENTS = 'assessments';
    public const PACKAGE_COSTING = 'package_costing';
    public const LA_DECISIONS = 'la_decisions';
    public const SUPPORTED_LIVING = 'supported_living';
    public const TRANSITION = 'transition';
    public const MANAGEMENT_DASHBOARD = 'management_dashboard';
    public const REPORTS = 'reports';

    /**
     * @return list<string>
     */
    public static function known_modules(): array
    {
        return [
            self::MEETINGS,
            self::ASSESSMENTS,
            self::PACKAGE_COSTING,
            self::LA_DECISIONS,
            self::SUPPORTED_LIVING,
            self::TRANSITION,
            self::MANAGEMENT_DASHBOARD,
            self::REPORTS,
        ];
    }

    /**
     * Hard dependencies: module => list of modules that must also be enabled.
     *
     * Verified against pipeline: assessment → package cost → LA decision → transition/care commencement.
     *
     * @return array<string, list<string>>
     */
    public static function dependencies(): array
    {
        return [
            self::PACKAGE_COSTING => [self::ASSESSMENTS],
            self::LA_DECISIONS    => [self::PACKAGE_COSTING],
            self::TRANSITION      => [self::LA_DECISIONS],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::known_modules() as $module) {
            $defaults[$module] = true;
        }

        return $defaults;
    }

    /**
     * @return array<string, bool>
     */
    public static function all(): array
    {
        return self::sanitize_settings(self::resolved_raw());
    }

    public static function is_enabled(string $module): bool
    {
        if (! in_array($module, self::known_modules(), true)) {
            return false;
        }

        return ! empty(self::all()[$module]);
    }

    /**
     * Human-readable module titles for settings UI.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::MEETINGS              => __('Meetings', 'jm-referral-system'),
            self::ASSESSMENTS           => __('Assessments', 'jm-referral-system'),
            self::PACKAGE_COSTING       => __('Package Costing', 'jm-referral-system'),
            self::LA_DECISIONS          => __('Local Authority Decisions', 'jm-referral-system'),
            self::SUPPORTED_LIVING      => __('Supported Living (homes / occupancy)', 'jm-referral-system'),
            self::TRANSITION            => __('Transition & Care Commencement', 'jm-referral-system'),
            self::MANAGEMENT_DASHBOARD  => __('Management Dashboard', 'jm-referral-system'),
            self::REPORTS               => __('Reports', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return [
            self::MEETINGS             => __('Referral meeting records and attendee workflows.', 'jm-referral-system'),
            self::ASSESSMENTS          => __('Assessment scheduling, outcomes, and related pipeline steps.', 'jm-referral-system'),
            self::PACKAGE_COSTING      => __('Prepare and send package costs. Requires Assessments.', 'jm-referral-system'),
            self::LA_DECISIONS         => __('Record authority decisions. Requires Package Costing.', 'jm-referral-system'),
            self::SUPPORTED_LIVING     => __('Homes, bedrooms, occupancy, and Place Resident. Own-home referrals are unaffected.', 'jm-referral-system'),
            self::TRANSITION           => __('Transition planning and care commencement. Requires Local Authority Decisions.', 'jm-referral-system'),
            self::MANAGEMENT_DASHBOARD => __('Senior management operations dashboard. Module-specific blocks hide when their modules are off.', 'jm-referral-system'),
            self::REPORTS              => __('Admin reports and CSV exports. Supported Living sections hide when that module is off.', 'jm-referral-system'),
        ];
    }

    /**
     * @param array<string, mixed> $input Allowlisted module keys only (truthy = enabled).
     * @return array{ok: bool, errors: array<string, string>, warnings: array<int, string>}
     */
    public static function update(array $input): array
    {
        $errors = [];

        foreach (array_keys($input) as $key) {
            if (! in_array((string) $key, self::known_modules(), true)) {
                $errors[(string) $key] = __('Unknown module key.', 'jm-referral-system');
            } elseif (is_array($input[$key])) {
                $errors[(string) $key] = __('Invalid module value.', 'jm-referral-system');
            }
        }

        if ([] !== $errors) {
            return ['ok' => false, 'errors' => $errors, 'warnings' => []];
        }

        $merged = self::defaults();
        foreach (self::known_modules() as $module) {
            // Explicit allowlist: missing key = disabled when saving from checkbox form.
            if (array_key_exists($module, $input)) {
                $merged[$module] = ! empty($input[$module]);
            } else {
                $merged[$module] = false;
            }
        }

        $dep_errors = self::validate_dependencies($merged);
        if ([] !== $dep_errors) {
            return ['ok' => false, 'errors' => $dep_errors, 'warnings' => []];
        }

        $warnings = self::disable_warnings($merged);

        $sanitized = self::sanitize_settings($merged);
        update_option(self::OPTION_KEY, $sanitized, false);
        update_option(self::SCHEMA_OPTION_KEY, self::SCHEMA_VERSION, false);

        return ['ok' => true, 'errors' => [], 'warnings' => $warnings];
    }

    /**
     * @param array<string, bool> $modules
     * @return array<string, string>
     */
    public static function validate_dependencies(array $modules): array
    {
        $errors = [];
        $labels = self::labels();

        foreach (self::dependencies() as $module => $requires) {
            if (empty($modules[$module])) {
                continue;
            }

            foreach ($requires as $required) {
                if (empty($modules[$required])) {
                    $errors[$module] = sprintf(
                        /* translators: 1: module name, 2: required module name */
                        __('%1$s cannot be enabled while %2$s is disabled.', 'jm-referral-system'),
                        (string) ($labels[$module] ?? $module),
                        (string) ($labels[$required] ?? $required)
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * Non-blocking admin warnings when disabling modules that may leave in-progress work.
     *
     * @param array<string, bool> $modules
     * @return list<string>
     */
    private static function disable_warnings(array $modules): array
    {
        $previous = self::all();
        $warnings = [];
        $labels   = self::labels();

        foreach (self::known_modules() as $module) {
            if (! empty($previous[$module]) && empty($modules[$module])) {
                $warnings[] = sprintf(
                    /* translators: %s: module name */
                    __(
                        '%s is now disabled. Historical records are preserved. New actions for this module are blocked. Referrals already in related pipeline stages are not moved automatically.',
                        'jm-referral-system'
                    ),
                    (string) ($labels[$module] ?? $module)
                );
            }
        }

        return $warnings;
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolved_raw(): array
    {
        $stored = get_option(self::OPTION_KEY, null);
        $base   = self::defaults();

        // Additive: missing option ⇒ all modules ON (v1.5 / JM upgrade behaviour).
        if (! is_array($stored)) {
            return $base;
        }

        return array_merge($base, $stored);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, bool>
     */
    public static function sanitize_settings(array $settings): array
    {
        $out = [];
        foreach (self::known_modules() as $module) {
            $out[$module] = ! empty($settings[$module]);
        }

        return $out;
    }
}
