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

    /**
     * Tasks for many visits, grouped by visit_id.
     *
     * @param array<int, int> $visit_ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function get_by_visit_ids(array $visit_ids): array
    {
        global $wpdb;

        $ids = [];
        foreach ($visit_ids as $visit_id) {
            $visit_id = absint($visit_id);
            if ($visit_id > 0) {
                $ids[$visit_id] = $visit_id;
            }
        }

        if ([] === $ids) {
            return [];
        }

        $table   = Tables::visit_tasks_table();
        $columns = self::SELECT_COLUMNS;
        $grouped = [];
        foreach ($ids as $id) {
            $grouped[$id] = [];
        }

        foreach (array_chunk(array_values($ids), 200) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$columns}
                    FROM {$table}
                    WHERE visit_id IN ({$placeholders})
                    ORDER BY visit_id ASC, display_order ASC, id ASC",
                    ...$chunk
                ),
                ARRAY_A
            );

            if (! is_array($results)) {
                continue;
            }

            foreach ($results as $row) {
                $visit_id = absint($row['visit_id'] ?? 0);
                if ($visit_id > 0) {
                    $grouped[$visit_id][] = $row;
                }
            }
        }

        return $grouped;
    }

    /**
     * Existing task names keyed by visit_id (for batch generation dedupe).
     *
     * @param array<int, int> $visit_ids
     * @return array<int, array<string, true>>
     */
    public function get_existing_task_names_by_visit_ids(array $visit_ids): array
    {
        $grouped = $this->get_by_visit_ids($visit_ids);
        $names   = [];

        foreach ($grouped as $visit_id => $tasks) {
            $names[(int) $visit_id] = [];
            foreach ($tasks as $task) {
                $name = trim((string) ($task['task_name'] ?? ''));
                if ('' !== $name) {
                    $names[(int) $visit_id][$name] = true;
                }
            }
        }

        return $names;
    }

    /**
     * Inserts visit-task rows in chunks.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return int Number of rows inserted
     */
    public function insert_batch(array $rows): int
    {
        global $wpdb;

        if ([] === $rows) {
            return 0;
        }

        $table   = Tables::visit_tasks_table();
        $created = 0;

        foreach (array_chunk($rows, 200) as $chunk) {
            $value_parts = [];

            foreach ($chunk as $data) {
                $visit_id  = absint($data['visit_id'] ?? 0);
                $task_name = (string) ($data['task_name'] ?? '');
                if ($visit_id <= 0 || '' === trim($task_name)) {
                    continue;
                }

                $notes = $data['task_notes'] ?? null;
                if (null !== $notes && '' === trim((string) $notes)) {
                    $notes = null;
                }

                $value_parts[] = sprintf(
                    '(%d,%s,%s,%s,%d,%s,%s)',
                    $visit_id,
                    $wpdb->prepare('%s', $task_name),
                    $wpdb->prepare('%s', (string) ($data['task_status'] ?? 'pending')),
                    null === $notes ? 'NULL' : $wpdb->prepare('%s', (string) $notes),
                    absint($data['display_order'] ?? 0),
                    $wpdb->prepare('%s', (string) ($data['created_at'] ?? current_time('mysql'))),
                    $wpdb->prepare('%s', (string) ($data['updated_at'] ?? current_time('mysql')))
                );
            }

            if ([] === $value_parts) {
                continue;
            }

            $sql = "INSERT INTO {$table}
                (visit_id, task_name, task_status, task_notes, display_order, created_at, updated_at)
                VALUES " . implode(',', $value_parts);

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- values escaped via $wpdb->prepare fragments.
            $result = $wpdb->query($sql);
            if (false !== $result) {
                $created += (int) $result;
            }
        }

        return $created;
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
        $referrals   = Tables::referrals_table();
        $today       = current_time('Y-m-d');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.id, t.visit_id, t.task_name, t.task_status, t.task_notes, t.display_order,
                        v.visit_date, v.referral_id, v.assigned_user_id, r.client_name AS client_name
                FROM {$tasks_table} t
                INNER JOIN {$visits_table} v ON v.id = t.visit_id
                INNER JOIN {$referrals} r ON r.id = v.referral_id
                WHERE v.assigned_user_id = %d
                  AND v.visit_date = %s
                  AND t.task_status IN ('pending', 'not_completed')
                  AND r.archived_at IS NULL
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
            'r.archived_at IS NULL',
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
