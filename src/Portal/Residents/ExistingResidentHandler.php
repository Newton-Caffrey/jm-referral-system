<?php

namespace JMReferral\Portal\Residents;

use JMReferral\Homes\HomeService;
use JMReferral\Homes\OccupancyService;
use JMReferral\LocalAuthority\LocalAuthorityRepository;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\Clinical\PortalViewHost;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\CareSetting;
use JMReferral\Residents\ExistingResident;
use JMReferral\Residents\ExistingResidentRegistrationService;
use JMReferral\Residents\ExistingResidentValidator;
use JMReferral\Residents\Import\ResidentImportColumns;
use JMReferral\Residents\Import\ResidentImportService;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Settings\ModuleSettings;
use JMReferral\Users\UserProvider;

/**
 * Phase 5F.1: staff portal screens for registering clients who already receive care.
 *
 * Routes:
 * - residents_existing         overview: add one, import many, property/bedroom reference
 * - resident_existing_new      single-resident form
 * - resident_import            upload → check (preview) → confirm → result
 * - resident_import_template   CSV template download
 */
class ExistingResidentHandler
{
    private const DONE_TRANSIENT_PREFIX = 'jmrs_res_import_done_';

    /** @var array<int, string> */
    public const ROUTES = [
        'residents_existing',
        'resident_existing_new',
        'resident_import',
        'resident_import_template',
    ];

    public function __construct(
        private PortalViewHost $view_host,
        private ExistingResidentRegistrationService $registration,
        private ResidentImportService $import_service,
        private HomeService $home_service,
        private OccupancyService $occupancy_service,
        private ServiceTypeService $service_type_service,
        private UserProvider $user_provider,
        private LocalAuthorityRepository $local_authority_repository
    ) {
    }

    public function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public function dispatch(string $route): void
    {
        if (! Capabilities::current_user_can(Capabilities::REGISTER_EXISTING_RESIDENTS)) {
            $this->view_host->render_portal_error('403', __('Access Denied', 'jm-referral-system'), 403);

            return;
        }

        match ($route) {
            'residents_existing'       => $this->render_overview(),
            'resident_existing_new'    => $this->render_new(),
            'resident_import'          => $this->render_import(),
            'resident_import_template' => $this->send_template(),
            default                    => $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404),
        };
    }

    private function render_overview(): void
    {
        $this->view_host->render_portal_page(
            'residents/overview',
            __('Add Existing Residents', 'jm-referral-system'),
            'residents_existing',
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
                ['label' => __('Add Existing Residents', 'jm-referral-system'), 'url' => ''],
            ],
            [
                'new_url'           => PortalUrls::resident_existing_new(),
                'import_url'        => PortalUrls::resident_import(),
                'properties'        => $this->property_reference(),
                'supported_living'  => ModuleSettings::is_enabled(ModuleSettings::SUPPORTED_LIVING),
                'homes_url'         => PortalUrls::homes(),
            ]
        );
    }

    private function render_new(): void
    {
        $values = [];
        $errors = [];

        if ('POST' === strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'))) {
            $nonce = sanitize_text_field(wp_unslash((string) ($_POST['jmrs_existing_resident_nonce'] ?? '')));
            if (! wp_verify_nonce($nonce, 'jmrs_register_existing_resident')) {
                $this->view_host->render_portal_error('403', __('Your session expired. Please reload the page and try again.', 'jm-referral-system'), 403);

                return;
            }

            foreach (ExistingResidentValidator::FIELDS as $field) {
                $raw = $_POST['jmrs_er_' . $field] ?? '';
                $values[$field] = is_string($raw) ? wp_unslash($raw) : '';
            }

            if (empty($_POST['jmrs_er_confirm'])) {
                // Show every problem at once, not just the missing confirmation.
                $errors            = $this->registration->check_one($values);
                $errors['confirm'] = __('Please confirm this person already receives care from you. For someone new, create a referral instead.', 'jm-referral-system');
            }

            if ([] === $errors) {
                $result = $this->registration->register_one($values, get_current_user_id());
                if (! empty($result['ok'])) {
                    $created = $result['created'][0];
                    wp_safe_redirect(add_query_arg('jmrs_resident_registered', '1', PortalUrls::referral((int) $created['id'])));
                    exit;
                }

                $errors = $result['row_errors'][1] ?? [];
                if ([] === $errors) {
                    $errors['general'] = (string) $result['message'];
                }
            }
        }

        $this->view_host->render_portal_page(
            'residents/new',
            __('Add an Existing Resident', 'jm-referral-system'),
            'residents_existing',
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
                ['label' => __('Add Existing Residents', 'jm-referral-system'), 'url' => PortalUrls::residents_existing()],
                ['label' => __('Add One Resident', 'jm-referral-system'), 'url' => ''],
            ],
            [
                'values'           => $values,
                'errors'           => $errors,
                'form_action'      => PortalUrls::resident_existing_new(),
                'cancel_url'       => PortalUrls::residents_existing(),
                'bedroom_groups'   => $this->vacant_bedroom_groups(),
                'supported_living' => ModuleSettings::is_enabled(ModuleSettings::SUPPORTED_LIVING),
                'services'         => $this->service_type_service->get_active(),
                'owners'           => $this->user_provider->get_assignable_users(),
                'authorities'      => $this->local_authority_repository->find_active(),
                'funding_types'    => ExistingResident::funding_type_labels(),
                'care_settings'    => [
                    CareSetting::SUPPORTED_LIVING => __('Supported Living (lives in one of your properties)', 'jm-referral-system'),
                    CareSetting::OWN_HOME         => __('Own Home (you provide care at their home)', 'jm-referral-system'),
                ],
            ]
        );
    }

    private function render_import(): void
    {
        $user_id = get_current_user_id();
        $view    = [
            'stage'         => 'upload',
            'error'         => '',
            'preview'       => null,
            'result'        => null,
            'form_action'   => PortalUrls::resident_import(),
            'overview_url'  => PortalUrls::residents_existing(),
            'template_csv'  => PortalUrls::resident_import_template(),
            'template_xlsx' => JMRS_PLUGIN_URL . 'assets/templates/existing-residents-import-template.xlsx',
            'max_rows'      => ExistingResident::MAX_IMPORT_ROWS,
            'max_size'      => size_format(ExistingResident::MAX_IMPORT_BYTES),
            'columns'       => ResidentImportColumns::definitions(),
        ];

        if ('POST' === strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'))) {
            $nonce = sanitize_text_field(wp_unslash((string) ($_POST['jmrs_resident_import_nonce'] ?? '')));
            if (! wp_verify_nonce($nonce, 'jmrs_resident_import')) {
                $this->view_host->render_portal_error('403', __('Your session expired. Please reload the page and try again.', 'jm-referral-system'), 403);

                return;
            }

            $action = sanitize_key((string) ($_POST['jmrs_import_action'] ?? 'preview'));
            $token  = sanitize_text_field(wp_unslash((string) ($_POST['jmrs_import_token'] ?? '')));

            if ('cancel' === $action) {
                $this->import_service->discard($token, $user_id);
                wp_safe_redirect(PortalUrls::resident_import());
                exit;
            }

            if ('confirm' === $action) {
                $result = $this->import_service->confirm($token, $user_id);
                if (! empty($result['ok'])) {
                    set_transient(
                        self::DONE_TRANSIENT_PREFIX . $user_id,
                        ['created' => $result['created'], 'batch' => $result['batch']],
                        HOUR_IN_SECONDS
                    );
                    wp_safe_redirect(add_query_arg('jmrs_import_done', '1', PortalUrls::resident_import()));
                    exit;
                }

                $view['stage']  = 'failed';
                $view['result'] = $result;
            } else {
                $file    = isset($_FILES['jmrs_import_file']) && is_array($_FILES['jmrs_import_file']) ? $_FILES['jmrs_import_file'] : [];
                $preview = $this->import_service->preview($file, $user_id);
                if (empty($preview['ok'])) {
                    $view['error'] = (string) $preview['error'];
                } else {
                    $view['stage']   = 'preview';
                    $view['preview'] = $preview;
                }
            }
        } elseif (isset($_GET['jmrs_import_done'])) {
            $done = get_transient(self::DONE_TRANSIENT_PREFIX . $user_id);
            if (is_array($done)) {
                $created = [];
                foreach ((array) ($done['created'] ?? []) as $row) {
                    $row['url'] = PortalUrls::referral((int) ($row['id'] ?? 0));
                    $created[]  = $row;
                }
                $view['stage']  = 'done';
                $view['result'] = ['created' => $created, 'batch' => (string) ($done['batch'] ?? '')];
            }
        }

        $this->view_host->render_portal_page(
            'residents/import',
            __('Import Existing Residents', 'jm-referral-system'),
            'residents_existing',
            [
                ['label' => __('Dashboard', 'jm-referral-system'), 'url' => PortalUrls::dashboard()],
                ['label' => __('Add Existing Residents', 'jm-referral-system'), 'url' => PortalUrls::residents_existing()],
                ['label' => __('Import from Spreadsheet', 'jm-referral-system'), 'url' => ''],
            ],
            $view
        );
    }

    private function send_template(): void
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="existing-residents-import-template.csv"');
        header('X-Content-Type-Options: nosniff');
        echo ResidentImportColumns::template_csv(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download.
        exit;
    }

    /**
     * Active properties with their bedrooms, marking which are free, for the reference table.
     *
     * @return array<int, array{name: string, status: string, vacant: array<int, string>, occupied: int}>
     */
    private function property_reference(): array
    {
        if (! ModuleSettings::is_enabled(ModuleSettings::SUPPORTED_LIVING)) {
            return [];
        }

        $out = [];
        foreach ($this->home_service->list(['status' => 'active']) as $home) {
            $home_id = absint($home['id'] ?? 0);
            $vacant  = array_map(
                static fn (array $room): string => (string) ($room['room_label'] ?? ''),
                $this->occupancy_service->vacant_active_bedrooms($home_id)
            );
            $out[] = [
                'name'     => (string) ($home['name'] ?? ''),
                'status'   => (string) ($home['status'] ?? ''),
                'vacant'   => $vacant,
                'occupied' => absint($home['occupied'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{home: string, bedrooms: array<int, array{id: int, label: string}>}>
     */
    private function vacant_bedroom_groups(): array
    {
        if (! ModuleSettings::is_enabled(ModuleSettings::SUPPORTED_LIVING)) {
            return [];
        }

        $groups = [];
        foreach ($this->home_service->list(['status' => 'active']) as $home) {
            $rooms = [];
            foreach ($this->occupancy_service->vacant_active_bedrooms(absint($home['id'] ?? 0)) as $room) {
                $rooms[] = ['id' => absint($room['id'] ?? 0), 'label' => (string) ($room['room_label'] ?? '')];
            }
            if ([] !== $rooms) {
                $groups[] = ['home' => (string) ($home['name'] ?? ''), 'bedrooms' => $rooms];
            }
        }

        return $groups;
    }
}
