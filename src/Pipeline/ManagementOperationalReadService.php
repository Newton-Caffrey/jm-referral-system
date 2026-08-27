<?php

namespace JMReferral\Pipeline;

use JMReferral\Meeting\ReferralMeeting;
use JMReferral\Meeting\ReferralMeetingRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\ReferralActivityRepository;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;
use JMReferral\Workflow\WorkflowStageService;

/**
 * Read-only operational Management Dashboard sections (Phase 4D.1).
 *
 * Scope-aware aggregates only. No mutations. No contact PII.
 * Assessment scheduling KPI deferred (no reliable standalone status metric).
 */
class ManagementOperationalReadService
{
    public const UPCOMING_MEETING_DAYS = 14;

    public const RECENT_REFERRALS_LIMIT = 8;

    public const RECENT_ACTIVITY_LIMIT = 10;

    public const MEETING_LIST_LIMIT = 10;

    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralMeetingRepository $meeting_repository,
        private ReferralActivityRepository $activity_repository,
        private WorkflowStageService $workflow_stage_service,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider,
        private PipelineAttentionService $attention_service
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function get_operational_payload(): array
    {
        if (! $this->attention_service->current_user_can_view_pipeline_dashboard()) {
            return ['show' => false];
        }

        $access = $this->access_policy->get_assigned_user_constraint();
        $now    = current_time('mysql');
        $until  = wp_date('Y-m-d H:i:s', strtotime('+' . self::UPCOMING_MEETING_DAYS . ' days', (int) current_time('timestamp')));

        $status_new         = $this->referral_repository->countByStatus('new', $access, 'active');
        $status_in_progress = $this->referral_repository->countByStatus('in_progress', $access, 'active');
        $status_completed   = $this->referral_repository->countByStatus('completed', $access, 'active');
        $status_cancelled   = $this->referral_repository->countByStatus('cancelled', $access, 'active');
        $active_operational = $this->referral_repository->count_active_operational($access);

        $upcoming_count = $this->meeting_repository->count_scheduled_for_dashboard('upcoming', $now, $until, $access);
        $past_count     = $this->meeting_repository->count_scheduled_for_dashboard('past', $now, null, $access);

        $unassigned = $this->referral_repository->count_unassigned_responsibilities($access);

        $stage_rows = $this->workflow_stage_service->get_pipeline_counts($access);
        $stage_max  = 0;
        foreach ($stage_rows as $row) {
            $stage_max = max($stage_max, (int) ($row['count'] ?? 0));
        }

        $workflow_stages = [];
        foreach ($stage_rows as $row) {
            $count = (int) ($row['count'] ?? 0);
            $workflow_stages[] = [
                'id'          => (int) ($row['id'] ?? 0),
                'name'        => (string) ($row['name'] ?? ''),
                'stage_order' => (int) ($row['stage_order'] ?? 0),
                'count'       => $count,
                'pct'         => $stage_max > 0 ? (int) round(($count / $stage_max) * 100) : 0,
            ];
        }

        return [
            'show'                 => true,
            'definitions'          => [
                'active'            => __('Non-archived referrals that are not Completed or Cancelled.', 'jm-referral-system'),
                'upcoming_meetings' => sprintf(
                    /* translators: %d: days */
                    __('Scheduled meetings in the next %d days (site timezone).', 'jm-referral-system'),
                    self::UPCOMING_MEETING_DAYS
                ),
                'past_meetings'     => __('Meetings still marked Scheduled with a start time earlier than now.', 'jm-referral-system'),
                'assessment'        => __('Deferred — assessment scheduling status is derived and not a reliable standalone KPI.', 'jm-referral-system'),
            ],
            'status_cards'         => [
                [
                    'label' => __('Active referrals', 'jm-referral-system'),
                    'value' => (string) $active_operational,
                    'note'  => __('New + In progress (non-archived)', 'jm-referral-system'),
                ],
                [
                    'label' => __('New', 'jm-referral-system'),
                    'value' => (string) $status_new,
                    'note'  => __('Referral status', 'jm-referral-system'),
                ],
                [
                    'label' => __('In progress', 'jm-referral-system'),
                    'value' => (string) $status_in_progress,
                    'note'  => __('Referral status', 'jm-referral-system'),
                ],
                [
                    'label' => __('Completed', 'jm-referral-system'),
                    'value' => (string) $status_completed,
                    'note'  => __('Non-archived', 'jm-referral-system'),
                ],
                [
                    'label' => __('Upcoming meetings', 'jm-referral-system'),
                    'value' => (string) $upcoming_count,
                    'note'  => sprintf(
                        /* translators: %d: days */
                        __('Next %d days', 'jm-referral-system'),
                        self::UPCOMING_MEETING_DAYS
                    ),
                ],
                [
                    'label' => __('Past scheduled meetings', 'jm-referral-system'),
                    'value' => (string) $past_count,
                    'note'  => __('Still Scheduled, start in the past', 'jm-referral-system'),
                ],
            ],
            'cancelled_count'      => $status_cancelled,
            'workflow_stages'      => $workflow_stages,
            'unassigned'           => [
                'owner'           => (int) ($unassigned['owner'] ?? 0),
                'champion'        => (int) ($unassigned['champion'] ?? 0),
                'transition_lead' => (int) ($unassigned['transition_lead'] ?? 0),
            ],
            'workloads'            => [
                'owners'           => $this->present_workload(
                    $this->referral_repository->count_responsibility_workload('assigned_to', $access)
                ),
                'champions'        => $this->present_workload(
                    $this->referral_repository->count_responsibility_workload('champion_user_id', $access)
                ),
                'transition_leads' => $this->present_workload(
                    $this->referral_repository->count_responsibility_workload('transition_lead_user_id', $access)
                ),
            ],
            'upcoming_meetings'    => $this->present_meetings(
                $this->meeting_repository->list_scheduled_for_dashboard(
                    'upcoming',
                    $now,
                    $until,
                    self::MEETING_LIST_LIMIT,
                    $access
                )
            ),
            'past_meetings'        => $this->present_meetings(
                $this->meeting_repository->list_scheduled_for_dashboard(
                    'past',
                    $now,
                    null,
                    self::MEETING_LIST_LIMIT,
                    $access
                )
            ),
            'needs_attention_extra'=> array_merge(
                $this->unassigned_attention_items($unassigned),
                $this->past_meeting_attention_items(
                    $this->meeting_repository->list_scheduled_for_dashboard(
                        'past',
                        $now,
                        null,
                        5,
                        $access
                    )
                )
            ),
            'recent_referrals'     => $this->present_recent_referrals(
                $this->referral_repository->recent(self::RECENT_REFERRALS_LIMIT, $access, 'active')
            ),
            'recent_activity'      => $this->present_recent_activity(
                $this->activity_repository->list_recent_for_dashboard(self::RECENT_ACTIVITY_LIMIT, $access)
            ),
            'deferred_metrics'     => [
                'assessment_scheduling' => true,
                'package_conversion'    => true,
                'authority_sla'         => true,
                'placement_conversion'  => true,
                'revenue'               => true,
            ],
        ];
    }

    /**
     * @param array<int, array{user_id: int, count: int}> $rows
     * @return array<int, array{label: string, count: int}>
     */
    private function present_workload(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = absint($row['user_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $names = $this->user_provider->get_display_names_by_ids($ids);
        $unavailable = __('Unavailable user', 'jm-referral-system');
        $unassigned  = __('Unassigned', 'jm-referral-system');

        $out = [];
        foreach ($rows as $row) {
            $id    = absint($row['user_id'] ?? 0);
            $count = (int) ($row['count'] ?? 0);
            if ($id <= 0) {
                $label = $unassigned;
            } else {
                $label = (string) ($names[$id] ?? $unavailable);
                if ('' === trim($label)) {
                    $label = $unavailable;
                }
            }
            $out[] = [
                'label' => $label,
                'count' => $count,
            ];
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function present_meetings(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $meeting_id  = absint($row['id'] ?? 0);
            $referral_id = absint($row['referral_id'] ?? 0);
            $type        = (string) ($row['meeting_type'] ?? '');
            $scheduled   = (string) ($row['scheduled_at'] ?? '');
            $out[]       = [
                'meeting_id'      => $meeting_id,
                'referral_id'     => $referral_id,
                'referral_number' => (string) ($row['referral_number'] ?? ''),
                'meeting_type'    => ReferralMeeting::type_label($type),
                'status_label'    => ReferralMeeting::status_label(ReferralMeeting::STATUS_SCHEDULED),
                'scheduled_label' => '' !== $scheduled
                    ? (string) mysql2date(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        $scheduled
                    )
                    : '—',
                'detail_url'      => ($meeting_id > 0 && $referral_id > 0)
                    ? PortalUrls::referral_meeting($referral_id, $meeting_id)
                    : '',
            ];
        }

        return $out;
    }

    /**
     * Concise unassigned-responsibility attention summaries (counts only — not overdue).
     *
     * @param array{owner: int, champion: int, transition_lead: int} $unassigned
     * @return array<int, array<string, string>>
     */
    private function unassigned_attention_items(array $unassigned): array
    {
        $items = [];
        $map   = [
            'owner'           => [
                'title'  => __('Referrals with no Referral owner', 'jm-referral-system'),
                'detail' => __('Active non-archived referrals where assigned_to is empty.', 'jm-referral-system'),
            ],
            'champion'        => [
                'title'  => __('Referrals with no Champion', 'jm-referral-system'),
                'detail' => __('Active non-archived referrals where champion is empty. Not labelled overdue.', 'jm-referral-system'),
            ],
            'transition_lead' => [
                'title'  => __('Referrals with no Transition lead', 'jm-referral-system'),
                'detail' => __('Active non-archived referrals where transition lead is empty. Not labelled overdue.', 'jm-referral-system'),
            ],
        ];

        foreach ($map as $key => $meta) {
            $count = (int) ($unassigned[$key] ?? 0);
            if ($count <= 0) {
                continue;
            }
            $items[] = [
                'title'  => $meta['title'],
                'detail' => sprintf(
                    /* translators: 1: count 2: definition */
                    _n('%1$d referral. %2$s', '%1$d referrals. %2$s', $count, 'jm-referral-system'),
                    $count,
                    $meta['detail']
                ),
                'url'    => '',
            ];
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, string>>
     */
    private function past_meeting_attention_items(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $meeting_id  = absint($row['id'] ?? 0);
            $referral_id = absint($row['referral_id'] ?? 0);
            if ($meeting_id <= 0 || $referral_id <= 0) {
                continue;
            }
            $items[] = [
                'title' => sprintf(
                    /* translators: %s: referral number */
                    __('Past scheduled meeting — %s', 'jm-referral-system'),
                    (string) ($row['referral_number'] ?? '')
                ),
                'detail' => ReferralMeeting::type_label((string) ($row['meeting_type'] ?? '')),
                'url'    => PortalUrls::referral_meeting($referral_id, $meeting_id),
            ];
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, string>>
     */
    private function present_recent_referrals(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $id = absint($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $created = (string) ($row['created_at'] ?? '');
            $status  = (string) ($row['status'] ?? '');
            $status_labels = [
                'new'         => __('New', 'jm-referral-system'),
                'in_progress' => __('In progress', 'jm-referral-system'),
                'completed'   => __('Completed', 'jm-referral-system'),
                'cancelled'   => __('Cancelled', 'jm-referral-system'),
            ];
            $out[]   = [
                'referral_number' => (string) ($row['referral_number'] ?? ''),
                'status'          => $status_labels[$status] ?? $status,
                'created_label'   => '' !== $created
                    ? (string) mysql2date(get_option('date_format'), $created)
                    : '—',
                'url'             => PortalUrls::referral($id),
            ];
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, string>>
     */
    private function present_recent_activity(array $rows): array
    {
        $user_ids = [];
        foreach ($rows as $row) {
            $uid = absint($row['user_id'] ?? 0);
            if ($uid > 0) {
                $user_ids[] = $uid;
            }
        }
        $names = $this->user_provider->get_display_names_by_ids($user_ids);
        $unavailable = __('Unavailable user', 'jm-referral-system');

        $out = [];
        foreach ($rows as $row) {
            $referral_id = absint($row['referral_id'] ?? 0);
            $uid         = absint($row['user_id'] ?? 0);
            $actor       = $uid > 0 ? (string) ($names[$uid] ?? $unavailable) : '';
            if ($uid > 0 && '' === trim($actor)) {
                $actor = $unavailable;
            }
            $created = (string) ($row['created_at'] ?? '');
            $out[]   = [
                'description'     => (string) ($row['description'] ?? ''),
                'referral_number' => (string) ($row['referral_number'] ?? ''),
                'actor'           => $actor,
                'when_label'      => '' !== $created
                    ? (string) mysql2date(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        $created
                    )
                    : '—',
                'url'             => $referral_id > 0 ? PortalUrls::referral($referral_id) : '',
            ];
        }

        return $out;
    }
}
