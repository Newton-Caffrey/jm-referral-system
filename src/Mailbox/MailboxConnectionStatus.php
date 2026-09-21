<?php

namespace JMReferral\Mailbox;

/**
 * Persistent mailbox connection health statuses (Phase 5C.1).
 *
 * Distinct from Referral Inbox item lifecycle statuses.
 * Computed when no row exists: not_configured.
 */
class MailboxConnectionStatus
{
    public const CONFIGURED               = 'configured';
    public const CONNECTED                = 'connected';
    public const ATTENTION_REQUIRED       = 'attention_required';
    public const REAUTHORIZATION_REQUIRED = 'reauthorization_required';
    public const DISABLED                 = 'disabled';
    public const ERROR                    = 'error';

    /** Computed only — not persisted. */
    public const NOT_CONFIGURED = 'not_configured';

    /**
     * @return array<int, string>
     */
    public static function persisted(): array
    {
        return [
            self::CONFIGURED,
            self::CONNECTED,
            self::ATTENTION_REQUIRED,
            self::REAUTHORIZATION_REQUIRED,
            self::DISABLED,
            self::ERROR,
        ];
    }

    public static function is_persisted(string $status): bool
    {
        return in_array($status, self::persisted(), true);
    }
}
