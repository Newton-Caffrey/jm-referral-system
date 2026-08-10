<?php

namespace JMReferral\LaDecision;

/**
 * Local Authority / funding decision constants and labels.
 */
class LaDecision
{
    public const DECISION_APPROVED = 'approved';
    public const DECISION_DECLINED = 'declined';
    public const DECISION_NOT_PROCEEDING = 'not_proceeding';

    /** Funding: Yes */
    public const FUNDING_YES = 1;
    /** Funding: No */
    public const FUNDING_NO = 0;
    /** Funding: Not recorded (stored as NULL) */
    public const FUNDING_NOT_RECORDED = null;

    public const NOTES_MAX = 500;

    /**
     * @return array<string, string>
     */
    public static function decision_labels(): array
    {
        return [
            self::DECISION_APPROVED       => __('Approved', 'jm-referral-system'),
            self::DECISION_DECLINED       => __('Declined', 'jm-referral-system'),
            self::DECISION_NOT_PROCEEDING => __('Not Proceeding', 'jm-referral-system'),
        ];
    }

    public static function decision_label(string $decision): string
    {
        $labels = self::decision_labels();

        return $labels[$decision] ?? $decision;
    }

    public static function is_valid_decision(string $decision): bool
    {
        return array_key_exists($decision, self::decision_labels());
    }

    /**
     * @return array<string, string>
     */
    public static function declined_reason_labels(): array
    {
        return [
            'funding_declined'    => __('Funding declined', 'jm-referral-system'),
            'package_not_approved'=> __('Package not approved', 'jm-referral-system'),
            'alternative_provider'=> __('Alternative provider', 'jm-referral-system'),
            'needs_changed'       => __('Needs changed', 'jm-referral-system'),
            'other'               => __('Other', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function not_proceeding_reason_labels(): array
    {
        return \JMReferral\Pipeline\NonProceedingReason::labels();
    }

    public static function reason_label(string $decision, string $reason_code): string
    {
        if ('' === $reason_code) {
            return '';
        }

        if (self::DECISION_NOT_PROCEEDING === $decision) {
            return \JMReferral\Pipeline\NonProceedingReason::label($reason_code);
        }

        if (self::DECISION_DECLINED === $decision) {
            $map = self::declined_reason_labels();

            return $map[$reason_code] ?? $reason_code;
        }

        return $reason_code;
    }

    public static function is_valid_reason(string $decision, string $reason_code): bool
    {
        if (self::DECISION_DECLINED === $decision) {
            return array_key_exists($reason_code, self::declined_reason_labels());
        }

        if (self::DECISION_NOT_PROCEEDING === $decision) {
            return \JMReferral\Pipeline\NonProceedingReason::is_valid($reason_code);
        }

        return '' === $reason_code;
    }

    /**
     * @return array<string, string>
     */
    public static function funding_confirmed_labels(): array
    {
        return [
            'yes'          => __('Yes', 'jm-referral-system'),
            'no'           => __('No', 'jm-referral-system'),
            'not_recorded' => __('Not Recorded', 'jm-referral-system'),
        ];
    }

    public static function funding_confirmed_label(?int $value): string
    {
        if (null === $value) {
            return self::funding_confirmed_labels()['not_recorded'];
        }

        return 1 === $value
            ? self::funding_confirmed_labels()['yes']
            : self::funding_confirmed_labels()['no'];
    }

    /**
     * Maps UI funding token to DB value (null / 0 / 1).
     */
    public static function normalize_funding_confirmed(string $token): ?int
    {
        $token = sanitize_key($token);

        if ('yes' === $token) {
            return self::FUNDING_YES;
        }

        if ('no' === $token) {
            return self::FUNDING_NO;
        }

        return self::FUNDING_NOT_RECORDED;
    }

    public static function funding_token_from_db(?int $value): string
    {
        if (null === $value) {
            return 'not_recorded';
        }

        return 1 === $value ? 'yes' : 'no';
    }
}
