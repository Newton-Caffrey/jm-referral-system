<?php

namespace JMReferral\Pipeline;

use JMReferral\Notifications\NotificationService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;

/**
 * Records JM's interest response to a Local Authority / referrer and advances the pipeline.
 *
 * Email is attempted before any DB mutation. Milestone + pipeline transition share one transaction.
 */
class InterestResponseService
{
    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralPipelineService $pipeline_service,
        private NotificationService $notification_service,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Whether Express Interest UI/action is available for this referral and user.
     *
     * @param array<string, mixed> $referral
     */
    public function can_express(array $referral): bool
    {
        if (! $this->access_policy->can_express_interest($referral)) {
            return false;
        }

        if ($this->has_interest_been_recorded($referral)) {
            return false;
        }

        return PipelineStage::INTEREST_REQUIRED === $this->pipeline_service->current_stage_slug($referral);
    }

    /**
     * @param array<string, mixed> $referral
     */
    public function has_interest_been_recorded(array $referral): bool
    {
        $expressed_at = $referral['interest_expressed_at'] ?? null;

        return null !== $expressed_at && '' !== (string) $expressed_at;
    }

    /**
     * Form context for Express Interest UI.
     *
     * @param array<string, mixed> $referral
     * @return array{
     *     can_express: bool,
     *     already_recorded: bool,
     *     stage_slug: string|null,
     *     referral_number: string,
     *     submitted_at: string,
     *     referrer_name: string,
     *     referrer_organisation: string,
     *     referrer_email: string,
     *     referrer_phone: string,
     *     email_available: bool,
     *     methods: array<string, string>,
     *     default_method: string
     * }
     */
    public function get_form_context(array $referral): array
    {
        $email = trim((string) ($referral['referrer_email'] ?? ''));
        $email_available = '' !== $email && is_email($email);
        $methods = InterestResponse::method_labels();

        if (! $email_available) {
            unset($methods[InterestResponse::METHOD_EMAIL]);
        }

        return [
            'can_express'            => $this->can_express($referral),
            'already_recorded'       => $this->has_interest_been_recorded($referral),
            'stage_slug'             => $this->pipeline_service->current_stage_slug($referral),
            'referral_number'        => (string) ($referral['referral_number'] ?? ''),
            'submitted_at'           => (string) ($referral['created_at'] ?? ''),
            'referrer_name'          => (string) ($referral['referrer_name'] ?? ''),
            'referrer_organisation'  => (string) ($referral['referrer_organisation'] ?? ''),
            'referrer_email'         => $email,
            'referrer_phone'         => (string) ($referral['referrer_phone'] ?? ''),
            'email_available'        => $email_available,
            'methods'                => $methods,
            'default_method'         => $email_available
                ? InterestResponse::METHOD_EMAIL
                : InterestResponse::METHOD_PHONE,
        ];
    }

    /**
     * Milestone display for pipeline panel.
     *
     * @param array<string, mixed> $referral
     * @return array{
     *     recorded: bool,
     *     method: string,
     *     method_label: string,
     *     expressed_at: string,
     *     expressed_by_name: string,
     *     email_status: string,
     *     email_status_label: string
     * }|null
     */
    public function get_milestone_display(array $referral, string $expressed_by_name = ''): ?array
    {
        if (! $this->has_interest_been_recorded($referral)) {
            return null;
        }

        $method = (string) ($referral['interest_response_method'] ?? '');
        $email_status = (string) ($referral['interest_email_status'] ?? '');

        return [
            'recorded'           => true,
            'method'             => $method,
            'method_label'       => InterestResponse::method_label($method),
            'expressed_at'       => (string) ($referral['interest_expressed_at'] ?? ''),
            'expressed_by_name'  => $expressed_by_name,
            'email_status'       => $email_status,
            'email_status_label' => '' !== $email_status
                ? InterestResponse::email_status_label($email_status)
                : '',
        ];
    }

    /**
     * Express interest and advance interest_required → assessment_to_schedule.
     *
     * @param array<string, mixed> $input Raw/sanitized request fields.
     * @return array{ok: true}|array{ok: false, error: string, message: string}
     */
    public function express(int $referral_id, array $input): array
    {
        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return $this->fail('referral_not_found', __('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->access_policy->can_express_interest($referral)) {
            return $this->fail('access_denied', __('You do not have permission to express interest on this referral.', 'jm-referral-system'));
        }

        if ($this->has_interest_been_recorded($referral)) {
            return $this->fail(
                'already_recorded',
                __('Interest has already been recorded for this referral.', 'jm-referral-system')
            );
        }

        $stage_slug = $this->pipeline_service->current_stage_slug($referral);
        if (PipelineStage::INTEREST_REQUIRED !== $stage_slug) {
            return $this->fail(
                'wrong_stage',
                __('Express Interest is only available when the pipeline stage is Interest Response Required.', 'jm-referral-system')
            );
        }

        $method = sanitize_key((string) ($input['method'] ?? ''));
        if (! InterestResponse::is_valid_method($method)) {
            return $this->fail('invalid_method', __('Please select a valid response method.', 'jm-referral-system'));
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return $this->fail('access_denied', __('You must be logged in to express interest.', 'jm-referral-system'));
        }

        $email = trim((string) ($referral['referrer_email'] ?? ''));
        $phone = trim((string) ($referral['referrer_phone'] ?? ''));
        $confirmed = ! empty($input['confirmed']);

        $recipient_snapshot = null;
        $email_status       = InterestResponse::EMAIL_NOT_APPLICABLE;
        $email_sent_at      = null;

        if (InterestResponse::METHOD_EMAIL === $method) {
            if ('' === $email || ! is_email($email)) {
                return $this->fail(
                    'email_unavailable',
                    __('No valid referrer email is available. Please use phone or another communication method.', 'jm-referral-system')
                );
            }

            $sent = $this->notification_service->notify_interest_expressed($referral, $email);
            if (! $sent) {
                $this->activity_service->log_interest_email_failed($referral_id);

                return $this->fail(
                    'email_failed',
                    __('The interest email could not be sent. The referral was not advanced. Please retry or use phone/other.', 'jm-referral-system')
                );
            }

            $recipient_snapshot = $email;
            $email_status       = InterestResponse::EMAIL_SENT;
            $email_sent_at      = current_time('mysql');
        } elseif (InterestResponse::METHOD_PHONE === $method) {
            if (! $confirmed) {
                return $this->fail(
                    'confirmation_required',
                    __('Please confirm that JM Healthcare’s interest has been communicated to the referrer.', 'jm-referral-system')
                );
            }
            $recipient_snapshot = '' !== $phone ? $phone : null;
        } else {
            // other
            if (! $confirmed) {
                return $this->fail(
                    'confirmation_required',
                    __('Please confirm that JM Healthcare’s interest has been communicated to the referrer.', 'jm-referral-system')
                );
            }
            $note = isset($input['other_note']) ? sanitize_text_field((string) $input['other_note']) : '';
            $note = substr(trim($note), 0, 190);
            $recipient_snapshot = '' !== $note ? $note : null;
        }

        $now = current_time('mysql');

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        $saved = $this->referral_repository->update_interest_response(
            $referral_id,
            [
                'interest_expressed_at'       => $now,
                'interest_expressed_by'       => $user_id,
                'interest_response_method'    => $method,
                'interest_response_recipient' => $recipient_snapshot,
                'interest_email_status'       => $email_status,
                'interest_email_sent_at'      => $email_sent_at,
            ]
        );

        if (! $saved) {
            $wpdb->query('ROLLBACK');

            return $this->fail(
                'persist_failed',
                __('Unable to save the interest response. Please try again.', 'jm-referral-system')
            );
        }

        // Nested TX disabled: share this transaction for stage pointer + history.
        $transition = $this->pipeline_service->transition(
            $referral_id,
            PipelineStage::ASSESSMENT_TO_SCHEDULE,
            null,
            false,
            false
        );

        if (empty($transition['ok'])) {
            $wpdb->query('ROLLBACK');

            return $this->fail(
                'transition_failed',
                __('Interest could not be completed because the pipeline could not be updated. Please try again.', 'jm-referral-system')
            );
        }

        $wpdb->query('COMMIT');

        $this->activity_service->log_interest_expressed($referral_id, $method);

        $from_label = (string) ($transition['from_label'] ?? PipelineStage::label(PipelineStage::INTEREST_REQUIRED));
        $to_label   = (string) ($transition['to_label'] ?? PipelineStage::label(PipelineStage::ASSESSMENT_TO_SCHEDULE));
        $this->activity_service->log_pipeline_stage_changed($referral_id, $from_label, $to_label);

        return ['ok' => true];
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
