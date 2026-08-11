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

    /**
     * Distinct referral counts that have ever transitioned into the given stage slugs.
     * Uses stage history only — does not invent milestones for referrals without history.
     *
     * @param array<int, string> $to_stage_slugs
     * @return array<string, int> slug => distinct referral count
     */
    public function count_distinct_referrals_reached_slugs(
        array $to_stage_slugs,
        ?int $access_assigned_to = null
    ): array {
        global $wpdb;

        $to_stage_slugs = array_values(array_filter(array_map('strval', $to_stage_slugs), static function (string $slug): bool {
            return PipelineStage::is_canonical($slug);
        }));

        $out = [];
        foreach ($to_stage_slugs as $slug) {
            $out[$slug] = 0;
        }

        if ([] === $to_stage_slugs) {
            return $out;
        }

        $history   = Tables::referral_stage_history_table();
        $referrals = Tables::referrals_table();
        $placeholders = implode(',', array_fill(0, count($to_stage_slugs), '%s'));

        $sql = "SELECT h.to_stage_slug AS stage_slug, COUNT(DISTINCT h.referral_id) AS total
            FROM {$history} h
            INNER JOIN {$referrals} r ON r.id = h.referral_id
            WHERE h.to_stage_slug IN ({$placeholders})
              AND r.archived_at IS NULL";

        $params = $to_stage_slugs;

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $sql     .= ' AND r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql .= ' GROUP BY h.to_stage_slug';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        if (! is_array($rows)) {
            return $out;
        }

        foreach ($rows as $row) {
            $slug = (string) ($row['stage_slug'] ?? '');
            if (isset($out[$slug])) {
                $out[$slug] = (int) ($row['total'] ?? 0);
            }
        }

        return $out;
    }

    /**
     * Distinct referral IDs that have history into any of the given stage slugs.
     *
     * @param array<int, string> $to_stage_slugs
     * @return array<int, int> referral_id list
     */
    public function find_referral_ids_reached_slugs(
        array $to_stage_slugs,
        ?int $access_assigned_to = null,
        int $limit = 500
    ): array {
        global $wpdb;

        $to_stage_slugs = array_values(array_filter(array_map('strval', $to_stage_slugs), static function (string $slug): bool {
            return PipelineStage::is_canonical($slug);
        }));

        if ([] === $to_stage_slugs) {
            return [];
        }

        $limit     = max(1, min(1000, $limit));
        $history   = Tables::referral_stage_history_table();
        $referrals = Tables::referrals_table();
        $placeholders = implode(',', array_fill(0, count($to_stage_slugs), '%s'));

        $sql = "SELECT DISTINCT h.referral_id
            FROM {$history} h
            INNER JOIN {$referrals} r ON r.id = h.referral_id
            WHERE h.to_stage_slug IN ({$placeholders})
              AND r.archived_at IS NULL";

        $params = $to_stage_slugs;

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $sql     .= ' AND r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql     .= ' ORDER BY h.referral_id ASC LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_col($wpdb->prepare($sql, ...$params));

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map('absint', $rows)));
    }
}
