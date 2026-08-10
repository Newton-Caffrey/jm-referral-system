<?php

namespace JMReferral\Users;

use JMReferral\Permissions\Capabilities;

class UserProvider
{
    /**
     * Returns WordPress users who can access the referral system.
     *
     * @return array<int, array{id: int, display_name: string}>
     */
    public function get_assignable_users(): array
    {
        $users = get_users(
            [
                'capability' => Capabilities::VIEW_REFERRALS,
                'orderby'    => 'display_name',
                'order'      => 'ASC',
                'fields'     => ['ID', 'display_name'],
            ]
        );

        $assignable = [];

        foreach ($users as $user) {
            $assignable[] = [
                'id'           => (int) $user->ID,
                'display_name' => (string) $user->display_name,
            ];
        }

        return $assignable;
    }

    /**
     * Whether the given user can be assigned referrals.
     */
    public function is_assignable(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $user = get_userdata($user_id);

        if (! $user instanceof \WP_User) {
            return false;
        }

        return user_can($user, Capabilities::VIEW_REFERRALS);
    }

    /**
     * Staff eligible to perform clinical assessments (EDIT_REFERRALS).
     *
     * @return array<int, array{id: int, display_name: string}>
     */
    public function get_assessment_eligible_users(): array
    {
        $users = get_users(
            [
                'capability' => Capabilities::EDIT_REFERRALS,
                'orderby'    => 'display_name',
                'order'      => 'ASC',
                'fields'     => ['ID', 'display_name'],
            ]
        );

        $eligible = [];

        foreach ($users as $user) {
            $eligible[] = [
                'id'           => (int) $user->ID,
                'display_name' => (string) $user->display_name,
            ];
        }

        return $eligible;
    }

    /**
     * Whether the given user may be selected as assessment assessor.
     */
    public function is_assessment_eligible(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $user = get_userdata($user_id);

        if (! $user instanceof \WP_User) {
            return false;
        }

        return user_can($user, Capabilities::EDIT_REFERRALS);
    }

    /**
     * Returns a user's display name, or empty string when not found.
     */
    public function get_display_name(int $user_id): string
    {
        if ($user_id <= 0) {
            return '';
        }

        $user = get_userdata($user_id);

        if (! $user instanceof \WP_User) {
            return '';
        }

        return (string) $user->display_name;
    }

    /**
     * Batch-resolves display names for user IDs (single get_users call).
     *
     * @param array<int, int> $user_ids
     * @return array<int, string> Map of user_id => display_name
     */
    public function get_display_names_by_ids(array $user_ids): array
    {
        $ids = [];
        foreach ($user_ids as $user_id) {
            $id = absint($user_id);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if ([] === $ids) {
            return [];
        }

        $users = get_users(
            [
                'include' => array_values($ids),
                'fields'  => ['ID', 'display_name'],
            ]
        );

        $map = [];
        foreach ($users as $user) {
            $map[(int) $user->ID] = (string) $user->display_name;
        }

        return $map;
    }

    /**
     * Returns a user's email address, or empty string when not found.
     */
    public function get_email(int $user_id): string
    {
        if ($user_id <= 0) {
            return '';
        }

        $user = get_userdata($user_id);

        if (! $user instanceof \WP_User) {
            return '';
        }

        $email = sanitize_email((string) $user->user_email);

        return is_email($email) ? $email : '';
    }
}
