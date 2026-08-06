<?php

namespace JMReferral\Portal\Clinical;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Referral\ReferralRetentionService;

/**
 * Shared authorization helpers for portal clinical routes.
 */
class ClinicalAccess
{
    public function __construct(
        private ReferralRepository $referral_repository,
        private AccessPolicy $access_policy,
        private ReferralRetentionService $retention_service
    ) {
    }

    /**
     * @return array{ok: true, referral: array<string, mixed>}|array{ok: false, status: int, title: string}
     */
    public function require_referral(int $referral_id, bool $require_mutate = false, bool $require_edit = false): array
    {
        $referral = $referral_id > 0 ? $this->referral_repository->find($referral_id) : null;

        if (null === $referral || ! $this->access_policy->can_view_referral($referral)) {
            return [
                'ok'     => false,
                'status' => 404,
                'title'  => __('Not Found', 'jm-referral-system'),
            ];
        }

        if ($require_edit && ! $this->access_policy->can_edit_referral($referral)) {
            return [
                'ok'     => false,
                'status' => 404,
                'title'  => __('Not Found', 'jm-referral-system'),
            ];
        }

        if ($require_mutate && ! $this->access_policy->can_mutate_referral($referral)) {
            return [
                'ok'     => false,
                'status' => 404,
                'title'  => __('Not Found', 'jm-referral-system'),
            ];
        }

        return [
            'ok'       => true,
            'referral' => $referral,
        ];
    }

    public function is_archived(array $referral): bool
    {
        return $this->retention_service->is_archived($referral);
    }

    public function can_review_care_plan(array $referral): bool
    {
        return ! $this->is_archived($referral)
            && Capabilities::current_user_can(Capabilities::REVIEW_CARE_PLANS)
            && $this->access_policy->can_edit_referral($referral)
            && $this->access_policy->can_mutate_referral($referral);
    }

    public function can_manage_medications(array $referral): bool
    {
        return ! $this->is_archived($referral)
            && Capabilities::current_user_can(Capabilities::MANAGE_MEDICATIONS)
            && $this->access_policy->can_edit_referral($referral)
            && $this->access_policy->can_mutate_referral($referral);
    }

    public function can_manage_care_team(array $referral): bool
    {
        return ! $this->is_archived($referral)
            && Capabilities::current_user_can(Capabilities::MANAGE_CARE_TEAM)
            && $this->access_policy->can_edit_referral($referral)
            && $this->access_policy->can_mutate_referral($referral);
    }

    public function can_view_schedules(array $referral): bool
    {
        return Capabilities::current_user_can(Capabilities::VIEW_SCHEDULES)
            && $this->access_policy->can_view_referral($referral);
    }

    public function can_manage_schedules(array $referral): bool
    {
        return ! $this->is_archived($referral)
            && Capabilities::current_user_can(Capabilities::MANAGE_SCHEDULES)
            && $this->access_policy->can_edit_referral($referral)
            && $this->access_policy->can_mutate_referral($referral);
    }

    public function can_manage_visits(array $referral): bool
    {
        return ! $this->is_archived($referral)
            && Capabilities::current_user_can(Capabilities::MANAGE_VISITS)
            && $this->access_policy->can_edit_referral($referral)
            && $this->access_policy->can_mutate_referral($referral);
    }

    /**
     * Standard Dashboard → (My) Referrals → Referral number → Page title breadcrumb trail
     * shared by all portal clinical handlers.
     *
     * @param array<string, mixed> $referral
     * @return array<int, array{label: string, url: string}>
     */
    public function referral_breadcrumbs(array $referral, string $page_title): array
    {
        $list_title = $this->access_policy->should_scope_to_assigned()
            ? __('My Referrals', 'jm-referral-system')
            : __('Referrals', 'jm-referral-system');

        $referral_id     = absint($referral['id'] ?? 0);
        $referral_number = (string) ($referral['referral_number'] ?? '');
        $referral_label  = '' !== $referral_number ? $referral_number : __('Referral', 'jm-referral-system');

        return [
            ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
            ['label' => $list_title, 'url' => PortalUrls::referrals()],
            ['label' => $referral_label, 'url' => PortalUrls::referral($referral_id)],
            ['label' => $page_title, 'url' => ''],
        ];
    }
}
