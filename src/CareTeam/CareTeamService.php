<?php

namespace JMReferral\CareTeam;

use JMReferral\CarePlan\ReferralCarePlanRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;

class CareTeamService
{
    public const ROLE_PRIMARY_CARER = 'primary_carer';
    public const ROLE_SECONDARY_CARER = 'secondary_carer';
    public const ROLE_RELIEF_CARER = 'relief_carer';
    public const ROLE_NURSE = 'nurse';
    public const ROLE_ASSESSOR = 'assessor';
    public const ROLE_COORDINATOR = 'coordinator';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /**
     * @return array<int, string>
     */
    public static function allowed_roles(): array
    {
        return [
            self::ROLE_PRIMARY_CARER,
            self::ROLE_SECONDARY_CARER,
            self::ROLE_RELIEF_CARER,
            self::ROLE_NURSE,
            self::ROLE_ASSESSOR,
            self::ROLE_COORDINATOR,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function role_labels(): array
    {
        return [
            self::ROLE_PRIMARY_CARER   => __('Primary Carer', 'jm-referral-system'),
            self::ROLE_SECONDARY_CARER => __('Secondary Carer', 'jm-referral-system'),
            self::ROLE_RELIEF_CARER    => __('Relief Carer', 'jm-referral-system'),
            self::ROLE_NURSE           => __('Nurse', 'jm-referral-system'),
            self::ROLE_ASSESSOR        => __('Assessor', 'jm-referral-system'),
            self::ROLE_COORDINATOR     => __('Coordinator', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowed_statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function status_labels(): array
    {
        return [
            self::STATUS_ACTIVE   => __('Active', 'jm-referral-system'),
            self::STATUS_INACTIVE => __('Inactive', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function empty_form_data(): array
    {
        return [
            'user_id'           => '',
            'team_role'         => '',
            'is_primary'        => '0',
            'assignment_status' => self::STATUS_ACTIVE,
            'start_date'        => '',
            'end_date'          => '',
            'notes'             => '',
            'care_plan_id'      => '',
        ];
    }

    /**
     * @param array<string, mixed>|null $assignment
     * @return array<string, string>
     */
    public static function map_to_form_data(?array $assignment): array
    {
        $data = self::empty_form_data();

        if (null === $assignment) {
            return $data;
        }

        $data['user_id']           = (string) absint($assignment['user_id'] ?? 0);
        $data['team_role']         = (string) ($assignment['team_role'] ?? '');
        $data['is_primary']        = ! empty($assignment['is_primary']) ? '1' : '0';
        $data['assignment_status'] = (string) ($assignment['assignment_status'] ?? self::STATUS_ACTIVE);
        $data['start_date']        = (string) ($assignment['start_date'] ?? '');
        $data['end_date']          = (string) ($assignment['end_date'] ?? '');
        $data['notes']             = (string) ($assignment['notes'] ?? '');
        $data['care_plan_id']      = (string) absint($assignment['care_plan_id'] ?? 0);

        return $data;
    }

    public function __construct(
        private CareTeamRepository $care_team_repository,
        private ReferralRepository $referral_repository,
        private ReferralCarePlanRepository $care_plan_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider
    ) {
    }

    /**
     * @param array<string, string> $input
     * @return array{id: int, created: bool}|array{errors: array<string, string>}|false
     */
    public function save(int $referral_id, array $input, int $assignment_id = 0): array|false
    {
        $errors = $this->validate($referral_id, $input, $assignment_id);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $existing   = $assignment_id > 0 ? $this->care_team_repository->find($assignment_id) : null;
        $now        = current_time('mysql');
        $is_primary = ! empty($input['is_primary']) && '1' === (string) $input['is_primary'];
        $was_primary = is_array($existing) && ! empty($existing['is_primary']);

        $care_plan_id = absint($input['care_plan_id'] ?? 0);
        if ($care_plan_id <= 0) {
            $care_plan = $this->care_plan_repository->find_by_referral($referral_id);
            $care_plan_id = absint($care_plan['id'] ?? 0);
        }

        $payload = [
            'referral_id'       => $referral_id,
            'care_plan_id'      => $care_plan_id > 0 ? $care_plan_id : null,
            'user_id'           => absint($input['user_id'] ?? 0),
            'team_role'         => (string) ($input['team_role'] ?? ''),
            'is_primary'        => $is_primary ? 1 : 0,
            'assignment_status' => (string) ($input['assignment_status'] ?? self::STATUS_ACTIVE),
            'start_date'        => (string) ($input['start_date'] ?? ''),
            'end_date'          => $this->nullable_date((string) ($input['end_date'] ?? '')),
            'notes'             => $this->nullable_text((string) ($input['notes'] ?? '')),
            'updated_at'        => $now,
        ];

        $primary_changed = false;

        if ($is_primary) {
            $previous_primary = $this->care_team_repository->find_primary_by_referral(
                $referral_id,
                $assignment_id
            );

            if (null !== $previous_primary) {
                $this->care_team_repository->clear_primary_for_referral($referral_id, $assignment_id);
                $primary_changed = true;
            } elseif (! $was_primary) {
                $primary_changed = true;
            }
        }

        if (null === $existing) {
            $payload['assigned_by'] = get_current_user_id();
            $payload['created_at']  = $now;

            $id = $this->care_team_repository->create($payload);

            if (false === $id) {
                return false;
            }

            $this->activity_service->log_care_team_member_added($referral_id);

            if ($primary_changed || $is_primary) {
                $this->activity_service->log_care_team_primary_changed($referral_id);
            }

            return [
                'id'      => $id,
                'created' => true,
            ];
        }

        $updated = $this->care_team_repository->update($assignment_id, $payload);

        if (! $updated) {
            return false;
        }

        $this->activity_service->log_care_team_member_updated($referral_id);

        if ($is_primary && (! $was_primary || $primary_changed)) {
            $this->activity_service->log_care_team_primary_changed($referral_id);
        }

        return [
            'id'      => $assignment_id,
            'created' => false,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_members_for_referral(int $referral_id): array
    {
        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral || ! $this->can_view_care_team($referral)) {
            return [];
        }

        return $this->care_team_repository->get_by_referral($referral_id);
    }

    /**
     * Active care team members suitable for visit staff dropdowns.
     *
     * @return array<int, array{id: int, display_name: string}>
     */
    public function get_assignable_staff_for_referral(int $referral_id): array
    {
        $active = $this->care_team_repository->get_active_by_referral($referral_id);

        if (empty($active)) {
            return [];
        }

        $staff = [];
        $seen  = [];

        foreach ($active as $row) {
            $user_id = absint($row['user_id'] ?? 0);
            if ($user_id <= 0 || isset($seen[$user_id])) {
                continue;
            }

            $seen[$user_id] = true;
            $name           = $this->user_provider->get_display_name($user_id);

            if ('' === $name) {
                continue;
            }

            $staff[] = [
                'id'           => $user_id,
                'display_name' => $name,
            ];
        }

        usort(
            $staff,
            static function (array $a, array $b): int {
                return strcasecmp($a['display_name'], $b['display_name']);
            }
        );

        return $staff;
    }

    public function has_active_care_team(int $referral_id): bool
    {
        return ! empty($this->care_team_repository->get_active_by_referral($referral_id));
    }

    public function is_active_team_member(int $referral_id, int $user_id): bool
    {
        return $this->care_team_repository->is_active_member($referral_id, $user_id);
    }

    public function count_active_clients_for_user(int $user_id): int
    {
        return $this->care_team_repository->count_active_referrals_for_user($user_id);
    }

    /**
     * @return array{assignment: array<string, mixed>, referral: array<string, mixed>}|array{errors: array<string, string>}
     */
    public function prepare_edit(int $assignment_id): array
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_CARE_TEAM)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to manage the care team.', 'jm-referral-system'),
                ],
            ];
        }

        $assignment = $this->care_team_repository->find($assignment_id);
        if (null === $assignment) {
            return [
                'errors' => [
                    'assignment' => __('Care team assignment not found.', 'jm-referral-system'),
                ],
            ];
        }

        $referral = $this->referral_repository->find(absint($assignment['referral_id'] ?? 0));
        if (null === $referral || ! $this->access_policy->can_edit_referral($referral)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to edit this care team assignment.', 'jm-referral-system'),
                ],
            ];
        }

        return [
            'assignment' => $assignment,
            'referral'   => $referral,
        ];
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_view_care_team(array $referral): bool
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_CARE_TEAM)) {
            return false;
        }

        return $this->access_policy->can_view_referral($referral);
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_manage_care_team(array $referral): bool
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_CARE_TEAM)) {
            return false;
        }

        return $this->access_policy->can_edit_referral($referral);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validate(int $referral_id, array $input, int $assignment_id): array
    {
        $errors = [];

        if (! Capabilities::current_user_can(Capabilities::MANAGE_CARE_TEAM)) {
            $errors['permission'] = __('You do not have permission to manage the care team.', 'jm-referral-system');
            return $errors;
        }

        $referral = $this->referral_repository->find($referral_id);
        if ($referral_id <= 0 || null === $referral) {
            $errors['referral_id'] = __('Referral not found.', 'jm-referral-system');
            return $errors;
        }

        if (! $this->access_policy->can_edit_referral($referral)) {
            $errors['permission'] = __('You do not have permission to manage the care team for this referral.', 'jm-referral-system');
            return $errors;
        }

        if ($assignment_id > 0) {
            $existing = $this->care_team_repository->find($assignment_id);
            if (null === $existing || absint($existing['referral_id'] ?? 0) !== $referral_id) {
                $errors['assignment'] = __('Care team assignment not found.', 'jm-referral-system');
                return $errors;
            }
        }

        $user_id = absint($input['user_id'] ?? 0);
        if ($user_id <= 0) {
            $errors['user_id'] = __('Please select a staff member.', 'jm-referral-system');
        } elseif (! $this->user_provider->is_assignable($user_id)) {
            $errors['user_id'] = __('Please select a valid staff member who can view referrals.', 'jm-referral-system');
        }

        $team_role = (string) ($input['team_role'] ?? '');
        if (! in_array($team_role, self::allowed_roles(), true)) {
            $errors['team_role'] = __('Please select a valid team role.', 'jm-referral-system');
        }

        $status = (string) ($input['assignment_status'] ?? '');
        if (! in_array($status, self::allowed_statuses(), true)) {
            $errors['assignment_status'] = __('Please select a valid assignment status.', 'jm-referral-system');
        }

        $start_date = trim((string) ($input['start_date'] ?? ''));
        if ('' === $start_date) {
            $errors['start_date'] = __('Start date is required.', 'jm-referral-system');
        } elseif (null === $this->nullable_date($start_date)) {
            $errors['start_date'] = __('Please enter a valid start date.', 'jm-referral-system');
        }

        $end_date = trim((string) ($input['end_date'] ?? ''));
        if ('' !== $end_date) {
            if (null === $this->nullable_date($end_date)) {
                $errors['end_date'] = __('Please enter a valid end date.', 'jm-referral-system');
            } elseif ('' !== $start_date && null !== $this->nullable_date($start_date) && $end_date < $start_date) {
                $errors['end_date'] = __('End date cannot be earlier than the start date.', 'jm-referral-system');
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
