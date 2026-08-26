<?php

namespace JMReferral\Meeting;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;

/**
 * Champion and transition-lead responsibility assignment (Phase 4B.1).
 * Does not change assigned_to ownership or AccessPolicy scoping.
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
     * @return array{champion_user_id: int|null, transition_lead_user_id: int|null}|null
     */
    public function get_for_referral(int $referral_id): ?array
    {
        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return null;
        }

        $champion = absint($referral['champion_user_id'] ?? 0);
        $lead     = absint($referral['transition_lead_user_id'] ?? 0);

        return [
            'champion_user_id'        => $champion > 0 ? $champion : null,
            'transition_lead_user_id' => $lead > 0 ? $lead : null,
        ];
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
     * @param 'champion_user_id'|'transition_lead_user_id' $field
     * @return array{ok: true}|array{ok: false, error: string}
     */
    private function set_responsibility(
        int $referral_id,
        string $field,
        ?int $user_id,
        ?int $actor_user_id
    ): array {
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

        if (null !== $user_id) {
            $user_id = absint($user_id);
            if ($user_id <= 0 || ! $this->user_provider->is_assignable($user_id)) {
                return ['ok' => false, 'error' => 'invalid_user'];
            }
        }

        $previous = absint($referral[$field] ?? 0);
        $next     = null === $user_id ? 0 : $user_id;

        if ($previous === $next) {
            return ['ok' => true];
        }

        if (! $this->referral_repository->update_responsibility_fields($referral_id, [
            $field => null === $user_id ? null : $next,
        ])) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->log_change($referral_id, $field, $previous, $next);

        return ['ok' => true];
    }

    private function log_change(int $referral_id, string $field, int $previous, int $next): void
    {
        $is_champion = 'champion_user_id' === $field;
        $prev_name   = $previous > 0
            ? ($this->user_provider->get_display_name($previous) ?: __('Unknown', 'jm-referral-system'))
            : '';
        $next_name = $next > 0
            ? ($this->user_provider->get_display_name($next) ?: __('Unknown', 'jm-referral-system'))
            : '';

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
