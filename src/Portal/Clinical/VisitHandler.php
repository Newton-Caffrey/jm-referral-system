<?php

namespace JMReferral\Portal\Clinical;

use JMReferral\Medication\MedicationAdministrationService;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\PortalUrls;
use JMReferral\Scheduling\ScheduleRepository;
use JMReferral\Users\UserProvider;
use JMReferral\Visits\CareVisitController;
use JMReferral\Visits\CareVisitRepository;
use JMReferral\Visits\CareVisitService;
use JMReferral\Visits\ServiceLocationPresenter;
use JMReferral\Visits\ServiceLocationResolver;
use JMReferral\Visits\VisitExecutionService;
use JMReferral\Visits\VisitTaskService;

/**
 * Portal handler for care visit create/edit/execute/review.
 */
class VisitHandler
{
    public function __construct(
        private PortalViewHost $view_host,
        private ClinicalAccess $clinical_access,
        private CareVisitController $visit_controller,
        private CareVisitService $visit_service,
        private VisitExecutionService $execution_service,
        private VisitTaskService $visit_task_service,
        private MedicationAdministrationService $medication_administration_service,
        private CareVisitRepository $visit_repository,
        private ScheduleRepository $schedule_repository,
        private UserProvider $user_provider,
        private ServiceLocationResolver $service_location_resolver
    ) {
    }

    /**
     * @param array<string, mixed> $referral
     * @param array<string, mixed>|null $visit
     * @return array<string, mixed>
     */
    private function build_service_location_panel(array $referral, ?array $visit, string $context): array
    {
        $referral_id = absint($referral['id'] ?? 0);

        if (null !== $visit && ServiceLocationPresenter::is_terminal_without_snapshot($visit)) {
            $current = $this->service_location_resolver->resolve_for_referral($referral_id);

            return ServiceLocationPresenter::panel_vars(
                $current,
                [
                    'heading'             => ServiceLocationPresenter::heading('cancelled', $current),
                    'unavailable_message' => __('Recorded service location unavailable', 'jm-referral-system'),
                    'show_warning'        => false,
                    'secondary_heading'   => __('Current Client Service Location', 'jm-referral-system'),
                    'secondary_location'  => $current,
                ]
            );
        }

        if (null !== $visit) {
            $location = $this->service_location_resolver->resolve_for_visit($visit);
        } else {
            $location = $this->service_location_resolver->resolve_for_referral($referral_id);
        }

        $show_recorded = $location->is_historical() && null !== $location->recorded_at();

        return ServiceLocationPresenter::panel_vars(
            $location,
            [
                'heading'          => ServiceLocationPresenter::heading($context, $location),
                'show_warning'     => ! $location->is_historical(),
                'show_recorded_at' => $show_recorded,
            ]
        );
    }

    public function handle_new(int $referral_id): void
    {
        $this->handle_form($referral_id, 0);
    }

    public function handle_edit(int $referral_id, int $visit_id): void
    {
        $this->handle_form($referral_id, $visit_id);
    }

    private function handle_form(int $referral_id, int $visit_id): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_VISITS)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $access = $this->clinical_access->require_referral($referral_id, true, true);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return;
        }
        $referral = $access['referral'];

        if (! $this->clinical_access->can_manage_visits($referral)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $visit = null;
        if ($visit_id > 0) {
            $prepared = $this->visit_service->prepare_edit($visit_id);
            if (isset($prepared['errors'])) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }

            $visit = $prepared['visit'];
            if (absint($visit['referral_id'] ?? 0) !== $referral_id) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }
        }

        if (isset($_POST['jmrs_save_care_visit'])) {
            $this->handle_save_post($referral_id, $visit_id);

            return;
        }

        $form_state = CareVisitController::get_form_state($referral_id, true, 'portal');
        $errors     = $form_state['errors'];
        $data       = (! empty($form_state['data']) && absint($form_state['visit_id'] ?? 0) === $visit_id)
            ? array_merge(CareVisitService::empty_form_data(), $form_state['data'])
            : CareVisitService::map_to_form_data($visit);

        $schedule_source_label = '';
        $schedule_id = absint($visit['schedule_id'] ?? 0);
        if ($schedule_id > 0) {
            $schedule = $this->schedule_repository->find($schedule_id);
            $schedule_name = is_array($schedule) ? (string) ($schedule['schedule_name'] ?? '') : '';
            $schedule_source_label = '' !== $schedule_name
                ? sprintf(
                    /* translators: %s: schedule name */
                    __('Schedule: %s', 'jm-referral-system'),
                    $schedule_name
                )
                : __('Schedule', 'jm-referral-system');
        }

        $page_title = null === $visit
            ? __('Schedule Visit', 'jm-referral-system')
            : __('Edit Visit', 'jm-referral-system');

        $form_action = $visit_id > 0
            ? PortalUrls::visit_edit($referral_id, $visit_id)
            : PortalUrls::visit_new($referral_id);

        $view = [
            'referral'               => $referral,
            'visit'                  => $visit,
            'visit_id'               => $visit_id,
            'data'                   => $data,
            'errors'                 => $errors,
            'assignable_users'       => $this->visit_service->get_assignable_staff_for_referral($referral_id),
            'status_labels'          => CareVisitService::status_labels(),
            'schedule_source_label'  => $schedule_source_label,
            'form_action'            => $form_action,
            'cancel_url'             => PortalUrls::referral($referral_id),
            'is_create'              => null === $visit,
            'service_location_panel' => $this->build_service_location_panel(
                $referral,
                is_array($visit) ? $visit : null,
                null !== $visit && '' !== trim((string) ($visit['visit_outcome'] ?? ''))
                    ? 'visit_historical'
                    : 'visit_current'
            ),
        ];

        $this->view_host->render_portal_page(
            'referrals/visit-form',
            $page_title,
            'referral',
            $this->clinical_access->referral_breadcrumbs($referral, $page_title),
            $view
        );
    }

    private function handle_save_post(int $referral_id, int $visit_id): void
    {
        $nonce = isset($_POST['jmrs_care_visit_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_care_visit_nonce']))
            : '';

        if (
            ! wp_verify_nonce($nonce, 'jmrs_save_care_visit_' . $referral_id)
            || absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id
        ) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $posted_visit_id = isset($_POST['jmrs_visit_id']) ? absint($_POST['jmrs_visit_id']) : 0;
        if ($posted_visit_id !== $visit_id) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->visit_controller->attempt_save($referral_id, $_POST, $visit_id);

        if (! empty($result['not_found']) || ! empty($result['forbidden'])) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $redirect_url = $visit_id > 0
            ? PortalUrls::visit_edit($referral_id, $visit_id)
            : PortalUrls::visit_new($referral_id);

        if (! $result['success']) {
            $this->visit_controller->persist_form_state(
                $referral_id,
                $result['data'],
                $result['errors'],
                $visit_id,
                'portal'
            );
            wp_safe_redirect($redirect_url);
            exit;
        }

        wp_safe_redirect(
            add_query_arg('jmrs_visit_saved', '1', PortalUrls::referral($referral_id))
        );
        exit;
    }

    public function handle_execute(int $referral_id, int $visit_id): void
    {
        if (! Capabilities::current_user_can(Capabilities::EXECUTE_VISITS)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $access = $this->clinical_access->require_referral($referral_id);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return;
        }
        $referral = $access['referral'];

        // VisitExecutionService::can_execute_visit() does not check archived status;
        // enforce it explicitly here without forking the underlying business rule.
        if ($this->clinical_access->is_archived($referral)) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $visit = $this->visit_repository->find($visit_id);
        if (null === $visit || absint($visit['referral_id'] ?? 0) !== $referral_id) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (! $this->execution_service->can_execute_visit($referral, $visit)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        if ($this->execution_service->is_executed($visit)) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (isset($_POST['jmrs_execute_care_visit'])) {
            $this->handle_execute_post($referral_id, $visit_id);

            return;
        }

        $form_state = CareVisitController::get_execution_form_state($referral_id, true, 'portal');
        $errors     = $form_state['errors'];
        $data       = (! empty($form_state['data']) && absint($form_state['visit_id'] ?? 0) === $visit_id)
            ? array_merge(VisitExecutionService::empty_execution_form_data(), $form_state['data'])
            : VisitExecutionService::empty_execution_form_data();

        $posted_tasks = is_array($data['tasks'] ?? null) ? $data['tasks'] : [];
        $posted_meds  = is_array($data['medications'] ?? null) ? $data['medications'] : [];

        $visit_tasks = $this->visit_task_service->get_tasks_for_visit($visit_id);
        foreach ($visit_tasks as $idx => $task_row) {
            $tid = absint($task_row['id'] ?? 0);
            if ($tid > 0 && isset($posted_tasks[$tid]) && is_array($posted_tasks[$tid])) {
                $visit_tasks[$idx]['task_status'] = (string) ($posted_tasks[$tid]['task_status'] ?? $task_row['task_status']);
                $visit_tasks[$idx]['task_notes']  = (string) ($posted_tasks[$tid]['task_notes'] ?? '');
            }
        }

        $active_medications = [];
        $can_show_mar       = $this->medication_administration_service->can_show_administration_for_visit($referral, $visit);
        if ($can_show_mar) {
            $active_medications = $this->medication_administration_service->get_active_medications_valid_on_visit($visit);
        }

        $admin_by_med = [];
        foreach ($this->medication_administration_service->get_for_visit($visit_id) as $admin_row) {
            $admin_by_med[absint($admin_row['medication_id'] ?? 0)] = $admin_row;
        }

        $page_title = __('Complete Visit', 'jm-referral-system');

        $view = [
            'referral'                     => $referral,
            'visit'                        => $visit,
            'data'                         => $data,
            'errors'                       => $errors,
            'outcome_labels'               => VisitExecutionService::outcome_labels(),
            'visit_tasks'                  => $visit_tasks,
            'task_status_labels'           => VisitTaskService::status_labels(),
            'can_show_mar'                 => $can_show_mar,
            'active_medications'           => $active_medications,
            'posted_medications'           => $posted_meds,
            'medication_admin_by_id'       => $admin_by_med,
            'medication_status_labels'     => MedicationAdministrationService::status_labels(),
            'medication_reason_labels'     => MedicationAdministrationService::reason_labels(),
            'witness_users'                => $this->user_provider->get_assignable_users(),
            'form_action'                  => PortalUrls::visit_execute($referral_id, $visit_id),
            'cancel_url'                   => PortalUrls::referral($referral_id),
            'service_location_panel'       => $this->build_service_location_panel($referral, $visit, 'execute'),
        ];

        $this->view_host->render_portal_page(
            'referrals/visit-execute',
            $page_title,
            'referral',
            $this->clinical_access->referral_breadcrumbs($referral, $page_title),
            $view
        );
    }

    private function handle_execute_post(int $referral_id, int $visit_id): void
    {
        $nonce = isset($_POST['jmrs_execute_visit_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_execute_visit_nonce']))
            : '';

        if (
            ! wp_verify_nonce($nonce, 'jmrs_execute_care_visit_' . $visit_id)
            || absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id
            || absint($_POST['jmrs_visit_id'] ?? 0) !== $visit_id
        ) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->visit_controller->attempt_execute($referral_id, $visit_id, $_POST);

        if (! empty($result['not_found']) || ! empty($result['forbidden'])) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (! $result['success']) {
            $this->visit_controller->persist_execution_form_state(
                $referral_id,
                $result['data'],
                $result['errors'],
                $visit_id,
                'portal'
            );
            wp_safe_redirect(PortalUrls::visit_execute($referral_id, $visit_id));
            exit;
        }

        $args = ['jmrs_visit_executed' => '1'];
        if (! empty($result['medication_warning'])) {
            $args['jmrs_medication_warning'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, PortalUrls::referral($referral_id)));
        exit;
    }

    public function handle_review(int $referral_id, int $visit_id): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_VISITS)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $access = $this->clinical_access->require_referral($referral_id, true);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return;
        }
        $referral = $access['referral'];

        $visit = $this->visit_repository->find($visit_id);
        if (null === $visit || absint($visit['referral_id'] ?? 0) !== $referral_id) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (! $this->execution_service->can_review_visit($referral)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        if (! $this->execution_service->is_executed($visit) || $this->execution_service->is_reviewed($visit)) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (isset($_POST['jmrs_review_care_visit'])) {
            $this->handle_review_post($referral_id, $visit_id);

            return;
        }

        $form_state = CareVisitController::get_execution_form_state($referral_id, true, 'portal');
        $errors     = $form_state['errors'];
        $data       = (! empty($form_state['data']) && absint($form_state['visit_id'] ?? 0) === $visit_id)
            ? array_merge(VisitExecutionService::empty_execution_form_data(), $form_state['data'])
            : VisitExecutionService::empty_execution_form_data();

        $task_summaries = $this->visit_task_service->get_summaries($visit_id);
        $outcome_labels = VisitExecutionService::outcome_labels();
        $outcome_key    = (string) ($visit['visit_outcome'] ?? '');

        $page_title = __('Review Visit', 'jm-referral-system');

        $view = [
            'referral'       => $referral,
            'visit'          => $visit,
            'data'           => $data,
            'errors'         => $errors,
            'outcome_label'  => $outcome_labels[$outcome_key] ?? $outcome_key,
            'task_summaries' => $task_summaries,
            'form_action'    => PortalUrls::visit_review($referral_id, $visit_id),
            'cancel_url'     => PortalUrls::referral($referral_id),
            'service_location_panel' => $this->build_service_location_panel($referral, $visit, 'review'),
        ];

        $this->view_host->render_portal_page(
            'referrals/visit-review',
            $page_title,
            'referral',
            $this->clinical_access->referral_breadcrumbs($referral, $page_title),
            $view
        );
    }

    private function handle_review_post(int $referral_id, int $visit_id): void
    {
        $nonce = isset($_POST['jmrs_review_visit_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_review_visit_nonce']))
            : '';

        if (
            ! wp_verify_nonce($nonce, 'jmrs_review_care_visit_' . $visit_id)
            || absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id
            || absint($_POST['jmrs_visit_id'] ?? 0) !== $visit_id
        ) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->visit_controller->attempt_review($referral_id, $visit_id, $_POST);

        if (! empty($result['not_found']) || ! empty($result['forbidden'])) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (! $result['success']) {
            $this->visit_controller->persist_execution_form_state(
                $referral_id,
                $result['data'],
                $result['errors'],
                $visit_id,
                'portal'
            );
            wp_safe_redirect(PortalUrls::visit_review($referral_id, $visit_id));
            exit;
        }

        wp_safe_redirect(
            add_query_arg('jmrs_visit_reviewed', '1', PortalUrls::referral($referral_id))
        );
        exit;
    }
}
