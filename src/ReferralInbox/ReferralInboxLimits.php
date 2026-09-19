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

    public const MAILBOX_IDENTIFIER_MAX = 255;
    public const PROVIDER_MESSAGE_ID_MAX = 255;
    public const INTERNET_MESSAGE_ID_MAX = 255;
    public const CONVERSATION_IDENTIFIER_MAX = 255;
    public const SENDER_NAME_MAX = 255;
    public const SENDER_EMAIL_MAX = 190;
    public const SENDER_DOMAIN_MAX = 255;
    public const ERROR_CODE_MAX = 100;
    public const PROVIDER_ATTACHMENT_ID_MAX = 255;
    public const FILENAME_MAX = 255;
    public const MIME_TYPE_MAX = 100;
    public const PRIVATE_PATH_MAX = 500;
    public const DETECTION_REASON_MAX = 255;
}
