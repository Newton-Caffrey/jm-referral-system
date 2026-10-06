<?php

namespace JMReferral\ReferralInbox\Document;

use JMReferral\ReferralInbox\ReferralInboxCandidateField;

/**
 * Advisory field suggestions read from an uploaded referral form (Phase 5E.1).
 *
 * In memory only, like ReferralInboxCandidateResult. No string cast.
 */
final class ReferralFormExtractionResult
{
    /** The Inbox item has no stored document. */
    public const STATUS_NO_DOCUMENT = 'no_document';

    /** The stored file could not be found or opened. */
    public const STATUS_MISSING_FILE = 'missing_file';

    /**
     * Fields this result can carry, in form order.
     *
     * @var array<int, string>
     */
    public const FIELDS = [
        'client_name',
        'client_date_of_birth',
        'client_phone',
        'client_email',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'referrer_name',
        'referrer_email',
        'referrer_phone',
        'referrer_organisation',
        'relationship_to_client',
        'care_requirements',
        'care_start_date',
        'service_hint',
        'priority_hint',
    ];

    /**
     * @param array<string, ReferralInboxCandidateField> $fields
     */
    private function __construct(
        private string $status,
        private array $fields,
        private string $text,
        private string $filename
    ) {
    }

    public static function no_document(): self
    {
        return new self(self::STATUS_NO_DOCUMENT, [], '', '');
    }

    public static function missing_file(string $filename): self
    {
        return new self(self::STATUS_MISSING_FILE, [], '', $filename);
    }

    public static function unread(string $document_status, string $filename): self
    {
        return new self($document_status, [], '', $filename);
    }

    /**
     * @param array<string, ReferralInboxCandidateField> $fields
     */
    public static function read(array $fields, string $text, string $filename): self
    {
        $clean = [];
        foreach (self::FIELDS as $key) {
            if (isset($fields[$key]) && $fields[$key] instanceof ReferralInboxCandidateField) {
                $clean[$key] = $fields[$key];
            }
        }

        return new self(ExtractedDocument::STATUS_OK, $clean, $text, $filename);
    }

    /**
     * ExtractedDocument::STATUS_* or one of this class's STATUS_* values.
     */
    public function status(): string
    {
        return $this->status;
    }

    public function has_document(): bool
    {
        return self::STATUS_NO_DOCUMENT !== $this->status;
    }

    public function was_read(): bool
    {
        return ExtractedDocument::STATUS_OK === $this->status;
    }

    public function field(string $key): ReferralInboxCandidateField
    {
        return $this->fields[$key] ?? ReferralInboxCandidateField::none();
    }

    /**
     * Number of fields with one clear value.
     */
    public function single_count(): int
    {
        $count = 0;
        foreach ($this->fields as $key => $field) {
            if (in_array($key, ['service_hint', 'priority_hint'], true)) {
                continue;
            }
            if (ReferralInboxCandidateField::STATE_SINGLE === $field->state()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Document text for the on-screen reference panel. Never stored.
     */
    public function text(): string
    {
        return $this->text;
    }

    public function filename(): string
    {
        return $this->filename;
    }
}
