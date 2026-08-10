<?php

namespace JMReferral\Assessment;

/**
 * Assessment appointment location-type constants and labels.
 */
class AssessmentScheduling
{
    public const LOCATION_HOSPITAL = 'hospital';
    public const LOCATION_CURRENT_CARE_HOME = 'current_care_home';
    public const LOCATION_OWN_HOME = 'own_home';
    public const LOCATION_OTHER = 'other';

    public const NOTES_MAX_LENGTH = 2000;
    public const ADDRESS_MAX_LENGTH = 2000;
    public const REASON_MAX_LENGTH = 500;

    /**
     * @return array<string, string>
     */
    public static function location_type_labels(): array
    {
        return [
            self::LOCATION_HOSPITAL           => __('Hospital', 'jm-referral-system'),
            self::LOCATION_CURRENT_CARE_HOME  => __('Current Care Home', 'jm-referral-system'),
            self::LOCATION_OWN_HOME           => __('Own Home', 'jm-referral-system'),
            self::LOCATION_OTHER              => __('Other', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function location_types(): array
    {
        return array_keys(self::location_type_labels());
    }

    public static function is_valid_location_type(string $type): bool
    {
        return in_array($type, self::location_types(), true);
    }

    public static function location_type_label(string $type): string
    {
        $labels = self::location_type_labels();

        return $labels[$type] ?? $type;
    }
}
