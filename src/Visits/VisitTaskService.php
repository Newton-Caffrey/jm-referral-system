<?php

namespace JMReferral\Visits;

use JMReferral\CarePlan\ReferralCarePlanRepository;

class VisitTaskService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NOT_COMPLETED = 'not_completed';
    public const STATUS_REFUSED = 'refused';
    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    /**
     * Care-plan column => default visit task name.
     *
     * @return array<string, string>
     */
    public static function care_plan_task_map(): array
    {
        return [
            'personal_care_tasks'  => __('Personal Care Tasks', 'jm-referral-system'),
            'medication_support'   => __('Medication Support', 'jm-referral-system'),
            'nutrition_support'    => __('Nutrition Support', 'jm-referral-system'),
            'mobility_support'     => __('Mobility Support', 'jm-referral-system'),
            'communication_support'=> __('Communication Support', 'jm-referral-system'),
            'continence_support'   => __('Continence Support', 'jm-referral-system'),
            'social_support'       => __('Social Support', 'jm-referral-system'),
            'equipment_required'   => __('Equipment Required', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowed_statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_COMPLETED,
            self::STATUS_NOT_COMPLETED,
            self::STATUS_REFUSED,
            self::STATUS_NOT_APPLICABLE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function status_labels(): array
    {
        return [
            self::STATUS_PENDING         => __('Pending', 'jm-referral-system'),
            self::STATUS_COMPLETED       => __('Completed', 'jm-referral-system'),
            self::STATUS_NOT_COMPLETED   => __('Not Completed', 'jm-referral-system'),
            self::STATUS_REFUSED         => __('Refused', 'jm-referral-system'),
            self::STATUS_NOT_APPLICABLE  => __('Not Applicable', 'jm-referral-system'),
        ];
    }

    public function __construct(
        private VisitTaskRepository $task_repository,
        private CareVisitRepository $visit_repository,
        private ReferralCarePlanRepository $care_plan_repository
    ) {
    }

    /**
     * Generate checklist tasks from the visit care plan (idempotent per task name).
     *
     * @return int Number of tasks created
     */
    public function generate_for_visit(int $visit_id): int
    {
        $visit = $this->visit_repository->find($visit_id);
        if (null === $visit) {
            return 0;
        }

        $care_plan_id = absint($visit['care_plan_id'] ?? 0);
        if ($care_plan_id <= 0) {
            return 0;
        }

        $care_plan = $this->care_plan_repository->find($care_plan_id);
        if (null === $care_plan) {
            return 0;
        }

        $now     = current_time('mysql');
        $created = 0;
        $order   = 0;

        foreach (self::care_plan_task_map() as $column => $task_name) {
            ++$order;
            $section = trim((string) ($care_plan[$column] ?? ''));
            if ('' === $section) {
                continue;
            }

            if ($this->task_repository->exists_for_visit_with_name($visit_id, $task_name)) {
                continue;
            }

            $id = $this->task_repository->create(
                [
                    'visit_id'      => $visit_id,
                    'task_name'     => $task_name,
                    'task_status'   => self::STATUS_PENDING,
                    'task_notes'    => null,
                    'display_order' => $order,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]
            );

            if (false !== $id) {
                ++$created;
            }
        }

        return $created;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_tasks_for_visit(int $visit_id): array
    {
        return $this->task_repository->get_by_visit($visit_id);
    }

    /**
     * Update task statuses/notes from execution form input.
     *
     * @param array<int|string, array<string, mixed>> $tasks_input
     * @return array{updated: int, errors: array<string, string>}
     */
    public function update_tasks_for_visit(int $visit_id, array $tasks_input): array
    {
        $errors  = [];
        $updated = 0;
        $now     = current_time('mysql');
        $existing = $this->task_repository->get_by_visit($visit_id);
        $by_id    = [];

        foreach ($existing as $task) {
            $by_id[absint($task['id'] ?? 0)] = $task;
        }

        foreach ($tasks_input as $task_id => $row) {
            $task_id = absint($task_id);
            if ($task_id <= 0 || ! isset($by_id[$task_id])) {
                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $status = sanitize_key((string) ($row['task_status'] ?? $row['status'] ?? ''));
            if (! in_array($status, self::allowed_statuses(), true)) {
                $errors['task_' . $task_id] = __('Please select a valid task status.', 'jm-referral-system');
                continue;
            }

            $notes = isset($row['task_notes'])
                ? sanitize_textarea_field((string) $row['task_notes'])
                : (isset($row['notes']) ? sanitize_textarea_field((string) $row['notes']) : '');

            $ok = $this->task_repository->update(
                $task_id,
                [
                    'task_status' => $status,
                    'task_notes'  => '' !== trim($notes) ? $notes : null,
                    'updated_at'  => $now,
                ]
            );

            if ($ok) {
                ++$updated;
            }
        }

        return [
            'updated' => $updated,
            'errors'  => $errors,
        ];
    }

    /**
     * @return array{
     *   completed: array<int, string>,
     *   outstanding: array<int, string>,
     *   refused: array<int, string>,
     *   completed_text: string,
     *   outstanding_text: string,
     *   refused_text: string
     * }
     */
    public function get_summaries(int $visit_id): array
    {
        $completed   = [];
        $outstanding = [];
        $refused     = [];

        foreach ($this->task_repository->get_by_visit($visit_id) as $task) {
            $name   = (string) ($task['task_name'] ?? '');
            $status = (string) ($task['task_status'] ?? '');

            if ('' === $name) {
                continue;
            }

            if (self::STATUS_COMPLETED === $status) {
                $completed[] = $name;
            } elseif (in_array($status, [self::STATUS_PENDING, self::STATUS_NOT_COMPLETED], true)) {
                $outstanding[] = $name;
            } elseif (self::STATUS_REFUSED === $status) {
                $refused[] = $name;
            }
        }

        return [
            'completed'         => $completed,
            'outstanding'       => $outstanding,
            'refused'           => $refused,
            'completed_text'    => implode("\n", $completed),
            'outstanding_text'  => implode("\n", $outstanding),
            'refused_text'      => implode("\n", $refused),
        ];
    }

    /**
     * Persist auto-generated summary strings onto the care visit row.
     */
    public function sync_visit_task_summaries(int $visit_id): void
    {
        $summaries = $this->get_summaries($visit_id);
        $not_completed = trim(
            trim($summaries['outstanding_text'])
            . ('' !== $summaries['refused_text']
                ? ("\n\n" . __('Refused', 'jm-referral-system') . ":\n" . $summaries['refused_text'])
                : '')
        );

        $this->visit_repository->update(
            $visit_id,
            [
                'tasks_completed'     => '' !== $summaries['completed_text'] ? $summaries['completed_text'] : null,
                'tasks_not_completed' => '' !== $not_completed ? $not_completed : null,
                'updated_at'          => current_time('mysql'),
            ]
        );
    }

    /**
     * @return array<int, array{task_name: string, count: int}>
     */
    public function get_top_outstanding_task_types(int $limit = 10): array
    {
        return $this->task_repository->count_outstanding_by_task_name($limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_todays_outstanding_for_user(int $user_id, int $limit = 10): array
    {
        return $this->task_repository->get_outstanding_today_for_user($user_id, $limit);
    }
}
