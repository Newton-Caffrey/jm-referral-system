<?php

namespace JMReferral\Reports;

use JMReferral\Database\Tables;
use JMReferral\Medication\MedicationAdministrationService;
use JMReferral\Visits\CareVisitService;

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

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $sql     .= ' AND r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted; query prepared.
        $count = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return (int) $count;
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

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $sql     .= ' AND r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return (int) $count;
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

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $sql     .= ' AND r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return (int) $count;
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

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $sql     .= ' AND r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return (int) $count;
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

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $sql     .= ' AND r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return (int) $count;
    }

    /**
     * Visits scheduled / confirmed / in progress with visit_date in range.
     */
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

    /**
     * Visits completed with visit_date in range.
     */
    public function count_visits_completed_in_range(string $start_date, string $end_date, ?int $access_assigned_to = null): int
    {
        return $this->count_visits_by_statuses_in_range(
            [CareVisitService::STATUS_COMPLETED],
            $start_date,
            $end_date,
            $access_assigned_to
        );
    }

    /**
     * Visits missed with visit_date in range.
     */
    public function count_visits_missed_in_range(string $start_date, string $end_date, ?int $access_assigned_to = null): int
    {
        return $this->count_visits_by_statuses_in_range(
            [CareVisitService::STATUS_MISSED],
            $start_date,
            $end_date,
            $access_assigned_to
        );
    }

    /**
     * Medication administrations recorded in range.
     */
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

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $sql     .= ' AND r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return (int) $count;
    }

    /**
     * Medication administration exceptions in range.
     */
    public function count_medication_exceptions_in_range(string $start_date, string $end_date, ?int $access_assigned_to = null): int
    {
        global $wpdb;

        $admins    = Tables::medication_administrations_table();
        $referrals = Tables::referrals_table();
        $statuses  = MedicationAdministrationService::exception_statuses();

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

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $sql     .= ' AND r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return (int) $count;
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

        $visits    = Tables::care_visits_table();
        $referrals = Tables::referrals_table();
        $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));

        $sql = "SELECT COUNT(*) FROM {$visits} v
            INNER JOIN {$referrals} r ON r.id = v.referral_id
            WHERE v.visit_status IN ({$placeholders})
              AND v.visit_date >= %s
              AND v.visit_date <= %s";
        $params = array_merge($statuses, [$start_date, $end_date]);

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $sql     .= ' AND r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return (int) $count;
    }
}
