<?php

namespace JMReferral\Meeting;

/**
 * Meeting attendee controlled values (Phase 4B.1).
 */
class MeetingAttendee
{
    public const KIND_INTERNAL = 'internal';
    public const KIND_EXTERNAL = 'external';

    public const ATTENDANCE_INVITED = 'invited';
    public const ATTENDANCE_CONFIRMED = 'confirmed';
    public const ATTENDANCE_ATTENDED = 'attended';
    public const ATTENDANCE_ABSENT = 'absent';
    public const ATTENDANCE_DECLINED = 'declined';

    public const CATEGORY_LA_OFFICER = 'la_officer';
    public const CATEGORY_SOCIAL_WORKER = 'social_worker';
    public const CATEGORY_COMMISSIONER = 'commissioner';
    public const CATEGORY_CLIENT = 'client';
    public const CATEGORY_FAMILY = 'family';
    public const CATEGORY_ADVOCATE = 'advocate';
    public const CATEGORY_JM_STAFF = 'jm_staff';
    public const CATEGORY_OTHER = 'other';

    /**
     * @return array<string, string>
     */
    public static function kind_labels(): array
    {
        return [
            self::KIND_INTERNAL => __('Internal', 'jm-referral-system'),
            self::KIND_EXTERNAL => __('External', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attendance_status_labels(): array
    {
        return [
            self::ATTENDANCE_INVITED   => __('Invited', 'jm-referral-system'),
            self::ATTENDANCE_CONFIRMED => __('Confirmed', 'jm-referral-system'),
            self::ATTENDANCE_ATTENDED  => __('Attended', 'jm-referral-system'),
            self::ATTENDANCE_ABSENT    => __('Absent', 'jm-referral-system'),
            self::ATTENDANCE_DECLINED  => __('Declined', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function category_labels(): array
    {
        return [
            self::CATEGORY_LA_OFFICER     => __('LA officer', 'jm-referral-system'),
            self::CATEGORY_SOCIAL_WORKER  => __('Social worker', 'jm-referral-system'),
            self::CATEGORY_COMMISSIONER   => __('Commissioner', 'jm-referral-system'),
            self::CATEGORY_CLIENT         => __('Client', 'jm-referral-system'),
            self::CATEGORY_FAMILY         => __('Family', 'jm-referral-system'),
            self::CATEGORY_ADVOCATE       => __('Advocate', 'jm-referral-system'),
            self::CATEGORY_JM_STAFF       => __('J&M staff', 'jm-referral-system'),
            self::CATEGORY_OTHER          => __('Other', 'jm-referral-system'),
        ];
    }

    public static function is_valid_kind(string $kind): bool
    {
        return array_key_exists($kind, self::kind_labels());
    }

    public static function is_valid_attendance_status(string $status): bool
    {
        return array_key_exists($status, self::attendance_status_labels());
    }

    public static function is_valid_category(string $category): bool
    {
        return '' === $category || array_key_exists($category, self::category_labels());
    }

    /**
     * Final attendance values allowed when correcting after meeting completion.
     *
     * @return array<string, string>
     */
    public static function final_attendance_status_labels(): array
    {
        return [
            self::ATTENDANCE_ATTENDED => self::attendance_status_labels()[self::ATTENDANCE_ATTENDED],
            self::ATTENDANCE_ABSENT   => self::attendance_status_labels()[self::ATTENDANCE_ABSENT],
            self::ATTENDANCE_DECLINED => self::attendance_status_labels()[self::ATTENDANCE_DECLINED],
        ];
    }

    public static function is_final_attendance_status(string $status): bool
    {
        return array_key_exists(sanitize_key($status), self::final_attendance_status_labels());
    }

    public static function is_non_final_attendance_status(string $status): bool
    {
        return in_array(sanitize_key($status), [
            self::ATTENDANCE_INVITED,
            self::ATTENDANCE_CONFIRMED,
        ], true);
    }

    public static function kind_label(string $kind): string
    {
        $labels = self::kind_labels();

        return $labels[$kind] ?? $kind;
    }

    public static function attendance_status_label(string $status): string
    {
        $labels = self::attendance_status_labels();

        return $labels[$status] ?? $status;
    }

    public static function category_label(string $category): string
    {
        $labels = self::category_labels();

        return $labels[$category] ?? $category;
    }
}
