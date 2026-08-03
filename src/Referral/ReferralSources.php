<?php

namespace JMReferral\Referral;

class ReferralSources
{
    /**
     * Returns allowed referral source values mapped to display labels.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'hospital'        => __('Hospital', 'jm-referral-system'),
            'social_worker'   => __('Social Worker', 'jm-referral-system'),
            'gp'              => __('GP / Doctor', 'jm-referral-system'),
            'family'          => __('Family Member', 'jm-referral-system'),
            'website'         => __('Website', 'jm-referral-system'),
            'care_agency'     => __('Care Agency', 'jm-referral-system'),
            'existing_client' => __('Existing Client', 'jm-referral-system'),
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

    /**
     * Returns the display label for a source value.
     */
    public static function label(string $source): string
    {
        $options = self::options();

        return $options[$source] ?? '';
    }

    /**
     * Whether the value is a valid referral source.
     */
    public static function is_valid(string $source): bool
    {
        return in_array($source, self::allowed(), true);
    }
}
