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
}
