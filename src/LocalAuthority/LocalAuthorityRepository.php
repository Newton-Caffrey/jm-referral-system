<?php

namespace JMReferral\LocalAuthority;

use JMReferral\Database\Tables;

class LocalAuthorityRepository
{
    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::local_authorities_table(),
            [
                'name'          => $data['name'],
                'slug'          => $data['slug'],
                'status'        => $data['status'],
                'contact_name'  => $data['contact_name'],
                'contact_email' => $data['contact_email'],
                'contact_phone' => $data['contact_phone'],
                'website'       => $data['website'],
                'notes'         => $data['notes'],
                'created_at'    => $data['created_at'],
                'updated_at'    => $data['updated_at'],
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
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
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            Tables::local_authorities_table(),
            [
                'name'          => $data['name'],
                'slug'          => $data['slug'],
                'status'        => $data['status'],
                'contact_name'  => $data['contact_name'],
                'contact_email' => $data['contact_email'],
                'contact_phone' => $data['contact_phone'],
                'website'       => $data['website'],
                'notes'         => $data['notes'],
                'updated_at'    => $data['updated_at'],
            ],
            ['id' => $id],
            [
                '%s',
                '%s',
                '%s',
                '%s',
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

    public function set_status(int $id, string $status, string $updated_at): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            Tables::local_authorities_table(),
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

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = Tables::local_authorities_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, slug, status, contact_name, contact_email, contact_phone, website, notes, created_at, updated_at
                FROM {$table}
                WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        global $wpdb;

        if ('' === $slug) {
            return null;
        }

        $table = Tables::local_authorities_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, slug, status, contact_name, contact_email, contact_phone, website, notes, created_at, updated_at
                FROM {$table}
                WHERE slug = %s
                LIMIT 1",
                $slug
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{search?: string, status?: string} $args
     * @return array<int, array<string, mixed>>
     */
    public function list(array $args = []): array
    {
        global $wpdb;

        $table  = Tables::local_authorities_table();
        $where  = ['1=1'];
        $params = [];

        $status = isset($args['status']) ? (string) $args['status'] : '';
        if (in_array($status, ['active', 'inactive'], true)) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        $search = isset($args['search']) ? trim((string) $args['search']) : '';
        if ('' !== $search) {
            $where[]  = 'name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $sql = 'SELECT id, name, slug, status, contact_name, contact_email, contact_phone, website, notes, created_at, updated_at
            FROM ' . $table . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY name ASC, id ASC';

        if (! empty($params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared below with trusted fragments.
            $sql = $wpdb->prepare($sql, ...$params);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/where built safely above.
        $results = $wpdb->get_results($sql, ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function find_active(): array
    {
        return $this->list(['status' => 'active']);
    }

    public function count_all(): int
    {
        global $wpdb;

        $table = Tables::local_authorities_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }
}
