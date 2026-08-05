<?php

namespace JMReferral\Frontend;

/**
 * Allowlist for public referrer types.
 */
class ReferrerTypes
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'self'            => __('Myself (self-referral)', 'jm-referral-system'),
            'family'          => __('Family member', 'jm-referral-system'),
            'friend'          => __('Friend', 'jm-referral-system'),
            'hospital'        => __('Hospital', 'jm-referral-system'),
            'gp'              => __('GP / Doctor', 'jm-referral-system'),
            'social_worker'   => __('Social worker', 'jm-referral-system'),
            'local_authority' => __('Local authority', 'jm-referral-system'),
            'care_provider'   => __('Care provider', 'jm-referral-system'),
            'other'           => __('Other', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowed(): array
    {
        return array_keys(self::options());
    }

    public static function label(string $type): string
    {
        $options = self::options();

        return $options[$type] ?? '';
    }

    public static function is_valid(string $type): bool
    {
        return in_array($type, self::allowed(), true);
    }
}
