<?php

namespace JMReferral\Documents;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralRepository;

class ReferralDocumentController
{
    private const ERROR_TRANSIENT_PREFIX = 'jmrs_referral_document_errors_';

    public function __construct(
        private ReferralDocumentService $document_service,
        private ReferralRepository $referral_repository,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Registers document upload and download hooks.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_upload']);
        add_action('admin_init', [$this, 'handle_download']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    /**
     * Handles document upload POST from the referral view page.
     */
    public function handle_upload(): void
    {
        if (! isset($_POST['jmrs_upload_document'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::UPLOAD_DOCUMENTS)) {
            wp_die(esc_html__('You do not have permission to upload documents.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;

        check_admin_referer('jmrs_upload_document_' . $referral_id, 'jmrs_upload_document_nonce');

        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral || ! $this->access_policy->can_view_referral($referral)) {
            wp_die(esc_html__('You do not have permission to upload documents for this referral.', 'jm-referral-system'));
        }

        $file = isset($_FILES['jmrs_document']) && is_array($_FILES['jmrs_document'])
            ? $_FILES['jmrs_document']
            : [];

        $result = $this->document_service->upload($referral_id, $file);

        if (false === $result) {
            $this->store_errors(
                $referral_id,
                [
                    'general' => __('Unable to upload the document. Please try again.', 'jm-referral-system'),
                ]
            );
            $this->redirect_to_view($referral_id);
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_errors($referral_id, $result['errors']);
            $this->redirect_to_view($referral_id);
        }

        $this->redirect_to_view($referral_id, true);
    }

    /**
     * Streams a document download after capability and AccessPolicy checks.
     */
    public function handle_download(): void
    {
        if (! isset($_GET['jmrs_download_document'])) {
            return;
        }

        $document_id = absint(wp_unslash($_GET['jmrs_download_document']));

        if ($document_id <= 0) {
            wp_die(esc_html__('Document not found.', 'jm-referral-system'));
        }

        check_admin_referer('jmrs_download_document_' . $document_id);

        if (! Capabilities::current_user_can(Capabilities::DOWNLOAD_DOCUMENTS)) {
            wp_die(esc_html__('You do not have permission to download documents.', 'jm-referral-system'));
        }

        $prepared = $this->document_service->prepare_download($document_id);

        if (isset($prepared['errors']) && is_array($prepared['errors'])) {
            $message = (string) reset($prepared['errors']);
            wp_die(esc_html('' !== $message ? $message : __('Unable to download the document.', 'jm-referral-system')));
        }

        $document  = $prepared['document'];
        $file_path = (string) $prepared['file_path'];
        $filename  = sanitize_file_name((string) ($document['original_name'] ?? 'document'));
        $mime_type = (string) ($document['mime_type'] ?? 'application/octet-stream');
        $file_size = absint($document['file_size'] ?? 0);

        if ('' === $filename) {
            $filename = 'document';
        }

        if ($file_size <= 0 && is_readable($file_path)) {
            $file_size = (int) filesize($file_path);
        }

        if ('' === $mime_type || ! preg_match('/^[a-z0-9.+\/-]+$/i', $mime_type)) {
            $mime_type = 'application/octet-stream';
        }

        // Avoid loading the full file into PHP memory; clear buffers before streaming.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . (string) max(0, $file_size));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- intentional binary stream.
        readfile($file_path);
        exit;
    }

    /**
     * Renders upload success and validation notices on the view screen.
     */
    public function render_notices(): void
    {
        if (! $this->is_view_screen()) {
            return;
        }

        if (isset($_GET['jmrs_document_uploaded']) && '1' === $_GET['jmrs_document_uploaded']) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Document uploaded successfully.', 'jm-referral-system');
            echo '</p></div>';
        }

        $referral_id = isset($_GET['referral_id']) ? absint($_GET['referral_id']) : 0;
        $errors      = self::get_errors($referral_id, false);

        if (empty($errors)) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Please fix the following errors:', 'jm-referral-system');
        echo '</p><ul>';

        foreach ($errors as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }

        echo '</ul></div>';
    }

    /**
     * Builds a nonce-protected download URL for a document ID.
     */
    public static function get_download_url(int $document_id): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'jmrs_download_document' => $document_id,
                ],
                admin_url('admin.php')
            ),
            'jmrs_download_document_' . $document_id
        );
    }

    /**
     * @param bool $consume Whether to delete the transient after reading.
     * @return array<string, string>
     */
    public static function get_errors(int $referral_id, bool $consume = true): array
    {
        $key    = self::ERROR_TRANSIENT_PREFIX . get_current_user_id() . '_' . $referral_id;
        $errors = get_transient($key);

        if (! is_array($errors)) {
            return [];
        }

        if ($consume) {
            delete_transient($key);
        }

        $clean = [];
        foreach ($errors as $field => $message) {
            $clean[(string) $field] = (string) $message;
        }

        return $clean;
    }

    /**
     * @param array<string, string> $errors
     */
    private function store_errors(int $referral_id, array $errors): void
    {
        set_transient(
            self::ERROR_TRANSIENT_PREFIX . get_current_user_id() . '_' . $referral_id,
            $errors,
            MINUTE_IN_SECONDS * 5
        );
    }

    private function redirect_to_view(int $referral_id, bool $success = false): void
    {
        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];

        if ($success) {
            $args['jmrs_document_uploaded'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function is_view_screen(): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        return 'jm-referrals-view' === $page;
    }
}
