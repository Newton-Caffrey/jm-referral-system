<?php

namespace JMReferral\Reports;

use JMReferral\Assessment\ReferralAssessmentService;
use JMReferral\Database\Tables;
use JMReferral\LaDecision\LaDecision;
use JMReferral\PackageCost\PackageCost;
use JMReferral\Pipeline\PipelineStage;

/**
 * Acquisition cohort aggregates. Includes archived historical records.
 * AccessPolicy assigned-to scope only (no archived_at exclusion).
 *
 * Structured Phase 3 identity is NOT current is_pipeline_stage.
 * Genuine Phase 3 referrals have stage-history change_type = created
 * (written by ReferralPipelineService::record_pipeline_started).
 * Legacy referrals overridden onto a canonical stage remain Legacy for reporting.
 */
class AcquisitionReportRepository
{
    /**
     * @return array{
     *     received_total: int,
     *     received_canonical: int,
     *     received_legacy: int
     * }
     */
    public function count_cohort(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals = Tables::referrals_table();
        $identity  = $this->structured_phase3_exists_sql('r');

        $sql = "SELECT
                COUNT(*) AS received_total,
                SUM(CASE WHEN {$identity['sql']} THEN 1 ELSE 0 END) AS received_canonical,
                SUM(CASE WHEN {$identity['sql']} THEN 0 ELSE 1 END) AS received_legacy
            FROM {$referrals} r
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s";
        $params = array_merge(
            $identity['params'],
            $identity['params'],
            [$start_date, $end_date]
        );
        $this->append_assigned_scope($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$params), ARRAY_A);

        return [
            'received_total'     => (int) ($row['received_total'] ?? 0),
            'received_canonical' => (int) ($row['received_canonical'] ?? 0),
            'received_legacy'    => (int) ($row['received_legacy'] ?? 0),
        ];
    }

    /**
     * Funnel / milestone counts for the structured Phase 3 referral cohort.
     *
     * @return array<string, int>
     */
    public function get_funnel_counts(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals   = Tables::referrals_table();
        $stages      = Tables::workflow_stages_table();
        $assessments = Tables::referral_assessments_table();
        $costs       = Tables::referral_package_costs_table();
        $decisions   = Tables::referral_la_decisions_table();
        $identity    = $this->structured_phase3_exists_sql('r');

        $suitable     = ReferralAssessmentService::OUTCOME_SUITABLE;
        $with_cond    = ReferralAssessmentService::OUTCOME_SUITABLE_WITH_CONDITIONS;
        $not_suitable = ReferralAssessmentService::OUTCOME_NOT_SUITABLE;
        $pc_sent      = PackageCost::STATUS_SENT;
        $approved     = LaDecision::DECISION_APPROVED;
        $declined     = LaDecision::DECISION_DECLINED;

        $care_commenced_slug = PipelineStage::CARE_COMMENCED;
        $declined_slug       = PipelineStage::DECLINED;
        $np_slug             = PipelineStage::NOT_PROCEEDING;

        $active_slugs = $this->active_pipeline_slugs();
        $active_in    = $this->placeholders($active_slugs);

        $sql = "SELECT
                COUNT(*) AS received,
                SUM(CASE WHEN r.interest_expressed_at IS NOT NULL
                    AND r.interest_expressed_at <> '0000-00-00 00:00:00' THEN 1 ELSE 0 END) AS interest_expressed,
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM {$assessments} a
                    WHERE a.referral_id = r.id
                      AND a.outcome IN (%s, %s, %s)
                ) THEN 1 ELSE 0 END) AS assessments_completed,
                SUM(CASE WHEN pc.id IS NOT NULL
                    AND (pc.status = %s OR (pc.sent_at IS NOT NULL AND pc.sent_at <> '0000-00-00 00:00:00'))
                    THEN 1 ELSE 0 END) AS package_costs_sent,
                SUM(CASE WHEN d.decision = %s THEN 1 ELSE 0 END) AS la_approved,
                SUM(CASE WHEN ws.slug = %s OR d.decision = %s THEN 1 ELSE 0 END) AS la_declined,
                SUM(CASE WHEN ws.slug = %s THEN 1 ELSE 0 END) AS not_proceeding,
                SUM(CASE WHEN (
                    (r.care_commenced_at IS NOT NULL AND r.care_commenced_at <> '0000-00-00 00:00:00')
                    OR ws.slug = %s
                ) THEN 1 ELSE 0 END) AS care_commenced,
                SUM(CASE WHEN ws.slug IN ({$active_in}) THEN 1 ELSE 0 END) AS still_active
            FROM {$referrals} r
            LEFT JOIN {$stages} ws ON ws.id = r.workflow_stage_id
            LEFT JOIN (
                SELECT pc1.*
                FROM {$costs} pc1
                INNER JOIN (
                    SELECT referral_id, MAX(id) AS max_id
                    FROM {$costs}
                    GROUP BY referral_id
                ) latest ON latest.max_id = pc1.id
            ) pc ON pc.referral_id = r.id
            LEFT JOIN (
                SELECT d1.*
                FROM {$decisions} d1
                INNER JOIN (
                    SELECT referral_id, MAX(id) AS max_id
                    FROM {$decisions}
                    GROUP BY referral_id
                ) dlatest ON dlatest.max_id = d1.id
            ) d ON d.referral_id = r.id
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s
              AND {$identity['sql']}";

        $params = array_merge(
            [$suitable, $with_cond, $not_suitable, $pc_sent, $approved, $declined_slug, $declined, $np_slug, $care_commenced_slug],
            $active_slugs,
            [$start_date, $end_date],
            $identity['params']
        );
        $this->append_assigned_scope($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$params), ARRAY_A);

        return [
            'received'               => (int) ($row['received'] ?? 0),
            'interest_expressed'     => (int) ($row['interest_expressed'] ?? 0),
            'assessments_completed'  => (int) ($row['assessments_completed'] ?? 0),
            'package_costs_sent'     => (int) ($row['package_costs_sent'] ?? 0),
            'la_approved'            => (int) ($row['la_approved'] ?? 0),
            'la_declined'            => (int) ($row['la_declined'] ?? 0),
            'not_proceeding'         => (int) ($row['not_proceeding'] ?? 0),
            'care_commenced'         => (int) ($row['care_commenced'] ?? 0),
            'still_active'           => (int) ($row['still_active'] ?? 0),
        ];
    }

    /**
     * Outcome summary (mutually exclusive current acquisition outcome).
     *
     * @return array<string, int>
     */
    public function get_outcome_counts(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals = Tables::referrals_table();
        $stages    = Tables::workflow_stages_table();
        $identity  = $this->structured_phase3_exists_sql('r');

        $care = PipelineStage::CARE_COMMENCED;
        $dec  = PipelineStage::DECLINED;
        $np   = PipelineStage::NOT_PROCEEDING;

        $sql = "SELECT
                SUM(CASE WHEN (
                    (r.care_commenced_at IS NOT NULL AND r.care_commenced_at <> '0000-00-00 00:00:00')
                    OR ws.slug = %s
                ) THEN 1 ELSE 0 END) AS care_commenced,
                SUM(CASE WHEN NOT (
                    (r.care_commenced_at IS NOT NULL AND r.care_commenced_at <> '0000-00-00 00:00:00')
                    OR ws.slug = %s
                ) AND ws.slug = %s THEN 1 ELSE 0 END) AS declined,
                SUM(CASE WHEN NOT (
                    (r.care_commenced_at IS NOT NULL AND r.care_commenced_at <> '0000-00-00 00:00:00')
                    OR ws.slug = %s
                ) AND ws.slug <> %s AND ws.slug = %s THEN 1 ELSE 0 END) AS not_proceeding,
                SUM(CASE WHEN NOT (
                    (r.care_commenced_at IS NOT NULL AND r.care_commenced_at <> '0000-00-00 00:00:00')
                    OR ws.slug IN (%s, %s, %s)
                ) THEN 1 ELSE 0 END) AS still_active
            FROM {$referrals} r
            LEFT JOIN {$stages} ws ON ws.id = r.workflow_stage_id
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s
              AND {$identity['sql']}";

        $params = array_merge(
            [
                $care, $care, $dec,
                $care, $dec, $np,
                $care, $dec, $np,
                $start_date, $end_date,
            ],
            $identity['params']
        );
        $this->append_assigned_scope($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$params), ARRAY_A);

        return [
            'care_commenced' => (int) ($row['care_commenced'] ?? 0),
            'declined'       => (int) ($row['declined'] ?? 0),
            'not_proceeding' => (int) ($row['not_proceeding'] ?? 0),
            'still_active'   => (int) ($row['still_active'] ?? 0),
        ];
    }

    /**
     * Active pipeline stage distribution for still-active structured cohort referrals.
     *
     * @return array<int, array{slug: string, label: string, count: int}>
     */
    public function get_active_stage_distribution(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals = Tables::referrals_table();
        $stages    = Tables::workflow_stages_table();
        $identity  = $this->structured_phase3_exists_sql('r');
        $active    = $this->active_pipeline_slugs();
        $in        = $this->placeholders($active);

        $sql = "SELECT ws.slug AS slug, COUNT(*) AS count
            FROM {$referrals} r
            INNER JOIN {$stages} ws ON ws.id = r.workflow_stage_id
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s
              AND {$identity['sql']}
              AND ws.slug IN ({$in})
            GROUP BY ws.slug";
        $params = array_merge([$start_date, $end_date], $identity['params'], $active);
        $this->append_assigned_scope($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        $by_slug = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ('' === $slug) {
                continue;
            }
            $by_slug[$slug] = (int) ($row['count'] ?? 0);
        }

        $out = [];
        foreach ($active as $slug) {
            $out[] = [
                'slug'  => $slug,
                'label' => PipelineStage::label($slug),
                'count' => (int) ($by_slug[$slug] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, int>
     */
    public function get_received_to_interest_seconds(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals = Tables::referrals_table();
        $identity  = $this->structured_phase3_exists_sql('r');

        $sql = "SELECT TIMESTAMPDIFF(SECOND, r.created_at, r.interest_expressed_at) AS seconds
            FROM {$referrals} r
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s
              AND {$identity['sql']}
              AND r.interest_expressed_at IS NOT NULL
              AND r.interest_expressed_at <> '0000-00-00 00:00:00'
              AND r.created_at IS NOT NULL
              AND r.created_at <> '0000-00-00 00:00:00'
              AND TIMESTAMPDIFF(SECOND, r.created_at, r.interest_expressed_at) >= 0";
        $params = array_merge([$start_date, $end_date], $identity['params']);
        $this->append_assigned_scope($sql, $params, $access_assigned_to, 'r');

        return $this->fetch_seconds_list($sql, $params);
    }

    /**
     * Package Cost Sent → LA Decision using linked package_cost_id only.
     *
     * @return array<int, int>
     */
    public function get_package_sent_to_decision_seconds(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals = Tables::referrals_table();
        $costs     = Tables::referral_package_costs_table();
        $decisions = Tables::referral_la_decisions_table();
        $identity  = $this->structured_phase3_exists_sql('r');

        $sql = "SELECT TIMESTAMPDIFF(SECOND, pc.sent_at, d.decision_at) AS seconds
            FROM {$referrals} r
            INNER JOIN {$decisions} d ON d.referral_id = r.id
            INNER JOIN {$costs} pc ON pc.id = d.package_cost_id
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s
              AND {$identity['sql']}
              AND d.package_cost_id IS NOT NULL
              AND pc.sent_at IS NOT NULL
              AND pc.sent_at <> '0000-00-00 00:00:00'
              AND d.decision_at IS NOT NULL
              AND d.decision_at <> '0000-00-00 00:00:00'
              AND TIMESTAMPDIFF(SECOND, pc.sent_at, d.decision_at) >= 0";
        $params = array_merge([$start_date, $end_date], $identity['params']);
        $this->append_assigned_scope($sql, $params, $access_assigned_to, 'r');

        return $this->fetch_seconds_list($sql, $params);
    }

    /**
     * @return array<int, int>
     */
    public function get_approval_to_commencement_seconds(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals = Tables::referrals_table();
        $decisions = Tables::referral_la_decisions_table();
        $identity  = $this->structured_phase3_exists_sql('r');
        $approved  = LaDecision::DECISION_APPROVED;

        $sql = "SELECT TIMESTAMPDIFF(SECOND, d.decision_at, r.care_commenced_at) AS seconds
            FROM {$referrals} r
            INNER JOIN (
                SELECT d1.*
                FROM {$decisions} d1
                INNER JOIN (
                    SELECT referral_id, MAX(id) AS max_id
                    FROM {$decisions}
                    GROUP BY referral_id
                ) latest ON latest.max_id = d1.id
            ) d ON d.referral_id = r.id
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s
              AND {$identity['sql']}
              AND d.decision = %s
              AND d.decision_at IS NOT NULL
              AND d.decision_at <> '0000-00-00 00:00:00'
              AND r.care_commenced_at IS NOT NULL
              AND r.care_commenced_at <> '0000-00-00 00:00:00'
              AND TIMESTAMPDIFF(SECOND, d.decision_at, r.care_commenced_at) >= 0";
        $params = array_merge([$start_date, $end_date], $identity['params'], [$approved]);
        $this->append_assigned_scope($sql, $params, $access_assigned_to, 'r');

        return $this->fetch_seconds_list($sql, $params);
    }

    /**
     * @return array<int, int>
     */
    public function get_received_to_commencement_seconds(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals = Tables::referrals_table();
        $identity  = $this->structured_phase3_exists_sql('r');

        $sql = "SELECT TIMESTAMPDIFF(SECOND, r.created_at, r.care_commenced_at) AS seconds
            FROM {$referrals} r
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s
              AND {$identity['sql']}
              AND r.care_commenced_at IS NOT NULL
              AND r.care_commenced_at <> '0000-00-00 00:00:00'
              AND r.created_at IS NOT NULL
              AND r.created_at <> '0000-00-00 00:00:00'
              AND TIMESTAMPDIFF(SECOND, r.created_at, r.care_commenced_at) >= 0";
        $params = array_merge([$start_date, $end_date], $identity['params']);
        $this->append_assigned_scope($sql, $params, $access_assigned_to, 'r');

        return $this->fetch_seconds_list($sql, $params);
    }

    /**
     * Completed stage durations from consecutive stage-history rows.
     *
     * @return array<string, array<int, int>> slug => list of seconds
     */
    public function get_completed_stage_duration_seconds(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals = Tables::referrals_table();
        $history   = Tables::referral_stage_history_table();
        $identity  = $this->structured_phase3_exists_sql('r');
        $active    = $this->active_pipeline_slugs();
        $in        = $this->placeholders($active);

        $sql = "SELECT h1.to_stage_slug AS slug,
                TIMESTAMPDIFF(SECOND, h1.created_at, h2.created_at) AS seconds
            FROM {$history} h1
            INNER JOIN {$history} h2
                ON h2.referral_id = h1.referral_id
               AND h2.id = (
                    SELECT MIN(h3.id)
                    FROM {$history} h3
                    WHERE h3.referral_id = h1.referral_id
                      AND h3.id > h1.id
               )
            INNER JOIN {$referrals} r ON r.id = h1.referral_id
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s
              AND {$identity['sql']}
              AND h1.to_stage_slug IN ({$in})
              AND h1.created_at IS NOT NULL
              AND h2.created_at IS NOT NULL
              AND TIMESTAMPDIFF(SECOND, h1.created_at, h2.created_at) >= 0";
        $params = array_merge([$start_date, $end_date], $identity['params'], $active);
        $this->append_assigned_scope($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $out = [];
        foreach ($active as $slug) {
            $out[$slug] = [];
        }
        foreach (is_array($rows) ? $rows : [] as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if (! isset($out[$slug])) {
                continue;
            }
            $out[$slug][] = (int) ($row['seconds'] ?? 0);
        }

        return $out;
    }

    /**
     * Latest assessment outcome per structured Phase 3 cohort referral.
     *
     * @return array{suitable: int, suitable_with_conditions: int, not_suitable: int, pending: int}
     */
    public function get_assessment_outcome_counts(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals   = Tables::referrals_table();
        $assessments = Tables::referral_assessments_table();
        $identity    = $this->structured_phase3_exists_sql('r');

        $sql = "SELECT a.outcome AS outcome, COUNT(*) AS count
            FROM {$referrals} r
            INNER JOIN (
                SELECT a1.*
                FROM {$assessments} a1
                INNER JOIN (
                    SELECT referral_id, MAX(id) AS max_id
                    FROM {$assessments}
                    GROUP BY referral_id
                ) latest ON latest.max_id = a1.id
            ) a ON a.referral_id = r.id
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s
              AND {$identity['sql']}
            GROUP BY a.outcome";
        $params = array_merge([$start_date, $end_date], $identity['params']);
        $this->append_assigned_scope($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $out = [
            'suitable'                 => 0,
            'suitable_with_conditions' => 0,
            'not_suitable'             => 0,
            'pending'                  => 0,
        ];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $outcome = (string) ($row['outcome'] ?? '');
            $count   = (int) ($row['count'] ?? 0);
            if (isset($out[$outcome])) {
                $out[$outcome] = $count;
            }
        }

        return $out;
    }

    /**
     * Current (latest-by-id) package cost aggregates for the structured Phase 3 cohort.
     *
     * @return array{
     *     prepared: int,
     *     sent: int,
     *     total_proposed: float|null,
     *     average_proposed: float|null,
     *     proposed_count: int
     * }
     */
    public function get_package_cost_summary(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals = Tables::referrals_table();
        $costs     = Tables::referral_package_costs_table();
        $identity  = $this->structured_phase3_exists_sql('r');

        $sql = "SELECT
                SUM(CASE WHEN pc.status IN (%s, %s) THEN 1 ELSE 0 END) AS prepared,
                SUM(CASE WHEN pc.status = %s
                    OR (pc.sent_at IS NOT NULL AND pc.sent_at <> '0000-00-00 00:00:00')
                    THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN pc.package_total IS NOT NULL AND pc.package_total <> '' THEN 1 ELSE 0 END) AS proposed_count,
                SUM(CASE WHEN pc.package_total IS NOT NULL AND pc.package_total <> ''
                    THEN CAST(pc.package_total AS DECIMAL(12,2)) ELSE 0 END) AS total_proposed
            FROM {$referrals} r
            INNER JOIN (
                SELECT pc1.*
                FROM {$costs} pc1
                INNER JOIN (
                    SELECT referral_id, MAX(id) AS max_id
                    FROM {$costs}
                    GROUP BY referral_id
                ) latest ON latest.max_id = pc1.id
            ) pc ON pc.referral_id = r.id
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s
              AND {$identity['sql']}";
        $params = array_merge(
            [
                PackageCost::STATUS_PREPARED,
                PackageCost::STATUS_SENT,
                PackageCost::STATUS_SENT,
                $start_date,
                $end_date,
            ],
            $identity['params']
        );
        $this->append_assigned_scope($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$params), ARRAY_A);

        $proposed_count = (int) ($row['proposed_count'] ?? 0);
        $total          = null;
        $average        = null;
        if ($proposed_count > 0) {
            $total   = round((float) ($row['total_proposed'] ?? 0), 2);
            $average = round($total / $proposed_count, 2);
        }

        return [
            'prepared'         => (int) ($row['prepared'] ?? 0),
            'sent'             => (int) ($row['sent'] ?? 0),
            'total_proposed'   => $total,
            'average_proposed' => $average,
            'proposed_count'   => $proposed_count,
        ];
    }

    /**
     * Funding confirmation for approved LA decisions (latest per referral).
     *
     * @return array{yes: int, no: int, not_recorded: int}
     */
    public function get_funding_summary(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals = Tables::referrals_table();
        $decisions = Tables::referral_la_decisions_table();
        $identity  = $this->structured_phase3_exists_sql('r');
        $approved  = LaDecision::DECISION_APPROVED;

        $sql = "SELECT
                SUM(CASE WHEN d.funding_confirmed = 1 THEN 1 ELSE 0 END) AS yes_count,
                SUM(CASE WHEN d.funding_confirmed = 0 THEN 1 ELSE 0 END) AS no_count,
                SUM(CASE WHEN d.funding_confirmed IS NULL THEN 1 ELSE 0 END) AS not_recorded
            FROM {$referrals} r
            INNER JOIN (
                SELECT d1.*
                FROM {$decisions} d1
                INNER JOIN (
                    SELECT referral_id, MAX(id) AS max_id
                    FROM {$decisions}
                    GROUP BY referral_id
                ) latest ON latest.max_id = d1.id
            ) d ON d.referral_id = r.id
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s
              AND {$identity['sql']}
              AND d.decision = %s";
        $params = array_merge([$start_date, $end_date], $identity['params'], [$approved]);
        $this->append_assigned_scope($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$params), ARRAY_A);

        return [
            'yes'          => (int) ($row['yes_count'] ?? 0),
            'no'           => (int) ($row['no_count'] ?? 0),
            'not_recorded' => (int) ($row['not_recorded'] ?? 0),
        ];
    }

    /**
     * Acquisition CSV detail rows for the referral cohort (canonical + legacy).
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_acquisition_export_rows(string $start_date, string $end_date, ?int $access_assigned_to = null): array
    {
        global $wpdb;

        $referrals   = Tables::referrals_table();
        $stages      = Tables::workflow_stages_table();
        $assessments = Tables::referral_assessments_table();
        $costs       = Tables::referral_package_costs_table();
        $decisions   = Tables::referral_la_decisions_table();
        $identity    = $this->structured_phase3_exists_sql('r');

        $sql = "SELECT
                r.referral_number,
                r.client_name,
                r.created_at,
                r.priority,
                r.assigned_to,
                r.status,
                r.care_setting,
                r.interest_expressed_at,
                r.care_commenced_at,
                r.archived_at,
                ws.slug AS stage_slug,
                ws.name AS stage_name,
                COALESCE(ws.is_pipeline_stage, 0) AS is_pipeline_stage,
                CASE WHEN {$identity['sql']} THEN 1 ELSE 0 END AS is_structured_phase3,
                a.outcome AS assessment_outcome,
                pc.status AS package_cost_status,
                pc.sent_at AS package_cost_sent_at,
                pc.package_total AS package_total,
                pc.currency AS package_currency,
                d.decision AS la_decision,
                d.decision_at AS la_decision_at,
                d.funding_confirmed AS funding_confirmed
            FROM {$referrals} r
            LEFT JOIN {$stages} ws ON ws.id = r.workflow_stage_id
            LEFT JOIN (
                SELECT a1.*
                FROM {$assessments} a1
                INNER JOIN (
                    SELECT referral_id, MAX(id) AS max_id
                    FROM {$assessments}
                    GROUP BY referral_id
                ) al ON al.max_id = a1.id
            ) a ON a.referral_id = r.id
            LEFT JOIN (
                SELECT pc1.*
                FROM {$costs} pc1
                INNER JOIN (
                    SELECT referral_id, MAX(id) AS max_id
                    FROM {$costs}
                    GROUP BY referral_id
                ) pl ON pl.max_id = pc1.id
            ) pc ON pc.referral_id = r.id
            LEFT JOIN (
                SELECT d1.*
                FROM {$decisions} d1
                INNER JOIN (
                    SELECT referral_id, MAX(id) AS max_id
                    FROM {$decisions}
                    GROUP BY referral_id
                ) dl ON dl.max_id = d1.id
            ) d ON d.referral_id = r.id
            WHERE DATE(r.created_at) >= %s
              AND DATE(r.created_at) <= %s
            ORDER BY r.created_at ASC, r.id ASC";
        $params = array_merge($identity['params'], [$start_date, $end_date]);
        $this->append_assigned_scope($sql, $params, $access_assigned_to, 'r');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Structured Phase 3 acquisition identity (not current-stage classification).
     *
     * Genuine new referrals receive history change_type = created via
     * ReferralPipelineService::record_pipeline_started. Override-only history
     * does not establish structured Phase 3 reporting identity.
     *
     * @return array{sql: string, params: array<int, string>}
     */
    private function structured_phase3_exists_sql(string $referral_alias = 'r'): array
    {
        $history = Tables::referral_stage_history_table();
        $slugs   = PipelineStage::slugs();
        $in      = $this->placeholders($slugs);

        $sql = "EXISTS (
            SELECT 1
            FROM {$history} h_p3
            WHERE h_p3.referral_id = {$referral_alias}.id
              AND h_p3.change_type = %s
              AND h_p3.to_stage_slug IN ({$in})
        )";

        return [
            'sql'    => $sql,
            'params' => array_merge([PipelineStage::CHANGE_CREATED], $slugs),
        ];
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private function append_assigned_scope(string &$sql, array &$params, ?int $access_assigned_to, string $alias): void
    {
        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $sql     .= " AND {$alias}.assigned_to = %d";
            $params[] = $access_assigned_to;
        }
    }

    /**
     * @return array<int, string>
     */
    private function active_pipeline_slugs(): array
    {
        $slugs = [];
        foreach (PipelineStage::definitions() as $slug => $definition) {
            if (PipelineStage::KIND_ACTIVE === ($definition['kind'] ?? '')) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * @param array<int, string> $values
     */
    private function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '%s'));
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int, int>
     */
    private function fetch_seconds_list(string $sql, array $params): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_col($wpdb->prepare($sql, ...$params));
        $out  = [];
        foreach (is_array($rows) ? $rows : [] as $value) {
            if (null === $value || '' === $value) {
                continue;
            }
            $out[] = (int) $value;
        }

        return $out;
    }
}
