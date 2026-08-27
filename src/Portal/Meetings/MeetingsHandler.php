<?php

namespace JMReferral\Portal\Meetings;

use JMReferral\Meeting\MeetingAttendee;
use JMReferral\Meeting\MeetingAttendeeRepository;
use JMReferral\Meeting\MeetingAttendeeService;
use JMReferral\Meeting\MeetingLifecyclePolicy;
use JMReferral\Meeting\ReferralMeeting;
use JMReferral\Meeting\ReferralMeetingReadService;
use JMReferral\Meeting\ReferralMeetingRepository;
use JMReferral\Meeting\ReferralMeetingService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Portal\Clinical\ClinicalAccess;
use JMReferral\Portal\Clinical\PortalViewHost;
use JMReferral\Portal\PortalRouter;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\ReferralRetentionService;

/**
 * Portal meeting list/detail (4B.2.1), write workflows (4B.2.2), internal attendees (4B.2.3).
 */
class MeetingsHandler
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_meeting_form_';

    private const WRITE_ROUTES = [
        'referral_meeting_new',
        'referral_meeting_edit',
        'referral_meeting_schedule',
        'referral_meeting_complete',
        'referral_meeting_cancel',
    ];

    private const ATTENDEE_ROUTES = [
        'referral_meeting_internal_attendee_new',
        'referral_meeting_internal_attendee_edit',
        'referral_meeting_internal_attendee_remove',
    ];

    private MeetingLifecyclePolicy $lifecycle_policy;

    public function __construct(
        private PortalViewHost $view_host,
        private ClinicalAccess $clinical_access,
        private AccessPolicy $access_policy,
        private ReferralMeetingReadService $read_service,
        private ReferralMeetingService $meeting_service,
        private MeetingAttendeeService $attendee_service,
        private ReferralMeetingRepository $meeting_repository,
        private MeetingAttendeeRepository $attendee_repository,
        private ReferralRetentionService $retention_service,
        ?MeetingLifecyclePolicy $lifecycle_policy = null
    ) {
        $this->lifecycle_policy = $lifecycle_policy ?? new MeetingLifecyclePolicy();
    }

    public function handles(string $route): bool
    {
        return in_array(
            $route,
            array_merge(['referral_meetings', 'referral_meeting'], self::WRITE_ROUTES, self::ATTENDEE_ROUTES),
            true
        );
    }

    public function dispatch(string $route): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (! in_array($method, ['GET', 'POST'], true)) {
            $this->view_host->render_portal_error('405', __('Method Not Allowed', 'jm-referral-system'), 405);

            return;
        }

        $referral_id = absint(get_query_var(PortalRouter::QV_ID));
        $meeting_id  = absint(get_query_var(PortalRouter::QV_ENTITY));
        $attendee_id = absint(get_query_var(PortalRouter::QV_SUB_ENTITY));

        match ($route) {
            'referral_meetings' => $this->handle_list($referral_id),
            'referral_meeting' => $this->handle_detail($referral_id, $meeting_id),
            'referral_meeting_new' => $this->handle_new($referral_id),
            'referral_meeting_edit' => $this->handle_edit($referral_id, $meeting_id),
            'referral_meeting_schedule' => $this->handle_schedule($referral_id, $meeting_id),
            'referral_meeting_complete' => $this->handle_complete($referral_id, $meeting_id),
            'referral_meeting_cancel' => $this->handle_cancel($referral_id, $meeting_id),
            'referral_meeting_internal_attendee_new' => $this->handle_internal_attendee_new($referral_id, $meeting_id),
            'referral_meeting_internal_attendee_edit' => $this->handle_internal_attendee_edit($referral_id, $meeting_id, $attendee_id),
            'referral_meeting_internal_attendee_remove' => $this->handle_internal_attendee_remove($referral_id, $meeting_id, $attendee_id),
            default => $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404),
        };
    }

    private function handle_list(int $referral_id): void
    {
        $access = $this->clinical_access->require_referral($referral_id, false, false);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return;
        }

        $referral = $access['referral'];
        if (! $this->access_policy->can_view_referral_meetings($referral)) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $page = isset($_GET['jmrs_meeting_page']) ? absint(wp_unslash($_GET['jmrs_meeting_page'])) : 1;
        $data = $this->read_service->get_list_page($referral, $page);
        if (null === $data) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $is_archived = $this->retention_service->is_archived($referral);
        $can_manage  = ! $is_archived && $this->access_policy->can_manage_referral_meetings($referral);

        $this->view_host->render_portal_page(
            'referrals/meetings-list',
            __('Meetings', 'jm-referral-system'),
            'referral',
            $this->meetings_breadcrumbs($referral, __('Meetings', 'jm-referral-system'), null),
            [
                'referral'      => $referral,
                'is_archived'   => $is_archived,
                'list'          => $data,
                'can_manage'    => $can_manage,
                'new_url'       => $can_manage ? PortalUrls::referral_meeting_new($referral_id) : '',
                'referral_url'  => PortalUrls::referral($referral_id),
                'flash_notice'  => $this->consume_flash_notice(),
            ]
        );
    }

    private function handle_detail(int $referral_id, int $meeting_id): void
    {
        $access = $this->clinical_access->require_referral($referral_id, false, false);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return;
        }

        $referral = $access['referral'];
        if (! $this->access_policy->can_view_referral_meetings($referral)) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $data = $this->read_service->get_detail_page($referral, $meeting_id);
        if (null === $data) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $is_archived = $this->retention_service->is_archived($referral);
        $can_manage  = ! $is_archived && ! empty($data['can_manage']);
        $status      = (string) ($data['meeting']['status'] ?? '');
        $actions     = $this->action_urls($referral_id, $meeting_id, $status, $can_manage);
        $attendee_actions = $this->internal_attendee_action_urls(
            $referral_id,
            $meeting_id,
            $status,
            $can_manage,
            is_array($data['internal'] ?? null) ? $data['internal'] : []
        );

        $type_label = (string) ($data['meeting']['meeting_type_label'] ?? __('Meeting', 'jm-referral-system'));

        $this->view_host->render_portal_page(
            'referrals/meeting-detail',
            $type_label,
            'referral',
            $this->meetings_breadcrumbs($referral, __('Meetings', 'jm-referral-system'), $type_label),
            [
                'referral'          => $referral,
                'is_archived'       => $is_archived,
                'detail'            => $data,
                'can_manage'        => $can_manage,
                'actions'           => $actions,
                'attendee_actions'  => $attendee_actions,
                'flash_notice'      => $this->consume_flash_notice(),
            ]
        );
    }

    private function handle_new(int $referral_id): void
    {
        $gate = $this->require_manage_referral($referral_id);
        if (null === $gate) {
            return;
        }
        $referral = $gate;

        if (isset($_POST['jmrs_save_meeting'])) {
            $this->post_create($referral_id);

            return;
        }

        $state = $this->get_form_state($referral_id, 'new', 0, true);
        $data  = $state['data'] !== [] ? $state['data'] : $this->empty_form_data();

        $this->render_form(
            $referral,
            0,
            'new',
            __('Add meeting', 'jm-referral-system'),
            PortalUrls::referral_meeting_new($referral_id),
            $data,
            $state['errors'],
            [
                'mode'            => 'create',
                'show_type'       => true,
                'show_purpose'    => true,
                'show_schedule'   => true,
                'show_location'   => true,
                'show_outcome'    => false,
                'past_warning'    => $this->form_has_past_schedule($data),
                'submit_draft'    => true,
                'submit_scheduled'=> true,
            ]
        );
    }

    private function handle_edit(int $referral_id, int $meeting_id): void
    {
        $ctx = $this->require_manage_meeting($referral_id, $meeting_id);
        if (null === $ctx) {
            return;
        }
        ['referral' => $referral, 'meeting' => $meeting] = $ctx;
        $status = (string) ($meeting['status'] ?? '');

        if (! $this->lifecycle_policy->allows(MeetingLifecyclePolicy::ACTION_UPDATE_DETAILS, $status)) {
            $this->view_host->render_portal_error(
                '403',
                __('This meeting cannot be edited.', 'jm-referral-system'),
                403
            );

            return;
        }

        if (isset($_POST['jmrs_save_meeting'])) {
            $this->post_edit($referral_id, $meeting_id);

            return;
        }

        $state = $this->get_form_state($referral_id, 'edit', $meeting_id, true);
        $data  = $state['data'] !== [] ? $state['data'] : $this->meeting_to_form_data($meeting);

        $this->render_form(
            $referral,
            $meeting_id,
            'edit',
            __('Edit meeting', 'jm-referral-system'),
            PortalUrls::referral_meeting_edit($referral_id, $meeting_id),
            $data,
            $state['errors'],
            [
                'mode'             => 'edit',
                'show_type'        => true,
                'show_purpose'     => true,
                'show_schedule'    => ReferralMeeting::STATUS_DRAFT === $status,
                'show_location'    => true,
                'show_outcome'     => false,
                'past_warning'     => $this->form_has_past_schedule($data),
                'submit_draft'     => false,
                'submit_scheduled' => false,
                'submit_label'     => __('Save changes', 'jm-referral-system'),
                'status'           => $status,
            ]
        );
    }

    private function handle_schedule(int $referral_id, int $meeting_id): void
    {
        $ctx = $this->require_manage_meeting($referral_id, $meeting_id);
        if (null === $ctx) {
            return;
        }
        ['referral' => $referral, 'meeting' => $meeting] = $ctx;
        $status = (string) ($meeting['status'] ?? '');

        $is_draft = ReferralMeeting::STATUS_DRAFT === $status;
        $action   = $is_draft
            ? MeetingLifecyclePolicy::ACTION_SCHEDULE
            : MeetingLifecyclePolicy::ACTION_RESCHEDULE;

        if (! $this->lifecycle_policy->allows($action, $status)) {
            $this->view_host->render_portal_error(
                '403',
                __('This meeting cannot be scheduled or rescheduled.', 'jm-referral-system'),
                403
            );

            return;
        }

        if (isset($_POST['jmrs_save_meeting'])) {
            $this->post_schedule($referral_id, $meeting_id, $is_draft);

            return;
        }

        $state = $this->get_form_state($referral_id, 'schedule', $meeting_id, true);
        $data  = $state['data'] !== [] ? $state['data'] : $this->meeting_to_form_data($meeting);
        $title = $is_draft
            ? __('Schedule meeting', 'jm-referral-system')
            : __('Reschedule meeting', 'jm-referral-system');

        $this->render_form(
            $referral,
            $meeting_id,
            'schedule',
            $title,
            PortalUrls::referral_meeting_schedule($referral_id, $meeting_id),
            $data,
            $state['errors'],
            [
                'mode'             => $is_draft ? 'schedule' : 'reschedule',
                'show_type'        => false,
                'show_purpose'     => false,
                'show_schedule'    => true,
                'show_location'    => true,
                'show_outcome'     => false,
                'past_warning'     => $this->form_has_past_schedule($data),
                'submit_draft'     => false,
                'submit_scheduled' => false,
                'submit_label'     => $title,
                'status'           => $status,
            ]
        );
    }

    private function handle_complete(int $referral_id, int $meeting_id): void
    {
        $ctx = $this->require_manage_meeting($referral_id, $meeting_id);
        if (null === $ctx) {
            return;
        }
        ['referral' => $referral, 'meeting' => $meeting] = $ctx;
        $status = (string) ($meeting['status'] ?? '');

        if (ReferralMeeting::STATUS_COMPLETED === $status) {
            wp_safe_redirect(add_query_arg(
                'jmrs_meeting_notice',
                'already_completed',
                PortalUrls::referral_meeting($referral_id, $meeting_id)
            ));
            exit;
        }

        if (! $this->lifecycle_policy->allows(MeetingLifecyclePolicy::ACTION_COMPLETE, $status)) {
            $this->view_host->render_portal_error(
                '403',
                __('Only scheduled meetings can be completed.', 'jm-referral-system'),
                403
            );

            return;
        }

        if (isset($_POST['jmrs_save_meeting'])) {
            $this->post_complete($referral_id, $meeting_id);

            return;
        }

        $state = $this->get_form_state($referral_id, 'complete', $meeting_id, true);
        $data  = $state['data'] !== [] ? $state['data'] : [
            'outcome' => (string) ($meeting['outcome'] ?? ''),
        ];

        $attendance_warning_count = $this->attendee_service->count_non_final_attendance($meeting_id);

        $this->view_host->render_portal_page(
            'referrals/meeting-complete',
            __('Complete meeting', 'jm-referral-system'),
            'referral',
            $this->meetings_breadcrumbs($referral, __('Meetings', 'jm-referral-system'), __('Complete meeting', 'jm-referral-system')),
            [
                'referral'                  => $referral,
                'meeting'                   => $meeting,
                'meeting_id'                => $meeting_id,
                'data'                      => $data,
                'errors'                    => $state['errors'],
                'form_action'               => PortalUrls::referral_meeting_complete($referral_id, $meeting_id),
                'cancel_url'                => PortalUrls::referral_meeting($referral_id, $meeting_id),
                'attendance_warning'        => $attendance_warning_count > 0,
                'attendance_warning_count'  => $attendance_warning_count,
            ]
        );
    }

    private function handle_cancel(int $referral_id, int $meeting_id): void
    {
        $ctx = $this->require_manage_meeting($referral_id, $meeting_id);
        if (null === $ctx) {
            return;
        }
        ['referral' => $referral, 'meeting' => $meeting] = $ctx;
        $status = (string) ($meeting['status'] ?? '');

        if (ReferralMeeting::STATUS_CANCELLED === $status) {
            wp_safe_redirect(add_query_arg(
                'jmrs_meeting_notice',
                'already_cancelled',
                PortalUrls::referral_meeting($referral_id, $meeting_id)
            ));
            exit;
        }

        if (! $this->lifecycle_policy->allows(MeetingLifecyclePolicy::ACTION_CANCEL, $status)) {
            $this->view_host->render_portal_error(
                '403',
                __('This meeting cannot be cancelled.', 'jm-referral-system'),
                403
            );

            return;
        }

        if (isset($_POST['jmrs_save_meeting'])) {
            $this->post_cancel($referral_id, $meeting_id);

            return;
        }

        $this->view_host->render_portal_page(
            'referrals/meeting-cancel',
            __('Cancel meeting', 'jm-referral-system'),
            'referral',
            $this->meetings_breadcrumbs($referral, __('Meetings', 'jm-referral-system'), __('Cancel meeting', 'jm-referral-system')),
            [
                'referral'    => $referral,
                'meeting'     => $meeting,
                'meeting_id'  => $meeting_id,
                'form_action' => PortalUrls::referral_meeting_cancel($referral_id, $meeting_id),
                'cancel_url'  => PortalUrls::referral_meeting($referral_id, $meeting_id),
            ]
        );
    }

    private function handle_internal_attendee_new(int $referral_id, int $meeting_id): void
    {
        $ctx = $this->require_manage_meeting($referral_id, $meeting_id);
        if (null === $ctx) {
            return;
        }
        ['referral' => $referral, 'meeting' => $meeting] = $ctx;
        $status = (string) ($meeting['status'] ?? '');

        if (! $this->lifecycle_policy->allows_internal_attendee_add($status)) {
            $this->view_host->render_portal_error(
                '403',
                __('Internal attendees cannot be added for this meeting status.', 'jm-referral-system'),
                403
            );

            return;
        }

        if (isset($_POST['jmrs_save_meeting_attendee'])) {
            $this->post_internal_attendee_new($referral_id, $meeting_id);

            return;
        }

        $state = $this->get_form_state($referral_id, 'attendee_new', $meeting_id, true);
        $data  = $state['data'] !== [] ? $state['data'] : [
            'user_id'            => '',
            'meeting_role'       => '',
            'attendance_status'  => MeetingAttendee::ATTENDANCE_INVITED,
        ];

        $this->view_host->render_portal_page(
            'referrals/meeting-internal-attendee-form',
            __('Add internal attendee', 'jm-referral-system'),
            'referral',
            $this->meetings_breadcrumbs($referral, __('Meetings', 'jm-referral-system'), __('Add internal attendee', 'jm-referral-system')),
            [
                'referral'            => $referral,
                'meeting'             => $meeting,
                'meeting_id'          => $meeting_id,
                'attendee_id'         => 0,
                'mode'                => 'add',
                'data'                => $data,
                'errors'              => $state['errors'],
                'staff_options'       => $this->attendee_service->eligible_internal_staff_for_meeting($meeting_id),
                'attendance_labels'   => MeetingAttendee::attendance_status_labels(),
                'staff_display_name'  => '',
                'form_action'         => PortalUrls::referral_meeting_internal_attendee_new($referral_id, $meeting_id),
                'cancel_url'          => PortalUrls::referral_meeting($referral_id, $meeting_id),
            ]
        );
    }

    private function handle_internal_attendee_edit(int $referral_id, int $meeting_id, int $attendee_id): void
    {
        $ctx = $this->require_manage_internal_attendee($referral_id, $meeting_id, $attendee_id);
        if (null === $ctx) {
            return;
        }
        ['referral' => $referral, 'meeting' => $meeting, 'attendee' => $attendee] = $ctx;
        $status = (string) ($meeting['status'] ?? '');

        $can_full_edit = $this->lifecycle_policy->allows_internal_attendee_edit($status);
        $can_correct   = $this->lifecycle_policy->allows_internal_attendance_correction($status);

        if (! $can_full_edit && ! $can_correct) {
            $this->view_host->render_portal_error(
                '403',
                __('This attendee cannot be edited for the current meeting status.', 'jm-referral-system'),
                403
            );

            return;
        }

        if (isset($_POST['jmrs_save_meeting_attendee'])) {
            $this->post_internal_attendee_edit($referral_id, $meeting_id, $attendee_id, $can_full_edit);

            return;
        }

        $form_key = 'attendee_edit_' . $attendee_id;
        $state    = $this->get_form_state($referral_id, $form_key, $meeting_id, true);
        $data     = $state['data'] !== [] ? $state['data'] : [
            'meeting_role'      => (string) ($attendee['meeting_role'] ?? ''),
            'attendance_status' => (string) ($attendee['attendance_status'] ?? MeetingAttendee::ATTENDANCE_INVITED),
        ];

        $title = $can_full_edit
            ? __('Edit internal attendee', 'jm-referral-system')
            : __('Correct attendance', 'jm-referral-system');

        $this->view_host->render_portal_page(
            'referrals/meeting-internal-attendee-form',
            $title,
            'referral',
            $this->meetings_breadcrumbs($referral, __('Meetings', 'jm-referral-system'), $title),
            [
                'referral'            => $referral,
                'meeting'             => $meeting,
                'meeting_id'          => $meeting_id,
                'attendee_id'         => $attendee_id,
                'mode'                => $can_full_edit ? 'edit' : 'correct',
                'data'                => $data,
                'errors'              => $state['errors'],
                'staff_options'       => [],
                'attendance_labels'   => $can_full_edit
                    ? MeetingAttendee::attendance_status_labels()
                    : MeetingAttendee::final_attendance_status_labels(),
                'staff_display_name'  => $this->internal_attendee_display_name($attendee),
                'form_action'         => PortalUrls::referral_meeting_internal_attendee_edit($referral_id, $meeting_id, $attendee_id),
                'cancel_url'          => PortalUrls::referral_meeting($referral_id, $meeting_id),
            ]
        );
    }

    private function handle_internal_attendee_remove(int $referral_id, int $meeting_id, int $attendee_id): void
    {
        $ctx = $this->require_manage_internal_attendee($referral_id, $meeting_id, $attendee_id);
        if (null === $ctx) {
            return;
        }
        ['referral' => $referral, 'meeting' => $meeting, 'attendee' => $attendee] = $ctx;
        $status = (string) ($meeting['status'] ?? '');

        if (! $this->lifecycle_policy->allows_internal_attendee_remove($status)) {
            $this->view_host->render_portal_error(
                '403',
                __('This attendee cannot be removed for the current meeting status.', 'jm-referral-system'),
                403
            );

            return;
        }

        if (isset($_POST['jmrs_save_meeting_attendee'])) {
            $this->post_internal_attendee_remove($referral_id, $meeting_id, $attendee_id);

            return;
        }

        $this->view_host->render_portal_page(
            'referrals/meeting-internal-attendee-remove',
            __('Remove internal attendee', 'jm-referral-system'),
            'referral',
            $this->meetings_breadcrumbs($referral, __('Meetings', 'jm-referral-system'), __('Remove internal attendee', 'jm-referral-system')),
            [
                'referral'           => $referral,
                'meeting'            => $meeting,
                'meeting_id'         => $meeting_id,
                'attendee_id'        => $attendee_id,
                'staff_display_name' => $this->internal_attendee_display_name($attendee),
                'meeting_role'       => (string) ($attendee['meeting_role'] ?? ''),
                'attendance_label'   => MeetingAttendee::attendance_status_label((string) ($attendee['attendance_status'] ?? '')),
                'form_action'        => PortalUrls::referral_meeting_internal_attendee_remove($referral_id, $meeting_id, $attendee_id),
                'cancel_url'         => PortalUrls::referral_meeting($referral_id, $meeting_id),
            ]
        );
    }

    private function post_internal_attendee_new(int $referral_id, int $meeting_id): void
    {
        if (! $this->verify_attendee_nonce($referral_id, $meeting_id, 0)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $input = [
            'user_id'            => absint($_POST['jmrs_attendee_user_id'] ?? 0),
            'meeting_role'       => isset($_POST['jmrs_attendee_meeting_role'])
                ? wp_unslash((string) $_POST['jmrs_attendee_meeting_role'])
                : '',
            'attendance_status'  => isset($_POST['jmrs_attendee_attendance_status'])
                ? sanitize_key((string) wp_unslash($_POST['jmrs_attendee_attendance_status']))
                : MeetingAttendee::ATTENDANCE_INVITED,
        ];

        $result = $this->attendee_service->add_internal_attendee($meeting_id, $input);

        if (empty($result['ok'])) {
            if (in_array((string) ($result['error'] ?? ''), ['not_found', 'forbidden', 'archived', 'invalid_transition', 'meeting_not_found', 'referral_not_found'], true)) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }
            $this->persist_form_state($referral_id, 'attendee_new', $meeting_id, [
                'user_id'           => (string) $input['user_id'],
                'meeting_role'      => (string) $input['meeting_role'],
                'attendance_status' => (string) $input['attendance_status'],
            ], $result['field_errors'] ?? [
                'form' => $this->attendee_error_message((string) ($result['error'] ?? 'validation')),
            ]);
            wp_safe_redirect(PortalUrls::referral_meeting_internal_attendee_new($referral_id, $meeting_id));
            exit;
        }

        wp_safe_redirect(add_query_arg(
            'jmrs_meeting_notice',
            'attendee_added',
            PortalUrls::referral_meeting($referral_id, $meeting_id)
        ));
        exit;
    }

    private function post_internal_attendee_edit(int $referral_id, int $meeting_id, int $attendee_id, bool $full_edit): void
    {
        if (! $this->verify_attendee_nonce($referral_id, $meeting_id, $attendee_id)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $input = [
            'meeting_role'      => isset($_POST['jmrs_attendee_meeting_role'])
                ? wp_unslash((string) $_POST['jmrs_attendee_meeting_role'])
                : '',
            'attendance_status' => isset($_POST['jmrs_attendee_attendance_status'])
                ? sanitize_key((string) wp_unslash($_POST['jmrs_attendee_attendance_status']))
                : '',
        ];

        $result = $full_edit
            ? $this->attendee_service->update_internal_attendee($attendee_id, $input, null, $meeting_id)
            : $this->attendee_service->update_internal_attendance($attendee_id, $input, null, $meeting_id);

        if (empty($result['ok'])) {
            if (in_array((string) ($result['error'] ?? ''), ['not_found', 'forbidden', 'archived', 'invalid_transition', 'meeting_not_found', 'referral_not_found'], true)) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }
            $form_key = 'attendee_edit_' . $attendee_id;
            $this->persist_form_state($referral_id, $form_key, $meeting_id, [
                'meeting_role'      => (string) $input['meeting_role'],
                'attendance_status' => (string) $input['attendance_status'],
            ], $result['field_errors'] ?? [
                'form' => $this->attendee_error_message((string) ($result['error'] ?? 'validation')),
            ]);
            wp_safe_redirect(PortalUrls::referral_meeting_internal_attendee_edit($referral_id, $meeting_id, $attendee_id));
            exit;
        }

        $notice = empty($result['changed']) ? 'attendee_unchanged' : 'attendee_updated';
        wp_safe_redirect(add_query_arg(
            'jmrs_meeting_notice',
            $notice,
            PortalUrls::referral_meeting($referral_id, $meeting_id)
        ));
        exit;
    }

    private function post_internal_attendee_remove(int $referral_id, int $meeting_id, int $attendee_id): void
    {
        if (! $this->verify_attendee_nonce($referral_id, $meeting_id, $attendee_id)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->attendee_service->remove_internal_attendee($attendee_id, null, $meeting_id);

        if (empty($result['ok'])) {
            if (in_array((string) ($result['error'] ?? ''), ['not_found', 'forbidden', 'archived', 'invalid_transition', 'meeting_not_found', 'referral_not_found'], true)) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }
            wp_safe_redirect(add_query_arg(
                'jmrs_meeting_notice',
                'attendee_remove_failed',
                PortalUrls::referral_meeting($referral_id, $meeting_id)
            ));
            exit;
        }

        wp_safe_redirect(add_query_arg(
            'jmrs_meeting_notice',
            'attendee_removed',
            PortalUrls::referral_meeting($referral_id, $meeting_id)
        ));
        exit;
    }

    private function post_create(int $referral_id): void
    {
        if (! $this->verify_nonce($referral_id, 0)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $intent = sanitize_key((string) ($_POST['jmrs_save_meeting'] ?? 'draft'));
        if (! in_array($intent, ['draft', 'scheduled'], true)) {
            $intent = 'draft';
        }
        $input  = $this->posted_meeting_fields();

        $result = 'scheduled' === $intent
            ? $this->meeting_service->create_scheduled($referral_id, $input)
            : $this->meeting_service->create_draft($referral_id, $input);

        if (empty($result['ok'])) {
            $this->persist_form_state($referral_id, 'new', 0, $input, $result['field_errors'] ?? [
                'form' => $this->error_message((string) ($result['error'] ?? 'validation')),
            ]);
            wp_safe_redirect(PortalUrls::referral_meeting_new($referral_id));
            exit;
        }

        $meeting_id = (int) $result['meeting_id'];
        wp_safe_redirect(add_query_arg(
            'jmrs_meeting_notice',
            'scheduled' === $intent ? 'created_scheduled' : 'created_draft',
            PortalUrls::referral_meeting($referral_id, $meeting_id)
        ));
        exit;
    }

    private function post_edit(int $referral_id, int $meeting_id): void
    {
        if (! $this->verify_nonce($referral_id, $meeting_id)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $input  = $this->posted_meeting_fields();
        $result = $this->meeting_service->update_details($meeting_id, $input);

        if (empty($result['ok'])) {
            if (in_array((string) ($result['error'] ?? ''), ['not_found', 'forbidden', 'archived', 'invalid_transition'], true)) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }
            $this->persist_form_state($referral_id, 'edit', $meeting_id, $input, $result['field_errors'] ?? [
                'form' => $this->error_message((string) ($result['error'] ?? 'validation')),
            ]);
            wp_safe_redirect(PortalUrls::referral_meeting_edit($referral_id, $meeting_id));
            exit;
        }

        $notice = empty($result['changed']) ? 'unchanged' : 'updated';
        wp_safe_redirect(add_query_arg(
            'jmrs_meeting_notice',
            $notice,
            PortalUrls::referral_meeting($referral_id, $meeting_id)
        ));
        exit;
    }

    private function post_schedule(int $referral_id, int $meeting_id, bool $is_draft): void
    {
        if (! $this->verify_nonce($referral_id, $meeting_id)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $input  = $this->posted_meeting_fields();
        $result = $is_draft
            ? $this->meeting_service->schedule($meeting_id, $input)
            : $this->meeting_service->reschedule($meeting_id, $input);

        if (empty($result['ok'])) {
            if (in_array((string) ($result['error'] ?? ''), ['not_found', 'forbidden', 'archived', 'invalid_transition'], true)) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }
            $this->persist_form_state($referral_id, 'schedule', $meeting_id, $input, $result['field_errors'] ?? [
                'form' => $this->error_message((string) ($result['error'] ?? 'validation')),
            ]);
            wp_safe_redirect(PortalUrls::referral_meeting_schedule($referral_id, $meeting_id));
            exit;
        }

        wp_safe_redirect(add_query_arg(
            'jmrs_meeting_notice',
            $is_draft ? 'scheduled' : 'rescheduled',
            PortalUrls::referral_meeting($referral_id, $meeting_id)
        ));
        exit;
    }

    private function post_complete(int $referral_id, int $meeting_id): void
    {
        if (! $this->verify_nonce($referral_id, $meeting_id)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $input  = ['outcome' => isset($_POST['jmrs_meeting_outcome']) ? wp_unslash((string) $_POST['jmrs_meeting_outcome']) : ''];
        $result = $this->meeting_service->complete($meeting_id, $input);

        if (empty($result['ok'])) {
            if (in_array((string) ($result['error'] ?? ''), ['not_found', 'forbidden', 'archived', 'invalid_transition'], true)) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }
            $this->persist_form_state($referral_id, 'complete', $meeting_id, $input, $result['field_errors'] ?? [
                'form' => $this->error_message((string) ($result['error'] ?? 'validation')),
            ]);
            wp_safe_redirect(PortalUrls::referral_meeting_complete($referral_id, $meeting_id));
            exit;
        }

        $notice = ! empty($result['already_done']) ? 'already_completed' : 'completed';
        wp_safe_redirect(add_query_arg(
            'jmrs_meeting_notice',
            $notice,
            PortalUrls::referral_meeting($referral_id, $meeting_id)
        ));
        exit;
    }

    private function post_cancel(int $referral_id, int $meeting_id): void
    {
        if (! $this->verify_nonce($referral_id, $meeting_id)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $result = $this->meeting_service->cancel($meeting_id);

        if (empty($result['ok'])) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $notice = ! empty($result['already_done']) ? 'already_cancelled' : 'cancelled';
        wp_safe_redirect(add_query_arg(
            'jmrs_meeting_notice',
            $notice,
            PortalUrls::referral_meeting($referral_id, $meeting_id)
        ));
        exit;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function require_manage_referral(int $referral_id): ?array
    {
        $access = $this->clinical_access->require_referral($referral_id, false, false);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return null;
        }

        $referral = $access['referral'];
        if ($this->retention_service->is_archived($referral)
            || ! $this->access_policy->can_manage_referral_meetings($referral)
        ) {
            // Non-leaking denial for Assessor / Support Worker / archived.
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return null;
        }

        return $referral;
    }

    /**
     * @return array{referral: array<string, mixed>, meeting: array<string, mixed>}|null
     */
    private function require_manage_meeting(int $referral_id, int $meeting_id): ?array
    {
        $referral = $this->require_manage_referral($referral_id);
        if (null === $referral) {
            return null;
        }

        $meeting = $this->meeting_repository->find($meeting_id);
        if (null === $meeting || absint($meeting['referral_id'] ?? 0) !== $referral_id) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return null;
        }

        return ['referral' => $referral, 'meeting' => $meeting];
    }

    /**
     * @return array{referral: array<string, mixed>, meeting: array<string, mixed>, attendee: array<string, mixed>}|null
     */
    private function require_manage_internal_attendee(int $referral_id, int $meeting_id, int $attendee_id): ?array
    {
        $ctx = $this->require_manage_meeting($referral_id, $meeting_id);
        if (null === $ctx) {
            return null;
        }

        $attendee = $this->attendee_repository->find($attendee_id);
        if (
            null === $attendee
            || absint($attendee['meeting_id'] ?? 0) !== $meeting_id
            || MeetingAttendee::KIND_INTERNAL !== (string) ($attendee['attendee_kind'] ?? '')
        ) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return null;
        }

        return [
            'referral' => $ctx['referral'],
            'meeting'  => $ctx['meeting'],
            'attendee' => $attendee,
        ];
    }

    private function verify_nonce(int $referral_id, int $meeting_id): bool
    {
        $nonce = isset($_POST['jmrs_meeting_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['jmrs_meeting_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'jmrs_save_meeting_' . $referral_id)) {
            return false;
        }

        if (absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id) {
            return false;
        }

        if (absint($_POST['jmrs_meeting_id'] ?? 0) !== $meeting_id) {
            return false;
        }

        return true;
    }

    private function verify_attendee_nonce(int $referral_id, int $meeting_id, int $attendee_id): bool
    {
        $nonce = isset($_POST['jmrs_meeting_attendee_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['jmrs_meeting_attendee_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'jmrs_save_meeting_attendee_' . $referral_id)) {
            return false;
        }

        if (absint($_POST['jmrs_referral_id'] ?? 0) !== $referral_id) {
            return false;
        }

        if (absint($_POST['jmrs_meeting_id'] ?? 0) !== $meeting_id) {
            return false;
        }

        if (absint($_POST['jmrs_attendee_id'] ?? 0) !== $attendee_id) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function posted_meeting_fields(): array
    {
        return [
            'meeting_type'         => isset($_POST['jmrs_meeting_type']) ? wp_unslash((string) $_POST['jmrs_meeting_type']) : '',
            'purpose'              => isset($_POST['jmrs_meeting_purpose']) ? wp_unslash((string) $_POST['jmrs_meeting_purpose']) : '',
            'scheduled_date'       => isset($_POST['jmrs_meeting_scheduled_date']) ? wp_unslash((string) $_POST['jmrs_meeting_scheduled_date']) : '',
            'scheduled_time'       => isset($_POST['jmrs_meeting_scheduled_time']) ? wp_unslash((string) $_POST['jmrs_meeting_scheduled_time']) : '',
            'scheduled_end_date'   => isset($_POST['jmrs_meeting_scheduled_end_date']) ? wp_unslash((string) $_POST['jmrs_meeting_scheduled_end_date']) : '',
            'scheduled_end_time'   => isset($_POST['jmrs_meeting_scheduled_end_time']) ? wp_unslash((string) $_POST['jmrs_meeting_scheduled_end_time']) : '',
            'location_type'        => isset($_POST['jmrs_meeting_location_type']) ? wp_unslash((string) $_POST['jmrs_meeting_location_type']) : '',
            'location_name'        => isset($_POST['jmrs_meeting_location_name']) ? wp_unslash((string) $_POST['jmrs_meeting_location_name']) : '',
            'location_address'     => isset($_POST['jmrs_meeting_location_address']) ? wp_unslash((string) $_POST['jmrs_meeting_location_address']) : '',
            'online_meeting_url'   => isset($_POST['jmrs_meeting_online_url']) ? wp_unslash((string) $_POST['jmrs_meeting_online_url']) : '',
            'outcome'              => isset($_POST['jmrs_meeting_outcome']) ? wp_unslash((string) $_POST['jmrs_meeting_outcome']) : '',
        ];
    }

    /**
     * @param array<string, mixed> $referral
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     * @param array<string, mixed> $options
     */
    private function render_form(
        array $referral,
        int $meeting_id,
        string $form_key,
        string $page_title,
        string $form_action,
        array $data,
        array $errors,
        array $options
    ): void {
        $referral_id = absint($referral['id'] ?? 0);

        $this->view_host->render_portal_page(
            'referrals/meeting-form',
            $page_title,
            'referral',
            $this->meetings_breadcrumbs($referral, __('Meetings', 'jm-referral-system'), $page_title),
            array_merge($options, [
                'referral'     => $referral,
                'meeting_id'   => $meeting_id,
                'form_key'     => $form_key,
                'data'         => $data,
                'errors'       => $errors,
                'form_action'  => $form_action,
                'cancel_url'   => $meeting_id > 0
                    ? PortalUrls::referral_meeting($referral_id, $meeting_id)
                    : PortalUrls::referral_meetings($referral_id),
                'type_labels'  => ReferralMeeting::type_labels(),
                'location_labels' => ReferralMeeting::location_type_labels(),
            ])
        );
    }

    /**
     * @return array<string, string>
     */
    private function empty_form_data(): array
    {
        return [
            'meeting_type'           => '',
            'purpose'                => '',
            'scheduled_date'         => '',
            'scheduled_time'         => '',
            'scheduled_end_date'     => '',
            'scheduled_end_time'     => '',
            'location_type'          => '',
            'location_name'          => '',
            'location_address'       => '',
            'online_meeting_url'     => '',
            'outcome'                => '',
        ];
    }

    /**
     * @param array<string, mixed> $meeting
     * @return array<string, string>
     */
    private function meeting_to_form_data(array $meeting): array
    {
        $start = (string) ($meeting['scheduled_at'] ?? '');
        $end   = (string) ($meeting['scheduled_end_at'] ?? '');

        return [
            'meeting_type'       => (string) ($meeting['meeting_type'] ?? ''),
            'purpose'            => (string) ($meeting['purpose'] ?? ''),
            'scheduled_date'     => '' !== $start ? substr($start, 0, 10) : '',
            'scheduled_time'     => '' !== $start ? substr($start, 11, 5) : '',
            'scheduled_end_date' => '' !== $end ? substr($end, 0, 10) : '',
            'scheduled_end_time' => '' !== $end ? substr($end, 11, 5) : '',
            'location_type'      => (string) ($meeting['location_type'] ?? ''),
            'location_name'      => (string) ($meeting['location_name'] ?? ''),
            'location_address'   => (string) ($meeting['location_address'] ?? ''),
            'online_meeting_url' => (string) ($meeting['online_meeting_url'] ?? ''),
            'outcome'            => (string) ($meeting['outcome'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function form_has_past_schedule(array $data): bool
    {
        $date = trim((string) ($data['scheduled_date'] ?? ''));
        $time = trim((string) ($data['scheduled_time'] ?? ''));
        if ('' === $date || '' === $time) {
            return false;
        }
        if (1 === substr_count($time, ':')) {
            $time .= ':00';
        }

        try {
            $dt = new \DateTimeImmutable($date . ' ' . $time, wp_timezone());
        } catch (\Exception $e) {
            return false;
        }

        return $dt->format('Y-m-d H:i:s') < current_time('mysql');
    }

    /**
     * @param array<int, array<string, mixed>> $internal_rows
     * @return array{add?: string, by_id: array<int, array{edit?: string, remove?: string, correct?: string}>}
     */
    private function internal_attendee_action_urls(
        int $referral_id,
        int $meeting_id,
        string $status,
        bool $can_manage,
        array $internal_rows
    ): array {
        $out = ['by_id' => []];
        if (! $can_manage) {
            return $out;
        }

        if ($this->lifecycle_policy->allows_internal_attendee_add($status)) {
            $out['add'] = PortalUrls::referral_meeting_internal_attendee_new($referral_id, $meeting_id);
        }

        foreach ($internal_rows as $row) {
            $aid = absint($row['id'] ?? 0);
            if ($aid <= 0) {
                continue;
            }
            $links = [];
            if ($this->lifecycle_policy->allows_internal_attendee_edit($status)) {
                $links['edit'] = PortalUrls::referral_meeting_internal_attendee_edit($referral_id, $meeting_id, $aid);
                $links['remove'] = PortalUrls::referral_meeting_internal_attendee_remove($referral_id, $meeting_id, $aid);
            } elseif ($this->lifecycle_policy->allows_internal_attendance_correction($status)) {
                $links['correct'] = PortalUrls::referral_meeting_internal_attendee_edit($referral_id, $meeting_id, $aid);
            }
            if ([] !== $links) {
                $out['by_id'][$aid] = $links;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $attendee
     */
    private function internal_attendee_display_name(array $attendee): string
    {
        $user_id = absint($attendee['user_id'] ?? 0);
        if ($user_id <= 0) {
            return __('Unavailable user', 'jm-referral-system');
        }

        $user = get_userdata($user_id);
        if (! $user instanceof \WP_User) {
            return __('Unavailable user', 'jm-referral-system');
        }

        $name = trim((string) $user->display_name);

        return '' !== $name ? $name : __('Unavailable user', 'jm-referral-system');
    }

    /**
     * @return array<string, string>
     */
    private function action_urls(int $referral_id, int $meeting_id, string $status, bool $can_manage): array
    {
        if (! $can_manage) {
            return [];
        }

        $urls = [];
        if ($this->lifecycle_policy->allows(MeetingLifecyclePolicy::ACTION_UPDATE_DETAILS, $status)) {
            $urls['edit'] = PortalUrls::referral_meeting_edit($referral_id, $meeting_id);
        }
        if ($this->lifecycle_policy->allows(MeetingLifecyclePolicy::ACTION_SCHEDULE, $status)) {
            $urls['schedule'] = PortalUrls::referral_meeting_schedule($referral_id, $meeting_id);
        }
        if ($this->lifecycle_policy->allows(MeetingLifecyclePolicy::ACTION_RESCHEDULE, $status)) {
            $urls['reschedule'] = PortalUrls::referral_meeting_schedule($referral_id, $meeting_id);
        }
        if ($this->lifecycle_policy->allows(MeetingLifecyclePolicy::ACTION_COMPLETE, $status)) {
            $urls['complete'] = PortalUrls::referral_meeting_complete($referral_id, $meeting_id);
        }
        if ($this->lifecycle_policy->allows(MeetingLifecyclePolicy::ACTION_CANCEL, $status)) {
            $urls['cancel'] = PortalUrls::referral_meeting_cancel($referral_id, $meeting_id);
        }

        return $urls;
    }

    /**
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    private function get_form_state(int $referral_id, string $form_key, int $meeting_id, bool $consume): array
    {
        $key  = $this->form_transient_key($referral_id, $form_key, $meeting_id);
        $raw  = get_transient($key);
        $data = [];
        $errors = [];

        if (is_array($raw)) {
            $data   = is_array($raw['data'] ?? null) ? $raw['data'] : [];
            $errors = is_array($raw['errors'] ?? null) ? $raw['errors'] : [];
            if ($consume) {
                delete_transient($key);
            }
        }

        return ['data' => $data, 'errors' => $errors];
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $errors
     */
    private function persist_form_state(int $referral_id, string $form_key, int $meeting_id, array $data, array $errors): void
    {
        set_transient(
            $this->form_transient_key($referral_id, $form_key, $meeting_id),
            ['data' => $data, 'errors' => $errors],
            10 * MINUTE_IN_SECONDS
        );
    }

    private function form_transient_key(int $referral_id, string $form_key, int $meeting_id): string
    {
        $user_id = get_current_user_id();

        return self::FORM_TRANSIENT_PREFIX . $user_id . '_' . $referral_id . '_' . $form_key . '_' . $meeting_id;
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function consume_flash_notice(): ?array
    {
        if (! isset($_GET['jmrs_meeting_notice'])) {
            return null;
        }

        $key = sanitize_key((string) wp_unslash($_GET['jmrs_meeting_notice']));
        $map = [
            'created_draft'      => __('Draft meeting created.', 'jm-referral-system'),
            'created_scheduled'  => __('Scheduled meeting created.', 'jm-referral-system'),
            'updated'            => __('Meeting updated.', 'jm-referral-system'),
            'unchanged'          => __('No changes were made.', 'jm-referral-system'),
            'scheduled'          => __('Meeting scheduled.', 'jm-referral-system'),
            'rescheduled'        => __('Meeting rescheduled.', 'jm-referral-system'),
            'completed'          => __('Meeting marked as completed.', 'jm-referral-system'),
            'already_completed'  => __('Meeting was already completed.', 'jm-referral-system'),
            'cancelled'          => __('Meeting cancelled.', 'jm-referral-system'),
            'already_cancelled'  => __('Meeting was already cancelled.', 'jm-referral-system'),
            'attendee_added'     => __('Internal attendee added.', 'jm-referral-system'),
            'attendee_updated'   => __('Internal attendee updated.', 'jm-referral-system'),
            'attendee_unchanged' => __('No changes were made.', 'jm-referral-system'),
            'attendee_removed'   => __('Internal attendee removed.', 'jm-referral-system'),
            'attendee_remove_failed' => __('Unable to remove this attendee. Please try again.', 'jm-referral-system'),
        ];

        if (! isset($map[$key])) {
            return null;
        }

        $type = 'attendee_remove_failed' === $key ? 'error' : 'success';

        return ['type' => $type, 'message' => $map[$key]];
    }

    private function error_message(string $code): string
    {
        return match ($code) {
            'archived' => __('This referral is archived. Meetings cannot be changed.', 'jm-referral-system'),
            'forbidden', 'not_found', 'referral_not_found' => __('Unable to save this meeting.', 'jm-referral-system'),
            'invalid_transition' => __('That action is not allowed for the current meeting status.', 'jm-referral-system'),
            'persist_failed' => __('Unable to save this meeting. Please try again.', 'jm-referral-system'),
            default => __('Please fix the highlighted errors.', 'jm-referral-system'),
        };
    }

    private function attendee_error_message(string $code): string
    {
        return match ($code) {
            'archived' => __('This referral is archived. Attendees cannot be changed.', 'jm-referral-system'),
            'forbidden', 'not_found', 'meeting_not_found', 'referral_not_found' => __('Unable to save this attendee.', 'jm-referral-system'),
            'invalid_transition' => __('That action is not allowed for the current meeting status.', 'jm-referral-system'),
            'persist_failed' => __('Unable to save this attendee. Please try again.', 'jm-referral-system'),
            default => __('Please fix the highlighted errors.', 'jm-referral-system'),
        };
    }

    /**
     * @param array<string, mixed> $referral
     * @return array<int, array{label: string, url: string}>
     */
    private function meetings_breadcrumbs(array $referral, string $meetings_label, ?string $detail_label): array
    {
        $crumbs = $this->clinical_access->referral_breadcrumbs($referral, $meetings_label);
        if (null !== $detail_label && '' !== $detail_label) {
            $referral_id = absint($referral['id'] ?? 0);
            $crumbs[count($crumbs) - 1]['url'] = PortalUrls::referral_meetings($referral_id);
            $crumbs[] = ['label' => $detail_label, 'url' => ''];
        }

        return $crumbs;
    }
}
