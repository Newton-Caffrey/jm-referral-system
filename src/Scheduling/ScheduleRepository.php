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

    public function count_by_status(string $status): int
    {
        global $wpdb;

        if ('' === $status) {
            return 0;
        }

        $table = Tables::visit_schedules_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = %s",
                $status
            )
        );

        return (int) $count;
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
