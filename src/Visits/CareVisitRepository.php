<?php

namespace JMReferral\Visits;

use JMReferral\Database\Tables;

class CareVisitRepository
{
    private const SELECT_COLUMNS = 'id, referral_id, care_plan_id, assigned_user_id, schedule_id, schedule_occurrence_date, generation_key, visit_date, start_time, end_time, visit_status, visit_type, tasks, notes, completed_at, arrival_time, departure_time, actual_duration_minutes, visit_outcome, tasks_completed, tasks_not_completed, client_response, wellbeing_observations, incident_report, manager_review_notes, reviewed_by, reviewed_at, created_by, created_at, updated_at';

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
     * @return array<string, mixed>|null
     */
    public function find_by_generation_key(string $generation_key): ?array
    {
        global $wpdb;

        $generation_key = trim($generation_key);
        if ('' === $generation_key) {
            return null;
        }

        $table   = Tables::care_visits_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE generation_key = %s
                LIMIT 1",
                $generation_key
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function count_by_schedule(int $schedule_id): int
    {
        global $wpdb;

        if ($schedule_id <= 0) {
            return 0;
        }

        $table = Tables::care_visits_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE schedule_id = %d",
                $schedule_id
            )
        );

        return (int) $count;
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
     * Completed visits awaiting manager review.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_awaiting_review(int $limit = 10): array
    {
        global $wpdb;

        $limit   = max(1, min(100, $limit));
        $table   = Tables::care_visits_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE visit_status = %s
                  AND visit_outcome IS NOT NULL
                  AND visit_outcome != ''
                  AND (reviewed_at IS NULL OR reviewed_at = '')
                ORDER BY completed_at DESC, visit_date DESC, id DESC
                LIMIT %d",
                'completed',
                $limit
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Visits completed today for a staff member.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_completed_today_by_user(int $user_id, int $limit = 10): array
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
                  AND visit_status = %s
                  AND (
                    DATE(completed_at) = %s
                    OR (completed_at IS NULL AND visit_date = %s)
                  )
                ORDER BY completed_at DESC, departure_time DESC, id DESC
                LIMIT %d",
                $user_id,
                'completed',
                $today,
                $today,
                $limit
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Open visits whose scheduled date/time is in the past.
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_overdue_visits(
        string $today,
        string $now_time,
        int $limit = 100,
        ?int $access_assigned_to = null
    ): array {
        global $wpdb;

        $limit     = max(1, min(500, $limit));
        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();
        $where     = [
            "v.visit_status IN ('scheduled', 'confirmed', 'in_progress')",
            'r.archived_at IS NULL',
            '(
                v.visit_date < %s
                OR (
                    v.visit_date = %s
                    AND v.start_time IS NOT NULL
                    AND v.start_time != \'\'
                    AND v.start_time < %s
                )
            )',
        ];
        $params = [$today, $today, $now_time];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT v.id, v.referral_id, v.visit_date, v.start_time, v.end_time, v.visit_status,
                v.assigned_user_id, r.referral_number, r.client_name, r.assigned_to
            FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY v.visit_date ASC, v.start_time ASC, v.id ASC
            LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/where fragments are trusted; values are prepared.
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Completed executed visits awaiting manager review.
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_visits_awaiting_review(int $limit = 100, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $limit     = max(1, min(500, $limit));
        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();
        $where     = [
            "v.visit_status = 'completed'",
            'v.visit_outcome IS NOT NULL',
            "v.visit_outcome != ''",
            '(v.reviewed_at IS NULL OR v.reviewed_at = \'\')',
            'r.archived_at IS NULL',
        ];
        $params = [];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT v.id, v.referral_id, v.visit_date, v.start_time, v.visit_status, v.visit_outcome,
                v.completed_at, v.reviewed_at, r.referral_number, r.client_name, r.assigned_to
            FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY v.completed_at ASC, v.visit_date ASC, v.id ASC
            LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/where fragments are trusted; values are prepared.
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function map_row(array $data, bool $include_create_fields): array
    {
        if ($include_create_fields) {
            return $this->map_create_row($data);
        }

        return $this->map_update_row($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function map_create_row(array $data): array
    {
        $row = [
            'created_by'               => absint($data['created_by'] ?? 0),
            'referral_id'              => absint($data['referral_id'] ?? 0),
            'care_plan_id'             => array_key_exists('care_plan_id', $data) ? $data['care_plan_id'] : null,
            'assigned_user_id'         => array_key_exists('assigned_user_id', $data) ? $data['assigned_user_id'] : null,
            'schedule_id'              => array_key_exists('schedule_id', $data) ? $data['schedule_id'] : null,
            'schedule_occurrence_date' => array_key_exists('schedule_occurrence_date', $data) ? $data['schedule_occurrence_date'] : null,
            'generation_key'           => array_key_exists('generation_key', $data) ? $data['generation_key'] : null,
            'visit_date'               => (string) ($data['visit_date'] ?? ''),
            'start_time'               => (string) ($data['start_time'] ?? ''),
            'end_time'                 => (string) ($data['end_time'] ?? ''),
            'visit_status'             => (string) ($data['visit_status'] ?? 'scheduled'),
            'visit_type'               => $data['visit_type'] ?? null,
            'tasks'                    => $data['tasks'] ?? null,
            'notes'                    => $data['notes'] ?? null,
            'completed_at'             => array_key_exists('completed_at', $data) ? $data['completed_at'] : null,
            'arrival_time'             => null,
            'departure_time'           => null,
            'actual_duration_minutes'  => null,
            'visit_outcome'            => null,
            'tasks_completed'          => null,
            'tasks_not_completed'      => null,
            'client_response'          => null,
            'wellbeing_observations'   => null,
            'incident_report'          => null,
            'manager_review_notes'     => null,
            'reviewed_by'              => null,
            'reviewed_at'              => null,
            'created_at'               => (string) ($data['created_at'] ?? ''),
            'updated_at'               => (string) ($data['updated_at'] ?? ''),
        ];

        return $this->normalize_nullable_ids($row);
    }

    /**
     * Partial update: only keys present in $data are written.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function map_update_row(array $data): array
    {
        $row = [];

        $nullable_strings = [
            'visit_type',
            'tasks',
            'notes',
            'completed_at',
            'schedule_occurrence_date',
            'generation_key',
            'arrival_time',
            'departure_time',
            'visit_outcome',
            'tasks_completed',
            'tasks_not_completed',
            'client_response',
            'wellbeing_observations',
            'incident_report',
            'manager_review_notes',
            'reviewed_at',
        ];

        $required_strings = [
            'visit_date',
            'start_time',
            'end_time',
            'visit_status',
            'updated_at',
        ];

        foreach ($required_strings as $field) {
            if (array_key_exists($field, $data)) {
                $row[$field] = (string) ($data[$field] ?? '');
            }
        }

        foreach ($nullable_strings as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if (null === $value || (is_string($value) && '' === trim($value))) {
                $row[$field] = null;
            } else {
                $row[$field] = (string) $value;
            }
        }

        $int_fields = [
            'referral_id',
            'care_plan_id',
            'assigned_user_id',
            'schedule_id',
            'actual_duration_minutes',
            'reviewed_by',
        ];

        foreach ($int_fields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if (null === $value || '' === $value) {
                $row[$field] = null;
                continue;
            }
            if ('referral_id' === $field || 'actual_duration_minutes' === $field) {
                $row[$field] = absint($value);
            } else {
                $row[$field] = absint($value) ?: null;
            }
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize_nullable_ids(array $row): array
    {
        foreach (['care_plan_id', 'assigned_user_id', 'schedule_id', 'reviewed_by'] as $key) {
            if (! array_key_exists($key, $row) || null === $row[$key]) {
                continue;
            }
            $row[$key] = absint($row[$key]) ?: null;
        }

        if (array_key_exists('generation_key', $row) && (null === $row['generation_key'] || '' === trim((string) $row['generation_key']))) {
            $row['generation_key'] = null;
        }

        if (array_key_exists('schedule_occurrence_date', $row) && (null === $row['schedule_occurrence_date'] || '' === trim((string) $row['schedule_occurrence_date']))) {
            $row['schedule_occurrence_date'] = null;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int, string>
     */
    private function formats_for_row(array $row): array
    {
        $int_keys = [
            'referral_id',
            'care_plan_id',
            'assigned_user_id',
            'schedule_id',
            'created_by',
            'actual_duration_minutes',
            'reviewed_by',
        ];
        $formats = [];

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
