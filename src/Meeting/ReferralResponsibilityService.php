<?php

namespace JMReferral\Meeting;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;

/**
 * Referral owner, champion and transition-lead responsibilities (Phase 4B.1 / 4C.1).
 *
 * Owner maps to assigned_to. Champion / transition lead do not grant access.
 * Does not send email or change workflow_stage_id.
 */
class ReferralResponsibilityService
{
    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider
    ) {
    }

    /**
     * @return array{
     *     assigned_to: int|null,
     *     champion_user_id: int|null,
     *     transition_lead_user_id: int|null
     * }|null
     */
    public function get_for_referral(int $referral_id): ?array
    {
        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return null;
        }

        return $this->normalize_ids($referral);
    }

    /**
     * Atomic update of owner / champion / transition lead from allowlisted IDs.
     * No-op when all values unchanged. Logs only fields that actually change.
     * Does not notify by email.
     *
     * @param array{
     *     assigned_to?: int|null,
     *     champion_user_id?: int|null,
     *     transition_lead_user_id?: int|null
     * } $input
     * @return array{ok: true, changed: bool}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function update_responsibilities(int $referral_id, array $input, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $referral      = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return ['ok' => false, 'error' => 'referral_not_found'];
        }

        if (null !== ($referral['archived_at'] ?? null) && '' !== (string) $referral['archived_at']) {
            return ['ok' => false, 'error' => 'archived'];
        }

        if (! $this->access_policy->can_assign_referral_responsibilities($referral, $actor_user_id)) {
            return ['ok' => false, 'error' => 'forbidden'];
        }

        $current = $this->normalize_ids($referral);
        $fields  = ['assigned_to', 'champion_user_id', 'transition_lead_user_id'];
        $next    = $current;
        $errors  = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }
            $parsed = $this->parse_user_id($input[$field]);
            if (false === $parsed) {
                $errors[$field] = __('Please select a valid staff member.', 'jm-referral-system');
                continue;
            }
            $next[$field] = $parsed;
        }

        if ([] !== $errors) {
            return ['ok' => false, 'error' => 'validation', 'field_errors' => $errors];
        }

        $payload = [];
        $changes = [];
        foreach ($fields as $field) {
            $old = $current[$field];
            $new = $next[$field];
            if ($old === $new) {
                continue;
            }
            $payload[$field] = $new;
            $changes[$field] = ['from' => $old ?? 0, 'to' => $new ?? 0];
        }

        if ([] === $payload) {
            return ['ok' => true, 'changed' => false];
        }

        if (! $this->referral_repository->update_responsibility_fields($referral_id, $payload)) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        foreach ($changes as $field => $change) {
            $this->log_change($referral_id, $field, (int) $change['from'], (int) $change['to']);
        }

        return ['ok' => true, 'changed' => true];
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function assign_champion(int $referral_id, int $user_id, ?int $actor_user_id = null): array
    {
        return $this->set_responsibility($referral_id, 'champion_user_id', $user_id, $actor_user_id);
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function clear_champion(int $referral_id, ?int $actor_user_id = null): array
    {
        return $this->set_responsibility($referral_id, 'champion_user_id', null, $actor_user_id);
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function assign_transition_lead(int $referral_id, int $user_id, ?int $actor_user_id = null): array
    {
        return $this->set_responsibility($referral_id, 'transition_lead_user_id', $user_id, $actor_user_id);
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function clear_transition_lead(int $referral_id, ?int $actor_user_id = null): array
    {
        return $this->set_responsibility($referral_id, 'transition_lead_user_id', null, $actor_user_id);
    }

    /**
     * @param 'assigned_to'|'champion_user_id'|'transition_lead_user_id' $field
     * @return array{ok: true}|array{ok: false, error: string}
     */
    private function set_responsibility(
        int $referral_id,
        string $field,
        ?int $user_id,
        ?int $actor_user_id
    ): array {
        $result = $this->update_responsibilities($referral_id, [$field => $user_id], $actor_user_id);
        if (! empty($result['ok'])) {
            return ['ok' => true];
        }

        return [
            'ok'    => false,
            'error' => (string) ($result['error'] ?? 'persist_failed'),
        ];
    }

    /**
     * @param array<string, mixed> $referral
     * @return array{
     *     assigned_to: int|null,
     *     champion_user_id: int|null,
     *     transition_lead_user_id: int|null
     * }
     */
    private function normalize_ids(array $referral): array
    {
        $owner    = absint($referral['assigned_to'] ?? 0);
        $champion = absint($referral['champion_user_id'] ?? 0);
        $lead     = absint($referral['transition_lead_user_id'] ?? 0);

        return [
            'assigned_to'             => $owner > 0 ? $owner : null,
            'champion_user_id'        => $champion > 0 ? $champion : null,
            'transition_lead_user_id' => $lead > 0 ? $lead : null,
        ];
    }

    /**
     * @param mixed $value
     * @return int|null|false null = unassigned, false = invalid
     */
    private function parse_user_id($value)
    {
        if (null === $value || '' === $value || 0 === $value || '0' === $value) {
            return null;
        }

        $user_id = absint($value);
        if ($user_id <= 0 || ! $this->user_provider->is_assignable($user_id)) {
            return false;
        }

        return $user_id;
    }

    /**
     * @param 'assigned_to'|'champion_user_id'|'transition_lead_user_id' $field
     */
    private function log_change(int $referral_id, string $field, int $previous, int $next): void
    {
        $unassigned = __('Unassigned', 'jm-referral-system');
        $prev_name  = $previous > 0
            ? ($this->user_provider->get_display_name($previous) ?: __('Unknown', 'jm-referral-system'))
            : $unassigned;
        $next_name = $next > 0
            ? ($this->user_provider->get_display_name($next) ?: __('Unknown', 'jm-referral-system'))
            : $unassigned;

        if ('assigned_to' === $field) {
            if ($previous <= 0 && $next > 0) {
                $this->activity_service->log_assigned($referral_id, $next_name);

                return;
            }
            $this->activity_service->log_reassigned($referral_id, $prev_name, $next_name);

            return;
        }

        $is_champion = 'champion_user_id' === $field;

        if ($next <= 0) {
            if ($is_champion) {
                $this->activity_service->log_champion_unassigned($referral_id);
            } else {
                $this->activity_service->log_transition_lead_unassigned($referral_id);
            }

            return;
        }

        if ($previous <= 0) {
            if ($is_champion) {
                $this->activity_service->log_champion_assigned($referral_id, $next_name);
            } else {
                $this->activity_service->log_transition_lead_assigned($referral_id, $next_name);
            }

            return;
        }

        if ($is_champion) {
            $this->activity_service->log_champion_reassigned($referral_id, $prev_name, $next_name);
        } else {
            $this->activity_service->log_transition_lead_reassigned($referral_id, $prev_name, $next_name);
        }
    }
}
