<?php

namespace JMReferral\Transition;

use JMReferral\CarePlan\ReferralCarePlanRepository;
use JMReferral\CarePlan\ReferralCarePlanService;
use JMReferral\CareTeam\CareTeamRepository;
use JMReferral\Homes\BedroomRepository;
use JMReferral\Homes\HomeRepository;
use JMReferral\Homes\OccupancyRepository;
use JMReferral\LaDecision\LaDecision;
use JMReferral\LaDecision\LaDecisionRepository;
use JMReferral\PackageCost\PackageCost;
use JMReferral\PackageCost\PackageCostRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Pipeline\PipelineStage;
use JMReferral\Pipeline\ReferralPipelineService;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\CareSetting;
use JMReferral\Referral\ReferralEditController;
use JMReferral\Referral\ReferralRetentionService;
use JMReferral\Scheduling\ScheduleRepository;
use JMReferral\Scheduling\ScheduleService;
use JMReferral\Users\UserProvider;
use JMReferral\Visits\ServiceLocationResolver;

/**
 * Derives Transition Planning readiness from existing care operations systems.
 *
 * Not a persistent workflow engine — read-only context for the referral view panel.
 * Phase 4H.1: panel clarity, occupancy integrity checks, responsibility display.
 * No target-home reservation, checklist, or readiness percentage.
 */
class TransitionPlanningService
{
    public function __construct(
        private ReferralPipelineService $pipeline_service,
        private LaDecisionRepository $la_decision_repository,
        private PackageCostRepository $package_cost_repository,
        private OccupancyRepository $occupancy_repository,
        private HomeRepository $home_repository,
        private BedroomRepository $bedroom_repository,
        private ReferralCarePlanRepository $care_plan_repository,
        private CareTeamRepository $care_team_repository,
        private ScheduleRepository $schedule_repository,
        private ServiceLocationResolver $service_location_resolver,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider,
        private ReferralRetentionService $retention_service
    ) {
    }

    /**
     * @param array<string, mixed> $referral
     * @return array<string, mixed>
     */
    public function get_panel_context(array $referral, string $ui_context = 'portal'): array
    {
        $referral_id = absint($referral['id'] ?? 0);
        $stage       = $this->pipeline_service->current_stage_slug($referral);
        $can_view    = $this->access_policy->can_view_referral($referral);

        $commenced_at = $this->nullable_mysql((string) ($referral['care_commenced_at'] ?? ''));
        $commenced_by = absint($referral['care_commenced_by'] ?? 0);
        $is_commenced = null !== $commenced_at
            || PipelineStage::CARE_COMMENCED === $stage;

        $show_panel = $can_view && (
            PipelineStage::TRANSITION_PLANNING === $stage
            || PipelineStage::CARE_COMMENCED === $stage
            || null !== $commenced_at
        );

        if (! $show_panel) {
            return [
                'show_panel' => false,
                'stage_slug' => $stage,
            ];
        }

        $decision = $this->la_decision_repository->find_current_for_referral($referral_id);
        $la_approved = is_array($decision)
            && LaDecision::DECISION_APPROVED === (string) ($decision['decision'] ?? '');

        $funding_raw = is_array($decision) && array_key_exists('funding_confirmed', $decision)
            ? $decision['funding_confirmed']
            : null;
        $funding_int = null === $funding_raw || '' === $funding_raw
            ? null
            : (int) $funding_raw;
        $funding_ok = LaDecision::FUNDING_YES === $funding_int;

        $care_setting = CareSetting::normalize(
            null === ($referral['care_setting'] ?? null)
                ? null
                : (string) $referral['care_setting']
        );

        $package = $this->package_cost_repository->find_current_for_referral($referral_id);
        $package_status = is_array($package) ? (string) ($package['status'] ?? '') : '';
        $package_sent = '' !== $package_status && PackageCost::is_sent($package_status);

        $occupancy = $this->occupancy_repository->current_for_referral($referral_id);
        $placement = $this->build_placement_summary($occupancy);
        $occupancy_integrity = $this->evaluate_occupancy_integrity($occupancy);

        $location = $this->service_location_resolver->resolve_for_referral($referral_id);
        $address_complete = CareSetting::is_own_home_address_complete($referral);

        $care_plan = $this->care_plan_repository->find_by_referral($referral_id);
        $plan_status = is_array($care_plan) ? (string) ($care_plan['plan_status'] ?? '') : '';
        $plan_active = ReferralCarePlanService::STATUS_ACTIVE === $plan_status;

        $team_members = $this->care_team_repository->get_active_by_referral($referral_id);
        $team_count   = count($team_members);
        $team_active  = $team_count > 0;

        $schedules = $this->schedule_repository->get_by_referral($referral_id);
        $active_schedule_count = 0;
        foreach ($schedules as $schedule) {
            if (ScheduleService::STATUS_ACTIVE === (string) ($schedule['status'] ?? '')) {
                ++$active_schedule_count;
            }
        }

        $hard = $this->evaluate_hard_requirements(
            $referral,
            $stage,
            $la_approved,
            $care_setting,
            $occupancy
        );

        $can_commence = $this->access_policy->can_commence_care($referral)
            && PipelineStage::TRANSITION_PLANNING === $stage
            && empty($hard['blocking'])
            && null === $commenced_at;

        $is_portal = 'portal' === $ui_context;
        $care_setting_url = $is_portal
            ? PortalUrls::referral_edit($referral_id)
            : ReferralEditController::get_edit_url($referral_id);

        $place_url = '';
        $show_place_prompt = CareSetting::SUPPORTED_LIVING === $care_setting
            && null === $occupancy
            && ! $is_commenced
            && PipelineStage::TRANSITION_PLANNING === $stage
            && ! $this->retention_service->is_archived($referral);

        if ($show_place_prompt
            && Capabilities::current_user_can(Capabilities::MANAGE_OCCUPANCIES)
        ) {
            $place_url = PortalUrls::occupancy_place(['referral_id' => $referral_id]);
        }

        $ops_links = $this->build_ops_links($referral_id, $is_portal);
        $responsibilities = $this->build_responsibility_summary($referral);

        $preferred_start = $this->nullable_date((string) ($referral['care_start_date'] ?? ''));

        $soft = $this->soft_warnings(
            $funding_ok,
            $funding_int,
            $care_setting,
            $address_complete,
            $plan_active,
            $plan_status,
            $team_active,
            $active_schedule_count,
            $occupancy
        );

        $next_action = $this->resolve_next_action(
            $is_commenced,
            $can_commence,
            $hard['blocking'],
            $care_setting,
            $place_url,
            $show_place_prompt
        );

        $placement_ready = null !== $occupancy && $occupancy_integrity['ok'];

        return [
            'show_panel'                 => true,
            'stage_slug'                 => $stage,
            'stage_label'                => PipelineStage::label($stage),
            'is_transition_planning'     => PipelineStage::TRANSITION_PLANNING === $stage,
            'is_care_commenced'          => $is_commenced,
            'can_commence'               => $can_commence,
            'hard_blockers'              => $hard['blocking'],
            'soft_warnings'              => $soft,
            'requires_funding_ack'       => ! $funding_ok,
            'la_approved'                => $la_approved,
            'la_decision_label'          => $la_approved
                ? LaDecision::decision_label(LaDecision::DECISION_APPROVED)
                : (is_array($decision)
                    ? LaDecision::decision_label((string) ($decision['decision'] ?? ''))
                    : __('Not recorded', 'jm-referral-system')),
            'funding_confirmed'          => $funding_int,
            'funding_confirmed_label'    => is_array($decision)
                ? LaDecision::funding_confirmed_label($funding_int)
                : __('Not Recorded', 'jm-referral-system'),
            'package_sent'               => $package_sent,
            'package_status_label'       => '' !== $package_status
                ? PackageCost::status_label($package_status)
                : __('Not prepared', 'jm-referral-system'),
            'care_setting'               => $care_setting,
            'care_setting_label'         => CareSetting::label($care_setting),
            'care_setting_required'      => null === $care_setting,
            'care_setting_url'           => $care_setting_url,
            'preferred_care_start_date'  => $preferred_start ?? '',
            'owner_name'                 => $responsibilities['owner_name'],
            'champion_name'              => $responsibilities['champion_name'],
            'transition_lead_name'       => $responsibilities['transition_lead_name'],
            'placement_ready'            => $placement_ready,
            'placement_home_name'        => (string) ($placement['home_name'] ?? ''),
            'placement_room_label'       => (string) ($placement['room_label'] ?? ''),
            'placement_move_in_date'     => (string) ($placement['move_in_date'] ?? ''),
            'occupancy_integrity_ok'     => $occupancy_integrity['ok'],
            'place_resident_url'         => $place_url,
            'show_place_prompt'          => $show_place_prompt && '' !== $place_url,
            'service_location_label'     => $location->label(),
            'own_home_address_complete'  => $address_complete,
            'own_home_address_summary'   => CareSetting::OWN_HOME === $care_setting
                ? $this->own_home_address_summary($referral)
                : '',
            'own_home_safe_summary'      => CareSetting::OWN_HOME === $care_setting
                ? $this->own_home_safe_summary($referral)
                : '',
            'care_plan_status'           => $plan_status,
            'care_plan_status_label'     => $this->care_plan_readiness_label($plan_status),
            'care_plan_ready'            => $plan_active,
            'care_team_ready'            => $team_active,
            'care_team_count'            => $team_count,
            'schedule_ready'             => $active_schedule_count > 0,
            'active_schedule_count'      => $active_schedule_count,
            'care_commenced_at'          => $commenced_at ?? '',
            'care_commenced_by_name'     => $commenced_by > 0
                ? $this->user_provider->get_display_name($commenced_by)
                : '',
            'default_commenced_at'       => current_time('Y-m-d\TH:i'),
            'next_action_label'          => $next_action['label'],
            'next_action_hint'           => $next_action['hint'],
            'ops_links'                  => $ops_links,
        ];
    }

    /**
     * Hard blockers that prevent Confirm Care Commenced.
     *
     * @param array<string, mixed>      $referral
     * @param array<string, mixed>|null $occupancy
     * @return array{blocking: array<int, string>}
     */
    public function evaluate_hard_requirements(
        array $referral,
        string $stage,
        bool $la_approved,
        ?string $care_setting,
        ?array $occupancy
    ): array {
        $blocking = [];

        if (PipelineStage::TRANSITION_PLANNING !== $stage) {
            $blocking[] = __('Pipeline must be Transition Planning.', 'jm-referral-system');
        }

        if ($this->retention_service->is_archived($referral)
            || ! $this->access_policy->can_mutate_referral($referral)
        ) {
            $blocking[] = __('This referral is not mutable (archived or read-only).', 'jm-referral-system');
        }

        $status = (string) ($referral['status'] ?? '');
        if (in_array($status, ['completed', 'cancelled'], true)) {
            $blocking[] = __('Care cannot commence on a completed or cancelled referral.', 'jm-referral-system');
        }

        if (null !== $this->nullable_mysql((string) ($referral['care_commenced_at'] ?? ''))) {
            $blocking[] = __('Care commencement has already been recorded.', 'jm-referral-system');
        }

        if (! $la_approved) {
            $blocking[] = __(
                'An approved Local Authority decision is required before care commencement.',
                'jm-referral-system'
            );
        }

        if (null === $care_setting) {
            $blocking[] = __('Care setting must be Supported Living or Own Home.', 'jm-referral-system');
        } elseif (CareSetting::SUPPORTED_LIVING === $care_setting) {
            if (null === $occupancy) {
                $blocking[] = __(
                    'An active Supported Living placement is required before care commencement.',
                    'jm-referral-system'
                );
            } else {
                $integrity = $this->evaluate_occupancy_integrity($occupancy);
                if (! $integrity['ok'] && '' !== $integrity['message']) {
                    $blocking[] = $integrity['message'];
                }
            }
        } elseif (CareSetting::OWN_HOME === $care_setting) {
            if (null !== $occupancy) {
                $blocking[] = __(
                    'Own Home referrals cannot have an active Supported Living occupancy.',
                    'jm-referral-system'
                );
            }
        } else {
            $blocking[] = __('Care setting must be Supported Living or Own Home.', 'jm-referral-system');
        }

        return ['blocking' => $blocking];
    }

    /**
     * @param array<string, mixed>|null $occupancy
     * @return array{ok: bool, message: string}
     */
    public function evaluate_occupancy_integrity(?array $occupancy): array
    {
        if (null === $occupancy) {
            return ['ok' => false, 'message' => ''];
        }

        if ('active' !== (string) ($occupancy['status'] ?? '')) {
            return [
                'ok'      => false,
                'message' => __('The linked placement is not an active occupancy.', 'jm-referral-system'),
            ];
        }

        $home_id = absint($occupancy['home_id'] ?? 0);
        $bedroom_id = absint($occupancy['bedroom_id'] ?? 0);
        $home = $home_id > 0 ? $this->home_repository->find($home_id) : null;
        $bedroom = $bedroom_id > 0 ? $this->bedroom_repository->find($bedroom_id) : null;

        if (null === $home || 'active' !== (string) ($home['status'] ?? '')) {
            return [
                'ok'      => false,
                'message' => __('The placement home is missing or not active.', 'jm-referral-system'),
            ];
        }

        if (null === $bedroom || 'active' !== (string) ($bedroom['status'] ?? '')) {
            return [
                'ok'      => false,
                'message' => __('The placement bedroom is missing or not active.', 'jm-referral-system'),
            ];
        }

        if (absint($bedroom['home_id'] ?? 0) !== $home_id) {
            return [
                'ok'      => false,
                'message' => __('The placement bedroom does not belong to the placement home.', 'jm-referral-system'),
            ];
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * @param array<string, mixed>|null $occupancy
     * @return array{home_name: string, room_label: string, move_in_date: string}
     */
    private function build_placement_summary(?array $occupancy): array
    {
        if (null === $occupancy) {
            return [
                'home_name'    => '',
                'room_label'   => '',
                'move_in_date' => '',
            ];
        }

        $home    = $this->home_repository->find(absint($occupancy['home_id'] ?? 0));
        $bedroom = $this->bedroom_repository->find(absint($occupancy['bedroom_id'] ?? 0));

        return [
            'home_name'    => (string) ($home['name'] ?? ''),
            'room_label'   => (string) ($bedroom['room_label'] ?? ''),
            'move_in_date' => (string) ($occupancy['move_in_date'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $referral
     * @return array{owner_name: string, champion_name: string, transition_lead_name: string}
     */
    private function build_responsibility_summary(array $referral): array
    {
        $owner_id = absint($referral['assigned_to'] ?? 0);
        $champion_id = absint($referral['champion_user_id'] ?? 0);
        $lead_id = absint($referral['transition_lead_user_id'] ?? 0);

        return [
            'owner_name'           => $owner_id > 0
                ? $this->user_provider->get_display_name($owner_id)
                : __('Unassigned', 'jm-referral-system'),
            'champion_name'        => $champion_id > 0
                ? $this->user_provider->get_display_name($champion_id)
                : __('Unassigned', 'jm-referral-system'),
            'transition_lead_name' => $lead_id > 0
                ? $this->user_provider->get_display_name($lead_id)
                : __('Unassigned', 'jm-referral-system'),
        ];
    }

    /**
     * @param array<int, string> $hard_blockers
     * @return array{label: string, hint: string}
     */
    private function resolve_next_action(
        bool $is_commenced,
        bool $can_commence,
        array $hard_blockers,
        ?string $care_setting,
        string $place_url,
        bool $show_place_prompt
    ): array {
        if ($is_commenced) {
            return [
                'label' => __('Care commenced — acquisition complete', 'jm-referral-system'),
                'hint'  => __('Care operations continue on this referral. Occupancy transfer/end remain available to authorised occupancy managers.', 'jm-referral-system'),
            ];
        }

        if ($can_commence) {
            return [
                'label' => __('Confirm Care Commenced', 'jm-referral-system'),
                'hint'  => __('Hard requirements are met. Review soft warnings before confirming.', 'jm-referral-system'),
            ];
        }

        if ($show_place_prompt && '' !== $place_url) {
            return [
                'label' => __('Place Resident', 'jm-referral-system'),
                'hint'  => __('Supported Living requires an active occupancy before care commencement. Place Resident does not advance the pipeline.', 'jm-referral-system'),
            ];
        }

        if (null === $care_setting) {
            return [
                'label' => __('Set care setting', 'jm-referral-system'),
                'hint'  => __('Choose Supported Living or Own Home before continuing.', 'jm-referral-system'),
            ];
        }

        if ([] !== $hard_blockers) {
            return [
                'label' => __('Resolve hard blockers', 'jm-referral-system'),
                'hint'  => __('Care commencement cannot proceed until every hard blocker is cleared.', 'jm-referral-system'),
            ];
        }

        return [
            'label' => __('Transition planning in progress', 'jm-referral-system'),
            'hint'  => __('Continue preparation. Soft warnings do not block commencement when acknowledgement is provided where required.', 'jm-referral-system'),
        ];
    }

    /**
     * @param array<string, mixed>|null $occupancy
     * @return array<int, string>
     */
    private function soft_warnings(
        bool $funding_ok,
        ?int $funding_int,
        ?string $care_setting,
        bool $address_complete,
        bool $plan_active,
        string $plan_status,
        bool $team_active,
        int $active_schedule_count,
        ?array $occupancy
    ): array {
        $warnings = [];

        if (! $funding_ok) {
            if (LaDecision::FUNDING_NO === $funding_int) {
                $warnings[] = __(
                    'Funding is recorded as not confirmed. Confirm with management before commencing care.',
                    'jm-referral-system'
                );
            } else {
                $warnings[] = __(
                    'Funding confirmation is not recorded. Confirm with management before commencing care.',
                    'jm-referral-system'
                );
            }
        }

        if (CareSetting::OWN_HOME === $care_setting && ! $address_complete) {
            $warnings[] = __(
                'Own Home address appears incomplete (line 1, city, and postcode recommended).',
                'jm-referral-system'
            );
        }

        if (CareSetting::SUPPORTED_LIVING === $care_setting && null !== $occupancy) {
            $move_in = (string) ($occupancy['move_in_date'] ?? '');
            if ('' !== $move_in && $move_in > current_time('Y-m-d')) {
                $warnings[] = sprintf(
                    /* translators: %s: move-in date */
                    __('Placement move-in date is %s. Care cannot be marked commenced before that date.', 'jm-referral-system'),
                    $move_in
                );
            }
        }

        if (! $plan_active) {
            if ('' === $plan_status) {
                $warnings[] = __('Care plan is not yet active.', 'jm-referral-system');
            } else {
                $warnings[] = sprintf(
                    /* translators: %s: care plan status label */
                    __('Care plan status: %s.', 'jm-referral-system'),
                    $this->care_plan_readiness_label($plan_status)
                );
            }
        }

        if (! $team_active) {
            $warnings[] = __('Care team is not configured.', 'jm-referral-system');
        }

        if ($active_schedule_count <= 0) {
            $warnings[] = __('No active schedule.', 'jm-referral-system');
        }

        return $warnings;
    }

    private function care_plan_readiness_label(string $status): string
    {
        if ('' === $status) {
            return __('Missing', 'jm-referral-system');
        }

        $labels = ReferralCarePlanService::status_labels();

        return $labels[$status] ?? $status;
    }

    /**
     * Line 1 + city + postcode for authorised transition-panel review (not dashboard).
     *
     * @param array<string, mixed> $referral
     */
    private function own_home_address_summary(array $referral): string
    {
        $parts = array_filter(
            [
                trim((string) ($referral['address_line_1'] ?? '')),
                trim((string) ($referral['city'] ?? '')),
                trim((string) ($referral['postcode'] ?? '')),
            ],
            static fn (string $part): bool => '' !== $part
        );

        return implode(', ', $parts);
    }

    /**
     * Safer terminal/display summary without street-level address.
     *
     * @param array<string, mixed> $referral
     */
    private function own_home_safe_summary(array $referral): string
    {
        $parts = array_filter(
            [
                trim((string) ($referral['city'] ?? '')),
                trim((string) ($referral['postcode'] ?? '')),
            ],
            static fn (string $part): bool => '' !== $part
        );

        if ([] === $parts) {
            return __("Client's Own Home", 'jm-referral-system');
        }

        return sprintf(
            /* translators: %s: city and/or postcode */
            __("Client's Own Home — %s", 'jm-referral-system'),
            implode(', ', $parts)
        );
    }

    /**
     * @return array<string, string>
     */
    private function build_ops_links(int $referral_id, bool $is_portal): array
    {
        if ($is_portal) {
            return [
                'care_plan'   => PortalUrls::referral_care_plan($referral_id),
                'care_team'   => PortalUrls::care_team_new($referral_id),
                'schedules'   => PortalUrls::schedule_new($referral_id),
                'visits'      => PortalUrls::referral($referral_id) . '#jmrs-portal-visits',
                'medications' => PortalUrls::referral($referral_id) . '#jmrs-portal-medications',
                'documents'   => PortalUrls::referral($referral_id) . '#jmrs-portal-documents',
            ];
        }

        $view = add_query_arg(
            [
                'page'        => 'jm-referrals-view',
                'referral_id' => $referral_id,
            ],
            admin_url('admin.php')
        );

        return [
            'care_plan'   => $view . '#jmrs-care-plan',
            'care_team'   => $view . '#jmrs-care-team',
            'schedules'   => $view . '#jmrs-schedules',
            'visits'      => $view . '#jmrs-visits',
            'medications' => $view . '#jmrs-medications',
            'documents'   => $view . '#jmrs-documents',
        ];
    }

    private function nullable_mysql(string $value): ?string
    {
        $value = trim($value);

        return '' === $value || '0000-00-00 00:00:00' === $value ? null : $value;
    }

    private function nullable_date(string $value): ?string
    {
        $value = trim($value);
        if ('' === $value || '0000-00-00' === $value) {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }
}
