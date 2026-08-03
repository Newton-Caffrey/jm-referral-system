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
