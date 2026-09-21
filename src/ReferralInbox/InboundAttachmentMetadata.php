<?php

namespace JMReferral\ReferralInbox;

/**
 * Provider-neutral inbound attachment metadata (Phase 5B.4).
 *
 * Connectors supply identity + descriptive fields only.
 * storage_status is applied by the ingestion service as metadata_only.
 * No binary payload, file path, or Media Library reference.
 */
final class InboundAttachmentMetadata
{
    private function __construct(
        private string $provider_attachment_id,
        private string $filename,
        private string $mime_type,
        private ?int $size_bytes,
        private ?string $sha256
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: true, attachment: self}|array{ok: false, errors: array<string, string>}
     */
    public static function try_from(array $data): array
    {
        $errors = [];

        $provider_attachment_id = trim((string) ($data['provider_attachment_id'] ?? ''));
        if ('' === $provider_attachment_id) {
            $errors['provider_attachment_id'] = __('Provider attachment ID is required.', 'jm-referral-system');
        } elseif (strlen($provider_attachment_id) > ReferralInboxLimits::PROVIDER_ATTACHMENT_ID_MAX) {
            $errors['provider_attachment_id'] = __('Provider attachment ID exceeds the maximum length.', 'jm-referral-system');
        }

        $filename = trim((string) ($data['filename'] ?? ''));
        if ('' === $filename) {
            $errors['filename'] = __('Attachment filename is required.', 'jm-referral-system');
        } elseif (strlen($filename) > ReferralInboxLimits::FILENAME_MAX) {
            $errors['filename'] = __('Attachment filename exceeds the maximum length.', 'jm-referral-system');
        }

        $mime_type = trim((string) ($data['mime_type'] ?? ''));
        if ('' === $mime_type) {
            $errors['mime_type'] = __('Attachment MIME type is required.', 'jm-referral-system');
        } elseif (strlen($mime_type) > ReferralInboxLimits::MIME_TYPE_MAX) {
            $errors['mime_type'] = __('Attachment MIME type exceeds the maximum length.', 'jm-referral-system');
        }

        $size_bytes = null;
        if (array_key_exists('size_bytes', $data) && null !== $data['size_bytes'] && '' !== $data['size_bytes']) {
            if (! is_scalar($data['size_bytes']) || ! is_numeric($data['size_bytes']) || (int) $data['size_bytes'] < 0) {
                $errors['size_bytes'] = __('Attachment size must be zero or a positive integer.', 'jm-referral-system');
            } else {
                $size_bytes = (int) $data['size_bytes'];
            }
        }

        $sha256 = null;
        if (array_key_exists('sha256', $data) && null !== $data['sha256'] && '' !== trim((string) $data['sha256'])) {
            // Pass through for ReferralInboxService validation so ingestion can return PARTIAL
            // when the Inbox row succeeds but one attachment fails (Phase 5B.4).
            $sha256 = strtolower(trim((string) $data['sha256']));
        }

        // Reject connector attempts to supply storage/path/binary fields.
        if (array_key_exists('storage_status', $data) && null !== $data['storage_status'] && '' !== (string) $data['storage_status']) {
            $errors['storage_status'] = __('Connectors must not supply storage_status; ingestion applies metadata_only.', 'jm-referral-system');
        }
        if (array_key_exists('private_path', $data) && null !== $data['private_path'] && '' !== trim((string) $data['private_path'])) {
            $errors['private_path'] = __('Connectors must not supply private_path.', 'jm-referral-system');
        }
        if (array_key_exists('content', $data) || array_key_exists('bytes', $data) || array_key_exists('binary', $data)) {
            $errors['content'] = __('Binary attachment payloads are not accepted by the ingestion gateway.', 'jm-referral-system');
        }

        if ([] !== $errors) {
            return [
                'ok'     => false,
                'errors' => $errors,
            ];
        }

        return [
            'ok'         => true,
            'attachment' => new self(
                $provider_attachment_id,
                $filename,
                $mime_type,
                $size_bytes,
                $sha256
            ),
        ];
    }

    public function provider_attachment_id(): string
    {
        return $this->provider_attachment_id;
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function mime_type(): string
    {
        return $this->mime_type;
    }

    public function size_bytes(): ?int
    {
        return $this->size_bytes;
    }

    public function sha256(): ?string
    {
        return $this->sha256;
    }

    /**
     * Input for ReferralInboxService::addAttachmentMetadata().
     *
     * @return array<string, mixed>
     */
    public function to_service_input(): array
    {
        $input = [
            'provider_attachment_id' => $this->provider_attachment_id,
            'filename'               => $this->filename,
            'mime_type'              => $this->mime_type,
            'storage_status'         => InboxAttachmentStatus::METADATA_ONLY,
        ];

        if (null !== $this->size_bytes) {
            $input['size_bytes'] = $this->size_bytes;
        }
        if (null !== $this->sha256) {
            $input['sha256'] = $this->sha256;
        }

        return $input;
    }
}
