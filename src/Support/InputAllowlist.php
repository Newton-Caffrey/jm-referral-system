<?php

namespace JMReferral\Support;

/**
 * Shared allowlist helpers for request sanitization.
 */
class InputAllowlist
{
    /**
     * Returns $value when it is in $allowed; otherwise $default.
     *
     * @param array<int, string> $allowed
     */
    public static function pick(string $value, array $allowed, string $default = ''): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Sanitizes a request key against an allowlist.
     *
     * @param array<int, string> $allowed
     */
    public static function from_request_key(mixed $raw, array $allowed, string $default = ''): string
    {
        $value = is_string($raw) || is_numeric($raw)
            ? sanitize_key(wp_unslash((string) $raw))
            : '';

        return self::pick($value, $allowed, $default);
    }

    /**
     * Sanitizes a request text field against an allowlist.
     *
     * @param array<int, string> $allowed
     */
    public static function from_request_text(mixed $raw, array $allowed, string $default = ''): string
    {
        $value = is_string($raw) || is_numeric($raw)
            ? sanitize_text_field(wp_unslash((string) $raw))
            : '';

        return self::pick($value, $allowed, $default);
    }
}
