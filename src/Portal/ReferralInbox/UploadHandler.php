<?php

namespace JMReferral\Portal\ReferralInbox;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Portal\Clinical\PortalViewHost;
use JMReferral\Portal\PortalUrls;
use JMReferral\ReferralInbox\Document\ReferralInboxDocumentService;
use JMReferral\ReferralInbox\ReferralInboxStatus;

/**
 * Staff Portal referral form upload (Phase 5E.1).
 *
 * GET shows the upload form. POST stores one Word or PDF form as a manual
 * Referral Inbox item and sends the user to Prepare Referral. No referral is
 * created here: preparation, validation and staff confirmation still apply.
 */
class UploadHandler
{
    private const NONCE_ACTION = 'jmrs_inbox_upload_form';

    private const NONCE_FIELD = 'jmrs_inbox_upload_nonce';

    private const FILE_FIELD = 'jmrs_inbox_upload_file';

    public function __construct(
        private PortalViewHost $view_host,
        private AccessPolicy $access_policy,
        private ReferralInboxDocumentService $document_service
    ) {
    }

    public function dispatch(): void
    {
        // Same rule as Prepare Referral: Inbox management plus CREATE_REFERRALS.
        if (! is_user_logged_in() || ! $this->access_policy->can_prepare_referral_from_inbox()) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $error = '';

        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
            $error = $this->handle_post();
            if (null === $error) {
                return;
            }
        }

        $this->render($error);
    }

    /**
     * @return string|null Error message to show, or null when the request was redirected.
     */
    private function handle_post(): ?string
    {
        $nonce = isset($_POST[self::NONCE_FIELD]) && is_scalar($_POST[self::NONCE_FIELD])
            ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_FIELD]))
            : '';

        // A request larger than the server's post limit arrives with no fields at all.
        if ('' === $nonce && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            return __('The file exceeds the maximum size this server accepts. Upload a smaller file.', 'jm-referral-system');
        }

        if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return __('Security check failed. Please try again.', 'jm-referral-system');
        }

        $file = isset($_FILES[self::FILE_FIELD]) && is_array($_FILES[self::FILE_FIELD])
            ? $_FILES[self::FILE_FIELD]
            : [];

        // One file only. A multi-file field posts arrays here.
        if (isset($file['name']) && is_array($file['name'])) {
            return __('Upload one file at a time.', 'jm-referral-system');
        }

        $outcome = $this->document_service->upload($file, get_current_user_id());
        $result  = (string) ($outcome['result'] ?? '');

        if (ReferralInboxDocumentService::ERROR === $result) {
            $errors = is_array($outcome['errors'] ?? null) ? $outcome['errors'] : [];
            $first  = reset($errors);

            return is_string($first) && '' !== $first
                ? $first
                : __('The upload failed. Please try again.', 'jm-referral-system');
        }

        $inbox_id = absint($outcome['inbox_id'] ?? 0);
        if ($inbox_id <= 0) {
            return __('The upload failed. Please try again.', 'jm-referral-system');
        }

        $status = (string) ($outcome['status'] ?? '');

        if (ReferralInboxDocumentService::EXISTING === $result) {
            $this->redirect_existing($inbox_id, $status);

            return null;
        }

        if (ReferralInboxStatus::NEEDS_REVIEW === $status) {
            wp_safe_redirect(PortalUrls::referral_inbox_prepare($inbox_id));
            exit;
        }

        $this->redirect_item(
            $inbox_id,
            'success',
            __('The form was uploaded. Start Review to prepare it as a referral.', 'jm-referral-system')
        );

        return null;
    }

    private function redirect_existing(int $inbox_id, string $status): void
    {
        if (ReferralInboxStatus::NEEDS_REVIEW === $status) {
            $this->redirect_item(
                $inbox_id,
                'info',
                __('This file has already been uploaded. It is waiting here for review.', 'jm-referral-system')
            );

            return;
        }

        if (ReferralInboxStatus::ACCEPTED === $status) {
            $this->redirect_item(
                $inbox_id,
                'info',
                __('This file has already been uploaded and converted to a referral.', 'jm-referral-system')
            );

            return;
        }

        $this->redirect_item(
            $inbox_id,
            'info',
            __('This file has already been uploaded. This is the Inbox item it created.', 'jm-referral-system')
        );
    }

    private function redirect_item(int $inbox_id, string $type, string $message): void
    {
        wp_safe_redirect(
            add_query_arg(
                [
                    'jmrs_inbox_notice' => sanitize_key($type),
                    'jmrs_inbox_msg'    => rawurlencode($message),
                ],
                PortalUrls::referral_inbox_item($inbox_id)
            )
        );
        exit;
    }

    private function render(string $error): void
    {
        $max_bytes = min(
            ReferralInboxDocumentService::MAX_FILE_SIZE,
            function_exists('wp_max_upload_size') ? (int) wp_max_upload_size() : ReferralInboxDocumentService::MAX_FILE_SIZE
        );
        if ($max_bytes <= 0) {
            $max_bytes = ReferralInboxDocumentService::MAX_FILE_SIZE;
        }

        $view = [
            'error'        => $error,
            'form_action'  => PortalUrls::referral_inbox_upload(),
            'list_url'     => PortalUrls::referral_inbox(),
            'file_field'   => self::FILE_FIELD,
            'max_display'  => size_format($max_bytes),
            'max_bytes'    => $max_bytes,
            'nonce_field'  => wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD, true, false),
        ];

        $this->view_host->render_portal_page(
            'referral-inbox/upload',
            __('Upload Referral Form', 'jm-referral-system'),
            'referral_inbox_upload',
            [
                [
                    'label' => __('Referral Inbox', 'jm-referral-system'),
                    'url'   => PortalUrls::referral_inbox(),
                ],
                [
                    'label' => __('Upload Referral Form', 'jm-referral-system'),
                    'url'   => '',
                ],
            ],
            $view
        );
    }
}
