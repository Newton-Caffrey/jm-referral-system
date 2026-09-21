<?php

namespace JMReferral\Security;

/**
 * Authenticated encryption for short-lived plaintext secrets (Phase 5C.1).
 *
 * Algorithm: libsodium XChaCha20-Poly1305 IETF AEAD.
 * Fails closed when crypto or keys are unavailable.
 */
class SecretCipher
{
    public const ALGORITHM = 'sodium_xchacha20poly1305_ietf';

    public const RESULT_OK                    = 'ok';
    public const RESULT_UNAVAILABLE           = 'secret_unavailable';
    public const RESULT_KEY_MISSING           = 'secret_key_missing';
    public const RESULT_DECRYPTION_FAILED     = 'secret_decryption_failed';
    public const RESULT_ENCRYPTION_FAILED     = 'secret_encryption_failed';

    public function __construct(
        private SecretKeyProvider $keys
    ) {
    }

    /**
     * @return array{ok: true, algorithm: string, key_version: int, nonce: string, ciphertext: string}|array{ok: false, error: string}
     */
    public function encrypt(string $plaintext, int $connection_id, string $secret_name): array
    {
        if (! $this->keys->crypto_available()) {
            return ['ok' => false, 'error' => self::RESULT_UNAVAILABLE];
        }

        if (SecretKeyProvider::STATUS_READY !== $this->keys->encryption_status()) {
            $status = $this->keys->encryption_status();
            if (SecretKeyProvider::STATUS_UNAVAILABLE === $status) {
                return ['ok' => false, 'error' => self::RESULT_UNAVAILABLE];
            }
            if (SecretKeyProvider::STATUS_KEY_MISSING === $status) {
                return ['ok' => false, 'error' => self::RESULT_KEY_MISSING];
            }

            return ['ok' => false, 'error' => self::RESULT_KEY_MISSING];
        }

        $version = $this->keys->active_version();
        if (null === $version) {
            return ['ok' => false, 'error' => self::RESULT_KEY_MISSING];
        }

        $key = $this->keys->get_raw_key($version);
        if (null === $key) {
            return ['ok' => false, 'error' => self::RESULT_KEY_MISSING];
        }

        try {
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $aad   = $this->aad($connection_id, $secret_name, $version);
            $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => self::RESULT_ENCRYPTION_FAILED];
        }

        if (! is_string($cipher) || '' === $cipher) {
            return ['ok' => false, 'error' => self::RESULT_ENCRYPTION_FAILED];
        }

        return [
            'ok'          => true,
            'algorithm'   => self::ALGORITHM,
            'key_version' => $version,
            'nonce'       => base64_encode($nonce),
            'ciphertext'  => base64_encode($cipher),
        ];
    }

    /**
     * @param array{algorithm: string, key_version: int, nonce: string, ciphertext: string} $stored
     * @return array{ok: true, plaintext: string}|array{ok: false, error: string}
     */
    public function decrypt(array $stored, int $connection_id, string $secret_name): array
    {
        if (! $this->keys->crypto_available()) {
            return ['ok' => false, 'error' => self::RESULT_UNAVAILABLE];
        }

        $version = absint($stored['key_version'] ?? 0);
        if ($version < 1) {
            return ['ok' => false, 'error' => self::RESULT_DECRYPTION_FAILED];
        }

        $key = $this->keys->get_raw_key($version);
        if (null === $key) {
            return ['ok' => false, 'error' => self::RESULT_KEY_MISSING];
        }

        $nonce_b64 = (string) ($stored['nonce'] ?? '');
        $cipher_b64 = (string) ($stored['ciphertext'] ?? '');
        $nonce  = base64_decode($nonce_b64, true);
        $cipher = base64_decode($cipher_b64, true);

        if (false === $nonce || false === $cipher
            || SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES !== strlen($nonce)
        ) {
            return ['ok' => false, 'error' => self::RESULT_DECRYPTION_FAILED];
        }

        $aad = $this->aad($connection_id, $secret_name, $version);

        try {
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, $aad, $nonce, $key);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => self::RESULT_DECRYPTION_FAILED];
        }

        if (false === $plain) {
            return ['ok' => false, 'error' => self::RESULT_DECRYPTION_FAILED];
        }

        return [
            'ok'        => true,
            'plaintext' => $plain,
        ];
    }

    private function aad(int $connection_id, string $secret_name, int $key_version): string
    {
        return 'jmrs|' . $connection_id . '|' . $secret_name . '|' . $key_version;
    }
}
