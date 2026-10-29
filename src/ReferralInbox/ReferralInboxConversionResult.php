<?php

namespace JMReferral\ReferralInbox;

/**
 * Outcome of one Inbox → referral conversion attempt (Phase 5D.5).
 *
 * In memory only. No SQL text, stack traces, or raw POST bodies.
 */
final class ReferralInboxConversionResult
{
    public const SUCCESS                  = 'SUCCESS';
    public const ALREADY_CONVERTED        = 'ALREADY_CONVERTED';
    public const VALIDATION_ERROR         = 'VALIDATION_ERROR';
    public const INVALID_STATE            = 'INVALID_STATE';
    public const INCONSISTENT_LINK        = 'INCONSISTENT_LINK';
    public const INCONSISTENT_STATE       = 'INCONSISTENT_STATE';
    public const TRANSACTION_UNAVAILABLE  = 'TRANSACTION_UNAVAILABLE';
    public const LOCK_TIMEOUT             = 'LOCK_TIMEOUT';
    public const CREATE_FAILED            = 'CREATE_FAILED';
    public const ACCEPT_FAILED            = 'ACCEPT_FAILED';
    public const CONFLICT                 = 'CONFLICT';
    public const PERSISTENCE_ERROR        = 'PERSISTENCE_ERROR';
    public const NOT_FOUND                = 'NOT_FOUND';

    public const WARNING_ASSIGNMENT_EMAIL = 'assignment_email_failed';

    /** The referral was created, but the uploaded form could not be added to its documents (Phase 5E.1). */
    public const WARNING_DOCUMENT_ATTACH = 'document_attach_failed';

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $values
     */
    private function __construct(
        private string $outcome,
        private int $referral_id,
        private string $referral_number,
        private string $warning,
        private array $errors,
        private array $values
    ) {
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $values
     */
    public static function of(
        string $outcome,
        int $referral_id = 0,
        string $referral_number = '',
        string $warning = '',
        array $errors = [],
        array $values = []
    ): self {
        return new self($outcome, $referral_id, $referral_number, $warning, $errors, $values);
    }

    public function outcome(): string
    {
        return $this->outcome;
    }

    public function referral_id(): int
    {
        return $this->referral_id;
    }

    public function referral_number(): string
    {
        return $this->referral_number;
    }

    /**
     * Warning code, or several codes joined by commas.
     */
    public function warning(): string
    {
        return $this->warning;
    }

    public function has_warning(string $code): bool
    {
        return '' !== $code && in_array($code, explode(',', $this->warning), true);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function redirects(): bool
    {
        return in_array($this->outcome, [self::SUCCESS, self::ALREADY_CONVERTED], true);
    }
}
