<?php

namespace JMReferral\Portal;

use JMReferral\Alerts\OperationalAlertService;
use JMReferral\Assessment\ReferralAssessmentController;
use JMReferral\Assessment\ReferralAssessmentRepository;
use JMReferral\Assessment\ReferralAssessmentService;
use JMReferral\CarePlan\ReferralCarePlanController;
use JMReferral\CarePlan\ReferralCarePlanRepository;
use JMReferral\CarePlan\ReferralCarePlanReviewService;
use JMReferral\CarePlan\ReferralCarePlanService;
use JMReferral\CareTeam\CareTeamService;
use JMReferral\Documents\ReferralDocumentController;
use JMReferral\Documents\ReferralDocumentRepository;
use JMReferral\Frontend\ReferrerTypes;
use JMReferral\Frontend\SubmissionChannels;
use JMReferral\Medication\MedicationAdministrationService;
use JMReferral\Medication\MedicationService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\Clinical\ClinicalDispatcher;
use JMReferral\Portal\Clinical\PortalViewHost;
use JMReferral\Referral\ReferralActivityRepository;
use JMReferral\Referral\ReferralEditController;
use JMReferral\Referral\ReferralFilters;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Referral\ReferralRetentionService;
use JMReferral\Referral\ReferralService;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;
use JMReferral\Visits\CareVisitService;
use JMReferral\Visits\VisitExecutionService;
use JMReferral\Scheduling\ScheduleService;
use JMReferral\Workflow\WorkflowStageService;

/**
 * Staff portal request dispatcher and view-model builder.
 */
class PortalController implements PortalViewHost
{
    private const VIEW_VISITS_LIMIT = 10;
    private const VIEW_ACTIVITY_LIMIT = 25;

    /**
     * Set after construction via set_clinical_dispatcher() to break the
     * circular dependency (ClinicalDispatcher depends on this class's
     * PortalViewHost interface).
     */
    private ?ClinicalDispatcher $clinical_dispatcher = null;

    public function __construct(
        private PortalNavigation $navigation,
        private ReferralService $referral_service,
        private ReferralRepository $referral_repository,
        private ReferralFilters $filters,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider,
        private ServiceTypeService $service_type_service,
        private WorkflowStageService $workflow_stage_service,
        private ReferralRetentionService $retention_service,
        private CareVisitService $visit_service,
        private VisitExecutionService $visit_execution_service,
        private CareTeamService $care_team_service,
        private ScheduleService $schedule_service,
        private OperationalAlertService $alert_service,
        private MedicationAdministrationService $medication_administration_service,
        private MedicationService $medication_service,
        private ReferralDocumentRepository $document_repository,
        private ReferralAssessmentRepository $assessment_repository,
        private ReferralCarePlanRepository $care_plan_repository,
        private ReferralActivityRepository $activity_repository,
        private ReferralEditController $edit_controller,
        private PortalRetentionHandler $retention_handler,
        private ReferralAssessmentController $assessment_controller,
        private ReferralCarePlanController $care_plan_controller,
        private ReferralCarePlanReviewService $care_plan_review_service
    ) {
    }

    /**
     * Breaks the PortalController <-> ClinicalDispatcher circular dependency.
     * Must be called once during plugin bootstrap before dispatch().
     */
    public function set_clinical_dispatcher(ClinicalDispatcher $clinical_dispatcher): void
    {
        $this->clinical_dispatcher = $clinical_dispatcher;
    }

    public function render_portal_page(
        string $template,
        string $page_title,
        string $current_route,
        array $breadcrumbs,
        array $view
    ): void {
        $this->render_page($template, $page_title, $current_route, $breadcrumbs, $view);
    }

    public function render_portal_error(string $template, string $title, int $status): void
    {
        $this->render_error($template, $title, $status);
    }

    public function dispatch(): void
    {
        $this->send_privacy_headers();

        if (! PortalSettings::is_enabled()) {
            $this->render_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (! is_user_logged_in()) {
            PortalAccess::require_login_redirect();
        }

        if (! PortalAccess::current_user_can_access_portal()) {
            $this->render_error(
                '403',
                __('Access Denied', 'jm-referral-system'),
                403
            );

            return;
        }

        $this->retention_handler->maybe_handle();

        $route = sanitize_key((string) get_query_var(PortalRouter::QV_ROUTE));
        if ('' === $route) {
            $route = 'dashboard';
        }

        $clinical_routes = [
            'care_plan_review',
            'medication_new',
            'medication_edit',
            'care_team_new',
            'care_team_edit',
            'schedule_new',
            'schedule_edit',
            'schedule_generate',
            'visit_new',
            'visit_edit',
            'visit_execute',
            'visit_review',
        ];

        if (in_array($route, $clinical_routes, true)) {
            if (null === $this->clinical_dispatcher) {
                $this->render_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }

            $this->clinical_dispatcher->dispatch($route);

            return;
        }

        match ($route) {
            'dashboard'            => $this->render_dashboard(),
            'referrals'            => $this->render_referral_list(),
            'referral'             => $this->render_referral_view(),
            'referral_edit'        => $this->render_referral_edit(),
            'referral_assessment'  => $this->render_referral_assessment(),
            'referral_care_plan'   => $this->render_referral_care_plan(),
            default                => $this->render_error('404', __('Not Found', 'jm-referral-system'), 404),
        };
    }

    private function send_privacy_headers(): void
    {
        if (! headers_sent()) {
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('X-Content-Type-Options: nosniff');
        }

        nocache_headers();
    }

    private function render_dashboard(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_DASHBOARD)) {
            $this->render_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $dashboard          = $this->referral_service->get_dashboard_data(5);
        $stats              = $dashboard['stats'];
        $recent             = $dashboard['recent'];
        $scoped_to_assigned = ! empty($dashboard['scoped_to_assigned']);

        foreach ($recent as $index => $row) {
            $recent[$index] = array_merge($row, $this->build_referral_portal_actions($row));
        }

        $can_view_visits     = Capabilities::current_user_can(Capabilities::VIEW_VISITS);
        $visit_status_labels = CareVisitService::status_labels();
        $visit_outcome_labels = VisitExecutionService::outcome_labels();
        $upcoming_visits     = [];

        if ($can_view_visits) {
            $upcoming_visits = $this->enrich_portal_visits(
                $this->visit_service->get_upcoming_for_dashboard(10),
                $visit_outcome_labels,
                false
            );
        }

        $show_my_active_clients = $this->access_policy->should_scope_to_assigned()
            && Capabilities::current_user_can(Capabilities::VIEW_CARE_TEAM);
        $my_active_clients_count = 0;
        if ($show_my_active_clients) {
            $my_active_clients_count = $this->care_team_service->count_active_clients_for_user(
                get_current_user_id()
            );
        }

        $show_awaiting_review = Capabilities::current_user_can(Capabilities::MANAGE_VISITS)
            && ! $this->access_policy->should_scope_to_assigned();
        $awaiting_review_visits = [];
        if ($show_awaiting_review) {
            $awaiting_review_visits = $this->enrich_portal_visits(
                $this->visit_execution_service->get_awaiting_review_for_dashboard(10),
                $visit_outcome_labels,
                true
            );
        }

        $show_todays_completed = $this->access_policy->should_scope_to_assigned()
            && Capabilities::current_user_can(Capabilities::EXECUTE_VISITS);
        $todays_completed_visits = [];
        if ($show_todays_completed) {
            $todays_completed_visits = $this->enrich_portal_visits(
                $this->visit_execution_service->get_todays_completed_for_current_user(10),
                $visit_outcome_labels,
                true
            );
        }

        $show_operational_alerts = false;
        $operational_alerts      = null;
        $alert_count             = 0;
        if (Capabilities::current_user_can(Capabilities::VIEW_OPERATIONAL_ALERTS)) {
            $alert_result            = $this->alert_service->get_alerts();
            $operational_alerts      = $this->alert_service->format_dashboard_alerts($alert_result);
            $operational_alerts['view_all_url'] = '';
            $show_operational_alerts = true;
            $alert_count             = absint($operational_alerts['counts']['total'] ?? 0);
        }

        $show_medication_exceptions = Capabilities::current_user_can(Capabilities::MANAGE_VISITS)
            && ! $this->access_policy->should_scope_to_assigned();
        $medication_exceptions_today = 0;
        if ($show_medication_exceptions) {
            $medication_exceptions_today = $this->medication_administration_service->count_exceptions_today_for_managers();
        }

        $show_my_medication_exceptions = $this->access_policy->should_scope_to_assigned()
            && Capabilities::current_user_can(Capabilities::ADMINISTER_MEDICATIONS);
        $my_medication_exceptions_today = 0;
        if ($show_my_medication_exceptions) {
            $my_medication_exceptions_today = $this->medication_administration_service->count_my_exceptions_today();
        }

        $visits_today_count = 0;
        if ($can_view_visits) {
            $visits_today_count = count($upcoming_visits);
            // Prefer count of today's scheduled when available from upcoming rows.
            $today = wp_date('Y-m-d');
            $today_only = 0;
            foreach ($upcoming_visits as $v) {
                if ((string) ($v['visit_date'] ?? '') === $today) {
                    ++$today_only;
                }
            }
            if ($today_only > 0) {
                $visits_today_count = $today_only;
            }
        }

        $current_user  = wp_get_current_user();
        $welcome_name  = ($current_user instanceof \WP_User && $current_user->ID > 0)
            ? $current_user->display_name
            : '';
        $welcome_role  = $this->navigation->role_label();

        $view = [
            'welcome_name'                   => $welcome_name,
            'welcome_role'                   => $welcome_role,
            'stats'                          => $stats,
            'recent'                         => $recent,
            'scoped_to_assigned'             => $scoped_to_assigned,
            'can_view_visits'                => $can_view_visits,
            'upcoming_visits'                => $upcoming_visits,
            'visit_status_labels'            => $visit_status_labels,
            'show_my_active_clients'         => $show_my_active_clients,
            'my_active_clients_count'        => $my_active_clients_count,
            'show_awaiting_review'           => $show_awaiting_review,
            'awaiting_review_visits'         => $awaiting_review_visits,
            'show_todays_completed'          => $show_todays_completed,
            'todays_completed_visits'        => $todays_completed_visits,
            'show_operational_alerts'        => $show_operational_alerts,
            'operational_alerts'             => $operational_alerts,
            'alert_count'                    => $alert_count,
            'show_medication_exceptions'     => $show_medication_exceptions,
            'medication_exceptions_today'    => $medication_exceptions_today,
            'show_my_medication_exceptions'  => $show_my_medication_exceptions,
            'my_medication_exceptions_today' => $my_medication_exceptions_today,
            'visits_today_count'             => $visits_today_count,
            'referrals_url'                  => PortalUrls::referrals(),
        ];

        $this->render_page(
            'dashboard',
            __('Dashboard', 'jm-referral-system'),
            'dashboard',
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => ''],
            ],
            $view
        );
    }

    private function render_referral_list(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_REFERRALS)) {
            $this->render_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $filters            = $this->filters->from_request();
        $pagination         = $this->filters->pagination_from_request();
        $per_page           = $pagination['per_page'];
        $page               = $pagination['page'];
        $access_assigned_to = $this->access_policy->get_assigned_user_constraint();
        $scope_to_assigned  = null !== $access_assigned_to;
        $assignable_users   = $scope_to_assigned ? [] : $this->user_provider->get_assignable_users();
        $can_filter_archive = ! $scope_to_assigned;

        if (! $can_filter_archive) {
            $filters['archive_scope'] = 'active';
        }

        $query_result = $this->referral_repository->query($filters, $per_page, $page, $access_assigned_to);
        $total        = absint($query_result['total'] ?? 0);
        $total_pages  = $per_page > 0 ? (int) max(1, (int) ceil($total / $per_page)) : 1;
        if ($page > $total_pages) {
            $page         = $total_pages;
            $query_result = $this->referral_repository->query($filters, $per_page, $page, $access_assigned_to);
        }

        $service_type_ids   = [];
        $workflow_stage_ids = [];
        $assignee_ids       = [];
        foreach ($query_result['items'] as $referral) {
            $service_type_id = absint($referral['service_type_id'] ?? 0);
            if ($service_type_id > 0) {
                $service_type_ids[] = $service_type_id;
            }
            $workflow_stage_id = absint($referral['workflow_stage_id'] ?? 0);
            if ($workflow_stage_id > 0) {
                $workflow_stage_ids[] = $workflow_stage_id;
            }
            $assigned_to = absint($referral['assigned_to'] ?? 0);
            if ($assigned_to > 0) {
                $assignee_ids[] = $assigned_to;
            }
        }

        $service_names  = $this->service_type_service->get_names_by_ids($service_type_ids);
        $stage_names    = $this->workflow_stage_service->get_names_by_ids($workflow_stage_ids);
        $assignee_names = $this->user_provider->get_display_names_by_ids($assignee_ids);
        $referrals      = [];

        foreach ($query_result['items'] as $referral) {
            $assigned_to = absint($referral['assigned_to'] ?? 0);
            $referral['assigned_to_name'] = $assigned_to > 0
                ? (string) ($assignee_names[$assigned_to] ?? '')
                : '';

            $service_type_id = absint($referral['service_type_id'] ?? 0);
            $referral['service_name'] = ($service_type_id > 0 && isset($service_names[$service_type_id]))
                ? $service_names[$service_type_id]
                : (string) ($referral['service_required'] ?? '');

            $workflow_stage_id = absint($referral['workflow_stage_id'] ?? 0);
            $referral['workflow_stage_name'] = ($workflow_stage_id > 0 && isset($stage_names[$workflow_stage_id]))
                ? $stage_names[$workflow_stage_id]
                : '';

            $referral['is_archived'] = $this->retention_service->is_archived($referral);
            $referral                = array_merge($referral, $this->build_referral_portal_actions($referral));
            $referrals[]             = $referral;
        }

        $list_args = $this->portal_list_query_args($filters, $per_page);
        $list_base = add_query_arg($list_args, PortalUrls::referrals());
        $pagination_links = '';
        if ($total_pages > 1) {
            $pagination_links = paginate_links(
                [
                    'base'      => esc_url_raw($list_base) . '%_%',
                    'format'    => '&paged=%#%',
                    'current'   => $page,
                    'total'     => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'type'      => 'list',
                ]
            );
        }

        $archive_scope = (string) ($filters['archive_scope'] ?? 'active');
        $scope_urls    = [];
        if ($can_filter_archive) {
            foreach (['active', 'archived', 'all'] as $scope_key) {
                $scope_filters                 = $filters;
                $scope_filters['archive_scope'] = $scope_key;
                $scope_urls[$scope_key]        = PortalUrls::referrals_with_args(
                    $this->portal_list_query_args($scope_filters, $per_page)
                );
            }
        }

        $empty_message = match ($archive_scope) {
            'archived' => __('No archived referrals found.', 'jm-referral-system'),
            'all'      => __('No referrals found.', 'jm-referral-system'),
            default    => __('No active referrals found.', 'jm-referral-system'),
        };

        $page_title = $scope_to_assigned
            ? __('My Referrals', 'jm-referral-system')
            : __('Referrals', 'jm-referral-system');

        $view = [
            'referrals'           => $referrals,
            'filters'             => $filters,
            'assignable_users'    => $assignable_users,
            'scope_to_assigned'   => $scope_to_assigned,
            'can_filter_assignee' => ! $scope_to_assigned,
            'can_filter_archive'  => $can_filter_archive,
            'archive_scope'       => $archive_scope,
            'scope_urls'          => $scope_urls,
            'empty_message'       => $empty_message,
            'archived_list_url'   => $can_filter_archive
                ? PortalUrls::referrals_with_args(['jmrs_archive_scope' => 'archived'])
                : '',
            'per_page'            => $per_page,
            'page'                => $page,
            'total'               => $total,
            'total_pages'         => $total_pages,
            'from'                => 0 === $total ? 0 : (($page - 1) * $per_page) + 1,
            'to'                  => min($page * $per_page, $total),
            'pagination_links'    => is_string($pagination_links) ? $pagination_links : '',
            'form_action'         => PortalUrls::referrals(),
            'allowed_per_page'    => ReferralFilters::ALLOWED_PER_PAGE,
            'status_options'      => [
                'new'         => __('New', 'jm-referral-system'),
                'in_progress' => __('In Progress', 'jm-referral-system'),
                'completed'   => __('Completed', 'jm-referral-system'),
                'cancelled'   => __('Cancelled', 'jm-referral-system'),
            ],
            'priority_options'    => [
                'low'    => __('Low', 'jm-referral-system'),
                'medium' => __('Medium', 'jm-referral-system'),
                'high'   => __('High', 'jm-referral-system'),
                'urgent' => __('Urgent', 'jm-referral-system'),
            ],
            'list_notice'         => $this->portal_retention_notice(),
        ];

        $this->render_page(
            'referrals/list',
            $page_title,
            'referrals',
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
                ['label' => $page_title, 'url' => ''],
            ],
            $view
        );
    }

    private function render_referral_view(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_REFERRALS)) {
            $this->render_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $referral_id = absint(get_query_var(PortalRouter::QV_ID));
        $referral    = $referral_id > 0 ? $this->referral_repository->find($referral_id) : null;

        // Generic 404 for missing or inaccessible referrals (no existence leak).
        if (null === $referral || ! $this->access_policy->can_view_referral($referral)) {
            $this->render_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $assigned_to = absint($referral['assigned_to'] ?? 0);
        $assigned_to_name = $assigned_to > 0
            ? $this->user_provider->get_display_name($assigned_to)
            : '';

        $archived_by = absint($referral['archived_by'] ?? 0);
        $archived_by_name = $archived_by > 0
            ? $this->user_provider->get_display_name($archived_by)
            : '';

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

        $is_archived = $this->retention_service->is_archived($referral);

        $can_download_documents = Capabilities::current_user_can(Capabilities::DOWNLOAD_DOCUMENTS);
        $documents              = [];
        if ($can_download_documents) {
            foreach ($this->document_repository->get_by_referral_id($referral_id) as $document_row) {
                $uploader_id = absint($document_row['uploaded_by'] ?? 0);
                $document_row['uploaded_by_name'] = $uploader_id > 0
                    ? $this->user_provider->get_display_name($uploader_id)
                    : '';
                $document_row['download_url'] = ReferralDocumentController::get_download_url(
                    absint($document_row['id'] ?? 0)
                );
                $documents[] = $document_row;
            }
        }

        $assessment = $this->assessment_repository->find_by_referral($referral_id);
        $assessor_name = '';
        $assessment_data = ReferralAssessmentService::empty_form_data();
        if (null !== $assessment) {
            $assessor_user_id = absint($assessment['assessor_user_id'] ?? 0);
            $assessor_name    = $assessor_user_id > 0
                ? $this->user_provider->get_display_name($assessor_user_id)
                : '';
            $assessment_data = ReferralAssessmentService::map_to_form_data($assessment);
        }

        $can_view_care_plan = Capabilities::current_user_can(Capabilities::VIEW_CARE_PLANS);
        $care_plan          = $can_view_care_plan
            ? $this->care_plan_repository->find_by_referral($referral_id)
            : null;
        $care_plan_statuses = ReferralCarePlanService::status_labels();
        $care_plan_data     = ReferralCarePlanService::map_to_form_data($care_plan);
        $care_plan_created_by_name = '';
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

        $care_plan_reviews = [];
        if ($can_view_care_plan) {
            foreach ($this->care_plan_review_service->get_reviews_for_referral($referral_id) as $review_row) {
                $reviewer_id = absint($review_row['reviewed_by'] ?? 0);
                $review_row['reviewer_name'] = $reviewer_id > 0
                    ? $this->user_provider->get_display_name($reviewer_id)
                    : '';
                $care_plan_reviews[] = $review_row;
            }
        }
        $care_plan_review_outcome_labels = ReferralCarePlanReviewService::outcome_labels();

        $can_review_care_plan = ! $is_archived
            && null !== $care_plan
            && Capabilities::current_user_can(Capabilities::REVIEW_CARE_PLANS)
            && $this->access_policy->can_edit_referral($referral)
            && $this->access_policy->can_mutate_referral($referral);

        $can_view_care_team = Capabilities::current_user_can(Capabilities::VIEW_CARE_TEAM);
        $can_manage_care_team = $this->care_team_service->can_manage_care_team($referral);
        $care_team_roles    = CareTeamService::role_labels();
        $care_team_statuses = CareTeamService::status_labels();
        $care_team_members  = [];
        if ($can_view_care_team) {
            foreach ($this->care_team_service->get_members_for_referral($referral_id) as $member_row) {
                $member_user_id = absint($member_row['user_id'] ?? 0);
                $member_row['staff_name'] = $member_user_id > 0
                    ? $this->user_provider->get_display_name($member_user_id)
                    : '';
                $member_row['edit_url'] = $can_manage_care_team
                    ? PortalUrls::care_team_edit($referral_id, absint($member_row['id'] ?? 0))
                    : '';
                $care_team_members[] = $member_row;
            }
        }

        $can_view_visits = Capabilities::current_user_can(Capabilities::VIEW_VISITS);
        $can_manage_visits = $this->visit_service->can_manage_visits_for_referral($referral);
        $care_visits     = [];
        $visit_status_labels  = CareVisitService::status_labels();
        $visit_outcome_labels = VisitExecutionService::outcome_labels();
        if ($can_view_visits) {
            $visit_rows = $this->care_visit_service_get_recent($referral_id);
            $user_ids   = [];
            $schedule_ids = [];
            foreach ($visit_rows as $visit_row) {
                $assigned_id = absint($visit_row['assigned_user_id'] ?? 0);
                if ($assigned_id > 0) {
                    $user_ids[] = $assigned_id;
                }
                $schedule_id = absint($visit_row['schedule_id'] ?? 0);
                if ($schedule_id > 0) {
                    $schedule_ids[] = $schedule_id;
                }
            }
            $names = $this->user_provider->get_display_names_by_ids($user_ids);
            $schedule_names = [] !== $schedule_ids
                ? $this->schedule_service->get_names_by_ids($schedule_ids)
                : [];
            foreach ($visit_rows as $visit_row) {
                $assigned_id = absint($visit_row['assigned_user_id'] ?? 0);
                $visit_row['assigned_staff_name'] = $assigned_id > 0
                    ? (string) ($names[$assigned_id] ?? '')
                    : '';
                $schedule_id = absint($visit_row['schedule_id'] ?? 0);
                if ($schedule_id > 0) {
                    $schedule_name = (string) ($schedule_names[$schedule_id] ?? '');
                    $visit_row['source_label'] = '' !== $schedule_name
                        ? sprintf(
                            /* translators: %s: schedule name */
                            __('Schedule: %s', 'jm-referral-system'),
                            $schedule_name
                        )
                        : __('Schedule', 'jm-referral-system');
                } else {
                    $visit_row['source_label'] = __('Manual', 'jm-referral-system');
                }
                $outcome_key = (string) ($visit_row['visit_outcome'] ?? '');
                $visit_row['outcome_label'] = isset($visit_outcome_labels[$outcome_key])
                    ? $visit_outcome_labels[$outcome_key]
                    : $outcome_key;

                $visit_id_row  = absint($visit_row['id'] ?? 0);
                $is_executed   = $this->visit_execution_service->is_executed($visit_row);
                $is_reviewed   = $this->visit_execution_service->is_reviewed($visit_row);
                $can_edit_visit = $can_manage_visits && ! $is_archived;
                $can_execute_visit = ! $is_archived
                    && ! $is_executed
                    && $this->visit_execution_service->can_execute_visit($referral, $visit_row);
                $can_review_visit = $is_executed
                    && ! $is_reviewed
                    && $this->visit_execution_service->can_review_visit($referral);

                $visit_row['can_edit']    = $can_edit_visit;
                $visit_row['can_execute'] = $can_execute_visit;
                $visit_row['can_review']  = $can_review_visit;
                $visit_row['edit_url']    = $can_edit_visit ? PortalUrls::visit_edit($referral_id, $visit_id_row) : '';
                $visit_row['execute_url'] = $can_execute_visit ? PortalUrls::visit_execute($referral_id, $visit_id_row) : '';
                $visit_row['review_url']  = $can_review_visit ? PortalUrls::visit_review($referral_id, $visit_id_row) : '';

                $care_visits[] = $visit_row;
            }
        }

        $can_view_medications = $this->medication_service->can_view_medications($referral);
        $can_manage_medications = $this->medication_service->can_manage_medications($referral);
        $medications          = [];
        $medication_status_labels = MedicationService::status_labels();
        $medication_route_labels  = MedicationService::route_labels();
        if ($can_view_medications) {
            foreach ($this->medication_service->get_medications_for_referral($referral_id, false) as $medication_row) {
                $medication_row['edit_url'] = $can_manage_medications
                    ? PortalUrls::medication_edit($referral_id, absint($medication_row['id'] ?? 0))
                    : '';
                $medications[] = $medication_row;
            }
        }

        $can_view_schedules   = $this->schedule_service->can_view_schedules($referral);
        $can_manage_schedules = $this->schedule_service->can_manage_schedules($referral);
        $schedules            = [];
        $schedule_repeat_labels = ScheduleService::repeat_type_labels();
        $schedule_status_labels = ScheduleService::status_labels();
        if ($can_view_schedules) {
            foreach ($this->schedule_service->get_schedules_for_referral($referral_id) as $schedule_row) {
                $schedule_id_row = absint($schedule_row['id'] ?? 0);
                $schedule_row['generated_visit_count'] = $this->schedule_service->count_generated_visits($schedule_id_row);
                $schedule_row['edit_url'] = $can_manage_schedules
                    ? PortalUrls::schedule_edit($referral_id, $schedule_id_row)
                    : '';
                $schedule_row['generate_url'] = $can_manage_schedules
                    && ScheduleService::STATUS_ACTIVE === (string) ($schedule_row['status'] ?? '')
                    ? PortalUrls::schedule_generate($referral_id, $schedule_id_row)
                    : '';
                $schedules[] = $schedule_row;
            }
        }

        $can_view_activity = Capabilities::current_user_can(Capabilities::VIEW_REFERRALS);
        $activities        = [];
        if ($can_view_activity) {
            foreach ($this->activity_repository->get_by_referral_id($referral_id, self::VIEW_ACTIVITY_LIMIT) as $activity_row) {
                $activities[] = $activity_row;
            }
        }

        $submission_channel = (string) ($referral['submission_channel'] ?? 'admin');
        $is_public_referral = SubmissionChannels::is_public($submission_channel);
        $referrer_type      = (string) ($referral['referrer_type'] ?? '');

        $list_title = $this->access_policy->should_scope_to_assigned()
            ? __('My Referrals', 'jm-referral-system')
            : __('Referrals', 'jm-referral-system');

        $referral_number = (string) ($referral['referral_number'] ?? '');
        $page_title      = '' !== $referral_number
            ? $referral_number
            : __('Referral', 'jm-referral-system');

        $can_edit_referral = Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)
            && $this->access_policy->can_edit_referral($referral)
            && $this->access_policy->can_mutate_referral($referral);

        $can_archive_referral = ! $is_archived
            && Capabilities::current_user_can(Capabilities::ARCHIVE_REFERRALS)
            && $this->access_policy->can_edit_referral($referral)
            && $this->access_policy->can_mutate_referral($referral);

        $can_restore_referral = $is_archived
            && Capabilities::current_user_can(Capabilities::RESTORE_REFERRALS)
            && $this->access_policy->can_view_referral($referral);

        $can_edit_assessment = ! $is_archived
            && Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)
            && $this->access_policy->can_edit_referral($referral)
            && $this->access_policy->can_mutate_referral($referral);

        $can_manage_care_plan = ! $is_archived
            && Capabilities::current_user_can(Capabilities::MANAGE_CARE_PLANS)
            && $this->access_policy->can_edit_referral($referral)
            && $this->access_policy->can_mutate_referral($referral);

        $can_filter_archive = ! $this->access_policy->should_scope_to_assigned();

        $updated_notice = isset($_GET['jmrs_updated']) && '1' === (string) wp_unslash($_GET['jmrs_updated']);
        $clinical_notice = $this->portal_clinical_notice();

        $view = [
            'referral'                   => $referral,
            'assigned_to_name'           => $assigned_to_name,
            'service_name'               => $service_name,
            'workflow_stage_name'        => $workflow_stage_name,
            'is_archived'                => $is_archived,
            'archived_at'                => (string) ($referral['archived_at'] ?? ''),
            'archive_reason'             => (string) ($referral['archive_reason'] ?? ''),
            'archived_by_name'           => $archived_by_name,
            'documents'                  => $documents,
            'can_download_documents'     => $can_download_documents,
            'assessment'                 => $assessment,
            'assessment_data'            => $assessment_data,
            'assessor_name'              => $assessor_name,
            'assessment_outcomes'        => ReferralAssessmentService::outcome_labels(),
            'can_edit_assessment'        => $can_edit_assessment,
            'assessment_url'             => $can_edit_assessment
                ? PortalUrls::referral_assessment($referral_id)
                : '',
            'can_view_care_plan'         => $can_view_care_plan,
            'care_plan'                  => $care_plan,
            'care_plan_data'             => $care_plan_data,
            'care_plan_statuses'         => $care_plan_statuses,
            'care_plan_created_by_name'  => $care_plan_created_by_name,
            'care_plan_approved_by_name' => $care_plan_approved_by_name,
            'can_manage_care_plan'       => $can_manage_care_plan,
            'care_plan_url'              => $can_manage_care_plan
                ? PortalUrls::referral_care_plan($referral_id)
                : '',
            'has_assessment'             => null !== $assessment,
            'care_plan_reviews'          => $care_plan_reviews,
            'care_plan_review_outcome_labels' => $care_plan_review_outcome_labels,
            'can_review_care_plan'       => $can_review_care_plan,
            'care_plan_review_url'       => $can_review_care_plan
                ? PortalUrls::care_plan_review($referral_id)
                : '',
            'can_view_care_team'         => $can_view_care_team,
            'can_manage_care_team'       => $can_manage_care_team,
            'care_team_new_url'          => $can_manage_care_team
                ? PortalUrls::care_team_new($referral_id)
                : '',
            'care_team_members'          => $care_team_members,
            'care_team_roles'            => $care_team_roles,
            'care_team_statuses'         => $care_team_statuses,
            'can_view_visits'            => $can_view_visits,
            'can_manage_visits'          => $can_manage_visits,
            'visit_new_url'              => ($can_manage_visits && ! $is_archived)
                ? PortalUrls::visit_new($referral_id)
                : '',
            'care_visits'                => $care_visits,
            'visit_status_labels'        => $visit_status_labels,
            'can_view_schedules'         => $can_view_schedules,
            'can_manage_schedules'       => $can_manage_schedules,
            'schedule_new_url'           => $can_manage_schedules
                ? PortalUrls::schedule_new($referral_id)
                : '',
            'schedules'                  => $schedules,
            'schedule_repeat_labels'     => $schedule_repeat_labels,
            'schedule_status_labels'     => $schedule_status_labels,
            'can_view_medications'       => $can_view_medications,
            'can_manage_medications'     => $can_manage_medications,
            'medication_new_url'         => $can_manage_medications
                ? PortalUrls::medication_new($referral_id)
                : '',
            'medications'                => $medications,
            'medication_status_labels'   => $medication_status_labels,
            'medication_route_labels'    => $medication_route_labels,
            'activities'                 => $activities,
            'submission_channel_label'   => SubmissionChannels::label($submission_channel),
            'is_public_referral'         => $is_public_referral,
            'referrer_type_label'        => '' !== $referrer_type ? ReferrerTypes::label($referrer_type) : '',
            'list_url'                   => PortalUrls::referrals(),
            'archived_list_url'          => $can_filter_archive
                ? PortalUrls::referrals_with_args(['jmrs_archive_scope' => 'archived'])
                : '',
            'can_edit_referral'          => $can_edit_referral,
            'edit_url'                   => $can_edit_referral ? PortalUrls::referral_edit($referral_id) : '',
            'can_archive_referral'       => $can_archive_referral,
            'can_restore_referral'       => $can_restore_referral,
            'updated_notice'             => $updated_notice,
            'retention_notice'           => $this->portal_retention_notice(),
            'clinical_notice'            => $clinical_notice,
        ];

        // Presentation-only grouping of already-gated action URLs for the
        // referral view "quick actions" bar. Reuses the view model above;
        // adds no new permission checks or routes.
        $view['quick_actions'] = $this->build_referral_quick_actions($view);

        $this->render_page(
            'referrals/view',
            $page_title,
            'referral',
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
                ['label' => $list_title, 'url' => PortalUrls::referrals()],
                ['label' => $page_title, 'url' => ''],
            ],
            $view
        );
    }

    private function render_referral_edit(): void
    {
        if (! Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)) {
            $this->render_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $referral_id = absint(get_query_var(PortalRouter::QV_ID));
        $referral    = $referral_id > 0 ? $this->referral_repository->find($referral_id) : null;

        // Generic 404 for missing or inaccessible referrals (no existence leak).
        if (
            null === $referral
            || ! $this->access_policy->can_view_referral($referral)
            || ! $this->access_policy->can_edit_referral($referral)
            || ! $this->access_policy->can_mutate_referral($referral)
        ) {
            $this->render_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (isset($_POST['jmrs_update_referral'])) {
            $this->handle_referral_edit_post($referral_id);

            return;
        }

        $form_state = ReferralEditController::get_form_state($referral_id);
        $errors     = $form_state['errors'];
        $data       = ! empty($form_state['data'])
            ? $form_state['data']
            : $this->edit_controller->map_referral_to_form_data($referral);
        $options    = $this->edit_controller->get_form_options($data);

        $list_title = $this->access_policy->should_scope_to_assigned()
            ? __('My Referrals', 'jm-referral-system')
            : __('Referrals', 'jm-referral-system');

        $referral_number = (string) ($referral['referral_number'] ?? '');
        $view_label      = '' !== $referral_number
            ? $referral_number
            : __('Referral', 'jm-referral-system');
        $page_title = sprintf(
            /* translators: %s: referral number or label */
            __('Edit %s', 'jm-referral-system'),
            $view_label
        );

        $view = [
            'referral'          => $referral,
            'data'              => $data,
            'errors'            => $errors,
            'assignable_users'  => $options['assignable_users'],
            'service_types'     => $options['service_types'],
            'workflow_stages'   => $options['workflow_stages'],
            'form_action'       => PortalUrls::referral_edit($referral_id),
            'cancel_url'        => PortalUrls::referral($referral_id),
            'can_assign'        => Capabilities::current_user_can(Capabilities::ASSIGN_REFERRALS),
            'assigned_to_name'  => absint($data['assigned_to'] ?? 0) > 0
                ? $this->user_provider->get_display_name(absint($data['assigned_to']))
                : '',
        ];

        $this->render_page(
            'referrals/edit',
            $page_title,
            'referral',
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
                ['label' => $list_title, 'url' => PortalUrls::referrals()],
                ['label' => $view_label, 'url' => PortalUrls::referral($referral_id)],
                ['label' => __('Edit', 'jm-referral-system'), 'url' => ''],
            ],
            $view
        );
    }

    private function handle_referral_edit_post(int $referral_id): void
    {
        $nonce = isset($_POST['jmrs_edit_referral_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_edit_referral_nonce']))
            : '';

        if (
            ! wp_verify_nonce($nonce, 'jmrs_edit_referral_' . $referral_id)
            || absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id
        ) {
            $this->render_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->edit_controller->attempt_update($referral_id, $_POST);

        if (! empty($result['not_found']) || ! empty($result['forbidden'])) {
            $this->render_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (! $result['success']) {
            $this->edit_controller->persist_form_state($referral_id, $result['data'], $result['errors']);
            wp_safe_redirect(PortalUrls::referral_edit($referral_id));
            exit;
        }

        wp_safe_redirect(
            add_query_arg('jmrs_updated', '1', PortalUrls::referral($referral_id))
        );
        exit;
    }

    private function render_referral_assessment(): void
    {
        if (! Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)) {
            $this->render_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $referral_id = absint(get_query_var(PortalRouter::QV_ID));
        $referral    = $referral_id > 0 ? $this->referral_repository->find($referral_id) : null;

        if (
            null === $referral
            || ! $this->access_policy->can_view_referral($referral)
            || ! $this->access_policy->can_edit_referral($referral)
            || ! $this->access_policy->can_mutate_referral($referral)
        ) {
            $this->render_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (isset($_POST['jmrs_save_assessment'])) {
            $this->handle_assessment_post($referral_id);

            return;
        }

        $assessment = $this->assessment_repository->find_by_referral($referral_id);
        $form_state = ReferralAssessmentController::get_form_state($referral_id);
        $errors     = $form_state['errors'];
        $data       = ! empty($form_state['data'])
            ? $form_state['data']
            : ReferralAssessmentService::map_to_form_data($assessment);

        $list_title = $this->access_policy->should_scope_to_assigned()
            ? __('My Referrals', 'jm-referral-system')
            : __('Referrals', 'jm-referral-system');
        $referral_number = (string) ($referral['referral_number'] ?? '');
        $view_label      = '' !== $referral_number
            ? $referral_number
            : __('Referral', 'jm-referral-system');
        $page_title = null === $assessment
            ? __('Create Assessment', 'jm-referral-system')
            : __('Edit Assessment', 'jm-referral-system');

        $view = [
            'referral'             => $referral,
            'assessment'           => $assessment,
            'data'                 => $data,
            'errors'               => $errors,
            'outcome_options'      => ReferralAssessmentService::outcome_labels(),
            'form_action'          => PortalUrls::referral_assessment($referral_id),
            'cancel_url'           => PortalUrls::referral($referral_id),
            'is_create'            => null === $assessment,
        ];

        $this->render_page(
            'referrals/assessment',
            $page_title,
            'referral',
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
                ['label' => $list_title, 'url' => PortalUrls::referrals()],
                ['label' => $view_label, 'url' => PortalUrls::referral($referral_id)],
                ['label' => $page_title, 'url' => ''],
            ],
            $view
        );
    }

    private function handle_assessment_post(int $referral_id): void
    {
        $nonce = isset($_POST['jmrs_save_assessment_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_save_assessment_nonce']))
            : '';

        if (
            ! wp_verify_nonce($nonce, 'jmrs_save_assessment_' . $referral_id)
            || absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id
        ) {
            $this->render_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->assessment_controller->attempt_save($referral_id, $_POST);

        if (! empty($result['not_found']) || ! empty($result['forbidden'])) {
            $this->render_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (! $result['success']) {
            $this->assessment_controller->persist_form_state($referral_id, $result['data'], $result['errors']);
            wp_safe_redirect(PortalUrls::referral_assessment($referral_id));
            exit;
        }

        wp_safe_redirect(
            add_query_arg(
                'jmrs_assessment_saved',
                ! empty($result['created']) ? 'created' : 'updated',
                PortalUrls::referral($referral_id)
            )
        );
        exit;
    }

    private function render_referral_care_plan(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_CARE_PLANS)) {
            $this->render_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $referral_id = absint(get_query_var(PortalRouter::QV_ID));
        $referral    = $referral_id > 0 ? $this->referral_repository->find($referral_id) : null;

        if (
            null === $referral
            || ! $this->access_policy->can_view_referral($referral)
            || ! $this->access_policy->can_edit_referral($referral)
            || ! $this->access_policy->can_mutate_referral($referral)
        ) {
            $this->render_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (isset($_POST['jmrs_generate_care_plan'])) {
            $this->handle_care_plan_generate_post($referral_id);

            return;
        }

        if (isset($_POST['jmrs_blank_care_plan'])) {
            $this->handle_care_plan_blank_post($referral_id);

            return;
        }

        if (isset($_POST['jmrs_save_care_plan'])) {
            $this->handle_care_plan_save_post($referral_id);

            return;
        }

        $care_plan  = $this->care_plan_repository->find_by_referral($referral_id);
        $assessment = $this->assessment_repository->find_by_referral($referral_id);
        $form_state = ReferralCarePlanController::get_form_state($referral_id);
        $errors     = $form_state['errors'];
        $drafting   = ! empty($form_state['drafting']);
        $data       = ! empty($form_state['data'])
            ? $form_state['data']
            : ReferralCarePlanService::map_to_form_data($care_plan);

        $show_start = null === $care_plan && ! $drafting && empty($errors);

        $list_title = $this->access_policy->should_scope_to_assigned()
            ? __('My Referrals', 'jm-referral-system')
            : __('Referrals', 'jm-referral-system');
        $referral_number = (string) ($referral['referral_number'] ?? '');
        $view_label      = '' !== $referral_number
            ? $referral_number
            : __('Referral', 'jm-referral-system');
        $page_title = $show_start
            ? __('Create Care Plan', 'jm-referral-system')
            : (null === $care_plan
                ? __('Create Care Plan', 'jm-referral-system')
                : __('Edit Care Plan', 'jm-referral-system'));

        $view = [
            'referral'            => $referral,
            'care_plan'           => $care_plan,
            'assessment'          => $assessment,
            'data'                => $data,
            'errors'              => $errors,
            'status_options'      => ReferralCarePlanService::status_labels(),
            'form_action'         => PortalUrls::referral_care_plan($referral_id),
            'cancel_url'          => PortalUrls::referral($referral_id),
            'show_start'          => $show_start,
            'has_assessment'      => null !== $assessment,
            'is_create'           => null === $care_plan,
        ];

        $this->render_page(
            'referrals/care-plan',
            $page_title,
            'referral',
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
                ['label' => $list_title, 'url' => PortalUrls::referrals()],
                ['label' => $view_label, 'url' => PortalUrls::referral($referral_id)],
                ['label' => $page_title, 'url' => ''],
            ],
            $view
        );
    }

    private function handle_care_plan_generate_post(int $referral_id): void
    {
        $nonce = isset($_POST['jmrs_generate_care_plan_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_generate_care_plan_nonce']))
            : '';

        if (
            ! wp_verify_nonce($nonce, 'jmrs_generate_care_plan_' . $referral_id)
            || absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id
        ) {
            $this->render_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->care_plan_controller->attempt_generate($referral_id);

        if (! empty($result['not_found']) || ! empty($result['forbidden'])) {
            $this->render_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (! $result['success']) {
            $this->care_plan_controller->persist_form_state($referral_id, [], $result['errors'], false);
            wp_safe_redirect(PortalUrls::referral_care_plan($referral_id));
            exit;
        }

        $this->care_plan_controller->persist_form_state($referral_id, $result['data'], [], true);
        wp_safe_redirect(PortalUrls::referral_care_plan($referral_id));
        exit;
    }

    private function handle_care_plan_blank_post(int $referral_id): void
    {
        $nonce = isset($_POST['jmrs_blank_care_plan_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_blank_care_plan_nonce']))
            : '';

        if (
            ! wp_verify_nonce($nonce, 'jmrs_blank_care_plan_' . $referral_id)
            || absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id
        ) {
            $this->render_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->care_plan_controller->attempt_blank($referral_id);

        if (! empty($result['not_found']) || ! empty($result['forbidden'])) {
            $this->render_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (! $result['success']) {
            $this->care_plan_controller->persist_form_state($referral_id, [], $result['errors'], false);
            wp_safe_redirect(PortalUrls::referral_care_plan($referral_id));
            exit;
        }

        $this->care_plan_controller->persist_form_state($referral_id, $result['data'], [], true);
        wp_safe_redirect(PortalUrls::referral_care_plan($referral_id));
        exit;
    }

    private function handle_care_plan_save_post(int $referral_id): void
    {
        $nonce = isset($_POST['jmrs_save_care_plan_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_save_care_plan_nonce']))
            : '';

        if (
            ! wp_verify_nonce($nonce, 'jmrs_save_care_plan_' . $referral_id)
            || absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id
        ) {
            $this->render_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->care_plan_controller->attempt_save($referral_id, $_POST);

        if (! empty($result['not_found']) || ! empty($result['forbidden'])) {
            $this->render_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (! $result['success']) {
            $this->care_plan_controller->persist_form_state($referral_id, $result['data'], $result['errors'], true);
            wp_safe_redirect(PortalUrls::referral_care_plan($referral_id));
            exit;
        }

        wp_safe_redirect(
            add_query_arg(
                'jmrs_care_plan_saved',
                ! empty($result['created']) ? 'created' : 'updated',
                PortalUrls::referral($referral_id)
            )
        );
        exit;
    }

    /**
     * Action URLs/flags for portal list, dashboard, and related tables.
     *
     * @param array<string, mixed> $referral
     * @return array{
     *     portal_url: string,
     *     edit_url: string,
     *     archive_url: string,
     *     can_edit: bool,
     *     can_archive: bool,
     *     can_restore: bool,
     *     is_archived: bool
     * }
     */
    private function build_referral_portal_actions(array $referral): array
    {
        $id          = absint($referral['id'] ?? 0);
        $is_archived = $this->retention_service->is_archived($referral);
        $portal_url  = $id > 0 ? PortalUrls::referral($id) : '';

        $can_edit = $id > 0
            && ! $is_archived
            && Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)
            && $this->access_policy->can_edit_referral($referral)
            && $this->access_policy->can_mutate_referral($referral);

        $can_archive = $id > 0
            && ! $is_archived
            && Capabilities::current_user_can(Capabilities::ARCHIVE_REFERRALS)
            && $this->access_policy->can_edit_referral($referral)
            && $this->access_policy->can_mutate_referral($referral);

        $can_restore = $id > 0
            && $is_archived
            && Capabilities::current_user_can(Capabilities::RESTORE_REFERRALS)
            && $this->access_policy->can_view_referral($referral);

        return [
            'portal_url'  => $portal_url,
            'edit_url'    => $can_edit ? PortalUrls::referral_edit($id) : '',
            'archive_url' => $can_archive && '' !== $portal_url
                ? $portal_url . '#jmrs-archive-referral'
                : '',
            'can_edit'    => $can_edit,
            'can_archive' => $can_archive,
            'can_restore' => $can_restore,
            'is_archived' => $is_archived,
        ];
    }

    /**
     * Presentation-only quick-action links for the referral view header,
     * built purely from URLs/flags already computed for the referral view
     * model. Adds no new permission logic, routes, or business data.
     *
     * @param array<string, mixed> $view
     * @return array<int, array{label: string, url: string, class: string}>
     */
    private function build_referral_quick_actions(array $view): array
    {
        $actions = [];

        $candidates = [
            ['can_edit_referral', 'edit_url', __('Edit Referral', 'jm-referral-system'), 'jmrs-button jmrs-button--primary'],
            [
                'can_edit_assessment',
                'assessment_url',
                null === ($view['assessment'] ?? null) ? __('Create Assessment', 'jm-referral-system') : __('Edit Assessment', 'jm-referral-system'),
                'jmrs-button jmrs-button--secondary',
            ],
            [
                'can_manage_care_plan',
                'care_plan_url',
                null === ($view['care_plan'] ?? null) ? __('Create Care Plan', 'jm-referral-system') : __('Edit Care Plan', 'jm-referral-system'),
                'jmrs-button jmrs-button--secondary',
            ],
            ['can_manage_visits', 'visit_new_url', __('Schedule Visit', 'jm-referral-system'), 'jmrs-button jmrs-button--secondary'],
            ['can_manage_medications', 'medication_new_url', __('Add Medication', 'jm-referral-system'), 'jmrs-button jmrs-button--secondary'],
            ['can_manage_care_team', 'care_team_new_url', __('Add Team Member', 'jm-referral-system'), 'jmrs-button jmrs-button--secondary'],
        ];

        foreach ($candidates as [$flag_key, $url_key, $label, $class]) {
            $url = (string) ($view[$url_key] ?? '');
            if (! empty($view[$flag_key]) && '' !== $url) {
                $actions[] = ['label' => $label, 'url' => $url, 'class' => $class];
            }
        }

        return $actions;
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function portal_retention_notice(): ?array
    {
        if (isset($_GET['jmrs_archived']) && '1' === (string) wp_unslash($_GET['jmrs_archived'])) {
            return [
                'type'    => 'success',
                'message' => __('Referral archived successfully.', 'jm-referral-system'),
            ];
        }

        if (isset($_GET['jmrs_archive_error']) && '1' === (string) wp_unslash($_GET['jmrs_archive_error'])) {
            return [
                'type'    => 'error',
                'message' => __('Unable to archive the referral. An archive reason is required.', 'jm-referral-system'),
            ];
        }

        if (isset($_GET['jmrs_restored']) && '1' === (string) wp_unslash($_GET['jmrs_restored'])) {
            return [
                'type'    => 'success',
                'message' => __('Referral restored successfully.', 'jm-referral-system'),
            ];
        }

        if (isset($_GET['jmrs_restore_error']) && '1' === (string) wp_unslash($_GET['jmrs_restore_error'])) {
            return [
                'type'    => 'error',
                'message' => __('Unable to restore the referral.', 'jm-referral-system'),
            ];
        }

        return null;
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function portal_clinical_notice(): ?array
    {
        if (isset($_GET['jmrs_assessment_saved'])) {
            $status = sanitize_key((string) wp_unslash($_GET['jmrs_assessment_saved']));
            if ('created' === $status) {
                return [
                    'type'    => 'success',
                    'message' => __('Assessment created successfully.', 'jm-referral-system'),
                ];
            }
            if ('updated' === $status) {
                return [
                    'type'    => 'success',
                    'message' => __('Assessment updated successfully.', 'jm-referral-system'),
                ];
            }
        }

        if (isset($_GET['jmrs_care_plan_saved'])) {
            $status = sanitize_key((string) wp_unslash($_GET['jmrs_care_plan_saved']));
            if ('created' === $status) {
                return [
                    'type'    => 'success',
                    'message' => __('Care plan created successfully.', 'jm-referral-system'),
                ];
            }
            if ('updated' === $status) {
                return [
                    'type'    => 'success',
                    'message' => __('Care plan updated successfully.', 'jm-referral-system'),
                ];
            }
        }

        if (isset($_GET['jmrs_care_plan_reviewed']) && '1' === (string) wp_unslash($_GET['jmrs_care_plan_reviewed'])) {
            return [
                'type'    => 'success',
                'message' => __('Care plan review recorded successfully.', 'jm-referral-system'),
            ];
        }

        if (isset($_GET['jmrs_medication_saved']) && '1' === (string) wp_unslash($_GET['jmrs_medication_saved'])) {
            return [
                'type'    => 'success',
                'message' => __('Medication saved successfully.', 'jm-referral-system'),
            ];
        }

        if (isset($_GET['jmrs_care_team_saved']) && '1' === (string) wp_unslash($_GET['jmrs_care_team_saved'])) {
            return [
                'type'    => 'success',
                'message' => __('Care team assignment saved successfully.', 'jm-referral-system'),
            ];
        }

        if (isset($_GET['jmrs_schedule_saved']) && '1' === (string) wp_unslash($_GET['jmrs_schedule_saved'])) {
            return [
                'type'    => 'success',
                'message' => __('Schedule saved successfully.', 'jm-referral-system'),
            ];
        }

        if (isset($_GET['jmrs_schedule_visits_created'])) {
            $created = absint($_GET['jmrs_schedule_visits_created']);
            $skipped = isset($_GET['jmrs_schedule_visits_skipped']) ? absint($_GET['jmrs_schedule_visits_skipped']) : 0;
            $outside = isset($_GET['jmrs_schedule_visits_outside']) ? absint($_GET['jmrs_schedule_visits_outside']) : 0;

            $parts   = [];
            $parts[] = sprintf(
                /* translators: %d: number of visits created */
                _n('%d visit generated.', '%d visits generated.', $created, 'jm-referral-system'),
                $created
            );

            if ($skipped > 0) {
                $parts[] = sprintf(
                    /* translators: %d: number of duplicate visits skipped */
                    _n('%d existing visit skipped.', '%d existing visits skipped.', $skipped, 'jm-referral-system'),
                    $skipped
                );
            }

            if ($outside > 0) {
                $parts[] = sprintf(
                    /* translators: %d: number of occurrences outside schedule range */
                    _n('%d occurrence outside the schedule range skipped.', '%d occurrences outside the schedule range skipped.', $outside, 'jm-referral-system'),
                    $outside
                );
            }

            return [
                'type'    => 'success',
                'message' => implode(' ', $parts),
            ];
        }

        if (isset($_GET['jmrs_visit_saved']) && '1' === (string) wp_unslash($_GET['jmrs_visit_saved'])) {
            return [
                'type'    => 'success',
                'message' => __('Care visit saved successfully.', 'jm-referral-system'),
            ];
        }

        if (isset($_GET['jmrs_visit_executed']) && '1' === (string) wp_unslash($_GET['jmrs_visit_executed'])) {
            if (isset($_GET['jmrs_medication_warning']) && '1' === (string) wp_unslash($_GET['jmrs_medication_warning'])) {
                return [
                    'type'    => 'warning',
                    'message' => __(
                        'Visit completed successfully, but active medications exist for this client and no medication administrations were recorded for this visit.',
                        'jm-referral-system'
                    ),
                ];
            }

            return [
                'type'    => 'success',
                'message' => __('Visit completed successfully.', 'jm-referral-system'),
            ];
        }

        if (isset($_GET['jmrs_visit_reviewed']) && '1' === (string) wp_unslash($_GET['jmrs_visit_reviewed'])) {
            return [
                'type'    => 'success',
                'message' => __('Visit reviewed successfully.', 'jm-referral-system'),
            ];
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function care_visit_service_get_recent(int $referral_id): array
    {
        return $this->visit_service->get_visits_for_referral(
            $referral_id,
            self::VIEW_VISITS_LIMIT,
            0
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, scalar>
     */
    private function portal_list_query_args(array $filters, int $per_page): array
    {
        $args = [];

        if (! empty($filters['search'])) {
            $args['jmrs_search'] = (string) $filters['search'];
        }
        if (! empty($filters['status'])) {
            $args['jmrs_status'] = (string) $filters['status'];
        }
        if (! empty($filters['priority'])) {
            $args['jmrs_priority'] = (string) $filters['priority'];
        }
        if (! empty($filters['assigned_to'])) {
            $args['jmrs_assigned_to'] = absint($filters['assigned_to']);
        }
        $archive_scope = (string) ($filters['archive_scope'] ?? 'active');
        if ('' !== $archive_scope && 'active' !== $archive_scope) {
            $args['jmrs_archive_scope'] = $archive_scope;
        }
        if (in_array($per_page, ReferralFilters::ALLOWED_PER_PAGE, true)
            && ReferralFilters::DEFAULT_PER_PAGE !== $per_page
        ) {
            $args['jmrs_per_page'] = $per_page;
        }

        return $args;
    }

    /**
     * @param array<int, array<string, mixed>> $visits
     * @param array<string, string>            $outcome_labels
     * @return array<int, array<string, mixed>>
     */
    private function enrich_portal_visits(array $visits, array $outcome_labels, bool $include_outcome): array
    {
        $user_ids = [];
        foreach ($visits as $visit_row) {
            $assigned_id = absint($visit_row['assigned_user_id'] ?? 0);
            if ($assigned_id > 0) {
                $user_ids[] = $assigned_id;
            }
        }

        $names    = $this->user_provider->get_display_names_by_ids($user_ids);
        $enriched = [];
        /** @var array<int, array<string, mixed>|null> $referral_cache */
        $referral_cache = [];

        foreach ($visits as $visit_row) {
            $assigned_id = absint($visit_row['assigned_user_id'] ?? 0);
            $visit_row['assigned_staff_name'] = $assigned_id > 0
                ? (string) ($names[$assigned_id] ?? '')
                : '';

            $referral_id = absint($visit_row['referral_id'] ?? 0);
            $visit_id    = absint($visit_row['id'] ?? 0);
            $visit_row['client_name'] = (string) ($visit_row['client_name'] ?? '');
            $visit_row['referral_url'] = $referral_id > 0
                ? PortalUrls::referral($referral_id)
                : '';

            if ($include_outcome) {
                $outcome_key = (string) ($visit_row['visit_outcome'] ?? '');
                $visit_row['outcome_label'] = isset($outcome_labels[$outcome_key])
                    ? $outcome_labels[$outcome_key]
                    : $outcome_key;
            }

            $can_execute = false;
            $can_review  = false;
            if ($referral_id > 0) {
                if (! array_key_exists($referral_id, $referral_cache)) {
                    $referral_cache[$referral_id] = $this->referral_repository->find($referral_id);
                }
                $referral = $referral_cache[$referral_id];
                if (null !== $referral) {
                    $is_executed = $this->visit_execution_service->is_executed($visit_row);
                    $is_reviewed = $this->visit_execution_service->is_reviewed($visit_row);
                    $can_execute = ! $is_executed
                        && $this->visit_execution_service->can_execute_visit($referral, $visit_row);
                    $can_review = $is_executed
                        && ! $is_reviewed
                        && $this->visit_execution_service->can_review_visit($referral);
                }
            }
            $visit_row['can_execute'] = $can_execute;
            $visit_row['can_review']  = $can_review;
            $visit_row['execute_url'] = $can_execute ? PortalUrls::visit_execute($referral_id, $visit_id) : '';
            $visit_row['review_url']  = $can_review ? PortalUrls::visit_review($referral_id, $visit_id) : '';

            $enriched[] = $visit_row;
        }

        return $enriched;
    }

    /**
     * @param array<int, array{label: string, url: string}> $breadcrumbs
     * @param array<string, mixed>                          $view
     */
    private function render_page(
        string $template,
        string $page_title,
        string $current_route,
        array $breadcrumbs,
        array $view
    ): void {
        $branding     = PortalSettings::branding();
        $nav_items    = $this->navigation->items($current_route);
        $nav_section_labels = $this->navigation->section_labels();
        $user         = wp_get_current_user();
        $display_name = ($user instanceof \WP_User) ? $user->display_name : '';
        $role_label   = $this->navigation->role_label();
        $logout_url   = wp_logout_url(home_url('/'));
        $show_alerts_indicator = Capabilities::current_user_can(Capabilities::VIEW_OPERATIONAL_ALERTS);
        $alert_indicator_count = absint($view['alert_count'] ?? 0);

        $content_template = JMRS_PLUGIN_PATH . 'templates/portal/' . $template . '.php';
        if (! is_readable($content_template)) {
            $this->render_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        PortalAssets::enqueue();

        status_header(200);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- layout template escapes.
        include JMRS_PLUGIN_PATH . 'templates/portal/layout.php';
    }

    private function render_error(string $which, string $page_title, int $status): void
    {
        status_header($status);

        $branding   = PortalSettings::branding();
        $nav_items  = PortalAccess::current_user_can_access_portal()
            ? $this->navigation->items('')
            : [];
        $nav_section_labels = $this->navigation->section_labels();
        $user         = wp_get_current_user();
        $display_name = ($user instanceof \WP_User && $user->ID > 0) ? $user->display_name : '';
        $role_label   = $user instanceof \WP_User && $user->ID > 0 ? $this->navigation->role_label() : '';
        $logout_url   = is_user_logged_in() ? wp_logout_url(home_url('/')) : '';
        $show_alerts_indicator = false;
        $alert_indicator_count = 0;
        $breadcrumbs = [
            ['label' => $page_title, 'url' => ''],
        ];
        $current_route = 'error';
        $view = [
            'dashboard_url' => PortalUrls::dashboard(),
            'logout_url'    => $logout_url,
        ];

        $content_template = JMRS_PLUGIN_PATH . 'templates/portal/errors/' . $which . '.php';
        if (! is_readable($content_template)) {
            wp_die(esc_html($page_title), '', ['response' => $status]);
        }

        if (PortalSettings::is_enabled() && is_user_logged_in()) {
            PortalAssets::enqueue();
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        include JMRS_PLUGIN_PATH . 'templates/portal/layout.php';
    }
}
