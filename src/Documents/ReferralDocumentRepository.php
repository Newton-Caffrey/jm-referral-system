<?php

namespace JMReferral\Documents;

use JMReferral\Database\Tables;

class ReferralDocumentRepository
{
    /**
     * Inserts document metadata.
     *
     * @param array{
     *     referral_id: int,
     *     attachment_id: int,
     *     original_name: string,
     *     mime_type: string,
     *     file_size: int,
     *     uploaded_by: int,
     *     created_at: string
     * } $data
     * @return int|false Inserted row ID on success, false on failure.
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::referral_documents_table(),
            [
                'referral_id'   => absint($data['referral_id']),
                'attachment_id' => absint($data['attachment_id']),
                'original_name' => (string) $data['original_name'],
                'mime_type'     => (string) $data['mime_type'],
                'file_size'     => absint($data['file_size']),
                'uploaded_by'   => absint($data['uploaded_by']),
                'created_at'    => (string) $data['created_at'],
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
            ]
        );

        if (false === $result) {
            return false;
        }

        return (int) $wpdb->insert_id;
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

        $table = Tables::referral_documents_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, referral_id, attachment_id, original_name, mime_type, file_size, uploaded_by, created_at
                FROM {$table}
                WHERE id = %d
                LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
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

        $table = Tables::referral_documents_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, referral_id, attachment_id, original_name, mime_type, file_size, uploaded_by, created_at
                FROM {$table}
                WHERE referral_id = %d
                ORDER BY created_at DESC, id DESC",
                $referral_id
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }
}
