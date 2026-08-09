<?php

namespace JMReferral\Portal\Homes;

use JMReferral\Homes\BedroomService;
use JMReferral\Homes\HomeDashboardService;
use JMReferral\Homes\HomeService;
use JMReferral\Homes\OccupancyService;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\Clinical\PortalViewHost;
use JMReferral\Portal\PortalRouter;
use JMReferral\Portal\PortalUrls;
use JMReferral\Users\UserProvider;

/**
 * Staff portal Supported Living homes & bedrooms (Phase 2B/2E).
 *
 * Mutations go through shared HomeService / BedroomService only.
 * Hard-delete is not exposed; inactive status is the archive path.
 */
class HomesHandler
{
    private const HOME_FORM_TRANSIENT_PREFIX = 'jmrs_portal_home_form_';
    private const BEDROOM_FORM_TRANSIENT_PREFIX = 'jmrs_portal_bedroom_form_';

    /** @var array<int, string> */
    private const ROUTES = [
        'homes',
        'home',
        'home_new',
        'home_edit',
        'bedroom_new',
        'bedroom_edit',
    ];

    public function __construct(
        private PortalViewHost $view_host,
        private HomeService $home_service,
        private BedroomService $bedroom_service,
        private UserProvider $user_provider,
        private ?OccupancyService $occupancy_service = null,
        private ?HomeDashboardService $dashboard_service = null
    ) {
    }

    public function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public function dispatch(string $route): void
    {
        match ($route) {
            'homes'        => $this->render_list(),
            'home'         => $this->render_view(),
            'home_new'     => $this->render_home_form(0),
            'home_edit'    => $this->render_home_form(absint(get_query_var(PortalRouter::QV_ID))),
            'bedroom_new'  => $this->render_bedroom_form(
                absint(get_query_var(PortalRouter::QV_ID)),
                0
            ),
            'bedroom_edit' => $this->render_bedroom_form(
                absint(get_query_var(PortalRouter::QV_ID)),
                absint(get_query_var(PortalRouter::QV_ENTITY))
            ),
            default        => $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404),
        };
    }

    private function render_list(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_HOMES)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $can_manage = Capabilities::current_user_can(Capabilities::MANAGE_HOMES);
        $status     = sanitize_key((string) ($_GET['jmrs_home_status'] ?? 'active'));
        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }
        $search = sanitize_text_field(wp_unslash((string) ($_GET['jmrs_search'] ?? '')));

        $filters = [
            'search' => $search,
        ];
        if ('all' !== $status) {
            $filters['status'] = $status;
        }

        $homes = $this->home_service->list($filters);
        foreach ($homes as $index => $home) {
            $home_id = absint($home['id'] ?? 0);
            $homes[$index]['view_url'] = PortalUrls::home_view($home_id);
            $homes[$index]['edit_url'] = $can_manage ? PortalUrls::home_edit($home_id) : '';
            $homes[$index]['location'] = $this->format_location($home);
            $homes[$index]['status_label'] = HomeService::status_labels()[$home['status'] ?? '']
                ?? ucfirst((string) ($home['status'] ?? ''));
        }

        $list_notice = null;
        if (isset($_GET['jmrs_home_saved'])) {
            $list_notice = [
                'type'    => 'success',
                'message' => __('Home saved successfully.', 'jm-referral-system'),
            ];
        }

        $view = [
            'homes'           => $homes,
            'filters'         => [
                'status' => $status,
                'search' => $search,
            ],
            'status_options'  => [
                'active'   => __('Active', 'jm-referral-system'),
                'inactive' => __('Inactive', 'jm-referral-system'),
                'all'      => __('All', 'jm-referral-system'),
            ],
            'can_manage'      => $can_manage,
            'form_action'     => PortalUrls::homes(),
            'new_url'         => $can_manage ? PortalUrls::home_new() : '',
            'list_notice'     => $list_notice,
            'status_labels'   => HomeService::status_labels(),
        ];

        $this->view_host->render_portal_page(
            'homes/list',
            __('Homes', 'jm-referral-system'),
            'homes',
            $this->homes_breadcrumbs(),
            $view
        );
    }

    private function render_view(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_HOMES)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $home_id = absint(get_query_var(PortalRouter::QV_ID));
        $home    = $this->home_service->find($home_id);
        if (null === $home) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $notice = null;
        if (isset($_GET['jmrs_home_saved'])) {
            $notice = [
                'type'    => 'success',
                'message' => __('Home saved successfully.', 'jm-referral-system'),
            ];
        } elseif (isset($_GET['jmrs_bedroom_saved'])) {
            $notice = [
                'type'    => 'success',
                'message' => __('Bedroom saved successfully.', 'jm-referral-system'),
            ];
        } elseif (isset($_GET['jmrs_placement_saved'])) {
            $notice = [
                'type'    => 'success',
                'message' => __('Supported Living placement saved successfully.', 'jm-referral-system'),
            ];
        } elseif (isset($_GET['jmrs_placement_ended'])) {
            $notice = [
                'type'    => 'success',
                'message' => __('Supported Living placement ended successfully.', 'jm-referral-system'),
            ];
        }

        $home_name = (string) ($home['name'] ?? '');
        $location  = $this->format_location($home);
        $status_label = HomeService::status_labels()[$home['status'] ?? '']
            ?? ucfirst((string) ($home['status'] ?? ''));

        if (null !== $this->dashboard_service) {
            $dashboard = $this->dashboard_service->build($home);
            $view      = array_merge(
                [
                    'home'         => $home,
                    'home_name'    => $home_name,
                    'location'     => $location,
                    'status_label' => $status_label,
                    'notice'       => $notice,
                ],
                $dashboard
            );
        } else {
            // Fallback if dashboard service is unavailable (should not happen in normal bootstrap).
            $can_manage = Capabilities::current_user_can(Capabilities::MANAGE_HOMES);
            $can_place  = Capabilities::current_user_can(Capabilities::MANAGE_OCCUPANCIES);
            $capacity   = $this->home_service->capacity($home_id);
            $occupied   = null !== $this->occupancy_service
                ? $this->occupancy_service->count_active_for_home($home_id)
                : 0;
            $metrics    = OccupancyService::compute_metrics($capacity, $occupied);
            $manager_id = absint($home['manager_user_id'] ?? 0);
            $view       = [
                'home'              => $home,
                'home_name'         => $home_name,
                'location'          => $location,
                'manager_name'      => $manager_id > 0 ? $this->user_provider->get_display_name($manager_id) : '',
                'status_label'      => $status_label,
                'capacity'          => $metrics['capacity'],
                'occupied'          => $metrics['occupied'],
                'vacant'            => $metrics['vacant'],
                'occupancy_pct'     => $metrics['occupancy_pct'],
                'bedrooms'          => [],
                'residents'         => [],
                'upcoming_visits'   => [],
                'attention'         => ['items' => [], 'total' => 0],
                'can_manage'        => $can_manage,
                'can_place'         => $can_place,
                'can_view_visits'   => false,
                'home_is_active'    => 'active' === ($home['status'] ?? ''),
                'edit_url'          => $can_manage ? PortalUrls::home_edit($home_id) : '',
                'add_bedroom_url'   => ($can_manage && 'active' === ($home['status'] ?? ''))
                    ? PortalUrls::bedroom_new($home_id)
                    : '',
                'place_url'         => '',
                'vacancies_url'     => PortalUrls::occupancy(),
                'list_url'          => PortalUrls::homes(),
                'notice'            => $notice,
            ];
        }

        $this->view_host->render_portal_page(
            'homes/view',
            $home_name !== '' ? $home_name : __('Home', 'jm-referral-system'),
            'homes',
            $this->homes_breadcrumbs($home_name),
            $view
        );
    }

    private function render_home_form(int $home_id): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_HOMES)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $home = null;
        if ($home_id > 0) {
            $home = $this->home_service->find($home_id);
            if (null === $home) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }
        }

        if (isset($_POST['jmrs_save_home'])) {
            $this->handle_home_post($home_id);

            return;
        }

        $form_state = self::get_home_form_state($home_id, true);
        $errors     = $form_state['errors'];
        $data       = (! empty($form_state['data']))
            ? array_merge(HomeService::empty_form_data(), $form_state['data'])
            : HomeService::map_to_form_data($home);

        $page_title  = null === $home
            ? __('Add Home', 'jm-referral-system')
            : __('Edit Home', 'jm-referral-system');
        $form_action = $home_id > 0 ? PortalUrls::home_edit($home_id) : PortalUrls::home_new();
        $cancel_url  = $home_id > 0 ? PortalUrls::home_view($home_id) : PortalUrls::homes();
        $home_name   = (string) ($home['name'] ?? '');

        $view = [
            'home_id'           => $home_id,
            'data'              => $data,
            'errors'            => $errors,
            'status_labels'     => HomeService::status_labels(),
            'manager_options'   => $this->user_provider->get_assignable_users(),
            'form_action'       => $form_action,
            'cancel_url'        => $cancel_url,
            'is_create'         => null === $home,
        ];

        $crumbs = $this->homes_breadcrumbs(
            $home_id > 0 ? $home_name : '',
            $page_title,
            $home_id > 0 ? PortalUrls::home_view($home_id) : ''
        );

        $this->view_host->render_portal_page(
            'homes/form',
            $page_title,
            'homes',
            $crumbs,
            $view
        );
    }

    private function handle_home_post(int $home_id): void
    {
        $nonce = isset($_POST['jmrs_home_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_home_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'jmrs_save_home_' . $home_id)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $posted_id = isset($_POST['jmrs_home_id']) ? absint($_POST['jmrs_home_id']) : 0;
        if ($posted_id !== $home_id) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $input = [
            'name'            => wp_unslash((string) ($_POST['jmrs_home_name'] ?? '')),
            'address_line_1'  => wp_unslash((string) ($_POST['jmrs_home_address_line_1'] ?? '')),
            'address_line_2'  => wp_unslash((string) ($_POST['jmrs_home_address_line_2'] ?? '')),
            'city'            => wp_unslash((string) ($_POST['jmrs_home_city'] ?? '')),
            'postcode'        => wp_unslash((string) ($_POST['jmrs_home_postcode'] ?? '')),
            'phone'           => wp_unslash((string) ($_POST['jmrs_home_phone'] ?? '')),
            'manager_user_id' => absint($_POST['jmrs_home_manager_user_id'] ?? 0),
            'status'          => wp_unslash((string) ($_POST['jmrs_home_status'] ?? 'active')),
            'notes'           => wp_unslash((string) ($_POST['jmrs_home_notes'] ?? '')),
        ];

        $result = $home_id > 0
            ? $this->home_service->update($home_id, $input)
            : $this->home_service->create($input);

        $redirect = $home_id > 0 ? PortalUrls::home_edit($home_id) : PortalUrls::home_new();

        if (false === $result) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- technical failure only.
                error_log('JMRS: failed to save supported living home (id=' . $home_id . ').');
            }
            self::persist_home_form_state(
                $home_id,
                HomeService::map_to_form_data($this->home_service->sanitize_input($input)),
                ['general' => __('Unable to save the home. Please try again.', 'jm-referral-system')]
            );
            wp_safe_redirect($redirect);
            exit;
        }

        if (isset($result['errors'])) {
            self::persist_home_form_state(
                $home_id,
                HomeService::map_to_form_data($this->home_service->sanitize_input($input)),
                $result['errors']
            );
            wp_safe_redirect($redirect);
            exit;
        }

        $saved_id = $home_id > 0 ? $home_id : absint($result['id'] ?? 0);
        wp_safe_redirect(
            add_query_arg('jmrs_home_saved', '1', PortalUrls::home_view($saved_id))
        );
        exit;
    }

    private function render_bedroom_form(int $home_id, int $bedroom_id): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_HOMES)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $home = $this->home_service->find($home_id);
        if (null === $home) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $bedroom = null;
        if ($bedroom_id > 0) {
            $bedroom = $this->bedroom_service->find($bedroom_id);
            if (null === $bedroom || ! $this->bedroom_service->belongs_to_home($bedroom_id, $home_id)) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }
        } elseif ('inactive' === ($home['status'] ?? '')) {
            $this->view_host->render_portal_error(
                '403',
                __('Cannot Add Bedroom', 'jm-referral-system'),
                403
            );

            return;
        }

        if (isset($_POST['jmrs_save_bedroom'])) {
            $this->handle_bedroom_post($home_id, $bedroom_id);

            return;
        }

        $form_state = self::get_bedroom_form_state($home_id, $bedroom_id, true);
        $errors     = $form_state['errors'];
        $data       = (! empty($form_state['data']))
            ? array_merge(BedroomService::empty_form_data(), $form_state['data'])
            : BedroomService::map_to_form_data($bedroom);

        $page_title  = null === $bedroom
            ? __('Add Bedroom', 'jm-referral-system')
            : __('Edit Bedroom', 'jm-referral-system');
        $form_action = $bedroom_id > 0
            ? PortalUrls::bedroom_edit($home_id, $bedroom_id)
            : PortalUrls::bedroom_new($home_id);
        $home_name   = (string) ($home['name'] ?? '');

        $view = [
            'home'          => $home,
            'home_id'       => $home_id,
            'home_name'     => $home_name,
            'bedroom_id'    => $bedroom_id,
            'data'          => $data,
            'errors'        => $errors,
            'status_labels' => BedroomService::status_labels(),
            'form_action'   => $form_action,
            'cancel_url'    => PortalUrls::home_view($home_id),
            'is_create'     => null === $bedroom,
            'home_is_active'=> 'active' === ($home['status'] ?? ''),
        ];

        $this->view_host->render_portal_page(
            'homes/bedroom-form',
            $page_title,
            'homes',
            $this->homes_breadcrumbs($home_name, $page_title, PortalUrls::home_view($home_id)),
            $view
        );
    }

    private function handle_bedroom_post(int $home_id, int $bedroom_id): void
    {
        $nonce = isset($_POST['jmrs_bedroom_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_bedroom_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'jmrs_save_bedroom_' . $home_id . '_' . $bedroom_id)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $posted_home    = isset($_POST['jmrs_home_id']) ? absint($_POST['jmrs_home_id']) : 0;
        $posted_bedroom = isset($_POST['jmrs_bedroom_id']) ? absint($_POST['jmrs_bedroom_id']) : 0;
        if ($posted_home !== $home_id || $posted_bedroom !== $bedroom_id) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $input = [
            'room_label' => wp_unslash((string) ($_POST['jmrs_bedroom_room_label'] ?? '')),
            'floor'      => wp_unslash((string) ($_POST['jmrs_bedroom_floor'] ?? '')),
            'status'     => wp_unslash((string) ($_POST['jmrs_bedroom_status'] ?? 'active')),
            'notes'      => wp_unslash((string) ($_POST['jmrs_bedroom_notes'] ?? '')),
        ];

        $result = $bedroom_id > 0
            ? $this->bedroom_service->update($bedroom_id, $input)
            : $this->bedroom_service->create($home_id, $input);

        $redirect = $bedroom_id > 0
            ? PortalUrls::bedroom_edit($home_id, $bedroom_id)
            : PortalUrls::bedroom_new($home_id);

        if (false === $result) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- technical failure only.
                error_log('JMRS: failed to save bedroom (home_id=' . $home_id . ', bedroom_id=' . $bedroom_id . ').');
            }
            self::persist_bedroom_form_state(
                $home_id,
                $bedroom_id,
                BedroomService::map_to_form_data($this->bedroom_service->sanitize_input($input)),
                ['general' => __('Unable to save the bedroom. Please try again.', 'jm-referral-system')]
            );
            wp_safe_redirect($redirect);
            exit;
        }

        if (isset($result['errors'])) {
            self::persist_bedroom_form_state(
                $home_id,
                $bedroom_id,
                BedroomService::map_to_form_data($this->bedroom_service->sanitize_input($input)),
                $result['errors']
            );
            wp_safe_redirect($redirect);
            exit;
        }

        wp_safe_redirect(
            add_query_arg('jmrs_bedroom_saved', '1', PortalUrls::home_view($home_id))
        );
        exit;
    }

    /**
     * @param array<string, mixed> $home
     */
    private function format_location(array $home): string
    {
        $parts = array_filter(
            [
                trim((string) ($home['address_line_1'] ?? '')),
                trim((string) ($home['address_line_2'] ?? '')),
                trim((string) ($home['city'] ?? '')),
                trim((string) ($home['postcode'] ?? '')),
            ]
        );

        return implode(', ', $parts);
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function homes_breadcrumbs(
        string $home_label = '',
        string $trail_label = '',
        string $home_url = ''
    ): array {
        $crumbs = [
            ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
            ['label' => __('Homes', 'jm-referral-system'), 'url' => PortalUrls::homes()],
        ];

        if ('' !== $home_label) {
            $crumbs[] = [
                'label' => $home_label,
                'url'   => '' !== $home_url ? $home_url : '',
            ];
        }

        if ('' !== $trail_label) {
            $crumbs[] = ['label' => $trail_label, 'url' => ''];
        }

        return $crumbs;
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $errors
     */
    private static function persist_home_form_state(int $home_id, array $data, array $errors): void
    {
        set_transient(
            self::HOME_FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $home_id,
            [
                'data'   => $data,
                'errors' => $errors,
            ],
            5 * MINUTE_IN_SECONDS
        );
    }

    /**
     * @return array{data: array<string, string>, errors: array<string, string>}
     */
    private static function get_home_form_state(int $home_id, bool $consume = true): array
    {
        $key   = self::HOME_FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $home_id;
        $state = get_transient($key);
        if ($consume) {
            delete_transient($key);
        }

        if (! is_array($state)) {
            return ['data' => [], 'errors' => []];
        }

        return [
            'data'   => is_array($state['data'] ?? null) ? $state['data'] : [],
            'errors' => is_array($state['errors'] ?? null) ? $state['errors'] : [],
        ];
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $errors
     */
    private static function persist_bedroom_form_state(
        int $home_id,
        int $bedroom_id,
        array $data,
        array $errors
    ): void {
        set_transient(
            self::BEDROOM_FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $home_id . '_' . $bedroom_id,
            [
                'data'   => $data,
                'errors' => $errors,
            ],
            5 * MINUTE_IN_SECONDS
        );
    }

    /**
     * @return array{data: array<string, string>, errors: array<string, string>}
     */
    private static function get_bedroom_form_state(int $home_id, int $bedroom_id, bool $consume = true): array
    {
        $key   = self::BEDROOM_FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $home_id . '_' . $bedroom_id;
        $state = get_transient($key);
        if ($consume) {
            delete_transient($key);
        }

        if (! is_array($state)) {
            return ['data' => [], 'errors' => []];
        }

        return [
            'data'   => is_array($state['data'] ?? null) ? $state['data'] : [],
            'errors' => is_array($state['errors'] ?? null) ? $state['errors'] : [],
        ];
    }
}
