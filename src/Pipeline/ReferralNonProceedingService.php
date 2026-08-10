<?php

namespace JMReferral\Pipeline;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Referral\ReferralService;
use JMReferral\Users\UserProvider;

/**
 * Marks an acquisition referral as Not Proceeding from eligible active stages.
 *
 * Does not apply at awaiting_la_decision (use LocalAuthorityDecisionService).
 */
class ReferralNonProceedingService
{
    private const LOCK_TTL = 60;

    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralService $referral_service,
        private ReferralPipelineService $pipeline_service,
        private ReferralStageHistoryRepository $stage_history_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider
    ) {
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_mark(array $referral): bool
    {
        if (! $this->access_policy->can_mark_not_proceeding($referral)) {
            return false;
        }

        $slug = $this->pipeline_service->current_stage_slug($referral);

        return NonProceedingReason::is_allowed_source_stage($slug);
    }

    /**
     * @param array<string, mixed> $referral
     * @return array<string, mixed>
     */
    public function get_panel_context(array $referral, string $suggested_reason = ''): array
    {
        $referral_id = absint($referral['id'] ?? 0);
        $stage = $this->pipeline_service->current_stage_slug($referral);
        $is_closed = PipelineStage::NOT_PROCEEDING === $stage;
        $can_view = $this->access_policy->can_view_referral($referral);

        $history = null;
        if ($is_closed) {
            $history = $this->stage_history_repository->find_latest_to_stage(
                $referral_id,
                PipelineStage::NOT_PROCEEDING
            );
        }

        $reason_code = is_array($history) ? (string) ($history['reason'] ?? '') : '';
        $changed_by = is_array($history) ? absint($history['changed_by'] ?? 0) : 0;

        $suggested = sanitize_key($suggested_reason);
        if (! NonProceedingReason::is_valid($suggested)) {
            $suggested = '';
        }

        return [
            'show_panel'           => $can_view && ($this->can_mark($referral) || $is_closed),
            'can_mark'             => $this->can_mark($referral),
            'is_closed'            => $is_closed,
            'stage_slug'           => $stage,
            'reason_options'       => NonProceedingReason::labels(),
            'suggested_reason'     => $suggested,
            'closed_reason_code'   => $reason_code,
            'closed_reason_label'  => '' !== $reason_code ? NonProceedingReason::label($reason_code) : '',
            'closed_at'            => is_array($history) ? (string) ($history['created_at'] ?? '') : '',
            'closed_by_name'       => $changed_by > 0 ? $this->user_provider->get_display_name($changed_by) : '',
            'status_label'         => $is_closed
                ? __('Cancelled', 'jm-referral-system')
                : '',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true}|array{ok: false, error: string, message: string}
     */
    public function mark(int $referral_id, array $input): array
    {
        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return $this->fail('referral_not_found', __('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->can_mark($referral)) {
            $slug = $this->pipeline_service->current_stage_slug($referral);
            if (PipelineStage::AWAITING_LA_DECISION === $slug) {
                return $this->fail(
                    'use_la_decision',
                    __('Use Record Local Authority Decision to mark this referral as not proceeding.', 'jm-referral-system')
                );
            }
            if (PipelineStage::NOT_PROCEEDING === $slug) {
                return $this->fail(
                    'already_closed',
                    __('This referral is already marked as not proceeding.', 'jm-referral-system')
                );
            }

            return $this->fail(
                'access_denied',
                __('You cannot mark this referral as not proceeding in its current state.', 'jm-referral-system')
            );
        }

        $reason_code = sanitize_key((string) ($input['reason_code'] ?? ''));
        if (! NonProceedingReason::is_valid($reason_code)) {
            return $this->fail('validation', __('Please select a valid reason.', 'jm-referral-system'));
        }

        if (! $this->acquire_lock($referral_id)) {
            return $this->fail(
                'in_progress',
                __('A Not Proceeding action is already in progress. Please wait a moment.', 'jm-referral-system')
            );
        }

        try {
            return $this->persist($referral_id, $reason_code);
        } finally {
            $this->release_lock($referral_id);
        }
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string, message: string}
     */
    private function persist(int $referral_id, string $reason_code): array
    {
        $fresh = $this->referral_repository->find($referral_id);
        if (null === $fresh || ! $this->can_mark($fresh)) {
            return $this->fail(
                'already_closed',
                __('This referral is already marked as not proceeding.', 'jm-referral-system')
            );
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        $transition = $this->pipeline_service->transition(
            $referral_id,
            PipelineStage::NOT_PROCEEDING,
            $reason_code,
            false,
            false
        );

        if (empty($transition['ok'])) {
            $wpdb->query('ROLLBACK');

            return $this->fail(
                'transition_failed',
                __('Unable to mark the referral as not proceeding. Please try again.', 'jm-referral-system')
            );
        }

        $status_result = $this->referral_service->change_lifecycle_status($referral_id, 'cancelled', false);
        if (empty($status_result['ok'])) {
            $wpdb->query('ROLLBACK');

            return $this->fail(
                'status_failed',
                __('Unable to update referral status. Please try again.', 'jm-referral-system')
            );
        }

        $wpdb->query('COMMIT');

        $this->activity_service->log_referral_not_proceeding($referral_id);
        $from_label = (string) ($transition['from_label'] ?? '');
        $to_label   = (string) ($transition['to_label'] ?? PipelineStage::label(PipelineStage::NOT_PROCEEDING));
        if ('' !== $from_label) {
            $this->activity_service->log_pipeline_stage_changed($referral_id, $from_label, $to_label);
        }

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

    private function lock_option_key(int $referral_id): string
    {
        return 'jmrs_np_lock_' . $referral_id;
    }

    private function acquire_lock(int $referral_id): bool
    {
        $key = $this->lock_option_key($referral_id);
        $now = time();
        $existing = get_option($key, false);

        if (false !== $existing) {
            if (($now - (int) $existing) < self::LOCK_TTL) {
                return false;
            }
            delete_option($key);
        }

        return (bool) add_option($key, (string) $now, '', 'no');
    }

    private function release_lock(int $referral_id): void
    {
        delete_option($this->lock_option_key($referral_id));
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
}
