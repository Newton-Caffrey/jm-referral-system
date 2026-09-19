<?php

namespace JMReferral\ReferralInbox;

/**
 * Provider-neutral Inbox identity and deterministic dedupe-key generation.
 *
 * Canonical identity: source_provider + mailbox_identifier + provider_message_id.
 * Callers must never supply a trusted dedupe_key — the service generates it.
 */
class ReferralInboxIdentity
{
    /**
     * Builds a 64-character lowercase hex SHA-256 dedupe key.
     *
     * Uses length-prefixed fields so delimiters inside values cannot collide.
     *
     * @return array{ok: true, dedupe_key: string, source_provider: string, mailbox_identifier: string, provider_message_id: string}|array{ok: false, errors: array<string, string>}
     */
    public function build(string $source_provider, string $mailbox_identifier, string $provider_message_id): array
    {
        $errors = [];

        $provider = $this->normalise_source_provider($source_provider);
        if (null === $provider) {
            $errors['source_provider'] = __('Invalid source provider.', 'jm-referral-system');
        }

        $mailbox = $this->normalise_mailbox_identifier($mailbox_identifier);
        if (null === $mailbox) {
            $errors['mailbox_identifier'] = __('Mailbox identifier is required and must be within length limits.', 'jm-referral-system');
        }

        $message_id = $this->normalise_provider_message_id($provider_message_id);
        if (null === $message_id) {
            $errors['provider_message_id'] = __('Provider message ID is required and must be within length limits.', 'jm-referral-system');
        }

        if (! empty($errors)) {
            return [
                'ok'     => false,
                'errors' => $errors,
            ];
        }

        /** @var string $provider */
        /** @var string $mailbox */
        /** @var string $message_id */

        return [
            'ok'                   => true,
            'dedupe_key'           => $this->hash_canonical($provider, $mailbox, $message_id),
            'source_provider'      => $provider,
            'mailbox_identifier'   => $mailbox,
            'provider_message_id'  => $message_id,
        ];
    }

    /**
     * Deterministic SHA-256 of a length-prefixed canonical string.
     */
    public function hash_canonical(string $source_provider, string $mailbox_identifier, string $provider_message_id): string
    {
        $canonical = sprintf(
            "v1\n%d:%s\n%d:%s\n%d:%s",
            strlen($source_provider),
            $source_provider,
            strlen($mailbox_identifier),
            $mailbox_identifier,
            strlen($provider_message_id),
            $provider_message_id
        );

        return hash('sha256', $canonical);
    }

    public function normalise_source_provider(string $source_provider): ?string
    {
        $source = strtolower(trim($source_provider));

        if (! ReferralInboxSource::is_valid($source)) {
            return null;
        }

        return $source;
    }

    /**
     * Opaque mailbox identifier — trim only; do not lowercase.
     */
    public function normalise_mailbox_identifier(string $mailbox_identifier): ?string
    {
        $value = trim($mailbox_identifier);

        if ('' === $value || strlen($value) > ReferralInboxLimits::MAILBOX_IDENTIFIER_MAX) {
            return null;
        }

        return $value;
    }

    /**
     * Opaque provider message ID — trim only; preserve case.
     */
    public function normalise_provider_message_id(string $provider_message_id): ?string
    {
        $value = trim($provider_message_id);

        if ('' === $value || strlen($value) > ReferralInboxLimits::PROVIDER_MESSAGE_ID_MAX) {
            return null;
        }

        return $value;
    }
}
