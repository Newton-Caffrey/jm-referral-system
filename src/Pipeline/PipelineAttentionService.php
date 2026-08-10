<?php

namespace JMReferral\Pipeline;

use JMReferral\PackageCost\PackageCost;
use JMReferral\PackageCost\PackageCostRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\CareSetting;
use JMReferral\Referral\ReferralFilters;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Referral\ReferralViewController;
use JMReferral\Users\UserProvider;

/**
 * Pipeline overview, action queue, and Needs Attention (Phase 3H).
 *
 * Visibility only — no mutations, emails, or stage transitions.
 */
class PipelineAttentionService
{
    private const QUEUE_LIMIT = 300;

    public function __construct(
        private ReferralRepository $referral_repository,
        private PackageCostRepository $package_cost_repository,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider
    ) {
    }

    /**
     * Whether the current user may see commercial pipeline dashboard sections.
     *
     * Support Workers retain scoped dashboard KPIs but do not get the acquisition
     * pipeline / Needs Attention commercial surface.
     */
    public function current_user_can_view_pipeline_dashboard(): bool
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_DASHBOARD)) {
            return false;
        }

        if (! Capabilities::current_user_can(Capabilities::VIEW_REFERRALS)) {
            return false;
        }

        // Scoped Support Workers must not gain commercial acquisition dashboards.
        if ($this->access_policy->should_scope_to_assigned()) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function get_dashboard_payload(string $ui_context = 'portal', array $request_filters = []): array
    {
        if (! $this->current_user_can_view_pipeline_dashboard()) {
            return [
                'show' => false,
            ];
        }

        $access = $this->access_policy->get_assigned_user_constraint();
        $filters = $this->normalize_dashboard_filters($request_filters);
        $now_ts = (int) current_time('timestamp');
        $now_mysql = current_time('mysql');

        $active_slugs = PipelineInternalTargets::configurable_stages();
        $terminal_slugs = [
            PipelineStage::CARE_COMMENCED,
            PipelineStage::DECLINED,
            PipelineStage::NOT_PROCEEDING,
        ];

        $active_counts = $this->referral_repository->count_active_by_pipeline_slugs($active_slugs, $access);
        $terminal_counts = $this->referral_repository->count_active_by_pipeline_slugs($terminal_slugs, $access);
        $legacy_count = $this->referral_repository->count_active_legacy_workflow($access);

        $rows = $this->referral_repository->find_active_pipeline_referrals($active_slugs, $access, self::QUEUE_LIMIT);
        $items = $this->enrich_rows($rows, $ui_context, $now_ts, $now_mysql);

        $attention_by_stage = [];
        foreach ($active_slugs as $slug) {
            $attention_by_stage[$slug] = 0;
        }

        $needs_attention = [];
        foreach ($items as $item) {
            if (! empty($item['needs_attention'])) {
                $needs_attention[] = $item;
                $slug = (string) ($item['stage_slug'] ?? '');
                if (isset($attention_by_stage[$slug])) {
                    ++$attention_by_stage[$slug];
                }
            }
        }

        usort($needs_attention, [$this, 'compare_attention_items']);

        $action_queue = $this->apply_queue_filters($items, $filters);
        usort($action_queue, [$this, 'compare_action_queue_items']);

        $filtered_attention = $this->apply_queue_filters($needs_attention, $filters);
        // Needs Attention filter already implies attention-only.
        if (! empty($filters['needs_attention_only'])) {
            $filtered_attention = $needs_attention;
            if ('' !== $filters['pipeline_stage']
                || '' !== $filters['priority']
                || $filters['assigned_to'] > 0
                || ! empty($filters['unassigned'])
                || ! empty($filters['my_referrals'])
            ) {
                $filtered_attention = $this->apply_queue_filters($needs_attention, array_merge($filters, [
                    'needs_attention_only' => false,
                ]));
                $filtered_attention = array_values(array_filter(
                    $filtered_attention,
                    static fn (array $row): bool => ! empty($row['needs_attention'])
                ));
            }
        }

        $stage_cards = [];
        foreach ($active_slugs as $slug) {
            $stage_cards[] = [
                'slug'              => $slug,
                'label'             => PipelineStage::label($slug),
                'count'             => (int) ($active_counts[$slug] ?? 0),
                'attention_count'   => (int) ($attention_by_stage[$slug] ?? 0),
                'list_url'          => $this->stage_list_url($slug, $ui_context),
            ];
        }

        $outcome_cards = [];
        foreach ($terminal_slugs as $slug) {
            $outcome_cards[] = [
                'slug'     => $slug,
                'label'    => PipelineStage::label($slug),
                'count'    => (int) ($terminal_counts[$slug] ?? 0),
                'list_url' => $this->stage_list_url($slug, $ui_context),
            ];
        }

        return [
            'show'                 => true,
            'stage_cards'          => $stage_cards,
            'outcome_cards'        => $outcome_cards,
            'legacy_count'         => $legacy_count,
            'legacy_list_url'      => $this->stage_list_url(PipelineStage::FILTER_LEGACY, $ui_context),
            'action_queue'         => $action_queue,
            'needs_attention'      => $filtered_attention,
            'needs_attention_total'=> count($needs_attention),
            'filters'              => $filters,
            'filter_urls'          => $this->dashboard_filter_urls($ui_context, $filters),
            'priority_options'     => [
                'urgent' => __('Urgent', 'jm-referral-system'),
                'high'   => __('High', 'jm-referral-system'),
                'medium' => __('Medium', 'jm-referral-system'),
                'low'    => __('Low', 'jm-referral-system'),
            ],
            'stage_filter_options' => $this->stage_filter_options(),
            'sort_note'            => __(
                'Needs Attention order: next-action overdue, internal target exceeded, urgent/high priority, unassigned, then oldest waiting.',
                'jm-referral-system'
            ),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function enrich_rows(array $rows, string $ui_context, int $now_ts, string $now_mysql): array
    {
        if ([] === $rows) {
            return [];
        }

        $user_ids = [];
        $pc_ids = [];
        $transition_ids = [];

        foreach ($rows as $row) {
            $assigned = absint($row['assigned_to'] ?? 0);
            if ($assigned > 0) {
                $user_ids[$assigned] = true;
            }
            $slug = (string) ($row['pipeline_stage_slug'] ?? '');
            $rid = absint($row['id'] ?? 0);
            if (PipelineStage::PACKAGE_COST_REQUIRED === $slug && $rid > 0) {
                $pc_ids[] = $rid;
            }
            if (PipelineStage::TRANSITION_PLANNING === $slug && $rid > 0) {
                $transition_ids[] = $rid;
            }
        }

        $names = [];
        foreach (array_keys($user_ids) as $uid) {
            $names[(int) $uid] = $this->user_provider->get_display_name((int) $uid);
        }

        $pc_status = $this->package_cost_repository->current_status_map_for_referrals($pc_ids);
        $occupancy_map = $this->referral_repository->active_occupancy_referral_id_map($transition_ids);
        $targets = PipelineInternalTargets::all();

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->build_item(
                $row,
                $ui_context,
                $now_ts,
                $now_mysql,
                $names,
                $pc_status,
                $occupancy_map,
                $targets
            );
        }

        return $items;
    }

    /**
     * @param array<string, mixed>      $row
     * @param array<int, string>        $names
     * @param array<int, string>        $pc_status
     * @param array<int, true>          $occupancy_map
     * @param array<string, int|null>   $targets
     * @return array<string, mixed>
     */
    private function build_item(
        array $row,
        string $ui_context,
        int $now_ts,
        string $now_mysql,
        array $names,
        array $pc_status,
        array $occupancy_map,
        array $targets
    ): array {
        $referral_id = absint($row['id'] ?? 0);
        $slug = (string) ($row['pipeline_stage_slug'] ?? '');
        $assigned = absint($row['assigned_to'] ?? 0);
        $priority = (string) ($row['priority'] ?? 'medium');
        $entered_at = (string) ($row['workflow_stage_entered_at'] ?? '');
        $due_at = trim((string) ($row['next_action_due_at'] ?? ''));
        $care_setting = CareSetting::normalize(
            null === ($row['care_setting'] ?? null) ? null : (string) $row['care_setting']
        );

        $waiting = PipelineWaitingTime::from_entered_at($entered_at, $now_ts);
        $reasons = [];

        if ($assigned <= 0) {
            $reasons[] = PipelineAttentionReason::UNASSIGNED;
        }

        if ('' !== $due_at && '0000-00-00 00:00:00' !== $due_at) {
            $due_ts = strtotime($due_at);
            if (false !== $due_ts && $due_ts < $now_ts) {
                $reasons[] = PipelineAttentionReason::NEXT_ACTION_OVERDUE;
            }
        }

        $target_hours = $targets[$slug] ?? null;
        $target_state = 'none';
        $target_label = '—';
        $exceeded_by_label = '';
        if (null !== $target_hours && $target_hours > 0 && ! empty($waiting['known'])) {
            $target_seconds = $target_hours * HOUR_IN_SECONDS;
            $wait_seconds = (int) ($waiting['seconds'] ?? 0);
            $target_label = PipelineWaitingTime::format_hours($target_hours);
            if ($wait_seconds > $target_seconds) {
                $reasons[] = PipelineAttentionReason::TARGET_EXCEEDED;
                $target_state = 'exceeded';
                $exceeded_by_label = PipelineWaitingTime::format_seconds($wait_seconds - $target_seconds);
            } else {
                $target_state = 'within';
            }
        }

        if (PipelineStage::ASSESSMENT_REVIEW_REQUIRED === $slug) {
            $reasons[] = PipelineAttentionReason::ASSESSMENT_REVIEW_REQUIRED;
        }

        if (PipelineStage::TRANSITION_PLANNING === $slug) {
            if (null === $care_setting) {
                $reasons[] = PipelineAttentionReason::CARE_SETTING_REQUIRED;
            } elseif (CareSetting::SUPPORTED_LIVING === $care_setting
                && empty($occupancy_map[$referral_id])
            ) {
                $reasons[] = PipelineAttentionReason::PLACEMENT_REQUIRED;
            }
        }

        $reasons = array_values(array_unique($reasons));
        $primary = $reasons[0] ?? '';
        $severity = '' !== $primary
            ? PipelineAttentionReason::severity($primary)
            : '';

        // Prefer strongest severity among reasons.
        foreach ($reasons as $code) {
            $sev = PipelineAttentionReason::severity($code);
            if ('' === $severity
                || PipelineAttentionReason::severity_rank($sev) < PipelineAttentionReason::severity_rank($severity)
            ) {
                $severity = $sev;
                $primary = $code;
            }
        }

        // Urgent/high unassigned elevates operational severity.
        if (in_array(PipelineAttentionReason::UNASSIGNED, $reasons, true)
            && in_array($priority, ['urgent', 'high'], true)
            && PipelineAttentionReason::severity_rank(PipelineAttentionReason::SEVERITY_HIGH)
                < PipelineAttentionReason::severity_rank($severity)
        ) {
            $severity = PipelineAttentionReason::SEVERITY_HIGH;
        }

        $next_action = $this->resolve_next_action($slug, $referral_id, $care_setting, $pc_status, $occupancy_map);

        return [
            'referral_id'           => $referral_id,
            'referral_number'       => (string) ($row['referral_number'] ?? ''),
            'client_name'           => (string) ($row['client_name'] ?? ''),
            'stage_slug'            => $slug,
            'stage_label'           => PipelineStage::label($slug),
            'next_action'           => $next_action,
            'priority'              => $priority,
            'priority_label'        => ucfirst($priority),
            'assigned_to'           => $assigned,
            'owner_name'            => $assigned > 0
                ? (string) ($names[$assigned] ?? __('Unknown', 'jm-referral-system'))
                : __('Unassigned', 'jm-referral-system'),
            'is_unassigned'         => $assigned <= 0,
            'entered_at'            => $entered_at,
            'waiting_label'         => (string) ($waiting['label'] ?? ''),
            'waiting_seconds'       => $waiting['seconds'],
            'waiting_known'         => ! empty($waiting['known']),
            'target_state'          => $target_state,
            'target_hours'          => $target_hours,
            'target_label'          => $target_label,
            'exceeded_by_label'     => $exceeded_by_label,
            'target_status_label'   => $this->target_status_label($target_state),
            'reasons'               => $reasons,
            'reason_labels'         => array_map([PipelineAttentionReason::class, 'label'], $reasons),
            'primary_reason'        => $primary,
            'primary_reason_label'  => '' !== $primary ? PipelineAttentionReason::label($primary) : '',
            'severity'             => $severity,
            'needs_attention'       => [] !== $reasons,
            'view_url'              => $this->referral_url($referral_id, $ui_context),
            'action_label'          => $this->action_label($slug, $reasons),
        ];
    }

    /**
     * @param array<int, string> $pc_status
     * @param array<int, true>   $occupancy_map
     */
    private function resolve_next_action(
        string $slug,
        int $referral_id,
        ?string $care_setting,
        array $pc_status,
        array $occupancy_map
    ): string {
        if (PipelineStage::PACKAGE_COST_REQUIRED === $slug) {
            $status = $pc_status[$referral_id] ?? '';
            if (PackageCost::is_prepared($status)) {
                return __('Send package cost to Local Authority', 'jm-referral-system');
            }

            return __('Prepare package cost', 'jm-referral-system');
        }

        if (PipelineStage::TRANSITION_PLANNING === $slug) {
            if (null === $care_setting) {
                return __('Select care setting', 'jm-referral-system');
            }
            if (CareSetting::SUPPORTED_LIVING === $care_setting && empty($occupancy_map[$referral_id])) {
                return __('Place resident', 'jm-referral-system');
            }

            return __('Confirm care commencement', 'jm-referral-system');
        }

        return PipelineStage::next_action($slug);
    }

    /**
     * @param array<int, string> $reasons
     */
    private function action_label(string $slug, array $reasons): string
    {
        if (in_array(PipelineAttentionReason::ASSESSMENT_REVIEW_REQUIRED, $reasons, true)) {
            return __('Review', 'jm-referral-system');
        }
        if (in_array(PipelineAttentionReason::PLACEMENT_REQUIRED, $reasons, true)
            || in_array(PipelineAttentionReason::CARE_SETTING_REQUIRED, $reasons, true)
            || PipelineStage::TRANSITION_PLANNING === $slug
        ) {
            return __('Open Transition', 'jm-referral-system');
        }

        return __('View', 'jm-referral-system');
    }

    private function target_status_label(string $state): string
    {
        return match ($state) {
            'within' => __('Within Target', 'jm-referral-system'),
            'exceeded' => __('Target Exceeded', 'jm-referral-system'),
            default => '—',
        };
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function compare_attention_items(array $a, array $b): int
    {
        $a_reasons = is_array($a['reasons'] ?? null) ? $a['reasons'] : [];
        $b_reasons = is_array($b['reasons'] ?? null) ? $b['reasons'] : [];

        $a_overdue = in_array(PipelineAttentionReason::NEXT_ACTION_OVERDUE, $a_reasons, true) ? 0 : 1;
        $b_overdue = in_array(PipelineAttentionReason::NEXT_ACTION_OVERDUE, $b_reasons, true) ? 0 : 1;
        if ($a_overdue !== $b_overdue) {
            return $a_overdue <=> $b_overdue;
        }

        $a_target = in_array(PipelineAttentionReason::TARGET_EXCEEDED, $a_reasons, true) ? 0 : 1;
        $b_target = in_array(PipelineAttentionReason::TARGET_EXCEEDED, $b_reasons, true) ? 0 : 1;
        if ($a_target !== $b_target) {
            return $a_target <=> $b_target;
        }

        $a_pri = $this->priority_rank((string) ($a['priority'] ?? ''));
        $b_pri = $this->priority_rank((string) ($b['priority'] ?? ''));
        if ($a_pri !== $b_pri) {
            return $a_pri <=> $b_pri;
        }

        $a_un = ! empty($a['is_unassigned']) ? 0 : 1;
        $b_un = ! empty($b['is_unassigned']) ? 0 : 1;
        if ($a_un !== $b_un) {
            return $a_un <=> $b_un;
        }

        $a_wait = $a['waiting_seconds'];
        $b_wait = $b['waiting_seconds'];
        if (null === $a_wait && null === $b_wait) {
            return absint($a['referral_id'] ?? 0) <=> absint($b['referral_id'] ?? 0);
        }
        if (null === $a_wait) {
            return 1;
        }
        if (null === $b_wait) {
            return -1;
        }

        // Oldest waiting first.
        return ((int) $b_wait) <=> ((int) $a_wait);
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function compare_action_queue_items(array $a, array $b): int
    {
        $a_pri = $this->priority_rank((string) ($a['priority'] ?? ''));
        $b_pri = $this->priority_rank((string) ($b['priority'] ?? ''));
        if ($a_pri !== $b_pri) {
            return $a_pri <=> $b_pri;
        }

        $a_wait = $a['waiting_seconds'];
        $b_wait = $b['waiting_seconds'];
        if (null !== $a_wait && null !== $b_wait && (int) $a_wait !== (int) $b_wait) {
            return ((int) $b_wait) <=> ((int) $a_wait);
        }

        return absint($a['referral_id'] ?? 0) <=> absint($b['referral_id'] ?? 0);
    }

    private function priority_rank(string $priority): int
    {
        return match ($priority) {
            'urgent' => 1,
            'high' => 2,
            'medium' => 3,
            'low' => 4,
            default => 5,
        };
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed>             $filters
     * @return array<int, array<string, mixed>>
     */
    private function apply_queue_filters(array $items, array $filters): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! empty($filters['needs_attention_only']) && empty($item['needs_attention'])) {
                continue;
            }
            if ('' !== ($filters['pipeline_stage'] ?? '')
                && (string) ($item['stage_slug'] ?? '') !== (string) $filters['pipeline_stage']
            ) {
                continue;
            }
            if ('' !== ($filters['priority'] ?? '')
                && (string) ($item['priority'] ?? '') !== (string) $filters['priority']
            ) {
                continue;
            }
            if (! empty($filters['unassigned']) && empty($item['is_unassigned'])) {
                continue;
            }
            if (! empty($filters['my_referrals'])) {
                $uid = get_current_user_id();
                if ($uid <= 0 || absint($item['assigned_to'] ?? 0) !== $uid) {
                    continue;
                }
            } elseif (($filters['assigned_to'] ?? 0) > 0
                && absint($item['assigned_to'] ?? 0) !== absint($filters['assigned_to'])
            ) {
                continue;
            }

            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function normalize_dashboard_filters(array $request): array
    {
        $stage = sanitize_key((string) ($request['pipeline_stage'] ?? ''));
        if ('' !== $stage && ! PipelineStage::is_canonical($stage)) {
            $stage = '';
        }

        $priority = sanitize_key((string) ($request['priority'] ?? ''));
        if (! in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $priority = '';
        }

        $quick = sanitize_key((string) ($request['quick'] ?? ''));
        $my = 'my' === $quick || ! empty($request['my_referrals']);
        $unassigned = 'unassigned' === $quick || ! empty($request['unassigned']);
        $needs = 'attention' === $quick || ! empty($request['needs_attention']);

        $assigned_to = absint($request['assigned_to'] ?? 0);
        if ($my || $unassigned) {
            $assigned_to = 0;
        }

        return [
            'pipeline_stage'       => $stage,
            'priority'             => $priority,
            'assigned_to'          => $assigned_to,
            'my_referrals'         => $my,
            'unassigned'           => $unassigned,
            'needs_attention_only' => $needs,
            'quick'                => $quick,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function stage_filter_options(): array
    {
        $options = ['' => __('All active stages', 'jm-referral-system')];
        foreach (PipelineInternalTargets::configurable_stages() as $slug) {
            $options[$slug] = PipelineStage::label($slug);
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, string>
     */
    private function dashboard_filter_urls(string $ui_context, array $filters): array
    {
        $base = 'portal' === $ui_context
            ? PortalUrls::dashboard()
            : admin_url('admin.php?page=jm-referrals');

        $build = static function (array $args) use ($base): string {
            return add_query_arg($args, $base);
        };

        return [
            'all'         => $build([]),
            'my'          => $build(['jmrs_pipe_quick' => 'my']),
            'unassigned'  => $build(['jmrs_pipe_quick' => 'unassigned']),
            'attention'   => $build(['jmrs_pipe_quick' => 'attention']),
            'current'     => $build($this->filters_to_query_args($filters)),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, scalar>
     */
    private function filters_to_query_args(array $filters): array
    {
        $args = [];
        if (! empty($filters['quick'])) {
            $args['jmrs_pipe_quick'] = (string) $filters['quick'];
        }
        if ('' !== ($filters['pipeline_stage'] ?? '')) {
            $args['jmrs_pipe_stage'] = (string) $filters['pipeline_stage'];
        }
        if ('' !== ($filters['priority'] ?? '')) {
            $args['jmrs_pipe_priority'] = (string) $filters['priority'];
        }
        if (($filters['assigned_to'] ?? 0) > 0) {
            $args['jmrs_pipe_assigned_to'] = absint($filters['assigned_to']);
        }

        return $args;
    }

    /**
     * Read dashboard filter params from $_GET.
     *
     * @return array<string, mixed>
     */
    public function filters_from_request(): array
    {
        return $this->normalize_dashboard_filters([
            'pipeline_stage' => isset($_GET['jmrs_pipe_stage'])
                ? sanitize_key(wp_unslash($_GET['jmrs_pipe_stage']))
                : '',
            'priority' => isset($_GET['jmrs_pipe_priority'])
                ? sanitize_key(wp_unslash($_GET['jmrs_pipe_priority']))
                : '',
            'assigned_to' => isset($_GET['jmrs_pipe_assigned_to'])
                ? absint($_GET['jmrs_pipe_assigned_to'])
                : 0,
            'quick' => isset($_GET['jmrs_pipe_quick'])
                ? sanitize_key(wp_unslash($_GET['jmrs_pipe_quick']))
                : '',
        ]);
    }

    private function stage_list_url(string $slug, string $ui_context): string
    {
        $filters = [
            'pipeline_stage' => $slug,
            'archive_scope'  => 'active',
        ];

        if ('portal' === $ui_context) {
            return add_query_arg(
                [
                    'jmrs_pipeline_stage' => $slug,
                ],
                PortalUrls::referrals()
            );
        }

        return add_query_arg(
            ReferralFilters::list_query_args($filters, ReferralFilters::DEFAULT_PER_PAGE),
            admin_url('admin.php')
        );
    }

    private function referral_url(int $referral_id, string $ui_context): string
    {
        if ('portal' === $ui_context) {
            return PortalUrls::referral($referral_id);
        }

        return ReferralViewController::get_view_url($referral_id);
    }
}
