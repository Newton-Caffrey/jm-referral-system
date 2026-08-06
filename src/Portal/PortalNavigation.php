<?php

namespace JMReferral\Portal;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;

/**
 * Capability-based portal navigation.
 */
class PortalNavigation
{
    public function __construct(
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Route keys that should highlight the "Referrals" nav item because they
     * are all reached from, and return to, a specific referral record.
     *
     * @var string[]
     */
    private const REFERRAL_RELATED_ROUTES = [
        'referrals',
        'referral',
        'referral_edit',
        'referral_assessment',
        'referral_care_plan',
        'care_plan_review',
        'medication_new',
        'medication_edit',
        'care_team_new',
        'care_team_edit',
        'schedule_new',
        'schedule_edit',
        'schedule_generate',
        'visit_new',
        'visit_edit',
        'visit_execute',
        'visit_review',
    ];

    /**
     * @return array<int, array{id: string, label: string, url: string, current: bool, icon: string, section: string}>
     */
    public function items(string $current_route = ''): array
    {
        $items = [];

        if (Capabilities::current_user_can(Capabilities::VIEW_DASHBOARD)) {
            $items[] = [
                'id'      => 'dashboard',
                'label'   => __('Dashboard', 'jm-referral-system'),
                'url'     => PortalUrls::dashboard(),
                'current' => in_array($current_route, ['dashboard', ''], true),
                'icon'    => 'dashboard',
                'section' => 'overview',
            ];
        }

        if (Capabilities::current_user_can(Capabilities::VIEW_REFERRALS)) {
            $scoped = $this->access_policy->should_scope_to_assigned();
            $items[] = [
                'id'      => 'referrals',
                'label'   => $scoped
                    ? __('My Referrals', 'jm-referral-system')
                    : __('Referrals', 'jm-referral-system'),
                'url'     => PortalUrls::referrals(),
                'current' => in_array($current_route, self::REFERRAL_RELATED_ROUTES, true),
                'icon'    => 'referrals',
                'section' => 'care',
            ];
        }

        return $items;
    }

    /**
     * Human-readable labels for nav section keys, in display order.
     *
     * @return array<string, string>
     */
    public function section_labels(): array
    {
        return [
            'overview' => __('Overview', 'jm-referral-system'),
            'care'     => __('Care', 'jm-referral-system'),
        ];
    }

    public function role_label(): string
    {
        $user = wp_get_current_user();
        if (! ($user instanceof \WP_User) || $user->ID <= 0) {
            return '';
        }

        if (user_can($user, 'manage_options')) {
            return __('Administrator', 'jm-referral-system');
        }

        $map = [
            'jmrs_administrator'    => __('JM Administrator', 'jm-referral-system'),
            'jmrs_referral_manager' => __('Referral Manager', 'jm-referral-system'),
            'jmrs_care_coordinator' => __('Care Coordinator', 'jm-referral-system'),
            'jmrs_assessor'         => __('Assessor', 'jm-referral-system'),
            'jmrs_support_worker'   => __('Support Worker', 'jm-referral-system'),
        ];

        foreach ((array) $user->roles as $role) {
            if (isset($map[$role])) {
                return $map[$role];
            }
        }

        return __('Staff', 'jm-referral-system');
    }
}
