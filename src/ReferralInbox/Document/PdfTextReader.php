<?php

namespace JMReferral\ReferralInbox\Document;

/**
 * Reads the text layer and fillable-field values of a PDF (Phase 5E.1).
 *
 * Uses the bundled smalot/pdfparser library on the server. No external
 * service is called and no text recognition is attempted, so a scanned or
 * photographed form has no text to read and reports STATUS_NO_TEXT.
 */
class PdfTextReader
{
    private const MAX_PAGES = 40;

    private const MAX_FIELD_OBJECTS = 20000;

    /** Cap for a single decoded PDF stream. */
    private const DECODE_MEMORY_LIMIT = 33554432; // 32 MB

    public function is_available(): bool
    {
        if (! function_exists('mb_substr') || ! function_exists('gzuncompress') || ! function_exists('iconv')) {
            return false;
        }

        $this->load_library();

        return class_exists('\\Smalot\\PdfParser\\Parser');
    }

    public function read(string $path): ExtractedDocument
    {
        if (! $this->is_available()) {
            return ExtractedDocument::unsupported();
        }

        if ('' === $path || ! is_file($path) || ! is_readable($path)) {
            return ExtractedDocument::unreadable();
        }

        try {
            $config = new \Smalot\PdfParser\Config();
            $config->setRetainImageContent(false);
            $config->setDecodeMemoryLimit(self::DECODE_MEMORY_LIMIT);

            $parser = new \Smalot\PdfParser\Parser([], $config);
            $pdf    = $parser->parseFile($path);

            $rows   = $this->page_rows($pdf);
            $fields = $this->form_fields($pdf);
        } catch (\Throwable $exception) {
            // Encrypted, damaged, or unsupported PDF. The message may quote file content.
            unset($exception);

            return ExtractedDocument::unreadable();
        }

        return ExtractedDocument::from_content($rows, $fields, true);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function page_rows(\Smalot\PdfParser\Document $pdf): array
    {
        $rows  = [];
        $pages = $pdf->getPages();
        $count = 0;

        foreach ($pages as $page) {
            if ($count >= self::MAX_PAGES || count($rows) >= ExtractedDocument::MAX_ROWS) {
                break;
            }
            $count++;

            try {
                $text = (string) $page->getText();
            } catch (\Throwable $exception) {
                unset($exception);
                continue;
            }

            $text = str_replace(["\r\n", "\r"], "\n", $text);
            foreach (explode("\n", $text) as $line) {
                if ('' === trim($line)) {
                    continue;
                }

                // A tab marks a column gap, so a tabbed line is treated as a table row.
                if (str_contains($line, "\t")) {
                    $cells = [];
                    foreach (explode("\t", $line) as $cell) {
                        if ('' !== trim($cell)) {
                            $cells[] = trim($cell);
                        }
                    }
                    if ([] !== $cells) {
                        $rows[] = $cells;
                    }
                    continue;
                }

                $rows[] = [rtrim($line)];
            }
        }

        return $rows;
    }

    /**
     * Fillable-form values: the field's description (or name) and its text value.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function form_fields(\Smalot\PdfParser\Document $pdf): array
    {
        $fields  = [];
        $seen    = [];
        $visited = 0;

        foreach ($pdf->getObjects() as $object) {
            if (++$visited > self::MAX_FIELD_OBJECTS) {
                break;
            }

            if (! is_object($object) || ! method_exists($object, 'getHeader')) {
                continue;
            }

            $header = $object->getHeader();
            if (! $header instanceof \Smalot\PdfParser\Header || ! $header->has('T') || ! $header->has('V')) {
                continue;
            }

            $value = $header->get('V');
            if (! $value instanceof \Smalot\PdfParser\Element\ElementString) {
                // Checkbox and radio states are names (/Yes, /Off), not text.
                continue;
            }

            $text = $this->decode_pdf_string((string) $value->getContent());
            if ('' === trim($text)) {
                continue;
            }

            $label = '';
            if ($header->has('TU')) {
                $label = $this->element_text($header->get('TU'));
            }
            if ('' === trim($label)) {
                $label = $this->humanise_field_name($this->element_text($header->get('T')));
            }
            if ('' === trim($label)) {
                continue;
            }

            $key = strtolower($label) . "\0" . $text;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $fields[] = [$label, $text];
        }

        return $fields;
    }

    private function element_text(mixed $element): string
    {
        if (! is_object($element) || ! method_exists($element, 'getContent')) {
            return '';
        }

        $content = $element->getContent();

        return is_scalar($content) ? $this->decode_pdf_string((string) $content) : '';
    }

    /**
     * PDF text strings are UTF-16BE with a byte-order mark, or a single-byte encoding.
     */
    private function decode_pdf_string(string $raw): string
    {
        if (str_starts_with($raw, "\xFE\xFF")) {
            $converted = @iconv('UTF-16BE', 'UTF-8//IGNORE', substr($raw, 2));

            return is_string($converted) ? $converted : '';
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $raw);

        return is_string($converted) ? $converted : '';
    }

    /**
     * "client_name", "ClientName" and "form.client.name" all read as "client name".
     */
    private function humanise_field_name(string $name): string
    {
        $name = (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $name);
        $name = (string) preg_replace('/\[\d+\]/', ' ', $name);
        $name = str_replace(['_', '.', '-', '#'], ' ', $name);

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    private function load_library(): void
    {
        if (class_exists('\\Smalot\\PdfParser\\Parser', false)) {
            return;
        }

        $loader = defined('JMRS_PLUGIN_PATH')
            ? JMRS_PLUGIN_PATH . 'lib/smalot-pdfparser/autoload.php'
            : dirname(__DIR__, 3) . '/lib/smalot-pdfparser/autoload.php';

        if (is_readable($loader)) {
            require_once $loader;
        }
    }
}
