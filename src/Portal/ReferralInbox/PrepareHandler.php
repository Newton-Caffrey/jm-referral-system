<?php

namespace JMReferral\Portal\ReferralInbox;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\Clinical\PortalViewHost;
use JMReferral\Portal\PortalRouter;
use JMReferral\Portal\PortalUrls;
use JMReferral\ReferralInbox\LocalAuthorityOrigin;
use JMReferral\ReferralInbox\ReferralDetectionStatus;
use JMReferral\ReferralInbox\ReferralInboxDetectionExplanation;
use JMReferral\ReferralInbox\ReferralInboxConversionResult;
use JMReferral\ReferralInbox\ReferralInboxConversionService;
use JMReferral\ReferralInbox\ReferralInboxPreparationService;
use JMReferral\ReferralInbox\ReferralInboxStatus;

/**
 * Staff Portal referral preparation screen (Phase 5D.4).
 *
 * GET is read-only. POST validates a draft or creates one referral.
 */
class PrepareHandler
{
    private const NONCE_ACTION_PREFIX = 'jmrs_inbox_prepare_';

    private const NONCE_FIELD = 'jmrs_prepare_nonce';

    public function __construct(
        private PortalViewHost $view_host,
        private AccessPolicy $access_policy,
        private ReferralInboxPreparationService $preparation,
        private ReferralInboxConversionService $conversion
    ) {
    }

    public function dispatch(): void
    {
        if (! is_user_logged_in() || ! $this->access_policy->can_prepare_referral_from_inbox()) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $inbox_id = absint(get_query_var(PortalRouter::QV_ID));
        if ($inbox_id <= 0) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $can_assign = current_user_can(Capabilities::ASSIGN_REFERRALS);
        $notice     = '';

        $offer_create = false;

        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
            $posted_action = isset($_POST['jmrs_prepare_action'])
                ? sanitize_key(wp_unslash((string) $_POST['jmrs_prepare_action']))
                : '';
            if (! in_array($posted_action, ['validate', 'create_referral'], true)) {
                $notice = __('The request could not be processed.', 'jm-referral-system');
                $payload = $this->preparation->present($inbox_id);
            } else {
                $guard = $this->guard_post($inbox_id);
                if (null !== $guard) {
                    $notice  = $guard;
                    $payload = $this->preparation->present($inbox_id);
                } elseif ('create_referral' === $posted_action) {
                    $handled = $this->handle_create($inbox_id, $can_assign);
                    if (null === $handled) {
                        return;
                    }
                    $payload      = $handled['payload'];
                    $notice       = $handled['notice'];
                    $offer_create = $handled['offer_create'];
                } else {
                    $payload = $this->preparation->validateDraft($inbox_id, $_POST, $can_assign);
                    $offer_create = ReferralInboxPreparationService::VALID === (string) ($payload['result'] ?? '');
                }
            }
        } else {
            $payload = $this->preparation->present($inbox_id);
        }

        $this->render($inbox_id, $payload, $can_assign, $notice, $offer_create);
    }

    /**
     * @return array{payload: array<string, mixed>, notice: string, offer_create: bool}|null
     */
    private function handle_create(int $inbox_id, bool $can_assign): ?array
    {
        $draft  = $this->preparation->validateDraft($inbox_id, $_POST, $can_assign);
        $result = (string) ($draft['result'] ?? '');

        if (ReferralInboxPreparationService::NOT_FOUND === $result) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return null;
        }

        if (ReferralInboxPreparationService::INVALID_STATE === $result) {
            $existing = $this->conversion->existing($inbox_id);
            if ($existing instanceof ReferralInboxConversionResult && $existing->redirects()) {
                $this->redirect_detail($inbox_id, $existing);

                return null;
            }
            $notice = $existing instanceof ReferralInboxConversionResult
                && in_array($existing->outcome(), [
                    ReferralInboxConversionResult::INCONSISTENT_LINK,
                    ReferralInboxConversionResult::INCONSISTENT_STATE,
                ], true)
                ? $this->outcome_message($existing)
                : '';

            return [
                'payload'      => $draft,
                'notice'       => $notice,
                'offer_create' => false,
            ];
        }

        if (ReferralInboxPreparationService::VALIDATION_ERROR === $result) {
            return [
                'payload'      => $draft,
                'notice'       => '',
                'offer_create' => false,
            ];
        }

        if (! $this->confirmed()) {
            $errors = is_array($draft['errors'] ?? null) ? $draft['errors'] : [];
            $errors['confirmation'] = __('Please confirm that you have reviewed the referral details.', 'jm-referral-system');
            $draft['errors'] = $errors;

            return [
                'payload'      => $draft,
                'notice'       => '',
                'offer_create' => true,
            ];
        }

        $outcome = $this->conversion->commit(
            $inbox_id,
            is_array($draft['values'] ?? null) ? $draft['values'] : [],
            $can_assign,
            get_current_user_id(),
            true
        );

        if ($outcome->redirects()) {
            $this->redirect_detail($inbox_id, $outcome);

            return null;
        }

        if (ReferralInboxConversionResult::VALIDATION_ERROR === $outcome->outcome()) {
            $draft['result'] = ReferralInboxPreparationService::VALIDATION_ERROR;
            $draft['errors'] = $outcome->errors();

            return [
                'payload'      => $draft,
                'notice'       => '',
                'offer_create' => false,
            ];
        }

        if (ReferralInboxConversionResult::ALREADY_CONVERTED === $outcome->outcome()) {
            $this->redirect_detail($inbox_id, $outcome);

            return null;
        }

        return [
            'payload'      => $draft,
            'notice'       => $this->outcome_message($outcome),
            'offer_create' => ReferralInboxConversionResult::LOCK_TIMEOUT === $outcome->outcome(),
        ];
    }

    private function confirmed(): bool
    {
        if (! isset($_POST['jmrs_prepare_confirm']) || ! is_scalar($_POST['jmrs_prepare_confirm'])) {
            return false;
        }

        return '1' === sanitize_text_field(wp_unslash((string) $_POST['jmrs_prepare_confirm']));
    }

    private function redirect_detail(int $inbox_id, ReferralInboxConversionResult $result): void
    {
        $created = ReferralInboxConversionResult::SUCCESS === $result->outcome();
        $warning = $result->has_warning(ReferralInboxConversionResult::WARNING_ASSIGNMENT_EMAIL);
        $document_warning = $result->has_warning(ReferralInboxConversionResult::WARNING_DOCUMENT_ATTACH);
        $number  = $result->referral_number();
        if ('' === $number) {
            $number = '#' . $result->referral_id();
        }

        if ($created) {
            $message = sprintf(
                /* translators: %s: referral number */
                __('Referral %s was created and linked to this opportunity.', 'jm-referral-system'),
                $number
            );
            if ($warning) {
                $message .= ' ' . __('Referral created successfully, but the assignment email could not be sent.', 'jm-referral-system');
            }
            if ($document_warning) {
                $message .= ' ' . __('The uploaded form could not be added to the referral\'s documents. Upload it again from the referral.', 'jm-referral-system');
            }
            $type = ($warning || $document_warning) ? 'warning' : 'success';
        } else {
            $message = sprintf(
                /* translators: %s: referral number */
                __('This opportunity has already been converted to referral %s.', 'jm-referral-system'),
                $number
            );
            $type = 'info';
        }

        $args = [
            'jmrs_inbox_notice' => sanitize_key($type),
            'jmrs_inbox_msg'    => rawurlencode($message),
        ];
        wp_safe_redirect(add_query_arg($args, PortalUrls::referral_inbox_item($inbox_id)));
        exit;
    }

    private function outcome_message(ReferralInboxConversionResult $result): string
    {
        return match ($result->outcome()) {
            ReferralInboxConversionResult::TRANSACTION_UNAVAILABLE => __(
                'Referral conversion is unavailable because the database does not support the required atomic transaction.',
                'jm-referral-system'
            ),
            ReferralInboxConversionResult::LOCK_TIMEOUT => __(
                'Another referral is currently being created. Please try again.',
                'jm-referral-system'
            ),
            ReferralInboxConversionResult::INCONSISTENT_LINK => __(
                'This opportunity already has a linked referral and was not changed.',
                'jm-referral-system'
            ),
            ReferralInboxConversionResult::INCONSISTENT_STATE => __(
                'This opportunity is accepted without a linked referral. It was not changed.',
                'jm-referral-system'
            ),
            ReferralInboxConversionResult::INVALID_STATE => __(
                'This opportunity has already changed. Refresh the page to see its current status.',
                'jm-referral-system'
            ),
            default => __(
                'The referral could not be created. No changes were saved.',
                'jm-referral-system'
            ),
        };
    }

    private function guard_post(int $inbox_id): ?string
    {
        $nonce = isset($_POST[self::NONCE_FIELD]) && is_scalar($_POST[self::NONCE_FIELD])
            ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_FIELD]))
            : '';
        if (! wp_verify_nonce($nonce, self::NONCE_ACTION_PREFIX . $inbox_id)) {
            return __('Security check failed. Please try again.', 'jm-referral-system');
        }

        if (isset($_POST['jmrs_prepare_inbox_id'])) {
            if (! is_scalar($_POST['jmrs_prepare_inbox_id'])) {
                return __('The request could not be processed.', 'jm-referral-system');
            }
            $posted_id = absint(sanitize_text_field(wp_unslash((string) $_POST['jmrs_prepare_inbox_id'])));
            if ($posted_id !== $inbox_id) {
                return __('The request did not match this Referral Inbox item.', 'jm-referral-system');
            }
        }

        foreach ($this->posted_fields() as $field) {
            if (isset($_POST[$field]) && ! is_scalar($_POST[$field])) {
                return __('The request could not be processed.', 'jm-referral-system');
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function posted_fields(): array
    {
        return [
            'jmrs_prepare_client_name',
            'jmrs_prepare_client_email',
            'jmrs_prepare_client_phone',
            'jmrs_prepare_referrer_name',
            'jmrs_prepare_referrer_email',
            'jmrs_prepare_referrer_organisation',
            'jmrs_prepare_service_type_id',
            'jmrs_prepare_referral_source',
            'jmrs_prepare_priority',
            'jmrs_prepare_assigned_to',
            'jmrs_prepare_notes',
            'jmrs_prepare_confirm',
            'jmrs_prepare_client_date_of_birth',
            'jmrs_prepare_address_line_1',
            'jmrs_prepare_address_line_2',
            'jmrs_prepare_city',
            'jmrs_prepare_postcode',
            'jmrs_prepare_referrer_phone',
            'jmrs_prepare_relationship_to_client',
            'jmrs_prepare_care_requirements',
            'jmrs_prepare_care_start_date',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function render(int $inbox_id, array $payload, bool $can_assign, string $notice, bool $offer_create = false): void
    {
        $result = (string) ($payload['result'] ?? '');
        if (ReferralInboxPreparationService::NOT_FOUND === $result) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $item   = is_array($payload['item'] ?? null) ? $payload['item'] : [];
        $status = (string) ($payload['status'] ?? ($item['status'] ?? ''));
        $linked_referral_id = absint($item['linked_referral_id'] ?? 0);
        $view_referral_url  = $linked_referral_id > 0 ? PortalUrls::referral($linked_referral_id) : '';
        $show_form = in_array(
            $result,
            [
                ReferralInboxPreparationService::READY,
                ReferralInboxPreparationService::VALID,
                ReferralInboxPreparationService::VALIDATION_ERROR,
            ],
            true
        );

        $stale = ! $show_form
            && 'POST' === ($_SERVER['REQUEST_METHOD'] ?? '')
            && ReferralInboxPreparationService::INVALID_STATE === $result;

        $view = [
            'inbox_id'            => $inbox_id,
            'item'                => $item,
            'show_form'           => $show_form,
            'can_assign'          => $can_assign && $show_form,
            'notice'              => $notice,
            'stale_message'       => $stale
                ? __('This opportunity has already changed. Refresh the page to see its current status.', 'jm-referral-system')
                : '',
            'blocked_message'     => $show_form ? '' : $this->blocked_message($status, $linked_referral_id),
            'ready_message'       => ReferralInboxPreparationService::VALID === $result
                ? __('Referral details are valid and ready for confirmation.', 'jm-referral-system')
                : '',
            'not_created_message' => ReferralInboxPreparationService::VALID === $result
                ? __('No referral has been created yet.', 'jm-referral-system')
                : '',
            'offer_create'        => $offer_create && $show_form,
            'view_referral_url'   => $view_referral_url,
            'values'              => is_array($payload['values'] ?? null) ? $payload['values'] : [],
            'errors'              => is_array($payload['errors'] ?? null) ? $payload['errors'] : [],
            'warnings'            => is_array($payload['warnings'] ?? null) ? $payload['warnings'] : [],
            'alternatives'        => is_array($payload['alternatives'] ?? null) ? $payload['alternatives'] : [],
            'field_notes'         => is_array($payload['field_notes'] ?? null) ? $payload['field_notes'] : [],
            'document'            => is_array($payload['document'] ?? null) ? $payload['document'] : [],
            'service_hint'        => (string) ($payload['service_hint'] ?? ''),
            'priority_hint'       => (string) ($payload['priority_hint'] ?? ''),
            'authority_note'      => (string) ($payload['authority_note'] ?? ''),
            'authority_status_label' => (string) ($payload['authority_status_label'] ?? LocalAuthorityOrigin::stored_summary(0, null)),
            'service_types'       => is_array($payload['service_types'] ?? null) ? $payload['service_types'] : [],
            'referral_sources'    => is_array($payload['referral_sources'] ?? null) ? $payload['referral_sources'] : [],
            'priorities'          => is_array($payload['priorities'] ?? null) ? $payload['priorities'] : [],
            'assignable_users'    => is_array($payload['assignable_users'] ?? null) ? $payload['assignable_users'] : [],
            'status'              => $status,
            'status_label'        => $this->status_label($status),
            'detection_label'     => $this->detection_label((string) ($item['detection_status'] ?? '')),
            'detection_explanation' => ReferralInboxDetectionExplanation::for_reason(
                (string) ($item['detection_reason'] ?? '')
            ),
            'received_display'    => $this->format_datetime((string) ($item['received_at'] ?? '')),
            'detail_url'          => PortalUrls::referral_inbox_item($inbox_id),
            'form_action'         => PortalUrls::referral_inbox_prepare($inbox_id),
            'nonce_field'         => wp_nonce_field(
                self::NONCE_ACTION_PREFIX . $inbox_id,
                self::NONCE_FIELD,
                true,
                false
            ),
        ];

        $this->view_host->render_portal_page(
            'referral-inbox/prepare',
            __('Prepare Referral', 'jm-referral-system'),
            'referral_inbox_prepare',
            [
                [
                    'label' => __('Referral Inbox', 'jm-referral-system'),
                    'url'   => PortalUrls::referral_inbox(),
                ],
                [
                    'label' => sprintf(
                        /* translators: %d: inbox item ID */
                        __('Item #%d', 'jm-referral-system'),
                        $inbox_id
                    ),
                    'url'   => PortalUrls::referral_inbox_item($inbox_id),
                ],
                [
                    'label' => __('Prepare Referral', 'jm-referral-system'),
                    'url'   => '',
                ],
            ],
            $view
        );
    }

    private function blocked_message(string $status, int $linked_referral_id = 0): string
    {
        if (ReferralInboxStatus::ACCEPTED === $status && $linked_referral_id <= 0) {
            return __('This opportunity is accepted without a linked referral. It was not changed.', 'jm-referral-system');
        }

        return match ($status) {
            ReferralInboxStatus::NEW => __(
                'Start Review before preparing this opportunity as a referral.',
                'jm-referral-system'
            ),
            ReferralInboxStatus::ACCEPTED => __(
                'This opportunity has already been converted to a referral.',
                'jm-referral-system'
            ),
            ReferralInboxStatus::IGNORED => __(
                'This opportunity has been ignored. Preparation is no longer available.',
                'jm-referral-system'
            ),
            ReferralInboxStatus::DUPLICATE => __(
                'This opportunity is marked as a duplicate. Preparation is no longer available.',
                'jm-referral-system'
            ),
            ReferralInboxStatus::ERROR => __(
                'This opportunity is in an error state. Preparation is not available.',
                'jm-referral-system'
            ),
            default => __(
                'This opportunity is not ready for referral preparation.',
                'jm-referral-system'
            ),
        };
    }

    private function format_datetime(string $mysql): string
    {
        if ('' === $mysql || '0000-00-00 00:00:00' === $mysql) {
            return '—';
        }

        $formatted = mysql2date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $mysql,
            true
        );

        return is_string($formatted) && '' !== $formatted ? $formatted : '—';
    }

    private function status_label(string $status): string
    {
        return match ($status) {
            ReferralInboxStatus::NEW          => __('New', 'jm-referral-system'),
            ReferralInboxStatus::NEEDS_REVIEW => __('Needs Review', 'jm-referral-system'),
            ReferralInboxStatus::ACCEPTED     => __('Accepted', 'jm-referral-system'),
            ReferralInboxStatus::IGNORED      => __('Ignored', 'jm-referral-system'),
            ReferralInboxStatus::DUPLICATE    => __('Duplicate', 'jm-referral-system'),
            ReferralInboxStatus::ERROR        => __('Error', 'jm-referral-system'),
            default                           => '' !== $status ? ucfirst(str_replace('_', ' ', $status)) : '—',
        };
    }

    private function detection_label(string $status): string
    {
        return match ($status) {
            ReferralDetectionStatus::UNCLASSIFIED => __('Unclassified', 'jm-referral-system'),
            ReferralDetectionStatus::LIKELY       => __('Likely Referral', 'jm-referral-system'),
            ReferralDetectionStatus::UNCERTAIN    => __('Uncertain', 'jm-referral-system'),
            ReferralDetectionStatus::NOT_REFERRAL => __('Not Referral', 'jm-referral-system'),
            default                               => __('Unclassified', 'jm-referral-system'),
        };
    }
}
