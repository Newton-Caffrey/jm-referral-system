<?php

namespace JMReferral\ReferralInbox;

/**
 * How a Referral Inbox Local Authority link was decided (Phase 5D.2).
 *
 * NULL means no provenance was recorded (legacy or not yet decided).
 * These values are not sender-authentication states.
 */
final class LocalAuthorityOrigin
{
    public const SUGGESTED = 'suggested';
    public const CONFIRMED = 'confirmed';
    public const CLEARED   = 'cleared';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::SUGGESTED,
            self::CONFIRMED,
            self::CLEARED,
        ];
    }

    public static function is_valid(string $origin): bool
    {
        return in_array($origin, self::all(), true);
    }

    /**
     * Staff-facing summary of the stored link. Separate from live sender matching.
     */
    public static function stored_summary(int $authority_id, ?string $origin): string
    {
        $origin = is_string($origin) ? strtolower(trim($origin)) : '';

        if (self::CLEARED === $origin) {
            return __('Cleared by staff', 'jm-referral-system');
        }

        if ($authority_id > 0 && self::SUGGESTED === $origin) {
            return __('Suggested by JMRS', 'jm-referral-system');
        }

        if ($authority_id > 0 && self::CONFIRMED === $origin) {
            return __('Confirmed by staff', 'jm-referral-system');
        }

        if ($authority_id > 0) {
            return __('Linked authority — source not recorded', 'jm-referral-system');
        }

        return __('No Local Authority linked', 'jm-referral-system');
    }
}
