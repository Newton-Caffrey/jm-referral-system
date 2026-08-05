<?php

namespace JMReferral\Portal;

use JMReferral\Alerts\OperationalAlertService;
use JMReferral\Assessment\ReferralAssessmentRepository;
use JMReferral\Assessment\ReferralAssessmentService;
use JMReferral\CarePlan\ReferralCarePlanRepository;
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
use JMReferral\Referral\ReferralActivityRepository;
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
class PortalController
{
    private const VIEW_VISITS_LIMIT = 10;
    private const VIEW_ACTIVITY_LIMIT = 25;

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
        private ReferralActivityRepository $activity_repository
    ) {
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

        $route = sanitize_key((string) get_query_var(PortalRouter::QV_ROUTE));
        if ('' === $route) {
            $route = 'dashboard';
        }

        match ($route) {
            'dashboard' => $this->render_dashboard(),
            'referrals' => $this->render_referral_list(),
            'referral'  => $this->render_referral_view(),
            default     => $this->render_error('404', __('Not Found', 'jm-referral-system'), 404),
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
            $id = absint($row['id'] ?? 0);
            $recent[$index]['portal_url'] = $id > 0 ? PortalUrls::referral($id) : '';
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

        $view = [
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
        $can_filter_archive = ! $scope_to_assigned
            && (
                Capabilities::current_user_can(Capabilities::ARCHIVE_REFERRALS)
                || Capabilities::current_user_can(Capabilities::RESTORE_REFERRALS)
                || current_user_can('manage_options')
            );

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
            $id = absint($referral['id'] ?? 0);
            $referral['portal_url'] = $id > 0 ? PortalUrls::referral($id) : '';
            $referrals[] = $referral;
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

        $can_view_care_team = Capabilities::current_user_can(Capabilities::VIEW_CARE_TEAM);
        $care_team_roles    = CareTeamService::role_labels();
        $care_team_statuses = CareTeamService::status_labels();
        $care_team_members  = [];
        if ($can_view_care_team) {
            foreach ($this->care_team_service->get_members_for_referral($referral_id) as $member_row) {
                $member_user_id = absint($member_row['user_id'] ?? 0);
                $member_row['staff_name'] = $member_user_id > 0
                    ? $this->user_provider->get_display_name($member_user_id)
                    : '';
                $care_team_members[] = $member_row;
            }
        }

        $can_view_visits = Capabilities::current_user_can(Capabilities::VIEW_VISITS);
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
                $care_visits[] = $visit_row;
            }
        }

        $can_view_medications = $this->medication_service->can_view_medications($referral);
        $medications          = [];
        $medication_status_labels = MedicationService::status_labels();
        $medication_route_labels  = MedicationService::route_labels();
        if ($can_view_medications) {
            $medications = $this->medication_service->get_medications_for_referral($referral_id, false);
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
            'can_view_care_plan'         => $can_view_care_plan,
            'care_plan'                  => $care_plan,
            'care_plan_data'             => $care_plan_data,
            'care_plan_statuses'         => $care_plan_statuses,
            'care_plan_created_by_name'  => $care_plan_created_by_name,
            'care_plan_approved_by_name' => $care_plan_approved_by_name,
            'can_view_care_team'         => $can_view_care_team,
            'care_team_members'          => $care_team_members,
            'care_team_roles'            => $care_team_roles,
            'care_team_statuses'         => $care_team_statuses,
            'can_view_visits'            => $can_view_visits,
            'care_visits'                => $care_visits,
            'visit_status_labels'        => $visit_status_labels,
            'can_view_medications'       => $can_view_medications,
            'medications'                => $medications,
            'medication_status_labels'   => $medication_status_labels,
            'medication_route_labels'    => $medication_route_labels,
            'activities'                 => $activities,
            'submission_channel_label'   => SubmissionChannels::label($submission_channel),
            'is_public_referral'         => $is_public_referral,
            'referrer_type_label'        => '' !== $referrer_type ? ReferrerTypes::label($referrer_type) : '',
            'list_url'                   => PortalUrls::referrals(),
        ];

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

        foreach ($visits as $visit_row) {
            $assigned_id = absint($visit_row['assigned_user_id'] ?? 0);
            $visit_row['assigned_staff_name'] = $assigned_id > 0
                ? (string) ($names[$assigned_id] ?? '')
                : '';

            $referral_id = absint($visit_row['referral_id'] ?? 0);
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
