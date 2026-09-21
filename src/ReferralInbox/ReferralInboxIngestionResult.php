<?php

namespace JMReferral\ReferralInbox;

/**
 * Structured result codes for Referral Inbox ingestion (Phase 5B.4).
 */
class ReferralInboxIngestionResult
{
    public const CREATED           = 'CREATED';
    public const EXISTING          = 'EXISTING';
    public const PARTIAL           = 'PARTIAL';
    public const VALIDATION_ERROR  = 'VALIDATION_ERROR';
    public const PERSISTENCE_ERROR = 'PERSISTENCE_ERROR';
}
