<?php

namespace JMReferral\ReferralInbox;

/**
 * Advisory detection outcome (Phase 5D.1).
 *
 * Runtime-only fields (evidence codes, matcher metadata, ambiguous candidates)
 * are not persisted. Only detection_status and detection_reason are stored,
 * plus a Local Authority id when the guarded writer allows it.
 */
final class ReferralInboxDetectionResult
{
    public const REASON_RECOGNISED_SENDER_AND_REFERRAL_SIGNAL = 'recognised_sender_and_referral_signal';
    public const REASON_MULTIPLE_INDEPENDENT_REFERRAL_SIGNALS  = 'multiple_independent_referral_signals';
    public const REASON_RECOGNISED_SENDER_WITHOUT_SIGNAL       = 'recognised_sender_without_referral_signal';
    public const REASON_SINGLE_SIGNAL_UNRECOGNISED_SENDER      = 'single_referral_signal_unrecognised_sender';
    public const REASON_AMBIGUOUS_RECOGNISED_SENDER            = 'ambiguous_recognised_sender';
    public const REASON_INVALID_SENDER_WITH_SIGNAL             = 'invalid_sender_with_referral_signal';
    public const REASON_EXPLICIT_NON_REFERRAL                  = 'explicit_non_referral_signal';
    public const REASON_MIXED_SIGNALS                          = 'mixed_referral_and_non_referral_signals';
    public const REASON_NO_DETERMINISTIC_SIGNAL                = 'no_deterministic_referral_signal';

    /** Not a detection outcome: staff uploaded the file as a referral form (Phase 5E.1). */
    public const REASON_STAFF_UPLOADED_FORM                    = 'staff_uploaded_referral_form';

    /**
     * @param array{id: int, rule_type: string, local_authority_id: int}|null $matched_rule
     * @param array<int, string> $evidence_codes
     * @param array<int, array{authority_id: int, authority_name: string}> $ambiguous_candidates
     */
    public function __construct(
        private string $detection_status,
        private string $detection_reason,
        private string $sender_match_status,
        private ?int $suggested_local_authority_id,
        private ?array $matched_rule,
        private array $evidence_codes,
        private array $ambiguous_candidates
    ) {
    }

    public function detection_status(): string
    {
        return $this->detection_status;
    }

    public function detection_reason(): string
    {
        return $this->detection_reason;
    }

    public function sender_match_status(): string
    {
        return $this->sender_match_status;
    }

    public function suggested_local_authority_id(): ?int
    {
        return $this->suggested_local_authority_id;
    }

    /**
     * Safe rule metadata. Rule values (emails/domains) are omitted.
     *
     * @return array{id: int, rule_type: string, local_authority_id: int}|null
     */
    public function matched_rule(): ?array
    {
        return $this->matched_rule;
    }

    /**
     * @return array<int, string>
     */
    public function evidence_codes(): array
    {
        return $this->evidence_codes;
    }

    /**
     * Runtime-only. Not persisted in Phase 5D.1.
     *
     * @return array<int, array{authority_id: int, authority_name: string}>
     */
    public function ambiguous_candidates(): array
    {
        return $this->ambiguous_candidates;
    }
}
