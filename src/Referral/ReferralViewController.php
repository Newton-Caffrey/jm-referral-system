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
use JMReferral\Scheduling\ScheduleController;
use JMReferral\Scheduling\ScheduleGenerationService;
use JMReferral\Scheduling\ScheduleService;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;
use JMReferral\Visits\CareVisitController;
use JMReferral\Visits\CareVisitService;
use JMReferral\Workflow\WorkflowStageService;

class ReferralViewController
{
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
        private ScheduleService $schedule_service
    ) {
    }

    /**
     * Registers view-related hooks.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_stage_change']);
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

        $referral_id = isset($_GET['referral_id']) ? absint($_GET['referral_id']) : 0;
        $referral    = $this->repository->find($referral_id);

        if (null === $referral) {
            wp_die(esc_html__('Referral not found.', 'jm-referral-system'));
        }

        if (! $this->access_policy->can_view_referral($referral)) {
            wp_die(esc_html__('You do not have permission to view this referral.', 'jm-referral-system'));
        }

        $activities       = $this->activity_repository->get_by_referral_id($referral_id);
        $assigned_to      = absint($referral['assigned_to'] ?? 0);
        $assigned_to_name = $assigned_to > 0
            ? $this->user_provider->get_display_name($assigned_to)
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

        $workflow_stages = $this->workflow_stage_service->get_options_for_referral($workflow_stage_id);

        $notes = [];
        foreach ($this->note_repository->get_by_referral_id($referral_id) as $note_row) {
            $author_id               = absint($note_row['user_id'] ?? 0);
            $note_row['author_name'] = $author_id > 0
                ? $this->user_provider->get_display_name($author_id)
                : '';
            $notes[] = $note_row;
        }

        $note_form_state = ReferralNoteController::get_form_state($referral_id);
        $note_value      = $note_form_state['note'];
        $note_errors     = $note_form_state['errors'];

        $can_upload_documents   = Capabilities::current_user_can(Capabilities::UPLOAD_DOCUMENTS);
        $can_download_documents = Capabilities::current_user_can(Capabilities::DOWNLOAD_DOCUMENTS);
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

        $can_edit_assessment = Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)
            && $this->access_policy->can_edit_referral($referral);

        $assessment           = $this->assessment_repository->find_by_referral($referral_id);
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
            && $this->access_policy->can_edit_referral($referral);
        $can_review_care_plan = Capabilities::current_user_can(Capabilities::REVIEW_CARE_PLANS)
            && $this->access_policy->can_edit_referral($referral);

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
        if ($can_view_care_plan && null !== $care_plan) {
            foreach ($this->care_plan_review_service->get_reviews_for_referral($referral_id) as $review_row) {
                $reviewed_by_id = absint($review_row['reviewed_by'] ?? 0);
                $review_row['reviewed_by_name'] = $reviewed_by_id > 0
                    ? $this->user_provider->get_display_name($reviewed_by_id)
                    : '';
                $care_plan_reviews[] = $review_row;
            }

            foreach ($this->care_plan_review_service->get_versions_for_referral($referral_id) as $version_row) {
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
            && $this->access_policy->can_edit_referral($referral);

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
            && $this->access_policy->can_edit_referral($referral);

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

        $care_visits = [];
        $schedule_name_by_id = [];

        $can_view_schedules = Capabilities::current_user_can(Capabilities::VIEW_SCHEDULES)
            && $this->access_policy->can_view_referral($referral);
        $can_manage_schedules = Capabilities::current_user_can(Capabilities::MANAGE_SCHEDULES)
            && $this->access_policy->can_edit_referral($referral);

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
            foreach ($this->care_visit_service->get_visits_for_referral($referral_id) as $visit_row) {
                $assigned_id = absint($visit_row['assigned_user_id'] ?? 0);
                $visit_row['assigned_staff_name'] = $assigned_id > 0
                    ? $this->user_provider->get_display_name($assigned_id)
                    : '';
                $visit_row['edit_url'] = CareVisitController::get_edit_url(absint($visit_row['id'] ?? 0));

                $schedule_id = absint($visit_row['schedule_id'] ?? 0);
                if ($schedule_id > 0) {
                    $name = $schedule_name_by_id[$schedule_id] ?? '';
                    if ('' === $name) {
                        $linked = $this->schedule_service->get_schedule($schedule_id);
                        $name   = is_array($linked) ? (string) ($linked['schedule_name'] ?? '') : '';
                    }
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

                $care_visits[] = $visit_row;
            }
        }

        include JMRS_PLUGIN_PATH . 'templates/referrals/view.php';
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

        if (null === $referral || ! $this->access_policy->can_edit_referral($referral)) {
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
     * Renders stage-update notices on the view screen.
     */
    public function render_notices(): void
    {
        if (! $this->is_view_screen()) {
            return;
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
