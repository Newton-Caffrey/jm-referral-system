<?php

namespace JMReferral\Admin;

use JMReferral\Admin\Pages\AddReferralPage;
use JMReferral\Admin\Pages\DashboardPage;
use JMReferral\Admin\Pages\ReferralsPage;
use JMReferral\Admin\Pages\SettingsPage;
use JMReferral\Assessment\ReferralAssessmentRepository;
use JMReferral\CarePlan\ReferralCarePlanRepository;
use JMReferral\CarePlan\ReferralCarePlanReviewController;
use JMReferral\CarePlan\ReferralCarePlanReviewRepository;
use JMReferral\CarePlan\ReferralCarePlanReviewService;
use JMReferral\CarePlan\ReferralCarePlanVersionRepository;
use JMReferral\CareTeam\CareTeamController;
use JMReferral\CareTeam\CareTeamRepository;
use JMReferral\CareTeam\CareTeamService;
use JMReferral\Documents\ReferralDocumentRepository;
use JMReferral\Notifications\EmailNotificationService;
use JMReferral\Notifications\NotificationService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralActivityRepository;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralEditController;
use JMReferral\Referral\ReferralFilters;
use JMReferral\Referral\ReferralListController;
use JMReferral\Referral\ReferralNoteRepository;
use JMReferral\Referral\ReferralNumberGenerator;
use JMReferral\Referral\ReferralRepository;
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
use JMReferral\Workflow\WorkflowStageController;
use JMReferral\Workflow\WorkflowStageRepository;
use JMReferral\Workflow\WorkflowStageService;

class Menu
{
    private DashboardPage $dashboard_page;
    private ReferralsPage $referrals_page;
    private AddReferralPage $add_referral_page;
    private SettingsPage $settings_page;
    private ReferralEditController $edit_controller;
    private ReferralViewController $view_controller;
    private ServiceTypeController $service_type_controller;
    private WorkflowStageController $workflow_stage_controller;
    private ?ReferralCarePlanReviewController $care_plan_review_controller;
    private CareVisitController $care_visit_controller;
    private CareTeamController $care_team_controller;
    private ScheduleController $schedule_controller;

    public function __construct(
        ?ReferralListController $list_controller = null,
        ?ReferralEditController $edit_controller = null,
        ?ReferralService $service = null,
        ?ReferralViewController $view_controller = null,
        ?UserProvider $user_provider = null,
        ?ReferralFilters $filters = null,
        ?ServiceTypeController $service_type_controller = null,
        ?ServiceTypeService $service_type_service = null,
        ?WorkflowStageController $workflow_stage_controller = null,
        ?WorkflowStageService $workflow_stage_service = null,
        ?AccessPolicy $access_policy = null,
        ?ReferralCarePlanReviewController $care_plan_review_controller = null,
        ?CareVisitController $care_visit_controller = null,
        ?CareVisitService $care_visit_service = null,
        ?CareTeamController $care_team_controller = null,
        ?CareTeamService $care_team_service = null,
        ?ScheduleController $schedule_controller = null,
        ?ScheduleService $schedule_service = null
    ) {
        $repository      = new ReferralRepository();
        $access_policy ??= new AccessPolicy();
        $user_provider ??= new UserProvider();
        $filters       ??= new ReferralFilters($user_provider, $access_policy);

        $service_type_repository = new ServiceTypeRepository();
        $service_type_service  ??= new ServiceTypeService($service_type_repository, $repository);
        $service_type_controller ??= new ServiceTypeController($service_type_service);

        $workflow_stage_repository = new WorkflowStageRepository();
        $workflow_stage_service  ??= new WorkflowStageService($workflow_stage_repository, $repository);
        $workflow_stage_controller ??= new WorkflowStageController($workflow_stage_service);

        $list_controller ??= new ReferralListController(
            $repository,
            $user_provider,
            $filters,
            $service_type_service,
            $workflow_stage_service,
            $access_policy
        );

        $number_generator     = new ReferralNumberGenerator($repository);
        $activity_repository  = new ReferralActivityRepository();
        $activity_service     = new ReferralActivityService($activity_repository);
        $note_repository      = new ReferralNoteRepository();
        $email_service        = new EmailNotificationService();
        $notification_service = new NotificationService($email_service, $user_provider);
        $service            ??= new ReferralService(
            $repository,
            $number_generator,
            $activity_service,
            $user_provider,
            $notification_service,
            $service_type_service,
            $workflow_stage_service,
            $access_policy
        );
        $validator = new ReferralValidator(
            $user_provider,
            $service_type_service,
            $workflow_stage_service
        );

        if (null === $edit_controller) {
            $edit_controller = new ReferralEditController(
                $service,
                $validator,
                $repository,
                $user_provider,
                $service_type_service,
                $workflow_stage_service,
                $access_policy
            );
        }

        $care_plan_repository         = new ReferralCarePlanRepository();
        $care_plan_review_service     = new ReferralCarePlanReviewService(
            new ReferralCarePlanReviewRepository(),
            new ReferralCarePlanVersionRepository(),
            $care_plan_repository,
            $repository,
            $activity_service,
            $access_policy
        );

        $care_team_service ??= new CareTeamService(
            new CareTeamRepository(),
            $repository,
            $care_plan_repository,
            $activity_service,
            $access_policy,
            $user_provider
        );

        $schedule_service ??= new ScheduleService(
            new ScheduleRepository(),
            $repository,
            $care_plan_repository,
            new CareTeamRepository(),
            $activity_service,
            $access_policy
        );

        $visit_repository = new CareVisitRepository();
        $visit_task_service = new VisitTaskService(
            new VisitTaskRepository(),
            $visit_repository,
            $care_plan_repository
        );

        $care_visit_service ??= new CareVisitService(
            $visit_repository,
            $repository,
            $care_plan_repository,
            $activity_service,
            $access_policy,
            $user_provider,
            $care_team_service,
            $visit_task_service
        );

        $schedule_generation_service = new ScheduleGenerationService(
            new ScheduleRepository(),
            $visit_repository,
            $repository,
            $care_plan_repository,
            new CareTeamRepository(),
            $activity_service,
            $access_policy,
            $visit_task_service
        );

        $visit_execution_service = new VisitExecutionService(
            $visit_repository,
            $repository,
            $activity_service,
            $access_policy,
            $visit_task_service
        );

        $view_controller ??= new ReferralViewController(
            $repository,
            $activity_repository,
            $note_repository,
            $user_provider,
            $service_type_service,
            $workflow_stage_service,
            $service,
            $access_policy,
            new ReferralDocumentRepository(),
            new ReferralAssessmentRepository(),
            $care_plan_repository,
            $care_plan_review_service,
            $care_visit_service,
            $care_team_service,
            $schedule_service,
            $visit_execution_service,
            $visit_task_service
        );

        $this->dashboard_page            = new DashboardPage(
            $service,
            $care_visit_service,
            $user_provider,
            $repository,
            $care_team_service,
            $access_policy,
            $schedule_service,
            $visit_execution_service,
            $visit_task_service
        );
        $this->referrals_page            = new ReferralsPage($list_controller);
        $this->add_referral_page         = new AddReferralPage($user_provider, $service_type_service);
        $this->settings_page             = new SettingsPage();
        $this->edit_controller           = $edit_controller;
        $this->view_controller           = $view_controller;
        $this->service_type_controller   = $service_type_controller;
        $this->workflow_stage_controller = $workflow_stage_controller;
        $this->care_plan_review_controller = $care_plan_review_controller ?? new ReferralCarePlanReviewController(
            $care_plan_review_service,
            $repository,
            $access_policy,
            $user_provider
        );
        $this->care_visit_controller = $care_visit_controller ?? new CareVisitController(
            $care_visit_service,
            $visit_execution_service,
            $repository,
            $access_policy,
            $user_provider,
            new ScheduleRepository()
        );
        $this->care_team_controller = $care_team_controller ?? new CareTeamController(
            $care_team_service,
            $repository,
            $access_policy,
            $user_provider
        );
        $this->schedule_controller = $schedule_controller ?? new ScheduleController(
            $schedule_service,
            $schedule_generation_service,
            $repository,
            $access_policy,
            $care_team_service,
            $user_provider
        );
    }

    public function register(): void
    {
        add_menu_page(
            __('J&M Referrals', 'jm-referral-system'),
            __('J&M Referrals', 'jm-referral-system'),
            Capabilities::VIEW_DASHBOARD,
            'jm-referrals',
            [$this->dashboard_page, 'render'],
            'dashicons-groups',
            26
        );

        add_submenu_page(
            'jm-referrals',
            __('Dashboard', 'jm-referral-system'),
            __('Dashboard', 'jm-referral-system'),
            Capabilities::VIEW_DASHBOARD,
            'jm-referrals',
            [$this->dashboard_page, 'render']
        );

        add_submenu_page(
            'jm-referrals',
            __('Referrals', 'jm-referral-system'),
            __('Referrals', 'jm-referral-system'),
            Capabilities::VIEW_REFERRALS,
            'jm-referrals-list',
            [$this->referrals_page, 'render']
        );

        add_submenu_page(
            'jm-referrals',
            __('Add Referral', 'jm-referral-system'),
            __('Add Referral', 'jm-referral-system'),
            Capabilities::CREATE_REFERRALS,
            'jm-referrals-add',
            [$this->add_referral_page, 'render']
        );

        add_submenu_page(
            'jm-referrals',
            __('Service Types', 'jm-referral-system'),
            __('Service Types', 'jm-referral-system'),
            Capabilities::MANAGE_SERVICE_TYPES,
            'jm-referrals-service-types',
            [$this->service_type_controller, 'render_list']
        );

        add_submenu_page(
            'jm-referrals',
            __('Add Service Type', 'jm-referral-system'),
            __('Add Service Type', 'jm-referral-system'),
            Capabilities::MANAGE_SERVICE_TYPES,
            'jm-referrals-service-types-add',
            [$this->service_type_controller, 'render_create']
        );

        add_submenu_page(
            null,
            __('Edit Service Type', 'jm-referral-system'),
            __('Edit Service Type', 'jm-referral-system'),
            Capabilities::MANAGE_SERVICE_TYPES,
            'jm-referrals-service-types-edit',
            [$this->service_type_controller, 'render_edit']
        );

        add_submenu_page(
            'jm-referrals',
            __('Workflow Stages', 'jm-referral-system'),
            __('Workflow Stages', 'jm-referral-system'),
            Capabilities::MANAGE_WORKFLOW_STAGES,
            'jm-referrals-workflow-stages',
            [$this->workflow_stage_controller, 'render_list']
        );

        add_submenu_page(
            'jm-referrals',
            __('Add Workflow Stage', 'jm-referral-system'),
            __('Add Workflow Stage', 'jm-referral-system'),
            Capabilities::MANAGE_WORKFLOW_STAGES,
            'jm-referrals-workflow-stages-add',
            [$this->workflow_stage_controller, 'render_create']
        );

        add_submenu_page(
            null,
            __('Edit Workflow Stage', 'jm-referral-system'),
            __('Edit Workflow Stage', 'jm-referral-system'),
            Capabilities::MANAGE_WORKFLOW_STAGES,
            'jm-referrals-workflow-stages-edit',
            [$this->workflow_stage_controller, 'render_edit']
        );

        add_submenu_page(
            null,
            __('Edit Referral', 'jm-referral-system'),
            __('Edit Referral', 'jm-referral-system'),
            Capabilities::EDIT_REFERRALS,
            'jm-referrals-edit',
            [$this->edit_controller, 'render']
        );

        add_submenu_page(
            null,
            __('View Referral', 'jm-referral-system'),
            __('View Referral', 'jm-referral-system'),
            Capabilities::VIEW_REFERRALS,
            'jm-referrals-view',
            [$this->view_controller, 'render']
        );

        add_submenu_page(
            null,
            __('Care Plan Version', 'jm-referral-system'),
            __('Care Plan Version', 'jm-referral-system'),
            Capabilities::VIEW_CARE_PLANS,
            'jm-referrals-care-plan-version',
            [$this->care_plan_review_controller, 'render_version']
        );

        add_submenu_page(
            null,
            __('Edit Care Visit', 'jm-referral-system'),
            __('Edit Care Visit', 'jm-referral-system'),
            Capabilities::MANAGE_VISITS,
            'jm-referrals-visit-edit',
            [$this->care_visit_controller, 'render_edit']
        );

        add_submenu_page(
            null,
            __('Edit Care Team Assignment', 'jm-referral-system'),
            __('Edit Care Team Assignment', 'jm-referral-system'),
            Capabilities::MANAGE_CARE_TEAM,
            'jm-referrals-care-team-edit',
            [$this->care_team_controller, 'render_edit']
        );

        add_submenu_page(
            null,
            __('Edit Schedule', 'jm-referral-system'),
            __('Edit Schedule', 'jm-referral-system'),
            Capabilities::MANAGE_SCHEDULES,
            'jm-referrals-schedule-edit',
            [$this->schedule_controller, 'render_edit']
        );

        add_submenu_page(
            'jm-referrals',
            __('Settings', 'jm-referral-system'),
            __('Settings', 'jm-referral-system'),
            Capabilities::MANAGE_SETTINGS,
            'jm-referrals-settings',
            [$this->settings_page, 'render']
        );
    }
}
