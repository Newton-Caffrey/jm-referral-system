<?php

namespace JMReferral\LocalAuthority;

use JMReferral\Database\Tables;

class SenderRuleRepository
{
    public const TYPE_EXACT_EMAIL = 'exact_email';
    public const TYPE_DOMAIN      = 'domain';

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::local_authority_sender_rules_table(),
            [
                'local_authority_id' => $data['local_authority_id'],
                'rule_type'          => $data['rule_type'],
                'rule_value'         => $data['rule_value'],
                'status'             => $data['status'],
                'created_at'         => $data['created_at'],
                'updated_at'         => $data['updated_at'],
            ],
            [
                '%d',
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

    public function set_status(int $id, string $status, string $updated_at): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            Tables::local_authority_sender_rules_table(),
            [
                'status'     => $status,
                'updated_at' => $updated_at,
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        return false !== $result;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->delete(
            Tables::local_authority_sender_rules_table(),
            ['id' => $id],
            ['%d']
        );

        return false !== $result && $result > 0;
    }

    /**
     * Deletes all rules for an authority (used only if authority hard-delete is ever invoked).
     */
    public function delete_for_authority(int $local_authority_id): bool
    {
        global $wpdb;

        if ($local_authority_id <= 0) {
            return false;
        }

        $result = $wpdb->delete(
            Tables::local_authority_sender_rules_table(),
            ['local_authority_id' => $local_authority_id],
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

        $table = Tables::local_authority_sender_rules_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, local_authority_id, rule_type, rule_value, status, created_at, updated_at
                FROM {$table}
                WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAuthority(int $local_authority_id): array
    {
        global $wpdb;

        if ($local_authority_id <= 0) {
            return [];
        }

        $table = Tables::local_authority_sender_rules_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, local_authority_id, rule_type, rule_value, status, created_at, updated_at
                FROM {$table}
                WHERE local_authority_id = %d
                ORDER BY rule_type ASC, rule_value ASC, id ASC",
                $local_authority_id
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Counts rules per authority for list display.
     *
     * @param array<int, int> $authority_ids
     * @return array<int, int>
     */
    public function count_by_authority_ids(array $authority_ids): array
    {
        global $wpdb;

        $authority_ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', $authority_ids)
                )
            )
        );

        if (empty($authority_ids)) {
            return [];
        }

        $table        = Tables::local_authority_sender_rules_table();
        $placeholders = implode(',', array_fill(0, count($authority_ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/placeholders trusted.
        $sql = $wpdb->prepare(
            "SELECT local_authority_id, COUNT(*) AS rule_count
            FROM {$table}
            WHERE local_authority_id IN ({$placeholders})
            GROUP BY local_authority_id",
            ...$authority_ids
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
        $results = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($results)) {
            return [];
        }

        $map = [];
        foreach ($results as $row) {
            $map[(int) $row['local_authority_id']] = (int) $row['rule_count'];
        }

        return $map;
    }

    /**
     * Finds an exact duplicate for the same authority (after normalisation).
     *
     * @return array<string, mixed>|null
     */
    public function find_duplicate(int $local_authority_id, string $rule_type, string $rule_value): ?array
    {
        global $wpdb;

        if ($local_authority_id <= 0 || '' === $rule_type || '' === $rule_value) {
            return null;
        }

        $table = Tables::local_authority_sender_rules_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, local_authority_id, rule_type, rule_value, status, created_at, updated_at
                FROM {$table}
                WHERE local_authority_id = %d
                  AND rule_type = %s
                  AND rule_value = %s
                LIMIT 1",
                $local_authority_id,
                $rule_type,
                $rule_value
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Active rules of a given type/value across authorities (for overlap warnings / matching).
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_active_by_type_value(string $rule_type, string $rule_value): array
    {
        global $wpdb;

        if ('' === $rule_type || '' === $rule_value) {
            return [];
        }

        $rules_table = Tables::local_authority_sender_rules_table();
        $auth_table  = Tables::local_authorities_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.id, r.local_authority_id, r.rule_type, r.rule_value, r.status, r.created_at, r.updated_at,
                        a.name AS authority_name, a.status AS authority_status
                FROM {$rules_table} r
                INNER JOIN {$auth_table} a ON a.id = r.local_authority_id
                WHERE r.rule_type = %s
                  AND r.rule_value = %s
                  AND r.status = %s
                  AND a.status = %s
                ORDER BY a.name ASC, r.id ASC",
                $rule_type,
                $rule_value,
                'active',
                'active'
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * All active exact-email rules matching a normalised email (with active authority).
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_active_exact_email_matches(string $normalised_email): array
    {
        return $this->find_active_by_type_value(self::TYPE_EXACT_EMAIL, $normalised_email);
    }

    /**
     * All active domain rules with active authorities (for in-memory domain matching).
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_active_domain_rules(): array
    {
        global $wpdb;

        $rules_table = Tables::local_authority_sender_rules_table();
        $auth_table  = Tables::local_authorities_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.id, r.local_authority_id, r.rule_type, r.rule_value, r.status, r.created_at, r.updated_at,
                        a.name AS authority_name, a.slug AS authority_slug, a.status AS authority_status
                FROM {$rules_table} r
                INNER JOIN {$auth_table} a ON a.id = r.local_authority_id
                WHERE r.rule_type = %s
                  AND r.status = %s
                  AND a.status = %s
                ORDER BY CHAR_LENGTH(r.rule_value) DESC, r.id ASC",
                self::TYPE_DOMAIN,
                'active',
                'active'
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }
}
