<?php

namespace JMReferral\LaDecision;

use JMReferral\Database\Tables;

class LaDecisionRepository
{
    private const SELECT_COLUMNS = 'id, referral_id, package_cost_id, decision, decision_at, recorded_by, funding_confirmed, funding_reference, decision_reference, reason_code, notes, created_at, updated_at';

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $row = [
            'referral_id' => absint($data['referral_id'] ?? 0),
            'decision'    => (string) ($data['decision'] ?? ''),
            'decision_at' => (string) ($data['decision_at'] ?? ''),
            'created_at'  => (string) ($data['created_at'] ?? current_time('mysql')),
            'updated_at'  => (string) ($data['updated_at'] ?? current_time('mysql')),
        ];
        $formats = ['%d', '%s', '%s', '%s', '%s'];

        if (isset($data['package_cost_id']) && null !== $data['package_cost_id'] && absint($data['package_cost_id']) > 0) {
            $row['package_cost_id'] = absint($data['package_cost_id']);
            $formats[] = '%d';
        }

        if (isset($data['recorded_by']) && null !== $data['recorded_by'] && absint($data['recorded_by']) > 0) {
            $row['recorded_by'] = absint($data['recorded_by']);
            $formats[] = '%d';
        }

        if (array_key_exists('funding_confirmed', $data) && null !== $data['funding_confirmed']) {
            $row['funding_confirmed'] = (int) $data['funding_confirmed'];
            $formats[] = '%d';
        }

        if (! empty($data['funding_reference'])) {
            $row['funding_reference'] = (string) $data['funding_reference'];
            $formats[] = '%s';
        }

        if (! empty($data['decision_reference'])) {
            $row['decision_reference'] = (string) $data['decision_reference'];
            $formats[] = '%s';
        }

        if (! empty($data['reason_code'])) {
            $row['reason_code'] = (string) $data['reason_code'];
            $formats[] = '%s';
        }

        if (! empty($data['notes'])) {
            $row['notes'] = (string) $data['notes'];
            $formats[] = '%s';
        }

        $result = $wpdb->insert(Tables::referral_la_decisions_table(), $row, $formats);

        if (false === $result) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Latest decision for the referral (current cycle head).
     *
     * @return array<string, mixed>|null
     */
    public function find_current_for_referral(int $referral_id): ?array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return null;
        }

        $table = Tables::referral_la_decisions_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT ' . self::SELECT_COLUMNS . " FROM {$table} WHERE referral_id = %d ORDER BY id DESC LIMIT 1",
                $referral_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Latest LA decision per referral (batch).
     *
     * @param array<int, int> $referral_ids
     * @return array<int, array<string, mixed>>
     */
    public function current_decision_map_for_referrals(array $referral_ids): array
    {
        global $wpdb;

        $referral_ids = array_values(array_unique(array_filter(array_map('absint', $referral_ids))));
        if ([] === $referral_ids) {
            return [];
        }

        $table = Tables::referral_la_decisions_table();
        $placeholders = implode(',', array_fill(0, count($referral_ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT d.id, d.referral_id, d.package_cost_id, d.decision, d.decision_at, d.recorded_by,
                    d.funding_confirmed, d.funding_reference, d.decision_reference, d.reason_code,
                    d.notes, d.created_at, d.updated_at
                FROM {$table} d
                INNER JOIN (
                    SELECT referral_id, MAX(id) AS max_id
                    FROM {$table}
                    WHERE referral_id IN ({$placeholders})
                    GROUP BY referral_id
                ) latest ON latest.max_id = d.id",
                ...$referral_ids
            ),
            ARRAY_A
        );

        $map = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $rid = absint($row['referral_id'] ?? 0);
                if ($rid > 0) {
                    $map[$rid] = $row;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = Tables::referral_la_decisions_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT ' . self::SELECT_COLUMNS . " FROM {$table} WHERE id = %d LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Whether any decision row exists for this referral (blocks normal re-record).
     */
    public function exists_for_referral(int $referral_id): bool
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return false;
        }

        $table = Tables::referral_la_decisions_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE referral_id = %d",
                $referral_id
            )
        );

        return $count > 0;
    }

    /**
     * Counts of latest LA-decision rows by outcome (approved / declined / not_proceeding).
     * Older historical rows for the same referral are excluded via MAX(id).
     *
     * Limitation: referral_id is non-unique; “current” = highest id per referral.
     * Absence of UNIQUE(referral_id) is an accepted application-level constraint.
     *
     * @return array{approved: int, declined: int, not_proceeding: int}
     */
    public function count_current_decision_for_dashboard(?int $access_assigned_to = null): array
    {
        global $wpdb;

        $decisions = Tables::referral_la_decisions_table();
        $referrals = Tables::referrals_table();

        $where  = [
            'r.archived_at IS NULL',
            'd.decision IN (%s, %s, %s)',
        ];
        $params = [
            LaDecision::DECISION_APPROVED,
            LaDecision::DECISION_DECLINED,
            LaDecision::DECISION_NOT_PROCEEDING,
        ];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT d.decision, COUNT(*) AS total
            FROM {$decisions} d
            INNER JOIN (
                SELECT referral_id, MAX(id) AS max_id
                FROM {$decisions}
                GROUP BY referral_id
            ) latest ON latest.max_id = d.id
            INNER JOIN {$referrals} r ON r.id = d.referral_id
            WHERE " . implode(' AND ', $where) . '
            GROUP BY d.decision';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $out = [
            LaDecision::DECISION_APPROVED       => 0,
            LaDecision::DECISION_DECLINED       => 0,
            LaDecision::DECISION_NOT_PROCEEDING => 0,
        ];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $decision = (string) ($row['decision'] ?? '');
                if (isset($out[$decision])) {
                    $out[$decision] = (int) ($row['total'] ?? 0);
                }
            }
        }

        return [
            'approved'       => $out[LaDecision::DECISION_APPROVED],
            'declined'       => $out[LaDecision::DECISION_DECLINED],
            'not_proceeding' => $out[LaDecision::DECISION_NOT_PROCEEDING],
        ];
    }

    /**
     * Compact list of current LA decisions for Operations (no notes/refs/funding).
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_current_for_dashboard(
        string $decision,
        int $limit,
        ?int $access_assigned_to = null
    ): array {
        global $wpdb;

        $limit = max(1, min(50, $limit));
        if (! LaDecision::is_valid_decision($decision)) {
            return [];
        }

        $decisions = Tables::referral_la_decisions_table();
        $referrals = Tables::referrals_table();

        $where  = [
            'r.archived_at IS NULL',
            'd.decision = %s',
        ];
        $params = [$decision];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $params[] = $limit;

        $sql = "SELECT d.id, d.referral_id, d.decision, d.decision_at, r.referral_number
            FROM {$decisions} d
            INNER JOIN (
                SELECT referral_id, MAX(id) AS max_id
                FROM {$decisions}
                GROUP BY referral_id
            ) latest ON latest.max_id = d.id
            INNER JOIN {$referrals} r ON r.id = d.referral_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY d.decision_at DESC, d.id DESC
            LIMIT %d';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}
