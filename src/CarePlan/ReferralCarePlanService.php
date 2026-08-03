<?php

namespace JMReferral\CarePlan;

use JMReferral\Assessment\ReferralAssessmentRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;

class ReferralCarePlanService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Assessment field => care plan field mapping for generation.
     *
     * @var array<string, string>
     */
    public const ASSESSMENT_FIELD_MAP = [
        'visit_frequency'       => 'visit_frequency',
        'visit_duration'        => 'visit_duration',
        'preferred_visit_times' => 'preferred_visit_times',
        'personal_care_support' => 'personal_care_tasks',
        'mobility_support'      => 'mobility_support',
        'medication_support'    => 'medication_support',
        'nutrition_hydration'   => 'nutrition_support',
        'communication_needs'   => 'communication_support',
        'continence_support'    => 'continence_support',
        'family_support'        => 'social_support',
        'equipment_required'    => 'equipment_required',
        'safeguarding_risks'    => 'risks_and_safeguards',
        'recommendations'       => 'goals',
        'summary'               => 'additional_instructions',
    ];

    /**
     * @var array<int, string>
     */
    public const LONGTEXT_FIELDS = [
        'preferred_visit_times',
        'personal_care_tasks',
        'mobility_support',
        'medication_support',
        'nutrition_support',
        'communication_support',
        'continence_support',
        'social_support',
        'equipment_required',
        'risks_and_safeguards',
        'goals',
        'additional_instructions',
    ];

    /**
     * @var array<int, string>
     */
    public const SHORTTEXT_FIELDS = [
        'visit_frequency',
        'visit_duration',
    ];

    /**
     * @return array<int, string>
     */
    public static function allowed_statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_ACTIVE,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function status_labels(): array
    {
        return [
            self::STATUS_DRAFT        => __('Draft', 'jm-referral-system'),
            self::STATUS_ACTIVE       => __('Active', 'jm-referral-system'),
            self::STATUS_UNDER_REVIEW => __('Under Review', 'jm-referral-system'),
            self::STATUS_COMPLETED    => __('Completed', 'jm-referral-system'),
            self::STATUS_CANCELLED    => __('Cancelled', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function empty_form_data(): array
    {
        $data = [
            'plan_status'   => self::STATUS_DRAFT,
            'start_date'    => '',
            'review_date'   => '',
            'assessment_id' => '',
        ];

        foreach (array_merge(self::LONGTEXT_FIELDS, self::SHORTTEXT_FIELDS) as $field) {
            $data[$field] = '';
        }

        return $data;
    }

    /**
     * @param array<string, mixed>|null $care_plan
     * @return array<string, string>
     */
    public static function map_to_form_data(?array $care_plan): array
    {
        $data = self::empty_form_data();

        if (null === $care_plan) {
            return $data;
        }

        $data['plan_status']   = (string) ($care_plan['plan_status'] ?? self::STATUS_DRAFT);
        $data['start_date']    = (string) ($care_plan['start_date'] ?? '');
        $data['review_date']   = (string) ($care_plan['review_date'] ?? '');
        $data['assessment_id'] = (string) absint($care_plan['assessment_id'] ?? 0);

        foreach (array_merge(self::LONGTEXT_FIELDS, self::SHORTTEXT_FIELDS) as $field) {
            $data[$field] = (string) ($care_plan[$field] ?? '');
        }

        return $data;
    }

    public function __construct(
        private ReferralCarePlanRepository $care_plan_repository,
        private ReferralRepository $referral_repository,
        private ReferralAssessmentRepository $assessment_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Builds form values from the referral assessment without saving.
     *
     * @return array{data: array<string, string>}|array{errors: array<string, string>}
     */
    public function generate_from_assessment(int $referral_id): array
    {
        $errors = $this->authorize_manage($referral_id);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        if ($this->care_plan_repository->exists($referral_id)) {
            return [
                'errors' => [
                    'general' => __('A care plan already exists for this referral.', 'jm-referral-system'),
                ],
            ];
        }

        $assessment = $this->assessment_repository->find_by_referral($referral_id);

        if (null === $assessment) {
            return [
                'errors' => [
                    'general' => __('An assessment is required before generating a care plan.', 'jm-referral-system'),
                ],
            ];
        }

        $data = self::empty_form_data();
        $data['assessment_id'] = (string) absint($assessment['id'] ?? 0);

        foreach (self::ASSESSMENT_FIELD_MAP as $assessment_field => $care_plan_field) {
            $data[$care_plan_field] = (string) ($assessment[$assessment_field] ?? '');
        }

        return ['data' => $data];
    }

    /**
     * @return array{data: array<string, string>}|array{errors: array<string, string>}
     */
    public function prepare_blank(int $referral_id): array
    {
        $errors = $this->authorize_manage($referral_id);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        if ($this->care_plan_repository->exists($referral_id)) {
            return [
                'errors' => [
                    'general' => __('A care plan already exists for this referral.', 'jm-referral-system'),
                ],
            ];
        }

        $data = self::empty_form_data();
        $assessment = $this->assessment_repository->find_by_referral($referral_id);
        if (null !== $assessment) {
            $data['assessment_id'] = (string) absint($assessment['id'] ?? 0);
        }

        return ['data' => $data];
    }

    /**
     * Creates or updates the care plan for a referral.
     *
     * @param array<string, string> $input
     * @return array{id: int, created: bool}|array{errors: array<string, string>}|false
     */
    public function save(int $referral_id, array $input): array|false
    {
        $errors = $this->validate($referral_id, $input);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $existing   = $this->care_plan_repository->find_by_referral($referral_id);
        $now        = current_time('mysql');
        $new_status = (string) ($input['plan_status'] ?? self::STATUS_DRAFT);
        $old_status = is_array($existing) ? (string) ($existing['plan_status'] ?? '') : '';

        $approved_by = null;
        if (is_array($existing)) {
            $approved_by = absint($existing['approved_by'] ?? 0) ?: null;
        }

        if (self::STATUS_DRAFT === $new_status) {
            $approved_by = null;
        } elseif (self::STATUS_ACTIVE === $new_status && self::STATUS_ACTIVE !== $old_status) {
            $approved_by = get_current_user_id();
        }

        $assessment_id = absint($input['assessment_id'] ?? 0);
        if ($assessment_id <= 0) {
            $assessment = $this->assessment_repository->find_by_referral($referral_id);
            $assessment_id = absint($assessment['id'] ?? 0);
        }

        $payload = [
            'assessment_id'           => $assessment_id > 0 ? $assessment_id : null,
            'approved_by'             => $approved_by,
            'plan_status'             => $new_status,
            'start_date'              => $this->nullable_date((string) ($input['start_date'] ?? '')),
            'review_date'             => $this->nullable_date((string) ($input['review_date'] ?? '')),
            'updated_at'              => $now,
        ];

        foreach (array_merge(self::LONGTEXT_FIELDS, self::SHORTTEXT_FIELDS) as $field) {
            $payload[$field] = $this->nullable_text((string) ($input[$field] ?? ''));
        }

        if (null === $existing) {
            $payload['referral_id'] = $referral_id;
            $payload['created_by']  = get_current_user_id();
            $payload['created_at']  = $now;

            $id = $this->care_plan_repository->create($payload);

            if (false === $id) {
                return false;
            }

            $this->activity_service->log_care_plan_created($referral_id);

            if (self::STATUS_ACTIVE === $new_status) {
                $this->activity_service->log_care_plan_activated($referral_id);
            }

            return [
                'id'      => $id,
                'created' => true,
            ];
        }

        $updated = $this->care_plan_repository->update(
            absint($existing['id'] ?? 0),
            $payload
        );

        if (! $updated) {
            return false;
        }

        $this->activity_service->log_care_plan_updated($referral_id);

        if (self::STATUS_ACTIVE === $new_status && self::STATUS_ACTIVE !== $old_status) {
            $this->activity_service->log_care_plan_activated($referral_id);
        }

        return [
            'id'      => absint($existing['id'] ?? 0),
            'created' => false,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authorize_manage(int $referral_id): array
    {
        $errors = [];

        if (! Capabilities::current_user_can(Capabilities::MANAGE_CARE_PLANS)) {
            $errors['permission'] = __('You do not have permission to manage care plans.', 'jm-referral-system');
            return $errors;
        }

        $referral = $this->referral_repository->find($referral_id);

        if ($referral_id <= 0 || null === $referral) {
            $errors['referral_id'] = __('Referral not found.', 'jm-referral-system');
            return $errors;
        }

        if (! $this->access_policy->can_edit_referral($referral)) {
            $errors['permission'] = __('You do not have permission to manage a care plan for this referral.', 'jm-referral-system');
        }

        return $errors;
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validate(int $referral_id, array $input): array
    {
        $errors = $this->authorize_manage($referral_id);

        if (! empty($errors)) {
            return $errors;
        }

        $status = (string) ($input['plan_status'] ?? '');
        if (! in_array($status, self::allowed_statuses(), true)) {
            $errors['plan_status'] = __('Please select a valid care plan status.', 'jm-referral-system');
        }

        $start_date = trim((string) ($input['start_date'] ?? ''));
        if (self::STATUS_ACTIVE === $status) {
            if ('' === $start_date) {
                $errors['start_date'] = __('Start date is required when the care plan is Active.', 'jm-referral-system');
            } elseif (null === $this->nullable_date($start_date)) {
                $errors['start_date'] = __('Please enter a valid start date.', 'jm-referral-system');
            }
        } elseif ('' !== $start_date && null === $this->nullable_date($start_date)) {
            $errors['start_date'] = __('Please enter a valid start date.', 'jm-referral-system');
        }

        $review_date = trim((string) ($input['review_date'] ?? ''));
        if ('' !== $review_date) {
            if (null === $this->nullable_date($review_date)) {
                $errors['review_date'] = __('Please enter a valid review date.', 'jm-referral-system');
            } elseif ('' !== $start_date && null !== $this->nullable_date($start_date) && $review_date < $start_date) {
                $errors['review_date'] = __('Review date cannot be earlier than the start date.', 'jm-referral-system');
            }
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
