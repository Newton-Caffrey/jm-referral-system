<?php

namespace JMReferral\Visits;

use JMReferral\Database\Tables;

class CareVisitRepository
{
    private const SELECT_COLUMNS = 'id, referral_id, care_plan_id, assigned_user_id, visit_date, start_time, end_time, visit_status, visit_type, tasks, notes, completed_at, created_by, created_at, updated_at';

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $row     = $this->map_row($data, true);
        $formats = $this->formats_for_row($row);

        $result = $wpdb->insert(
            Tables::care_visits_table(),
            $row,
            $formats
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

        $row     = $this->map_row($data, false);
        $formats = $this->formats_for_row($row);

        $result = $wpdb->update(
            Tables::care_visits_table(),
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

        $table   = Tables::care_visits_table();
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
     * Visits for a referral, upcoming/newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_by_referral(int $referral_id, ?int $assigned_user_id = null): array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return [];
        }

        $table   = Tables::care_visits_table();
        $columns = self::SELECT_COLUMNS;

        if (null !== $assigned_user_id && $assigned_user_id > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$columns}
                    FROM {$table}
                    WHERE referral_id = %d
                      AND assigned_user_id = %d
                    ORDER BY visit_date DESC, start_time DESC, id DESC",
                    $referral_id,
                    $assigned_user_id
                ),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$columns}
                    FROM {$table}
                    WHERE referral_id = %d
                    ORDER BY visit_date DESC, start_time DESC, id DESC",
                    $referral_id
                ),
                ARRAY_A
            );
        }

        return is_array($results) ? $results : [];
    }

    /**
     * Upcoming visits assigned to a user (from today forward).
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_upcoming_by_user(int $user_id, int $limit = 10): array
    {
        global $wpdb;

        if ($user_id <= 0) {
            return [];
        }

        $limit   = max(1, min(100, $limit));
        $table   = Tables::care_visits_table();
        $columns = self::SELECT_COLUMNS;
        $today   = current_time('Y-m-d');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE assigned_user_id = %d
                  AND visit_date >= %s
                  AND visit_status NOT IN ('cancelled', 'completed', 'missed')
                ORDER BY visit_date ASC, start_time ASC, id ASC
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
     * Upcoming visits across all referrals (from today forward).
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_upcoming(int $limit = 10): array
    {
        global $wpdb;

        $limit   = max(1, min(100, $limit));
        $table   = Tables::care_visits_table();
        $columns = self::SELECT_COLUMNS;
        $today   = current_time('Y-m-d');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE visit_date >= %s
                  AND visit_status NOT IN ('cancelled', 'completed', 'missed')
                ORDER BY visit_date ASC, start_time ASC, id ASC
                LIMIT %d",
                $today,
                $limit
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function map_row(array $data, bool $include_create_fields): array
    {
        $row = [
            'referral_id'      => absint($data['referral_id'] ?? 0),
            'care_plan_id'     => array_key_exists('care_plan_id', $data) ? $data['care_plan_id'] : null,
            'assigned_user_id' => array_key_exists('assigned_user_id', $data) ? $data['assigned_user_id'] : null,
            'visit_date'       => (string) ($data['visit_date'] ?? ''),
            'start_time'       => (string) ($data['start_time'] ?? ''),
            'end_time'         => (string) ($data['end_time'] ?? ''),
            'visit_status'     => (string) ($data['visit_status'] ?? 'scheduled'),
            'visit_type'       => $data['visit_type'] ?? null,
            'tasks'            => $data['tasks'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'completed_at'     => array_key_exists('completed_at', $data) ? $data['completed_at'] : null,
            'updated_at'       => (string) ($data['updated_at'] ?? ''),
        ];

        if (null !== $row['care_plan_id']) {
            $row['care_plan_id'] = absint($row['care_plan_id']) ?: null;
        }

        if (null !== $row['assigned_user_id']) {
            $row['assigned_user_id'] = absint($row['assigned_user_id']) ?: null;
        }

        if ($include_create_fields) {
            $row = [
                'created_by' => absint($data['created_by'] ?? 0),
            ] + $row + [
                'created_at' => (string) ($data['created_at'] ?? ''),
            ];
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int, string>
     */
    private function formats_for_row(array $row): array
    {
        $int_keys = ['referral_id', 'care_plan_id', 'assigned_user_id', 'created_by'];
        $formats  = [];

        foreach ($row as $key => $value) {
            if (in_array($key, $int_keys, true) && null !== $value) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }
}
