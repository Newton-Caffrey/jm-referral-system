<?php

namespace JMReferral\Meeting;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;

/**
 * Meeting attendee CRUD (Phase 4B.1). No controllers/routes yet.
 * Does not log external email or telephone in activity descriptions.
 */
class MeetingAttendeeService
{
    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralMeetingRepository $meeting_repository,
        private MeetingAttendeeRepository $attendee_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, attendee_id: int}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function add(int $meeting_id, array $input, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_meeting_context($meeting_id, $actor_user_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>} $ctx */
        $fields = $this->sanitize_fields($input);
        $errors = $this->validate_fields($fields, $meeting_id, null);
        if ([] !== $errors) {
            return ['ok' => false, 'error' => 'validation', 'field_errors' => $errors];
        }

        $payload = $this->persist_payload($fields);
        $now     = current_time('mysql');
        $id      = $this->attendee_repository->create(array_merge($payload, [
            'meeting_id' => $meeting_id,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        if (false === $id) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_attendee_added(
            $ctx['referral_id'],
            MeetingAttendee::kind_label((string) $fields['attendee_kind'])
        );

        return ['ok' => true, 'attendee_id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function update(int $attendee_id, array $input, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $attendee      = $this->attendee_repository->find($attendee_id);
        if (null === $attendee) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $meeting_id = absint($attendee['meeting_id'] ?? 0);
        $ctx        = $this->resolve_meeting_context($meeting_id, $actor_user_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>} $ctx */
        $fields = $this->sanitize_fields($input);
        $errors = $this->validate_fields($fields, $meeting_id, $attendee_id);
        if ([] !== $errors) {
            return ['ok' => false, 'error' => 'validation', 'field_errors' => $errors];
        }

        $payload = $this->persist_payload($fields);
        $payload['updated_at'] = current_time('mysql');

        if (! $this->attendee_repository->update($attendee_id, $payload)) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_attendee_updated($ctx['referral_id']);

        return ['ok' => true];
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function remove(int $attendee_id, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $attendee      = $this->attendee_repository->find($attendee_id);
        if (null === $attendee) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $meeting_id = absint($attendee['meeting_id'] ?? 0);
        $ctx        = $this->resolve_meeting_context($meeting_id, $actor_user_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>} $ctx */
        if (! $this->attendee_repository->delete($attendee_id)) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_attendee_removed($ctx['referral_id']);

        return ['ok' => true];
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
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function sanitize_fields(array $input): array
    {
        $kind = sanitize_key((string) ($input['attendee_kind'] ?? ''));
        $status = sanitize_key((string) ($input['attendance_status'] ?? MeetingAttendee::ATTENDANCE_INVITED));
        $category = sanitize_key((string) ($input['participant_category'] ?? ''));

        $email = sanitize_email((string) ($input['email'] ?? ''));
        $phone = sanitize_text_field((string) ($input['telephone'] ?? ''));

        return [
            'attendee_kind'        => $kind,
            'user_id'              => isset($input['user_id']) ? absint($input['user_id']) : 0,
            'display_name'         => $this->nullable_text((string) ($input['display_name'] ?? ''), 255),
            'professional_role'    => $this->nullable_text((string) ($input['professional_role'] ?? ''), 150),
            'organisation'         => $this->nullable_text((string) ($input['organisation'] ?? ''), 255),
            'email'                => is_email($email) ? $email : null,
            'telephone'            => '' !== $phone ? $phone : null,
            'participant_category' => '' !== $category ? $category : null,
            'meeting_role'         => $this->nullable_text((string) ($input['meeting_role'] ?? ''), 150),
            'attendance_status'    => $status,
            'sort_order'           => isset($input['sort_order']) ? (int) $input['sort_order'] : 0,
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, string>
     */
    private function validate_fields(array $fields, int $meeting_id, ?int $exclude_attendee_id): array
    {
        $errors = [];
        $kind   = (string) ($fields['attendee_kind'] ?? '');

        if (! MeetingAttendee::is_valid_kind($kind)) {
            $errors['attendee_kind'] = __('Please select a valid attendee kind.', 'jm-referral-system');
        }

        if (! MeetingAttendee::is_valid_attendance_status((string) ($fields['attendance_status'] ?? ''))) {
            $errors['attendance_status'] = __('Please select a valid attendance status.', 'jm-referral-system');
        }

        $category = (string) ($fields['participant_category'] ?? '');
        if ('' !== $category && ! MeetingAttendee::is_valid_category($category)) {
            $errors['participant_category'] = __('Please select a valid participant category.', 'jm-referral-system');
        }

        if (MeetingAttendee::KIND_INTERNAL === $kind) {
            $user_id = absint($fields['user_id'] ?? 0);
            if ($user_id <= 0 || ! $this->user_provider->is_assignable($user_id)) {
                $errors['user_id'] = __('Please select a valid internal staff user.', 'jm-referral-system');
            } elseif ($this->attendee_repository->has_internal_user($meeting_id, $user_id, $exclude_attendee_id)) {
                $errors['user_id'] = __('This staff member is already an attendee of this meeting.', 'jm-referral-system');
            }
            // Internal identity is user_id; display_name is optional snapshot only.
        } elseif (MeetingAttendee::KIND_EXTERNAL === $kind) {
            $name = trim((string) ($fields['display_name'] ?? ''));
            if ('' === $name) {
                $errors['display_name'] = __('External participant name is required.', 'jm-referral-system');
            }
            // Reject invalid kind combinations: external must not carry user_id identity.
            if (absint($fields['user_id'] ?? 0) > 0) {
                $errors['user_id'] = __('External participants must not be linked to a staff user ID.', 'jm-referral-system');
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function persist_payload(array $fields): array
    {
        $kind = (string) ($fields['attendee_kind'] ?? '');
        if (MeetingAttendee::KIND_INTERNAL === $kind) {
            $fields['user_id'] = absint($fields['user_id'] ?? 0);
        } else {
            $fields['user_id'] = null;
        }

        return $fields;
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
