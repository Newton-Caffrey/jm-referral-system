<?php

namespace JMReferral\Scheduling;

use JMReferral\Database\Tables;

class ScheduleRepository
{
    private const SELECT_COLUMNS = 'id, referral_id, care_plan_id, team_assignment_id, schedule_name, start_date, end_date, repeat_type, repeat_interval, days_of_week, start_time, end_time, visit_type, status, notes, created_by, created_at, updated_at';

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
            Tables::visit_schedules_table(),
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
            Tables::visit_schedules_table(),
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

        $table   = Tables::visit_schedules_table();
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
    public function get_by_referral(int $referral_id): array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return [];
        }

        $table   = Tables::visit_schedules_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE referral_id = %d
                ORDER BY status ASC, start_date DESC, id DESC",
                $referral_id
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Schedule names keyed by id.
     *
     * @param array<int, int> $schedule_ids
     * @return array<int, string>
     */
    public function get_names_by_ids(array $schedule_ids): array
    {
        global $wpdb;

        $ids = [];
        foreach ($schedule_ids as $schedule_id) {
            $schedule_id = absint($schedule_id);
            if ($schedule_id > 0) {
                $ids[$schedule_id] = $schedule_id;
            }
        }

        if ([] === $ids) {
            return [];
        }

        $table = Tables::visit_schedules_table();
        $names = [];

        foreach (array_chunk(array_values($ids), 200) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, schedule_name FROM {$table} WHERE id IN ({$placeholders})",
                    ...$chunk
                ),
                ARRAY_A
            );

            if (! is_array($results)) {
                continue;
            }

            foreach ($results as $row) {
                $id = absint($row['id'] ?? 0);
                if ($id > 0) {
                    $names[$id] = (string) ($row['schedule_name'] ?? '');
                }
            }
        }

        return $names;
    }

    /**
     * Count care visits generated from a schedule.
     */
    public function count_generated_visits(int $schedule_id): int
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

    public function count_by_status(string $status): int
    {
        global $wpdb;

        if ('' === $status) {
            return 0;
        }

        $table     = Tables::visit_schedules_table();
        $referrals = Tables::referrals_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} s
                INNER JOIN {$referrals} r ON r.id = s.referral_id
                WHERE s.status = %s
                  AND r.archived_at IS NULL",
                $status
            )
        );

        return (int) $count;
    }

    /**
     * Active schedules with missing, inactive, or mismatched care-team assignment.
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_active_schedules_without_team(int $limit = 100, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $limit     = max(1, min(500, $limit));
        $schedules = Tables::visit_schedules_table();
        $referrals = Tables::referrals_table();
        $care_team = Tables::care_team_table();
        $where     = [
            "s.status = 'active'",
            'r.archived_at IS NULL',
            '(
                s.team_assignment_id IS NULL
                OR s.team_assignment_id = 0
                OR t.id IS NULL
                OR t.assignment_status != \'active\'
                OR t.referral_id != s.referral_id
            )',
        ];
        $params = [];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT s.id, s.referral_id, s.schedule_name, s.team_assignment_id, s.status, s.start_date,
                r.referral_number, r.client_name, r.assigned_to
            FROM {$schedules} s
            INNER JOIN {$referrals} r ON r.id = s.referral_id
            LEFT JOIN {$care_team} t ON t.id = s.team_assignment_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY s.start_date ASC, s.id ASC
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
        $row = [
            'referral_id'        => absint($data['referral_id'] ?? 0),
            'care_plan_id'       => array_key_exists('care_plan_id', $data) ? $data['care_plan_id'] : null,
            'team_assignment_id' => array_key_exists('team_assignment_id', $data) ? $data['team_assignment_id'] : null,
            'schedule_name'      => (string) ($data['schedule_name'] ?? ''),
            'start_date'         => (string) ($data['start_date'] ?? ''),
            'end_date'           => array_key_exists('end_date', $data) ? $data['end_date'] : null,
            'repeat_type'        => (string) ($data['repeat_type'] ?? 'weekly'),
            'repeat_interval'    => max(1, absint($data['repeat_interval'] ?? 1)),
            'days_of_week'       => array_key_exists('days_of_week', $data) ? $data['days_of_week'] : null,
            'start_time'         => (string) ($data['start_time'] ?? ''),
            'end_time'           => (string) ($data['end_time'] ?? ''),
            'visit_type'         => array_key_exists('visit_type', $data) ? $data['visit_type'] : null,
            'status'             => (string) ($data['status'] ?? 'active'),
            'notes'              => array_key_exists('notes', $data) ? $data['notes'] : null,
            'updated_at'         => (string) ($data['updated_at'] ?? ''),
        ];

        if (null !== $row['care_plan_id']) {
            $row['care_plan_id'] = absint($row['care_plan_id']) ?: null;
        }

        if (null !== $row['team_assignment_id']) {
            $row['team_assignment_id'] = absint($row['team_assignment_id']) ?: null;
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
        $int_keys = ['referral_id', 'care_plan_id', 'team_assignment_id', 'repeat_interval', 'created_by'];
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
