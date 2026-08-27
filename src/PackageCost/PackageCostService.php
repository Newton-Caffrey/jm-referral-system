<?php

namespace JMReferral\PackageCost;

use JMReferral\Documents\ReferralDocumentController;
use JMReferral\Documents\ReferralDocumentRepository;
use JMReferral\Documents\ReferralDocumentService;
use JMReferral\Notifications\NotificationService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Pipeline\PipelineStage;
use JMReferral\Pipeline\ReferralPipelineService;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;

/**
 * Package Cost preparation and Local Authority submission.
 *
 * Prepare/upload does NOT advance the pipeline.
 * Email: JMRS sends Package Cost with private attachment, then advances.
 * Secure portal / other: record-only confirmation, then advances.
 */
class PackageCostService
{
    private const SEND_LOCK_TTL = 60;

    public function __construct(
        private ReferralRepository $referral_repository,
        private PackageCostRepository $package_cost_repository,
        private ReferralDocumentService $document_service,
        private ReferralDocumentRepository $document_repository,
        private ReferralPipelineService $pipeline_service,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider,
        private NotificationService $notification_service
    ) {
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_prepare(array $referral): bool
    {
        if (! $this->access_policy->can_manage_package_cost($referral)) {
            return false;
        }

        if (! Capabilities::current_user_can(Capabilities::UPLOAD_DOCUMENTS)) {
            return false;
        }

        if (PipelineStage::PACKAGE_COST_REQUIRED !== $this->pipeline_service->current_stage_slug($referral)) {
            return false;
        }

        $current = $this->package_cost_repository->find_current_for_referral(absint($referral['id'] ?? 0));
        if (null !== $current && PackageCost::is_sent((string) ($current['status'] ?? ''))) {
            // Already sent for this cycle; do not prepare again without a future revision workflow.
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function can_send(array $referral): bool
    {
        if (! $this->access_policy->can_manage_package_cost($referral)) {
            return false;
        }

        if (PipelineStage::PACKAGE_COST_REQUIRED !== $this->pipeline_service->current_stage_slug($referral)) {
            return false;
        }

        $current = $this->package_cost_repository->find_current_for_referral(absint($referral['id'] ?? 0));
        if (null === $current) {
            return false;
        }

        if (! PackageCost::is_prepared((string) ($current['status'] ?? ''))) {
            return false;
        }

        $document_id = absint($current['document_id'] ?? 0);

        return $document_id > 0;
    }

    /**
     * Refined next-action label from stage + package-cost milestone (not stored).
     *
     * @param array<string, mixed> $referral
     */
    public function refined_next_action(array $referral, ?array $package_cost = null): string
    {
        $slug = $this->pipeline_service->current_stage_slug($referral);
        if (PipelineStage::PACKAGE_COST_REQUIRED !== $slug) {
            return PipelineStage::next_action((string) $slug);
        }

        if (null === $package_cost) {
            $package_cost = $this->package_cost_repository->find_current_for_referral(absint($referral['id'] ?? 0));
        }

        $status = is_array($package_cost) ? (string) ($package_cost['status'] ?? '') : '';
        if (PackageCost::is_prepared($status)) {
            return __('Send package cost to Local Authority', 'jm-referral-system');
        }

        return __('Prepare package cost', 'jm-referral-system');
    }

    /**
     * Panel context for portal/admin UI.
     *
     * @param array<string, mixed> $referral
     * @return array<string, mixed>
     */
    public function get_panel_context(array $referral): array
    {
        $referral_id = absint($referral['id'] ?? 0);
        $stage = $this->pipeline_service->current_stage_slug($referral);
        $current = $this->package_cost_repository->find_current_for_referral($referral_id);
        $status = is_array($current) ? (string) ($current['status'] ?? '') : '';

        $document = null;
        $document_id = is_array($current) ? absint($current['document_id'] ?? 0) : 0;
        if ($document_id > 0) {
            $document = $this->document_repository->find($document_id);
            if (is_array($document) && Capabilities::current_user_can(Capabilities::DOWNLOAD_DOCUMENTS)) {
                $document['download_url'] = ReferralDocumentController::get_download_url($document_id);
            }
        }

        $prepared_by = is_array($current) ? absint($current['prepared_by'] ?? 0) : 0;
        $sent_by = is_array($current) ? absint($current['sent_by'] ?? 0) : 0;
        $total = is_array($current) ? ($current['package_total'] ?? null) : null;
        $currency = is_array($current) ? (string) ($current['currency'] ?? PackageCost::CURRENCY_GBP) : PackageCost::CURRENCY_GBP;

        $can_view_commercial = $this->access_policy->can_view_referral($referral)
            && (
                $this->access_policy->can_manage_package_cost($referral)
                || Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)
            );

        // Assessor may view prepared/sent summary when they can view referral; mutation gated separately.
        $show_panel = PipelineStage::PACKAGE_COST_REQUIRED === $stage
            || (PipelineStage::AWAITING_LA_DECISION === $stage && null !== $current)
            || (null !== $current && PackageCost::is_sent($status));

        $referrer_email = trim((string) ($referral['referrer_email'] ?? ''));
        $email_available = '' !== $referrer_email && is_email($referrer_email);
        $methods = PackageCost::send_method_labels();
        if (! $email_available) {
            unset($methods[PackageCost::METHOD_EMAIL]);
        }

        return [
            'show_panel'            => $show_panel && $can_view_commercial,
            'stage_slug'            => $stage,
            'can_prepare'           => $this->can_prepare($referral),
            'can_send'              => $this->can_send($referral),
            'can_edit'              => $this->can_prepare($referral) && null !== $current && PackageCost::is_prepared($status),
            'has_record'            => null !== $current,
            'status'                => $status,
            'status_label'          => '' !== $status ? PackageCost::status_label($status) : __('Not Prepared', 'jm-referral-system'),
            'is_prepared'           => PackageCost::is_prepared($status),
            'is_sent'               => PackageCost::is_sent($status),
            'package_total'         => null !== $total && '' !== (string) $total ? (string) $total : '',
            'package_total_display' => PackageCost::format_total(
                null !== $total && '' !== (string) $total ? (string) $total : null,
                $currency
            ),
            'currency'              => $currency,
            'prepared_at'           => is_array($current) ? (string) ($current['prepared_at'] ?? '') : '',
            'prepared_by_name'      => $prepared_by > 0 ? $this->user_provider->get_display_name($prepared_by) : '',
            'sent_at'               => is_array($current) ? (string) ($current['sent_at'] ?? '') : '',
            'sent_by_name'          => $sent_by > 0 ? $this->user_provider->get_display_name($sent_by) : '',
            'send_method'           => is_array($current) ? (string) ($current['send_method'] ?? '') : '',
            'send_method_label'     => is_array($current) && '' !== (string) ($current['send_method'] ?? '')
                ? PackageCost::send_method_label((string) $current['send_method'])
                : '',
            'recipient'             => is_array($current) ? (string) ($current['recipient'] ?? '') : '',
            'submission_reference'  => is_array($current) ? (string) ($current['submission_reference'] ?? '') : '',
            'email_status_label'    => (is_array($current) && PackageCost::METHOD_EMAIL === (string) ($current['send_method'] ?? '') && PackageCost::is_sent($status))
                ? __('Sent', 'jm-referral-system')
                : '',
            'document'              => $document,
            'document_name'         => is_array($document) ? (string) ($document['original_name'] ?? '') : '',
            'document_download_url' => is_array($document) ? (string) ($document['download_url'] ?? '') : '',
            'send_methods'          => $methods,
            'referrer_email'        => $referrer_email,
            'referrer_name'         => (string) ($referral['referrer_name'] ?? ''),
            'referrer_organisation' => (string) ($referral['referrer_organisation'] ?? ''),
            'referral_number'       => (string) ($referral['referral_number'] ?? ''),
            'email_available'       => $email_available,
            'email_automation'      => $email_available,
            'email_unavailable_note'=> __(
                'No valid referrer email is available. Use Secure Portal or Other, or update the referral contact details.',
                'jm-referral-system'
            ),
        ];
    }

    /**
     * Prepare or update Package Cost (does not advance pipeline).
     *
     * Sent packages are terminal. Currency is always forced to GBP.
     * Callers must pass an allowlisted payload only (package_total).
     *
     * @param array<string, mixed> $input Allowlisted keys only: package_total
     * @param array<string, mixed>|null $file $_FILES entry or null when keeping existing document
     * @return array{ok: true, id: int, unchanged?: bool}|array{ok: false, error: string, message: string, field_errors?: array<string, string>}
     */
    public function prepare(int $referral_id, array $input, ?array $file = null): array
    {
        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return $this->fail('referral_not_found', __('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->can_prepare($referral)) {
            $current_denied = $this->package_cost_repository->find_current_for_referral($referral_id);
            if (null !== $current_denied && PackageCost::is_sent((string) ($current_denied['status'] ?? ''))) {
                return $this->fail(
                    'already_sent',
                    __('This Package Cost has already been sent and cannot be overwritten.', 'jm-referral-system')
                );
            }

            return $this->fail(
                'access_denied',
                __('You do not have permission to prepare a Package Cost for this referral.', 'jm-referral-system')
            );
        }

        $current = $this->package_cost_repository->find_current_for_referral($referral_id);
        if (null !== $current && PackageCost::is_sent((string) ($current['status'] ?? ''))) {
            return $this->fail(
                'already_sent',
                __('This Package Cost has already been sent and cannot be overwritten.', 'jm-referral-system')
            );
        }

        // Explicit allowlist — ignore currency/status/actor/pipeline keys from callers.
        $total_result = $this->normalize_package_total((string) ($input['package_total'] ?? ''));
        if (! empty($total_result['error'])) {
            return [
                'ok'           => false,
                'error'        => 'validation',
                'message'      => (string) $total_result['error'],
                'field_errors' => ['package_total' => (string) $total_result['error']],
            ];
        }

        $existing_document_id = null !== $current ? absint($current['document_id'] ?? 0) : 0;
        $document_id = $existing_document_id;
        $has_new_file = is_array($file)
            && isset($file['error'])
            && (int) $file['error'] !== UPLOAD_ERR_NO_FILE
            && '' !== (string) ($file['name'] ?? '');

        if ($has_new_file) {
            $ext_error = $this->validate_package_cost_file($file);
            if (null !== $ext_error) {
                return [
                    'ok'           => false,
                    'error'        => 'validation',
                    'message'      => $ext_error,
                    'field_errors' => ['document' => $ext_error],
                ];
            }

            $upload = $this->document_service->upload($referral_id, $file);
            if (false === $upload) {
                return $this->fail('upload_failed', __('Unable to upload the Package Cost document. Please try again.', 'jm-referral-system'));
            }
            if (isset($upload['errors']) && is_array($upload['errors'])) {
                $msg = (string) reset($upload['errors']);

                return [
                    'ok'           => false,
                    'error'        => 'validation',
                    'message'      => '' !== $msg ? $msg : __('Document upload failed.', 'jm-referral-system'),
                    'field_errors' => $upload['errors'],
                ];
            }

            $document_id = absint($upload['id'] ?? 0);
            if ($document_id <= 0) {
                return $this->fail('upload_failed', __('Unable to upload the Package Cost document. Please try again.', 'jm-referral-system'));
            }
        } elseif ($document_id <= 0) {
            return [
                'ok'           => false,
                'error'        => 'validation',
                'message'      => __('A Package Cost document is required.', 'jm-referral-system'),
                'field_errors' => ['document' => __('A Package Cost document is required.', 'jm-referral-system')],
            ];
        } elseif (! $this->document_belongs_to_referral($document_id, $referral_id)) {
            return $this->fail(
                'document_mismatch',
                __('The Package Cost document could not be verified for this referral.', 'jm-referral-system')
            );
        }

        $user_id = get_current_user_id();
        $now = current_time('mysql');
        $is_update = null !== $current && PackageCost::is_prepared((string) ($current['status'] ?? ''));

        if ($is_update) {
            $existing_total = null !== ($current['package_total'] ?? null) && '' !== (string) $current['package_total']
                ? (string) $current['package_total']
                : null;
            $new_total = $total_result['value'];
            $same_total = $this->package_totals_equal($existing_total, $new_total);
            $same_document = ! $has_new_file && $document_id === $existing_document_id;

            if ($same_total && $same_document) {
                return [
                    'ok'        => true,
                    'id'        => absint($current['id'] ?? 0),
                    'unchanged' => true,
                ];
            }

            $saved = $this->package_cost_repository->update(
                absint($current['id'] ?? 0),
                [
                    'document_id'   => $document_id,
                    'package_total' => $new_total,
                    'currency'      => PackageCost::CURRENCY_GBP,
                    'status'        => PackageCost::STATUS_PREPARED,
                    'updated_at'    => $now,
                ]
            );

            if (! $saved) {
                return $this->fail('persist_failed', __('Unable to update the Package Cost. Please try again.', 'jm-referral-system'));
            }

            $this->activity_service->log_package_cost_updated($referral_id);

            return ['ok' => true, 'id' => absint($current['id'] ?? 0), 'unchanged' => false];
        }

        $id = $this->package_cost_repository->create(
            [
                'referral_id'   => $referral_id,
                'document_id'   => $document_id,
                'package_total' => $total_result['value'],
                'currency'      => PackageCost::CURRENCY_GBP,
                'prepared_at'   => $now,
                'prepared_by'   => $user_id,
                'status'        => PackageCost::STATUS_PREPARED,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]
        );

        if (false === $id) {
            return $this->fail('persist_failed', __('Unable to save the Package Cost. Please try again.', 'jm-referral-system'));
        }

        $this->activity_service->log_package_cost_prepared($referral_id);

        return ['ok' => true, 'id' => $id, 'unchanged' => false];
    }

    /**
     * Submit Package Cost (email send or manual record) and advance pipeline.
     *
     * Sent packages are terminal. Does not accept package_total, currency, status,
     * document paths, or pipeline fields from the caller.
     *
     * @param array<string, mixed> $input Allowlisted: send_method, recipient, submission_reference, confirmed
     * @return array{ok: true, method?: string}|array{ok: false, error: string, message: string}
     */
    public function record_sent(int $referral_id, array $input): array
    {
        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return $this->fail('referral_not_found', __('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->can_send($referral)) {
            $current = $this->package_cost_repository->find_current_for_referral($referral_id);
            if (null !== $current && PackageCost::is_sent((string) ($current['status'] ?? ''))) {
                return $this->fail(
                    'already_sent',
                    __('This Package Cost has already been sent.', 'jm-referral-system')
                );
            }

            return $this->fail(
                'access_denied',
                __('You cannot submit the Package Cost for this referral in its current state.', 'jm-referral-system')
            );
        }

        $current = $this->package_cost_repository->find_current_for_referral($referral_id);
        if (null === $current) {
            return $this->fail('not_prepared', __('Prepare the Package Cost before recording submission.', 'jm-referral-system'));
        }

        if (PackageCost::is_sent((string) ($current['status'] ?? ''))) {
            return $this->fail(
                'already_sent',
                __('This Package Cost has already been sent.', 'jm-referral-system')
            );
        }

        $document_id = absint($current['document_id'] ?? 0);
        if ($document_id <= 0 || ! $this->document_belongs_to_referral($document_id, $referral_id)) {
            return $this->fail(
                'attachment_missing',
                __('The Package Cost document could not be found. Please re-prepare the Package Cost.', 'jm-referral-system')
            );
        }

        $method = sanitize_key((string) ($input['send_method'] ?? ''));
        if (! PackageCost::is_valid_send_method($method)) {
            return $this->fail('invalid_method', __('Please select a valid submission method.', 'jm-referral-system'));
        }

        $safe_input = [
            'send_method'          => $method,
            'recipient'            => (string) ($input['recipient'] ?? ''),
            'submission_reference' => (string) ($input['submission_reference'] ?? ''),
            'confirmed'            => ! empty($input['confirmed']),
        ];

        if (PackageCost::METHOD_EMAIL === $method) {
            return $this->send_by_email($referral_id, $referral, $current);
        }

        return $this->record_manual_submission($referral_id, $referral, $current, $method, $safe_input);
    }

    /**
     * @param array<string, mixed> $referral
     * @param array<string, mixed> $current
     * @return array{ok: true, method?: string}|array{ok: false, error: string, message: string}
     */
    private function send_by_email(int $referral_id, array $referral, array $current): array
    {
        $to_email = trim((string) ($referral['referrer_email'] ?? ''));
        if ('' === $to_email || ! is_email($to_email)) {
            return $this->fail(
                'email_unavailable',
                __('No valid referrer email is available. Use Secure Portal or Other, or update the referral contact details.', 'jm-referral-system')
            );
        }

        $package_cost_id = absint($current['id'] ?? 0);
        $document_id = absint($current['document_id'] ?? 0);
        if ($package_cost_id <= 0 || $document_id <= 0) {
            return $this->fail(
                'attachment_missing',
                __('The Package Cost document could not be found. Please re-prepare the Package Cost.', 'jm-referral-system')
            );
        }

        if (! $this->acquire_send_lock($package_cost_id)) {
            return $this->fail(
                'in_progress',
                __('A Package Cost send is already in progress. Please wait a moment.', 'jm-referral-system')
            );
        }

        try {
            // Re-check immediately before external side effect.
            $fresh = $this->package_cost_repository->find($package_cost_id);
            if (null === $fresh
                || absint($fresh['referral_id'] ?? 0) !== $referral_id
                || ! PackageCost::is_prepared((string) ($fresh['status'] ?? ''))
            ) {
                return $this->fail(
                    'already_sent',
                    __('This Package Cost has already been sent.', 'jm-referral-system')
                );
            }

            if (PipelineStage::PACKAGE_COST_REQUIRED !== $this->pipeline_service->current_stage_slug($referral)) {
                return $this->fail(
                    'wrong_stage',
                    __('Package Cost email is only available when the pipeline stage is Package Cost to Prepare.', 'jm-referral-system')
                );
            }

            $fresh_document_id = absint($fresh['document_id'] ?? 0);
            if ($fresh_document_id <= 0 || $fresh_document_id !== $document_id) {
                return $this->fail(
                    'attachment_missing',
                    __('The Package Cost document could not be found. Please re-prepare the Package Cost.', 'jm-referral-system')
                );
            }

            $attachment = $this->document_service->resolve_attachment_path_for_referral(
                $fresh_document_id,
                $referral_id
            );
            if (null === $attachment) {
                return $this->fail(
                    'attachment_unreadable',
                    __('The Package Cost document could not be attached. The email was not sent and the referral was not advanced.', 'jm-referral-system')
                );
            }

            $mail_path = $this->prepare_mail_attachment_copy(
                (string) $attachment['path'],
                (string) $attachment['original_name']
            );
            if (null === $mail_path) {
                return $this->fail(
                    'attachment_unreadable',
                    __('The Package Cost document could not be attached. The email was not sent and the referral was not advanced.', 'jm-referral-system')
                );
            }

            $sent = false;
            try {
                $sent = $this->notification_service->notify_package_cost_sent(
                    $referral,
                    $to_email,
                    [$mail_path['path']]
                );
            } finally {
                $this->cleanup_mail_attachment_copy($mail_path);
            }

            if (! $sent) {
                $this->activity_service->log_package_cost_email_failed($referral_id);

                return $this->fail(
                    'email_failed',
                    __('Package Cost email could not be sent. The referral has not been advanced. Please try again.', 'jm-referral-system')
                );
            }

            return $this->persist_sent_and_transition(
                $referral_id,
                $fresh,
                PackageCost::METHOD_EMAIL,
                $to_email,
                null
            );
        } finally {
            $this->release_send_lock($package_cost_id);
        }
    }

    private function send_lock_option_key(int $package_cost_id): string
    {
        return 'jmrs_pc_email_lock_' . $package_cost_id;
    }

    /**
     * Atomic-ish send claim via add_option (false if already present).
     * Stale locks older than SEND_LOCK_TTL are cleared so crashed sends can retry.
     */
    private function acquire_send_lock(int $package_cost_id): bool
    {
        $key = $this->send_lock_option_key($package_cost_id);
        $now = time();
        $existing = get_option($key, false);

        if (false !== $existing) {
            if (($now - (int) $existing) < self::SEND_LOCK_TTL) {
                return false;
            }
            delete_option($key);
        }

        return (bool) add_option($key, (string) $now, '', 'no');
    }

    private function release_send_lock(int $package_cost_id): void
    {
        delete_option($this->send_lock_option_key($package_cost_id));
    }

    /**
     * @param array<string, mixed> $referral
     * @param array<string, mixed> $current
     * @param array<string, mixed> $input
     * @return array{ok: true, method?: string}|array{ok: false, error: string, message: string}
     */
    private function record_manual_submission(
        int $referral_id,
        array $referral,
        array $current,
        string $method,
        array $input
    ): array {
        $confirmed = ! empty($input['confirmed']);
        if (! $confirmed) {
            return $this->fail(
                'confirmation_required',
                __('Please confirm that the Package Cost has been submitted to the Local Authority.', 'jm-referral-system')
            );
        }

        $recipient = sanitize_text_field((string) ($input['recipient'] ?? ''));
        $recipient = substr(trim($recipient), 0, 190);
        $reference = sanitize_text_field((string) ($input['submission_reference'] ?? ''));
        $reference = substr(trim($reference), 0, 190);

        return $this->persist_sent_and_transition(
            $referral_id,
            $current,
            $method,
            '' !== $recipient ? $recipient : null,
            '' !== $reference ? $reference : null
        );
    }

    /**
     * @param array<string, mixed> $current
     * @return array{ok: true, method?: string}|array{ok: false, error: string, message: string}
     */
    private function persist_sent_and_transition(
        int $referral_id,
        array $current,
        string $method,
        ?string $recipient,
        ?string $reference
    ): array {
        $user_id = get_current_user_id();
        $now = current_time('mysql');

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        // Idempotent claim: only update while still prepared.
        $table = \JMReferral\Database\Tables::referral_package_costs_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $claimed = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                SET status = %s,
                    sent_at = %s,
                    sent_by = %d,
                    send_method = %s,
                    recipient = %s,
                    submission_reference = %s,
                    updated_at = %s
                WHERE id = %d
                  AND referral_id = %d
                  AND status = %s
                LIMIT 1",
                PackageCost::STATUS_SENT,
                $now,
                $user_id,
                $method,
                null !== $recipient ? $recipient : '',
                null !== $reference ? $reference : '',
                $now,
                absint($current['id'] ?? 0),
                $referral_id,
                PackageCost::STATUS_PREPARED
            )
        );

        if (false === $claimed || (int) $claimed < 1) {
            $wpdb->query('ROLLBACK');

            return $this->fail(
                'already_sent',
                __('This Package Cost has already been sent.', 'jm-referral-system')
            );
        }

        $transition = $this->pipeline_service->transition(
            $referral_id,
            PipelineStage::AWAITING_LA_DECISION,
            null,
            false,
            false
        );

        if (empty($transition['ok'])) {
            $wpdb->query('ROLLBACK');

            return $this->fail(
                'transition_failed',
                __('Submission could not be completed because the pipeline could not be updated. Please try again.', 'jm-referral-system')
            );
        }

        $wpdb->query('COMMIT');

        $this->activity_service->log_package_cost_sent($referral_id, $method);
        $from_label = (string) ($transition['from_label'] ?? PipelineStage::label(PipelineStage::PACKAGE_COST_REQUIRED));
        $to_label   = (string) ($transition['to_label'] ?? PipelineStage::label(PipelineStage::AWAITING_LA_DECISION));
        $this->activity_service->log_pipeline_stage_changed($referral_id, $from_label, $to_label);

        return ['ok' => true, 'method' => $method];
    }

    /**
     * Creates a short-lived temp copy so the mailer can use the original filename.
     * Private storage path is never exposed; temp file is removed after send.
     *
     * @return array{path: string, dir: string}|null
     */
    private function prepare_mail_attachment_copy(string $source_path, string $original_name): ?array
    {
        if (! is_readable($source_path)) {
            return null;
        }

        $safe_name = sanitize_file_name($original_name);
        if ('' === $safe_name) {
            $safe_name = 'package-cost.pdf';
        }

        $dir = trailingslashit(get_temp_dir()) . 'jmrs-pc-' . wp_generate_password(12, false, false);
        if (! wp_mkdir_p($dir)) {
            return null;
        }

        $dest = trailingslashit($dir) . $safe_name;
        if (! @copy($source_path, $dest)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- intentional; failure returns null.
            $this->cleanup_mail_attachment_copy(['path' => $dest, 'dir' => $dir]);

            return null;
        }

        return ['path' => $dest, 'dir' => $dir];
    }

    /**
     * @param array{path?: string, dir?: string}|null $mail_path
     */
    private function cleanup_mail_attachment_copy(?array $mail_path): void
    {
        if (null === $mail_path) {
            return;
        }

        $path = (string) ($mail_path['path'] ?? '');
        $dir = (string) ($mail_path['dir'] ?? '');
        if ('' !== $path && is_file($path)) {
            @unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
        if ('' !== $dir && is_dir($dir)) {
            @rmdir($dir); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
    }

    /**
     * Normalises optional package_total to a DECIMAL(12,2)-compatible string or null.
     * Blank → null. Zero allowed. Negatives, scientific notation, HTML, and excess precision rejected.
     * Does not use floating-point arithmetic for the stored value.
     *
     * @return array{value: string|null, error?: string}
     */
    private function normalize_package_total(string $raw): array
    {
        $raw = trim($raw);
        if ('' === $raw) {
            return ['value' => null];
        }

        // Reject markup / control characters before stripping currency decoration.
        if (preg_match('/[<>&]|[\x00-\x1F\x7F]/', $raw)) {
            return [
                'value' => null,
                'error' => __('Please enter a valid package total (e.g. 2450.00).', 'jm-referral-system'),
            ];
        }

        // Strip GBP symbol, thousands commas, and spaces (including UTF-8 £).
        $cleaned = str_replace(["\xC2\xA3", '£', ',', ' '], ['', '', '', ''], $raw);
        $cleaned = trim($cleaned);

        if ('' === $cleaned) {
            return ['value' => null];
        }

        // Reject scientific notation and any non decimal-digit form.
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $cleaned)) {
            return [
                'value' => null,
                'error' => __('Please enter a valid package total (e.g. 2450.00).', 'jm-referral-system'),
            ];
        }

        $parts     = explode('.', $cleaned, 2);
        $int_part  = ltrim($parts[0], '0');
        if ('' === $int_part) {
            $int_part = '0';
        }
        $frac_part = isset($parts[1]) ? $parts[1] : '';

        // DECIMAL(12,2): max 10 digits before the decimal point.
        if (strlen($int_part) > 10) {
            return [
                'value' => null,
                'error' => __('Package total is out of the allowed range.', 'jm-referral-system'),
            ];
        }

        if ('' === $frac_part) {
            $frac_part = '00';
        } elseif (1 === strlen($frac_part)) {
            $frac_part .= '0';
        }

        return ['value' => $int_part . '.' . $frac_part];
    }

    /**
     * Compare optional DECIMAL strings without float conversion.
     */
    private function package_totals_equal(?string $a, ?string $b): bool
    {
        if (null === $a && null === $b) {
            return true;
        }
        if (null === $a || null === $b) {
            return false;
        }

        $na = $this->normalize_package_total($a);
        $nb = $this->normalize_package_total($b);
        if (! empty($na['error']) || ! empty($nb['error'])) {
            return false;
        }

        return ($na['value'] ?? null) === ($nb['value'] ?? null);
    }

    private function document_belongs_to_referral(int $document_id, int $referral_id): bool
    {
        if ($document_id <= 0 || $referral_id <= 0) {
            return false;
        }

        $document = $this->document_repository->find($document_id);
        if (! is_array($document)) {
            return false;
        }

        return absint($document['referral_id'] ?? 0) === $referral_id;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function validate_package_cost_file(array $file): ?string
    {
        $name = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (! in_array($ext, PackageCost::DOCUMENT_EXTENSIONS, true)) {
            return __('Package Cost documents must be PDF, DOC, or DOCX.', 'jm-referral-system');
        }

        return null;
    }

    /**
     * @return array{ok: false, error: string, message: string}
     */
    private function fail(string $error, string $message): array
    {
        return [
            'ok'      => false,
            'error'   => $error,
            'message' => $message,
        ];
    }
}
