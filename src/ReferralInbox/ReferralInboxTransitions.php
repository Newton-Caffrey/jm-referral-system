<?php

namespace JMReferral\ReferralInbox;

/**
 * Allowed Referral Inbox lifecycle transitions (Phase 5B.2).
 *
 * Terminal in this phase: accepted, ignored, duplicate.
 * Recovery: error → needs_review.
 */
class ReferralInboxTransitions
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function map(): array
    {
        return [
            ReferralInboxStatus::NEW => [
                ReferralInboxStatus::NEEDS_REVIEW,
                ReferralInboxStatus::DUPLICATE,
                ReferralInboxStatus::ERROR,
            ],
            ReferralInboxStatus::NEEDS_REVIEW => [
                ReferralInboxStatus::ACCEPTED,
                ReferralInboxStatus::IGNORED,
                ReferralInboxStatus::DUPLICATE,
                ReferralInboxStatus::ERROR,
            ],
            ReferralInboxStatus::ERROR => [
                ReferralInboxStatus::NEEDS_REVIEW,
            ],
            ReferralInboxStatus::ACCEPTED  => [],
            ReferralInboxStatus::IGNORED   => [],
            ReferralInboxStatus::DUPLICATE => [],
        ];
    }

    public static function is_allowed(string $from, string $to): bool
    {
        if (! ReferralInboxStatus::is_valid($from) || ! ReferralInboxStatus::is_valid($to)) {
            return false;
        }

        $map = self::map();

        return in_array($to, $map[$from] ?? [], true);
    }

    public static function is_terminal(string $status): bool
    {
        return in_array(
            $status,
            [
                ReferralInboxStatus::ACCEPTED,
                ReferralInboxStatus::IGNORED,
                ReferralInboxStatus::DUPLICATE,
            ],
            true
        );
    }
}
