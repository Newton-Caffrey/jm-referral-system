<?php

namespace JMReferral\ReferralInbox;

/**
 * Staff-facing explanations for persisted detection reason codes (Phase 5D.2).
 *
 * Explanations do not include message excerpts, sender text, or filenames.
 */
final class ReferralInboxDetectionExplanation
{
    public static function for_reason(?string $reason): string
    {
        $code = strtolower(trim((string) $reason));

        return match ($code) {
            ReferralInboxDetectionResult::REASON_RECOGNISED_SENDER_AND_REFERRAL_SIGNAL => __(
                'The sender matches a configured Local Authority rule and referral-related indicators were found in the message metadata.',
                'jm-referral-system'
            ),
            ReferralInboxDetectionResult::REASON_MULTIPLE_INDEPENDENT_REFERRAL_SIGNALS => __(
                'Referral-related indicators were found in more than one part of the message.',
                'jm-referral-system'
            ),
            ReferralInboxDetectionResult::REASON_RECOGNISED_SENDER_WITHOUT_SIGNAL => __(
                'The sender matches a configured Local Authority rule, but JMRS did not find a clear referral indicator.',
                'jm-referral-system'
            ),
            ReferralInboxDetectionResult::REASON_SINGLE_SIGNAL_UNRECOGNISED_SENDER => __(
                'JMRS found one referral-related signal, but the sender does not match a configured Local Authority rule.',
                'jm-referral-system'
            ),
            ReferralInboxDetectionResult::REASON_AMBIGUOUS_RECOGNISED_SENDER => __(
                'The sender matches more than one configured Local Authority.',
                'jm-referral-system'
            ),
            ReferralInboxDetectionResult::REASON_INVALID_SENDER_WITH_SIGNAL => __(
                'Referral-related indicators were found, but the sender address could not be reliably evaluated.',
                'jm-referral-system'
            ),
            ReferralInboxDetectionResult::REASON_EXPLICIT_NON_REFERRAL => __(
                'JMRS found an explicit administrative or non-referral indicator.',
                'jm-referral-system'
            ),
            ReferralInboxDetectionResult::REASON_MIXED_SIGNALS => __(
                'JMRS found both referral-related and non-referral indicators.',
                'jm-referral-system'
            ),
            ReferralInboxDetectionResult::REASON_NO_DETERMINISTIC_SIGNAL => __(
                'JMRS did not find enough deterministic information to classify this message.',
                'jm-referral-system'
            ),
            default => __(
                'JMRS does not have a detailed explanation for this classification.',
                'jm-referral-system'
            ),
        };
    }

    public static function recognised_sender_disclaimer(): string
    {
        return __(
            'Recognised sender means the address matches a configured sender rule. It does not verify or authenticate the email sender.',
            'jm-referral-system'
        );
    }
}
