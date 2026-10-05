<?php

namespace JMReferral\ReferralInbox;

/**
 * Deterministic phrase lists for Referral Inbox detection (Phase 5D.1).
 *
 * Phrases are literals owned by this class. Callers never supply the pattern.
 * Matching is padded substring search after normalisation, so a generic word
 * such as "care", "support", or "assessment" is not a positive signal.
 *
 * Signal sources are independent categories: subject, body_preview, and
 * attachment filenames (all filenames count as one category).
 */
final class ReferralInboxDetectionRules
{
    public const SOURCE_SUBJECT    = 'subject';
    public const SOURCE_BODY       = 'body_preview';
    public const SOURCE_ATTACHMENT = 'attachment_filename';

    /**
     * Contiguous referral phrases for subject, body preview, and filenames.
     *
     * @return array<int, string>
     */
    public static function positive_phrases(): array
    {
        return [
            'new referral',
            'referral form',
            'referral opportunity',
            'supported living referral',
            'home care referral',
            'domiciliary care referral',
            'residential care referral',
            'placement request',
            'placement opportunity',
            'care package request',
            'care package',
            'request for care',
            'request for support',
            'support package',
            'care enquiry',
        ];
    }

    /**
     * Extra filename tokens. Not applied to subject or body preview, so a
     * subject that only says "referral" is not a positive signal.
     *
     * @return array<int, string>
     */
    public static function filename_tokens(): array
    {
        return [
            'referral',
            'referral-form',
            'care-plan',
            'support-plan',
            'needs-assessment',
        ];
    }

    /**
     * Explicit non-referral phrases. Absence of a positive phrase is not negative.
     *
     * @return array<int, string>
     */
    public static function negative_phrases(): array
    {
        return [
            'automatic reply',
            'out of office',
            'undeliverable',
            'delivery status notification',
            'invoice',
            'remittance',
            'payment confirmation',
            'newsletter',
            'password reset',
        ];
    }

    /**
     * @param array<int, string> $filenames
     * @return array<int, string> Hit source categories, stable order.
     */
    public static function positive_sources(string $subject, string $body_preview, array $filenames): array
    {
        $sources = [];

        if (self::text_has_phrase($subject, self::positive_phrases())) {
            $sources[] = self::SOURCE_SUBJECT;
        }

        if (self::text_has_phrase($body_preview, self::positive_phrases())) {
            $sources[] = self::SOURCE_BODY;
        }

        if (self::filenames_have_positive($filenames)) {
            $sources[] = self::SOURCE_ATTACHMENT;
        }

        return $sources;
    }

    /**
     * @param array<int, string> $filenames
     * @return array<int, string>
     */
    public static function negative_sources(string $subject, string $body_preview, array $filenames): array
    {
        $sources = [];

        if (self::text_has_phrase($subject, self::negative_phrases())) {
            $sources[] = self::SOURCE_SUBJECT;
        }

        if (self::text_has_phrase($body_preview, self::negative_phrases())) {
            $sources[] = self::SOURCE_BODY;
        }

        if (self::filenames_have_phrase($filenames, self::negative_phrases())) {
            $sources[] = self::SOURCE_ATTACHMENT;
        }

        return $sources;
    }

    /**
     * Lowercase, treat separator characters as boundaries, collapse whitespace.
     * Does not modify stored Inbox text. The pattern is a fixed literal.
     */
    public static function normalise(string $text): string
    {
        if (function_exists('mb_strtolower')) {
            $text = mb_strtolower($text, 'UTF-8');
        } else {
            $text = strtolower($text);
        }

        $text = strtr($text, [
            '-' => ' ',
            '_' => ' ',
            '.' => ' ',
        ]);

        $collapsed = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $text);
        if (! is_string($collapsed)) {
            $collapsed = preg_replace('/[^a-z0-9 ]+/', ' ', $text);
        }

        $collapsed = preg_replace('/\s+/', ' ', (string) $collapsed);

        return trim((string) $collapsed);
    }

    /**
     * @param array<int, string> $phrases
     */
    public static function text_has_phrase(string $text, array $phrases): bool
    {
        $haystack = self::normalise($text);
        if ('' === $haystack) {
            return false;
        }

        $padded = ' ' . $haystack . ' ';

        foreach ($phrases as $phrase) {
            $needle = self::normalise($phrase);
            if ('' === $needle) {
                continue;
            }

            if (str_contains($padded, ' ' . $needle . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $filenames
     */
    private static function filenames_have_positive(array $filenames): bool
    {
        if (self::filenames_have_phrase($filenames, self::positive_phrases())) {
            return true;
        }

        return self::filenames_have_phrase($filenames, self::filename_tokens());
    }

    /**
     * @param array<int, string> $filenames
     * @param array<int, string> $phrases
     */
    private static function filenames_have_phrase(array $filenames, array $phrases): bool
    {
        foreach ($filenames as $filename) {
            if (! is_string($filename)) {
                continue;
            }

            if (self::text_has_phrase($filename, $phrases)) {
                return true;
            }
        }

        return false;
    }
}
