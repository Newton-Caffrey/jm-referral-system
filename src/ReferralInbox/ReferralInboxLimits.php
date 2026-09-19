<?php

namespace JMReferral\ReferralInbox;

/**
 * Bounded field lengths for Referral Inbox data minimisation.
 */
class ReferralInboxLimits
{
    /** Email subject storage (not indexed as a full string). */
    public const SUBJECT_MAX = 500;

    /** Plaintext preview only — not an email archive. */
    public const BODY_PREVIEW_MAX = 1000;

    /** Minimal To/Cc style summary — not raw headers; no Bcc by default. */
    public const RECIPIENT_SUMMARY_MAX = 500;

    /** Safe operational error text — no tokens, stack traces, or raw provider payloads. */
    public const ERROR_MESSAGE_MAX = 500;

    /** SHA-256 hex length for dedupe_key / optional attachment hash. */
    public const DEDUPE_KEY_LENGTH = 64;
}
