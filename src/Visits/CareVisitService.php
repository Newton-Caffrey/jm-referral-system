<?php

namespace JMReferral\Visits;

use JMReferral\CarePlan\ReferralCarePlanRepository;
use JMReferral\CareTeam\CareTeamService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;

class CareVisitService
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_MISSED = 'missed';

    /**
     * @return array<int, string>
     */
    public static function allowed_statuses(): array
    {
        return [
            self::STATUS_SCHEDULED,
            self::STATUS_CONFIRMED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_MISSED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function status_labels(): array
    {
        return [
            self::STATUS_SCHEDULED   => __('Scheduled', 'jm-referral-system'),
            self::STATUS_CONFIRMED   => __('Confirmed', 'jm-referral-system'),
            self::STATUS_IN_PROGRESS => __('In Progress', 'jm-referral-system'),
            self::STATUS_COMPLETED   => __('Completed', 'jm-referral-system'),
            self::STATUS_CANCELLED   => __('Cancelled', 'jm-referral-system'),
            self::STATUS_MISSED      => __('Missed', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function empty_form_data(): array
    {
        return [
            'visit_date'       => '',
            'start_time'       => '',
            'end_time'         => '',
            'assigned_user_id' => '',
            'visit_type'       => '',
            'visit_status'     => self::STATUS_SCHEDULED,
            'tasks'            => '',
            'notes'            => '',
            'care_plan_id'     => '',
        ];
    }

    /**
     * @param array<string, mixed>|null $visit
     * @return array<string, string>
     */
    public static function map_to_form_data(?array $visit): array
    {
        $data = self::empty_form_data();

        if (null === $visit) {
            return $data;
        }

        $data['visit_date']       = (string) ($visit['visit_date'] ?? '');
        $data['start_time']       = self::normalize_time_for_input((string) ($visit['start_time'] ?? ''));
        $data['end_time']         = self::normalize_time_for_input((string) ($visit['end_time'] ?? ''));
        $data['assigned_user_id'] = (string) absint($visit['assigned_user_id'] ?? 0);
        $data['visit_type']       = (string) ($visit['visit_type'] ?? '');
        $data['visit_status']     = (string) ($visit['visit_status'] ?? self::STATUS_SCHEDULED);
        $data['tasks']            = (string) ($visit['tasks'] ?? '');
        $data['notes']            = (string) ($visit['notes'] ?? '');
        $data['care_plan_id']     = (string) absint($visit['care_plan_id'] ?? 0);

        return $data;
    }

    public function __construct(
        private CareVisitRepository $visit_repository,
        private ReferralRepository $referral_repository,
        private ReferralCarePlanRepository $care_plan_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider,
        private CareTeamService $care_team_service
    ) {
    }

    /**
     * @param array<string, string> $input
     * @return array{id: int, created: bool}|array{errors: array<string, string>}|false
     */
    public function save(int $referral_id, array $input, int $visit_id = 0): array|false
    {
        $errors = $this->validate($referral_id, $input, $visit_id);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $now        = current_time('mysql');
        $new_status = (string) ($input['visit_status'] ?? self::STATUS_SCHEDULED);
        $existing   = $visit_id > 0 ? $this->visit_repository->find($visit_id) : null;
        $old_status = is_array($existing) ? (string) ($existing['visit_status'] ?? '') : '';

        $completed_at = null;
        if (self::STATUS_COMPLETED === $new_status) {
            if (is_array($existing) && self::STATUS_COMPLETED === $old_status) {
                $completed_at = $existing['completed_at'] ?? $now;
            } else {
                $completed_at = $now;
            }
        }

        $care_plan_id = absint($input['care_plan_id'] ?? 0);
        if ($care_plan_id <= 0) {
            $care_plan = $this->care_plan_repository->find_by_referral($referral_id);
            $care_plan_id = absint($care_plan['id'] ?? 0);
        }

        $assigned_user_id = absint($input['assigned_user_id'] ?? 0);

        $payload = [
            'referral_id'      => $referral_id,
            'care_plan_id'     => $care_plan_id > 0 ? $care_plan_id : null,
            'assigned_user_id' => $assigned_user_id > 0 ? $assigned_user_id : null,
            'visit_date'       => (string) ($input['visit_date'] ?? ''),
            'start_time'       => $this->normalize_time_for_storage((string) ($input['start_time'] ?? '')),
            'end_time'         => $this->normalize_time_for_storage((string) ($input['end_time'] ?? '')),
            'visit_status'     => $new_status,
            'visit_type'       => $this->nullable_text((string) ($input['visit_type'] ?? '')),
            'tasks'            => $this->nullable_text((string) ($input['tasks'] ?? '')),
            'notes'            => $this->nullable_text((string) ($input['notes'] ?? '')),
            'completed_at'     => $completed_at,
            'updated_at'       => $now,
        ];

        if (null === $existing) {
            $payload['created_by'] = get_current_user_id();
            $payload['created_at'] = $now;

            $id = $this->visit_repository->create($payload);

            if (false === $id) {
                return false;
            }

            $this->activity_service->log_visit_created($referral_id);

            if (self::STATUS_COMPLETED === $new_status) {
                $this->activity_service->log_visit_completed($referral_id);
            }

            return [
                'id'      => $id,
                'created' => true,
            ];
        }

        $updated = $this->visit_repository->update($visit_id, $payload);

        if (! $updated) {
            return false;
        }

        $this->activity_service->log_visit_updated($referral_id);

        if (self::STATUS_COMPLETED === $new_status && self::STATUS_COMPLETED !== $old_status) {
            $this->activity_service->log_visit_completed($referral_id);
        }

        return [
            'id'      => $visit_id,
            'created' => false,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_visits_for_referral(int $referral_id): array
    {
        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral || ! $this->can_view_visits_for_referral($referral)) {
            return [];
        }

        $assigned_filter = $this->visit_assigned_user_filter();

        return $this->visit_repository->get_by_referral($referral_id, $assigned_filter);
    }

    /**
     * Upcoming visits for the dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_upcoming_for_dashboard(int $limit = 10): array
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_VISITS)) {
            return [];
        }

        if ($this->is_support_worker_scoped()) {
            return $this->visit_repository->get_upcoming_by_user(get_current_user_id(), $limit);
        }

        return $this->visit_repository->get_upcoming($limit);
    }

    /**
     * Loads a visit for editing when the user may manage it.
     *
     * @return array{visit: array<string, mixed>, referral: array<string, mixed>}|array{errors: array<string, string>}
     */
    public function prepare_edit(int $visit_id): array
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_VISITS)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to manage visits.', 'jm-referral-system'),
                ],
            ];
        }

        $visit = $this->visit_repository->find($visit_id);
        if (null === $visit) {
            return [
                'errors' => [
                    'visit' => __('Care visit not found.', 'jm-referral-system'),
                ],
            ];
        }

        $referral = $this->referral_repository->find(absint($visit['referral_id'] ?? 0));
        if (null === $referral || ! $this->access_policy->can_edit_referral($referral)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to edit this visit.', 'jm-referral-system'),
                ],
            ];
        }

        if (! $this->can_view_visit($visit, $referral)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to view this visit.', 'jm-referral-system'),
                ],
            ];
        }

        return [
            'visit'    => $visit,
            'referral' => $referral,
        ];
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_view_visits_for_referral(array $referral): bool
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_VISITS)) {
            return false;
        }

        return $this->access_policy->can_view_referral($referral);
    }

    /**
     * @param array<string, mixed> $visit
     * @param array<string, mixed> $referral
     */
    public function can_view_visit(array $visit, array $referral): bool
    {
        if (! $this->can_view_visits_for_referral($referral)) {
            return false;
        }

        if (! $this->is_support_worker_scoped()) {
            return true;
        }

        return absint($visit['assigned_user_id'] ?? 0) === get_current_user_id();
    }

    public function can_manage_visits_for_referral(array $referral): bool
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_VISITS)) {
            return false;
        }

        return $this->access_policy->can_edit_referral($referral);
    }

    /**
     * Staff options for visit assignment: active care team when present, otherwise all assignable users.
     *
     * @return array<int, array{id: int, display_name: string}>
     */
    public function get_assignable_staff_for_referral(int $referral_id): array
    {
        $team_staff = $this->care_team_service->get_assignable_staff_for_referral($referral_id);

        if (! empty($team_staff)) {
            return $team_staff;
        }

        return $this->user_provider->get_assignable_users();
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validate(int $referral_id, array $input, int $visit_id): array
    {
        $errors = [];

        if (! Capabilities::current_user_can(Capabilities::MANAGE_VISITS)) {
            $errors['permission'] = __('You do not have permission to manage visits.', 'jm-referral-system');
            return $errors;
        }

        $referral = $this->referral_repository->find($referral_id);
        if ($referral_id <= 0 || null === $referral) {
            $errors['referral_id'] = __('Referral not found.', 'jm-referral-system');
            return $errors;
        }

        if (! $this->access_policy->can_edit_referral($referral)) {
            $errors['permission'] = __('You do not have permission to manage visits for this referral.', 'jm-referral-system');
            return $errors;
        }

        if ($visit_id > 0) {
            $existing = $this->visit_repository->find($visit_id);
            if (null === $existing || absint($existing['referral_id'] ?? 0) !== $referral_id) {
                $errors['visit'] = __('Care visit not found.', 'jm-referral-system');
                return $errors;
            }
        }

        $visit_date = trim((string) ($input['visit_date'] ?? ''));
        if ('' === $visit_date) {
            $errors['visit_date'] = __('Visit date is required.', 'jm-referral-system');
        } elseif (null === $this->nullable_date($visit_date)) {
            $errors['visit_date'] = __('Please enter a valid visit date.', 'jm-referral-system');
        }

        $start_time = trim((string) ($input['start_time'] ?? ''));
        $end_time   = trim((string) ($input['end_time'] ?? ''));

        if ('' === $start_time) {
            $errors['start_time'] = __('Start time is required.', 'jm-referral-system');
        } elseif (null === $this->nullable_time($start_time)) {
            $errors['start_time'] = __('Please enter a valid start time.', 'jm-referral-system');
        }

        if ('' === $end_time) {
            $errors['end_time'] = __('End time is required.', 'jm-referral-system');
        } elseif (null === $this->nullable_time($end_time)) {
            $errors['end_time'] = __('Please enter a valid end time.', 'jm-referral-system');
        }

        if (
            ! isset($errors['start_time'])
            && ! isset($errors['end_time'])
            && null !== $this->nullable_time($start_time)
            && null !== $this->nullable_time($end_time)
            && $this->normalize_time_for_storage($start_time) >= $this->normalize_time_for_storage($end_time)
        ) {
            $errors['end_time'] = __('End time must be later than start time.', 'jm-referral-system');
        }

        $status = (string) ($input['visit_status'] ?? '');
        if (! in_array($status, self::allowed_statuses(), true)) {
            $errors['visit_status'] = __('Please select a valid visit status.', 'jm-referral-system');
        }

        $assigned_user_id = absint($input['assigned_user_id'] ?? 0);
        if ($assigned_user_id > 0) {
            if ($this->care_team_service->has_active_care_team($referral_id)) {
                if (! $this->care_team_service->is_active_team_member($referral_id, $assigned_user_id)) {
                    $errors['assigned_user_id'] = __('Please select an active care team member for this referral.', 'jm-referral-system');
                }
            } elseif (! $this->user_provider->is_assignable($assigned_user_id)) {
                $errors['assigned_user_id'] = __('Please select a valid staff member who can view referrals.', 'jm-referral-system');
            }
        }

        $care_plan_id = absint($input['care_plan_id'] ?? 0);
        if ($care_plan_id > 0) {
            $care_plan = $this->care_plan_repository->find($care_plan_id);
            if (null === $care_plan || absint($care_plan['referral_id'] ?? 0) !== $referral_id) {
                $errors['care_plan_id'] = __('The selected care plan does not belong to this referral.', 'jm-referral-system');
            }
        }

        return $errors;
    }

    /**
     * Support Workers only see visits assigned to them.
     */
    private function visit_assigned_user_filter(): ?int
    {
        if (! $this->is_support_worker_scoped()) {
            return null;
        }

        $user_id = get_current_user_id();

        return $user_id > 0 ? $user_id : null;
    }

    private function is_support_worker_scoped(): bool
    {
        return $this->access_policy->should_scope_to_assigned();
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

    private function nullable_time(string $value): ?string
    {
        $value = trim($value);

        if ('' === $value) {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) !== 1) {
            return null;
        }

        $normalized = $this->normalize_time_for_storage($value);
        $parts      = explode(':', $normalized);

        if (3 !== count($parts)) {
            return null;
        }

        $hour   = (int) $parts[0];
        $minute = (int) $parts[1];
        $second = (int) $parts[2];

        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return $normalized;
    }

    private function normalize_time_for_storage(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return $value;
    }

    private static function normalize_time_for_input(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return substr($value, 0, 5);
        }

        return $value;
    }

    private function nullable_text(string $value): ?string
    {
        $value = trim($value);

        return '' !== $value ? $value : null;
    }
}
