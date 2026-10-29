<?php

namespace JMReferral\Residents;

use JMReferral\Homes\BedroomRepository;
use JMReferral\Homes\HomeRepository;
use JMReferral\Homes\OccupancyRepository;
use JMReferral\LocalAuthority\LocalAuthorityRepository;
use JMReferral\Referral\CareSetting;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Settings\ModuleSettings;
use JMReferral\Users\UserProvider;

/**
 * Phase 5F.1: checks one existing resident's details before anything is saved.
 *
 * Works on plain strings from either the single-resident form or one import row,
 * so both routes apply exactly the same rules. Returns cleaned values ready for
 * the registration service and plain-English errors keyed by field.
 */
class ExistingResidentValidator
{
    /** Every field the registration service understands. */
    public const FIELDS = [
        'first_name',
        'last_name',
        'date_of_birth',
        'phone',
        'email',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'care_setting',
        'bedroom_id',
        'move_in_date',
        'admission_date',
        'care_start_date',
        'service_type_id',
        'assigned_to',
        'funding_type',
        'local_authority_id',
        'funder_name',
        'funding_reference',
        'funding_start_date',
        'weekly_amount',
        'weekly_support_hours',
        'package_summary',
        'notes',
    ];

    public function __construct(
        private ReferralRepository $referral_repository,
        private BedroomRepository $bedroom_repository,
        private HomeRepository $home_repository,
        private OccupancyRepository $occupancy_repository,
        private ServiceTypeService $service_type_service,
        private UserProvider $user_provider,
        private LocalAuthorityRepository $local_authority_repository
    ) {
    }

    /**
     * @param array<string, mixed> $input Raw strings keyed by FIELDS (missing keys are treated as empty).
     * @return array{values: array<string, string>, errors: array<string, string>}
     */
    public function validate(array $input): array
    {
        $v      = $this->clean($input);
        $errors = [];
        $today  = DateInput::today();

        // Who.
        if ('' === $v['first_name']) {
            $errors['first_name'] = __('First name is required.', 'jm-referral-system');
        } elseif (mb_strlen($v['first_name']) > ExistingResident::NAME_MAX) {
            $errors['first_name'] = __('First name is too long.', 'jm-referral-system');
        }

        if ('' === $v['last_name']) {
            $errors['last_name'] = __('Last name is required.', 'jm-referral-system');
        } elseif (mb_strlen($v['last_name']) > ExistingResident::NAME_MAX) {
            $errors['last_name'] = __('Last name is too long.', 'jm-referral-system');
        }

        $dob = $this->date_field($v, 'date_of_birth', __('Date of birth', 'jm-referral-system'), true, $errors);
        if (null !== $dob && $dob >= $today) {
            $errors['date_of_birth'] = __('Date of birth must be in the past.', 'jm-referral-system');
            $dob = null;
        }

        if ('' !== $v['email'] && ! is_email($v['email'])) {
            $errors['email'] = __('Email address is not valid.', 'jm-referral-system');
        }

        if (mb_strlen($v['phone']) > 50) {
            $errors['phone'] = __('Phone number is too long.', 'jm-referral-system');
        }

        // When.
        $admission = $this->date_field($v, 'admission_date', __('Admission date', 'jm-referral-system'), true, $errors);
        $care      = $this->date_field($v, 'care_start_date', __('Care start date', 'jm-referral-system'), true, $errors);

        if (null !== $admission && $admission > $today) {
            $errors['admission_date'] = __('Admission date cannot be in the future. Use the Referral process for someone who has not started yet.', 'jm-referral-system');
        }
        if (null !== $care && $care > $today) {
            $errors['care_start_date'] = __('Care start date cannot be in the future. Use the Referral process for someone who has not started yet.', 'jm-referral-system');
        }
        if (null !== $dob && null !== $admission && $admission < $dob) {
            $errors['admission_date'] = __('Admission date is before the date of birth.', 'jm-referral-system');
        }
        if (null !== $dob && null !== $care && $care < $dob) {
            $errors['care_start_date'] = __('Care start date is before the date of birth.', 'jm-referral-system');
        }

        // Where.
        $setting = $v['care_setting'];
        if ('' === $setting) {
            $errors['care_setting'] = __('Care setting is required (Supported Living or Own Home).', 'jm-referral-system');
        } elseif (! in_array($setting, [CareSetting::SUPPORTED_LIVING, CareSetting::OWN_HOME], true)) {
            $errors['care_setting'] = __('Care setting must be Supported Living or Own Home.', 'jm-referral-system');
        }

        if (CareSetting::SUPPORTED_LIVING === $setting) {
            $this->check_bedroom($v, $admission, $today, $errors);
        } else {
            $v['bedroom_id']   = '';
            $v['move_in_date'] = '';
        }

        if (CareSetting::OWN_HOME === $setting) {
            if ('' === $v['address_line_1']) {
                $errors['address_line_1'] = __('Address line 1 is required for care in the client\'s own home.', 'jm-referral-system');
            }
            if ('' === $v['postcode']) {
                $errors['postcode'] = __('Postcode is required for care in the client\'s own home.', 'jm-referral-system');
            }
        }

        foreach (['address_line_1', 'address_line_2'] as $key) {
            if (mb_strlen($v[$key]) > 255) {
                $errors[$key] = __('Address line is too long.', 'jm-referral-system');
            }
        }
        if (mb_strlen($v['city']) > 150) {
            $errors['city'] = __('Town or city is too long.', 'jm-referral-system');
        }
        if (mb_strlen($v['postcode']) > 30) {
            $errors['postcode'] = __('Postcode is too long.', 'jm-referral-system');
        }

        // Optional links.
        $service_type_id = absint($v['service_type_id']);
        if ($service_type_id > 0 && ! $this->service_type_service->is_selectable($service_type_id)) {
            $errors['service_type_id'] = __('The selected service is not available.', 'jm-referral-system');
        }
        $v['service_type_id'] = $service_type_id > 0 ? (string) $service_type_id : '';

        $assigned_to = absint($v['assigned_to']);
        if ($assigned_to > 0 && ! $this->user_provider->is_assignable($assigned_to)) {
            $errors['assigned_to'] = __('The selected owner cannot be assigned clients.', 'jm-referral-system');
        }
        $v['assigned_to'] = $assigned_to > 0 ? (string) $assigned_to : '';

        $this->check_funding($v, $dob, $errors);

        if (mb_strlen($v['notes']) > ExistingResident::NOTES_MAX) {
            $errors['notes'] = __('Notes are too long.', 'jm-referral-system');
        }

        // Same person already on the platform.
        if (! isset($errors['first_name'], $errors['last_name']) && null !== $dob) {
            $matches = $this->referral_repository->find_by_client_identity($v['first_name'], $v['last_name'], $dob, 1);
            if ([] !== $matches) {
                $errors['first_name'] = sprintf(
                    /* translators: %s: existing record number */
                    __('Someone with this name and date of birth is already on the platform (%s). Check the existing record instead of registering them again.', 'jm-referral-system'),
                    $matches[0]['referral_number']
                );
            }
        }

        return ['values' => $v, 'errors' => $errors];
    }

    /**
     * Checks a whole import together: the same bedroom or person may not appear twice.
     *
     * @param array<int, array<string, mixed>> $rows Raw rows keyed by spreadsheet row number.
     * @return array{values: array<int, array<string, string>>, errors: array<int, array<string, string>>}
     */
    public function validate_batch(array $rows): array
    {
        $values = [];
        $errors = [];
        $seen_bedrooms = [];
        $seen_people   = [];

        foreach ($rows as $row_number => $row) {
            $result = $this->validate($row);
            $values[$row_number] = $result['values'];
            $row_errors = $result['errors'];

            $bedroom_id = absint($result['values']['bedroom_id'] ?? 0);
            if ($bedroom_id > 0 && ! isset($row_errors['bedroom_id'])) {
                if (isset($seen_bedrooms[$bedroom_id])) {
                    $row_errors['bedroom_id'] = sprintf(
                        /* translators: %d: spreadsheet row number */
                        __('This bedroom is also given to the resident on row %d. Each bedroom can only have one resident.', 'jm-referral-system'),
                        $seen_bedrooms[$bedroom_id]
                    );
                } else {
                    $seen_bedrooms[$bedroom_id] = $row_number;
                }
            }

            $person = strtolower(trim((string) $result['values']['first_name'])) . '|'
                . strtolower(trim((string) $result['values']['last_name'])) . '|'
                . (string) $result['values']['date_of_birth'];
            if ('||' !== $person && '' !== (string) $result['values']['date_of_birth'] && ! isset($row_errors['first_name'])) {
                if (isset($seen_people[$person])) {
                    $row_errors['first_name'] = sprintf(
                        /* translators: %d: spreadsheet row number */
                        __('This person is also on row %d.', 'jm-referral-system'),
                        $seen_people[$person]
                    );
                } else {
                    $seen_people[$person] = $row_number;
                }
            }

            if ([] !== $row_errors) {
                $errors[$row_number] = $row_errors;
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function clean(array $input): array
    {
        $v = [];
        foreach (self::FIELDS as $key) {
            $raw = $input[$key] ?? '';
            if (is_array($raw) || is_object($raw)) {
                $raw = '';
            }
            $raw = (string) $raw;
            $v[$key] = in_array($key, ['package_summary', 'notes'], true)
                ? sanitize_textarea_field($raw)
                : sanitize_text_field($raw);
        }

        $v['care_setting'] = sanitize_key($v['care_setting']);
        $v['funding_type'] = sanitize_key($v['funding_type']);
        $v['postcode']     = strtoupper($v['postcode']);

        return $v;
    }

    /**
     * Parses a date field in place. Returns Y-m-d or null.
     *
     * @param array<string, string> $v
     * @param array<string, string> $errors
     */
    private function date_field(array &$v, string $key, string $label, bool $required, array &$errors): ?string
    {
        $raw = trim($v[$key]);
        if ('' === $raw) {
            if ($required) {
                $errors[$key] = sprintf(
                    /* translators: %s: field label */
                    __('%s is required.', 'jm-referral-system'),
                    $label
                );
            }

            return null;
        }

        $date = DateInput::parse($raw);
        if (null === $date) {
            $errors[$key] = sprintf(
                /* translators: 1: field label, 2: value typed */
                __('%1$s "%2$s" is not a date. Use day/month/year, for example 01/03/2024.', 'jm-referral-system'),
                $label,
                $raw
            );

            return null;
        }

        $v[$key] = $date;

        return $date;
    }

    /**
     * @param array<string, string> $v
     * @param array<string, string> $errors
     */
    private function check_bedroom(array &$v, ?string $admission, string $today, array &$errors): void
    {
        if (! ModuleSettings::is_enabled(ModuleSettings::SUPPORTED_LIVING)) {
            $errors['care_setting'] = __('Supported Living is switched off in Settings, so residents cannot be placed in a bedroom.', 'jm-referral-system');

            return;
        }

        $bedroom_id = absint($v['bedroom_id']);
        $v['bedroom_id'] = $bedroom_id > 0 ? (string) $bedroom_id : '';

        if ($bedroom_id <= 0) {
            $errors['bedroom_id'] = __('Choose the property and bedroom the resident lives in now.', 'jm-referral-system');
        } else {
            $bedroom = $this->bedroom_repository->find($bedroom_id);
            $home    = is_array($bedroom) ? $this->home_repository->find(absint($bedroom['home_id'] ?? 0)) : null;

            if (null === $bedroom || null === $home) {
                $errors['bedroom_id'] = __('The selected bedroom was not found.', 'jm-referral-system');
            } elseif ('active' !== (string) ($home['status'] ?? '')) {
                $errors['bedroom_id'] = __('That property is not active.', 'jm-referral-system');
            } elseif ('active' !== (string) ($bedroom['status'] ?? '')) {
                $errors['bedroom_id'] = __('That bedroom is not active.', 'jm-referral-system');
            } elseif (null !== $this->occupancy_repository->current_for_bedroom($bedroom_id)) {
                $errors['bedroom_id'] = sprintf(
                    /* translators: 1: property name, 2: room label */
                    __('%1$s, %2$s already has a resident on the platform.', 'jm-referral-system'),
                    (string) ($home['name'] ?? ''),
                    (string) ($bedroom['room_label'] ?? '')
                );
            }
        }

        // Date they moved into their current bedroom: defaults to the admission date.
        if ('' === trim($v['move_in_date'])) {
            if (null !== $admission) {
                $v['move_in_date'] = $admission;
            }

            return;
        }

        $move_in = $this->date_field($v, 'move_in_date', __('Moved into current bedroom', 'jm-referral-system'), false, $errors);
        if (null === $move_in) {
            return;
        }
        if ($move_in > $today) {
            $errors['move_in_date'] = __('The date they moved into their current bedroom cannot be in the future.', 'jm-referral-system');
        } elseif (null !== $admission && $move_in < $admission) {
            $errors['move_in_date'] = __('The date they moved into their current bedroom cannot be before their admission date.', 'jm-referral-system');
        }
    }

    /**
     * @param array<string, string> $v
     * @param array<string, string> $errors
     */
    private function check_funding(array &$v, ?string $dob, array &$errors): void
    {
        $any = '' !== $v['funding_type']
            || absint($v['local_authority_id']) > 0
            || '' !== $v['funder_name']
            || '' !== $v['funding_reference']
            || '' !== $v['funding_start_date']
            || '' !== $v['weekly_amount']
            || '' !== $v['weekly_support_hours']
            || '' !== $v['package_summary'];

        if (! $any) {
            $v['local_authority_id'] = '';

            return;
        }

        if ('' === $v['funding_type']) {
            $errors['funding_type'] = __('Choose how the care is funded, or leave all funding details blank.', 'jm-referral-system');
        } elseif (! ExistingResident::is_valid_funding_type($v['funding_type'])) {
            $errors['funding_type'] = __('Funding type is not recognised.', 'jm-referral-system');
        }

        $la_id = absint($v['local_authority_id']);
        if ($la_id > 0) {
            $la = $this->local_authority_repository->find($la_id);
            if (null === $la) {
                $errors['local_authority_id'] = __('The selected Local Authority was not found.', 'jm-referral-system');
                $la_id = 0;
            }
        }
        $v['local_authority_id'] = $la_id > 0 ? (string) $la_id : '';

        if (in_array($v['funding_type'], [ExistingResident::FUNDING_LOCAL_AUTHORITY, ExistingResident::FUNDING_JOINT], true)
            && $la_id <= 0
            && '' === $v['funder_name']
        ) {
            $errors['local_authority_id'] = __('Choose the funding Local Authority.', 'jm-referral-system');
        }

        if (mb_strlen($v['funder_name']) > ExistingResident::FUNDER_NAME_MAX) {
            $errors['funder_name'] = __('Funder name is too long.', 'jm-referral-system');
        }
        if (mb_strlen($v['funding_reference']) > ExistingResident::REFERENCE_MAX) {
            $errors['funding_reference'] = __('Funding reference is too long.', 'jm-referral-system');
        }
        if (mb_strlen($v['package_summary']) > ExistingResident::PACKAGE_SUMMARY_MAX) {
            $errors['package_summary'] = __('Support package description is too long.', 'jm-referral-system');
        }

        $start = $this->date_field($v, 'funding_start_date', __('Funding start date', 'jm-referral-system'), false, $errors);
        if (null !== $start && null !== $dob && $start < $dob) {
            $errors['funding_start_date'] = __('Funding start date is before the date of birth.', 'jm-referral-system');
        }

        $v['weekly_amount'] = $this->money($v['weekly_amount'], 'weekly_amount', $errors);
        $v['weekly_support_hours'] = $this->hours($v['weekly_support_hours'], $errors);
    }

    /**
     * @param array<string, string> $errors
     */
    private function money(string $raw, string $key, array &$errors): string
    {
        $clean = str_replace([',', '£', ' '], '', trim($raw));
        if ('' === $clean) {
            return '';
        }
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $clean) || (float) $clean > ExistingResident::WEEKLY_AMOUNT_MAX) {
            $errors[$key] = sprintf(
                /* translators: %s: value typed */
                __('Weekly package value "%s" is not an amount in pounds, for example 1250.00.', 'jm-referral-system'),
                $raw
            );

            return $raw;
        }

        return number_format((float) $clean, 2, '.', '');
    }

    /**
     * @param array<string, string> $errors
     */
    private function hours(string $raw, array &$errors): string
    {
        $clean = trim($raw);
        if ('' === $clean) {
            return '';
        }
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $clean) || (float) $clean > ExistingResident::WEEKLY_HOURS_MAX) {
            $errors['weekly_support_hours'] = sprintf(
                /* translators: %s: value typed */
                __('Weekly support hours "%s" must be a number of hours between 0 and 168.', 'jm-referral-system'),
                $raw
            );

            return $raw;
        }

        return number_format((float) $clean, 2, '.', '');
    }
}
