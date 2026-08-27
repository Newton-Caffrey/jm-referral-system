<?php

namespace JMReferral\Referral;

use JMReferral\Database\Tables;
use JMReferral\Pipeline\PipelineStage;

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
                'referral_number'          => $data['referral_number'],
                'client_name'              => $data['client_name'],
                'client_email'             => $data['client_email'],
                'client_phone'             => $data['client_phone'],
                'service_required'         => $data['service_required'],
                'service_type_id'          => $data['service_type_id'],
                'workflow_stage_id'        => $data['workflow_stage_id'],
                'referrer_name'            => $data['referrer_name'],
                'referrer_email'           => $data['referrer_email'],
                'priority'                 => $data['priority'],
                'notes'                    => $data['notes'],
                'status'                   => $data['status'],
                'assigned_to'              => $data['assigned_to'],
                'referral_source'          => $data['referral_source'],
                'care_start_date'          => $data['care_start_date'],
                'preferred_contact_method' => $data['preferred_contact_method'],
                'care_requirements'        => $data['care_requirements'],
                'client_first_name'        => $data['client_first_name'] ?? null,
                'client_last_name'         => $data['client_last_name'] ?? null,
                'client_date_of_birth'     => $data['client_date_of_birth'] ?? null,
                'address_line_1'           => $data['address_line_1'] ?? null,
                'address_line_2'           => $data['address_line_2'] ?? null,
                'city'                     => $data['city'] ?? null,
                'postcode'                 => $data['postcode'] ?? null,
                'referrer_type'            => $data['referrer_type'] ?? null,
                'referrer_organisation'    => $data['referrer_organisation'] ?? null,
                'referrer_phone'           => $data['referrer_phone'] ?? null,
                'relationship_to_client'   => $data['relationship_to_client'] ?? null,
                'submission_channel'       => $data['submission_channel'] ?? 'admin',
                'public_consent_at'           => $data['public_consent_at'] ?? null,
                'public_consent_version'      => $data['public_consent_version'] ?? null,
                'workflow_stage_entered_at'   => $data['workflow_stage_entered_at'] ?? null,
                'next_action_due_at'          => $data['next_action_due_at'] ?? null,
                'created_at'                  => $data['created_at'],
                'updated_at'                  => $data['updated_at'],
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

        $sql = "SELECT id, referral_number, client_name, client_email, client_phone, service_required, service_type_id, workflow_stage_id, workflow_stage_entered_at, next_action_due_at, priority, status, assigned_to, referral_source, care_start_date, preferred_contact_method, care_requirements, care_setting, submission_channel, referrer_type, referrer_organisation, relationship_to_client, public_consent_at, public_consent_version, client_first_name, client_last_name, client_date_of_birth, address_line_1, address_line_2, city, postcode, referrer_phone, archived_at, archived_by, archive_reason, created_at, updated_at
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
     *     assigned_to?: int,
     *     archive_scope?: string,
     *     care_setting?: string
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

        $care_setting = isset($filters['care_setting']) ? (string) $filters['care_setting'] : '';
        if (CareSetting::NOT_SPECIFIED === $care_setting) {
            $where[] = '(care_setting IS NULL OR care_setting = \'\')';
        } elseif ('' !== $care_setting && CareSetting::is_valid($care_setting)) {
            $where[]  = 'care_setting = %s';
            $params[] = $care_setting;
        }

        $pipeline_stage = isset($filters['pipeline_stage']) ? (string) $filters['pipeline_stage'] : '';
        if ('' !== $pipeline_stage) {
            $this->append_pipeline_stage_filter($where, $params, $pipeline_stage);
        }

        // Record-level access constraint always wins over request assignee filters.
        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'assigned_to = %d';
            $params[] = $access_assigned_to;
        } else {
            if (! empty($filters['unassigned'])) {
                $where[] = '(assigned_to IS NULL OR assigned_to = 0)';
            } else {
                $assigned_to = isset($filters['assigned_to']) ? absint($filters['assigned_to']) : 0;
                if ($assigned_to > 0) {
                    $where[]  = 'assigned_to = %d';
                    $params[] = $assigned_to;
                }
            }
        }

        $this->append_archive_scope($where, (string) ($filters['archive_scope'] ?? 'active'));

        return [
            implode(' AND ', $where),
            $params,
        ];
    }

    /**
     * @param array<int, string> $where
     * @param array<int, mixed>  $params
     */
    private function append_pipeline_stage_filter(array &$where, array &$params, string $pipeline_stage): void
    {
        global $wpdb;

        $stages_table = Tables::workflow_stages_table();

        if (PipelineStage::FILTER_LEGACY === $pipeline_stage) {
            // Non-pipeline stages OR null stage OR stage missing pipeline flag.
            $where[] = "(workflow_stage_id IS NULL OR workflow_stage_id NOT IN (
                SELECT id FROM {$stages_table} WHERE is_pipeline_stage = 1
            ))";
            return;
        }

        if (! PipelineStage::is_canonical($pipeline_stage)) {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
        $stage_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$stages_table} WHERE slug = %s AND is_pipeline_stage = 1 LIMIT 1",
                $pipeline_stage
            )
        );

        if ($stage_id <= 0) {
            $where[] = '1=0';
            return;
        }

        $where[]  = 'workflow_stage_id = %d';
        $params[] = $stage_id;
    }

    /**
     * Centralizes archive_scope SQL for list/count/dashboard helpers.
     *
     * @param array<int, string> $where
     * @param string             $scope active|archived|all
     * @param string             $column Qualified or unqualified archived_at column.
     */
    public function append_archive_scope(array &$where, string $scope = 'active', string $column = 'archived_at'): void
    {
        $scope = $this->normalize_archive_scope($scope);

        if ('active' === $scope) {
            $where[] = "{$column} IS NULL";
            return;
        }

        if ('archived' === $scope) {
            $where[] = "{$column} IS NOT NULL";
        }
    }

    /**
     * @return 'active'|'archived'|'all'
     */
    public function normalize_archive_scope(string $scope): string
    {
        $scope = sanitize_key($scope);

        if (in_array($scope, ['active', 'archived', 'all'], true)) {
            return $scope;
        }

        return 'active';
    }

    /**
     * Sets or clears archive metadata for a referral.
     */
    public function set_archive_state(
        int $id,
        ?string $archived_at,
        ?int $archived_by,
        ?string $archive_reason
    ): bool {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            Tables::referrals_table(),
            [
                'archived_at'    => $archived_at,
                'archived_by'    => $archived_by,
                'archive_reason' => $archive_reason,
                'updated_at'     => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        );

        return false !== $result;
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
                'care_setting'             => $data['care_setting'],
                'address_line_1'           => $data['address_line_1'] ?? null,
                'address_line_2'           => $data['address_line_2'] ?? null,
                'city'                     => $data['city'] ?? null,
                'postcode'                 => $data['postcode'] ?? null,
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
     * Updates interest-response milestone columns only.
     *
     * @param array{
     *     interest_expressed_at: string,
     *     interest_expressed_by: int,
     *     interest_response_method: string,
     *     interest_response_recipient: string|null,
     *     interest_email_status: string,
     *     interest_email_sent_at: string|null
     * } $data
     */
    public function update_interest_response(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            Tables::referrals_table(),
            [
                'interest_expressed_at'       => $data['interest_expressed_at'],
                'interest_expressed_by'       => absint($data['interest_expressed_by']),
                'interest_response_method'    => $data['interest_response_method'],
                'interest_response_recipient' => $data['interest_response_recipient'],
                'interest_email_status'       => $data['interest_email_status'],
                'interest_email_sent_at'      => $data['interest_email_sent_at'],
                'updated_at'                  => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Updates only the broad referral.status column.
     *
     * Prefer ReferralService::change_lifecycle_status for side effects.
     */
    public function update_status_only(int $id, string $status, ?string $updated_at = null): bool
    {
        global $wpdb;

        if ($id <= 0 || '' === $status) {
            return false;
        }

        $result = $wpdb->update(
            Tables::referrals_table(),
            [
                'status'     => $status,
                'updated_at' => $updated_at ?? current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Claims care commencement once (conditional update).
     *
     * @return bool True when this request claimed the milestone.
     */
    public function claim_care_commencement(int $id, string $care_commenced_at, ?int $care_commenced_by): bool
    {
        global $wpdb;

        if ($id <= 0 || '' === $care_commenced_at) {
            return false;
        }

        $table = Tables::referrals_table();
        $now = current_time('mysql');
        $by = null !== $care_commenced_by && $care_commenced_by > 0 ? $care_commenced_by : null;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                SET care_commenced_at = %s,
                    care_commenced_by = " . (null === $by ? 'NULL' : '%d') . ",
                    updated_at = %s
                WHERE id = %d
                  AND care_commenced_at IS NULL
                LIMIT 1",
                ...(null === $by
                    ? [$care_commenced_at, $now, $id]
                    : [$care_commenced_at, $by, $now, $id])
            )
        );

        return false !== $updated && (int) $updated > 0;
    }

    /**
     * Updates pipeline pointer and timing columns only.
     */
    public function update_pipeline_state(
        int $id,
        int $workflow_stage_id,
        ?string $workflow_stage_entered_at,
        ?string $next_action_due_at
    ): bool {
        global $wpdb;

        if ($id <= 0 || $workflow_stage_id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            Tables::referrals_table(),
            [
                'workflow_stage_id'           => $workflow_stage_id,
                'workflow_stage_entered_at'   => $workflow_stage_entered_at,
                'next_action_due_at'          => $next_action_due_at,
                'updated_at'                  => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s', '%s', '%s'],
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Updates only care_setting (used inside occupancy placement transactions).
     */
    public function update_care_setting(int $id, ?string $care_setting): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        if (null !== $care_setting && ! CareSetting::is_valid($care_setting)) {
            return false;
        }

        $result = $wpdb->update(
            Tables::referrals_table(),
            [
                'care_setting' => $care_setting,
                'updated_at'   => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Updates owner (assigned_to), champion and/or transition lead only (Phase 4B.1 / 4C.1).
     * Does not touch workflow_stage_id, status, client, or other referral columns.
     *
     * @param array{assigned_to?: int|null, champion_user_id?: int|null, transition_lead_user_id?: int|null} $data
     */
    public function update_responsibility_fields(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $row     = [];
        $formats = [];

        foreach (['assigned_to', 'champion_user_id', 'transition_lead_user_id'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            $row[$field] = null === $value || '' === $value || 0 === (int) $value
                ? null
                : absint($value);
            $formats[] = '%d';
        }

        if ([] === $row) {
            return false;
        }

        $row['updated_at'] = current_time('mysql');
        $formats[]         = '%s';

        $result = $wpdb->update(
            Tables::referrals_table(),
            $row,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Batch read of responsibility user IDs for referrals (future dashboard).
     *
     * @param array<int, int> $referral_ids
     * @return array<int, array{champion_user_id: int, transition_lead_user_id: int}>
     */
    public function responsibility_map_for_referrals(array $referral_ids): array
    {
        global $wpdb;

        $referral_ids = array_values(array_unique(array_filter(array_map('absint', $referral_ids))));
        if ([] === $referral_ids) {
            return [];
        }

        $table        = Tables::referrals_table();
        $placeholders = implode(',', array_fill(0, count($referral_ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, champion_user_id, transition_lead_user_id
                FROM {$table}
                WHERE id IN ({$placeholders})",
                ...$referral_ids
            ),
            ARRAY_A
        );

        $map = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $rid = absint($row['id'] ?? 0);
                if ($rid > 0) {
                    $map[$rid] = [
                        'champion_user_id'        => absint($row['champion_user_id'] ?? 0),
                        'transition_lead_user_id' => absint($row['transition_lead_user_id'] ?? 0),
                    ];
                }
            }
        }

        return $map;
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
     * Counts referrals (default: active / non-archived only).
     *
     * @param int|null $access_assigned_to Optional record-level assignee constraint.
     * @param string   $archive_scope      active|archived|all
     */
    public function countAll(?int $access_assigned_to = null, string $archive_scope = 'active'): int
    {
        return $this->count_by_filters(
            ['archive_scope' => $this->normalize_archive_scope($archive_scope)],
            $access_assigned_to
        );
    }

    /**
     * Counts referrals by status (default: active only).
     *
     * @param int|null $access_assigned_to Optional record-level assignee constraint.
     * @param string   $archive_scope      active|archived|all
     */
    public function countByStatus(string $status, ?int $access_assigned_to = null, string $archive_scope = 'active'): int
    {
        return $this->count_by_filters(
            [
                'status'         => $status,
                'archive_scope'  => $this->normalize_archive_scope($archive_scope),
            ],
            $access_assigned_to
        );
    }

    /**
     * Returns the most recent referrals (default: active only).
     *
     * @param int|null $access_assigned_to Optional record-level assignee constraint.
     * @param string   $archive_scope      active|archived|all
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 5, ?int $access_assigned_to = null, string $archive_scope = 'active'): array
    {
        $limit = max(1, $limit);

        return $this->find_by_filters(
            ['archive_scope' => $this->normalize_archive_scope($archive_scope)],
            $limit,
            0,
            $access_assigned_to
        );
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
        $where = [
            'workflow_stage_id IS NOT NULL',
            'archived_at IS NULL',
        ];
        $params = [];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT workflow_stage_id, COUNT(*) AS total
            FROM {$table}
            WHERE " . implode(' AND ', $where) . '
            GROUP BY workflow_stage_id';

        if ([] === $params) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- trusted fragments.
            $results = $wpdb->get_results($sql, ARRAY_A);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- trusted fragments + prepared params.
            $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
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

    /**
     * Counts active (non-archived) referrals by canonical pipeline stage slug.
     *
     * @param array<int, string> $slugs
     * @return array<string, int> slug => count
     */
    public function count_active_by_pipeline_slugs(array $slugs, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $slugs = array_values(array_filter(array_map('strval', $slugs), static function (string $slug): bool {
            return PipelineStage::is_canonical($slug);
        }));

        $out = [];
        foreach ($slugs as $slug) {
            $out[$slug] = 0;
        }

        if ([] === $slugs) {
            return $out;
        }

        $referrals = Tables::referrals_table();
        $stages    = Tables::workflow_stages_table();
        $placeholders = implode(',', array_fill(0, count($slugs), '%s'));

        $where = [
            "s.is_pipeline_stage = 1",
            "s.slug IN ({$placeholders})",
            'r.archived_at IS NULL',
        ];
        $params = $slugs;

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT s.slug AS stage_slug, COUNT(*) AS total
            FROM {$referrals} r
            INNER JOIN {$stages} s ON s.id = r.workflow_stage_id
            WHERE " . implode(' AND ', $where) . '
            GROUP BY s.slug';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- trusted fragments + prepared params.
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        if (! is_array($results)) {
            return $out;
        }

        foreach ($results as $row) {
            $slug = (string) ($row['stage_slug'] ?? '');
            if (isset($out[$slug])) {
                $out[$slug] = (int) ($row['total'] ?? 0);
            }
        }

        return $out;
    }

    /**
     * Count active referrals not on a canonical pipeline stage.
     */
    public function count_active_legacy_workflow(?int $access_assigned_to = null): int
    {
        global $wpdb;

        $referrals = Tables::referrals_table();
        $stages    = Tables::workflow_stages_table();

        $where = [
            'r.archived_at IS NULL',
            "(r.workflow_stage_id IS NULL OR r.workflow_stage_id NOT IN (
                SELECT id FROM {$stages} WHERE is_pipeline_stage = 1
            ))",
        ];
        $params = [];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT COUNT(*) FROM {$referrals} r WHERE " . implode(' AND ', $where);

        if ([] === $params) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
            return (int) $wpdb->get_var($sql);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * Active acquisition-stage referrals for pipeline dashboard queues.
     *
     * @param array<int, string> $slugs
     * @return array<int, array<string, mixed>>
     */
    public function find_active_pipeline_referrals(
        array $slugs,
        ?int $access_assigned_to = null,
        int $limit = 300
    ): array {
        global $wpdb;

        $slugs = array_values(array_filter(array_map('strval', $slugs), static function (string $slug): bool {
            return PipelineStage::is_canonical($slug);
        }));

        if ([] === $slugs) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $referrals = Tables::referrals_table();
        $stages    = Tables::workflow_stages_table();
        $placeholders = implode(',', array_fill(0, count($slugs), '%s'));

        $where = [
            "s.is_pipeline_stage = 1",
            "s.slug IN ({$placeholders})",
            'r.archived_at IS NULL',
        ];
        $params = $slugs;

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $params[] = $limit;

        $sql = "SELECT r.id, r.referral_number, r.client_name, r.priority, r.status, r.assigned_to,
                r.care_setting, r.workflow_stage_id, r.workflow_stage_entered_at, r.next_action_due_at,
                r.created_at, r.referrer_organisation, r.referrer_type,
                r.interest_expressed_at, r.interest_expressed_by, r.interest_response_method,
                r.interest_response_recipient, r.interest_email_status,
                s.slug AS pipeline_stage_slug, s.name AS pipeline_stage_name, s.stage_order AS pipeline_stage_order
            FROM {$referrals} r
            INNER JOIN {$stages} s ON s.id = r.workflow_stage_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY r.workflow_stage_entered_at ASC, r.id ASC
            LIMIT %d';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Ownership counts for active referrals on the given pipeline slugs (uncapped).
     *
     * @param array<int, string> $slugs
     * @return array<int, array{assigned_to: int, referrals_owned: int}>
     */
    public function count_ownership_by_pipeline_slugs(array $slugs, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $slugs = array_values(array_filter(array_map('strval', $slugs), static function (string $slug): bool {
            return PipelineStage::is_canonical($slug);
        }));

        if ([] === $slugs) {
            return [];
        }

        $referrals = Tables::referrals_table();
        $stages    = Tables::workflow_stages_table();
        $placeholders = implode(',', array_fill(0, count($slugs), '%s'));

        $where = [
            's.is_pipeline_stage = 1',
            "s.slug IN ({$placeholders})",
            'r.archived_at IS NULL',
        ];
        $params = $slugs;

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT COALESCE(r.assigned_to, 0) AS assigned_to, COUNT(*) AS referrals_owned
            FROM {$referrals} r
            INNER JOIN {$stages} s ON s.id = r.workflow_stage_id
            WHERE " . implode(' AND ', $where) . '
            GROUP BY COALESCE(r.assigned_to, 0)
            ORDER BY referrals_owned DESC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        if (! is_array($results)) {
            return [];
        }

        $out = [];
        foreach ($results as $row) {
            $out[] = [
                'assigned_to'     => absint($row['assigned_to'] ?? 0),
                'referrals_owned' => absint($row['referrals_owned'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Referral IDs that currently have an active Supported Living occupancy.
     *
     * @param array<int, int> $referral_ids
     * @return array<int, true> keyed by referral_id
     */
    public function active_occupancy_referral_id_map(array $referral_ids): array
    {
        global $wpdb;

        $referral_ids = array_values(array_unique(array_filter(array_map('absint', $referral_ids))));
        if ([] === $referral_ids) {
            return [];
        }

        $table = Tables::occupancies_table();
        $placeholders = implode(',', array_fill(0, count($referral_ids), '%d'));
        $params = array_merge($referral_ids, ['active']);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT referral_id FROM {$table}
                WHERE referral_id IN ({$placeholders}) AND status = %s",
                ...$params
            )
        );

        $map = [];
        if (is_array($rows)) {
            foreach ($rows as $id) {
                $rid = absint($id);
                if ($rid > 0) {
                    $map[$rid] = true;
                }
            }
        }

        return $map;
    }

    /**
     * Open high/urgent referrals with no assignee.
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_unassigned_high_priority(int $limit = 100, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $limit  = max(1, min(500, $limit));
        $table  = Tables::referrals_table();
        $where  = [
            "priority IN ('high', 'urgent')",
            "status NOT IN ('completed', 'cancelled')",
            '(assigned_to IS NULL OR assigned_to = 0)',
            'archived_at IS NULL',
        ];
        $params = [];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT id, referral_number, client_name, priority, status, assigned_to, created_at, updated_at
            FROM {$table}
            WHERE " . implode(' AND ', $where) . '
            ORDER BY FIELD(priority, \'urgent\', \'high\'), created_at ASC, id ASC
            LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/where fragments are trusted; values are prepared.
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Open referrals older than the cutoff with no assessment row.
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_without_assessment(string $created_before, int $limit = 100, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $limit       = max(1, min(500, $limit));
        $referrals   = Tables::referrals_table();
        $assessments = Tables::referral_assessments_table();
        $where       = [
            "r.status NOT IN ('completed', 'cancelled')",
            'a.id IS NULL',
            'r.created_at <= %s',
            'r.archived_at IS NULL',
        ];
        $params = [$created_before];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT r.id, r.referral_number, r.client_name, r.priority, r.status, r.assigned_to, r.created_at
            FROM {$referrals} r
            LEFT JOIN {$assessments} a ON a.referral_id = r.id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY r.created_at ASC, r.id ASC
            LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/where fragments are trusted; values are prepared.
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Open referrals that have an assessment but no active care plan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_without_active_care_plan(int $limit = 100, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $limit       = max(1, min(500, $limit));
        $referrals   = Tables::referrals_table();
        $assessments = Tables::referral_assessments_table();
        $care_plans  = Tables::referral_care_plans_table();
        $where       = [
            "r.status NOT IN ('completed', 'cancelled')",
            '(cp.id IS NULL OR cp.plan_status IN (\'draft\', \'under_review\'))',
            'r.archived_at IS NULL',
        ];
        $params = [];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT r.id, r.referral_number, r.client_name, r.priority, r.status, r.assigned_to, r.created_at,
                cp.id AS care_plan_id, cp.plan_status
            FROM {$referrals} r
            INNER JOIN {$assessments} a ON a.referral_id = r.id
            LEFT JOIN {$care_plans} cp ON cp.referral_id = r.id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY r.created_at ASC, r.id ASC
            LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/where fragments are trusted; values are prepared.
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * High/urgent open referrals with no upcoming open visit.
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_high_priority_without_upcoming_visits(
        string $today,
        int $limit = 100,
        ?int $access_assigned_to = null
    ): array {
        global $wpdb;

        $limit     = max(1, min(500, $limit));
        $referrals = Tables::referrals_table();
        $visits    = Tables::care_visits_table();
        $where     = [
            "r.priority IN ('high', 'urgent')",
            "r.status NOT IN ('completed', 'cancelled')",
            'r.archived_at IS NULL',
            "NOT EXISTS (
                SELECT 1 FROM {$visits} v
                WHERE v.referral_id = r.id
                  AND v.visit_status IN ('scheduled', 'confirmed', 'in_progress')
                  AND v.visit_date >= %s
            )",
        ];
        $params = [$today];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT r.id, r.referral_number, r.client_name, r.priority, r.status, r.assigned_to, r.created_at
            FROM {$referrals} r
            WHERE " . implode(' AND ', $where) . '
            ORDER BY FIELD(r.priority, \'urgent\', \'high\'), r.created_at ASC, r.id ASC
            LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/where fragments are trusted; values are prepared.
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Unassigned responsibility counts for non-archived, non-terminal referrals (Phase 4D.1).
     *
     * @return array{owner: int, champion: int, transition_lead: int}
     */
    public function count_unassigned_responsibilities(?int $access_assigned_to = null): array
    {
        global $wpdb;

        $table = Tables::referrals_table();
        $where = [
            'archived_at IS NULL',
            "status NOT IN ('completed', 'cancelled')",
        ];
        $params = [];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $where_sql = implode(' AND ', $where);
        $sql       = "SELECT
                SUM(CASE WHEN assigned_to IS NULL OR assigned_to = 0 THEN 1 ELSE 0 END) AS owner_unassigned,
                SUM(CASE WHEN champion_user_id IS NULL OR champion_user_id = 0 THEN 1 ELSE 0 END) AS champion_unassigned,
                SUM(CASE WHEN transition_lead_user_id IS NULL OR transition_lead_user_id = 0 THEN 1 ELSE 0 END) AS transition_lead_unassigned
            FROM {$table}
            WHERE {$where_sql}";

        if ([] === $params) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- trusted fragments.
            $row = $wpdb->get_row($sql, ARRAY_A);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $row = $wpdb->get_row($wpdb->prepare($sql, ...$params), ARRAY_A);
        }

        return [
            'owner'           => (int) ($row['owner_unassigned'] ?? 0),
            'champion'        => (int) ($row['champion_unassigned'] ?? 0),
            'transition_lead' => (int) ($row['transition_lead_unassigned'] ?? 0),
        ];
    }

    /**
     * Workload counts by responsibility field for non-archived, non-terminal referrals.
     *
     * @param 'assigned_to'|'champion_user_id'|'transition_lead_user_id' $field
     * @return array<int, array{user_id: int, count: int}>
     */
    public function count_responsibility_workload(string $field, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $allowed = ['assigned_to', 'champion_user_id', 'transition_lead_user_id'];
        if (! in_array($field, $allowed, true)) {
            return [];
        }

        $table = Tables::referrals_table();
        $where = [
            'archived_at IS NULL',
            "status NOT IN ('completed', 'cancelled')",
        ];
        $params = [];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $where_sql = implode(' AND ', $where);
        // Column name is allowlisted above.
        $sql = "SELECT COALESCE({$field}, 0) AS user_id, COUNT(*) AS total
            FROM {$table}
            WHERE {$where_sql}
            GROUP BY COALESCE({$field}, 0)
            ORDER BY total DESC, user_id ASC";

        if ([] === $params) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- trusted allowlisted column.
            $results = $wpdb->get_results($sql, ARRAY_A);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        }

        if (! is_array($results)) {
            return [];
        }

        $out = [];
        foreach ($results as $row) {
            $out[] = [
                'user_id' => absint($row['user_id'] ?? 0),
                'count'   => (int) ($row['total'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Active operational referral count: non-archived and not completed/cancelled.
     */
    public function count_active_operational(?int $access_assigned_to = null): int
    {
        global $wpdb;

        $table = Tables::referrals_table();
        $where = [
            'archived_at IS NULL',
            "status NOT IN ('completed', 'cancelled')",
        ];
        $params = [];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . implode(' AND ', $where);

        if ([] === $params) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- trusted fragments.
            return (int) $wpdb->get_var($sql);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }
}
