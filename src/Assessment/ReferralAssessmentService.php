<?php

namespace JMReferral\Assessment;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Pipeline\PipelineStage;
use JMReferral\Pipeline\ReferralPipelineService;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;

class ReferralAssessmentService
{
    public const OUTCOME_PENDING = 'pending';
    public const OUTCOME_SUITABLE = 'suitable';
    public const OUTCOME_SUITABLE_WITH_CONDITIONS = 'suitable_with_conditions';
    public const OUTCOME_NOT_SUITABLE = 'not_suitable';

    /**
     * Optional LONGTEXT assessment fields.
     *
     * @var array<int, string>
     */
    public const LONGTEXT_FIELDS = [
        'mobility_support',
        'personal_care_support',
        'medication_support',
        'nutrition_hydration',
        'communication_needs',
        'cognitive_needs',
        'continence_support',
        'home_environment',
        'safeguarding_risks',
        'equipment_required',
        'family_support',
        'preferred_visit_times',
        'summary',
        'recommendations',
    ];

    /**
     * Optional short text assessment fields.
     *
     * @var array<int, string>
     */
    public const SHORTTEXT_FIELDS = [
        'visit_frequency',
        'visit_duration',
    ];

    /**
     * @return array<int, string>
     */
    public static function allowed_outcomes(): array
    {
        return [
            self::OUTCOME_PENDING,
            self::OUTCOME_SUITABLE,
            self::OUTCOME_SUITABLE_WITH_CONDITIONS,
            self::OUTCOME_NOT_SUITABLE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function outcome_labels(): array
    {
        return [
            self::OUTCOME_PENDING                  => __('Pending', 'jm-referral-system'),
            self::OUTCOME_SUITABLE                 => __('Suitable', 'jm-referral-system'),
            self::OUTCOME_SUITABLE_WITH_CONDITIONS => __('Suitable with Conditions', 'jm-referral-system'),
            self::OUTCOME_NOT_SUITABLE             => __('Not Suitable', 'jm-referral-system'),
        ];
    }

    /**
     * Canonical completion check for pipeline/business events.
     *
     * A row with outcome=pending (or missing/unknown) is not completed.
     * Row existence alone is not completion.
     *
     * @param array<string, mixed>|null $assessment
     */
    public static function is_completed_assessment(?array $assessment): bool
    {
        if (null === $assessment) {
            return false;
        }

        $outcome = (string) ($assessment['outcome'] ?? self::OUTCOME_PENDING);

        if (self::OUTCOME_PENDING === $outcome) {
            return false;
        }

        return in_array($outcome, self::allowed_outcomes(), true);
    }

    /**
     * Empty form defaults for create/edit.
     *
     * @return array<string, string>
     */
    public static function empty_form_data(): array
    {
        $data = [
            'assessment_date'  => '',
            'outcome'          => self::OUTCOME_PENDING,
            'next_review_date' => '',
        ];

        foreach (array_merge(self::LONGTEXT_FIELDS, self::SHORTTEXT_FIELDS) as $field) {
            $data[$field] = '';
        }

        return $data;
    }

    /**
     * Maps a stored assessment row to form values.
     *
     * @param array<string, mixed>|null $assessment
     * @return array<string, string>
     */
    public static function map_to_form_data(?array $assessment): array
    {
        $data = self::empty_form_data();

        if (null === $assessment) {
            return $data;
        }

        $data['assessment_date']  = (string) ($assessment['assessment_date'] ?? '');
        $data['outcome']          = (string) ($assessment['outcome'] ?? self::OUTCOME_PENDING);
        $data['next_review_date'] = (string) ($assessment['next_review_date'] ?? '');

        foreach (array_merge(self::LONGTEXT_FIELDS, self::SHORTTEXT_FIELDS) as $field) {
            $data[$field] = (string) ($assessment[$field] ?? '');
        }

        return $data;
    }

    public function __construct(
        private ReferralAssessmentRepository $assessment_repository,
        private ReferralRepository $referral_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy,
        private ?ReferralPipelineService $pipeline_service = null
    ) {
    }

    /**
     * Returns the assessment for a referral when the user may view it.
     *
     * @return array<string, mixed>|null
     */
    public function get_for_referral(int $referral_id): ?array
    {
        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral || ! $this->access_policy->can_view_referral($referral)) {
            return null;
        }

        return $this->assessment_repository->find_by_referral($referral_id);
    }

    /**
     * Creates or updates the assessment for a referral.
     *
     * On clinical completion:
     * - suitable / suitable_with_conditions from assessment_scheduled or assessment_review_required → package_cost_required
     * - not_suitable from assessment_scheduled → assessment_review_required
     * - pending edit while on review does not rewind the pipeline
     *
     * @param array<string, string> $input Sanitized form data.
     * @return array{id: int, created: bool, pipeline_advanced?: bool}|array{errors: array<string, string>}|false
     */
    public function save(int $referral_id, array $input): array|false
    {
        $errors = $this->validate($referral_id, $input);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $existing = $this->assessment_repository->find_by_referral($referral_id);
        $was_completed = self::is_completed_assessment($existing);
        $now      = current_time('mysql');
        $payload  = $this->build_payload($input, $now);

        // Preserve appointment scheduling columns on clinical save.
        if (null !== $existing) {
            foreach (ReferralAssessmentRepository::SCHEDULING_FIELDS as $field) {
                if (! array_key_exists($field, $payload)) {
                    $payload[$field] = $existing[$field] ?? null;
                }
            }
        }

        if (null === $existing) {
            $payload['referral_id']      = $referral_id;
            $payload['assessor_user_id'] = get_current_user_id();
            $payload['created_at']       = $now;

            $id = $this->assessment_repository->create($payload);

            if (false === $id) {
                return false;
            }

            $this->activity_service->log_assessment_created($referral_id);

            $saved = $this->assessment_repository->find_by_referral($referral_id);
            $pipeline_advanced = $this->maybe_advance_pipeline_on_completion(
                $referral_id,
                $was_completed,
                $saved
            );

            return [
                'id'                 => $id,
                'created'            => true,
                'pipeline_advanced'  => $pipeline_advanced,
            ];
        }

        $assessor_user_id = absint($existing['assessor_user_id'] ?? 0);
        if ($assessor_user_id <= 0) {
            $assessor_user_id = get_current_user_id();
        }

        $payload['assessor_user_id'] = $assessor_user_id;

        $updated = $this->assessment_repository->update(
            absint($existing['id'] ?? 0),
            $payload
        );

        if (! $updated) {
            return false;
        }

        $this->activity_service->log_assessment_updated($referral_id);

        $saved = $this->assessment_repository->find_by_referral($referral_id);
        $pipeline_advanced = $this->maybe_advance_pipeline_on_completion(
            $referral_id,
            $was_completed,
            $saved
        );

        return [
            'id'                => absint($existing['id'] ?? 0),
            'created'           => false,
            'pipeline_advanced' => $pipeline_advanced,
        ];
    }

    /**
     * Outcomes that should advance acquisition to Package Cost.
     *
     * @return array<int, string>
     */
    public static function package_cost_eligible_outcomes(): array
    {
        return [
            self::OUTCOME_SUITABLE,
            self::OUTCOME_SUITABLE_WITH_CONDITIONS,
        ];
    }

    public static function is_package_cost_eligible_outcome(string $outcome): bool
    {
        return in_array($outcome, self::package_cost_eligible_outcomes(), true);
    }

    /**
     * @param array<string, mixed>|null $saved
     */
    private function maybe_advance_pipeline_on_completion(
        int $referral_id,
        bool $was_completed,
        ?array $saved
    ): bool {
        if (null === $this->pipeline_service) {
            return false;
        }

        if (! self::is_completed_assessment($saved)) {
            // Pending (or incomplete) edits do not rewind assessment_review_required.
            return false;
        }

        $outcome = (string) ($saved['outcome'] ?? '');

        // First genuine clinical completion → assessment_completed (any non-pending outcome).
        if (! $was_completed) {
            $this->activity_service->log_assessment_completed($referral_id);
        }

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return false;
        }

        $stage = $this->pipeline_service->current_stage_slug($referral);

        if (self::is_package_cost_eligible_outcome($outcome)) {
            if (! in_array(
                $stage,
                [PipelineStage::ASSESSMENT_SCHEDULED, PipelineStage::ASSESSMENT_REVIEW_REQUIRED],
                true
            )) {
                return false;
            }

            $transition = $this->pipeline_service->transition(
                $referral_id,
                PipelineStage::PACKAGE_COST_REQUIRED,
                null,
                true,
                false
            );

            if (empty($transition['ok'])) {
                return false;
            }

            $from_label = (string) ($transition['from_label'] ?? PipelineStage::label($stage));
            $to_label   = (string) ($transition['to_label'] ?? PipelineStage::label(PipelineStage::PACKAGE_COST_REQUIRED));
            $this->activity_service->log_pipeline_stage_changed($referral_id, $from_label, $to_label);

            return true;
        }

        // not_suitable: move to management review (commercial closure is a separate action).
        if (self::OUTCOME_NOT_SUITABLE === $outcome
            && PipelineStage::ASSESSMENT_SCHEDULED === $stage
        ) {
            $transition = $this->pipeline_service->transition(
                $referral_id,
                PipelineStage::ASSESSMENT_REVIEW_REQUIRED,
                null,
                true,
                false
            );

            if (empty($transition['ok'])) {
                return false;
            }

            $from_label = (string) ($transition['from_label'] ?? PipelineStage::label(PipelineStage::ASSESSMENT_SCHEDULED));
            $to_label   = (string) ($transition['to_label'] ?? PipelineStage::label(PipelineStage::ASSESSMENT_REVIEW_REQUIRED));
            $this->activity_service->log_pipeline_stage_changed($referral_id, $from_label, $to_label);

            return true;
        }

        return false;
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    private function build_payload(array $input, string $now): array
    {
        $payload = [
            'assessment_date'  => $this->nullable_date((string) ($input['assessment_date'] ?? '')),
            'outcome'          => (string) ($input['outcome'] ?? self::OUTCOME_PENDING),
            'next_review_date' => $this->nullable_date((string) ($input['next_review_date'] ?? '')),
            'updated_at'       => $now,
        ];

        foreach (array_merge(self::LONGTEXT_FIELDS, self::SHORTTEXT_FIELDS) as $field) {
            $payload[$field] = $this->nullable_text((string) ($input[$field] ?? ''));
        }

        return $payload;
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validate(int $referral_id, array $input): array
    {
        $errors = [];

        if (! Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)) {
            $errors['permission'] = __('You do not have permission to save assessments.', 'jm-referral-system');
            return $errors;
        }

        $referral = $this->referral_repository->find($referral_id);

        if ($referral_id <= 0 || null === $referral) {
            $errors['referral_id'] = __('Referral not found.', 'jm-referral-system');
            return $errors;
        }

        if (! $this->access_policy->can_mutate_referral($referral)) {
            $errors['permission'] = __('You do not have permission to save an assessment for this referral.', 'jm-referral-system');
            return $errors;
        }

        $assessment_date = trim((string) ($input['assessment_date'] ?? ''));
        if ('' === $assessment_date) {
            $errors['assessment_date'] = __('Assessment date is required.', 'jm-referral-system');
        } elseif (null === $this->nullable_date($assessment_date)) {
            $errors['assessment_date'] = __('Please enter a valid assessment date.', 'jm-referral-system');
        }

        $outcome = (string) ($input['outcome'] ?? '');
        if (! in_array($outcome, self::allowed_outcomes(), true)) {
            $errors['outcome'] = __('Please select a valid outcome.', 'jm-referral-system');
        }

        $next_review_date = trim((string) ($input['next_review_date'] ?? ''));
        if ('' !== $next_review_date && null === $this->nullable_date($next_review_date)) {
            $errors['next_review_date'] = __('Please enter a valid next review date.', 'jm-referral-system');
        }

        return $errors;
    }

    private function nullable_date(string $value): ?string
    {
        $value = trim($value);

        if ('' === $value) {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if (! $dt instanceof \DateTimeImmutable || $dt->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }

    private function nullable_text(string $value): ?string
    {
        $value = trim($value);

        return '' !== $value ? $value : null;
    }
}
