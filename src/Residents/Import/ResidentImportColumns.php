<?php

namespace JMReferral\Residents\Import;

/**
 * Phase 5F.1: the columns of the existing residents import spreadsheet.
 *
 * Headings are matched loosely (case, spaces, punctuation and a trailing * are ignored),
 * so staff can retype them or reorder columns.
 */
class ResidentImportColumns
{
    /**
     * @return array<string, array{heading: string, required: string, example: string, help: string, aliases: array<int, string>}>
     */
    public static function definitions(): array
    {
        return [
            'first_name' => [
                'heading'  => __('First name', 'jm-referral-system'),
                'required' => __('Yes', 'jm-referral-system'),
                'example'  => 'Peter',
                'help'     => '',
                'aliases'  => ['forename', 'firstname', 'given name'],
            ],
            'last_name' => [
                'heading'  => __('Last name', 'jm-referral-system'),
                'required' => __('Yes', 'jm-referral-system'),
                'example'  => 'Holloway',
                'help'     => '',
                'aliases'  => ['surname', 'lastname', 'family name'],
            ],
            'date_of_birth' => [
                'heading'  => __('Date of birth', 'jm-referral-system'),
                'required' => __('Yes', 'jm-referral-system'),
                'example'  => '14/02/1987',
                'help'     => __('Day/month/year.', 'jm-referral-system'),
                'aliases'  => ['dob', 'birth date'],
            ],
            'care_setting' => [
                'heading'  => __('Care setting', 'jm-referral-system'),
                'required' => __('Yes', 'jm-referral-system'),
                'example'  => 'Supported Living',
                'help'     => __('Supported Living or Own Home.', 'jm-referral-system'),
                'aliases'  => ['setting'],
            ],
            'property' => [
                'heading'  => __('Property', 'jm-referral-system'),
                'required' => __('Supported Living only', 'jm-referral-system'),
                'example'  => 'Oak House',
                'help'     => __('Exactly as the property is named on the platform.', 'jm-referral-system'),
                'aliases'  => ['home', 'property name', 'house', 'scheme'],
            ],
            'bedroom' => [
                'heading'  => __('Bedroom', 'jm-referral-system'),
                'required' => __('Supported Living only', 'jm-referral-system'),
                'example'  => 'Room 2',
                'help'     => __('Exactly as the bedroom is named on the platform.', 'jm-referral-system'),
                'aliases'  => ['room', 'bedroom name', 'room label'],
            ],
            'admission_date' => [
                'heading'  => __('Admission date', 'jm-referral-system'),
                'required' => __('Yes', 'jm-referral-system'),
                'example'  => '01/03/2021',
                'help'     => __('The day they joined your service.', 'jm-referral-system'),
                'aliases'  => ['admitted', 'admission'],
            ],
            'care_start_date' => [
                'heading'  => __('Care start date', 'jm-referral-system'),
                'required' => __('Yes', 'jm-referral-system'),
                'example'  => '01/03/2021',
                'help'     => __('The day your care and support began.', 'jm-referral-system'),
                'aliases'  => ['care commencement date', 'care started', 'start of care', 'care commenced'],
            ],
            'move_in_date' => [
                'heading'  => __('Moved into current bedroom', 'jm-referral-system'),
                'required' => __('No', 'jm-referral-system'),
                'example'  => '',
                'help'     => __('Only if they moved rooms after admission. Leave blank to use the admission date.', 'jm-referral-system'),
                'aliases'  => ['move in date', 'current bedroom since', 'moved in'],
            ],
            'phone' => [
                'heading'  => __('Phone', 'jm-referral-system'),
                'required' => __('No', 'jm-referral-system'),
                'example'  => '',
                'help'     => '',
                'aliases'  => ['telephone', 'phone number', 'mobile'],
            ],
            'email' => [
                'heading'  => __('Email', 'jm-referral-system'),
                'required' => __('No', 'jm-referral-system'),
                'example'  => '',
                'help'     => '',
                'aliases'  => ['email address'],
            ],
            'address_line_1' => [
                'heading'  => __('Address line 1', 'jm-referral-system'),
                'required' => __('Own Home only', 'jm-referral-system'),
                'example'  => '',
                'help'     => __('Home address for clients cared for in their own home.', 'jm-referral-system'),
                'aliases'  => ['address 1', 'address'],
            ],
            'address_line_2' => [
                'heading'  => __('Address line 2', 'jm-referral-system'),
                'required' => __('No', 'jm-referral-system'),
                'example'  => '',
                'help'     => '',
                'aliases'  => ['address 2'],
            ],
            'city' => [
                'heading'  => __('Town or city', 'jm-referral-system'),
                'required' => __('No', 'jm-referral-system'),
                'example'  => '',
                'help'     => '',
                'aliases'  => ['town', 'city'],
            ],
            'postcode' => [
                'heading'  => __('Postcode', 'jm-referral-system'),
                'required' => __('Own Home only', 'jm-referral-system'),
                'example'  => '',
                'help'     => '',
                'aliases'  => ['post code'],
            ],
            'service' => [
                'heading'  => __('Service', 'jm-referral-system'),
                'required' => __('No', 'jm-referral-system'),
                'example'  => '',
                'help'     => __('One of the service types set up on the platform.', 'jm-referral-system'),
                'aliases'  => ['service type'],
            ],
            'owner' => [
                'heading'  => __('Owner (staff email)', 'jm-referral-system'),
                'required' => __('No', 'jm-referral-system'),
                'example'  => '',
                'help'     => __('Email or username of the staff member responsible for them.', 'jm-referral-system'),
                'aliases'  => ['owner', 'assigned to', 'key worker', 'staff email'],
            ],
            'funding_type' => [
                'heading'  => __('Funding type', 'jm-referral-system'),
                'required' => __('If any funding is given', 'jm-referral-system'),
                'example'  => 'Local Authority',
                'help'     => __('Local Authority, NHS Continuing Healthcare, Joint funded, Self-funded or Other.', 'jm-referral-system'),
                'aliases'  => ['funding', 'funded by'],
            ],
            'funding_authority' => [
                'heading'  => __('Funding authority', 'jm-referral-system'),
                'required' => __('Local Authority or Joint funding', 'jm-referral-system'),
                'example'  => 'Leeds City Council',
                'help'     => __('Matched to the Local Authority list when the name is the same; otherwise stored as written.', 'jm-referral-system'),
                'aliases'  => ['local authority', 'funder', 'commissioner', 'funding body'],
            ],
            'funding_reference' => [
                'heading'  => __('Funding reference', 'jm-referral-system'),
                'required' => __('No', 'jm-referral-system'),
                'example'  => 'LCC-ASC-48213',
                'help'     => '',
                'aliases'  => ['reference', 'funding ref', 'contract reference', 'po number'],
            ],
            'funding_start_date' => [
                'heading'  => __('Funding start date', 'jm-referral-system'),
                'required' => __('No', 'jm-referral-system'),
                'example'  => '01/03/2021',
                'help'     => '',
                'aliases'  => ['funding start', 'funded from'],
            ],
            'weekly_amount' => [
                'heading'  => __('Weekly package value (£)', 'jm-referral-system'),
                'required' => __('No', 'jm-referral-system'),
                'example'  => '1250.00',
                'help'     => '',
                'aliases'  => ['weekly package value', 'weekly value', 'weekly cost', 'weekly fee', 'package value'],
            ],
            'weekly_support_hours' => [
                'heading'  => __('Weekly support hours', 'jm-referral-system'),
                'required' => __('No', 'jm-referral-system'),
                'example'  => '42',
                'help'     => '',
                'aliases'  => ['support hours', 'hours per week', 'weekly hours', 'commissioned hours'],
            ],
            'package_summary' => [
                'heading'  => __('Support package', 'jm-referral-system'),
                'required' => __('No', 'jm-referral-system'),
                'example'  => '42 hours 1:1 per week plus shared night support',
                'help'     => __('Short description of the commissioned support.', 'jm-referral-system'),
                'aliases'  => ['package', 'support package description', 'package details'],
            ],
            'notes' => [
                'heading'  => __('Notes', 'jm-referral-system'),
                'required' => __('No', 'jm-referral-system'),
                'example'  => '',
                'help'     => '',
                'aliases'  => ['comments'],
            ],
        ];
    }

    /**
     * Normalises a heading so "Date of Birth *" and "date_of_birth" match.
     */
    public static function normalise(string $heading): string
    {
        $heading = strtolower(trim($heading));
        $heading = str_replace('£', '', $heading);
        $heading = (string) preg_replace('/\(.*?\)/', '', $heading);
        $heading = (string) preg_replace('/[^a-z0-9]+/', ' ', $heading);

        return trim($heading);
    }

    /**
     * @return array<string, string> Normalised heading => column key.
     */
    public static function heading_map(): array
    {
        $map = [];
        foreach (self::definitions() as $key => $def) {
            $map[self::normalise($def['heading'])] = $key;
            $map[self::normalise(str_replace('_', ' ', $key))] = $key;
            foreach ($def['aliases'] as $alias) {
                $map[self::normalise($alias)] = $key;
            }
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    public static function date_keys(): array
    {
        return ['date_of_birth', 'admission_date', 'care_start_date', 'move_in_date', 'funding_start_date'];
    }

    /**
     * CSV template: headings, then one example row marked so the import skips it.
     */
    public static function template_csv(): string
    {
        $defs     = self::definitions();
        $headings = [];
        $example  = [];
        foreach ($defs as $key => $def) {
            $headings[] = $def['heading'] . (__('Yes', 'jm-referral-system') === $def['required'] ? ' *' : '');
            $example[]  = $def['example'];
        }
        $example[0] = 'EXAMPLE - ' . $example[0];

        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            return '';
        }
        fputcsv($handle, $headings, ',', '"', '');
        fputcsv($handle, $example, ',', '"', '');
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF" . $csv;
    }
}
