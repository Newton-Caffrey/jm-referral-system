<?php

namespace JMReferral\Support;

/**
 * Neutralizes CSV formula injection for spreadsheet clients.
 */
class CsvExportHelper
{
    /**
     * Characters that can trigger formula execution when leading a cell.
     */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Escapes a single cell value for CSV export.
     *
     * Intentional integers and floats are left unchanged. String values that
     * begin with a formula-trigger character are prefixed with a single apostrophe.
     *
     * @param mixed $value Cell value.
     * @return int|float|string
     */
    public static function cell(mixed $value): int|float|string
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (null === $value || is_bool($value)) {
            return '';
        }

        $string = (string) $value;

        if ('' === $string) {
            return '';
        }

        $first = $string[0];

        if (in_array($first, self::FORMULA_PREFIXES, true)) {
            return "'" . $string;
        }

        return $string;
    }

    /**
     * Escapes every cell in a row.
     *
     * @param array<int|string, mixed> $row
     * @return array<int, int|float|string>
     */
    public static function row(array $row): array
    {
        $escaped = [];

        foreach ($row as $cell) {
            $escaped[] = self::cell($cell);
        }

        return $escaped;
    }

    /**
     * Writes an escaped CSV row to an open stream.
     *
     * @param resource             $handle
     * @param array<int|string, mixed> $row
     */
    public static function put_row($handle, array $row): void
    {
        fputcsv($handle, self::row($row));
    }
}
