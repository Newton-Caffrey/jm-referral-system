<?php

namespace JMReferral\Meeting;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;

/**
 * Referral meeting mutations with server-side lifecycle enforcement (Phase 4B.2.2).
 * Does not advance pipeline stages. Does not send emails.
 */
class ReferralMeetingService
{
    private MeetingLifecyclePolicy $lifecycle_policy;

    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralMeetingRepository $meeting_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy,
        ?MeetingLifecyclePolicy $lifecycle_policy = null
    ) {
        $this->lifecycle_policy = $lifecycle_policy ?? new MeetingLifecyclePolicy();
    }

    /**
     * Create a draft meeting. Caller-supplied status is ignored.
     *
     * @param array<string, mixed> $input
     * @return array{ok: true, meeting_id: int}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function create_draft(int $referral_id, array $input, ?int $actor_user_id = null): array
    {
        return $this->create_with_status($referral_id, $input, ReferralMeeting::STATUS_DRAFT, $actor_user_id);
    }

    /**
     * Create a scheduled meeting atomically. Caller-supplied status is ignored.
     *
     * @param array<string, mixed> $input
     * @return array{ok: true, meeting_id: int}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function create_scheduled(int $referral_id, array $input, ?int $actor_user_id = null): array
    {
        return $this->create_with_status($referral_id, $input, ReferralMeeting::STATUS_SCHEDULED, $actor_user_id);
    }

    /**
     * Update non-lifecycle content fields on draft or scheduled meetings.
     * Does not accept status, completed_at, cancelled_at, referral_id, or schedule times on scheduled meetings.
     *
     * @param array<string, mixed> $input
     * @return array{ok: true, changed?: bool}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function update_details(int $meeting_id, array $input, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_mutable_meeting($meeting_id, $actor_user_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>} $ctx */
        $meeting = $ctx['meeting'];
        $status  = (string) ($meeting['status'] ?? '');

        if (! $this->lifecycle_policy->allows(MeetingLifecyclePolicy::ACTION_UPDATE_DETAILS, $status)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }

        $fields = $this->sanitize_content_fields($input, ReferralMeeting::STATUS_DRAFT === $status);
        // Scheduled meetings must change datetime via reschedule().
        if (ReferralMeeting::STATUS_SCHEDULED === $status) {
            unset($fields['scheduled_at'], $fields['scheduled_end_at']);
            $fields['scheduled_at']     = $meeting['scheduled_at'] ?? null;
            $fields['scheduled_end_at'] = $meeting['scheduled_end_at'] ?? null;
        }

        $errors = $this->validate_content_fields($fields, true, ReferralMeeting::STATUS_DRAFT === $status);
        if ([] !== $errors) {
            return ['ok' => false, 'error' => 'validation', 'field_errors' => $errors];
        }

        $fields = $this->apply_location_side_effects($fields);

        if (! $this->details_changed($meeting, $fields)) {
            return ['ok' => true, 'changed' => false];
        }

        $payload = array_merge($fields, [
            'updated_by' => $actor_user_id,
            'updated_at' => current_time('mysql'),
        ]);

        if (! $this->meeting_repository->update($meeting_id, $payload)) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_updated($ctx['referral_id']);

        return ['ok' => true, 'changed' => true];
    }

    /**
     * Draft → scheduled.
     *
     * @param array<string, mixed> $input
     * @return array{ok: true, changed?: bool}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function schedule(int $meeting_id, array $input, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_mutable_meeting($meeting_id, $actor_user_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>} $ctx */
        $meeting = $ctx['meeting'];
        $status  = (string) ($meeting['status'] ?? '');

        if (! $this->lifecycle_policy->allows(MeetingLifecyclePolicy::ACTION_SCHEDULE, $status)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }

        $fields = $this->sanitize_schedule_fields($input, $meeting);
        $errors = $this->validate_schedule_fields($fields, true);
        if ([] !== $errors) {
            return ['ok' => false, 'error' => 'validation', 'field_errors' => $errors];
        }

        $fields = $this->apply_location_side_effects($fields);

        $ok = $this->meeting_repository->update($meeting_id, array_merge($fields, [
            'status'       => ReferralMeeting::STATUS_SCHEDULED,
            'completed_at' => null,
            'cancelled_at' => null,
            'updated_by'   => $actor_user_id,
            'updated_at'   => current_time('mysql'),
        ]));

        if (! $ok) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_scheduled($ctx['referral_id']);

        return ['ok' => true, 'changed' => true];
    }

    /**
     * Scheduled → scheduled (datetime/location).
     *
     * @param array<string, mixed> $input
     * @return array{ok: true, changed?: bool}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function reschedule(int $meeting_id, array $input, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_mutable_meeting($meeting_id, $actor_user_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>} $ctx */
        $meeting = $ctx['meeting'];
        $status  = (string) ($meeting['status'] ?? '');

        if (! $this->lifecycle_policy->allows(MeetingLifecyclePolicy::ACTION_RESCHEDULE, $status)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }

        $fields = $this->sanitize_schedule_fields($input, $meeting);
        $errors = $this->validate_schedule_fields($fields, true);
        if ([] !== $errors) {
            return ['ok' => false, 'error' => 'validation', 'field_errors' => $errors];
        }

        $fields = $this->apply_location_side_effects($fields);

        if (! $this->details_changed($meeting, $fields)) {
            return ['ok' => true, 'changed' => false];
        }

        $ok = $this->meeting_repository->update($meeting_id, array_merge($fields, [
            'status'       => ReferralMeeting::STATUS_SCHEDULED,
            'completed_at' => null,
            'cancelled_at' => null,
            'updated_by'   => $actor_user_id,
            'updated_at'   => current_time('mysql'),
        ]));

        if (! $ok) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_rescheduled($ctx['referral_id']);

        return ['ok' => true, 'changed' => true];
    }

    /**
     * Scheduled → completed. Idempotent when already completed (no duplicate activity).
     *
     * @param array<string, mixed> $input Optional outcome.
     * @return array{ok: true, already_done?: bool}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function complete(int $meeting_id, array $input = [], ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_mutable_meeting($meeting_id, $actor_user_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>} $ctx */
        $meeting = $ctx['meeting'];
        $status  = (string) ($meeting['status'] ?? '');

        if (ReferralMeeting::STATUS_COMPLETED === $status) {
            return ['ok' => true, 'already_done' => true];
        }

        if (! $this->lifecycle_policy->allows(MeetingLifecyclePolicy::ACTION_COMPLETE, $status)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }

        $outcome = $this->nullable_text((string) ($input['outcome'] ?? ''), 255);

        $payload = [
            'status'       => ReferralMeeting::STATUS_COMPLETED,
            'completed_at' => current_time('mysql'),
            'cancelled_at' => null,
            'updated_by'   => $actor_user_id,
            'updated_at'   => current_time('mysql'),
        ];
        if (null !== $outcome) {
            $payload['outcome'] = $outcome;
        } elseif (array_key_exists('outcome', $input) && '' === trim((string) $input['outcome'])) {
            $payload['outcome'] = null;
        }

        if (! $this->meeting_repository->update($meeting_id, $payload)) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_completed($ctx['referral_id']);

        return ['ok' => true, 'already_done' => false];
    }

    /**
     * Draft/scheduled → cancelled. Idempotent when already cancelled (no duplicate activity).
     *
     * @return array{ok: true, already_done?: bool}|array{ok: false, error: string}
     */
    public function cancel(int $meeting_id, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $ctx           = $this->resolve_mutable_meeting($meeting_id, $actor_user_id);
        if (isset($ctx['ok']) && false === $ctx['ok']) {
            /** @var array{ok: false, error: string} $ctx */
            return $ctx;
        }

        /** @var array{ok: true, referral_id: int, meeting: array<string, mixed>} $ctx */
        $meeting = $ctx['meeting'];
        $status  = (string) ($meeting['status'] ?? '');

        if (ReferralMeeting::STATUS_CANCELLED === $status) {
            return ['ok' => true, 'already_done' => true];
        }

        if (! $this->lifecycle_policy->allows(MeetingLifecyclePolicy::ACTION_CANCEL, $status)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }

        if (! $this->meeting_repository->mark_cancelled($meeting_id, $actor_user_id)) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_cancelled($ctx['referral_id']);

        return ['ok' => true, 'already_done' => false];
    }

    /**
     * Whether a scheduled_at value is before site "now".
     */
    public function is_past_scheduled(?string $scheduled_at): bool
    {
        if (null === $scheduled_at || '' === $scheduled_at) {
            return false;
        }

        return $scheduled_at < current_time('mysql');
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, meeting_id: int}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    private function create_with_status(int $referral_id, array $input, string $forced_status, ?int $actor_user_id): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $gate          = $this->gate_referral($referral_id, $actor_user_id);
        if (null !== $gate) {
            return $gate;
        }

        $is_draft = ReferralMeeting::STATUS_DRAFT === $forced_status;
        $fields   = $this->sanitize_content_fields($input, true);
        $errors   = $this->validate_content_fields($fields, true, $is_draft);
        if ([] !== $errors) {
            return ['ok' => false, 'error' => 'validation', 'field_errors' => $errors];
        }

        $fields = $this->apply_location_side_effects($fields);

        $now = current_time('mysql');
        $id  = $this->meeting_repository->create([
            'referral_id'        => $referral_id,
            'meeting_type'       => $fields['meeting_type'],
            'status'             => $forced_status,
            'scheduled_at'       => $fields['scheduled_at'],
            'scheduled_end_at'   => $fields['scheduled_end_at'],
            'location_type'      => $fields['location_type'],
            'location_name'      => $fields['location_name'],
            'location_address'   => $fields['location_address'],
            'online_meeting_url' => $fields['online_meeting_url'],
            'purpose'            => $fields['purpose'],
            'outcome'            => null,
            'created_by'         => $actor_user_id,
            'updated_by'         => $actor_user_id,
            'created_at'         => $now,
            'updated_at'         => $now,
            'completed_at'       => null,
            'cancelled_at'       => null,
        ]);

        if (false === $id) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_created(
            $referral_id,
            ReferralMeeting::type_label((string) $fields['meeting_type'])
        );

        return ['ok' => true, 'meeting_id' => $id];
    }

    /**
     * @return array{ok: true, referral_id: int, meeting: array<string, mixed>}|array{ok: false, error: string}
     */
    private function resolve_mutable_meeting(int $meeting_id, int $actor_user_id): array
    {
        $meeting = $this->meeting_repository->find($meeting_id);
        if (null === $meeting) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $referral_id = absint($meeting['referral_id'] ?? 0);
        $gate        = $this->gate_referral($referral_id, $actor_user_id);
        if (null !== $gate) {
            return $gate;
        }

        return [
            'ok'          => true,
            'referral_id' => $referral_id,
            'meeting'     => $meeting,
        ];
    }

    /**
     * @return array{ok: false, error: string}|null
     */
    private function gate_referral(int $referral_id, int $actor_user_id): ?array
    {
        $referral = $this->referral_repository->find($referral_id);
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

        return null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function sanitize_content_fields(array $input, bool $include_schedule): array
    {
        $type = sanitize_key((string) ($input['meeting_type'] ?? ''));
        $loc  = sanitize_key((string) ($input['location_type'] ?? ''));

        $url = trim((string) ($input['online_meeting_url'] ?? ''));
        $url = '' !== $url ? esc_url_raw($url) : '';

        $out = [
            'meeting_type'       => $type,
            'location_type'      => '' !== $loc ? $loc : null,
            'location_name'      => $this->nullable_text((string) ($input['location_name'] ?? ''), 255),
            'location_address'   => $this->nullable_text((string) ($input['location_address'] ?? ''), 500),
            'online_meeting_url' => '' !== $url ? $url : null,
            'purpose'            => $this->nullable_text((string) ($input['purpose'] ?? ''), 255),
        ];

        if ($include_schedule) {
            $out['scheduled_at']     = $this->combine_datetime_input($input, 'scheduled');
            $out['scheduled_end_at'] = $this->combine_datetime_input($input, 'scheduled_end');
            if (null === $out['scheduled_at'] && isset($input['scheduled_at'])) {
                $out['scheduled_at'] = $this->normalize_datetime((string) $input['scheduled_at']);
            }
            if (null === $out['scheduled_end_at'] && isset($input['scheduled_end_at'])) {
                $out['scheduled_end_at'] = $this->normalize_datetime((string) $input['scheduled_end_at']);
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed>      $input
     * @param array<string, mixed>      $meeting
     * @return array<string, mixed>
     */
    private function sanitize_schedule_fields(array $input, array $meeting): array
    {
        $fields = $this->sanitize_content_fields($input, true);
        // Preserve meeting_type / purpose from meeting when not posted.
        if ('' === (string) ($fields['meeting_type'] ?? '')) {
            $fields['meeting_type'] = (string) ($meeting['meeting_type'] ?? '');
        }
        if (null === ($fields['purpose'] ?? null) || '' === (string) $fields['purpose']) {
            $fields['purpose'] = $this->nullable_text((string) ($meeting['purpose'] ?? ''), 255);
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, string>
     */
    private function validate_content_fields(array $fields, bool $require_type, bool $is_draft): array
    {
        $errors = [];

        if ($require_type || '' !== (string) ($fields['meeting_type'] ?? '')) {
            if (! ReferralMeeting::is_valid_type((string) ($fields['meeting_type'] ?? ''))) {
                $errors['meeting_type'] = __('Please select a valid meeting type.', 'jm-referral-system');
            }
        }

        $purpose = $fields['purpose'] ?? null;
        if (null === $purpose || '' === trim((string) $purpose)) {
            $errors['purpose'] = __('Please enter a concise meeting purpose.', 'jm-referral-system');
        }

        $loc = $fields['location_type'] ?? null;
        if (null !== $loc && '' !== (string) $loc && ! ReferralMeeting::is_valid_location_type((string) $loc)) {
            $errors['location_type'] = __('Please select a valid location type.', 'jm-referral-system');
        }

        if (! $is_draft) {
            $errors = array_merge($errors, $this->validate_schedule_fields($fields, true));
        } else {
            $errors = array_merge($errors, $this->validate_schedule_fields($fields, false));
            $url    = (string) ($fields['online_meeting_url'] ?? '');
            if ('' !== $url && ! $this->is_valid_http_url($url)) {
                $errors['online_meeting_url'] = __('Please enter a valid online meeting URL.', 'jm-referral-system');
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, string>
     */
    private function validate_schedule_fields(array $fields, bool $require_schedule): array
    {
        $errors = [];
        $start  = $fields['scheduled_at'] ?? null;
        $end    = $fields['scheduled_end_at'] ?? null;

        if ($require_schedule) {
            if (null === $start || '' === (string) $start) {
                $errors['scheduled_at'] = __('Please enter a valid scheduled date and time.', 'jm-referral-system');
            }
            $loc = (string) ($fields['location_type'] ?? '');
            if ('' === $loc || ! ReferralMeeting::is_valid_location_type($loc)) {
                $errors['location_type'] = __('Please select a valid location type.', 'jm-referral-system');
            } else {
                $errors = array_merge($errors, $this->validate_location_for_scheduled($fields));
            }
        } else {
            if (null === $start && array_key_exists('scheduled_date', $fields)) {
                // no-op
            }
            if (isset($fields['_scheduled_date_invalid'])) {
                $errors['scheduled_at'] = __('Please enter a valid scheduled date and time.', 'jm-referral-system');
            }
        }

        if (null !== $end && null !== $start && (string) $end < (string) $start) {
            $errors['scheduled_end_at'] = __('End time must be after the start time.', 'jm-referral-system');
        }

        if (null === $end && $this->had_invalid_end_input($fields)) {
            $errors['scheduled_end_at'] = __('Please enter a valid end date and time.', 'jm-referral-system');
        }

        $url = (string) ($fields['online_meeting_url'] ?? '');
        if ('' !== $url && ! $this->is_valid_http_url($url)) {
            $errors['online_meeting_url'] = __('Please enter a valid online meeting URL.', 'jm-referral-system');
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, string>
     */
    private function validate_location_for_scheduled(array $fields): array
    {
        $errors = [];
        $loc    = (string) ($fields['location_type'] ?? '');

        if (ReferralMeeting::LOCATION_ONLINE === $loc) {
            $url = (string) ($fields['online_meeting_url'] ?? '');
            if ('' === $url) {
                $errors['online_meeting_url'] = __('An online meeting URL is required for online meetings.', 'jm-referral-system');
            } elseif (! $this->is_valid_http_url($url)) {
                $errors['online_meeting_url'] = __('Please enter a valid online meeting URL.', 'jm-referral-system');
            }
        }

        if (ReferralMeeting::LOCATION_IN_PERSON === $loc) {
            $name = trim((string) ($fields['location_name'] ?? ''));
            if ('' === $name) {
                $errors['location_name'] = __('Please enter a location name for in-person meetings.', 'jm-referral-system');
            }
        }

        if (ReferralMeeting::LOCATION_OTHER === $loc) {
            $name = trim((string) ($fields['location_name'] ?? ''));
            $addr = trim((string) ($fields['location_address'] ?? ''));
            if ('' === $name && '' === $addr) {
                $errors['location_name'] = __('Please describe the meeting location.', 'jm-referral-system');
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function apply_location_side_effects(array $fields): array
    {
        $loc = (string) ($fields['location_type'] ?? '');

        if (ReferralMeeting::LOCATION_ONLINE === $loc) {
            $fields['location_address'] = null;
        } elseif (ReferralMeeting::LOCATION_IN_PERSON === $loc) {
            $fields['online_meeting_url'] = null;
        } elseif (ReferralMeeting::LOCATION_TELEPHONE === $loc) {
            $fields['location_address']   = null;
            $fields['online_meeting_url'] = null;
        } elseif (ReferralMeeting::LOCATION_OTHER === $loc) {
            $fields['online_meeting_url'] = null;
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $meeting
     * @param array<string, mixed> $fields
     */
    private function details_changed(array $meeting, array $fields): bool
    {
        $keys = [
            'meeting_type',
            'location_type',
            'location_name',
            'location_address',
            'online_meeting_url',
            'purpose',
            'scheduled_at',
            'scheduled_end_at',
        ];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $fields)) {
                continue;
            }
            $old = $meeting[$key] ?? null;
            $new = $fields[$key] ?? null;
            $old_s = null === $old || '' === (string) $old ? '' : (string) $old;
            $new_s = null === $new || '' === (string) $new ? '' : (string) $new;
            if ($old_s !== $new_s) {
                return true;
            }
        }

        return false;
    }

    /**
     * Combine date + time POST fields into MySQL datetime (site timezone).
     *
     * @param array<string, mixed> $input
     */
    private function combine_datetime_input(array $input, string $prefix): ?string
    {
        $date_key = $prefix . '_date';
        $time_key = $prefix . '_time';

        if (! isset($input[$date_key]) && ! isset($input[$time_key])) {
            return null;
        }

        $date = trim((string) ($input[$date_key] ?? ''));
        $time = trim((string) ($input[$time_key] ?? ''));

        if ('' === $date && '' === $time) {
            return null;
        }

        if ('' === $date || '' === $time) {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        if (! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            return null;
        }
        if (1 === substr_count($time, ':')) {
            $time .= ':00';
        }

        return $this->normalize_datetime($date . ' ' . $time);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function had_invalid_end_input(array $fields): bool
    {
        return ! empty($fields['_scheduled_end_invalid']);
    }

    private function is_valid_http_url(string $url): bool
    {
        if ('' === $url) {
            return false;
        }
        if (function_exists('wp_http_validate_url')) {
            return false !== wp_http_validate_url($url);
        }

        return (bool) filter_var($url, FILTER_VALIDATE_URL);
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

    private function normalize_datetime(string $value): ?string
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        try {
            $dt = new \DateTimeImmutable($value, wp_timezone());
        } catch (\Exception $e) {
            return null;
        }

        return $dt->format('Y-m-d H:i:s');
    }
}
