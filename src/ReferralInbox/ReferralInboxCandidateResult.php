<?php

namespace JMReferral\ReferralInbox;

/**
 * In-memory advisory candidates for a later review screen (Phase 5D.3).
 *
 * Nothing here is written to the database. There is no string cast.
 */
final class ReferralInboxCandidateResult
{
    public const FOUND     = 'found';
    public const NOT_FOUND = 'not_found';

    private function __construct(
        private string $outcome,
        private ReferralInboxCandidateField $client_name,
        private ReferralInboxCandidateField $client_email,
        private ReferralInboxCandidateField $client_phone,
        private ReferralInboxCandidateField $referrer_name,
        private ReferralInboxCandidateField $referrer_email,
        private ReferralInboxCandidateField $referrer_organisation,
        private ReferralInboxCandidateField $service_hint,
        private ReferralInboxCandidateField $priority_hint,
        private string $detection_status,
        private string $detection_reason
    ) {
    }

    public static function not_found(): self
    {
        $none = ReferralInboxCandidateField::none();

        return new self(
            self::NOT_FOUND,
            $none,
            $none,
            $none,
            $none,
            $none,
            $none,
            $none,
            $none,
            '',
            ''
        );
    }

    public static function found(
        ReferralInboxCandidateField $client_name,
        ReferralInboxCandidateField $client_email,
        ReferralInboxCandidateField $client_phone,
        ReferralInboxCandidateField $referrer_name,
        ReferralInboxCandidateField $referrer_email,
        ReferralInboxCandidateField $referrer_organisation,
        ReferralInboxCandidateField $service_hint,
        ReferralInboxCandidateField $priority_hint,
        string $detection_status,
        string $detection_reason
    ): self {
        return new self(
            self::FOUND,
            $client_name,
            $client_email,
            $client_phone,
            $referrer_name,
            $referrer_email,
            $referrer_organisation,
            $service_hint,
            $priority_hint,
            $detection_status,
            $detection_reason
        );
    }

    public function outcome(): string
    {
        return $this->outcome;
    }

    public function client_name(): ReferralInboxCandidateField
    {
        return $this->client_name;
    }

    public function client_email(): ReferralInboxCandidateField
    {
        return $this->client_email;
    }

    public function client_phone(): ReferralInboxCandidateField
    {
        return $this->client_phone;
    }

    public function referrer_name(): ReferralInboxCandidateField
    {
        return $this->referrer_name;
    }

    public function referrer_email(): ReferralInboxCandidateField
    {
        return $this->referrer_email;
    }

    public function referrer_organisation(): ReferralInboxCandidateField
    {
        return $this->referrer_organisation;
    }

    public function service_hint(): ReferralInboxCandidateField
    {
        return $this->service_hint;
    }

    public function priority_hint(): ReferralInboxCandidateField
    {
        return $this->priority_hint;
    }

    public function detection_status(): string
    {
        return $this->detection_status;
    }

    public function detection_reason(): string
    {
        return $this->detection_reason;
    }
}
