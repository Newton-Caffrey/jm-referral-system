<?php

namespace JMReferral\Mailbox;

use JMReferral\Database\Tables;

class MailboxConnectionSecretRepository
{
    /**
     * @return array{id: int, connection_id: int, secret_name: string, algorithm: string, key_version: int, nonce: string, ciphertext: string, created_at: string, updated_at: string}|null
     */
    public function find(int $connection_id, string $secret_name): ?array
    {
        global $wpdb;

        if ($connection_id <= 0 || '' === $secret_name) {
            return null;
        }

        $table = Tables::mailbox_connection_secrets_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, connection_id, secret_name, algorithm, key_version, nonce, ciphertext, created_at, updated_at
                FROM {$table}
                WHERE connection_id = %d AND secret_name = %s
                LIMIT 1",
                $connection_id,
                $secret_name
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function exists(int $connection_id, string $secret_name): bool
    {
        return null !== $this->find($connection_id, $secret_name);
    }

    /**
     * @param array{connection_id: int, secret_name: string, algorithm: string, key_version: int, nonce: string, ciphertext: string, created_at: string, updated_at: string} $data
     */
    public function upsert(array $data): bool
    {
        global $wpdb;

        $existing = $this->find((int) $data['connection_id'], (string) $data['secret_name']);
        $table    = Tables::mailbox_connection_secrets_table();

        if (null !== $existing) {
            $result = $wpdb->update(
                $table,
                [
                    'algorithm'   => $data['algorithm'],
                    'key_version' => $data['key_version'],
                    'nonce'       => $data['nonce'],
                    'ciphertext'  => $data['ciphertext'],
                    'updated_at'  => $data['updated_at'],
                ],
                ['id' => (int) $existing['id']],
                ['%s', '%d', '%s', '%s', '%s'],
                ['%d']
            );

            return false !== $result;
        }

        $result = $wpdb->insert(
            $table,
            $data,
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        return false !== $result;
    }

    public function delete_for_connection(int $connection_id): int
    {
        global $wpdb;

        if ($connection_id <= 0) {
            return 0;
        }

        $result = $wpdb->delete(
            Tables::mailbox_connection_secrets_table(),
            ['connection_id' => $connection_id],
            ['%d']
        );

        return false === $result ? 0 : (int) $result;
    }

    public function delete(int $connection_id, string $secret_name): bool
    {
        global $wpdb;

        if ($connection_id <= 0 || '' === $secret_name) {
            return false;
        }

        $result = $wpdb->delete(
            Tables::mailbox_connection_secrets_table(),
            [
                'connection_id' => $connection_id,
                'secret_name'   => $secret_name,
            ],
            ['%d', '%s']
        );

        return false !== $result;
    }
}
