<?php

namespace JMReferral\Medication;

use JMReferral\Database\Tables;

class MedicationAdministrationRepository
{
    private const SELECT_COLUMNS = 'id, medication_id, referral_id, visit_id, administered_by, scheduled_time, administered_time, administration_status, dose_given, notes, reason_code, witness_user_id, created_at, updated_at';

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $row = $this->map_row($data, true);

        $result = $wpdb->insert(
            Tables::medication_administrations_table(),
            $row,
            $this->formats_for_row($row)
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

        $row = $this->map_row($data, false);
        if ([] === $row) {
            return false;
        }

        $result = $wpdb->update(
            Tables::medication_administrations_table(),
            $row,
            ['id' => $id],
            $this->formats_for_row($row),
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

        $table   = Tables::medication_administrations_table();
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

        $table   = Tables::medication_administrations_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE visit_id = %d
                ORDER BY scheduled_time ASC, id ASC",
                $visit_id
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Administrations for many visits, grouped by visit_id.
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

        $table   = Tables::medication_administrations_table();
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
                    ORDER BY visit_id ASC, scheduled_time ASC, id ASC",
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
     * @return array<int, array<string, mixed>>
     */
    public function get_by_medication(int $medication_id): array
    {
        global $wpdb;

        if ($medication_id <= 0) {
            return [];
        }

        $table   = Tables::medication_administrations_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE medication_id = %d
                ORDER BY administered_time DESC, id DESC",
                $medication_id
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_existing_for_visit_and_time(
        int $medication_id,
        int $visit_id,
        ?string $scheduled_time
    ): ?array {
        global $wpdb;

        if ($medication_id <= 0 || $visit_id <= 0) {
            return null;
        }

        $table   = Tables::medication_administrations_table();
        $columns = self::SELECT_COLUMNS;

        if (null === $scheduled_time || '' === trim($scheduled_time)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT {$columns}
                    FROM {$table}
                    WHERE medication_id = %d
                      AND visit_id = %d
                      AND (scheduled_time IS NULL OR scheduled_time = '')
                    LIMIT 1",
                    $medication_id,
                    $visit_id
                ),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT {$columns}
                    FROM {$table}
                    WHERE medication_id = %d
                      AND visit_id = %d
                      AND scheduled_time = %s
                    LIMIT 1",
                    $medication_id,
                    $visit_id,
                    $scheduled_time
                ),
                ARRAY_A
            );
        }

        return is_array($row) ? $row : null;
    }

    /**
     * Exception administrations for a calendar day (managers).
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_exceptions_for_date(string $date, int $limit = 100, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $limit         = max(1, min(500, $limit));
        $admin_table   = Tables::medication_administrations_table();
        $med_table     = Tables::medications_table();
        $referrals     = Tables::referrals_table();
        $where         = [
            "a.administration_status IN ('refused', 'omitted', 'unavailable', 'error')",
            '(DATE(a.administered_time) = %s OR (a.administered_time IS NULL AND DATE(a.created_at) = %s))',
            'r.archived_at IS NULL',
        ];
        $params = [$date, $date];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT a.id, a.medication_id, a.referral_id, a.visit_id, a.administered_by,
                a.scheduled_time, a.administered_time, a.administration_status, a.dose_given,
                a.notes, a.reason_code, a.created_at,
                m.medication_name, m.strength, m.dosage, m.route,
                r.referral_number, r.client_name, r.assigned_to
            FROM {$admin_table} a
            INNER JOIN {$med_table} m ON m.id = a.medication_id
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY a.administered_time DESC, a.id DESC
            LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/where fragments are trusted; values are prepared.
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Count today's medication administration exceptions for the given referrals.
     *
     * Uses the same exception statuses as OperationalAlertService.
     *
     * @param array<int, int> $referral_ids
     */
    public function count_exceptions_for_date_and_referral_ids(string $date, array $referral_ids): int
    {
        global $wpdb;

        $ids = array_values(array_unique(array_filter(array_map('absint', $referral_ids))));
        if ([] === $ids) {
            return 0;
        }

        $admin_table = Tables::medication_administrations_table();
        $referrals   = Tables::referrals_table();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params = array_merge([$date, $date], $ids);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $sql = "SELECT COUNT(*)
            FROM {$admin_table} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE a.administration_status IN ('refused', 'omitted', 'unavailable', 'error')
              AND (DATE(a.administered_time) = %s OR (a.administered_time IS NULL AND DATE(a.created_at) = %s))
              AND r.archived_at IS NULL
              AND a.referral_id IN ({$placeholders})";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return (int) $count;
    }

    public function count_exceptions_for_date(string $date, ?int $access_assigned_to = null): int
    {
        global $wpdb;

        $admin_table = Tables::medication_administrations_table();
        $referrals   = Tables::referrals_table();
        $where       = [
            "a.administration_status IN ('refused', 'omitted', 'unavailable', 'error')",
            '(DATE(a.administered_time) = %s OR (a.administered_time IS NULL AND DATE(a.created_at) = %s))',
            'r.archived_at IS NULL',
        ];
        $params = [$date, $date];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT COUNT(*)
            FROM {$admin_table} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE " . implode(' AND ', $where);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/where fragments are trusted; values are prepared.
        $count = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return (int) $count;
    }

    /**
     * Exceptions today for a support worker (recorded by them or on their assigned visits).
     */
    public function count_exceptions_today_for_user(int $user_id, string $date): int
    {
        global $wpdb;

        if ($user_id <= 0) {
            return 0;
        }

        $admin_table = Tables::medication_administrations_table();
        $visits      = Tables::care_visits_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$admin_table} a
                INNER JOIN {$visits} v ON v.id = a.visit_id
                WHERE a.administration_status IN ('refused', 'omitted', 'unavailable', 'error')
                  AND (DATE(a.administered_time) = %s OR (a.administered_time IS NULL AND DATE(a.created_at) = %s))
                  AND (a.administered_by = %d OR v.assigned_user_id = %d)",
                $date,
                $date,
                $user_id,
                $user_id
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
        $row = [];

        if (array_key_exists('medication_id', $data)) {
            $row['medication_id'] = absint($data['medication_id']);
        }
        if (array_key_exists('referral_id', $data)) {
            $row['referral_id'] = absint($data['referral_id']);
        }
        if (array_key_exists('visit_id', $data)) {
            $row['visit_id'] = absint($data['visit_id']);
        }
        if (array_key_exists('administered_by', $data)) {
            $row['administered_by'] = absint($data['administered_by']);
        }
        if (array_key_exists('witness_user_id', $data)) {
            $witness = $data['witness_user_id'];
            $row['witness_user_id'] = (null === $witness || '' === $witness || 0 === absint($witness))
                ? null
                : absint($witness);
        }

        foreach (['scheduled_time', 'administered_time', 'administration_status', 'dose_given', 'notes', 'reason_code', 'updated_at'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if (in_array($field, ['scheduled_time', 'administered_time', 'dose_given', 'notes', 'reason_code'], true)) {
                $row[$field] = (null === $value || '' === trim((string) $value)) ? null : (string) $value;
            } else {
                $row[$field] = (string) $value;
            }
        }

        if ($include_create_fields) {
            $row = [
                'medication_id'     => absint($data['medication_id'] ?? 0),
                'referral_id'       => absint($data['referral_id'] ?? 0),
                'visit_id'          => absint($data['visit_id'] ?? 0),
                'administered_by'   => absint($data['administered_by'] ?? 0),
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
        $int_keys = ['medication_id', 'referral_id', 'visit_id', 'administered_by', 'witness_user_id'];
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
