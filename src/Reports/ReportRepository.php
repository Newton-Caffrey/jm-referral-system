<?php

namespace JMReferral\Reports;

use JMReferral\Database\Tables;
use JMReferral\Medication\MedicationAdministrationService;
use JMReferral\Visits\CareVisitService;
use JMReferral\Visits\VisitTaskService;

/**
 * Aggregate reporting queries only. No capability or role checks.
 */
class ReportRepository
{
    /**
     * Total referrals created within the date range.
     */
    public function count_referrals_in_range(string $start_date, string $end_date, ?int $access_assigned_to = null): int
    {
        global $wpdb;

        $table = Tables::referrals_table();
        $sql   = "SELECT COUNT(*) FROM {$table} r
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s";
        $params = [$start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * Referrals with status "new" created within the date range.
     */
    public function count_new_referrals_in_range(string $start_date, string $end_date, ?int $access_assigned_to = null): int
    {
        global $wpdb;

        $table = Tables::referrals_table();
        $sql   = "SELECT COUNT(*) FROM {$table} r
            WHERE r.status = %s
              AND DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s";
        $params = ['new', $start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * Active client caseload: referrals not completed or cancelled (snapshot).
     */
    public function count_active_clients(?int $access_assigned_to = null): int
    {
        global $wpdb;

        $table = Tables::referrals_table();
        $sql   = "SELECT COUNT(*) FROM {$table} r
            WHERE r.status NOT IN (%s, %s)";
        $params = ['completed', 'cancelled'];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * Active care records grouped by care_setting (snapshot).
     *
     * Uses the same active-client rule as count_active_clients():
     * archived_at IS NULL AND status NOT IN ('completed','cancelled').
     *
     * @return array{supported_living: int, own_home: int, not_specified: int}
     */
    public function count_active_clients_by_care_setting(?int $access_assigned_to = null): array
    {
        global $wpdb;

        $table = Tables::referrals_table();
        $sql   = "SELECT
                SUM(CASE WHEN r.care_setting = %s THEN 1 ELSE 0 END) AS supported_living,
                SUM(CASE WHEN r.care_setting = %s THEN 1 ELSE 0 END) AS own_home,
                SUM(CASE WHEN r.care_setting IS NULL OR r.care_setting = '' THEN 1 ELSE 0 END) AS not_specified
            FROM {$table} r
            WHERE r.status NOT IN (%s, %s)";
        $params = ['supported_living', 'own_home', 'completed', 'cancelled'];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$params), ARRAY_A);

        return [
            'supported_living' => (int) ($row['supported_living'] ?? 0),
            'own_home'         => (int) ($row['own_home'] ?? 0),
            'not_specified'    => (int) ($row['not_specified'] ?? 0),
        ];
    }

    /**
     * Active Supported Living care records with no active occupancy (snapshot).
     */
    public function count_supported_living_awaiting_placement(?int $access_assigned_to = null): int
    {
        global $wpdb;

        $referrals   = Tables::referrals_table();
        $occupancies = Tables::occupancies_table();

        $sql = "SELECT COUNT(*) FROM {$referrals} r
            WHERE r.status NOT IN (%s, %s)
              AND r.care_setting = %s
              AND NOT EXISTS (
                  SELECT 1 FROM {$occupancies} o
                  WHERE o.referral_id = r.id AND o.status = %s
              )";
        $params = ['completed', 'cancelled', 'supported_living', 'active'];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * Current vacant active bedrooms in active homes (snapshot).
     *
     * Vacant = active bedroom in active home with no active occupancy.
     * Vacant Since = MAX(move_out_date) from ended occupancy history for that bedroom.
     *
     * @return array<int, array{
     *     home_id: int,
     *     home_name: string,
     *     home_city: string,
     *     home_postcode: string,
     *     bedroom_id: int,
     *     room_label: string,
     *     latest_move_out_date: string|null,
     *     has_occupancy_history: bool
     * }>
     */
    public function list_current_vacancies(?int $home_id = null): array
    {
        global $wpdb;

        $homes       = Tables::homes_table();
        $bedrooms    = Tables::bedrooms_table();
        $occupancies = Tables::occupancies_table();

        $sql = "SELECT
                h.id AS home_id,
                h.name AS home_name,
                h.city AS home_city,
                h.postcode AS home_postcode,
                b.id AS bedroom_id,
                b.room_label AS room_label,
                MAX(CASE WHEN o_hist.status = %s THEN o_hist.move_out_date ELSE NULL END) AS latest_move_out_date,
                MAX(CASE WHEN o_hist.id IS NOT NULL THEN 1 ELSE 0 END) AS has_occupancy_history
            FROM {$bedrooms} b
            INNER JOIN {$homes} h ON h.id = b.home_id AND h.status = %s
            LEFT JOIN {$occupancies} o_active
                ON o_active.bedroom_id = b.id AND o_active.status = %s
            LEFT JOIN {$occupancies} o_hist
                ON o_hist.bedroom_id = b.id
            WHERE b.status = %s
              AND o_active.id IS NULL";

        $params = ['ended', 'active', 'active', 'active'];

        if (null !== $home_id && $home_id > 0) {
            $sql     .= ' AND h.id = %d';
            $params[] = $home_id;
        }

        $sql .= ' GROUP BY b.id, h.id, h.name, h.city, h.postcode, b.room_label
            ORDER BY h.name ASC, b.room_label ASC, b.id ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $move_out = isset($row['latest_move_out_date']) ? (string) $row['latest_move_out_date'] : '';
            if ('' === $move_out || '0000-00-00' === $move_out) {
                $move_out = null;
            }

            $out[] = [
                'home_id'                => absint($row['home_id'] ?? 0),
                'home_name'              => (string) ($row['home_name'] ?? ''),
                'home_city'              => (string) ($row['home_city'] ?? ''),
                'home_postcode'          => (string) ($row['home_postcode'] ?? ''),
                'bedroom_id'             => absint($row['bedroom_id'] ?? 0),
                'room_label'             => (string) ($row['room_label'] ?? ''),
                'latest_move_out_date'   => $move_out,
                'has_occupancy_history'  => ((int) ($row['has_occupancy_history'] ?? 0)) > 0,
            ];
        }

        return $out;
    }

    /**
     * Placement movement action constants (jmrs_referral_activity.action).
     */
    public const ACTION_PLACEMENT_STARTED = 'placement_started';
    public const ACTION_PLACEMENT_TRANSFERRED = 'placement_transferred';
    public const ACTION_PLACEMENT_ENDED = 'placement_ended';

    /**
     * Counts placement movement activity by action within a recorded-at date range.
     *
     * Filters on activity.created_at (when the event was logged), not move-in/out dates.
     * Does not apply Active Clients / archived filters — historical period events remain.
     * Applies assigned-to AccessPolicy scope when present.
     *
     * @return array{placement_started: int, placement_transferred: int, placement_ended: int}
     */
    public function count_placement_movements_in_range(
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null
    ): array {
        global $wpdb;

        $activity  = Tables::referral_activity_table();
        $referrals = Tables::referrals_table();
        $actions   = [
            self::ACTION_PLACEMENT_STARTED,
            self::ACTION_PLACEMENT_TRANSFERRED,
            self::ACTION_PLACEMENT_ENDED,
        ];
        $placeholders = implode(', ', array_fill(0, count($actions), '%s'));

        $sql = "SELECT a.action AS group_key, COUNT(*) AS aggregate_count
            FROM {$activity} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE a.action IN ({$placeholders})
              AND DATE(a.created_at) >= %s
              AND DATE(a.created_at) <= %s";
        $params = array_merge($actions, [$start_date, $end_date]);
        $this->append_referral_assigned_scope($sql, $params, $access_assigned_to, 'r');
        $sql .= ' GROUP BY a.action';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $counts = [
            self::ACTION_PLACEMENT_STARTED     => 0,
            self::ACTION_PLACEMENT_TRANSFERRED => 0,
            self::ACTION_PLACEMENT_ENDED       => 0,
        ];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $key = (string) ($row['group_key'] ?? '');
            if (isset($counts[$key])) {
                $counts[$key] = (int) ($row['aggregate_count'] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * Placement movement activity rows for the recorded-at date range.
     *
     * @return array<int, array{
     *     id: int,
     *     referral_id: int,
     *     action: string,
     *     description: string,
     *     created_at: string,
     *     referral_number: string,
     *     client_first_name: string,
     *     client_last_name: string,
     *     client_name: string
     * }>
     */
    public function list_placement_movements_in_range(
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null,
        ?int $limit = null
    ): array {
        global $wpdb;

        $activity  = Tables::referral_activity_table();
        $referrals = Tables::referrals_table();
        $actions   = [
            self::ACTION_PLACEMENT_STARTED,
            self::ACTION_PLACEMENT_TRANSFERRED,
            self::ACTION_PLACEMENT_ENDED,
        ];
        $placeholders = implode(', ', array_fill(0, count($actions), '%s'));

        $sql = "SELECT
                a.id,
                a.referral_id,
                a.action,
                a.description,
                a.created_at,
                r.referral_number,
                r.client_first_name,
                r.client_last_name,
                r.client_name
            FROM {$activity} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE a.action IN ({$placeholders})
              AND DATE(a.created_at) >= %s
              AND DATE(a.created_at) <= %s";
        $params = array_merge($actions, [$start_date, $end_date]);
        $this->append_referral_assigned_scope($sql, $params, $access_assigned_to, 'r');
        $sql .= ' ORDER BY a.created_at DESC, a.id DESC';

        if (null !== $limit && $limit > 0) {
            $sql     .= ' LIMIT %d';
            $params[] = $limit;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[] = [
                'id'                => absint($row['id'] ?? 0),
                'referral_id'       => absint($row['referral_id'] ?? 0),
                'action'            => (string) ($row['action'] ?? ''),
                'description'       => (string) ($row['description'] ?? ''),
                'created_at'        => (string) ($row['created_at'] ?? ''),
                'referral_number'   => (string) ($row['referral_number'] ?? ''),
                'client_first_name' => (string) ($row['client_first_name'] ?? ''),
                'client_last_name'  => (string) ($row['client_last_name'] ?? ''),
                'client_name'       => (string) ($row['client_name'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Assessments completed in range (outcome is not pending).
     */
    public function count_assessments_completed_in_range(string $start_date, string $end_date, ?int $access_assigned_to = null): int
    {
        global $wpdb;

        $assessments = Tables::referral_assessments_table();
        $referrals   = Tables::referrals_table();

        $sql = "SELECT COUNT(*) FROM {$assessments} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE a.outcome <> %s
              AND COALESCE(a.assessment_date, DATE(a.created_at)) >= %s
              AND COALESCE(a.assessment_date, DATE(a.created_at)) <= %s";
        $params = ['pending', $start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * Active care plans (snapshot).
     */
    public function count_active_care_plans(?int $access_assigned_to = null): int
    {
        global $wpdb;

        $plans     = Tables::referral_care_plans_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT COUNT(*) FROM {$plans} p
            INNER JOIN {$referrals} r ON r.id = p.referral_id
            WHERE p.plan_status = %s";
        $params = ['active'];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    public function count_visits_scheduled_in_range(
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null,
        ?array $visit_filters = null
    ): int {
        return $this->count_visits_by_statuses_in_range(
            [
                CareVisitService::STATUS_SCHEDULED,
                CareVisitService::STATUS_CONFIRMED,
                CareVisitService::STATUS_IN_PROGRESS,
            ],
            $start_date,
            $end_date,
            $access_assigned_to,
            $visit_filters
        );
    }

    public function count_visits_completed_in_range(
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null,
        ?array $visit_filters = null
    ): int {
        return $this->count_visits_by_statuses_in_range(
            [CareVisitService::STATUS_COMPLETED],
            $start_date,
            $end_date,
            $access_assigned_to,
            $visit_filters
        );
    }

    public function count_visits_missed_in_range(
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null,
        ?array $visit_filters = null
    ): int {
        return $this->count_visits_by_statuses_in_range(
            [CareVisitService::STATUS_MISSED],
            $start_date,
            $end_date,
            $access_assigned_to,
            $visit_filters
        );
    }

    /**
     * Selected-period visit counts by delivery-context classification (same semantics as filters).
     *
     * @param array{care_context: string, home_id: int, is_active: bool}|null $visit_filters
     * @return array{supported_living: int, own_home: int, unresolved: int}
     */
    public function count_visits_by_delivery_context_in_range(
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null,
        ?array $visit_filters = null
    ): array {
        $base_home = (int) ($visit_filters['home_id'] ?? 0);

        return [
            'supported_living' => $this->count_visits_matching_delivery_context(
                $start_date,
                $end_date,
                $access_assigned_to,
                VisitDeliveryContext::SUPPORTED_LIVING,
                $base_home
            ),
            'own_home' => $this->count_visits_matching_delivery_context(
                $start_date,
                $end_date,
                $access_assigned_to,
                VisitDeliveryContext::OWN_HOME,
                0
            ),
            'unresolved' => $this->count_visits_matching_delivery_context(
                $start_date,
                $end_date,
                $access_assigned_to,
                VisitDeliveryContext::UNRESOLVED,
                0
            ),
        ];
    }

    /**
     * Active homes plus inactive homes that have historical snapshot visits in range.
     *
     * @return array<int, array{id: int, name: string, status: string, is_inactive: bool}>
     */
    public function list_visit_home_filter_options(string $start_date, string $end_date): array
    {
        global $wpdb;

        $homes  = Tables::homes_table();
        $visits = Tables::care_visits_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names trusted.
        $active = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, status FROM {$homes}
                WHERE status = %s
                ORDER BY name ASC, id ASC",
                'active'
            ),
            ARRAY_A
        );

        $has_snap = VisitDeliveryContext::sql_has_snapshot('v');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/fragment trusted.
        $inactive = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT h.id, h.name, h.status
                FROM {$homes} h
                INNER JOIN {$visits} v ON v.service_home_id = h.id
                WHERE h.status = %s
                  AND {$has_snap}
                  AND v.visit_date >= %s
                  AND v.visit_date <= %s
                ORDER BY h.name ASC, h.id ASC",
                'inactive',
                $start_date,
                $end_date
            ),
            ARRAY_A
        );

        $out  = [];
        $seen = [];
        foreach (array_merge(is_array($active) ? $active : [], is_array($inactive) ? $inactive : []) as $row) {
            $id = absint($row['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $status    = (string) ($row['status'] ?? '');
            $out[]     = [
                'id'          => $id,
                'name'        => (string) ($row['name'] ?? ''),
                'status'      => $status,
                'is_inactive' => 'inactive' === $status,
            ];
        }

        return $out;
    }

    /**
     * @param array{care_context: string, home_id: int, is_active: bool}|null $visit_filters
     */
    private function count_visits_matching_delivery_context(
        string $start_date,
        string $end_date,
        ?int $access_assigned_to,
        string $care_context,
        int $home_id
    ): int {
        global $wpdb;

        $filters = VisitDeliveryContext::normalize([
            'care_context' => $care_context,
            'home_id'      => $home_id,
        ]);

        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();
        $sql       = "SELECT COUNT(*) FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id";
        if (VisitDeliveryContext::needs_occupancy_join($filters)) {
            $sql .= VisitDeliveryContext::occupancy_join_sql('r', 'o_ctx');
        }
        $sql   .= ' WHERE v.visit_date >= %s AND v.visit_date <= %s';
        $params = [$start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        VisitDeliveryContext::append_filters($sql, $params, $filters, 'v', 'r', 'o_ctx');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    public function count_medication_administrations_in_range(string $start_date, string $end_date, ?int $access_assigned_to = null): int
    {
        global $wpdb;

        $admins    = Tables::medication_administrations_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT COUNT(*) FROM {$admins} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE DATE(COALESCE(a.administered_time, a.created_at)) >= %s
              AND DATE(COALESCE(a.administered_time, a.created_at)) <= %s";
        $params = [$start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    public function count_medication_exceptions_in_range(string $start_date, string $end_date, ?int $access_assigned_to = null): int
    {
        global $wpdb;

        $admins   = Tables::medication_administrations_table();
        $referrals = Tables::referrals_table();
        $statuses = MedicationAdministrationService::exception_statuses();

        if ([] === $statuses) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
        $sql          = "SELECT COUNT(*) FROM {$admins} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE a.administration_status IN ({$placeholders})
              AND DATE(COALESCE(a.administered_time, a.created_at)) >= %s
              AND DATE(COALESCE(a.administered_time, a.created_at)) <= %s";
        $params = array_merge($statuses, [$start_date, $end_date]);
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * @return array<int, array{month: string, count: int}>
     */
    public function get_referrals_by_month(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $table = Tables::referrals_table();
        $sql   = "SELECT DATE_FORMAT(r.created_at, '%%Y-%%m') AS month_key, COUNT(*) AS aggregate_count
            FROM {$table} r
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s";
        $params = [$start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        $sql .= ' GROUP BY month_key ORDER BY month_key ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return $this->map_keyed_counts(is_array($rows) ? $rows : [], 'month_key');
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function get_referrals_by_service_type(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals = Tables::referrals_table();
        $types     = Tables::service_types_table();

        $sql = "SELECT COALESCE(st.id, 0) AS group_key,
                COALESCE(st.name, %s) AS group_label,
                COUNT(*) AS aggregate_count
            FROM {$referrals} r
            LEFT JOIN {$types} st ON st.id = r.service_type_id
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s";
        $params = [__('Unassigned', 'jm-referral-system'), $start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        $sql .= ' GROUP BY group_key, group_label ORDER BY aggregate_count DESC, group_label ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return $this->map_labelled_counts(is_array($rows) ? $rows : []);
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function get_referrals_by_workflow_stage(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals = Tables::referrals_table();
        $stages    = Tables::workflow_stages_table();

        $sql = "SELECT COALESCE(ws.id, 0) AS group_key,
                COALESCE(ws.name, %s) AS group_label,
                COUNT(*) AS aggregate_count
            FROM {$referrals} r
            LEFT JOIN {$stages} ws ON ws.id = r.workflow_stage_id
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s";
        $params = [__('Unassigned', 'jm-referral-system'), $start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        $sql .= ' GROUP BY group_key, group_label ORDER BY aggregate_count DESC, group_label ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return $this->map_labelled_counts(is_array($rows) ? $rows : []);
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function get_referrals_by_priority(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $table = Tables::referrals_table();
        $sql   = "SELECT COALESCE(NULLIF(r.priority, ''), %s) AS group_key,
                COUNT(*) AS aggregate_count
            FROM {$table} r
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s";
        $params = ['medium', $start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        $sql .= ' GROUP BY group_key ORDER BY aggregate_count DESC, group_key ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $mapped = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $key = (string) ($row['group_key'] ?? '');
            $mapped[] = [
                'key'   => $key,
                'label' => $key,
                'count' => (int) ($row['aggregate_count'] ?? 0),
            ];
        }

        return $mapped;
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function get_visits_by_status_in_range(
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null,
        ?array $visit_filters = null
    ): array {
        global $wpdb;

        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();
        $statuses  = [
            CareVisitService::STATUS_SCHEDULED,
            CareVisitService::STATUS_CONFIRMED,
            CareVisitService::STATUS_IN_PROGRESS,
            CareVisitService::STATUS_COMPLETED,
            CareVisitService::STATUS_MISSED,
            CareVisitService::STATUS_CANCELLED,
        ];
        $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));

        $sql = "SELECT v.visit_status AS group_key, COUNT(*) AS aggregate_count
            FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id";
        if (VisitDeliveryContext::needs_occupancy_join($visit_filters)) {
            $sql .= VisitDeliveryContext::occupancy_join_sql('r', 'o_ctx');
        }
        $sql   .= " WHERE v.visit_status IN ({$placeholders})
              AND v.visit_date >= %s
              AND v.visit_date <= %s";
        $params = array_merge($statuses, [$start_date, $end_date]);
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        VisitDeliveryContext::append_filters($sql, $params, $visit_filters, 'v', 'r', 'o_ctx');
        $sql .= ' GROUP BY group_key ORDER BY aggregate_count DESC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $mapped = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $key = (string) ($row['group_key'] ?? '');
            $mapped[] = [
                'key'   => $key,
                'label' => $key,
                'count' => (int) ($row['aggregate_count'] ?? 0),
            ];
        }

        return $mapped;
    }

    public function get_average_visit_duration_minutes(
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null,
        ?array $visit_filters = null
    ): ?float {
        global $wpdb;

        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT AVG(v.actual_duration_minutes) AS avg_duration
            FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id";
        if (VisitDeliveryContext::needs_occupancy_join($visit_filters)) {
            $sql .= VisitDeliveryContext::occupancy_join_sql('r', 'o_ctx');
        }
        $sql   .= ' WHERE v.visit_status = %s
              AND v.actual_duration_minutes IS NOT NULL
              AND v.actual_duration_minutes > 0
              AND v.visit_date >= %s
              AND v.visit_date <= %s';
        $params = [CareVisitService::STATUS_COMPLETED, $start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        VisitDeliveryContext::append_filters($sql, $params, $visit_filters, 'v', 'r', 'o_ctx');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $avg = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        if (null === $avg || '' === $avg) {
            return null;
        }

        return round((float) $avg, 1);
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function get_visits_by_type(
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null,
        ?array $visit_filters = null
    ): array {
        global $wpdb;

        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT COALESCE(NULLIF(TRIM(v.visit_type), ''), %s) AS group_key,
                COUNT(*) AS aggregate_count
            FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id";
        if (VisitDeliveryContext::needs_occupancy_join($visit_filters)) {
            $sql .= VisitDeliveryContext::occupancy_join_sql('r', 'o_ctx');
        }
        $sql   .= ' WHERE v.visit_date >= %s
              AND v.visit_date <= %s';
        $params = [__('Unspecified', 'jm-referral-system'), $start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        VisitDeliveryContext::append_filters($sql, $params, $visit_filters, 'v', 'r', 'o_ctx');
        $sql .= ' GROUP BY group_key ORDER BY aggregate_count DESC, group_key ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return $this->map_self_labelled_counts(is_array($rows) ? $rows : []);
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function get_medication_administrations_by_status(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $admins    = Tables::medication_administrations_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT a.administration_status AS group_key, COUNT(*) AS aggregate_count
            FROM {$admins} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE DATE(COALESCE(a.administered_time, a.created_at)) >= %s
              AND DATE(COALESCE(a.administered_time, a.created_at)) <= %s";
        $params = [$start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        $sql .= ' GROUP BY group_key ORDER BY aggregate_count DESC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return $this->map_self_labelled_counts(is_array($rows) ? $rows : []);
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function get_medication_exceptions_by_reason(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $admins    = Tables::medication_administrations_table();
        $referrals = Tables::referrals_table();
        $statuses  = MedicationAdministrationService::exception_statuses();

        if ([] === $statuses) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
        $sql          = "SELECT COALESCE(NULLIF(a.reason_code, ''), %s) AS group_key,
                COUNT(*) AS aggregate_count
            FROM {$admins} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE a.administration_status IN ({$placeholders})
              AND DATE(COALESCE(a.administered_time, a.created_at)) >= %s
              AND DATE(COALESCE(a.administered_time, a.created_at)) <= %s";
        $params = array_merge(
            [__('Unspecified', 'jm-referral-system')],
            $statuses,
            [$start_date, $end_date]
        );
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        $sql .= ' GROUP BY group_key ORDER BY aggregate_count DESC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return $this->map_self_labelled_counts(is_array($rows) ? $rows : []);
    }

    /**
     * @return array<int, array{month: string, count: int}>
     */
    public function get_medication_exceptions_by_month(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $admins    = Tables::medication_administrations_table();
        $referrals = Tables::referrals_table();
        $statuses  = MedicationAdministrationService::exception_statuses();

        if ([] === $statuses) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
        $sql          = "SELECT DATE_FORMAT(COALESCE(a.administered_time, a.created_at), '%%Y-%%m') AS month_key,
                COUNT(*) AS aggregate_count
            FROM {$admins} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE a.administration_status IN ({$placeholders})
              AND DATE(COALESCE(a.administered_time, a.created_at)) >= %s
              AND DATE(COALESCE(a.administered_time, a.created_at)) <= %s";
        $params = array_merge($statuses, [$start_date, $end_date]);
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        $sql .= ' GROUP BY month_key ORDER BY month_key ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return $this->map_keyed_counts(is_array($rows) ? $rows : [], 'month_key');
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function get_visit_tasks_by_status(
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null,
        ?array $visit_filters = null
    ): array {
        global $wpdb;

        $tasks     = Tables::visit_tasks_table();
        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT t.task_status AS group_key, COUNT(*) AS aggregate_count
            FROM {$tasks} t
            INNER JOIN {$visits} v ON v.id = t.visit_id
            INNER JOIN {$referrals} r ON r.id = v.referral_id";
        if (VisitDeliveryContext::needs_occupancy_join($visit_filters)) {
            $sql .= VisitDeliveryContext::occupancy_join_sql('r', 'o_ctx');
        }
        $sql   .= ' WHERE v.visit_date >= %s
              AND v.visit_date <= %s';
        $params = [$start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        VisitDeliveryContext::append_filters($sql, $params, $visit_filters, 'v', 'r', 'o_ctx');
        $sql .= ' GROUP BY group_key ORDER BY aggregate_count DESC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return $this->map_self_labelled_counts(is_array($rows) ? $rows : []);
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function get_top_task_exception_types(
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null,
        int $limit = 10,
        ?array $visit_filters = null
    ): array {
        global $wpdb;

        $tasks     = Tables::visit_tasks_table();
        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();
        $limit     = max(1, min(50, $limit));

        $sql = "SELECT t.task_name AS group_key, COUNT(*) AS aggregate_count
            FROM {$tasks} t
            INNER JOIN {$visits} v ON v.id = t.visit_id
            INNER JOIN {$referrals} r ON r.id = v.referral_id";
        if (VisitDeliveryContext::needs_occupancy_join($visit_filters)) {
            $sql .= VisitDeliveryContext::occupancy_join_sql('r', 'o_ctx');
        }
        $sql   .= ' WHERE t.task_status IN (%s, %s)
              AND v.visit_date >= %s
              AND v.visit_date <= %s';
        $params = [
            VisitTaskService::STATUS_NOT_COMPLETED,
            VisitTaskService::STATUS_REFUSED,
            $start_date,
            $end_date,
        ];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        VisitDeliveryContext::append_filters($sql, $params, $visit_filters, 'v', 'r', 'o_ctx');
        $sql .= ' GROUP BY group_key ORDER BY aggregate_count DESC, group_key ASC LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return $this->map_self_labelled_counts(is_array($rows) ? $rows : []);
    }

    /**
     * @return array<int, array{user_id: int, count: int}>
     */
    public function get_visits_completed_per_staff(
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null,
        ?array $visit_filters = null
    ): array {
        global $wpdb;

        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT v.assigned_user_id AS user_id, COUNT(*) AS aggregate_count
            FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id";
        if (VisitDeliveryContext::needs_occupancy_join($visit_filters)) {
            $sql .= VisitDeliveryContext::occupancy_join_sql('r', 'o_ctx');
        }
        $sql   .= ' WHERE v.visit_status = %s
              AND v.assigned_user_id IS NOT NULL
              AND v.assigned_user_id > 0
              AND v.visit_date >= %s
              AND v.visit_date <= %s';
        $params = [CareVisitService::STATUS_COMPLETED, $start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        VisitDeliveryContext::append_filters($sql, $params, $visit_filters, 'v', 'r', 'o_ctx');
        $sql .= ' GROUP BY user_id ORDER BY aggregate_count DESC, user_id ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return $this->map_user_counts(is_array($rows) ? $rows : []);
    }

    /**
     * @return array<int, array{user_id: int, count: int}>
     */
    public function get_medication_administrations_per_staff(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $admins    = Tables::medication_administrations_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT a.administered_by AS user_id, COUNT(*) AS aggregate_count
            FROM {$admins} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE a.administered_by IS NOT NULL
              AND a.administered_by > 0
              AND DATE(COALESCE(a.administered_time, a.created_at)) >= %s
              AND DATE(COALESCE(a.administered_time, a.created_at)) <= %s";
        $params = [$start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        $sql .= ' GROUP BY user_id ORDER BY aggregate_count DESC, user_id ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return $this->map_user_counts(is_array($rows) ? $rows : []);
    }

    /**
     * Completed visits awaiting manager review (snapshot).
     *
     * @param array{care_context: string, home_id: int, is_active: bool}|null $visit_filters
     */
    public function count_outstanding_manager_reviews(
        ?int $access_assigned_to = null,
        ?array $visit_filters = null
    ): int {
        global $wpdb;

        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT COUNT(*) FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id";
        if (VisitDeliveryContext::needs_occupancy_join($visit_filters)) {
            $sql .= VisitDeliveryContext::occupancy_join_sql('r', 'o_ctx');
        }
        $sql   .= " WHERE v.visit_status = %s
              AND v.visit_outcome IS NOT NULL
              AND v.visit_outcome != ''
              AND (v.reviewed_at IS NULL OR v.reviewed_at = '')";
        $params = [CareVisitService::STATUS_COMPLETED];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        VisitDeliveryContext::append_filters($sql, $params, $visit_filters, 'v', 'r', 'o_ctx');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * Active care-team assignment rows (snapshot).
     */
    public function count_active_care_team_assignments(?int $access_assigned_to = null): int
    {
        global $wpdb;

        $team      = Tables::care_team_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT COUNT(*) FROM {$team} ct
            INNER JOIN {$referrals} r ON r.id = ct.referral_id
            WHERE ct.assignment_status = %s";
        $params = ['active'];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * Active care plans with overdue review_date (snapshot).
     */
    public function count_overdue_care_plan_reviews(?int $access_assigned_to = null): int
    {
        global $wpdb;

        $plans     = Tables::referral_care_plans_table();
        $referrals = Tables::referrals_table();
        $today     = current_time('Y-m-d');

        $sql = "SELECT COUNT(*) FROM {$plans} p
            INNER JOIN {$referrals} r ON r.id = p.referral_id
            WHERE p.plan_status = %s
              AND p.review_date IS NOT NULL
              AND p.review_date != ''
              AND p.review_date < %s";
        $params = ['active', $today];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * High/urgent priority referrals still open (snapshot).
     */
    public function count_high_priority_referrals(?int $access_assigned_to = null): int
    {
        global $wpdb;

        $table = Tables::referrals_table();
        $sql   = "SELECT COUNT(*) FROM {$table} r
            WHERE r.priority IN (%s, %s)
              AND r.status NOT IN (%s, %s)";
        $params = ['high', 'urgent', 'completed', 'cancelled'];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * Average days from referral creation to assessment completion in range.
     */
    public function get_average_assessment_turnaround_days(string $start_date, string $end_date, ?int $access_assigned_to = null): ?float
    {
        global $wpdb;

        $assessments = Tables::referral_assessments_table();
        $referrals   = Tables::referrals_table();

        $sql = "SELECT AVG(
                DATEDIFF(
                    COALESCE(a.assessment_date, DATE(a.created_at)),
                    DATE(r.created_at)
                )
            ) AS avg_days
            FROM {$assessments} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE a.outcome <> %s
              AND COALESCE(a.assessment_date, DATE(a.created_at)) >= %s
              AND COALESCE(a.assessment_date, DATE(a.created_at)) <= %s
              AND COALESCE(a.assessment_date, DATE(a.created_at)) >= DATE(r.created_at)";
        $params = ['pending', $start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $avg = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        if (null === $avg || '' === $avg) {
            return null;
        }

        return round((float) $avg, 1);
    }

    /**
     * @param array<int, string>                                                 $statuses
     * @param array{care_context: string, home_id: int, is_active: bool}|null $visit_filters
     */
    private function count_visits_by_statuses_in_range(
        array $statuses,
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null,
        ?array $visit_filters = null
    ): int {
        global $wpdb;

        if ([] === $statuses) {
            return 0;
        }

        $visits       = Tables::care_visits_table();
        $referrals    = Tables::referrals_table();
        $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));

        $sql = "SELECT COUNT(*) FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id";
        if (VisitDeliveryContext::needs_occupancy_join($visit_filters)) {
            $sql .= VisitDeliveryContext::occupancy_join_sql('r', 'o_ctx');
        }
        $sql   .= " WHERE v.visit_status IN ({$placeholders})
              AND v.visit_date >= %s
              AND v.visit_date <= %s";
        $params = array_merge($statuses, [$start_date, $end_date]);
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        VisitDeliveryContext::append_filters($sql, $params, $visit_filters, 'v', 'r', 'o_ctx');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private function append_referral_access(string &$sql, array &$params, ?int $access_assigned_to, string $alias): void
    {
        $sql .= " AND {$alias}.archived_at IS NULL";

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $sql     .= " AND {$alias}.assigned_to = %d";
            $params[] = $access_assigned_to;
        }
    }

    /**
     * AccessPolicy assigned-to scope only (no archived_at filter).
     *
     * Used for period placement-movement reporting so completed/cancelled/archived
     * care records still contribute historical events recorded in the period.
     *
     * @param array<int|string, mixed> $params
     */
    private function append_referral_assigned_scope(
        string &$sql,
        array &$params,
        ?int $access_assigned_to,
        string $alias
    ): void {
        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $sql     .= " AND {$alias}.assigned_to = %d";
            $params[] = $access_assigned_to;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{month: string, count: int}>
     */
    private function map_keyed_counts(array $rows, string $key_field): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = [
                'month' => (string) ($row[$key_field] ?? ''),
                'count' => (int) ($row['aggregate_count'] ?? 0),
            ];
        }

        return $mapped;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function map_labelled_counts(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = [
                'key'   => (string) ($row['group_key'] ?? ''),
                'label' => (string) ($row['group_label'] ?? ''),
                'count' => (int) ($row['aggregate_count'] ?? 0),
            ];
        }

        return $mapped;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function map_self_labelled_counts(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $key = (string) ($row['group_key'] ?? '');
            $mapped[] = [
                'key'   => $key,
                'label' => $key,
                'count' => (int) ($row['aggregate_count'] ?? 0),
            ];
        }

        return $mapped;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{user_id: int, count: int}>
     */
    private function map_user_counts(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'count'   => (int) ($row['aggregate_count'] ?? 0),
            ];
        }

        return $mapped;
    }
}
