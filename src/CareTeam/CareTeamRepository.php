<?php

namespace JMReferral\CareTeam;

use JMReferral\Database\Tables;

class CareTeamRepository
{
    private const SELECT_COLUMNS = 'id, referral_id, care_plan_id, user_id, team_role, is_primary, assignment_status, start_date, end_date, notes, assigned_by, created_at, updated_at';

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
            Tables::care_team_table(),
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
            Tables::care_team_table(),
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

        $table   = Tables::care_team_table();
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

        $table   = Tables::care_team_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE referral_id = %d
                ORDER BY is_primary DESC, assignment_status ASC, start_date DESC, id DESC",
                $referral_id
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
        global $wpdb;

        if ($referral_id <= 0) {
            return [];
        }

        $table   = Tables::care_team_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE referral_id = %d
                  AND assignment_status = %s
                ORDER BY is_primary DESC, start_date ASC, id ASC",
                $referral_id,
                'active'
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Finds the current primary assignment for a referral, optionally excluding an ID.
     *
     * @return array<string, mixed>|null
     */
    public function find_primary_by_referral(int $referral_id, int $exclude_id = 0): ?array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return null;
        }

        $table   = Tables::care_team_table();
        $columns = self::SELECT_COLUMNS;

        if ($exclude_id > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT {$columns}
                    FROM {$table}
                    WHERE referral_id = %d
                      AND is_primary = 1
                      AND id <> %d
                    LIMIT 1",
                    $referral_id,
                    $exclude_id
                ),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT {$columns}
                    FROM {$table}
                    WHERE referral_id = %d
                      AND is_primary = 1
                    LIMIT 1",
                    $referral_id
                ),
                ARRAY_A
            );
        }

        return is_array($row) ? $row : null;
    }

    /**
     * Clears the primary flag for all other members of a referral.
     */
    public function clear_primary_for_referral(int $referral_id, int $exclude_id = 0): bool
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return false;
        }

        $table = Tables::care_team_table();
        $now   = current_time('mysql');

        if ($exclude_id > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                    SET is_primary = 0, updated_at = %s
                    WHERE referral_id = %d
                      AND is_primary = 1
                      AND id <> %d",
                    $now,
                    $referral_id,
                    $exclude_id
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                    SET is_primary = 0, updated_at = %s
                    WHERE referral_id = %d
                      AND is_primary = 1",
                    $now,
                    $referral_id
                )
            );
        }

        return false !== $result;
    }

    /**
     * Counts distinct referrals where the user is an active care team member.
     */
    public function count_active_referrals_for_user(int $user_id): int
    {
        global $wpdb;

        if ($user_id <= 0) {
            return 0;
        }

        $table = Tables::care_team_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT referral_id)
                FROM {$table}
                WHERE user_id = %d
                  AND assignment_status = %s",
                $user_id,
                'active'
            )
        );

        return (int) $count;
    }

    /**
     * Whether the user is an active care team member on the referral.
     */
    public function is_active_member(int $referral_id, int $user_id): bool
    {
        global $wpdb;

        if ($referral_id <= 0 || $user_id <= 0) {
            return false;
        }

        $table = Tables::care_team_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$table}
                WHERE referral_id = %d
                  AND user_id = %d
                  AND assignment_status = %s",
                $referral_id,
                $user_id,
                'active'
            )
        );

        return (int) $count > 0;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function map_row(array $data, bool $include_create_fields): array
    {
        $row = [
            'referral_id'       => absint($data['referral_id'] ?? 0),
            'care_plan_id'      => array_key_exists('care_plan_id', $data) ? $data['care_plan_id'] : null,
            'user_id'           => absint($data['user_id'] ?? 0),
            'team_role'         => (string) ($data['team_role'] ?? ''),
            'is_primary'        => ! empty($data['is_primary']) ? 1 : 0,
            'assignment_status' => (string) ($data['assignment_status'] ?? 'active'),
            'start_date'        => (string) ($data['start_date'] ?? ''),
            'end_date'          => array_key_exists('end_date', $data) ? $data['end_date'] : null,
            'notes'             => array_key_exists('notes', $data) ? $data['notes'] : null,
            'updated_at'        => (string) ($data['updated_at'] ?? ''),
        ];

        if (null !== $row['care_plan_id']) {
            $row['care_plan_id'] = absint($row['care_plan_id']) ?: null;
        }

        if ($include_create_fields) {
            $row = [
                'assigned_by' => absint($data['assigned_by'] ?? 0),
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
        $int_keys = ['referral_id', 'care_plan_id', 'user_id', 'is_primary', 'assigned_by'];
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
