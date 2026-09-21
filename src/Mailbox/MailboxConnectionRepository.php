<?php

namespace JMReferral\Mailbox;

use JMReferral\Database\Tables;

class MailboxConnectionRepository
{
    private const SELECT_COLUMNS = 'id, provider, auth_mode, credential_type, tenant_id, client_id, mailbox_identifier, mailbox_address, mailbox_type, status, is_enabled, last_verified_at, last_sync_at, last_successful_ingestion_at, last_error_code, last_error_at, created_at, updated_at';

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = Tables::mailbox_connections_table();
        $cols  = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT {$cols} FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_active_microsoft(): ?array
    {
        global $wpdb;

        $table = Tables::mailbox_connections_table();
        $cols  = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$cols} FROM {$table}
                WHERE provider = %s AND is_enabled = 1
                ORDER BY id ASC
                LIMIT 1",
                MailboxConnectionConstants::PROVIDER_MICROSOFT_GRAPH
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Any Microsoft connection row (including disabled), newest first.
     *
     * @return array<string, mixed>|null
     */
    public function find_latest_microsoft(): ?array
    {
        global $wpdb;

        $table = Tables::mailbox_connections_table();
        $cols  = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$cols} FROM {$table}
                WHERE provider = %s
                ORDER BY id DESC
                LIMIT 1",
                MailboxConnectionConstants::PROVIDER_MICROSOFT_GRAPH
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function count_enabled_microsoft(?int $exclude_id = null): int
    {
        global $wpdb;

        $table = Tables::mailbox_connections_table();

        if (null !== $exclude_id && $exclude_id > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table}
                    WHERE provider = %s AND is_enabled = 1 AND id <> %d",
                    MailboxConnectionConstants::PROVIDER_MICROSOFT_GRAPH,
                    $exclude_id
                )
            );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                WHERE provider = %s AND is_enabled = 1",
                MailboxConnectionConstants::PROVIDER_MICROSOFT_GRAPH
            )
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(Tables::mailbox_connections_table(), $data);

        return false === $result ? false : (int) $wpdb->insert_id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0 || [] === $data) {
            return false;
        }

        $result = $wpdb->update(
            Tables::mailbox_connections_table(),
            $data,
            ['id' => $id]
        );

        return false !== $result;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->delete(
            Tables::mailbox_connections_table(),
            ['id' => $id],
            ['%d']
        );

        return false !== $result;
    }
}
