<?php

namespace JMReferral\Visits;

use JMReferral\Database\Tables;

class VisitTaskRepository
{
    private const SELECT_COLUMNS = 'id, visit_id, task_name, task_status, task_notes, display_order, created_at, updated_at';

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $row = [
            'visit_id'      => absint($data['visit_id'] ?? 0),
            'task_name'     => (string) ($data['task_name'] ?? ''),
            'task_status'   => (string) ($data['task_status'] ?? 'pending'),
            'task_notes'    => $data['task_notes'] ?? null,
            'display_order' => absint($data['display_order'] ?? 0),
            'created_at'    => (string) ($data['created_at'] ?? ''),
            'updated_at'    => (string) ($data['updated_at'] ?? ''),
        ];

        if (null !== $row['task_notes'] && '' === trim((string) $row['task_notes'])) {
            $row['task_notes'] = null;
        }

        $result = $wpdb->insert(
            Tables::visit_tasks_table(),
            $row,
            ['%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if (false === $result) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $row     = [];
        $formats = [];

        if (array_key_exists('task_status', $data)) {
            $row['task_status'] = (string) $data['task_status'];
            $formats[]          = '%s';
        }

        if (array_key_exists('task_notes', $data)) {
            $notes = $data['task_notes'];
            $row['task_notes'] = (null === $notes || '' === trim((string) $notes))
                ? null
                : (string) $notes;
            $formats[] = '%s';
        }

        if (array_key_exists('updated_at', $data)) {
            $row['updated_at'] = (string) $data['updated_at'];
            $formats[]         = '%s';
        }

        if ([] === $row) {
            return false;
        }

        $result = $wpdb->update(
            Tables::visit_tasks_table(),
            $row,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return false !== $result;
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

        $table   = Tables::visit_tasks_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$columns}
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
     * @return array<int, array<string, mixed>>
     */
    public function get_by_visit(int $visit_id): array
    {
        global $wpdb;

        if ($visit_id <= 0) {
            return [];
        }

        $table   = Tables::visit_tasks_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE visit_id = %d
                ORDER BY display_order ASC, id ASC",
                $visit_id
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function exists_for_visit_with_name(int $visit_id, string $task_name): bool
    {
        global $wpdb;

        if ($visit_id <= 0 || '' === trim($task_name)) {
            return false;
        }

        $table = Tables::visit_tasks_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE visit_id = %d AND task_name = %s",
                $visit_id,
                $task_name
            )
        );

        return (int) $count > 0;
    }

    /**
     * Outstanding task counts grouped by task name (managers).
     *
     * @return array<int, array{task_name: string, count: int}>
     */
    public function count_outstanding_by_task_name(int $limit = 10): array
    {
        global $wpdb;

        $limit = max(1, min(50, $limit));
        $table = Tables::visit_tasks_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT task_name, COUNT(*) AS count
                FROM {$table}
                WHERE task_status IN ('pending', 'not_completed')
                GROUP BY task_name
                ORDER BY count DESC, task_name ASC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        if (! is_array($results)) {
            return [];
        }

        $rows = [];
        foreach ($results as $row) {
            $rows[] = [
                'task_name' => (string) ($row['task_name'] ?? ''),
                'count'     => absint($row['count'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Outstanding tasks for a staff member's visits today.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_outstanding_today_for_user(int $user_id, int $limit = 10): array
    {
        global $wpdb;

        if ($user_id <= 0) {
            return [];
        }

        $limit       = max(1, min(100, $limit));
        $tasks_table = Tables::visit_tasks_table();
        $visits_table = Tables::care_visits_table();
        $today       = current_time('Y-m-d');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.id, t.visit_id, t.task_name, t.task_status, t.task_notes, t.display_order,
                        v.visit_date, v.referral_id, v.assigned_user_id
                FROM {$tasks_table} t
                INNER JOIN {$visits_table} v ON v.id = t.visit_id
                WHERE v.assigned_user_id = %d
                  AND v.visit_date = %s
                  AND t.task_status IN ('pending', 'not_completed')
                ORDER BY t.display_order ASC, t.id ASC
                LIMIT %d",
                $user_id,
                $today,
                $limit
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Visits that have refused or not_completed care tasks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_visits_with_task_exceptions(int $limit = 100, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $limit       = max(1, min(500, $limit));
        $tasks_table = Tables::visit_tasks_table();
        $visits      = Tables::care_visits_table();
        $referrals   = Tables::referrals_table();
        $where       = [
            "t.task_status IN ('refused', 'not_completed')",
        ];
        $params = [];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT v.id AS visit_id, v.referral_id, v.visit_date, v.start_time, v.visit_status,
                r.referral_number, r.client_name, r.assigned_to,
                COUNT(t.id) AS exception_count
            FROM {$tasks_table} t
            INNER JOIN {$visits} v ON v.id = t.visit_id
            INNER JOIN {$referrals} r ON r.id = v.referral_id
            WHERE " . implode(' AND ', $where) . '
            GROUP BY v.id, v.referral_id, v.visit_date, v.start_time, v.visit_status,
                     r.referral_number, r.client_name, r.assigned_to
            ORDER BY v.visit_date DESC, v.id DESC
            LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/where fragments are trusted; values are prepared.
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }
}
