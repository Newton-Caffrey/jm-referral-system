<?php

namespace JMReferral\ReferralInbox;

use JMReferral\LocalAuthority\LocalAuthorityRepository;

/**
 * Advisory, read-only candidate extraction from stored Inbox metadata (Phase 5D.3).
 *
 * Suggestions stay in memory. This class does not write Inbox rows, options,
 * transients, logs, or referrals, and it does not call the sender matcher.
 * Detection status is copied for context and does not decide field values.
 */
class ReferralInboxCandidateExtractor
{
    private const NAME_MAX  = 255;
    private const EMAIL_MAX = 190;
    private const PHONE_MAX = 50;
    private const MAX_LINES = 40;
    private const MAX_ALTERNATIVES = 8;
    private const MAX_ATTACHMENTS  = 25;

    /**
     * Whole-string mailbox labels. A personal name is not inferred from these.
     *
     * @var array<int, string>
     */
    private const GENERIC_SENDER_NAMES = [
        'referrals',
        'referrals team',
        'referral team',
        'admissions',
        'admissions team',
        'duty team',
        'care team',
        'placement team',
        'commissioning',
        'commissioning team',
        'inbox',
    ];

    /**
     * @var array<int, string>
     */
    private const CLIENT_NAME_LABELS = [
        'service user name',
        'name of client',
        'client name',
        'service user',
        'client',
        'person',
    ];

    /**
     * @var array<int, string>
     */
    private const CLIENT_EMAIL_LABELS = [
        'service user email',
        'client email',
        'email address',
    ];

    /**
     * @var array<int, string>
     */
    private const CLIENT_PHONE_LABELS = [
        'client phone',
        'contact number',
        'telephone',
        'mobile',
        'phone',
    ];

    /**
     * @var array<string, string>
     */
    private const SERVICE_PHRASES = [
        'supported living'       => 'supported_living',
        'home care'              => 'home_care',
        'domiciliary care'       => 'home_care',
        'care at home'           => 'home_care',
        'residential care'       => 'residential_care',
        'residential placement'  => 'residential_care',
    ];

    /**
     * @var array<string, string>
     */
    private const PRIORITY_PHRASES = [
        'urgent referral'  => 'urgent',
        'same day'         => 'urgent',
        'immediate'        => 'urgent',
        'emergency'        => 'urgent',
        'urgent'           => 'urgent',
        'high priority'    => 'high',
        'priority referral'=> 'high',
    ];

    public function __construct(
        private ReferralInboxService $inbox_service,
        private LocalAuthorityRepository $authority_repository
    ) {
    }

    public function extract(int $inbox_id): ReferralInboxCandidateResult
    {
        $item = $this->inbox_service->find($inbox_id);
        if (null === $item) {
            return ReferralInboxCandidateResult::not_found();
        }

        return $this->extractFromFields(
            $item,
            $this->inbox_service->list_attachments($inbox_id),
            $this->stored_authority_name($item)
        );
    }

    /**
     * Pure evaluation of an already-loaded snapshot.
     *
     * @param array<string, mixed> $item
     * @param array<int, array<string, mixed>> $attachments
     */
    public function extractFromFields(array $item, array $attachments = [], ?string $authority_name = null): ReferralInboxCandidateResult
    {
        $subject = $this->bound_text((string) ($item['subject'] ?? ''), ReferralInboxLimits::SUBJECT_MAX);
        $body    = $this->bound_text((string) ($item['body_preview'] ?? ''), ReferralInboxLimits::BODY_PREVIEW_MAX);
        $files   = $this->filenames($attachments);

        return ReferralInboxCandidateResult::found(
            $this->client_name($body),
            $this->client_email($body),
            $this->client_phone($body),
            $this->referrer_name((string) ($item['sender_name'] ?? '')),
            $this->referrer_email((string) ($item['sender_email'] ?? '')),
            $this->referrer_organisation($item, $authority_name),
            $this->service_hint($subject, $body, $files),
            $this->priority_hint($subject, $body),
            (string) ($item['detection_status'] ?? ''),
            (string) ($item['detection_reason'] ?? '')
        );
    }

    private function client_name(string $body): ReferralInboxCandidateField
    {
        return $this->labelled_field(
            $body,
            self::CLIENT_NAME_LABELS,
            [$this, 'sanitise_name'],
            'body_preview',
            'body_label_client_name',
            ReferralInboxCandidateField::BAND_STRONG,
            false
        );
    }

    private function client_email(string $body): ReferralInboxCandidateField
    {
        return $this->labelled_field(
            $body,
            self::CLIENT_EMAIL_LABELS,
            function (string $raw): ?string {
                return $this->sanitise_email($this->isolate_email_token($raw));
            },
            'body_preview',
            'body_label_client_email',
            ReferralInboxCandidateField::BAND_STRONG,
            true
        );
    }

    private function client_phone(string $body): ReferralInboxCandidateField
    {
        return $this->labelled_field(
            $body,
            self::CLIENT_PHONE_LABELS,
            function (string $raw): ?string {
                return $this->sanitise_phone($this->isolate_phone_token($raw));
            },
            'body_preview',
            'body_label_client_phone',
            ReferralInboxCandidateField::BAND_STRONG,
            false,
            static function (string $value): string {
                return (string) preg_replace('/\D/', '', $value);
            }
        );
    }

    private function referrer_name(string $sender_name): ReferralInboxCandidateField
    {
        $name = $this->sanitise_name($sender_name);
        if (null === $name || $this->is_generic_sender_name($name)) {
            return ReferralInboxCandidateField::none();
        }

        return ReferralInboxCandidateField::single(
            $name,
            'sender_name',
            'sender_name_referrer',
            ReferralInboxCandidateField::BAND_STRONG
        );
    }

    private function referrer_email(string $sender_email): ReferralInboxCandidateField
    {
        $email = $this->sanitise_email($sender_email);
        if (null === $email) {
            return ReferralInboxCandidateField::none();
        }

        return ReferralInboxCandidateField::single(
            $email,
            'sender_email',
            'sender_email_referrer',
            ReferralInboxCandidateField::BAND_STRONG
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function referrer_organisation(array $item, ?string $authority_name): ReferralInboxCandidateField
    {
        $origin = strtolower(trim((string) ($item['local_authority_origin'] ?? '')));
        if (LocalAuthorityOrigin::CLEARED === $origin) {
            return ReferralInboxCandidateField::none();
        }

        $authority_id = (int) ($item['local_authority_id'] ?? 0);
        if ($authority_id <= 0) {
            return ReferralInboxCandidateField::none();
        }

        $name = $this->sanitise_name((string) $authority_name);
        if (null === $name) {
            return ReferralInboxCandidateField::none();
        }

        if (LocalAuthorityOrigin::CONFIRMED === $origin) {
            return ReferralInboxCandidateField::single(
                $name,
                'stored_local_authority',
                'staff_confirmed_authority',
                ReferralInboxCandidateField::BAND_STRONG
            );
        }

        if (LocalAuthorityOrigin::SUGGESTED === $origin) {
            return ReferralInboxCandidateField::single(
                $name,
                'stored_local_authority',
                'suggested_authority',
                ReferralInboxCandidateField::BAND_MODERATE
            );
        }

        return ReferralInboxCandidateField::single(
            $name,
            'stored_local_authority',
            'linked_authority_unknown_origin',
            ReferralInboxCandidateField::BAND_MODERATE
        );
    }

    /**
     * @param array<int, string> $filenames
     */
    private function service_hint(string $subject, string $body, array $filenames): ReferralInboxCandidateField
    {
        $found = [];

        $this->collect_phrase_hits($subject, self::SERVICE_PHRASES, 'subject', 'subject_service_hint', $found);
        $this->collect_phrase_hits($body, self::SERVICE_PHRASES, 'body_preview', 'body_service_hint', $found);
        foreach ($filenames as $filename) {
            $this->collect_phrase_hits($filename, self::SERVICE_PHRASES, 'attachment_filename', 'attachment_filename_service_hint', $found);
        }

        return $this->hint_field($found, 'service_hint_ambiguous');
    }

    private function priority_hint(string $subject, string $body): ReferralInboxCandidateField
    {
        $found = [];
        $this->collect_phrase_hits($subject, self::PRIORITY_PHRASES, 'subject', 'subject_priority_hint', $found);
        $this->collect_phrase_hits($body, self::PRIORITY_PHRASES, 'body_preview', 'body_priority_hint', $found);

        return $this->hint_field($found, 'priority_hint_ambiguous');
    }

    /**
     * @param array<string, string> $phrases
     * @param array<string, array{source: string, evidence: string}> $found
     */
    private function collect_phrase_hits(string $text, array $phrases, string $source, string $evidence, array &$found): void
    {
        foreach ($phrases as $phrase => $canonical) {
            if (isset($found[$canonical])) {
                continue;
            }
            if ($this->has_explicit_phrase($text, $phrase)) {
                $found[$canonical] = [
                    'source'   => $source,
                    'evidence' => $evidence,
                ];
            }
        }
    }

    /**
     * @param array<string, array{source: string, evidence: string}> $found
     */
    private function hint_field(array $found, string $ambiguous_evidence): ReferralInboxCandidateField
    {
        if ([] === $found) {
            return ReferralInboxCandidateField::none();
        }

        $values = array_keys($found);
        if (count($values) > 1) {
            return ReferralInboxCandidateField::ambiguous(
                array_slice($values, 0, self::MAX_ALTERNATIVES),
                '',
                $ambiguous_evidence
            );
        }

        $canonical = (string) $values[0];
        $meta      = $found[$canonical];

        return ReferralInboxCandidateField::single(
            $canonical,
            $meta['source'],
            $meta['evidence'],
            ReferralInboxCandidateField::BAND_MODERATE
        );
    }

    /**
     * @param array<int, string> $labels
     * @param callable(string): ?string $sanitise
     * @param callable(string): string|null $identity
     */
    private function labelled_field(
        string $body,
        array $labels,
        callable $sanitise,
        string $source,
        string $evidence,
        string $band,
        bool $case_fold,
        ?callable $identity = null
    ): ReferralInboxCandidateField {
        $values = [];
        $keys   = [];

        foreach ($this->labelled_values($body, $labels) as $raw) {
            $clean = $sanitise($raw);
            if (null === $clean || '' === $clean) {
                continue;
            }

            if (null !== $identity) {
                $key = $identity($clean);
            } else {
                $key = $case_fold ? strtolower($clean) : $clean;
            }
            if (isset($keys[$key])) {
                continue;
            }

            $keys[$key] = true;
            $values[]   = $clean;
            if (count($values) >= self::MAX_ALTERNATIVES) {
                break;
            }
        }

        if ([] === $values) {
            return ReferralInboxCandidateField::none();
        }

        if (count($values) > 1) {
            return ReferralInboxCandidateField::ambiguous($values, $source, $evidence);
        }

        return ReferralInboxCandidateField::single($values[0], $source, $evidence, $band);
    }

    /**
     * Values for one field's labels.
     *
     * Inbox storage keeps body_preview as one line, so a label is recognised
     * at the start of the preview or after whitespace, not only after a newline.
     * The value ends at the next recognised label. Labels are fixed constants.
     *
     * @param array<int, string> $labels
     * @return array<int, string>
     */
    private function labelled_values(string $text, array $labels): array
    {
        if (strlen($text) > ReferralInboxLimits::BODY_PREVIEW_MAX) {
            $text = substr($text, 0, ReferralInboxLimits::BODY_PREVIEW_MAX);
        }

        $wanted = [];
        foreach ($labels as $label) {
            $wanted[strtolower($label)] = true;
        }

        $spans = $this->label_spans($text);
        $found = [];
        $count = count($spans);
        for ($index = 0; $index < $count; $index++) {
            $label = $spans[$index]['label'];
            if (! isset($wanted[$label])) {
                continue;
            }

            $start = $spans[$index]['value_start'];
            $end   = ($index + 1) < $count ? $spans[$index + 1]['label_start'] : strlen($text);
            if ($end < $start) {
                continue;
            }

            $value = trim(substr($text, $start, $end - $start));
            if ('' === $value) {
                continue;
            }

            $found[] = $value;
            if (count($found) >= self::MAX_ALTERNATIVES) {
                break;
            }
        }

        return $found;
    }

    /**
     * @return array<int, array{label: string, label_start: int, value_start: int}>
     */
    private function label_spans(string $text): array
    {
        $labels = $this->labels_longest_first($this->recognised_labels());
        $length = strlen($text);
        $spans  = [];
        $offset = 0;
        $guard  = 0;

        while ($offset < $length && $guard < $length && count($spans) < self::MAX_LINES) {
            $guard++;
            if (! $this->is_label_boundary($text, $offset)) {
                $offset++;
                continue;
            }

            $match = $this->match_label_at($text, $offset, $labels);
            if (null === $match) {
                $offset++;
                continue;
            }

            $spans[] = $match;
            if ($match['value_start'] <= $offset) {
                $offset++;
                continue;
            }

            $offset = $match['value_start'];
        }

        return $spans;
    }

    /**
     * @return array<int, string>
     */
    private function recognised_labels(): array
    {
        return array_values(array_unique(array_merge(
            self::CLIENT_NAME_LABELS,
            self::CLIENT_EMAIL_LABELS,
            self::CLIENT_PHONE_LABELS
        )));
    }

    private function is_label_boundary(string $text, int $offset): bool
    {
        if (0 === $offset) {
            return true;
        }

        return 1 === preg_match('/\s/', $text[$offset - 1]);
    }

    /**
     * @param array<int, string> $labels
     * @return array{label: string, label_start: int, value_start: int}|null
     */
    private function match_label_at(string $text, int $offset, array $labels): ?array
    {
        $slice = substr($text, $offset);
        foreach ($labels as $label) {
            $pattern = '/^' . preg_quote($label, '/') . '\s*[:\-]\s*/iu';
            if (1 !== preg_match($pattern, $slice, $matches)) {
                continue;
            }

            $consumed = strlen((string) ($matches[0] ?? ''));
            if ($consumed <= strlen($label)) {
                continue;
            }

            return [
                'label'       => strtolower($label),
                'label_start' => $offset,
                'value_start' => $offset + $consumed,
            ];
        }

        return null;
    }

    /**
     * The stored preview appends the rest of the message after the last label.
     * Keep a single leading address. Two addresses in one value are left intact
     * so neither is chosen.
     */
    private function isolate_email_token(string $raw): string
    {
        $raw = trim($raw);
        if ('' === $raw || 1 !== substr_count($raw, '@')) {
            return $raw;
        }

        if (1 !== preg_match('/^\S+/u', $raw, $matches)) {
            return $raw;
        }

        $token = (string) ($matches[0] ?? '');
        if ('' === $token || ! str_contains($token, '@') || $token === $raw) {
            return $raw;
        }

        return $token;
    }

    /**
     * Keep a leading phone when the stored preview continues with a sentence.
     * A further phone-length number in that remainder is not discarded.
     */
    private function isolate_phone_token(string $raw): string
    {
        $raw = trim($raw);
        if ('' === $raw || 1 !== preg_match('/[A-Za-z]/', $raw)) {
            return $raw;
        }

        if (1 !== preg_match('/^(\+?[0-9][0-9() \-]*)/u', $raw, $matches)) {
            return $raw;
        }

        $token = trim((string) ($matches[1] ?? ''));
        $rest  = trim(substr($raw, strlen((string) ($matches[1] ?? ''))));
        if ('' === $token || '' === $rest) {
            return $raw;
        }

        $rest_digits = preg_replace('/\D/', '', $rest);
        if (strlen((string) $rest_digits) >= 8) {
            return $raw;
        }

        if (1 !== preg_match('/^\p{L}/u', $rest) && 1 !== preg_match('/^[A-Za-z]/', $rest)) {
            return $raw;
        }

        $word_count = preg_match_all('/\p{L}{2,}/u', $rest);
        if (false === $word_count || $word_count < 2) {
            return $raw;
        }

        return $token;
    }

    /**
     * @param array<int, string> $labels
     * @return array<int, string>
     */
    private function labels_longest_first(array $labels): array
    {
        usort(
            $labels,
            static function (string $left, string $right): int {
                return strlen($right) <=> strlen($left);
            }
        );

        return $labels;
    }

    private function has_explicit_phrase(string $text, string $phrase): bool
    {
        $haystack = ReferralInboxDetectionRules::normalise($text);
        $needle   = ReferralInboxDetectionRules::normalise($phrase);
        if ('' === $haystack || '' === $needle) {
            return false;
        }

        $padded = ' ' . $haystack . ' ';
        $token  = ' ' . $needle . ' ';
        if (! str_contains($padded, $token)) {
            return false;
        }

        $without_negation = str_replace(' not ' . $needle . ' ', ' ', $padded);

        return str_contains($without_negation, $token);
    }

    private function is_generic_sender_name(string $name): bool
    {
        $normalised = ReferralInboxDetectionRules::normalise($name);

        return in_array($normalised, self::GENERIC_SENDER_NAMES, true);
    }

    private function sanitise_name(string $raw): ?string
    {
        $raw = function_exists('sanitize_text_field') ? sanitize_text_field($raw) : trim(strip_tags($raw));
        $raw = trim((string) preg_replace('/\s+/u', ' ', $raw));
        if ('' === $raw) {
            return null;
        }

        if (function_exists('mb_substr')) {
            $raw = mb_substr($raw, 0, self::NAME_MAX, 'UTF-8');
        } else {
            $raw = substr($raw, 0, self::NAME_MAX);
        }

        $raw = trim($raw);
        if ('' === $raw || str_contains($raw, '@')) {
            return null;
        }

        if (1 !== preg_match('/\p{L}/u', $raw) && 1 !== preg_match('/[A-Za-z]/', $raw)) {
            return null;
        }

        return $raw;
    }

    private function sanitise_email(string $raw): ?string
    {
        $raw = trim(function_exists('wp_strip_all_tags') ? wp_strip_all_tags($raw) : strip_tags($raw));
        if ('' === $raw || strlen($raw) > 320) {
            return null;
        }

        $email = function_exists('sanitize_email') ? sanitize_email($raw) : strtolower(trim($raw));
        if (strlen($email) > self::EMAIL_MAX) {
            return null;
        }

        $valid = function_exists('is_email') ? (bool) is_email($email) : (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
        if (! $valid) {
            return null;
        }

        return strtolower($email);
    }

    private function sanitise_phone(string $raw): ?string
    {
        $raw = trim(function_exists('wp_strip_all_tags') ? wp_strip_all_tags($raw) : strip_tags($raw));
        if ('' === $raw || strlen($raw) > 80) {
            return null;
        }

        if (1 === preg_match('/\d{1,4}[\/\-.]\d{1,2}[\/\-.]\d{1,4}/', $raw)) {
            return null;
        }

        if (1 === preg_match('/[A-Za-z]/', $raw)) {
            return null;
        }

        $kept = preg_replace('/[^0-9+() \-]/', '', $raw);
        $kept = trim((string) preg_replace('/\s+/', ' ', (string) $kept));
        if ('' === $kept || strlen($kept) > self::PHONE_MAX) {
            return null;
        }

        if (str_contains(substr($kept, 1), '+')) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $kept);
        $count  = strlen((string) $digits);
        if ($count < 8 || $count > 15) {
            return null;
        }

        return $kept;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function stored_authority_name(array $item): ?string
    {
        $origin = strtolower(trim((string) ($item['local_authority_origin'] ?? '')));
        if (LocalAuthorityOrigin::CLEARED === $origin) {
            return null;
        }

        $authority_id = (int) ($item['local_authority_id'] ?? 0);
        if ($authority_id <= 0) {
            return null;
        }

        $authority = $this->authority_repository->findById($authority_id);
        if (! is_array($authority)) {
            return null;
        }

        $name = trim((string) ($authority['name'] ?? ''));

        return '' === $name ? null : $name;
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     * @return array<int, string>
     */
    private function filenames(array $attachments): array
    {
        $names = [];
        foreach (array_slice($attachments, 0, self::MAX_ATTACHMENTS) as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $name = $this->bound_text((string) ($attachment['filename'] ?? ''), ReferralInboxLimits::FILENAME_MAX);
            if ('' !== $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function bound_text(string $text, int $max): string
    {
        if ($max <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max, 'UTF-8');
        }

        return substr($text, 0, $max);
    }
}
