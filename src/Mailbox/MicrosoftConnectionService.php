<?php

namespace JMReferral\Mailbox;

use JMReferral\Security\SecretCipher;
use JMReferral\Security\SecretKeyProvider;

/**
 * Microsoft 365 mailbox connection application service (Phase 5C.1).
 *
 * Persists configuration and encrypted credentials only.
 * Does not call Microsoft Graph or acquire tokens.
 */
class MicrosoftConnectionService
{
    public const ERROR_VALIDATION             = 'validation_error';
    public const ERROR_ENCRYPTION_UNAVAILABLE = 'encryption_unavailable';
    public const ERROR_KEY_MISSING            = 'encryption_key_missing';
    public const ERROR_KEY_INVALID            = 'encryption_key_invalid';
    public const ERROR_ONE_ACTIVE             = 'one_active_microsoft_connection';
    public const ERROR_NOT_FOUND              = 'connection_not_found';
    public const ERROR_PERSISTENCE            = 'persistence_error';
    public const ERROR_SECRET_REQUIRED        = 'client_secret_required';
    public const ERROR_INCOMPLETE             = 'configuration_incomplete';

    public function __construct(
        private MailboxConnectionRepository $connections,
        private MailboxConnectionSecretService $secrets,
        private SecretKeyProvider $keys,
        private SecretCipher $cipher
    ) {
    }

    /**
     * Safe view for admin templates — never includes plaintext secrets.
     *
     * @return array{
     *   exists: bool,
     *   computed_status: string,
     *   connection: array<string, mixed>|null,
     *   has_client_secret: bool,
     *   encryption: array{status: string, message: string},
     *   can_enable: bool
     * }
     */
    public function get_safe_view(): array
    {
        $connection = $this->connections->find_latest_microsoft();
        $encryption = $this->encryption_status_payload();
        $encReady   = SecretKeyProvider::STATUS_READY === $this->keys->encryption_status();

        if (null === $connection) {
            return [
                'exists'            => false,
                'computed_status'   => MailboxConnectionStatus::NOT_CONFIGURED,
                'connection'        => null,
                'has_client_secret' => false,
                'encryption'        => $encryption,
                'can_enable'        => false,
            ];
        }

        $id        = (int) $connection['id'];
        $hasSecret = $this->secrets->has_secret($id, MailboxConnectionConstants::SECRET_NAME_CLIENT_SECRET);
        $status    = (string) ($connection['status'] ?? '');
        $enabled   = (int) ($connection['is_enabled'] ?? 0) === 1;

        return [
            'exists'            => true,
            'computed_status'   => $status !== '' ? $status : MailboxConnectionStatus::CONFIGURED,
            'connection'        => [
                'id'                 => $id,
                'provider'           => (string) $connection['provider'],
                'auth_mode'          => (string) $connection['auth_mode'],
                'credential_type'    => (string) $connection['credential_type'],
                'tenant_id'          => (string) $connection['tenant_id'],
                'client_id'          => (string) $connection['client_id'],
                'mailbox_address'    => (string) $connection['mailbox_address'],
                'mailbox_type'       => (string) $connection['mailbox_type'],
                'mailbox_identifier' => (string) ($connection['mailbox_identifier'] ?? ''),
                'status'             => $status,
                'is_enabled'         => $enabled,
                'last_verified_at'   => $connection['last_verified_at'],
                'last_error_code'    => $connection['last_error_code'],
                'last_error_at'      => $connection['last_error_at'],
                'created_at'         => $connection['created_at'],
                'updated_at'         => $connection['updated_at'],
            ],
            'has_client_secret' => $hasSecret,
            'encryption'        => $encryption,
            'can_enable'        => ! $enabled
                && $hasSecret
                && $this->is_config_complete($connection)
                && $encReady,
        ];
    }

    /**
     * Create or update the single Microsoft connection for this installation.
     *
     * @param array{
     *   tenant_id?: string,
     *   client_id?: string,
     *   mailbox_address?: string,
     *   mailbox_type?: string,
     *   client_secret?: string
     * } $input
     * @return array{ok: true, connection_id: int}|array{ok: false, error: string, messages: list<string>}
     */
    public function save_microsoft_connection(array $input): array
    {
        $validated = $this->validate_microsoft_input($input);
        if (! ($validated['ok'] ?? false)) {
            return [
                'ok'       => false,
                'error'    => self::ERROR_VALIDATION,
                'messages' => $validated['messages'] ?? ['Invalid configuration.'],
            ];
        }

        $data      = $validated['data'];
        $newSecret = trim((string) ($input['client_secret'] ?? ''));
        $existing  = $this->connections->find_latest_microsoft();
        $encStatus = $this->keys->encryption_status();

        if (null === $existing) {
            if ('' === $newSecret) {
                return [
                    'ok'       => false,
                    'error'    => self::ERROR_SECRET_REQUIRED,
                    'messages' => ['Client secret is required when creating a Microsoft 365 connection.'],
                ];
            }

            $encBlock = $this->encryption_gate($encStatus);
            if (null !== $encBlock) {
                return $encBlock;
            }

            if ($this->connections->count_enabled_microsoft() > 0) {
                return [
                    'ok'       => false,
                    'error'    => self::ERROR_ONE_ACTIVE,
                    'messages' => ['Only one active Microsoft 365 mailbox connection is allowed per installation.'],
                ];
            }

            $now = current_time('mysql');
            $id  = $this->connections->insert(
                [
                    'provider'                     => MailboxConnectionConstants::PROVIDER_MICROSOFT_GRAPH,
                    'auth_mode'                    => MailboxConnectionConstants::AUTH_APPLICATION,
                    'credential_type'              => MailboxConnectionConstants::CREDENTIAL_CLIENT_SECRET,
                    'tenant_id'                    => $data['tenant_id'],
                    'client_id'                    => $data['client_id'],
                    'mailbox_identifier'           => $data['mailbox_address'],
                    'mailbox_address'              => $data['mailbox_address'],
                    'mailbox_type'                 => $data['mailbox_type'],
                    'status'                       => MailboxConnectionStatus::CONFIGURED,
                    'is_enabled'                   => 1,
                    'last_verified_at'             => null,
                    'last_sync_at'                 => null,
                    'last_successful_ingestion_at' => null,
                    'last_error_code'              => null,
                    'last_error_at'                => null,
                    'created_at'                   => $now,
                    'updated_at'                   => $now,
                ]
            );

            if (false === $id || $id <= 0) {
                return [
                    'ok'       => false,
                    'error'    => self::ERROR_PERSISTENCE,
                    'messages' => ['Could not save the Microsoft 365 connection.'],
                ];
            }

            $stored = $this->secrets->store_secret(
                $id,
                MailboxConnectionConstants::SECRET_NAME_CLIENT_SECRET,
                $newSecret
            );

            if (! ($stored['ok'] ?? false)) {
                $this->connections->delete($id);

                return $this->secret_store_failure_result((string) ($stored['error'] ?? ''));
            }

            return ['ok' => true, 'connection_id' => $id];
        }

        $connectionId = (int) $existing['id'];
        $hasSecret    = $this->secrets->has_secret(
            $connectionId,
            MailboxConnectionConstants::SECRET_NAME_CLIENT_SECRET
        );

        if ('' !== $newSecret) {
            $encBlock = $this->encryption_gate($encStatus);
            if (null !== $encBlock) {
                return $encBlock;
            }
        } elseif (! $hasSecret) {
            return [
                'ok'       => false,
                'error'    => self::ERROR_SECRET_REQUIRED,
                'messages' => ['Client secret is required. No secret is currently stored for this connection.'],
            ];
        }

        $now     = current_time('mysql');
        $enabled = (int) ($existing['is_enabled'] ?? 0) === 1;
        $status  = (string) ($existing['status'] ?? MailboxConnectionStatus::CONFIGURED);

        // 5C.1: enabled configuration remains "configured" (not "connected").
        if ($enabled && MailboxConnectionStatus::DISABLED !== $status) {
            $status = MailboxConnectionStatus::CONFIGURED;
        }

        $updated = $this->connections->update(
            $connectionId,
            [
                'tenant_id'          => $data['tenant_id'],
                'client_id'          => $data['client_id'],
                'mailbox_identifier' => $data['mailbox_address'],
                'mailbox_address'    => $data['mailbox_address'],
                'mailbox_type'       => $data['mailbox_type'],
                'provider'           => MailboxConnectionConstants::PROVIDER_MICROSOFT_GRAPH,
                'auth_mode'          => MailboxConnectionConstants::AUTH_APPLICATION,
                'credential_type'    => MailboxConnectionConstants::CREDENTIAL_CLIENT_SECRET,
                'status'             => $status,
                'updated_at'         => $now,
            ]
        );

        if (! $updated) {
            return [
                'ok'       => false,
                'error'    => self::ERROR_PERSISTENCE,
                'messages' => ['Could not update the Microsoft 365 connection.'],
            ];
        }

        if ('' !== $newSecret) {
            $stored = $this->secrets->store_secret(
                $connectionId,
                MailboxConnectionConstants::SECRET_NAME_CLIENT_SECRET,
                $newSecret
            );
            if (! ($stored['ok'] ?? false)) {
                return $this->secret_store_failure_result((string) ($stored['error'] ?? ''));
            }
        }

        return ['ok' => true, 'connection_id' => $connectionId];
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string, messages: list<string>}
     */
    public function disable(): array
    {
        $connection = $this->connections->find_latest_microsoft();
        if (null === $connection) {
            return [
                'ok'       => false,
                'error'    => self::ERROR_NOT_FOUND,
                'messages' => ['No Microsoft 365 connection is configured.'],
            ];
        }

        $ok = $this->connections->update(
            (int) $connection['id'],
            [
                'is_enabled' => 0,
                'status'     => MailboxConnectionStatus::DISABLED,
                'updated_at' => current_time('mysql'),
            ]
        );

        return $ok
            ? ['ok' => true]
            : [
                'ok'       => false,
                'error'    => self::ERROR_PERSISTENCE,
                'messages' => ['Could not disable the Microsoft 365 connection.'],
            ];
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string, messages: list<string>}
     */
    public function enable(): array
    {
        $connection = $this->connections->find_latest_microsoft();
        if (null === $connection) {
            return [
                'ok'       => false,
                'error'    => self::ERROR_NOT_FOUND,
                'messages' => ['No Microsoft 365 connection is configured.'],
            ];
        }

        $id = (int) $connection['id'];

        if (! $this->is_config_complete($connection)) {
            return [
                'ok'       => false,
                'error'    => self::ERROR_INCOMPLETE,
                'messages' => ['Microsoft 365 configuration is incomplete and cannot be enabled.'],
            ];
        }

        if (! $this->secrets->has_secret($id, MailboxConnectionConstants::SECRET_NAME_CLIENT_SECRET)) {
            return [
                'ok'       => false,
                'error'    => self::ERROR_SECRET_REQUIRED,
                'messages' => ['A client secret must be stored before enabling the integration.'],
            ];
        }

        if ($this->connections->count_enabled_microsoft($id) > 0) {
            return [
                'ok'       => false,
                'error'    => self::ERROR_ONE_ACTIVE,
                'messages' => ['Only one active Microsoft 365 mailbox connection is allowed per installation.'],
            ];
        }

        $ok = $this->connections->update(
            $id,
            [
                'is_enabled' => 1,
                'status'     => MailboxConnectionStatus::CONFIGURED,
                'updated_at' => current_time('mysql'),
            ]
        );

        return $ok
            ? ['ok' => true]
            : [
                'ok'       => false,
                'error'    => self::ERROR_PERSISTENCE,
                'messages' => ['Could not enable the Microsoft 365 connection.'],
            ];
    }

    /**
     * Destructive removal of connection row and encrypted secrets.
     *
     * @return array{ok: true}|array{ok: false, error: string, messages: list<string>}
     */
    public function remove(): array
    {
        $connection = $this->connections->find_latest_microsoft();
        if (null === $connection) {
            return [
                'ok'       => false,
                'error'    => self::ERROR_NOT_FOUND,
                'messages' => ['No Microsoft 365 connection is configured.'],
            ];
        }

        $id = (int) $connection['id'];
        $this->secrets->delete_all_for_connection($id);
        $ok = $this->connections->delete($id);

        return $ok
            ? ['ok' => true]
            : [
                'ok'       => false,
                'error'    => self::ERROR_PERSISTENCE,
                'messages' => ['Could not remove the Microsoft 365 connection.'],
            ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array{tenant_id: string, client_id: string, mailbox_address: string, mailbox_type: string}}|array{ok: false, messages: list<string>}
     */
    private function validate_microsoft_input(array $input): array
    {
        $messages = [];

        $tenantId = strtolower(trim((string) ($input['tenant_id'] ?? '')));
        $clientId = strtolower(trim((string) ($input['client_id'] ?? '')));
        $mailbox  = strtolower(trim((string) ($input['mailbox_address'] ?? '')));
        $type     = strtolower(trim((string) ($input['mailbox_type'] ?? '')));

        if (! $this->is_guid($tenantId)) {
            $messages[] = 'Tenant ID must be a valid GUID.';
        }

        if (! $this->is_guid($clientId)) {
            $messages[] = 'Application / Client ID must be a valid GUID.';
        }

        if ('' === $mailbox || ! is_email($mailbox)) {
            $messages[] = 'Referral mailbox address must be a valid email address.';
        }

        if (! in_array($type, MailboxConnectionConstants::mailbox_types(), true)) {
            $messages[] = 'Mailbox type must be Shared mailbox or User mailbox.';
        }

        if ([] !== $messages) {
            return ['ok' => false, 'messages' => $messages];
        }

        return [
            'ok'   => true,
            'data' => [
                'tenant_id'       => $tenantId,
                'client_id'       => $clientId,
                'mailbox_address' => $mailbox,
                'mailbox_type'    => $type,
            ],
        ];
    }

    private function is_guid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $value
        );
    }

    /**
     * @param array<string, mixed> $connection
     */
    private function is_config_complete(array $connection): bool
    {
        return $this->is_guid((string) ($connection['tenant_id'] ?? ''))
            && $this->is_guid((string) ($connection['client_id'] ?? ''))
            && is_email((string) ($connection['mailbox_address'] ?? ''))
            && in_array((string) ($connection['mailbox_type'] ?? ''), MailboxConnectionConstants::mailbox_types(), true);
    }

    /**
     * @return array{status: string, message: string}
     */
    private function encryption_status_payload(): array
    {
        $status = $this->keys->encryption_status();

        return match ($status) {
            SecretKeyProvider::STATUS_READY => [
                'status'  => 'ready',
                'message' => 'Encrypted credential storage ready',
            ],
            SecretKeyProvider::STATUS_UNAVAILABLE => [
                'status'  => 'unavailable',
                'message' => 'Secure credential encryption is unavailable on this server.',
            ],
            SecretKeyProvider::STATUS_KEY_MISSING => [
                'status'  => 'missing',
                'message' => 'Encryption key not configured',
            ],
            default => [
                'status'  => 'invalid',
                'message' => 'Encryption key configuration invalid',
            ],
        };
    }

    /**
     * @return array{ok: false, error: string, messages: list<string>}|null
     */
    private function encryption_gate(string $encStatus): ?array
    {
        if (SecretKeyProvider::STATUS_READY === $encStatus) {
            return null;
        }

        if (SecretKeyProvider::STATUS_UNAVAILABLE === $encStatus) {
            return [
                'ok'       => false,
                'error'    => self::ERROR_ENCRYPTION_UNAVAILABLE,
                'messages' => ['Secure credential encryption is unavailable on this server.'],
            ];
        }

        if (SecretKeyProvider::STATUS_KEY_MISSING === $encStatus) {
            return [
                'ok'       => false,
                'error'    => self::ERROR_KEY_MISSING,
                'messages' => ['Encryption key not configured. Configure JMRS secret keys in wp-config.php before saving a client secret.'],
            ];
        }

        return [
            'ok'       => false,
            'error'    => self::ERROR_KEY_INVALID,
            'messages' => ['Encryption key configuration invalid. Correct the JMRS secret key constants before saving a client secret.'],
        ];
    }

    /**
     * @return array{ok: false, error: string, messages: list<string>}
     */
    private function secret_store_failure_result(string $code): array
    {
        return match ($code) {
            SecretCipher::RESULT_UNAVAILABLE => [
                'ok'       => false,
                'error'    => self::ERROR_ENCRYPTION_UNAVAILABLE,
                'messages' => ['Secure credential encryption is unavailable on this server.'],
            ],
            SecretCipher::RESULT_KEY_MISSING => [
                'ok'       => false,
                'error'    => self::ERROR_KEY_MISSING,
                'messages' => ['Encryption key not configured'],
            ],
            default => [
                'ok'       => false,
                'error'    => self::ERROR_PERSISTENCE,
                'messages' => ['Could not store the encrypted client secret.'],
            ],
        };
    }
}
