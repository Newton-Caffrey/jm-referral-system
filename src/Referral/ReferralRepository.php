<?php

namespace JMReferral\Referral;

use JMReferral\Database\Tables;

class ReferralRepository
{
    /**
     * Inserts a referral row into the custom table.
     *
     * @param array<string, mixed> $data Sanitized referral data.
     * @return int|false Inserted row ID on success, false on failure.
     */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::referrals_table(),
            [
                'referral_number'  => $data['referral_number'],
                'client_name'      => $data['client_name'],
                'client_email'     => $data['client_email'],
                'client_phone'     => $data['client_phone'],
                'service_required' => $data['service_required'],
                'service_type_id'  => $data['service_type_id'],
                'workflow_stage_id'=> $data['workflow_stage_id'],
                'referrer_name'    => $data['referrer_name'],
                'referrer_email'   => $data['referrer_email'],
                'priority'         => $data['priority'],
                'notes'            => $data['notes'],
                'status'           => $data['status'],
                'assigned_to'              => $data['assigned_to'],
                'referral_source'          => $data['referral_source'],
                'care_start_date'          => $data['care_start_date'],
                'preferred_contact_method' => $data['preferred_contact_method'],
                'care_requirements'        => $data['care_requirements'],
                'created_at'               => $data['created_at'],
                'updated_at'               => $data['updated_at'],
            ],
            [
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
                '%s',
                '%s',
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
     * Returns all referrals ordered by newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->find_by_filters([]);
    }

    /**
     * Queries referrals with filters, structured for future pagination.
     *
     * @param array{
     *     search?: string,
     *     status?: string,
     *     priority?: string,
     *     assigned_to?: int
     * } $filters
     * @param int|null $access_assigned_to Optional record-level assignee constraint.
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     per_page: int|null
     * }
     */
    public function query(array $filters = [], ?int $per_page = null, int $page = 1, ?int $access_assigned_to = null): array
    {
        $page = max(1, $page);

        $limit  = null;
        $offset = 0;

        if (null !== $per_page && $per_page > 0) {
            $limit  = $per_page;
            $offset = ($page - 1) * $per_page;
        }

        return [
            'items'    => $this->find_by_filters($filters, $limit, $offset, $access_assigned_to),
            'total'    => $this->count_by_filters($filters, $access_assigned_to),
            'page'     => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * Returns referrals matching the given filters.
     *
     * @param array{
     *     search?: string,
     *     status?: string,
     *     priority?: string,
     *     assigned_to?: int
     * } $filters
     * @param int|null $access_assigned_to Optional record-level assignee constraint.
     * @return array<int, array<string, mixed>>
     */
    public function find_by_filters(array $filters = [], ?int $limit = null, int $offset = 0, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $table = Tables::referrals_table();
        [$where_sql, $params] = $this->build_filter_clause($filters, $access_assigned_to);

        $sql = "SELECT id, referral_number, client_name, client_email, client_phone, service_required, service_type_id, workflow_stage_id, priority, status, assigned_to, referral_source, care_start_date, preferred_contact_method, care_requirements, created_at, updated_at
            FROM {$table}
            WHERE {$where_sql}
            ORDER BY created_at DESC, id DESC";

        if (null !== $limit && $limit > 0) {
            $sql     .= ' LIMIT %d OFFSET %d';
            $params[] = $limit;
            $params[] = max(0, $offset);
        }

        if (empty($params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- trusted table/where with no user params.
            $results = $wpdb->get_results($sql, ARRAY_A);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built from trusted fragments + prepared placeholders.
            $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        }

        return is_array($results) ? $results : [];
    }

    /**
     * Counts referrals matching the given filters.
     *
     * @param array{
     *     search?: string,
     *     status?: string,
     *     priority?: string,
     *     assigned_to?: int
     * } $filters
     * @param int|null $access_assigned_to Optional record-level assignee constraint.
     */
    public function count_by_filters(array $filters = [], ?int $access_assigned_to = null): int
    {
        global $wpdb;

        $table = Tables::referrals_table();
        [$where_sql, $params] = $this->build_filter_clause($filters, $access_assigned_to);

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

        if (empty($params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- trusted table/where with no user params.
            $count = $wpdb->get_var($sql);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built from trusted fragments + prepared placeholders.
            $count = $wpdb->get_var($wpdb->prepare($sql, ...$params));
        }

        return (int) $count;
    }

    /**
     * Builds a WHERE clause and prepare params for list filters.
     *
     * @param array{
     *     search?: string,
     *     status?: string,
     *     priority?: string,
     *     assigned_to?: int
     * } $filters
     * @param int|null $access_assigned_to Optional record-level assignee constraint.
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function build_filter_clause(array $filters, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $where  = ['1=1'];
        $params = [];

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ('' !== $search) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = '(referral_number LIKE %s OR client_name LIKE %s OR client_email LIKE %s OR client_phone LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $status = isset($filters['status']) ? (string) $filters['status'] : '';
        if ('' !== $status) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        $priority = isset($filters['priority']) ? (string) $filters['priority'] : '';
        if ('' !== $priority) {
            $where[]  = 'priority = %s';
            $params[] = $priority;
        }

        // Record-level access constraint always wins over request assignee filters.
        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'assigned_to = %d';
            $params[] = $access_assigned_to;
        } else {
            $assigned_to = isset($filters['assigned_to']) ? absint($filters['assigned_to']) : 0;
            if ($assigned_to > 0) {
                $where[]  = 'assigned_to = %d';
                $params[] = $assigned_to;
            }
        }

        return [
            implode(' AND ', $where),
            $params,
        ];
    }

    /**
     * Finds a referral by ID.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = Tables::referrals_table();

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
     * Updates an existing referral.
     *
     * @param array<string, mixed> $data Sanitized referral fields to update.
     * @return bool True on success (including no-change updates).
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            Tables::referrals_table(),
            [
                'client_name'      => $data['client_name'],
                'client_email'     => $data['client_email'],
                'client_phone'     => $data['client_phone'],
                'service_required' => $data['service_required'],
                'service_type_id'  => $data['service_type_id'],
                'workflow_stage_id'=> $data['workflow_stage_id'],
                'referrer_name'    => $data['referrer_name'],
                'referrer_email'   => $data['referrer_email'],
                'priority'         => $data['priority'],
                'notes'            => $data['notes'],
                'status'           => $data['status'],
                'assigned_to'              => $data['assigned_to'],
                'referral_source'          => $data['referral_source'],
                'care_start_date'          => $data['care_start_date'],
                'preferred_contact_method' => $data['preferred_contact_method'],
                'care_requirements'        => $data['care_requirements'],
                'updated_at'               => $data['updated_at'],
            ],
            ['id' => $id],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
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
                '%s',
            ],
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Deletes a referral by ID.
     *
     * @return bool True when a row was deleted.
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->delete(
            Tables::referrals_table(),
            ['id' => $id],
            ['%d']
        );

        return false !== $result && $result > 0;
    }

    /**
     * Counts all referrals.
     */
    /**
     * @param int|null $access_assigned_to Optional record-level assignee constraint.
     */
    public function countAll(?int $access_assigned_to = null): int
    {
        global $wpdb;

        $table = Tables::referrals_table();

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE assigned_to = %d",
                    $access_assigned_to
                )
            );

            return (int) $count;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        return (int) $count;
    }

    /**
     * Counts referrals by status.
     *
     * @param int|null $access_assigned_to Optional record-level assignee constraint.
     */
    public function countByStatus(string $status, ?int $access_assigned_to = null): int
    {
        global $wpdb;

        $table = Tables::referrals_table();

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE status = %s AND assigned_to = %d",
                    $status,
                    $access_assigned_to
                )
            );

            return (int) $count;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = %s",
                $status
            )
        );

        return (int) $count;
    }

    /**
     * Returns the most recent referrals.
     *
     * @param int|null $access_assigned_to Optional record-level assignee constraint.
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 5, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $limit = max(1, $limit);
        $table = Tables::referrals_table();

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, referral_number, client_name, service_required, service_type_id, workflow_stage_id, status, created_at
                    FROM {$table}
                    WHERE assigned_to = %d
                    ORDER BY created_at DESC, id DESC
                    LIMIT %d",
                    $access_assigned_to,
                    $limit
                ),
                ARRAY_A
            );

            return is_array($results) ? $results : [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, referral_number, client_name, service_required, service_type_id, workflow_stage_id, status, created_at
                FROM {$table}
                ORDER BY created_at DESC, id DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Counts referrals whose number starts with the given prefix.
     */
    public function count_by_number_prefix(string $prefix): int
    {
        global $wpdb;

        $table = Tables::referrals_table();
        $like  = $wpdb->esc_like($prefix) . '%';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE referral_number LIKE %s",
                $like
            )
        );

        return (int) $count;
    }

    /**
     * Counts referrals linked to a service type.
     */
    public function count_by_service_type_id(int $service_type_id): int
    {
        global $wpdb;

        if ($service_type_id <= 0) {
            return 0;
        }

        $table = Tables::referrals_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE service_type_id = %d",
                $service_type_id
            )
        );

        return (int) $count;
    }

    /**
     * Counts referrals linked to a workflow stage.
     */
    public function count_by_workflow_stage_id(int $workflow_stage_id): int
    {
        global $wpdb;

        if ($workflow_stage_id <= 0) {
            return 0;
        }

        $table = Tables::referrals_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE workflow_stage_id = %d",
                $workflow_stage_id
            )
        );

        return (int) $count;
    }

    /**
     * Returns referral counts grouped by workflow stage ID.
     *
     * @return array<int, int>
     */
    /**
     * @param int|null $access_assigned_to Optional record-level assignee constraint.
     * @return array<int, int>
     */
    public function count_grouped_by_workflow_stage(?int $access_assigned_to = null): array
    {
        global $wpdb;

        $table = Tables::referrals_table();

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT workflow_stage_id, COUNT(*) AS total
                    FROM {$table}
                    WHERE workflow_stage_id IS NOT NULL
                      AND assigned_to = %d
                    GROUP BY workflow_stage_id",
                    $access_assigned_to
                ),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
            $results = $wpdb->get_results(
                "SELECT workflow_stage_id, COUNT(*) AS total
                FROM {$table}
                WHERE workflow_stage_id IS NOT NULL
                GROUP BY workflow_stage_id",
                ARRAY_A
            );
        }

        if (! is_array($results)) {
            return [];
        }

        $counts = [];
        foreach ($results as $row) {
            $stage_id = absint($row['workflow_stage_id'] ?? 0);
            if ($stage_id > 0) {
                $counts[$stage_id] = (int) ($row['total'] ?? 0);
            }
        }

        return $counts;
    }
}
