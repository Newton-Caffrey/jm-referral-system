<?php

namespace JMReferral\Portal\Homes;

use JMReferral\Homes\HomeService;
use JMReferral\Homes\OccupancyService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\Clinical\PortalViewHost;
use JMReferral\Portal\PortalRouter;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\ReferralRepository;

/**
 * Staff portal occupancy / placement workflows (Phase 2C).
 */
class OccupancyHandler
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_portal_occupancy_form_';

    /** @var array<int, string> */
    private const ROUTES = [
        'occupancy',
        'occupancy_place',
        'occupancy_transfer',
        'occupancy_end',
    ];

    public function __construct(
        private PortalViewHost $view_host,
        private OccupancyService $occupancy_service,
        private HomeService $home_service,
        private ReferralRepository $referral_repository,
        private AccessPolicy $access_policy
    ) {
    }

    public function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public function dispatch(string $route): void
    {
        match ($route) {
            'occupancy'           => $this->render_board(),
            'occupancy_place'     => $this->render_place(),
            'occupancy_transfer'  => $this->render_transfer(absint(get_query_var(PortalRouter::QV_ID))),
            'occupancy_end'       => $this->render_end(absint(get_query_var(PortalRouter::QV_ID))),
            default               => $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404),
        };
    }

    private function render_board(): void
    {
        if (! Capabilities::current_user_can(Capabilities::VIEW_HOMES)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $home_id = absint($_GET['jmrs_home_id'] ?? 0);
        $vacancy = sanitize_key((string) ($_GET['jmrs_vacancy'] ?? 'all'));
        if (! in_array($vacancy, ['all', 'vacant', 'occupied'], true)) {
            $vacancy = 'all';
        }
        $search = sanitize_text_field(wp_unslash((string) ($_GET['jmrs_search'] ?? '')));

        $filters = ['status' => 'active', 'search' => $search];
        $homes   = $this->home_service->list($filters);
        if ($home_id > 0) {
            $homes = array_values(
                array_filter(
                    $homes,
                    static fn (array $row): bool => absint($row['id'] ?? 0) === $home_id
                )
            );
        }

        if ('vacant' === $vacancy) {
            $homes = array_values(
                array_filter(
                    $homes,
                    static fn (array $row): bool => absint($row['vacant'] ?? 0) > 0
                )
            );
        } elseif ('occupied' === $vacancy) {
            $homes = array_values(
                array_filter(
                    $homes,
                    static fn (array $row): bool => absint($row['occupied'] ?? 0) > 0
                )
            );
        }

        foreach ($homes as $index => $home) {
            $id = absint($home['id'] ?? 0);
            $homes[$index]['view_url'] = PortalUrls::home_view($id);
        }

        $summary     = $this->occupancy_service->estate_summary();
        $can_manage  = Capabilities::current_user_can(Capabilities::MANAGE_OCCUPANCIES);
        $home_options = $this->home_service->list(['status' => 'active']);

        $notice = null;
        if (isset($_GET['jmrs_placement_saved'])) {
            $notice = [
                'type'    => 'success',
                'message' => __('Placement saved successfully.', 'jm-referral-system'),
            ];
        } elseif (isset($_GET['jmrs_placement_ended'])) {
            $notice = [
                'type'    => 'success',
                'message' => __('Placement ended successfully.', 'jm-referral-system'),
            ];
        }

        $this->view_host->render_portal_page(
            'homes/occupancy',
            __('Vacancies / Occupancy', 'jm-referral-system'),
            'occupancy',
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
                ['label' => __('Vacancies / Occupancy', 'jm-referral-system'), 'url' => ''],
            ],
            [
                'homes'         => $homes,
                'summary'       => $summary,
                'filters'       => [
                    'home_id' => $home_id,
                    'vacancy' => $vacancy,
                    'search'  => $search,
                ],
                'home_options'  => $home_options,
                'form_action'   => PortalUrls::occupancy(),
                'can_manage'    => $can_manage,
                'list_notice'   => $notice,
            ]
        );
    }

    private function render_place(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_OCCUPANCIES)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $home_id     = absint($_REQUEST['jmrs_home_id'] ?? $_GET['home_id'] ?? 0);
        $bedroom_id  = absint($_REQUEST['jmrs_bedroom_id'] ?? $_GET['bedroom_id'] ?? 0);
        $referral_id = absint($_REQUEST['jmrs_referral_id'] ?? $_GET['referral_id'] ?? 0);

        if (isset($_POST['jmrs_save_placement'])) {
            $this->handle_place_post();

            return;
        }

        $form_state = self::get_form_state('place', 0, true);
        $errors     = $form_state['errors'];
        $data       = array_merge(
            [
                'referral_id'  => (string) $referral_id,
                'home_id'      => (string) $home_id,
                'bedroom_id'   => (string) $bedroom_id,
                'move_in_date' => gmdate('Y-m-d', current_time('timestamp')),
                'notes'        => '',
                'client_search'=> '',
            ],
            $form_state['data']
        );

        $home_id     = absint($data['home_id'] ?? 0);
        $bedroom_id  = absint($data['bedroom_id'] ?? 0);
        $referral_id = absint($data['referral_id'] ?? 0);

        $homes            = $this->home_service->list(['status' => 'active']);
        $vacant_bedrooms  = $home_id > 0 ? $this->occupancy_service->vacant_active_bedrooms($home_id) : [];
        $selected_referral = $referral_id > 0 ? $this->referral_repository->find($referral_id) : null;
        if (null !== $selected_referral && (
            $this->access_policy->is_referral_archived($selected_referral)
            || ! $this->access_policy->can_view_referral($selected_referral)
        )) {
            $selected_referral = null;
            $referral_id       = 0;
            $data['referral_id'] = '0';
        }

        $client_results = [];
        $client_search  = sanitize_text_field((string) ($data['client_search'] ?? ''));
        if ('' !== $client_search && $referral_id <= 0) {
            $client_results = $this->search_placeable_referrals($client_search);
        }

        $this->view_host->render_portal_page(
            'homes/place-form',
            __('Place Resident', 'jm-referral-system'),
            'occupancy',
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
                ['label' => __('Vacancies / Occupancy', 'jm-referral-system'), 'url' => PortalUrls::occupancy()],
                ['label' => __('Place Resident', 'jm-referral-system'), 'url' => ''],
            ],
            [
                'data'              => $data,
                'errors'            => $errors,
                'homes'             => $homes,
                'vacant_bedrooms'   => $vacant_bedrooms,
                'selected_referral' => $selected_referral,
                'client_results'    => $client_results,
                'form_action'       => PortalUrls::occupancy_place(),
                'cancel_url'        => $home_id > 0 ? PortalUrls::home_view($home_id) : PortalUrls::occupancy(),
            ]
        );
    }

    private function handle_place_post(): void
    {
        $nonce = isset($_POST['jmrs_occupancy_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_occupancy_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'jmrs_save_placement')) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $input = [
            'referral_id'  => absint($_POST['jmrs_referral_id'] ?? 0),
            'home_id'      => absint($_POST['jmrs_home_id'] ?? 0),
            'bedroom_id'   => absint($_POST['jmrs_bedroom_id'] ?? 0),
            'move_in_date' => wp_unslash((string) ($_POST['jmrs_move_in_date'] ?? '')),
            'notes'        => wp_unslash((string) ($_POST['jmrs_placement_notes'] ?? '')),
        ];

        // Client picker via search selection.
        if ($input['referral_id'] <= 0 && isset($_POST['jmrs_selected_referral_id'])) {
            $input['referral_id'] = absint($_POST['jmrs_selected_referral_id']);
        }

        // Searching without saving / reload bedrooms.
        if (isset($_POST['jmrs_search_clients']) || isset($_POST['jmrs_reload_bedrooms'])) {
            self::persist_form_state(
                'place',
                0,
                [
                    'referral_id'   => (string) (
                        $input['referral_id'] > 0
                            ? $input['referral_id']
                            : absint($_POST['jmrs_selected_referral_id'] ?? 0)
                    ),
                    'home_id'       => (string) $input['home_id'],
                    'bedroom_id'    => isset($_POST['jmrs_reload_bedrooms']) ? '0' : (string) $input['bedroom_id'],
                    'move_in_date'  => sanitize_text_field((string) $input['move_in_date']),
                    'notes'         => sanitize_textarea_field((string) $input['notes']),
                    'client_search' => sanitize_text_field(wp_unslash((string) ($_POST['jmrs_client_search'] ?? ''))),
                ],
                []
            );
            wp_safe_redirect(PortalUrls::occupancy_place());
            exit;
        }

        $result = $this->occupancy_service->place_resident($input);

        if (false === $result || isset($result['errors'])) {
            self::persist_form_state(
                'place',
                0,
                [
                    'referral_id'   => (string) $input['referral_id'],
                    'home_id'       => (string) $input['home_id'],
                    'bedroom_id'    => (string) $input['bedroom_id'],
                    'move_in_date'  => sanitize_text_field((string) $input['move_in_date']),
                    'notes'         => sanitize_textarea_field((string) $input['notes']),
                    'client_search' => sanitize_text_field(wp_unslash((string) ($_POST['jmrs_client_search'] ?? ''))),
                ],
                is_array($result) ? ($result['errors'] ?? ['general' => __('Unable to save placement.', 'jm-referral-system')]) : ['general' => __('Unable to save placement.', 'jm-referral-system')]
            );
            wp_safe_redirect(PortalUrls::occupancy_place());
            exit;
        }

        $referral_id = absint($input['referral_id']);
        wp_safe_redirect(
            add_query_arg('jmrs_placement_saved', '1', PortalUrls::referral($referral_id))
        );
        exit;
    }

    private function render_transfer(int $occupancy_id): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_OCCUPANCIES)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $current = $this->occupancy_service->find($occupancy_id);
        if (null === $current || 'active' !== ($current['status'] ?? '')) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $referral = $this->referral_repository->find(absint($current['referral_id'] ?? 0));
        if (null === $referral || ! $this->access_policy->can_view_referral($referral)) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (isset($_POST['jmrs_save_transfer'])) {
            $this->handle_transfer_post($occupancy_id);

            return;
        }

        $enriched = $this->occupancy_service->enrich_rows([$current])[0] ?? $current;
        $form_state = self::get_form_state('transfer', $occupancy_id, true);
        $errors     = $form_state['errors'];
        $data       = array_merge(
            [
                'new_home_id'    => '0',
                'new_bedroom_id' => '0',
                'transfer_date'  => gmdate('Y-m-d', current_time('timestamp')),
                'end_reason'     => '',
                'notes'          => '',
            ],
            $form_state['data']
        );

        $new_home_id = absint($data['new_home_id'] ?? 0);
        $homes       = $this->home_service->list(['status' => 'active']);
        $vacant      = $new_home_id > 0
            ? $this->occupancy_service->vacant_active_bedrooms($new_home_id, absint($current['bedroom_id'] ?? 0))
            : [];

        $this->view_host->render_portal_page(
            'homes/transfer-form',
            __('Transfer Resident', 'jm-referral-system'),
            'occupancy',
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
                ['label' => $this->occupancy_service->client_display_name($referral), 'url' => PortalUrls::referral(absint($referral['id'] ?? 0))],
                ['label' => __('Transfer', 'jm-referral-system'), 'url' => ''],
            ],
            [
                'occupancy_id' => $occupancy_id,
                'current'      => $enriched,
                'referral'     => $referral,
                'data'         => $data,
                'errors'       => $errors,
                'homes'        => $homes,
                'vacant_bedrooms' => $vacant,
                'form_action'  => PortalUrls::occupancy_transfer($occupancy_id),
                'cancel_url'   => PortalUrls::referral(absint($referral['id'] ?? 0)),
            ]
        );
    }

    private function handle_transfer_post(int $occupancy_id): void
    {
        $nonce = isset($_POST['jmrs_occupancy_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_occupancy_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'jmrs_save_transfer_' . $occupancy_id)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        // Home change without full save reloads vacant bedrooms.
        if (isset($_POST['jmrs_reload_bedrooms'])) {
            self::persist_form_state(
                'transfer',
                $occupancy_id,
                [
                    'new_home_id'    => (string) absint($_POST['jmrs_new_home_id'] ?? 0),
                    'new_bedroom_id' => '0',
                    'transfer_date'  => sanitize_text_field(wp_unslash((string) ($_POST['jmrs_transfer_date'] ?? ''))),
                    'end_reason'     => sanitize_text_field(wp_unslash((string) ($_POST['jmrs_end_reason'] ?? ''))),
                    'notes'          => sanitize_textarea_field(wp_unslash((string) ($_POST['jmrs_notes'] ?? ''))),
                ],
                []
            );
            wp_safe_redirect(PortalUrls::occupancy_transfer($occupancy_id));
            exit;
        }

        $input = [
            'new_home_id'    => absint($_POST['jmrs_new_home_id'] ?? 0),
            'new_bedroom_id' => absint($_POST['jmrs_new_bedroom_id'] ?? 0),
            'transfer_date'  => wp_unslash((string) ($_POST['jmrs_transfer_date'] ?? '')),
            'end_reason'     => wp_unslash((string) ($_POST['jmrs_end_reason'] ?? '')),
            'notes'          => wp_unslash((string) ($_POST['jmrs_notes'] ?? '')),
        ];

        $result = $this->occupancy_service->transfer_resident($occupancy_id, $input);

        if (false === $result || isset($result['errors'])) {
            self::persist_form_state(
                'transfer',
                $occupancy_id,
                [
                    'new_home_id'    => (string) $input['new_home_id'],
                    'new_bedroom_id' => (string) $input['new_bedroom_id'],
                    'transfer_date'  => sanitize_text_field((string) $input['transfer_date']),
                    'end_reason'     => sanitize_text_field((string) $input['end_reason']),
                    'notes'          => sanitize_textarea_field((string) $input['notes']),
                ],
                is_array($result) ? ($result['errors'] ?? ['general' => __('Unable to transfer placement.', 'jm-referral-system')]) : ['general' => __('Unable to transfer placement.', 'jm-referral-system')]
            );
            wp_safe_redirect(PortalUrls::occupancy_transfer($occupancy_id));
            exit;
        }

        $current = $this->occupancy_service->find(absint($result['id'] ?? 0));
        $referral_id = absint($current['referral_id'] ?? 0);
        wp_safe_redirect(
            add_query_arg('jmrs_placement_saved', '1', PortalUrls::referral($referral_id))
        );
        exit;
    }

    private function render_end(int $occupancy_id): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_OCCUPANCIES)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $current = $this->occupancy_service->find($occupancy_id);
        if (null === $current || 'active' !== ($current['status'] ?? '')) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        $referral = $this->referral_repository->find(absint($current['referral_id'] ?? 0));
        if (null === $referral || ! $this->access_policy->can_view_referral($referral)) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if (isset($_POST['jmrs_save_end_placement'])) {
            $this->handle_end_post($occupancy_id);

            return;
        }

        $enriched   = $this->occupancy_service->enrich_rows([$current])[0] ?? $current;
        $form_state = self::get_form_state('end', $occupancy_id, true);
        $errors     = $form_state['errors'];
        $data       = array_merge(
            [
                'move_out_date' => gmdate('Y-m-d', current_time('timestamp')),
                'end_reason'    => '',
                'notes'         => '',
            ],
            $form_state['data']
        );

        $this->view_host->render_portal_page(
            'homes/end-form',
            __('End Supported Living Placement', 'jm-referral-system'),
            'occupancy',
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
                ['label' => $this->occupancy_service->client_display_name($referral), 'url' => PortalUrls::referral(absint($referral['id'] ?? 0))],
                ['label' => __('End Placement', 'jm-referral-system'), 'url' => ''],
            ],
            [
                'occupancy_id' => $occupancy_id,
                'current'      => $enriched,
                'referral'     => $referral,
                'data'         => $data,
                'errors'       => $errors,
                'form_action'  => PortalUrls::occupancy_end($occupancy_id),
                'cancel_url'   => PortalUrls::referral(absint($referral['id'] ?? 0)),
            ]
        );
    }

    private function handle_end_post(int $occupancy_id): void
    {
        $nonce = isset($_POST['jmrs_occupancy_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['jmrs_occupancy_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'jmrs_save_end_placement_' . $occupancy_id)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        if (empty($_POST['jmrs_confirm_end'])) {
            self::persist_form_state(
                'end',
                $occupancy_id,
                [
                    'move_out_date' => sanitize_text_field(wp_unslash((string) ($_POST['jmrs_move_out_date'] ?? ''))),
                    'end_reason'    => sanitize_text_field(wp_unslash((string) ($_POST['jmrs_end_reason'] ?? ''))),
                    'notes'         => sanitize_textarea_field(wp_unslash((string) ($_POST['jmrs_notes'] ?? ''))),
                ],
                ['confirm' => __('Please confirm that you want to end this placement.', 'jm-referral-system')]
            );
            wp_safe_redirect(PortalUrls::occupancy_end($occupancy_id));
            exit;
        }

        $current = $this->occupancy_service->find($occupancy_id);
        $referral_id = absint($current['referral_id'] ?? 0);

        $result = $this->occupancy_service->end_occupancy(
            $occupancy_id,
            [
                'move_out_date' => wp_unslash((string) ($_POST['jmrs_move_out_date'] ?? '')),
                'end_reason'    => wp_unslash((string) ($_POST['jmrs_end_reason'] ?? '')),
                'notes'         => wp_unslash((string) ($_POST['jmrs_notes'] ?? '')),
            ]
        );

        if (false === $result || isset($result['errors'])) {
            self::persist_form_state(
                'end',
                $occupancy_id,
                [
                    'move_out_date' => sanitize_text_field(wp_unslash((string) ($_POST['jmrs_move_out_date'] ?? ''))),
                    'end_reason'    => sanitize_text_field(wp_unslash((string) ($_POST['jmrs_end_reason'] ?? ''))),
                    'notes'         => sanitize_textarea_field(wp_unslash((string) ($_POST['jmrs_notes'] ?? ''))),
                ],
                is_array($result) ? ($result['errors'] ?? ['general' => __('Unable to end placement.', 'jm-referral-system')]) : ['general' => __('Unable to end placement.', 'jm-referral-system')]
            );
            wp_safe_redirect(PortalUrls::occupancy_end($occupancy_id));
            exit;
        }

        wp_safe_redirect(
            add_query_arg('jmrs_placement_ended', '1', PortalUrls::referral($referral_id))
        );
        exit;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function search_placeable_referrals(string $search): array
    {
        $assigned = $this->access_policy->get_assigned_user_constraint();
        $query    = $this->referral_repository->query(
            [
                'search'        => $search,
                'archive_scope' => 'active',
            ],
            20,
            1,
            $assigned
        );
        $rows = is_array($query['items'] ?? null) ? $query['items'] : [];

        $results = [];
        foreach ($rows as $row) {
            $id = absint($row['id'] ?? 0);
            if ($id <= 0 || ! $this->access_policy->can_mutate_referral($row)) {
                continue;
            }
            if (null !== $this->occupancy_service->current_for_referral($id)) {
                continue;
            }
            if (\JMReferral\Referral\CareSetting::is_own_home($row['care_setting'] ?? null)) {
                continue;
            }
            $results[] = [
                'id'              => $id,
                'referral_number' => (string) ($row['referral_number'] ?? ''),
                'client_name'     => $this->occupancy_service->client_display_name($row),
            ];
        }

        return $results;
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $errors
     */
    private static function persist_form_state(string $action, int $id, array $data, array $errors): void
    {
        set_transient(
            self::FORM_TRANSIENT_PREFIX . $action . '_' . get_current_user_id() . '_' . $id,
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
    private static function get_form_state(string $action, int $id, bool $consume = true): array
    {
        $key   = self::FORM_TRANSIENT_PREFIX . $action . '_' . get_current_user_id() . '_' . $id;
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
