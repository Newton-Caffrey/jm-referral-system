<?php

namespace JMReferral\Meeting;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Users\UserProvider;

/**
 * Read-only meeting presentation helpers (Phase 4B.2.1).
 * Does not create, update, or delete meetings/attendees.
 */
class ReferralMeetingReadService
{
    public const LIST_PER_PAGE = 20;

    public function __construct(
        private ReferralMeetingRepository $meeting_repository,
        private MeetingAttendeeRepository $attendee_repository,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider
    ) {
    }

    /**
     * Compact referral-workspace summary. Empty payload when view denied.
     *
     * @param array<string, mixed> $referral
     * @return array{
     *   can_view: bool,
     *   can_manage: bool,
     *   counts: array{total: int, draft: int, scheduled: int, completed: int, cancelled: int},
     *   next_meeting: array<string, mixed>|null,
     *   list_url: string
     * }
     */
    public function get_referral_summary(array $referral, ?int $user_id = null): array
    {
        $referral_id = absint($referral['id'] ?? 0);
        $can_view    = $referral_id > 0 && $this->access_policy->can_view_referral_meetings($referral, $user_id);
        $can_manage  = $can_view && $this->access_policy->can_manage_referral_meetings($referral, $user_id);

        if (! $can_view) {
            return [
                'can_view'     => false,
                'can_manage'   => false,
                'counts'       => [
                    'total'     => 0,
                    'draft'     => 0,
                    'scheduled' => 0,
                    'completed' => 0,
                    'cancelled' => 0,
                ],
                'next_meeting' => null,
                'list_url'     => '',
            ];
        }

        $counts = $this->meeting_repository->count_by_status_for_referral($referral_id);
        $next   = $this->meeting_repository->find_next_upcoming_for_referral($referral_id);
        if (null !== $next) {
            $next = $this->present_meeting_row($next, false);
        }

        return [
            'can_view'     => true,
            'can_manage'   => $can_manage,
            'counts'       => $counts,
            'next_meeting' => $next,
            'list_url'     => \JMReferral\Portal\PortalUrls::referral_meetings($referral_id),
        ];
    }

    /**
     * @param array<string, mixed> $referral
     * @return array{
     *   can_view: bool,
     *   can_manage: bool,
     *   counts: array{total: int, draft: int, scheduled: int, completed: int, cancelled: int},
     *   meetings: array<int, array<string, mixed>>,
     *   page: int,
     *   per_page: int,
     *   total: int,
     *   total_pages: int
     * }|null
     */
    public function get_list_page(array $referral, int $page = 1, ?int $user_id = null): ?array
    {
        if (! $this->access_policy->can_view_referral_meetings($referral, $user_id)) {
            return null;
        }

        $referral_id = absint($referral['id'] ?? 0);
        $per_page    = self::LIST_PER_PAGE;
        $page        = max(1, $page);
        $offset      = ($page - 1) * $per_page;

        $result   = $this->meeting_repository->list_for_ui($referral_id, $per_page, $offset);
        $rows     = $result['rows'];
        $total    = $result['total'];
        $ids      = array_map(static fn (array $r): int => absint($r['id'] ?? 0), $rows);
        $counts   = $this->attendee_repository->count_kinds_by_meeting_ids($ids);
        $user_ids = [];
        foreach ($rows as $row) {
            $user_ids[] = absint($row['created_by'] ?? 0);
            $user_ids[] = absint($row['updated_by'] ?? 0);
        }
        $names = $this->user_provider->get_display_names_by_ids($user_ids);

        $meetings = [];
        foreach ($rows as $row) {
            $mid      = absint($row['id'] ?? 0);
            $presented = $this->present_meeting_row($row, false);
            $presented['internal_count'] = $counts[$mid]['internal'] ?? 0;
            $presented['external_count'] = $counts[$mid]['external'] ?? 0;
            $created_by                  = absint($row['created_by'] ?? 0);
            $presented['created_by_name'] = $created_by > 0
                ? ($names[$created_by] ?? __('Unavailable user', 'jm-referral-system'))
                : '';
            $presented['detail_url'] = \JMReferral\Portal\PortalUrls::referral_meeting($referral_id, $mid);
            $meetings[]              = $presented;
        }

        $total_pages = (int) max(1, (int) ceil($total / $per_page));

        return [
            'can_view'     => true,
            'can_manage'   => $this->access_policy->can_manage_referral_meetings($referral, $user_id),
            'counts'       => $this->meeting_repository->count_by_status_for_referral($referral_id),
            'meetings'     => $meetings,
            'page'         => $page,
            'per_page'     => $per_page,
            'total'        => $total,
            'total_pages'  => $total_pages,
        ];
    }

    /**
     * @param array<string, mixed> $referral
     * @return array<string, mixed>|null Null when not found or not authorised (caller maps to 404).
     */
    public function get_detail_page(array $referral, int $meeting_id, ?int $user_id = null): ?array
    {
        if (! $this->access_policy->can_view_referral_meetings($referral, $user_id)) {
            return null;
        }

        $referral_id = absint($referral['id'] ?? 0);
        $meeting     = $this->meeting_repository->find($meeting_id);
        if (null === $meeting || absint($meeting['referral_id'] ?? 0) !== $referral_id) {
            return null;
        }

        $can_manage         = $this->access_policy->can_manage_referral_meetings($referral, $user_id);
        $can_view_contacts  = $this->access_policy->can_view_referral_meeting_contacts($referral, $user_id);
        $attendees          = $this->attendee_repository->list_by_meeting($meeting_id);

        $user_ids = [
            absint($meeting['created_by'] ?? 0),
            absint($meeting['updated_by'] ?? 0),
        ];
        foreach ($attendees as $attendee) {
            if (MeetingAttendee::KIND_INTERNAL === (string) ($attendee['attendee_kind'] ?? '')) {
                $user_ids[] = absint($attendee['user_id'] ?? 0);
            }
        }
        $names = $this->user_provider->get_display_names_by_ids($user_ids);

        $presented = $this->present_meeting_row($meeting, $can_view_contacts);
        $created_by = absint($meeting['created_by'] ?? 0);
        $updated_by = absint($meeting['updated_by'] ?? 0);
        $presented['created_by_name'] = $created_by > 0
            ? ($names[$created_by] ?? __('Unavailable user', 'jm-referral-system'))
            : '';
        $presented['updated_by_name'] = $updated_by > 0
            ? ($names[$updated_by] ?? __('Unavailable user', 'jm-referral-system'))
            : '';

        $internal = [];
        $external = [];
        foreach ($attendees as $attendee) {
            $row = $this->present_attendee_row($attendee, $can_view_contacts, $names);
            if (MeetingAttendee::KIND_INTERNAL === (string) ($attendee['attendee_kind'] ?? '')) {
                $internal[] = $row;
            } else {
                $external[] = $row;
            }
        }

        return [
            'can_view'           => true,
            'can_manage'         => $can_manage,
            'can_view_contacts'  => $can_view_contacts,
            'meeting'            => $presented,
            'internal'           => $internal,
            'external'           => $external,
            'list_url'           => \JMReferral\Portal\PortalUrls::referral_meetings($referral_id),
            'referral_url'       => \JMReferral\Portal\PortalUrls::referral($referral_id),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present_meeting_row(array $row, bool $include_sensitive_url): array
    {
        $location_type = (string) ($row['location_type'] ?? '');
        $location_name = (string) ($row['location_name'] ?? '');
        $type          = (string) ($row['meeting_type'] ?? '');
        $status        = (string) ($row['status'] ?? '');

        $location_summary = ReferralMeeting::location_type_label($location_type);
        if ('' !== $location_name) {
            $location_summary = '' !== $location_summary
                ? $location_summary . ' — ' . $location_name
                : $location_name;
        }
        if ('' === $location_summary) {
            $location_summary = '—';
        }

        $out = [
            'id'                 => absint($row['id'] ?? 0),
            'referral_id'        => absint($row['referral_id'] ?? 0),
            'meeting_type'       => $type,
            'meeting_type_label' => ReferralMeeting::type_label($type),
            'status'             => $status,
            'status_label'       => ReferralMeeting::status_label($status),
            'scheduled_at'       => (string) ($row['scheduled_at'] ?? ''),
            'scheduled_end_at'   => (string) ($row['scheduled_end_at'] ?? ''),
            'location_type'      => $location_type,
            'location_type_label'=> ReferralMeeting::location_type_label($location_type),
            'location_name'      => $location_name,
            'location_address'   => (string) ($row['location_address'] ?? ''),
            'location_summary'   => $location_summary,
            'purpose'            => (string) ($row['purpose'] ?? ''),
            'outcome'            => (string) ($row['outcome'] ?? ''),
            'created_by'         => absint($row['created_by'] ?? 0),
            'updated_by'         => absint($row['updated_by'] ?? 0),
            'created_at'         => (string) ($row['created_at'] ?? ''),
            'updated_at'         => (string) ($row['updated_at'] ?? ''),
            'completed_at'       => (string) ($row['completed_at'] ?? ''),
            'cancelled_at'       => (string) ($row['cancelled_at'] ?? ''),
        ];

        if ($include_sensitive_url) {
            $url = trim((string) ($row['online_meeting_url'] ?? ''));
            $out['online_meeting_url'] = '' !== $url ? esc_url_raw($url) : '';
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $attendee
     * @param array<int, string>   $names
     * @return array<string, mixed>
     */
    private function present_attendee_row(array $attendee, bool $include_contacts, array $names): array
    {
        $kind   = (string) ($attendee['attendee_kind'] ?? '');
        $user_id = absint($attendee['user_id'] ?? 0);
        $category = (string) ($attendee['participant_category'] ?? '');
        $attendance = (string) ($attendee['attendance_status'] ?? '');

        $display = (string) ($attendee['display_name'] ?? '');
        if (MeetingAttendee::KIND_INTERNAL === $kind) {
            $display = $user_id > 0
                ? ($names[$user_id] ?? __('Unavailable user', 'jm-referral-system'))
                : __('Unavailable user', 'jm-referral-system');
        }

        $row = [
            'id'                      => absint($attendee['id'] ?? 0),
            'attendee_kind'           => $kind,
            'display_name'            => $display,
            'professional_role'       => (string) ($attendee['professional_role'] ?? ''),
            'organisation'            => (string) ($attendee['organisation'] ?? ''),
            'participant_category'    => $category,
            'participant_category_label' => MeetingAttendee::category_label($category),
            'meeting_role'            => (string) ($attendee['meeting_role'] ?? ''),
            'attendance_status'       => $attendance,
            'attendance_status_label' => MeetingAttendee::attendance_status_label($attendance),
        ];

        if ($include_contacts && MeetingAttendee::KIND_EXTERNAL === $kind) {
            $row['email']     = (string) ($attendee['email'] ?? '');
            $row['telephone'] = (string) ($attendee['telephone'] ?? '');
        }

        return $row;
    }
}
