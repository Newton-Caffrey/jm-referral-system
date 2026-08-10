<?php

namespace JMReferral\Workflow;

use JMReferral\Database\Tables;
use JMReferral\Pipeline\PipelineStage;

class WorkflowStageRepository
{
    private const SELECT_COLUMNS = 'id, name, slug, description, stage_order, status, is_system, is_pipeline_stage, created_at, updated_at';

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
                'name'              => $data['name'],
                'slug'              => $data['slug'],
                'description'       => $data['description'],
                'stage_order'       => $data['stage_order'],
                'status'            => $data['status'],
                'is_system'         => absint($data['is_system'] ?? 0),
                'is_pipeline_stage' => absint($data['is_pipeline_stage'] ?? 0),
                'created_at'        => $data['created_at'],
                'updated_at'        => $data['updated_at'],
            ],
            [
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%d',
                '%d',
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

        $fields  = [
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'],
            'stage_order' => $data['stage_order'],
            'status'      => $data['status'],
            'updated_at'  => $data['updated_at'],
        ];
        $formats = ['%s', '%s', '%s', '%d', '%s', '%s'];

        if (array_key_exists('is_system', $data)) {
            $fields['is_system'] = absint($data['is_system']);
            $formats[]           = '%d';
        }

        if (array_key_exists('is_pipeline_stage', $data)) {
            $fields['is_pipeline_stage'] = absint($data['is_pipeline_stage']);
            $formats[]                   = '%d';
        }

        $result = $wpdb->update(
            Tables::workflow_stages_table(),
            $fields,
            ['id' => $id],
            $formats,
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

        $table   = Tables::workflow_stages_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/columns trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->normalize_row($row) : null;
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

        $table   = Tables::workflow_stages_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/columns trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE slug = %s
                LIMIT 1",
                $slug
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->normalize_row($row) : null;
    }

    /**
     * Returns all workflow stages ordered by stage_order then name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        global $wpdb;

        $table   = Tables::workflow_stages_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/columns trusted.
        $results = $wpdb->get_results(
            "SELECT {$columns}
            FROM {$table}
            ORDER BY stage_order ASC, name ASC, id ASC",
            ARRAY_A
        );

        if (! is_array($results)) {
            return [];
        }

        return array_map([$this, 'normalize_row'], $results);
    }

    /**
     * Returns active workflow stages ordered by stage_order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_active(): array
    {
        global $wpdb;

        $table   = Tables::workflow_stages_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/columns trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE status = %s
                ORDER BY stage_order ASC, name ASC, id ASC",
                'active'
            ),
            ARRAY_A
        );

        if (! is_array($results)) {
            return [];
        }

        return array_map([$this, 'normalize_row'], $results);
    }

    /**
     * Active non-pipeline (legacy/custom) stages for legacy referral editing.
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_active_legacy(): array
    {
        $stages = [];

        foreach ($this->find_active() as $stage) {
            if (! empty($stage['is_pipeline_stage'])) {
                continue;
            }
            $stages[] = $stage;
        }

        return $stages;
    }

    /**
     * Canonical pipeline stage IDs (for filters).
     *
     * @return array<int, int>
     */
    public function get_pipeline_stage_ids(): array
    {
        $ids = [];

        foreach ($this->all() as $stage) {
            if (empty($stage['is_pipeline_stage'])) {
                continue;
            }
            $slug = (string) ($stage['slug'] ?? '');
            if (! PipelineStage::is_canonical($slug)) {
                continue;
            }
            $id = (int) ($stage['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Returns the first active stage by stage_order (prefers canonical default).
     *
     * @return array<string, mixed>|null
     */
    public function find_first_active(): ?array
    {
        $default = $this->find_by_slug(PipelineStage::default_slug());
        if (null !== $default && 'active' === ($default['status'] ?? '')) {
            return $default;
        }

        // Legacy fallback for installs before pipeline seed.
        $by_slug = $this->find_by_slug('new-referral');
        if (null !== $by_slug && 'active' === ($by_slug['status'] ?? '')) {
            return $by_slug;
        }

        global $wpdb;

        $table   = Tables::workflow_stages_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/columns trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE status = %s
                ORDER BY stage_order ASC, id ASC
                LIMIT 1",
                'active'
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->normalize_row($row) : null;
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

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize_row(array $row): array
    {
        $row['is_system']         = absint($row['is_system'] ?? 0);
        $row['is_pipeline_stage'] = absint($row['is_pipeline_stage'] ?? 0);

        return $row;
    }
}
