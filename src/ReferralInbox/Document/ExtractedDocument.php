<?php

namespace JMReferral\ReferralInbox\Document;

/**
 * Text read out of an uploaded referral form (Phase 5E.1).
 *
 * In memory only. Rows keep table structure: a paragraph is a one-cell row,
 * a table row is one cell per column. Form fields are label/value pairs from
 * a fillable PDF. No string cast, because the content is personal data.
 */
final class ExtractedDocument
{
    public const STATUS_OK          = 'ok';
    public const STATUS_NO_TEXT     = 'no_text';
    public const STATUS_UNSUPPORTED = 'unsupported';
    public const STATUS_UNREADABLE  = 'unreadable';

    public const MAX_ROWS  = 4000;
    public const MAX_CHARS = 400000;

    /**
     * @param array<int, array<int, string>> $rows
     * @param array<int, array{0: string, 1: string}> $form_fields
     */
    private function __construct(
        private string $status,
        private array $rows,
        private array $form_fields,
        private bool $lines_may_wrap = false
    ) {
    }

    /**
     * @param array<int, array<int, string>> $rows
     * @param array<int, array{0: string, 1: string}> $form_fields
     * @param bool $lines_may_wrap True when a long value can spill onto following
     *                             rows, as in text read from a PDF page.
     */
    public static function from_content(array $rows, array $form_fields = [], bool $lines_may_wrap = false): self
    {
        $clean_rows = [];
        $budget     = self::MAX_CHARS;

        foreach ($rows as $row) {
            if (! is_array($row) || count($clean_rows) >= self::MAX_ROWS || $budget <= 0) {
                break;
            }

            $cells = [];
            foreach ($row as $cell) {
                if (! is_string($cell)) {
                    continue;
                }
                $cell = self::clean_text($cell);
                if (strlen($cell) > $budget) {
                    $cell = substr($cell, 0, $budget);
                }
                $budget -= strlen($cell);
                $cells[] = $cell;
            }

            if ('' === trim(implode('', $cells))) {
                continue;
            }

            $clean_rows[] = $cells;
        }

        $clean_fields = [];
        foreach ($form_fields as $pair) {
            if (! is_array($pair) || count($clean_fields) >= 500) {
                continue;
            }
            $label = self::clean_text((string) ($pair[0] ?? ''));
            $value = self::clean_text((string) ($pair[1] ?? ''));
            if ('' === trim($label) || '' === trim($value)) {
                continue;
            }
            $clean_fields[] = [$label, $value];
        }

        if ([] === $clean_rows && [] === $clean_fields) {
            return new self(self::STATUS_NO_TEXT, [], []);
        }

        return new self(self::STATUS_OK, $clean_rows, $clean_fields, $lines_may_wrap);
    }

    public static function unsupported(): self
    {
        return new self(self::STATUS_UNSUPPORTED, [], []);
    }

    public static function unreadable(): self
    {
        return new self(self::STATUS_UNREADABLE, [], []);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function is_readable(): bool
    {
        return self::STATUS_OK === $this->status;
    }

    public function lines_may_wrap(): bool
    {
        return $this->lines_may_wrap;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public function form_fields(): array
    {
        return $this->form_fields;
    }

    /**
     * Plain text for the on-screen reference panel. Never stored.
     */
    public function plain_text(int $max_chars = 20000): string
    {
        $lines = [];
        foreach ($this->form_fields as $pair) {
            $lines[] = $pair[0] . ': ' . $pair[1];
        }
        if ([] !== $lines && [] !== $this->rows) {
            $lines[] = '';
        }
        foreach ($this->rows as $row) {
            $cells = [];
            foreach ($row as $cell) {
                $cell = trim($cell);
                if ('' !== $cell) {
                    $cells[] = $cell;
                }
            }
            if ([] !== $cells) {
                $lines[] = implode(' | ', $cells);
            }
        }

        $text = implode("\n", $lines);
        if ($max_chars > 0 && strlen($text) > $max_chars) {
            $text = function_exists('mb_strcut')
                ? mb_strcut($text, 0, $max_chars, 'UTF-8')
                : substr($text, 0, $max_chars);
        }

        return $text;
    }

    /**
     * Valid UTF-8, no control characters, consistent line breaks and spaces.
     */
    private static function clean_text(string $text): string
    {
        if (function_exists('mb_check_encoding') && ! mb_check_encoding($text, 'UTF-8')) {
            $converted = function_exists('mb_convert_encoding')
                ? @mb_convert_encoding($text, 'UTF-8', 'UTF-8')
                : '';
            $text = is_string($converted) ? $converted : '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Non-breaking and typographic spaces become ordinary spaces.
        $text = (string) preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\x{FEFF}]/u', ' ', $text);
        // Drop control characters except tab and newline.
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        return $text;
    }
}
