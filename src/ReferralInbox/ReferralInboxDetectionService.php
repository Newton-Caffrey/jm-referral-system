<?php

namespace JMReferral\ReferralInbox;

use JMReferral\LocalAuthority\LocalAuthoritySenderMatcher;

/**
 * Advisory, deterministic Referral Inbox detection (Phase 5D.1).
 *
 * Evaluates stored metadata and recognised-sender rules. Does not change
 * Inbox lifecycle, does not create referrals, and does not call external
 * classifiers. Automatic persistence is guarded: detection metadata is
 * written only while detection_status is still unclassified, and a Local
 * Authority id is written only when the current value is NULL and the
 * matcher returned MATCH.
 */
class ReferralInboxDetectionService
{
    public function __construct(
        private ReferralInboxService $inbox_service,
        private LocalAuthoritySenderMatcher $matcher
    ) {
    }

    /**
     * Pure evaluation. Does not persist.
     */
    public function evaluate(int $inbox_id): ?ReferralInboxDetectionResult
    {
        $item = $this->inbox_service->find($inbox_id);
        if (null === $item) {
            return null;
        }

        return $this->evaluateFields($item, $this->inbox_service->list_attachments($inbox_id));
    }

    /**
     * Pure evaluation of an already-loaded snapshot.
     *
     * Used for in-memory checks (including sender values the Inbox create
     * path rejects). Does not persist and does not read the database.
     *
     * @param array<string, mixed> $item
     * @param array<int, array<string, mixed>> $attachments
     */
    public function evaluateFields(array $item, array $attachments): ReferralInboxDetectionResult
    {
        $sender_email = trim((string) ($item['sender_email'] ?? ''));
        $match        = $this->matcher->matchSender($sender_email);
        $match_status = (string) ($match['status'] ?? LocalAuthoritySenderMatcher::NO_MATCH);

        $filenames = [];
        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $filename = trim((string) ($attachment['filename'] ?? ''));
            if ('' !== $filename) {
                $filenames[] = $filename;
            }
        }

        $subject = (string) ($item['subject'] ?? '');
        $preview = (string) ($item['body_preview'] ?? '');

        $positive_sources = ReferralInboxDetectionRules::positive_sources($subject, $preview, $filenames);
        $negative_sources = ReferralInboxDetectionRules::negative_sources($subject, $preview, $filenames);

        [$status, $reason] = $this->classify(
            $match_status,
            count($positive_sources),
            [] !== $negative_sources
        );

        $suggested_id = null;
        $matched_rule = null;
        if (LocalAuthoritySenderMatcher::MATCH === $match_status) {
            $authority_id = (int) ($match['authority']['id'] ?? 0);
            if ($authority_id > 0) {
                $suggested_id = $authority_id;
            }
            $matched_rule = $this->safe_rule(is_array($match['matched_rule'] ?? null) ? $match['matched_rule'] : null);
        }

        $evidence = ['sender:' . $match_status];
        foreach ($positive_sources as $source) {
            $evidence[] = 'positive:' . $source;
        }
        foreach ($negative_sources as $source) {
            $evidence[] = 'negative:' . $source;
        }
        $evidence[] = 'rule:' . $reason;

        return new ReferralInboxDetectionResult(
            $status,
            $reason,
            $match_status,
            $suggested_id,
            $matched_rule,
            $evidence,
            $this->safe_candidates($match_status, $match['candidates'] ?? [])
        );
    }

    /**
     * Evaluate and guarded-persist. Does not overwrite an existing
     * classification or a non-null Local Authority id.
     *
     * @return array{
     *   outcome: string,
     *   evaluation: ReferralInboxDetectionResult|null,
     *   detection_written: bool,
     *   authority_written: bool,
     *   item: array<string, mixed>|null,
     *   errors?: array<string, string>
     * }
     */
    public function evaluateAndApply(int $inbox_id): array
    {
        $evaluation = $this->evaluate($inbox_id);
        if (null === $evaluation) {
            return [
                'outcome'           => 'not_found',
                'evaluation'        => null,
                'detection_written' => false,
                'authority_written' => false,
                'item'              => null,
            ];
        }

        $applied = $this->inbox_service->applyGuardedDetection(
            $inbox_id,
            $evaluation->detection_status(),
            $evaluation->detection_reason(),
            $evaluation->suggested_local_authority_id()
        );

        $written_detection = ! empty($applied['detection_written']);
        $written_authority = ! empty($applied['authority_written']);

        $outcome = 'unchanged';
        if (ReferralInboxResult::NOT_FOUND === ($applied['result'] ?? '')) {
            $outcome = 'not_found';
        } elseif ($written_detection || $written_authority) {
            $outcome = 'applied';
        }

        $response = [
            'outcome'           => $outcome,
            'evaluation'        => $evaluation,
            'detection_written' => $written_detection,
            'authority_written' => $written_authority,
            'item'              => is_array($applied['item'] ?? null) ? $applied['item'] : null,
        ];

        if (is_array($applied['errors'] ?? null)) {
            $response['errors'] = $applied['errors'];
        }

        return $response;
    }

    /**
     * Precedence (first match wins):
     * 1. Mixed positive and negative signals.
     * 2. Explicit negative signals only.
     * 3. Ambiguous recognised sender.
     * 4. Invalid sender with any positive signal.
     * 5. Recognised sender with any positive signal.
     * 6. Recognised sender with no positive signal.
     * 7. Unrecognised sender with two or more independent positive sources.
     * 8. Unrecognised sender with exactly one positive source.
     * 9. No deterministic signal.
     *
     * Authority suggestion is not decided here. MATCH still carries a
     * suggested authority id on the result, including when the status is
     * not_referral or uncertain.
     *
     * @return array{0: string, 1: string}
     */
    private function classify(string $sender_status, int $positive_sources, bool $has_negative): array
    {
        if ($positive_sources > 0 && $has_negative) {
            return [
                ReferralDetectionStatus::UNCERTAIN,
                ReferralInboxDetectionResult::REASON_MIXED_SIGNALS,
            ];
        }

        if ($has_negative) {
            return [
                ReferralDetectionStatus::NOT_REFERRAL,
                ReferralInboxDetectionResult::REASON_EXPLICIT_NON_REFERRAL,
            ];
        }

        if (LocalAuthoritySenderMatcher::AMBIGUOUS === $sender_status) {
            return [
                ReferralDetectionStatus::UNCERTAIN,
                ReferralInboxDetectionResult::REASON_AMBIGUOUS_RECOGNISED_SENDER,
            ];
        }

        if (LocalAuthoritySenderMatcher::INVALID_SENDER === $sender_status && $positive_sources > 0) {
            return [
                ReferralDetectionStatus::UNCERTAIN,
                ReferralInboxDetectionResult::REASON_INVALID_SENDER_WITH_SIGNAL,
            ];
        }

        if (LocalAuthoritySenderMatcher::MATCH === $sender_status && $positive_sources > 0) {
            return [
                ReferralDetectionStatus::LIKELY,
                ReferralInboxDetectionResult::REASON_RECOGNISED_SENDER_AND_REFERRAL_SIGNAL,
            ];
        }

        if (LocalAuthoritySenderMatcher::MATCH === $sender_status) {
            return [
                ReferralDetectionStatus::UNCERTAIN,
                ReferralInboxDetectionResult::REASON_RECOGNISED_SENDER_WITHOUT_SIGNAL,
            ];
        }

        if ($positive_sources >= 2) {
            return [
                ReferralDetectionStatus::LIKELY,
                ReferralInboxDetectionResult::REASON_MULTIPLE_INDEPENDENT_REFERRAL_SIGNALS,
            ];
        }

        if (1 === $positive_sources) {
            return [
                ReferralDetectionStatus::UNCERTAIN,
                ReferralInboxDetectionResult::REASON_SINGLE_SIGNAL_UNRECOGNISED_SENDER,
            ];
        }

        return [
            ReferralDetectionStatus::UNCLASSIFIED,
            ReferralInboxDetectionResult::REASON_NO_DETERMINISTIC_SIGNAL,
        ];
    }

    /**
     * @param array<string, mixed>|null $rule
     * @return array{id: int, rule_type: string, local_authority_id: int}|null
     */
    private function safe_rule(?array $rule): ?array
    {
        if (null === $rule) {
            return null;
        }

        $id = (int) ($rule['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        return [
            'id'                 => $id,
            'rule_type'          => (string) ($rule['rule_type'] ?? ''),
            'local_authority_id' => (int) ($rule['local_authority_id'] ?? 0),
        ];
    }

    /**
     * @param mixed $candidates
     * @return array<int, array{authority_id: int, authority_name: string}>
     */
    private function safe_candidates(string $match_status, mixed $candidates): array
    {
        if (LocalAuthoritySenderMatcher::AMBIGUOUS !== $match_status || ! is_array($candidates)) {
            return [];
        }

        $safe = [];
        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            $authority_id = (int) ($candidate['authority_id'] ?? 0);
            if ($authority_id <= 0) {
                continue;
            }
            $safe[] = [
                'authority_id'   => $authority_id,
                'authority_name' => (string) ($candidate['authority_name'] ?? ''),
            ];
        }

        return $safe;
    }
}
