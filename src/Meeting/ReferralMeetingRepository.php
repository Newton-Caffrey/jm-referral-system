<?php

namespace JMReferral\Meeting;

use JMReferral\Database\Tables;

class ReferralMeetingRepository
{
    private const SELECT_COLUMNS = 'id, referral_id, meeting_type, status, scheduled_at, scheduled_end_at,
        location_type, location_name, location_address, online_meeting_url, purpose, outcome,
        created_by, updated_by, created_at, updated_at, completed_at, cancelled_at';

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            Tables::referral_meetings_table(),
            [
                'referral_id'        => absint($data['referral_id'] ?? 0),
                'meeting_type'       => (string) ($data['meeting_type'] ?? ''),
                'status'             => (string) ($data['status'] ?? ReferralMeeting::STATUS_DRAFT),
                'scheduled_at'       => $data['scheduled_at'] ?? null,
                'scheduled_end_at'   => $data['scheduled_end_at'] ?? null,
                'location_type'      => $data['location_type'] ?? null,
                'location_name'      => $data['location_name'] ?? null,
                'location_address'   => $data['location_address'] ?? null,
                'online_meeting_url' => $data['online_meeting_url'] ?? null,
                'purpose'            => $data['purpose'] ?? null,
                'outcome'            => $data['outcome'] ?? null,
                'created_by'         => isset($data['created_by']) ? absint($data['created_by']) : null,
                'updated_by'         => isset($data['updated_by']) ? absint($data['updated_by']) : null,
                'created_at'         => (string) ($data['created_at'] ?? current_time('mysql')),
                'updated_at'         => (string) ($data['updated_at'] ?? current_time('mysql')),
                'completed_at'       => $data['completed_at'] ?? null,
                'cancelled_at'       => $data['cancelled_at'] ?? null,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s']
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
            'meeting_type'       => '%s',
            'status'             => '%s',
            'scheduled_at'       => '%s',
            'scheduled_end_at'   => '%s',
            'location_type'      => '%s',
            'location_name'      => '%s',
            'location_address'   => '%s',
            'online_meeting_url' => '%s',
            'purpose'            => '%s',
            'outcome'            => '%s',
            'updated_by'         => '%d',
            'updated_at'         => '%s',
            'completed_at'       => '%s',
            'cancelled_at'       => '%s',
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
            Tables::referral_meetings_table(),
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

        $table = Tables::referral_meetings_table();
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
    public function list_by_referral(int $referral_id): array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return [];
        }

        $table = Tables::referral_meetings_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ' . self::SELECT_COLUMNS . " FROM {$table}
                WHERE referral_id = %d
                ORDER BY
                    CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END ASC,
                    scheduled_at ASC,
                    id ASC",
                $referral_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Counts meetings for a referral grouped by status.
     *
     * @return array{total: int, draft: int, scheduled: int, completed: int, cancelled: int}
     */
    public function count_by_status_for_referral(int $referral_id): array
    {
        global $wpdb;

        $counts = [
            'total'     => 0,
            'draft'     => 0,
            'scheduled' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];

        if ($referral_id <= 0) {
            return $counts;
        }

        $table = Tables::referral_meetings_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) AS cnt FROM {$table}
                WHERE referral_id = %d
                GROUP BY status",
                $referral_id
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return $counts;
        }

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $cnt    = absint($row['cnt'] ?? 0);
            $counts['total'] += $cnt;
            if (isset($counts[$status])) {
                $counts[$status] = $cnt;
            }
        }

        return $counts;
    }

    /**
     * UI list ordering: upcoming scheduled → past scheduled → drafts → completed → cancelled.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function list_for_ui(int $referral_id, int $limit = 20, int $offset = 0): array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return ['rows' => [], 'total' => 0];
        }

        $limit  = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $table  = Tables::referral_meetings_table();
        $now    = current_time('mysql');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE referral_id = %d",
                $referral_id
            )
        );

        $order = '
            CASE
                WHEN status = \'' . ReferralMeeting::STATUS_SCHEDULED . '\' AND scheduled_at IS NOT NULL AND scheduled_at >= %s THEN 0
                WHEN status = \'' . ReferralMeeting::STATUS_SCHEDULED . '\' THEN 1
                WHEN status = \'' . ReferralMeeting::STATUS_DRAFT . '\' THEN 2
                WHEN status = \'' . ReferralMeeting::STATUS_COMPLETED . '\' THEN 3
                WHEN status = \'' . ReferralMeeting::STATUS_CANCELLED . '\' THEN 4
                ELSE 5
            END ASC,
            CASE
                WHEN status = \'' . ReferralMeeting::STATUS_SCHEDULED . '\' AND scheduled_at IS NOT NULL AND scheduled_at >= %s THEN scheduled_at
            END ASC,
            CASE
                WHEN status = \'' . ReferralMeeting::STATUS_SCHEDULED . '\' AND (scheduled_at IS NULL OR scheduled_at < %s) THEN scheduled_at
            END DESC,
            updated_at DESC,
            id DESC';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ' . self::SELECT_COLUMNS . " FROM {$table}
                WHERE referral_id = %d
                ORDER BY {$order}
                LIMIT %d OFFSET %d",
                $referral_id,
                $now,
                $now,
                $now,
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return [
            'rows'  => is_array($rows) ? $rows : [],
            'total' => $total,
        ];
    }

    /**
     * Next upcoming non-cancelled meeting with scheduled_at >= now (site time).
     *
     * @return array<string, mixed>|null
     */
    public function find_next_upcoming_for_referral(int $referral_id): ?array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return null;
        }

        $table = Tables::referral_meetings_table();
        $now   = current_time('mysql');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT ' . self::SELECT_COLUMNS . " FROM {$table}
                WHERE referral_id = %d
                  AND status <> %s
                  AND scheduled_at IS NOT NULL
                  AND scheduled_at >= %s
                ORDER BY scheduled_at ASC, id ASC
                LIMIT 1",
                $referral_id,
                ReferralMeeting::STATUS_CANCELLED,
                $now
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Latest relevant meeting: prefer next upcoming, else latest non-cancelled by scheduled_at/id.
     *
     * @return array<string, mixed>|null
     */
    public function find_latest_relevant_for_referral(int $referral_id): ?array
    {
        $upcoming = $this->find_next_upcoming_for_referral($referral_id);
        if (null !== $upcoming) {
            return $upcoming;
        }

        global $wpdb;

        if ($referral_id <= 0) {
            return null;
        }

        $table = Tables::referral_meetings_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT ' . self::SELECT_COLUMNS . " FROM {$table}
                WHERE referral_id = %d
                  AND status <> %s
                ORDER BY
                    CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END ASC,
                    scheduled_at DESC,
                    id DESC
                LIMIT 1",
                $referral_id,
                ReferralMeeting::STATUS_CANCELLED
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function mark_scheduled(int $id, ?int $updated_by = null): bool
    {
        return $this->update($id, [
            'status'       => ReferralMeeting::STATUS_SCHEDULED,
            'cancelled_at' => null,
            'completed_at' => null,
            'updated_by'   => $updated_by,
            'updated_at'   => current_time('mysql'),
        ]);
    }

    public function mark_completed(int $id, ?int $updated_by = null): bool
    {
        return $this->update($id, [
            'status'       => ReferralMeeting::STATUS_COMPLETED,
            'completed_at' => current_time('mysql'),
            'cancelled_at' => null,
            'updated_by'   => $updated_by,
            'updated_at'   => current_time('mysql'),
        ]);
    }

    public function mark_cancelled(int $id, ?int $updated_by = null): bool
    {
        return $this->update($id, [
            'status'       => ReferralMeeting::STATUS_CANCELLED,
            'cancelled_at' => current_time('mysql'),
            'updated_by'   => $updated_by,
            'updated_at'   => current_time('mysql'),
        ]);
    }

    /**
     * Permanent cleanup for referral deletion (attendees must be deleted first by caller).
     */
    public function delete_by_referral_id(int $referral_id): bool
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return false;
        }

        $result = $wpdb->delete(
            Tables::referral_meetings_table(),
            ['referral_id' => $referral_id],
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Batch: latest relevant meeting per referral (for future dashboard). Non-cancelled preferred.
     *
     * @param array<int, int> $referral_ids
     * @return array<int, array<string, mixed>>
     */
    public function latest_relevant_map_for_referrals(array $referral_ids): array
    {
        $referral_ids = array_values(array_unique(array_filter(array_map('absint', $referral_ids))));
        if ([] === $referral_ids) {
            return [];
        }

        $map = [];
        foreach ($referral_ids as $rid) {
            $row = $this->find_latest_relevant_for_referral($rid);
            if (null !== $row) {
                $map[$rid] = $row;
            }
        }

        return $map;
    }
}
