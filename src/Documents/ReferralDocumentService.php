<?php

namespace JMReferral\Documents;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;

class ReferralDocumentService
{
    private const MAX_FILE_SIZE = 10485760; // 10 MB

    private const MIGRATION_BATCH_SIZE = 20;

    /**
     * Allowed extensions mapped to MIME types.
     *
     * @var array<string, string>
     */
    private const ALLOWED_MIMES = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ];

    public function __construct(
        private ReferralDocumentRepository $document_repository,
        private ReferralRepository $referral_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy,
        private PrivateDocumentStorage $private_storage
    ) {
    }

    /**
     * Validates and uploads a document into private storage.
     *
     * @param array<string, mixed> $file $_FILES entry for the uploaded document.
     * @return array{id: int}|array{errors: array<string, string>}|false
     */
    public function upload(int $referral_id, array $file): array|false
    {
        $errors = $this->validate_upload($referral_id, $file);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral) {
            return [
                'errors' => [
                    'referral_id' => __('Referral not found.', 'jm-referral-system'),
                ],
            ];
        }

        $ready = $this->private_storage->ensure_ready();

        if (is_wp_error($ready)) {
            return [
                'errors' => [
                    'file' => __('Unable to prepare private document storage.', 'jm-referral-system'),
                ],
            ];
        }

        $original_name = $this->sanitize_original_name((string) ($file['name'] ?? ''));
        $ext           = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $tmp_name      = (string) ($file['tmp_name'] ?? '');

        $check = wp_check_filetype_and_ext($tmp_name, (string) ($file['name'] ?? ''), self::ALLOWED_MIMES);

        if (empty($check['type']) || empty($check['ext']) || ! $this->is_allowed_mime((string) $check['type'])) {
            return [
                'errors' => [
                    'file' => __('That file type is not allowed.', 'jm-referral-system'),
                ],
            ];
        }

        $ext       = strtolower((string) $check['ext']);
        $mime_type = (string) $check['type'];

        $month_dir = $this->private_storage->ensure_month_directory();

        if ('' === $month_dir) {
            return [
                'errors' => [
                    'file' => __('Unable to prepare private document storage.', 'jm-referral-system'),
                ],
            ];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $stored_name = $this->private_storage->generate_stored_name($ext);
        $stored_name = wp_unique_filename($month_dir, $stored_name);
        $dest_path   = trailingslashit($month_dir) . $stored_name;

        if (! @move_uploaded_file($tmp_name, $dest_path)) {
            return [
                'errors' => [
                    'file' => __('The upload failed. Please try again.', 'jm-referral-system'),
                ],
            ];
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
        @chmod($dest_path, 0640);

        $file_size = is_readable($dest_path) ? (int) filesize($dest_path) : 0;

        if ($file_size <= 0 || $file_size > self::MAX_FILE_SIZE) {
            $this->safe_unlink($dest_path);

            return [
                'errors' => [
                    'file' => __('The file exceeds the maximum size of 10 MB.', 'jm-referral-system'),
                ],
            ];
        }

        if (! $this->is_allowed_mime($mime_type)) {
            $this->safe_unlink($dest_path);

            return [
                'errors' => [
                    'file' => __('That file type is not allowed.', 'jm-referral-system'),
                ],
            ];
        }

        $checksum = hash_file('sha256', $dest_path);

        if (! is_string($checksum) || 64 !== strlen($checksum)) {
            $this->safe_unlink($dest_path);

            return [
                'errors' => [
                    'file' => __('Unable to verify the uploaded file.', 'jm-referral-system'),
                ],
            ];
        }

        $relative_path = $this->private_storage->build_relative_path($stored_name);

        if (null === $this->private_storage->normalize_relative_path($relative_path)) {
            $this->safe_unlink($dest_path);

            return [
                'errors' => [
                    'file' => __('Unable to store the uploaded file.', 'jm-referral-system'),
                ],
            ];
        }

        $document_id = $this->document_repository->create(
            [
                'referral_id'     => $referral_id,
                'attachment_id'   => 0,
                'original_name'   => $original_name,
                'mime_type'       => $mime_type,
                'file_size'       => $file_size,
                'uploaded_by'     => get_current_user_id(),
                'created_at'      => current_time('mysql'),
                'storage_type'    => PrivateDocumentStorage::STORAGE_PRIVATE,
                'relative_path'   => $relative_path,
                'stored_name'     => $stored_name,
                'checksum_sha256' => $checksum,
            ]
        );

        if (false === $document_id) {
            $this->safe_unlink($dest_path);

            return false;
        }

        $this->activity_service->log_document_uploaded($referral_id, $original_name);

        return ['id' => $document_id];
    }

    /**
     * Public intake upload: private storage only, no capability / AccessPolicy checks.
     *
     * @param array<string, mixed> $file $_FILES entry.
     * @return array{id: int}|array{errors: array<string, string>}|false
     */
    public function upload_for_public_intake(int $referral_id, array $file, int $max_bytes): array|false
    {
        $errors = $this->validate_public_upload($referral_id, $file, $max_bytes);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral) {
            return [
                'errors' => [
                    'referral_id' => __('Referral not found.', 'jm-referral-system'),
                ],
            ];
        }

        $ready = $this->private_storage->ensure_ready();

        if (is_wp_error($ready)) {
            return [
                'errors' => [
                    'file' => __('Unable to prepare private document storage.', 'jm-referral-system'),
                ],
            ];
        }

        $original_name = $this->sanitize_original_name((string) ($file['name'] ?? ''));
        $tmp_name      = (string) ($file['tmp_name'] ?? '');

        $check = wp_check_filetype_and_ext($tmp_name, (string) ($file['name'] ?? ''), self::ALLOWED_MIMES);

        if (empty($check['type']) || empty($check['ext']) || ! $this->is_allowed_mime((string) $check['type'])) {
            return [
                'errors' => [
                    'file' => __('That file type is not allowed.', 'jm-referral-system'),
                ],
            ];
        }

        $ext       = strtolower((string) $check['ext']);
        $mime_type = (string) $check['type'];

        $month_dir = $this->private_storage->ensure_month_directory();

        if ('' === $month_dir) {
            return [
                'errors' => [
                    'file' => __('Unable to prepare private document storage.', 'jm-referral-system'),
                ],
            ];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $stored_name = $this->private_storage->generate_stored_name($ext);
        $stored_name = wp_unique_filename($month_dir, $stored_name);
        $dest_path   = trailingslashit($month_dir) . $stored_name;

        if (! @move_uploaded_file($tmp_name, $dest_path)) {
            return [
                'errors' => [
                    'file' => __('The upload failed. Please try again.', 'jm-referral-system'),
                ],
            ];
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
        @chmod($dest_path, 0640);

        $file_size = is_readable($dest_path) ? (int) filesize($dest_path) : 0;

        if ($file_size <= 0 || $file_size > $max_bytes) {
            $this->safe_unlink($dest_path);

            return [
                'errors' => [
                    'file' => __('The file exceeds the maximum allowed size.', 'jm-referral-system'),
                ],
            ];
        }

        $checksum = hash_file('sha256', $dest_path);

        if (! is_string($checksum) || 64 !== strlen($checksum)) {
            $this->safe_unlink($dest_path);

            return [
                'errors' => [
                    'file' => __('Unable to verify the uploaded file.', 'jm-referral-system'),
                ],
            ];
        }

        $relative_path = $this->private_storage->build_relative_path($stored_name);

        if (null === $this->private_storage->normalize_relative_path($relative_path)) {
            $this->safe_unlink($dest_path);

            return [
                'errors' => [
                    'file' => __('Unable to store the uploaded file.', 'jm-referral-system'),
                ],
            ];
        }

        $document_id = $this->document_repository->create(
            [
                'referral_id'     => $referral_id,
                'attachment_id'   => 0,
                'original_name'   => $original_name,
                'mime_type'       => $mime_type,
                'file_size'       => $file_size,
                'uploaded_by'     => 0,
                'created_at'      => current_time('mysql'),
                'storage_type'    => PrivateDocumentStorage::STORAGE_PRIVATE,
                'relative_path'   => $relative_path,
                'stored_name'     => $stored_name,
                'checksum_sha256' => $checksum,
            ]
        );

        if (false === $document_id) {
            $this->safe_unlink($dest_path);

            return false;
        }

        $this->activity_service->log_document_uploaded($referral_id, $original_name);

        return ['id' => $document_id];
    }

    /**
     * Returns documents for a referral when the user may download them.
     *
     * @return array<int, array<string, mixed>>|array{errors: array<string, string>}
     */
    public function get_documents_for_referral(int $referral_id): array
    {
        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral) {
            return [
                'errors' => [
                    'referral_id' => __('Referral not found.', 'jm-referral-system'),
                ],
            ];
        }

        if (! Capabilities::current_user_can(Capabilities::DOWNLOAD_DOCUMENTS)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to download documents.', 'jm-referral-system'),
                ],
            ];
        }

        if (! $this->access_policy->can_view_referral($referral)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to view documents for this referral.', 'jm-referral-system'),
                ],
            ];
        }

        return $this->document_repository->get_by_referral_id($referral_id);
    }

    /**
     * Loads a document and verifies download access for either storage type.
     *
     * @return array{document: array<string, mixed>, referral: array<string, mixed>, file_path: string}|array{errors: array<string, string>}
     */
    public function prepare_download(int $document_id): array
    {
        if (! Capabilities::current_user_can(Capabilities::DOWNLOAD_DOCUMENTS)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to download documents.', 'jm-referral-system'),
                ],
            ];
        }

        $document = $this->document_repository->find($document_id);

        if (null === $document) {
            return [
                'errors' => [
                    'document' => __('Document not found.', 'jm-referral-system'),
                ],
            ];
        }

        $referral_id = absint($document['referral_id'] ?? 0);
        $referral    = $this->referral_repository->find($referral_id);

        if (null === $referral) {
            return [
                'errors' => [
                    'referral_id' => __('Referral not found.', 'jm-referral-system'),
                ],
            ];
        }

        if (! $this->access_policy->can_view_referral($referral)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to download this document.', 'jm-referral-system'),
                ],
            ];
        }

        $storage_type = (string) ($document['storage_type'] ?? PrivateDocumentStorage::STORAGE_LEGACY);

        if (PrivateDocumentStorage::STORAGE_PRIVATE === $storage_type) {
            return $this->prepare_private_download($document, $referral);
        }

        return $this->prepare_legacy_download($document, $referral);
    }

    /**
     * Resolves an absolute readable filesystem path for server-side attachment use.
     *
     * Callers must already enforce business authorization. This does not replace
     * HTTP download authorization (prepare_download).
     *
     * @return array{path: string, original_name: string, mime_type: string}|null
     */
    public function resolve_attachment_path_for_referral(int $document_id, int $referral_id): ?array
    {
        if ($document_id <= 0 || $referral_id <= 0) {
            return null;
        }

        $document = $this->document_repository->find($document_id);
        if (null === $document) {
            return null;
        }

        if (absint($document['referral_id'] ?? 0) !== $referral_id) {
            return null;
        }

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return null;
        }

        $storage_type = (string) ($document['storage_type'] ?? PrivateDocumentStorage::STORAGE_LEGACY);
        $resolved = PrivateDocumentStorage::STORAGE_PRIVATE === $storage_type
            ? $this->prepare_private_download($document, $referral)
            : $this->prepare_legacy_download($document, $referral);

        if (isset($resolved['errors']) || empty($resolved['file_path'])) {
            return null;
        }

        $path = (string) $resolved['file_path'];
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        return [
            'path'          => $path,
            'original_name' => (string) ($document['original_name'] ?? basename($path)),
            'mime_type'     => (string) ($document['mime_type'] ?? ''),
        ];
    }

    /**
     * Returns counts for the Settings migration UI.
     *
     * @return array{legacy: int, private: int}
     */
    public function get_storage_counts(): array
    {
        return [
            'legacy'  => $this->document_repository->count_legacy(),
            'private' => $this->document_repository->count_by_storage_type(PrivateDocumentStorage::STORAGE_PRIVATE),
        ];
    }

    /**
     * Copies one batch of legacy Media Library documents into private storage.
     *
     * Does not delete original attachments. Safe to re-run; already-private rows are skipped.
     *
     * @return array{migrated: int, skipped: int, failed: int}|array{errors: array<string, string>}
     */
    public function migrate_legacy_batch(int $limit = self::MIGRATION_BATCH_SIZE): array
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SETTINGS)) {
            return [
                'errors' => [
                    'permission' => __('You do not have permission to migrate documents.', 'jm-referral-system'),
                ],
            ];
        }

        $ready = $this->private_storage->ensure_ready();

        if (is_wp_error($ready)) {
            return [
                'errors' => [
                    'storage' => __('Unable to prepare private document storage.', 'jm-referral-system'),
                ],
            ];
        }

        $batch    = $this->document_repository->get_legacy_batch($limit);
        $migrated = 0;
        $skipped  = 0;
        $failed   = 0;

        foreach ($batch as $document) {
            $result = $this->migrate_single_legacy_document($document);

            if ('migrated' === $result) {
                ++$migrated;
            } elseif ('skipped' === $result) {
                ++$skipped;
            } else {
                ++$failed;
            }
        }

        return [
            'migrated' => $migrated,
            'skipped'  => $skipped,
            'failed'   => $failed,
        ];
    }

    /**
     * @param array<string, string> $mimes
     * @return array<string, string>
     */
    public function filter_allowed_mimes(array $mimes): array
    {
        return self::ALLOWED_MIMES;
    }

    /**
     * @param array{ext?: string|false, type?: string|false, proper_filename?: string|false} $data
     * @param array<string, string>|null $mimes
     * @return array{ext?: string|false, type?: string|false, proper_filename?: string|false}
     */
    public function filter_filetype_and_ext(array $data, string $file, string $filename, ?array $mimes = null, $real_mime = null): array
    {
        unset($file, $mimes, $real_mime);

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (isset(self::ALLOWED_MIMES[$ext])) {
            $data['ext']  = $ext;
            $data['type'] = self::ALLOWED_MIMES[$ext];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $referral
     * @return array{document: array<string, mixed>, referral: array<string, mixed>, file_path: string}|array{errors: array<string, string>}
     */
    private function prepare_private_download(array $document, array $referral): array
    {
        $relative = (string) ($document['relative_path'] ?? '');
        $file_path = $this->private_storage->resolve_safe_path($relative);

        if (null === $file_path) {
            return [
                'errors' => [
                    'file' => __('The requested action could not be completed.', 'jm-referral-system'),
                ],
            ];
        }

        $expected = (string) ($document['checksum_sha256'] ?? '');

        if ('' !== $expected) {
            $actual = hash_file('sha256', $file_path);

            if (! is_string($actual) || ! hash_equals($expected, $actual)) {
                return [
                    'errors' => [
                        'file' => __('The requested action could not be completed.', 'jm-referral-system'),
                    ],
                ];
            }
        }

        return [
            'document'  => $document,
            'referral'  => $referral,
            'file_path' => $file_path,
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $referral
     * @return array{document: array<string, mixed>, referral: array<string, mixed>, file_path: string}|array{errors: array<string, string>}
     */
    private function prepare_legacy_download(array $document, array $referral): array
    {
        $attachment_id = absint($document['attachment_id'] ?? 0);
        $file_path     = $attachment_id > 0 ? get_attached_file($attachment_id) : false;

        if (! is_string($file_path) || '' === $file_path || ! is_readable($file_path)) {
            return [
                'errors' => [
                    'file' => __('The requested action could not be completed.', 'jm-referral-system'),
                ],
            ];
        }

        if (! $this->is_path_within_uploads($file_path)) {
            return [
                'errors' => [
                    'file' => __('The requested action could not be completed.', 'jm-referral-system'),
                ],
            ];
        }

        return [
            'document'  => $document,
            'referral'  => $referral,
            'file_path' => $file_path,
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @return 'migrated'|'skipped'|'failed'
     */
    private function migrate_single_legacy_document(array $document): string
    {
        $id = absint($document['id'] ?? 0);

        if ($id <= 0) {
            return 'failed';
        }

        $storage_type = (string) ($document['storage_type'] ?? PrivateDocumentStorage::STORAGE_LEGACY);

        if (PrivateDocumentStorage::STORAGE_PRIVATE === $storage_type
            && '' !== (string) ($document['relative_path'] ?? '')
        ) {
            return 'skipped';
        }

        $attachment_id = absint($document['attachment_id'] ?? 0);

        if ($attachment_id <= 0) {
            return 'failed';
        }

        $source = get_attached_file($attachment_id);

        if (! is_string($source) || '' === $source || ! is_readable($source)) {
            return 'failed';
        }

        if (! $this->is_path_within_uploads($source)) {
            return 'failed';
        }

        $original_name = (string) ($document['original_name'] ?? 'document');
        $ext           = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        if ('' === $ext || ! isset(self::ALLOWED_MIMES[$ext])) {
            $source_ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            $ext        = isset(self::ALLOWED_MIMES[$source_ext]) ? $source_ext : '';
        }

        if ('' === $ext) {
            return 'failed';
        }

        $month_dir = $this->private_storage->ensure_month_directory();

        if ('' === $month_dir) {
            return 'failed';
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $stored_name = $this->private_storage->generate_stored_name($ext);
        $stored_name = wp_unique_filename($month_dir, $stored_name);
        $dest_path   = trailingslashit($month_dir) . $stored_name;

        if (! @copy($source, $dest_path)) {
            return 'failed';
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
        @chmod($dest_path, 0640);

        $source_size = (int) filesize($source);
        $dest_size   = is_readable($dest_path) ? (int) filesize($dest_path) : 0;

        if ($dest_size <= 0 || $dest_size !== $source_size) {
            $this->safe_unlink($dest_path);

            return 'failed';
        }

        $checksum = hash_file('sha256', $dest_path);
        $source_checksum = hash_file('sha256', $source);

        if (! is_string($checksum) || ! is_string($source_checksum) || ! hash_equals($source_checksum, $checksum)) {
            $this->safe_unlink($dest_path);

            return 'failed';
        }

        $relative_path = $this->private_storage->build_relative_path($stored_name);

        if (null === $this->private_storage->normalize_relative_path($relative_path)) {
            $this->safe_unlink($dest_path);

            return 'failed';
        }

        $updated = $this->document_repository->update_private_storage(
            $id,
            [
                'storage_type'    => PrivateDocumentStorage::STORAGE_PRIVATE,
                'relative_path'   => $relative_path,
                'stored_name'     => $stored_name,
                'checksum_sha256' => $checksum,
                'file_size'       => $dest_size,
            ]
        );

        if (! $updated) {
            $this->safe_unlink($dest_path);

            return 'failed';
        }

        // Original Media Library file intentionally retained until a later cleanup phase.
        return 'migrated';
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, string>
     */
    private function validate_upload(int $referral_id, array $file): array
    {
        $errors = [];

        if (! Capabilities::current_user_can(Capabilities::UPLOAD_DOCUMENTS)) {
            $errors['permission'] = __('You do not have permission to upload documents.', 'jm-referral-system');
            return $errors;
        }

        $referral = $this->referral_repository->find($referral_id);

        if ($referral_id <= 0 || null === $referral) {
            $errors['referral_id'] = __('Referral not found.', 'jm-referral-system');
            return $errors;
        }

        if (! $this->access_policy->can_mutate_referral($referral)) {
            $errors['permission'] = __('You do not have permission to upload documents for this referral.', 'jm-referral-system');
            return $errors;
        }

        if (empty($file) || ! isset($file['error'])) {
            $errors['file'] = __('Please choose a file to upload.', 'jm-referral-system');
            return $errors;
        }

        $error_code = (int) $file['error'];

        if (UPLOAD_ERR_NO_FILE === $error_code) {
            $errors['file'] = __('Please choose a file to upload.', 'jm-referral-system');
            return $errors;
        }

        if (UPLOAD_ERR_INI_SIZE === $error_code || UPLOAD_ERR_FORM_SIZE === $error_code) {
            $errors['file'] = __('The file exceeds the maximum size of 10 MB.', 'jm-referral-system');
            return $errors;
        }

        if (UPLOAD_ERR_OK !== $error_code) {
            $errors['file'] = __('The upload failed. Please try again.', 'jm-referral-system');
            return $errors;
        }

        $size = absint($file['size'] ?? 0);

        if ($size <= 0) {
            $errors['file'] = __('The uploaded file is empty.', 'jm-referral-system');
            return $errors;
        }

        if ($size > self::MAX_FILE_SIZE) {
            $errors['file'] = __('The file exceeds the maximum size of 10 MB.', 'jm-referral-system');
            return $errors;
        }

        $name = str_replace("\0", '', (string) ($file['name'] ?? ''));
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ('' === $ext || ! isset(self::ALLOWED_MIMES[$ext])) {
            $errors['file'] = __('Allowed file types are PDF, DOC, DOCX, JPG, JPEG, and PNG.', 'jm-referral-system');
            return $errors;
        }

        // Reject obvious double-extension executables (e.g. file.php.pdf still allowed by type;
        // block names containing null or path separators after sanitization checks).
        if (str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
            $errors['file'] = __('The upload is invalid.', 'jm-referral-system');
            return $errors;
        }

        $tmp_name = (string) ($file['tmp_name'] ?? '');

        if ('' === $tmp_name || ! is_uploaded_file($tmp_name)) {
            $errors['file'] = __('The upload is invalid.', 'jm-referral-system');
            return $errors;
        }

        $check = wp_check_filetype_and_ext($tmp_name, $name, self::ALLOWED_MIMES);

        if (empty($check['type']) || empty($check['ext']) || ! $this->is_allowed_mime((string) $check['type'])) {
            $errors['file'] = __('That file type is not allowed.', 'jm-referral-system');
        }

        return $errors;
    }

    /**
     * Public intake file validation (no capability checks).
     *
     * @param array<string, mixed> $file
     * @return array<string, string>
     */
    private function validate_public_upload(int $referral_id, array $file, int $max_bytes): array
    {
        $errors = [];

        $referral = $this->referral_repository->find($referral_id);

        if ($referral_id <= 0 || null === $referral) {
            $errors['referral_id'] = __('Referral not found.', 'jm-referral-system');
            return $errors;
        }

        if (empty($file) || ! isset($file['error'])) {
            $errors['file'] = __('Please choose a file to upload.', 'jm-referral-system');
            return $errors;
        }

        $error_code = (int) $file['error'];

        if (UPLOAD_ERR_NO_FILE === $error_code) {
            $errors['file'] = __('Please choose a file to upload.', 'jm-referral-system');
            return $errors;
        }

        if (UPLOAD_ERR_INI_SIZE === $error_code || UPLOAD_ERR_FORM_SIZE === $error_code) {
            $errors['file'] = __('The file exceeds the maximum allowed size.', 'jm-referral-system');
            return $errors;
        }

        if (UPLOAD_ERR_OK !== $error_code) {
            $errors['file'] = __('The upload failed. Please try again.', 'jm-referral-system');
            return $errors;
        }

        $size = absint($file['size'] ?? 0);

        if ($size <= 0) {
            $errors['file'] = __('The uploaded file is empty.', 'jm-referral-system');
            return $errors;
        }

        if ($size > $max_bytes) {
            $errors['file'] = __('The file exceeds the maximum allowed size.', 'jm-referral-system');
            return $errors;
        }

        $name = str_replace("\0", '', (string) ($file['name'] ?? ''));
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ('' === $ext || ! isset(self::ALLOWED_MIMES[$ext])) {
            $errors['file'] = __('Allowed file types are PDF, DOC, DOCX, JPG, JPEG, and PNG.', 'jm-referral-system');
            return $errors;
        }

        if (str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
            $errors['file'] = __('The upload is invalid.', 'jm-referral-system');
            return $errors;
        }

        $tmp_name = (string) ($file['tmp_name'] ?? '');

        if ('' === $tmp_name || ! is_uploaded_file($tmp_name)) {
            $errors['file'] = __('The upload is invalid.', 'jm-referral-system');
            return $errors;
        }

        $check = wp_check_filetype_and_ext($tmp_name, $name, self::ALLOWED_MIMES);

        if (empty($check['type']) || empty($check['ext']) || ! $this->is_allowed_mime((string) $check['type'])) {
            $errors['file'] = __('That file type is not allowed.', 'jm-referral-system');
        }

        return $errors;
    }

    private function sanitize_original_name(string $name): string
    {
        $name = str_replace("\0", '', $name);
        $name = basename(str_replace('\\', '/', $name));
        $name = sanitize_file_name($name);

        return '' !== $name ? $name : 'document';
    }

    private function is_allowed_mime(string $mime_type): bool
    {
        return in_array($mime_type, array_values(self::ALLOWED_MIMES), true);
    }

    private function is_path_within_uploads(string $file_path): bool
    {
        $uploads = wp_get_upload_dir();

        if (! empty($uploads['error']) || empty($uploads['basedir'])) {
            return false;
        }

        $real_path = realpath($file_path);
        $real_base = realpath((string) $uploads['basedir']);

        if (false === $real_path || false === $real_base) {
            return false;
        }

        $real_base = rtrim(str_replace('\\', '/', $real_base), '/') . '/';
        $real_path = str_replace('\\', '/', $real_path);

        return str_starts_with($real_path, $real_base);
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
