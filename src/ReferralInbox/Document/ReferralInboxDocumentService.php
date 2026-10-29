<?php

namespace JMReferral\ReferralInbox\Document;

use JMReferral\Documents\PrivateDocumentStorage;
use JMReferral\Documents\ReferralDocumentService;
use JMReferral\ReferralInbox\InboundMessage;
use JMReferral\ReferralInbox\InboxAttachmentStatus;
use JMReferral\ReferralInbox\ReferralDetectionStatus;
use JMReferral\ReferralInbox\ReferralInboxDetectionResult;
use JMReferral\ReferralInbox\ReferralInboxIngestionResult;
use JMReferral\ReferralInbox\ReferralInboxIngestionService;
use JMReferral\ReferralInbox\ReferralInboxResult;
use JMReferral\ReferralInbox\ReferralInboxService;
use JMReferral\ReferralInbox\ReferralInboxSource;
use JMReferral\ReferralInbox\ReferralInboxStatus;

/**
 * Staff upload of a referral form into the Referral Inbox (Phase 5E.1).
 *
 * An uploaded Word or PDF form becomes a `manual` Inbox item with the file in
 * private storage. Field suggestions are read from that file each time the
 * preparation screen is shown; they are never stored. Creating the referral
 * still goes through preparation, validation, staff confirmation, and the
 * atomic conversion, after which the file is attached to the referral.
 *
 * Callers authorise the user. This class does not check capabilities.
 */
class ReferralInboxDocumentService
{
    public const CREATED  = 'created';
    public const EXISTING = 'existing';
    public const ERROR    = 'error';

    public const MAX_FILE_SIZE = 10485760; // 10 MB, same as referral documents.

    /** Mailbox identifier for the manual document-upload source. */
    public const MAILBOX_IDENTIFIER = 'document-upload';

    private const ATTACHMENT_ID_PREFIX = 'upload-';

    /** How many times one file can be uploaded afresh after being ignored or marked duplicate. */
    private const MAX_REUPLOADS = 20;

    /**
     * Per-request memo so one page render reads the file once.
     *
     * @var array<int, ReferralFormExtractionResult>
     */
    private array $memo = [];

    public function __construct(
        private ReferralInboxService $inbox_service,
        private ReferralInboxIngestionService $ingestion_service,
        private PrivateDocumentStorage $private_storage,
        private DocumentTextReader $reader,
        private ReferralFormFieldExtractor $extractor,
        private ReferralDocumentService $document_service
    ) {
    }

    /**
     * Validate, store, and register an uploaded referral form.
     *
     * The same file uploaded twice returns the Inbox item it already created.
     *
     * @param array<string, mixed> $file $_FILES entry.
     * @return array{result: string, inbox_id?: int, status?: string, errors?: array<string, string>}
     */
    public function upload(array $file, int $actor_id): array
    {
        $checked = $this->validate($file);
        if (isset($checked['errors'])) {
            return ['result' => self::ERROR, 'errors' => $checked['errors']];
        }

        $tmp_name      = $checked['tmp_name'];
        $extension     = $checked['extension'];
        $mime_type     = $checked['mime_type'];
        $original_name = $checked['original_name'];

        $checksum = hash_file('sha256', $tmp_name);
        if (! is_string($checksum) || 64 !== strlen($checksum)) {
            return $this->file_error(__('Unable to verify the uploaded file.', 'jm-referral-system'));
        }

        $registered = $this->register_inbox_item($checksum, $original_name);
        if (isset($registered['errors'])) {
            return ['result' => self::ERROR, 'errors' => $registered['errors']];
        }

        $inbox_id = $registered['inbox_id'];
        $status   = $registered['status'];
        $existing = $registered['existing'];

        if ($existing && null !== $this->stored_attachment($inbox_id)) {
            return [
                'result'   => self::EXISTING,
                'inbox_id' => $inbox_id,
                'status'   => $status,
            ];
        }

        // New item, or an earlier attempt that never stored its file.
        $stored = $this->store_file($tmp_name, $extension);
        if (isset($stored['error'])) {
            $this->flag_storage_failure($inbox_id, $existing);

            return $this->file_error($stored['error']);
        }

        $attached = $this->inbox_service->addStoredAttachment(
            $inbox_id,
            [
                'provider_attachment_id' => self::ATTACHMENT_ID_PREFIX . $checksum,
                'filename'               => $original_name,
                'mime_type'              => $mime_type,
                'size_bytes'             => $stored['size'],
                'sha256'                 => $checksum,
                'private_path'           => $stored['relative_path'],
            ]
        );

        $attach_result = (string) ($attached['result'] ?? '');
        if (! in_array($attach_result, [ReferralInboxResult::CREATED, ReferralInboxResult::EXISTING], true)) {
            $this->safe_unlink($stored['absolute_path']);
            $this->flag_storage_failure($inbox_id, $existing);

            return $this->file_error(__('Unable to store the uploaded file.', 'jm-referral-system'));
        }

        if (ReferralInboxResult::EXISTING === $attach_result) {
            // Another request stored this file first. Keep its copy.
            $this->safe_unlink($stored['absolute_path']);
        }

        // The person uploading is the first reviewer, so skip the separate Start Review step.
        if (ReferralInboxStatus::NEW === $status || ReferralInboxStatus::ERROR === $status) {
            $moved = $this->inbox_service->markNeedsReview($inbox_id);
            if (in_array(
                (string) ($moved['result'] ?? ''),
                [ReferralInboxResult::SUCCESS, ReferralInboxResult::ALREADY_APPLIED],
                true
            )) {
                $this->inbox_service->markReviewed($inbox_id, $actor_id);
                $status = ReferralInboxStatus::NEEDS_REVIEW;
            }
        }

        return [
            'result'   => $existing ? self::EXISTING : self::CREATED,
            'inbox_id' => $inbox_id,
            'status'   => $status,
        ];
    }

    /**
     * Field suggestions for an Inbox item's stored form. Read-only, in memory.
     */
    public function candidates(int $inbox_id): ReferralFormExtractionResult
    {
        if (isset($this->memo[$inbox_id])) {
            return $this->memo[$inbox_id];
        }

        $attachment = $this->stored_attachment($inbox_id);
        if (null === $attachment) {
            return $this->memo[$inbox_id] = ReferralFormExtractionResult::no_document();
        }

        $filename  = (string) ($attachment['filename'] ?? '');
        $extension = strtolower(pathinfo((string) ($attachment['private_path'] ?? ''), PATHINFO_EXTENSION));
        $path      = $this->private_storage->resolve_safe_path((string) ($attachment['private_path'] ?? ''));

        if (null === $path) {
            return $this->memo[$inbox_id] = ReferralFormExtractionResult::missing_file($filename);
        }

        try {
            $document = $this->reader->read($path, $extension);
            $result   = $this->extractor->extract($document, $filename);
        } catch (\Throwable $exception) {
            unset($exception);
            $result = ReferralFormExtractionResult::unread(ExtractedDocument::STATUS_UNREADABLE, $filename);
        }

        return $this->memo[$inbox_id] = $result;
    }

    /**
     * After conversion: add the stored form to the new referral's documents.
     *
     * Safe to call again. Returns false when a stored file could not be attached;
     * the referral and the accepted Inbox link are unaffected either way.
     */
    public function promote_to_referral(int $inbox_id, int $referral_id, int $actor_id): bool
    {
        if ($inbox_id <= 0 || $referral_id <= 0) {
            return false;
        }

        $all_attached = true;

        foreach ($this->inbox_service->list_attachments($inbox_id) as $attachment) {
            if (InboxAttachmentStatus::STORED !== (string) ($attachment['storage_status'] ?? '')) {
                continue;
            }

            $relative_path = (string) ($attachment['private_path'] ?? '');
            if ('' === $relative_path) {
                continue;
            }

            // Conversion commits once per Inbox item, so this runs once per file.
            // The row stays `stored` if attaching fails, which keeps the status truthful.
            $document_id = $this->document_service->attach_private_file(
                $referral_id,
                [
                    'original_name'   => (string) ($attachment['filename'] ?? ''),
                    'mime_type'       => (string) ($attachment['mime_type'] ?? ''),
                    'relative_path'   => $relative_path,
                    'checksum_sha256' => (string) ($attachment['sha256'] ?? ''),
                ],
                $actor_id
            );

            if (false === $document_id) {
                $all_attached = false;
                continue;
            }

            $this->inbox_service->markAttachmentPromoted(absint($attachment['id'] ?? 0));
        }

        return $all_attached;
    }

    public function has_stored_document(int $inbox_id): bool
    {
        return null !== $this->stored_attachment($inbox_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stored_attachment(int $inbox_id): ?array
    {
        if ($inbox_id <= 0) {
            return null;
        }

        foreach ($this->inbox_service->list_attachments($inbox_id) as $attachment) {
            $status = (string) ($attachment['storage_status'] ?? '');
            if (! in_array($status, [InboxAttachmentStatus::STORED, InboxAttachmentStatus::PROMOTED], true)) {
                continue;
            }

            $path = (string) ($attachment['private_path'] ?? '');
            if ('' === $path) {
                continue;
            }

            if (DocumentTextReader::is_supported_extension(pathinfo($path, PATHINFO_EXTENSION))) {
                return $attachment;
            }
        }

        return null;
    }

    /**
     * Create the Inbox item for this file, or find the one it already created.
     *
     * @return array{inbox_id: int, status: string, existing: bool}|array{errors: array<string, string>}
     */
    private function register_inbox_item(string $checksum, string $original_name): array
    {
        // The first upload of a file is "upload-<hash>". If that item was later
        // ignored or marked duplicate, the same file may be uploaded afresh as
        // "upload-<hash>-r1", "-r2", … so there is never more than one open item per file.
        for ($attempt = 0; $attempt <= self::MAX_REUPLOADS; $attempt++) {
            $message_id = self::ATTACHMENT_ID_PREFIX . $checksum . ($attempt > 0 ? '-r' . $attempt : '');

            $envelope = InboundMessage::try_from(
                [
                    'source_provider'           => ReferralInboxSource::MANUAL,
                    'mailbox_identifier'        => self::MAILBOX_IDENTIFIER,
                    'provider_message_id'       => $message_id,
                    'subject'                   => sprintf(
                        /* translators: %s: uploaded file name */
                        __('Uploaded referral form: %s', 'jm-referral-system'),
                        $original_name
                    ),
                    'received_at'               => current_time('mysql'),
                    'declared_attachment_count' => 1,
                ]
            );

            if (! ($envelope['ok'] ?? false)) {
                return ['errors' => ['file' => __('The upload could not be added to the Referral Inbox.', 'jm-referral-system')]];
            }

            $ingested = $this->ingestion_service->ingest($envelope['message']);
            $outcome  = (string) ($ingested['result'] ?? '');
            $inbox_id = absint($ingested['inbox_id'] ?? 0);

            if ($inbox_id <= 0
                || ! in_array($outcome, [ReferralInboxIngestionResult::CREATED, ReferralInboxIngestionResult::EXISTING], true)
            ) {
                return ['errors' => ['file' => __('The upload could not be added to the Referral Inbox.', 'jm-referral-system')]];
            }

            $item   = is_array($ingested['item'] ?? null) ? $ingested['item'] : [];
            $status = (string) ($item['status'] ?? '');

            if (ReferralInboxIngestionResult::CREATED === $outcome) {
                // There is no sender or message to classify. Record why this item
                // is here instead of an email-detection result that does not apply.
                $this->inbox_service->setDetectionMetadata(
                    $inbox_id,
                    ReferralDetectionStatus::LIKELY,
                    ReferralInboxDetectionResult::REASON_STAFF_UPLOADED_FORM
                );

                return ['inbox_id' => $inbox_id, 'status' => $status, 'existing' => false];
            }

            $closed = in_array($status, [ReferralInboxStatus::IGNORED, ReferralInboxStatus::DUPLICATE], true);
            if ($closed && $attempt < self::MAX_REUPLOADS) {
                continue;
            }

            return ['inbox_id' => $inbox_id, 'status' => $status, 'existing' => true];
        }

        return ['errors' => ['file' => __('The upload could not be added to the Referral Inbox.', 'jm-referral-system')]];
    }

    /**
     * @param array<string, mixed> $file
     * @return array{tmp_name: string, extension: string, mime_type: string, original_name: string}|array{errors: array<string, string>}
     */
    private function validate(array $file): array
    {
        $fail = static fn (string $message): array => ['errors' => ['file' => $message]];

        if (empty($file) || ! isset($file['error']) || is_array($file['error'])) {
            return $fail(__('Please choose a file to upload.', 'jm-referral-system'));
        }

        $error_code = (int) $file['error'];
        if (UPLOAD_ERR_NO_FILE === $error_code) {
            return $fail(__('Please choose a file to upload.', 'jm-referral-system'));
        }
        if (UPLOAD_ERR_INI_SIZE === $error_code || UPLOAD_ERR_FORM_SIZE === $error_code) {
            return $fail(__('The file exceeds the maximum size of 10 MB.', 'jm-referral-system'));
        }
        if (UPLOAD_ERR_OK !== $error_code) {
            return $fail(__('The upload failed. Please try again.', 'jm-referral-system'));
        }

        $size = absint($file['size'] ?? 0);
        if ($size <= 0) {
            return $fail(__('The uploaded file is empty.', 'jm-referral-system'));
        }
        if ($size > self::MAX_FILE_SIZE) {
            return $fail(__('The file exceeds the maximum size of 10 MB.', 'jm-referral-system'));
        }

        $name = str_replace("\0", '', (string) ($file['name'] ?? ''));
        if (str_contains($name, '/') || str_contains($name, '\\')) {
            return $fail(__('The upload is invalid.', 'jm-referral-system'));
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ('doc' === $extension) {
            return $fail(__('Older .doc files cannot be read. Open the form in Word, save it as .docx or PDF, and upload that.', 'jm-referral-system'));
        }
        if (! DocumentTextReader::is_supported_extension($extension)) {
            return $fail(__('Upload a Word (.docx) or PDF file.', 'jm-referral-system'));
        }

        $tmp_name = (string) ($file['tmp_name'] ?? '');
        if ('' === $tmp_name || ! is_uploaded_file($tmp_name)) {
            return $fail(__('The upload is invalid.', 'jm-referral-system'));
        }

        $check = wp_check_filetype_and_ext($tmp_name, $name, DocumentTextReader::SUPPORTED_MIMES);
        $type  = (string) ($check['type'] ?? '');
        $ext   = strtolower((string) ($check['ext'] ?? ''));
        if ('' === $type || '' === $ext
            || ! DocumentTextReader::is_supported_extension($ext)
            || DocumentTextReader::SUPPORTED_MIMES[$ext] !== $type
            || ! $this->has_expected_signature($tmp_name, $ext)
        ) {
            return $fail(__('That file is not a valid Word (.docx) or PDF document.', 'jm-referral-system'));
        }

        $original_name = sanitize_file_name(basename(str_replace('\\', '/', $name)));

        return [
            'tmp_name'      => $tmp_name,
            'extension'     => $ext,
            'mime_type'     => $type,
            'original_name' => '' !== $original_name ? $original_name : 'referral-form.' . $ext,
        ];
    }

    /**
     * The file's first bytes must match its claimed type.
     */
    private function has_expected_signature(string $path, string $extension): bool
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp file, bounded read.
        $head = @file_get_contents($path, false, null, 0, 1024);
        if (! is_string($head) || '' === $head) {
            return false;
        }

        if ('pdf' === $extension) {
            return str_contains($head, '%PDF-');
        }

        if ('docx' === $extension) {
            return str_starts_with($head, "PK\x03\x04");
        }

        return false;
    }

    /**
     * @return array{relative_path: string, absolute_path: string, size: int}|array{error: string}
     */
    private function store_file(string $tmp_name, string $extension): array
    {
        $unavailable = __('Unable to prepare private document storage.', 'jm-referral-system');

        $ready = $this->private_storage->ensure_ready();
        if (is_wp_error($ready)) {
            return ['error' => $unavailable];
        }

        $month_dir = $this->private_storage->ensure_month_directory();
        if ('' === $month_dir) {
            return ['error' => $unavailable];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $stored_name = $this->private_storage->generate_stored_name($extension);
        $stored_name = wp_unique_filename($month_dir, $stored_name);
        $dest_path   = trailingslashit($month_dir) . $stored_name;

        if (! @move_uploaded_file($tmp_name, $dest_path)) {
            return ['error' => __('The upload failed. Please try again.', 'jm-referral-system')];
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
        @chmod($dest_path, 0640);

        $size = is_readable($dest_path) ? (int) filesize($dest_path) : 0;
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            $this->safe_unlink($dest_path);

            return ['error' => __('The file exceeds the maximum size of 10 MB.', 'jm-referral-system')];
        }

        $relative_path = $this->private_storage->build_relative_path($stored_name);
        if (null === $this->private_storage->normalize_relative_path($relative_path)) {
            $this->safe_unlink($dest_path);

            return ['error' => __('Unable to store the uploaded file.', 'jm-referral-system')];
        }

        return [
            'relative_path' => $relative_path,
            'absolute_path' => $dest_path,
            'size'          => $size,
        ];
    }

    /**
     * A newly created item whose file could not be stored is put into the
     * recoverable error state so it is not mistaken for a reviewable opportunity.
     */
    private function flag_storage_failure(int $inbox_id, bool $existing): void
    {
        if ($existing) {
            return;
        }

        $this->inbox_service->markError(
            $inbox_id,
            'document_storage_failed',
            __('The uploaded referral form could not be stored. Upload it again.', 'jm-referral-system')
        );
    }

    /**
     * @return array{result: string, errors: array<string, string>}
     */
    private function file_error(string $message): array
    {
        return ['result' => self::ERROR, 'errors' => ['file' => $message]];
    }

    private function safe_unlink(string $path): void
    {
        if ('' === $path || ! is_file($path)) {
            return;
        }

        if (! $this->private_storage->is_path_within_private_root($path)) {
            return;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
        @unlink($path);
    }
}
