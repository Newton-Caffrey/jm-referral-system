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
use JMReferral\Notifications\EmailNotificationService;
use JMReferral\Notifications\NotificationService;
use JMReferral\Permissions\AccessPolicy;
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
use JMReferral\Visits\VisitExecutionService;
use JMReferral\Visits\VisitTaskRepository;
use JMReferral\Visits\VisitTaskService;
use JMReferral\Medication\MedicationAdministrationController;
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
        $assessment_service    = new ReferralAssessmentService(
            $assessment_repository,
            $repository,
            $activity_service,
            $this->access_policy
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

        $this->visit_execution_service = new VisitExecutionService(
            $visit_repository,
            $repository,
            $activity_service,
            $this->access_policy,
            $visit_task_service,
            $this->medication_administration_service
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

        $email_service        = new EmailNotificationService();
        $notification_service = new NotificationService($email_service, $this->user_provider);
        $this->service        = new ReferralService(
            $repository,
            $number_generator,
            $activity_service,
            $this->user_provider,
            $notification_service,
            $this->service_type_service,
            $this->workflow_stage_service,
            $this->access_policy
        );
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

        $retention_service = new ReferralRetentionService(
            $repository,
            new ReferralDependencyRepository(),
            $activity_service,
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
        $this->edit_controller = new ReferralEditController(
            $this->service,
            $validator,
            $repository,
            $this->user_provider,
            $this->service_type_service,
            $this->workflow_stage_service,
            $this->access_policy
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
            $retention_service
        );

        $document_controller = new ReferralDocumentController(
            $document_service,
            $repository,
            $this->access_policy
        );

        $document_migration_controller = new DocumentMigrationController($document_service);

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
        new MedicationAdministrationController($this->medication_administration_service);

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
}
