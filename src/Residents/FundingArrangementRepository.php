<?php

namespace JMReferral\Residents;

use JMReferral\Database\Tables;

/**
 * Phase 5F.1: funding currently in place for a client (who pays and for what package).
 */
class FundingArrangementRepository
{
    private const SELECT_COLUMNS = 'id, referral_id, funding_type, local_authority_id, funder_name, funding_reference, start_date, weekly_amount, weekly_support_hours, package_summary, status, source, created_by, created_at, updated_at';

    /**
     * @param array{
     *     referral_id: int,
     *     funding_type: string,
     *     local_authority_id: int|null,
     *     funder_name: string|null,
     *     funding_reference: string|null,
     *     start_date: string|null,
     *     weekly_amount: string|null,
     *     weekly_support_hours: string|null,
     *     package_summary: string|null,
     *     source: string|null,
     *     created_by: int|null
     * } $data
     */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $now = current_time('mysql');
        $row = [
            'referral_id'          => (int) $data['referral_id'],
            'funding_type'         => (string) $data['funding_type'],
            'local_authority_id'   => $data['local_authority_id'] ?? null,
            'funder_name'          => $data['funder_name'] ?? null,
            'funding_reference'    => $data['funding_reference'] ?? null,
            'start_date'           => $data['start_date'] ?? null,
            'weekly_amount'        => $data['weekly_amount'] ?? null,
            'weekly_support_hours' => $data['weekly_support_hours'] ?? null,
            'package_summary'      => $data['package_summary'] ?? null,
            'status'               => 'active',
            'source'               => $data['source'] ?? null,
            'created_by'           => $data['created_by'] ?? null,
            'created_at'           => $now,
            'updated_at'           => $now,
        ];

        // Nullable columns are inserted as NULL rather than formatted as empty strings or zero.
        $columns = [];
        $formats = [
            'referral_id'          => '%d',
            'funding_type'         => '%s',
            'local_authority_id'   => '%d',
            'funder_name'          => '%s',
            'funding_reference'    => '%s',
            'start_date'           => '%s',
            'weekly_amount'        => '%s',
            'weekly_support_hours' => '%s',
            'package_summary'      => '%s',
            'status'               => '%s',
            'source'               => '%s',
            'created_by'           => '%d',
            'created_at'           => '%s',
            'updated_at'           => '%s',
        ];
        $format_list = [];
        foreach ($row as $column => $value) {
            if (null === $value) {
                continue;
            }
            $columns[$column] = $value;
            $format_list[]    = $formats[$column];
        }

        $result = $wpdb->insert(Tables::funding_arrangements_table(), $columns, $format_list);

        return false === $result ? false : (int) $wpdb->insert_id;
    }

    /**
     * The active arrangement for a client (latest when several exist).
     *
     * @return array<string, mixed>|null
     */
    public function current_for_referral(int $referral_id): ?array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return null;
        }

        $table = Tables::funding_arrangements_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and columns are trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT ' . self::SELECT_COLUMNS . " FROM {$table}
                WHERE referral_id = %d AND status = %s
                ORDER BY id DESC
                LIMIT 1",
                $referral_id,
                'active'
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function count_for_referral(int $referral_id): int
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return 0;
        }

        $table = Tables::funding_arrangements_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE referral_id = %d", $referral_id)
        );
    }
}
