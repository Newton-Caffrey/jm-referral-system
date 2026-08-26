<?php

namespace JMReferral\Meeting;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;

/**
 * Internal meeting CRUD (Phase 4B.1). No controllers/routes yet.
 * Does not advance or reverse pipeline stages.
 */
class ReferralMeetingService
{
    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralMeetingRepository $meeting_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, meeting_id: int}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function create(int $referral_id, array $input, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $gate          = $this->gate_referral($referral_id, $actor_user_id);
        if (null !== $gate) {
            return $gate;
        }

        $fields = $this->sanitize_fields($input);
        $errors = $this->validate_fields($fields, true);
        if ([] !== $errors) {
            return ['ok' => false, 'error' => 'validation', 'field_errors' => $errors];
        }

        $status = (string) $fields['status'];
        if (ReferralMeeting::STATUS_SCHEDULED === $status && empty($fields['scheduled_at'])) {
            return [
                'ok'           => false,
                'error'        => 'validation',
                'field_errors' => [
                    'scheduled_at' => __('A scheduled date and time are required for a scheduled meeting.', 'jm-referral-system'),
                ],
            ];
        }

        $now = current_time('mysql');
        $id  = $this->meeting_repository->create([
            'referral_id'        => $referral_id,
            'meeting_type'       => $fields['meeting_type'],
            'status'             => $status,
            'scheduled_at'       => $fields['scheduled_at'],
            'scheduled_end_at'   => $fields['scheduled_end_at'],
            'location_type'      => $fields['location_type'],
            'location_name'      => $fields['location_name'],
            'location_address'   => $fields['location_address'],
            'online_meeting_url' => $fields['online_meeting_url'],
            'purpose'            => $fields['purpose'],
            'outcome'            => $fields['outcome'],
            'created_by'         => $actor_user_id,
            'updated_by'         => $actor_user_id,
            'created_at'         => $now,
            'updated_at'         => $now,
            'completed_at'       => ReferralMeeting::STATUS_COMPLETED === $status ? $now : null,
            'cancelled_at'       => ReferralMeeting::STATUS_CANCELLED === $status ? $now : null,
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
     * @param array<string, mixed> $input
     * @return array{ok: true}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function update(int $meeting_id, array $input, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $meeting       = $this->meeting_repository->find($meeting_id);
        if (null === $meeting) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $referral_id = absint($meeting['referral_id'] ?? 0);
        $gate        = $this->gate_referral($referral_id, $actor_user_id);
        if (null !== $gate) {
            return $gate;
        }

        $fields = $this->sanitize_fields($input);
        $errors = $this->validate_fields($fields, false);
        if ([] !== $errors) {
            return ['ok' => false, 'error' => 'validation', 'field_errors' => $errors];
        }

        $payload = array_merge($fields, [
            'updated_by' => $actor_user_id,
            'updated_at' => current_time('mysql'),
        ]);

        $status = (string) ($fields['status'] ?? '');
        if (ReferralMeeting::STATUS_COMPLETED === $status) {
            $payload['completed_at'] = (string) ($meeting['completed_at'] ?? '') !== ''
                ? $meeting['completed_at']
                : current_time('mysql');
            $payload['cancelled_at'] = null;
        } elseif (ReferralMeeting::STATUS_CANCELLED === $status) {
            $payload['cancelled_at'] = (string) ($meeting['cancelled_at'] ?? '') !== ''
                ? $meeting['cancelled_at']
                : current_time('mysql');
        } elseif (ReferralMeeting::STATUS_SCHEDULED === $status || ReferralMeeting::STATUS_DRAFT === $status) {
            $payload['completed_at'] = null;
            $payload['cancelled_at'] = null;
        }

        if (! $this->meeting_repository->update($meeting_id, $payload)) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_updated($referral_id);

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true}|array{ok: false, error: string, field_errors?: array<string, string>}
     */
    public function reschedule(int $meeting_id, array $input, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $meeting       = $this->meeting_repository->find($meeting_id);
        if (null === $meeting) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $referral_id = absint($meeting['referral_id'] ?? 0);
        $gate        = $this->gate_referral($referral_id, $actor_user_id);
        if (null !== $gate) {
            return $gate;
        }

        if (ReferralMeeting::STATUS_CANCELLED === (string) ($meeting['status'] ?? '')) {
            return ['ok' => false, 'error' => 'cancelled'];
        }

        $scheduled_at     = $this->normalize_datetime((string) ($input['scheduled_at'] ?? ''));
        $scheduled_end_at = $this->normalize_datetime((string) ($input['scheduled_end_at'] ?? ''));

        if (null === $scheduled_at) {
            return [
                'ok'           => false,
                'error'        => 'validation',
                'field_errors' => [
                    'scheduled_at' => __('Please enter a valid scheduled date and time.', 'jm-referral-system'),
                ],
            ];
        }

        if ('' !== (string) ($input['scheduled_end_at'] ?? '') && null === $scheduled_end_at) {
            return [
                'ok'           => false,
                'error'        => 'validation',
                'field_errors' => [
                    'scheduled_end_at' => __('Please enter a valid end date and time.', 'jm-referral-system'),
                ],
            ];
        }

        if (null !== $scheduled_end_at && $scheduled_end_at < $scheduled_at) {
            return [
                'ok'           => false,
                'error'        => 'validation',
                'field_errors' => [
                    'scheduled_end_at' => __('End time must be after the start time.', 'jm-referral-system'),
                ],
            ];
        }

        $ok = $this->meeting_repository->update($meeting_id, [
            'scheduled_at'     => $scheduled_at,
            'scheduled_end_at' => $scheduled_end_at,
            'status'           => ReferralMeeting::STATUS_SCHEDULED,
            'cancelled_at'     => null,
            'completed_at'     => null,
            'updated_by'       => $actor_user_id,
            'updated_at'       => current_time('mysql'),
        ]);

        if (! $ok) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_rescheduled($referral_id);

        return ['ok' => true];
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function complete(int $meeting_id, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $meeting       = $this->meeting_repository->find($meeting_id);
        if (null === $meeting) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $referral_id = absint($meeting['referral_id'] ?? 0);
        $gate        = $this->gate_referral($referral_id, $actor_user_id);
        if (null !== $gate) {
            return $gate;
        }

        if (ReferralMeeting::STATUS_CANCELLED === (string) ($meeting['status'] ?? '')) {
            return ['ok' => false, 'error' => 'cancelled'];
        }

        if (! $this->meeting_repository->mark_completed($meeting_id, $actor_user_id)) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_completed($referral_id);

        return ['ok' => true];
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function cancel(int $meeting_id, ?int $actor_user_id = null): array
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $meeting       = $this->meeting_repository->find($meeting_id);
        if (null === $meeting) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $referral_id = absint($meeting['referral_id'] ?? 0);
        $gate        = $this->gate_referral($referral_id, $actor_user_id);
        if (null !== $gate) {
            return $gate;
        }

        if (ReferralMeeting::STATUS_CANCELLED === (string) ($meeting['status'] ?? '')) {
            return ['ok' => true];
        }

        if (! $this->meeting_repository->mark_cancelled($meeting_id, $actor_user_id)) {
            return ['ok' => false, 'error' => 'persist_failed'];
        }

        $this->activity_service->log_meeting_cancelled($referral_id);

        return ['ok' => true];
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

        if (! $this->access_policy->can_manage_referral_meetings($referral, $actor_user_id)) {
            return ['ok' => false, 'error' => 'forbidden'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function sanitize_fields(array $input): array
    {
        $type   = sanitize_key((string) ($input['meeting_type'] ?? ''));
        $status = sanitize_key((string) ($input['status'] ?? ReferralMeeting::STATUS_DRAFT));
        $loc    = sanitize_key((string) ($input['location_type'] ?? ''));

        $url = trim((string) ($input['online_meeting_url'] ?? ''));
        $url = '' !== $url ? esc_url_raw($url) : '';

        return [
            'meeting_type'       => $type,
            'status'             => $status,
            'scheduled_at'       => $this->normalize_datetime((string) ($input['scheduled_at'] ?? '')),
            'scheduled_end_at'   => $this->normalize_datetime((string) ($input['scheduled_end_at'] ?? '')),
            'location_type'      => '' !== $loc ? $loc : null,
            'location_name'      => $this->nullable_text((string) ($input['location_name'] ?? ''), 255),
            'location_address'   => $this->nullable_text((string) ($input['location_address'] ?? ''), 500),
            'online_meeting_url' => '' !== $url ? $url : null,
            'purpose'            => $this->nullable_text((string) ($input['purpose'] ?? ''), 255),
            'outcome'            => $this->nullable_text((string) ($input['outcome'] ?? ''), 255),
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, string>
     */
    private function validate_fields(array $fields, bool $require_type): array
    {
        $errors = [];

        if ($require_type || '' !== (string) ($fields['meeting_type'] ?? '')) {
            if (! ReferralMeeting::is_valid_type((string) ($fields['meeting_type'] ?? ''))) {
                $errors['meeting_type'] = __('Please select a valid meeting type.', 'jm-referral-system');
            }
        }

        if (! ReferralMeeting::is_valid_status((string) ($fields['status'] ?? ''))) {
            $errors['status'] = __('Please select a valid meeting status.', 'jm-referral-system');
        }

        $loc = $fields['location_type'] ?? null;
        if (null !== $loc && '' !== (string) $loc && ! ReferralMeeting::is_valid_location_type((string) $loc)) {
            $errors['location_type'] = __('Please select a valid location type.', 'jm-referral-system');
        }

        $start = $fields['scheduled_at'] ?? null;
        $end   = $fields['scheduled_end_at'] ?? null;
        if (null !== $end && null !== $start && (string) $end < (string) $start) {
            $errors['scheduled_end_at'] = __('End time must be after the start time.', 'jm-referral-system');
        }

        return $errors;
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
