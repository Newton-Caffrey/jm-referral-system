<?php

namespace JMReferral\Homes;

use JMReferral\Database\Tables;

class BedroomRepository
{
    /**
     * Inserts a bedroom row.
     *
     * @param array<string, mixed> $data Sanitized bedroom data.
     * @return int|false Inserted row ID on success, false on failure.
     */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::bedrooms_table(),
            [
                'home_id'    => $data['home_id'],
                'room_label' => $data['room_label'],
                'floor'      => $data['floor'],
                'status'     => $data['status'],
                'notes'      => $data['notes'],
                'created_at' => $data['created_at'],
                'updated_at' => $data['updated_at'],
            ],
            [
                '%d',
                '%s',
                '%s',
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
     * Updates an existing bedroom.
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
            Tables::bedrooms_table(),
            [
                'room_label' => $data['room_label'],
                'floor'      => $data['floor'],
                'status'     => $data['status'],
                'notes'      => $data['notes'],
                'updated_at' => $data['updated_at'],
            ],
            ['id' => $id],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ],
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Finds a bedroom by ID.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = Tables::bedrooms_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, home_id, room_label, floor, status, notes, created_at, updated_at
                FROM {$table}
                WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Lists bedrooms for a home.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_by_home(int $home_id, ?string $status = null): array
    {
        global $wpdb;

        if ($home_id <= 0) {
            return [];
        }

        $table = Tables::bedrooms_table();

        if (null !== $status && in_array($status, ['active', 'inactive'], true)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, home_id, room_label, floor, status, notes, created_at, updated_at
                    FROM {$table}
                    WHERE home_id = %d AND status = %s
                    ORDER BY room_label ASC, id ASC",
                    $home_id,
                    $status
                ),
                ARRAY_A
            );

            return is_array($results) ? $results : [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, home_id, room_label, floor, status, notes, created_at, updated_at
                FROM {$table}
                WHERE home_id = %d
                ORDER BY room_label ASC, id ASC",
                $home_id
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Whether a room label already exists within a home.
     */
    public function room_label_exists(int $home_id, string $room_label, ?int $exclude_id = null): bool
    {
        global $wpdb;

        if ($home_id <= 0 || '' === $room_label) {
            return false;
        }

        $table = Tables::bedrooms_table();

        if (null !== $exclude_id && $exclude_id > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
            $found = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table}
                    WHERE home_id = %d AND room_label = %s AND id != %d
                    LIMIT 1",
                    $home_id,
                    $room_label,
                    $exclude_id
                )
            );

            return null !== $found;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $found = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                WHERE home_id = %d AND room_label = %s
                LIMIT 1",
                $home_id,
                $room_label
            )
        );

        return null !== $found;
    }
}
