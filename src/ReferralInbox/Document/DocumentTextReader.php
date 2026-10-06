<?php

namespace JMReferral\ReferralInbox\Document;

/**
 * Picks the reader for an uploaded referral form by file type (Phase 5E.1).
 *
 * Supported: Word .docx and PDF. The older binary .doc format is not read.
 */
class DocumentTextReader
{
    /**
     * Extension => MIME type accepted for referral form upload.
     *
     * @var array<string, string>
     */
    public const SUPPORTED_MIMES = [
        'pdf'  => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function __construct(
        private DocxTextReader $docx_reader,
        private PdfTextReader $pdf_reader
    ) {
    }

    public static function is_supported_extension(string $extension): bool
    {
        return isset(self::SUPPORTED_MIMES[strtolower($extension)]);
    }

    public function read(string $path, string $extension): ExtractedDocument
    {
        return match (strtolower($extension)) {
            'docx'  => $this->docx_reader->read($path),
            'pdf'   => $this->pdf_reader->read($path),
            default => ExtractedDocument::unsupported(),
        };
    }
}
