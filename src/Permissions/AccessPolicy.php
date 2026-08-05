<?php

namespace JMReferral\Permissions;

class AccessPolicy
{
    /**
     * Whether the user may view the given referral record.
     *
     * @param array<string, mixed> $referral
     */
    public function can_view_referral(array $referral, ?int $user_id = null): bool
    {
        $user = $this->resolve_user($user_id);

        if (! $user instanceof \WP_User) {
            return false;
        }

        if (! user_can($user, Capabilities::VIEW_REFERRALS)) {
            return false;
        }

        if (! $this->should_scope_to_assigned((int) $user->ID)) {
            return true;
        }

        return absint($referral['assigned_to'] ?? 0) === (int) $user->ID;
    }

    /**
     * Whether the user may edit the given referral record.
     *
     * @param array<string, mixed> $referral
     */
    public function can_edit_referral(array $referral, ?int $user_id = null): bool
    {
        $user = $this->resolve_user($user_id);

        if (! $user instanceof \WP_User) {
            return false;
        }

        if (! user_can($user, Capabilities::EDIT_REFERRALS)) {
            return false;
        }

        if (! $this->should_scope_to_assigned((int) $user->ID)) {
            return true;
        }

        return absint($referral['assigned_to'] ?? 0) === (int) $user->ID;
    }

    /**
     * Whether the referral is archived (soft-retained).
     *
     * @param array<string, mixed> $referral
     */
    public function is_referral_archived(array $referral): bool
    {
        $archived_at = $referral['archived_at'] ?? null;

        return null !== $archived_at && '' !== (string) $archived_at;
    }

    /**
     * Whether the user may mutate clinical/operational data on the referral.
     *
     * Archived referrals are read-only aside from restore/archive flows.
     *
     * @param array<string, mixed> $referral
     */
    public function can_mutate_referral(array $referral, ?int $user_id = null): bool
    {
        if ($this->is_referral_archived($referral)) {
            return false;
        }

        return $this->can_edit_referral($referral, $user_id);
    }

    /**
     * Whether list/query results should be limited to the user's assigned referrals.
     */
    public function should_scope_to_assigned(?int $user_id = null): bool
    {
        $user = $this->resolve_user($user_id);

        if (! $user instanceof \WP_User) {
            return false;
        }

        if ($this->has_unrestricted_referral_access($user)) {
            return false;
        }

        return in_array(Roles::SUPPORT_WORKER, (array) $user->roles, true);
    }

    /**
     * Returns the assigned-user ID to constrain queries with, or null when unscoped.
     */
    public function get_assigned_user_constraint(?int $user_id = null): ?int
    {
        $user = $this->resolve_user($user_id);

        if (! $user instanceof \WP_User) {
            return null;
        }

        if (! $this->should_scope_to_assigned((int) $user->ID)) {
            return null;
        }

        $id = (int) $user->ID;

        return $id > 0 ? $id : null;
    }

    /**
     * Roles/users that may access every referral record.
     */
    private function has_unrestricted_referral_access(\WP_User $user): bool
    {
        if (user_can($user, 'manage_options')) {
            return true;
        }

        if (in_array('administrator', (array) $user->roles, true)) {
            return true;
        }

        $unrestricted_roles = [
            Roles::JM_ADMINISTRATOR,
            Roles::REFERRAL_MANAGER,
            Roles::CARE_COORDINATOR,
            Roles::ASSESSOR,
        ];

        foreach ($unrestricted_roles as $role) {
            if (in_array($role, (array) $user->roles, true)) {
                return true;
            }
        }

        return false;
    }

    private function resolve_user(?int $user_id): ?\WP_User
    {
        $id = null === $user_id ? get_current_user_id() : $user_id;

        if ($id <= 0) {
            return null;
        }

        $user = get_userdata($id);

        return $user instanceof \WP_User ? $user : null;
    }
}
