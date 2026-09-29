<?php

namespace JMReferral\ReferralInbox;

/**
 * One advisory candidate field (Phase 5D.3).
 *
 * In memory only. No string cast, because the value may be personal data.
 */
final class ReferralInboxCandidateField
{
    public const STATE_NONE      = 'none';
    public const STATE_SINGLE    = 'single';
    public const STATE_AMBIGUOUS = 'ambiguous';

    public const BAND_STRONG   = 'strong';
    public const BAND_MODERATE = 'moderate';
    public const BAND_WEAK     = 'weak';

    /**
     * @param array<int, string> $alternatives
     */
    private function __construct(
        private string $state,
        private ?string $value,
        private array $alternatives,
        private string $source,
        private string $evidence_type,
        private string $confidence_band
    ) {
    }

    public static function none(): self
    {
        return new self(self::STATE_NONE, null, [], '', '', '');
    }

    public static function single(string $value, string $source, string $evidence_type, string $confidence_band): self
    {
        return new self(self::STATE_SINGLE, $value, [$value], $source, $evidence_type, $confidence_band);
    }

    /**
     * @param array<int, string> $alternatives
     */
    public static function ambiguous(array $alternatives, string $source, string $evidence_type): self
    {
        $clean = [];
        foreach ($alternatives as $alternative) {
            if (! is_string($alternative) || '' === $alternative) {
                continue;
            }
            $clean[] = $alternative;
        }

        return new self(self::STATE_AMBIGUOUS, null, $clean, $source, $evidence_type, '');
    }

    public function state(): string
    {
        return $this->state;
    }

    public function value(): ?string
    {
        return $this->value;
    }

    /**
     * @return array<int, string>
     */
    public function alternatives(): array
    {
        return $this->alternatives;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function evidence_type(): string
    {
        return $this->evidence_type;
    }

    public function confidence_band(): string
    {
        return $this->confidence_band;
    }
}
