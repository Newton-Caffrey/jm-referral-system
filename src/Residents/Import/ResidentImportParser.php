<?php

namespace JMReferral\Residents\Import;

use JMReferral\Homes\BedroomRepository;
use JMReferral\Homes\HomeRepository;
use JMReferral\LocalAuthority\LocalAuthorityRepository;
use JMReferral\Referral\CareSetting;
use JMReferral\Residents\ExistingResident;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;

/**
 * Phase 5F.1: turns spreadsheet rows into the values the registration validator expects.
 *
 * Matches names typed in the spreadsheet (property, bedroom, Local Authority, service,
 * owner) to the records on the platform and explains anything it cannot match.
 * It does not save anything.
 */
class ResidentImportParser
{
    /** Columns without which no row can be registered. */
    public const REQUIRED_COLUMNS = ['first_name', 'last_name', 'date_of_birth', 'care_setting', 'admission_date', 'care_start_date'];

    /** @var array<string, int>|null */
    private ?array $home_ids = null;

    /** @var array<int, string> */
    private array $home_names = [];

    /** @var array<int, array<string, array<string, mixed>>> */
    private array $bedrooms_by_home = [];

    /** @var array<string, int>|null */
    private ?array $authority_ids = null;

    /** @var array<string, int>|null */
    private ?array $service_ids = null;

    /** @var array<int, string> */
    private array $service_names = [];

    public function __construct(
        private HomeRepository $home_repository,
        private BedroomRepository $bedroom_repository,
        private LocalAuthorityRepository $local_authority_repository,
        private ServiceTypeService $service_type_service,
        private UserProvider $user_provider
    ) {
    }

    /**
     * @param array<int, array<int, string>> $rows Spreadsheet rows keyed by row number.
     * @return array{
     *     ok: bool,
     *     error: string,
     *     rows: array<int, array<string, string>>,
     *     row_errors: array<int, array<string, string>>,
     *     row_notes: array<int, array<int, string>>,
     *     display: array<int, array<string, string>>,
     *     skipped_examples: int,
     *     ignored_columns: array<int, string>
     * }
     */
    public function parse(array $rows): array
    {
        $out = [
            'ok'               => false,
            'error'            => '',
            'rows'             => [],
            'row_errors'       => [],
            'row_notes'        => [],
            'display'          => [],
            'skipped_examples' => 0,
            'ignored_columns'  => [],
        ];

        $header_number = null;
        foreach ($rows as $number => $cells) {
            if ($this->is_blank($cells)) {
                continue;
            }
            $header_number = (int) $number;
            break;
        }

        if (null === $header_number) {
            $out['error'] = __('The file is empty.', 'jm-referral-system');

            return $out;
        }

        $map     = ResidentImportColumns::heading_map();
        $columns = [];
        foreach ($rows[$header_number] as $index => $heading) {
            $normal = ResidentImportColumns::normalise((string) $heading);
            if ('' === $normal) {
                continue;
            }
            if (isset($map[$normal]) && ! in_array($map[$normal], $columns, true)) {
                $columns[$index] = $map[$normal];
            } else {
                $out['ignored_columns'][] = (string) $heading;
            }
        }

        $defs    = ResidentImportColumns::definitions();
        $missing = [];
        foreach (self::REQUIRED_COLUMNS as $key) {
            if (! in_array($key, $columns, true)) {
                $missing[] = $defs[$key]['heading'];
            }
        }
        if ([] !== $missing) {
            $out['error'] = sprintf(
                /* translators: %s: list of column headings */
                __('The first row must contain the column headings from the template. Missing: %s.', 'jm-referral-system'),
                implode(', ', $missing)
            );

            return $out;
        }

        foreach ($rows as $number => $cells) {
            $number = (int) $number;
            if ($number <= $header_number || $this->is_blank($cells)) {
                continue;
            }

            $raw = [];
            foreach ($defs as $key => $def) {
                $raw[$key] = '';
            }
            foreach ($columns as $index => $key) {
                $raw[$key] = trim((string) ($cells[$index] ?? ''));
            }

            if (0 === stripos($raw['first_name'], 'example')) {
                ++$out['skipped_examples'];
                continue;
            }

            foreach (ResidentImportColumns::date_keys() as $key) {
                $serial = SpreadsheetReader::excel_serial_to_date($raw[$key]);
                if (null !== $serial) {
                    $raw[$key] = $serial;
                }
            }

            $errors = [];
            $notes  = [];
            $input  = $this->resolve($raw, $errors, $notes);

            $out['rows'][$number]    = $input;
            $out['display'][$number] = [
                'name'     => trim($raw['first_name'] . ' ' . $raw['last_name']),
                'setting'  => $raw['care_setting'],
                'location' => CareSetting::SUPPORTED_LIVING === $input['care_setting']
                    ? trim($raw['property'] . ', ' . $raw['bedroom'], ', ')
                    : ('' !== $raw['postcode'] ? $raw['postcode'] : $raw['address_line_1']),
                'funding'  => '' !== $input['funding_type']
                    ? ExistingResident::funding_type_label($input['funding_type'])
                    : '',
            ];
            if ([] !== $errors) {
                $out['row_errors'][$number] = $errors;
            }
            if ([] !== $notes) {
                $out['row_notes'][$number] = $notes;
            }
        }

        if ([] === $out['rows']) {
            $out['error'] = __('No residents were found under the column headings. Add one resident per row and try again.', 'jm-referral-system');

            return $out;
        }

        $out['ok'] = true;

        return $out;
    }

    /**
     * Maps one row's text to validator input, recording what could not be matched.
     *
     * @param array<string, string> $raw
     * @param array<string, string> $errors
     * @param array<int, string>    $notes
     * @return array<string, string>
     */
    private function resolve(array $raw, array &$errors, array &$notes): array
    {
        $input = [
            'first_name'           => $raw['first_name'],
            'last_name'            => $raw['last_name'],
            'date_of_birth'        => $raw['date_of_birth'],
            'phone'                => $raw['phone'],
            'email'                => $raw['email'],
            'address_line_1'       => $raw['address_line_1'],
            'address_line_2'       => $raw['address_line_2'],
            'city'                 => $raw['city'],
            'postcode'             => $raw['postcode'],
            'care_setting'         => '',
            'bedroom_id'           => '',
            'move_in_date'         => $raw['move_in_date'],
            'admission_date'       => $raw['admission_date'],
            'care_start_date'      => $raw['care_start_date'],
            'service_type_id'      => '',
            'assigned_to'          => '',
            'funding_type'         => '',
            'local_authority_id'   => '',
            'funder_name'          => '',
            'funding_reference'    => $raw['funding_reference'],
            'funding_start_date'   => $raw['funding_start_date'],
            'weekly_amount'        => $raw['weekly_amount'],
            'weekly_support_hours' => $raw['weekly_support_hours'],
            'package_summary'      => $raw['package_summary'],
            'notes'                => $raw['notes'],
        ];

        // Care setting.
        $setting = $this->care_setting_from_text($raw['care_setting']);
        if ('' !== $raw['care_setting'] && '' === $setting) {
            $errors['care_setting'] = sprintf(
                /* translators: %s: value typed */
                __('Care setting "%s" is not recognised. Use Supported Living or Own Home.', 'jm-referral-system'),
                $raw['care_setting']
            );
        }
        $input['care_setting'] = $setting;

        // Property and bedroom.
        if (CareSetting::SUPPORTED_LIVING === $setting) {
            $this->resolve_bedroom($raw['property'], $raw['bedroom'], $input, $errors);
        } elseif (CareSetting::OWN_HOME === $setting && ('' !== $raw['property'] || '' !== $raw['bedroom'])) {
            $errors['bedroom_id'] = __('A property or bedroom is filled in, but the care setting is Own Home. Clear them or change the care setting to Supported Living.', 'jm-referral-system');
        }

        // Service.
        if ('' !== $raw['service']) {
            $this->load_services();
            $key = ResidentImportColumns::normalise($raw['service']);
            if (isset($this->service_ids[$key])) {
                $input['service_type_id'] = (string) $this->service_ids[$key];
            } else {
                $errors['service_type_id'] = sprintf(
                    /* translators: 1: value typed, 2: list of services */
                    __('Service "%1$s" is not set up on the platform. Available: %2$s.', 'jm-referral-system'),
                    $raw['service'],
                    $this->short_list($this->service_names)
                );
            }
        }

        // Owner.
        if ('' !== $raw['owner']) {
            $user = is_email($raw['owner'])
                ? get_user_by('email', $raw['owner'])
                : get_user_by('login', $raw['owner']);
            if ($user instanceof \WP_User && $this->user_provider->is_assignable((int) $user->ID)) {
                $input['assigned_to'] = (string) $user->ID;
            } else {
                $errors['assigned_to'] = sprintf(
                    /* translators: %s: value typed */
                    __('Owner "%s" is not a staff member who can be given clients. Use their email address.', 'jm-referral-system'),
                    $raw['owner']
                );
            }
        }

        // Funding.
        if ('' !== $raw['funding_type']) {
            $type = ExistingResident::funding_type_from_text($raw['funding_type']);
            if ('' === $type) {
                $errors['funding_type'] = sprintf(
                    /* translators: %s: value typed */
                    __('Funding type "%s" is not recognised. Use Local Authority, NHS Continuing Healthcare, Joint funded, Self-funded or Other.', 'jm-referral-system'),
                    $raw['funding_type']
                );
            }
            $input['funding_type'] = $type;
        }

        if ('' !== $raw['funding_authority']) {
            $this->load_authorities();
            $key = ResidentImportColumns::normalise($raw['funding_authority']);
            if (isset($this->authority_ids[$key])) {
                $input['local_authority_id'] = (string) $this->authority_ids[$key];
            } else {
                $input['funder_name'] = $raw['funding_authority'];
                if (in_array($input['funding_type'], [ExistingResident::FUNDING_LOCAL_AUTHORITY, ExistingResident::FUNDING_JOINT], true)) {
                    $notes[] = sprintf(
                        /* translators: %s: authority name typed */
                        __('"%s" is not in your Local Authority list, so it will be saved as written.', 'jm-referral-system'),
                        $raw['funding_authority']
                    );
                }
            }
        }

        return $input;
    }

    /**
     * @param array<string, string> $input
     * @param array<string, string> $errors
     */
    private function resolve_bedroom(string $property, string $bedroom, array &$input, array &$errors): void
    {
        if ('' === $property || '' === $bedroom) {
            $errors['bedroom_id'] = __('Property and Bedroom are required for Supported Living.', 'jm-referral-system');

            return;
        }

        $this->load_homes();
        $home_key = ResidentImportColumns::normalise($property);
        if (! isset($this->home_ids[$home_key])) {
            $errors['bedroom_id'] = sprintf(
                /* translators: 1: property typed, 2: list of property names */
                __('Property "%1$s" is not on the platform. Properties: %2$s.', 'jm-referral-system'),
                $property,
                $this->short_list($this->home_names)
            );

            return;
        }

        $home_id  = $this->home_ids[$home_key];
        $rooms    = $this->bedrooms_by_home[$home_id] ?? [];
        $room_key = ResidentImportColumns::normalise($bedroom);
        if (! isset($rooms[$room_key])) {
            $labels = array_map(static fn (array $room): string => (string) ($room['room_label'] ?? ''), array_values($rooms));
            $errors['bedroom_id'] = sprintf(
                /* translators: 1: bedroom typed, 2: property name, 3: list of bedrooms */
                __('Bedroom "%1$s" is not set up at %2$s. Bedrooms there: %3$s.', 'jm-referral-system'),
                $bedroom,
                $this->home_names[$home_id] ?? $property,
                [] !== $labels ? $this->short_list($labels) : __('none yet', 'jm-referral-system')
            );

            return;
        }

        $input['bedroom_id'] = (string) absint($rooms[$room_key]['id'] ?? 0);
    }

    private function care_setting_from_text(string $text): string
    {
        $key = ResidentImportColumns::normalise(str_replace(["'", '’'], '', $text));
        if ('' === $key) {
            return '';
        }

        $supported = ['supported living', 'sl', 'supported', 'supported accommodation', 'supported housing'];
        $own_home  = ['own home', 'clients own home', 'client own home', 'home care', 'domiciliary', 'domiciliary care', 'community'];

        if (in_array($key, $supported, true) || CareSetting::SUPPORTED_LIVING === str_replace(' ', '_', $key)) {
            return CareSetting::SUPPORTED_LIVING;
        }
        if (in_array($key, $own_home, true) || CareSetting::OWN_HOME === str_replace(' ', '_', $key)) {
            return CareSetting::OWN_HOME;
        }

        return '';
    }

    private function load_homes(): void
    {
        if (null !== $this->home_ids) {
            return;
        }

        $this->home_ids = [];
        foreach ($this->home_repository->query([]) as $home) {
            $id   = absint($home['id'] ?? 0);
            $name = (string) ($home['name'] ?? '');
            $this->home_ids[ResidentImportColumns::normalise($name)] = $id;
            $this->home_names[$id] = $name;

            $this->bedrooms_by_home[$id] = [];
            foreach ($this->bedroom_repository->list_by_home($id) as $room) {
                $this->bedrooms_by_home[$id][ResidentImportColumns::normalise((string) ($room['room_label'] ?? ''))] = $room;
            }
        }
    }

    private function load_authorities(): void
    {
        if (null !== $this->authority_ids) {
            return;
        }

        $this->authority_ids = [];
        foreach ($this->local_authority_repository->list([]) as $la) {
            $id = absint($la['id'] ?? 0);
            $this->authority_ids[ResidentImportColumns::normalise((string) ($la['name'] ?? ''))] = $id;
            $this->authority_ids[ResidentImportColumns::normalise(str_replace('-', ' ', (string) ($la['slug'] ?? '')))] = $id;
        }
    }

    private function load_services(): void
    {
        if (null !== $this->service_ids) {
            return;
        }

        $this->service_ids = [];
        foreach ($this->service_type_service->get_active() as $service) {
            $id   = absint($service['id'] ?? 0);
            $name = (string) ($service['name'] ?? '');
            $this->service_ids[ResidentImportColumns::normalise($name)] = $id;
            $this->service_names[$id] = $name;
        }
    }

    /**
     * @param array<int|string, string> $names
     */
    private function short_list(array $names): string
    {
        $names = array_values(array_filter(array_map('strval', $names), static fn (string $n): bool => '' !== $n));
        if ([] === $names) {
            return __('none set up yet', 'jm-referral-system');
        }

        $shown = array_slice($names, 0, 12);
        $text  = implode(', ', $shown);
        if (count($names) > 12) {
            $text .= sprintf(
                /* translators: %d: number not shown */
                __(' and %d more', 'jm-referral-system'),
                count($names) - 12
            );
        }

        return $text;
    }

    /**
     * @param array<int, string> $cells
     */
    private function is_blank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ('' !== trim((string) $cell)) {
                return false;
            }
        }

        return true;
    }
}
