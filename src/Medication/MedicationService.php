<?php

namespace JMReferral\Medication;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;

class MedicationService
{
    /** @deprecated Use MedicationStatuses::ACTIVE */
    public const STATUS_ACTIVE = MedicationStatuses::ACTIVE;
    /** @deprecated Use MedicationStatuses::PAUSED */
    public const STATUS_PAUSED = MedicationStatuses::PAUSED;
    /** @deprecated Use MedicationStatuses::DISCONTINUED */
    public const STATUS_DISCONTINUED = MedicationStatuses::DISCONTINUED;

    public const ROUTE_ORAL = 'oral';
    public const ROUTE_TOPICAL = 'topical';
    public const ROUTE_INHALED = 'inhaled';
    public const ROUTE_SUBLINGUAL = 'sublingual';
    public const ROUTE_EYE = 'eye';
    public const ROUTE_EAR = 'ear';
    public const ROUTE_NASAL = 'nasal';
    public const ROUTE_INJECTION = 'injection';
    public const ROUTE_OTHER = 'other';

    /**
     * @return array<int, string>
     */
    public static function allowed_statuses(): array
    {
        return MedicationStatuses::all();
    }

    /**
     * @return array<string, string>
     */
    public static function status_labels(): array
    {
        return MedicationStatuses::labels();
    }

    /**
     * @return array<int, string>
     */
    public static function allowed_routes(): array
    {
        return [
            self::ROUTE_ORAL,
            self::ROUTE_TOPICAL,
            self::ROUTE_INHALED,
            self::ROUTE_SUBLINGUAL,
            self::ROUTE_EYE,
            self::ROUTE_EAR,
            self::ROUTE_NASAL,
            self::ROUTE_INJECTION,
            self::ROUTE_OTHER,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function route_labels(): array
    {
        return [
            self::ROUTE_ORAL       => __('Oral', 'jm-referral-system'),
            self::ROUTE_TOPICAL    => __('Topical', 'jm-referral-system'),
            self::ROUTE_INHALED    => __('Inhaled', 'jm-referral-system'),
            self::ROUTE_SUBLINGUAL => __('Sublingual', 'jm-referral-system'),
            self::ROUTE_EYE        => __('Eye', 'jm-referral-system'),
            self::ROUTE_EAR        => __('Ear', 'jm-referral-system'),
            self::ROUTE_NASAL      => __('Nasal', 'jm-referral-system'),
            self::ROUTE_INJECTION  => __('Injection', 'jm-referral-system'),
            self::ROUTE_OTHER      => __('Other', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function empty_form_data(): array
    {
        return [
            'medication_name'     => '',
            'strength'            => '',
            'dosage'              => '',
            'route'               => '',
            'frequency'           => '',
            'instructions'        => '',
            'start_date'          => '',
            'end_date'            => '',
            'medication_status'   => MedicationStatuses::ACTIVE,
            'prescribing_source'  => '',
        ];
    }

    /**
     * @param array<string, mixed>|null $medication
     * @return array<string, string>
     */
    public static function map_to_form_data(?array $medication): array
    {
        $data = self::empty_form_data();
        if (null === $medication) {
            return $data;
        }

        foreach (array_keys($data) as $key) {
            $data[$key] = (string) ($medication[$key] ?? $data[$key]);
        }

        return $data;
    }

    public function __construct(
        private MedicationRepository $medication_repository,
        private ReferralRepository $referral_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * @param array<string, string> $input
     * @return array{id: int, created: bool}|array{errors: array<string, string>}|false
     */
    public function save(int $referral_id, array $input, int $medication_id = 0): array|false
    {
        $errors = $this->validate($referral_id, $input, $medication_id);
        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $existing = $medication_id > 0 ? $this->medication_repository->find($medication_id) : null;
        if ($medication_id > 0 && null === $existing) {
            return [
                'errors' => [
                    'medication' => __('Medication not found.', 'jm-referral-system'),
                ],
            ];
        }

        if (null !== $existing && absint($existing['referral_id'] ?? 0) !== $referral_id) {
            return [
                'errors' => [
                    'medication' => __('Medication does not belong to this referral.', 'jm-referral-system'),
                ],
            ];
        }

        $now    = current_time('mysql');
        $name   = trim((string) ($input['medication_name'] ?? ''));
        $status = sanitize_key((string) ($input['medication_status'] ?? MedicationStatuses::ACTIVE));
        $old_status = is_array($existing) ? (string) ($existing['medication_status'] ?? '') : '';

        $payload = [
            'medication_name'    => $name,
            'strength'           => $this->nullable_text((string) ($input['strength'] ?? '')),
            'dosage'             => trim((string) ($input['dosage'] ?? '')),
            'route'              => sanitize_key((string) ($input['route'] ?? '')),
            'frequency'          => $this->nullable_text((string) ($input['frequency'] ?? '')),
            'instructions'       => $this->nullable_text((string) ($input['instructions'] ?? '')),
            'start_date'         => $this->nullable_date((string) ($input['start_date'] ?? '')),
            'end_date'           => $this->nullable_date((string) ($input['end_date'] ?? '')),
            'medication_status'  => $status,
            'prescribing_source' => $this->nullable_text((string) ($input['prescribing_source'] ?? '')),
            'updated_at'         => $now,
        ];

        if (null === $existing) {
            $payload['referral_id'] = $referral_id;
            $payload['created_by']  = get_current_user_id();
            $payload['created_at']  = $now;

            $id = $this->medication_repository->create($payload);
            if (false === $id) {
                return false;
            }

            $this->activity_service->log_medication_added($referral_id, $name);

            return [
                'id'      => $id,
                'created' => true,
            ];
        }

        $updated = $this->medication_repository->update($medication_id, $payload);
        if (! $updated) {
            return false;
        }

        $this->activity_service->log_medication_updated($referral_id, $name);

        if ('' !== $old_status && $old_status !== $status) {
            $labels = self::status_labels();
            $this->activity_service->log_medication_status_changed(
                $referral_id,
                $labels[$old_status] ?? $old_status,
                $labels[$status] ?? $status
            );
        }

        return [
            'id'      => $medication_id,
            'created' => false,
        ];
    }

    /**
     * @return array{medication: array<string, mixed>, referral: array<string, mixed>}|array{errors: array<string, string>}
     */
    public function prepare_edit(int $medication_id): array
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_MEDICATIONS)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to manage medications.', 'jm-referral-system'),
                ],
            ];
        }

        $medication = $this->medication_repository->find($medication_id);
        if (null === $medication) {
            return [
                'errors' => [
                    'medication' => __('Medication not found.', 'jm-referral-system'),
                ],
            ];
        }

        $referral_id = absint($medication['referral_id'] ?? 0);
        $referral    = $this->referral_repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_mutate_referral($referral)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to edit medications for this referral.', 'jm-referral-system'),
                ],
            ];
        }

        return [
            'medication' => $medication,
            'referral'   => $referral,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_medications_for_referral(int $referral_id, bool $include_inactive = true): array
    {
        return $this->medication_repository->get_by_referral($referral_id, $include_inactive);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_active_for_referral(int $referral_id): array
    {
        return $this->medication_repository->get_active_by_referral($referral_id);
    }

    public function count_active_for_referral(int $referral_id): int
    {
        return $this->medication_repository->count_active_by_referral($referral_id);
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_view_medications(array $referral): bool
    {
        return Capabilities::current_user_can(Capabilities::VIEW_MEDICATIONS)
            && $this->access_policy->can_view_referral($referral);
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_manage_medications(array $referral): bool
    {
        return Capabilities::current_user_can(Capabilities::MANAGE_MEDICATIONS)
            && $this->access_policy->can_mutate_referral($referral);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validate(int $referral_id, array $input, int $medication_id): array
    {
        $errors = [];

        if (! Capabilities::current_user_can(Capabilities::MANAGE_MEDICATIONS)) {
            $errors['permission'] = __('You do not have permission to manage medications.', 'jm-referral-system');
            return $errors;
        }

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            $errors['referral'] = __('Referral not found.', 'jm-referral-system');
            return $errors;
        }

        if (! $this->access_policy->can_mutate_referral($referral)) {
            $errors['permission'] = __('You do not have permission to manage medications for this referral.', 'jm-referral-system');
            return $errors;
        }

        if ('' === trim((string) ($input['medication_name'] ?? ''))) {
            $errors['medication_name'] = __('Medication name is required.', 'jm-referral-system');
        }

        if ('' === trim((string) ($input['dosage'] ?? ''))) {
            $errors['dosage'] = __('Dosage is required.', 'jm-referral-system');
        }

        $route = sanitize_key((string) ($input['route'] ?? ''));
        if ('' === $route) {
            $errors['route'] = __('Route is required.', 'jm-referral-system');
        } elseif (! in_array($route, self::allowed_routes(), true)) {
            $errors['route'] = __('Please select a valid route.', 'jm-referral-system');
        }

        $status = sanitize_key((string) ($input['medication_status'] ?? ''));
        if ('' === $status) {
            $errors['medication_status'] = __('Status is required.', 'jm-referral-system');
        } elseif (! in_array($status, self::allowed_statuses(), true)) {
            $errors['medication_status'] = __('Please select a valid medication status.', 'jm-referral-system');
        }

        $start = trim((string) ($input['start_date'] ?? ''));
        $end   = trim((string) ($input['end_date'] ?? ''));

        if ('' !== $start && ! $this->is_valid_date($start)) {
            $errors['start_date'] = __('Please enter a valid start date.', 'jm-referral-system');
        }

        if ('' !== $end && ! $this->is_valid_date($end)) {
            $errors['end_date'] = __('Please enter a valid end date.', 'jm-referral-system');
        }

        if (
            '' !== $start
            && '' !== $end
            && $this->is_valid_date($start)
            && $this->is_valid_date($end)
            && $end < $start
        ) {
            $errors['end_date'] = __('End date cannot be earlier than start date.', 'jm-referral-system');
        }

        unset($medication_id);

        return $errors;
    }

    private function nullable_text(string $value): ?string
    {
        $value = trim($value);

        return '' !== $value ? $value : null;
    }

    private function nullable_date(string $value): ?string
    {
        $value = trim($value);
        if ('' === $value || ! $this->is_valid_date($value)) {
            return null;
        }

        return $value;
    }

    private function is_valid_date(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        $parts = explode('-', $value);

        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }
}
