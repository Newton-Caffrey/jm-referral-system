<?php

namespace JMReferral\PackageCost;

use JMReferral\Database\Tables;

class PackageCostRepository
{
    private const SELECT_COLUMNS = 'id, referral_id, document_id, package_total, currency, prepared_at, prepared_by, sent_at, sent_by, send_method, recipient, submission_reference, status, created_at, updated_at';

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::referral_package_costs_table(),
            [
                'referral_id'           => absint($data['referral_id'] ?? 0),
                'document_id'           => isset($data['document_id']) ? absint($data['document_id']) : null,
                'package_total'         => $data['package_total'] ?? null,
                'currency'              => (string) ($data['currency'] ?? PackageCost::CURRENCY_GBP),
                'prepared_at'           => $data['prepared_at'] ?? null,
                'prepared_by'           => isset($data['prepared_by']) ? absint($data['prepared_by']) : null,
                'sent_at'               => $data['sent_at'] ?? null,
                'sent_by'               => isset($data['sent_by']) ? absint($data['sent_by']) : null,
                'send_method'           => $data['send_method'] ?? null,
                'recipient'             => $data['recipient'] ?? null,
                'submission_reference'  => $data['submission_reference'] ?? null,
                'status'                => (string) ($data['status'] ?? PackageCost::STATUS_DRAFT),
                'created_at'            => (string) ($data['created_at'] ?? current_time('mysql')),
                'updated_at'            => (string) ($data['updated_at'] ?? current_time('mysql')),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (false === $result) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $row = [];
        $formats = [];

        $map = [
            'document_id'          => '%d',
            'package_total'        => '%s',
            'currency'             => '%s',
            'prepared_at'          => '%s',
            'prepared_by'          => '%d',
            'sent_at'              => '%s',
            'sent_by'              => '%d',
            'send_method'          => '%s',
            'recipient'            => '%s',
            'submission_reference' => '%s',
            'status'               => '%s',
            'updated_at'           => '%s',
        ];

        foreach ($map as $key => $format) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $row[$key] = $data[$key];
            $formats[] = $format;
        }

        if ([] === $row) {
            return false;
        }

        $result = $wpdb->update(
            Tables::referral_package_costs_table(),
            $row,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Latest package-cost record for a referral (current cycle / history head).
     *
     * @return array<string, mixed>|null
     */
    public function find_current_for_referral(int $referral_id): ?array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return null;
        }

        $table = Tables::referral_package_costs_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE referral_id = %d
                ORDER BY id DESC
                LIMIT 1",
                $referral_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Current package-cost status keyed by referral_id (latest row per referral).
     *
     * @param array<int, int> $referral_ids
     * @return array<int, string> referral_id => status
     */
    public function current_status_map_for_referrals(array $referral_ids): array
    {
        global $wpdb;

        $referral_ids = array_values(array_unique(array_filter(array_map('absint', $referral_ids))));
        if ([] === $referral_ids) {
            return [];
        }

        $table = Tables::referral_package_costs_table();
        $placeholders = implode(',', array_fill(0, count($referral_ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pc.referral_id, pc.status
                FROM {$table} pc
                INNER JOIN (
                    SELECT referral_id, MAX(id) AS max_id
                    FROM {$table}
                    WHERE referral_id IN ({$placeholders})
                    GROUP BY referral_id
                ) latest ON latest.max_id = pc.id",
                ...$referral_ids
            ),
            ARRAY_A
        );

        $map = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $rid = absint($row['referral_id'] ?? 0);
                if ($rid > 0) {
                    $map[$rid] = (string) ($row['status'] ?? '');
                }
            }
        }

        return $map;
    }

    /**
     * Latest package_total keyed by referral_id.
     *
     * @param array<int, int> $referral_ids
     * @return array<int, array{package_total: string|null, status: string, currency: string}>
     */
    public function current_package_map_for_referrals(array $referral_ids): array
    {
        global $wpdb;

        $referral_ids = array_values(array_unique(array_filter(array_map('absint', $referral_ids))));
        if ([] === $referral_ids) {
            return [];
        }

        $table = Tables::referral_package_costs_table();
        $placeholders = implode(',', array_fill(0, count($referral_ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pc.referral_id, pc.package_total, pc.status, pc.currency
                FROM {$table} pc
                INNER JOIN (
                    SELECT referral_id, MAX(id) AS max_id
                    FROM {$table}
                    WHERE referral_id IN ({$placeholders})
                    GROUP BY referral_id
                ) latest ON latest.max_id = pc.id",
                ...$referral_ids
            ),
            ARRAY_A
        );

        $map = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $rid = absint($row['referral_id'] ?? 0);
                if ($rid > 0) {
                    $map[$rid] = [
                        'package_total' => null !== ($row['package_total'] ?? null) && '' !== (string) $row['package_total']
                            ? (string) $row['package_total']
                            : null,
                        'status'   => (string) ($row['status'] ?? ''),
                        'currency' => (string) ($row['currency'] ?? PackageCost::CURRENCY_GBP),
                    ];
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

        $table = Tables::referral_package_costs_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$columns} FROM {$table} WHERE id = %d LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }
}
