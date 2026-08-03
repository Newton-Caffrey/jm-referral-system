<?php

namespace JMReferral\CarePlan;

use JMReferral\Database\Tables;

class ReferralCarePlanRepository
{
    private const SELECT_COLUMNS = 'id, referral_id, assessment_id, created_by, approved_by, plan_status, start_date, review_date, visit_frequency, visit_duration, preferred_visit_times, personal_care_tasks, mobility_support, medication_support, nutrition_support, communication_support, continence_support, social_support, equipment_required, risks_and_safeguards, goals, additional_instructions, created_at, updated_at';

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $row     = $this->map_row($data, true);
        $formats = $this->formats_for_row($row);

        $result = $wpdb->insert(
            Tables::referral_care_plans_table(),
            $row,
            $formats
        );

        if (false === $result) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $row     = $this->map_row($data, false);
        $formats = $this->formats_for_row($row);

        $result = $wpdb->update(
            Tables::referral_care_plans_table(),
            $row,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return false !== $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_by_referral(int $referral_id): ?array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return null;
        }

        $table   = Tables::referral_care_plans_table();
        $columns = self::SELECT_COLUMNS;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are trusted.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$columns}
                FROM {$table}
                WHERE referral_id = %d
                LIMIT 1",
                $referral_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function exists(int $referral_id): bool
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return false;
        }

        $table = Tables::referral_care_plans_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE referral_id = %d",
                $referral_id
            )
        );

        return (int) $count > 0;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function map_row(array $data, bool $include_create_fields): array
    {
        $row = [
            'assessment_id'            => isset($data['assessment_id']) ? $data['assessment_id'] : null,
            'approved_by'              => array_key_exists('approved_by', $data) ? $data['approved_by'] : null,
            'plan_status'              => (string) ($data['plan_status'] ?? 'draft'),
            'start_date'               => $data['start_date'] ?? null,
            'review_date'              => $data['review_date'] ?? null,
            'visit_frequency'          => $data['visit_frequency'] ?? null,
            'visit_duration'           => $data['visit_duration'] ?? null,
            'preferred_visit_times'    => $data['preferred_visit_times'] ?? null,
            'personal_care_tasks'      => $data['personal_care_tasks'] ?? null,
            'mobility_support'         => $data['mobility_support'] ?? null,
            'medication_support'       => $data['medication_support'] ?? null,
            'nutrition_support'        => $data['nutrition_support'] ?? null,
            'communication_support'    => $data['communication_support'] ?? null,
            'continence_support'       => $data['continence_support'] ?? null,
            'social_support'           => $data['social_support'] ?? null,
            'equipment_required'       => $data['equipment_required'] ?? null,
            'risks_and_safeguards'     => $data['risks_and_safeguards'] ?? null,
            'goals'                    => $data['goals'] ?? null,
            'additional_instructions'  => $data['additional_instructions'] ?? null,
            'updated_at'               => (string) ($data['updated_at'] ?? ''),
        ];

        if (null !== $row['assessment_id']) {
            $row['assessment_id'] = absint($row['assessment_id']) ?: null;
        }

        if (null !== $row['approved_by']) {
            $row['approved_by'] = absint($row['approved_by']) ?: null;
        }

        if ($include_create_fields) {
            $row = [
                'referral_id' => absint($data['referral_id'] ?? 0),
                'created_by'  => absint($data['created_by'] ?? 0),
            ] + $row + [
                'created_at' => (string) ($data['created_at'] ?? ''),
            ];
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int, string>
     */
    private function formats_for_row(array $row): array
    {
        $int_keys = ['referral_id', 'assessment_id', 'created_by', 'approved_by'];
        $formats  = [];

        foreach ($row as $key => $value) {
            $formats[] = in_array($key, $int_keys, true) ? '%d' : '%s';
            unset($value);
        }

        return $formats;
    }
}
