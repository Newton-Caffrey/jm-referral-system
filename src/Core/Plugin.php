<?php

namespace JMReferral\Core;

use JMReferral\Admin\Menu;
use JMReferral\Database\Migrator;
use JMReferral\Documents\ReferralDocumentController;
use JMReferral\Documents\ReferralDocumentRepository;
use JMReferral\Documents\ReferralDocumentService;
use JMReferral\Notifications\EmailNotificationService;
use JMReferral\Notifications\NotificationService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Referral\ReferralActivityRepository;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralController;
use JMReferral\Referral\ReferralEditController;
use JMReferral\Referral\ReferralExportController;
use JMReferral\Referral\ReferralFilters;
use JMReferral\Referral\ReferralListController;
use JMReferral\Referral\ReferralNoteController;
use JMReferral\Referral\ReferralNoteRepository;
use JMReferral\Referral\ReferralNoteService;
use JMReferral\Referral\ReferralNumberGenerator;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Referral\ReferralService;
use JMReferral\Referral\ReferralValidator;
use JMReferral\Referral\ReferralViewController;
use JMReferral\Services\ServiceTypeController;
use JMReferral\Services\ServiceTypeRepository;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;
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

    public function run(): void
    {
        add_action('plugins_loaded', [Migrator::class, 'maybe_migrate']);
        add_action('admin_menu', [$this, 'registerAdminMenu']);

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
            $this->access_policy
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

        $document_repository = new ReferralDocumentRepository();
        $document_service    = new ReferralDocumentService(
            $document_repository,
            $repository,
            $activity_service,
            $this->access_policy
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

        $this->list_controller = new ReferralListController(
            $repository,
            $this->user_provider,
            $this->filters,
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
            $document_repository
        );

        $document_controller = new ReferralDocumentController(
            $document_service,
            $repository,
            $this->access_policy
        );

        $create_controller->register();
        $this->list_controller->register();
        $this->edit_controller->register();
        $this->view_controller->register();
        $note_controller->register();
        $export_controller->register();
        $document_controller->register();
        $this->service_type_controller->register();
        $this->workflow_stage_controller->register();
    }
}
