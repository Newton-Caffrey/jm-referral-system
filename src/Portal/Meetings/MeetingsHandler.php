<?php

namespace JMReferral\Portal\Meetings;

use JMReferral\Meeting\ReferralMeetingReadService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Portal\Clinical\ClinicalAccess;
use JMReferral\Portal\Clinical\PortalViewHost;
use JMReferral\Portal\PortalRouter;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\ReferralRetentionService;

/**
 * Read-only portal meeting list and detail (Phase 4B.2.1).
 * No POST handlers.
 */
class MeetingsHandler
{
    public function __construct(
        private PortalViewHost $view_host,
        private ClinicalAccess $clinical_access,
        private AccessPolicy $access_policy,
        private ReferralMeetingReadService $read_service,
        private ReferralRetentionService $retention_service
    ) {
    }

    public function handles(string $route): bool
    {
        return in_array($route, ['referral_meetings', 'referral_meeting'], true);
    }

    public function dispatch(string $route): void
    {
        $referral_id = absint(get_query_var(PortalRouter::QV_ID));
        $meeting_id  = absint(get_query_var(PortalRouter::QV_ENTITY));

        match ($route) {
            'referral_meetings' => $this->handle_list($referral_id),
            'referral_meeting'  => $this->handle_detail($referral_id, $meeting_id),
            default             => $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404),
        };
    }

    private function handle_list(int $referral_id): void
    {
        $access = $this->clinical_access->require_referral($referral_id, false, false);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return;
        }

        $referral = $access['referral'];
        if (! $this->access_policy->can_view_referral_meetings($referral)) {
            // Do not leak whether meetings exist.
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $page = isset($_GET['jmrs_meeting_page']) ? absint(wp_unslash($_GET['jmrs_meeting_page'])) : 1;
        $data = $this->read_service->get_list_page($referral, $page);
        if (null === $data) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $page_title = __('Meetings', 'jm-referral-system');
        $is_archived = $this->retention_service->is_archived($referral);

        $this->view_host->render_portal_page(
            'referrals/meetings-list',
            $page_title,
            'referral',
            $this->meetings_breadcrumbs($referral, $page_title, null),
            [
                'referral'     => $referral,
                'is_archived'  => $is_archived,
                'list'         => $data,
                'referral_url' => PortalUrls::referral($referral_id),
            ]
        );
    }

    private function handle_detail(int $referral_id, int $meeting_id): void
    {
        $access = $this->clinical_access->require_referral($referral_id, false, false);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return;
        }

        $referral = $access['referral'];
        if (! $this->access_policy->can_view_referral_meetings($referral)) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $data = $this->read_service->get_detail_page($referral, $meeting_id);
        if (null === $data) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $type_label = (string) ($data['meeting']['meeting_type_label'] ?? __('Meeting', 'jm-referral-system'));
        $page_title = $type_label;
        $is_archived = $this->retention_service->is_archived($referral);

        $this->view_host->render_portal_page(
            'referrals/meeting-detail',
            $page_title,
            'referral',
            $this->meetings_breadcrumbs($referral, __('Meetings', 'jm-referral-system'), $page_title),
            [
                'referral'    => $referral,
                'is_archived' => $is_archived,
                'detail'      => $data,
            ]
        );
    }

    /**
     * @param array<string, mixed> $referral
     * @return array<int, array{label: string, url: string}>
     */
    private function meetings_breadcrumbs(array $referral, string $meetings_label, ?string $detail_label): array
    {
        $crumbs = $this->clinical_access->referral_breadcrumbs($referral, $meetings_label);
        // Replace final empty URL crumb with meetings list when showing detail.
        if (null !== $detail_label && '' !== $detail_label) {
            $referral_id = absint($referral['id'] ?? 0);
            $crumbs[count($crumbs) - 1]['url'] = PortalUrls::referral_meetings($referral_id);
            $crumbs[] = ['label' => $detail_label, 'url' => ''];
        }

        return $crumbs;
    }
}
