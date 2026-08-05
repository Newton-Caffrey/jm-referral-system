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

    public function count_visits_scheduled_in_range(string $start_date, string $end_date, ?int $access_assigned_to = null): int
    {
        return $this->count_visits_by_statuses_in_range(
            [
                CareVisitService::STATUS_SCHEDULED,
                CareVisitService::STATUS_CONFIRMED,
                CareVisitService::STATUS_IN_PROGRESS,
            ],
            $start_date,
            $end_date,
            $access_assigned_to
        );
    }

    public function count_visits_completed_in_range(string $start_date, string $end_date, ?int $access_assigned_to = null): int
    {
        return $this->count_visits_by_statuses_in_range(
            [CareVisitService::STATUS_COMPLETED],
            $start_date,
            $end_date,
            $access_assigned_to
        );
    }

    public function count_visits_missed_in_range(string $start_date, string $end_date, ?int $access_assigned_to = null): int
    {
        return $this->count_visits_by_statuses_in_range(
            [CareVisitService::STATUS_MISSED],
            $start_date,
            $end_date,
            $access_assigned_to
        );
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
    public function get_visits_by_status_in_range(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
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
            INNER JOIN {$referrals} r ON r.id = v.referral_id
            WHERE v.visit_status IN ({$placeholders})
              AND v.visit_date >= %s
              AND v.visit_date <= %s";
        $params = array_merge($statuses, [$start_date, $end_date]);
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
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

    public function get_average_visit_duration_minutes(string $start_date, string $end_date, ?int $access_assigned_to = null): ?float
    {
        global $wpdb;

        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT AVG(v.actual_duration_minutes) AS avg_duration
            FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id
            WHERE v.visit_status = %s
              AND v.actual_duration_minutes IS NOT NULL
              AND v.actual_duration_minutes > 0
              AND v.visit_date >= %s
              AND v.visit_date <= %s";
        $params = [CareVisitService::STATUS_COMPLETED, $start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

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
    public function get_visits_by_type(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT COALESCE(NULLIF(TRIM(v.visit_type), ''), %s) AS group_key,
                COUNT(*) AS aggregate_count
            FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id
            WHERE v.visit_date >= %s
              AND v.visit_date <= %s";
        $params = [__('Unspecified', 'jm-referral-system'), $start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
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
    public function get_visit_tasks_by_status(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $tasks     = Tables::visit_tasks_table();
        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT t.task_status AS group_key, COUNT(*) AS aggregate_count
            FROM {$tasks} t
            INNER JOIN {$visits} v ON v.id = t.visit_id
            INNER JOIN {$referrals} r ON r.id = v.referral_id
            WHERE v.visit_date >= %s
              AND v.visit_date <= %s";
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
    public function get_top_task_exception_types(string $start_date, string $end_date, ?int $access_assigned_to = null, int $limit = 10): array
    {
        global $wpdb;

        $tasks     = Tables::visit_tasks_table();
        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();
        $limit     = max(1, min(50, $limit));

        $sql = "SELECT t.task_name AS group_key, COUNT(*) AS aggregate_count
            FROM {$tasks} t
            INNER JOIN {$visits} v ON v.id = t.visit_id
            INNER JOIN {$referrals} r ON r.id = v.referral_id
            WHERE t.task_status IN (%s, %s)
              AND v.visit_date >= %s
              AND v.visit_date <= %s";
        $params = [
            VisitTaskService::STATUS_REFUSED,
            VisitTaskService::STATUS_NOT_COMPLETED,
            $start_date,
            $end_date,
        ];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
        $sql .= ' GROUP BY group_key ORDER BY aggregate_count DESC, group_key ASC LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return $this->map_self_labelled_counts(is_array($rows) ? $rows : []);
    }

    /**
     * @return array<int, array{user_id: int, count: int}>
     */
    public function get_visits_completed_per_staff(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT v.assigned_user_id AS user_id, COUNT(*) AS aggregate_count
            FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id
            WHERE v.visit_status = %s
              AND v.assigned_user_id IS NOT NULL
              AND v.assigned_user_id > 0
              AND v.visit_date >= %s
              AND v.visit_date <= %s";
        $params = [CareVisitService::STATUS_COMPLETED, $start_date, $end_date];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');
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
     */
    public function count_outstanding_manager_reviews(?int $access_assigned_to = null): int
    {
        global $wpdb;

        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();

        $sql = "SELECT COUNT(*) FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id
            WHERE v.visit_status = %s
              AND v.visit_outcome IS NOT NULL
              AND v.visit_outcome != ''
              AND (v.reviewed_at IS NULL OR v.reviewed_at = '')";
        $params = [CareVisitService::STATUS_COMPLETED];
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

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
     * @param array<int, string> $statuses
     */
    private function count_visits_by_statuses_in_range(
        array $statuses,
        string $start_date,
        string $end_date,
        ?int $access_assigned_to = null
    ): int {
        global $wpdb;

        if ([] === $statuses) {
            return 0;
        }

        $visits       = Tables::care_visits_table();
        $referrals    = Tables::referrals_table();
        $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));

        $sql = "SELECT COUNT(*) FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id
            WHERE v.visit_status IN ({$placeholders})
              AND v.visit_date >= %s
              AND v.visit_date <= %s";
        $params = array_merge($statuses, [$start_date, $end_date]);
        $this->append_referral_access($sql, $params, $access_assigned_to, 'r');

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
