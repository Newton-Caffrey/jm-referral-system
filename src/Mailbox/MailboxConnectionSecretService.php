<?php

namespace JMReferral\Mailbox;

use JMReferral\Security\SecretCipher;

/**
 * Application boundary for encrypted mailbox connection secrets (Phase 5C.1).
 *
 * Never returns plaintext through list/view helpers used by templates.
 */
class MailboxConnectionSecretService
{
    public function __construct(
        private MailboxConnectionSecretRepository $repository,
        private SecretCipher $cipher
    ) {
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function store_secret(int $connection_id, string $secret_name, string $plaintext): array
    {
        if ($connection_id <= 0 || '' === $secret_name || '' === $plaintext) {
            return ['ok' => false, 'error' => 'invalid_input'];
        }

        $encrypted = $this->cipher->encrypt($plaintext, $connection_id, $secret_name);
        if (! ($encrypted['ok'] ?? false)) {
            return [
                'ok'    => false,
                'error' => (string) ($encrypted['error'] ?? SecretCipher::RESULT_ENCRYPTION_FAILED),
            ];
        }

        $now = current_time('mysql');
        $ok  = $this->repository->upsert(
            [
                'connection_id' => $connection_id,
                'secret_name'   => $secret_name,
                'algorithm'     => $encrypted['algorithm'],
                'key_version'   => $encrypted['key_version'],
                'nonce'         => $encrypted['nonce'],
                'ciphertext'    => $encrypted['ciphertext'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]
        );

        return $ok
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'persistence_error'];
    }

    public function has_secret(int $connection_id, string $secret_name): bool
    {
        return $this->repository->exists($connection_id, $secret_name);
    }

    /**
     * Runtime-only retrieval for future Graph auth (not used by 5C.1 UI).
     *
     * @return array{ok: true, plaintext: string}|array{ok: false, error: string}
     */
    public function retrieve_secret(int $connection_id, string $secret_name): array
    {
        $row = $this->repository->find($connection_id, $secret_name);
        if (null === $row) {
            return ['ok' => false, 'error' => SecretCipher::RESULT_KEY_MISSING];
        }

        return $this->cipher->decrypt(
            [
                'algorithm'   => (string) $row['algorithm'],
                'key_version' => (int) $row['key_version'],
                'nonce'       => (string) $row['nonce'],
                'ciphertext'  => (string) $row['ciphertext'],
            ],
            $connection_id,
            $secret_name
        );
    }

    public function delete_secret(int $connection_id, string $secret_name): bool
    {
        return $this->repository->delete($connection_id, $secret_name);
    }

    public function delete_all_for_connection(int $connection_id): int
    {
        return $this->repository->delete_for_connection($connection_id);
    }
}
