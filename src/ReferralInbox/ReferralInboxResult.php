<?php

namespace JMReferral\ReferralInbox;

/**
 * Structured result codes for Referral Inbox service operations.
 */
class ReferralInboxResult
{
    public const CREATED            = 'CREATED';
    public const EXISTING           = 'EXISTING';
    public const SUCCESS            = 'SUCCESS';
    public const ALREADY_APPLIED    = 'ALREADY_APPLIED';
    public const INVALID_TRANSITION = 'INVALID_TRANSITION';
    public const NOT_FOUND          = 'NOT_FOUND';
    public const CONFLICT           = 'CONFLICT';
    public const VALIDATION_ERROR   = 'VALIDATION_ERROR';
    public const PERSISTENCE_ERROR  = 'PERSISTENCE_ERROR';
}
