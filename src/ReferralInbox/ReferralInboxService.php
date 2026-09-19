<?php

namespace JMReferral\ReferralInbox;

use JMReferral\LocalAuthority\LocalAuthorityRepository;
use JMReferral\Referral\ReferralRepository;

/**
 * Application boundary for Referral Inbox operations (Phase 5B.2).
 *
 * No UI, connectors, detection, referral creation, or attachment file I/O.
 *
 * attachment_count semantics: source-declared count from create input.
 * Metadata inserts do not rewrite attachment_count.
 *
 * Attachment idempotency: best-effort via inbox_id + provider_attachment_id.
 * No DB UNIQUE on that pair (5B.1) — race-proof attachment uniqueness is limited.
 *
 * Error recovery (error → needs_review): clears error_code and error_message.
 */
class ReferralInboxService
{
    public function __construct(
        private ReferralInboxRepository $inbox_repository,
        private ReferralInboxAttachmentRepository $attachment_repository,
        private ReferralInboxIdentity $identity,
        private LocalAuthorityRepository $authority_repository,
        private ReferralRepository $referral_repository
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $inbox_id): ?array
    {
        return $this->inbox_repository->findById($inbox_id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list_attachments(int $inbox_id): array
    {
        return $this->attachment_repository->listForInbox($inbox_id);
    }

    /**
     * Create or return existing Inbox item for a provider identity.
     *
     * External dedupe_key inputs are ignored.
     *
     * @param array<string, mixed> $input
     * @return array{result: string, item?: array<string, mixed>, errors?: array<string, string>, reason?: string}
     */
    public function create(array $input): array
    {
        unset($input['dedupe_key']);

        $identity = $this->identity->build(
            (string) ($input['source_provider'] ?? ''),
            (string) ($input['mailbox_identifier'] ?? ''),
            (string) ($input['provider_message_id'] ?? '')
        );

        if (! ($identity['ok'] ?? false)) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => $identity['errors'] ?? ['general' => __('Invalid identity.', 'jm-referral-system')],
            ];
        }

        $validated = $this->validate_create_fields($input);
        if (isset($validated['errors'])) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => $validated['errors'],
            ];
        }

        $fields = $validated['fields'];

        $existing = $this->inbox_repository->findByDedupeKey($identity['dedupe_key']);
        if (null !== $existing) {
            return [
                'result' => ReferralInboxResult::EXISTING,
                'item'   => $existing,
                'reason' => 'dedupe_key',
            ];
        }

        $now = current_time('mysql');
        $row = array_merge(
            $fields,
            [
                'source_provider'         => $identity['source_provider'],
                'mailbox_identifier'      => $identity['mailbox_identifier'],
                'provider_message_id'     => $identity['provider_message_id'],
                'dedupe_key'              => $identity['dedupe_key'],
                'status'                  => ReferralInboxStatus::NEW,
                'detection_status'        => ReferralDetectionStatus::UNCLASSIFIED,
                'detection_reason'        => null,
                'local_authority_id'      => null,
                'linked_referral_id'      => null,
                'reviewed_by'             => null,
                'reviewed_at'             => null,
                'accepted_by'             => null,
                'accepted_at'             => null,
                'ignored_by'              => null,
                'ignored_at'              => null,
                'duplicate_of_inbox_id'   => null,
                'response_started_at'     => null,
                'response_sent_at'        => null,
                'error_code'              => null,
                'error_message'           => null,
                'created_at'              => $now,
                'updated_at'              => $now,
            ]
        );

        $id = $this->inbox_repository->insert($row);

        if (false === $id) {
            if ($this->inbox_repository->is_duplicate_key_error()) {
                $race = $this->inbox_repository->findByDedupeKey($identity['dedupe_key']);
                if (null !== $race) {
                    return [
                        'result' => ReferralInboxResult::EXISTING,
                        'item'   => $race,
                        'reason' => 'dedupe_key_race',
                    ];
                }
            }

            return [
                'result' => ReferralInboxResult::PERSISTENCE_ERROR,
                'errors' => [
                    'general' => __('Unable to create Inbox item.', 'jm-referral-system'),
                ],
            ];
        }

        $item = $this->inbox_repository->findById($id);

        return [
            'result' => ReferralInboxResult::CREATED,
            'item'   => $item,
        ];
    }

    /**
     * @return array{result: string, item?: array<string, mixed>, errors?: array<string, string>, current_status?: string}
     */
    public function markNeedsReview(int $inbox_id): array
    {
        $item = $this->inbox_repository->findById($inbox_id);
        if (null === $item) {
            return ['result' => ReferralInboxResult::NOT_FOUND];
        }

        $current = (string) ($item['status'] ?? '');
        if (ReferralInboxStatus::NEEDS_REVIEW === $current) {
            return [
                'result' => ReferralInboxResult::ALREADY_APPLIED,
                'item'   => $item,
            ];
        }

        if (! ReferralInboxTransitions::is_allowed($current, ReferralInboxStatus::NEEDS_REVIEW)) {
            return [
                'result'         => ReferralInboxResult::INVALID_TRANSITION,
                'current_status' => $current,
            ];
        }

        $extra = [];
        if (ReferralInboxStatus::ERROR === $current) {
            // Clear active error state on recovery.
            $extra['error_code']    = null;
            $extra['error_message'] = null;
        }

        $affected = $this->inbox_repository->transition_status(
            $inbox_id,
            $current,
            ReferralInboxStatus::NEEDS_REVIEW,
            $extra,
            current_time('mysql')
        );

        if ($affected < 1) {
            $fresh = $this->inbox_repository->findById($inbox_id);

            return [
                'result'         => ReferralInboxResult::CONFLICT,
                'current_status' => (string) ($fresh['status'] ?? ''),
                'item'           => $fresh,
            ];
        }

        return [
            'result' => ReferralInboxResult::SUCCESS,
            'item'   => $this->inbox_repository->findById($inbox_id),
        ];
    }

    /**
     * Record-once human review metadata while status is needs_review.
     *
     * @return array{result: string, item?: array<string, mixed>, errors?: array<string, string>}
     */
    public function markReviewed(int $inbox_id, int $actor_id): array
    {
        $actor_error = $this->validate_actor($actor_id);
        if (null !== $actor_error) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => ['actor_id' => $actor_error],
            ];
        }

        $item = $this->inbox_repository->findById($inbox_id);
        if (null === $item) {
            return ['result' => ReferralInboxResult::NOT_FOUND];
        }

        if (ReferralInboxStatus::NEEDS_REVIEW !== ($item['status'] ?? '')) {
            return [
                'result'         => ReferralInboxResult::INVALID_TRANSITION,
                'current_status' => (string) ($item['status'] ?? ''),
            ];
        }

        if (! empty($item['reviewed_at'])) {
            return [
                'result' => ReferralInboxResult::ALREADY_APPLIED,
                'item'   => $item,
            ];
        }

        $now      = current_time('mysql');
        $affected = $this->inbox_repository->set_first_review($inbox_id, $actor_id, $now, $now);

        if ($affected < 1) {
            $fresh = $this->inbox_repository->findById($inbox_id);
            if (null !== $fresh && ! empty($fresh['reviewed_at'])) {
                return [
                    'result' => ReferralInboxResult::ALREADY_APPLIED,
                    'item'   => $fresh,
                ];
            }

            return ['result' => ReferralInboxResult::CONFLICT, 'item' => $fresh];
        }

        return [
            'result' => ReferralInboxResult::SUCCESS,
            'item'   => $this->inbox_repository->findById($inbox_id),
        ];
    }

    /**
     * @return array{result: string, item?: array<string, mixed>, errors?: array<string, string>, current_status?: string}
     */
    public function markIgnored(int $inbox_id, int $actor_id): array
    {
        $actor_error = $this->validate_actor($actor_id);
        if (null !== $actor_error) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => ['actor_id' => $actor_error],
            ];
        }

        $item = $this->inbox_repository->findById($inbox_id);
        if (null === $item) {
            return ['result' => ReferralInboxResult::NOT_FOUND];
        }

        $current = (string) ($item['status'] ?? '');
        if (ReferralInboxStatus::IGNORED === $current) {
            return [
                'result' => ReferralInboxResult::ALREADY_APPLIED,
                'item'   => $item,
            ];
        }

        if (! ReferralInboxTransitions::is_allowed($current, ReferralInboxStatus::IGNORED)) {
            return [
                'result'         => ReferralInboxResult::INVALID_TRANSITION,
                'current_status' => $current,
            ];
        }

        $now      = current_time('mysql');
        $affected = $this->inbox_repository->transition_status(
            $inbox_id,
            $current,
            ReferralInboxStatus::IGNORED,
            [
                'ignored_by' => $actor_id,
                'ignored_at' => $now,
            ],
            $now
        );

        if ($affected < 1) {
            $fresh = $this->inbox_repository->findById($inbox_id);

            return [
                'result'         => ReferralInboxResult::CONFLICT,
                'current_status' => (string) ($fresh['status'] ?? ''),
                'item'           => $fresh,
            ];
        }

        return [
            'result' => ReferralInboxResult::SUCCESS,
            'item'   => $this->inbox_repository->findById($inbox_id),
        ];
    }

    /**
     * @return array{result: string, item?: array<string, mixed>, errors?: array<string, string>, current_status?: string}
     */
    public function markDuplicate(int $inbox_id, int $duplicate_of_inbox_id, ?int $actor_id = null): array
    {
        if (null !== $actor_id) {
            $actor_error = $this->validate_actor($actor_id);
            if (null !== $actor_error) {
                return [
                    'result' => ReferralInboxResult::VALIDATION_ERROR,
                    'errors' => ['actor_id' => $actor_error],
                ];
            }
        }

        if ($inbox_id <= 0 || $duplicate_of_inbox_id <= 0) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => [
                    'duplicate_of_inbox_id' => __('A valid duplicate target Inbox ID is required.', 'jm-referral-system'),
                ],
            ];
        }

        if ($inbox_id === $duplicate_of_inbox_id) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => [
                    'duplicate_of_inbox_id' => __('An Inbox item cannot be marked as a duplicate of itself.', 'jm-referral-system'),
                ],
            ];
        }

        $item = $this->inbox_repository->findById($inbox_id);
        if (null === $item) {
            return ['result' => ReferralInboxResult::NOT_FOUND];
        }

        $target = $this->inbox_repository->findById($duplicate_of_inbox_id);
        if (null === $target) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => [
                    'duplicate_of_inbox_id' => __('Duplicate target Inbox item was not found.', 'jm-referral-system'),
                ],
            ];
        }

        $current = (string) ($item['status'] ?? '');
        if (ReferralInboxStatus::DUPLICATE === $current
            && (int) ($item['duplicate_of_inbox_id'] ?? 0) === $duplicate_of_inbox_id
        ) {
            return [
                'result' => ReferralInboxResult::ALREADY_APPLIED,
                'item'   => $item,
            ];
        }

        if (! ReferralInboxTransitions::is_allowed($current, ReferralInboxStatus::DUPLICATE)) {
            return [
                'result'         => ReferralInboxResult::INVALID_TRANSITION,
                'current_status' => $current,
            ];
        }

        $affected = $this->inbox_repository->transition_status(
            $inbox_id,
            $current,
            ReferralInboxStatus::DUPLICATE,
            ['duplicate_of_inbox_id' => $duplicate_of_inbox_id],
            current_time('mysql')
        );

        if ($affected < 1) {
            $fresh = $this->inbox_repository->findById($inbox_id);

            return [
                'result'         => ReferralInboxResult::CONFLICT,
                'current_status' => (string) ($fresh['status'] ?? ''),
                'item'           => $fresh,
            ];
        }

        return [
            'result' => ReferralInboxResult::SUCCESS,
            'item'   => $this->inbox_repository->findById($inbox_id),
        ];
    }

    /**
     * @return array{result: string, item?: array<string, mixed>, errors?: array<string, string>, current_status?: string}
     */
    public function markError(int $inbox_id, string $error_code, string $error_message): array
    {
        $code = $this->bound_plain_text($error_code, ReferralInboxLimits::ERROR_CODE_MAX);
        $msg  = $this->bound_plain_text($error_message, ReferralInboxLimits::ERROR_MESSAGE_MAX);

        if ('' === $code) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => ['error_code' => __('Error code is required.', 'jm-referral-system')],
            ];
        }

        if ('' === $msg) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => ['error_message' => __('Error message is required.', 'jm-referral-system')],
            ];
        }

        $item = $this->inbox_repository->findById($inbox_id);
        if (null === $item) {
            return ['result' => ReferralInboxResult::NOT_FOUND];
        }

        $current = (string) ($item['status'] ?? '');
        if (! ReferralInboxTransitions::is_allowed($current, ReferralInboxStatus::ERROR)) {
            return [
                'result'         => ReferralInboxResult::INVALID_TRANSITION,
                'current_status' => $current,
            ];
        }

        $affected = $this->inbox_repository->transition_status(
            $inbox_id,
            $current,
            ReferralInboxStatus::ERROR,
            [
                'error_code'    => $code,
                'error_message' => $msg,
            ],
            current_time('mysql')
        );

        if ($affected < 1) {
            $fresh = $this->inbox_repository->findById($inbox_id);

            return [
                'result'         => ReferralInboxResult::CONFLICT,
                'current_status' => (string) ($fresh['status'] ?? ''),
                'item'           => $fresh,
            ];
        }

        return [
            'result' => ReferralInboxResult::SUCCESS,
            'item'   => $this->inbox_repository->findById($inbox_id),
        ];
    }

    /**
     * Future-safe acceptance: requires needs_review + existing referral link.
     * Does not create referrals.
     *
     * @return array{result: string, item?: array<string, mixed>, errors?: array<string, string>, current_status?: string}
     */
    public function markAccepted(int $inbox_id, int $referral_id, int $actor_id): array
    {
        $actor_error = $this->validate_actor($actor_id);
        if (null !== $actor_error) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => ['actor_id' => $actor_error],
            ];
        }

        if ($referral_id <= 0) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => [
                    'linked_referral_id' => __('A linked referral ID is required to accept an Inbox item.', 'jm-referral-system'),
                ],
            ];
        }

        if (null === $this->referral_repository->find($referral_id)) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => [
                    'linked_referral_id' => __('Referral not found.', 'jm-referral-system'),
                ],
            ];
        }

        $item = $this->inbox_repository->findById($inbox_id);
        if (null === $item) {
            return ['result' => ReferralInboxResult::NOT_FOUND];
        }

        $current = (string) ($item['status'] ?? '');
        if (ReferralInboxStatus::ACCEPTED === $current
            && (int) ($item['linked_referral_id'] ?? 0) === $referral_id
        ) {
            return [
                'result' => ReferralInboxResult::ALREADY_APPLIED,
                'item'   => $item,
            ];
        }

        if (ReferralInboxStatus::NEEDS_REVIEW !== $current) {
            return [
                'result'         => ReferralInboxResult::INVALID_TRANSITION,
                'current_status' => $current,
                'errors'         => [
                    'status' => __('Inbox item must be in needs_review before acceptance.', 'jm-referral-system'),
                ],
            ];
        }

        $existing_link = (int) ($item['linked_referral_id'] ?? 0);
        if ($existing_link > 0 && $existing_link !== $referral_id) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => [
                    'linked_referral_id' => __('Inbox item is already linked to a different referral.', 'jm-referral-system'),
                ],
            ];
        }

        $now      = current_time('mysql');
        $affected = $this->inbox_repository->transition_status(
            $inbox_id,
            ReferralInboxStatus::NEEDS_REVIEW,
            ReferralInboxStatus::ACCEPTED,
            [
                'linked_referral_id' => $referral_id,
                'accepted_by'        => $actor_id,
                'accepted_at'        => $now,
            ],
            $now
        );

        if ($affected < 1) {
            $fresh = $this->inbox_repository->findById($inbox_id);

            return [
                'result'         => ReferralInboxResult::CONFLICT,
                'current_status' => (string) ($fresh['status'] ?? ''),
                'item'           => $fresh,
            ];
        }

        return [
            'result' => ReferralInboxResult::SUCCESS,
            'item'   => $this->inbox_repository->findById($inbox_id),
        ];
    }

    /**
     * Low-level detection metadata for future Phase 5D — no matcher/classifier.
     *
     * @return array{result: string, item?: array<string, mixed>, errors?: array<string, string>}
     */
    public function setDetectionMetadata(int $inbox_id, string $detection_status, ?string $detection_reason = null, ?int $local_authority_id = null): array
    {
        if (! ReferralDetectionStatus::is_valid($detection_status)) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => ['detection_status' => __('Invalid detection status.', 'jm-referral-system')],
            ];
        }

        $item = $this->inbox_repository->findById($inbox_id);
        if (null === $item) {
            return ['result' => ReferralInboxResult::NOT_FOUND];
        }

        $reason = null;
        if (null !== $detection_reason && '' !== trim($detection_reason)) {
            $reason = $this->bound_plain_text($detection_reason, ReferralInboxLimits::DETECTION_REASON_MAX);
        }

        if (null !== $local_authority_id) {
            if ($local_authority_id <= 0) {
                return [
                    'result' => ReferralInboxResult::VALIDATION_ERROR,
                    'errors' => ['local_authority_id' => __('Invalid Local Authority ID.', 'jm-referral-system')],
                ];
            }

            $authority = $this->authority_repository->findById($local_authority_id);
            if (null === $authority) {
                return [
                    'result' => ReferralInboxResult::VALIDATION_ERROR,
                    'errors' => ['local_authority_id' => __('Local Authority not found.', 'jm-referral-system')],
                ];
            }
        }

        $ok = $this->inbox_repository->update_fields(
            $inbox_id,
            [
                'detection_status'   => $detection_status,
                'detection_reason'   => $reason,
                'local_authority_id' => $local_authority_id,
            ],
            current_time('mysql')
        );

        if (! $ok) {
            return [
                'result' => ReferralInboxResult::PERSISTENCE_ERROR,
                'errors' => ['general' => __('Unable to update detection metadata.', 'jm-referral-system')],
            ];
        }

        return [
            'result' => ReferralInboxResult::SUCCESS,
            'item'   => $this->inbox_repository->findById($inbox_id),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{result: string, item?: array<string, mixed>, attachment?: array<string, mixed>, errors?: array<string, string>}
     */
    public function addAttachmentMetadata(int $inbox_id, array $input): array
    {
        $item = $this->inbox_repository->findById($inbox_id);
        if (null === $item) {
            return ['result' => ReferralInboxResult::NOT_FOUND];
        }

        $validated = $this->validate_attachment_fields($input);
        if (isset($validated['errors'])) {
            return [
                'result' => ReferralInboxResult::VALIDATION_ERROR,
                'errors' => $validated['errors'],
            ];
        }

        $fields = $validated['fields'];
        $provider_attachment_id = $fields['provider_attachment_id'];

        if (null !== $provider_attachment_id && '' !== $provider_attachment_id) {
            $existing = $this->attachment_repository->findByInboxAndProviderAttachmentId(
                $inbox_id,
                $provider_attachment_id
            );
            if (null !== $existing) {
                return [
                    'result'     => ReferralInboxResult::EXISTING,
                    'item'       => $item,
                    'attachment' => $existing,
                    'reason'     => 'inbox_provider_attachment_id',
                ];
            }
        }

        $now = current_time('mysql');
        $id  = $this->attachment_repository->insert(
            [
                'inbox_id'               => $inbox_id,
                'provider_attachment_id' => $provider_attachment_id,
                'filename'               => $fields['filename'],
                'mime_type'              => $fields['mime_type'],
                'size_bytes'             => $fields['size_bytes'],
                'sha256'                 => $fields['sha256'],
                'storage_status'         => $fields['storage_status'],
                'private_path'           => $fields['private_path'],
                'created_at'             => $now,
                'updated_at'             => $now,
            ]
        );

        if (false === $id) {
            return [
                'result' => ReferralInboxResult::PERSISTENCE_ERROR,
                'errors' => ['general' => __('Unable to save attachment metadata.', 'jm-referral-system')],
            ];
        }

        // Does not rewrite inbox.attachment_count (source-declared).

        return [
            'result'     => ReferralInboxResult::CREATED,
            'item'       => $item,
            'attachment' => $this->attachment_repository->findById($id),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{fields: array<string, mixed>}|array{errors: array<string, string>}
     */
    private function validate_create_fields(array $input): array
    {
        $errors = [];

        $subject = $this->bound_plain_text((string) ($input['subject'] ?? ''), ReferralInboxLimits::SUBJECT_MAX);
        $preview = $this->bound_plain_text((string) ($input['body_preview'] ?? ''), ReferralInboxLimits::BODY_PREVIEW_MAX);
        $summary = $this->bound_plain_text((string) ($input['recipient_summary'] ?? ''), ReferralInboxLimits::RECIPIENT_SUMMARY_MAX);
        $sender_name = $this->bound_plain_text((string) ($input['sender_name'] ?? ''), ReferralInboxLimits::SENDER_NAME_MAX);

        $sender_email = null;
        $sender_domain = null;
        $raw_email = trim((string) ($input['sender_email'] ?? ''));
        if ('' !== $raw_email) {
            $normalised = strtolower($raw_email);
            if (! is_email($normalised) || strlen($normalised) > ReferralInboxLimits::SENDER_EMAIL_MAX) {
                $errors['sender_email'] = __('Please provide a valid sender email.', 'jm-referral-system');
            } else {
                $sender_email  = $normalised;
                $sender_domain = $this->domain_from_email($normalised);
            }
        }

        $internet = $this->optional_opaque(
            (string) ($input['internet_message_id'] ?? ''),
            ReferralInboxLimits::INTERNET_MESSAGE_ID_MAX
        );
        $conversation = $this->optional_opaque(
            (string) ($input['conversation_identifier'] ?? ''),
            ReferralInboxLimits::CONVERSATION_IDENTIFIER_MAX
        );

        $received_at = $this->normalise_received_at($input['received_at'] ?? null);
        if (null === $received_at) {
            $errors['received_at'] = __('A valid received_at timestamp is required.', 'jm-referral-system');
        }

        $attachment_count = 0;
        if (array_key_exists('attachment_count', $input) && null !== $input['attachment_count'] && '' !== $input['attachment_count']) {
            if (! is_numeric($input['attachment_count']) || (int) $input['attachment_count'] < 0) {
                $errors['attachment_count'] = __('Attachment count must be zero or a positive integer.', 'jm-referral-system');
            } else {
                $attachment_count = (int) $input['attachment_count'];
            }
        }

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        return [
            'fields' => [
                'internet_message_id'     => $internet,
                'conversation_identifier' => $conversation,
                'sender_name'             => '' !== $sender_name ? $sender_name : null,
                'sender_email'            => $sender_email,
                'sender_domain'           => $sender_domain,
                'recipient_summary'       => '' !== $summary ? $summary : null,
                'subject'                 => '' !== $subject ? $subject : null,
                'body_preview'            => '' !== $preview ? $preview : null,
                'received_at'             => $received_at,
                'attachment_count'        => $attachment_count,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{fields: array<string, mixed>}|array{errors: array<string, string>}
     */
    private function validate_attachment_fields(array $input): array
    {
        $errors = [];

        $provider_attachment_id = $this->optional_opaque(
            (string) ($input['provider_attachment_id'] ?? ''),
            ReferralInboxLimits::PROVIDER_ATTACHMENT_ID_MAX
        );

        $filename = $this->bound_plain_text((string) ($input['filename'] ?? ''), ReferralInboxLimits::FILENAME_MAX);
        $mime     = $this->bound_plain_text((string) ($input['mime_type'] ?? ''), ReferralInboxLimits::MIME_TYPE_MAX);

        $size_bytes = null;
        if (array_key_exists('size_bytes', $input) && null !== $input['size_bytes'] && '' !== $input['size_bytes']) {
            if (! is_numeric($input['size_bytes']) || (int) $input['size_bytes'] < 0) {
                $errors['size_bytes'] = __('Size must be zero or a positive integer.', 'jm-referral-system');
            } else {
                $size_bytes = (int) $input['size_bytes'];
            }
        }

        $sha256 = null;
        $raw_hash = strtolower(trim((string) ($input['sha256'] ?? '')));
        if ('' !== $raw_hash) {
            if (! preg_match('/^[a-f0-9]{64}$/', $raw_hash)) {
                $errors['sha256'] = __('SHA-256 must be a 64-character lowercase hex string.', 'jm-referral-system');
            } else {
                $sha256 = $raw_hash;
            }
        }

        $storage_status = (string) ($input['storage_status'] ?? InboxAttachmentStatus::METADATA_ONLY);
        if (! InboxAttachmentStatus::is_valid($storage_status)) {
            $errors['storage_status'] = __('Invalid attachment storage status.', 'jm-referral-system');
        }

        $private_path = null;
        $raw_path = trim((string) ($input['private_path'] ?? ''));
        if ('' !== $raw_path) {
            if (InboxAttachmentStatus::METADATA_ONLY === $storage_status) {
                $errors['private_path'] = __('private_path is not allowed for metadata_only attachments.', 'jm-referral-system');
            } else {
                $private_path = $this->bound_plain_text($raw_path, ReferralInboxLimits::PRIVATE_PATH_MAX);
                if ('' === $private_path) {
                    $errors['private_path'] = __('Invalid private path.', 'jm-referral-system');
                }
            }
        }

        // Phase 5B.2 normal creation uses metadata_only with no files.
        if (InboxAttachmentStatus::METADATA_ONLY !== $storage_status && empty($errors['storage_status'])) {
            // Allow status values for future phases but reject non-metadata_only in this phase create path.
            $errors['storage_status'] = __('Only metadata_only attachments may be created in this phase.', 'jm-referral-system');
        }

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        return [
            'fields' => [
                'provider_attachment_id' => $provider_attachment_id,
                'filename'               => '' !== $filename ? $filename : null,
                'mime_type'              => '' !== $mime ? $mime : null,
                'size_bytes'             => $size_bytes,
                'sha256'                 => $sha256,
                'storage_status'         => InboxAttachmentStatus::METADATA_ONLY,
                'private_path'           => null,
            ],
        ];
    }

    private function validate_actor(int $actor_id): ?string
    {
        if ($actor_id <= 0) {
            return __('A valid actor user ID is required.', 'jm-referral-system');
        }

        $user = get_userdata($actor_id);
        if (! $user instanceof \WP_User) {
            return __('Actor user was not found.', 'jm-referral-system');
        }

        return null;
    }

    private function normalise_received_at(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return current_time('mysql');
        }

        if (is_array($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw)) {
            return $raw;
        }

        $ts = strtotime($raw);
        if (false === $ts) {
            return null;
        }

        return wp_date('Y-m-d H:i:s', $ts);
    }

    private function domain_from_email(string $email): ?string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return null;
        }

        $host = strtolower(trim($parts[1]));
        if ('' === $host || strlen($host) > ReferralInboxLimits::SENDER_DOMAIN_MAX) {
            return null;
        }

        return $host;
    }

    private function bound_plain_text(string $value, int $max): string
    {
        $value = wp_strip_all_tags($value);
        $value = trim(preg_replace("/[\r\n]+/", ' ', $value) ?? $value);

        if (strlen($value) > $max) {
            $value = substr($value, 0, $max);
        }

        return $value;
    }

    private function optional_opaque(string $value, int $max): ?string
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        if (strlen($value) > $max) {
            $value = substr($value, 0, $max);
        }

        return $value;
    }
}
