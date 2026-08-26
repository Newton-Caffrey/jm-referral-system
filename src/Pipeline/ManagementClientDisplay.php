<?php

namespace JMReferral\Pipeline;

/**
 * Management Dashboard display helpers (privacy-safe client labels).
 * Initials are derived in memory only — never persisted.
 */
class ManagementClientDisplay
{
    /**
     * Build initials from a full client name for management presentation.
     *
     * Examples: "Raymond Reddington" → "R.R."; "Mary-Jane Watson" → "M.W.";
     * "Madonna" → "M."; empty → "—".
     */
    public static function initials_from_name(string $full_name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $full_name) ?? '');
        if ('' === $name) {
            return '—';
        }

        // Split on spaces; treat hyphenated tokens as one word (first letter only).
        $parts = preg_split('/\s+/u', $name) ?: [];
        $letters = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ('' === $part) {
                continue;
            }
            // Prefer letter after optional leading punctuation; take first Unicode letter.
            if (preg_match('/\p{L}/u', $part, $m)) {
                $letters[] = mb_strtoupper($m[0], 'UTF-8');
            }
        }

        if ([] === $letters) {
            return '—';
        }

        if (1 === count($letters)) {
            return $letters[0] . '.';
        }

        // First + last significant name parts (management convention).
        $first = $letters[0];
        $last  = $letters[count($letters) - 1];

        return $first . '.' . $last . '.';
    }

    /**
     * Strip full client names from free-text that may have been composed by other services.
     * Replaces the exact name string with initials when the name is known.
     */
    public static function scrub_text(string $text, string $full_name, string $initials): string
    {
        $name = trim($full_name);
        if ('' === $name || '—' === $initials) {
            return $text;
        }

        return str_replace($name, $initials, $text);
    }
}
