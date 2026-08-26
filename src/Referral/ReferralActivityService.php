<?php

namespace JMReferral\Referral;

class ReferralActivityService
{
    public function __construct(
        private ReferralActivityRepository $repository
    ) {
    }

    /**
     * Logs a custom activity action for a referral.
     */
    public function log_action(int $referral_id, string $action, string $description): void
    {
        $this->log($referral_id, $action, $description);
    }

    /**
     * Logs that a referral was archived.
     */
    public function log_archived(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'referral_archived',
            __('Referral archived', 'jm-referral-system')
        );
    }

    /**
     * Logs that a referral was restored from archive.
     */
    public function log_restored(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'referral_restored',
            __('Referral restored', 'jm-referral-system')
        );
    }

    /**
     * Logs that a referral was created.
     */
    public function log_created(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'created',
            __('Referral created', 'jm-referral-system')
        );
    }

    /**
     * Logs that a referral was updated.
     */
    public function log_updated(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'updated',
            __('Referral updated', 'jm-referral-system')
        );
    }

    /**
     * Logs that a referral status changed.
     */
    public function log_status_changed(int $referral_id, string $old_status, string $new_status): void
    {
        $description = sprintf(
            /* translators: 1: previous status, 2: new status */
            __('Status changed from %1$s to %2$s', 'jm-referral-system'),
            $old_status,
            $new_status
        );

        $this->log($referral_id, 'status_changed', $description);
    }

    /**
     * Logs that a referral was assigned.
     */
    public function log_assigned(int $referral_id, string $display_name): void
    {
        $description = sprintf(
            /* translators: %s: assignee display name */
            __('Referral assigned to %s', 'jm-referral-system'),
            $display_name
        );

        $this->log($referral_id, 'assigned', $description);
    }

    /**
     * Logs that a referral was reassigned.
     */
    public function log_reassigned(int $referral_id, string $old_name, string $new_name): void
    {
        $description = sprintf(
            /* translators: 1: previous assignee, 2: new assignee */
            __('Referral reassigned from %1$s to %2$s', 'jm-referral-system'),
            $old_name,
            $new_name
        );

        $this->log($referral_id, 'reassigned', $description);
    }

    /**
     * Logs that a referral workflow stage changed.
     */
    public function log_workflow_stage_changed(int $referral_id, string $old_stage, string $new_stage): void
    {
        $description = sprintf(
            /* translators: 1: previous workflow stage, 2: new workflow stage */
            __('Workflow stage changed from %1$s to %2$s', 'jm-referral-system'),
            $old_stage,
            $new_stage
        );

        $this->log($referral_id, 'workflow_stage_changed', $description);
    }

    public function log_pipeline_started(int $referral_id, string $stage_label): void
    {
        $description = sprintf(
            /* translators: %s: pipeline stage label */
            __('Referral entered %s.', 'jm-referral-system'),
            $stage_label
        );

        $this->log($referral_id, 'pipeline_started', $description);
    }

    public function log_pipeline_stage_changed(int $referral_id, string $from_label, string $to_label): void
    {
        $description = sprintf(
            /* translators: 1: previous pipeline stage, 2: new pipeline stage */
            __('Pipeline moved from %1$s to %2$s.', 'jm-referral-system'),
            $from_label,
            $to_label
        );

        $this->log($referral_id, 'pipeline_stage_changed', $description);
    }

    public function log_pipeline_stage_overridden(int $referral_id, string $from_label, string $to_label): void
    {
        $description = sprintf(
            /* translators: 1: previous pipeline stage, 2: new pipeline stage */
            __('Pipeline stage overridden from %1$s to %2$s.', 'jm-referral-system'),
            $from_label,
            $to_label
        );

        $this->log($referral_id, 'pipeline_stage_overridden', $description);
    }

    public function log_interest_expressed(int $referral_id, string $method): void
    {
        $description = match ($method) {
            'email' => __('Interest expressed to the referrer by email.', 'jm-referral-system'),
            'phone' => __('Interest response recorded by phone.', 'jm-referral-system'),
            default => __('Interest response recorded through another communication channel.', 'jm-referral-system'),
        };

        $this->log($referral_id, 'interest_expressed', $description);
    }

    public function log_interest_email_failed(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'interest_email_failed',
            __('Interest email could not be sent. Pipeline was not advanced.', 'jm-referral-system')
        );
    }

    /**
     * Logs that an internal note was added.
     */
    public function log_note_added(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'note_added',
            __('Internal note added', 'jm-referral-system')
        );
    }

    /**
     * Logs that a document was uploaded.
     */
    public function log_document_uploaded(int $referral_id, string $original_filename): void
    {
        $description = sprintf(
            /* translators: %s: original uploaded filename */
            __('Document uploaded: %s', 'jm-referral-system'),
            $original_filename
        );

        $this->log($referral_id, 'document_uploaded', $description);
    }

    /**
     * Logs that an assessment was created.
     */
    public function log_assessment_created(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'assessment_created',
            __('Assessment created', 'jm-referral-system')
        );
    }

    /**
     * Logs that an assessment was updated.
     */
    public function log_assessment_updated(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'assessment_updated',
            __('Assessment updated', 'jm-referral-system')
        );
    }

    public function log_assessment_scheduled(int $referral_id, string $scheduled_at = ''): void
    {
        $description = __('Assessment scheduled.', 'jm-referral-system');
        $scheduled_at = trim($scheduled_at);
        if ('' !== $scheduled_at) {
            $display = (string) mysql2date(
                (string) get_option('date_format') . ' ' . (string) get_option('time_format'),
                $scheduled_at
            );
            if ('' !== $display) {
                $description = sprintf(
                    /* translators: %s: scheduled date/time */
                    __('Assessment scheduled for %s.', 'jm-referral-system'),
                    $display
                );
            }
        }

        $this->log($referral_id, 'assessment_scheduled', $description);
    }

    public function log_assessment_rescheduled(int $referral_id, string $scheduled_at = ''): void
    {
        $description = __('Assessment rescheduled.', 'jm-referral-system');
        $scheduled_at = trim($scheduled_at);
        if ('' !== $scheduled_at) {
            $display = (string) mysql2date(
                (string) get_option('date_format') . ' ' . (string) get_option('time_format'),
                $scheduled_at
            );
            if ('' !== $display) {
                $description = sprintf(
                    /* translators: %s: scheduled date/time */
                    __('Assessment rescheduled for %s.', 'jm-referral-system'),
                    $display
                );
            }
        }

        $this->log($referral_id, 'assessment_rescheduled', $description);
    }

    public function log_assessment_needs_rescheduling(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'assessment_needs_rescheduling',
            __('Assessment requires rescheduling.', 'jm-referral-system')
        );
    }

    public function log_assessment_completed(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'assessment_completed',
            __('Assessment completed.', 'jm-referral-system')
        );
    }

    public function log_package_cost_prepared(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'package_cost_prepared',
            __('Package Cost prepared.', 'jm-referral-system')
        );
    }

    public function log_package_cost_updated(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'package_cost_updated',
            __('Package Cost updated.', 'jm-referral-system')
        );
    }

    public function log_package_cost_sent(int $referral_id, string $method = ''): void
    {
        $description = 'email' === $method
            ? __('Package Cost emailed to the Local Authority.', 'jm-referral-system')
            : __('Package Cost submitted to the Local Authority.', 'jm-referral-system');

        $this->log($referral_id, 'package_cost_sent', $description);
    }

    public function log_package_cost_email_failed(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'package_cost_email_failed',
            __('Package Cost email could not be sent.', 'jm-referral-system')
        );
    }

    public function log_la_decision_approved(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'la_decision_approved',
            __('Local Authority approval recorded.', 'jm-referral-system')
        );
    }

    public function log_la_decision_declined(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'la_decision_declined',
            __('Local Authority decision recorded as declined.', 'jm-referral-system')
        );
    }

    public function log_referral_not_proceeding(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'referral_not_proceeding',
            __('Referral marked as not proceeding.', 'jm-referral-system')
        );
    }

    public function log_care_commenced(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'care_commenced',
            __('Care commencement recorded.', 'jm-referral-system')
        );
    }

    /**
     * Logs that a care plan was created.
     */
    public function log_care_plan_created(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'care_plan_created',
            __('Care plan created', 'jm-referral-system')
        );
    }

    /**
     * Logs that a care plan was updated.
     */
    public function log_care_plan_updated(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'care_plan_updated',
            __('Care plan updated', 'jm-referral-system')
        );
    }

    /**
     * Logs that a care plan was activated.
     */
    public function log_care_plan_activated(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'care_plan_activated',
            __('Care plan activated', 'jm-referral-system')
        );
    }

    /**
     * Logs that a care plan was reviewed.
     */
    public function log_care_plan_reviewed(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'care_plan_reviewed',
            __('Care plan reviewed', 'jm-referral-system')
        );
    }

    /**
     * Logs that a care plan version snapshot was created.
     */
    public function log_care_plan_version_created(int $referral_id, int $version_number): void
    {
        $description = sprintf(
            /* translators: %d: care plan version number */
            __('Care plan version %d created', 'jm-referral-system'),
            $version_number
        );

        $this->log($referral_id, 'care_plan_version_created', $description);
    }

    /**
     * Logs that a care visit was scheduled.
     */
    public function log_visit_created(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'visit_created',
            __('Care visit scheduled', 'jm-referral-system')
        );
    }

    /**
     * Logs that a care visit was updated.
     */
    public function log_visit_updated(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'visit_updated',
            __('Care visit updated', 'jm-referral-system')
        );
    }

    /**
     * Logs that a care visit was completed.
     */
    public function log_visit_completed(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'visit_completed',
            __('Care visit completed', 'jm-referral-system')
        );
    }

    /**
     * Logs that staff recorded visit execution details.
     */
    public function log_visit_executed(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'visit_executed',
            __('Visit completed by staff', 'jm-referral-system')
        );
    }

    /**
     * Logs that a visit task checklist was updated.
     */
    public function log_visit_tasks_updated(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'visit_tasks_updated',
            __('Visit task checklist updated', 'jm-referral-system')
        );
    }

    /**
     * Logs that a manager reviewed an executed visit.
     */
    public function log_visit_reviewed(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'visit_reviewed',
            __('Visit reviewed by manager', 'jm-referral-system')
        );
    }

    /**
     * Logs that a medication was added.
     */
    public function log_medication_added(int $referral_id, string $medication_name): void
    {
        $this->log(
            $referral_id,
            'medication_added',
            sprintf(
                /* translators: %s: medication name */
                __('Medication added: %s', 'jm-referral-system'),
                $medication_name
            )
        );
    }

    /**
     * Logs that a medication was updated.
     */
    public function log_medication_updated(int $referral_id, string $medication_name): void
    {
        $this->log(
            $referral_id,
            'medication_updated',
            sprintf(
                /* translators: %s: medication name */
                __('Medication updated: %s', 'jm-referral-system'),
                $medication_name
            )
        );
    }

    /**
     * Logs that a medication status changed.
     */
    public function log_medication_status_changed(int $referral_id, string $from, string $to): void
    {
        $this->log(
            $referral_id,
            'medication_status_changed',
            sprintf(
                /* translators: 1: previous status, 2: new status */
                __('Medication status changed from %1$s to %2$s', 'jm-referral-system'),
                $from,
                $to
            )
        );
    }

    /**
     * Logs that a medication administration was recorded.
     */
    public function log_medication_administered(int $referral_id, string $medication_name, string $status_label): void
    {
        $this->log(
            $referral_id,
            'medication_administered',
            sprintf(
                /* translators: 1: medication name, 2: administration status */
                __('Medication administration recorded: %1$s — %2$s', 'jm-referral-system'),
                $medication_name,
                $status_label
            )
        );
    }

    /**
     * Logs that a care team member was added.
     */
    public function log_care_team_member_added(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'care_team_member_added',
            __('Care team member added', 'jm-referral-system')
        );
    }

    /**
     * Logs that a care team member was updated.
     */
    public function log_care_team_member_updated(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'care_team_member_updated',
            __('Care team member updated', 'jm-referral-system')
        );
    }

    /**
     * Logs that the primary carer for a referral changed.
     */
    public function log_care_team_primary_changed(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'care_team_primary_changed',
            __('Primary carer changed', 'jm-referral-system')
        );
    }

    /**
     * Logs that a visit schedule was created.
     */
    public function log_schedule_created(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'schedule_created',
            __('Visit schedule created', 'jm-referral-system')
        );
    }

    /**
     * Logs that a visit schedule was updated.
     */
    public function log_schedule_updated(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'schedule_updated',
            __('Visit schedule updated', 'jm-referral-system')
        );
    }

    /**
     * Logs that care visits were generated from a schedule (one entry per generation run).
     */
    public function log_schedule_visits_generated(int $referral_id, int $count): void
    {
        $this->log(
            $referral_id,
            'schedule_visits_generated',
            sprintf(
                /* translators: %d: number of visits generated */
                _n(
                    '%d care visit generated from schedule',
                    '%d care visits generated from schedule',
                    $count,
                    'jm-referral-system'
                ),
                $count
            )
        );
    }

    /**
     * Logs an explicit care setting change (not auto-classification during placement).
     */
    public function log_care_setting_changed(int $referral_id, ?string $care_setting): void
    {
        $label = CareSetting::label($care_setting);

        $this->log(
            $referral_id,
            'care_setting_changed',
            sprintf(
                /* translators: %s: care setting label */
                __('Care setting changed to %s.', 'jm-referral-system'),
                $label
            )
        );
    }

    /**
     * Logs a client residential address change (own-home service location).
     * Description deliberately omits address text.
     */
    public function log_client_address_updated(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'client_address_updated',
            __('Client address updated.', 'jm-referral-system')
        );
    }

    public function log_meeting_created(int $referral_id, string $meeting_type_label = ''): void
    {
        $this->log(
            $referral_id,
            'meeting_created',
            '' !== $meeting_type_label
                ? sprintf(
                    /* translators: %s: meeting type label */
                    __('Meeting created (%s).', 'jm-referral-system'),
                    $meeting_type_label
                )
                : __('Meeting created.', 'jm-referral-system')
        );
    }

    public function log_meeting_updated(int $referral_id): void
    {
        $this->log($referral_id, 'meeting_updated', __('Meeting updated.', 'jm-referral-system'));
    }

    public function log_meeting_rescheduled(int $referral_id): void
    {
        $this->log($referral_id, 'meeting_rescheduled', __('Meeting rescheduled.', 'jm-referral-system'));
    }

    public function log_meeting_completed(int $referral_id): void
    {
        $this->log($referral_id, 'meeting_completed', __('Meeting completed.', 'jm-referral-system'));
    }

    public function log_meeting_cancelled(int $referral_id): void
    {
        $this->log($referral_id, 'meeting_cancelled', __('Meeting cancelled.', 'jm-referral-system'));
    }

    public function log_meeting_attendee_added(int $referral_id, string $kind_label = ''): void
    {
        $this->log(
            $referral_id,
            'meeting_attendee_added',
            '' !== $kind_label
                ? sprintf(
                    /* translators: %s: attendee kind label */
                    __('Meeting attendee added (%s).', 'jm-referral-system'),
                    $kind_label
                )
                : __('Meeting attendee added.', 'jm-referral-system')
        );
    }

    public function log_meeting_attendee_updated(int $referral_id): void
    {
        $this->log($referral_id, 'meeting_attendee_updated', __('Meeting attendee updated.', 'jm-referral-system'));
    }

    public function log_meeting_attendee_removed(int $referral_id): void
    {
        $this->log($referral_id, 'meeting_attendee_removed', __('Meeting attendee removed.', 'jm-referral-system'));
    }

    public function log_champion_assigned(int $referral_id, string $display_name): void
    {
        $this->log(
            $referral_id,
            'champion_assigned',
            sprintf(
                /* translators: %s: staff display name */
                __('Client champion assigned to %s.', 'jm-referral-system'),
                $display_name
            )
        );
    }

    public function log_champion_reassigned(int $referral_id, string $old_name, string $new_name): void
    {
        $this->log(
            $referral_id,
            'champion_reassigned',
            sprintf(
                /* translators: 1: previous name 2: new name */
                __('Client champion reassigned from %1$s to %2$s.', 'jm-referral-system'),
                $old_name,
                $new_name
            )
        );
    }

    public function log_champion_unassigned(int $referral_id): void
    {
        $this->log($referral_id, 'champion_unassigned', __('Client champion cleared.', 'jm-referral-system'));
    }

    public function log_transition_lead_assigned(int $referral_id, string $display_name): void
    {
        $this->log(
            $referral_id,
            'transition_lead_assigned',
            sprintf(
                /* translators: %s: staff display name */
                __('Transition lead assigned to %s.', 'jm-referral-system'),
                $display_name
            )
        );
    }

    public function log_transition_lead_reassigned(int $referral_id, string $old_name, string $new_name): void
    {
        $this->log(
            $referral_id,
            'transition_lead_reassigned',
            sprintf(
                /* translators: 1: previous name 2: new name */
                __('Transition lead reassigned from %1$s to %2$s.', 'jm-referral-system'),
                $old_name,
                $new_name
            )
        );
    }

    public function log_transition_lead_unassigned(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'transition_lead_unassigned',
            __('Transition lead cleared.', 'jm-referral-system')
        );
    }

    /**
     * Persists an activity entry for the current user.
     */
    private function log(int $referral_id, string $action, string $description): void
    {
        if ($referral_id <= 0 || '' === $action) {
            return;
        }

        $this->repository->create(
            [
                'referral_id' => $referral_id,
                'user_id'     => get_current_user_id(),
                'action'      => $action,
                'description' => $description,
            ]
        );
    }
}
