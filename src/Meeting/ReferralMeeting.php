<?php

namespace JMReferral\Meeting;

/**
 * Referral meeting controlled values (Phase 4B.1).
 * Distinct from formal assessment appointments on jmrs_referral_assessments.
 */
class ReferralMeeting
{
    public const TYPE_COMMISSIONER_PRE_ASSESSMENT = 'commissioner_pre_assessment';
    public const TYPE_OTHER = 'other';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const LOCATION_IN_PERSON = 'in_person';
    public const LOCATION_ONLINE = 'online';
    public const LOCATION_TELEPHONE = 'telephone';
    public const LOCATION_OTHER = 'other';

    /**
     * @return array<string, string>
     */
    public static function type_labels(): array
    {
        return [
            self::TYPE_COMMISSIONER_PRE_ASSESSMENT => __('Commissioner / pre-assessment', 'jm-referral-system'),
            self::TYPE_OTHER                       => __('Other', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function status_labels(): array
    {
        return [
            self::STATUS_DRAFT     => __('Draft', 'jm-referral-system'),
            self::STATUS_SCHEDULED => __('Scheduled', 'jm-referral-system'),
            self::STATUS_COMPLETED => __('Completed', 'jm-referral-system'),
            self::STATUS_CANCELLED => __('Cancelled', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function location_type_labels(): array
    {
        return [
            self::LOCATION_IN_PERSON => __('In person', 'jm-referral-system'),
            self::LOCATION_ONLINE    => __('Online', 'jm-referral-system'),
            self::LOCATION_TELEPHONE => __('Telephone', 'jm-referral-system'),
            self::LOCATION_OTHER     => __('Other', 'jm-referral-system'),
        ];
    }

    public static function is_valid_type(string $type): bool
    {
        return array_key_exists($type, self::type_labels());
    }

    public static function is_valid_status(string $status): bool
    {
        return array_key_exists($status, self::status_labels());
    }

    public static function is_valid_location_type(string $type): bool
    {
        return array_key_exists($type, self::location_type_labels());
    }

    public static function type_label(string $type): string
    {
        $labels = self::type_labels();

        return $labels[$type] ?? $type;
    }

    public static function status_label(string $status): string
    {
        $labels = self::status_labels();

        return $labels[$status] ?? $status;
    }

    public static function location_type_label(string $type): string
    {
        $labels = self::location_type_labels();

        return $labels[$type] ?? $type;
    }
}
