<?php

namespace JMReferral\CarePlan;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;

class ReferralCarePlanReviewService
{
    public const OUTCOME_NO_CHANGE = 'no_change';
    public const OUTCOME_UPDATED = 'updated';
    public const OUTCOME_CONTINUE = 'continue';
    public const OUTCOME_SUSPEND = 'suspend';
    public const OUTCOME_END_SERVICE = 'end_service';

    /**
     * Care-plan fields stored in version snapshots.
     *
     * @var array<int, string>
     */
    public const SNAPSHOT_FIELDS = [
        'referral_id',
        'assessment_id',
        'created_by',
        'approved_by',
        'plan_status',
        'start_date',
        'review_date',
        'visit_frequency',
        'visit_duration',
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
     * @return array<int, string>
     */
    public static function allowed_outcomes(): array
    {
        return [
            self::OUTCOME_NO_CHANGE,
            self::OUTCOME_UPDATED,
            self::OUTCOME_CONTINUE,
            self::OUTCOME_SUSPEND,
            self::OUTCOME_END_SERVICE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function outcome_labels(): array
    {
        return [
            self::OUTCOME_NO_CHANGE    => __('No Change Required', 'jm-referral-system'),
            self::OUTCOME_UPDATED      => __('Care Plan Updated', 'jm-referral-system'),
            self::OUTCOME_CONTINUE     => __('Continue Current Plan', 'jm-referral-system'),
            self::OUTCOME_SUSPEND      => __('Suspend Care', 'jm-referral-system'),
            self::OUTCOME_END_SERVICE  => __('End Service', 'jm-referral-system'),
        ];
    }

    public function __construct(
        private ReferralCarePlanReviewRepository $review_repository,
        private ReferralCarePlanVersionRepository $version_repository,
        private ReferralCarePlanRepository $care_plan_repository,
        private ReferralRepository $referral_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Creates a version snapshot of the current care plan before an update when data changed.
     *
     * @param array<string, mixed> $existing Current care plan row.
     * @param array<string, mixed> $new_payload Incoming update payload (repository map shape).
     * @return int|null New version number when created, null when skipped.
     */
    public function create_version_if_changed(array $existing, array $new_payload, string $change_summary, int $referral_id): ?int
    {
        if (! $this->has_care_plan_data_changed($existing, $new_payload)) {
            return null;
        }

        $care_plan_id = absint($existing['id'] ?? 0);
        if ($care_plan_id <= 0) {
            return null;
        }

        $version_number = $this->version_repository->get_latest_version_number($care_plan_id) + 1;
        $snapshot       = $this->build_snapshot_json($existing);
        $summary        = trim($change_summary);

        $id = $this->version_repository->create(
            [
                'care_plan_id'   => $care_plan_id,
                'version_number' => $version_number,
                'snapshot'       => $snapshot,
                'created_by'     => get_current_user_id(),
                'change_summary' => '' !== $summary ? $summary : null,
                'created_at'     => current_time('mysql'),
            ]
        );

        if (false === $id) {
            return null;
        }

        $this->activity_service->log_care_plan_version_created($referral_id, $version_number);

        return $version_number;
    }

    /**
     * @param array<string, string> $input
     * @return array{id: int}|array{errors: array<string, string>}|false
     */
    public function add_review(int $referral_id, array $input): array|false
    {
        $errors = $this->validate_review($referral_id, $input);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $care_plan = $this->care_plan_repository->find_by_referral($referral_id);
        if (null === $care_plan) {
            return [
                'errors' => [
                    'general' => __('A care plan is required before adding a review.', 'jm-referral-system'),
                ],
            ];
        }

        $outcome          = (string) ($input['outcome'] ?? '');
        $review_date      = (string) ($input['review_date'] ?? '');
        $next_review_date = $this->nullable_date((string) ($input['next_review_date'] ?? ''));
        $notes            = trim((string) ($input['notes'] ?? ''));
        $now              = current_time('mysql');

        $review_id = $this->review_repository->create(
            [
                'care_plan_id'     => absint($care_plan['id'] ?? 0),
                'reviewed_by'      => get_current_user_id(),
                'review_date'      => $review_date,
                'outcome'          => $outcome,
                'notes'            => '' !== $notes ? $notes : null,
                'next_review_date' => $next_review_date,
                'created_at'       => $now,
            ]
        );

        if (false === $review_id) {
            return false;
        }

        $this->apply_review_side_effects($care_plan, $outcome, $next_review_date, $now);
        $this->activity_service->log_care_plan_reviewed($referral_id);

        return ['id' => $review_id];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_reviews_for_referral(int $referral_id): array
    {
        if (! $this->can_view_care_plan_history($referral_id)) {
            return [];
        }

        $care_plan = $this->care_plan_repository->find_by_referral($referral_id);
        if (null === $care_plan) {
            return [];
        }

        return $this->review_repository->get_by_care_plan(absint($care_plan['id'] ?? 0));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_versions_for_referral(int $referral_id): array
    {
        if (! $this->can_view_care_plan_history($referral_id)) {
            return [];
        }

        $care_plan = $this->care_plan_repository->find_by_referral($referral_id);
        if (null === $care_plan) {
            return [];
        }

        return $this->version_repository->get_by_care_plan(absint($care_plan['id'] ?? 0));
    }

    /**
     * Loads a version snapshot for display when the user may view the related referral.
     *
     * @return array{version: array<string, mixed>, care_plan: array<string, mixed>, referral: array<string, mixed>, snapshot: array<string, mixed>}|array{errors: array<string, string>}
     */
    public function prepare_version_view(int $version_id): array
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_CARE_PLANS)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to view care plans.', 'jm-referral-system'),
                ],
            ];
        }

        $version = $this->version_repository->find($version_id);
        if (null === $version) {
            return [
                'errors' => [
                    'version' => __('Care plan version not found.', 'jm-referral-system'),
                ],
            ];
        }

        $care_plan = $this->care_plan_repository->find(absint($version['care_plan_id'] ?? 0));
        if (null === $care_plan) {
            return [
                'errors' => [
                    'care_plan' => __('Care plan not found.', 'jm-referral-system'),
                ],
            ];
        }

        $referral = $this->referral_repository->find(absint($care_plan['referral_id'] ?? 0));
        if (null === $referral || ! $this->access_policy->can_view_referral($referral)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to view this care plan version.', 'jm-referral-system'),
                ],
            ];
        }

        $decoded = json_decode((string) ($version['snapshot'] ?? ''), true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $snapshot = [];
        foreach (self::SNAPSHOT_FIELDS as $field) {
            $snapshot[$field] = $decoded[$field] ?? null;
        }

        return [
            'version'   => $version,
            'care_plan' => $care_plan,
            'referral'  => $referral,
            'snapshot'  => $snapshot,
        ];
    }

    public function can_view_care_plan_history(int $referral_id): bool
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_CARE_PLANS)) {
            return false;
        }

        $referral = $this->referral_repository->find($referral_id);

        return null !== $referral && $this->access_policy->can_view_referral($referral);
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $new_payload
     */
    private function has_care_plan_data_changed(array $existing, array $new_payload): bool
    {
        foreach (self::SNAPSHOT_FIELDS as $field) {
            if (in_array($field, ['referral_id', 'created_by'], true)) {
                continue;
            }

            $old = $this->normalize_compare_value($existing[$field] ?? null);
            $new = array_key_exists($field, $new_payload)
                ? $this->normalize_compare_value($new_payload[$field])
                : $old;

            if ($old !== $new) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $care_plan
     */
    private function build_snapshot_json(array $care_plan): string
    {
        $snapshot = [];
        foreach (self::SNAPSHOT_FIELDS as $field) {
            $snapshot[$field] = $care_plan[$field] ?? null;
        }

        $json = wp_json_encode($snapshot);

        return is_string($json) ? $json : '{}';
    }

    /**
     * @param array<string, mixed> $care_plan
     */
    private function apply_review_side_effects(array $care_plan, string $outcome, ?string $next_review_date, string $now): void
    {
        $payload = [
            'assessment_id'           => $care_plan['assessment_id'] ?? null,
            'approved_by'             => array_key_exists('approved_by', $care_plan) ? $care_plan['approved_by'] : null,
            'plan_status'             => (string) ($care_plan['plan_status'] ?? ReferralCarePlanService::STATUS_DRAFT),
            'start_date'              => $care_plan['start_date'] ?? null,
            'review_date'             => $next_review_date ?? ($care_plan['review_date'] ?? null),
            'updated_at'              => $now,
        ];

        foreach (array_merge(ReferralCarePlanService::LONGTEXT_FIELDS, ReferralCarePlanService::SHORTTEXT_FIELDS) as $field) {
            $payload[$field] = $care_plan[$field] ?? null;
        }

        if (self::OUTCOME_SUSPEND === $outcome) {
            $payload['plan_status'] = ReferralCarePlanService::STATUS_UNDER_REVIEW;
        } elseif (self::OUTCOME_END_SERVICE === $outcome) {
            $payload['plan_status'] = ReferralCarePlanService::STATUS_COMPLETED;
        }

        if (null !== $next_review_date) {
            $payload['review_date'] = $next_review_date;
        }

        $this->care_plan_repository->update(absint($care_plan['id'] ?? 0), $payload);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validate_review(int $referral_id, array $input): array
    {
        $errors = [];

        if (! Capabilities::current_user_can(Capabilities::REVIEW_CARE_PLANS)) {
            $errors['permission'] = __('You do not have permission to review care plans.', 'jm-referral-system');
            return $errors;
        }

        $referral = $this->referral_repository->find($referral_id);
        if ($referral_id <= 0 || null === $referral) {
            $errors['referral_id'] = __('Referral not found.', 'jm-referral-system');
            return $errors;
        }

        if (! $this->access_policy->can_edit_referral($referral)) {
            $errors['permission'] = __('You do not have permission to review this care plan.', 'jm-referral-system');
            return $errors;
        }

        $review_date = trim((string) ($input['review_date'] ?? ''));
        if ('' === $review_date) {
            $errors['review_date'] = __('Review date is required.', 'jm-referral-system');
        } elseif (null === $this->nullable_date($review_date)) {
            $errors['review_date'] = __('Please enter a valid review date.', 'jm-referral-system');
        }

        $outcome = (string) ($input['outcome'] ?? '');
        if (! in_array($outcome, self::allowed_outcomes(), true)) {
            $errors['outcome'] = __('Please select a valid review outcome.', 'jm-referral-system');
        }

        $next_review_date = trim((string) ($input['next_review_date'] ?? ''));
        if ('' !== $next_review_date) {
            if (null === $this->nullable_date($next_review_date)) {
                $errors['next_review_date'] = __('Please enter a valid next review date.', 'jm-referral-system');
            } elseif ('' !== $review_date && null !== $this->nullable_date($review_date) && $next_review_date < $review_date) {
                $errors['next_review_date'] = __('Next review date cannot be earlier than the review date.', 'jm-referral-system');
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

    private function normalize_compare_value(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        return trim((string) $value);
    }
}
