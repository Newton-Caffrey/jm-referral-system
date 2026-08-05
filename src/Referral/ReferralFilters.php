<?php

namespace JMReferral\Referral;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Users\UserProvider;

class ReferralFilters
{
    private const ALLOWED_STATUSES = [
        'new',
        'in_progress',
        'completed',
        'cancelled',
    ];

    private const ALLOWED_PRIORITIES = [
        'low',
        'medium',
        'high',
        'urgent',
    ];

    private const ALLOWED_ARCHIVE_SCOPES = [
        'active',
        'archived',
        'all',
    ];

    public const ALLOWED_PER_PAGE = [20, 50, 100];

    public const DEFAULT_PER_PAGE = 20;

    public function __construct(
        private UserProvider $user_provider,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Reads and sanitizes list/export filter values from the request.
     *
     * When the user is scoped to assigned referrals, assignee request filters are
     * ignored so they cannot bypass record-level access.
     *
     * Default archive_scope is active (non-archived only).
     *
     * @return array{
     *     search: string,
     *     status: string,
     *     priority: string,
     *     assigned_to: int,
     *     archive_scope: string
     * }
     */
    public function from_request(): array
    {
        $search = isset($_GET['jmrs_search'])
            ? sanitize_text_field(wp_unslash($_GET['jmrs_search']))
            : '';

        $status = isset($_GET['jmrs_status'])
            ? sanitize_key(wp_unslash($_GET['jmrs_status']))
            : '';

        if ('' !== $status && ! in_array($status, self::ALLOWED_STATUSES, true)) {
            $status = '';
        }

        $priority = isset($_GET['jmrs_priority'])
            ? sanitize_key(wp_unslash($_GET['jmrs_priority']))
            : '';

        if ('' !== $priority && ! in_array($priority, self::ALLOWED_PRIORITIES, true)) {
            $priority = '';
        }

        $archive_scope = isset($_GET['jmrs_archive_scope'])
            ? sanitize_key(wp_unslash($_GET['jmrs_archive_scope']))
            : 'active';

        if (! in_array($archive_scope, self::ALLOWED_ARCHIVE_SCOPES, true)) {
            $archive_scope = 'active';
        }

        $assigned_to = 0;

        if (! $this->access_policy->should_scope_to_assigned()) {
            $assigned_to = isset($_GET['jmrs_assigned_to'])
                ? absint($_GET['jmrs_assigned_to'])
                : 0;

            if ($assigned_to > 0 && ! $this->user_provider->is_assignable($assigned_to)) {
                $assigned_to = 0;
            }
        }

        return [
            'search'         => $search,
            'status'         => $status,
            'priority'       => $priority,
            'assigned_to'    => $assigned_to,
            'archive_scope'  => $archive_scope,
        ];
    }

    /**
     * Reads list pagination from the request (allowlisted page size).
     *
     * @return array{page: int, per_page: int}
     */
    public function pagination_from_request(): array
    {
        $per_page = isset($_GET['jmrs_per_page'])
            ? absint($_GET['jmrs_per_page'])
            : self::DEFAULT_PER_PAGE;

        if (! in_array($per_page, self::ALLOWED_PER_PAGE, true)) {
            $per_page = self::DEFAULT_PER_PAGE;
        }

        $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        if ($page < 1) {
            $page = 1;
        }

        return [
            'page'     => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * Builds query args for list URLs that preserve filters and page size.
     *
     * @param array{
     *     search?: string,
     *     status?: string,
     *     priority?: string,
     *     assigned_to?: int,
     *     archive_scope?: string
     * } $filters
     * @return array<string, scalar>
     */
    public static function list_query_args(array $filters, int $per_page, ?int $page = null): array
    {
        $args = [
            'page' => 'jm-referrals-list',
        ];

        if (! empty($filters['search'])) {
            $args['jmrs_search'] = (string) $filters['search'];
        }

        if (! empty($filters['status'])) {
            $args['jmrs_status'] = (string) $filters['status'];
        }

        if (! empty($filters['priority'])) {
            $args['jmrs_priority'] = (string) $filters['priority'];
        }

        if (! empty($filters['assigned_to'])) {
            $args['jmrs_assigned_to'] = absint($filters['assigned_to']);
        }

        $archive_scope = (string) ($filters['archive_scope'] ?? 'active');
        if ('' !== $archive_scope && 'active' !== $archive_scope) {
            $args['jmrs_archive_scope'] = $archive_scope;
        }

        if (in_array($per_page, self::ALLOWED_PER_PAGE, true) && self::DEFAULT_PER_PAGE !== $per_page) {
            $args['jmrs_per_page'] = $per_page;
        }

        if (null !== $page && $page > 1) {
            $args['paged'] = $page;
        }

        return $args;
    }
}
