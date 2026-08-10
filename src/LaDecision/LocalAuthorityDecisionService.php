<?php

namespace JMReferral\LaDecision;

use JMReferral\PackageCost\PackageCost;
use JMReferral\PackageCost\PackageCostRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Pipeline\PipelineStage;
use JMReferral\Pipeline\ReferralPipelineService;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Referral\ReferralService;
use JMReferral\Users\UserProvider;

/**
 * Records Local Authority / funding outcomes and advances the acquisition pipeline.
 *
 * Decisions are immutable in normal workflow after recording.
 */
class LocalAuthorityDecisionService
{
    private const LOCK_TTL = 60;

    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralService $referral_service,
        private LaDecisionRepository $decision_repository,
        private PackageCostRepository $package_cost_repository,
        private ReferralPipelineService $pipeline_service,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider
    ) {
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_record(array $referral): bool
    {
        if (! $this->access_policy->can_record_la_decision($referral)) {
            return false;
        }

        if (PipelineStage::AWAITING_LA_DECISION !== $this->pipeline_service->current_stage_slug($referral)) {
            return false;
        }

        $referral_id = absint($referral['id'] ?? 0);
        if ($this->decision_repository->exists_for_referral($referral_id)) {
            return false;
        }

        $package_cost = $this->package_cost_repository->find_current_for_referral($referral_id);
        if (null === $package_cost || ! PackageCost::is_sent((string) ($package_cost['status'] ?? ''))) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $referral
     * @return array<string, mixed>
     */
    public function get_panel_context(array $referral): array
    {
        $referral_id = absint($referral['id'] ?? 0);
        $stage = $this->pipeline_service->current_stage_slug($referral);
        $decision = $this->decision_repository->find_current_for_referral($referral_id);
        $package_cost = $this->package_cost_repository->find_current_for_referral($referral_id);
        $has_sent_pc = null !== $package_cost && PackageCost::is_sent((string) ($package_cost['status'] ?? ''));

        $can_view = $this->access_policy->can_view_referral($referral)
            && (
                $this->access_policy->can_record_la_decision($referral)
                || $this->access_policy->can_edit_referral($referral)
            );

        $show_panel = $can_view && (
            PipelineStage::AWAITING_LA_DECISION === $stage
            || null !== $decision
            || (
                $has_sent_pc
                && in_array(
                    $stage,
                    [
                        PipelineStage::TRANSITION_PLANNING,
                        PipelineStage::DECLINED,
                        PipelineStage::NOT_PROCEEDING,
                        PipelineStage::CARE_COMMENCED,
                    ],
                    true
                )
            )
        );

        $recorded_by = is_array($decision) ? absint($decision['recorded_by'] ?? 0) : 0;
        $funding_raw = is_array($decision) && array_key_exists('funding_confirmed', $decision)
            ? $decision['funding_confirmed']
            : null;
        $funding_int = null === $funding_raw || '' === $funding_raw
            ? null
            : (int) $funding_raw;

        return [
            'show_panel'              => $show_panel,
            'stage_slug'              => $stage,
            'can_record'              => $this->can_record($referral),
            'has_decision'            => null !== $decision,
            'is_awaiting'             => PipelineStage::AWAITING_LA_DECISION === $stage,
            'decision'                => is_array($decision) ? (string) ($decision['decision'] ?? '') : '',
            'decision_label'          => is_array($decision)
                ? LaDecision::decision_label((string) ($decision['decision'] ?? ''))
                : '',
            'decision_at'             => is_array($decision) ? (string) ($decision['decision_at'] ?? '') : '',
            'recorded_by_name'        => $recorded_by > 0 ? $this->user_provider->get_display_name($recorded_by) : '',
            'funding_confirmed'       => $funding_int,
            'funding_confirmed_label' => is_array($decision)
                ? LaDecision::funding_confirmed_label($funding_int)
                : '',
            'funding_reference'       => is_array($decision) ? (string) ($decision['funding_reference'] ?? '') : '',
            'decision_reference'      => is_array($decision) ? (string) ($decision['decision_reference'] ?? '') : '',
            'reason_code'             => is_array($decision) ? (string) ($decision['reason_code'] ?? '') : '',
            'reason_label'            => is_array($decision)
                ? LaDecision::reason_label(
                    (string) ($decision['decision'] ?? ''),
                    (string) ($decision['reason_code'] ?? '')
                )
                : '',
            'notes'                   => is_array($decision) ? (string) ($decision['notes'] ?? '') : '',
            'package_cost_sent_at'    => $has_sent_pc ? (string) ($package_cost['sent_at'] ?? '') : '',
            'package_cost_method'     => $has_sent_pc
                ? PackageCost::send_method_label((string) ($package_cost['send_method'] ?? ''))
                : '',
            'package_cost_recipient'  => $has_sent_pc ? (string) ($package_cost['recipient'] ?? '') : '',
            'package_cost_id'         => $has_sent_pc ? absint($package_cost['id'] ?? 0) : 0,
            'decision_options'        => LaDecision::decision_labels(),
            'declined_reasons'        => LaDecision::declined_reason_labels(),
            'not_proceeding_reasons'  => LaDecision::not_proceeding_reason_labels(),
            'funding_options'         => LaDecision::funding_confirmed_labels(),
            'default_decision_at'     => current_time('Y-m-d\TH:i'),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, decision: string}|array{ok: false, error: string, message: string}
     */
    public function record(int $referral_id, array $input): array
    {
        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return $this->fail('referral_not_found', __('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->can_record($referral)) {
            if ($this->decision_repository->exists_for_referral($referral_id)) {
                return $this->fail(
                    'already_recorded',
                    __('A Local Authority decision has already been recorded for this referral.', 'jm-referral-system')
                );
            }

            return $this->fail(
                'access_denied',
                __('You cannot record a Local Authority decision for this referral in its current state.', 'jm-referral-system')
            );
        }

        if (! $this->acquire_lock($referral_id)) {
            return $this->fail(
                'in_progress',
                __('A Local Authority decision is already being recorded. Please wait a moment.', 'jm-referral-system')
            );
        }

        try {
            return $this->persist_decision($referral_id, $referral, $input);
        } finally {
            $this->release_lock($referral_id);
        }
    }

    /**
     * @param array<string, mixed> $referral
     * @param array<string, mixed> $input
     * @return array{ok: true, decision: string}|array{ok: false, error: string, message: string}
     */
    private function persist_decision(int $referral_id, array $referral, array $input): array
    {
        // Re-check inside lock.
        $fresh = $this->referral_repository->find($referral_id);
        if (null === $fresh
            || PipelineStage::AWAITING_LA_DECISION !== $this->pipeline_service->current_stage_slug($fresh)
            || $this->decision_repository->exists_for_referral($referral_id)
        ) {
            return $this->fail(
                'already_recorded',
                __('A Local Authority decision has already been recorded for this referral.', 'jm-referral-system')
            );
        }

        $package_cost = $this->package_cost_repository->find_current_for_referral($referral_id);
        if (null === $package_cost
            || ! PackageCost::is_sent((string) ($package_cost['status'] ?? ''))
            || absint($package_cost['referral_id'] ?? 0) !== $referral_id
        ) {
            return $this->fail(
                'package_cost_required',
                __('A sent Package Cost is required before recording a Local Authority decision.', 'jm-referral-system')
            );
        }

        $decision = sanitize_key((string) ($input['decision'] ?? ''));
        if (! LaDecision::is_valid_decision($decision)) {
            return $this->fail('invalid_decision', __('Please select a valid decision outcome.', 'jm-referral-system'));
        }

        $decision_at = $this->normalize_decision_at((string) ($input['decision_at'] ?? ''));
        if (null === $decision_at) {
            return $this->fail('validation', __('Please enter a valid decision date and time.', 'jm-referral-system'));
        }

        $reason_code = sanitize_key((string) ($input['reason_code'] ?? ''));
        $notes = sanitize_textarea_field((string) ($input['notes'] ?? ''));
        $notes = substr(trim($notes), 0, LaDecision::NOTES_MAX);
        $funding_reference = substr(sanitize_text_field((string) ($input['funding_reference'] ?? '')), 0, 190);
        $decision_reference = substr(sanitize_text_field((string) ($input['decision_reference'] ?? '')), 0, 190);

        $funding_confirmed = null;
        $target_stage = '';
        $target_status = '';

        if (LaDecision::DECISION_APPROVED === $decision) {
            $funding_token = sanitize_key((string) ($input['funding_confirmed'] ?? 'not_recorded'));
            if (! array_key_exists($funding_token, LaDecision::funding_confirmed_labels())) {
                $funding_token = 'not_recorded';
            }
            $funding_confirmed = LaDecision::normalize_funding_confirmed($funding_token);
            if (LaDecision::FUNDING_YES !== $funding_confirmed) {
                $funding_reference = '';
            }
            $reason_code = '';
            $target_stage = PipelineStage::TRANSITION_PLANNING;
            $target_status = 'in_progress';
        } elseif (LaDecision::DECISION_DECLINED === $decision) {
            if ('' !== $reason_code && ! LaDecision::is_valid_reason($decision, $reason_code)) {
                return $this->fail('validation', __('Please select a valid decline reason.', 'jm-referral-system'));
            }
            if ('' === $reason_code && '' === $notes) {
                return $this->fail(
                    'validation',
                    __('Please provide a reason or short operational note for a declined decision.', 'jm-referral-system')
                );
            }
            $funding_confirmed = null;
            $funding_reference = '';
            $target_stage = PipelineStage::DECLINED;
            $target_status = 'cancelled';
        } else {
            if ('' === $reason_code || ! LaDecision::is_valid_reason($decision, $reason_code)) {
                return $this->fail('validation', __('Please select a reason for not proceeding.', 'jm-referral-system'));
            }
            $funding_confirmed = null;
            $funding_reference = '';
            $target_stage = PipelineStage::NOT_PROCEEDING;
            $target_status = 'cancelled';
        }

        $user_id = get_current_user_id();
        $now = current_time('mysql');
        $package_cost_id = absint($package_cost['id'] ?? 0);

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        // Idempotent: refuse if a row appeared concurrently.
        if ($this->decision_repository->exists_for_referral($referral_id)) {
            $wpdb->query('ROLLBACK');

            return $this->fail(
                'already_recorded',
                __('A Local Authority decision has already been recorded for this referral.', 'jm-referral-system')
            );
        }

        $decision_id = $this->decision_repository->create(
            [
                'referral_id'        => $referral_id,
                'package_cost_id'    => $package_cost_id > 0 ? $package_cost_id : null,
                'decision'           => $decision,
                'decision_at'        => $decision_at,
                'recorded_by'        => $user_id > 0 ? $user_id : null,
                'funding_confirmed'  => $funding_confirmed,
                'funding_reference'  => '' !== $funding_reference ? $funding_reference : null,
                'decision_reference' => '' !== $decision_reference ? $decision_reference : null,
                'reason_code'        => '' !== $reason_code ? $reason_code : null,
                'notes'              => '' !== $notes ? $notes : null,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]
        );

        if (false === $decision_id) {
            $wpdb->query('ROLLBACK');

            return $this->fail('persist_failed', __('Unable to save the Local Authority decision. Please try again.', 'jm-referral-system'));
        }

        $history_reason = '' !== $reason_code ? $reason_code : null;

        $transition = $this->pipeline_service->transition(
            $referral_id,
            $target_stage,
            $history_reason,
            false,
            false
        );

        if (empty($transition['ok'])) {
            $wpdb->query('ROLLBACK');

            return $this->fail(
                'transition_failed',
                __('The decision could not be completed because the pipeline could not be updated. Please try again.', 'jm-referral-system')
            );
        }

        $status_result = $this->referral_service->change_lifecycle_status($referral_id, $target_status, false);
        if (empty($status_result['ok'])) {
            $wpdb->query('ROLLBACK');

            return $this->fail(
                'status_failed',
                __('The decision could not be completed because referral status could not be updated. Please try again.', 'jm-referral-system')
            );
        }

        $wpdb->query('COMMIT');

        $this->log_decision_activity($referral_id, $decision);
        $from_label = (string) ($transition['from_label'] ?? PipelineStage::label(PipelineStage::AWAITING_LA_DECISION));
        $to_label   = (string) ($transition['to_label'] ?? PipelineStage::label($target_stage));
        $this->activity_service->log_pipeline_stage_changed($referral_id, $from_label, $to_label);

        if (! empty($status_result['changed'])) {
            $updated_referral = $this->referral_repository->find($referral_id);
            if (is_array($updated_referral)) {
                $this->referral_service->emit_status_change_side_effects(
                    $updated_referral,
                    (string) $status_result['old_status'],
                    (string) $status_result['new_status']
                );
            }
        }

        return ['ok' => true, 'decision' => $decision];
    }

    private function log_decision_activity(int $referral_id, string $decision): void
    {
        if (LaDecision::DECISION_APPROVED === $decision) {
            $this->activity_service->log_la_decision_approved($referral_id);
        } elseif (LaDecision::DECISION_DECLINED === $decision) {
            $this->activity_service->log_la_decision_declined($referral_id);
        } else {
            $this->activity_service->log_referral_not_proceeding($referral_id);
        }
    }

    private function normalize_decision_at(string $raw): ?string
    {
        $raw = trim($raw);
        if ('' === $raw) {
            return null;
        }

        $raw = str_replace('T', ' ', $raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
            $raw .= ':00';
        }

        try {
            $dt = date_create_immutable($raw, wp_timezone());
        } catch (\Exception $e) {
            return null;
        }

        if (false === $dt) {
            return null;
        }

        return $dt->format('Y-m-d H:i:s');
    }

    private function lock_option_key(int $referral_id): string
    {
        return 'jmrs_la_decision_lock_' . $referral_id;
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
