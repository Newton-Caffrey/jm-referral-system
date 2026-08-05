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
}
