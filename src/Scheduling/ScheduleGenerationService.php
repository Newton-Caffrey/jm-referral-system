<?php

namespace JMReferral\Scheduling;

use DateInterval;
use DateTimeImmutable;
use JMReferral\CarePlan\ReferralCarePlanRepository;
use JMReferral\CareTeam\CareTeamRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Visits\CareVisitRepository;
use JMReferral\Visits\CareVisitService;

class ScheduleGenerationService
{
    private const MAX_RANGE_MONTHS = 12;
    private const DEFAULT_WEEKS = 12;

    public function __construct(
        private ScheduleRepository $schedule_repository,
        private CareVisitRepository $visit_repository,
        private ReferralRepository $referral_repository,
        private ReferralCarePlanRepository $care_plan_repository,
        private CareTeamRepository $care_team_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Default generation window for an active schedule.
     *
     * @param array<string, mixed> $schedule
     * @return array{start: string, end: string}|null
     */
    public static function default_window(array $schedule): ?array
    {
        $schedule_start = self::parse_date((string) ($schedule['start_date'] ?? ''));
        if (null === $schedule_start) {
            return null;
        }

        $today = self::parse_date(current_time('Y-m-d'));
        if (null === $today) {
            return null;
        }

        $start = $today > $schedule_start ? $today : $schedule_start;
        $end   = $start->add(new DateInterval('P' . self::DEFAULT_WEEKS . 'W'));

        $schedule_end_raw = trim((string) ($schedule['end_date'] ?? ''));
        if ('' !== $schedule_end_raw) {
            $schedule_end = self::parse_date($schedule_end_raw);
            if (null !== $schedule_end && $end > $schedule_end) {
                $end = $schedule_end;
            }
        }

        if ($end < $start) {
            $end = $start;
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end'   => $end->format('Y-m-d'),
        ];
    }

    /**
     * Expand an active schedule into discrete care visits for a date window.
     *
     * @return array{
     *   errors?: array<string, string>,
     *   created?: int,
     *   skipped_duplicates?: int,
     *   skipped_outside_range?: int
     * }
     */
    public function generate(int $referral_id, int $schedule_id, string $start_date, string $end_date): array
    {
        $errors = [];

        if (! Capabilities::current_user_can(Capabilities::MANAGE_SCHEDULES)) {
            $errors['permission'] = __('You do not have permission to generate visits from schedules.', 'jm-referral-system');
            return ['errors' => $errors];
        }

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_edit_referral($referral)) {
            $errors['permission'] = __('You do not have permission to generate visits for this referral.', 'jm-referral-system');
            return ['errors' => $errors];
        }

        $schedule = $this->schedule_repository->find($schedule_id);
        if (null === $schedule || absint($schedule['referral_id'] ?? 0) !== $referral_id) {
            $errors['schedule'] = __('Schedule not found.', 'jm-referral-system');
            return ['errors' => $errors];
        }

        if (ScheduleService::STATUS_ACTIVE !== (string) ($schedule['status'] ?? '')) {
            $errors['status'] = __('Only active schedules can generate visits.', 'jm-referral-system');
            return ['errors' => $errors];
        }

        $schedule_start = self::parse_date((string) ($schedule['start_date'] ?? ''));
        if (null === $schedule_start) {
            $errors['start_date'] = __('This schedule does not have a valid start date.', 'jm-referral-system');
            return ['errors' => $errors];
        }

        $schedule_end = null;
        $schedule_end_raw = trim((string) ($schedule['end_date'] ?? ''));
        if ('' !== $schedule_end_raw) {
            $schedule_end = self::parse_date($schedule_end_raw);
            if (null === $schedule_end) {
                $errors['end_date'] = __('This schedule does not have a valid end date.', 'jm-referral-system');
                return ['errors' => $errors];
            }
        }

        $start_time = $this->normalize_time((string) ($schedule['start_time'] ?? ''));
        $end_time   = $this->normalize_time((string) ($schedule['end_time'] ?? ''));
        if (null === $start_time || null === $end_time || $start_time >= $end_time) {
            $errors['time'] = __('This schedule does not have valid start and end times.', 'jm-referral-system');
            return ['errors' => $errors];
        }

        $window = $this->resolve_window($schedule, $start_date, $end_date);
        if (isset($window['errors'])) {
            return ['errors' => $window['errors']];
        }

        /** @var DateTimeImmutable $gen_start */
        $gen_start = $window['start'];
        /** @var DateTimeImmutable $gen_end */
        $gen_end = $window['end'];

        $care_plan_id = absint($schedule['care_plan_id'] ?? 0);
        if ($care_plan_id > 0) {
            $care_plan = $this->care_plan_repository->find($care_plan_id);
            if (null === $care_plan || absint($care_plan['referral_id'] ?? 0) !== $referral_id) {
                $errors['care_plan_id'] = __('The linked care plan does not belong to this referral.', 'jm-referral-system');
                return ['errors' => $errors];
            }
        }

        $assigned_user_id = null;
        $team_assignment_id = absint($schedule['team_assignment_id'] ?? 0);
        if ($team_assignment_id > 0) {
            $assignment = $this->care_team_repository->find($team_assignment_id);
            if (
                null === $assignment
                || absint($assignment['referral_id'] ?? 0) !== $referral_id
                || 'active' !== (string) ($assignment['assignment_status'] ?? '')
            ) {
                $errors['team_assignment_id'] = __('The assigned care team member is not an active assignment for this referral.', 'jm-referral-system');
                return ['errors' => $errors];
            }

            $assigned_user_id = absint($assignment['user_id'] ?? 0) ?: null;
        }

        $repeat_type     = (string) ($schedule['repeat_type'] ?? '');
        $repeat_interval = max(1, absint($schedule['repeat_interval'] ?? 1));
        $days_of_week    = ScheduleService::decode_days_of_week((string) ($schedule['days_of_week'] ?? ''));

        if (
            in_array($repeat_type, [ScheduleService::REPEAT_WEEKLY, ScheduleService::REPEAT_CUSTOM], true)
            && [] === $days_of_week
        ) {
            $errors['days_of_week'] = __('This schedule requires at least one day of the week to generate visits.', 'jm-referral-system');
            return ['errors' => $errors];
        }

        if (! in_array($repeat_type, ScheduleService::allowed_repeat_types(), true)) {
            $errors['repeat_type'] = __('This schedule has an unsupported repeat type.', 'jm-referral-system');
            return ['errors' => $errors];
        }

        $occurrences = $this->calculate_occurrences(
            $repeat_type,
            $repeat_interval,
            $days_of_week,
            $schedule_start,
            $schedule_end,
            $gen_start,
            $gen_end
        );

        $created                = 0;
        $skipped_duplicates     = 0;
        $skipped_outside_range  = (int) ($occurrences['skipped_outside_range'] ?? 0);
        $now                    = current_time('mysql');
        $created_by             = get_current_user_id();
        $visit_type             = $this->nullable_text((string) ($schedule['visit_type'] ?? ''));
        $notes                  = $this->nullable_text((string) ($schedule['notes'] ?? ''));
        $user_key               = null !== $assigned_user_id ? $assigned_user_id : 0;

        foreach ($occurrences['dates'] as $occurrence_date) {
            $generation_key = $this->build_generation_key(
                $schedule_id,
                $occurrence_date,
                $start_time,
                $user_key
            );

            if (null !== $this->visit_repository->find_by_generation_key($generation_key)) {
                ++$skipped_duplicates;
                continue;
            }

            $inserted = $this->visit_repository->create(
                [
                    'referral_id'              => $referral_id,
                    'care_plan_id'             => $care_plan_id > 0 ? $care_plan_id : null,
                    'assigned_user_id'         => $assigned_user_id,
                    'schedule_id'              => $schedule_id,
                    'schedule_occurrence_date' => $occurrence_date,
                    'generation_key'           => $generation_key,
                    'visit_date'               => $occurrence_date,
                    'start_time'               => $start_time,
                    'end_time'                 => $end_time,
                    'visit_status'             => CareVisitService::STATUS_SCHEDULED,
                    'visit_type'               => $visit_type,
                    'tasks'                    => null,
                    'notes'                    => $notes,
                    'completed_at'             => null,
                    'created_by'               => $created_by,
                    'created_at'               => $now,
                    'updated_at'               => $now,
                ]
            );

            if (false === $inserted) {
                // Unique key race or DB error: treat as duplicate skip when key already exists.
                if (null !== $this->visit_repository->find_by_generation_key($generation_key)) {
                    ++$skipped_duplicates;
                    continue;
                }

                $errors['general'] = __('Unable to create one or more generated visits. Please try again.', 'jm-referral-system');
                return [
                    'errors'                 => $errors,
                    'created'                => $created,
                    'skipped_duplicates'     => $skipped_duplicates,
                    'skipped_outside_range'  => $skipped_outside_range,
                ];
            }

            ++$created;
        }

        if ($created > 0) {
            $this->activity_service->log_schedule_visits_generated($referral_id, $created);
        }

        return [
            'created'               => $created,
            'skipped_duplicates'    => $skipped_duplicates,
            'skipped_outside_range' => $skipped_outside_range,
        ];
    }

    public function count_generated_visits(int $schedule_id): int
    {
        return $this->visit_repository->count_by_schedule($schedule_id);
    }

    /**
     * @param array<string, mixed> $schedule
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}|array{errors: array<string, string>}
     */
    private function resolve_window(array $schedule, string $start_date, string $end_date): array
    {
        $errors = [];

        $start_date = trim($start_date);
        $end_date   = trim($end_date);

        if ('' === $start_date || '' === $end_date) {
            $defaults = self::default_window($schedule);
            if (null === $defaults) {
                $errors['generation_window'] = __('Unable to determine a valid generation window for this schedule.', 'jm-referral-system');
                return ['errors' => $errors];
            }

            if ('' === $start_date) {
                $start_date = $defaults['start'];
            }
            if ('' === $end_date) {
                $end_date = $defaults['end'];
            }
        }

        $gen_start = self::parse_date($start_date);
        $gen_end   = self::parse_date($end_date);

        if (null === $gen_start) {
            $errors['generation_start_date'] = __('Please enter a valid generate-from date.', 'jm-referral-system');
        }
        if (null === $gen_end) {
            $errors['generation_end_date'] = __('Please enter a valid generate-until date.', 'jm-referral-system');
        }
        if ([] !== $errors) {
            return ['errors' => $errors];
        }

        if ($gen_end < $gen_start) {
            $errors['generation_window'] = __('Generate until date must be on or after the generate from date.', 'jm-referral-system');
            return ['errors' => $errors];
        }

        $max_end = $gen_start->add(new DateInterval('P' . self::MAX_RANGE_MONTHS . 'M'));
        if ($gen_end > $max_end) {
            $errors['generation_window'] = __('The generation range cannot exceed 12 months.', 'jm-referral-system');
            return ['errors' => $errors];
        }

        $schedule_start = self::parse_date((string) ($schedule['start_date'] ?? ''));
        $schedule_end   = null;
        $schedule_end_raw = trim((string) ($schedule['end_date'] ?? ''));
        if ('' !== $schedule_end_raw) {
            $schedule_end = self::parse_date($schedule_end_raw);
        }

        if (null === $schedule_start) {
            $errors['start_date'] = __('This schedule does not have a valid start date.', 'jm-referral-system');
            return ['errors' => $errors];
        }

        // Entirely outside schedule range.
        if ($gen_end < $schedule_start) {
            $errors['generation_window'] = __('The selected dates are entirely before this schedule starts.', 'jm-referral-system');
            return ['errors' => $errors];
        }
        if (null !== $schedule_end && $gen_start > $schedule_end) {
            $errors['generation_window'] = __('The selected dates are entirely after this schedule ends.', 'jm-referral-system');
            return ['errors' => $errors];
        }

        return [
            'start' => $gen_start,
            'end'   => $gen_end,
        ];
    }

    /**
     * @param array<int, string> $days_of_week
     * @return array{dates: array<int, string>, skipped_outside_range: int}
     */
    private function calculate_occurrences(
        string $repeat_type,
        int $interval,
        array $days_of_week,
        DateTimeImmutable $schedule_start,
        ?DateTimeImmutable $schedule_end,
        DateTimeImmutable $gen_start,
        DateTimeImmutable $gen_end
    ): array {
        $dates                 = [];
        $skipped_outside_range = 0;

        if (ScheduleService::REPEAT_MONTHLY === $repeat_type) {
            $candidates = $this->monthly_candidates($schedule_start, $interval, $gen_start, $gen_end);
        } elseif (ScheduleService::REPEAT_DAILY === $repeat_type) {
            $candidates = $this->daily_candidates($schedule_start, $interval, $gen_start, $gen_end);
        } else {
            // Weekly and custom (weekday pattern every N weeks, anchored to schedule start).
            $candidates = $this->weekly_candidates($schedule_start, $interval, $days_of_week, $gen_start, $gen_end);
        }

        foreach ($candidates as $candidate) {
            if ($candidate < $schedule_start || (null !== $schedule_end && $candidate > $schedule_end)) {
                ++$skipped_outside_range;
                continue;
            }

            if ($candidate < $gen_start || $candidate > $gen_end) {
                continue;
            }

            $dates[] = $candidate->format('Y-m-d');
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return [
            'dates'                 => $dates,
            'skipped_outside_range' => $skipped_outside_range,
        ];
    }

    /**
     * @return array<int, DateTimeImmutable>
     */
    private function daily_candidates(
        DateTimeImmutable $schedule_start,
        int $interval,
        DateTimeImmutable $gen_start,
        DateTimeImmutable $gen_end
    ): array {
        $candidates = [];
        $cursor     = $schedule_start;

        // Advance to first occurrence on/after gen_start.
        if ($cursor < $gen_start) {
            $days = (int) $cursor->diff($gen_start)->format('%a');
            $steps = (int) floor($days / $interval);
            $cursor = $cursor->add(new DateInterval('P' . ($steps * $interval) . 'D'));
            while ($cursor < $gen_start) {
                $cursor = $cursor->add(new DateInterval('P' . $interval . 'D'));
            }
        }

        while ($cursor <= $gen_end) {
            $candidates[] = $cursor;
            $cursor = $cursor->add(new DateInterval('P' . $interval . 'D'));
        }

        return $candidates;
    }

    /**
     * @param array<int, string> $days_of_week Lowercase weekday keys
     * @return array<int, DateTimeImmutable>
     */
    private function weekly_candidates(
        DateTimeImmutable $schedule_start,
        int $interval,
        array $days_of_week,
        DateTimeImmutable $gen_start,
        DateTimeImmutable $gen_end
    ): array {
        $iso_numbers = ScheduleService::weekday_iso_numbers();
        $selected    = [];

        foreach ($days_of_week as $day) {
            $iso = $iso_numbers[$day] ?? null;
            if (null !== $iso) {
                $selected[$iso] = true;
            }
        }

        if ([] === $selected) {
            return [];
        }

        $candidates = [];
        $cursor     = $gen_start < $schedule_start ? $schedule_start : $gen_start;

        // Walk each day in the generation window (clamped to schedule start).
        while ($cursor <= $gen_end) {
            $iso_day = (int) $cursor->format('N');
            if (isset($selected[$iso_day])) {
                $days_from_start = (int) $schedule_start->diff($cursor)->format('%a');
                if ($cursor >= $schedule_start) {
                    $week_index = (int) floor($days_from_start / 7);
                    if (0 === ($week_index % $interval)) {
                        $candidates[] = $cursor;
                    }
                }
            }
            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        return $candidates;
    }

    /**
     * @return array<int, DateTimeImmutable>
     */
    private function monthly_candidates(
        DateTimeImmutable $schedule_start,
        int $interval,
        DateTimeImmutable $gen_start,
        DateTimeImmutable $gen_end
    ): array {
        $candidates   = [];
        $day_of_month = (int) $schedule_start->format('j');
        $cursor       = $schedule_start;

        // Advance month cursor until on/after gen_start month.
        while ($this->month_occurrence($cursor, $day_of_month) < $gen_start) {
            $cursor = $this->add_months($cursor, $interval);
            // Safety: avoid infinite loop on bad data.
            if ($cursor > $gen_end && $this->month_occurrence($cursor, $day_of_month) > $gen_end) {
                break;
            }
        }

        while (true) {
            $occurrence = $this->month_occurrence($cursor, $day_of_month);
            if ($occurrence > $gen_end) {
                break;
            }
            if ($occurrence >= $gen_start) {
                $candidates[] = $occurrence;
            }
            $cursor = $this->add_months($cursor, $interval);
        }

        return $candidates;
    }

    private function month_occurrence(DateTimeImmutable $month_anchor, int $day_of_month): DateTimeImmutable
    {
        $year  = (int) $month_anchor->format('Y');
        $month = (int) $month_anchor->format('n');
        $last  = (int) $month_anchor->format('t');
        $day   = min($day_of_month, $last);

        return $month_anchor->setDate($year, $month, $day);
    }

    private function add_months(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $day = (int) $date->format('j');
        $base = $date->modify('first day of this month')->add(new DateInterval('P' . $months . 'M'));
        $last = (int) $base->format('t');

        return $base->setDate((int) $base->format('Y'), (int) $base->format('n'), min($day, $last));
    }

    private function build_generation_key(int $schedule_id, string $occurrence_date, string $start_time, int $user_id): string
    {
        $raw = sprintf(
            'schedule:%d|date:%s|time:%s|user:%d',
            $schedule_id,
            $occurrence_date,
            $start_time,
            $user_id
        );

        if (strlen($raw) <= 191) {
            return $raw;
        }

        return hash('sha256', $raw);
    }

    private function normalize_time(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            $value .= ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) !== 1) {
            return null;
        }

        [$h, $m, $s] = array_map('intval', explode(':', $value));
        if ($h > 23 || $m > 59 || $s > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    private function nullable_text(string $value): ?string
    {
        $value = trim($value);

        return '' !== $value ? $value : null;
    }

    private static function parse_date(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }
}
