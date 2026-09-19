<?php

namespace JMReferral\ReferralInbox;

/**
 * Operational lifecycle for Referral Inbox items.
 *
 * Independent of detection classification ({@see ReferralDetectionStatus}).
 *
 * Conceptual flow: new → needs_review → accepted | ignored | duplicate | error
 */
class ReferralInboxStatus
{
    public const NEW          = 'new';
    public const NEEDS_REVIEW = 'needs_review';
    public const ACCEPTED     = 'accepted';
    public const IGNORED      = 'ignored';
    public const DUPLICATE    = 'duplicate';
    public const ERROR        = 'error';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::NEW,
            self::NEEDS_REVIEW,
            self::ACCEPTED,
            self::IGNORED,
            self::DUPLICATE,
            self::ERROR,
        ];
    }

    public static function is_valid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
