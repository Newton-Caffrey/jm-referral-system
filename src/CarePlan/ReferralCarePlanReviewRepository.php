<?php

namespace JMReferral\CarePlan;

use JMReferral\Database\Tables;

class ReferralCarePlanReviewRepository
{
    /**
     * @param array{
     *     care_plan_id: int,
     *     reviewed_by: int,
     *     review_date: string,
     *     outcome: string,
     *     notes: string|null,
     *     next_review_date: string|null,
     *     created_at: string
     * } $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::care_plan_reviews_table(),
            [
                'care_plan_id'     => absint($data['care_plan_id']),
                'reviewed_by'      => absint($data['reviewed_by']),
                'review_date'      => (string) $data['review_date'],
                'outcome'          => (string) $data['outcome'],
                'notes'            => $data['notes'],
                'next_review_date' => $data['next_review_date'],
                'created_at'       => (string) $data['created_at'],
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
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
    public function get_by_care_plan(int $care_plan_id, ?int $limit = null): array
    {
        global $wpdb;

        if ($care_plan_id <= 0) {
            return [];
        }

        $table  = Tables::care_plan_reviews_table();
        $sql    = "SELECT id, care_plan_id, reviewed_by, review_date, outcome, notes, next_review_date, created_at
            FROM {$table}
            WHERE care_plan_id = %d
            ORDER BY review_date DESC, id DESC";
        $params = [$care_plan_id];

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

        $table = Tables::care_plan_reviews_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE care_plan_id = %d",
                $care_plan_id
            )
        );

        return (int) $count;
    }
}
