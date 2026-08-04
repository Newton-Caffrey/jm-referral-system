<?php

namespace JMReferral\Medication;

use JMReferral\Database\Tables;

class MedicationRepository
{
    private const SELECT_COLUMNS = 'id, referral_id, medication_name, strength, dosage, route, frequency, instructions, start_date, end_date, medication_status, prescribing_source, created_by, created_at, updated_at';

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $row = $this->map_row($data, true);

        $result = $wpdb->insert(
            Tables::medications_table(),
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
            Tables::medications_table(),
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

        $table   = Tables::medications_table();
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
    public function get_by_referral(int $referral_id, bool $include_inactive = true): array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return [];
        }

        $table   = Tables::medications_table();
        $columns = self::SELECT_COLUMNS;
        $where   = 'referral_id = %d';
        $params  = [$referral_id];

        if (! $include_inactive) {
            $where   .= ' AND medication_status = %s';
            $params[] = MedicationStatuses::ACTIVE;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE {$where}
                ORDER BY medication_status ASC, medication_name ASC, id ASC",
                ...$params
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_active_by_referral(int $referral_id): array
    {
        return $this->get_by_referral($referral_id, false);
    }

    public function count_active_by_referral(int $referral_id): int
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return 0;
        }

        $table = Tables::medications_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                WHERE referral_id = %d AND medication_status = %s",
                $referral_id,
                MedicationStatuses::ACTIVE
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

        $string_fields = [
            'medication_name',
            'strength',
            'dosage',
            'route',
            'frequency',
            'instructions',
            'start_date',
            'end_date',
            'medication_status',
            'prescribing_source',
            'updated_at',
        ];

        foreach ($string_fields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];
            if (in_array($field, ['strength', 'frequency', 'instructions', 'start_date', 'end_date', 'prescribing_source'], true)) {
                if (null === $value || '' === trim((string) $value)) {
                    $row[$field] = null;
                } else {
                    $row[$field] = (string) $value;
                }
            } else {
                $row[$field] = (string) $value;
            }
        }

        if ($include_create_fields) {
            $row = [
                'referral_id' => absint($data['referral_id'] ?? 0),
                'created_by'  => absint($data['created_by'] ?? 0),
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
        $int_keys = ['referral_id', 'created_by'];
        $formats  = [];

        foreach ($row as $key => $value) {
            $formats[] = in_array($key, $int_keys, true) ? '%d' : '%s';
            unset($value);
        }

        return $formats;
    }
}
