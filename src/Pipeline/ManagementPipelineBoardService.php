<?php

namespace JMReferral\Pipeline;

use JMReferral\Assessment\AssessmentScheduling;
use JMReferral\Assessment\ReferralAssessmentRepository;
use JMReferral\Assessment\ReferralAssessmentService;
use JMReferral\Database\Tables;
use JMReferral\Homes\HomeService;
use JMReferral\Homes\OccupancyRepository;
use JMReferral\Homes\OccupancyService;
use JMReferral\LaDecision\LaDecision;
use JMReferral\LaDecision\LaDecisionRepository;
use JMReferral\PackageCost\PackageCost;
use JMReferral\PackageCost\PackageCostRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;

/**
 * Read-only Management Dashboard board (Phase 4A).
 *
 * Composes existing services; no mutations, no new policy thresholds.
 * Privacy: client initials only in payload (never full names).
 */
class ManagementPipelineBoardService
{
    private const QUEUE_LIMIT = 300;

    public function __construct(
        private PipelineAttentionService $attention_service,
        private ReferralRepository $referral_repository,
        private ReferralStageHistoryRepository $stage_history_repository,
        private PackageCostRepository $package_cost_repository,
        private ReferralAssessmentRepository $assessment_repository,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider,
        private ?HomeService $home_service = null,
        private ?OccupancyService $occupancy_service = null,
        private ?OccupancyRepository $occupancy_repository = null,
        private ?LaDecisionRepository $la_decision_repository = null,
        private ?ManagementOperationalReadService $operational_read_service = null
    ) {
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function get_board_payload(array $request = []): array
    {
        if (! $this->attention_service->current_user_can_view_pipeline_dashboard()) {
            return [
                'show' => false,
            ];
        }

        $access = $this->access_policy->get_assigned_user_constraint();
        $mode   = $this->normalize_mode((string) ($request['jmrs_mgmt_mode'] ?? 'now'));

        $active_slugs = VisualStageMap::all_active_slugs();

        // Uncapped KPI / stage counts (aggregates — not derived from capped row sets).
        $slug_counts = $this->referral_repository->count_active_by_pipeline_slugs(
            array_merge($active_slugs, [
                PipelineStage::CARE_COMMENCED,
                PipelineStage::DECLINED,
                PipelineStage::NOT_PROCEEDING,
            ]),
            $access
        );

        $live_count = 0;
        foreach ($active_slugs as $slug) {
            $live_count += (int) ($slug_counts[$slug] ?? 0);
        }

        $care_commenced = (int) ($slug_counts[PipelineStage::CARE_COMMENCED] ?? 0);
        $declined       = (int) ($slug_counts[PipelineStage::DECLINED] ?? 0);
        $not_proceeding = (int) ($slug_counts[PipelineStage::NOT_PROCEEDING] ?? 0);
        $acquisition_current = $live_count + $care_commenced + $declined + $not_proceeding;

        $awaiting_la = (int) ($slug_counts[PipelineStage::AWAITING_LA_DECISION] ?? 0);
        $transition  = (int) ($slug_counts[PipelineStage::TRANSITION_PLANNING] ?? 0);

        $proposed_live = $this->package_cost_repository->sum_current_package_total_for_pipeline_slugs(
            $active_slugs,
            $access
        );

        // Capped rows for table display only.
        $active_rows     = $this->referral_repository->find_active_pipeline_referrals(
            $active_slugs,
            $access,
            self::QUEUE_LIMIT
        );
        $here_now_groups = $this->group_here_now($active_rows);

        $all_row_ids = [];
        foreach ($here_now_groups as $rows) {
            foreach ($rows as $row) {
                $all_row_ids[] = absint($row['id'] ?? 0);
            }
        }
        $all_row_ids = array_values(array_filter(array_unique($all_row_ids)));

        $package_map     = $this->package_cost_repository->current_package_map_for_referrals($all_row_ids);
        $assessment_map  = $this->assessment_map_for_referrals($all_row_ids);
        $la_decision_map = $this->la_decision_map_for_referrals($all_row_ids);
        $occupancy_map   = $this->occupancy_map_for_referrals($all_row_ids);
        $owner_names     = $this->owner_name_map_from_rows($active_rows);
        $owner_names     = $this->enrich_owner_names_from_related(
            $owner_names,
            $active_rows,
            $assessment_map,
            $package_map
        );

        $stages = [];
        foreach (VisualStageMap::definitions() as $key => $def) {
            $slugs          = $def['slugs'];
            $here_now_total = 0;
            foreach ($slugs as $slug) {
                $here_now_total += (int) ($slug_counts[$slug] ?? 0);
            }

            $reached_total = $this->stage_history_repository->count_distinct_reached_or_currently_in(
                $slugs,
                $access
            );

            $here_rows = $here_now_groups[$key] ?? [];
            $stage_ppv = $this->package_cost_repository->sum_current_package_total_for_pipeline_slugs(
                $slugs,
                $access
            );

            if ('now' === $mode) {
                $table_rows = $this->build_table_rows(
                    $key,
                    $here_rows,
                    $package_map,
                    $assessment_map,
                    $la_decision_map,
                    $occupancy_map,
                    $owner_names,
                    $mode
                );
                $rows_total     = $here_now_total;
                $rows_truncated = count($table_rows) < $here_now_total;
            } else {
                [$table_rows, $rows_total, $rows_truncated] = $this->build_reached_table_rows(
                    $key,
                    $access,
                    $package_map,
                    $assessment_map,
                    $la_decision_map,
                    $occupancy_map,
                    $owner_names,
                    $here_rows,
                    $reached_total
                );
            }

            $stages[] = [
                'key'            => $key,
                'order'          => (int) $def['order'],
                'name'           => (string) $def['name'],
                'colour'         => (string) $def['colour'],
                'question'       => (string) $def['question'],
                'here_now'       => $here_now_total,
                'reached'        => $reached_total,
                'proposed_value' => $stage_ppv,
                'rows'           => $table_rows,
                'rows_shown'     => count($table_rows),
                'rows_total'     => $rows_total,
                'rows_truncated'=> $rows_truncated,
                'rows_note'      => $rows_truncated
                    ? sprintf(
                        /* translators: 1: shown count 2: total count */
                        __('Showing the first %1$d of %2$d referrals', 'jm-referral-system'),
                        count($table_rows),
                        $rows_total
                    )
                    : '',
            ];
        }

        $actions = $this->build_actions_payload();

        $kpis = [
            [
                'label' => __('Live in pipeline', 'jm-referral-system'),
                'value' => (string) $live_count,
                'note'  => __('Currently in active acquisition stages only (excludes care commenced, declined, not proceeding)', 'jm-referral-system'),
            ],
            [
                'label' => __('Acquisition referrals', 'jm-referral-system'),
                'value' => (string) $acquisition_current,
                'note'  => sprintf(
                    /* translators: 1: live 2: care commenced 3: declined 4: not proceeding */
                    __('%1$d live · %2$d care commenced · %3$d declined · %4$d not proceeding (current stage)', 'jm-referral-system'),
                    $live_count,
                    $care_commenced,
                    $declined,
                    $not_proceeding
                ),
            ],
            [
                'label' => __('Proposed Package Value', 'jm-referral-system'),
                'value' => $this->format_money($proposed_live),
                'note'  => __('Latest Package Cost record per live referral only. Not revenue.', 'jm-referral-system'),
            ],
            [
                'label' => __('Awaiting LA decision', 'jm-referral-system'),
                'value' => (string) $awaiting_la,
                'note'  => __('Here now in Authority Consideration', 'jm-referral-system'),
            ],
            [
                'label' => __('Transition planning', 'jm-referral-system'),
                'value' => (string) $transition,
                'note'  => __('Here now — care not yet commenced', 'jm-referral-system'),
            ],
            [
                'label' => __('Care commenced (current stage)', 'jm-referral-system'),
                'value' => (string) $care_commenced,
                'note'  => __('Point-in-time count only. Cohort conversion uses Reports → Acquisition Pipeline.', 'jm-referral-system'),
            ],
        ];

        $ownership = $this->build_ownership_stats($active_slugs, $access);

        return [
            'show'              => true,
            'mode'              => $mode,
            'masthead'          => [
                'company'        => __('J&M Healthcare Services', 'jm-referral-system'),
                'subtitle'       => __('Referral to placement pipeline', 'jm-referral-system'),
                'period_label'   => __('Reporting view', 'jm-referral-system'),
                'period_value'   => __('Live pipeline (point in time)', 'jm-referral-system'),
                'updated_label'  => __('Last updated', 'jm-referral-system'),
                'updated_value'  => wp_date(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    (int) current_time('timestamp')
                ),
                'audience'       => __('Prepared for', 'jm-referral-system'),
                'audience_value' => __('Senior management', 'jm-referral-system'),
            ],
            'kpis'              => $kpis,
            'stages'            => $stages,
            'funnel'            => array_map(static function (array $s): array {
                return [
                    'key'      => $s['key'],
                    'order'    => $s['order'],
                    'name'     => $s['name'],
                    'colour'   => $s['colour'],
                    'reached'  => $s['reached'],
                    'here_now' => $s['here_now'],
                ];
            }, $stages),
            'actions'           => $actions,
            'actions_high'      => count(array_filter(
                $actions,
                static fn (array $a): bool => 'critical' === ($a['severity'] ?? '') || 'high' === ($a['severity'] ?? '')
            )),
            'homes'             => $this->build_homes_payload(),
            'show_homes'        => Capabilities::current_user_can(Capabilities::VIEW_HOMES),
            'ownership'         => $ownership,
            'show_ownership'    => [] !== $ownership,
            'show_team_tab'     => [] !== $ownership,
            'show_funding_tab'  => false,
            'operational'       => null !== $this->operational_read_service
                ? $this->operational_read_service->get_operational_payload()
                : ['show' => false],
            'deferred'          => [
                'team_performance_full' => true,
                'funding_authority'     => true,
                'assessment_scheduling' => false,
            ],
        ];
    }

    private function normalize_mode(string $mode): string
    {
        return 'reached' === $mode ? 'reached' : 'now';
    }

    /**
     * @param array<int, array<string, mixed>> $active_rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function group_here_now(array $active_rows): array
    {
        $groups = [];
        foreach (VisualStageMap::keys() as $key) {
            $groups[$key] = [];
        }

        foreach ($active_rows as $row) {
            $slug = (string) ($row['pipeline_stage_slug'] ?? '');
            $key  = VisualStageMap::visual_key_for_slug($slug);
            if (null === $key) {
                continue;
            }
            $groups[$key][] = $row;
        }

        return $groups;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function build_actions_payload(): array
    {
        $attention = $this->attention_service->get_dashboard_payload('portal', []);
        $actions   = [];
        if (empty($attention['show']) || ! is_array($attention['needs_attention'] ?? null)) {
            return [];
        }

        foreach ($attention['needs_attention'] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $full_name = (string) ($item['client_name'] ?? '');
            $initials  = ManagementClientDisplay::initials_from_name($full_name);

            $reason_labels = [];
            if (is_array($item['reason_labels'] ?? null)) {
                foreach ($item['reason_labels'] as $label) {
                    $reason_labels[] = ManagementClientDisplay::scrub_text((string) $label, $full_name, $initials);
                }
            }

            $actions[] = [
                'referral_id'     => absint($item['referral_id'] ?? 0),
                'referral_number' => (string) ($item['referral_number'] ?? ''),
                'client_initials' => $initials,
                'stage_label'     => ManagementClientDisplay::scrub_text(
                    (string) ($item['stage_label'] ?? ''),
                    $full_name,
                    $initials
                ),
                'severity'       => (string) ($item['severity'] ?? 'medium'),
                'reason_labels'   => $reason_labels,
                'next_action'     => ManagementClientDisplay::scrub_text(
                    (string) ($item['next_action'] ?? ''),
                    $full_name,
                    $initials
                ),
                'owner_name'      => (string) ($item['owner_name'] ?? ''),
                'view_url'        => (string) ($item['view_url'] ?? ''),
                'waiting_label'   => ManagementClientDisplay::scrub_text(
                    (string) ($item['waiting_label'] ?? ''),
                    $full_name,
                    $initials
                ),
            ];
        }

        return $actions;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $package_map
     * @param array<int, array<string, mixed>> $assessment_map
     * @param array<int, array<string, mixed>> $la_decision_map
     * @param array<int, array<string, mixed>> $occupancy_map
     * @param array<int, string> $owner_names
     * @return array<int, array<string, mixed>>
     */
    private function build_table_rows(
        string $visual_key,
        array $rows,
        array $package_map,
        array $assessment_map,
        array $la_decision_map,
        array $occupancy_map,
        array $owner_names,
        string $mode
    ): array {
        $first_reached = [];
        if ('reached' === $mode) {
            $ids = [];
            foreach ($rows as $row) {
                $ids[] = absint($row['id'] ?? 0);
            }
            $first_reached = $this->stage_history_repository->first_reached_at_map(
                $ids,
                VisualStageMap::slugs_for_visual($visual_key)
            );
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->map_row(
                $visual_key,
                $row,
                $package_map,
                $assessment_map,
                $la_decision_map,
                $occupancy_map,
                $owner_names,
                $mode,
                $first_reached
            );
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $package_map
     * @param array<int, array<string, mixed>> $assessment_map
     * @param array<int, array<string, mixed>> $la_decision_map
     * @param array<int, array<string, mixed>> $occupancy_map
     * @param array<int, string> $owner_names
     * @param array<int, array<string, mixed>> $here_rows
     * @return array{0: array<int, array<string, mixed>>, 1: int, 2: bool}
     */
    private function build_reached_table_rows(
        string $visual_key,
        ?int $access,
        array &$package_map,
        array &$assessment_map,
        array &$la_decision_map,
        array &$occupancy_map,
        array &$owner_names,
        array $here_rows,
        int $reached_total
    ): array {
        $slugs  = VisualStageMap::slugs_for_visual($visual_key);
        $ids    = $this->stage_history_repository->find_referral_ids_reached_slugs($slugs, $access, self::QUEUE_LIMIT);
        $id_set = array_fill_keys($ids, true);
        foreach ($here_rows as $row) {
            $rid = absint($row['id'] ?? 0);
            if ($rid > 0) {
                $id_set[$rid] = true;
            }
        }
        $ids = array_keys($id_set);
        if ([] === $ids) {
            return [[], $reached_total, false];
        }

        // Cap display set after union.
        $truncated = count($ids) > self::QUEUE_LIMIT || $reached_total > count($ids);
        if (count($ids) > self::QUEUE_LIMIT) {
            $ids = array_slice($ids, 0, self::QUEUE_LIMIT);
        }

        $missing_pkg = array_values(array_diff($ids, array_keys($package_map)));
        if ([] !== $missing_pkg) {
            $package_map += $this->package_cost_repository->current_package_map_for_referrals($missing_pkg);
        }
        $missing_assess = array_values(array_diff($ids, array_keys($assessment_map)));
        if ([] !== $missing_assess) {
            $assessment_map += $this->assessment_map_for_referrals($missing_assess);
        }
        $missing_la = array_values(array_diff($ids, array_keys($la_decision_map)));
        if ([] !== $missing_la) {
            $la_decision_map += $this->la_decision_map_for_referrals($missing_la);
        }
        $missing_occ = array_values(array_diff($ids, array_keys($occupancy_map)));
        if ([] !== $missing_occ) {
            $occupancy_map += $this->occupancy_map_for_referrals($missing_occ);
        }

        $referrals = $this->load_referral_summaries($ids, $access);
        foreach ($referrals as $row) {
            $oid = absint($row['assigned_to'] ?? 0);
            if ($oid > 0 && ! isset($owner_names[$oid])) {
                $owner_names[$oid] = $this->user_provider->get_display_name($oid) ?: __('Unassigned', 'jm-referral-system');
            }
        }
        $owner_names = $this->enrich_owner_names_from_related(
            $owner_names,
            $referrals,
            $assessment_map,
            $package_map
        );

        $first_reached = $this->stage_history_repository->first_reached_at_map($ids, $slugs);

        $out = [];
        foreach ($referrals as $row) {
            $out[] = $this->map_row(
                $visual_key,
                $row,
                $package_map,
                $assessment_map,
                $la_decision_map,
                $occupancy_map,
                $owner_names,
                'reached',
                $first_reached
            );
        }

        $shown = count($out);
        $truncated = $shown < $reached_total;

        return [$out, $reached_total, $truncated];
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function load_referral_summaries(array $ids, ?int $access): array
    {
        global $wpdb;

        $ids = array_values(array_filter(array_map('absint', $ids)));
        if ([] === $ids) {
            return [];
        }

        $referrals = Tables::referrals_table();
        $stages    = Tables::workflow_stages_table();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = "SELECT r.id, r.referral_number, r.client_name, r.priority, r.status, r.assigned_to,
                r.care_setting, r.workflow_stage_id, r.workflow_stage_entered_at, r.next_action_due_at,
                r.created_at, r.referrer_organisation, r.referrer_type,
                r.interest_expressed_at, r.interest_expressed_by, r.interest_response_method,
                r.interest_response_recipient, r.interest_email_status,
                s.slug AS pipeline_stage_slug, s.name AS pipeline_stage_name
            FROM {$referrals} r
            LEFT JOIN {$stages} s ON s.id = r.workflow_stage_id
            WHERE r.id IN ({$placeholders})
              AND r.archived_at IS NULL";

        $params = $ids;
        if (null !== $access && $access > 0) {
            $sql     .= ' AND r.assigned_to = %d';
            $params[] = $access;
        }

        $sql .= ' ORDER BY r.workflow_stage_entered_at ASC, r.id ASC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<int, array<string, mixed>> $package_map
     * @param array<int, array<string, mixed>> $assessment_map
     * @param array<int, array<string, mixed>> $la_decision_map
     * @param array<int, array<string, mixed>> $occupancy_map
     * @param array<int, string> $owner_names
     * @param array<int, string> $first_reached
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function map_row(
        string $visual_key,
        array $row,
        array $package_map,
        array $assessment_map,
        array $la_decision_map,
        array $occupancy_map,
        array $owner_names,
        string $mode,
        array $first_reached
    ): array {
        $id       = absint($row['id'] ?? 0);
        $slug     = (string) ($row['pipeline_stage_slug'] ?? '');
        $owner_id = absint($row['assigned_to'] ?? 0);
        $pkg      = $package_map[$id] ?? null;
        $assess   = $assessment_map[$id] ?? null;
        $la       = $la_decision_map[$id] ?? null;
        $occ      = $occupancy_map[$id] ?? null;

        $full_name = (string) ($row['client_name'] ?? '');
        $initials  = ManagementClientDisplay::initials_from_name($full_name);

        $waiting_days      = null;
        $first_reached_at  = null;
        $first_reached_label = '—';

        if ('now' === $mode) {
            $entered = (string) ($row['workflow_stage_entered_at'] ?? '');
            if ('' !== $entered && false !== strtotime($entered)) {
                $waiting_days = max(
                    0,
                    (int) floor(((int) current_time('timestamp') - (int) strtotime($entered)) / DAY_IN_SECONDS)
                );
            }
        } else {
            $at = (string) ($first_reached[$id] ?? '');
            if ('' !== $at && false !== strtotime($at)) {
                $first_reached_at    = $at;
                $first_reached_label = (string) mysql2date((string) get_option('date_format'), $at);
            }
        }

        $created_at = (string) ($row['created_at'] ?? '');
        $days_since_received = null;
        if ('' !== $created_at && false !== strtotime($created_at)) {
            $days_since_received = max(
                0,
                (int) floor(((int) current_time('timestamp') - (int) strtotime($created_at)) / DAY_IN_SECONDS)
            );
        }

        $base = [
            'referral_id'          => $id,
            'referral_number'      => (string) ($row['referral_number'] ?? ''),
            'client_initials'      => $initials,
            'priority'             => (string) ($row['priority'] ?? ''),
            'stage_slug'           => $slug,
            'stage_label'          => PipelineStage::is_canonical($slug)
                ? PipelineStage::label($slug)
                : (string) ($row['pipeline_stage_name'] ?? ''),
            'owner_name'           => $owner_id > 0
                ? ($owner_names[$owner_id] ?? __('Unknown', 'jm-referral-system'))
                : __('Unassigned', 'jm-referral-system'),
            'waiting_days'         => $waiting_days,
            'first_reached_at'     => $first_reached_at,
            'first_reached_label'  => $first_reached_label,
            'view_url'             => PortalUrls::referral($id),
            'created_at'           => $created_at,
            'received_label'       => '' !== $created_at
                ? (string) mysql2date((string) get_option('date_format'), $created_at)
                : '—',
            'days_since_received'  => $days_since_received,
            'referrer_organisation'=> (string) ($row['referrer_organisation'] ?? ''),
            'referrer_type'        => (string) ($row['referrer_type'] ?? ''),
            'funding_label'        => $this->funding_label($row),
            'proposed_value'       => null !== $pkg && null !== ($pkg['package_total'] ?? null)
                ? $this->format_money((float) $pkg['package_total'])
                : '—',
            'package_status'       => null !== $pkg ? (string) ($pkg['status'] ?? '') : '',
            'package_status_label' => null !== $pkg && '' !== (string) ($pkg['status'] ?? '')
                ? PackageCost::status_label((string) $pkg['status'])
                : '—',
            'care_setting'         => (string) ($row['care_setting'] ?? ''),
        ];

        $base = array_merge($base, $this->interest_fields($row, $owner_names));
        $base = array_merge($base, $this->assessment_fields($assess, $owner_names, $visual_key));
        $base = array_merge($base, $this->package_fields($pkg, $owner_names));
        $base = array_merge($base, $this->authority_fields($pkg, $la));
        $base = array_merge($base, $this->placement_fields($occ, $row));

        return $base;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function funding_label(array $row): string
    {
        $org = trim((string) ($row['referrer_organisation'] ?? ''));
        if ('' !== $org) {
            return $org;
        }
        $type = trim((string) ($row['referrer_type'] ?? ''));

        return '' !== $type ? $type : '—';
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $owner_names
     * @return array<string, mixed>
     */
    private function interest_fields(array $row, array $owner_names): array
    {
        $expressed_at = (string) ($row['interest_expressed_at'] ?? '');
        $method       = (string) ($row['interest_response_method'] ?? '');
        $recipient    = (string) ($row['interest_response_recipient'] ?? '');
        $by_id        = absint($row['interest_expressed_by'] ?? 0);
        $slug         = (string) ($row['pipeline_stage_slug'] ?? '');

        $state = __('Response required', 'jm-referral-system');
        if ('' !== $expressed_at) {
            if (PipelineStage::INTEREST_REQUIRED === $slug) {
                $state = __('Response recorded', 'jm-referral-system');
            } else {
                $state = __('Taken forward', 'jm-referral-system');
            }
        }

        return [
            'interest_state'            => $state,
            'interest_expressed_at'     => $expressed_at,
            'interest_response_date'    => '' !== $expressed_at
                ? (string) mysql2date((string) get_option('date_format'), $expressed_at)
                : '—',
            'interest_response_method'  => '' !== $method ? InterestResponse::method_label($method) : '—',
            'interest_response_recipient' => '' !== $recipient ? $recipient : '—',
            'interest_recorded_by'      => $by_id > 0
                ? ($owner_names[$by_id] ?? ($this->user_provider->get_display_name($by_id) ?: '—'))
                : '—',
        ];
    }

    /**
     * @param array<string, mixed>|null $assess
     * @param array<int, string> $owner_names
     * @return array<string, mixed>
     */
    private function assessment_fields(?array $assess, array $owner_names, string $visual_key): array
    {
        $scheduled_at = is_array($assess) ? (string) ($assess['scheduled_at'] ?? '') : '';
        $has_appt     = '' !== $scheduled_at;
        $loc_type     = is_array($assess) ? (string) ($assess['assessment_location_type'] ?? '') : '';
        $outcome      = is_array($assess) ? (string) ($assess['outcome'] ?? '') : '';
        $assessor_id  = is_array($assess) ? absint($assess['assessor_user_id'] ?? 0) : 0;
        $assessment_date = is_array($assess) ? (string) ($assess['assessment_date'] ?? '') : '';

        $scheduling_status = __('Scheduling required', 'jm-referral-system');
        if (VisualStageMap::APPOINTMENT_SET === $visual_key) {
            $scheduling_status = $has_appt
                ? __('Appointment on record (stage still to arrange)', 'jm-referral-system')
                : __('Scheduling required — not booked', 'jm-referral-system');
        } elseif (VisualStageMap::ASSESSMENT === $visual_key) {
            $scheduling_status = $has_appt
                ? __('Scheduled', 'jm-referral-system')
                : __('No schedule on record', 'jm-referral-system');
        }

        $date_display = '';
        $time_display = '';
        if ($has_appt && preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/', $scheduled_at, $m)) {
            $date_display = (string) mysql2date((string) get_option('date_format'), $m[1] . ' 00:00:00');
            $time_display = (string) mysql2date((string) get_option('time_format'), '1970-01-01 ' . $m[2] . ':00');
        } elseif ($has_appt) {
            $date_display = (string) mysql2date((string) get_option('date_format'), $scheduled_at);
            $time_display = (string) mysql2date((string) get_option('time_format'), $scheduled_at);
        }

        $outcome_labels = ReferralAssessmentService::outcome_labels();

        return [
            'scheduling_status'       => $scheduling_status,
            'scheduled_at'            => $scheduled_at,
            'scheduled_date_label'    => '' !== $date_display ? $date_display : '—',
            'scheduled_time_label'    => '' !== $time_display ? $time_display : '—',
            'assessment_date'         => $assessment_date,
            'assessment_date_label'   => '' !== $assessment_date
                ? (string) mysql2date((string) get_option('date_format'), $assessment_date)
                : '—',
            'outcome'                 => $outcome,
            'outcome_label'           => '' !== $outcome
                ? ($outcome_labels[$outcome] ?? $outcome)
                : '—',
            'location_type'           => $loc_type,
            'location_type_label'     => '' !== $loc_type
                ? AssessmentScheduling::location_type_label($loc_type)
                : '—',
            'location_name'           => is_array($assess) ? (string) ($assess['assessment_location_name'] ?? '') : '',
            'location_address'        => is_array($assess) ? (string) ($assess['assessment_location_address'] ?? '') : '',
            'assessment_contact_name' => is_array($assess) ? (string) ($assess['assessment_contact_name'] ?? '') : '',
            'assessor_name'           => $assessor_id > 0
                ? ($owner_names[$assessor_id] ?? ($this->user_provider->get_display_name($assessor_id) ?: '—'))
                : '—',
            'assessment_status_label' => $this->assessment_status_label($outcome, $has_appt, $visual_key),
        ];
    }

    private function assessment_status_label(string $outcome, bool $has_appt, string $visual_key): string
    {
        if (VisualStageMap::APPOINTMENT_SET === $visual_key) {
            return $has_appt
                ? __('Appointment recorded', 'jm-referral-system')
                : __('To arrange', 'jm-referral-system');
        }

        if ('' !== $outcome && ReferralAssessmentService::OUTCOME_PENDING !== $outcome) {
            $labels = ReferralAssessmentService::outcome_labels();

            return $labels[$outcome] ?? $outcome;
        }

        return $has_appt
            ? __('Assessment in progress', 'jm-referral-system')
            : __('Awaiting schedule', 'jm-referral-system');
    }

    /**
     * @param array<string, mixed>|null $pkg
     * @param array<int, string> $owner_names
     * @return array<string, mixed>
     */
    private function package_fields(?array $pkg, array $owner_names): array
    {
        if (null === $pkg) {
            return [
                'prepared_at_label'          => '—',
                'prepared_by_name'           => '—',
                'sent_at_label'              => '—',
                'sent_by_name'               => '—',
                'send_method_label'          => '—',
                'package_recipient'          => '—',
                'submission_reference'       => '—',
            ];
        }

        $prepared_at = (string) ($pkg['prepared_at'] ?? '');
        $sent_at     = (string) ($pkg['sent_at'] ?? '');
        $prepared_by = absint($pkg['prepared_by'] ?? 0);
        $sent_by     = absint($pkg['sent_by'] ?? 0);
        $method      = (string) ($pkg['send_method'] ?? '');
        $recipient   = (string) ($pkg['recipient'] ?? '');
        $ref         = (string) ($pkg['submission_reference'] ?? '');

        return [
            'prepared_at_label'    => '' !== $prepared_at
                ? (string) mysql2date((string) get_option('date_format'), $prepared_at)
                : '—',
            'prepared_by_name'     => $prepared_by > 0
                ? ($owner_names[$prepared_by] ?? ($this->user_provider->get_display_name($prepared_by) ?: '—'))
                : '—',
            'sent_at_label'        => '' !== $sent_at
                ? (string) mysql2date((string) get_option('date_format'), $sent_at)
                : '—',
            'sent_by_name'         => $sent_by > 0
                ? ($owner_names[$sent_by] ?? ($this->user_provider->get_display_name($sent_by) ?: '—'))
                : '—',
            'send_method_label'    => '' !== $method ? PackageCost::send_method_label($method) : '—',
            'package_recipient'    => '' !== $recipient ? $recipient : '—',
            'submission_reference' => '' !== $ref ? $ref : '—',
        ];
    }

    /**
     * @param array<string, mixed>|null $pkg
     * @param array<string, mixed>|null $la
     * @return array<string, mixed>
     */
    private function authority_fields(?array $pkg, ?array $la): array
    {
        $sent_at = is_array($pkg) ? (string) ($pkg['sent_at'] ?? '') : '';
        $days_awaiting = null;
        if ('' !== $sent_at && false !== strtotime($sent_at)) {
            $days_awaiting = max(
                0,
                (int) floor(((int) current_time('timestamp') - (int) strtotime($sent_at)) / DAY_IN_SECONDS)
            );
        }

        $decision = is_array($la) ? (string) ($la['decision'] ?? '') : '';
        $decision_label = '—';
        $submission_status = __('Awaiting authority decision', 'jm-referral-system');

        if ('' !== $decision && LaDecision::is_valid_decision($decision)) {
            $decision_label    = LaDecision::decision_label($decision);
            $submission_status = $decision_label;
        } elseif (is_array($pkg) && PackageCost::is_sent((string) ($pkg['status'] ?? ''))) {
            $submission_status = __('Submitted — awaiting decision', 'jm-referral-system');
        } elseif (is_array($pkg) && '' !== (string) ($pkg['status'] ?? '')) {
            $submission_status = PackageCost::status_label((string) $pkg['status']);
        } else {
            $submission_status = __('No submission on record', 'jm-referral-system');
        }

        return [
            'days_awaiting_authority' => $days_awaiting,
            'authority_status_label'  => $submission_status,
            'la_decision_label'       => $decision_label,
            'package_sent_label'      => '' !== $sent_at
                ? (string) mysql2date((string) get_option('date_format'), $sent_at)
                : '—',
        ];
    }

    /**
     * @param array<string, mixed>|null $occ
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function placement_fields(?array $occ, array $row): array
    {
        $home_name     = is_array($occ) ? (string) ($occ['home_name'] ?? '') : '';
        $bedroom_label = is_array($occ) ? (string) ($occ['bedroom_label'] ?? '') : '';
        $move_in       = is_array($occ) ? (string) ($occ['move_in_date'] ?? '') : '';
        $today         = wp_date('Y-m-d', (int) current_time('timestamp'));

        $days_until = null;
        $move_label = '—';
        $move_note  = '';

        if ('' !== $move_in) {
            $move_label = (string) mysql2date((string) get_option('date_format'), $move_in . ' 00:00:00');
            if ($move_in > $today) {
                $days_until = (int) floor((strtotime($move_in . ' 00:00:00') - strtotime($today . ' 00:00:00')) / DAY_IN_SECONDS);
                $move_note  = sprintf(
                    /* translators: %d: days until move-in */
                    __('%d days until move-in', 'jm-referral-system'),
                    max(0, $days_until)
                );
            } elseif ($move_in === $today) {
                $days_until = 0;
                $move_note  = __('Move-in today', 'jm-referral-system');
            } else {
                $move_note = __('Move date passed', 'jm-referral-system');
            }
        }

        return [
            'destination_setting' => (string) ($row['care_setting'] ?? '') ?: '—',
            'home_name'           => '' !== $home_name ? $home_name : '—',
            'bedroom_label'       => '' !== $bedroom_label ? $bedroom_label : '—',
            'move_in_date'        => $move_in,
            'move_in_label'       => $move_label,
            'days_until_move_in'  => $days_until,
            'move_in_note'        => $move_note,
        ];
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function assessment_map_for_referrals(array $ids): array
    {
        global $wpdb;

        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if ([] === $ids) {
            return [];
        }

        $table = Tables::referral_assessments_table();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT referral_id, assessor_user_id, assessment_date, outcome, scheduled_at,
                    assessment_location_name, assessment_location_type, assessment_location_address,
                    assessment_contact_name
                FROM {$table}
                WHERE referral_id IN ({$placeholders})",
                ...$ids
            ),
            ARRAY_A
        );

        $map = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $rid = absint($row['referral_id'] ?? 0);
                if ($rid > 0) {
                    $map[$rid] = $row;
                }
            }
        }

        return $map;
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function la_decision_map_for_referrals(array $ids): array
    {
        if (null === $this->la_decision_repository) {
            return [];
        }

        return $this->la_decision_repository->current_decision_map_for_referrals($ids);
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function occupancy_map_for_referrals(array $ids): array
    {
        if (null === $this->occupancy_repository) {
            return [];
        }

        return $this->occupancy_repository->active_occupancy_detail_map_for_referrals($ids);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function owner_name_map_from_rows(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = absint($row['assigned_to'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
            $by = absint($row['interest_expressed_by'] ?? 0);
            if ($by > 0) {
                $ids[$by] = true;
            }
        }

        return $this->resolve_user_names(array_keys($ids));
    }

    /**
     * @param array<int, string> $owner_names
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $assessment_map
     * @param array<int, array<string, mixed>> $package_map
     * @return array<int, string>
     */
    private function enrich_owner_names_from_related(
        array $owner_names,
        array $rows,
        array $assessment_map,
        array $package_map
    ): array {
        $ids = [];
        foreach ($rows as $row) {
            $by = absint($row['interest_expressed_by'] ?? 0);
            if ($by > 0 && ! isset($owner_names[$by])) {
                $ids[$by] = true;
            }
        }
        foreach ($assessment_map as $assess) {
            $aid = absint($assess['assessor_user_id'] ?? 0);
            if ($aid > 0 && ! isset($owner_names[$aid])) {
                $ids[$aid] = true;
            }
        }
        foreach ($package_map as $pkg) {
            foreach (['prepared_by', 'sent_by'] as $field) {
                $uid = absint($pkg[$field] ?? 0);
                if ($uid > 0 && ! isset($owner_names[$uid])) {
                    $ids[$uid] = true;
                }
            }
        }

        if ([] === $ids) {
            return $owner_names;
        }

        return $owner_names + $this->resolve_user_names(array_keys($ids));
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, string>
     */
    private function resolve_user_names(array $ids): array
    {
        $map = [];
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id <= 0) {
                continue;
            }
            $map[$id] = $this->user_provider->get_display_name($id) ?: __('Unknown', 'jm-referral-system');
        }

        return $map;
    }

    private function format_money(float $amount): string
    {
        return '£' . number_format_i18n($amount, 2);
    }

    /**
     * @param array<int, string> $active_slugs
     * @return array<int, array{name: string, referrals_owned: int}>
     */
    private function build_ownership_stats(array $active_slugs, ?int $access): array
    {
        $rows = $this->referral_repository->count_ownership_by_pipeline_slugs($active_slugs, $access);
        if ([] === $rows) {
            return [];
        }

        $user_ids = [];
        foreach ($rows as $row) {
            $oid = absint($row['assigned_to'] ?? 0);
            if ($oid > 0) {
                $user_ids[$oid] = true;
            }
        }
        $names = $this->resolve_user_names(array_keys($user_ids));

        $out = [];
        foreach ($rows as $row) {
            $oid = absint($row['assigned_to'] ?? 0);
            $out[] = [
                'name'            => $oid > 0
                    ? ($names[$oid] ?? __('Unknown', 'jm-referral-system'))
                    : __('Unassigned', 'jm-referral-system'),
                'referrals_owned' => absint($row['referrals_owned'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function build_homes_payload(): array
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_HOMES)) {
            return [
                'estate' => null,
                'homes'  => [],
            ];
        }

        if (null === $this->home_service || null === $this->occupancy_repository) {
            return [
                'estate' => null,
                'homes'  => [],
            ];
        }

        $estate_split = $this->occupancy_repository->estate_occupancy_split();
        $homes        = $this->home_service->list(['status' => 'active']);
        $home_ids     = [];
        foreach ($homes as $home) {
            $id = absint($home['id'] ?? 0);
            if ($id > 0) {
                $home_ids[] = $id;
            }
        }
        $split_map = $this->occupancy_repository->capacity_occupancy_split_by_home_ids($home_ids);
        $cards     = [];

        foreach ($homes as $home) {
            $id = absint($home['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $metrics = $split_map[$id] ?? [
                'capacity'        => absint($home['capacity'] ?? 0),
                'occupied_now'    => 0,
                'future_move_ins' => 0,
                'vacancies_today' => absint($home['capacity'] ?? 0),
                'projected'       => 0,
            ];
            $capacity     = (int) $metrics['capacity'];
            $occupied_now = (int) $metrics['occupied_now'];
            $future       = (int) $metrics['future_move_ins'];
            $projected    = (int) $metrics['projected'];

            $cards[] = [
                'id'                   => $id,
                'name'                 => (string) ($home['name'] ?? ''),
                'area'                 => trim(implode(', ', array_filter([
                    (string) ($home['city'] ?? ''),
                    (string) ($home['postcode'] ?? ''),
                ]))),
                'capacity'             => $capacity,
                'occupied_now'         => $occupied_now,
                'vacancies_today'      => (int) $metrics['vacancies_today'],
                'future_move_ins'      => $future,
                'projected'            => $projected,
                'occupancy_pct'        => $capacity > 0 ? round(($occupied_now / $capacity) * 100, 1) : 0.0,
                'projected_pct'        => $capacity > 0 ? round(($projected / $capacity) * 100, 1) : 0.0,
                // Bed visual uses occupied-now only (not future).
                'occupied'             => $occupied_now,
                'vacant'               => (int) $metrics['vacancies_today'],
                'view_url'             => PortalUrls::home_view($id),
            ];
        }

        return [
            'estate' => [
                'capacity'            => (int) $estate_split['capacity'],
                'occupied_now'        => (int) $estate_split['occupied_now'],
                'vacancies_today'     => (int) $estate_split['vacancies_today'],
                'future_move_ins'     => (int) $estate_split['future_move_ins'],
                'projected'           => (int) $estate_split['projected'],
                'occupancy_pct'       => (float) $estate_split['occupancy_pct_today'],
                'projected_pct'       => (float) $estate_split['projected_pct'],
                // Backward-compatible keys for any residual template refs.
                'occupied'            => (int) $estate_split['occupied_now'],
                'vacant'              => (int) $estate_split['vacancies_today'],
            ],
            'homes'  => $cards,
        ];
    }
}
