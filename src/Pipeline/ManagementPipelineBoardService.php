<?php

namespace JMReferral\Pipeline;

use JMReferral\Assessment\ReferralAssessmentRepository;
use JMReferral\Database\Tables;
use JMReferral\Homes\HomeService;
use JMReferral\Homes\OccupancyService;
use JMReferral\PackageCost\PackageCostRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Users\UserProvider;

/**
 * Read-only Management Dashboard board (Phase 2A).
 *
 * Composes existing services; no mutations, no new policy thresholds.
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
        private ?OccupancyService $occupancy_service = null
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
        $active_rows  = $this->referral_repository->find_active_pipeline_referrals(
            $active_slugs,
            $access,
            self::QUEUE_LIMIT
        );

        $here_now_groups = $this->group_here_now($active_rows);
        $reached_counts  = $this->build_reached_counts($access, $here_now_groups);

        $all_row_ids = [];
        foreach ($here_now_groups as $rows) {
            foreach ($rows as $row) {
                $all_row_ids[] = absint($row['id'] ?? 0);
            }
        }
        $all_row_ids = array_values(array_filter(array_unique($all_row_ids)));

        $package_map    = $this->package_cost_repository->current_package_map_for_referrals($all_row_ids);
        $assessment_map = $this->assessment_map_for_referrals($all_row_ids);
        $owner_names    = $this->owner_name_map($active_rows);

        $stages = [];
        foreach (VisualStageMap::definitions() as $key => $def) {
            $here_rows = $here_now_groups[$key] ?? [];
            $table_rows = 'now' === $mode
                ? $this->build_table_rows($key, $here_rows, $package_map, $assessment_map, $owner_names)
                : $this->build_reached_table_rows($key, $access, $package_map, $assessment_map, $owner_names, $here_rows);

            $stages[] = [
                'key'           => $key,
                'order'         => (int) $def['order'],
                'name'          => (string) $def['name'],
                'colour'        => (string) $def['colour'],
                'question'      => (string) $def['question'],
                'here_now'      => count($here_rows),
                'reached'       => (int) ($reached_counts[$key] ?? 0),
                'proposed_value'=> $this->sum_proposed_value($here_rows, $package_map),
                'rows'          => $table_rows,
            ];
        }

        $attention = $this->attention_service->get_dashboard_payload('portal', []);
        $actions   = [];
        if (! empty($attention['show']) && is_array($attention['needs_attention'] ?? null)) {
            foreach ($attention['needs_attention'] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $actions[] = [
                    'referral_id'     => absint($item['referral_id'] ?? 0),
                    'referral_number' => (string) ($item['referral_number'] ?? ''),
                    'client_name'     => (string) ($item['client_name'] ?? ''),
                    'stage_label'     => (string) ($item['stage_label'] ?? ''),
                    'severity'       => (string) ($item['severity'] ?? 'medium'),
                    'reason_labels'   => is_array($item['reason_labels'] ?? null) ? $item['reason_labels'] : [],
                    'next_action'     => (string) ($item['next_action'] ?? ''),
                    'owner_name'      => (string) ($item['owner_name'] ?? ''),
                    'view_url'        => (string) ($item['view_url'] ?? ''),
                    'waiting_label'   => (string) ($item['waiting_label'] ?? ''),
                ];
            }
        }

        $live_count = count($active_rows);
        $proposed_live = $this->sum_proposed_value($active_rows, $package_map);
        $awaiting_la = count($here_now_groups[VisualStageMap::AUTHORITY_CONSIDERATION] ?? []);
        $transition  = count($here_now_groups[VisualStageMap::PLACEMENT_TRANSITION] ?? []);

        $outcome_counts = $this->referral_repository->count_active_by_pipeline_slugs(
            [PipelineStage::CARE_COMMENCED, PipelineStage::DECLINED, PipelineStage::NOT_PROCEEDING],
            $access
        );
        $care_commenced = (int) ($outcome_counts[PipelineStage::CARE_COMMENCED] ?? 0);
        $declined       = (int) ($outcome_counts[PipelineStage::DECLINED] ?? 0);
        $not_proceeding = (int) ($outcome_counts[PipelineStage::NOT_PROCEEDING] ?? 0);

        // Point-in-time population currently on any canonical acquisition stage (active or terminal).
        // Not a date-bounded Phase 3 cohort — that lives on Reports → Acquisition Pipeline.
        $acquisition_current = $live_count + $care_commenced + $declined + $not_proceeding;

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

        $ownership = $this->build_ownership_stats($active_rows, $owner_names);

        return [
            'show'              => true,
            'mode'              => $mode,
            'masthead'          => [
                'company'      => __('J&M Healthcare Services', 'jm-referral-system'),
                'subtitle'     => __('Referral to placement pipeline', 'jm-referral-system'),
                'period_label' => __('Reporting view', 'jm-referral-system'),
                'period_value' => __('Live pipeline (point in time)', 'jm-referral-system'),
                'updated_label'=> __('Last updated', 'jm-referral-system'),
                'updated_value'=> wp_date(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    (int) current_time('timestamp')
                ),
                'audience'     => __('Prepared for', 'jm-referral-system'),
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
            'actions_high'      => count(array_filter($actions, static fn (array $a): bool => 'critical' === ($a['severity'] ?? '') || 'high' === ($a['severity'] ?? ''))),
            'homes'             => $this->build_homes_payload(),
            'show_homes'        => Capabilities::current_user_can(Capabilities::VIEW_HOMES),
            'ownership'         => $ownership,
            'show_ownership'    => [] !== $ownership,
            'show_team_tab'     => [] !== $ownership,
            'show_funding_tab'  => false,
            'deferred'          => [
                'team_performance_full' => true,
                'funding_authority'     => true,
            ],
            'mapping_note'      => __(
                'Visual stages group canonical pipeline stages for presentation only. Care commenced is an outcome, not an active transition row.',
                'jm-referral-system'
            ),
            'legacy_note'       => __(
                '“All who reached” uses stage history only. Referrals without history are not given fabricated earlier milestones.',
                'jm-referral-system'
            ),
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
            // care_commenced and other terminals are not in VisualStageMap active slugs.
            $groups[$key][] = $row;
        }

        return $groups;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $here_now_groups
     * @return array<string, int>
     */
    private function build_reached_counts(?int $access, array $here_now_groups): array
    {
        $out = [];
        foreach (VisualStageMap::definitions() as $key => $def) {
            $ids = $this->stage_history_repository->find_referral_ids_reached_slugs($def['slugs'], $access, 1000);
            $id_set = array_fill_keys($ids, true);

            // Legacy: currently here-now but no history into these slugs — count current presence only
            // (does not invent earlier stages).
            foreach ($here_now_groups[$key] ?? [] as $row) {
                $rid = absint($row['id'] ?? 0);
                if ($rid > 0) {
                    $id_set[$rid] = true;
                }
            }

            $out[$key] = count($id_set);
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array{package_total: string|null, status: string, currency: string}> $package_map
     * @param array<int, array<string, mixed>> $assessment_map
     * @param array<int, string> $owner_names
     * @return array<int, array<string, mixed>>
     */
    private function build_table_rows(
        string $visual_key,
        array $rows,
        array $package_map,
        array $assessment_map,
        array $owner_names
    ): array {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->map_row($visual_key, $row, $package_map, $assessment_map, $owner_names);
        }

        return $out;
    }

    /**
     * All who reached: history IDs plus here-now rows; load referral summaries without fabricating history.
     *
     * @param array<int, array{package_total: string|null, status: string, currency: string}> $package_map
     * @param array<int, array<string, mixed>> $assessment_map
     * @param array<int, string> $owner_names
     * @param array<int, array<string, mixed>> $here_rows
     * @return array<int, array<string, mixed>>
     */
    private function build_reached_table_rows(
        string $visual_key,
        ?int $access,
        array &$package_map,
        array &$assessment_map,
        array &$owner_names,
        array $here_rows
    ): array {
        $slugs = VisualStageMap::slugs_for_visual($visual_key);
        $ids   = $this->stage_history_repository->find_referral_ids_reached_slugs($slugs, $access, self::QUEUE_LIMIT);
        $id_set = array_fill_keys($ids, true);
        foreach ($here_rows as $row) {
            $rid = absint($row['id'] ?? 0);
            if ($rid > 0) {
                $id_set[$rid] = true;
            }
        }
        $ids = array_keys($id_set);
        if ([] === $ids) {
            return [];
        }

        $missing_pkg = array_values(array_diff($ids, array_keys($package_map)));
        if ([] !== $missing_pkg) {
            $package_map += $this->package_cost_repository->current_package_map_for_referrals($missing_pkg);
        }
        $missing_assess = array_values(array_diff($ids, array_keys($assessment_map)));
        if ([] !== $missing_assess) {
            $assessment_map += $this->assessment_map_for_referrals($missing_assess);
        }

        $referrals = $this->load_referral_summaries($ids, $access);
        foreach ($referrals as $row) {
            $oid = absint($row['assigned_to'] ?? 0);
            if ($oid > 0 && ! isset($owner_names[$oid])) {
                $owner_names[$oid] = $this->user_provider->get_display_name($oid) ?: __('Unassigned', 'jm-referral-system');
            }
        }

        $out = [];
        foreach ($referrals as $row) {
            $out[] = $this->map_row($visual_key, $row, $package_map, $assessment_map, $owner_names);
        }

        return $out;
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
                r.created_at, s.slug AS pipeline_stage_slug, s.name AS pipeline_stage_name
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
     * @param array<int, array{package_total: string|null, status: string, currency: string}> $package_map
     * @param array<int, array<string, mixed>> $assessment_map
     * @param array<int, string> $owner_names
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function map_row(
        string $visual_key,
        array $row,
        array $package_map,
        array $assessment_map,
        array $owner_names
    ): array {
        $id = absint($row['id'] ?? 0);
        $slug = (string) ($row['pipeline_stage_slug'] ?? '');
        $owner_id = absint($row['assigned_to'] ?? 0);
        $pkg = $package_map[$id] ?? null;
        $assess = $assessment_map[$id] ?? null;

        $entered = (string) ($row['workflow_stage_entered_at'] ?? '');
        $waiting_days = null;
        if ('' !== $entered && false !== strtotime($entered)) {
            $waiting_days = (int) floor(((int) current_time('timestamp') - (int) strtotime($entered)) / DAY_IN_SECONDS);
        }

        $base = [
            'referral_id'     => $id,
            'referral_number' => (string) ($row['referral_number'] ?? ''),
            'client_name'     => (string) ($row['client_name'] ?? ''),
            'priority'        => (string) ($row['priority'] ?? ''),
            'stage_slug'      => $slug,
            'stage_label'     => PipelineStage::is_canonical($slug) ? PipelineStage::label($slug) : (string) ($row['pipeline_stage_name'] ?? ''),
            'owner_name'      => $owner_id > 0
                ? ($owner_names[$owner_id] ?? __('Unknown', 'jm-referral-system'))
                : __('Unassigned', 'jm-referral-system'),
            'waiting_days'    => $waiting_days,
            'view_url'        => PortalUrls::referral($id),
            'created_at'      => (string) ($row['created_at'] ?? ''),
            'proposed_value'  => null !== $pkg && null !== $pkg['package_total']
                ? $this->format_money((float) $pkg['package_total'])
                : '—',
            'package_status'  => null !== $pkg ? (string) $pkg['status'] : '',
        ];

        if (VisualStageMap::ASSESSMENT === $visual_key || VisualStageMap::APPOINTMENT_SET === $visual_key) {
            $base['scheduled_at'] = is_array($assess) ? (string) ($assess['scheduled_at'] ?? '') : '';
            $base['assessment_date'] = is_array($assess) ? (string) ($assess['assessment_date'] ?? '') : '';
            $base['outcome'] = is_array($assess) ? (string) ($assess['outcome'] ?? '') : '';
            $base['location_name'] = is_array($assess) ? (string) ($assess['assessment_location_name'] ?? '') : '';
            $assessor_id = is_array($assess) ? absint($assess['assessor_user_id'] ?? 0) : 0;
            $base['assessor_name'] = $assessor_id > 0
                ? ($this->user_provider->get_display_name($assessor_id) ?: '—')
                : '—';
        }

        if (VisualStageMap::PACKAGE_COSTING === $visual_key || VisualStageMap::AUTHORITY_CONSIDERATION === $visual_key) {
            $base['care_setting'] = (string) ($row['care_setting'] ?? '');
        }

        return $base;
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
                    assessment_location_name, assessment_location_type
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
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function owner_name_map(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = absint($row['assigned_to'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        $map = [];
        foreach (array_keys($ids) as $id) {
            $map[$id] = $this->user_provider->get_display_name($id) ?: __('Unknown', 'jm-referral-system');
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array{package_total: string|null, status: string, currency: string}> $package_map
     */
    private function sum_proposed_value(array $rows, array $package_map): float
    {
        $sum = 0.0;
        foreach ($rows as $row) {
            $id = absint($row['id'] ?? $row['referral_id'] ?? 0);
            $pkg = $package_map[$id] ?? null;
            if (null === $pkg || null === $pkg['package_total']) {
                continue;
            }
            $sum += (float) $pkg['package_total'];
        }

        return $sum;
    }

    private function format_money(float $amount): string
    {
        return '£' . number_format_i18n($amount, 2);
    }

    /**
     * Current ownership counts among live pipeline referrals (existing assigned_to only).
     *
     * @param array<int, array<string, mixed>> $active_rows
     * @param array<int, string> $owner_names
     * @return array<int, array{name: string, referrals_owned: int}>
     */
    private function build_ownership_stats(array $active_rows, array $owner_names): array
    {
        $counts = [];
        foreach ($active_rows as $row) {
            $oid = absint($row['assigned_to'] ?? 0);
            $key = $oid > 0 ? (string) $oid : '0';
            if (! isset($counts[$key])) {
                $counts[$key] = 0;
            }
            ++$counts[$key];
        }

        $out = [];
        foreach ($counts as $key => $n) {
            $oid = (int) $key;
            $out[] = [
                'name'            => $oid > 0
                    ? ($owner_names[$oid] ?? __('Unknown', 'jm-referral-system'))
                    : __('Unassigned', 'jm-referral-system'),
                'referrals_owned' => $n,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['referrals_owned'] <=> $a['referrals_owned']);

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

        if (null === $this->home_service || null === $this->occupancy_service) {
            return [
                'estate' => null,
                'homes'  => [],
            ];
        }

        $estate = $this->occupancy_service->estate_summary();
        $homes  = $this->home_service->list(['status' => 'active']);
        $cards  = [];

        foreach ($homes as $home) {
            $id = absint($home['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $cards[] = [
                'id'            => $id,
                'name'          => (string) ($home['name'] ?? ''),
                'area'          => trim(implode(', ', array_filter([
                    (string) ($home['city'] ?? ''),
                    (string) ($home['postcode'] ?? ''),
                ]))),
                'capacity'      => absint($home['capacity'] ?? 0),
                'occupied'      => absint($home['occupied'] ?? 0),
                'vacant'        => absint($home['vacant'] ?? 0),
                'occupancy_pct' => (float) ($home['occupancy_pct'] ?? 0),
                'view_url'      => PortalUrls::home_view($id),
            ];
        }

        return [
            'estate' => $estate,
            'homes'  => $cards,
        ];
    }
}
