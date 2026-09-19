<?php

namespace JMReferral\Portal\ReferralInbox;

use JMReferral\LocalAuthority\LocalAuthorityRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Portal\Clinical\PortalViewHost;
use JMReferral\Portal\PortalRouter;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\ReferralRepository;
use JMReferral\ReferralInbox\InboxAttachmentStatus;
use JMReferral\ReferralInbox\ReferralDetectionStatus;
use JMReferral\ReferralInbox\ReferralInboxAttachmentRepository;
use JMReferral\ReferralInbox\ReferralInboxRepository;
use JMReferral\ReferralInbox\ReferralInboxResult;
use JMReferral\ReferralInbox\ReferralInboxService;
use JMReferral\ReferralInbox\ReferralInboxSource;
use JMReferral\ReferralInbox\ReferralInboxStatus;
use JMReferral\Settings\TerminologySettings;
use JMReferral\Users\UserProvider;

/**
 * Staff Portal Referral Inbox UI (Phase 5B.3).
 *
 * GET is strictly read-only. Mutations go through ReferralInboxService only.
 * Does not create referrals, download attachments, or connect mailboxes.
 */
class InboxHandler
{
    private const PER_PAGE = 20;

    private const NONCE_ACTION_PREFIX = 'jmrs_inbox_action_';

    /** @var array<int, string> */
    private const ROUTES = [
        'referral_inbox',
        'referral_inbox_item',
    ];

    public function __construct(
        private PortalViewHost $view_host,
        private ReferralInboxService $inbox_service,
        private ReferralInboxRepository $inbox_repository,
        private ReferralInboxAttachmentRepository $attachment_repository,
        private LocalAuthorityRepository $local_authority_repository,
        private ReferralRepository $referral_repository,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider
    ) {
    }

    public function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public function dispatch(string $route): void
    {
        match ($route) {
            'referral_inbox'      => $this->render_list(),
            'referral_inbox_item' => $this->render_detail(),
            default               => $this->view_host->render_portal_error(
                '404',
                __('Not Found', 'jm-referral-system'),
                404
            ),
        };
    }

    private function render_list(): void
    {
        if (! $this->access_policy->can_view_referral_inbox()) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $status = sanitize_key((string) ($_GET['jmrs_inbox_status'] ?? 'all'));
        if ('all' !== $status && ! ReferralInboxStatus::is_valid($status)) {
            $status = 'all';
        }

        $search = sanitize_text_field(wp_unslash((string) ($_GET['jmrs_inbox_search'] ?? '')));
        $page   = max(1, absint($_GET['jmrs_page'] ?? 1));

        $filters = [];
        if ('all' !== $status) {
            $filters['status'] = $status;
        }
        if ('' !== $search) {
            $filters['search'] = $search;
        }

        $total    = $this->inbox_repository->count($filters);
        $per_page = self::PER_PAGE;
        $pages    = max(1, (int) ceil($total / $per_page));
        if ($page > $pages) {
            $page = $pages;
        }

        $items          = $this->inbox_repository->query($filters, $page, $per_page);
        $status_counts  = $this->inbox_repository->countByStatus();
        $authority_ids  = [];
        $reviewer_ids   = [];
        foreach ($items as $row) {
            $la_id = absint($row['local_authority_id'] ?? 0);
            if ($la_id > 0) {
                $authority_ids[$la_id] = $la_id;
            }
            $reviewed_by = absint($row['reviewed_by'] ?? 0);
            if ($reviewed_by > 0) {
                $reviewer_ids[$reviewed_by] = $reviewed_by;
            }
        }

        $authority_names = $this->local_authority_repository->findNamesByIds(array_values($authority_ids));
        $reviewer_names  = $this->user_provider->get_display_names_by_ids(array_values($reviewer_ids));

        $rows = [];
        foreach ($items as $item) {
            $rows[] = $this->present_list_row($item, $authority_names, $reviewer_names);
        }

        $list_args = [];
        if ('all' !== $status) {
            $list_args['jmrs_inbox_status'] = $status;
        }
        if ('' !== $search) {
            $list_args['jmrs_inbox_search'] = $search;
        }

        $pagination_links = '';
        if ($pages > 1) {
            $pagination_links = paginate_links(
                [
                    'base'      => esc_url_raw(
                        add_query_arg(
                            array_merge($list_args, ['jmrs_page' => '%#%']),
                            PortalUrls::referral_inbox()
                        )
                    ),
                    'format'    => '',
                    'current'   => $page,
                    'total'     => $pages,
                    'prev_text' => __('Previous', 'jm-referral-system'),
                    'next_text' => __('Next', 'jm-referral-system'),
                    'type'      => 'list',
                ]
            );
            if (! is_string($pagination_links)) {
                $pagination_links = '';
            }
        }

        $referral_singular = TerminologySettings::referral_singular();
        $notice = $this->notice_from_query();

        $view = [
            'intro'            => sprintf(
                /* translators: %s: referral singular label */
                __('Review incoming %s opportunities before they enter the workflow.', 'jm-referral-system'),
                strtolower($referral_singular)
            ),
            'items'            => $rows,
            'filters'          => [
                'status' => $status,
                'search' => $search,
            ],
            'status_tabs'      => $this->status_tabs($status_counts, $status, $search),
            'total'            => $total,
            'page'             => $page,
            'per_page'         => $per_page,
            'from'             => 0 === $total ? 0 : (($page - 1) * $per_page) + 1,
            'to'               => min($page * $per_page, $total),
            'pagination_links' => $pagination_links,
            'form_action'      => PortalUrls::referral_inbox(),
            'list_notice'      => $notice,
            'has_active_filter'=> 'all' !== $status || '' !== $search,
            'referral_label'   => TerminologySettings::referral_singular(),
            'la_label'         => TerminologySettings::local_authority_singular(),
        ];

        $this->view_host->render_portal_page(
            'referral-inbox/list',
            __('Referral Inbox', 'jm-referral-system'),
            'referral_inbox',
            [
                ['label' => __('Referral Inbox', 'jm-referral-system'), 'url' => ''],
            ],
            $view
        );
    }

    private function render_detail(): void
    {
        if (! $this->access_policy->can_view_referral_inbox()) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $inbox_id = absint(get_query_var(PortalRouter::QV_ID));
        if ($inbox_id <= 0) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if ('POST' === strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'))) {
            $this->handle_action_post($inbox_id);

            return;
        }

        $item = $this->inbox_service->find($inbox_id);
        if (null === $item) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $can_manage = $this->access_policy->can_manage_referral_inbox();
        $status     = (string) ($item['status'] ?? '');

        $attachments = $this->attachment_repository->listForInbox($inbox_id);
        $presented_attachments = [];
        foreach ($attachments as $attachment) {
            $presented_attachments[] = $this->present_attachment($attachment);
        }

        $la_name = '—';
        $la_id   = absint($item['local_authority_id'] ?? 0);
        if ($la_id > 0) {
            $names  = $this->local_authority_repository->findNamesByIds([$la_id]);
            $la_name = $names[$la_id] ?? '—';
            if ('' === $la_name) {
                $la_name = '—';
            }
        }

        $duplicate_of_id   = absint($item['duplicate_of_inbox_id'] ?? 0);
        $duplicate_of_url  = '';
        $duplicate_of_label = '';
        if ($duplicate_of_id > 0) {
            $duplicate_of_label = sprintf(
                /* translators: %d: inbox item ID */
                __('Duplicate of Inbox #%d', 'jm-referral-system'),
                $duplicate_of_id
            );
            if ($this->access_policy->can_view_referral_inbox()) {
                $duplicate_of_url = PortalUrls::referral_inbox_item($duplicate_of_id);
            }
        }

        $linked_referral_id  = absint($item['linked_referral_id'] ?? 0);
        $linked_referral_url = '';
        $linked_referral_label = '';
        if ($linked_referral_id > 0) {
            $referral = $this->referral_repository->find($linked_referral_id);
            if (is_array($referral) && $this->access_policy->can_view_referral($referral)) {
                $linked_referral_url = PortalUrls::referral($linked_referral_id);
                $number              = (string) ($referral['referral_number'] ?? '');
                $linked_referral_label = '' !== $number
                    ? $number
                    : sprintf(
                        /* translators: %d: referral ID */
                        __('Referral #%d', 'jm-referral-system'),
                        $linked_referral_id
                    );
            }
        }

        $dup_preview_id   = absint($_GET['jmrs_dup_preview'] ?? 0);
        $duplicate_preview = null;
        if ($can_manage && $dup_preview_id > 0 && $dup_preview_id !== $inbox_id) {
            $preview_item = $this->inbox_repository->findById($dup_preview_id);
            if (null !== $preview_item) {
                $duplicate_preview = [
                    'id'      => $dup_preview_id,
                    'subject' => (string) ($preview_item['subject'] ?? ''),
                    'sender'  => $this->format_sender($preview_item),
                    'status'  => $this->status_label((string) ($preview_item['status'] ?? '')),
                    'url'     => PortalUrls::referral_inbox_item($dup_preview_id),
                ];
            }
        }

        $view = [
            'item'                 => $item,
            'inbox_id'             => $inbox_id,
            'status'               => $status,
            'status_label'         => $this->status_label($status),
            'detection_label'      => $this->detection_label((string) ($item['detection_status'] ?? '')),
            'source_label'         => $this->source_label((string) ($item['source_provider'] ?? '')),
            'received_display'     => $this->format_datetime((string) ($item['received_at'] ?? '')),
            'reviewed_by_display'  => $this->format_user_display(absint($item['reviewed_by'] ?? 0)),
            'reviewed_at_display'  => $this->format_datetime((string) ($item['reviewed_at'] ?? '')),
            'ignored_by_display'   => $this->format_user_display(absint($item['ignored_by'] ?? 0)),
            'ignored_at_display'   => $this->format_datetime((string) ($item['ignored_at'] ?? '')),
            'accepted_by_display'  => $this->format_user_display(absint($item['accepted_by'] ?? 0)),
            'accepted_at_display'  => $this->format_datetime((string) ($item['accepted_at'] ?? '')),
            'la_name'              => $la_name,
            'la_label'             => TerminologySettings::local_authority_singular(),
            'referral_label'       => TerminologySettings::referral_singular(),
            'attachments'          => $presented_attachments,
            'can_manage'           => $can_manage,
            'can_start_review'     => $can_manage && ReferralInboxStatus::NEW === $status,
            'can_ignore'           => $can_manage && ReferralInboxStatus::NEEDS_REVIEW === $status,
            'can_duplicate'        => $can_manage && in_array(
                $status,
                [ReferralInboxStatus::NEW, ReferralInboxStatus::NEEDS_REVIEW],
                true
            ),
            'can_recover_error'    => $can_manage && ReferralInboxStatus::ERROR === $status,
            'is_terminal'          => in_array(
                $status,
                [
                    ReferralInboxStatus::ACCEPTED,
                    ReferralInboxStatus::IGNORED,
                    ReferralInboxStatus::DUPLICATE,
                ],
                true
            ),
            'duplicate_of_id'      => $duplicate_of_id,
            'duplicate_of_url'     => $duplicate_of_url,
            'duplicate_of_label'   => $duplicate_of_label,
            'linked_referral_id'   => $linked_referral_id,
            'linked_referral_url'  => $linked_referral_url,
            'linked_referral_label'=> $linked_referral_label,
            'duplicate_preview'    => $duplicate_preview,
            'dup_preview_id'       => $dup_preview_id,
            'form_action'          => PortalUrls::referral_inbox_item($inbox_id),
            'nonce_field'          => wp_nonce_field(
                self::NONCE_ACTION_PREFIX . $inbox_id,
                'jmrs_inbox_nonce',
                true,
                false
            ),
            'list_url'             => PortalUrls::referral_inbox(),
            'detail_notice'        => $this->notice_from_query(),
            'confirm_info'         => __(
                'Referral creation will be available after the opportunity is confirmed.',
                'jm-referral-system'
            ),
        ];

        $this->view_host->render_portal_page(
            'referral-inbox/view',
            sprintf(
                /* translators: %d: inbox item ID */
                __('Referral Inbox #%d', 'jm-referral-system'),
                $inbox_id
            ),
            'referral_inbox_item',
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
                    'url'   => '',
                ],
            ],
            $view
        );
    }

    private function handle_action_post(int $inbox_id): void
    {
        if (! $this->access_policy->can_manage_referral_inbox()) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $nonce = isset($_POST['jmrs_inbox_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['jmrs_inbox_nonce']))
            : '';
        if (! wp_verify_nonce($nonce, self::NONCE_ACTION_PREFIX . $inbox_id)) {
            $this->redirect_detail(
                $inbox_id,
                'error',
                __('Security check failed. Please try again.', 'jm-referral-system')
            );

            return;
        }

        // Reject non-scalar / forged inbox id from POST body — route ID is authoritative.
        if (isset($_POST['jmrs_inbox_id']) && ! is_scalar($_POST['jmrs_inbox_id'])) {
            $this->redirect_detail(
                $inbox_id,
                'error',
                __('Invalid request.', 'jm-referral-system')
            );

            return;
        }
        if (isset($_POST['jmrs_inbox_id']) && absint($_POST['jmrs_inbox_id']) !== $inbox_id) {
            $this->redirect_detail(
                $inbox_id,
                'error',
                __('Invalid request.', 'jm-referral-system')
            );

            return;
        }

        $action = sanitize_key((string) ($_POST['jmrs_inbox_action'] ?? ''));
        $actor  = get_current_user_id();

        $result = match ($action) {
            'start_review'   => $this->action_start_review($inbox_id, $actor),
            'ignore'         => $this->action_ignore($inbox_id, $actor),
            'mark_duplicate' => $this->action_mark_duplicate($inbox_id, $actor),
            'recover_error'  => $this->action_recover_error($inbox_id, $actor),
            default          => [
                'type'    => 'error',
                'message' => __('Unknown action.', 'jm-referral-system'),
            ],
        };

        $this->redirect_detail($inbox_id, $result['type'], $result['message']);
    }

    /**
     * @return array{type: string, message: string}
     */
    private function action_start_review(int $inbox_id, int $actor): array
    {
        $transition = $this->inbox_service->markNeedsReview($inbox_id);
        $mapped     = $this->map_service_result($transition, __('Review started.', 'jm-referral-system'));
        if ('success' !== $mapped['type'] && ReferralInboxResult::ALREADY_APPLIED !== ($transition['result'] ?? '')) {
            return $mapped;
        }

        $reviewed = $this->inbox_service->markReviewed($inbox_id, $actor);
        if (ReferralInboxResult::SUCCESS === ($reviewed['result'] ?? '')
            || ReferralInboxResult::ALREADY_APPLIED === ($reviewed['result'] ?? '')
        ) {
            return [
                'type'    => 'success',
                'message' => __('Review started. This opportunity is now in Needs Review.', 'jm-referral-system'),
            ];
        }

        return $this->map_service_result(
            $reviewed,
            __('Review started.', 'jm-referral-system')
        );
    }

    /**
     * @return array{type: string, message: string}
     */
    private function action_ignore(int $inbox_id, int $actor): array
    {
        $confirmed = ! empty($_POST['jmrs_inbox_ignore_confirm']);
        if (! $confirmed) {
            return [
                'type'    => 'error',
                'message' => __(
                    'Please confirm that you want to ignore this referral opportunity.',
                    'jm-referral-system'
                ),
            ];
        }

        $result = $this->inbox_service->markIgnored($inbox_id, $actor);

        return $this->map_service_result(
            $result,
            __('Opportunity ignored.', 'jm-referral-system')
        );
    }

    /**
     * @return array{type: string, message: string}
     */
    private function action_mark_duplicate(int $inbox_id, int $actor): array
    {
        if (isset($_POST['jmrs_duplicate_of_id']) && ! is_scalar($_POST['jmrs_duplicate_of_id'])) {
            return [
                'type'    => 'error',
                'message' => __('A valid duplicate target Inbox ID is required.', 'jm-referral-system'),
            ];
        }

        $target_id = absint($_POST['jmrs_duplicate_of_id'] ?? 0);
        $result    = $this->inbox_service->markDuplicate($inbox_id, $target_id, $actor);

        return $this->map_service_result(
            $result,
            __('Opportunity marked as duplicate.', 'jm-referral-system')
        );
    }

    /**
     * @return array{type: string, message: string}
     */
    private function action_recover_error(int $inbox_id, int $actor): array
    {
        $transition = $this->inbox_service->markNeedsReview($inbox_id);
        $mapped     = $this->map_service_result(
            $transition,
            __('Opportunity returned to Needs Review.', 'jm-referral-system')
        );
        if ('success' !== $mapped['type'] && ReferralInboxResult::ALREADY_APPLIED !== ($transition['result'] ?? '')) {
            return $mapped;
        }

        $reviewed = $this->inbox_service->markReviewed($inbox_id, $actor);
        if (in_array(
            $reviewed['result'] ?? '',
            [ReferralInboxResult::SUCCESS, ReferralInboxResult::ALREADY_APPLIED],
            true
        )) {
            return [
                'type'    => 'success',
                'message' => __('Opportunity returned to Needs Review.', 'jm-referral-system'),
            ];
        }

        return $this->map_service_result(
            $reviewed,
            __('Opportunity returned to Needs Review.', 'jm-referral-system')
        );
    }

    /**
     * @param array<string, mixed> $result
     * @return array{type: string, message: string}
     */
    private function map_service_result(array $result, string $success_message): array
    {
        $code = (string) ($result['result'] ?? '');

        return match ($code) {
            ReferralInboxResult::SUCCESS,
            ReferralInboxResult::ALREADY_APPLIED => [
                'type'    => 'success',
                'message' => $success_message,
            ],
            ReferralInboxResult::NOT_FOUND => [
                'type'    => 'error',
                'message' => __('Inbox item was not found.', 'jm-referral-system'),
            ],
            ReferralInboxResult::INVALID_TRANSITION,
            ReferralInboxResult::CONFLICT => [
                'type'    => 'warning',
                'message' => __(
                    'This opportunity has already changed. Refresh the page to see its current status.',
                    'jm-referral-system'
                ),
            ],
            ReferralInboxResult::VALIDATION_ERROR => [
                'type'    => 'error',
                'message' => $this->first_validation_error($result),
            ],
            default => [
                'type'    => 'error',
                'message' => __('The action could not be completed.', 'jm-referral-system'),
            ],
        };
    }

    /**
     * @param array<string, mixed> $result
     */
    private function first_validation_error(array $result): string
    {
        $errors = $result['errors'] ?? [];
        if (! is_array($errors) || [] === $errors) {
            return __('Validation failed.', 'jm-referral-system');
        }

        $first = reset($errors);

        return is_string($first) && '' !== $first
            ? $first
            : __('Validation failed.', 'jm-referral-system');
    }

    private function redirect_detail(int $inbox_id, string $type, string $message): void
    {
        $args = [
            'jmrs_inbox_notice' => sanitize_key($type),
            'jmrs_inbox_msg'    => rawurlencode($message),
        ];
        wp_safe_redirect(add_query_arg($args, PortalUrls::referral_inbox_item($inbox_id)));
        exit;
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function notice_from_query(): ?array
    {
        $type = sanitize_key((string) ($_GET['jmrs_inbox_notice'] ?? ''));
        if (! in_array($type, ['success', 'warning', 'error', 'info'], true)) {
            return null;
        }

        $raw = (string) ($_GET['jmrs_inbox_msg'] ?? '');
        $msg = sanitize_text_field(rawurldecode(wp_unslash($raw)));
        if ('' === $msg) {
            return null;
        }

        return [
            'type'    => $type,
            'message' => $msg,
        ];
    }

    /**
     * @param array<string, int> $counts
     * @return array<int, array{key: string, label: string, count: int, url: string, current: bool}>
     */
    private function status_tabs(array $counts, string $current, string $search): array
    {
        $all_count = array_sum($counts);
        $defs      = [
            'all'                            => __('All', 'jm-referral-system'),
            ReferralInboxStatus::NEW         => __('New', 'jm-referral-system'),
            ReferralInboxStatus::NEEDS_REVIEW => __('Needs Review', 'jm-referral-system'),
            ReferralInboxStatus::ACCEPTED    => __('Accepted', 'jm-referral-system'),
            ReferralInboxStatus::IGNORED     => __('Ignored', 'jm-referral-system'),
            ReferralInboxStatus::DUPLICATE   => __('Duplicates', 'jm-referral-system'),
            ReferralInboxStatus::ERROR       => __('Errors', 'jm-referral-system'),
        ];

        $tabs = [];
        foreach ($defs as $key => $label) {
            $count = 'all' === $key ? $all_count : (int) ($counts[$key] ?? 0);
            $args  = [];
            if ('all' !== $key) {
                $args['jmrs_inbox_status'] = $key;
            }
            if ('' !== $search) {
                $args['jmrs_inbox_search'] = $search;
            }
            $tabs[] = [
                'key'     => $key,
                'label'   => $label,
                'count'   => $count,
                'url'     => PortalUrls::referral_inbox_with_args($args),
                'current' => $current === $key,
            ];
        }

        return $tabs;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, string>   $authority_names
     * @param array<int, string>   $reviewer_names
     * @return array<string, mixed>
     */
    private function present_list_row(array $item, array $authority_names, array $reviewer_names): array
    {
        $id     = absint($item['id'] ?? 0);
        $status = (string) ($item['status'] ?? '');
        $la_id  = absint($item['local_authority_id'] ?? 0);
        $la     = '—';
        if ($la_id > 0 && isset($authority_names[$la_id]) && '' !== $authority_names[$la_id]) {
            $la = $authority_names[$la_id];
        }

        $reviewed_by = absint($item['reviewed_by'] ?? 0);
        $reviewed    = '—';
        if ($reviewed_by > 0) {
            $reviewed = $reviewer_names[$reviewed_by] ?? $this->former_user_label($reviewed_by);
        }

        return [
            'id'                => $id,
            'view_url'          => PortalUrls::referral_inbox_item($id),
            'received_display'  => $this->format_datetime((string) ($item['received_at'] ?? '')),
            'sender_display'    => $this->format_sender($item),
            'subject'           => (string) ($item['subject'] ?? ''),
            'la_name'           => $la,
            'detection_label'   => $this->detection_label((string) ($item['detection_status'] ?? '')),
            'detection_key'     => (string) ($item['detection_status'] ?? ''),
            'status'            => $status,
            'status_label'      => $this->status_label($status),
            'attachment_count'  => absint($item['attachment_count'] ?? 0),
            'reviewed_by'       => $reviewed,
            'source_key'        => (string) ($item['source_provider'] ?? ''),
            'source_label'      => $this->source_label((string) ($item['source_provider'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $attachment
     * @return array<string, mixed>
     */
    private function present_attachment(array $attachment): array
    {
        $status = (string) ($attachment['storage_status'] ?? '');
        $bytes  = absint($attachment['size_bytes'] ?? 0);

        return [
            'filename'       => (string) ($attachment['filename'] ?? ''),
            'mime_type'      => (string) ($attachment['mime_type'] ?? ''),
            'size_display'   => $bytes > 0 ? size_format($bytes) : '—',
            'storage_status' => $status,
            'storage_label'  => $this->attachment_status_label($status),
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function format_sender(array $item): string
    {
        $name  = trim((string) ($item['sender_name'] ?? ''));
        $email = trim((string) ($item['sender_email'] ?? ''));

        if ('' !== $name && '' !== $email) {
            return $name . ' <' . $email . '>';
        }
        if ('' !== $name) {
            return $name;
        }
        if ('' !== $email) {
            return $email;
        }

        return '—';
    }

    private function format_datetime(string $mysql): string
    {
        if ('' === $mysql) {
            return '—';
        }

        $format = get_option('date_format') . ' ' . get_option('time_format');
        $out    = mysql2date($format, $mysql);

        return is_string($out) && '' !== $out ? $out : '—';
    }

    private function format_user_display(int $user_id): string
    {
        if ($user_id <= 0) {
            return '—';
        }

        $name = $this->user_provider->get_display_name($user_id);
        if ('' !== $name) {
            return $name;
        }

        return $this->former_user_label($user_id);
    }

    private function former_user_label(int $user_id): string
    {
        return sprintf(
            /* translators: %d: WordPress user ID */
            __('Former user / User #%d', 'jm-referral-system'),
            $user_id
        );
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
            default                           => ucfirst(str_replace('_', ' ', $status)),
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

    private function source_label(string $provider): string
    {
        return match ($provider) {
            ReferralInboxSource::FIXTURE         => __('Test Fixture', 'jm-referral-system'),
            ReferralInboxSource::MANUAL          => __('Manual', 'jm-referral-system'),
            ReferralInboxSource::MICROSOFT_GRAPH => __('Microsoft 365', 'jm-referral-system'),
            ReferralInboxSource::GMAIL           => __('Gmail', 'jm-referral-system'),
            default                              => '' !== $provider ? $provider : '—',
        };
    }

    private function attachment_status_label(string $status): string
    {
        return match ($status) {
            InboxAttachmentStatus::METADATA_ONLY => __('Metadata received', 'jm-referral-system'),
            InboxAttachmentStatus::QUARANTINED   => __('Quarantined', 'jm-referral-system'),
            InboxAttachmentStatus::STORED        => __('Stored', 'jm-referral-system'),
            InboxAttachmentStatus::PROMOTED      => __('Promoted', 'jm-referral-system'),
            InboxAttachmentStatus::DELETED       => __('Deleted', 'jm-referral-system'),
            InboxAttachmentStatus::ERROR         => __('Error', 'jm-referral-system'),
            default                              => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
