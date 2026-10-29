<?php

namespace JMReferral\Residents\Import;

/**
 * Phase 5F.1: reads the rows of an uploaded CSV or Excel (.xlsx) file as plain strings.
 *
 * Reads on the server only. For .xlsx it opens the zip directly (no macros, formulas
 * or external links are evaluated; a formula cell contributes its last saved value).
 * Size limits guard against oversized or zip-bomb files.
 */
class SpreadsheetReader
{
    public const MAX_XML_BYTES = 20971520;
    public const MAX_COLUMNS = 60;

    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PKG_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * @return array{ok: true, rows: array<int, array<int, string>>}|array{ok: false, error: string}
     *     Rows keyed by their 1-based row number in the file.
     */
    public function read(string $path, string $original_name, int $max_rows): array
    {
        $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));

        if ('csv' === $extension) {
            return $this->read_csv($path, $max_rows);
        }

        if ('xlsx' === $extension) {
            return $this->read_xlsx($path, $max_rows);
        }

        return ['ok' => false, 'error' => 'type'];
    }

    /**
     * @return array{ok: true, rows: array<int, array<int, string>>}|array{ok: false, error: string}
     */
    private function read_csv(string $path, int $max_rows): array
    {
        $raw = file_get_contents($path);
        if (! is_string($raw)) {
            return ['ok' => false, 'error' => 'unreadable'];
        }

        // Excel "CSV UTF-8" adds a byte-order mark; older exports may be Windows-1252.
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        } elseif (! mb_check_encoding($raw, 'UTF-8')) {
            $converted = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
            $raw       = is_string($converted) ? $converted : $raw;
        }

        if (str_contains($raw, "\0")) {
            return ['ok' => false, 'error' => 'unreadable'];
        }

        $first_line = strtok($raw, "\n");
        $delimiter  = is_string($first_line) && substr_count($first_line, ';') > substr_count($first_line, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            return ['ok' => false, 'error' => 'unreadable'];
        }
        fwrite($handle, $raw);
        rewind($handle);

        $rows   = [];
        $number = 0;
        while (false !== ($cells = fgetcsv($handle, 0, $delimiter, '"', ''))) {
            ++$number;
            if ($number > $max_rows + 1) {
                fclose($handle);

                return ['ok' => false, 'error' => 'too_many'];
            }
            $rows[$number] = array_map(
                static fn ($cell): string => trim((string) $cell),
                array_slice($cells, 0, self::MAX_COLUMNS)
            );
        }
        fclose($handle);

        return ['ok' => true, 'rows' => $rows];
    }

    /**
     * @return array{ok: true, rows: array<int, array<int, string>>}|array{ok: false, error: string}
     */
    private function read_xlsx(string $path, int $max_rows): array
    {
        if (! class_exists(\ZipArchive::class) || ! class_exists(\DOMDocument::class)) {
            return ['ok' => false, 'error' => 'unsupported'];
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
            return ['ok' => false, 'error' => 'unreadable'];
        }

        try {
            $sheet_path = $this->resident_sheet_path($zip);
            if (null === $sheet_path) {
                return ['ok' => false, 'error' => 'unreadable'];
            }

            $shared = $this->shared_strings($zip);
            $sheet  = $this->entry($zip, $sheet_path);
            if (null === $sheet) {
                return ['ok' => false, 'error' => 'unreadable'];
            }
        } finally {
            $zip->close();
        }

        $dom = $this->dom($sheet);
        if (null === $dom) {
            return ['ok' => false, 'error' => 'unreadable'];
        }

        $rows = [];
        foreach ($dom->getElementsByTagNameNS(self::MAIN_NS, 'row') as $row_el) {
            if (! $row_el instanceof \DOMElement) {
                continue;
            }
            $number = (int) $row_el->getAttribute('r');
            if ($number <= 0) {
                $number = count($rows) + 1;
            }

            $cells = [];
            foreach ($row_el->getElementsByTagNameNS(self::MAIN_NS, 'c') as $cell) {
                if (! $cell instanceof \DOMElement) {
                    continue;
                }
                $col = $this->column_index($cell->getAttribute('r'));
                if (null === $col || $col >= self::MAX_COLUMNS) {
                    continue;
                }
                $cells[$col] = $this->cell_value($cell, $shared);
            }

            if ([] === $cells) {
                continue;
            }

            if (count($rows) >= $max_rows + 1) {
                return ['ok' => false, 'error' => 'too_many'];
            }

            $max  = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $max; ++$i) {
                $line[] = trim((string) ($cells[$i] ?? ''));
            }
            $rows[$number] = $line;
        }

        return ['ok' => true, 'rows' => $rows];
    }

    /**
     * Uses a sheet named "Residents" when present, otherwise the first sheet.
     */
    private function resident_sheet_path(\ZipArchive $zip): ?string
    {
        $workbook = $this->entry($zip, 'xl/workbook.xml');
        $rels     = $this->entry($zip, 'xl/_rels/workbook.xml.rels');
        if (null === $workbook || null === $rels) {
            return null;
        }

        $wb_dom  = $this->dom($workbook);
        $rel_dom = $this->dom($rels);
        if (null === $wb_dom || null === $rel_dom) {
            return null;
        }

        $targets = [];
        foreach ($rel_dom->getElementsByTagNameNS(self::PKG_REL_NS, 'Relationship') as $rel) {
            if ($rel instanceof \DOMElement) {
                $targets[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
            }
        }

        $chosen = null;
        foreach ($wb_dom->getElementsByTagNameNS(self::MAIN_NS, 'sheet') as $sheet) {
            if (! $sheet instanceof \DOMElement) {
                continue;
            }
            $rid = $sheet->getAttributeNS(self::REL_NS, 'id');
            if (null === $chosen) {
                $chosen = $rid;
            }
            if ('residents' === strtolower(trim($sheet->getAttribute('name')))) {
                $chosen = $rid;
                break;
            }
        }

        if (null === $chosen || ! isset($targets[$chosen])) {
            return null;
        }

        $target = ltrim($targets[$chosen], '/');
        if (str_contains($target, '..')) {
            return null;
        }

        return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
    }

    /**
     * @return array<int, string>
     */
    private function shared_strings(\ZipArchive $zip): array
    {
        $xml = $this->entry($zip, 'xl/sharedStrings.xml');
        if (null === $xml) {
            return [];
        }

        $dom = $this->dom($xml);
        if (null === $dom) {
            return [];
        }

        $strings = [];
        foreach ($dom->getElementsByTagNameNS(self::MAIN_NS, 'si') as $si) {
            $text = '';
            if ($si instanceof \DOMElement) {
                foreach ($si->getElementsByTagNameNS(self::MAIN_NS, 't') as $t) {
                    // Skip phonetic runs (rPh), which repeat the text in another script.
                    if ($t->parentNode instanceof \DOMElement && 'rPh' === $t->parentNode->localName) {
                        continue;
                    }
                    $text .= $t->textContent;
                }
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param array<int, string> $shared
     */
    private function cell_value(\DOMElement $cell, array $shared): string
    {
        $type = $cell->getAttribute('t');

        if ('inlineStr' === $type) {
            $text = '';
            foreach ($cell->getElementsByTagNameNS(self::MAIN_NS, 't') as $t) {
                $text .= $t->textContent;
            }

            return $text;
        }

        $v = null;
        foreach ($cell->getElementsByTagNameNS(self::MAIN_NS, 'v') as $node) {
            $v = $node->textContent;
            break;
        }
        if (null === $v) {
            return '';
        }

        if ('s' === $type) {
            return $shared[(int) $v] ?? '';
        }

        if ('b' === $type) {
            return '1' === $v ? 'TRUE' : 'FALSE';
        }

        return $v;
    }

    private function column_index(string $reference): ?int
    {
        if (! preg_match('/^([A-Z]{1,3})\d*$/', strtoupper($reference), $m)) {
            return null;
        }

        $index = 0;
        foreach (str_split($m[1]) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return $index - 1;
    }

    private function entry(\ZipArchive $zip, string $name): ?string
    {
        $stat = $zip->statName($name);
        if (! is_array($stat)) {
            return null;
        }

        $size = (int) ($stat['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_XML_BYTES) {
            return null;
        }

        $data = $zip->getFromName($name, self::MAX_XML_BYTES);

        return is_string($data) && '' !== $data ? $data : null;
    }

    private function dom(string $xml): ?\DOMDocument
    {
        // Refuse DOCTYPE declarations outright: spreadsheets never need them.
        if (false !== stripos($xml, '<!DOCTYPE')) {
            return null;
        }

        $dom      = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded   = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $dom : null;
    }

    /**
     * Converts an Excel date serial number (1900 date system) to Y-m-d.
     */
    public static function excel_serial_to_date(string $value): ?string
    {
        if (! preg_match('/^\d{4,6}(\.\d+)?$/', trim($value))) {
            return null;
        }

        $serial = (int) floor((float) $value);
        // 1 = 1900-01-01; Excel wrongly treats 1900 as a leap year, so serials after 59 are off by one.
        if ($serial < 61 || $serial > 80000) {
            return null;
        }

        $ts = ($serial - 25569) * 86400;

        return gmdate('Y-m-d', $ts);
    }
}
