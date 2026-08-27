<?php

namespace JMReferral\PackageCost;

use JMReferral\Database\Tables;
use JMReferral\Pipeline\PipelineStage;

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
                "SELECT pc.referral_id, pc.package_total, pc.status, pc.currency,
                    pc.prepared_at, pc.prepared_by, pc.sent_at, pc.sent_by,
                    pc.send_method, pc.recipient, pc.submission_reference
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
                        'status'               => (string) ($row['status'] ?? ''),
                        'currency'             => (string) ($row['currency'] ?? PackageCost::CURRENCY_GBP),
                        'prepared_at'          => (string) ($row['prepared_at'] ?? ''),
                        'prepared_by'          => absint($row['prepared_by'] ?? 0),
                        'sent_at'              => (string) ($row['sent_at'] ?? ''),
                        'sent_by'              => absint($row['sent_by'] ?? 0),
                        'send_method'          => (string) ($row['send_method'] ?? ''),
                        'recipient'            => (string) ($row['recipient'] ?? ''),
                        'submission_reference' => (string) ($row['submission_reference'] ?? ''),
                    ];
                }
            }
        }

        return $map;
    }

    /**
     * Sum of latest package_total for non-archived referrals currently on the given pipeline slugs.
     *
     * @param array<int, string> $slugs
     */
    public function sum_current_package_total_for_pipeline_slugs(array $slugs, ?int $access_assigned_to = null): float
    {
        global $wpdb;

        $slugs = array_values(array_filter(array_map('strval', $slugs), static function (string $slug): bool {
            return \JMReferral\Pipeline\PipelineStage::is_canonical($slug);
        }));

        if ([] === $slugs) {
            return 0.0;
        }

        $pc       = Tables::referral_package_costs_table();
        $referrals = Tables::referrals_table();
        $stages   = Tables::workflow_stages_table();
        $placeholders = implode(',', array_fill(0, count($slugs), '%s'));

        $where = [
            's.is_pipeline_stage = 1',
            "s.slug IN ({$placeholders})",
            'r.archived_at IS NULL',
            'pc.package_total IS NOT NULL',
        ];
        $params = $slugs;

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT COALESCE(SUM(pc.package_total), 0) AS total
            FROM {$pc} pc
            INNER JOIN (
                SELECT referral_id, MAX(id) AS max_id
                FROM {$pc}
                GROUP BY referral_id
            ) latest ON latest.max_id = pc.id
            INNER JOIN {$referrals} r ON r.id = pc.referral_id
            INNER JOIN {$stages} s ON s.id = r.workflow_stage_id
            WHERE " . implode(' AND ', $where);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return (float) $total;
    }

    /**
     * Count non-archived referrals currently on a canonical pipeline slug.
     */
    public function count_referrals_on_pipeline_slug(string $slug, ?int $access_assigned_to = null): int
    {
        global $wpdb;

        if (! PipelineStage::is_canonical($slug)) {
            return 0;
        }

        $referrals = Tables::referrals_table();
        $stages    = Tables::workflow_stages_table();

        $where  = [
            'r.archived_at IS NULL',
            's.is_pipeline_stage = 1',
            's.slug = %s',
        ];
        $params = [$slug];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT COUNT(*)
            FROM {$referrals} r
            INNER JOIN {$stages} s ON s.id = r.workflow_stage_id
            WHERE " . implode(' AND ', $where);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * Counts of latest package-cost rows by status (prepared / sent).
     * Older historical rows for the same referral are excluded via MAX(id).
     *
     * Limitation: referral_id is non-unique; “current” = highest id per referral.
     *
     * @return array{prepared: int, sent: int}
     */
    public function count_current_status_for_dashboard(?int $access_assigned_to = null): array
    {
        global $wpdb;

        $pc        = Tables::referral_package_costs_table();
        $referrals = Tables::referrals_table();

        $where  = [
            'r.archived_at IS NULL',
            'pc.status IN (%s, %s)',
        ];
        $params = [PackageCost::STATUS_PREPARED, PackageCost::STATUS_SENT];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT pc.status, COUNT(*) AS total
            FROM {$pc} pc
            INNER JOIN (
                SELECT referral_id, MAX(id) AS max_id
                FROM {$pc}
                GROUP BY referral_id
            ) latest ON latest.max_id = pc.id
            INNER JOIN {$referrals} r ON r.id = pc.referral_id
            WHERE " . implode(' AND ', $where) . '
            GROUP BY pc.status';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $out = [
            PackageCost::STATUS_PREPARED => 0,
            PackageCost::STATUS_SENT     => 0,
        ];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? '');
                if (isset($out[$status])) {
                    $out[$status] = (int) ($row['total'] ?? 0);
                }
            }
        }

        return [
            'prepared' => $out[PackageCost::STATUS_PREPARED],
            'sent'     => $out[PackageCost::STATUS_SENT],
        ];
    }

    /**
     * Compact list of current packages for Operations dashboard (no amounts / recipients).
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_current_for_dashboard(
        string $status,
        int $limit,
        ?int $access_assigned_to = null
    ): array {
        global $wpdb;

        $limit = max(1, min(50, $limit));
        if (! in_array($status, [PackageCost::STATUS_PREPARED, PackageCost::STATUS_SENT], true)) {
            return [];
        }

        $pc        = Tables::referral_package_costs_table();
        $referrals = Tables::referrals_table();

        $where  = [
            'r.archived_at IS NULL',
            'pc.status = %s',
        ];
        $params = [$status];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $order = PackageCost::STATUS_SENT === $status
            ? 'pc.sent_at DESC, pc.id DESC'
            : 'pc.prepared_at DESC, pc.id DESC';

        $params[] = $limit;

        $sql = "SELECT pc.id, pc.referral_id, pc.status, pc.prepared_at, pc.sent_at,
                r.referral_number
            FROM {$pc} pc
            INNER JOIN (
                SELECT referral_id, MAX(id) AS max_id
                FROM {$pc}
                GROUP BY referral_id
            ) latest ON latest.max_id = pc.id
            INNER JOIN {$referrals} r ON r.id = pc.referral_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$order}
            LIMIT %d";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($rows) ? $rows : [];
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
