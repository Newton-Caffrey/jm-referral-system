<?php

namespace JMReferral\Assessment;

use JMReferral\Database\Tables;

class ReferralAssessmentRepository
{
    /**
     * Columns selected for assessment rows.
     */
    private const SELECT_COLUMNS = 'id, referral_id, assessor_user_id, assessment_date, outcome, summary, recommendations, next_review_date, mobility_support, personal_care_support, medication_support, nutrition_hydration, communication_needs, cognitive_needs, continence_support, home_environment, safeguarding_risks, equipment_required, family_support, visit_frequency, visit_duration, preferred_visit_times, scheduled_at, assessment_location_type, assessment_location_name, assessment_location_address, assessment_contact_name, assessment_contact_phone, assessment_contact_email, scheduling_notes, created_at, updated_at';

    /**
     * Scheduling column names (appointment data; not clinical).
     *
     * @var array<int, string>
     */
    public const SCHEDULING_FIELDS = [
        'scheduled_at',
        'assessment_location_type',
        'assessment_location_name',
        'assessment_location_address',
        'assessment_contact_name',
        'assessment_contact_phone',
        'assessment_contact_email',
        'scheduling_notes',
    ];

    /**
     * Inserts a new assessment.
     *
     * @param array<string, mixed> $data
     * @return int|false Inserted row ID on success, false on failure.
     */
    public function create(array $data): int|false
    {
        global $wpdb;

        $row     = $this->map_row($data, true);
        $formats = $this->formats_for_row($row);

        $result = $wpdb->insert(
            Tables::referral_assessments_table(),
            $row,
            $formats
        );

        if (false === $result) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Updates an existing assessment by ID.
     *
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
            Tables::referral_assessments_table(),
            $row,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Updates appointment scheduling fields and assessor only (preserves clinical columns).
     *
     * @param array{
     *     assessor_user_id: int,
     *     scheduled_at: string,
     *     assessment_location_type: string,
     *     assessment_location_name: string,
     *     assessment_location_address: string|null,
     *     assessment_contact_name: string|null,
     *     assessment_contact_phone: string|null,
     *     assessment_contact_email: string|null,
     *     scheduling_notes: string|null,
     *     updated_at: string
     * } $data
     */
    public function update_scheduling(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            Tables::referral_assessments_table(),
            [
                'assessor_user_id'            => absint($data['assessor_user_id'] ?? 0),
                'scheduled_at'                => $data['scheduled_at'] ?? null,
                'assessment_location_type'    => $data['assessment_location_type'] ?? null,
                'assessment_location_name'    => $data['assessment_location_name'] ?? null,
                'assessment_location_address' => $data['assessment_location_address'] ?? null,
                'assessment_contact_name'     => $data['assessment_contact_name'] ?? null,
                'assessment_contact_phone'    => $data['assessment_contact_phone'] ?? null,
                'assessment_contact_email'    => $data['assessment_contact_email'] ?? null,
                'scheduling_notes'            => $data['scheduling_notes'] ?? null,
                'updated_at'                  => (string) ($data['updated_at'] ?? current_time('mysql')),
            ],
            ['id' => $id],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        return false !== $result;
    }

    /**
     * Finds the assessment for a referral.
     *
     * @return array<string, mixed>|null
     */
    public function find_by_referral(int $referral_id): ?array
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return null;
        }

        $table   = Tables::referral_assessments_table();
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

    /**
     * Whether an assessment already exists for the referral.
     */
    public function exists(int $referral_id): bool
    {
        global $wpdb;

        if ($referral_id <= 0) {
            return false;
        }

        $table = Tables::referral_assessments_table();

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
            'assessor_user_id'            => absint($data['assessor_user_id'] ?? 0),
            'assessment_date'             => $data['assessment_date'] ?? null,
            'outcome'                     => (string) ($data['outcome'] ?? 'pending'),
            'summary'                     => $data['summary'] ?? null,
            'recommendations'             => $data['recommendations'] ?? null,
            'next_review_date'            => $data['next_review_date'] ?? null,
            'mobility_support'            => $data['mobility_support'] ?? null,
            'personal_care_support'       => $data['personal_care_support'] ?? null,
            'medication_support'          => $data['medication_support'] ?? null,
            'nutrition_hydration'         => $data['nutrition_hydration'] ?? null,
            'communication_needs'         => $data['communication_needs'] ?? null,
            'cognitive_needs'             => $data['cognitive_needs'] ?? null,
            'continence_support'          => $data['continence_support'] ?? null,
            'home_environment'            => $data['home_environment'] ?? null,
            'safeguarding_risks'          => $data['safeguarding_risks'] ?? null,
            'equipment_required'          => $data['equipment_required'] ?? null,
            'family_support'              => $data['family_support'] ?? null,
            'visit_frequency'             => $data['visit_frequency'] ?? null,
            'visit_duration'              => $data['visit_duration'] ?? null,
            'preferred_visit_times'       => $data['preferred_visit_times'] ?? null,
            'scheduled_at'                => $data['scheduled_at'] ?? null,
            'assessment_location_type'    => $data['assessment_location_type'] ?? null,
            'assessment_location_name'    => $data['assessment_location_name'] ?? null,
            'assessment_location_address' => $data['assessment_location_address'] ?? null,
            'assessment_contact_name'     => $data['assessment_contact_name'] ?? null,
            'assessment_contact_phone'    => $data['assessment_contact_phone'] ?? null,
            'assessment_contact_email'    => $data['assessment_contact_email'] ?? null,
            'scheduling_notes'            => $data['scheduling_notes'] ?? null,
            'updated_at'                  => (string) ($data['updated_at'] ?? ''),
        ];

        if ($include_create_fields) {
            $row = [
                'referral_id' => absint($data['referral_id'] ?? 0),
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
        $formats = [];

        foreach ($row as $key => $value) {
            $formats[] = in_array($key, ['referral_id', 'assessor_user_id'], true) ? '%d' : '%s';
            unset($value);
        }

        return $formats;
    }
}
