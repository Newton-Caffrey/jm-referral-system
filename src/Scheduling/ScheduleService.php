<?php

namespace JMReferral\Scheduling;

use JMReferral\CarePlan\ReferralCarePlanRepository;
use JMReferral\CareTeam\CareTeamRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;

class ScheduleService
{
    public const REPEAT_DAILY = 'daily';
    public const REPEAT_WEEKLY = 'weekly';
    public const REPEAT_MONTHLY = 'monthly';
    public const REPEAT_CUSTOM = 'custom';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';

    /**
     * Weekday keys stored in days_of_week JSON (lowercase English names).
     *
     * @return array<string, string>
     */
    public static function weekday_labels(): array
    {
        return [
            'monday'    => __('Monday', 'jm-referral-system'),
            'tuesday'   => __('Tuesday', 'jm-referral-system'),
            'wednesday' => __('Wednesday', 'jm-referral-system'),
            'thursday'  => __('Thursday', 'jm-referral-system'),
            'friday'    => __('Friday', 'jm-referral-system'),
            'saturday'  => __('Saturday', 'jm-referral-system'),
            'sunday'    => __('Sunday', 'jm-referral-system'),
        ];
    }

    /**
     * Allowed lowercase weekday keys.
     *
     * @return array<int, string>
     */
    public static function allowed_weekday_keys(): array
    {
        return array_keys(self::weekday_labels());
    }

    /**
     * Map ISO-8601 weekday numbers (1=Monday … 7=Sunday) to storage keys.
     *
     * @return array<string, string>
     */
    public static function iso_weekday_map(): array
    {
        return [
            '1' => 'monday',
            '2' => 'tuesday',
            '3' => 'wednesday',
            '4' => 'thursday',
            '5' => 'friday',
            '6' => 'saturday',
            '7' => 'sunday',
        ];
    }

    /**
     * Map storage keys to ISO-8601 weekday numbers (1=Monday … 7=Sunday).
     *
     * @return array<string, int>
     */
    public static function weekday_iso_numbers(): array
    {
        return [
            'monday'    => 1,
            'tuesday'   => 2,
            'wednesday' => 3,
            'thursday'  => 4,
            'friday'    => 5,
            'saturday'  => 6,
            'sunday'    => 7,
        ];
    }

    /**
     * Normalize mixed weekday input into a unique list of valid lowercase keys.
     *
     * Accepts lowercase names, ISO numbers (1–7), and common legacy aliases.
     *
     * @param mixed $days
     * @return array<int, string>
     */
    public static function normalize_weekday_list(mixed $days): array
    {
        if (! is_array($days)) {
            if (is_string($days) && '' !== trim($days)) {
                return self::decode_days_of_week($days);
            }

            return [];
        }

        $allowed = self::allowed_weekday_keys();
        $iso_map = self::iso_weekday_map();
        $clean   = [];

        foreach ($days as $day) {
            $day = sanitize_key((string) $day);
            if ('' === $day) {
                continue;
            }

            if (isset($iso_map[$day])) {
                $day = $iso_map[$day];
            }

            if (in_array($day, $allowed, true)) {
                $clean[] = $day;
            }
        }

        $clean = array_values(array_unique($clean));

        usort(
            $clean,
            static function (string $a, string $b): int {
                $order = self::weekday_iso_numbers();

                return ($order[$a] ?? 99) <=> ($order[$b] ?? 99);
            }
        );

        return $clean;
    }

    /**
     * Encode weekdays for database storage as a JSON array.
     *
     * @param mixed $days
     */
    public static function encode_days_of_week(mixed $days): ?string
    {
        $clean = self::normalize_weekday_list($days);

        if ([] === $clean) {
            return null;
        }

        $encoded = wp_json_encode($clean);

        return is_string($encoded) ? $encoded : null;
    }

    /**
     * Decode stored days_of_week into normalized lowercase weekday keys.
     *
     * Supports:
     * - JSON arrays: ["monday","wednesday"]
     * - Legacy comma-separated values: monday,wednesday or 1,3,5
     *
     * @return array<int, string>
     */
    public static function decode_days_of_week(string $value): array
    {
        $value = trim($value);
        if ('' === $value) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return self::normalize_weekday_list($decoded);
        }

        // Backward-compatible CSV (and single values).
        $parts = array_map('trim', explode(',', $value));

        return self::normalize_weekday_list($parts);
    }

    /**
     * @return array<int, string>
     */
    public static function allowed_repeat_types(): array
    {
        return [
            self::REPEAT_DAILY,
            self::REPEAT_WEEKLY,
            self::REPEAT_MONTHLY,
            self::REPEAT_CUSTOM,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function repeat_type_labels(): array
    {
        return [
            self::REPEAT_DAILY   => __('Daily', 'jm-referral-system'),
            self::REPEAT_WEEKLY  => __('Weekly', 'jm-referral-system'),
            self::REPEAT_MONTHLY => __('Monthly', 'jm-referral-system'),
            self::REPEAT_CUSTOM  => __('Custom', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowed_statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_PAUSED,
            self::STATUS_COMPLETED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function status_labels(): array
    {
        return [
            self::STATUS_ACTIVE    => __('Active', 'jm-referral-system'),
            self::STATUS_PAUSED    => __('Paused', 'jm-referral-system'),
            self::STATUS_COMPLETED => __('Completed', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function empty_form_data(): array
    {
        return [
            'schedule_name'      => '',
            'start_date'         => '',
            'end_date'           => '',
            'repeat_type'        => self::REPEAT_WEEKLY,
            'repeat_interval'    => '1',
            'days_of_week'       => [],
            'start_time'         => '',
            'end_time'           => '',
            'visit_type'         => '',
            'team_assignment_id' => '',
            'status'             => self::STATUS_ACTIVE,
            'notes'              => '',
            'care_plan_id'       => '',
        ];
    }

    /**
     * @param array<string, mixed>|null $schedule
     * @return array<string, mixed>
     */
    public static function map_to_form_data(?array $schedule): array
    {
        $data = self::empty_form_data();

        if (null === $schedule) {
            return $data;
        }

        $data['schedule_name']      = (string) ($schedule['schedule_name'] ?? '');
        $data['start_date']         = (string) ($schedule['start_date'] ?? '');
        $data['end_date']           = (string) ($schedule['end_date'] ?? '');
        $data['repeat_type']        = (string) ($schedule['repeat_type'] ?? self::REPEAT_WEEKLY);
        $data['repeat_interval']    = (string) max(1, absint($schedule['repeat_interval'] ?? 1));
        $data['days_of_week']       = self::decode_days_of_week((string) ($schedule['days_of_week'] ?? ''));
        $data['start_time']         = self::normalize_time_for_input((string) ($schedule['start_time'] ?? ''));
        $data['end_time']           = self::normalize_time_for_input((string) ($schedule['end_time'] ?? ''));
        $data['visit_type']         = (string) ($schedule['visit_type'] ?? '');
        $data['team_assignment_id'] = (string) absint($schedule['team_assignment_id'] ?? 0);
        $data['status']             = (string) ($schedule['status'] ?? self::STATUS_ACTIVE);
        $data['notes']              = (string) ($schedule['notes'] ?? '');
        $data['care_plan_id']       = (string) absint($schedule['care_plan_id'] ?? 0);

        return $data;
    }

    public function __construct(
        private ScheduleRepository $schedule_repository,
        private ReferralRepository $referral_repository,
        private ReferralCarePlanRepository $care_plan_repository,
        private CareTeamRepository $care_team_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, created: bool}|array{errors: array<string, string>}|false
     */
    public function save(int $referral_id, array $input, int $schedule_id = 0): array|false
    {
        $errors = $this->validate($referral_id, $input, $schedule_id);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $existing = $schedule_id > 0 ? $this->schedule_repository->find($schedule_id) : null;
        $now      = current_time('mysql');

        $care_plan_id = absint($input['care_plan_id'] ?? 0);
        if ($care_plan_id <= 0) {
            $care_plan = $this->care_plan_repository->find_by_referral($referral_id);
            $care_plan_id = absint($care_plan['id'] ?? 0);
        }

        $team_assignment_id = absint($input['team_assignment_id'] ?? 0);
        $days_of_week       = self::encode_days_of_week($input['days_of_week'] ?? []);

        $payload = [
            'referral_id'        => $referral_id,
            'care_plan_id'       => $care_plan_id > 0 ? $care_plan_id : null,
            'team_assignment_id' => $team_assignment_id > 0 ? $team_assignment_id : null,
            'schedule_name'      => trim((string) ($input['schedule_name'] ?? '')),
            'start_date'         => (string) ($input['start_date'] ?? ''),
            'end_date'           => $this->nullable_date((string) ($input['end_date'] ?? '')),
            'repeat_type'        => (string) ($input['repeat_type'] ?? self::REPEAT_WEEKLY),
            'repeat_interval'    => max(1, absint($input['repeat_interval'] ?? 1)),
            'days_of_week'       => $days_of_week,
            'start_time'         => $this->normalize_time_for_storage((string) ($input['start_time'] ?? '')),
            'end_time'           => $this->normalize_time_for_storage((string) ($input['end_time'] ?? '')),
            'visit_type'         => $this->nullable_text((string) ($input['visit_type'] ?? '')),
            'status'             => (string) ($input['status'] ?? self::STATUS_ACTIVE),
            'notes'              => $this->nullable_text((string) ($input['notes'] ?? '')),
            'updated_at'         => $now,
        ];

        if (null === $existing) {
            $payload['created_by'] = get_current_user_id();
            $payload['created_at'] = $now;

            $id = $this->schedule_repository->create($payload);

            if (false === $id) {
                return false;
            }

            $this->activity_service->log_schedule_created($referral_id);

            return [
                'id'      => $id,
                'created' => true,
            ];
        }

        $updated = $this->schedule_repository->update($schedule_id, $payload);

        if (! $updated) {
            return false;
        }

        $this->activity_service->log_schedule_updated($referral_id);

        return [
            'id'      => $schedule_id,
            'created' => false,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_schedules_for_referral(int $referral_id): array
    {
        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral || ! $this->can_view_schedules($referral)) {
            return [];
        }

        return $this->schedule_repository->get_by_referral($referral_id);
    }

    public function count_active_schedules(): int
    {
        return $this->schedule_repository->count_by_status(self::STATUS_ACTIVE);
    }

    public function count_generated_visits(int $schedule_id): int
    {
        return $this->schedule_repository->count_generated_visits($schedule_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_schedule(int $schedule_id): ?array
    {
        return $this->schedule_repository->find($schedule_id);
    }

    /**
     * @return array{schedule: array<string, mixed>, referral: array<string, mixed>}|array{errors: array<string, string>}
     */
    public function prepare_edit(int $schedule_id): array
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SCHEDULES)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to manage schedules.', 'jm-referral-system'),
                ],
            ];
        }

        $schedule = $this->schedule_repository->find($schedule_id);
        if (null === $schedule) {
            return [
                'errors' => [
                    'schedule' => __('Schedule not found.', 'jm-referral-system'),
                ],
            ];
        }

        $referral = $this->referral_repository->find(absint($schedule['referral_id'] ?? 0));
        if (null === $referral || ! $this->access_policy->can_mutate_referral($referral)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to edit this schedule.', 'jm-referral-system'),
                ],
            ];
        }

        return [
            'schedule' => $schedule,
            'referral' => $referral,
        ];
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_view_schedules(array $referral): bool
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_SCHEDULES)) {
            return false;
        }

        return $this->access_policy->can_view_referral($referral);
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_manage_schedules(array $referral): bool
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SCHEDULES)) {
            return false;
        }

        return $this->access_policy->can_mutate_referral($referral);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validate(int $referral_id, array $input, int $schedule_id): array
    {
        $errors = [];

        if (! Capabilities::current_user_can(Capabilities::MANAGE_SCHEDULES)) {
            $errors['permission'] = __('You do not have permission to manage schedules.', 'jm-referral-system');
            return $errors;
        }

        $referral = $this->referral_repository->find($referral_id);
        if ($referral_id <= 0 || null === $referral) {
            $errors['referral_id'] = __('Referral not found.', 'jm-referral-system');
            return $errors;
        }

        if (! $this->access_policy->can_mutate_referral($referral)) {
            $errors['permission'] = __('You do not have permission to manage schedules for this referral.', 'jm-referral-system');
            return $errors;
        }

        if ($schedule_id > 0) {
            $existing = $this->schedule_repository->find($schedule_id);
            if (null === $existing || absint($existing['referral_id'] ?? 0) !== $referral_id) {
                $errors['schedule'] = __('Schedule not found.', 'jm-referral-system');
                return $errors;
            }
        }

        $name = trim((string) ($input['schedule_name'] ?? ''));
        if ('' === $name) {
            $errors['schedule_name'] = __('Schedule name is required.', 'jm-referral-system');
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

        $repeat_type = (string) ($input['repeat_type'] ?? '');
        if (! in_array($repeat_type, self::allowed_repeat_types(), true)) {
            $errors['repeat_type'] = __('Please select a valid repeat type.', 'jm-referral-system');
        }

        $repeat_interval = absint($input['repeat_interval'] ?? 0);
        if ($repeat_interval < 1) {
            $errors['repeat_interval'] = __('Repeat interval must be at least 1.', 'jm-referral-system');
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

        $status = (string) ($input['status'] ?? '');
        if (! in_array($status, self::allowed_statuses(), true)) {
            $errors['status'] = __('Please select a valid schedule status.', 'jm-referral-system');
        }

        $days = self::normalize_weekday_list($input['days_of_week'] ?? []);
        if (in_array($repeat_type, [self::REPEAT_WEEKLY, self::REPEAT_CUSTOM], true) && [] === $days) {
            $errors['days_of_week'] = __('Please select at least one day of the week.', 'jm-referral-system');
        }

        $team_assignment_id = absint($input['team_assignment_id'] ?? 0);
        if ($team_assignment_id > 0) {
            $assignment = $this->care_team_repository->find($team_assignment_id);
            if (null === $assignment || absint($assignment['referral_id'] ?? 0) !== $referral_id) {
                $errors['team_assignment_id'] = __('Please select a valid care team member for this referral.', 'jm-referral-system');
            } elseif ('active' !== (string) ($assignment['assignment_status'] ?? '')) {
                $errors['team_assignment_id'] = __('Only active care team members may be selected.', 'jm-referral-system');
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
