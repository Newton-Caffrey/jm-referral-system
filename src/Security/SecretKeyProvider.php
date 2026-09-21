<?php

namespace JMReferral\Security;

/**
 * Dedicated JMRS secret-key material from wp-config / environment (Phase 5C.1).
 *
 * Never falls back to WordPress salts. Keys must not live in the database.
 *
 * Expected wp-config:
 *
 * define('JMRS_SECRET_ACTIVE_KEY_VERSION', 1);
 * define('JMRS_SECRET_KEY_V1', '<base64 encoded 32-byte key>');
 */
class SecretKeyProvider
{
    public const STATUS_READY       = 'ready';
    public const STATUS_KEY_MISSING = 'key_missing';
    public const STATUS_KEY_INVALID = 'key_invalid';
    public const STATUS_UNAVAILABLE = 'unavailable';

    public const KEY_BYTES = 32;

    /**
     * Sodium availability for AEAD encryption.
     */
    public function crypto_available(): bool
    {
        return function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')
            && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')
            && function_exists('random_bytes');
    }

    /**
     * @return self::STATUS_*
     */
    public function encryption_status(): string
    {
        if (! $this->crypto_available()) {
            return self::STATUS_UNAVAILABLE;
        }

        if (! defined('JMRS_SECRET_ACTIVE_KEY_VERSION')) {
            return self::STATUS_KEY_MISSING;
        }

        $version = (int) constant('JMRS_SECRET_ACTIVE_KEY_VERSION');
        if ($version < 1) {
            return self::STATUS_KEY_INVALID;
        }

        $key = $this->get_raw_key($version);
        if (null === $key) {
            $const = $this->constant_name($version);
            if (! defined($const)) {
                return self::STATUS_KEY_MISSING;
            }

            return self::STATUS_KEY_INVALID;
        }

        return self::STATUS_READY;
    }

    public function active_version(): ?int
    {
        if (! defined('JMRS_SECRET_ACTIVE_KEY_VERSION')) {
            return null;
        }

        $version = (int) constant('JMRS_SECRET_ACTIVE_KEY_VERSION');

        return $version >= 1 ? $version : null;
    }

    /**
     * Returns exactly 32 raw key bytes, or null if unavailable/invalid.
     */
    public function get_raw_key(int $version): ?string
    {
        if ($version < 1) {
            return null;
        }

        $const = $this->constant_name($version);
        if (! defined($const)) {
            return null;
        }

        $encoded = constant($const);
        if (! is_string($encoded) || '' === trim($encoded)) {
            return null;
        }

        $decoded = base64_decode(trim($encoded), true);
        if (false === $decoded || self::KEY_BYTES !== strlen($decoded)) {
            return null;
        }

        return $decoded;
    }

    public function constant_name(int $version): string
    {
        return 'JMRS_SECRET_KEY_V' . $version;
    }
}
