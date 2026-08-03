<?php

namespace JMReferral\Workflow;

use JMReferral\Database\Tables;

class WorkflowStageRepository
{
    /**
     * Inserts a workflow stage row.
     *
     * @param array<string, mixed> $data Sanitized workflow stage data.
     * @return int|false Inserted row ID on success, false on failure.
     */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::workflow_stages_table(),
            [
                'name'        => $data['name'],
                'slug'        => $data['slug'],
                'description' => $data['description'],
                'stage_order' => $data['stage_order'],
                'status'      => $data['status'],
                'created_at'  => $data['created_at'],
                'updated_at'  => $data['updated_at'],
            ],
            [
                '%s',
                '%s',
                '%s',
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
     * Updates an existing workflow stage.
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
            Tables::workflow_stages_table(),
            [
                'name'        => $data['name'],
                'slug'        => $data['slug'],
                'description' => $data['description'],
                'stage_order' => $data['stage_order'],
                'status'      => $data['status'],
                'updated_at'  => $data['updated_at'],
            ],
            ['id' => $id],
            [
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
            ],
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Deletes a workflow stage by ID.
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->delete(
            Tables::workflow_stages_table(),
            ['id' => $id],
            ['%d']
        );

        return false !== $result && $result > 0;
    }

    /**
     * Finds a workflow stage by ID.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = Tables::workflow_stages_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, slug, description, stage_order, status, created_at, updated_at
                FROM {$table}
                WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Finds a workflow stage by slug.
     *
     * @return array<string, mixed>|null
     */
    public function find_by_slug(string $slug): ?array
    {
        global $wpdb;

        if ('' === $slug) {
            return null;
        }

        $table = Tables::workflow_stages_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, slug, description, stage_order, status, created_at, updated_at
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
     * Returns all workflow stages ordered by stage_order then name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        global $wpdb;

        $table = Tables::workflow_stages_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $results = $wpdb->get_results(
            "SELECT id, name, slug, description, stage_order, status, created_at, updated_at
            FROM {$table}
            ORDER BY stage_order ASC, name ASC, id ASC",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Returns active workflow stages ordered by stage_order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_active(): array
    {
        global $wpdb;

        $table = Tables::workflow_stages_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, slug, description, stage_order, status, created_at, updated_at
                FROM {$table}
                WHERE status = %s
                ORDER BY stage_order ASC, name ASC, id ASC",
                'active'
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Returns the first active stage by stage_order (default "New Referral").
     *
     * @return array<string, mixed>|null
     */
    public function find_first_active(): ?array
    {
        global $wpdb;

        $table = Tables::workflow_stages_table();

        // Prefer the seeded New Referral slug when present.
        $by_slug = $this->find_by_slug('new-referral');
        if (null !== $by_slug && 'active' === ($by_slug['status'] ?? '')) {
            return $by_slug;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, slug, description, stage_order, status, created_at, updated_at
                FROM {$table}
                WHERE status = %s
                ORDER BY stage_order ASC, id ASC
                LIMIT 1",
                'active'
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Returns a map of workflow stage ID => name for the given IDs.
     *
     * @param array<int, int> $ids
     * @return array<int, string>
     */
    public function get_names_by_ids(array $ids): array
    {
        global $wpdb;

        $ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', $ids)
                )
            )
        );

        if (empty($ids)) {
            return [];
        }

        $table        = Tables::workflow_stages_table();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name/placeholders are trusted.
        $sql = $wpdb->prepare(
            "SELECT id, name FROM {$table} WHERE id IN ({$placeholders})",
            ...$ids
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
        $results = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($results)) {
            return [];
        }

        $map = [];
        foreach ($results as $row) {
            $map[(int) $row['id']] = (string) $row['name'];
        }

        return $map;
    }
}
