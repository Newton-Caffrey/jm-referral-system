<?php

namespace JMReferral\Pipeline;

use JMReferral\Database\Tables;

class ReferralStageHistoryRepository
{
    /**
     * @param array{
     *     referral_id: int,
     *     from_stage_id: int|null,
     *     from_stage_slug: string|null,
     *     to_stage_id: int,
     *     to_stage_slug: string,
     *     changed_by: int|null,
     *     change_type: string,
     *     reason: string|null,
     *     created_at: string
     * } $data
     * @return int|false
     */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::referral_stage_history_table(),
            [
                'referral_id'     => absint($data['referral_id']),
                'from_stage_id'   => null !== $data['from_stage_id'] ? absint($data['from_stage_id']) : null,
                'from_stage_slug' => $data['from_stage_slug'],
                'to_stage_id'     => absint($data['to_stage_id']),
                'to_stage_slug'   => (string) $data['to_stage_slug'],
                'changed_by'      => null !== $data['changed_by'] ? absint($data['changed_by']) : null,
                'change_type'     => (string) $data['change_type'],
                'reason'          => $data['reason'],
                'created_at'      => (string) $data['created_at'],
            ],
            [
                '%d',
                '%d',
                '%s',
                '%d',
                '%s',
                '%d',
                '%s',
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
     * @return array<int, array<string, mixed>>
     */
    public function get_by_referral_id(int $referral_id, ?int $limit = null): array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return [];
        }

        $table = Tables::referral_stage_history_table();

        if (null !== $limit && $limit > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, referral_id, from_stage_id, from_stage_slug, to_stage_id, to_stage_slug,
                        changed_by, change_type, reason, created_at
                    FROM {$table}
                    WHERE referral_id = %d
                    ORDER BY created_at DESC, id DESC
                    LIMIT %d",
                    $referral_id,
                    $limit
                ),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, referral_id, from_stage_id, from_stage_slug, to_stage_id, to_stage_slug,
                        changed_by, change_type, reason, created_at
                    FROM {$table}
                    WHERE referral_id = %d
                    ORDER BY created_at DESC, id DESC",
                    $referral_id
                ),
                ARRAY_A
            );
        }

        return is_array($results) ? $results : [];
    }

    /**
     * Latest history row transitioning to a given stage slug.
     *
     * @return array<string, mixed>|null
     */
    public function find_latest_to_stage(int $referral_id, string $to_stage_slug): ?array
    {
        global $wpdb;

        if ($referral_id <= 0 || '' === $to_stage_slug) {
            return null;
        }

        $table = Tables::referral_stage_history_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, referral_id, from_stage_id, from_stage_slug, to_stage_id, to_stage_slug,
                    changed_by, change_type, reason, created_at
                FROM {$table}
                WHERE referral_id = %d AND to_stage_slug = %s
                ORDER BY created_at DESC, id DESC
                LIMIT 1",
                $referral_id,
                $to_stage_slug
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }
}
