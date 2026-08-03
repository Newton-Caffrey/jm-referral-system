<?php

namespace JMReferral\Assessment;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
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
        private AccessPolicy $access_policy
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
     * @param array<string, string> $input Sanitized form data.
     * @return array{id: int, created: bool}|array{errors: array<string, string>}|false
     */
    public function save(int $referral_id, array $input): array|false
    {
        $errors = $this->validate($referral_id, $input);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $existing = $this->assessment_repository->find_by_referral($referral_id);
        $now      = current_time('mysql');
        $payload  = $this->build_payload($input, $now);

        if (null === $existing) {
            $payload['referral_id']      = $referral_id;
            $payload['assessor_user_id'] = get_current_user_id();
            $payload['created_at']       = $now;

            $id = $this->assessment_repository->create($payload);

            if (false === $id) {
                return false;
            }

            $this->activity_service->log_assessment_created($referral_id);

            return [
                'id'      => $id,
                'created' => true,
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

        return [
            'id'      => absint($existing['id'] ?? 0),
            'created' => false,
        ];
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

        if (! $this->access_policy->can_edit_referral($referral)) {
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
