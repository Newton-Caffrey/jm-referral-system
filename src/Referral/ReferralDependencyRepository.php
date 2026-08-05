<?php

namespace JMReferral\Referral;

use JMReferral\Database\Tables;

/**
 * Focused COUNT / cleanup queries for referral-owned dependent records.
 */
class ReferralDependencyRepository
{
    /**
     * Counts notes for a referral.
     */
    public function count_notes(int $referral_id): int
    {
        return $this->count_by_referral_id(Tables::referral_notes_table(), $referral_id);
    }

    /**
     * Counts documents for a referral.
     */
    public function count_documents(int $referral_id): int
    {
        return $this->count_by_referral_id(Tables::referral_documents_table(), $referral_id);
    }

    /**
     * Counts assessment rows for a referral.
     */
    public function count_assessments(int $referral_id): int
    {
        return $this->count_by_referral_id(Tables::referral_assessments_table(), $referral_id);
    }

    /**
     * Counts care plans for a referral.
     */
    public function count_care_plans(int $referral_id): int
    {
        return $this->count_by_referral_id(Tables::referral_care_plans_table(), $referral_id);
    }

    /**
     * Counts care-plan versions linked via the referral's care plans.
     */
    public function count_care_plan_versions(int $referral_id): int
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return 0;
        }

        $versions   = Tables::care_plan_versions_table();
        $care_plans = Tables::referral_care_plans_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$versions} v
                INNER JOIN {$care_plans} cp ON cp.id = v.care_plan_id
                WHERE cp.referral_id = %d",
                $referral_id
            )
        );

        return (int) $count;
    }

    /**
     * Counts care-plan reviews linked via the referral's care plans.
     */
    public function count_care_plan_reviews(int $referral_id): int
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return 0;
        }

        $reviews    = Tables::care_plan_reviews_table();
        $care_plans = Tables::referral_care_plans_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$reviews} r
                INNER JOIN {$care_plans} cp ON cp.id = r.care_plan_id
                WHERE cp.referral_id = %d",
                $referral_id
            )
        );

        return (int) $count;
    }

    /**
     * Counts care-team assignments for a referral.
     */
    public function count_care_team(int $referral_id): int
    {
        return $this->count_by_referral_id(Tables::care_team_table(), $referral_id);
    }

    /**
     * Counts visit schedules for a referral.
     */
    public function count_schedules(int $referral_id): int
    {
        return $this->count_by_referral_id(Tables::visit_schedules_table(), $referral_id);
    }

    /**
     * Counts visits for a referral.
     */
    public function count_visits(int $referral_id): int
    {
        return $this->count_by_referral_id(Tables::care_visits_table(), $referral_id);
    }

    /**
     * Counts visit tasks linked via the referral's visits.
     */
    public function count_visit_tasks(int $referral_id): int
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return 0;
        }

        $tasks  = Tables::visit_tasks_table();
        $visits = Tables::care_visits_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tasks} t
                INNER JOIN {$visits} v ON v.id = t.visit_id
                WHERE v.referral_id = %d",
                $referral_id
            )
        );

        return (int) $count;
    }

    /**
     * Counts medications for a referral.
     */
    public function count_medications(int $referral_id): int
    {
        return $this->count_by_referral_id(Tables::medications_table(), $referral_id);
    }

    /**
     * Counts medication administrations for a referral.
     */
    public function count_medication_administrations(int $referral_id): int
    {
        return $this->count_by_referral_id(Tables::medication_administrations_table(), $referral_id);
    }

    /**
     * Counts activity entries excluding the initial "created" bootstrap row(s).
     */
    public function count_blocking_activity(int $referral_id): int
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return 0;
        }

        $table = Tables::referral_activity_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                WHERE referral_id = %d
                  AND action <> %s",
                $referral_id,
                'created'
            )
        );

        return (int) $count;
    }

    /**
     * Counts all activity entries for a referral (including created).
     */
    public function count_all_activity(int $referral_id): int
    {
        return $this->count_by_referral_id(Tables::referral_activity_table(), $referral_id);
    }

    /**
     * Deletes activity rows for a referral (safe empty-referral cleanup only).
     */
    public function delete_activity_for_referral(int $referral_id): bool
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return false;
        }

        $result = $wpdb->delete(
            Tables::referral_activity_table(),
            ['referral_id' => $referral_id],
            ['%d']
        );

        return false !== $result;
    }

    /**
     * @return array{
     *     orphan_notes: int,
     *     orphan_documents: int,
     *     orphan_assessments: int,
     *     orphan_care_plans: int,
     *     orphan_care_plan_versions: int,
     *     orphan_care_plan_reviews: int,
     *     orphan_care_team: int,
     *     orphan_schedules: int,
     *     orphan_visits: int,
     *     orphan_visit_tasks: int,
     *     orphan_medications: int,
     *     orphan_medication_administrations: int,
     *     orphan_activity: int,
     *     documents_missing_private_files: int,
     *     visits_missing_schedule: int,
     *     visits_missing_care_plan: int,
     *     visits_missing_team_user: int,
     *     administrations_missing_medication: int,
     *     administrations_missing_visit: int,
     *     visit_tasks_missing_visit: int
     * }
     */
    public function integrity_counts(): array
    {
        global $wpdb;

        $referrals = Tables::referrals_table();

        return [
            'orphan_notes'                       => $this->count_orphans(Tables::referral_notes_table(), $referrals),
            'orphan_documents'                   => $this->count_orphans(Tables::referral_documents_table(), $referrals),
            'orphan_assessments'                 => $this->count_orphans(Tables::referral_assessments_table(), $referrals),
            'orphan_care_plans'                  => $this->count_orphans(Tables::referral_care_plans_table(), $referrals),
            'orphan_care_plan_versions'          => $this->count_orphan_care_plan_children(
                Tables::care_plan_versions_table(),
                'care_plan_id'
            ),
            'orphan_care_plan_reviews'           => $this->count_orphan_care_plan_children(
                Tables::care_plan_reviews_table(),
                'care_plan_id'
            ),
            'orphan_care_team'                   => $this->count_orphans(Tables::care_team_table(), $referrals),
            'orphan_schedules'                   => $this->count_orphans(Tables::visit_schedules_table(), $referrals),
            'orphan_visits'                      => $this->count_orphans(Tables::care_visits_table(), $referrals),
            'orphan_visit_tasks'                 => $this->count_orphan_visit_tasks(),
            'orphan_medications'                 => $this->count_orphans(Tables::medications_table(), $referrals),
            'orphan_medication_administrations'  => $this->count_orphans(
                Tables::medication_administrations_table(),
                $referrals
            ),
            'orphan_activity'                    => $this->count_orphans(Tables::referral_activity_table(), $referrals),
            'documents_missing_private_files'    => $this->count_documents_missing_private_files(),
            'visits_missing_schedule'            => $this->count_visits_missing_fk('schedule_id', Tables::visit_schedules_table()),
            'visits_missing_care_plan'           => $this->count_visits_missing_fk('care_plan_id', Tables::referral_care_plans_table()),
            'visits_missing_team_user'           => $this->count_visits_missing_team_user(),
            'administrations_missing_medication' => $this->count_mar_missing_fk('medication_id', Tables::medications_table()),
            'administrations_missing_visit'      => $this->count_mar_missing_fk('visit_id', Tables::care_visits_table()),
            'visit_tasks_missing_visit'          => $this->count_visit_tasks_missing_visit(),
        ];
    }

    private function count_by_referral_id(string $table, int $referral_id): int
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE referral_id = %d",
                $referral_id
            )
        );

        return (int) $count;
    }

    private function count_orphans(string $child_table, string $referrals_table): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are trusted.
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$child_table} c
            LEFT JOIN {$referrals_table} r ON r.id = c.referral_id
            WHERE r.id IS NULL"
        );

        return (int) $count;
    }

    private function count_orphan_care_plan_children(string $child_table, string $fk_column): int
    {
        global $wpdb;

        $care_plans = Tables::referral_care_plans_table();
        $fk_column  = preg_replace('/[^a-z0-9_]/', '', $fk_column) ?? '';

        if ('' === $fk_column) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$child_table} c
            LEFT JOIN {$care_plans} cp ON cp.id = c.{$fk_column}
            WHERE cp.id IS NULL"
        );

        return (int) $count;
    }

    private function count_orphan_visit_tasks(): int
    {
        global $wpdb;

        $tasks    = Tables::visit_tasks_table();
        $visits   = Tables::care_visits_table();
        $referrals = Tables::referrals_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are trusted.
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tasks} t
            LEFT JOIN {$visits} v ON v.id = t.visit_id
            LEFT JOIN {$referrals} r ON r.id = v.referral_id
            WHERE v.id IS NULL OR r.id IS NULL"
        );

        return (int) $count;
    }

    private function count_documents_missing_private_files(): int
    {
        global $wpdb;

        $table   = Tables::referral_documents_table();
        $storage = new \JMReferral\Documents\PrivateDocumentStorage();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, relative_path, storage_type FROM {$table}
                WHERE storage_type = %s
                  AND relative_path IS NOT NULL
                  AND relative_path <> ''",
                \JMReferral\Documents\PrivateDocumentStorage::STORAGE_PRIVATE
            ),
            ARRAY_A
        );

        if (! is_array($rows) || [] === $rows) {
            return 0;
        }

        $missing = 0;

        foreach ($rows as $row) {
            $path = $storage->resolve_safe_path((string) ($row['relative_path'] ?? ''));
            if (null === $path || ! is_readable($path) || ! is_file($path)) {
                ++$missing;
            }
        }

        return $missing;
    }

    private function count_visits_missing_fk(string $column, string $parent_table): int
    {
        global $wpdb;

        $visits = Tables::care_visits_table();
        $column = preg_replace('/[^a-z0-9_]/', '', $column) ?? '';

        if ('' === $column) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$visits} v
            LEFT JOIN {$parent_table} p ON p.id = v.{$column}
            WHERE v.{$column} IS NOT NULL
              AND v.{$column} <> 0
              AND p.id IS NULL"
        );

        return (int) $count;
    }

    private function count_visits_missing_team_user(): int
    {
        global $wpdb;

        $visits = Tables::care_visits_table();
        $users  = $wpdb->users;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are trusted.
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$visits} v
            LEFT JOIN {$users} u ON u.ID = v.assigned_user_id
            WHERE v.assigned_user_id IS NOT NULL
              AND v.assigned_user_id <> 0
              AND u.ID IS NULL"
        );

        return (int) $count;
    }

    private function count_mar_missing_fk(string $column, string $parent_table): int
    {
        global $wpdb;

        $mar    = Tables::medication_administrations_table();
        $column = preg_replace('/[^a-z0-9_]/', '', $column) ?? '';

        if ('' === $column) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$mar} m
            LEFT JOIN {$parent_table} p ON p.id = m.{$column}
            WHERE m.{$column} IS NOT NULL
              AND m.{$column} <> 0
              AND p.id IS NULL"
        );

        return (int) $count;
    }

    private function count_visit_tasks_missing_visit(): int
    {
        global $wpdb;

        $tasks  = Tables::visit_tasks_table();
        $visits = Tables::care_visits_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are trusted.
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tasks} t
            LEFT JOIN {$visits} v ON v.id = t.visit_id
            WHERE v.id IS NULL"
        );

        return (int) $count;
    }
}
