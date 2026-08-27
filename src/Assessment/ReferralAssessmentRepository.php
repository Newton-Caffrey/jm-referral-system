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

    /**
     * Dashboard assessment window counts (Phase 4E.1).
     *
     * Joins non-archived referrals; optional assigned_to scope.
     *
     * @param 'scheduled'|'past_scheduled'|'completed' $window
     */
    public function count_for_dashboard(string $window, string $now_mysql, ?int $access_assigned_to = null): int
    {
        global $wpdb;

        $assessments = Tables::referral_assessments_table();
        $referrals   = Tables::referrals_table();

        $where  = ['r.archived_at IS NULL'];
        $params = [];

        if ('scheduled' === $window) {
            $where[]  = 'a.outcome = %s';
            $params[] = ReferralAssessmentService::OUTCOME_PENDING;
            $where[]  = 'a.scheduled_at IS NOT NULL';
            $where[]  = 'a.scheduled_at >= %s';
            $params[] = $now_mysql;
        } elseif ('past_scheduled' === $window) {
            $where[]  = 'a.outcome = %s';
            $params[] = ReferralAssessmentService::OUTCOME_PENDING;
            $where[]  = 'a.scheduled_at IS NOT NULL';
            $where[]  = 'a.scheduled_at < %s';
            $params[] = $now_mysql;
        } elseif ('completed' === $window) {
            $where[] = 'a.outcome IN (%s, %s, %s)';
            $params[] = ReferralAssessmentService::OUTCOME_SUITABLE;
            $params[] = ReferralAssessmentService::OUTCOME_SUITABLE_WITH_CONDITIONS;
            $params[] = ReferralAssessmentService::OUTCOME_NOT_SUITABLE;
        } else {
            return 0;
        }

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT COUNT(*)
            FROM {$assessments} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE " . implode(' AND ', $where);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * Completed outcome distribution for dashboard (excludes pending).
     *
     * @return array<string, int> outcome => count
     */
    public function count_outcomes_for_dashboard(?int $access_assigned_to = null): array
    {
        global $wpdb;

        $assessments = Tables::referral_assessments_table();
        $referrals   = Tables::referrals_table();

        $where  = [
            'r.archived_at IS NULL',
            'a.outcome IN (%s, %s, %s)',
        ];
        $params = [
            ReferralAssessmentService::OUTCOME_SUITABLE,
            ReferralAssessmentService::OUTCOME_SUITABLE_WITH_CONDITIONS,
            ReferralAssessmentService::OUTCOME_NOT_SUITABLE,
        ];

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $sql = "SELECT a.outcome, COUNT(*) AS total
            FROM {$assessments} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE " . implode(' AND ', $where) . '
            GROUP BY a.outcome';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        $out     = [
            ReferralAssessmentService::OUTCOME_SUITABLE                 => 0,
            ReferralAssessmentService::OUTCOME_SUITABLE_WITH_CONDITIONS => 0,
            ReferralAssessmentService::OUTCOME_NOT_SUITABLE             => 0,
        ];

        if (! is_array($results)) {
            return $out;
        }

        foreach ($results as $row) {
            $outcome = (string) ($row['outcome'] ?? '');
            if (isset($out[$outcome])) {
                $out[$outcome] = (int) ($row['total'] ?? 0);
            }
        }

        return $out;
    }

    /**
     * Safe list rows for scheduled/past scheduled assessments.
     *
     * @param 'upcoming'|'past' $window
     * @return array<int, array<string, mixed>>
     */
    public function list_scheduled_for_dashboard(
        string $window,
        string $now_mysql,
        ?string $until_mysql,
        int $limit,
        ?int $access_assigned_to = null
    ): array {
        global $wpdb;

        $limit       = max(1, min(50, $limit));
        $assessments = Tables::referral_assessments_table();
        $referrals   = Tables::referrals_table();

        $where  = [
            'r.archived_at IS NULL',
            'a.outcome = %s',
            'a.scheduled_at IS NOT NULL',
        ];
        $params = [ReferralAssessmentService::OUTCOME_PENDING];

        if ('upcoming' === $window) {
            $where[]  = 'a.scheduled_at >= %s';
            $params[] = $now_mysql;
            if (null !== $until_mysql && '' !== $until_mysql) {
                $where[]  = 'a.scheduled_at <= %s';
                $params[] = $until_mysql;
            }
            $order = 'a.scheduled_at ASC, a.id ASC';
        } else {
            $where[]  = 'a.scheduled_at < %s';
            $params[] = $now_mysql;
            $order    = 'a.scheduled_at DESC, a.id DESC';
        }

        if (null !== $access_assigned_to && $access_assigned_to > 0) {
            $where[]  = 'r.assigned_to = %d';
            $params[] = $access_assigned_to;
        }

        $params[] = $limit;

        $sql = "SELECT a.id, a.referral_id, a.assessor_user_id, a.scheduled_at, a.outcome,
                r.referral_number
            FROM {$assessments} a
            INNER JOIN {$referrals} r ON r.id = a.referral_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$order}
            LIMIT %d";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }
}
