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
 */
class TransitionPlanningService
{
    public function __construct(
        private ReferralPipelineService $pipeline_service,
        private LaDecisionRepository $la_decision_repository,
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

        $occupancy = $this->occupancy_repository->current_for_referral($referral_id);
        $placement = $this->build_placement_summary($occupancy);

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
        if (CareSetting::SUPPORTED_LIVING === $care_setting
            && Capabilities::current_user_can(Capabilities::MANAGE_OCCUPANCIES)
            && ! $this->retention_service->is_archived($referral)
            && null === $occupancy
        ) {
            $place_url = PortalUrls::occupancy_place(['referral_id' => $referral_id]);
        }

        $ops_links = $this->build_ops_links($referral_id, $is_portal);

        return [
            'show_panel'                 => true,
            'stage_slug'                 => $stage,
            'is_transition_planning'     => PipelineStage::TRANSITION_PLANNING === $stage,
            'is_care_commenced'          => $is_commenced,
            'can_commence'               => $can_commence,
            'hard_blockers'              => $hard['blocking'],
            'soft_warnings'              => $this->soft_warnings(
                $funding_ok,
                $funding_int,
                $care_setting,
                $address_complete,
                $plan_active,
                $plan_status,
                $team_active,
                $active_schedule_count,
                $occupancy
            ),
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
            'care_setting'               => $care_setting,
            'care_setting_label'         => CareSetting::label($care_setting),
            'care_setting_required'      => null === $care_setting,
            'care_setting_url'           => $care_setting_url,
            'placement_ready'            => null !== $occupancy,
            'placement_home_name'        => (string) ($placement['home_name'] ?? ''),
            'placement_room_label'       => (string) ($placement['room_label'] ?? ''),
            'placement_move_in_date'     => (string) ($placement['move_in_date'] ?? ''),
            'place_resident_url'         => $place_url,
            'service_location_label'     => $location->label(),
            'own_home_address_complete'  => $address_complete,
            'own_home_address_summary'   => CareSetting::OWN_HOME === $care_setting
                ? $this->own_home_address_summary($referral)
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
            $blocking[] = __('This referral is not mutable.', 'jm-referral-system');
        }

        $status = (string) ($referral['status'] ?? '');
        if (in_array($status, ['completed', 'cancelled'], true)) {
            $blocking[] = __('Care cannot commence on a completed or cancelled referral.', 'jm-referral-system');
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
     * @return array{home_name: string, room_label: string, move_in_date: string}
     */
    private function build_placement_summary(?array $occupancy): array
    {
        if (null === $occupancy) {
            return [
                'home_name'     => '',
                'room_label'    => '',
                'move_in_date'  => '',
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
     * @return array<string, string>
     */
    private function build_ops_links(int $referral_id, bool $is_portal): array
    {
        if ($is_portal) {
            return [
                'care_plan'  => PortalUrls::referral_care_plan($referral_id),
                'care_team'  => PortalUrls::care_team_new($referral_id),
                'schedules'  => PortalUrls::schedule_new($referral_id),
                'visits'     => PortalUrls::referral($referral_id) . '#jmrs-portal-visits',
                'medications'=> PortalUrls::referral($referral_id) . '#jmrs-portal-medications',
                'documents'  => PortalUrls::referral($referral_id) . '#jmrs-portal-documents',
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
}
