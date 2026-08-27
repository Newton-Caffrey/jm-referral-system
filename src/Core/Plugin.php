<?php

namespace JMReferral\Core;

use JMReferral\Admin\Menu;
use JMReferral\Admin\AdminAssets;
use JMReferral\Assessment\ReferralAssessmentController;
use JMReferral\Assessment\ReferralAssessmentRepository;
use JMReferral\Assessment\ReferralAssessmentService;
use JMReferral\CarePlan\ReferralCarePlanController;
use JMReferral\CarePlan\ReferralCarePlanRepository;
use JMReferral\CarePlan\ReferralCarePlanReviewController;
use JMReferral\CarePlan\ReferralCarePlanReviewRepository;
use JMReferral\CarePlan\ReferralCarePlanReviewService;
use JMReferral\CarePlan\ReferralCarePlanService;
use JMReferral\CarePlan\ReferralCarePlanVersionRepository;
use JMReferral\CareTeam\CareTeamController;
use JMReferral\CareTeam\CareTeamRepository;
use JMReferral\CareTeam\CareTeamService;
use JMReferral\Database\Migrator;
use JMReferral\Documents\DocumentMigrationController;
use JMReferral\Documents\PrivateDocumentStorage;
use JMReferral\Documents\ReferralDocumentController;
use JMReferral\Documents\ReferralDocumentRepository;
use JMReferral\Documents\ReferralDocumentService;
use JMReferral\Alerts\OperationalAlertService;
use JMReferral\Frontend\PublicReferralController;
use JMReferral\Frontend\PublicReferralService;
use JMReferral\Frontend\PublicReferralShortcode;
use JMReferral\Notifications\EmailNotificationService;
use JMReferral\Notifications\NotificationService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Portal\Clinical\CarePlanReviewHandler;
use JMReferral\Portal\Clinical\CareTeamHandler;
use JMReferral\Portal\Clinical\ClinicalAccess;
use JMReferral\Portal\Clinical\ClinicalDispatcher;
use JMReferral\Portal\Clinical\MedicationHandler;
use JMReferral\Portal\Clinical\ScheduleHandler;
use JMReferral\Portal\Clinical\VisitHandler;
use JMReferral\Portal\Homes\HomesHandler;
use JMReferral\Portal\Homes\OccupancyHandler;
use JMReferral\Portal\PortalAccess;
use JMReferral\Portal\PortalController;
use JMReferral\Portal\PortalNavigation;
use JMReferral\Portal\PortalRetentionHandler;
use JMReferral\Portal\PortalRouter;
use JMReferral\Homes\BedroomRepository;
use JMReferral\Homes\BedroomService;
use JMReferral\Homes\HomeDashboardService;
use JMReferral\Homes\HomeRepository;
use JMReferral\Homes\HomeService;
use JMReferral\Homes\OccupancyRepository;
use JMReferral\Homes\OccupancyService;
use JMReferral\Referral\ReferralActivityRepository;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralController;
use JMReferral\Referral\ReferralDependencyRepository;
use JMReferral\Referral\ReferralEditController;
use JMReferral\Referral\ReferralExportController;
use JMReferral\Referral\ReferralFilters;
use JMReferral\Referral\ReferralListController;
use JMReferral\Referral\ReferralNoteController;
use JMReferral\Referral\ReferralNoteRepository;
use JMReferral\Referral\ReferralNoteService;
use JMReferral\Referral\ReferralNumberGenerator;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Referral\ReferralRetentionService;
use JMReferral\Referral\ReferralService;
use JMReferral\Referral\ReferralValidator;
use JMReferral\Referral\ReferralViewController;
use JMReferral\Scheduling\ScheduleController;
use JMReferral\Scheduling\ScheduleGenerationService;
use JMReferral\Scheduling\ScheduleRepository;
use JMReferral\Scheduling\ScheduleService;
use JMReferral\Services\ServiceTypeController;
use JMReferral\Services\ServiceTypeRepository;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;
use JMReferral\Visits\CareVisitController;
use JMReferral\Visits\CareVisitRepository;
use JMReferral\Visits\CareVisitService;
use JMReferral\Visits\ServiceLocationResolver;
use JMReferral\Visits\VisitExecutionService;
use JMReferral\Visits\VisitTaskRepository;
use JMReferral\Visits\VisitTaskService;
use JMReferral\Medication\MedicationAdministrationRepository;
use JMReferral\Medication\MedicationAdministrationService;
use JMReferral\Medication\MedicationController;
use JMReferral\Medication\MedicationRepository;
use JMReferral\Medication\MedicationService;
use JMReferral\Workflow\WorkflowStageController;
use JMReferral\Workflow\WorkflowStageRepository;
use JMReferral\Workflow\WorkflowStageService;

class Plugin
{
    private ?ReferralListController $list_controller = null;
    private ?ReferralEditController $edit_controller = null;
    private ?ReferralViewController $view_controller = null;
    private ?ReferralService $service = null;
    private ?UserProvider $user_provider = null;
    private ?ReferralFilters $filters = null;
    private ?AccessPolicy $access_policy = null;
    private ?ServiceTypeController $service_type_controller = null;
    private ?ServiceTypeService $service_type_service = null;
    private ?WorkflowStageController $workflow_stage_controller = null;
    private ?WorkflowStageService $workflow_stage_service = null;
    private ?ReferralCarePlanReviewController $care_plan_review_controller = null;
    private ?CareVisitController $care_visit_controller = null;
    private ?CareVisitService $care_visit_service = null;
    private ?VisitExecutionService $visit_execution_service = null;
    private ?ServiceLocationResolver $service_location_resolver = null;
    private ?CareTeamController $care_team_controller = null;
    private ?CareTeamService $care_team_service = null;
    private ?ScheduleController $schedule_controller = null;
    private ?ScheduleService $schedule_service = null;
    private ?ScheduleGenerationService $schedule_generation_service = null;
    private ?MedicationController $medication_controller = null;
    private ?MedicationService $medication_service = null;
    private ?MedicationAdministrationService $medication_administration_service = null;
    private ?ReferralDocumentService $document_service = null;

    public function run(): void
    {
        add_action('plugins_loaded', [Migrator::class, 'maybe_migrate']);
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        AdminAssets::register();

        $this->registerReferralControllers();
    }

    public function registerAdminMenu(): void
    {
        $menu = new Menu(
            $this->list_controller,
            $this->edit_controller,
            $this->service,
            $this->view_controller,
            $this->user_provider,
            $this->filters,
            $this->service_type_controller,
            $this->service_type_service,
            $this->workflow_stage_controller,
            $this->workflow_stage_service,
            $this->access_policy,
            $this->care_plan_review_controller,
            $this->care_visit_controller,
            $this->care_visit_service,
            $this->care_team_controller,
            $this->care_team_service,
            $this->schedule_controller,
            $this->schedule_service,
            $this->medication_controller,
            $this->medication_service,
            $this->medication_administration_service,
            $this->document_service
        );
        $menu->register();
    }

    private function registerReferralControllers(): void
    {
        $repository          = new ReferralRepository();
        $number_generator    = new ReferralNumberGenerator($repository);
        $activity_repository = new ReferralActivityRepository();
        $activity_service    = new ReferralActivityService($activity_repository);
        $this->access_policy = new AccessPolicy();
        $note_repository     = new ReferralNoteRepository();
        $note_service        = new ReferralNoteService(
            $note_repository,
            $repository,
            $activity_service,
            $this->access_policy
        );
        $this->user_provider = new UserProvider();
        $this->filters       = new ReferralFilters($this->user_provider, $this->access_policy);

        $document_repository     = new ReferralDocumentRepository();
        $private_storage         = new PrivateDocumentStorage();
        $this->document_service  = new ReferralDocumentService(
            $document_repository,
            $repository,
            $activity_service,
            $this->access_policy,
            $private_storage
        );
        $document_service = $this->document_service;

        $assessment_repository = new ReferralAssessmentRepository();

        $email_service        = new EmailNotificationService();
        $notification_service = new NotificationService($email_service, $this->user_provider);
        $workflow_stage_repository_for_pipeline = new WorkflowStageRepository();
        $pipeline_history_repository            = new \JMReferral\Pipeline\ReferralStageHistoryRepository();
        $pipeline_service                       = new \JMReferral\Pipeline\ReferralPipelineService(
            $repository,
            $workflow_stage_repository_for_pipeline,
            $pipeline_history_repository,
            $activity_service,
            $this->access_policy,
            $this->user_provider
        );

        $assessment_service = new ReferralAssessmentService(
            $assessment_repository,
            $repository,
            $activity_service,
            $this->access_policy,
            $pipeline_service
        );
        $assessment_scheduling_service = new \JMReferral\Assessment\AssessmentSchedulingService(
            $repository,
            $assessment_repository,
            $pipeline_service,
            $activity_service,
            $this->access_policy,
            $this->user_provider
        );
        $package_cost_repository = new \JMReferral\PackageCost\PackageCostRepository();
        $package_cost_service    = new \JMReferral\PackageCost\PackageCostService(
            $repository,
            $package_cost_repository,
            $document_service,
            $document_repository,
            $pipeline_service,
            $activity_service,
            $this->access_policy,
            $this->user_provider,
            $notification_service
        );

        $care_plan_repository         = new ReferralCarePlanRepository();
        $care_plan_version_repository = new ReferralCarePlanVersionRepository();
        $care_plan_review_repository  = new ReferralCarePlanReviewRepository();
        $care_plan_review_service     = new ReferralCarePlanReviewService(
            $care_plan_review_repository,
            $care_plan_version_repository,
            $care_plan_repository,
            $repository,
            $activity_service,
            $this->access_policy
        );
        $care_plan_service = new ReferralCarePlanService(
            $care_plan_repository,
            $repository,
            $assessment_repository,
            $activity_service,
            $this->access_policy,
            $care_plan_review_service
        );

        $this->care_team_service = new CareTeamService(
            new CareTeamRepository(),
            $repository,
            $care_plan_repository,
            $activity_service,
            $this->access_policy,
            $this->user_provider
        );

        $this->schedule_service = new ScheduleService(
            new ScheduleRepository(),
            $repository,
            $care_plan_repository,
            new CareTeamRepository(),
            $activity_service,
            $this->access_policy
        );

        $visit_repository    = new CareVisitRepository();
        $visit_task_service  = new VisitTaskService(
            new VisitTaskRepository(),
            $visit_repository,
            $care_plan_repository
        );

        $medication_repository = new MedicationRepository();
        $this->medication_service = new MedicationService(
            $medication_repository,
            $repository,
            $activity_service,
            $this->access_policy
        );
        $medication_administration_repository = new MedicationAdministrationRepository();
        $this->medication_administration_service = new MedicationAdministrationService(
            $medication_administration_repository,
            $medication_repository,
            $visit_repository,
            $repository,
            $activity_service,
            $this->access_policy
        );

        $this->care_visit_service = new CareVisitService(
            $visit_repository,
            $repository,
            $care_plan_repository,
            $activity_service,
            $this->access_policy,
            $this->user_provider,
            $this->care_team_service,
            $visit_task_service
        );

        // Homes/occupancy repos created here so ServiceLocationResolver and ReferralService
        // share the same OccupancyRepository instance (avoid undefined-variable activation fatals).
        $occupancy_repository = new OccupancyRepository();
        $home_repository      = new HomeRepository();
        $bedroom_repository   = new BedroomRepository();

        $this->service_location_resolver = new ServiceLocationResolver(
            $repository,
            $occupancy_repository,
            $home_repository,
            $bedroom_repository
        );

        $this->visit_execution_service = new VisitExecutionService(
            $visit_repository,
            $repository,
            $activity_service,
            $this->access_policy,
            $visit_task_service,
            $this->medication_administration_service,
            $this->service_location_resolver
        );

        $this->schedule_generation_service = new ScheduleGenerationService(
            new ScheduleRepository(),
            $visit_repository,
            $repository,
            $care_plan_repository,
            new CareTeamRepository(),
            $activity_service,
            $this->access_policy,
            $visit_task_service
        );

        $service_type_repository       = new ServiceTypeRepository();
        $this->service_type_service    = new ServiceTypeService($service_type_repository, $repository);
        $this->service_type_controller = new ServiceTypeController($this->service_type_service);

        $workflow_stage_repository       = new WorkflowStageRepository();
        $this->workflow_stage_service    = new WorkflowStageService($workflow_stage_repository, $repository);
        $this->workflow_stage_controller = new WorkflowStageController($this->workflow_stage_service);

        // Reuse pipeline created earlier (assessment completion + interest response).
        $interest_response_service = new \JMReferral\Pipeline\InterestResponseService(
            $repository,
            $pipeline_service,
            $notification_service,
            $activity_service,
            $this->access_policy
        );
        $this->service        = new ReferralService(
            $repository,
            $number_generator,
            $activity_service,
            $this->user_provider,
            $notification_service,
            $this->service_type_service,
            $this->workflow_stage_service,
            $this->access_policy,
            $occupancy_repository,
            $pipeline_service
        );
        $la_decision_repository = new \JMReferral\LaDecision\LaDecisionRepository();
        $la_decision_service    = new \JMReferral\LaDecision\LocalAuthorityDecisionService(
            $repository,
            $this->service,
            $la_decision_repository,
            $package_cost_repository,
            $pipeline_service,
            $activity_service,
            $this->access_policy,
            $this->user_provider
        );
        $non_proceeding_service = new \JMReferral\Pipeline\ReferralNonProceedingService(
            $repository,
            $this->service,
            $pipeline_service,
            $pipeline_history_repository,
            $activity_service,
            $this->access_policy,
            $this->user_provider
        );

        // Phase 4B.1 foundation services — constructed for DI readiness; no write controllers yet.
        $meeting_repository          = new \JMReferral\Meeting\ReferralMeetingRepository();
        $meeting_attendee_repository = new \JMReferral\Meeting\MeetingAttendeeRepository();

        $retention_service = new ReferralRetentionService(
            $repository,
            new ReferralDependencyRepository(),
            $activity_service,
            $this->access_policy,
            $meeting_attendee_repository,
            $meeting_repository
        );

        $meeting_service = new \JMReferral\Meeting\ReferralMeetingService(
            $repository,
            $meeting_repository,
            $activity_service,
            $this->access_policy
        );
        $meeting_attendee_service = new \JMReferral\Meeting\MeetingAttendeeService(
            $repository,
            $meeting_repository,
            $meeting_attendee_repository,
            $activity_service,
            $this->access_policy,
            $this->user_provider
        );
        $responsibility_service = new \JMReferral\Meeting\ReferralResponsibilityService(
            $repository,
            $activity_service,
            $this->access_policy,
            $this->user_provider
        );

        $meeting_read_service = new \JMReferral\Meeting\ReferralMeetingReadService(
            $meeting_repository,
            $meeting_attendee_repository,
            $this->access_policy,
            $this->user_provider
        );

        $care_team_repository_for_transition = new CareTeamRepository();
        $schedule_repository_for_transition  = new ScheduleRepository();
        $service_location_resolver = $this->service_location_resolver;
        if (! $service_location_resolver instanceof ServiceLocationResolver) {
            throw new \RuntimeException('ServiceLocationResolver was not initialised before transition services.');
        }
        $transition_planning_service = new \JMReferral\Transition\TransitionPlanningService(
            $pipeline_service,
            $la_decision_repository,
            $occupancy_repository,
            $home_repository,
            $bedroom_repository,
            $care_plan_repository,
            $care_team_repository_for_transition,
            $schedule_repository_for_transition,
            $service_location_resolver,
            $this->access_policy,
            $this->user_provider,
            $retention_service
        );
        $care_commencement_service = new \JMReferral\Transition\CareCommencementService(
            $repository,
            $this->service,
            $pipeline_service,
            $la_decision_repository,
            $occupancy_repository,
            $transition_planning_service,
            $activity_service,
            $this->access_policy
        );
        $pipeline_attention_service = new \JMReferral\Pipeline\PipelineAttentionService(
            $repository,
            $package_cost_repository,
            $this->access_policy,
            $this->user_provider
        );

        $public_referral_service = new PublicReferralService(
            $this->service,
            $repository,
            $this->service_type_service,
            $document_service,
            $notification_service
        );
        $public_referral_controller = new PublicReferralController(
            $public_referral_service,
            $this->service_type_service
        );
        $public_referral_shortcode = new PublicReferralShortcode($public_referral_controller);
        $public_referral_controller->register();
        $public_referral_shortcode->register();

        $validator         = new ReferralValidator(
            $this->user_provider,
            $this->service_type_service,
            $this->workflow_stage_service
        );
        $create_controller = new ReferralController($this->service, $validator);
        $note_controller   = new ReferralNoteController(
            $note_service,
            $repository,
            $this->access_policy
        );
        $export_controller = new ReferralExportController(
            $repository,
            $this->filters,
            $this->user_provider,
            $this->service_type_service,
            $this->workflow_stage_service,
            $this->access_policy
        );

        $this->edit_controller = new ReferralEditController(
            $this->service,
            $validator,
            $repository,
            $this->user_provider,
            $this->service_type_service,
            $this->workflow_stage_service,
            $this->access_policy,
            $occupancy_repository,
            $pipeline_service
        );

        $assessment_controller = new ReferralAssessmentController(
            $assessment_service,
            $repository,
            $this->access_policy
        );

        $care_plan_controller = new ReferralCarePlanController(
            $care_plan_service,
            $repository,
            $this->access_policy
        );

        $this->list_controller = new ReferralListController(
            $repository,
            $this->user_provider,
            $this->filters,
            $this->service_type_service,
            $this->workflow_stage_service,
            $this->access_policy,
            $retention_service
        );
        $this->view_controller = new ReferralViewController(
            $repository,
            $activity_repository,
            $note_repository,
            $this->user_provider,
            $this->service_type_service,
            $this->workflow_stage_service,
            $this->service,
            $this->access_policy,
            $document_repository,
            $assessment_repository,
            $care_plan_repository,
            $care_plan_review_service,
            $this->care_visit_service,
            $this->care_team_service,
            $this->schedule_service,
            $this->visit_execution_service,
            $visit_task_service,
            $this->medication_service,
            $this->medication_administration_service,
            $retention_service,
            $pipeline_service,
            $interest_response_service,
            $assessment_scheduling_service,
            $package_cost_service,
            $la_decision_service,
            $non_proceeding_service,
            $transition_planning_service,
            $care_commencement_service
        );

        $document_controller = new ReferralDocumentController(
            $document_service,
            $repository,
            $this->access_policy
        );

        $document_migration_controller = new DocumentMigrationController($document_service);

        $this->care_plan_review_controller = new ReferralCarePlanReviewController(
            $care_plan_review_service,
            $repository,
            $this->access_policy,
            $this->user_provider
        );

        $this->care_visit_controller = new CareVisitController(
            $this->care_visit_service,
            $this->visit_execution_service,
            $repository,
            $this->access_policy,
            $this->user_provider,
            new ScheduleRepository()
        );

        $this->care_team_controller = new CareTeamController(
            $this->care_team_service,
            $repository,
            $this->access_policy,
            $this->user_provider
        );

        $this->schedule_controller = new ScheduleController(
            $this->schedule_service,
            $this->schedule_generation_service,
            $repository,
            $this->access_policy,
            $this->care_team_service,
            $this->user_provider
        );

        $this->medication_controller = new MedicationController(
            $this->medication_service,
            $repository,
            $this->access_policy
        );

        $this->registerStaffPortal(
            $repository,
            $activity_repository,
            $document_repository,
            $assessment_repository,
            $care_plan_repository,
            $visit_repository,
            $retention_service,
            $this->edit_controller,
            $assessment_controller,
            $care_plan_controller,
            $visit_task_service,
            $care_plan_review_service,
            $occupancy_repository,
            $pipeline_service,
            $interest_response_service,
            $assessment_scheduling_service,
            $package_cost_service,
            $la_decision_service,
            $non_proceeding_service,
            $transition_planning_service,
            $care_commencement_service,
            $pipeline_attention_service,
            $meeting_read_service,
            $meeting_service,
            $meeting_attendee_service,
            $meeting_repository,
            $meeting_attendee_repository,
            $responsibility_service
        );

        $create_controller->register();
        $this->list_controller->register();
        $this->edit_controller->register();
        $this->view_controller->register();
        $note_controller->register();
        $export_controller->register();
        $document_controller->register();
        $document_migration_controller->register();
        $assessment_controller->register();
        $care_plan_controller->register();
        $this->care_plan_review_controller->register();
        $this->care_visit_controller->register();
        $this->care_team_controller->register();
        $this->schedule_controller->register();
        $this->medication_controller->register();
        $this->service_type_controller->register();
        $this->workflow_stage_controller->register();
    }

    /**
     * Registers staff portal routing, access restriction, and controller.
     */
    private function registerStaffPortal(
        ReferralRepository $repository,
        ReferralActivityRepository $activity_repository,
        ReferralDocumentRepository $document_repository,
        ReferralAssessmentRepository $assessment_repository,
        ReferralCarePlanRepository $care_plan_repository,
        CareVisitRepository $visit_repository,
        ReferralRetentionService $retention_service,
        ReferralEditController $edit_controller,
        ReferralAssessmentController $assessment_controller,
        ReferralCarePlanController $care_plan_controller,
        VisitTaskService $visit_task_service,
        ReferralCarePlanReviewService $care_plan_review_service,
        OccupancyRepository $occupancy_repository,
        \JMReferral\Pipeline\ReferralPipelineService $pipeline_service,
        \JMReferral\Pipeline\InterestResponseService $interest_response_service,
        \JMReferral\Assessment\AssessmentSchedulingService $assessment_scheduling_service,
        \JMReferral\PackageCost\PackageCostService $package_cost_service,
        \JMReferral\LaDecision\LocalAuthorityDecisionService $la_decision_service,
        \JMReferral\Pipeline\ReferralNonProceedingService $non_proceeding_service,
        \JMReferral\Transition\TransitionPlanningService $transition_planning_service,
        \JMReferral\Transition\CareCommencementService $care_commencement_service,
        \JMReferral\Pipeline\PipelineAttentionService $pipeline_attention_service,
        \JMReferral\Meeting\ReferralMeetingReadService $meeting_read_service,
        \JMReferral\Meeting\ReferralMeetingService $meeting_service,
        \JMReferral\Meeting\MeetingAttendeeService $meeting_attendee_service,
        \JMReferral\Meeting\ReferralMeetingRepository $meeting_repository,
        \JMReferral\Meeting\MeetingAttendeeRepository $meeting_attendee_repository,
        \JMReferral\Meeting\ReferralResponsibilityService $responsibility_service
    ): void {
        $operational_alert_service = new OperationalAlertService(
            $repository,
            $assessment_repository,
            $care_plan_repository,
            new ReferralCarePlanReviewRepository(),
            new CareTeamRepository(),
            new ScheduleRepository(),
            $visit_repository,
            new VisitTaskRepository(),
            $this->access_policy,
            new MedicationAdministrationRepository()
        );

        $navigation = new PortalNavigation($this->access_policy);
        $controller = new PortalController(
            $navigation,
            $this->service,
            $repository,
            $this->filters,
            $this->access_policy,
            $this->user_provider,
            $this->service_type_service,
            $this->workflow_stage_service,
            $retention_service,
            $this->care_visit_service,
            $this->visit_execution_service,
            $this->care_team_service,
            $this->schedule_service,
            $operational_alert_service,
            $this->medication_administration_service,
            $this->medication_service,
            $document_repository,
            $assessment_repository,
            $care_plan_repository,
            $activity_repository,
            $edit_controller,
            new PortalRetentionHandler($retention_service),
            $assessment_controller,
            $care_plan_controller,
            $care_plan_review_service,
            $pipeline_service,
            $interest_response_service,
            $assessment_scheduling_service,
            $package_cost_service,
            $la_decision_service,
            $non_proceeding_service,
            $transition_planning_service,
            $care_commencement_service,
            $pipeline_attention_service
        );

        $clinical_access = new ClinicalAccess(
            $repository,
            $this->access_policy,
            $retention_service
        );

        $care_plan_review_handler = new CarePlanReviewHandler(
            $controller,
            $clinical_access,
            $this->care_plan_review_controller,
            $care_plan_repository
        );

        $medication_handler = new MedicationHandler(
            $controller,
            $clinical_access,
            $this->medication_controller,
            $this->medication_service
        );

        $care_team_handler = new CareTeamHandler(
            $controller,
            $clinical_access,
            $this->care_team_controller,
            $this->care_team_service,
            $this->user_provider
        );

        $service_location_resolver = $this->service_location_resolver;
        if (! $service_location_resolver instanceof ServiceLocationResolver) {
            throw new \RuntimeException('ServiceLocationResolver was not initialised before staff portal registration.');
        }

        $schedule_handler = new ScheduleHandler(
            $controller,
            $clinical_access,
            $this->schedule_controller,
            $this->schedule_service,
            $service_location_resolver
        );

        $visit_handler = new VisitHandler(
            $controller,
            $clinical_access,
            $this->care_visit_controller,
            $this->care_visit_service,
            $this->visit_execution_service,
            $visit_task_service,
            $this->medication_administration_service,
            $visit_repository,
            new ScheduleRepository(),
            $this->user_provider,
            $service_location_resolver
        );

        $clinical_dispatcher = new ClinicalDispatcher(
            $controller,
            $care_plan_review_handler,
            $medication_handler,
            $care_team_handler,
            $schedule_handler,
            $visit_handler
        );

        $controller->set_clinical_dispatcher($clinical_dispatcher);

        $meetings_handler = new \JMReferral\Portal\Meetings\MeetingsHandler(
            $controller,
            $clinical_access,
            $this->access_policy,
            $meeting_read_service,
            $meeting_service,
            $meeting_attendee_service,
            $meeting_repository,
            $meeting_attendee_repository,
            $retention_service
        );
        $controller->set_meetings_handler($meetings_handler);
        $controller->set_meeting_read_service($meeting_read_service);

        $responsibilities_handler = new \JMReferral\Portal\Responsibilities\ResponsibilitiesHandler(
            $controller,
            $clinical_access,
            $this->access_policy,
            $responsibility_service,
            $this->user_provider,
            $retention_service
        );
        $controller->set_responsibilities_handler($responsibilities_handler);

        $home_repository      = new HomeRepository();
        $bedroom_repository   = new BedroomRepository();
        $home_service         = new HomeService($home_repository, $this->user_provider, $occupancy_repository);
        $bedroom_service      = new BedroomService($bedroom_repository, $home_repository, $occupancy_repository);
        $occupancy_service    = new OccupancyService(
            $occupancy_repository,
            $home_repository,
            $bedroom_repository,
            $repository,
            $this->access_policy,
            new ReferralActivityService($activity_repository)
        );
        $dashboard_service = new HomeDashboardService(
            $home_service,
            $bedroom_service,
            $occupancy_service,
            $visit_repository,
            new ReferralCarePlanRepository(),
            new MedicationAdministrationRepository(),
            $this->access_policy,
            $this->user_provider
        );
        $homes_handler = new HomesHandler(
            $controller,
            $home_service,
            $bedroom_service,
            $this->user_provider,
            $occupancy_service,
            $dashboard_service
        );
        $controller->set_homes_handler($homes_handler);

        $occupancy_handler = new OccupancyHandler(
            $controller,
            $occupancy_service,
            $home_service,
            $repository,
            $this->access_policy
        );
        $controller->set_occupancy_handler($occupancy_handler);
        $controller->set_occupancy_service($occupancy_service);
        $controller->set_service_location_resolver($service_location_resolver);

        $meeting_repository_for_ops = new \JMReferral\Meeting\ReferralMeetingRepository();
        $operational_read = new \JMReferral\Pipeline\ManagementOperationalReadService(
            $repository,
            $meeting_repository_for_ops,
            $activity_repository,
            $this->workflow_stage_service,
            $this->access_policy,
            $this->user_provider,
            $pipeline_attention_service
        );

        $management_board = new \JMReferral\Pipeline\ManagementPipelineBoardService(
            $pipeline_attention_service,
            $repository,
            new \JMReferral\Pipeline\ReferralStageHistoryRepository(),
            new \JMReferral\PackageCost\PackageCostRepository(),
            $assessment_repository,
            $this->access_policy,
            $this->user_provider,
            $home_service,
            $occupancy_service,
            $occupancy_repository,
            new \JMReferral\LaDecision\LaDecisionRepository(),
            $operational_read
        );
        $controller->set_management_board_service($management_board);

        PortalRouter::set_controller($controller);
        PortalRouter::register();
        PortalAccess::register();
    }
}
