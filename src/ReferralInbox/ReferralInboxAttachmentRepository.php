<?php

namespace JMReferral\ReferralInbox;

use JMReferral\Database\Tables;

class ReferralInboxAttachmentRepository
{
    private const SELECT_COLUMNS = 'id, inbox_id, provider_attachment_id, filename, mime_type, size_bytes, sha256, storage_status, private_path, created_at, updated_at';

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = Tables::referral_inbox_attachments_table();
        $cols  = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/columns trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$cols} FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForInbox(int $inbox_id): array
    {
        global $wpdb;

        if ($inbox_id <= 0) {
            return [];
        }

        $table = Tables::referral_inbox_attachments_table();
        $cols  = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/columns trusted.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$cols} FROM {$table} WHERE inbox_id = %d ORDER BY id ASC",
                $inbox_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByInboxAndProviderAttachmentId(int $inbox_id, string $provider_attachment_id): ?array
    {
        global $wpdb;

        if ($inbox_id <= 0 || '' === $provider_attachment_id) {
            return null;
        }

        $table = Tables::referral_inbox_attachments_table();
        $cols  = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/columns trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$cols} FROM {$table}
                WHERE inbox_id = %d AND provider_attachment_id = %s
                LIMIT 1",
                $inbox_id,
                $provider_attachment_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::referral_inbox_attachments_table(),
            [
                'inbox_id'               => $data['inbox_id'],
                'provider_attachment_id' => $data['provider_attachment_id'],
                'filename'               => $data['filename'],
                'mime_type'              => $data['mime_type'],
                'size_bytes'             => $data['size_bytes'],
                'sha256'                 => $data['sha256'],
                'storage_status'         => $data['storage_status'],
                'private_path'           => $data['private_path'],
                'created_at'             => $data['created_at'],
                'updated_at'             => $data['updated_at'],
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
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
}
