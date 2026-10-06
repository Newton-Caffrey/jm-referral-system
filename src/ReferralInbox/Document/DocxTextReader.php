<?php

namespace JMReferral\ReferralInbox\Document;

/**
 * Reads the text of a Word .docx file (Phase 5E.1).
 *
 * Opens the file as a zip, reads word/document.xml only, and walks paragraphs
 * and tables in order. No macros, embedded objects, images, or external
 * references are followed. Nothing is written.
 */
class DocxTextReader
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const MC_NS = 'http://schemas.openxmlformats.org/markup-compatibility/2006';

    /** Uncompressed size cap for word/document.xml. */
    private const MAX_XML_BYTES = 15728640; // 15 MB

    private const MAX_DEPTH = 40;

    public function is_available(): bool
    {
        return class_exists(\ZipArchive::class) && class_exists(\DOMDocument::class);
    }

    public function read(string $path): ExtractedDocument
    {
        if (! $this->is_available()) {
            return ExtractedDocument::unsupported();
        }

        if ('' === $path || ! is_file($path) || ! is_readable($path)) {
            return ExtractedDocument::unreadable();
        }

        $xml = $this->document_xml($path);
        if (null === $xml) {
            return ExtractedDocument::unreadable();
        }

        // A Word document never declares a DTD. Refuse one rather than expand it.
        if (false !== stripos(substr($xml, 0, 4096), '<!DOCTYPE')) {
            return ExtractedDocument::unreadable();
        }

        $dom      = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded   = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return ExtractedDocument::unreadable();
        }

        $bodies = $dom->getElementsByTagNameNS(self::WORD_NS, 'body');
        $body   = $bodies->length > 0 ? $bodies->item(0) : null;
        if (! $body instanceof \DOMElement) {
            return ExtractedDocument::unreadable();
        }

        $rows = [];
        $this->walk_blocks($body, $rows, 0);

        return ExtractedDocument::from_content($rows);
    }

    private function document_xml(string $path): ?string
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
            return null;
        }

        try {
            $stat = $zip->statName('word/document.xml');
            if (! is_array($stat)) {
                return null;
            }

            $size = (int) ($stat['size'] ?? 0);
            if ($size <= 0 || $size > self::MAX_XML_BYTES) {
                return null;
            }

            $xml = $zip->getFromName('word/document.xml', self::MAX_XML_BYTES);
        } finally {
            $zip->close();
        }

        return is_string($xml) && '' !== $xml ? $xml : null;
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function walk_blocks(\DOMNode $container, array &$rows, int $depth): void
    {
        if ($depth > self::MAX_DEPTH || count($rows) >= ExtractedDocument::MAX_ROWS) {
            return;
        }

        foreach ($container->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }

            if ($this->is_fallback($child)) {
                continue;
            }

            if (self::WORD_NS === $child->namespaceURI && 'p' === $child->localName) {
                $text = $this->paragraph_text($child);
                if ('' !== trim($text)) {
                    $rows[] = [$text];
                }
                continue;
            }

            if (self::WORD_NS === $child->namespaceURI && 'tbl' === $child->localName) {
                $this->walk_table($child, $rows, $depth + 1);
                continue;
            }

            // Content controls, custom XML wrappers and similar containers.
            $this->walk_blocks($child, $rows, $depth + 1);
        }
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function walk_table(\DOMElement $table, array &$rows, int $depth): void
    {
        foreach ($this->table_rows($table) as $tr) {
            if (count($rows) >= ExtractedDocument::MAX_ROWS) {
                return;
            }

            $cells = [];
            foreach ($this->row_cells($tr) as $tc) {
                $cells[] = $this->cell_text($tc, $depth);
            }

            if ('' !== trim(implode('', $cells))) {
                $rows[] = $cells;
            }
        }
    }

    /**
     * @return array<int, \DOMElement>
     */
    private function table_rows(\DOMElement $table): array
    {
        $found = [];
        foreach ($table->childNodes as $child) {
            if (! $child instanceof \DOMElement || self::WORD_NS !== $child->namespaceURI) {
                continue;
            }
            if ('tr' === $child->localName) {
                $found[] = $child;
            } elseif ('sdt' === $child->localName) {
                foreach ($child->getElementsByTagNameNS(self::WORD_NS, 'tr') as $nested) {
                    if ($nested instanceof \DOMElement) {
                        $found[] = $nested;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * @return array<int, \DOMElement>
     */
    private function row_cells(\DOMElement $row): array
    {
        $found = [];
        foreach ($row->childNodes as $child) {
            if (! $child instanceof \DOMElement || self::WORD_NS !== $child->namespaceURI) {
                continue;
            }
            if ('tc' === $child->localName) {
                $found[] = $child;
            } elseif ('sdt' === $child->localName) {
                foreach ($child->getElementsByTagNameNS(self::WORD_NS, 'tc') as $nested) {
                    if ($nested instanceof \DOMElement) {
                        $found[] = $nested;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * A cell's paragraphs joined by newlines. Nested tables are flattened.
     */
    private function cell_text(\DOMElement $cell, int $depth): string
    {
        $nested = [];
        $this->walk_blocks($cell, $nested, $depth + 1);

        $lines = [];
        foreach ($nested as $row) {
            $line = trim(implode(' ', array_map('trim', $row)));
            if ('' !== $line) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    private function paragraph_text(\DOMElement $paragraph): string
    {
        $text = '';
        $this->collect_text($paragraph, $text, 0);

        return $text;
    }

    private function collect_text(\DOMNode $node, string &$text, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }

        foreach ($node->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }

            if ($this->is_fallback($child)) {
                continue;
            }

            if (self::WORD_NS === $child->namespaceURI) {
                switch ($child->localName) {
                    case 't':
                        $text .= $child->textContent;
                        continue 2;
                    case 'tab':
                        $text .= "\t";
                        continue 2;
                    case 'br':
                    case 'cr':
                        $text .= "\n";
                        continue 2;
                    case 'noBreakHyphen':
                        $text .= '-';
                        continue 2;
                    case 'delText':
                    case 'instrText':
                    case 'pPr':
                    case 'rPr':
                        continue 2;
                    case 'p':
                        // A paragraph inside a text box within this paragraph.
                        if ('' !== $text && ! str_ends_with($text, "\n")) {
                            $text .= "\n";
                        }
                        break;
                }
            }

            $this->collect_text($child, $text, $depth + 1);
        }
    }

    /**
     * Drawing fallbacks repeat the text of the preferred choice.
     */
    private function is_fallback(\DOMElement $element): bool
    {
        return self::MC_NS === $element->namespaceURI && 'Fallback' === $element->localName;
    }
}
