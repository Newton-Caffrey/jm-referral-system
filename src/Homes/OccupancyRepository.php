<?php

namespace JMReferral\Homes;

use JMReferral\Database\Tables;

class OccupancyRepository
{
    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::occupancies_table(),
            [
                'referral_id'   => $data['referral_id'],
                'home_id'       => $data['home_id'],
                'bedroom_id'    => $data['bedroom_id'],
                'move_in_date'  => $data['move_in_date'],
                'move_out_date' => $data['move_out_date'],
                'status'        => $data['status'],
                'notes'         => $data['notes'],
                'end_reason'    => $data['end_reason'],
                'created_by'    => $data['created_by'],
                'ended_by'      => $data['ended_by'],
                'created_at'    => $data['created_at'],
                'updated_at'    => $data['updated_at'],
                'ended_at'      => $data['ended_at'],
            ],
            [
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
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
     * Ends an active occupancy row.
     *
     * @param array<string, mixed> $data
     */
    public function end(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            Tables::occupancies_table(),
            [
                'move_out_date' => $data['move_out_date'],
                'status'        => 'ended',
                'end_reason'    => $data['end_reason'],
                'notes'         => $data['notes'],
                'ended_by'      => $data['ended_by'],
                'ended_at'      => $data['ended_at'],
                'updated_at'    => $data['updated_at'],
            ],
            [
                'id'     => $id,
                'status' => 'active',
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
            ],
            ['%d', '%s']
        );

        return false !== $result && $result > 0;
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

        $table = Tables::occupancies_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function current_for_referral(int $referral_id): ?array
    {
        return $this->current_by('referral_id', $referral_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function current_for_bedroom(int $bedroom_id): ?array
    {
        return $this->current_by('bedroom_id', $bedroom_id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function current_for_home(int $home_id): array
    {
        global $wpdb;

        if ($home_id <= 0) {
            return [];
        }

        $table = Tables::occupancies_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE home_id = %d AND status = %s
                ORDER BY move_in_date DESC, id DESC",
                $home_id,
                'active'
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history_for_referral(int $referral_id): array
    {
        return $this->history_by('referral_id', $referral_id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history_for_bedroom(int $bedroom_id): array
    {
        return $this->history_by('bedroom_id', $bedroom_id);
    }

    public function count_active_for_home(int $home_id): int
    {
        global $wpdb;

        if ($home_id <= 0) {
            return 0;
        }

        $table = Tables::occupancies_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE home_id = %d AND status = %s",
                $home_id,
                'active'
            )
        );
    }

    public function bedroom_has_any_occupancy(int $bedroom_id): bool
    {
        global $wpdb;

        if ($bedroom_id <= 0) {
            return false;
        }

        $table = Tables::occupancies_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $found = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE bedroom_id = %d LIMIT 1",
                $bedroom_id
            )
        );

        return null !== $found;
    }

    /**
     * Active occupancy counts keyed by home ID.
     *
     * @param array<int, int> $home_ids
     * @return array<int, int>
     */
    public function count_active_by_home_ids(array $home_ids): array
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

        $table        = Tables::occupancies_table();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params       = array_merge($ids, ['active']);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/placeholders trusted.
        $sql = $wpdb->prepare(
            "SELECT home_id, COUNT(*) AS occupied_count
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
            $map[(int) $row['home_id']] = (int) $row['occupied_count'];
        }

        return $map;
    }

    /**
     * Active occupancy rows for bedrooms in a home, keyed by bedroom_id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function active_by_bedroom_for_home(int $home_id): array
    {
        $rows = $this->current_for_home($home_id);
        $map  = [];

        foreach ($rows as $row) {
            $bedroom_id = absint($row['bedroom_id'] ?? 0);
            if ($bedroom_id > 0) {
                $map[$bedroom_id] = $row;
            }
        }

        return $map;
    }

    /**
     * Occupied bedroom IDs that currently have an active occupancy.
     *
     * @param array<int, int> $bedroom_ids
     * @return array<int, int>
     */
    public function occupied_bedroom_ids(array $bedroom_ids = []): array
    {
        global $wpdb;

        $table = Tables::occupancies_table();

        if ([] === $bedroom_ids) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
            $results = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT bedroom_id FROM {$table} WHERE status = %s",
                    'active'
                )
            );

            return array_map('absint', is_array($results) ? $results : []);
        }

        $ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', $bedroom_ids)
                )
            )
        );

        if ([] === $ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params       = array_merge($ids, ['active']);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/placeholders trusted.
        $sql = $wpdb->prepare(
            "SELECT bedroom_id FROM {$table}
            WHERE bedroom_id IN ({$placeholders}) AND status = %s",
            ...$params
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
        $results = $wpdb->get_col($sql);

        return array_map('absint', is_array($results) ? $results : []);
    }

    /**
     * Locks a referral row for the duration of the current transaction.
     */
    public function lock_referral_row(int $referral_id): bool
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return false;
        }

        $table = Tables::referrals_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d FOR UPDATE",
                $referral_id
            )
        );

        return null !== $id;
    }

    /**
     * Locks a bedroom row for the duration of the current transaction.
     *
     * @return array<string, mixed>|null
     */
    public function lock_bedroom_row(int $bedroom_id): ?array
    {
        global $wpdb;

        if ($bedroom_id <= 0) {
            return null;
        }

        $table = Tables::bedrooms_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d FOR UPDATE",
                $bedroom_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Locks an occupancy row for the duration of the current transaction.
     *
     * @return array<string, mixed>|null
     */
    public function lock_occupancy_row(int $occupancy_id): ?array
    {
        global $wpdb;

        if ($occupancy_id <= 0) {
            return null;
        }

        $table = Tables::occupancies_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d FOR UPDATE",
                $occupancy_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Estate occupancy summary for active bedrooms / active placements.
     *
     * @return array{capacity: int, occupied: int, vacant: int}
     */
    public function estate_summary(): array
    {
        global $wpdb;

        $bedrooms = Tables::bedrooms_table();
        $homes    = Tables::homes_table();
        $occ      = Tables::occupancies_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names trusted.
        $capacity = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$bedrooms} b
                INNER JOIN {$homes} h ON h.id = b.home_id
                WHERE b.status = %s AND h.status = %s",
                'active',
                'active'
            )
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names trusted.
        $occupied = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$occ} o
                INNER JOIN {$bedrooms} b ON b.id = o.bedroom_id
                INNER JOIN {$homes} h ON h.id = o.home_id
                WHERE o.status = %s AND b.status = %s AND h.status = %s",
                'active',
                'active',
                'active'
            )
        );

        return [
            'capacity' => $capacity,
            'occupied' => $occupied,
            'vacant'   => max(0, $capacity - $occupied),
        ];
    }

    /**
     * Count of homes with status = active.
     */
    public function count_active_homes(): int
    {
        global $wpdb;

        $homes = Tables::homes_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$homes} WHERE status = %s",
                'active'
            )
        );
    }

    /**
     * Active homes for report filters (id + name only).
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function list_active_home_options(): array
    {
        global $wpdb;

        $homes = Tables::homes_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name FROM {$homes}
                WHERE status = %s
                ORDER BY name ASC, id ASC",
                'active'
            ),
            ARRAY_A
        );

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $id = absint($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id'   => $id,
                'name' => (string) ($row['name'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Returns an active home row when the id is valid and active.
     *
     * @return array{id: int, name: string}|null
     */
    public function find_active_home(int $home_id): ?array
    {
        global $wpdb;

        if ($home_id <= 0) {
            return null;
        }

        $homes = Tables::homes_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name FROM {$homes}
                WHERE id = %d AND status = %s
                LIMIT 1",
                $home_id,
                'active'
            ),
            ARRAY_A
        );

        if (! is_array($row)) {
            return null;
        }

        return [
            'id'   => absint($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
        ];
    }

    /**
     * Per-active-home occupancy metrics (batched; no N+1).
     *
     * @return array<int, array{
     *     home_id: int,
     *     home_name: string,
     *     capacity: int,
     *     occupied: int,
     *     vacant: int,
     *     occupancy_percent: float
     * }>
     */
    public function occupancy_metrics_by_active_homes(): array
    {
        global $wpdb;

        $homes_table = Tables::homes_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
        $homes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name FROM {$homes_table}
                WHERE status = %s
                ORDER BY name ASC, id ASC",
                'active'
            ),
            ARRAY_A
        );

        if (! is_array($homes) || [] === $homes) {
            return [];
        }

        $home_ids = [];
        foreach ($homes as $home) {
            $id = absint($home['id'] ?? 0);
            if ($id > 0) {
                $home_ids[] = $id;
            }
        }

        if ([] === $home_ids) {
            return [];
        }

        $bedrooms_table = Tables::bedrooms_table();
        $placeholders   = implode(',', array_fill(0, count($home_ids), '%d'));
        $capacity_params = array_merge($home_ids, ['active']);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/placeholders trusted.
        $capacity_sql = $wpdb->prepare(
            "SELECT home_id, COUNT(*) AS bedroom_count
            FROM {$bedrooms_table}
            WHERE home_id IN ({$placeholders}) AND status = %s
            GROUP BY home_id",
            ...$capacity_params
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
        $capacity_rows = $wpdb->get_results($capacity_sql, ARRAY_A);
        $capacity_map  = array_fill_keys($home_ids, 0);
        if (is_array($capacity_rows)) {
            foreach ($capacity_rows as $row) {
                $capacity_map[(int) ($row['home_id'] ?? 0)] = (int) ($row['bedroom_count'] ?? 0);
            }
        }

        $occupied_map = $this->count_active_by_home_ids($home_ids);
        $out          = [];

        foreach ($homes as $home) {
            $home_id  = absint($home['id'] ?? 0);
            if ($home_id <= 0) {
                continue;
            }
            $capacity = (int) ($capacity_map[$home_id] ?? 0);
            $occupied = (int) ($occupied_map[$home_id] ?? 0);
            $vacant   = max(0, $capacity - $occupied);
            $pct      = $capacity > 0 ? round(($occupied / $capacity) * 100, 1) : 0.0;

            $out[] = [
                'home_id'           => $home_id,
                'home_name'         => (string) ($home['name'] ?? ''),
                'capacity'          => $capacity,
                'occupied'          => $occupied,
                'vacant'            => $vacant,
                'occupancy_percent' => $pct,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function current_by(string $column, int $id): ?array
    {
        global $wpdb;

        if ($id <= 0 || ! in_array($column, ['referral_id', 'bedroom_id'], true)) {
            return null;
        }

        $table = Tables::occupancies_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column allowlisted, table trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE {$column} = %d AND status = %s
                ORDER BY id DESC
                LIMIT 1",
                $id,
                'active'
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function history_by(string $column, int $id): array
    {
        global $wpdb;

        if ($id <= 0 || ! in_array($column, ['referral_id', 'bedroom_id'], true)) {
            return [];
        }

        $table = Tables::occupancies_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column allowlisted, table trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE {$column} = %d
                ORDER BY move_in_date DESC, id DESC",
                $id
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }
}
