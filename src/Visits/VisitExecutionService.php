<?php

namespace JMReferral\Visits;

use DateTimeImmutable;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;

class VisitExecutionService
{
    public const OUTCOME_COMPLETED = 'completed';
    public const OUTCOME_PARTIALLY_COMPLETED = 'partially_completed';
    public const OUTCOME_CLIENT_ABSENT = 'client_absent';
    public const OUTCOME_REFUSED_SERVICE = 'refused_service';
    public const OUTCOME_CANCELLED_ON_ARRIVAL = 'cancelled_on_arrival';
    public const OUTCOME_EMERGENCY = 'emergency';

    /**
     * @return array<int, string>
     */
    public static function allowed_outcomes(): array
    {
        return [
            self::OUTCOME_COMPLETED,
            self::OUTCOME_PARTIALLY_COMPLETED,
            self::OUTCOME_CLIENT_ABSENT,
            self::OUTCOME_REFUSED_SERVICE,
            self::OUTCOME_CANCELLED_ON_ARRIVAL,
            self::OUTCOME_EMERGENCY,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function outcome_labels(): array
    {
        return [
            self::OUTCOME_COMPLETED            => __('Completed', 'jm-referral-system'),
            self::OUTCOME_PARTIALLY_COMPLETED  => __('Partially Completed', 'jm-referral-system'),
            self::OUTCOME_CLIENT_ABSENT        => __('Client Absent', 'jm-referral-system'),
            self::OUTCOME_REFUSED_SERVICE      => __('Refused Service', 'jm-referral-system'),
            self::OUTCOME_CANCELLED_ON_ARRIVAL => __('Cancelled on Arrival', 'jm-referral-system'),
            self::OUTCOME_EMERGENCY            => __('Emergency', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function empty_execution_form_data(): array
    {
        return [
            'arrival_time'           => '',
            'departure_time'         => '',
            'visit_outcome'          => '',
            'tasks_completed'        => '',
            'tasks_not_completed'    => '',
            'client_response'        => '',
            'wellbeing_observations' => '',
            'incident_report'        => '',
            'manager_review_notes'   => '',
        ];
    }

    public function __construct(
        private CareVisitRepository $visit_repository,
        private ReferralRepository $referral_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * @param array<string, string> $input
     * @return array{id: int}|array{errors: array<string, string>}|false
     */
    public function execute(int $referral_id, int $visit_id, array $input): array|false
    {
        $visit = $this->visit_repository->find($visit_id);
        $errors = $this->validate_execution($referral_id, $visit, $input);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        /** @var array<string, mixed> $visit */
        $arrival_raw   = isset($input['arrival_time']) ? (string) $input['arrival_time'] : '';
        $departure_raw = isset($input['departure_time']) ? (string) $input['departure_time'] : '';

        $arrival_dt   = $this->parse_datetime($arrival_raw);
        $departure_dt = $this->parse_datetime($departure_raw);
        if (null === $arrival_dt || null === $departure_dt) {
            return [
                'errors' => [
                    'general' => __('Unable to process arrival or departure time.', 'jm-referral-system'),
                ],
            ];
        }

        $arrival   = $arrival_dt->format('Y-m-d H:i:s');
        $departure = $departure_dt->format('Y-m-d H:i:s');
        $duration  = $this->calculate_duration_minutes($arrival, $departure);

        if (null === $duration || $duration <= 0) {
            return [
                'errors' => [
                    'departure_time' => __('Departure time must be later than arrival time.', 'jm-referral-system'),
                ],
            ];
        }

        $now          = current_time('mysql');
        $completed_at = trim((string) ($visit['completed_at'] ?? ''));
        if ('' === $completed_at) {
            $completed_at = $now;
        }

        $updated = $this->visit_repository->update(
            $visit_id,
            [
                'arrival_time'             => $arrival,
                'departure_time'           => $departure,
                'actual_duration_minutes'  => $duration,
                'visit_outcome'            => (string) ($input['visit_outcome'] ?? ''),
                'tasks_completed'          => $this->nullable_text((string) ($input['tasks_completed'] ?? '')),
                'tasks_not_completed'      => $this->nullable_text((string) ($input['tasks_not_completed'] ?? '')),
                'client_response'          => $this->nullable_text((string) ($input['client_response'] ?? '')),
                'wellbeing_observations'   => $this->nullable_text((string) ($input['wellbeing_observations'] ?? '')),
                'incident_report'          => $this->nullable_text((string) ($input['incident_report'] ?? '')),
                'visit_status'             => CareVisitService::STATUS_COMPLETED,
                'completed_at'             => $completed_at,
                'updated_at'               => $now,
            ]
        );

        if (! $updated) {
            return false;
        }

        $this->activity_service->log_visit_executed($referral_id);

        return ['id' => $visit_id];
    }

    /**
     * @param array<string, string> $input
     * @return array{id: int}|array{errors: array<string, string>}|false
     */
    public function review(int $referral_id, int $visit_id, array $input): array|false
    {
        $visit = $this->visit_repository->find($visit_id);
        $errors = $this->validate_review($referral_id, $visit, $input);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $now = current_time('mysql');

        $updated = $this->visit_repository->update(
            $visit_id,
            [
                'manager_review_notes' => $this->nullable_text((string) ($input['manager_review_notes'] ?? '')),
                'reviewed_by'          => get_current_user_id(),
                'reviewed_at'          => $now,
                'updated_at'           => $now,
            ]
        );

        if (! $updated) {
            return false;
        }

        $this->activity_service->log_visit_reviewed($referral_id);

        return ['id' => $visit_id];
    }

    /**
     * @param array<string, mixed> $referral
     * @param array<string, mixed> $visit
     */
    public function can_execute_visit(array $referral, array $visit): bool
    {
        if (! Capabilities::current_user_can(Capabilities::EXECUTE_VISITS)) {
            return false;
        }

        if (! $this->access_policy->can_view_referral($referral)) {
            return false;
        }

        if ($this->access_policy->should_scope_to_assigned()) {
            return absint($visit['assigned_user_id'] ?? 0) === get_current_user_id();
        }

        return true;
    }

    /**
     * Managers (users who can manage visits) may review executed visits.
     *
     * @param array<string, mixed> $referral
     */
    public function can_review_visit(array $referral): bool
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_VISITS)) {
            return false;
        }

        if ($this->access_policy->should_scope_to_assigned()) {
            return false;
        }

        return $this->access_policy->can_edit_referral($referral);
    }

    /**
     * @param array<string, mixed> $visit
     */
    public function is_executed(array $visit): bool
    {
        return '' !== trim((string) ($visit['visit_outcome'] ?? ''));
    }

    /**
     * @param array<string, mixed> $visit
     */
    public function is_reviewed(array $visit): bool
    {
        return '' !== trim((string) ($visit['reviewed_at'] ?? ''));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_awaiting_review_for_dashboard(int $limit = 10): array
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_VISITS)) {
            return [];
        }

        if ($this->access_policy->should_scope_to_assigned()) {
            return [];
        }

        return $this->visit_repository->get_awaiting_review($limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_todays_completed_for_current_user(int $limit = 10): array
    {
        if (! Capabilities::current_user_can(Capabilities::EXECUTE_VISITS)) {
            return [];
        }

        if (! $this->access_policy->should_scope_to_assigned()) {
            return [];
        }

        return $this->visit_repository->get_completed_today_by_user(get_current_user_id(), $limit);
    }

    /**
     * @param array<string, mixed>|null $visit
     * @param array<string, string>     $input
     * @return array<string, string>
     */
    private function validate_execution(int $referral_id, ?array $visit, array $input): array
    {
        $errors = [];

        if (! Capabilities::current_user_can(Capabilities::EXECUTE_VISITS)) {
            $errors['permission'] = __('You do not have permission to execute visits.', 'jm-referral-system');
            return $errors;
        }

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral || null === $visit || absint($visit['referral_id'] ?? 0) !== $referral_id) {
            $errors['visit'] = __('Care visit not found.', 'jm-referral-system');
            return $errors;
        }

        if (! $this->can_execute_visit($referral, $visit)) {
            $errors['permission'] = __('You do not have permission to execute this visit.', 'jm-referral-system');
            return $errors;
        }

        if ($this->is_executed($visit)) {
            $errors['visit'] = __('This visit has already been executed.', 'jm-referral-system');
            return $errors;
        }

        $arrival = isset($input['arrival_time']) ? trim((string) $input['arrival_time']) : '';
        if ('' === $arrival) {
            $errors['arrival_time'] = __('Arrival time is required.', 'jm-referral-system');
        } elseif (null === $this->parse_datetime($arrival)) {
            $errors['arrival_time'] = __('Please enter a valid arrival time.', 'jm-referral-system');
        }

        $departure = isset($input['departure_time']) ? trim((string) $input['departure_time']) : '';
        if ('' === $departure) {
            $errors['departure_time'] = __('Departure time is required.', 'jm-referral-system');
        } elseif (null === $this->parse_datetime($departure)) {
            $errors['departure_time'] = __('Please enter a valid departure time.', 'jm-referral-system');
        }

        if (! isset($errors['arrival_time']) && ! isset($errors['departure_time'])) {
            $duration = $this->calculate_duration_minutes($arrival, $departure);
            if (null === $duration) {
                $errors['departure_time'] = __('Departure time must be later than arrival time.', 'jm-referral-system');
            } elseif ($duration <= 0) {
                $errors['departure_time'] = __('Visit duration must be greater than zero.', 'jm-referral-system');
            }
        }

        $outcome = sanitize_key((string) ($input['visit_outcome'] ?? ''));
        if ('' === $outcome) {
            $errors['visit_outcome'] = __('Visit outcome is required.', 'jm-referral-system');
        } elseif (! in_array($outcome, self::allowed_outcomes(), true)) {
            $errors['visit_outcome'] = __('Please select a valid visit outcome.', 'jm-referral-system');
        }

        return $errors;
    }

    /**
     * @param array<string, mixed>|null $visit
     * @param array<string, string>     $input
     * @return array<string, string>
     */
    private function validate_review(int $referral_id, ?array $visit, array $input): array
    {
        $errors = [];

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral || null === $visit || absint($visit['referral_id'] ?? 0) !== $referral_id) {
            $errors['visit'] = __('Care visit not found.', 'jm-referral-system');
            return $errors;
        }

        if (! $this->can_review_visit($referral)) {
            $errors['permission'] = __('You do not have permission to review visits.', 'jm-referral-system');
            return $errors;
        }

        if (! $this->is_executed($visit)) {
            $errors['visit'] = __('Only executed visits can be reviewed.', 'jm-referral-system');
            return $errors;
        }

        if ($this->is_reviewed($visit)) {
            $errors['visit'] = __('This visit has already been reviewed.', 'jm-referral-system');
            return $errors;
        }

        $notes = trim((string) ($input['manager_review_notes'] ?? ''));
        if ('' === $notes) {
            $errors['manager_review_notes'] = __('Manager review notes are required.', 'jm-referral-system');
        }

        return $errors;
    }

    private function calculate_duration_minutes(?string $arrival, ?string $departure): ?int
    {
        $start = $this->parse_datetime($arrival);
        $end   = $this->parse_datetime($departure);

        if (null === $start || null === $end) {
            return null;
        }

        if ($end <= $start) {
            return null;
        }

        $seconds = $end->getTimestamp() - $start->getTimestamp();

        return (int) floor($seconds / 60);
    }

    private function parse_datetime(?string $value): ?DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $value = trim(str_replace('T', ' ', $value));
        if ('' === $value) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
            $value .= ':00';
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        if (false === $dt || $dt->format('Y-m-d H:i:s') !== $value) {
            return null;
        }

        return $dt;
    }

    private function nullable_text(string $value): ?string
    {
        $value = trim($value);

        return '' !== $value ? $value : null;
    }
}
