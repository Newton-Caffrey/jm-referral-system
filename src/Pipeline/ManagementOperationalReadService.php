<?php

namespace JMReferral\Pipeline;

use JMReferral\Assessment\ReferralAssessmentRepository;
use JMReferral\Assessment\ReferralAssessmentService;
use JMReferral\Meeting\ReferralMeeting;
use JMReferral\Meeting\ReferralMeetingRepository;
use JMReferral\PackageCost\PackageCost;
use JMReferral\PackageCost\PackageCostRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\ReferralActivityRepository;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;
use JMReferral\Workflow\WorkflowStageService;

/**
 * Read-only operational Management Dashboard sections (Phase 4D.1 / 4E.1 / 4F.1).
 *
 * Scope-aware aggregates only. No mutations. No contact PII.
 * Assessment metrics are derived from scheduled_at + outcome (no status column).
 * Package Costing metrics use pipeline stage + latest package-cost row (MAX id).
 */
class ManagementOperationalReadService
{
    public const UPCOMING_MEETING_DAYS = 14;

    public const UPCOMING_ASSESSMENT_DAYS = 14;

    public const RECENT_REFERRALS_LIMIT = 8;

    public const RECENT_ACTIVITY_LIMIT = 10;

    public const MEETING_LIST_LIMIT = 10;

    public const ASSESSMENT_LIST_LIMIT = 8;

    public const PACKAGE_LIST_LIMIT = 8;

    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralMeetingRepository $meeting_repository,
        private ReferralActivityRepository $activity_repository,
        private WorkflowStageService $workflow_stage_service,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider,
        private PipelineAttentionService $attention_service,
        private ReferralAssessmentRepository $assessment_repository,
        private PackageCostRepository $package_cost_repository
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
        $assess_until = wp_date(
            'Y-m-d H:i:s',
            strtotime('+' . self::UPCOMING_ASSESSMENT_DAYS . ' days', (int) current_time('timestamp'))
        );

        $status_new         = $this->referral_repository->countByStatus('new', $access, 'active');
        $status_in_progress = $this->referral_repository->countByStatus('in_progress', $access, 'active');
        $status_completed   = $this->referral_repository->countByStatus('completed', $access, 'active');
        $status_cancelled   = $this->referral_repository->countByStatus('cancelled', $access, 'active');
        $active_operational = $this->referral_repository->count_active_operational($access);

        $upcoming_count = $this->meeting_repository->count_scheduled_for_dashboard('upcoming', $now, $until, $access);
        $past_count     = $this->meeting_repository->count_scheduled_for_dashboard('past', $now, null, $access);

        $scheduled_assessments = $this->assessment_repository->count_for_dashboard('scheduled', $now, $access);
        $past_assessments      = $this->assessment_repository->count_for_dashboard('past_scheduled', $now, $access);
        $completed_assessments = $this->assessment_repository->count_for_dashboard('completed', $now, $access);
        $outcome_counts        = $this->assessment_repository->count_outcomes_for_dashboard($access);

        $package_status_counts = $this->package_cost_repository->count_current_status_for_dashboard($access);
        $package_cost_required = $this->package_cost_repository->count_referrals_on_pipeline_slug(
            PipelineStage::PACKAGE_COST_REQUIRED,
            $access
        );
        $awaiting_la_decision  = $this->package_cost_repository->count_referrals_on_pipeline_slug(
            PipelineStage::AWAITING_LA_DECISION,
            $access
        );

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
                'assessment'        => __('Derived from scheduled_at and outcome on non-archived referrals (site timezone).', 'jm-referral-system'),
                'scheduled_assessments' => __('Outcome pending with scheduled_at now or in the future.', 'jm-referral-system'),
                'past_assessments'  => __('Outcome pending with scheduled_at earlier than now.', 'jm-referral-system'),
                'completed_assessments' => __('Outcome is suitable, suitable with conditions, or not suitable.', 'jm-referral-system'),
                'package_costing'   => __(
                    'Pipeline stage counts plus latest package-cost row per referral (highest id). Amounts and recipients are not shown here.',
                    'jm-referral-system'
                ),
                'package_cost_required' => __('Current acquisition pipeline stage is Package Cost to Prepare.', 'jm-referral-system'),
                'prepared_packages' => __('Latest package-cost row status is prepared.', 'jm-referral-system'),
                'sent_packages'     => __('Latest package-cost row status is sent.', 'jm-referral-system'),
                'awaiting_la_decision' => __('Current acquisition pipeline stage is Awaiting Local Authority Decision.', 'jm-referral-system'),
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
            'assessments'          => [
                'scheduled_count'  => $scheduled_assessments,
                'past_count'       => $past_assessments,
                'completed_count'  => $completed_assessments,
                'outcomes'         => [
                    [
                        'label' => ReferralAssessmentService::outcome_labels()[ReferralAssessmentService::OUTCOME_SUITABLE],
                        'count' => (int) ($outcome_counts[ReferralAssessmentService::OUTCOME_SUITABLE] ?? 0),
                    ],
                    [
                        'label' => ReferralAssessmentService::outcome_labels()[ReferralAssessmentService::OUTCOME_SUITABLE_WITH_CONDITIONS],
                        'count' => (int) ($outcome_counts[ReferralAssessmentService::OUTCOME_SUITABLE_WITH_CONDITIONS] ?? 0),
                    ],
                    [
                        'label' => ReferralAssessmentService::outcome_labels()[ReferralAssessmentService::OUTCOME_NOT_SUITABLE],
                        'count' => (int) ($outcome_counts[ReferralAssessmentService::OUTCOME_NOT_SUITABLE] ?? 0),
                    ],
                ],
                'upcoming_list'    => $this->present_assessments(
                    $this->assessment_repository->list_scheduled_for_dashboard(
                        'upcoming',
                        $now,
                        $assess_until,
                        self::ASSESSMENT_LIST_LIMIT,
                        $access
                    )
                ),
                'past_list'        => $this->present_assessments(
                    $this->assessment_repository->list_scheduled_for_dashboard(
                        'past',
                        $now,
                        null,
                        self::ASSESSMENT_LIST_LIMIT,
                        $access
                    )
                ),
            ],
            'package_costing'      => [
                'required_count'   => $package_cost_required,
                'prepared_count'   => (int) ($package_status_counts['prepared'] ?? 0),
                'sent_count'       => (int) ($package_status_counts['sent'] ?? 0),
                'awaiting_la_count'=> $awaiting_la_decision,
                'prepared_list'    => $this->present_packages(
                    $this->package_cost_repository->list_current_for_dashboard(
                        PackageCost::STATUS_PREPARED,
                        self::PACKAGE_LIST_LIMIT,
                        $access
                    )
                ),
                'sent_list'        => $this->present_packages(
                    $this->package_cost_repository->list_current_for_dashboard(
                        PackageCost::STATUS_SENT,
                        self::PACKAGE_LIST_LIMIT,
                        $access
                    )
                ),
            ],
            'deferred_metrics'     => [
                'assessment_scheduling' => false,
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

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, string>>
     */
    private function present_assessments(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = absint($row['assessor_user_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $names = $this->user_provider->get_display_names_by_ids($ids);
        $unavailable = __('Unavailable user', 'jm-referral-system');

        $out = [];
        foreach ($rows as $row) {
            $referral_id = absint($row['referral_id'] ?? 0);
            $assessor_id = absint($row['assessor_user_id'] ?? 0);
            $scheduled   = (string) ($row['scheduled_at'] ?? '');
            $assessor    = '';
            if ($assessor_id > 0) {
                $assessor = (string) ($names[$assessor_id] ?? $unavailable);
                if ('' === trim($assessor)) {
                    $assessor = $unavailable;
                }
            }
            $out[] = [
                'referral_number' => (string) ($row['referral_number'] ?? ''),
                'scheduled_label' => '' !== $scheduled
                    ? (string) mysql2date(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        $scheduled
                    )
                    : '—',
                'assessor_name'   => $assessor,
                'url'             => $referral_id > 0 ? PortalUrls::referral($referral_id) : '',
            ];
        }

        return $out;
    }

    /**
     * Safe package-costing list rows — no amounts, recipients, references, or filenames.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, string>>
     */
    private function present_packages(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $referral_id = absint($row['referral_id'] ?? 0);
            $status      = (string) ($row['status'] ?? '');
            $when        = PackageCost::STATUS_SENT === $status
                ? (string) ($row['sent_at'] ?? '')
                : (string) ($row['prepared_at'] ?? '');
            $out[] = [
                'referral_number' => (string) ($row['referral_number'] ?? ''),
                'status_label'    => '' !== $status ? PackageCost::status_label($status) : '',
                'when_label'      => '' !== $when
                    ? (string) mysql2date(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        $when
                    )
                    : '—',
                'url'             => $referral_id > 0 ? PortalUrls::referral($referral_id) : '',
            ];
        }

        return $out;
    }
}
