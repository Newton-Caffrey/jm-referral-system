<?php

namespace JMReferral\Documents;

use JMReferral\Database\Tables;

class ReferralDocumentRepository
{
    private const SELECT_COLUMNS = 'id, referral_id, attachment_id, original_name, mime_type, file_size, uploaded_by, created_at, storage_type, relative_path, stored_name, checksum_sha256';

    /**
     * Inserts document metadata.
     *
     * @param array<string, mixed> $data
     * @return int|false Inserted row ID on success, false on failure.
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::referral_documents_table(),
            [
                'referral_id'      => absint($data['referral_id'] ?? 0),
                'attachment_id'    => absint($data['attachment_id'] ?? 0),
                'original_name'    => (string) ($data['original_name'] ?? 'document'),
                'mime_type'        => (string) ($data['mime_type'] ?? ''),
                'file_size'        => absint($data['file_size'] ?? 0),
                'uploaded_by'      => absint($data['uploaded_by'] ?? 0),
                'created_at'       => (string) ($data['created_at'] ?? current_time('mysql')),
                'storage_type'     => (string) ($data['storage_type'] ?? PrivateDocumentStorage::STORAGE_LEGACY),
                'relative_path'    => isset($data['relative_path']) ? (string) $data['relative_path'] : null,
                'stored_name'      => isset($data['stored_name']) ? (string) $data['stored_name'] : null,
                'checksum_sha256'  => isset($data['checksum_sha256']) ? (string) $data['checksum_sha256'] : null,
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        if (false === $result) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Updates storage fields after a successful private copy (legacy migration).
     *
     * @param array{
     *     storage_type: string,
     *     relative_path: string,
     *     stored_name: string,
     *     checksum_sha256: string,
     *     file_size?: int
     * } $data
     */
    public function update_private_storage(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $fields = [
            'storage_type'    => (string) $data['storage_type'],
            'relative_path'   => (string) $data['relative_path'],
            'stored_name'     => (string) $data['stored_name'],
            'checksum_sha256' => (string) $data['checksum_sha256'],
        ];
        $formats = ['%s', '%s', '%s', '%s'];

        if (isset($data['file_size'])) {
            $fields['file_size'] = absint($data['file_size']);
            $formats[]           = '%d';
        }

        $result = $wpdb->update(
            Tables::referral_documents_table(),
            $fields,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Finds a document by ID.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table   = Tables::referral_documents_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/columns are trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE id = %d
                LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->normalize_row($row) : null;
    }

    /**
     * Returns documents for a referral, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_by_referral_id(int $referral_id): array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return [];
        }

        $table   = Tables::referral_documents_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/columns are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE referral_id = %d
                ORDER BY created_at DESC, id DESC",
                $referral_id
            ),
            ARRAY_A
        );

        if (! is_array($results)) {
            return [];
        }

        return array_map([$this, 'normalize_row'], $results);
    }

    /**
     * Counts documents by storage type.
     */
    public function count_by_storage_type(string $storage_type): int
    {
        global $wpdb;

        $table = Tables::referral_documents_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE storage_type = %s",
                $storage_type
            )
        );

        return absint($count);
    }

    /**
     * Counts legacy documents (including rows with empty/null storage_type).
     */
    public function count_legacy(): int
    {
        global $wpdb;

        $table  = Tables::referral_documents_table();
        $legacy = PrivateDocumentStorage::STORAGE_LEGACY;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                WHERE storage_type = %s
                   OR storage_type IS NULL
                   OR storage_type = ''",
                $legacy
            )
        );

        return absint($count);
    }

    /**
     * Returns a batch of legacy documents eligible for private migration.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_legacy_batch(int $limit = 20): array
    {
        global $wpdb;

        $limit   = max(1, min(25, $limit));
        $table   = Tables::referral_documents_table();
        $columns = self::SELECT_COLUMNS;
        $legacy  = PrivateDocumentStorage::STORAGE_LEGACY;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/columns are trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE (storage_type = %s OR storage_type IS NULL OR storage_type = '')
                  AND attachment_id > 0
                ORDER BY id ASC
                LIMIT %d",
                $legacy,
                $limit
            ),
            ARRAY_A
        );

        if (! is_array($results)) {
            return [];
        }

        return array_map([$this, 'normalize_row'], $results);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize_row(array $row): array
    {
        if (! isset($row['storage_type']) || '' === (string) $row['storage_type']) {
            $row['storage_type'] = PrivateDocumentStorage::STORAGE_LEGACY;
        }

        return $row;
    }
}
