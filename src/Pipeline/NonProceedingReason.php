<?php

namespace JMReferral\Pipeline;

/**
 * Canonical reason codes when a referral is marked Not Proceeding.
 */
class NonProceedingReason
{
    public const NO_CAPACITY = 'no_capacity';
    public const CLIENT_WITHDREW = 'client_withdrew';
    public const FAMILY_WITHDREW = 'family_withdrew';
    public const JM_NOT_SUITABLE = 'jm_not_suitable';
    public const NEEDS_CHANGED = 'needs_changed';
    public const PLACEMENT_NO_LONGER_REQUIRED = 'placement_no_longer_required';
    public const ALTERNATIVE_PROVIDER = 'alternative_provider';
    public const DUPLICATE = 'duplicate';
    public const MUTUAL_DECISION = 'mutual_decision';
    public const OTHER = 'other';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::NO_CAPACITY                   => __('No capacity', 'jm-referral-system'),
            self::CLIENT_WITHDREW               => __('Client withdrew', 'jm-referral-system'),
            self::FAMILY_WITHDREW               => __('Family / representative withdrew', 'jm-referral-system'),
            self::JM_NOT_SUITABLE               => __('JM assessed referral as not suitable', 'jm-referral-system'),
            self::NEEDS_CHANGED                 => __('Needs changed', 'jm-referral-system'),
            self::PLACEMENT_NO_LONGER_REQUIRED  => __('Placement / service no longer required', 'jm-referral-system'),
            self::ALTERNATIVE_PROVIDER          => __('Alternative provider selected', 'jm-referral-system'),
            self::DUPLICATE                     => __('Duplicate referral', 'jm-referral-system'),
            self::MUTUAL_DECISION               => __('Mutual decision', 'jm-referral-system'),
            self::OTHER                         => __('Other', 'jm-referral-system'),
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
     * Active acquisition stages where the generic Mark as Not Proceeding action applies.
     *
     * @return array<int, string>
     */
    public static function allowed_source_stages(): array
    {
        return [
            PipelineStage::INTEREST_REQUIRED,
            PipelineStage::ASSESSMENT_TO_SCHEDULE,
            PipelineStage::ASSESSMENT_SCHEDULED,
            PipelineStage::ASSESSMENT_REVIEW_REQUIRED,
            PipelineStage::PACKAGE_COST_REQUIRED,
            PipelineStage::TRANSITION_PLANNING,
        ];
    }

    public static function is_allowed_source_stage(string $slug): bool
    {
        return in_array($slug, self::allowed_source_stages(), true);
    }
}
