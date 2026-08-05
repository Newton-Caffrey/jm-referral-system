<?php

namespace JMReferral\Referral;

use JMReferral\Database\Tables;

class ReferralNoteRepository
{
    /**
     * Inserts an internal staff note.
     *
     * @param array{
     *     referral_id: int,
     *     user_id: int,
     *     note: string
     * } $data Note payload.
     * @return int|false Inserted row ID on success, false on failure.
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::referral_notes_table(),
            [
                'referral_id' => absint($data['referral_id']),
                'user_id'     => absint($data['user_id']),
                'note'        => $data['note'],
            ],
            [
                '%d',
                '%d',
                '%s',
            ]
        );

        if (false === $result) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Returns notes for a referral, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_by_referral_id(int $referral_id, ?int $limit = null): array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return [];
        }

        $table  = Tables::referral_notes_table();
        $sql    = "SELECT id, referral_id, user_id, note, created_at
            FROM {$table}
            WHERE referral_id = %d
            ORDER BY created_at DESC, id DESC";
        $params = [$referral_id];

        if (null !== $limit && $limit > 0) {
            $sql     .= ' LIMIT %d';
            $params[] = $limit;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- trusted fragments + prepared params.
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Counts notes for a referral.
     */
    public function count_by_referral_id(int $referral_id): int
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return 0;
        }

        $table = Tables::referral_notes_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE referral_id = %d",
                $referral_id
            )
        );

        return (int) $count;
    }
}
