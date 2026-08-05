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

    /**
     * Medication names keyed by id.
     *
     * @param array<int, int> $medication_ids
     * @return array<int, string>
     */
    public function get_names_by_ids(array $medication_ids): array
    {
        global $wpdb;

        $ids = [];
        foreach ($medication_ids as $medication_id) {
            $medication_id = absint($medication_id);
            if ($medication_id > 0) {
                $ids[$medication_id] = $medication_id;
            }
        }

        if ([] === $ids) {
            return [];
        }

        $table = Tables::medications_table();
        $names = [];

        foreach (array_chunk(array_values($ids), 200) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, medication_name FROM {$table} WHERE id IN ({$placeholders})",
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
                    $names[$id] = (string) ($row['medication_name'] ?? '');
                }
            }
        }

        return $names;
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
