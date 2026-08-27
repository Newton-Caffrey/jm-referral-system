<?php

namespace JMReferral\Meeting;

/**
 * Server-side meeting lifecycle transitions (Phase 4B.2.2).
 * Enforced by ReferralMeetingService; UI must not be the only gate.
 */
class MeetingLifecyclePolicy
{
    public const ACTION_CREATE_DRAFT = 'create_draft';
    public const ACTION_CREATE_SCHEDULED = 'create_scheduled';
    public const ACTION_UPDATE_DETAILS = 'update_details';
    public const ACTION_SCHEDULE = 'schedule';
    public const ACTION_RESCHEDULE = 'reschedule';
    public const ACTION_COMPLETE = 'complete';
    public const ACTION_CANCEL = 'cancel';

    /**
     * Whether a status transition is allowed for an explicit action.
     */
    public function allows(string $action, string $current_status): bool
    {
        $current_status = sanitize_key($current_status);
        $action         = sanitize_key($action);

        return match ($action) {
            self::ACTION_CREATE_DRAFT, self::ACTION_CREATE_SCHEDULED => true,
            self::ACTION_UPDATE_DETAILS => in_array($current_status, [
                ReferralMeeting::STATUS_DRAFT,
                ReferralMeeting::STATUS_SCHEDULED,
            ], true),
            self::ACTION_SCHEDULE => ReferralMeeting::STATUS_DRAFT === $current_status,
            self::ACTION_RESCHEDULE => ReferralMeeting::STATUS_SCHEDULED === $current_status,
            self::ACTION_COMPLETE => ReferralMeeting::STATUS_SCHEDULED === $current_status,
            self::ACTION_CANCEL => in_array($current_status, [
                ReferralMeeting::STATUS_DRAFT,
                ReferralMeeting::STATUS_SCHEDULED,
            ], true),
            default => false,
        };
    }

    /**
     * Target status for a successful action (null when create has no prior status).
     */
    public function target_status(string $action): ?string
    {
        return match (sanitize_key($action)) {
            self::ACTION_CREATE_DRAFT => ReferralMeeting::STATUS_DRAFT,
            self::ACTION_CREATE_SCHEDULED, self::ACTION_SCHEDULE, self::ACTION_RESCHEDULE => ReferralMeeting::STATUS_SCHEDULED,
            self::ACTION_COMPLETE => ReferralMeeting::STATUS_COMPLETED,
            self::ACTION_CANCEL => ReferralMeeting::STATUS_CANCELLED,
            self::ACTION_UPDATE_DETAILS => null,
            default => null,
        };
    }

    public function is_terminal(string $status): bool
    {
        return in_array(sanitize_key($status), [
            ReferralMeeting::STATUS_COMPLETED,
            ReferralMeeting::STATUS_CANCELLED,
        ], true);
    }

    public function allows_internal_attendee_add(string $meeting_status): bool
    {
        return in_array(sanitize_key($meeting_status), [
            ReferralMeeting::STATUS_DRAFT,
            ReferralMeeting::STATUS_SCHEDULED,
        ], true);
    }

    public function allows_internal_attendee_edit(string $meeting_status): bool
    {
        return $this->allows_internal_attendee_add($meeting_status);
    }

    public function allows_internal_attendee_remove(string $meeting_status): bool
    {
        return $this->allows_internal_attendee_add($meeting_status);
    }

    public function allows_internal_attendance_correction(string $meeting_status): bool
    {
        return ReferralMeeting::STATUS_COMPLETED === sanitize_key($meeting_status);
    }
}
