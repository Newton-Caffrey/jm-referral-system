<?php

namespace JMReferral\Mailbox;

/**
 * Provider / auth / mailbox type allowlists for mailbox connections (Phase 5C.1).
 */
class MailboxConnectionConstants
{
    public const PROVIDER_MICROSOFT_GRAPH = 'microsoft_graph';

    public const AUTH_APPLICATION = 'application';

    public const CREDENTIAL_CLIENT_SECRET = 'client_secret';

    /** Future-ready name — not implemented in 5C.1. */
    public const CREDENTIAL_CERTIFICATE = 'certificate';

    public const MAILBOX_USER   = 'user';
    public const MAILBOX_SHARED = 'shared';

    public const SECRET_NAME_CLIENT_SECRET = 'client_secret';

    /**
     * @return array<int, string>
     */
    public static function mailbox_types(): array
    {
        return [self::MAILBOX_USER, self::MAILBOX_SHARED];
    }
}
