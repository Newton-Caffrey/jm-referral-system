<?php

namespace JMReferral\Referral;

/**
 * Connection-scoped advisory lock around referral-number allocation (Phase 5D.5).
 *
 * The lock name is fixed. Callers must release it, including after rollback.
 */
class ReferralNumberLock
{
    public const NAME = 'jmrs_referral_number';

    private const TIMEOUT_SECONDS = 5;

    public function acquire(): bool
    {
        global $wpdb;

        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT GET_LOCK(%s, %d)',
                self::NAME,
                self::TIMEOUT_SECONDS
            )
        );

        return '1' === (string) $result;
    }

    public function release(): void
    {
        global $wpdb;

        $wpdb->get_var(
            $wpdb->prepare(
                'SELECT RELEASE_LOCK(%s)',
                self::NAME
            )
        );
    }
}
