<?php

namespace JMReferral\ReferralInbox;

use JMReferral\Database\Tables;

class ReferralInboxRepository
{
    private const SELECT_COLUMNS = 'id, source_provider, mailbox_identifier, provider_message_id, internet_message_id, conversation_identifier, dedupe_key, sender_name, sender_email, sender_domain, recipient_summary, subject, body_preview, received_at, status, detection_status, detection_reason, local_authority_id, attachment_count, linked_referral_id, reviewed_by, reviewed_at, accepted_by, accepted_at, ignored_by, ignored_at, duplicate_of_inbox_id, response_started_at, response_sent_at, error_code, error_message, created_at, updated_at';

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = Tables::referral_inbox_table();
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
     * @return array<string, mixed>|null
     */
    public function findByDedupeKey(string $dedupe_key): ?array
    {
        global $wpdb;

        if ('' === $dedupe_key) {
            return null;
        }

        $table = Tables::referral_inbox_table();
        $cols  = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/columns trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$cols} FROM {$table} WHERE dedupe_key = %s LIMIT 1",
                $dedupe_key
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByProviderIdentity(string $source_provider, string $mailbox_identifier, string $provider_message_id): ?array
    {
        global $wpdb;

        $table = Tables::referral_inbox_table();
        $cols  = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/columns trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$cols} FROM {$table}
                WHERE source_provider = %s
                  AND mailbox_identifier = %s
                  AND provider_message_id = %s
                LIMIT 1",
                $source_provider,
                $mailbox_identifier,
                $provider_message_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return int|false Inserted ID, or false on failure (including duplicate-key race).
     */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::referral_inbox_table(),
            [
                'source_provider'         => $data['source_provider'],
                'mailbox_identifier'      => $data['mailbox_identifier'],
                'provider_message_id'     => $data['provider_message_id'],
                'internet_message_id'     => $data['internet_message_id'],
                'conversation_identifier' => $data['conversation_identifier'],
                'dedupe_key'              => $data['dedupe_key'],
                'sender_name'             => $data['sender_name'],
                'sender_email'            => $data['sender_email'],
                'sender_domain'           => $data['sender_domain'],
                'recipient_summary'       => $data['recipient_summary'],
                'subject'                 => $data['subject'],
                'body_preview'            => $data['body_preview'],
                'received_at'             => $data['received_at'],
                'status'                  => $data['status'],
                'detection_status'        => $data['detection_status'],
                'detection_reason'        => $data['detection_reason'],
                'local_authority_id'      => $data['local_authority_id'],
                'attachment_count'        => $data['attachment_count'],
                'linked_referral_id'      => $data['linked_referral_id'],
                'reviewed_by'             => $data['reviewed_by'],
                'reviewed_at'             => $data['reviewed_at'],
                'accepted_by'             => $data['accepted_by'],
                'accepted_at'             => $data['accepted_at'],
                'ignored_by'              => $data['ignored_by'],
                'ignored_at'              => $data['ignored_at'],
                'duplicate_of_inbox_id'   => $data['duplicate_of_inbox_id'],
                'response_started_at'     => $data['response_started_at'],
                'response_sent_at'        => $data['response_sent_at'],
                'error_code'              => $data['error_code'],
                'error_message'           => $data['error_message'],
                'created_at'              => $data['created_at'],
                'updated_at'              => $data['updated_at'],
            ],
            [
                '%s', // source_provider
                '%s', // mailbox_identifier
                '%s', // provider_message_id
                '%s', // internet_message_id
                '%s', // conversation_identifier
                '%s', // dedupe_key
                '%s', // sender_name
                '%s', // sender_email
                '%s', // sender_domain
                '%s', // recipient_summary
                '%s', // subject
                '%s', // body_preview
                '%s', // received_at
                '%s', // status
                '%s', // detection_status
                '%s', // detection_reason
                '%d', // local_authority_id
                '%d', // attachment_count
                '%d', // linked_referral_id
                '%d', // reviewed_by
                '%s', // reviewed_at
                '%d', // accepted_by
                '%s', // accepted_at
                '%d', // ignored_by
                '%s', // ignored_at
                '%d', // duplicate_of_inbox_id
                '%s', // response_started_at
                '%s', // response_sent_at
                '%s', // error_code
                '%s', // error_message
                '%s', // created_at
                '%s', // updated_at
            ]
        );

        if (false === $result) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    public function is_duplicate_key_error(): bool
    {
        global $wpdb;

        $errno = (int) $wpdb->last_errno;
        if (1062 === $errno) {
            return true;
        }

        $error = (string) $wpdb->last_error;

        return false !== stripos($error, 'Duplicate') || false !== stripos($error, 'dedupe_key');
    }

    /**
     * Compare-and-set status transition.
     *
     * @param array<string, mixed> $extra Additional columns to set (already sanitised).
     * @return int Rows affected (0 = conflict / already changed).
     */
    public function transition_status(int $id, string $expected_status, string $new_status, array $extra, string $updated_at): int
    {
        global $wpdb;

        if ($id <= 0) {
            return 0;
        }

        $table  = Tables::referral_inbox_table();
        $set    = ['status = %s', 'updated_at = %s'];
        $params = [$new_status, $updated_at];

        foreach ($extra as $column => $value) {
            if (! preg_match('/^[a-z_]+$/', $column)) {
                continue;
            }
            if (null === $value) {
                $set[] = "{$column} = NULL";
            } elseif (is_int($value)) {
                $set[]    = "{$column} = %d";
                $params[] = $value;
            } else {
                $set[]    = "{$column} = %s";
                $params[] = (string) $value;
            }
        }

        $params[] = $id;
        $params[] = $expected_status;

        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $set) . ' WHERE id = %d AND status = %s';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fragments built from allowlisted columns.
        $wpdb->query($wpdb->prepare($sql, ...$params));

        return (int) $wpdb->rows_affected;
    }

    /**
     * Sets first-review metadata only when reviewed_at is still NULL.
     *
     * @return int Rows affected (0 = already reviewed or missing).
     */
    public function set_first_review(int $id, int $actor_id, string $reviewed_at, string $updated_at): int
    {
        global $wpdb;

        if ($id <= 0 || $actor_id <= 0) {
            return 0;
        }

        $table = Tables::referral_inbox_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table trusted.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                SET reviewed_by = %d, reviewed_at = %s, updated_at = %s
                WHERE id = %d
                  AND status = %s
                  AND reviewed_at IS NULL",
                $actor_id,
                $reviewed_at,
                $updated_at,
                $id,
                ReferralInboxStatus::NEEDS_REVIEW
            )
        );

        return (int) $wpdb->rows_affected;
    }

    /**
     * @param array<string, mixed> $fields Allowlisted nullable/scalar fields.
     */
    public function update_fields(int $id, array $fields, string $updated_at): bool
    {
        global $wpdb;

        if ($id <= 0 || empty($fields)) {
            return false;
        }

        $allowed = [
            'detection_status',
            'detection_reason',
            'local_authority_id',
            'attachment_count',
            'linked_referral_id',
            'error_code',
            'error_message',
        ];

        $data    = ['updated_at' => $updated_at];
        $formats = ['%s'];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $fields)) {
                continue;
            }
            $data[$key] = $fields[$key];
            $formats[]  = in_array($key, ['local_authority_id', 'attachment_count', 'linked_referral_id'], true)
                ? '%d'
                : '%s';
        }

        if (count($data) <= 1) {
            return false;
        }

        $result = $wpdb->update(
            Tables::referral_inbox_table(),
            $data,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return false !== $result;
    }
}
