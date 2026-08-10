<?php

namespace JMReferral\Pipeline;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;
use JMReferral\Workflow\WorkflowStageRepository;

class ReferralPipelineService
{
    public function __construct(
        private ReferralRepository $referral_repository,
        private WorkflowStageRepository $workflow_stage_repository,
        private ReferralStageHistoryRepository $history_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider
    ) {
    }

    /**
     * Resolves a canonical pipeline stage row by slug.
     *
     * @return array<string, mixed>|null
     */
    public function resolve_canonical_stage(string $slug): ?array
    {
        if (! PipelineStage::is_canonical($slug)) {
            return null;
        }

        $stage = $this->workflow_stage_repository->find_by_slug($slug);

        if (null === $stage) {
            return null;
        }

        if (empty($stage['is_pipeline_stage'])) {
            return null;
        }

        return $stage;
    }

    /**
     * Default stage row for newly created referrals.
     *
     * @return array<string, mixed>|null
     */
    public function get_default_pipeline_stage(): ?array
    {
        return $this->resolve_canonical_stage(PipelineStage::default_slug());
    }

    public function is_referral_on_pipeline(array $referral): bool
    {
        $stage_id = absint($referral['workflow_stage_id'] ?? 0);
        if ($stage_id <= 0) {
            return false;
        }

        $stage = $this->workflow_stage_repository->find($stage_id);
        if (null === $stage) {
            return false;
        }

        $slug = (string) ($stage['slug'] ?? '');

        return PipelineStage::is_canonical($slug) && ! empty($stage['is_pipeline_stage']);
    }

    public function current_stage_slug(array $referral): ?string
    {
        $stage_id = absint($referral['workflow_stage_id'] ?? 0);
        if ($stage_id <= 0) {
            return null;
        }

        $stage = $this->workflow_stage_repository->find($stage_id);
        if (null === $stage) {
            return null;
        }

        $slug = (string) ($stage['slug'] ?? '');

        return '' !== $slug ? $slug : null;
    }

    /**
     * Panel data for referral view (admin/portal).
     *
     * @return array{
     *     is_pipeline: bool,
     *     is_legacy: bool,
     *     stage_id: int,
     *     stage_slug: string,
     *     stage_label: string,
     *     entered_at: string|null,
     *     waiting_label: string|null,
     *     next_action: string,
     *     owner_name: string,
     *     can_override: bool,
     *     override_options: array<int, array{id: int, slug: string, name: string}>
     * }
     */
    public function get_panel_data(array $referral): array
    {
        $stage_id   = absint($referral['workflow_stage_id'] ?? 0);
        $stage      = $stage_id > 0 ? $this->workflow_stage_repository->find($stage_id) : null;
        $slug       = is_array($stage) ? (string) ($stage['slug'] ?? '') : '';
        $is_pipeline = '' !== $slug
            && PipelineStage::is_canonical($slug)
            && ! empty($stage['is_pipeline_stage']);
        $entered_at = isset($referral['workflow_stage_entered_at']) && '' !== (string) $referral['workflow_stage_entered_at']
            ? (string) $referral['workflow_stage_entered_at']
            : null;

        $owner_id   = absint($referral['assigned_to'] ?? 0);
        $owner_name = $owner_id > 0 ? $this->user_provider->get_display_name($owner_id) : '';
        if ('' === $owner_name) {
            $owner_name = __('Unassigned', 'jm-referral-system');
        }

        $stage_label = '';
        if (is_array($stage)) {
            $stage_label = $is_pipeline
                ? PipelineStage::label($slug)
                : (string) ($stage['name'] ?? '');
        }

        return [
            'is_pipeline'      => $is_pipeline,
            'is_legacy'        => ! $is_pipeline && (null !== $stage || $stage_id > 0),
            'stage_id'         => $stage_id,
            'stage_slug'       => $slug,
            'stage_label'      => '' !== $stage_label ? $stage_label : __('Not set', 'jm-referral-system'),
            'entered_at'       => $entered_at,
            'waiting_label'    => $this->format_waiting_label($entered_at),
            'next_action'      => $is_pipeline ? PipelineStage::next_action($slug) : '',
            'owner_name'       => $owner_name,
            'can_override'     => Capabilities::current_user_can(Capabilities::OVERRIDE_PIPELINE_STAGE)
                && $this->access_policy->can_mutate_referral($referral),
            'override_options' => $this->get_override_options(),
        ];
    }

    /**
     * @return array<int, array{id: int, slug: string, name: string}>
     */
    public function get_override_options(): array
    {
        $options = [];

        foreach (PipelineStage::slugs() as $slug) {
            $stage = $this->resolve_canonical_stage($slug);
            if (null === $stage) {
                continue;
            }

            $options[] = [
                'id'   => (int) ($stage['id'] ?? 0),
                'slug' => $slug,
                'name' => PipelineStage::label($slug),
            ];
        }

        return $options;
    }

    /**
     * Initializes pipeline for a newly created referral (public/admin).
     *
     * Expects referral already inserted with interest_required stage + entered_at.
     *
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function record_pipeline_started(int $referral_id): array
    {
        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return ['ok' => false, 'error' => 'referral_not_found'];
        }

        $to_stage = $this->get_default_pipeline_stage();
        if (null === $to_stage) {
            return ['ok' => false, 'error' => 'default_stage_missing'];
        }

        $to_id   = absint($to_stage['id'] ?? 0);
        $to_slug = (string) ($to_stage['slug'] ?? '');
        $now     = (string) ($referral['workflow_stage_entered_at'] ?? '') !== ''
            ? (string) $referral['workflow_stage_entered_at']
            : current_time('mysql');

        $history_id = $this->history_repository->insert(
            [
                'referral_id'     => $referral_id,
                'from_stage_id'   => null,
                'from_stage_slug' => null,
                'to_stage_id'     => $to_id,
                'to_stage_slug'   => $to_slug,
                'changed_by'      => get_current_user_id() > 0 ? get_current_user_id() : null,
                'change_type'     => PipelineStage::CHANGE_CREATED,
                'reason'          => null,
                'created_at'      => $now,
            ]
        );

        if (false === $history_id) {
            return ['ok' => false, 'error' => 'history_failed'];
        }

        $this->activity_service->log_pipeline_started($referral_id, PipelineStage::label($to_slug));

        return ['ok' => true];
    }

    /**
     * Normal canonical transition (for later business actions).
     *
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function transition(
        int $referral_id,
        string $to_slug,
        ?string $reason = null,
        bool $manage_transaction = true,
        bool $log_activity = true
    ): array {
        return $this->apply_stage_change(
            $referral_id,
            $to_slug,
            PipelineStage::CHANGE_TRANSITION,
            $reason,
            false,
            $manage_transaction,
            $log_activity
        );
    }

    /**
     * Explicit Manager/Admin override into any canonical stage.
     *
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function override(int $referral_id, string $to_slug, string $reason): array
    {
        $reason = trim($reason);
        if ('' === $reason) {
            return ['ok' => false, 'error' => 'reason_required'];
        }

        if (! Capabilities::current_user_can(Capabilities::OVERRIDE_PIPELINE_STAGE)) {
            return ['ok' => false, 'error' => 'capability_denied'];
        }

        return $this->apply_stage_change(
            $referral_id,
            $to_slug,
            PipelineStage::CHANGE_OVERRIDE,
            $reason,
            true,
            true,
            true
        );
    }

    /**
     * @return array{ok: true, from_label?: string, to_label?: string}|array{ok: false, error: string}
     */
    private function apply_stage_change(
        int $referral_id,
        string $to_slug,
        string $change_type,
        ?string $reason,
        bool $is_override,
        bool $manage_transaction = true,
        bool $log_activity = true
    ): array {
        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return ['ok' => false, 'error' => 'referral_not_found'];
        }

        if (! $this->access_policy->can_mutate_referral($referral)) {
            return ['ok' => false, 'error' => 'access_denied'];
        }

        if (! PipelineStage::is_canonical($to_slug)) {
            return ['ok' => false, 'error' => 'invalid_target'];
        }

        $to_stage = $this->resolve_canonical_stage($to_slug);
        if (null === $to_stage) {
            return ['ok' => false, 'error' => 'target_stage_missing'];
        }

        $from_id   = absint($referral['workflow_stage_id'] ?? 0);
        $from_row  = $from_id > 0 ? $this->workflow_stage_repository->find($from_id) : null;
        $from_slug = is_array($from_row) ? (string) ($from_row['slug'] ?? '') : '';

        $to_id = absint($to_stage['id'] ?? 0);
        if ($to_id <= 0) {
            return ['ok' => false, 'error' => 'target_stage_missing'];
        }

        if ($from_id === $to_id) {
            return ['ok' => true];
        }

        $from_is_pipeline = '' !== $from_slug
            && PipelineStage::is_canonical($from_slug)
            && is_array($from_row)
            && ! empty($from_row['is_pipeline_stage']);

        if (! $is_override) {
            if (! $from_is_pipeline) {
                return ['ok' => false, 'error' => 'not_on_pipeline'];
            }

            if (PipelineStage::is_terminal($from_slug)) {
                return ['ok' => false, 'error' => 'terminal_stage'];
            }

            if (! PipelineStage::can_transition($from_slug, $to_slug)) {
                return ['ok' => false, 'error' => 'transition_forbidden'];
            }
        }

        $now = current_time('mysql');

        global $wpdb;
        if ($manage_transaction) {
            $wpdb->query('START TRANSACTION');
        }

        $updated = $this->referral_repository->update_pipeline_state(
            $referral_id,
            $to_id,
            $now,
            null
        );

        if (! $updated) {
            if ($manage_transaction) {
                $wpdb->query('ROLLBACK');
            }
            return ['ok' => false, 'error' => 'update_failed'];
        }

        $history_id = $this->history_repository->insert(
            [
                'referral_id'     => $referral_id,
                'from_stage_id'   => $from_id > 0 ? $from_id : null,
                'from_stage_slug' => '' !== $from_slug ? $from_slug : null,
                'to_stage_id'     => $to_id,
                'to_stage_slug'   => $to_slug,
                'changed_by'      => get_current_user_id() > 0 ? get_current_user_id() : null,
                'change_type'     => $change_type,
                'reason'          => null !== $reason && '' !== trim($reason) ? trim($reason) : null,
                'created_at'      => $now,
            ]
        );

        if (false === $history_id) {
            if ($manage_transaction) {
                $wpdb->query('ROLLBACK');
            }
            return ['ok' => false, 'error' => 'history_failed'];
        }

        if ($manage_transaction) {
            $wpdb->query('COMMIT');
        }

        $from_label = $from_is_pipeline
            ? PipelineStage::label($from_slug)
            : (is_array($from_row) ? (string) ($from_row['name'] ?? $from_slug) : __('(none)', 'jm-referral-system'));
        $to_label = PipelineStage::label($to_slug);

        if ($log_activity) {
            if ($is_override) {
                $this->activity_service->log_pipeline_stage_overridden($referral_id, $from_label, $to_label);
            } else {
                $this->activity_service->log_pipeline_stage_changed($referral_id, $from_label, $to_label);
            }
        }

        return [
            'ok'         => true,
            'from_label' => $from_label,
            'to_label'   => $to_label,
        ];
    }

    private function format_waiting_label(?string $entered_at): ?string
    {
        if (null === $entered_at || '' === $entered_at) {
            return null;
        }

        $entered_ts = strtotime($entered_at);
        if (false === $entered_ts) {
            return null;
        }

        $now_ts = (int) current_time('timestamp');
        $diff   = max(0, $now_ts - $entered_ts);

        if ($diff < HOUR_IN_SECONDS) {
            $minutes = max(1, (int) floor($diff / MINUTE_IN_SECONDS));

            return sprintf(
                /* translators: %d: minutes */
                _n('%d minute', '%d minutes', $minutes, 'jm-referral-system'),
                $minutes
            );
        }

        if ($diff < DAY_IN_SECONDS) {
            $hours = (int) floor($diff / HOUR_IN_SECONDS);

            return sprintf(
                /* translators: %d: hours */
                _n('%d hour', '%d hours', $hours, 'jm-referral-system'),
                $hours
            );
        }

        $days = (int) floor($diff / DAY_IN_SECONDS);

        return sprintf(
            /* translators: %d: days */
            _n('%d day', '%d days', $days, 'jm-referral-system'),
            $days
        );
    }
}
