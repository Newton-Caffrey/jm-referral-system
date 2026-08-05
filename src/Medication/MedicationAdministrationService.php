<?php

namespace JMReferral\Medication;

use DateTimeImmutable;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Visits\CareVisitRepository;

class MedicationAdministrationService
{
    public const STATUS_GIVEN = 'given';
    public const STATUS_REFUSED = 'refused';
    public const STATUS_OMITTED = 'omitted';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_CLIENT_ABSENT = 'client_absent';
    public const STATUS_NOT_REQUIRED = 'not_required';
    public const STATUS_ERROR = 'error';

    public const REASON_CLIENT_REFUSED = 'client_refused';
    public const REASON_MEDICATION_UNAVAILABLE = 'medication_unavailable';
    public const REASON_CLIENT_ABSENT = 'client_absent';
    public const REASON_CONTRAINDICATION = 'contraindication';
    public const REASON_CLINICAL_INSTRUCTION = 'clinical_instruction';
    public const REASON_NOT_DUE = 'not_due';
    public const REASON_OTHER = 'other';

    /**
     * @return array<int, string>
     */
    public static function allowed_statuses(): array
    {
        return [
            self::STATUS_GIVEN,
            self::STATUS_REFUSED,
            self::STATUS_OMITTED,
            self::STATUS_UNAVAILABLE,
            self::STATUS_CLIENT_ABSENT,
            self::STATUS_NOT_REQUIRED,
            self::STATUS_ERROR,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function status_labels(): array
    {
        return [
            self::STATUS_GIVEN         => __('Given', 'jm-referral-system'),
            self::STATUS_REFUSED       => __('Refused', 'jm-referral-system'),
            self::STATUS_OMITTED       => __('Omitted', 'jm-referral-system'),
            self::STATUS_UNAVAILABLE   => __('Unavailable', 'jm-referral-system'),
            self::STATUS_CLIENT_ABSENT => __('Client Absent', 'jm-referral-system'),
            self::STATUS_NOT_REQUIRED  => __('Not Required', 'jm-referral-system'),
            self::STATUS_ERROR         => __('Error', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses_requiring_reason(): array
    {
        return [
            self::STATUS_REFUSED,
            self::STATUS_OMITTED,
            self::STATUS_UNAVAILABLE,
            self::STATUS_CLIENT_ABSENT,
            self::STATUS_ERROR,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function exception_statuses(): array
    {
        return [
            self::STATUS_REFUSED,
            self::STATUS_OMITTED,
            self::STATUS_UNAVAILABLE,
            self::STATUS_ERROR,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowed_reason_codes(): array
    {
        return [
            self::REASON_CLIENT_REFUSED,
            self::REASON_MEDICATION_UNAVAILABLE,
            self::REASON_CLIENT_ABSENT,
            self::REASON_CONTRAINDICATION,
            self::REASON_CLINICAL_INSTRUCTION,
            self::REASON_NOT_DUE,
            self::REASON_OTHER,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function reason_labels(): array
    {
        return [
            self::REASON_CLIENT_REFUSED          => __('Client Refused', 'jm-referral-system'),
            self::REASON_MEDICATION_UNAVAILABLE  => __('Medication Unavailable', 'jm-referral-system'),
            self::REASON_CLIENT_ABSENT           => __('Client Absent', 'jm-referral-system'),
            self::REASON_CONTRAINDICATION        => __('Contraindication', 'jm-referral-system'),
            self::REASON_CLINICAL_INSTRUCTION    => __('Clinical Instruction', 'jm-referral-system'),
            self::REASON_NOT_DUE                 => __('Not Due', 'jm-referral-system'),
            self::REASON_OTHER                   => __('Other', 'jm-referral-system'),
        ];
    }

    public function __construct(
        private MedicationAdministrationRepository $administration_repository,
        private MedicationRepository $medication_repository,
        private CareVisitRepository $visit_repository,
        private ReferralRepository $referral_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Save administrations posted with visit execution.
     *
     * @param array<int|string, array<string, mixed>> $rows
     * @return array{saved: int, errors: array<string, string>, warning: string|null}
     */
    public function save_for_visit(int $referral_id, int $visit_id, array $rows): array
    {
        $errors  = [];
        $saved   = 0;
        $warning = null;

        $visit = $this->visit_repository->find($visit_id);
        $referral = $this->referral_repository->find($referral_id);

        if (null === $visit || null === $referral || absint($visit['referral_id'] ?? 0) !== $referral_id) {
            return [
                'saved'   => 0,
                'errors'  => [
                    'medications' => __('Care visit not found for this referral.', 'jm-referral-system'),
                ],
                'warning' => null,
            ];
        }

        if (! $this->can_administer_for_visit($referral, $visit)) {
            return [
                'saved'   => 0,
                'errors'  => [
                    'medications' => __('You do not have permission to record medication administrations for this visit.', 'jm-referral-system'),
                ],
                'warning' => null,
            ];
        }

        $active_meds = $this->get_active_medications_valid_on_visit($visit);
        $active_ids  = [];
        foreach ($active_meds as $med) {
            $active_ids[absint($med['id'] ?? 0)] = $med;
        }

        foreach ($rows as $medication_id => $row) {
            $medication_id = absint($medication_id);
            if ($medication_id <= 0 || ! is_array($row)) {
                continue;
            }

            $status = sanitize_key((string) ($row['administration_status'] ?? ''));
            if ('' === $status) {
                continue;
            }

            $result = $this->save_single($referral_id, $visit_id, $medication_id, $row, $visit, $active_ids[$medication_id] ?? null);
            if (isset($result['errors'])) {
                foreach ($result['errors'] as $key => $message) {
                    $errors[$key] = $message;
                }
                continue;
            }

            if (! empty($result['saved'])) {
                ++$saved;
            }
        }

        $existing = $this->administration_repository->get_by_visit($visit_id);
        if (! empty($active_ids) && empty($existing) && 0 === $saved && empty($errors)) {
            $warning = __('Active medications exist for this client, but no medication administrations were recorded for this visit.', 'jm-referral-system');
        }

        return [
            'saved'   => $saved,
            'errors'  => $errors,
            'warning' => $warning,
        ];
    }

    /**
     * Whether the current user may record medication administrations for this visit.
     *
     * Rules:
     * - Requires jmrs_administer_medications
     * - Referral must be accessible via AccessPolicy
     * - Support Workers (scoped): visit.assigned_user_id must equal the current user
     * - Managers/unscoped users with the capability may administer on any accessible visit
     *
     * Visit ownership is based on visit.assigned_user_id, not referral.assigned_to
     * or care-team membership alone.
     *
     * @param array<string, mixed> $referral
     * @param array<string, mixed> $visit
     */
    public function can_administer_for_visit(array $referral, array $visit): bool
    {
        if (! Capabilities::current_user_can(Capabilities::ADMINISTER_MEDICATIONS)) {
            return false;
        }

        if (! $this->access_policy->can_view_referral($referral)) {
            return false;
        }

        if ($this->access_policy->is_referral_archived($referral)) {
            return false;
        }

        if ($this->access_policy->should_scope_to_assigned()) {
            return absint($visit['assigned_user_id'] ?? 0) === get_current_user_id();
        }

        return true;
    }

    /**
     * UI visibility for the Medication Administration section during visit execution.
     * Matches server-side save permission plus active medications valid on the visit date.
     *
     * @param array<string, mixed> $referral
     * @param array<string, mixed> $visit
     */
    public function can_show_administration_for_visit(array $referral, array $visit): bool
    {
        if (! $this->can_administer_for_visit($referral, $visit)) {
            return false;
        }

        return [] !== $this->get_active_medications_valid_on_visit($visit);
    }

    /**
     * Active medications for the referral that are valid on the visit date.
     *
     * @param array<string, mixed> $visit
     * @return array<int, array<string, mixed>>
     */
    public function get_active_medications_valid_on_visit(array $visit): array
    {
        $referral_id = absint($visit['referral_id'] ?? 0);
        if ($referral_id <= 0) {
            return [];
        }

        $visit_date = (string) ($visit['visit_date'] ?? '');
        $active     = $this->medication_repository->get_active_by_referral($referral_id);
        $valid      = [];

        foreach ($active as $medication) {
            if ($this->is_medication_valid_on_date($medication, $visit_date)) {
                $valid[] = $medication;
            }
        }

        return $valid;
    }

    /**
     * @param array<string, mixed> $medication
     */
    public function is_medication_valid_on_date(array $medication, string $visit_date): bool
    {
        return $this->medication_active_on_date($medication, $visit_date);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_for_visit(int $visit_id): array
    {
        return $this->administration_repository->get_by_visit($visit_id);
    }

    /**
     * @param array<int, int> $visit_ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function get_by_visit_ids(array $visit_ids): array
    {
        return $this->administration_repository->get_by_visit_ids($visit_ids);
    }

    public function count_exceptions_today_for_managers(): int
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_VISITS)) {
            return 0;
        }

        if ($this->access_policy->should_scope_to_assigned()) {
            return 0;
        }

        return $this->administration_repository->count_exceptions_for_date(current_time('Y-m-d'));
    }

    public function count_my_exceptions_today(): int
    {
        if (! Capabilities::current_user_can(Capabilities::ADMINISTER_MEDICATIONS)) {
            return 0;
        }

        if (! $this->access_policy->should_scope_to_assigned()) {
            return 0;
        }

        return $this->administration_repository->count_exceptions_today_for_user(
            get_current_user_id(),
            current_time('Y-m-d')
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_exception_rows_for_alerts(int $limit = 100): array
    {
        $access = $this->access_policy->get_assigned_user_constraint();

        return $this->administration_repository->get_exceptions_for_date(
            current_time('Y-m-d'),
            $limit,
            $access
        );
    }

    /**
     * @param array<string, mixed>              $visit
     * @param array<string, mixed>|null         $medication
     * @param array<string, mixed>              $row
     * @return array{saved?: bool, errors?: array<string, string>}
     */
    private function save_single(
        int $referral_id,
        int $visit_id,
        int $medication_id,
        array $row,
        array $visit,
        ?array $medication
    ): array {
        $prefix = 'medication_' . $medication_id;
        $errors = [];

        if (null === $medication) {
            $medication = $this->medication_repository->find($medication_id);
        }

        if (null === $medication || absint($medication['referral_id'] ?? 0) !== $referral_id) {
            return [
                'errors' => [
                    $prefix => __('Medication does not belong to this referral.', 'jm-referral-system'),
                ],
            ];
        }

        $status = sanitize_key((string) ($row['administration_status'] ?? ''));
        if (! in_array($status, self::allowed_statuses(), true)) {
            $errors[$prefix . '_status'] = __('Please select a valid administration status.', 'jm-referral-system');
        }

        $visit_date = (string) ($visit['visit_date'] ?? '');
        $med_status = (string) ($medication['medication_status'] ?? '');
        if (
            self::STATUS_NOT_REQUIRED !== $status
            && MedicationStatuses::ACTIVE !== $med_status
        ) {
            $errors[$prefix . '_status'] = __('Only active medications can be administered unless status is Not Required.', 'jm-referral-system');
        }

        if (
            self::STATUS_NOT_REQUIRED !== $status
            && ! $this->medication_active_on_date($medication, $visit_date)
        ) {
            $errors[$prefix] = __('This medication is not active for the visit date.', 'jm-referral-system');
        }

        $dose = trim((string) ($row['dose_given'] ?? ''));
        if (self::STATUS_GIVEN === $status && '' === $dose) {
            $errors[$prefix . '_dose'] = __('Dose given is required when status is Given.', 'jm-referral-system');
        }

        $reason = sanitize_key((string) ($row['reason_code'] ?? ''));
        if (in_array($status, self::statuses_requiring_reason(), true)) {
            if ('' === $reason) {
                $errors[$prefix . '_reason'] = __('A reason code is required for this administration status.', 'jm-referral-system');
            } elseif (! in_array($reason, self::allowed_reason_codes(), true)) {
                $errors[$prefix . '_reason'] = __('Please select a valid reason code.', 'jm-referral-system');
            }
        } elseif ('' !== $reason && ! in_array($reason, self::allowed_reason_codes(), true)) {
            $errors[$prefix . '_reason'] = __('Please select a valid reason code.', 'jm-referral-system');
        }

        $administered_raw = trim((string) ($row['administered_time'] ?? ''));
        if ('' === $administered_raw) {
            $administered_raw = current_time('Y-m-d H:i:s');
        }
        $administered_dt = $this->parse_datetime($administered_raw);
        if (null === $administered_dt) {
            $errors[$prefix . '_time'] = __('Please enter a valid administered time.', 'jm-referral-system');
        } else {
            $now = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', current_time('mysql'));
            if ($now instanceof DateTimeImmutable) {
                $max = $now->modify('+2 hours');
                if ($administered_dt > $max) {
                    $errors[$prefix . '_time'] = __('Administered time cannot be unreasonably far in the future.', 'jm-referral-system');
                }
            }
        }

        $scheduled_raw = trim((string) ($row['scheduled_time'] ?? ''));
        $scheduled_dt  = '' !== $scheduled_raw ? $this->parse_datetime($scheduled_raw) : null;
        if ('' !== $scheduled_raw && null === $scheduled_dt) {
            $errors[$prefix . '_scheduled'] = __('Please enter a valid scheduled time.', 'jm-referral-system');
        }

        $witness = absint($row['witness_user_id'] ?? 0);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $now_mysql = current_time('mysql');
        $scheduled_mysql = null !== $scheduled_dt ? $scheduled_dt->format('Y-m-d H:i:s') : null;
        /** @var DateTimeImmutable $administered_dt */
        $administered_mysql = $administered_dt->format('Y-m-d H:i:s');

        $existing = $this->administration_repository->find_existing_for_visit_and_time(
            $medication_id,
            $visit_id,
            $scheduled_mysql
        );

        $payload = [
            'medication_id'          => $medication_id,
            'referral_id'            => $referral_id,
            'visit_id'               => $visit_id,
            'administered_by'        => get_current_user_id(),
            'scheduled_time'         => $scheduled_mysql,
            'administered_time'      => $administered_mysql,
            'administration_status'  => $status,
            'dose_given'             => '' !== $dose ? $dose : null,
            'notes'                  => $this->nullable_text((string) ($row['notes'] ?? '')),
            'reason_code'            => '' !== $reason ? $reason : null,
            'witness_user_id'        => $witness > 0 ? $witness : null,
            'updated_at'             => $now_mysql,
        ];

        $status_labels = self::status_labels();
        $med_name      = (string) ($medication['medication_name'] ?? '');

        if (null !== $existing) {
            $ok = $this->administration_repository->update(absint($existing['id']), $payload);
            if (! $ok) {
                return [
                    'errors' => [
                        $prefix => __('Unable to update medication administration.', 'jm-referral-system'),
                    ],
                ];
            }
        } else {
            $payload['created_at'] = $now_mysql;
            $id = $this->administration_repository->create($payload);
            if (false === $id) {
                return [
                    'errors' => [
                        $prefix => __('Unable to save medication administration.', 'jm-referral-system'),
                    ],
                ];
            }
        }

        $this->activity_service->log_medication_administered(
            $referral_id,
            $med_name,
            $status_labels[$status] ?? $status
        );

        return ['saved' => true];
    }

    /**
     * @param array<string, mixed> $medication
     */
    private function medication_active_on_date(array $medication, string $visit_date): bool
    {
        if (MedicationStatuses::ACTIVE !== (string) ($medication['medication_status'] ?? '')) {
            return false;
        }

        if ('' === $visit_date) {
            return true;
        }

        $start = trim((string) ($medication['start_date'] ?? ''));
        $end   = trim((string) ($medication['end_date'] ?? ''));

        if ('' !== $start && $visit_date < $start) {
            return false;
        }

        if ('' !== $end && $visit_date > $end) {
            return false;
        }

        return true;
    }

    private function parse_datetime(string $value): ?DateTimeImmutable
    {
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
