<?php

namespace JMReferral\Assessment;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Pipeline\PipelineStage;
use JMReferral\Pipeline\ReferralPipelineService;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;

/**
 * Schedules / reschedules assessment appointments and advances the acquisition pipeline.
 *
 * Does not replace clinical assessment save — see ReferralAssessmentService.
 */
class AssessmentSchedulingService
{
    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralAssessmentRepository $assessment_repository,
        private ReferralPipelineService $pipeline_service,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider
    ) {
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_schedule(array $referral): bool
    {
        if (! $this->access_policy->can_schedule_assessment($referral)) {
            return false;
        }

        return PipelineStage::ASSESSMENT_TO_SCHEDULE === $this->pipeline_service->current_stage_slug($referral);
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_reschedule(array $referral): bool
    {
        if (! $this->access_policy->can_schedule_assessment($referral)) {
            return false;
        }

        return PipelineStage::ASSESSMENT_SCHEDULED === $this->pipeline_service->current_stage_slug($referral);
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_mark_needs_rescheduling(array $referral): bool
    {
        return $this->can_reschedule($referral);
    }

    /**
     * Form / panel context for schedule and appointment UI.
     *
     * @param array<string, mixed> $referral
     * @param array<string, mixed>|null $assessment
     * @return array<string, mixed>
     */
    public function get_panel_context(array $referral, ?array $assessment): array
    {
        $stage = $this->pipeline_service->current_stage_slug($referral);
        $assessor_id = absint($assessment['assessor_user_id'] ?? 0);
        $scheduled_at = (string) ($assessment['scheduled_at'] ?? '');
        $location_type = (string) ($assessment['assessment_location_type'] ?? '');
        $outcome = is_array($assessment) ? (string) ($assessment['outcome'] ?? '') : '';
        $clinically_completed = ReferralAssessmentService::is_completed_assessment($assessment);
        $is_not_suitable = ReferralAssessmentService::OUTCOME_NOT_SUITABLE === $outcome;

        $date = '';
        $time = '';
        if ('' !== $scheduled_at && preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/', $scheduled_at, $m)) {
            $date = $m[1];
            $time = $m[2];
        }

        $can_reschedule = $this->can_reschedule($referral) && ! $clinically_completed;

        return [
            'can_schedule'              => $this->can_schedule($referral),
            'can_reschedule'            => $can_reschedule,
            'can_needs_rescheduling'    => $can_reschedule,
            'stage_slug'                => $stage,
            'has_appointment'           => '' !== $scheduled_at,
            'scheduled_at'              => $scheduled_at,
            'scheduled_date'            => $date,
            'scheduled_time'            => $time,
            'scheduled_date_display'    => '' !== $scheduled_at
                ? (string) mysql2date((string) get_option('date_format'), $scheduled_at)
                : '',
            'scheduled_time_display'    => '' !== $scheduled_at
                ? (string) mysql2date((string) get_option('time_format'), $scheduled_at)
                : '',
            'assessor_user_id'          => $assessor_id,
            'assessor_name'             => $assessor_id > 0
                ? $this->user_provider->get_display_name($assessor_id)
                : '',
            'location_type'             => $location_type,
            'location_type_label'       => '' !== $location_type
                ? AssessmentScheduling::location_type_label($location_type)
                : '',
            'location_name'             => (string) ($assessment['assessment_location_name'] ?? ''),
            'location_address'          => (string) ($assessment['assessment_location_address'] ?? ''),
            'contact_name'              => (string) ($assessment['assessment_contact_name'] ?? ''),
            'contact_phone'             => (string) ($assessment['assessment_contact_phone'] ?? ''),
            'contact_email'             => (string) ($assessment['assessment_contact_email'] ?? ''),
            'scheduling_notes'          => (string) ($assessment['scheduling_notes'] ?? ''),
            'location_types'            => AssessmentScheduling::location_type_labels(),
            'eligible_assessors'        => $this->user_provider->get_assessment_eligible_users(),
            'referral_number'           => (string) ($referral['referral_number'] ?? ''),
            'is_past_appointment'       => '' !== $scheduled_at && strtotime($scheduled_at) < (int) current_time('timestamp'),
            'assessment_completed'      => $clinically_completed,
            'assessment_outcome'        => $outcome,
            'assessment_outcome_label'  => '' !== $outcome
                ? (ReferralAssessmentService::outcome_labels()[$outcome] ?? $outcome)
                : '',
            'is_not_suitable'           => $is_not_suitable,
            'not_suitable_next_action'  => $is_not_suitable && in_array(
                $stage,
                [PipelineStage::ASSESSMENT_SCHEDULED, PipelineStage::ASSESSMENT_REVIEW_REQUIRED],
                true
            )
                ? __('Review assessment outcome and decide whether to proceed', 'jm-referral-system')
                : '',
            'is_outcome_review'         => PipelineStage::ASSESSMENT_REVIEW_REQUIRED === $stage,
        ];
    }

    /**
     * First schedule: assessment_to_schedule → assessment_scheduled.
     *
     * @param array<string, mixed> $input
     * @return array{ok: true}|array{ok: false, error: string, message: string, field_errors?: array<string, string>}
     */
    public function schedule(int $referral_id, array $input): array
    {
        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return $this->fail('referral_not_found', __('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->access_policy->can_schedule_assessment($referral)) {
            return $this->fail('access_denied', __('You do not have permission to schedule an assessment for this referral.', 'jm-referral-system'));
        }

        if (PipelineStage::ASSESSMENT_TO_SCHEDULE !== $this->pipeline_service->current_stage_slug($referral)) {
            return $this->fail(
                'wrong_stage',
                __('Scheduling is only available when the pipeline stage is Assessment to Schedule.', 'jm-referral-system')
            );
        }

        $validated = $this->validate_appointment_input($input);
        if (! empty($validated['errors'])) {
            return [
                'ok'           => false,
                'error'        => 'validation',
                'message'      => __('Please correct the scheduling form and try again.', 'jm-referral-system'),
                'field_errors' => $validated['errors'],
            ];
        }

        /** @var array<string, mixed> $fields */
        $fields = $validated['fields'];
        $now    = current_time('mysql');
        $existing = $this->assessment_repository->find_by_referral($referral_id);

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        if (null === $existing) {
            $created = $this->assessment_repository->create(
                [
                    'referral_id'                   => $referral_id,
                    'assessor_user_id'              => $fields['assessor_user_id'],
                    'assessment_date'               => null,
                    'outcome'                       => ReferralAssessmentService::OUTCOME_PENDING,
                    'scheduled_at'                  => $fields['scheduled_at'],
                    'assessment_location_type'      => $fields['assessment_location_type'],
                    'assessment_location_name'      => $fields['assessment_location_name'],
                    'assessment_location_address'   => $fields['assessment_location_address'],
                    'assessment_contact_name'       => $fields['assessment_contact_name'],
                    'assessment_contact_phone'      => $fields['assessment_contact_phone'],
                    'assessment_contact_email'      => $fields['assessment_contact_email'],
                    'scheduling_notes'              => $fields['scheduling_notes'],
                    'created_at'                    => $now,
                    'updated_at'                    => $now,
                ]
            );

            if (false === $created) {
                $wpdb->query('ROLLBACK');

                return $this->fail('persist_failed', __('Unable to create the assessment appointment. Please try again.', 'jm-referral-system'));
            }
        } else {
            $saved = $this->assessment_repository->update_scheduling(
                absint($existing['id'] ?? 0),
                array_merge($fields, ['updated_at' => $now])
            );

            if (! $saved) {
                $wpdb->query('ROLLBACK');

                return $this->fail('persist_failed', __('Unable to save the assessment appointment. Please try again.', 'jm-referral-system'));
            }
        }

        $transition = $this->pipeline_service->transition(
            $referral_id,
            PipelineStage::ASSESSMENT_SCHEDULED,
            null,
            false,
            false
        );

        if (empty($transition['ok'])) {
            $wpdb->query('ROLLBACK');

            return $this->fail(
                'transition_failed',
                __('The appointment could not be scheduled because the pipeline could not be updated. Please try again.', 'jm-referral-system')
            );
        }

        $wpdb->query('COMMIT');

        if (null === $existing) {
            $this->activity_service->log_assessment_created($referral_id);
        }

        $this->activity_service->log_assessment_scheduled($referral_id, (string) $fields['scheduled_at']);

        $from_label = (string) ($transition['from_label'] ?? PipelineStage::label(PipelineStage::ASSESSMENT_TO_SCHEDULE));
        $to_label   = (string) ($transition['to_label'] ?? PipelineStage::label(PipelineStage::ASSESSMENT_SCHEDULED));
        $this->activity_service->log_pipeline_stage_changed($referral_id, $from_label, $to_label);

        return ['ok' => true];
    }

    /**
     * Update appointment while remaining on assessment_scheduled (no stage transition).
     *
     * @param array<string, mixed> $input
     * @return array{ok: true}|array{ok: false, error: string, message: string, field_errors?: array<string, string>}
     */
    public function reschedule(int $referral_id, array $input): array
    {
        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return $this->fail('referral_not_found', __('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->access_policy->can_schedule_assessment($referral)) {
            return $this->fail('access_denied', __('You do not have permission to reschedule this assessment.', 'jm-referral-system'));
        }

        if (PipelineStage::ASSESSMENT_SCHEDULED !== $this->pipeline_service->current_stage_slug($referral)) {
            return $this->fail(
                'wrong_stage',
                __('Rescheduling is only available when the pipeline stage is Assessment Scheduled.', 'jm-referral-system')
            );
        }

        $existing = $this->assessment_repository->find_by_referral($referral_id);
        if (null === $existing) {
            return $this->fail('no_assessment', __('No assessment appointment exists to reschedule.', 'jm-referral-system'));
        }

        $validated = $this->validate_appointment_input($input);
        if (! empty($validated['errors'])) {
            return [
                'ok'           => false,
                'error'        => 'validation',
                'message'      => __('Please correct the scheduling form and try again.', 'jm-referral-system'),
                'field_errors' => $validated['errors'],
            ];
        }

        /** @var array<string, mixed> $fields */
        $fields = $validated['fields'];
        $fields['updated_at'] = current_time('mysql');

        $saved = $this->assessment_repository->update_scheduling(
            absint($existing['id'] ?? 0),
            $fields
        );

        if (! $saved) {
            return $this->fail('persist_failed', __('Unable to reschedule the assessment. Please try again.', 'jm-referral-system'));
        }

        $this->activity_service->log_assessment_rescheduled($referral_id, (string) $fields['scheduled_at']);

        return ['ok' => true];
    }

    /**
     * Cancel/postpone appointment: assessment_scheduled → assessment_to_schedule.
     *
     * Preserves previous scheduling fields for audit; activity records the reason.
     *
     * @return array{ok: true}|array{ok: false, error: string, message: string}
     */
    public function mark_needs_rescheduling(int $referral_id, string $reason): array
    {
        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return $this->fail('referral_not_found', __('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->access_policy->can_schedule_assessment($referral)) {
            return $this->fail('access_denied', __('You do not have permission to update this assessment appointment.', 'jm-referral-system'));
        }

        if (PipelineStage::ASSESSMENT_SCHEDULED !== $this->pipeline_service->current_stage_slug($referral)) {
            return $this->fail(
                'wrong_stage',
                __('Needs Rescheduling is only available when the pipeline stage is Assessment Scheduled.', 'jm-referral-system')
            );
        }

        $reason = trim(sanitize_text_field($reason));
        if ('' === $reason) {
            return $this->fail(
                'reason_required',
                __('Please provide a short operational reason for needing to reschedule.', 'jm-referral-system')
            );
        }

        if (strlen($reason) > AssessmentScheduling::REASON_MAX_LENGTH) {
            return $this->fail(
                'reason_too_long',
                __('The reason is too long. Please keep it brief and operational.', 'jm-referral-system')
            );
        }

        $transition = $this->pipeline_service->transition(
            $referral_id,
            PipelineStage::ASSESSMENT_TO_SCHEDULE,
            $reason,
            true,
            false
        );

        if (empty($transition['ok'])) {
            return $this->fail(
                'transition_failed',
                __('Unable to mark the assessment as needing rescheduling. Please try again.', 'jm-referral-system')
            );
        }

        $this->activity_service->log_assessment_needs_rescheduling($referral_id);
        $from_label = (string) ($transition['from_label'] ?? PipelineStage::label(PipelineStage::ASSESSMENT_SCHEDULED));
        $to_label   = (string) ($transition['to_label'] ?? PipelineStage::label(PipelineStage::ASSESSMENT_TO_SCHEDULE));
        $this->activity_service->log_pipeline_stage_changed($referral_id, $from_label, $to_label);

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, fields: array<string, mixed>}
     */
    private function validate_appointment_input(array $input): array
    {
        $errors = [];

        $date = trim((string) ($input['scheduled_date'] ?? ''));
        $time = trim((string) ($input['scheduled_time'] ?? ''));

        $scheduled_at = null;
        if ('' === $date || '' === $time) {
            $errors['scheduled_at'] = __('Assessment date and time are required.', 'jm-referral-system');
        } else {
            $scheduled_at = $this->normalize_scheduled_at($date, $time);
            if (null === $scheduled_at) {
                $errors['scheduled_at'] = __('Please enter a valid assessment date and time.', 'jm-referral-system');
            }
        }

        $assessor_id = absint($input['assessor_user_id'] ?? 0);
        if ($assessor_id <= 0) {
            $errors['assessor_user_id'] = __('Please select an assessor.', 'jm-referral-system');
        } elseif (! $this->user_provider->is_assessment_eligible($assessor_id)) {
            $errors['assessor_user_id'] = __('Please select an eligible assessor.', 'jm-referral-system');
        }

        $location_type = sanitize_key((string) ($input['assessment_location_type'] ?? ''));
        if (! AssessmentScheduling::is_valid_location_type($location_type)) {
            $errors['assessment_location_type'] = __('Please select a location type.', 'jm-referral-system');
        }

        $location_name = sanitize_text_field((string) ($input['assessment_location_name'] ?? ''));
        $location_name = substr(trim($location_name), 0, 190);
        if ('' === $location_name) {
            $errors['assessment_location_name'] = __('Location name is required.', 'jm-referral-system');
        }

        $address = sanitize_textarea_field((string) ($input['assessment_location_address'] ?? ''));
        $address = substr(trim($address), 0, AssessmentScheduling::ADDRESS_MAX_LENGTH);

        $contact_name = sanitize_text_field((string) ($input['assessment_contact_name'] ?? ''));
        $contact_name = substr(trim($contact_name), 0, 190);

        $contact_phone = sanitize_text_field((string) ($input['assessment_contact_phone'] ?? ''));
        $contact_phone = substr(trim($contact_phone), 0, 50);

        $contact_email = sanitize_email((string) ($input['assessment_contact_email'] ?? ''));
        if ('' !== trim((string) ($input['assessment_contact_email'] ?? ''))) {
            if ('' === $contact_email || ! is_email($contact_email)) {
                $errors['assessment_contact_email'] = __('Please enter a valid contact email, or leave it blank.', 'jm-referral-system');
            }
        } else {
            $contact_email = '';
        }

        $notes = sanitize_textarea_field((string) ($input['scheduling_notes'] ?? ''));
        $notes = substr(trim($notes), 0, AssessmentScheduling::NOTES_MAX_LENGTH);

        return [
            'errors' => $errors,
            'fields' => [
                'assessor_user_id'            => $assessor_id,
                'scheduled_at'                => $scheduled_at,
                'assessment_location_type'    => $location_type,
                'assessment_location_name'    => $location_name,
                'assessment_location_address' => '' !== $address ? $address : null,
                'assessment_contact_name'     => '' !== $contact_name ? $contact_name : null,
                'assessment_contact_phone'    => '' !== $contact_phone ? $contact_phone : null,
                'assessment_contact_email'    => '' !== $contact_email ? $contact_email : null,
                'scheduling_notes'            => '' !== $notes ? $notes : null,
            ],
        ];
    }

    private function normalize_scheduled_at(string $date, string $time): ?string
    {
        $date = trim($date);
        $time = trim($time);

        if (! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        $combined = $date . ' ' . $time;
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $combined);

        if (! $dt instanceof \DateTimeImmutable || $dt->format('Y-m-d H:i:s') !== $combined) {
            return null;
        }

        return $combined;
    }

    /**
     * @return array{ok: false, error: string, message: string}
     */
    private function fail(string $error, string $message): array
    {
        return [
            'ok'      => false,
            'error'   => $error,
            'message' => $message,
        ];
    }
}
