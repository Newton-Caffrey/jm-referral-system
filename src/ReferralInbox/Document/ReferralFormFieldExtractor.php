<?php

namespace JMReferral\ReferralInbox\Document;

use JMReferral\ReferralInbox\ReferralInboxCandidateField;

/**
 * Reads referral fields from the text of an uploaded form (Phase 5E.1).
 *
 * Deterministic and advisory. A value is taken only from beside, beneath, or
 * after a recognised label. Plain labels (Name, Telephone, Email, Address) are
 * assigned to the client or the referrer by the section they sit in. Two
 * different values for one field are reported as ambiguous and neither is
 * chosen. Nothing is written, logged, or sent anywhere.
 *
 * No AI or external service. Labels are literals from ReferralFormLabels;
 * a caller never supplies a pattern.
 */
class ReferralFormFieldExtractor
{
    private const SOURCE = 'uploaded_document';

    private const NAME_MAX         = 255;
    private const EMAIL_MAX        = 190;
    private const PHONE_MAX        = 50;
    private const ADDRESS_LINE_MAX = 255;
    private const CITY_MAX         = 150;
    private const RELATIONSHIP_MAX = 150;
    private const CARE_MAX         = 5000;
    private const MAX_ALTERNATIVES = 8;

    private const ADDRESS_MAX_LINES = 5;
    private const CARE_MAX_LINES    = 40;

    private const SECTION_UNKNOWN  = 'unknown';
    private const SECTION_CLIENT   = 'client';
    private const SECTION_REFERRER = 'referrer';
    private const SECTION_OTHER    = 'other';
    private const SECTION_CARE     = 'care';

    /**
     * Plain labels resolved by section.
     *
     * @var array<string, array<int, string>>
     */
    private const GENERIC_LABELS = [
        'name' => ['name', 'full name', 'name in full', 'names'],
        'first_name' => [
            'first name', 'first names', 'forename', 'forenames', 'given name', 'given names',
        ],
        'last_name' => ['surname', 'last name', 'family name'],
        'phone' => [
            'telephone', 'telephone number', 'telephone no', 'tel', 'tel no', 'tel number', 'phone',
            'phone number', 'phone no', 'mobile', 'mobile number', 'mobile no', 'mobile phone',
            'contact number', 'contact telephone', 'contact tel', 'contact no', 'contact phone',
            'home telephone', 'home phone', 'home tel', 'landline', 'daytime telephone',
            'telephone/mobile', 'tel/mobile', 'phone/mobile',
        ],
        'email' => ['email', 'e-mail', 'email address', 'e-mail address', 'contact email'],
        'address' => [
            'address', 'full address', 'postal address', 'address including postcode',
            'address and postcode',
        ],
        'address_line_1' => ['address line 1', 'address 1', 'street', 'street address', 'house number and street'],
        'address_line_2' => ['address line 2', 'address 2', 'address line 3'],
        'city' => ['town', 'city', 'town/city', 'city/town', 'town or city', 'post town'],
        'postcode' => ['postcode', 'post code', 'postal code'],
        'organisation' => [
            'organisation', 'organization', 'organisation name', 'name of organisation', 'agency',
            'agency name', 'company',
        ],
        'relationship' => ['relationship'],
    ];

    /**
     * Recognised so they end the previous value and are never mistaken for a wanted field.
     *
     * @var array<int, string>
     */
    private const IGNORED_LABELS = [
        'nhs number', 'nhs no', 'nhs', 'national insurance number', 'ni number', 'gender', 'sex', 'age',
        'title', 'ethnicity', 'ethnic origin', 'religion', 'nationality', 'marital status', 'language',
        'first language', 'preferred language', 'gp', 'gp name', 'gp phone', 'gp telephone', 'gp address',
        'gp surgery', 'gp practice', 'gp details', 'next of kin', 'next of kin name',
        'next of kin telephone', 'next of kin phone', 'next of kin address', 'next of kin relationship',
        'nok', 'nok name', 'nok telephone', 'emergency contact', 'emergency contact name',
        'emergency contact number', 'emergency contact telephone', 'date', 'date of referral',
        'referral date', 'date completed', 'date received', 'signature', 'signed', 'print name',
        'job title', 'role', 'position', 'designation', 'team', 'team name', 'department', 'diagnosis',
        'medical conditions', 'medical history', 'medication', 'medications', 'allergies', 'funding',
        'funding type', 'funding source', 'reference', 'reference number', 'ref', 'id number',
        'social care id', 'case number', 'case reference', 'occupation', 'employer',
    ];

    /**
     * Ignored as fields of their own, but part of the narrative when they
     * appear inside a care-needs answer ("Medication: prompts twice daily").
     *
     * @var array<int, string>
     */
    private const CARE_CONTEXT_LABELS = [
        'diagnosis', 'medical conditions', 'medical history', 'medication', 'medications', 'allergies',
    ];

    /**
     * A recognised label standing alone on a line, with no colon, is a heading.
     *
     * @var array<string, string>
     */
    private const BARE_SECTION_LABELS = [
        'client'       => self::SECTION_CLIENT,
        'service user' => self::SECTION_CLIENT,
        'person'       => self::SECTION_CLIENT,
        'patient'      => self::SECTION_CLIENT,
        'referrer'     => self::SECTION_REFERRER,
        'referred by'  => self::SECTION_REFERRER,
    ];

    /**
     * Headings for parts of a form whose plain labels belong to someone else.
     *
     * @var array<int, string>
     */
    private const OTHER_SECTION_PHRASES = [
        'next of kin', 'emergency contact', 'gp details', 'gp information', 'gp surgery',
        'general practitioner', 'medical details', 'medical history', 'medical information',
        'health details', 'declaration', 'consent', 'signature', 'office use', 'funding details',
        'funding information', 'risk assessment', 'carer details', 'family details', 'representative',
        'advocate', 'power of attorney', 'other professionals', 'other agencies',
        'professionals involved',
    ];

    /**
     * @var array<int, string>
     */
    private const CARE_SECTION_PHRASES = [
        'care needs', 'support needs', 'reason for referral', 'referral details', 'referral information',
        'needs assessment', 'background information',
    ];

    /**
     * @var array<string, string>
     */
    private const SERVICE_VALUE_PHRASES = [
        'supported living' => 'supported_living',
        'home care'        => 'home_care',
        'homecare'         => 'home_care',
        'domiciliary'      => 'home_care',
        'care at home'     => 'home_care',
        'residential'      => 'residential_care',
    ];

    /**
     * Stricter phrases for the whole-document fallback.
     *
     * @var array<string, string>
     */
    private const SERVICE_TEXT_PHRASES = [
        'supported living'      => 'supported_living',
        'home care'             => 'home_care',
        'domiciliary care'      => 'home_care',
        'care at home'          => 'home_care',
        'residential care'      => 'residential_care',
        'residential placement' => 'residential_care',
    ];

    /**
     * @var array<string, int>
     */
    private const MONTHS = [
        'jan' => 1, 'january' => 1, 'feb' => 2, 'february' => 2, 'mar' => 3, 'march' => 3,
        'apr' => 4, 'april' => 4, 'may' => 5, 'jun' => 6, 'june' => 6, 'jul' => 7, 'july' => 7,
        'aug' => 8, 'august' => 8, 'sep' => 9, 'sept' => 9, 'september' => 9, 'oct' => 10,
        'october' => 10, 'nov' => 11, 'november' => 11, 'dec' => 12, 'december' => 12,
    ];

    public function __construct(private ReferralFormLabels $labels)
    {
    }

    public function extract(ExtractedDocument $document, string $filename = ''): ReferralFormExtractionResult
    {
        if (! $document->is_readable()) {
            return ReferralFormExtractionResult::unread($document->status(), $filename);
        }

        $groups = $this->labels->all();
        $index  = $this->build_index($groups);
        $state  = $this->fresh_state($groups);
        $state['lines_may_wrap'] = $document->lines_may_wrap();

        // Fillable-form values are isolated pairs with no section context.
        foreach ($document->form_fields() as $pair) {
            $this->boundary($state);
            $descriptor = $index['labels'][ReferralFormLabels::normalise($pair[0])] ?? null;
            if (null !== $descriptor) {
                $this->handle($descriptor, $pair[1], $state, false);
            }
        }
        $this->boundary($state);

        $rows  = $document->rows();
        $count = count($rows);
        for ($i = 0; $i < $count; $i++) {
            $row = $rows[$i];

            if (count($row) >= 2) {
                if (($i + 1) < $count && $this->is_header_row($row, $rows[$i + 1], $index)) {
                    $this->boundary($state);
                    foreach ($row as $column => $label_cell) {
                        $descriptor = $this->exact_label($label_cell, $index);
                        if (null !== $descriptor) {
                            $this->handle($descriptor, (string) ($rows[$i + 1][$column] ?? ''), $state, false);
                            $this->boundary($state);
                        }
                    }
                    $i++;
                    continue;
                }

                $this->process_cells($row, $index, $state);
                continue;
            }

            foreach (explode("\n", (string) ($row[0] ?? '')) as $line) {
                if (str_contains($line, "\t")) {
                    $cells = array_values(array_filter(
                        array_map('trim', explode("\t", $line)),
                        static fn (string $cell): bool => '' !== $cell
                    ));
                    if (count($cells) >= 2) {
                        $this->process_cells($cells, $index, $state);
                        continue;
                    }
                    $line = implode(' ', $cells);
                }

                $this->parse_line($line, $index, $state);
            }
        }

        return ReferralFormExtractionResult::read(
            $this->assemble($state, $document),
            $document->plain_text(),
            $filename
        );
    }

    // ------------------------------------------------------------------
    // Label index
    // ------------------------------------------------------------------

    /**
     * @param array<string, array<int, string>> $groups
     * @return array{labels: array<string, array<string, mixed>>, by_first_word: array<string, array<int, string>>, patterns: array<string, string>}
     */
    private function build_index(array $groups): array
    {
        $labels = [];

        foreach (self::IGNORED_LABELS as $label) {
            $labels[ReferralFormLabels::normalise($label)] = [
                'scope'        => 'ignored',
                'target'       => '',
                'care_context' => in_array($label, self::CARE_CONTEXT_LABELS, true),
            ];
        }

        foreach (self::GENERIC_LABELS as $target => $list) {
            foreach ($list as $label) {
                $labels[ReferralFormLabels::normalise($label)] = ['scope' => 'generic', 'target' => $target];
            }
        }

        // Configured groups win over built-in plain and ignored labels.
        // Earlier groups win over later ones, so iterate in reverse.
        $explicit = [
            'client_name', 'client_date_of_birth', 'client_phone', 'client_email', 'client_address',
            'referrer_name', 'referrer_email', 'referrer_phone', 'referrer_organisation',
            'relationship_to_client', 'care_requirements', 'care_start_date', 'service', 'priority',
        ];
        foreach (array_reverse($explicit) as $target) {
            foreach ($groups[$target] ?? [] as $label) {
                if ('' === $label) {
                    continue;
                }
                $labels[$label] = ['scope' => 'explicit', 'target' => $target];
            }
        }

        unset($labels['']);

        $by_first_word = [];
        $patterns      = [];
        foreach (array_keys($labels) as $label) {
            $label = (string) $label;
            $words = preg_split('/\s+/u', $label) ?: [];
            $first = rtrim((string) ($words[0] ?? ''), '.');
            if ('' === $first) {
                continue;
            }
            $by_first_word[$first][] = $label;
            $patterns[$label]        = $this->label_pattern($words);
        }

        foreach ($by_first_word as $first => $list) {
            usort($list, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
            $by_first_word[$first] = $list;
        }

        return [
            'labels'        => $labels,
            'by_first_word' => $by_first_word,
            'patterns'      => $patterns,
        ];
    }

    /**
     * Regex source for one label: flexible spacing, optional full stops, either apostrophe.
     *
     * @param array<int, string> $words
     */
    private function label_pattern(array $words): string
    {
        $parts = [];
        foreach ($words as $word) {
            $quoted = preg_quote($word, '/');
            $quoted = str_replace("'", "['\x{2019}]", $quoted);
            $quoted = str_replace('\/', '\s*\/\s*', $quoted);
            $parts[] = $quoted;
        }

        return implode('\.?\s+', $parts);
    }

    /**
     * @param array<string, array<int, string>> $groups
     * @return array<string, mixed>
     */
    private function fresh_state(array $groups): array
    {
        return [
            'section'          => self::SECTION_UNKNOWN,
            'pending'          => null,
            'open_target'      => null,
            'open_index'       => -1,
            'open_lines'       => 0,
            'lines_may_wrap'   => false,
            'found'            => [],
            'client_headings'  => $groups['client_section'] ?? [],
            'referrer_headings'=> $groups['referrer_section'] ?? [],
        ];
    }

    // ------------------------------------------------------------------
    // Walking the document
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $state
     */
    private function boundary(array &$state): void
    {
        $state['pending']     = null;
        $state['open_target'] = null;
        $state['open_index']  = -1;
        $state['open_lines']  = 0;
    }

    /**
     * A header row is all labels, with the values in the row beneath.
     *
     * @param array<int, string> $row
     * @param array<int, string> $next
     * @param array<string, mixed> $index
     */
    private function is_header_row(array $row, array $next, array $index): bool
    {
        if (count($row) < 2 || count($row) !== count($next)) {
            return false;
        }

        foreach ($row as $cell) {
            if (null === $this->exact_label($cell, $index)) {
                return false;
            }
        }

        foreach ($next as $cell) {
            if (null !== $this->exact_label($cell, $index)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string> $cells
     * @param array<string, mixed> $index
     * @param array<string, mixed> $state
     */
    private function process_cells(array $cells, array $index, array &$state): void
    {
        $this->boundary($state);

        $count = count($cells);
        $i     = 0;
        while ($i < $count) {
            $descriptor = $this->exact_label($cells[$i], $index);
            if (null !== $descriptor) {
                $has_value = ($i + 1) < $count && null === $this->exact_label($cells[$i + 1], $index);
                if ($has_value) {
                    $this->handle($descriptor, $cells[$i + 1], $state, false);
                    $i += 2;

                    // Text read from a PDF page wraps a long last-column value
                    // onto the following lines, so leave that value open.
                    if ($i >= $count && $state['lines_may_wrap'] && null !== $state['open_target']) {
                        return;
                    }

                    $this->boundary($state);
                    continue;
                }

                $i++;
                continue;
            }

            foreach (explode("\n", $cells[$i]) as $line) {
                $this->parse_line($line, $index, $state);
            }
            $this->boundary($state);
            $i++;
        }

        $this->boundary($state);
    }

    /**
     * The descriptor when a whole cell is one recognised label.
     *
     * @param array<string, mixed> $index
     * @return array<string, mixed>|null
     */
    private function exact_label(string $cell, array $index): ?array
    {
        $cell = trim($cell);
        if ('' === $cell || strlen($cell) > 160 || str_contains($cell, "\n")) {
            return null;
        }

        $normalised = ReferralFormLabels::normalise($cell);

        return $index['labels'][$normalised] ?? null;
    }

    /**
     * @param array<string, mixed> $index
     * @param array<string, mixed> $state
     */
    private function parse_line(string $line, array $index, array &$state): void
    {
        $line = trim($line);
        if ('' === $line) {
            return;
        }

        $stripped   = $this->strip_numbering($line);
        $normalised = ReferralFormLabels::normalise($stripped);

        // 1. The whole line is a recognised label: a heading, or a label whose value follows.
        if ('' !== $normalised && isset($index['labels'][$normalised])) {
            $descriptor = $index['labels'][$normalised];
            $has_colon  = 1 === preg_match('/:\s*$/', $stripped);

            if (! $has_colon) {
                // "Client" or "Referrer" alone on a line is a heading, not a question.
                if (isset(self::BARE_SECTION_LABELS[$normalised])) {
                    $this->boundary($state);
                    $state['section'] = self::BARE_SECTION_LABELS[$normalised];

                    return;
                }

                // A label that is also exactly a section heading ("Care needs",
                // "Person making the referral") starts that section and may still
                // be answered on the next line. A stacked label such as
                // "Referrer Name" only waits for its value.
                $section = 'ignored' === $descriptor['scope']
                    ? $this->heading_section($stripped, $state)
                    : $this->exact_section($normalised, $state);
                if (null !== $section) {
                    $this->boundary($state);
                    $state['section'] = $section;
                    if ('ignored' !== $descriptor['scope']) {
                        $state['pending'] = $descriptor;
                    }

                    return;
                }
            }

            $this->boundary($state);
            if ('ignored' !== $descriptor['scope']) {
                $state['pending'] = $descriptor;
                if ('care_requirements' === $descriptor['target']) {
                    $state['section'] = self::SECTION_CARE;
                }
            }

            return;
        }

        // 2. A section heading.
        $section = $this->heading_section($stripped, $state);
        if (null !== $section) {
            $this->boundary($state);
            $state['section'] = $section;

            return;
        }

        // 3. One or more "Label: value" spans.
        $spans = $this->find_spans($stripped, $index);
        if ([] !== $spans
            && 'care_requirements' === $state['open_target']
            && ! empty($spans[0]['descriptor']['care_context'])
            && 0 === $spans[0]['label_start']
        ) {
            $this->append($stripped, $state);

            return;
        }
        if ([] !== $spans) {
            $this->boundary($state);
            $last = count($spans) - 1;
            foreach ($spans as $position => $span) {
                if ($position < $last) {
                    $this->boundary($state);
                }
                $this->handle($span['descriptor'], $span['value'], $state, $position === $last);
            }

            return;
        }

        // 4. A label this extractor does not know.
        if ($this->is_unknown_label_line($stripped)) {
            if ('care_requirements' === $state['open_target']) {
                $this->append($stripped, $state);

                return;
            }
            $this->boundary($state);

            return;
        }

        // 5. Free text: the value of a waiting label, or more of an open value.
        if (null !== $state['pending']) {
            $descriptor       = $state['pending'];
            $state['pending'] = null;
            $this->handle($descriptor, $stripped, $state, false);

            return;
        }

        if (null !== $state['open_target']) {
            $this->append($stripped, $state);
        }
    }

    private function strip_numbering(string $line): string
    {
        $line = (string) preg_replace('/^\s*(?:Q\s?)?\d{1,3}(?:\.\d{1,3})*[.)]\s+/u', '', $line);
        $line = (string) preg_replace('/^\s*[A-Za-z][.)]\s+/u', '', $line);

        return trim($line);
    }

    /**
     * The section a line names when it is exactly one of the heading phrases.
     *
     * @param array<string, mixed> $state
     */
    private function exact_section(string $normalised, array $state): ?string
    {
        foreach ($this->section_phrases($state) as $section => $phrases) {
            if (in_array($normalised, $phrases, true)) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Heading phrases in the order they are tested.
     *
     * @param array<string, mixed> $state
     * @return array<string, array<int, string>>
     */
    private function section_phrases(array $state): array
    {
        return [
            self::SECTION_REFERRER => $state['referrer_headings'],
            self::SECTION_OTHER    => self::OTHER_SECTION_PHRASES,
            self::SECTION_CLIENT   => $state['client_headings'],
            self::SECTION_CARE     => self::CARE_SECTION_PHRASES,
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function heading_section(string $line, array $state): ?string
    {
        $line = (string) preg_replace('/^(?:section|part)\s+[A-Za-z0-9]{1,4}\s*[:.\-\x{2013}\x{2014})]?\s*/iu', '', $line);
        $line = trim($line);
        if ('' === $line || strlen($line) > 100) {
            return null;
        }

        // "Heading: some value" is a labelled line, not a heading.
        if (1 === preg_match('/:\s*\S/u', $line) || 1 === preg_match('/[.!?]\s*$/u', $line)) {
            return null;
        }

        $normalised = ReferralFormLabels::normalise($line);
        if ('' === $normalised) {
            return null;
        }

        $words = count(preg_split('/\s+/u', $normalised) ?: []);
        if ($words > 10) {
            return null;
        }

        $padded = ' ' . $normalised . ' ';
        foreach ($this->section_phrases($state) as $section => $phrases) {
            foreach ($phrases as $phrase) {
                $phrase = (string) $phrase;
                if ('' === $phrase || ! str_contains($padded, ' ' . $phrase . ' ')) {
                    continue;
                }
                $phrase_words = count(preg_split('/\s+/u', $phrase) ?: []);
                if ($words <= $phrase_words + 4) {
                    return $section;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $index
     * @return array<int, array{descriptor: array<string, mixed>, value: string, label_start: int}>
     */
    private function find_spans(string $line, array $index): array
    {
        $length = strlen($line);
        $spans  = [];
        $offset = 0;

        $start = $this->match_label_at($line, 0, $index, true);
        if (null !== $start) {
            $spans[] = $start;
            $offset  = $start['value_start'];
        } else {
            // Skip an unknown "Some Label:" prefix so its last word is not read as a label.
            $colon = strpos($line, ':');
            if (false !== $colon && $this->looks_like_label(substr($line, 0, $colon))) {
                $offset = $colon + 1;
            }
        }

        $guard = 0;
        while ($offset < $length && $guard < 4000 && count($spans) < 40) {
            $guard++;
            $previous = $offset > 0 ? $line[$offset - 1] : ' ';
            $at_word  = ' ' === $previous || "\t" === $previous || '|' === $previous || ';' === $previous || ',' === $previous;
            if ($at_word && ' ' !== $line[$offset]) {
                $match = $this->match_label_at($line, $offset, $index, false);
                if (null !== $match && $match['value_start'] > $offset) {
                    $spans[] = $match;
                    $offset  = $match['value_start'];
                    continue;
                }
            }
            $offset++;
        }

        $out   = [];
        $count = count($spans);
        for ($i = 0; $i < $count; $i++) {
            $from = $spans[$i]['value_start'];
            $to   = ($i + 1) < $count ? $spans[$i + 1]['label_start'] : $length;
            $out[] = [
                'descriptor'  => $spans[$i]['descriptor'],
                'value'       => $to > $from ? trim(substr($line, $from, $to - $from)) : '',
                'label_start' => $spans[$i]['label_start'],
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $index
     * @return array{descriptor: array<string, mixed>, label_start: int, value_start: int}|null
     */
    private function match_label_at(string $line, int $offset, array $index, bool $line_start): ?array
    {
        if (1 !== preg_match('/\G[\p{L}\p{N}.\'\x{2019}\-\/]+/u', $line, $word, 0, $offset)) {
            return null;
        }

        $first = str_replace("\u{2019}", "'", (string) $word[0]);
        $first = function_exists('mb_strtolower') ? mb_strtolower($first, 'UTF-8') : strtolower($first);
        $first = rtrim($first, '.');

        $candidates = $index['by_first_word'][$first] ?? [];
        if ([] === $candidates) {
            return null;
        }

        // After the label: an optional bracketed note, then a separator.
        $separator = $line_start
            ? '(?::|\t|\s+[\-\x{2013}\x{2014}]\s+|\s*[._\x{2026}]{3,})'
            : '(?::|\t)';

        foreach ($candidates as $label) {
            $pattern = '/\G' . $index['patterns'][$label]
                . '(?:\s*\([^()]{0,40}\))?\s*[.?*]?\s*' . $separator . '\s*/iu';
            if (1 !== preg_match($pattern, $line, $matches, 0, $offset)) {
                continue;
            }

            return [
                'descriptor'  => $index['labels'][$label],
                'label_start' => $offset,
                'value_start' => $offset + strlen((string) $matches[0]),
            ];
        }

        return null;
    }

    private function looks_like_label(string $prefix): bool
    {
        $prefix = trim($prefix);
        if ('' === $prefix || strlen($prefix) > 60) {
            return false;
        }

        if (1 === preg_match('/[.!?]\s/u', $prefix)) {
            return false;
        }

        if (1 !== preg_match('/\p{L}/u', $prefix)) {
            return false;
        }

        return count(preg_split('/\s+/u', $prefix) ?: []) <= 7;
    }

    private function is_unknown_label_line(string $line): bool
    {
        $colon = strpos($line, ':');

        return false !== $colon && $this->looks_like_label(substr($line, 0, $colon));
    }

    // ------------------------------------------------------------------
    // Recording values
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $descriptor
     * @param array<string, mixed> $state
     */
    private function handle(array $descriptor, string $raw, array &$state, bool $allow_pending): void
    {
        if ('ignored' === $descriptor['scope']) {
            $this->boundary($state);

            return;
        }

        $value = $this->clean_value($raw);
        if ('' === $value) {
            $this->boundary($state);
            if ($allow_pending) {
                $state['pending'] = $descriptor;
            }

            return;
        }

        $target = $this->resolve_target($descriptor, (string) $state['section']);
        if (null === $target) {
            $this->boundary($state);

            return;
        }

        $this->record($target, $value, 'explicit' === $descriptor['scope'], $state);
    }

    /**
     * @param array<string, mixed> $descriptor
     */
    private function resolve_target(array $descriptor, string $section): ?string
    {
        $target = (string) $descriptor['target'];

        if ('explicit' === $descriptor['scope']) {
            if ('relationship_to_client' === $target && self::SECTION_OTHER === $section) {
                return null;
            }
            if ('client_date_of_birth' === $target
                && in_array($section, [self::SECTION_OTHER, self::SECTION_REFERRER], true)
            ) {
                return null;
            }

            return $target;
        }

        $for_client   = in_array($section, [self::SECTION_CLIENT, self::SECTION_UNKNOWN], true);
        $for_referrer = self::SECTION_REFERRER === $section;

        return match ($target) {
            'name'           => $for_client ? 'client_name' : ($for_referrer ? 'referrer_name' : null),
            'first_name'     => $for_client ? 'client_first_name' : ($for_referrer ? 'referrer_first_name' : null),
            'last_name'      => $for_client ? 'client_last_name' : ($for_referrer ? 'referrer_last_name' : null),
            'phone'          => $for_client ? 'client_phone' : ($for_referrer ? 'referrer_phone' : null),
            'email'          => $for_client ? 'client_email' : ($for_referrer ? 'referrer_email' : null),
            'address'        => $for_client ? 'client_address' : null,
            'address_line_1' => $for_client ? 'address_line_1' : null,
            'address_line_2' => $for_client ? 'address_line_2' : null,
            'city'           => $for_client ? 'city' : null,
            'postcode'       => $for_client ? 'postcode' : null,
            'organisation'   => ($for_referrer || self::SECTION_UNKNOWN === $section) ? 'referrer_organisation' : null,
            'relationship'   => ($for_referrer || self::SECTION_UNKNOWN === $section) ? 'relationship_to_client' : null,
            default          => null,
        };
    }

    /**
     * @param array<string, mixed> $state
     */
    private function record(string $target, string $value, bool $explicit, array &$state): void
    {
        $this->boundary($state);

        $multiline = in_array($target, ['client_address', 'care_requirements'], true);

        $clean = match ($target) {
            'client_name', 'referrer_name', 'client_first_name', 'client_last_name',
            'referrer_first_name', 'referrer_last_name'
                => $this->name($this->before_embedded_label($this->first_line($value))),
            'client_date_of_birth' => $this->date($value, true),
            'care_start_date'      => $this->date($value, false),
            'client_phone', 'referrer_phone' => $this->phone($value),
            'client_email', 'referrer_email' => $this->email($value),
            'client_address'       => $this->block($value, 600),
            'address_line_1', 'address_line_2' => $this->short_text($this->first_line($value), self::ADDRESS_LINE_MAX),
            'city'                 => $this->short_text($this->first_line($value), self::CITY_MAX),
            'postcode'             => $this->postcode($value),
            'referrer_organisation'=> $this->short_text($this->first_line($value), self::NAME_MAX),
            'relationship_to_client' => $this->short_text(
                $this->before_embedded_label($this->first_line($value)),
                self::RELATIONSHIP_MAX
            ),
            'care_requirements'    => $this->block($value, self::CARE_MAX),
            'service', 'priority'  => $this->short_text($this->checked_options($value), 300),
            default                => null,
        };

        if (null === $clean || '' === $clean) {
            return;
        }

        $state['found'][$target][] = ['value' => $clean, 'explicit' => $explicit];

        if ($multiline) {
            $state['open_target'] = $target;
            $state['open_index']  = count($state['found'][$target]) - 1;
            $state['open_lines']  = substr_count($clean, "\n") + 1;
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function append(string $line, array &$state): void
    {
        $target = $state['open_target'];
        $at     = (int) $state['open_index'];
        if (null === $target || ! isset($state['found'][$target][$at])) {
            return;
        }

        $line = $this->clean_value($line);
        if ('' === $line) {
            return;
        }

        $current = (string) $state['found'][$target][$at]['value'];

        if ('client_address' === $target) {
            if ($state['open_lines'] >= self::ADDRESS_MAX_LINES
                || strlen($line) > 80
                || null !== $this->postcode($current)
            ) {
                $this->boundary($state);

                return;
            }
        } else {
            if ($state['open_lines'] >= self::CARE_MAX_LINES || strlen($current) >= self::CARE_MAX) {
                $this->boundary($state);

                return;
            }
        }

        $limit = 'client_address' === $target ? 600 : self::CARE_MAX;
        $state['found'][$target][$at]['value'] = $this->bound($current . "\n" . $line, $limit);
        $state['open_lines']++;
    }

    // ------------------------------------------------------------------
    // Building the result
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $state
     * @return array<string, ReferralInboxCandidateField>
     */
    private function assemble(array $state, ExtractedDocument $document): array
    {
        $found = $state['found'];
        $fold  = static fn (string $v): string => function_exists('mb_strtolower')
            ? mb_strtolower((string) preg_replace('/\s+/u', ' ', $v), 'UTF-8')
            : strtolower((string) preg_replace('/\s+/', ' ', $v));
        $digits = static fn (string $v): string => (string) preg_replace('/\D/', '', $v);
        $same   = static fn (string $v): string => $v;

        $fields = [];

        $fields['client_name'] = $this->field(
            $this->with_combined_name($found, 'client_name', 'client_first_name', 'client_last_name', $fold),
            $fold,
            'document_label_client_name'
        );
        $fields['referrer_name'] = $this->field(
            $this->with_combined_name($found, 'referrer_name', 'referrer_first_name', 'referrer_last_name', $fold),
            $fold,
            'document_label_referrer_name'
        );

        $fields['client_date_of_birth'] = $this->field($found['client_date_of_birth'] ?? [], $same, 'document_label_client_date_of_birth');
        $fields['client_phone']         = $this->field($found['client_phone'] ?? [], $digits, 'document_label_client_phone');
        $fields['client_email']         = $this->field($found['client_email'] ?? [], $fold, 'document_label_client_email');
        $fields['referrer_email']       = $this->field($found['referrer_email'] ?? [], $fold, 'document_label_referrer_email');
        $fields['referrer_phone']       = $this->field($found['referrer_phone'] ?? [], $digits, 'document_label_referrer_phone');
        $fields['referrer_organisation']= $this->field($found['referrer_organisation'] ?? [], $fold, 'document_label_referrer_organisation');
        $fields['relationship_to_client'] = $this->field($found['relationship_to_client'] ?? [], $fold, 'document_label_relationship');
        $fields['care_start_date']      = $this->field($found['care_start_date'] ?? [], $same, 'document_label_care_start_date');

        $fields = array_merge($fields, $this->address_fields($found, $fold));

        $fields['care_requirements'] = $this->care_field($found['care_requirements'] ?? [], $fold);
        $fields['service_hint']      = $this->service_hint($found['service'] ?? [], $document);
        $fields['priority_hint']     = $this->priority_hint($found['priority'] ?? []);

        return $fields;
    }

    /**
     * A full name, plus "First Last" when the form gives the two parts separately.
     *
     * @param array<string, array<int, array{value: string, explicit: bool}>> $found
     * @return array<int, array{value: string, explicit: bool}>
     */
    private function with_combined_name(array $found, string $full, string $first, string $last, callable $fold): array
    {
        $values = $found[$full] ?? [];

        $firsts = $this->distinct($found[$first] ?? [], $fold);
        $lasts  = $this->distinct($found[$last] ?? [], $fold);

        if (1 === count($firsts) && 1 === count($lasts)) {
            $combined = $this->name($firsts[0]['value'] . ' ' . $lasts[0]['value']);
            if (null !== $combined) {
                $values[] = ['value' => $combined, 'explicit' => false];
            }
        }

        return $values;
    }

    /**
     * @param array<string, array<int, array{value: string, explicit: bool}>> $found
     * @return array<string, ReferralInboxCandidateField>
     */
    private function address_fields(array $found, callable $fold): array
    {
        $line_1   = $found['address_line_1'] ?? [];
        $line_2   = $found['address_line_2'] ?? [];
        $city     = $found['city'] ?? [];
        $postcode = $found['postcode'] ?? [];

        $one_line  = static fn (string $v): string => trim((string) preg_replace('/\s*\n\s*/', ', ', $v));
        $addresses = $this->distinct($found['client_address'] ?? [], static fn (string $v): string => $fold($one_line($v)));

        if (count($addresses) > 1) {
            // Two different addresses: list them, choose neither.
            $alternatives = [];
            foreach (array_slice($addresses, 0, self::MAX_ALTERNATIVES) as $address) {
                $alternatives[] = $this->bound($one_line($address['value']), self::ADDRESS_LINE_MAX);
            }

            return [
                'address_line_1' => [] !== $line_1
                    ? $this->field($line_1, $fold, 'document_label_address')
                    : ReferralInboxCandidateField::ambiguous($alternatives, self::SOURCE, 'document_label_address'),
                'address_line_2' => $this->field($line_2, $fold, 'document_label_address'),
                'city'           => $this->field($city, $fold, 'document_label_address'),
                'postcode'       => $this->field($postcode, $fold, 'document_label_postcode'),
            ];
        }

        if (1 === count($addresses)) {
            $parts    = $this->split_address($addresses[0]['value']);
            $explicit = (bool) $addresses[0]['explicit'];

            if ([] === $line_1 && '' !== $parts['line_1']) {
                $line_1[] = ['value' => $parts['line_1'], 'explicit' => $explicit];
            }
            if ([] === $line_2 && '' !== $parts['line_2']) {
                $line_2[] = ['value' => $parts['line_2'], 'explicit' => false];
            }
            if ([] === $city && '' !== $parts['city']) {
                $city[] = ['value' => $parts['city'], 'explicit' => false];
            }
            if ('' !== $parts['postcode']) {
                $postcode[] = ['value' => $parts['postcode'], 'explicit' => $explicit];
            }
        }

        return [
            'address_line_1' => $this->field($line_1, $fold, 'document_label_address'),
            'address_line_2' => $this->field($line_2, $fold, 'document_label_address'),
            'city'           => $this->field($city, $fold, 'document_label_address'),
            'postcode'       => $this->field($postcode, $fold, 'document_label_postcode'),
        ];
    }

    /**
     * @return array{line_1: string, line_2: string, city: string, postcode: string}
     */
    private function split_address(string $address): array
    {
        $postcode = (string) ($this->postcode($address) ?? '');
        if ('' !== $postcode) {
            $address = (string) preg_replace('/\b[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}\b/i', '', $address, 1);
        }

        $parts = [];
        foreach (preg_split('/\s*[\n,;]\s*/u', $address) ?: [] as $part) {
            $part = trim($part, " \t.,;");
            if ('' !== $part) {
                $parts[] = $part;
            }
        }

        // "Flat 4" or a house name on its own belongs with the street that follows it.
        if (count($parts) >= 3
            && (1 === preg_match('/^(?:flat|apartment|apt|unit|room|suite|floor)\b/i', $parts[0])
                || (strlen($parts[0]) <= 12 && 1 !== preg_match('/\d+\s+\p{L}/u', $parts[0])))
        ) {
            $parts[1] = $parts[0] . ', ' . $parts[1];
            array_shift($parts);
        }

        $line_1 = (string) ($parts[0] ?? '');
        $city   = '';
        $line_2 = '';
        if (count($parts) >= 2) {
            $city   = (string) end($parts);
            $line_2 = implode(', ', array_slice($parts, 1, -1));
        }

        return [
            'line_1'   => $this->bound($line_1, self::ADDRESS_LINE_MAX),
            'line_2'   => $this->bound($line_2, self::ADDRESS_LINE_MAX),
            'city'     => $this->bound($city, self::CITY_MAX),
            'postcode' => $postcode,
        ];
    }

    /**
     * Several care sections are joined rather than treated as a conflict.
     *
     * @param array<int, array{value: string, explicit: bool}> $values
     */
    private function care_field(array $values, callable $fold): ReferralInboxCandidateField
    {
        $distinct = $this->distinct($values, $fold);
        if ([] === $distinct) {
            return ReferralInboxCandidateField::none();
        }

        $text = implode("\n\n", array_map(static fn (array $v): string => $v['value'], $distinct));

        return ReferralInboxCandidateField::single(
            $this->bound($text, self::CARE_MAX),
            self::SOURCE,
            'document_label_care_requirements',
            ReferralInboxCandidateField::BAND_STRONG
        );
    }

    /**
     * @param array<int, array{value: string, explicit: bool}> $values
     */
    private function service_hint(array $values, ExtractedDocument $document): ReferralInboxCandidateField
    {
        $hits = [];
        foreach ($values as $entry) {
            $text = ' ' . $this->phrase_text($entry['value']) . ' ';
            foreach (self::SERVICE_VALUE_PHRASES as $phrase => $canonical) {
                if (str_contains($text, ' ' . $phrase . ' ')) {
                    $hits[$canonical] = true;
                }
            }
        }

        if (1 === count($hits)) {
            return ReferralInboxCandidateField::single(
                (string) array_key_first($hits),
                self::SOURCE,
                'document_label_service_hint',
                ReferralInboxCandidateField::BAND_MODERATE
            );
        }

        if (count($hits) > 1) {
            return ReferralInboxCandidateField::ambiguous(array_keys($hits), self::SOURCE, 'service_hint_ambiguous');
        }

        // No labelled value: one service named anywhere in the form is still a useful hint.
        $text = ' ' . $this->phrase_text($document->plain_text(60000)) . ' ';
        foreach (self::SERVICE_TEXT_PHRASES as $phrase => $canonical) {
            if (str_contains($text, ' ' . $phrase . ' ')) {
                $hits[$canonical] = true;
            }
        }

        if (1 !== count($hits)) {
            return ReferralInboxCandidateField::none();
        }

        return ReferralInboxCandidateField::single(
            (string) array_key_first($hits),
            self::SOURCE,
            'document_text_service_hint',
            ReferralInboxCandidateField::BAND_MODERATE
        );
    }

    /**
     * @param array<int, array{value: string, explicit: bool}> $values
     */
    private function priority_hint(array $values): ReferralInboxCandidateField
    {
        $hits = [];
        foreach ($values as $entry) {
            $text = ' ' . $this->phrase_text($entry['value']) . ' ';

            $low = false;
            foreach ([' non urgent ', ' not urgent ', ' low '] as $phrase) {
                if (str_contains($text, $phrase)) {
                    $low  = true;
                    $text = str_replace($phrase, ' ', $text);
                }
            }
            if ($low) {
                $hits['low'] = true;
            }

            foreach ([' urgent ', ' emergency ', ' immediate ', ' same day ', ' asap ', ' critical '] as $phrase) {
                if (str_contains($text, $phrase)) {
                    $hits['urgent'] = true;
                }
            }
            if (str_contains($text, ' high ')) {
                $hits['high'] = true;
            }
            foreach ([' medium ', ' routine ', ' standard ', ' normal ', ' moderate '] as $phrase) {
                if (str_contains($text, $phrase)) {
                    $hits['medium'] = true;
                }
            }
        }

        if (1 !== count($hits)) {
            return ReferralInboxCandidateField::none();
        }

        return ReferralInboxCandidateField::single(
            (string) array_key_first($hits),
            self::SOURCE,
            'document_label_priority_hint',
            ReferralInboxCandidateField::BAND_MODERATE
        );
    }

    /**
     * @param array<int, array{value: string, explicit: bool}> $values
     */
    private function field(array $values, callable $identity, string $evidence): ReferralInboxCandidateField
    {
        $distinct = $this->distinct($values, $identity);
        if ([] === $distinct) {
            return ReferralInboxCandidateField::none();
        }

        if (count($distinct) > 1) {
            $alternatives = [];
            foreach (array_slice($distinct, 0, self::MAX_ALTERNATIVES) as $entry) {
                $alternatives[] = $entry['value'];
            }

            return ReferralInboxCandidateField::ambiguous($alternatives, self::SOURCE, $evidence);
        }

        return ReferralInboxCandidateField::single(
            $distinct[0]['value'],
            self::SOURCE,
            $evidence,
            $distinct[0]['explicit'] ? ReferralInboxCandidateField::BAND_STRONG : ReferralInboxCandidateField::BAND_MODERATE
        );
    }

    /**
     * @param array<int, array{value: string, explicit: bool}> $values
     * @return array<int, array{value: string, explicit: bool}>
     */
    private function distinct(array $values, callable $identity): array
    {
        $out  = [];
        $seen = [];
        foreach ($values as $entry) {
            $key = (string) $identity((string) $entry['value']);
            if (isset($seen[$key])) {
                if ($entry['explicit']) {
                    $out[$seen[$key]]['explicit'] = true;
                }
                continue;
            }
            $seen[$key] = count($out);
            $out[]      = ['value' => (string) $entry['value'], 'explicit' => (bool) $entry['explicit']];
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Value clean-up
    // ------------------------------------------------------------------

    /**
     * Trim, drop fill lines, and treat form placeholders as empty.
     */
    private function clean_value(string $value): string
    {
        $value = str_replace("\t", ' ', $value);
        $value = (string) preg_replace('/_{2,}|\.{3,}|\x{2026}+/u', ' ', $value);
        $value = trim($value, " \n:;|");
        $value = (string) preg_replace('/[ ]{2,}/', ' ', $value);

        $folded = strtolower(trim($value, " .-\u{2013}\u{2014}"));
        if ('' === $folded
            || in_array($folded, ['n/a', 'na', 'none', 'nil', 'tbc', 'tba', 'unknown', 'not known', 'not applicable', 'not provided'], true)
        ) {
            return '';
        }

        if (1 === preg_match('/^(?:click|tap|choose|select|enter)\b.{0,40}\b(?:text|date|item)\.?$/i', $value)) {
            return '';
        }

        return $value;
    }

    private function first_line(string $value): string
    {
        foreach (explode("\n", $value) as $line) {
            $line = trim($line);
            if ('' !== $line) {
                return $line;
            }
        }

        return '';
    }

    /**
     * A PDF can run two columns into one line ("Jane Doe Date of Birth 01/02/1950").
     * Cut a short value at a capitalised multi-word label that follows it.
     */
    private function before_embedded_label(string $value): string
    {
        static $markers = [
            'Date of Birth', 'Job Title', 'NHS Number', 'NHS No', 'Next of Kin', 'Contact Number',
            'Telephone Number', 'Phone Number', 'Mobile Number', 'Email Address', 'Home Address',
            'Post Code', 'Relationship to', 'Social Worker', 'Care Manager', 'First Name', 'Last Name',
            'Family Name', 'Preferred Name', 'Known As',
        ];

        $cut = strlen($value);
        foreach ($markers as $marker) {
            $at = stripos($value, ' ' . $marker);
            if (false !== $at && $at > 0 && $at < $cut && ctype_upper($value[$at + 1])) {
                $cut = $at;
            }
        }

        return trim(substr($value, 0, $cut));
    }

    private function name(string $raw): ?string
    {
        $raw = function_exists('sanitize_text_field') ? sanitize_text_field($raw) : trim(strip_tags($raw));
        $raw = trim((string) preg_replace('/\s+/u', ' ', $raw), " \t,;:-");
        if ('' === $raw || str_contains($raw, '@')) {
            return null;
        }

        if (1 !== preg_match('/\p{L}/u', $raw)) {
            return null;
        }

        // A sentence is not a name.
        if (count(preg_split('/\s+/u', $raw) ?: []) > 8) {
            return null;
        }

        return $this->bound($raw, self::NAME_MAX);
    }

    private function short_text(string $raw, int $max): ?string
    {
        $raw = function_exists('sanitize_text_field') ? sanitize_text_field($raw) : trim(strip_tags($raw));
        $raw = trim((string) preg_replace('/\s+/u', ' ', $raw), " \t,;:");
        if ('' === $raw || 1 !== preg_match('/[\p{L}\p{N}]/u', $raw)) {
            return null;
        }

        return $this->bound($raw, $max);
    }

    /**
     * Multi-line text with tidy lines.
     */
    private function block(string $raw, int $max): ?string
    {
        $lines = [];
        foreach (explode("\n", $raw) as $line) {
            $line = function_exists('sanitize_text_field') ? sanitize_text_field($line) : trim(strip_tags($line));
            $line = trim((string) preg_replace('/\s+/u', ' ', $line));
            if ('' !== $line) {
                $lines[] = $line;
            }
        }

        if ([] === $lines) {
            return null;
        }

        return $this->bound(implode("\n", $lines), $max);
    }

    private function email(string $raw): ?string
    {
        if (1 !== preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $raw, $matches)) {
            return null;
        }

        $email = rtrim((string) $matches[0], '.');
        $email = function_exists('sanitize_email') ? sanitize_email($email) : $email;
        if ('' === $email || strlen($email) > self::EMAIL_MAX) {
            return null;
        }

        $valid = function_exists('is_email') ? (bool) is_email($email) : (bool) filter_var($email, FILTER_VALIDATE_EMAIL);

        return $valid ? strtolower($email) : null;
    }

    private function phone(string $raw): ?string
    {
        if (1 !== preg_match('/(?:\+|\()?\d[\d ()\-]{6,}\d/', $raw, $matches)) {
            return null;
        }

        $phone = trim((string) preg_replace('/\s+/', ' ', (string) $matches[0]));
        if (1 === preg_match('/^\d{1,2}[\-.]\d{1,2}[\-.]\d{2,4}$/', $phone)) {
            return null;
        }

        $digits = strlen((string) preg_replace('/\D/', '', $phone));
        if ($digits < 8 || $digits > 15 || strlen($phone) > self::PHONE_MAX) {
            return null;
        }

        return $phone;
    }

    private function postcode(string $raw): ?string
    {
        if (1 !== preg_match('/\b([A-Z]{1,2}\d[A-Z\d]?)\s*(\d[A-Z]{2})\b/i', $raw, $matches)) {
            return null;
        }

        return strtoupper($matches[1]) . ' ' . strtoupper($matches[2]);
    }

    /**
     * UK day-first dates in common written forms, returned as YYYY-MM-DD.
     */
    private function date(string $raw, bool $is_birth): ?string
    {
        $year  = 0;
        $month = 0;
        $day   = 0;

        if (1 === preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/', $raw, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (1 === preg_match('/\b(\d{1,2})\s*[\/.\-]\s*(\d{1,2})\s*[\/.\-]\s*(\d{4}|\d{2})\b/', $raw, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], $this->full_year($m[3], $is_birth)];
            if ($month > 12 && $day <= 12) {
                [$day, $month] = [$month, $day];
            }
        } elseif (1 === preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(?:of\s+)?([A-Za-z]{3,9})\.?,?\s+(\d{4}|\d{2})\b/i', $raw, $m)) {
            $month = self::MONTHS[strtolower($m[2])] ?? 0;
            [$day, $year] = [(int) $m[1], $this->full_year($m[3], $is_birth)];
        } elseif (1 === preg_match('/\b([A-Za-z]{3,9})\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/i', $raw, $m)) {
            $month = self::MONTHS[strtolower($m[1])] ?? 0;
            [$day, $year] = [(int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        if ($month < 1 || $day < 1 || ! checkdate($month, $day, $year)) {
            return null;
        }

        $formatted = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $this_year = (int) gmdate('Y');

        if ($is_birth) {
            if ($year < 1900 || $formatted > gmdate('Y-m-d')) {
                return null;
            }
        } elseif ($year < 2000 || $year > $this_year + 5) {
            return null;
        }

        return $formatted;
    }

    private function full_year(string $digits, bool $is_birth): int
    {
        $year = (int) $digits;
        if (strlen($digits) > 2) {
            return $year;
        }

        if (! $is_birth) {
            return 2000 + $year;
        }

        return $year > ((int) gmdate('y')) ? 1900 + $year : 2000 + $year;
    }

    /**
     * Keep only the ticked choices of "☒ Urgent ☐ Routine"; otherwise the value as given.
     */
    private function checked_options(string $value): string
    {
        $value = $this->first_line($value) === $value ? $value : str_replace("\n", ' ', $value);

        $box = '(?:[\x{2610}\x{2611}\x{2612}\x{25A1}\x{25A0}\x{2713}\x{2714}\x{2717}\x{2718}]|\[[ xX\x{2713}\x{2714}]?\])';
        if (1 !== preg_match('/' . $box . '/u', $value)) {
            return $value;
        }

        $parts = preg_split('/(' . $box . ')/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $kept  = [];
        $count = count($parts);
        for ($i = 0; $i < $count; $i++) {
            if (1 !== preg_match('/^' . $box . '$/u', $parts[$i])) {
                continue;
            }
            $ticked = 1 === preg_match('/[\x{2611}\x{2612}\x{25A0}\x{2713}\x{2714}\x{2717}\x{2718}xX]/u', $parts[$i]);
            $text   = ($i + 1) < $count && 1 !== preg_match('/^' . $box . '$/u', $parts[$i + 1])
                ? trim($parts[$i + 1])
                : '';
            if ($ticked && '' !== $text) {
                $kept[] = $text;
            }
        }

        return implode(' / ', $kept);
    }

    /**
     * Lower case words separated by single spaces, for padded phrase checks.
     */
    private function phrase_text(string $text): string
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim($text);
    }

    private function bound(string $value, int $max): string
    {
        if ($max <= 0) {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}
