<?php

namespace JMReferral\ReferralInbox;

/**
 * Attachment storage lifecycle for Inbox attachment metadata.
 *
 * Phase 5B.1 stores metadata only — no downloaded files.
 */
class InboxAttachmentStatus
{
    public const METADATA_ONLY = 'metadata_only';
    public const QUARANTINED   = 'quarantined';
    public const STORED        = 'stored';
    public const PROMOTED      = 'promoted';
    public const DELETED       = 'deleted';
    public const ERROR         = 'error';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::METADATA_ONLY,
            self::QUARANTINED,
            self::STORED,
            self::PROMOTED,
            self::DELETED,
            self::ERROR,
        ];
    }

    public static function is_valid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
