<?php

namespace JMReferral\Homes;

use JMReferral\Database\Tables;

class HomeRepository
{
    /**
     * Inserts a home row.
     *
     * @param array<string, mixed> $data Sanitized home data.
     * @return int|false Inserted row ID on success, false on failure.
     */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::homes_table(),
            [
                'name'            => $data['name'],
                'address_line_1'  => $data['address_line_1'],
                'address_line_2'  => $data['address_line_2'],
                'city'            => $data['city'],
                'postcode'        => $data['postcode'],
                'phone'           => $data['phone'],
                'manager_user_id' => $data['manager_user_id'],
                'status'          => $data['status'],
                'notes'           => $data['notes'],
                'created_at'      => $data['created_at'],
                'updated_at'      => $data['updated_at'],
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
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
     * Updates an existing home.
     *
     * @param array<string, mixed> $data Sanitized fields to update.
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            Tables::homes_table(),
            [
                'name'            => $data['name'],
                'address_line_1'  => $data['address_line_1'],
                'address_line_2'  => $data['address_line_2'],
                'city'            => $data['city'],
                'postcode'        => $data['postcode'],
                'phone'           => $data['phone'],
                'manager_user_id' => $data['manager_user_id'],
                'status'          => $data['status'],
                'notes'           => $data['notes'],
                'updated_at'      => $data['updated_at'],
            ],
            ['id' => $id],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
            ],
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Finds a home by ID.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = Tables::homes_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, address_line_1, address_line_2, city, postcode, phone,
                        manager_user_id, status, notes, created_at, updated_at
                FROM {$table}
                WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Lists homes with optional status/search filters.
     *
     * @param array{status?: string, search?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function query(array $filters = []): array
    {
        global $wpdb;

        $table    = Tables::homes_table();
        $where    = ['1=1'];
        $params   = [];
        $status   = sanitize_key((string) ($filters['status'] ?? ''));
        $search   = trim((string) ($filters['search'] ?? ''));

        if (in_array($status, ['active', 'inactive'], true)) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        if ('' !== $search) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = '(name LIKE %s OR city LIKE %s OR postcode LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT id, name, address_line_1, address_line_2, city, postcode, phone,
                       manager_user_id, status, notes, created_at, updated_at
                FROM {$table}
                WHERE " . implode(' AND ', $where) . '
                ORDER BY name ASC, id ASC';

        if ([] !== $params) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
            $sql = $wpdb->prepare($sql, ...$params);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared when params present.
        $results = $wpdb->get_results($sql, ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Counts bedrooms for a home, optionally filtered by status.
     */
    public function count_bedrooms(int $home_id, ?string $status = null): int
    {
        global $wpdb;

        if ($home_id <= 0) {
            return 0;
        }

        $table = Tables::bedrooms_table();

        if (null !== $status && in_array($status, ['active', 'inactive'], true)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE home_id = %d AND status = %s",
                    $home_id,
                    $status
                )
            );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE home_id = %d",
                $home_id
            )
        );
    }

    /**
     * Batch counts of active bedrooms keyed by home ID.
     *
     * @param array<int, int> $home_ids
     * @return array<int, int>
     */
    public function count_active_bedrooms_by_home_ids(array $home_ids): array
    {
        global $wpdb;

        $ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', $home_ids)
                )
            )
        );

        if ([] === $ids) {
            return [];
        }

        $table        = Tables::bedrooms_table();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params       = array_merge($ids, ['active']);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/placeholders trusted.
        $sql = $wpdb->prepare(
            "SELECT home_id, COUNT(*) AS bedroom_count
            FROM {$table}
            WHERE home_id IN ({$placeholders}) AND status = %s
            GROUP BY home_id",
            ...$params
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
        $results = $wpdb->get_results($sql, ARRAY_A);

        $map = array_fill_keys($ids, 0);
        if (! is_array($results)) {
            return $map;
        }

        foreach ($results as $row) {
            $map[(int) $row['home_id']] = (int) $row['bedroom_count'];
        }

        return $map;
    }
}
