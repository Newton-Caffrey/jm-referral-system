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
     * Whether the user may record a commercial interest response (Express Interest).
     *
     * Allowed: JM Administrator, Referral Manager, Care Coordinator, WP admins.
     * Denied: Assessor, Support Worker (even with EDIT_REFERRALS).
     *
     * @param array<string, mixed> $referral
     */
    public function can_express_interest(array $referral, ?int $user_id = null): bool
    {
        if (! $this->can_mutate_referral($referral, $user_id)) {
            return false;
        }

        $user = $this->resolve_user($user_id);

        if (! $user instanceof \WP_User) {
            return false;
        }

        if (user_can($user, 'manage_options')
            || in_array('administrator', (array) $user->roles, true)
            || in_array(Roles::JM_ADMINISTRATOR, (array) $user->roles, true)
            || in_array(Roles::REFERRAL_MANAGER, (array) $user->roles, true)
            || in_array(Roles::CARE_COORDINATOR, (array) $user->roles, true)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Whether the user may prepare/send Package Cost (commercial action).
     *
     * Allowed: JM Administrator, Referral Manager, Care Coordinator, WP admins.
     * Denied: Assessor, Support Worker.
     * Does not require pipeline override.
     *
     * @param array<string, mixed> $referral
     */
    public function can_manage_package_cost(array $referral, ?int $user_id = null): bool
    {
        return $this->can_express_interest($referral, $user_id);
    }

    /**
     * Whether the user may record a Local Authority / funding decision.
     *
     * Same commercial gate as Express Interest / Package Cost.
     * Does not require pipeline override.
     *
     * @param array<string, mixed> $referral
     */
    public function can_record_la_decision(array $referral, ?int $user_id = null): bool
    {
        return $this->can_express_interest($referral, $user_id);
    }

    /**
     * Whether the user may mark a referral Not Proceeding (generic acquisition closure).
     *
     * Same commercial gate. Does not apply at awaiting_la_decision (use LA Decision).
     *
     * @param array<string, mixed> $referral
     */
    public function can_mark_not_proceeding(array $referral, ?int $user_id = null): bool
    {
        return $this->can_express_interest($referral, $user_id);
    }

    /**
     * Whether the user may confirm care commencement (acquisition terminal success).
     *
     * Same commercial gate as Express Interest / Package Cost / LA Decision.
     * Does not require pipeline override or occupancy manage capability.
     *
     * @param array<string, mixed> $referral
     */
    public function can_commence_care(array $referral, ?int $user_id = null): bool
    {
        return $this->can_express_interest($referral, $user_id);
    }

    /**
     * Whether the user may view referral meetings (Phase 4B.2 read UI).
     *
     * Requires referral visibility. Explicitly denies Support Workers even when
     * they can view an assigned referral. Assessor may view when they can view
     * the referral. Does not grant management or contact-PII access.
     * Does not change capability matrix grants.
     *
     * @param array<string, mixed> $referral
     */
    public function can_view_referral_meetings(array $referral, ?int $user_id = null): bool
    {
        if (! $this->can_view_referral($referral, $user_id)) {
            return false;
        }

        // Support Worker is the only assigned-scoped role; deny meeting access.
        if ($this->should_scope_to_assigned($user_id)) {
            return false;
        }

        return true;
    }

    /**
     * Whether the user may view sensitive meeting contact fields (Phase 4B.2.1A).
     *
     * Covers: external participant email/telephone and online meeting URL.
     * Requires referral visibility and the same commercial/management roles as
     * Express Interest (JM Admin, Referral Manager, Care Coordinator, WP admin).
     * Does **not** require can_mutate_referral — authorised managers may still
     * view contacts on archived referrals while writes remain blocked.
     * Denied: Assessor, Support Worker.
     * Does not grant meeting mutation. Does not bypass referral visibility.
     * Champion / transition lead / internal attendee status do not grant this.
     *
     * @param array<string, mixed> $referral
     */
    public function can_view_referral_meeting_contacts(array $referral, ?int $user_id = null): bool
    {
        if (! $this->can_view_referral_meetings($referral, $user_id)) {
            return false;
        }

        return $this->has_meeting_management_role($user_id);
    }

    /**
     * Meeting-management capability helper (Phase 4B foundation).
     *
     * Allowed when the user may express interest on the referral:
     * JM Administrator, Referral Manager, Care Coordinator, WordPress administrator.
     * Denied: Assessor, Support Worker.
     * Write/mutation gate for meetings (requires non-archived mutate path via express interest).
     * Does not control read-only contact visibility — use can_view_referral_meeting_contacts().
     * Does not bypass referral visibility (callers must still check can_view_referral).
     * Does not change existing capability grants. Does not advance workflow.
     *
     * @param array<string, mixed> $referral
     */
    public function can_manage_referral_meetings(array $referral, ?int $user_id = null): bool
    {
        return $this->can_express_interest($referral, $user_id);
    }

    /**
     * Commercial / meeting-management roles without the archived-mutate gate.
     */
    private function has_meeting_management_role(?int $user_id = null): bool
    {
        $user = $this->resolve_user($user_id);

        if (! $user instanceof \WP_User) {
            return false;
        }

        if (user_can($user, 'manage_options')
            || in_array('administrator', (array) $user->roles, true)
            || in_array(Roles::JM_ADMINISTRATOR, (array) $user->roles, true)
            || in_array(Roles::REFERRAL_MANAGER, (array) $user->roles, true)
            || in_array(Roles::CARE_COORDINATOR, (array) $user->roles, true)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Responsibility-assignment capability helper (Phase 4B foundation / 4C.1 UI).
     *
     * Assign/clear assigned_to (owner), champion_user_id, transition_lead_user_id.
     * Same allow/deny roles as meeting-management capability.
     * Champion / transition lead do not alter AccessPolicy visibility scoping.
     *
     * @param array<string, mixed> $referral
     */
    public function can_assign_referral_responsibilities(array $referral, ?int $user_id = null): bool
    {
        return $this->can_express_interest($referral, $user_id);
    }

    /**
     * Whether the user may schedule / reschedule assessment appointments.
     *
     * Allowed when they can mutate the referral (EDIT_REFERRALS + AccessPolicy),
     * including Assessor. Support Worker lacks EDIT_REFERRALS and is denied.
     * Does not grant pipeline override.
     *
     * @param array<string, mixed> $referral
     */
    public function can_schedule_assessment(array $referral, ?int $user_id = null): bool
    {
        return $this->can_mutate_referral($referral, $user_id);
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
