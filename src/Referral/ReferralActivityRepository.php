<?php

namespace JMReferral\Referral;

use JMReferral\Database\Tables;

class ReferralActivityRepository
{
    /**
     * Inserts a referral activity record.
     *
     * @param array{
     *     referral_id: int,
     *     user_id: int,
     *     action: string,
     *     description: string
     * } $data Activity payload.
     * @return int|false Inserted row ID on success, false on failure.
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::referral_activity_table(),
            [
                'referral_id' => absint($data['referral_id']),
                'user_id'     => absint($data['user_id']),
                'action'      => $data['action'],
                'description' => $data['description'],
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
            ]
        );

        if (false === $result) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Returns activity records for a referral, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_by_referral_id(int $referral_id): array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return [];
        }

        $table = Tables::referral_activity_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, referral_id, user_id, action, description, created_at
                FROM {$table}
                WHERE referral_id = %d
                ORDER BY created_at DESC, id DESC",
                $referral_id
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }
}
