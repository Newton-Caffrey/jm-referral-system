<?php

namespace JMReferral\Documents;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;

class ReferralDocumentService
{
    private const MAX_FILE_SIZE = 10485760; // 10 MB

    /**
     * Allowed extensions mapped to MIME types for WordPress upload overrides.
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
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Validates and uploads a document for a referral.
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

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $original_name = sanitize_file_name((string) ($file['name'] ?? ''));

        add_filter('upload_mimes', [$this, 'filter_allowed_mimes']);
        add_filter('wp_check_filetype_and_ext', [$this, 'filter_filetype_and_ext'], 10, 5);

        $attachment_id = media_handle_upload('jmrs_document', 0);

        remove_filter('upload_mimes', [$this, 'filter_allowed_mimes']);
        remove_filter('wp_check_filetype_and_ext', [$this, 'filter_filetype_and_ext'], 10);

        if (is_wp_error($attachment_id)) {
            return [
                'errors' => [
                    'file' => $attachment_id->get_error_message(),
                ],
            ];
        }

        $attachment_id = (int) $attachment_id;
        $mime_type     = (string) get_post_mime_type($attachment_id);
        $file_path     = get_attached_file($attachment_id);
        $file_size     = (is_string($file_path) && is_readable($file_path))
            ? (int) filesize($file_path)
            : absint($file['size'] ?? 0);

        if ('' === $mime_type || ! $this->is_allowed_mime($mime_type)) {
            wp_delete_attachment($attachment_id, true);

            return [
                'errors' => [
                    'file' => __('That file type is not allowed.', 'jm-referral-system'),
                ],
            ];
        }

        if ($file_size > self::MAX_FILE_SIZE) {
            wp_delete_attachment($attachment_id, true);

            return [
                'errors' => [
                    'file' => __('The file exceeds the maximum size of 10 MB.', 'jm-referral-system'),
                ],
            ];
        }

        $document_id = $this->document_repository->create(
            [
                'referral_id'   => $referral_id,
                'attachment_id' => $attachment_id,
                'original_name' => '' !== $original_name ? $original_name : 'document',
                'mime_type'     => $mime_type,
                'file_size'     => $file_size,
                'uploaded_by'   => get_current_user_id(),
                'created_at'    => current_time('mysql'),
            ]
        );

        if (false === $document_id) {
            wp_delete_attachment($attachment_id, true);

            return false;
        }

        $this->activity_service->log_document_uploaded(
            $referral_id,
            '' !== $original_name ? $original_name : 'document'
        );

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
     * Loads a document and verifies download access.
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

        $attachment_id = absint($document['attachment_id'] ?? 0);
        $file_path     = $attachment_id > 0 ? get_attached_file($attachment_id) : false;

        if (! is_string($file_path) || '' === $file_path || ! is_readable($file_path)) {
            return [
                'errors' => [
                    'file' => __('The document file could not be found.', 'jm-referral-system'),
                ],
            ];
        }

        if (! $this->is_path_within_uploads($file_path)) {
            return [
                'errors' => [
                    'file' => __('The document file path is invalid.', 'jm-referral-system'),
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
     * Restricts WordPress uploads to allowed document MIME types.
     *
     * @param array<string, string> $mimes
     * @return array<string, string>
     */
    public function filter_allowed_mimes(array $mimes): array
    {
        return self::ALLOWED_MIMES;
    }

    /**
     * Ensures WordPress filetype detection accepts our allowed extensions.
     *
     * @param array{ext?: string|false, type?: string|false, proper_filename?: string|false} $data
     * @param array<string, string>|null $mimes
     * @return array{ext?: string|false, type?: string|false, proper_filename?: string|false}
     */
    public function filter_filetype_and_ext(array $data, string $file, string $filename, ?array $mimes = null, $real_mime = null): array
    {
        $mimes = is_array($mimes) ? $mimes : [];
        unset($file, $mimes, $real_mime);

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (isset(self::ALLOWED_MIMES[$ext])) {
            $data['ext']  = $ext;
            $data['type'] = self::ALLOWED_MIMES[$ext];
        }

        return $data;
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

        if (! $this->access_policy->can_view_referral($referral)) {
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

        $name = (string) ($file['name'] ?? '');
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ('' === $ext || ! isset(self::ALLOWED_MIMES[$ext])) {
            $errors['file'] = __('Allowed file types are PDF, DOC, DOCX, JPG, JPEG, and PNG.', 'jm-referral-system');
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
}
