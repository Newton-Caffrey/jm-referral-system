<?php

namespace JMReferral\ReferralInbox;

/**
 * Provider-neutral source identifiers for Inbox items.
 *
 * Connector-specific sync state belongs to later email-connector phases.
 */
class ReferralInboxSource
{
    public const MICROSOFT_GRAPH = 'microsoft_graph';
    public const GMAIL           = 'gmail';
    public const MANUAL          = 'manual';
    public const FIXTURE         = 'fixture';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::MICROSOFT_GRAPH,
            self::GMAIL,
            self::MANUAL,
            self::FIXTURE,
        ];
    }

    public static function is_valid(string $source): bool
    {
        return in_array($source, self::all(), true);
    }
}
