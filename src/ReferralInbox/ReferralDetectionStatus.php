<?php

namespace JMReferral\ReferralInbox;

/**
 * Detection classification for an Inbox item.
 *
 * Independent of operational lifecycle ({@see ReferralInboxStatus}).
 * Detection logic is out of scope for Phase 5B.1.
 */
class ReferralDetectionStatus
{
    public const UNCLASSIFIED = 'unclassified';
    public const LIKELY       = 'likely';
    public const UNCERTAIN    = 'uncertain';
    public const NOT_REFERRAL = 'not_referral';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::UNCLASSIFIED,
            self::LIKELY,
            self::UNCERTAIN,
            self::NOT_REFERRAL,
        ];
    }

    public static function is_valid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
