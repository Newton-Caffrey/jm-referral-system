<?php

namespace JMReferral\Settings;

/**
 * Installation-wide display terminology (labels only).
 *
 * Does not rename technical keys, enums, routes, capabilities, or stored values.
 */
class TerminologySettings
{
    public const OPTION_KEY = 'jmrs_terminology_settings';
    public const SCHEMA_OPTION_KEY = 'jmrs_terminology_settings_schema';
    public const SCHEMA_VERSION = 1;

    public const MAX_LENGTH = 60;

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'referral_singular'           => 'Referral',
            'referral_plural'             => 'Referrals',
            'client_singular'             => 'Client',
            'client_plural'               => 'Clients',
            'local_authority_singular'    => 'Local Authority',
            'local_authority_plural'      => 'Local Authorities',
            'commissioner_singular'       => 'Commissioner',
            'commissioner_plural'         => 'Commissioners',
            'service_singular'            => 'Service',
            'service_plural'              => 'Services',
        ];
    }

    /**
     * Effective labels for UI (never raw option alone).
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::sanitize_settings(self::resolved_raw());
    }

    public static function referral_singular(): string
    {
        return self::label('referral_singular');
    }

    public static function referral_plural(): string
    {
        return self::label('referral_plural');
    }

    public static function client_singular(): string
    {
        return self::label('client_singular');
    }

    public static function client_plural(): string
    {
        return self::label('client_plural');
    }

    public static function local_authority_singular(): string
    {
        return self::label('local_authority_singular');
    }

    public static function local_authority_plural(): string
    {
        return self::label('local_authority_plural');
    }

    public static function commissioner_singular(): string
    {
        return self::label('commissioner_singular');
    }

    public static function commissioner_plural(): string
    {
        return self::label('commissioner_plural');
    }

    public static function service_singular(): string
    {
        return self::label('service_singular');
    }

    public static function service_plural(): string
    {
        return self::label('service_plural');
    }

    public static function label(string $key): string
    {
        $defaults = self::defaults();
        if (! isset($defaults[$key])) {
            return '';
        }

        $value = trim((string) (self::all()[$key] ?? ''));

        return '' !== $value ? $value : $defaults[$key];
    }

    /**
     * @param array<string, mixed> $input Allowlisted fields only.
     * @return array{ok: bool, errors: array<string, string>}
     */
    public static function update(array $input): array
    {
        $allow = array_keys(self::defaults());
        $merged = self::all();

        foreach ($allow as $key) {
            if (array_key_exists($key, $input)) {
                $merged[$key] = $input[$key];
            }
        }

        $errors = self::validate_for_save($input);
        if ([] !== $errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $sanitized = self::sanitize_settings($merged);
        update_option(self::OPTION_KEY, $sanitized, false);
        update_option(self::SCHEMA_OPTION_KEY, self::SCHEMA_VERSION, false);

        return ['ok' => true, 'errors' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolved_raw(): array
    {
        $stored = get_option(self::OPTION_KEY, null);
        $base   = self::defaults();

        if (! is_array($stored)) {
            return $base;
        }

        return array_merge($base, $stored);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, string>
     */
    public static function sanitize_settings(array $settings): array
    {
        $defaults = self::defaults();
        $out      = [];

        foreach ($defaults as $key => $default) {
            $raw = $settings[$key] ?? $default;
            if (is_array($raw)) {
                $out[$key] = $default;
                continue;
            }

            $value = sanitize_text_field((string) $raw);
            $value = trim($value);
            if ('' === $value) {
                $value = $default;
            }
            if (strlen($value) > self::MAX_LENGTH) {
                $value = substr($value, 0, self::MAX_LENGTH);
            }

            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $raw_input
     * @return array<string, string>
     */
    private static function validate_for_save(array $raw_input): array
    {
        $errors  = [];
        $defaults = self::defaults();

        foreach ($defaults as $key => $_default) {
            if (! array_key_exists($key, $raw_input)) {
                continue;
            }

            if (is_array($raw_input[$key])) {
                $errors[$key] = __('Invalid terminology value.', 'jm-referral-system');
                continue;
            }

            $raw = (string) $raw_input[$key];
            if (strlen(trim($raw)) > self::MAX_LENGTH) {
                $errors[$key] = sprintf(
                    /* translators: %d: max characters */
                    __('Terminology must be %d characters or fewer.', 'jm-referral-system'),
                    self::MAX_LENGTH
                );
            }

            if (preg_match('/<[^>]+>|javascript:|on\w+\s*=/i', $raw)) {
                $errors[$key] = __('Markup and scripts are not allowed in terminology.', 'jm-referral-system');
            }
        }

        foreach (array_keys($raw_input) as $key) {
            if (! isset($defaults[(string) $key])) {
                $errors[(string) $key] = __('Unknown terminology field.', 'jm-referral-system');
            }
        }

        return $errors;
    }
}
