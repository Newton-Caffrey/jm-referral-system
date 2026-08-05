<?php

namespace JMReferral\CarePlan;

use JMReferral\Database\Tables;

class ReferralCarePlanVersionRepository
{
    /**
     * @param array{
     *     care_plan_id: int,
     *     version_number: int,
     *     snapshot: string,
     *     created_by: int,
     *     change_summary: string|null,
     *     created_at: string
     * } $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::care_plan_versions_table(),
            [
                'care_plan_id'    => absint($data['care_plan_id']),
                'version_number'  => absint($data['version_number']),
                'snapshot'        => (string) $data['snapshot'],
                'created_by'      => absint($data['created_by']),
                'change_summary'  => $data['change_summary'],
                'created_at'      => (string) $data['created_at'],
            ],
            [
                '%d',
                '%d',
                '%s',
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
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = Tables::care_plan_versions_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, care_plan_id, version_number, snapshot, created_by, change_summary, created_at
                FROM {$table}
                WHERE id = %d
                LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Version list rows without snapshot payloads (snapshot loaded on dedicated view).
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_by_care_plan(int $care_plan_id, ?int $limit = null, bool $include_snapshot = true): array
    {
        global $wpdb;

        if ($care_plan_id <= 0) {
            return [];
        }

        $table   = Tables::care_plan_versions_table();
        $columns = $include_snapshot
            ? 'id, care_plan_id, version_number, snapshot, created_by, change_summary, created_at'
            : 'id, care_plan_id, version_number, created_by, change_summary, created_at';
        $sql     = "SELECT {$columns}
            FROM {$table}
            WHERE care_plan_id = %d
            ORDER BY version_number DESC, id DESC";
        $params  = [$care_plan_id];

        if (null !== $limit && $limit > 0) {
            $sql     .= ' LIMIT %d';
            $params[] = $limit;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- trusted fragments + prepared params.
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }

    public function count_by_care_plan(int $care_plan_id): int
    {
        global $wpdb;

        if ($care_plan_id <= 0) {
            return 0;
        }

        $table = Tables::care_plan_versions_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE care_plan_id = %d",
                $care_plan_id
            )
        );

        return (int) $count;
    }

    public function get_latest_version_number(int $care_plan_id): int
    {
        global $wpdb;

        if ($care_plan_id <= 0) {
            return 0;
        }

        $table = Tables::care_plan_versions_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $number = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(version_number) FROM {$table} WHERE care_plan_id = %d",
                $care_plan_id
            )
        );

        return (int) $number;
    }
}
