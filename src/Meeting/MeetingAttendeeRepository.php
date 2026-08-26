<?php

namespace JMReferral\Meeting;

use JMReferral\Database\Tables;

class MeetingAttendeeRepository
{
    private const SELECT_COLUMNS = 'id, meeting_id, attendee_kind, user_id, display_name, professional_role,
        organisation, email, telephone, participant_category, meeting_role, attendance_status,
        sort_order, created_at, updated_at';

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::referral_meeting_attendees_table(),
            [
                'meeting_id'            => absint($data['meeting_id'] ?? 0),
                'attendee_kind'         => (string) ($data['attendee_kind'] ?? ''),
                'user_id'               => isset($data['user_id']) && null !== $data['user_id'] && '' !== (string) $data['user_id']
                    ? absint($data['user_id'])
                    : null,
                'display_name'          => $data['display_name'] ?? null,
                'professional_role'     => $data['professional_role'] ?? null,
                'organisation'          => $data['organisation'] ?? null,
                'email'                 => $data['email'] ?? null,
                'telephone'             => $data['telephone'] ?? null,
                'participant_category'  => $data['participant_category'] ?? null,
                'meeting_role'          => $data['meeting_role'] ?? null,
                'attendance_status'     => (string) ($data['attendance_status'] ?? MeetingAttendee::ATTENDANCE_INVITED),
                'sort_order'            => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
                'created_at'            => (string) ($data['created_at'] ?? current_time('mysql')),
                'updated_at'            => (string) ($data['updated_at'] ?? current_time('mysql')),
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
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

        $map = [
            'attendee_kind'        => '%s',
            'user_id'              => '%d',
            'display_name'         => '%s',
            'professional_role'    => '%s',
            'organisation'         => '%s',
            'email'                => '%s',
            'telephone'            => '%s',
            'participant_category' => '%s',
            'meeting_role'         => '%s',
            'attendance_status'    => '%s',
            'sort_order'           => '%d',
            'updated_at'           => '%s',
        ];

        $row     = [];
        $formats = [];
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
            Tables::referral_meeting_attendees_table(),
            $row,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return false !== $result;
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

        $table = Tables::referral_meeting_attendees_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
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
     * @return array<int, array<string, mixed>>
     */
    public function list_by_meeting(int $meeting_id): array
    {
        global $wpdb;

        if ($meeting_id <= 0) {
            return [];
        }

        $table = Tables::referral_meeting_attendees_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ' . self::SELECT_COLUMNS . " FROM {$table}
                WHERE meeting_id = %d
                ORDER BY sort_order ASC, id ASC",
                $meeting_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function has_internal_user(int $meeting_id, int $user_id, ?int $exclude_attendee_id = null): bool
    {
        global $wpdb;

        if ($meeting_id <= 0 || $user_id <= 0) {
            return false;
        }

        $table = Tables::referral_meeting_attendees_table();
        $sql   = "SELECT id FROM {$table}
            WHERE meeting_id = %d AND attendee_kind = %s AND user_id = %d";
        $params = [$meeting_id, MeetingAttendee::KIND_INTERNAL, $user_id];

        if (null !== $exclude_attendee_id && $exclude_attendee_id > 0) {
            $sql     .= ' AND id <> %d';
            $params[] = $exclude_attendee_id;
        }

        $sql .= ' LIMIT 1';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $id = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return absint($id) > 0;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->delete(
            Tables::referral_meeting_attendees_table(),
            ['id' => $id],
            ['%d']
        );

        return false !== $result;
    }

    public function delete_by_meeting_id(int $meeting_id): bool
    {
        global $wpdb;

        if ($meeting_id <= 0) {
            return false;
        }

        $result = $wpdb->delete(
            Tables::referral_meeting_attendees_table(),
            ['meeting_id' => $meeting_id],
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Permanent cleanup for all attendees of meetings belonging to a referral.
     */
    public function delete_by_referral_id(int $referral_id): bool
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return false;
        }

        $attendees = Tables::referral_meeting_attendees_table();
        $meetings  = Tables::referral_meetings_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names trusted.
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE a FROM {$attendees} a
                INNER JOIN {$meetings} m ON m.id = a.meeting_id
                WHERE m.referral_id = %d",
                $referral_id
            )
        );

        return false !== $result;
    }

    /**
     * @param array<int, int> $meeting_ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function list_grouped_by_meeting_ids(array $meeting_ids): array
    {
        global $wpdb;

        $meeting_ids = array_values(array_unique(array_filter(array_map('absint', $meeting_ids))));
        if ([] === $meeting_ids) {
            return [];
        }

        $table        = Tables::referral_meeting_attendees_table();
        $placeholders = implode(',', array_fill(0, count($meeting_ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ' . self::SELECT_COLUMNS . " FROM {$table}
                WHERE meeting_id IN ({$placeholders})
                ORDER BY meeting_id ASC, sort_order ASC, id ASC",
                ...$meeting_ids
            ),
            ARRAY_A
        );

        $out = [];
        foreach ($meeting_ids as $mid) {
            $out[$mid] = [];
        }
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $mid = absint($row['meeting_id'] ?? 0);
                if ($mid > 0) {
                    $out[$mid][] = $row;
                }
            }
        }

        return $out;
    }
}
