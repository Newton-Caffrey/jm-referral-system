<?php

namespace JMReferral\ReferralInbox\Document;

/**
 * Wording that referral forms use for each field (Phase 5E.1).
 *
 * Administrators can edit the lists so a care provider's own form wording is
 * recognised without a code change. Labels are matched as whole labels after
 * normalisation; they are never used as patterns.
 *
 * Plain labels such as "Name", "Telephone", "Email", "Address" and "Postcode"
 * are built in. They are assigned to the client or the referrer by the section
 * of the form they appear in, which the two section-heading lists identify.
 */
class ReferralFormLabels
{
    public const OPTION_KEY = 'jmrs_referral_form_labels';

    public const MAX_LABEL_LENGTH = 80;

    public const MAX_LABELS_PER_GROUP = 80;

    /**
     * Editable groups in display order.
     *
     * @return array<string, array<int, string>>
     */
    public static function defaults(): array
    {
        return [
            'client_name' => [
                'client name', 'name of client', "client's name", 'client full name', 'full name of client',
                'service user name', "service user's name", 'name of service user', 'service user full name',
                'full name of service user', 'patient name', 'name of patient', 'resident name',
                'name of person', 'name of person being referred', "person's name", 'name of individual',
                "individual's name", 'customer name', 'applicant name', 'client', 'service user', 'person',
                'patient',
            ],
            'client_date_of_birth' => [
                'date of birth', 'dob', 'd.o.b', 'birth date', 'client date of birth', 'client dob',
                'service user date of birth', 'service user dob', 'patient date of birth',
            ],
            'client_phone' => [
                'client phone', 'client telephone', 'client tel', 'client mobile', 'client contact number',
                'client phone number', 'client telephone number', 'service user phone', 'service user telephone',
                'service user contact number', 'service user mobile', 'patient telephone', 'patient phone',
            ],
            'client_email' => [
                'client email', 'client email address', 'client e-mail', 'service user email',
                'service user email address', 'patient email',
            ],
            'client_address' => [
                'client address', "client's address", 'client home address', 'address of client',
                'service user address', "service user's address", 'address of service user',
                'patient address', 'home address', 'current address', 'address of person',
            ],
            'referrer_name' => [
                'referrer name', "referrer's name", 'name of referrer', 'referrer', 'referred by',
                'referring person', 'person making referral', 'person making the referral',
                'name of person making referral', 'name of person making the referral',
                'referral made by', 'referral completed by', 'completed by', 'form completed by',
                'social worker', 'social worker name', 'name of social worker', 'allocated social worker',
                'allocated worker', 'care manager', 'care manager name', 'case manager', 'case manager name',
                'referring professional', 'name of referring professional', 'your name',
            ],
            'referrer_email' => [
                'referrer email', "referrer's email", 'referrer email address', 'email of referrer',
                'referrer e-mail', 'social worker email', 'care manager email', 'your email',
                'your email address',
            ],
            'referrer_phone' => [
                'referrer phone', 'referrer telephone', 'referrer tel', 'referrer contact number',
                "referrer's telephone", 'referrer phone number', 'referrer telephone number',
                'referrer mobile', 'social worker phone', 'social worker telephone',
                'social worker contact number', 'care manager telephone', 'your telephone', 'your phone',
                'your contact number',
            ],
            'referrer_organisation' => [
                'referrer organisation', "referrer's organisation", 'referring organisation',
                'referring agency', 'referring team', 'referring authority', 'local authority',
                'name of local authority', 'council', 'funding authority', 'placing authority',
                'commissioning authority', 'commissioner',
            ],
            'relationship_to_client' => [
                'relationship to client', 'relationship to service user', 'relationship to person',
                'relationship to the person', 'relationship to person being referred',
                'relationship to patient', 'relationship to individual',
            ],
            'care_requirements' => [
                'care needs', 'care requirements', 'summary of care needs', 'support needs',
                'summary of support needs', 'summary of needs', 'care and support needs',
                'overview of needs', 'presenting needs', 'identified needs', 'description of needs',
                'reason for referral', 'reasons for referral', 'reason for the referral', 'referral reason',
                'details of referral', 'support required', 'details of support required', 'care required',
                'details of care required',
            ],
            'care_start_date' => [
                'start date', 'proposed start date', 'care start date', 'required start date',
                'preferred start date', 'anticipated start date', 'expected start date',
                'service start date', 'date care required', 'date care to start', 'date service required',
            ],
            'service' => [
                'service required', 'service requested', 'service type', 'type of service',
                'type of service required', 'type of support', 'type of support required', 'type of care',
                'type of care required', 'care type', 'support type', 'placement type', 'type of placement',
            ],
            'priority' => [
                'priority', 'priority level', 'referral priority', 'urgency', 'level of urgency',
                'urgency of referral',
            ],
            'client_section' => [
                'service user details', 'service user information', 'client details', 'client information',
                'person being referred', 'individual being referred', 'patient details',
                'patient information', 'personal details', 'resident details', 'applicant details',
                'customer details', 'about the person', 'details of the person', 'details of person',
                'details of individual',
            ],
            'referrer_section' => [
                'referrer', 'referred by', 'person making the referral', 'person making referral',
                'making this referral', 'referring professional', 'referring agency',
                'referring organisation', 'referral made by', 'completed by', 'your details',
                'professional details', 'details of professional',
            ],
        ];
    }

    /**
     * Group key => administrator-facing name and help text.
     *
     * @return array<string, array{label: string, help: string}>
     */
    public static function group_descriptions(): array
    {
        return [
            'client_name'            => ['label' => __('Client name', 'jm-referral-system'), 'help' => ''],
            'client_date_of_birth'   => ['label' => __('Client date of birth', 'jm-referral-system'), 'help' => ''],
            'client_phone'           => ['label' => __('Client phone', 'jm-referral-system'), 'help' => ''],
            'client_email'           => ['label' => __('Client email', 'jm-referral-system'), 'help' => ''],
            'client_address'         => ['label' => __('Client address', 'jm-referral-system'), 'help' => ''],
            'referrer_name'          => ['label' => __('Referrer name', 'jm-referral-system'), 'help' => ''],
            'referrer_email'         => ['label' => __('Referrer email', 'jm-referral-system'), 'help' => ''],
            'referrer_phone'         => ['label' => __('Referrer phone', 'jm-referral-system'), 'help' => ''],
            'referrer_organisation'  => ['label' => __('Referrer organisation', 'jm-referral-system'), 'help' => ''],
            'relationship_to_client' => ['label' => __('Relationship to client', 'jm-referral-system'), 'help' => ''],
            'care_requirements'      => ['label' => __('Care requirements', 'jm-referral-system'), 'help' => ''],
            'care_start_date'        => ['label' => __('Care start date', 'jm-referral-system'), 'help' => ''],
            'service'                => [
                'label' => __('Service wanted (suggestion only)', 'jm-referral-system'),
                'help'  => __('Shown as a suggestion beside Service Type. Staff still choose the service.', 'jm-referral-system'),
            ],
            'priority'               => [
                'label' => __('Priority (suggestion only)', 'jm-referral-system'),
                'help'  => __('Shown as a suggestion beside Priority. Staff still choose the priority.', 'jm-referral-system'),
            ],
            'client_section'         => [
                'label' => __('Headings that start the client section', 'jm-referral-system'),
                'help'  => __('Plain labels such as Name, Telephone, Email and Address under one of these headings are read as the client\'s.', 'jm-referral-system'),
            ],
            'referrer_section'       => [
                'label' => __('Headings that start the referrer section', 'jm-referral-system'),
                'help'  => __('Plain labels such as Name, Telephone and Email under one of these headings are read as the referrer\'s.', 'jm-referral-system'),
            ],
        ];
    }

    /**
     * Effective label lists (stored lists where saved, defaults otherwise).
     *
     * @return array<string, array<int, string>>
     */
    public function all(): array
    {
        $defaults = self::defaults();
        $stored   = get_option(self::OPTION_KEY, null);
        $out      = [];

        foreach ($defaults as $group => $default_labels) {
            $labels = $default_labels;
            if (is_array($stored) && isset($stored[$group]) && is_array($stored[$group])) {
                $labels = $stored[$group];
            }
            $out[$group] = self::sanitize_labels($labels);
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    public function group(string $group): array
    {
        return $this->all()[$group] ?? [];
    }

    public function is_customised(): bool
    {
        return is_array(get_option(self::OPTION_KEY, null));
    }

    /**
     * Save label lists from textarea input (one label per line).
     *
     * @param array<string, mixed> $input Group key => multi-line string.
     * @return array{ok: bool, errors: array<string, string>}
     */
    public function update(array $input): array
    {
        $defaults = self::defaults();
        $errors   = [];
        $merged   = $this->all();

        foreach ($input as $group => $raw) {
            $group = (string) $group;
            if (! isset($defaults[$group])) {
                $errors[$group] = __('Unknown label group.', 'jm-referral-system');
                continue;
            }

            if (! is_string($raw)) {
                $errors[$group] = __('Invalid label list.', 'jm-referral-system');
                continue;
            }

            if (1 === preg_match('/<[^>]+>|javascript:|on\w+\s*=/i', $raw)) {
                $errors[$group] = __('Markup and scripts are not allowed in labels.', 'jm-referral-system');
                continue;
            }

            $lines = preg_split('/\r\n|\r|\n/', $raw);
            $lines = is_array($lines) ? $lines : [];

            $too_long = false;
            foreach ($lines as $line) {
                if (self::text_length(trim($line)) > self::MAX_LABEL_LENGTH) {
                    $too_long = true;
                    break;
                }
            }
            if ($too_long) {
                $errors[$group] = sprintf(
                    /* translators: %d: max characters */
                    __('Each label must be %d characters or fewer.', 'jm-referral-system'),
                    self::MAX_LABEL_LENGTH
                );
                continue;
            }

            $labels = self::sanitize_labels($lines);
            if (count($lines) > 0 && count(array_filter(array_map('trim', $lines))) > self::MAX_LABELS_PER_GROUP) {
                $errors[$group] = sprintf(
                    /* translators: %d: max labels */
                    __('A field can have at most %d labels.', 'jm-referral-system'),
                    self::MAX_LABELS_PER_GROUP
                );
                continue;
            }

            $merged[$group] = $labels;
        }

        if ([] !== $errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        update_option(self::OPTION_KEY, $merged, false);

        return ['ok' => true, 'errors' => []];
    }

    public function reset(): void
    {
        delete_option(self::OPTION_KEY);
    }

    /**
     * @param array<int, mixed> $labels
     * @return array<int, string>
     */
    public static function sanitize_labels(array $labels): array
    {
        $out  = [];
        $seen = [];

        foreach ($labels as $label) {
            if (! is_scalar($label)) {
                continue;
            }

            $clean = self::normalise((string) $label);
            if ('' === $clean || self::text_length($clean) > self::MAX_LABEL_LENGTH) {
                continue;
            }
            if (1 !== preg_match('/\p{L}/u', $clean)) {
                continue;
            }
            if (isset($seen[$clean])) {
                continue;
            }

            $seen[$clean] = true;
            $out[]        = $clean;
            if (count($out) >= self::MAX_LABELS_PER_GROUP) {
                break;
            }
        }

        return $out;
    }

    /**
     * Canonical form for comparing a label on a form with a configured label.
     *
     * Lower case, single spaces, straight apostrophes. Leading numbering,
     * a trailing bracketed note, and trailing punctuation are removed, so
     * "3. Date of Birth (dd/mm/yyyy):" compares equal to "date of birth".
     */
    public static function normalise(string $label): string
    {
        $label = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($label) : strip_tags($label);
        $label = str_replace(["\u{2019}", "\u{2018}", '`'], "'", $label);
        $label = str_replace(["\u{2013}", "\u{2014}"], '-', $label);
        $label = (string) preg_replace('/\s+/u', ' ', $label);
        $label = trim($label);
        $label = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);

        // Leading numbering: "1.", "1)", "1.2", "a)", "q3.".
        $label = (string) preg_replace('/^(?:q\s?)?\d{1,3}(?:\.\d{1,3})*[.):]?\s+/u', '', $label);
        $label = (string) preg_replace('/^[a-z][.)]\s+/u', '', $label);

        // Trailing bracketed note, then trailing punctuation, repeated once for "(…):".
        for ($pass = 0; $pass < 2; $pass++) {
            $label = (string) preg_replace('/\s*[:?*.\-]+\s*$/u', '', $label);
            $label = (string) preg_replace('/\s*\([^()]{0,60}\)\s*$/u', '', $label);
        }

        $label = (string) preg_replace('/\s*\/\s*/u', '/', $label);

        return trim($label);
    }

    private static function text_length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }
}
