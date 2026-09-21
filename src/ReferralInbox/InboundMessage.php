<?php

namespace JMReferral\ReferralInbox;

/**
 * Provider-neutral inbound message envelope (Phase 5B.4).
 *
 * Future mailbox connectors construct this object and call
 * {@see ReferralInboxIngestionService::ingest()}.
 *
 * Does not carry Graph/Gmail objects, tokens, raw MIME, raw HTML, or headers.
 */
final class InboundMessage
{
    /**
     * @param array<int, InboundAttachmentMetadata> $attachments
     */
    private function __construct(
        private string $source_provider,
        private string $mailbox_identifier,
        private string $provider_message_id,
        private ?string $internet_message_id,
        private ?string $conversation_identifier,
        private ?string $sender_name,
        private ?string $sender_email,
        private ?string $recipient_summary,
        private ?string $subject,
        private ?string $body_preview,
        private ?string $received_at,
        private int $declared_attachment_count,
        private array $attachments
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: true, message: self}|array{ok: false, errors: array<string, string>}
     */
    public static function try_from(array $data): array
    {
        $errors = [];

        // Reject known connector-specific / dangerous payloads at the envelope.
        $forbidden = [
            'access_token',
            'refresh_token',
            'oauth_token',
            'raw_mime',
            'raw_html',
            'html_body',
            'headers',
            'graph_message',
            'gmail_message',
            'subscription_id',
            'history_id',
            'tenant_token',
        ];
        foreach ($forbidden as $key) {
            if (array_key_exists($key, $data) && null !== $data[$key] && '' !== $data[$key]) {
                $errors[$key] = __('This field is not accepted by the ingestion gateway.', 'jm-referral-system');
            }
        }

        $source_provider = trim((string) ($data['source_provider'] ?? ''));
        if ('' === $source_provider) {
            $errors['source_provider'] = __('Source provider is required.', 'jm-referral-system');
        }

        $mailbox_identifier = (string) ($data['mailbox_identifier'] ?? '');
        if ('' === trim($mailbox_identifier)) {
            $errors['mailbox_identifier'] = __('Mailbox identifier is required.', 'jm-referral-system');
        }

        $provider_message_id = (string) ($data['provider_message_id'] ?? '');
        if ('' === trim($provider_message_id)) {
            $errors['provider_message_id'] = __('Provider message ID is required.', 'jm-referral-system');
        }

        $internet_message_id = null;
        if (array_key_exists('internet_message_id', $data) && null !== $data['internet_message_id'] && '' !== trim((string) $data['internet_message_id'])) {
            $internet_message_id = (string) $data['internet_message_id'];
        }

        $conversation_identifier = null;
        if (array_key_exists('conversation_identifier', $data) && null !== $data['conversation_identifier'] && '' !== trim((string) $data['conversation_identifier'])) {
            $conversation_identifier = (string) $data['conversation_identifier'];
        }

        $sender_name = null;
        if (array_key_exists('sender_name', $data) && null !== $data['sender_name'] && '' !== trim((string) $data['sender_name'])) {
            $sender_name = (string) $data['sender_name'];
        }

        $sender_email = null;
        if (array_key_exists('sender_email', $data) && null !== $data['sender_email'] && '' !== trim((string) $data['sender_email'])) {
            $sender_email = (string) $data['sender_email'];
        }

        $recipient_summary = null;
        if (array_key_exists('recipient_summary', $data) && null !== $data['recipient_summary'] && '' !== trim((string) $data['recipient_summary'])) {
            $recipient_summary = (string) $data['recipient_summary'];
        }

        $subject = null;
        if (array_key_exists('subject', $data) && null !== $data['subject'] && '' !== trim((string) $data['subject'])) {
            $subject = (string) $data['subject'];
        }

        $body_preview = null;
        if (array_key_exists('body_preview', $data) && null !== $data['body_preview'] && '' !== (string) $data['body_preview']) {
            $body_preview = (string) $data['body_preview'];
        }

        $received_at = null;
        if (array_key_exists('received_at', $data) && null !== $data['received_at'] && '' !== trim((string) $data['received_at'])) {
            if (! is_scalar($data['received_at'])) {
                $errors['received_at'] = __('received_at must be a scalar timestamp.', 'jm-referral-system');
            } else {
                $received_at = (string) $data['received_at'];
            }
        }

        $declared_attachment_count = 0;
        if (array_key_exists('declared_attachment_count', $data) && null !== $data['declared_attachment_count'] && '' !== $data['declared_attachment_count']) {
            if (! is_scalar($data['declared_attachment_count']) || ! is_numeric($data['declared_attachment_count']) || (int) $data['declared_attachment_count'] < 0) {
                $errors['declared_attachment_count'] = __('Declared attachment count must be zero or a positive integer.', 'jm-referral-system');
            } else {
                $declared_attachment_count = (int) $data['declared_attachment_count'];
            }
        }

        $attachments = [];
        $raw_attachments = $data['attachments'] ?? [];
        if (null === $raw_attachments) {
            $raw_attachments = [];
        }
        if (! is_array($raw_attachments)) {
            $errors['attachments'] = __('Attachments must be a list.', 'jm-referral-system');
        } else {
            $index = 0;
            foreach ($raw_attachments as $raw) {
                if ($raw instanceof InboundAttachmentMetadata) {
                    $attachments[] = $raw;
                    ++$index;
                    continue;
                }
                if (! is_array($raw)) {
                    $errors['attachments.' . $index] = __('Each attachment must be an array or InboundAttachmentMetadata.', 'jm-referral-system');
                    ++$index;
                    continue;
                }
                $parsed = InboundAttachmentMetadata::try_from($raw);
                if (! ($parsed['ok'] ?? false)) {
                    foreach (($parsed['errors'] ?? []) as $field => $message) {
                        $errors['attachments.' . $index . '.' . $field] = $message;
                    }
                } else {
                    $attachments[] = $parsed['attachment'];
                }
                ++$index;
            }
        }

        // Connectors must not supply sender_domain — derived by ReferralInboxService.
        if (array_key_exists('sender_domain', $data) && null !== $data['sender_domain'] && '' !== trim((string) $data['sender_domain'])) {
            $errors['sender_domain'] = __('Connectors must not supply sender_domain; it is derived from sender_email.', 'jm-referral-system');
        }

        if ([] !== $errors) {
            return [
                'ok'     => false,
                'errors' => $errors,
            ];
        }

        return [
            'ok'      => true,
            'message' => new self(
                $source_provider,
                $mailbox_identifier,
                $provider_message_id,
                $internet_message_id,
                $conversation_identifier,
                $sender_name,
                $sender_email,
                $recipient_summary,
                $subject,
                $body_preview,
                $received_at,
                $declared_attachment_count,
                $attachments
            ),
        ];
    }

    public function source_provider(): string
    {
        return $this->source_provider;
    }

    public function mailbox_identifier(): string
    {
        return $this->mailbox_identifier;
    }

    public function provider_message_id(): string
    {
        return $this->provider_message_id;
    }

    public function internet_message_id(): ?string
    {
        return $this->internet_message_id;
    }

    public function conversation_identifier(): ?string
    {
        return $this->conversation_identifier;
    }

    public function sender_name(): ?string
    {
        return $this->sender_name;
    }

    public function sender_email(): ?string
    {
        return $this->sender_email;
    }

    public function recipient_summary(): ?string
    {
        return $this->recipient_summary;
    }

    public function subject(): ?string
    {
        return $this->subject;
    }

    public function body_preview(): ?string
    {
        return $this->body_preview;
    }

    public function received_at(): ?string
    {
        return $this->received_at;
    }

    public function declared_attachment_count(): int
    {
        return $this->declared_attachment_count;
    }

    /**
     * @return array<int, InboundAttachmentMetadata>
     */
    public function attachments(): array
    {
        return $this->attachments;
    }

    /**
     * Input for ReferralInboxService::create().
     *
     * attachment_count uses the source-declared count (not metadata row count).
     *
     * @return array<string, mixed>
     */
    public function to_create_input(): array
    {
        $input = [
            'source_provider'     => $this->source_provider,
            'mailbox_identifier'  => $this->mailbox_identifier,
            'provider_message_id' => $this->provider_message_id,
            'attachment_count'    => $this->declared_attachment_count,
        ];

        if (null !== $this->internet_message_id) {
            $input['internet_message_id'] = $this->internet_message_id;
        }
        if (null !== $this->conversation_identifier) {
            $input['conversation_identifier'] = $this->conversation_identifier;
        }
        if (null !== $this->sender_name) {
            $input['sender_name'] = $this->sender_name;
        }
        if (null !== $this->sender_email) {
            $input['sender_email'] = $this->sender_email;
        }
        if (null !== $this->recipient_summary) {
            $input['recipient_summary'] = $this->recipient_summary;
        }
        if (null !== $this->subject) {
            $input['subject'] = $this->subject;
        }
        if (null !== $this->body_preview) {
            $input['body_preview'] = $this->body_preview;
        }
        if (null !== $this->received_at) {
            $input['received_at'] = $this->received_at;
        }

        return $input;
    }
}
