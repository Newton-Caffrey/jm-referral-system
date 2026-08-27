<?php

namespace JMReferral\Meeting;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;

/**
 * Meeting attendee mutations (Phase 4B.2.3 internal; Phase 4B.2.4 external).
 * Does not log email/telephone. Does not grant access via attendee membership.
 * Does not send emails or change workflow stages.
 */
class MeetingAttendeeService
{
    private MeetingLifecyclePolicy $lifecycle_policy;

    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralMeetingRepository $meeting_repository,
        private MeetingAttendeeRepository $attendee_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider,
        ?MeetingLifecyclePolicy $lifecycle_policy = null
    ) {
        $this->lifecycle_policy = $lifecycle_policy ?? new MeetingLifecyclePolicy();
    }

    /**
     * Add an internal staff attendee. Attendee kind is forced to internal.
     *
     * @param array<string, mixed> $input
     * @return array{ok: true, attendee_id: int}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function add_internal_attendee(int $meeting_id, array $input, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_meeting_context($meeting_id, $actor_user_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>} $ctx */
        $status = (string) ($ctx['meeting']['status'] ?? '');
        if (! $this->lifecycle_policy->allows_internal_attendee_add($status)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }

        $user_id = absint($input['user_id'] ?? 0);
        $role    = $this->nullable_text((string) ($input['meeting_role'] ?? ''), 150);
        $attendance = sanitize_key((string) ($input['attendance_status'] ?? MeetingAttendee::ATTENDANCE_INVITED));
        if ('' === $attendance) {
            $attendance = MeetingAttendee::ATTENDANCE_INVITED;
        }

        $errors = [];
        if ($user_id <= 0 || ! $this->user_provider->is_assignable($user_id)) {
            $errors['user_id'] = __('Please select a valid internal staff user.', 'jm-referral-system');
        } elseif ($this->attendee_repository->has_internal_user($meeting_id, $user_id, null)) {
            $errors['user_id'] = __('This staff member is already an attendee of this meeting.', 'jm-referral-system');
        }

        if (null === $role || '' === trim($role)) {
            $errors['meeting_role'] = __('Please enter a meeting role.', 'jm-referral-system');
        }

        if (! MeetingAttendee::is_valid_attendance_status($attendance)) {
            $errors['attendance_status'] = __('Please select a valid attendance status.', 'jm-referral-system');
        }

        if ([] !== $errors) {
            return ['ok' => false, 'error' => 'validation', 'field_errors' => $errors];
        }

        $now = current_time('mysql');
        $id  = $this->attendee_repository->create([
            'meeting_id'            => $meeting_id,
            'attendee_kind'         => MeetingAttendee::KIND_INTERNAL,
            'user_id'               => $user_id,
            'display_name'          => null,
            'professional_role'     => null,
            'organisation'          => null,
            'email'                 => null,
            'telephone'             => null,
            'participant_category'  => null,
            'meeting_role'          => $role,
            'attendance_status'     => $attendance,
            'sort_order'            => 0,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        if (false === $id) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_attendee_added(
            $ctx['referral_id'],
            MeetingAttendee::KIND_INTERNAL
        );

        return ['ok' => true, 'attendee_id' => $id];
    }

    /**
     * Update internal attendee role and/or attendance (draft/scheduled), or attendance only (completed).
     *
     * @param array<string, mixed> $input
     * @return array{ok: true, changed?: bool}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function update_internal_attendee(int $attendee_id, array $input, ?int $actor_user_id = null, ?int $expected_meeting_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_internal_attendee_context($attendee_id, $actor_user_id, $expected_meeting_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>, attendee: array<string, mixed>} $ctx */
        $meeting = $ctx['meeting'];
        $status  = (string) ($meeting['status'] ?? '');

        if ($this->lifecycle_policy->allows_internal_attendee_edit($status)) {
            return $this->update_internal_full($ctx, $input);
        }

        if ($this->lifecycle_policy->allows_internal_attendance_correction($status)) {
            return $this->update_internal_attendance_only($ctx, $input);
        }

        return ['ok' => false, 'error' => 'invalid_transition'];
    }

    /**
     * Correct attendance after meeting completion (final statuses only).
     *
     * @param array<string, mixed> $input
     * @return array{ok: true, changed?: bool}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function update_internal_attendance(int $attendee_id, array $input, ?int $actor_user_id = null, ?int $expected_meeting_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_internal_attendee_context($attendee_id, $actor_user_id, $expected_meeting_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>, attendee: array<string, mixed>} $ctx */
        $status = (string) ($ctx['meeting']['status'] ?? '');
        if (! $this->lifecycle_policy->allows_internal_attendance_correction($status)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }

        return $this->update_internal_attendance_only($ctx, $input);
    }

    /**
     * Remove an internal attendee from a draft or scheduled meeting.
     *
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function remove_internal_attendee(int $attendee_id, ?int $actor_user_id = null, ?int $expected_meeting_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_internal_attendee_context($attendee_id, $actor_user_id, $expected_meeting_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>, attendee: array<string, mixed>} $ctx */
        $status = (string) ($ctx['meeting']['status'] ?? '');
        if (! $this->lifecycle_policy->allows_internal_attendee_remove($status)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }

        if (! $this->attendee_repository->delete($attendee_id)) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_attendee_removed($ctx['referral_id'], MeetingAttendee::KIND_INTERNAL);

        return ['ok' => true];
    }

    /**
     * Count attendees (any kind) with non-final attendance (invited/confirmed).
     */
    public function count_non_final_attendance(int $meeting_id): int
    {
        $count = 0;
        foreach ($this->attendee_repository->list_by_meeting($meeting_id) as $row) {
            $attendance = (string) ($row['attendance_status'] ?? '');
            if (MeetingAttendee::is_non_final_attendance_status($attendance)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Eligible internal staff for the selector (VIEW_REFERRALS / is_assignable).
     *
     * @return array<int, array{id: int, display_name: string}>
     */
    public function eligible_internal_staff_for_meeting(int $meeting_id): array
    {
        $users = $this->user_provider->get_assignable_users();
        $out   = [];
        foreach ($users as $row) {
            $uid = absint($row['id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            if ($this->attendee_repository->has_internal_user($meeting_id, $uid, null)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param array{ok: true, referral_id: int, meeting: array<string, mixed>, attendee: array<string, mixed>} $ctx
     * @param array<string, mixed> $input
     * @return array{ok: true, changed?: bool}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    private function update_internal_full(array $ctx, array $input): array
    {
        $attendee = $ctx['attendee'];
        $role     = $this->nullable_text((string) ($input['meeting_role'] ?? ''), 150);
        $attendance = sanitize_key((string) ($input['attendance_status'] ?? ''));

        $errors = [];
        if (null === $role || '' === trim($role)) {
            $errors['meeting_role'] = __('Please enter a meeting role.', 'jm-referral-system');
        }
        if (! MeetingAttendee::is_valid_attendance_status($attendance)) {
            $errors['attendance_status'] = __('Please select a valid attendance status.', 'jm-referral-system');
        }
        if ([] !== $errors) {
            return ['ok' => false, 'error' => 'validation', 'field_errors' => $errors];
        }

        $old_role = (string) ($attendee['meeting_role'] ?? '');
        $old_att  = (string) ($attendee['attendance_status'] ?? '');
        if ($old_role === (string) $role && $old_att === $attendance) {
            return ['ok' => true, 'changed' => false];
        }

        $ok = $this->attendee_repository->update($attendee['id'], [
            'meeting_role'      => $role,
            'attendance_status' => $attendance,
            'updated_at'        => current_time('mysql'),
        ]);
        if (! $ok) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_attendee_updated($ctx['referral_id'], MeetingAttendee::KIND_INTERNAL);

        return ['ok' => true, 'changed' => true];
    }

    /**
     * @param array{ok: true, referral_id: int, meeting: array<string, mixed>, attendee: array<string, mixed>} $ctx
     * @param array<string, mixed> $input
     * @return array{ok: true, changed?: bool}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    private function update_internal_attendance_only(array $ctx, array $input): array
    {
        $attendee   = $ctx['attendee'];
        $attendance = sanitize_key((string) ($input['attendance_status'] ?? ''));

        if (! MeetingAttendee::is_final_attendance_status($attendance)) {
            return [
                'ok'           => false,
                'error'        => 'validation',
                'field_errors' => [
                    'attendance_status' => __('After completion, attendance may only be set to attended, absent, or declined.', 'jm-referral-system'),
                ],
            ];
        }

        $old_att = (string) ($attendee['attendance_status'] ?? '');
        if ($old_att === $attendance) {
            return ['ok' => true, 'changed' => false];
        }

        $ok = $this->attendee_repository->update(absint($attendee['id']), [
            'attendance_status' => $attendance,
            'updated_at'        => current_time('mysql'),
        ]);
        if (! $ok) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_attendee_updated($ctx['referral_id'], MeetingAttendee::KIND_INTERNAL);

        return ['ok' => true, 'changed' => true];
    }

    /**
     * @return array{ok: true, referral_id: int, meeting: array<string, mixed>}|array{ok: false, error: string}
     */
    private function resolve_meeting_context(int $meeting_id, int $actor_user_id): array
    {
        $meeting = $this->meeting_repository->find($meeting_id);
        if (null === $meeting) {
            return ['ok' => false, 'error' => 'meeting_not_found'];
        }

        $referral_id = absint($meeting['referral_id'] ?? 0);
        $referral    = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return ['ok' => false, 'error' => 'referral_not_found'];
        }

        if (null !== ($referral['archived_at'] ?? null) && '' !== (string) $referral['archived_at']) {
            return ['ok' => false, 'error' => 'archived'];
        }

        if (! $this->access_policy->can_view_referral($referral, $actor_user_id)) {
            return ['ok' => false, 'error' => 'forbidden'];
        }

        if (! $this->access_policy->can_manage_referral_meetings($referral, $actor_user_id)) {
            return ['ok' => false, 'error' => 'forbidden'];
        }

        return [
            'ok'          => true,
            'referral_id' => $referral_id,
            'meeting'     => $meeting,
        ];
    }

    /**
     * @return array{ok: true, referral_id: int, meeting: array<string, mixed>, attendee: array<string, mixed>}|array{ok: false, error: string}
     */
    private function resolve_internal_attendee_context(int $attendee_id, int $actor_user_id, ?int $expected_meeting_id = null): array
    {
        $attendee = $this->attendee_repository->find($attendee_id);
        if (null === $attendee) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        if (MeetingAttendee::KIND_INTERNAL !== (string) ($attendee['attendee_kind'] ?? '')) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $meeting_id = absint($attendee['meeting_id'] ?? 0);
        if (null !== $expected_meeting_id && $meeting_id !== absint($expected_meeting_id)) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $ctx = $this->resolve_meeting_context($meeting_id, $actor_user_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>} $ctx */
        return [
            'ok'          => true,
            'referral_id' => $ctx['referral_id'],
            'meeting'     => $ctx['meeting'],
            'attendee'    => $attendee,
        ];
    }

    /**
     * Add an external meeting participant. Kind forced to external; user_id forced null.
     * Same-name participants are allowed (no uniqueness rule).
     *
     * @param array<string, mixed> $input
     * @return array{ok: true, attendee_id: int}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function add_external_attendee(int $meeting_id, array $input, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_meeting_context($meeting_id, $actor_user_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>} $ctx */
        $status = (string) ($ctx['meeting']['status'] ?? '');
        if (! $this->lifecycle_policy->allows_external_attendee_add($status)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }

        $parsed = $this->parse_external_fields($input);
        if (isset($parsed['ok']) && false === $parsed['ok']) {
            /** @var array{ok: false, error: string, field_errors: array<string, string>} $parsed */
            return $parsed;
        }

        /** @var array{ok: true, fields: array<string, mixed>} $parsed */
        $fields = $parsed['fields'];
        $now    = current_time('mysql');
        $id     = $this->attendee_repository->create([
            'meeting_id'           => $meeting_id,
            'attendee_kind'        => MeetingAttendee::KIND_EXTERNAL,
            'user_id'              => null,
            'display_name'         => $fields['display_name'],
            'professional_role'    => $fields['professional_role'],
            'organisation'         => $fields['organisation'],
            'email'                => $fields['email'],
            'telephone'            => $fields['telephone'],
            'participant_category' => $fields['participant_category'],
            'meeting_role'         => $fields['meeting_role'],
            'attendance_status'    => $fields['attendance_status'],
            'sort_order'           => 0,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        if (false === $id) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_attendee_added(
            $ctx['referral_id'],
            MeetingAttendee::KIND_EXTERNAL
        );

        return ['ok' => true, 'attendee_id' => $id];
    }

    /**
     * Update external participant identity/contact fields (draft/scheduled) or attendance only (completed).
     *
     * @param array<string, mixed> $input
     * @return array{ok: true, changed?: bool}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function update_external_attendee(int $attendee_id, array $input, ?int $actor_user_id = null, ?int $expected_meeting_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_external_attendee_context($attendee_id, $actor_user_id, $expected_meeting_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>, attendee: array<string, mixed>} $ctx */
        $status = (string) ($ctx['meeting']['status'] ?? '');

        if ($this->lifecycle_policy->allows_external_attendee_edit($status)) {
            return $this->update_external_full($ctx, $input);
        }

        if ($this->lifecycle_policy->allows_external_attendance_correction($status)) {
            return $this->update_external_attendance_only($ctx, $input);
        }

        return ['ok' => false, 'error' => 'invalid_transition'];
    }

    /**
     * Correct external attendance after meeting completion (final statuses only).
     *
     * @param array<string, mixed> $input
     * @return array{ok: true, changed?: bool}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function update_external_attendance(int $attendee_id, array $input, ?int $actor_user_id = null, ?int $expected_meeting_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_external_attendee_context($attendee_id, $actor_user_id, $expected_meeting_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>, attendee: array<string, mixed>} $ctx */
        $status = (string) ($ctx['meeting']['status'] ?? '');
        if (! $this->lifecycle_policy->allows_external_attendance_correction($status)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }

        return $this->update_external_attendance_only($ctx, $input);
    }

    /**
     * Remove an external participant from a draft or scheduled meeting.
     *
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function remove_external_attendee(int $attendee_id, ?int $actor_user_id = null, ?int $expected_meeting_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_external_attendee_context($attendee_id, $actor_user_id, $expected_meeting_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>, attendee: array<string, mixed>} $ctx */
        $status = (string) ($ctx['meeting']['status'] ?? '');
        if (! $this->lifecycle_policy->allows_external_attendee_remove($status)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }

        if (! $this->attendee_repository->delete($attendee_id)) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_attendee_removed($ctx['referral_id'], MeetingAttendee::KIND_EXTERNAL);

        return ['ok' => true];
    }

    /**
     * @param array{ok: true, referral_id: int, meeting: array<string, mixed>, attendee: array<string, mixed>} $ctx
     * @param array<string, mixed> $input
     * @return array{ok: true, changed?: bool}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    private function update_external_full(array $ctx, array $input): array
    {
        $parsed = $this->parse_external_fields($input);
        if (isset($parsed['ok']) && false === $parsed['ok']) {
            /** @var array{ok: false, error: string, field_errors: array<string, string>} $parsed */
            return $parsed;
        }

        /** @var array{ok: true, fields: array<string, mixed>} $parsed */
        $fields   = $parsed['fields'];
        $attendee = $ctx['attendee'];

        $same = (string) ($attendee['display_name'] ?? '') === (string) $fields['display_name']
            && $this->nullable_equal($attendee['professional_role'] ?? null, $fields['professional_role'])
            && $this->nullable_equal($attendee['organisation'] ?? null, $fields['organisation'])
            && $this->nullable_equal($attendee['email'] ?? null, $fields['email'])
            && $this->nullable_equal($attendee['telephone'] ?? null, $fields['telephone'])
            && (string) ($attendee['participant_category'] ?? '') === (string) $fields['participant_category']
            && (string) ($attendee['meeting_role'] ?? '') === (string) $fields['meeting_role']
            && (string) ($attendee['attendance_status'] ?? '') === (string) $fields['attendance_status'];

        if ($same) {
            return ['ok' => true, 'changed' => false];
        }

        $ok = $this->attendee_repository->update(absint($attendee['id']), [
            'display_name'         => $fields['display_name'],
            'professional_role'    => $fields['professional_role'],
            'organisation'         => $fields['organisation'],
            'email'                => $fields['email'],
            'telephone'            => $fields['telephone'],
            'participant_category' => $fields['participant_category'],
            'meeting_role'         => $fields['meeting_role'],
            'attendance_status'    => $fields['attendance_status'],
            'updated_at'           => current_time('mysql'),
        ]);
        if (! $ok) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_attendee_updated($ctx['referral_id'], MeetingAttendee::KIND_EXTERNAL);

        return ['ok' => true, 'changed' => true];
    }

    /**
     * @param array{ok: true, referral_id: int, meeting: array<string, mixed>, attendee: array<string, mixed>} $ctx
     * @param array<string, mixed> $input
     * @return array{ok: true, changed?: bool}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    private function update_external_attendance_only(array $ctx, array $input): array
    {
        $attendee   = $ctx['attendee'];
        $attendance = sanitize_key((string) ($input['attendance_status'] ?? ''));

        if (! MeetingAttendee::is_final_attendance_status($attendance)) {
            return [
                'ok'           => false,
                'error'        => 'validation',
                'field_errors' => [
                    'attendance_status' => __('After completion, attendance may only be set to attended, absent, or declined.', 'jm-referral-system'),
                ],
            ];
        }

        $old_att = (string) ($attendee['attendance_status'] ?? '');
        if ($old_att === $attendance) {
            return ['ok' => true, 'changed' => false];
        }

        $ok = $this->attendee_repository->update(absint($attendee['id']), [
            'attendance_status' => $attendance,
            'updated_at'        => current_time('mysql'),
        ]);
        if (! $ok) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_attendee_updated($ctx['referral_id'], MeetingAttendee::KIND_EXTERNAL);

        return ['ok' => true, 'changed' => true];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, fields: array<string, mixed>}|array{ok: false, error: string, field_errors: array<string, string>}
     */
    private function parse_external_fields(array $input): array
    {
        $display_name = $this->nullable_text((string) ($input['display_name'] ?? ''), 255);
        $professional = $this->nullable_text((string) ($input['professional_role'] ?? ''), 150);
        $organisation = $this->nullable_text((string) ($input['organisation'] ?? ''), 255);
        $meeting_role = $this->nullable_text((string) ($input['meeting_role'] ?? ''), 150);
        $category     = sanitize_key((string) ($input['participant_category'] ?? ''));
        $attendance   = sanitize_key((string) ($input['attendance_status'] ?? MeetingAttendee::ATTENDANCE_INVITED));
        if ('' === $attendance) {
            $attendance = MeetingAttendee::ATTENDANCE_INVITED;
        }

        $email_raw = trim((string) ($input['email'] ?? ''));
        $phone_raw = trim((string) ($input['telephone'] ?? ''));

        $errors = [];

        if (null === $display_name || '' === trim($display_name)) {
            $errors['display_name'] = __('Please enter a participant name.', 'jm-referral-system');
        }

        if ('' === $category || ! array_key_exists($category, MeetingAttendee::category_labels())) {
            $errors['participant_category'] = __('Please select a valid participant category.', 'jm-referral-system');
        }

        if (null === $meeting_role || '' === trim($meeting_role)) {
            $errors['meeting_role'] = __('Please enter a meeting role.', 'jm-referral-system');
        }

        if (! MeetingAttendee::is_valid_attendance_status($attendance)) {
            $errors['attendance_status'] = __('Please select a valid attendance status.', 'jm-referral-system');
        }

        $email = null;
        if ('' !== $email_raw) {
            $email = sanitize_email($email_raw);
            if ('' === $email || ! is_email($email)) {
                $errors['email'] = __('Enter a valid email address.', 'jm-referral-system');
                $email           = null;
            } elseif (strlen($email) > 190) {
                $errors['email'] = __('Enter a valid email address.', 'jm-referral-system');
                $email           = null;
            }
        }

        $telephone = null;
        if ('' !== $phone_raw) {
            $phone_check = $this->sanitize_telephone($phone_raw);
            if (null === $phone_check) {
                $errors['telephone'] = __('Enter a valid telephone number.', 'jm-referral-system');
            } else {
                $telephone = $phone_check;
            }
        }

        if ([] !== $errors) {
            return ['ok' => false, 'error' => 'validation', 'field_errors' => $errors];
        }

        return [
            'ok'     => true,
            'fields' => [
                'display_name'         => $display_name,
                'professional_role'    => $professional,
                'organisation'         => $organisation,
                'email'                => $email,
                'telephone'            => $telephone,
                'participant_category' => $category,
                'meeting_role'         => $meeting_role,
                'attendance_status'    => $attendance,
            ],
        ];
    }

    /**
     * @return array{ok: true, referral_id: int, meeting: array<string, mixed>, attendee: array<string, mixed>}|array{ok: false, error: string}
     */
    private function resolve_external_attendee_context(int $attendee_id, int $actor_user_id, ?int $expected_meeting_id = null): array
    {
        $attendee = $this->attendee_repository->find($attendee_id);
        if (null === $attendee) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        if (MeetingAttendee::KIND_EXTERNAL !== (string) ($attendee['attendee_kind'] ?? '')) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $meeting_id = absint($attendee['meeting_id'] ?? 0);
        if (null !== $expected_meeting_id && $meeting_id !== absint($expected_meeting_id)) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $ctx = $this->resolve_meeting_context($meeting_id, $actor_user_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>} $ctx */
        return [
            'ok'          => true,
            'referral_id' => $ctx['referral_id'],
            'meeting'     => $ctx['meeting'],
            'attendee'    => $attendee,
        ];
    }

    private function sanitize_telephone(string $value): ?string
    {
        $value = sanitize_text_field($value);
        $value = trim($value);
        if ('' === $value) {
            return null;
        }
        if (strlen($value) > 50) {
            return null;
        }
        // Digits, spaces, +, parentheses, hyphens only.
        if (1 !== preg_match('/^[0-9+\-\s()]+$/u', $value)) {
            return null;
        }
        if (! preg_match('/[0-9]/', $value)) {
            return null;
        }

        return $value;
    }

    private function nullable_equal(mixed $a, mixed $b): bool
    {
        $a = null === $a || '' === (string) $a ? null : (string) $a;
        $b = null === $b || '' === (string) $b ? null : (string) $b;

        return $a === $b;
    }

    private function nullable_text(string $value, int $max): ?string
    {
        $value = sanitize_text_field($value);
        if ('' === $value) {
            return null;
        }
        if (strlen($value) > $max) {
            $value = substr($value, 0, $max);
        }

        return $value;
    }
}
