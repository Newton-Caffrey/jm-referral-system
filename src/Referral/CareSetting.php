<?php

namespace JMReferral\Referral;

/**
 * Where / how care is delivered (independent of service_type = what care).
 */
class CareSetting
{
    public const SUPPORTED_LIVING = 'supported_living';
    public const OWN_HOME = 'own_home';

    /**
     * Filter value meaning care_setting IS NULL.
     */
    public const NOT_SPECIFIED = 'not_specified';

    /**
     * Stored values only (excludes not_specified filter sentinel).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::SUPPORTED_LIVING => __('Supported Living', 'jm-referral-system'),
            self::OWN_HOME         => __("Client's Own Home", 'jm-referral-system'),
        ];
    }

    /**
     * Form / filter options including empty "Not specified".
     *
     * @return array<string, string>
     */
    public static function form_options(): array
    {
        return array_merge(
            ['' => __('Not specified', 'jm-referral-system')],
            self::options()
        );
    }

    /**
     * List filter options including Not Specified.
     *
     * @return array<string, string>
     */
    public static function filter_options(): array
    {
        return array_merge(
            [self::NOT_SPECIFIED => __('Not Specified', 'jm-referral-system')],
            self::options()
        );
    }

    /**
     * @return array<int, string>
     */
    public static function allowed(): array
    {
        return array_keys(self::options());
    }

    public static function is_valid(string $value): bool
    {
        return in_array($value, self::allowed(), true);
    }

    /**
     * Human-readable label for a stored value or NULL/empty.
     */
    public static function label(?string $value): string
    {
        $value = null === $value ? '' : trim($value);
        if ('' === $value) {
            return __('Not Specified', 'jm-referral-system');
        }

        $options = self::options();

        return $options[$value] ?? __('Not Specified', 'jm-referral-system');
    }

    /**
     * Normalize form input to DB value (null = not specified).
     */
    public static function normalize(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);
        if ('' === $value || self::NOT_SPECIFIED === $value) {
            return null;
        }

        return self::is_valid($value) ? $value : null;
    }

    public static function is_supported_living(?string $value): bool
    {
        return self::SUPPORTED_LIVING === (string) $value;
    }

    public static function is_own_home(?string $value): bool
    {
        return self::OWN_HOME === (string) $value;
    }

    public static function is_unspecified(?string $value): bool
    {
        return null === $value || '' === trim((string) $value);
    }

    /**
     * Own-home service location uses existing referral address fields (Phase 2D).
     * Incomplete address is a soft operational warning, not a hard block.
     *
     * @param array<string, mixed> $referral
     */
    public static function is_own_home_address_complete(array $referral): bool
    {
        $line1    = trim((string) ($referral['address_line_1'] ?? ''));
        $city     = trim((string) ($referral['city'] ?? ''));
        $postcode = trim((string) ($referral['postcode'] ?? ''));

        return '' !== $line1 && '' !== $city && '' !== $postcode;
    }
}
