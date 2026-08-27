<?php

namespace JMReferral\Portal\Responsibilities;

use JMReferral\Meeting\ReferralResponsibilityService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Portal\Clinical\ClinicalAccess;
use JMReferral\Portal\Clinical\PortalViewHost;
use JMReferral\Portal\PortalRouter;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\ReferralRetentionService;
use JMReferral\Users\UserProvider;

/**
 * Staff Portal referral responsibility management (Phase 4C.1).
 */
class ResponsibilitiesHandler
{
    private const ROUTE = 'referral_responsibilities_edit';

    private const FORM_TRANSIENT_PREFIX = 'jmrs_responsibilities_form_';

    public function __construct(
        private PortalViewHost $view_host,
        private ClinicalAccess $clinical_access,
        private AccessPolicy $access_policy,
        private ReferralResponsibilityService $responsibility_service,
        private UserProvider $user_provider,
        private ReferralRetentionService $retention_service
    ) {
    }

    public function handles(string $route): bool
    {
        return self::ROUTE === $route;
    }

    public function dispatch(string $route): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (! in_array($method, ['GET', 'POST'], true)) {
            $this->view_host->render_portal_error('405', __('Method Not Allowed', 'jm-referral-system'), 405);

            return;
        }

        $referral_id = absint(get_query_var(PortalRouter::QV_ID));
        if ($referral_id <= 0) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return;
        }

        if ('POST' === $method) {
            $this->post_save($referral_id);

            return;
        }

        $this->render_form($referral_id);
    }

    private function render_form(int $referral_id): void
    {
        $ctx = $this->require_manage($referral_id);
        if (null === $ctx) {
            return;
        }

        $referral = $ctx['referral'];
        $state    = $this->get_form_state($referral_id);
        $data     = is_array($state['data'] ?? null) && [] !== $state['data']
            ? $state['data']
            : $this->current_form_data($referral);
        $errors   = is_array($state['errors'] ?? null) ? $state['errors'] : [];

        $this->clear_form_state($referral_id);

        $page_title = __('Manage responsibilities', 'jm-referral-system');
        $this->view_host->render_portal_page(
            'referrals/responsibilities-form',
            $page_title,
            self::ROUTE,
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
                ['label' => __('Referrals', 'jm-referral-system'), 'url' => PortalUrls::referrals()],
                [
                    'label' => (string) ($referral['referral_number'] ?? __('Referral', 'jm-referral-system')),
                    'url'   => PortalUrls::referral($referral_id),
                ],
                ['label' => $page_title, 'url' => ''],
            ],
            [
                'referral'         => $referral,
                'data'             => $data,
                'errors'           => $errors,
                'staff_options'    => $this->staff_options(),
                'form_action'      => PortalUrls::referral_responsibilities_edit($referral_id),
                'cancel_url'       => PortalUrls::referral($referral_id),
                'flash_notice'     => $this->flash_from_query(),
            ]
        );
    }

    private function post_save(int $referral_id): void
    {
        if (! $this->verify_nonce($referral_id)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        $ctx = $this->require_manage($referral_id);
        if (null === $ctx) {
            return;
        }

        $input  = $this->posted_fields();
        $result = $this->responsibility_service->update_responsibilities($referral_id, $input);

        if (empty($result['ok'])) {
            if (in_array((string) ($result['error'] ?? ''), ['not_found', 'referral_not_found', 'forbidden', 'archived'], true)) {
                $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

                return;
            }
            $this->persist_form_state($referral_id, $input, $result['field_errors'] ?? [
                'form' => $this->error_message((string) ($result['error'] ?? 'validation')),
            ]);
            wp_safe_redirect(PortalUrls::referral_responsibilities_edit($referral_id));
            exit;
        }

        $notice = empty($result['changed']) ? 'unchanged' : 'updated';
        wp_safe_redirect(add_query_arg(
            'jmrs_resp_notice',
            $notice,
            PortalUrls::referral($referral_id)
        ));
        exit;
    }

    /**
     * @return array{referral: array<string, mixed>}|null
     */
    private function require_manage(int $referral_id): ?array
    {
        $access = $this->clinical_access->require_referral($referral_id, false, false);
        if (! $access['ok']) {
            $this->view_host->render_portal_error((string) $access['status'], $access['title'], $access['status']);

            return null;
        }

        $referral = $access['referral'];
        if ($this->retention_service->is_archived($referral)
            || ! $this->access_policy->can_assign_referral_responsibilities($referral)
        ) {
            $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404);

            return null;
        }

        return ['referral' => $referral];
    }

    private function verify_nonce(int $referral_id): bool
    {
        $nonce = isset($_POST['jmrs_responsibilities_nonce'])
            ? (string) wp_unslash($_POST['jmrs_responsibilities_nonce'])
            : '';
        if ('' === $nonce || ! wp_verify_nonce($nonce, 'jmrs_save_responsibilities_' . $referral_id)) {
            return false;
        }

        $posted_referral = isset($_POST['jmrs_referral_id']) ? absint(wp_unslash($_POST['jmrs_referral_id'])) : 0;

        return $posted_referral === $referral_id;
    }

    /**
     * @return array{assigned_to: int|null, champion_user_id: int|null, transition_lead_user_id: int|null}
     */
    private function posted_fields(): array
    {
        return [
            'assigned_to'             => $this->posted_user_id('jmrs_resp_assigned_to'),
            'champion_user_id'        => $this->posted_user_id('jmrs_resp_champion_user_id'),
            'transition_lead_user_id' => $this->posted_user_id('jmrs_resp_transition_lead_user_id'),
        ];
    }

    private function posted_user_id(string $key): ?int
    {
        if (! isset($_POST[$key])) {
            return null;
        }
        $raw = absint(wp_unslash($_POST[$key]));

        return $raw > 0 ? $raw : null;
    }

    /**
     * @param array<string, mixed> $referral
     * @return array{assigned_to: int, champion_user_id: int, transition_lead_user_id: int}
     */
    private function current_form_data(array $referral): array
    {
        return [
            'assigned_to'             => absint($referral['assigned_to'] ?? 0),
            'champion_user_id'        => absint($referral['champion_user_id'] ?? 0),
            'transition_lead_user_id' => absint($referral['transition_lead_user_id'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function staff_options(): array
    {
        $options = [];
        foreach ($this->user_provider->get_assignable_users() as $user) {
            $id = absint($user['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $label = (string) ($user['display_name'] ?? '');
            $options[] = [
                'id'    => $id,
                'label' => '' !== $label ? $label : sprintf(/* translators: %d: user id */ __('User #%d', 'jm-referral-system'), $id),
            ];
        }

        return $options;
    }

    /**
     * @return array{data?: array<string, mixed>, errors?: array<string, string>}
     */
    private function get_form_state(int $referral_id): array
    {
        $raw = get_transient($this->form_transient_key($referral_id));
        if (! is_array($raw)) {
            return [];
        }

        return $raw;
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $errors
     */
    private function persist_form_state(int $referral_id, array $data, array $errors): void
    {
        set_transient(
            $this->form_transient_key($referral_id),
            [
                'data' => [
                    'assigned_to'             => absint($data['assigned_to'] ?? 0),
                    'champion_user_id'        => absint($data['champion_user_id'] ?? 0),
                    'transition_lead_user_id' => absint($data['transition_lead_user_id'] ?? 0),
                ],
                'errors' => $errors,
            ],
            10 * MINUTE_IN_SECONDS
        );
    }

    private function clear_form_state(int $referral_id): void
    {
        delete_transient($this->form_transient_key($referral_id));
    }

    private function form_transient_key(int $referral_id): string
    {
        return self::FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $referral_id;
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function flash_from_query(): ?array
    {
        // Form page does not consume referral-view flash; reserved for symmetry.
        return null;
    }

    private function error_message(string $code): string
    {
        return match ($code) {
            'invalid_user', 'validation' => __('Please correct the highlighted fields.', 'jm-referral-system'),
            'persist_failed' => __('Could not save responsibilities. Please try again.', 'jm-referral-system'),
            default => __('Could not save responsibilities.', 'jm-referral-system'),
        };
    }
}
