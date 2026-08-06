<?php

namespace JMReferral\Portal\Clinical;

use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\PortalUrls;
use JMReferral\Scheduling\ScheduleController;
use JMReferral\Scheduling\ScheduleGenerationService;
use JMReferral\Scheduling\ScheduleService;

/**
 * Portal handler for schedule create/edit/generate forms.
 */
class ScheduleHandler
{
    public function __construct(
        private PortalViewHost $view_host,
        private ClinicalAccess $clinical_access,
        private ScheduleController $schedule_controller,
        private ScheduleService $schedule_service
    ) {
    }

    public function handle_new(int $referral_id): void
    {
        $this->handle_form($referral_id, 0);
    }

    public function handle_edit(int $referral_id, int $schedule_id): void
    {
        $this->handle_form($referral_id, $schedule_id);
    }

    private function handle_form(int $referral_id, int $schedule_id): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SCHEDULES)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $access = $this->clinical_access->require_referral($referral_id, true, true);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return;
        }
        $referral = $access['referral'];

        if (! $this->clinical_access->can_manage_schedules($referral)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $schedule = null;
        if ($schedule_id > 0) {
            $prepared = $this->schedule_service->prepare_edit($schedule_id);
            if (isset($prepared['errors'])) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }

            $schedule = $prepared['schedule'];
            if (absint($schedule['referral_id'] ?? 0) !== $referral_id) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }
        }

        if (isset($_POST['jmrs_save_schedule'])) {
            $this->handle_save_post($referral_id, $schedule_id);

            return;
        }

        $form_state = ScheduleController::get_form_state($referral_id, true, 'portal');
        $errors     = $form_state['errors'];
        $data       = (! empty($form_state['data']) && absint($form_state['schedule_id'] ?? 0) === $schedule_id)
            ? array_merge(ScheduleService::empty_form_data(), $form_state['data'])
            : ScheduleService::map_to_form_data($schedule);

        $page_title = null === $schedule
            ? __('Add Schedule', 'jm-referral-system')
            : __('Edit Schedule', 'jm-referral-system');

        $form_action = $schedule_id > 0
            ? PortalUrls::schedule_edit($referral_id, $schedule_id)
            : PortalUrls::schedule_new($referral_id);

        $view = [
            'referral'       => $referral,
            'schedule'       => $schedule,
            'schedule_id'    => $schedule_id,
            'data'           => $data,
            'errors'         => $errors,
            'repeat_labels'  => ScheduleService::repeat_type_labels(),
            'status_labels'  => ScheduleService::status_labels(),
            'weekday_labels' => ScheduleService::weekday_labels(),
            'team_options'   => $this->schedule_controller->build_team_assignment_options($referral_id),
            'form_action'    => $form_action,
            'cancel_url'     => PortalUrls::referral($referral_id),
            'is_create'      => null === $schedule,
        ];

        $this->view_host->render_portal_page(
            'referrals/schedule-form',
            $page_title,
            'referral',
            $this->clinical_access->referral_breadcrumbs($referral, $page_title),
            $view
        );
    }

    private function handle_save_post(int $referral_id, int $schedule_id): void
    {
        $nonce = isset($_POST['jmrs_schedule_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_schedule_nonce']))
            : '';

        if (
            ! wp_verify_nonce($nonce, 'jmrs_save_schedule_' . $referral_id)
            || absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id
        ) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $posted_schedule_id = isset($_POST['jmrs_schedule_id']) ? absint($_POST['jmrs_schedule_id']) : 0;
        if ($posted_schedule_id !== $schedule_id) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->schedule_controller->attempt_save($referral_id, $_POST, $schedule_id);

        if (! empty($result['not_found']) || ! empty($result['forbidden'])) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $redirect_url = $schedule_id > 0
            ? PortalUrls::schedule_edit($referral_id, $schedule_id)
            : PortalUrls::schedule_new($referral_id);

        if (! $result['success']) {
            $this->schedule_controller->persist_form_state(
                $referral_id,
                $result['data'],
                $result['errors'],
                $schedule_id,
                'portal'
            );
            wp_safe_redirect($redirect_url);
            exit;
        }

        wp_safe_redirect(
            add_query_arg('jmrs_schedule_saved', '1', PortalUrls::referral($referral_id))
        );
        exit;
    }

    public function handle_generate(int $referral_id, int $schedule_id): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SCHEDULES)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $access = $this->clinical_access->require_referral($referral_id, true, true);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return;
        }
        $referral = $access['referral'];

        if (! $this->clinical_access->can_manage_schedules($referral)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $prepared = $this->schedule_service->prepare_edit($schedule_id);
        if (isset($prepared['errors'])) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $schedule = $prepared['schedule'];
        if (absint($schedule['referral_id'] ?? 0) !== $referral_id) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (isset($_POST['jmrs_generate_schedule_visits'])) {
            $this->handle_generate_post($referral_id, $schedule_id);

            return;
        }

        $form_state = ScheduleController::get_form_state($referral_id, true, 'portal');
        $errors     = $form_state['errors'];
        $posted     = (absint($form_state['schedule_id'] ?? 0) === $schedule_id) ? $form_state['data'] : [];

        $defaults = ScheduleGenerationService::default_window($schedule) ?? [];

        $page_title = __('Generate Visits', 'jm-referral-system');

        $view = [
            'referral'    => $referral,
            'schedule'    => $schedule,
            'errors'      => $errors,
            'start_date'  => (string) ($posted['generation_start_date'] ?? ($defaults['start'] ?? '')),
            'end_date'    => (string) ($posted['generation_end_date'] ?? ($defaults['end'] ?? '')),
            'form_action' => PortalUrls::schedule_generate($referral_id, $schedule_id),
            'cancel_url'  => PortalUrls::referral($referral_id),
        ];

        $this->view_host->render_portal_page(
            'referrals/schedule-generate',
            $page_title,
            'referral',
            $this->clinical_access->referral_breadcrumbs($referral, $page_title),
            $view
        );
    }

    private function handle_generate_post(int $referral_id, int $schedule_id): void
    {
        $nonce = isset($_POST['jmrs_generate_schedule_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_generate_schedule_nonce']))
            : '';

        if (
            ! wp_verify_nonce($nonce, 'jmrs_generate_schedule_visits_' . $schedule_id)
            || absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id
            || absint($_POST['jmrs_schedule_id'] ?? 0) !== $schedule_id
        ) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $start_date = isset($_POST['generation_start_date'])
            ? sanitize_text_field(wp_unslash($_POST['generation_start_date']))
            : '';
        $end_date = isset($_POST['generation_end_date'])
            ? sanitize_text_field(wp_unslash($_POST['generation_end_date']))
            : '';

        $result = $this->schedule_controller->attempt_generate($referral_id, $schedule_id, $start_date, $end_date);

        if (! empty($result['not_found']) || ! empty($result['forbidden'])) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (! $result['success']) {
            $this->schedule_controller->persist_form_state(
                $referral_id,
                $result['data'],
                $result['errors'],
                $schedule_id,
                'portal'
            );
            wp_safe_redirect(PortalUrls::schedule_generate($referral_id, $schedule_id));
            exit;
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'jmrs_schedule_visits_created' => (string) absint($result['created']),
                    'jmrs_schedule_visits_skipped' => (string) absint($result['skipped_duplicates']),
                    'jmrs_schedule_visits_outside' => (string) absint($result['skipped_outside_range']),
                ],
                PortalUrls::referral($referral_id)
            )
        );
        exit;
    }
}
