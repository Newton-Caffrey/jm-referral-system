<?php

namespace JMReferral\Transition;

use JMReferral\Homes\OccupancyRepository;
use JMReferral\LaDecision\LaDecision;
use JMReferral\LaDecision\LaDecisionRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Pipeline\PipelineStage;
use JMReferral\Pipeline\ReferralPipelineService;
use JMReferral\Referral\CareSetting;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Referral\ReferralService;

/**
 * Explicit care commencement milestone: transition_planning → care_commenced.
 *
 * Does not auto-commence from occupancy, visits, schedules, or care plans.
 */
class CareCommencementService
{
    private const LOCK_TTL = 60;

    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralService $referral_service,
        private ReferralPipelineService $pipeline_service,
        private LaDecisionRepository $la_decision_repository,
        private OccupancyRepository $occupancy_repository,
        private TransitionPlanningService $transition_planning_service,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_commence(array $referral): bool
    {
        if (! $this->access_policy->can_commence_care($referral)) {
            return false;
        }

        if (PipelineStage::TRANSITION_PLANNING !== $this->pipeline_service->current_stage_slug($referral)) {
            return false;
        }

        if (null !== $this->nullable_mysql((string) ($referral['care_commenced_at'] ?? ''))) {
            return false;
        }

        $referral_id = absint($referral['id'] ?? 0);
        $decision = $this->la_decision_repository->find_current_for_referral($referral_id);
        $la_approved = is_array($decision)
            && LaDecision::DECISION_APPROVED === (string) ($decision['decision'] ?? '');

        $care_setting = CareSetting::normalize(
            null === ($referral['care_setting'] ?? null)
                ? null
                : (string) $referral['care_setting']
        );
        $occupancy = $this->occupancy_repository->current_for_referral($referral_id);

        $hard = $this->transition_planning_service->evaluate_hard_requirements(
            $referral,
            PipelineStage::TRANSITION_PLANNING,
            $la_approved,
            $care_setting,
            $occupancy
        );

        return [] === $hard['blocking'];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true}|array{ok: false, error: string, message: string}
     */
    public function commence(int $referral_id, array $input): array
    {
        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return $this->fail('referral_not_found', __('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->access_policy->can_commence_care($referral)) {
            return $this->fail(
                'access_denied',
                __('You cannot confirm care commencement for this referral.', 'jm-referral-system')
            );
        }

        if (null !== $this->nullable_mysql((string) ($referral['care_commenced_at'] ?? ''))) {
            return $this->fail(
                'already_commenced',
                __('Care commencement has already been recorded for this referral.', 'jm-referral-system')
            );
        }

        if (PipelineStage::TRANSITION_PLANNING !== $this->pipeline_service->current_stage_slug($referral)) {
            return $this->fail(
                'wrong_stage',
                __('Care commencement is only available during Transition Planning.', 'jm-referral-system')
            );
        }

        if (! $this->acquire_lock($referral_id)) {
            return $this->fail(
                'in_progress',
                __('Care commencement is already being recorded. Please wait a moment.', 'jm-referral-system')
            );
        }

        try {
            return $this->persist_commencement($referral_id, $referral, $input);
        } finally {
            $this->release_lock($referral_id);
        }
    }

    /**
     * @param array<string, mixed> $referral
     * @param array<string, mixed> $input
     * @return array{ok: true}|array{ok: false, error: string, message: string}
     */
    private function persist_commencement(int $referral_id, array $referral, array $input): array
    {
        $fresh = $this->referral_repository->find($referral_id);
        if (null === $fresh
            || null !== $this->nullable_mysql((string) ($fresh['care_commenced_at'] ?? ''))
            || PipelineStage::TRANSITION_PLANNING !== $this->pipeline_service->current_stage_slug($fresh)
        ) {
            return $this->fail(
                'already_commenced',
                __('Care commencement has already been recorded for this referral.', 'jm-referral-system')
            );
        }

        $decision = $this->la_decision_repository->find_current_for_referral($referral_id);
        $la_approved = is_array($decision)
            && LaDecision::DECISION_APPROVED === (string) ($decision['decision'] ?? '');

        $care_setting = CareSetting::normalize(
            null === ($fresh['care_setting'] ?? null)
                ? null
                : (string) $fresh['care_setting']
        );
        $occupancy = $this->occupancy_repository->current_for_referral($referral_id);

        $hard = $this->transition_planning_service->evaluate_hard_requirements(
            $fresh,
            PipelineStage::TRANSITION_PLANNING,
            $la_approved,
            $care_setting,
            $occupancy
        );
        if ([] !== $hard['blocking']) {
            return $this->fail('blocked', (string) $hard['blocking'][0]);
        }

        $funding_raw = is_array($decision) && array_key_exists('funding_confirmed', $decision)
            ? $decision['funding_confirmed']
            : null;
        $funding_int = null === $funding_raw || '' === $funding_raw
            ? null
            : (int) $funding_raw;
        $funding_ok = LaDecision::FUNDING_YES === $funding_int;

        if (! $funding_ok) {
            $ack = ! empty($input['funding_acknowledge']);
            if (! $ack) {
                return $this->fail(
                    'funding_ack_required',
                    __(
                        'Please acknowledge that funding is not confirmed before commencing care.',
                        'jm-referral-system'
                    )
                );
            }
        }

        $commenced_at = $this->normalize_commenced_at(
            (string) ($input['care_commenced_at'] ?? ''),
            (string) ($input['care_commenced_date'] ?? ''),
            (string) ($input['care_commenced_time'] ?? '')
        );
        if (null === $commenced_at) {
            return $this->fail(
                'validation',
                __('Please enter a valid care commencement date and time.', 'jm-referral-system')
            );
        }

        $commenced_ts = strtotime($commenced_at);
        $now_ts = (int) current_time('timestamp');
        if (false === $commenced_ts || $commenced_ts > $now_ts) {
            return $this->fail(
                'validation',
                __('Care commencement cannot be recorded in the future.', 'jm-referral-system')
            );
        }

        if (CareSetting::SUPPORTED_LIVING === $care_setting && is_array($occupancy)) {
            $move_in = trim((string) ($occupancy['move_in_date'] ?? ''));
            if ('' !== $move_in) {
                $commenced_date = substr($commenced_at, 0, 10);
                if ($commenced_date < $move_in) {
                    return $this->fail(
                        'move_in_future',
                        __(
                            'Care commencement cannot be earlier than the Supported Living move-in date.',
                            'jm-referral-system'
                        )
                    );
                }
            }
        }

        $user_id = get_current_user_id();

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        $claimed = $this->referral_repository->claim_care_commencement(
            $referral_id,
            $commenced_at,
            $user_id > 0 ? $user_id : null
        );
        if (! $claimed) {
            $wpdb->query('ROLLBACK');

            return $this->fail(
                'already_commenced',
                __('Care commencement has already been recorded for this referral.', 'jm-referral-system')
            );
        }

        $transition = $this->pipeline_service->transition(
            $referral_id,
            PipelineStage::CARE_COMMENCED,
            null,
            false,
            false
        );

        if (empty($transition['ok'])) {
            $wpdb->query('ROLLBACK');

            return $this->fail(
                'transition_failed',
                __('Care commencement could not update the pipeline. Please try again.', 'jm-referral-system')
            );
        }

        // Keep broad lifecycle as in_progress (care beginning). Correct unexpected `new` only.
        $status = (string) ($fresh['status'] ?? '');
        $status_result = ['ok' => true, 'changed' => false, 'old_status' => $status, 'new_status' => $status];
        if ('in_progress' !== $status && ! in_array($status, ['completed', 'cancelled'], true)) {
            if ('new' === $status) {
                $status_result = $this->referral_service->change_lifecycle_status(
                    $referral_id,
                    'in_progress',
                    false
                );
                if (empty($status_result['ok'])) {
                    $wpdb->query('ROLLBACK');

                    return $this->fail(
                        'status_failed',
                        __('Care commencement could not update referral status. Please try again.', 'jm-referral-system')
                    );
                }
            }
        }

        $wpdb->query('COMMIT');

        $this->activity_service->log_care_commenced($referral_id);
        $from_label = (string) ($transition['from_label'] ?? PipelineStage::label(PipelineStage::TRANSITION_PLANNING));
        $to_label   = (string) ($transition['to_label'] ?? PipelineStage::label(PipelineStage::CARE_COMMENCED));
        $this->activity_service->log_pipeline_stage_changed($referral_id, $from_label, $to_label);

        if (! empty($status_result['changed'])) {
            $updated = $this->referral_repository->find($referral_id);
            if (is_array($updated)) {
                $this->referral_service->emit_status_change_side_effects(
                    $updated,
                    (string) $status_result['old_status'],
                    (string) $status_result['new_status']
                );
            }
        }

        return ['ok' => true];
    }

    /**
     * Accepts datetime-local, or separate date + time fields.
     */
    private function normalize_commenced_at(string $datetime, string $date, string $time): ?string
    {
        $datetime = trim($datetime);
        if ('' === $datetime) {
            $date = trim($date);
            $time = trim($time);
            if ('' === $date) {
                return null;
            }
            if ('' === $time) {
                $time = '00:00';
            }
            $datetime = $date . ' ' . $time;
        }

        $datetime = str_replace('T', ' ', $datetime);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $datetime)) {
            $datetime .= ':00';
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $datetime)) {
            return null;
        }

        $parts = date_parse($datetime);
        if (false === $parts
            || 0 !== (int) ($parts['error_count'] ?? 0)
            || ! checkdate((int) $parts['month'], (int) $parts['day'], (int) $parts['year'])
        ) {
            return null;
        }

        return sprintf(
            '%04d-%02d-%02d %02d:%02d:%02d',
            (int) $parts['year'],
            (int) $parts['month'],
            (int) $parts['day'],
            (int) $parts['hour'],
            (int) $parts['minute'],
            (int) ($parts['second'] ?? 0)
        );
    }

    private function nullable_mysql(string $value): ?string
    {
        $value = trim($value);

        return '' === $value || '0000-00-00 00:00:00' === $value ? null : $value;
    }

    /**
     * @return array{ok: false, error: string, message: string}
     */
    private function fail(string $error, string $message): array
    {
        return [
            'ok'      => false,
            'error'   => $error,
            'message' => $message,
        ];
    }

    private function acquire_lock(int $referral_id): bool
    {
        $key = $this->lock_key($referral_id);
        if (false !== get_transient($key)) {
            return false;
        }

        return set_transient($key, 1, self::LOCK_TTL);
    }

    private function release_lock(int $referral_id): void
    {
        delete_transient($this->lock_key($referral_id));
    }

    private function lock_key(int $referral_id): string
    {
        return 'jmrs_care_commence_lock_' . $referral_id;
    }
}
