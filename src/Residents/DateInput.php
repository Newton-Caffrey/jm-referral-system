<?php

namespace JMReferral\Residents;

/**
 * Phase 5F.1: reads dates typed by UK staff or exported from spreadsheets.
 *
 * Accepts 2024-03-01, 01/03/2024, 1/3/2024, 01-03-2024 and 01.03.2024 (day first).
 * Two-digit years and month-first dates are refused rather than guessed.
 */
class DateInput
{
    /**
     * @return string|null Y-m-d, or null when the value is not a real date in an accepted form.
     */
    public static function parse(string $value): ?string
    {
        $value = trim($value);
        if ('' === $value || strlen($value) > 20) {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            return self::build((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // Spreadsheet exports sometimes append a midnight time.
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})[ T]00:00(:00)?$/', $value, $m)) {
            return self::build((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})$#', $value, $m)) {
            return self::build((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    private static function build(int $year, int $month, int $day): ?string
    {
        if ($year < 1900 || $year > 2100 || ! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    public static function today(): string
    {
        return gmdate('Y-m-d', (int) current_time('timestamp'));
    }

    /**
     * Human date for messages, e.g. 1 March 2024.
     */
    public static function display(string $ymd): string
    {
        $ts = strtotime($ymd . ' 00:00:00 UTC');

        return false === $ts ? $ymd : gmdate('j F Y', $ts);
    }
}
