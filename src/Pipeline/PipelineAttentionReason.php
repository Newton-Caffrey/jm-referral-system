<?php

namespace JMReferral\Pipeline;

/**
 * Machine identifiers for Needs Attention reasons (operational, not clinical).
 */
class PipelineAttentionReason
{
    public const UNASSIGNED = 'unassigned';
    public const TARGET_EXCEEDED = 'target_exceeded';
    public const NEXT_ACTION_OVERDUE = 'next_action_overdue';
    public const ASSESSMENT_REVIEW_REQUIRED = 'assessment_review_required';
    public const CARE_SETTING_REQUIRED = 'care_setting_required';
    public const PLACEMENT_REQUIRED = 'placement_required';

    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_MEDIUM = 'medium';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::UNASSIGNED                   => __('Unassigned referral', 'jm-referral-system'),
            self::TARGET_EXCEEDED              => __('Internal target exceeded', 'jm-referral-system'),
            self::NEXT_ACTION_OVERDUE          => __('Next action overdue', 'jm-referral-system'),
            self::ASSESSMENT_REVIEW_REQUIRED   => __('Assessment outcome requires review', 'jm-referral-system'),
            self::CARE_SETTING_REQUIRED        => __('Care setting required', 'jm-referral-system'),
            self::PLACEMENT_REQUIRED           => __('Placement required', 'jm-referral-system'),
        ];
    }

    public static function label(string $code): string
    {
        $labels = self::labels();

        return $labels[$code] ?? $code;
    }

    public static function is_valid(string $code): bool
    {
        return array_key_exists($code, self::labels());
    }

    /**
     * Operational severity for a reason code.
     */
    public static function severity(string $code): string
    {
        return match ($code) {
            self::NEXT_ACTION_OVERDUE => self::SEVERITY_CRITICAL,
            self::TARGET_EXCEEDED,
            self::ASSESSMENT_REVIEW_REQUIRED => self::SEVERITY_HIGH,
            self::UNASSIGNED,
            self::CARE_SETTING_REQUIRED,
            self::PLACEMENT_REQUIRED => self::SEVERITY_MEDIUM,
            default => self::SEVERITY_MEDIUM,
        };
    }

    /**
     * Severity rank for sorting (lower = more urgent).
     */
    public static function severity_rank(string $severity): int
    {
        return match ($severity) {
            self::SEVERITY_CRITICAL => 1,
            self::SEVERITY_HIGH => 2,
            self::SEVERITY_MEDIUM => 3,
            default => 9,
        };
    }
}
