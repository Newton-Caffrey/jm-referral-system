<?php

namespace JMReferral\Referral;

use JMReferral\Assessment\ReferralAssessmentController;
use JMReferral\Assessment\ReferralAssessmentRepository;
use JMReferral\Assessment\ReferralAssessmentService;
use JMReferral\CarePlan\ReferralCarePlanController;
use JMReferral\CarePlan\ReferralCarePlanRepository;
use JMReferral\CarePlan\ReferralCarePlanReviewController;
use JMReferral\CarePlan\ReferralCarePlanReviewService;
use JMReferral\CarePlan\ReferralCarePlanService;
use JMReferral\CareTeam\CareTeamController;
use JMReferral\CareTeam\CareTeamService;
use JMReferral\Documents\ReferralDocumentController;
use JMReferral\Documents\ReferralDocumentRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Pipeline\InterestResponseService;
use JMReferral\Pipeline\ReferralPipelineService;
use JMReferral\Assessment\AssessmentSchedulingService;
use JMReferral\PackageCost\PackageCostService;
use JMReferral\LaDecision\LocalAuthorityDecisionService;
use JMReferral\Pipeline\NonProceedingReason;
use JMReferral\Pipeline\ReferralNonProceedingService;
use JMReferral\Transition\CareCommencementService;
use JMReferral\Transition\TransitionPlanningService;
use JMReferral\Scheduling\ScheduleController;
use JMReferral\Scheduling\ScheduleGenerationService;
use JMReferral\Scheduling\ScheduleService;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;
use JMReferral\Visits\CareVisitController;
use JMReferral\Visits\CareVisitService;
use JMReferral\Visits\VisitExecutionService;
use JMReferral\Visits\VisitTaskService;
use JMReferral\Medication\MedicationController;
use JMReferral\Medication\MedicationService;
use JMReferral\Medication\MedicationAdministrationService;
use JMReferral\Workflow\WorkflowStageService;

class ReferralViewController
{
    public const VISITS_DEFAULT_PER_PAGE = 20;

    /** @var array<int, int> */
    public const VISITS_ALLOWED_PER_PAGE = [20, 50, 100];

    private const ACTIVITY_LIMIT = 50;
    private const NOTES_LIMIT = 50;
    private const REVIEWS_LIMIT = 25;
    private const VERSIONS_LIMIT = 25;

    public function __construct(
        private ReferralRepository $repository,
        private ReferralActivityRepository $activity_repository,
        private ReferralNoteRepository $note_repository,
        private UserProvider $user_provider,
        private ServiceTypeService $service_type_service,
        private WorkflowStageService $workflow_stage_service,
        private ReferralService $referral_service,
        private AccessPolicy $access_policy,
        private ReferralDocumentRepository $document_repository,
        private ReferralAssessmentRepository $assessment_repository,
        private ReferralCarePlanRepository $care_plan_repository,
        private ReferralCarePlanReviewService $care_plan_review_service,
        private CareVisitService $care_visit_service,
        private CareTeamService $care_team_service,
        private ScheduleService $schedule_service,
        private VisitExecutionService $visit_execution_service,
        private VisitTaskService $visit_task_service,
        private MedicationService $medication_service,
        private MedicationAdministrationService $medication_administration_service,
        private ReferralRetentionService $retention_service,
        private ReferralPipelineService $pipeline_service,
        private InterestResponseService $interest_response_service,
        private AssessmentSchedulingService $assessment_scheduling_service,
        private PackageCostService $package_cost_service,
        private LocalAuthorityDecisionService $la_decision_service,
        private ReferralNonProceedingService $non_proceeding_service,
        private TransitionPlanningService $transition_planning_service,
        private CareCommencementService $care_commencement_service
    ) {
    }

    /**
     * Registers view-related hooks.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_stage_change']);
        add_action('admin_init', [$this, 'handle_pipeline_override']);
        add_action('admin_init', [$this, 'handle_express_interest']);
        add_action('admin_init', [$this, 'handle_schedule_assessment']);
        add_action('admin_init', [$this, 'handle_reschedule_assessment']);
        add_action('admin_init', [$this, 'handle_assessment_needs_rescheduling']);
        add_action('admin_init', [$this, 'handle_prepare_package_cost']);
        add_action('admin_init', [$this, 'handle_send_package_cost']);
        add_action('admin_init', [$this, 'handle_record_la_decision']);
        add_action('admin_init', [$this, 'handle_mark_not_proceeding']);
        add_action('admin_init', [$this, 'handle_confirm_care_commenced']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    /**
     * Renders the referral details page.
     */
    public function render(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_REFERRALS)) {
            wp_die(esc_html__('You do not have permission to view referrals.', 'jm-referral-system'));
        }

        global $wpdb;
        $queries_before = defined('WP_DEBUG') && WP_DEBUG ? (int) $wpdb->num_queries : 0;

        $referral_id = isset($_GET['referral_id']) ? absint($_GET['referral_id']) : 0;
        $referral    = $this->repository->find($referral_id);

        if (null === $referral) {
            wp_die(esc_html__('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->access_policy->can_view_referral($referral)) {
            wp_die(esc_html__('You do not have permission to view this referral.', 'jm-referral-system'));
        }

        $activity_total   = $this->activity_repository->count_by_referral_id($referral_id);
        $activities       = $this->activity_repository->get_by_referral_id($referral_id, self::ACTIVITY_LIMIT);
        $activities_truncated = $activity_total > self::ACTIVITY_LIMIT;
        $assigned_to      = absint($referral['assigned_to'] ?? 0);
        $assigned_to_name = $assigned_to > 0
            ? $this->user_provider->get_display_name($assigned_to)
            : '';

        $archived_by      = absint($referral['archived_by'] ?? 0);
        $archived_by_name = $archived_by > 0
            ? $this->user_provider->get_display_name($archived_by)
            : '';
        $archived_at      = (string) ($referral['archived_at'] ?? '');
        $archive_reason   = (string) ($referral['archive_reason'] ?? '');

        $service_type_id = absint($referral['service_type_id'] ?? 0);
        $service_name    = (string) ($referral['service_required'] ?? '');
        if ($service_type_id > 0) {
            $service_type = $this->service_type_service->find($service_type_id);
            if (null !== $service_type) {
                $service_name = (string) ($service_type['name'] ?? $service_name);
            }
        }

        $workflow_stage_id   = absint($referral['workflow_stage_id'] ?? 0);
        $workflow_stage_name = '';
        if ($workflow_stage_id > 0) {
            $workflow_stage = $this->workflow_stage_service->find($workflow_stage_id);
            if (null !== $workflow_stage) {
                $workflow_stage_name = (string) ($workflow_stage['name'] ?? '');
            }
        }

        $pipeline_panel  = $this->pipeline_service->get_panel_data($referral);
        $interest_form   = $this->interest_response_service->get_form_context($referral);
        $expressed_by_id = absint($referral['interest_expressed_by'] ?? 0);
        $interest_milestone = $this->interest_response_service->get_milestone_display(
            $referral,
            $expressed_by_id > 0 ? $this->user_provider->get_display_name($expressed_by_id) : ''
        );
        $assessment_for_scheduling = $this->assessment_repository->find_by_referral($referral_id);
        $scheduling_panel = $this->assessment_scheduling_service->get_panel_context(
            $referral,
            $assessment_for_scheduling
        );
        $package_cost_panel = $this->package_cost_service->get_panel_context($referral);
        if (! empty($pipeline_panel['is_pipeline'])
            && 'package_cost_required' === (string) ($pipeline_panel['stage_slug'] ?? '')
        ) {
            $pipeline_panel['next_action'] = $this->package_cost_service->refined_next_action($referral);
        }
        $la_decision_panel = $this->la_decision_service->get_panel_context($referral);
        $suggested_np = '';
        if (! empty($scheduling_panel['is_not_suitable'])
            || 'assessment_review_required' === (string) ($pipeline_panel['stage_slug'] ?? '')
        ) {
            $suggested_np = NonProceedingReason::JM_NOT_SUITABLE;
        }
        $non_proceeding_panel = $this->non_proceeding_service->get_panel_context($referral, $suggested_np);
        $transition_panel = $this->transition_planning_service->get_panel_context($referral, 'admin');
        $scheduling_errors = [];
        $force_reschedule_form = false;
        if (isset($_GET['jmrs_schedule_error']) && 'validation' === sanitize_key(wp_unslash($_GET['jmrs_schedule_error']))) {
            $force_reschedule_form = isset($_GET['jmrs_reschedule']) && '1' === sanitize_text_field(wp_unslash($_GET['jmrs_reschedule']));
        }
        $package_cost_errors = [];
        $show_prepare_form = isset($_GET['jmrs_pc_prepare']) && '1' === sanitize_text_field(wp_unslash($_GET['jmrs_pc_prepare']));
        $show_send_form = isset($_GET['jmrs_pc_send']) && '1' === sanitize_text_field(wp_unslash($_GET['jmrs_pc_send']));
        $la_decision_errors = [];
        $show_la_decision_form = isset($_GET['jmrs_la_decide']) && '1' === sanitize_text_field(wp_unslash($_GET['jmrs_la_decide']));
        $show_non_proceeding_form = isset($_GET['jmrs_np_form']) && '1' === sanitize_text_field(wp_unslash($_GET['jmrs_np_form']));
        $transition_errors = [];
        $show_commence_form = isset($_GET['jmrs_commence']) && '1' === sanitize_text_field(wp_unslash($_GET['jmrs_commence']));
        if (isset($_GET['jmrs_care_commenced']) && '0' === sanitize_text_field(wp_unslash($_GET['jmrs_care_commenced']))) {
            $show_commence_form = true;
            $error_key = isset($_GET['jmrs_care_commenced_error'])
                ? sanitize_key(wp_unslash($_GET['jmrs_care_commenced_error']))
                : 'failed';
            $transition_errors[] = $this->care_commencement_error_message($error_key);
        }        $workflow_stages = ! empty($pipeline_panel['is_pipeline'])
            ? []
            : $this->workflow_stage_service->get_legacy_options_for_referral($workflow_stage_id);

        $notes_total   = $this->note_repository->count_by_referral_id($referral_id);
        $notes         = [];
        $notes_truncated = $notes_total > self::NOTES_LIMIT;
        foreach ($this->note_repository->get_by_referral_id($referral_id, self::NOTES_LIMIT) as $note_row) {
            $author_id               = absint($note_row['user_id'] ?? 0);
            $note_row['author_name'] = $author_id > 0
                ? $this->user_provider->get_display_name($author_id)
                : '';
            $notes[] = $note_row;
        }

        $note_form_state = ReferralNoteController::get_form_state($referral_id);
        $note_value      = $note_form_state['note'];
        $note_errors     = $note_form_state['errors'];

        $is_archived = $this->retention_service->is_archived($referral);
        $can_mutate  = $this->access_policy->can_mutate_referral($referral);

        $can_upload_documents   = Capabilities::current_user_can(Capabilities::UPLOAD_DOCUMENTS)
            && $can_mutate;
        $can_download_documents = Capabilities::current_user_can(Capabilities::DOWNLOAD_DOCUMENTS);
        $can_edit_referral      = Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)
            && $can_mutate;
        $can_delete_referral    = ! $is_archived
            && Capabilities::current_user_can(Capabilities::DELETE_REFERRALS)
            && $this->access_policy->can_edit_referral($referral)
            && $this->retention_service->can_permanently_delete($referral_id);
        $can_archive_referral   = ! $is_archived
            && Capabilities::current_user_can(Capabilities::ARCHIVE_REFERRALS)
            && $this->access_policy->can_edit_referral($referral);
        $can_restore_referral   = $is_archived
            && Capabilities::current_user_can(Capabilities::RESTORE_REFERRALS)
            && $this->access_policy->can_view_referral($referral);
        $can_add_notes          = ! $is_archived
            && Capabilities::current_user_can(Capabilities::ADD_NOTES)
            && $this->access_policy->can_view_referral($referral);
        $documents              = [];

        if ($can_download_documents) {
            foreach ($this->document_repository->get_by_referral_id($referral_id) as $document_row) {
                $uploader_id                    = absint($document_row['uploaded_by'] ?? 0);
                $document_row['uploaded_by_name'] = $uploader_id > 0
                    ? $this->user_provider->get_display_name($uploader_id)
                    : '';
                $document_row['download_url'] = ReferralDocumentController::get_download_url(
                    absint($document_row['id'] ?? 0)
                );
                $documents[] = $document_row;
            }
        }

        $document_errors = ReferralDocumentController::get_errors($referral_id);

        $assessment           = $this->assessment_repository->find_by_referral($referral_id);
        $can_edit_assessment = Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)
            && $can_mutate
            && ! ReferralAssessmentService::is_completed_assessment($assessment);

        $assessment_form_state = ReferralAssessmentController::get_form_state($referral_id);
        $assessment_errors    = $assessment_form_state['errors'];
        $assessment_data      = ! empty($assessment_form_state['data'])
            ? array_merge(
                ReferralAssessmentService::empty_form_data(),
                $assessment_form_state['data']
            )
            : ReferralAssessmentService::map_to_form_data($assessment);

        $assessor_user_id = absint($assessment['assessor_user_id'] ?? 0);
        if ($assessor_user_id <= 0) {
            $assessor_user_id = get_current_user_id();
        }
        $assessor_name = $assessor_user_id > 0
            ? $this->user_provider->get_display_name($assessor_user_id)
            : '';

        $assessment_outcomes = ReferralAssessmentService::outcome_labels();

        $can_view_care_plan = Capabilities::current_user_can(Capabilities::VIEW_CARE_PLANS)
            && $this->access_policy->can_view_referral($referral);
        $can_manage_care_plan = Capabilities::current_user_can(Capabilities::MANAGE_CARE_PLANS)
            && $can_mutate;
        $can_review_care_plan = Capabilities::current_user_can(Capabilities::REVIEW_CARE_PLANS)
            && $can_mutate;

        $care_plan            = $this->care_plan_repository->find_by_referral($referral_id);
        $care_plan_form_state = ReferralCarePlanController::get_form_state($referral_id);
        $care_plan_errors     = $care_plan_form_state['errors'];
        $care_plan_drafting   = ! empty($care_plan_form_state['drafting'])
            || (isset($_GET['jmrs_care_plan_edit']) && '1' === sanitize_key(wp_unslash($_GET['jmrs_care_plan_edit'])));

        if (! empty($care_plan_form_state['data'])) {
            $care_plan_data = array_merge(
                ReferralCarePlanService::empty_form_data(),
                $care_plan_form_state['data']
            );
        } else {
            $care_plan_data = ReferralCarePlanService::map_to_form_data($care_plan);
        }

        $show_care_plan_form = $can_manage_care_plan && (null !== $care_plan || $care_plan_drafting);

        $care_plan_created_by_name  = '';
        $care_plan_approved_by_name = '';
        if (null !== $care_plan) {
            $created_by_id = absint($care_plan['created_by'] ?? 0);
            $approved_by_id = absint($care_plan['approved_by'] ?? 0);
            $care_plan_created_by_name = $created_by_id > 0
                ? $this->user_provider->get_display_name($created_by_id)
                : '';
            $care_plan_approved_by_name = $approved_by_id > 0
                ? $this->user_provider->get_display_name($approved_by_id)
                : '';
        }

        $care_plan_statuses = ReferralCarePlanService::status_labels();
        $has_assessment     = null !== $assessment;

        $care_plan_review_outcomes = ReferralCarePlanReviewService::outcome_labels();
        $care_plan_review_form_state = ReferralCarePlanReviewController::get_form_state($referral_id);
        $care_plan_review_errors     = $care_plan_review_form_state['errors'];
        $care_plan_review_data       = array_merge(
            [
                'review_date'      => '',
                'outcome'          => '',
                'notes'            => '',
                'next_review_date' => '',
            ],
            $care_plan_review_form_state['data']
        );

        $care_plan_reviews = [];
        $care_plan_versions = [];
        $care_plan_reviews_truncated  = false;
        $care_plan_versions_truncated = false;
        if ($can_view_care_plan && null !== $care_plan) {
            $reviews_total  = $this->care_plan_review_service->count_reviews_for_referral($referral_id);
            $versions_total = $this->care_plan_review_service->count_versions_for_referral($referral_id);
            $care_plan_reviews_truncated  = $reviews_total > self::REVIEWS_LIMIT;
            $care_plan_versions_truncated = $versions_total > self::VERSIONS_LIMIT;

            foreach ($this->care_plan_review_service->get_reviews_for_referral($referral_id, self::REVIEWS_LIMIT) as $review_row) {
                $reviewed_by_id = absint($review_row['reviewed_by'] ?? 0);
                $review_row['reviewed_by_name'] = $reviewed_by_id > 0
                    ? $this->user_provider->get_display_name($reviewed_by_id)
                    : '';
                $care_plan_reviews[] = $review_row;
            }

            foreach ($this->care_plan_review_service->get_versions_for_referral($referral_id, self::VERSIONS_LIMIT) as $version_row) {
                $version_created_by = absint($version_row['created_by'] ?? 0);
                $version_row['created_by_name'] = $version_created_by > 0
                    ? $this->user_provider->get_display_name($version_created_by)
                    : '';
                $version_row['view_url'] = ReferralCarePlanReviewController::get_version_url(
                    absint($version_row['id'] ?? 0)
                );
                $care_plan_versions[] = $version_row;
            }
        }

        $can_view_visits = Capabilities::current_user_can(Capabilities::VIEW_VISITS)
            && $this->access_policy->can_view_referral($referral);
        $can_manage_visits = Capabilities::current_user_can(Capabilities::MANAGE_VISITS)
            && $can_mutate;

        $care_visit_statuses = CareVisitService::status_labels();
        $care_visit_form_state = CareVisitController::get_form_state($referral_id);
        $care_visit_errors     = $care_visit_form_state['errors'];
        $care_visit_data       = ! empty($care_visit_form_state['data']) && absint($care_visit_form_state['visit_id'] ?? 0) === 0
            ? array_merge(CareVisitService::empty_form_data(), $care_visit_form_state['data'])
            : CareVisitService::empty_form_data();

        if (null !== $care_plan) {
            $care_visit_data['care_plan_id'] = (string) absint($care_plan['id'] ?? 0);
        }

        $assignable_users = $can_manage_visits
            ? $this->care_visit_service->get_assignable_staff_for_referral($referral_id)
            : [];

        $can_view_care_team = Capabilities::current_user_can(Capabilities::VIEW_CARE_TEAM)
            && $this->access_policy->can_view_referral($referral);
        $can_manage_care_team = Capabilities::current_user_can(Capabilities::MANAGE_CARE_TEAM)
            && $can_mutate;

        $care_team_roles    = CareTeamService::role_labels();
        $care_team_statuses = CareTeamService::status_labels();
        $care_team_form_state = CareTeamController::get_form_state($referral_id);
        $care_team_errors     = $care_team_form_state['errors'];
        $care_team_data       = ! empty($care_team_form_state['data']) && absint($care_team_form_state['assignment_id'] ?? 0) === 0
            ? array_merge(CareTeamService::empty_form_data(), $care_team_form_state['data'])
            : CareTeamService::empty_form_data();

        if (null !== $care_plan) {
            $care_team_data['care_plan_id'] = (string) absint($care_plan['id'] ?? 0);
        }

        $care_team_assignable_users = $can_manage_care_team
            ? $this->user_provider->get_assignable_users()
            : [];

        $care_team_members = [];
        if ($can_view_care_team) {
            foreach ($this->care_team_service->get_members_for_referral($referral_id) as $member_row) {
                $member_user_id = absint($member_row['user_id'] ?? 0);
                $member_row['staff_name'] = $member_user_id > 0
                    ? $this->user_provider->get_display_name($member_user_id)
                    : '';
                $member_row['edit_url'] = CareTeamController::get_edit_url(absint($member_row['id'] ?? 0));
                $care_team_members[] = $member_row;
            }
        }

        $can_view_medications = $this->medication_service->can_view_medications($referral);
        $can_manage_medications = $this->medication_service->can_manage_medications($referral);
        $show_inactive_medications = isset($_GET['jmrs_show_inactive_meds']) && '1' === (string) $_GET['jmrs_show_inactive_meds'];
        $medication_status_labels = MedicationService::status_labels();
        $medication_route_labels  = MedicationService::route_labels();
        $medication_form_state    = MedicationController::get_form_state($referral_id);
        $medication_errors        = $medication_form_state['errors'];
        $medication_data          = ! empty($medication_form_state['data']) && absint($medication_form_state['medication_id'] ?? 0) === 0
            ? array_merge(MedicationService::empty_form_data(), $medication_form_state['data'])
            : MedicationService::empty_form_data();
        $medications = [];
        $can_administer_medications_cap = Capabilities::current_user_can(Capabilities::ADMINISTER_MEDICATIONS)
            && $this->access_policy->can_view_referral($referral)
            && ! $is_archived;

        if ($can_view_medications) {
            $medications = $this->medication_service->get_medications_for_referral(
                $referral_id,
                $show_inactive_medications
            );
            foreach ($medications as $idx => $med_row) {
                $medications[$idx]['edit_url'] = MedicationController::get_edit_url(absint($med_row['id'] ?? 0));
            }
        }

        $administration_status_labels = MedicationAdministrationService::status_labels();
        $administration_reason_labels = MedicationAdministrationService::reason_labels();
        $witness_users = ($can_view_medications || $can_administer_medications_cap)
            ? $this->user_provider->get_assignable_users()
            : [];

        $care_visits = [];
        $schedule_name_by_id = [];

        $can_view_schedules = Capabilities::current_user_can(Capabilities::VIEW_SCHEDULES)
            && $this->access_policy->can_view_referral($referral);
        $can_manage_schedules = Capabilities::current_user_can(Capabilities::MANAGE_SCHEDULES)
            && $can_mutate;

        $schedule_repeat_labels  = ScheduleService::repeat_type_labels();
        $schedule_status_labels  = ScheduleService::status_labels();
        $schedule_weekday_labels = ScheduleService::weekday_labels();
        $schedule_form_state     = ScheduleController::get_form_state($referral_id);
        $schedule_errors         = $schedule_form_state['errors'];
        $schedule_data           = ! empty($schedule_form_state['data']) && absint($schedule_form_state['schedule_id'] ?? 0) === 0
            ? array_merge(ScheduleService::empty_form_data(), $schedule_form_state['data'])
            : ScheduleService::empty_form_data();

        if (null !== $care_plan) {
            $schedule_data['care_plan_id'] = (string) absint($care_plan['id'] ?? 0);
        }

        // Generation date errors may include posted dates for the same schedule.
        $generation_form_data = [];
        if (! empty($schedule_form_state['data']) && absint($schedule_form_state['schedule_id'] ?? 0) > 0) {
            $generation_form_data = $schedule_form_state['data'];
        }

        $schedule_team_options = [];
        if ($can_manage_schedules) {
            foreach ($care_team_members as $member_row) {
                if ('active' !== (string) ($member_row['assignment_status'] ?? '')) {
                    continue;
                }
                $assignment_id = absint($member_row['id'] ?? 0);
                if ($assignment_id <= 0) {
                    continue;
                }
                $role_key   = (string) ($member_row['team_role'] ?? '');
                $role_label = $care_team_roles[$role_key] ?? $role_key;
                $staff_name = (string) ($member_row['staff_name'] ?? '');
                if ('' === $staff_name) {
                    continue;
                }
                $schedule_team_options[] = [
                    'id'    => $assignment_id,
                    'label' => $staff_name . ' (' . $role_label . ')',
                ];
            }
        }

        $visit_schedules = [];
        if ($can_view_schedules) {
            foreach ($this->schedule_service->get_schedules_for_referral($referral_id) as $schedule_row) {
                $team_assignment_id = absint($schedule_row['team_assignment_id'] ?? 0);
                $assigned_label     = '—';
                if ($team_assignment_id > 0) {
                    foreach ($care_team_members as $member_row) {
                        if (absint($member_row['id'] ?? 0) === $team_assignment_id) {
                            $assigned_label = (string) ($member_row['staff_name'] ?? '—');
                            break;
                        }
                    }
                    if ('—' === $assigned_label) {
                        foreach ($this->care_team_service->get_members_for_referral($referral_id) as $member_row) {
                            if (absint($member_row['id'] ?? 0) === $team_assignment_id) {
                                $uid = absint($member_row['user_id'] ?? 0);
                                $assigned_label = $uid > 0
                                    ? $this->user_provider->get_display_name($uid)
                                    : '—';
                                break;
                            }
                        }
                    }
                }

                $schedule_id_row = absint($schedule_row['id'] ?? 0);
                $schedule_name   = (string) ($schedule_row['schedule_name'] ?? '');
                if ($schedule_id_row > 0 && '' !== $schedule_name) {
                    $schedule_name_by_id[$schedule_id_row] = $schedule_name;
                }

                $status_key   = (string) ($schedule_row['status'] ?? '');
                $can_generate = ScheduleService::STATUS_ACTIVE === $status_key && $can_manage_schedules;
                $defaults     = ScheduleGenerationService::default_window($schedule_row);

                $gen_start = $defaults['start'] ?? '';
                $gen_end   = $defaults['end'] ?? '';
                if (
                    $can_generate
                    && absint($schedule_form_state['schedule_id'] ?? 0) === $schedule_id_row
                    && ! empty($generation_form_data)
                ) {
                    if (isset($generation_form_data['generation_start_date'])) {
                        $gen_start = (string) $generation_form_data['generation_start_date'];
                    }
                    if (isset($generation_form_data['generation_end_date'])) {
                        $gen_end = (string) $generation_form_data['generation_end_date'];
                    }
                }

                $schedule_row['assigned_label']          = $assigned_label;
                $schedule_row['edit_url']                = ScheduleController::get_edit_url($schedule_id_row);
                $schedule_row['can_generate']            = $can_generate;
                $schedule_row['generated_visit_count']   = $schedule_id_row > 0
                    ? $this->schedule_service->count_generated_visits($schedule_id_row)
                    : 0;
                $schedule_row['generation_start_date']   = $gen_start;
                $schedule_row['generation_end_date']     = $gen_end;
                $visit_schedules[]                       = $schedule_row;
            }
        }

        if ($can_view_visits) {
            $execution_form_state = CareVisitController::get_execution_form_state($referral_id);
            $visit_outcome_labels = VisitExecutionService::outcome_labels();
            $visit_task_statuses  = VisitTaskService::status_labels();

            $visits_pagination = $this->visits_pagination_from_request();
            $visits_per_page   = $visits_pagination['per_page'];
            $visits_page       = $visits_pagination['page'];
            $visits_total      = $this->care_visit_service->count_visits_for_referral($referral_id);
            $visits_total_pages = max(1, (int) ceil($visits_total / $visits_per_page));
            if ($visits_page > $visits_total_pages) {
                $visits_page = $visits_total_pages;
            }
            $visits_offset = ($visits_page - 1) * $visits_per_page;
            $visits_from   = 0 === $visits_total ? 0 : $visits_offset + 1;
            $visits_to     = min($visits_offset + $visits_per_page, $visits_total);

            $visit_rows = $this->care_visit_service->get_visits_for_referral(
                $referral_id,
                $visits_per_page,
                $visits_offset
            );

            $visit_ids    = [];
            $user_ids     = [];
            $schedule_ids = [];
            foreach ($visit_rows as $visit_row) {
                $vid = absint($visit_row['id'] ?? 0);
                if ($vid > 0) {
                    $visit_ids[] = $vid;
                }
                $assigned_id = absint($visit_row['assigned_user_id'] ?? 0);
                if ($assigned_id > 0) {
                    $user_ids[$assigned_id] = $assigned_id;
                }
                $reviewed_by = absint($visit_row['reviewed_by'] ?? 0);
                if ($reviewed_by > 0) {
                    $user_ids[$reviewed_by] = $reviewed_by;
                }
                $sid = absint($visit_row['schedule_id'] ?? 0);
                if ($sid > 0 && ! isset($schedule_name_by_id[$sid])) {
                    $schedule_ids[$sid] = $sid;
                }
            }

            if ([] !== $schedule_ids) {
                foreach ($this->schedule_service->get_names_by_ids(array_values($schedule_ids)) as $sid => $name) {
                    $schedule_name_by_id[(int) $sid] = $name;
                }
            }

            $display_names = $this->user_provider->get_display_names_by_ids(array_values($user_ids));
            $tasks_by_visit = $this->visit_task_service->get_tasks_by_visit_ids($visit_ids);
            $admins_by_visit = $this->medication_administration_service->get_by_visit_ids($visit_ids);
            $active_medications = $this->medication_service->get_active_for_referral($referral_id);

            $visits_base_args = [
                'page'                 => 'jm-referrals-view',
                'referral_id'          => $referral_id,
                'jmrs_visits_per_page' => $visits_per_page,
            ];
            $visits_base_url = add_query_arg($visits_base_args, admin_url('admin.php'));
            $visits_pagination_links = '';
            if ($visits_total_pages > 1) {
                $visits_pagination_links = paginate_links(
                    [
                        'base'      => esc_url_raw($visits_base_url) . '%_%',
                        'format'    => '&jmrs_visits_page=%#%',
                        'current'   => $visits_page,
                        'total'     => $visits_total_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'type'      => 'plain',
                    ]
                );
            }

            foreach ($visit_rows as $visit_row) {
                $assigned_id = absint($visit_row['assigned_user_id'] ?? 0);
                $visit_row['assigned_staff_name'] = $assigned_id > 0
                    ? (string) ($display_names[$assigned_id] ?? '')
                    : '';
                $visit_row['edit_url'] = CareVisitController::get_edit_url(absint($visit_row['id'] ?? 0));

                $schedule_id = absint($visit_row['schedule_id'] ?? 0);
                if ($schedule_id > 0) {
                    $name = $schedule_name_by_id[$schedule_id] ?? '';
                    $visit_row['source_label'] = '' !== $name
                        ? sprintf(
                            /* translators: %s: schedule name */
                            __('Schedule: %s', 'jm-referral-system'),
                            $name
                        )
                        : __('Schedule', 'jm-referral-system');
                } else {
                    $visit_row['source_label'] = __('Manual', 'jm-referral-system');
                }

                $is_executed = $this->visit_execution_service->is_executed($visit_row);
                $is_reviewed = $this->visit_execution_service->is_reviewed($visit_row);
                $visit_row['is_executed'] = $is_executed;
                $visit_row['is_reviewed'] = $is_reviewed;
                $visit_row['can_execute'] = ! $is_executed
                    && $this->visit_execution_service->can_execute_visit($referral, $visit_row);
                $visit_row['can_review'] = $is_executed
                    && ! $is_reviewed
                    && $this->visit_execution_service->can_review_visit($referral);

                $outcome_key = (string) ($visit_row['visit_outcome'] ?? '');
                $visit_row['outcome_label'] = isset($visit_outcome_labels[$outcome_key])
                    ? $visit_outcome_labels[$outcome_key]
                    : $outcome_key;

                $reviewed_by = absint($visit_row['reviewed_by'] ?? 0);
                $visit_row['reviewed_by_name'] = $reviewed_by > 0
                    ? (string) ($display_names[$reviewed_by] ?? '')
                    : '';

                $visit_id_row = absint($visit_row['id'] ?? 0);
                $visit_tasks  = $visit_id_row > 0
                    ? ($tasks_by_visit[$visit_id_row] ?? [])
                    : [];
                $visit_row['visit_tasks']    = $visit_tasks;
                $visit_row['task_summaries'] = $this->visit_task_service->summarize_tasks($visit_tasks);

                $visit_active_medications = [];
                $visit_date = (string) ($visit_row['visit_date'] ?? '');
                foreach ($active_medications as $medication) {
                    if ($this->medication_administration_service->is_medication_valid_on_date($medication, $visit_date)) {
                        $visit_active_medications[] = $medication;
                    }
                }

                $visit_row['can_administer_medications'] = ! $is_executed
                    && $this->medication_administration_service->can_administer_for_visit($referral, $visit_row)
                    && [] !== $visit_active_medications;
                $visit_row['active_medications'] = $visit_active_medications;
                $visit_row['medication_administrations'] = $visit_id_row > 0
                    ? ($admins_by_visit[$visit_id_row] ?? [])
                    : [];

                $admin_by_med = [];
                foreach ($visit_row['medication_administrations'] as $admin_row) {
                    $admin_by_med[absint($admin_row['medication_id'] ?? 0)] = $admin_row;
                }
                $visit_row['medication_admin_by_id'] = $admin_by_med;

                if (
                    $visit_id_row > 0
                    && absint($execution_form_state['visit_id'] ?? 0) === $visit_id_row
                    && ! empty($execution_form_state['data'])
                ) {
                    $visit_row['execution_form_data'] = array_merge(
                        VisitExecutionService::empty_execution_form_data(),
                        $execution_form_state['data']
                    );
                    $visit_row['execution_errors'] = $execution_form_state['errors'];
                    $posted_tasks = is_array($execution_form_state['data']['tasks'] ?? null)
                        ? $execution_form_state['data']['tasks']
                        : [];
                    if (! empty($posted_tasks) && ! empty($visit_row['visit_tasks'])) {
                        foreach ($visit_row['visit_tasks'] as $idx => $task_row) {
                            $tid = absint($task_row['id'] ?? 0);
                            if ($tid > 0 && isset($posted_tasks[$tid]) && is_array($posted_tasks[$tid])) {
                                $visit_row['visit_tasks'][$idx]['task_status'] = (string) ($posted_tasks[$tid]['task_status'] ?? $task_row['task_status']);
                                $visit_row['visit_tasks'][$idx]['task_notes']  = (string) ($posted_tasks[$tid]['task_notes'] ?? '');
                            }
                        }
                    }
                    $posted_meds = is_array($execution_form_state['data']['medications'] ?? null)
                        ? $execution_form_state['data']['medications']
                        : [];
                    $visit_row['posted_medications'] = $posted_meds;
                } else {
                    $visit_row['execution_form_data'] = VisitExecutionService::empty_execution_form_data();
                    $visit_row['execution_errors']    = [];
                    $visit_row['posted_medications']  = [];
                }

                $care_visits[] = $visit_row;
            }
        } else {
            $visit_outcome_labels = [];
            $visit_task_statuses  = [];
            $visits_total         = 0;
            $visits_page          = 1;
            $visits_per_page      = self::VISITS_DEFAULT_PER_PAGE;
            $visits_from          = 0;
            $visits_to            = 0;
            $visits_total_pages   = 1;
            $visits_pagination_links = '';
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $delta = (int) $wpdb->num_queries - $queries_before;
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated by WP_DEBUG.
            error_log(
                sprintf(
                    '[JMRS] referral view query count: %d; visits loaded: %d',
                    max(0, $delta),
                    count($care_visits)
                )
            );
        }

        include JMRS_PLUGIN_PATH . 'templates/referrals/view.php';
    }

    /**
     * @return array{page: int, per_page: int}
     */
    private function visits_pagination_from_request(): array
    {
        $per_page = isset($_GET['jmrs_visits_per_page'])
            ? absint($_GET['jmrs_visits_per_page'])
            : self::VISITS_DEFAULT_PER_PAGE;

        if (! in_array($per_page, self::VISITS_ALLOWED_PER_PAGE, true)) {
            $per_page = self::VISITS_DEFAULT_PER_PAGE;
        }

        $page = isset($_GET['jmrs_visits_page']) ? absint($_GET['jmrs_visits_page']) : 1;

        return [
            'page'     => max(1, $page),
            'per_page' => $per_page,
        ];
    }

    /**
     * Handles workflow stage changes from the view page.
     */
    public function handle_stage_change(): void
    {
        if (! isset($_POST['jmrs_update_workflow_stage'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)) {
            wp_die(esc_html__('You do not have permission to update referrals.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;

        check_admin_referer('jmrs_update_workflow_stage_' . $referral_id, 'jmrs_update_workflow_stage_nonce');

        $referral = $this->repository->find($referral_id);

        if (null === $referral || ! $this->access_policy->can_mutate_referral($referral)) {
            wp_die(esc_html__('You do not have permission to edit this referral.', 'jm-referral-system'));
        }

        $workflow_stage_id = isset($_POST['jmrs_workflow_stage_id'])
            ? absint(wp_unslash($_POST['jmrs_workflow_stage_id']))
            : 0;

        $updated = $this->referral_service->change_workflow_stage($referral_id, $workflow_stage_id);

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'               => 'jm-referrals-view',
                    'referral_id'        => $referral_id,
                    'jmrs_stage_updated' => $updated ? '1' : '0',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Handles Express Interest from the admin referral view.
     */
    public function handle_express_interest(): void
    {
        if (! isset($_POST['jmrs_express_interest'])) {
            return;
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;

        check_admin_referer('jmrs_express_interest_' . $referral_id, 'jmrs_express_interest_nonce');

        $referral = $this->repository->find($referral_id);

        if (null === $referral || ! $this->access_policy->can_express_interest($referral)) {
            wp_die(esc_html__('You do not have permission to express interest on this referral.', 'jm-referral-system'));
        }

        $result = $this->interest_response_service->express(
            $referral_id,
            [
                'method'     => isset($_POST['jmrs_interest_method'])
                    ? sanitize_key(wp_unslash($_POST['jmrs_interest_method']))
                    : '',
                'confirmed'  => ! empty($_POST['jmrs_interest_confirmed']),
                'other_note' => isset($_POST['jmrs_interest_other_note'])
                    ? sanitize_text_field(wp_unslash($_POST['jmrs_interest_other_note']))
                    : '',
            ]
        );

        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];

        if (! empty($result['ok'])) {
            $args['jmrs_interest'] = '1';
        } else {
            $args['jmrs_interest'] = '0';
            $args['jmrs_interest_error'] = sanitize_key((string) ($result['error'] ?? 'failed'));
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles Schedule Assessment from the admin referral view.
     */
    public function handle_schedule_assessment(): void
    {
        if (! isset($_POST['jmrs_schedule_assessment'])) {
            return;
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        check_admin_referer('jmrs_schedule_assessment_' . $referral_id, 'jmrs_schedule_assessment_nonce');

        $referral = $this->repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_schedule_assessment($referral)) {
            wp_die(esc_html__('You do not have permission to schedule an assessment for this referral.', 'jm-referral-system'));
        }

        $result = $this->assessment_scheduling_service->schedule($referral_id, $this->scheduling_input_from_post());
        $this->redirect_after_scheduling($referral_id, $result, 'schedule');
    }

    /**
     * Handles Reschedule Assessment from the admin referral view.
     */
    public function handle_reschedule_assessment(): void
    {
        if (! isset($_POST['jmrs_reschedule_assessment'])) {
            return;
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        check_admin_referer('jmrs_reschedule_assessment_' . $referral_id, 'jmrs_reschedule_assessment_nonce');

        $referral = $this->repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_schedule_assessment($referral)) {
            wp_die(esc_html__('You do not have permission to reschedule this assessment.', 'jm-referral-system'));
        }

        $result = $this->assessment_scheduling_service->reschedule($referral_id, $this->scheduling_input_from_post());
        $this->redirect_after_scheduling($referral_id, $result, 'reschedule');
    }

    /**
     * Handles Mark as Needs Rescheduling from the admin referral view.
     */
    public function handle_assessment_needs_rescheduling(): void
    {
        if (! isset($_POST['jmrs_assessment_needs_rescheduling'])) {
            return;
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        check_admin_referer('jmrs_assessment_needs_rescheduling_' . $referral_id, 'jmrs_assessment_needs_rescheduling_nonce');

        $referral = $this->repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_schedule_assessment($referral)) {
            wp_die(esc_html__('You do not have permission to update this assessment appointment.', 'jm-referral-system'));
        }

        $reason = isset($_POST['jmrs_needs_reschedule_reason'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_needs_reschedule_reason']))
            : '';

        $result = $this->assessment_scheduling_service->mark_needs_rescheduling($referral_id, $reason);
        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];
        if (! empty($result['ok'])) {
            $args['jmrs_needs_reschedule'] = '1';
        } else {
            $args['jmrs_needs_reschedule'] = '0';
            $args['jmrs_schedule_error'] = sanitize_key((string) ($result['error'] ?? 'failed'));
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduling_input_from_post(): array
    {
        return [
            'scheduled_date'              => isset($_POST['jmrs_scheduled_date'])
                ? sanitize_text_field(wp_unslash($_POST['jmrs_scheduled_date']))
                : '',
            'scheduled_time'              => isset($_POST['jmrs_scheduled_time'])
                ? sanitize_text_field(wp_unslash($_POST['jmrs_scheduled_time']))
                : '',
            'assessor_user_id'            => isset($_POST['jmrs_assessor_user_id'])
                ? absint($_POST['jmrs_assessor_user_id'])
                : 0,
            'assessment_location_type'    => isset($_POST['jmrs_assessment_location_type'])
                ? sanitize_key(wp_unslash($_POST['jmrs_assessment_location_type']))
                : '',
            'assessment_location_name'    => isset($_POST['jmrs_assessment_location_name'])
                ? sanitize_text_field(wp_unslash($_POST['jmrs_assessment_location_name']))
                : '',
            'assessment_location_address' => isset($_POST['jmrs_assessment_location_address'])
                ? sanitize_textarea_field(wp_unslash($_POST['jmrs_assessment_location_address']))
                : '',
            'assessment_contact_name'     => isset($_POST['jmrs_assessment_contact_name'])
                ? sanitize_text_field(wp_unslash($_POST['jmrs_assessment_contact_name']))
                : '',
            'assessment_contact_phone'    => isset($_POST['jmrs_assessment_contact_phone'])
                ? sanitize_text_field(wp_unslash($_POST['jmrs_assessment_contact_phone']))
                : '',
            'assessment_contact_email'    => isset($_POST['jmrs_assessment_contact_email'])
                ? sanitize_email(wp_unslash($_POST['jmrs_assessment_contact_email']))
                : '',
            'scheduling_notes'            => isset($_POST['jmrs_scheduling_notes'])
                ? sanitize_textarea_field(wp_unslash($_POST['jmrs_scheduling_notes']))
                : '',
        ];
    }

    /**
     * @param array{ok?: bool, error?: string, message?: string} $result
     */
    private function redirect_after_scheduling(int $referral_id, array $result, string $action): void
    {
        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];

        if (! empty($result['ok'])) {
            $args['jmrs_schedule'] = 'schedule' === $action ? '1' : 'rescheduled';
        } else {
            $args['jmrs_schedule'] = '0';
            $args['jmrs_schedule_error'] = sanitize_key((string) ($result['error'] ?? 'failed'));
            if ('reschedule' === $action) {
                $args['jmrs_reschedule'] = '1';
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles Package Cost prepare/update from the admin referral view.
     */
    public function handle_prepare_package_cost(): void
    {
        if (! isset($_POST['jmrs_prepare_package_cost'])) {
            return;
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        check_admin_referer('jmrs_prepare_package_cost_' . $referral_id, 'jmrs_prepare_package_cost_nonce');

        $referral = $this->repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_manage_package_cost($referral)) {
            wp_die(esc_html__('You do not have permission to prepare a Package Cost for this referral.', 'jm-referral-system'));
        }

        $file = isset($_FILES['jmrs_package_cost_document']) && is_array($_FILES['jmrs_package_cost_document'])
            ? $_FILES['jmrs_package_cost_document']
            : null;

        $result = $this->package_cost_service->prepare(
            $referral_id,
            [
                'package_total' => isset($_POST['jmrs_package_total'])
                    ? sanitize_text_field(wp_unslash($_POST['jmrs_package_total']))
                    : '',
            ],
            $file
        );

        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];
        if (! empty($result['ok'])) {
            $args['jmrs_package_cost'] = 'prepared';
        } else {
            $args['jmrs_package_cost'] = '0';
            $args['jmrs_package_cost_error'] = sanitize_key((string) ($result['error'] ?? 'failed'));
            $args['jmrs_pc_prepare'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles Package Cost send/record from the admin referral view.
     */
    public function handle_send_package_cost(): void
    {
        if (! isset($_POST['jmrs_send_package_cost'])) {
            return;
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        check_admin_referer('jmrs_send_package_cost_' . $referral_id, 'jmrs_send_package_cost_nonce');

        $referral = $this->repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_manage_package_cost($referral)) {
            wp_die(esc_html__('You do not have permission to submit a Package Cost for this referral.', 'jm-referral-system'));
        }

        $result = $this->package_cost_service->record_sent(
            $referral_id,
            [
                'send_method'           => isset($_POST['jmrs_package_send_method'])
                    ? sanitize_key(wp_unslash($_POST['jmrs_package_send_method']))
                    : '',
                'recipient'             => isset($_POST['jmrs_package_recipient'])
                    ? sanitize_text_field(wp_unslash($_POST['jmrs_package_recipient']))
                    : '',
                'submission_reference'  => isset($_POST['jmrs_package_submission_reference'])
                    ? sanitize_text_field(wp_unslash($_POST['jmrs_package_submission_reference']))
                    : '',
                'confirmed'             => ! empty($_POST['jmrs_package_sent_confirmed']),
            ]
        );

        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];
        if (! empty($result['ok'])) {
            $args['jmrs_package_cost'] = ('email' === (string) ($result['method'] ?? ''))
                ? 'emailed'
                : 'sent';
        } else {
            $args['jmrs_package_cost'] = '0';
            $args['jmrs_package_cost_error'] = sanitize_key((string) ($result['error'] ?? 'failed'));
            $args['jmrs_pc_send'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles Confirm Care Commenced from the admin referral view.
     */
    public function handle_confirm_care_commenced(): void
    {
        if (! isset($_POST['jmrs_confirm_care_commenced'])) {
            return;
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        check_admin_referer('jmrs_confirm_care_commenced_' . $referral_id, 'jmrs_confirm_care_commenced_nonce');

        $referral = $this->repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_commence_care($referral)) {
            wp_die(esc_html__('You do not have permission to confirm care commencement for this referral.', 'jm-referral-system'));
        }

        $result = $this->care_commencement_service->commence(
            $referral_id,
            $this->care_commencement_input_from_request()
        );

        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];
        if (! empty($result['ok'])) {
            $args['jmrs_care_commenced'] = '1';
        } else {
            $args['jmrs_care_commenced'] = '0';
            $args['jmrs_care_commenced_error'] = sanitize_key((string) ($result['error'] ?? 'failed'));
            $args['jmrs_commence'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function care_commencement_input_from_request(): array
    {
        return [
            'care_commenced_at'   => isset($_POST['jmrs_care_commenced_at'])
                ? sanitize_text_field(wp_unslash($_POST['jmrs_care_commenced_at']))
                : '',
            'funding_acknowledge' => ! empty($_POST['jmrs_funding_acknowledge']),
        ];
    }

    private function care_commencement_error_message(string $error): string
    {
        return match ($error) {
            'already_commenced' => __('Care commencement has already been recorded for this referral.', 'jm-referral-system'),
            'funding_ack_required' => __('Please acknowledge that funding is not confirmed before commencing care.', 'jm-referral-system'),
            'move_in_future' => __('Care commencement cannot be earlier than the Supported Living move-in date.', 'jm-referral-system'),
            'wrong_stage' => __('Care commencement is only available during Transition Planning.', 'jm-referral-system'),
            'blocked' => __('Care commencement requirements are not met yet.', 'jm-referral-system'),
            'validation' => __('Please enter a valid care commencement date and time.', 'jm-referral-system'),
            'in_progress' => __('Care commencement is already being recorded. Please wait a moment.', 'jm-referral-system'),
            default => __('Unable to confirm care commencement.', 'jm-referral-system'),
        };
    }

    /**
     * Handles Local Authority decision recording from the admin referral view.
     */
    public function handle_record_la_decision(): void
    {
        if (! isset($_POST['jmrs_record_la_decision'])) {
            return;
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        check_admin_referer('jmrs_record_la_decision_' . $referral_id, 'jmrs_record_la_decision_nonce');

        $referral = $this->repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_record_la_decision($referral)) {
            wp_die(esc_html__('You do not have permission to record a Local Authority decision for this referral.', 'jm-referral-system'));
        }

        $result = $this->la_decision_service->record(
            $referral_id,
            $this->la_decision_input_from_request()
        );

        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];
        if (! empty($result['ok'])) {
            $args['jmrs_la_decision'] = sanitize_key((string) ($result['decision'] ?? 'recorded'));
        } else {
            $args['jmrs_la_decision'] = '0';
            $args['jmrs_la_decision_error'] = sanitize_key((string) ($result['error'] ?? 'failed'));
            $args['jmrs_la_decide'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Handles Mark as Not Proceeding from the admin referral view.
     */
    public function handle_mark_not_proceeding(): void
    {
        if (! isset($_POST['jmrs_mark_not_proceeding'])) {
            return;
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;
        check_admin_referer('jmrs_mark_not_proceeding_' . $referral_id, 'jmrs_mark_not_proceeding_nonce');

        $referral = $this->repository->find($referral_id);
        if (null === $referral || ! $this->access_policy->can_mark_not_proceeding($referral)) {
            wp_die(esc_html__('You do not have permission to mark this referral as not proceeding.', 'jm-referral-system'));
        }

        $result = $this->non_proceeding_service->mark(
            $referral_id,
            [
                'reason_code' => isset($_POST['jmrs_np_reason'])
                    ? sanitize_key(wp_unslash($_POST['jmrs_np_reason']))
                    : '',
            ]
        );

        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];
        if (! empty($result['ok'])) {
            $args['jmrs_not_proceeding'] = '1';
        } else {
            $args['jmrs_not_proceeding'] = '0';
            $args['jmrs_np_error'] = sanitize_key((string) ($result['error'] ?? 'failed'));
            $args['jmrs_np_form'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function la_decision_input_from_request(): array
    {
        $decision = isset($_POST['jmrs_la_decision'])
            ? sanitize_key(wp_unslash($_POST['jmrs_la_decision']))
            : '';

        $reason = '';
        $decision_reference = '';
        if ('declined' === $decision) {
            $reason = isset($_POST['jmrs_la_declined_reason'])
                ? sanitize_key(wp_unslash($_POST['jmrs_la_declined_reason']))
                : '';
            $decision_reference = isset($_POST['jmrs_la_declined_reference'])
                ? sanitize_text_field(wp_unslash($_POST['jmrs_la_declined_reference']))
                : '';
        } elseif ('not_proceeding' === $decision) {
            $reason = isset($_POST['jmrs_la_np_reason'])
                ? sanitize_key(wp_unslash($_POST['jmrs_la_np_reason']))
                : '';
        } else {
            $decision_reference = isset($_POST['jmrs_la_decision_reference'])
                ? sanitize_text_field(wp_unslash($_POST['jmrs_la_decision_reference']))
                : '';
        }

        return [
            'decision'           => $decision,
            'decision_at'        => isset($_POST['jmrs_la_decision_at'])
                ? sanitize_text_field(wp_unslash($_POST['jmrs_la_decision_at']))
                : '',
            'funding_confirmed'  => isset($_POST['jmrs_la_funding_confirmed'])
                ? sanitize_key(wp_unslash($_POST['jmrs_la_funding_confirmed']))
                : 'not_recorded',
            'funding_reference'  => isset($_POST['jmrs_la_funding_reference'])
                ? sanitize_text_field(wp_unslash($_POST['jmrs_la_funding_reference']))
                : '',
            'decision_reference' => $decision_reference,
            'reason_code'        => $reason,
            'notes'              => isset($_POST['jmrs_la_notes'])
                ? sanitize_textarea_field(wp_unslash($_POST['jmrs_la_notes']))
                : '',
        ];
    }

    /**
     * Handles explicit pipeline stage override (Manager/Admin).
     */
    public function handle_pipeline_override(): void
    {
        if (! isset($_POST['jmrs_override_pipeline_stage'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::OVERRIDE_PIPELINE_STAGE)) {
            wp_die(esc_html__('You do not have permission to override pipeline stages.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;

        check_admin_referer('jmrs_override_pipeline_stage_' . $referral_id, 'jmrs_override_pipeline_stage_nonce');

        $referral = $this->repository->find($referral_id);

        if (null === $referral || ! $this->access_policy->can_mutate_referral($referral)) {
            wp_die(esc_html__('You do not have permission to edit this referral.', 'jm-referral-system'));
        }

        $to_slug = isset($_POST['jmrs_pipeline_target_slug'])
            ? sanitize_key(wp_unslash($_POST['jmrs_pipeline_target_slug']))
            : '';
        $reason  = isset($_POST['jmrs_pipeline_override_reason'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_pipeline_override_reason']))
            : '';

        $result  = $this->pipeline_service->override($referral_id, $to_slug, $reason);
        $success = ! empty($result['ok']);

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'                  => 'jm-referrals-view',
                    'referral_id'           => $referral_id,
                    'jmrs_pipeline_override'=> $success ? '1' : '0',
                    'jmrs_pipeline_error'   => $success ? '' : sanitize_key((string) ($result['error'] ?? 'failed')),
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Renders stage-update notices on the view screen.
     */
    public function render_notices(): void
    {
        if (! $this->is_view_screen()) {
            return;
        }

        if (isset($_GET['jmrs_interest'])) {
            $ok = sanitize_text_field(wp_unslash($_GET['jmrs_interest']));
            if ('1' === $ok) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Interest response recorded. Next action: Schedule assessment.', 'jm-referral-system');
                echo '</p></div>';
            } else {
                $error = isset($_GET['jmrs_interest_error'])
                    ? sanitize_key(wp_unslash($_GET['jmrs_interest_error']))
                    : '';
                $message = __('Unable to record interest response.', 'jm-referral-system');
                if ('email_failed' === $error) {
                    $message = __('The interest email could not be sent. The referral was not advanced. Please retry or use phone/other.', 'jm-referral-system');
                } elseif ('already_recorded' === $error) {
                    $message = __('Interest has already been recorded for this referral.', 'jm-referral-system');
                } elseif ('confirmation_required' === $error) {
                    $message = __('Please confirm that JM Healthcare’s interest has been communicated to the referrer.', 'jm-referral-system');
                } elseif ('email_unavailable' === $error) {
                    $message = __('No valid referrer email is available. Please use phone or another communication method.', 'jm-referral-system');
                } elseif ('wrong_stage' === $error) {
                    $message = __('Express Interest is only available when the pipeline stage is Interest Response Required.', 'jm-referral-system');
                }
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo esc_html($message);
                echo '</p></div>';
            }
        }

        if (isset($_GET['jmrs_schedule'])) {
            $ok = sanitize_text_field(wp_unslash($_GET['jmrs_schedule']));
            if ('1' === $ok) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Assessment scheduled. Next action: Complete assessment.', 'jm-referral-system');
                echo '</p></div>';
            } elseif ('rescheduled' === $ok) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Assessment appointment updated.', 'jm-referral-system');
                echo '</p></div>';
            } else {
                $error = isset($_GET['jmrs_schedule_error'])
                    ? sanitize_key(wp_unslash($_GET['jmrs_schedule_error']))
                    : '';
                $message = __('Unable to save the assessment appointment.', 'jm-referral-system');
                if ('wrong_stage' === $error) {
                    $message = __('That scheduling action is not available for the current pipeline stage.', 'jm-referral-system');
                } elseif ('validation' === $error) {
                    $message = __('Please correct the scheduling form and try again.', 'jm-referral-system');
                }
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo esc_html($message);
                echo '</p></div>';
            }
        }

        if (isset($_GET['jmrs_needs_reschedule'])) {
            $ok = sanitize_text_field(wp_unslash($_GET['jmrs_needs_reschedule']));
            if ('1' === $ok) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Assessment marked as needing rescheduling. Next action: Schedule assessment.', 'jm-referral-system');
                echo '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo esc_html__('Unable to mark the assessment as needing rescheduling.', 'jm-referral-system');
                echo '</p></div>';
            }
        }

        if (isset($_GET['jmrs_package_cost'])) {
            $status = sanitize_key(wp_unslash($_GET['jmrs_package_cost']));
            if ('prepared' === $status) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Package Cost prepared. Next action: Send package cost to Local Authority.', 'jm-referral-system');
                echo '</p></div>';
            } elseif ('emailed' === $status) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Package Cost emailed. Next action: Await / follow up Local Authority decision.', 'jm-referral-system');
                echo '</p></div>';
            } elseif ('sent' === $status) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Package Cost submission recorded. Next action: Await / follow up Local Authority decision.', 'jm-referral-system');
                echo '</p></div>';
            } else {
                $error = isset($_GET['jmrs_package_cost_error'])
                    ? sanitize_key(wp_unslash($_GET['jmrs_package_cost_error']))
                    : '';
                $message = __('Unable to save Package Cost.', 'jm-referral-system');
                if ('confirmation_required' === $error) {
                    $message = __('Please confirm that the Package Cost has been submitted to the Local Authority.', 'jm-referral-system');
                } elseif ('already_sent' === $error) {
                    $message = __('This Package Cost has already been sent.', 'jm-referral-system');
                } elseif ('validation' === $error) {
                    $message = __('Please correct the Package Cost form and try again.', 'jm-referral-system');
                } elseif ('email_failed' === $error) {
                    $message = __('Package Cost email could not be sent. The referral has not been advanced. Please try again.', 'jm-referral-system');
                } elseif ('email_unavailable' === $error) {
                    $message = __('No valid referrer email is available. Use Secure Portal or Other, or update the referral contact details.', 'jm-referral-system');
                } elseif ('attachment_missing' === $error || 'attachment_unreadable' === $error) {
                    $message = __('The Package Cost document could not be attached. The email was not sent and the referral was not advanced.', 'jm-referral-system');
                } elseif ('in_progress' === $error) {
                    $message = __('A Package Cost send is already in progress. Please wait a moment.', 'jm-referral-system');
                } elseif ('wrong_stage' === $error) {
                    $message = __('Package Cost email is only available when the pipeline stage is Package Cost to Prepare.', 'jm-referral-system');
                }
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo esc_html($message);
                echo '</p></div>';
            }
        }

        if (isset($_GET['jmrs_la_decision'])) {
            $status = sanitize_key(wp_unslash($_GET['jmrs_la_decision']));
            if ('approved' === $status) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Local Authority approval recorded. Next action: Plan transition and commence care.', 'jm-referral-system');
                echo '</p></div>';
            } elseif ('declined' === $status) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Local Authority decision recorded as declined.', 'jm-referral-system');
                echo '</p></div>';
            } elseif ('not_proceeding' === $status) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Referral marked as not proceeding.', 'jm-referral-system');
                echo '</p></div>';
            } elseif ('0' === $status) {
                $error = isset($_GET['jmrs_la_decision_error'])
                    ? sanitize_key(wp_unslash($_GET['jmrs_la_decision_error']))
                    : '';
                $message = __('Unable to record Local Authority decision.', 'jm-referral-system');
                if ('already_recorded' === $error) {
                    $message = __('A Local Authority decision has already been recorded for this referral.', 'jm-referral-system');
                } elseif ('validation' === $error || 'invalid_decision' === $error) {
                    $message = __('Please correct the decision form and try again.', 'jm-referral-system');
                } elseif ('package_cost_required' === $error) {
                    $message = __('A sent Package Cost is required before recording a Local Authority decision.', 'jm-referral-system');
                } elseif ('in_progress' === $error) {
                    $message = __('A Local Authority decision is already being recorded. Please wait a moment.', 'jm-referral-system');
                }
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo esc_html($message);
                echo '</p></div>';
            }
        }

        if (isset($_GET['jmrs_not_proceeding'])) {
            $ok = sanitize_text_field(wp_unslash($_GET['jmrs_not_proceeding']));
            if ('1' === $ok) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Referral marked as not proceeding.', 'jm-referral-system');
                echo '</p></div>';
            } else {
                $error = isset($_GET['jmrs_np_error'])
                    ? sanitize_key(wp_unslash($_GET['jmrs_np_error']))
                    : '';
                $message = __('Unable to mark referral as not proceeding.', 'jm-referral-system');
                if ('validation' === $error) {
                    $message = __('Please select a valid reason.', 'jm-referral-system');
                } elseif ('already_closed' === $error) {
                    $message = __('This referral is already marked as not proceeding.', 'jm-referral-system');
                } elseif ('use_la_decision' === $error) {
                    $message = __('Use Record Local Authority Decision to mark this referral as not proceeding.', 'jm-referral-system');
                }
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo esc_html($message);
                echo '</p></div>';
            }
        }

        if (isset($_GET['jmrs_care_commenced'])) {
            $ok = sanitize_text_field(wp_unslash($_GET['jmrs_care_commenced']));
            if ('1' === $ok) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Care commencement recorded. Acquisition pipeline complete — care operations continue on this record.', 'jm-referral-system');
                echo '</p></div>';
            } else {
                $error = isset($_GET['jmrs_care_commenced_error'])
                    ? sanitize_key(wp_unslash($_GET['jmrs_care_commenced_error']))
                    : '';
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo esc_html($this->care_commencement_error_message($error));
                echo '</p></div>';
            }
        }

        if (isset($_GET['jmrs_pipeline_override'])) {
            $ok = sanitize_text_field(wp_unslash($_GET['jmrs_pipeline_override']));
            if ('1' === $ok) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html__('Pipeline stage overridden successfully.', 'jm-referral-system');
                echo '</p></div>';
            } else {
                $error = isset($_GET['jmrs_pipeline_error'])
                    ? sanitize_key(wp_unslash($_GET['jmrs_pipeline_error']))
                    : '';
                $message = __('Unable to override the pipeline stage.', 'jm-referral-system');
                if ('reason_required' === $error) {
                    $message = __('Override reason is required.', 'jm-referral-system');
                } elseif ('invalid_target' === $error || 'target_stage_missing' === $error) {
                    $message = __('Please select a valid canonical pipeline stage.', 'jm-referral-system');
                }
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo esc_html($message);
                echo '</p></div>';
            }
        }

        if (! isset($_GET['jmrs_stage_updated'])) {
            return;
        }

        $updated = sanitize_text_field(wp_unslash($_GET['jmrs_stage_updated']));

        if ('1' === $updated) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Workflow stage updated successfully.', 'jm-referral-system');
            echo '</p></div>';
            return;
        }

        echo '<div class="notice notice-error is-dismissible"><p>';
        echo esc_html__('Unable to update the workflow stage.', 'jm-referral-system');
        echo '</p></div>';
    }

    /**
     * Builds the view screen URL for a referral.
     */
    public static function get_view_url(int $referral_id): string
    {
        return add_query_arg(
            [
                'page'        => 'jm-referrals-view',
                'referral_id' => $referral_id,
            ],
            admin_url('admin.php')
        );
    }

    private function is_view_screen(): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        return 'jm-referrals-view' === $page;
    }
}
