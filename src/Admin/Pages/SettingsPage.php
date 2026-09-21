<?php

namespace JMReferral\Admin\Pages;

use JMReferral\Documents\PrivateDocumentStorage;
use JMReferral\Documents\ReferralDocumentService;
use JMReferral\Frontend\PublicReferralSettings;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\PortalSettings;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\ReferralDependencyRepository;
use JMReferral\Settings\ModuleSettings;
use JMReferral\Settings\OrganisationSettings;
use JMReferral\Settings\TerminologySettings;

class SettingsPage
{
    public function __construct(
        private ?ReferralDocumentService $document_service = null,
        private ?ReferralDependencyRepository $dependency_repository = null
    ) {
    }

    public function render(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to manage settings.', 'jm-referral-system'));
        }

        $this->maybe_save_organisation_settings();
        $this->maybe_save_terminology_settings();
        $this->maybe_save_module_settings();
        $this->maybe_save_public_referral_settings();
        $this->maybe_save_staff_portal_settings();
        $this->maybe_save_pipeline_internal_targets();

        $counts = [
            'legacy'  => 0,
            'private' => 0,
        ];

        if ($this->document_service instanceof ReferralDocumentService) {
            $counts = $this->document_service->get_storage_counts();
        }

        $legacy_count  = absint($counts['legacy'] ?? 0);
        $private_count = absint($counts['private'] ?? 0);

        $integrity = [];
        if ($this->dependency_repository instanceof ReferralDependencyRepository) {
            $integrity = $this->dependency_repository->integrity_counts();
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Settings', 'jm-referral-system') . '</h1>';

        $this->render_integrations_section();
        $this->render_organisation_and_branding_settings();
        $this->render_terminology_settings();
        $this->render_module_settings();
        $this->render_service_catalogue_link();
        $this->render_public_referral_settings();
        $this->render_staff_portal_settings();
        $this->render_pipeline_internal_targets();

        echo '<h2>' . esc_html__('Private Document Migration', 'jm-referral-system') . '</h2>';

        echo '<p>';
        echo esc_html__(
            'New referral documents are stored in a private directory under uploads/jmrs-private/ and are served only through secure plugin download links. Direct public URLs are not used for new files.',
            'jm-referral-system'
        );
        echo '</p>';

        echo '<p>';
        echo esc_html__(
            'Apache-compatible hosts receive an .htaccess deny rule in that directory. This protection may not apply on every server (for example some nginx setups). Download links must always use the plugin controller.',
            'jm-referral-system'
        );
        echo '</p>';

        if ($legacy_count > 0) {
            echo '<div class="notice notice-warning inline"><p>';
            echo esc_html__(
                'Legacy documents may still be directly accessible through their original Media Library URLs until migration and cleanup are completed.',
                'jm-referral-system'
            );
            echo '</p></div>';
        }

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">' . esc_html__('Legacy documents', 'jm-referral-system') . '</th>';
        echo '<td><strong>' . esc_html((string) $legacy_count) . '</strong></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Private documents', 'jm-referral-system') . '</th>';
        echo '<td><strong>' . esc_html((string) $private_count) . '</strong></td></tr>';
        echo '</tbody></table>';

        echo '<p class="description">';
        echo esc_html__(
            'Migration copies files into private storage in small batches and keeps the original Media Library files until a later cleanup phase. Running migration again is safe; already-private documents are skipped.',
            'jm-referral-system'
        );
        echo '</p>';

        if ($legacy_count > 0) {
            echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=jm-referrals-settings')) . '">';
            wp_nonce_field('jmrs_migrate_legacy_documents', 'jmrs_migrate_legacy_documents_nonce');
            submit_button(
                __('Migrate Legacy Documents', 'jm-referral-system'),
                'primary',
                'jmrs_migrate_legacy_documents',
                false
            );
            echo '</form>';
        } else {
            echo '<p><em>';
            echo esc_html__('There are no legacy documents left to migrate.', 'jm-referral-system');
            echo '</em></p>';
        }

        echo '<h2>' . esc_html__('Data Integrity Check', 'jm-referral-system') . '</h2>';
        echo '<p>';
        echo esc_html__(
            'Counts only. This check does not delete, repair, or change any records. Investigate unusual values with database backups and support processes.',
            'jm-referral-system'
        );
        echo '</p>';

        if ([] !== $integrity) {
            $labels = [
                'orphan_notes'                       => __('Notes referencing missing referrals', 'jm-referral-system'),
                'orphan_documents'                   => __('Documents referencing missing referrals', 'jm-referral-system'),
                'orphan_assessments'                 => __('Assessments referencing missing referrals', 'jm-referral-system'),
                'orphan_care_plans'                  => __('Care plans referencing missing referrals', 'jm-referral-system'),
                'orphan_care_plan_versions'          => __('Care-plan versions without a care plan', 'jm-referral-system'),
                'orphan_care_plan_reviews'           => __('Care-plan reviews without a care plan', 'jm-referral-system'),
                'orphan_care_team'                   => __('Care-team rows referencing missing referrals', 'jm-referral-system'),
                'orphan_schedules'                   => __('Schedules referencing missing referrals', 'jm-referral-system'),
                'orphan_visits'                      => __('Visits referencing missing referrals', 'jm-referral-system'),
                'orphan_visit_tasks'                 => __('Visit tasks without a valid visit/referral', 'jm-referral-system'),
                'orphan_medications'                 => __('Medications referencing missing referrals', 'jm-referral-system'),
                'orphan_medication_administrations'  => __('Medication administrations referencing missing referrals', 'jm-referral-system'),
                'orphan_activity'                    => __('Activity rows referencing missing referrals', 'jm-referral-system'),
                'documents_missing_private_files'    => __('Private documents with missing files', 'jm-referral-system'),
                'visits_missing_schedule'            => __('Visits referencing missing schedules', 'jm-referral-system'),
                'visits_missing_care_plan'           => __('Visits referencing missing care plans', 'jm-referral-system'),
                'visits_missing_team_user'           => __('Visits referencing missing assigned users', 'jm-referral-system'),
                'administrations_missing_medication' => __('Administrations referencing missing medications', 'jm-referral-system'),
                'administrations_missing_visit'      => __('Administrations referencing missing visits', 'jm-referral-system'),
                'visit_tasks_missing_visit'          => __('Visit tasks referencing missing visits', 'jm-referral-system'),
            ];

            echo '<table class="widefat striped" style="max-width:720px;"><thead><tr>';
            echo '<th>' . esc_html__('Check', 'jm-referral-system') . '</th>';
            echo '<th>' . esc_html__('Count', 'jm-referral-system') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($labels as $key => $label) {
                $value = absint($integrity[$key] ?? 0);
                echo '<tr><td>' . esc_html($label) . '</td>';
                echo '<td><strong>' . esc_html((string) $value) . '</strong></td></tr>';
            }

            echo '</tbody></table>';
        }

        echo '<h2>' . esc_html__('Backup requirements', 'jm-referral-system') . '</h2>';
        echo '<p>';
        echo esc_html__(
            'Back up both the WordPress database and the uploads/jmrs-private/ directory. Private files are not Media Library attachments and will not be included in attachment-only backups.',
            'jm-referral-system'
        );
        echo '</p>';

        echo '<p class="description">';
        printf(
            /* translators: %s: storage directory name */
            esc_html__('Private storage directory name: %s', 'jm-referral-system'),
            esc_html(PrivateDocumentStorage::DIRECTORY_NAME)
        );
        echo '</p>';

        echo '<h2>' . esc_html__('Uninstall behaviour', 'jm-referral-system') . '</h2>';
        echo '<p>';
        echo esc_html__(
            'By default, deleting the plugin removes JM roles and capabilities only. Custom tables and private files are preserved. To wipe plugin data on uninstall, set JMRS_DELETE_DATA_ON_UNINSTALL to true in wp-config.php on a disposable site only after taking backups. Legacy Media Library attachments are never deleted automatically.',
            'jm-referral-system'
        );
        echo '</p>';

        echo '</div>';
    }

    private function render_integrations_section(): void
    {
        echo '<h2>' . esc_html__('Integrations', 'jm-referral-system') . '</h2>';
        echo '<p>';
        echo esc_html__(
            'External mailbox and identity connections for this JMRS installation.',
            'jm-referral-system'
        );
        echo '</p>';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">' . esc_html__('Microsoft 365', 'jm-referral-system') . '</th><td>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=jm-referrals-microsoft-365')) . '">';
        echo esc_html__('Configure Microsoft 365', 'jm-referral-system');
        echo '</a>';
        echo '<p class="description">';
        echo esc_html__(
            'Customer-owned Entra application, referral mailbox, and encrypted client secret storage. Microsoft Graph verification is not performed in this phase.',
            'jm-referral-system'
        );
        echo '</p></td></tr>';
        echo '</tbody></table>';
    }

    private function maybe_save_organisation_settings(): void
    {
        if (! isset($_POST['jmrs_save_organisation_settings'])) {
            return;
        }

        check_admin_referer('jmrs_save_organisation_settings', 'jmrs_organisation_settings_nonce');

        if (! Capabilities::current_user_can(Capabilities::MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to manage settings.', 'jm-referral-system'));
        }

        $result = OrganisationSettings::update(
            [
                'display_name'       => isset($_POST['jmrs_org_display_name'])
                    ? wp_unslash((string) $_POST['jmrs_org_display_name'])
                    : '',
                'legal_name'         => isset($_POST['jmrs_org_legal_name'])
                    ? wp_unslash((string) $_POST['jmrs_org_legal_name'])
                    : '',
                'trading_name'       => isset($_POST['jmrs_org_trading_name'])
                    ? wp_unslash((string) $_POST['jmrs_org_trading_name'])
                    : '',
                'logo_attachment_id' => isset($_POST['jmrs_org_logo_attachment_id'])
                    ? absint(wp_unslash((string) $_POST['jmrs_org_logo_attachment_id']))
                    : 0,
                'contact_email'      => isset($_POST['jmrs_org_contact_email'])
                    ? wp_unslash((string) $_POST['jmrs_org_contact_email'])
                    : '',
                'contact_phone'      => isset($_POST['jmrs_org_contact_phone'])
                    ? wp_unslash((string) $_POST['jmrs_org_contact_phone'])
                    : '',
                'website'            => isset($_POST['jmrs_org_website'])
                    ? wp_unslash((string) $_POST['jmrs_org_website'])
                    : '',
                'address'            => isset($_POST['jmrs_org_address'])
                    ? wp_unslash((string) $_POST['jmrs_org_address'])
                    : '',
                'portal_title'       => isset($_POST['jmrs_org_portal_title'])
                    ? wp_unslash((string) $_POST['jmrs_org_portal_title'])
                    : '',
                'primary_colour'     => isset($_POST['jmrs_org_primary_colour'])
                    ? wp_unslash((string) $_POST['jmrs_org_primary_colour'])
                    : '',
                'secondary_colour'   => isset($_POST['jmrs_org_secondary_colour'])
                    ? wp_unslash((string) $_POST['jmrs_org_secondary_colour'])
                    : '',
                'email_sender_name'  => isset($_POST['jmrs_org_email_sender_name'])
                    ? wp_unslash((string) $_POST['jmrs_org_email_sender_name'])
                    : '',
            ]
        );

        if (! empty($result['ok'])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Organisation and branding settings saved.', 'jm-referral-system');
            echo '</p></div>';

            return;
        }

        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        echo '<div class="notice notice-error" role="alert"><p>';
        echo esc_html__('Organisation settings could not be saved. Please correct the highlighted fields.', 'jm-referral-system');
        echo '</p>';
        if ([] !== $errors) {
            echo '<ul>';
            foreach ($errors as $message) {
                echo '<li>' . esc_html((string) $message) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }

    private function render_organisation_and_branding_settings(): void
    {
        $org = OrganisationSettings::all();
        $logo_id = absint($org['logo_attachment_id'] ?? 0);
        $logo_url = OrganisationSettings::logo_url();

        echo '<div class="jmrs-settings-org">';
        echo '<h2>' . esc_html__('Organisation', 'jm-referral-system') . '</h2>';
        echo '<p>';
        echo esc_html__(
            'Client-facing organisation identity for this installation. Technical plugin identifiers (jmrs_*, routes, capabilities) are unchanged.',
            'jm-referral-system'
        );
        echo '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=jm-referrals-settings')) . '" class="jmrs-organisation-settings-form">';
        wp_nonce_field('jmrs_save_organisation_settings', 'jmrs_organisation_settings_nonce');

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="jmrs_org_display_name">' . esc_html__('Organisation display name', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_org_display_name" id="jmrs_org_display_name" value="' . esc_attr((string) $org['display_name']) . '" required />';
        echo '<p class="description">' . esc_html__('Shown in the portal header, management dashboard, public intake, and related labels.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_org_legal_name">' . esc_html__('Legal name', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_org_legal_name" id="jmrs_org_legal_name" value="' . esc_attr((string) $org['legal_name']) . '" />';
        echo '<p class="description">' . esc_html__('Optional registered legal name. Plain text only.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_org_trading_name">' . esc_html__('Trading name', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_org_trading_name" id="jmrs_org_trading_name" value="' . esc_attr((string) $org['trading_name']) . '" />';
        echo '<p class="description">' . esc_html__('Optional trading-as name if different from the display name.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_org_logo_attachment_id">' . esc_html__('Logo', 'jm-referral-system') . '</label></th><td>';
        echo '<div class="jmrs-org-logo-controls">';
        echo '<input type="hidden" name="jmrs_org_logo_attachment_id" id="jmrs_org_logo_attachment_id" value="' . esc_attr((string) $logo_id) . '" />';
        echo '<button type="button" class="button" id="jmrs_org_logo_select">' . esc_html__('Select logo', 'jm-referral-system') . '</button> ';
        echo '<button type="button" class="button" id="jmrs_org_logo_clear"' . ($logo_id > 0 ? '' : ' disabled') . '>' . esc_html__('Remove logo', 'jm-referral-system') . '</button>';
        echo '<div class="jmrs-org-logo-preview" id="jmrs_org_logo_preview">';
        if ('' !== $logo_url && $logo_id > 0) {
            echo '<img src="' . esc_url($logo_url) . '" alt="" />';
        } else {
            echo '<p class="description">' . esc_html__('No logo selected. Portal and intake use text branding as a fallback.', 'jm-referral-system') . '</p>';
        }
        echo '</div>';
        echo '<p class="description">' . esc_html__('Choose an image from the Media Library. Attachment ID is stored; arbitrary remote URLs are not accepted here.', 'jm-referral-system') . '</p>';
        echo '</div></td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_org_contact_email">' . esc_html__('Contact email', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="email" class="regular-text" name="jmrs_org_contact_email" id="jmrs_org_contact_email" value="' . esc_attr((string) $org['contact_email']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_org_contact_phone">' . esc_html__('Contact phone', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_org_contact_phone" id="jmrs_org_contact_phone" value="' . esc_attr((string) $org['contact_phone']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_org_website">' . esc_html__('Website', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="url" class="regular-text" name="jmrs_org_website" id="jmrs_org_website" value="' . esc_attr((string) $org['website']) . '" placeholder="https://" />';
        echo '<p class="description">' . esc_html__('Must be an http or https URL.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_org_address">' . esc_html__('Address', 'jm-referral-system') . '</label></th><td>';
        echo '<textarea class="large-text" rows="3" name="jmrs_org_address" id="jmrs_org_address">' . esc_textarea((string) $org['address']) . '</textarea>';
        echo '<p class="description">' . esc_html__('Business address as plain text. HTML is not allowed.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_org_portal_title">' . esc_html__('Portal title', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_org_portal_title" id="jmrs_org_portal_title" value="' . esc_attr((string) $org['portal_title']) . '" />';
        echo '<p class="description">' . esc_html__('Staff portal product title shown in the portal chrome.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Site timezone (WordPress)', 'jm-referral-system') . '</th><td>';
        echo '<code>' . esc_html(OrganisationSettings::effective_timezone_label()) . '</code>';
        echo '<p class="description">' . esc_html__('Informational only. WordPress Settings → General remains authoritative for timezone and date format.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Branding', 'jm-referral-system') . '</h2>';
        echo '<p>';
        echo esc_html__(
            'Colours must be 6-digit hex values (for example #17365D). Custom CSS and arbitrary style strings are not accepted.',
            'jm-referral-system'
        );
        echo '</p>';

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="jmrs_org_primary_colour">' . esc_html__('Primary colour', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_org_primary_colour" id="jmrs_org_primary_colour" value="' . esc_attr((string) $org['primary_colour']) . '" placeholder="#0b5f4b" pattern="#[0-9A-Fa-f]{6}" />';
        echo '<p class="description">' . esc_html__('Hex colour such as #0b5f4b or #17365D.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_org_secondary_colour">' . esc_html__('Secondary / accent colour', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_org_secondary_colour" id="jmrs_org_secondary_colour" value="' . esc_attr((string) $org['secondary_colour']) . '" placeholder="#1a3a32" pattern="#[0-9A-Fa-f]{6}" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_org_email_sender_name">' . esc_html__('Email sender display name', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_org_email_sender_name" id="jmrs_org_email_sender_name" value="' . esc_attr((string) $org['email_sender_name']) . '" />';
        echo '<p class="description">' . esc_html__('Used as the From display name for outgoing notifications. Falls back to the organisation display name when blank. Does not change SMTP settings.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '</tbody></table>';

        submit_button(
            __('Save Organisation & Branding', 'jm-referral-system'),
            'primary',
            'jmrs_save_organisation_settings',
            false
        );
        echo '</form>';
        echo '</div>';
    }

    private function maybe_save_terminology_settings(): void
    {
        if (! isset($_POST['jmrs_save_terminology_settings'])) {
            return;
        }

        check_admin_referer('jmrs_save_terminology_settings', 'jmrs_terminology_settings_nonce');

        if (! Capabilities::current_user_can(Capabilities::MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to manage settings.', 'jm-referral-system'));
        }

        $input = [];
        foreach (array_keys(TerminologySettings::defaults()) as $key) {
            $field = 'jmrs_term_' . $key;
            if (isset($_POST[$field])) {
                $input[$key] = wp_unslash($_POST[$field]);
            }
        }

        $result = TerminologySettings::update($input);

        if (! empty($result['ok'])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Terminology settings saved. Display labels only — stored referral data and technical keys are unchanged.', 'jm-referral-system');
            echo '</p></div>';

            return;
        }

        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        echo '<div class="notice notice-error" role="alert"><p>';
        echo esc_html__('Terminology settings could not be saved.', 'jm-referral-system');
        echo '</p>';
        if ([] !== $errors) {
            echo '<ul>';
            foreach ($errors as $message) {
                echo '<li>' . esc_html((string) $message) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }

    private function render_terminology_settings(): void
    {
        $terms = TerminologySettings::all();

        $fields = [
            'referral_singular'        => __('Referral singular', 'jm-referral-system'),
            'referral_plural'          => __('Referral plural', 'jm-referral-system'),
            'client_singular'          => __('Client / person singular', 'jm-referral-system'),
            'client_plural'            => __('Client / person plural', 'jm-referral-system'),
            'local_authority_singular' => __('Local Authority singular', 'jm-referral-system'),
            'local_authority_plural'   => __('Local Authority plural', 'jm-referral-system'),
            'commissioner_singular'    => __('Commissioner singular', 'jm-referral-system'),
            'commissioner_plural'      => __('Commissioner plural', 'jm-referral-system'),
            'service_singular'         => __('Service singular', 'jm-referral-system'),
            'service_plural'           => __('Service plural', 'jm-referral-system'),
        ];

        echo '<div class="jmrs-settings-terminology">';
        echo '<h2>' . esc_html__('Terminology', 'jm-referral-system') . '</h2>';
        echo '<p>';
        echo esc_html__(
            'These labels affect client-facing wording only. Pipeline keys, database values, capabilities, routes, and stored referral fields are not renamed.',
            'jm-referral-system'
        );
        echo '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=jm-referrals-settings')) . '">';
        wp_nonce_field('jmrs_save_terminology_settings', 'jmrs_terminology_settings_nonce');

        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($fields as $key => $label) {
            $id = 'jmrs_term_' . $key;
            echo '<tr><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';
            echo '<input type="text" class="regular-text" name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" value="' . esc_attr((string) ($terms[$key] ?? '')) . '" maxlength="' . esc_attr((string) TerminologySettings::MAX_LENGTH) . '" />';
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        submit_button(
            __('Save Terminology', 'jm-referral-system'),
            'primary',
            'jmrs_save_terminology_settings',
            false
        );
        echo '</form>';
        echo '</div>';
    }

    private function maybe_save_module_settings(): void
    {
        if (! isset($_POST['jmrs_save_module_settings'])) {
            return;
        }

        check_admin_referer('jmrs_save_module_settings', 'jmrs_module_settings_nonce');

        if (! Capabilities::current_user_can(Capabilities::MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to manage settings.', 'jm-referral-system'));
        }

        $input = [];
        foreach (ModuleSettings::known_modules() as $module) {
            $input[$module] = ! empty($_POST['jmrs_module_' . $module]);
        }

        $result = ModuleSettings::update($input);

        if (! empty($result['ok'])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Module settings saved. Disabled modules preserve historical data; new actions are blocked.', 'jm-referral-system');
            echo '</p></div>';

            $warnings = is_array($result['warnings'] ?? null) ? $result['warnings'] : [];
            foreach ($warnings as $warning) {
                echo '<div class="notice notice-warning is-dismissible" role="status"><p>';
                echo esc_html((string) $warning);
                echo '</p></div>';
            }

            return;
        }

        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        echo '<div class="notice notice-error" role="alert"><p>';
        echo esc_html__('Module settings could not be saved. The previous valid configuration was kept.', 'jm-referral-system');
        echo '</p>';
        if ([] !== $errors) {
            echo '<ul>';
            foreach ($errors as $message) {
                echo '<li>' . esc_html((string) $message) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }

    private function render_module_settings(): void
    {
        $modules = ModuleSettings::all();
        $labels  = ModuleSettings::labels();
        $descs   = ModuleSettings::descriptions();

        echo '<div class="jmrs-settings-modules">';
        echo '<h2>' . esc_html__('Modules', 'jm-referral-system') . '</h2>';
        echo '<p>';
        echo esc_html__(
            'Turn operational modules on or off for this installation. Disabling a module hides navigation and blocks new mutations. Historical records and pipeline history are not deleted or rewritten. Dependencies are enforced on save.',
            'jm-referral-system'
        );
        echo '</p>';
        echo '<p class="description">';
        echo esc_html__(
            'Package Costing requires Assessments. Local Authority Decisions require Package Costing. Transition & Care Commencement require Local Authority Decisions. Email intake is not available in this phase.',
            'jm-referral-system'
        );
        echo '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=jm-referrals-settings')) . '">';
        wp_nonce_field('jmrs_save_module_settings', 'jmrs_module_settings_nonce');

        echo '<table class="form-table" role="presentation"><tbody>';
        foreach (ModuleSettings::known_modules() as $module) {
            $id = 'jmrs_module_' . $module;
            echo '<tr><th scope="row">' . esc_html((string) ($labels[$module] ?? $module)) . '</th><td>';
            echo '<label for="' . esc_attr($id) . '">';
            echo '<input type="checkbox" name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" value="1" ' . checked(! empty($modules[$module]), true, false) . ' /> ';
            echo esc_html__('Enabled', 'jm-referral-system');
            echo '</label>';
            if (! empty($descs[$module])) {
                echo '<p class="description">' . esc_html((string) $descs[$module]) . '</p>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        submit_button(
            __('Save Modules', 'jm-referral-system'),
            'primary',
            'jmrs_save_module_settings',
            false
        );
        echo '</form>';
        echo '</div>';
    }

    private function render_service_catalogue_link(): void
    {
        $services_url = admin_url('admin.php?page=jm-referrals-service-types');
        $service_label = TerminologySettings::service_plural();

        echo '<div class="jmrs-settings-services">';
        echo '<h2>' . esc_html($service_label) . '</h2>';
        echo '<p>';
        echo esc_html__(
            'Manage the per-installation service catalogue (name, description, active/inactive). Prefer deactivating services that are referenced by historical referrals instead of deleting them.',
            'jm-referral-system'
        );
        echo '</p>';
        echo '<p class="description">';
        echo esc_html__(
            'Display order and separate public/staff availability flags require a later database schema change and are not available in this phase. Active services are offered for both staff and public intake.',
            'jm-referral-system'
        );
        echo '</p>';
        echo '<p><a class="button" href="' . esc_url($services_url) . '">';
        echo esc_html(
            sprintf(
                /* translators: %s: service plural label */
                __('Open %s management', 'jm-referral-system'),
                $service_label
            )
        );
        echo '</a></p>';
        echo '</div>';
    }

    private function maybe_save_public_referral_settings(): void
    {
        if (! isset($_POST['jmrs_save_public_referral_settings'])) {
            return;
        }

        check_admin_referer('jmrs_save_public_referral_settings', 'jmrs_public_referral_settings_nonce');

        // Organisation/branding identity is owned by OrganisationSettings (synced mirrors).
        PublicReferralSettings::update(
            [
                'enabled'            => ! empty($_POST['jmrs_public_form_enabled']),
                'privacy_notice_url' => isset($_POST['jmrs_privacy_notice_url'])
                    ? wp_unslash((string) $_POST['jmrs_privacy_notice_url'])
                    : '',
                'consent_version'    => isset($_POST['jmrs_public_consent_version'])
                    ? wp_unslash((string) $_POST['jmrs_public_consent_version'])
                    : PublicReferralSettings::DEFAULT_CONSENT_VERSION,
                'notification_email' => isset($_POST['jmrs_public_notification_email'])
                    ? wp_unslash((string) $_POST['jmrs_public_notification_email'])
                    : '',
                'success_message'    => isset($_POST['jmrs_public_success_message'])
                    ? wp_unslash((string) $_POST['jmrs_public_success_message'])
                    : '',
                'allow_uploads'      => ! empty($_POST['jmrs_public_allow_uploads']),
                'max_upload_count'   => isset($_POST['jmrs_public_max_upload_count'])
                    ? absint(wp_unslash((string) $_POST['jmrs_public_max_upload_count']))
                    : PublicReferralSettings::DEFAULT_MAX_UPLOAD_COUNT,
                'max_upload_size_mb' => isset($_POST['jmrs_public_max_upload_size_mb'])
                    ? absint(wp_unslash((string) $_POST['jmrs_public_max_upload_size_mb']))
                    : PublicReferralSettings::DEFAULT_MAX_UPLOAD_SIZE_MB,
                'public_heading'     => isset($_POST['jmrs_public_heading'])
                    ? wp_unslash((string) $_POST['jmrs_public_heading'])
                    : '',
                'public_intro'       => isset($_POST['jmrs_public_intro'])
                    ? wp_unslash((string) $_POST['jmrs_public_intro'])
                    : '',
                'success_next_steps' => isset($_POST['jmrs_success_next_steps'])
                    ? wp_unslash((string) $_POST['jmrs_success_next_steps'])
                    : '',
            ]
        );

        echo '<div class="notice notice-success is-dismissible"><p>';
        echo esc_html__('Public referral intake settings saved successfully.', 'jm-referral-system');
        echo '</p></div>';
    }

    private function render_public_referral_settings(): void
    {
        $settings = PublicReferralSettings::all();

        echo '<h2>' . esc_html__('Public Referral Intake', 'jm-referral-system') . '</h2>';
        echo '<p>';
        echo esc_html__(
            'Allow members of the public to submit referrals from the website using the shortcode [jmrs_public_referral_form]. The form is disabled by default.',
            'jm-referral-system'
        );
        echo '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=jm-referrals-settings')) . '">';
        wp_nonce_field('jmrs_save_public_referral_settings', 'jmrs_public_referral_settings_nonce');

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row">' . esc_html__('Enable Public Referral Form', 'jm-referral-system') . '</th><td>';
        echo '<label><input type="checkbox" name="jmrs_public_form_enabled" value="1" ' . checked(! empty($settings['enabled']), true, false) . ' /> ';
        echo esc_html__('Accept submissions from the public shortcode form', 'jm-referral-system') . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_privacy_notice_url">' . esc_html__('Privacy Notice URL', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="url" class="regular-text" name="jmrs_privacy_notice_url" id="jmrs_privacy_notice_url" value="' . esc_attr((string) $settings['privacy_notice_url']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_public_consent_version">' . esc_html__('Public Consent Version', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_public_consent_version" id="jmrs_public_consent_version" value="' . esc_attr((string) $settings['consent_version']) . '" />';
        echo '<p class="description">' . esc_html__('Stored with each public submission as operational evidence (not a full legal consent system).', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_public_notification_email">' . esc_html__('Public Referral Notification Email', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="email" class="regular-text" name="jmrs_public_notification_email" id="jmrs_public_notification_email" value="' . esc_attr((string) $settings['notification_email']) . '" />';
        echo '<p class="description">' . esc_html__('Falls back to the WordPress admin email when empty.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_public_success_message">' . esc_html__('Success Message', 'jm-referral-system') . '</label></th><td>';
        echo '<textarea class="large-text" rows="3" name="jmrs_public_success_message" id="jmrs_public_success_message">' . esc_textarea((string) $settings['success_message']) . '</textarea>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Organisation branding', 'jm-referral-system') . '</th><td>';
        echo '<p class="description">';
        echo esc_html__(
            'Company name, contact details, and primary colour are managed under Organisation and Branding above. Saving this section does not change those values.',
            'jm-referral-system'
        );
        echo '</p></td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_public_heading">' . esc_html__('Public Referral Heading', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_public_heading" id="jmrs_public_heading" value="' . esc_attr((string) $settings['public_heading']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_public_intro">' . esc_html__('Public Referral Intro', 'jm-referral-system') . '</label></th><td>';
        echo '<textarea class="large-text" rows="5" name="jmrs_public_intro" id="jmrs_public_intro">' . esc_textarea((string) $settings['public_intro']) . '</textarea>';
        echo '<p class="description">' . esc_html__('Leave blank to use the default intro with the configured organisation display name.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_success_next_steps">' . esc_html__('Success Page Next-Steps Text', 'jm-referral-system') . '</label></th><td>';
        echo '<textarea class="large-text" rows="4" name="jmrs_success_next_steps" id="jmrs_success_next_steps">' . esc_textarea((string) $settings['success_next_steps']) . '</textarea>';
        echo '<p class="description">' . esc_html__('One next-step item per line.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Allow Public Document Uploads', 'jm-referral-system') . '</th><td>';
        echo '<label><input type="checkbox" name="jmrs_public_allow_uploads" value="1" ' . checked(! empty($settings['allow_uploads']), true, false) . ' /> ';
        echo esc_html__('Allow supporting documents on the public form (private storage only)', 'jm-referral-system') . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_public_max_upload_count">' . esc_html__('Maximum Public Upload Count', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="number" min="1" max="10" name="jmrs_public_max_upload_count" id="jmrs_public_max_upload_count" value="' . esc_attr((string) $settings['max_upload_count']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_public_max_upload_size_mb">' . esc_html__('Maximum Public Upload Size (MB)', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="number" min="1" max="20" name="jmrs_public_max_upload_size_mb" id="jmrs_public_max_upload_size_mb" value="' . esc_attr((string) $settings['max_upload_size_mb']) . '" />';
        echo '</td></tr>';

        echo '</tbody></table>';

        submit_button(
            __('Save Public Referral Settings', 'jm-referral-system'),
            'primary',
            'jmrs_save_public_referral_settings',
            false
        );
        echo '</form>';
    }

    private function maybe_save_staff_portal_settings(): void
    {
        if (! isset($_POST['jmrs_save_staff_portal_settings'])) {
            return;
        }

        check_admin_referer('jmrs_save_staff_portal_settings', 'jmrs_staff_portal_settings_nonce');

        // Branding (name, logo, colours, support contact) is owned by OrganisationSettings.
        $result = PortalSettings::update(
            [
                'enabled'            => ! empty($_POST['jmrs_portal_enabled']),
                'base_path'          => isset($_POST['jmrs_portal_base_path'])
                    ? wp_unslash((string) $_POST['jmrs_portal_base_path'])
                    : PortalSettings::DEFAULT_BASE_PATH,
                'login_redirect_url' => isset($_POST['jmrs_portal_login_redirect_url'])
                    ? wp_unslash((string) $_POST['jmrs_portal_login_redirect_url'])
                    : '',
                'redirect_wp_admin'  => ! empty($_POST['jmrs_portal_redirect_wp_admin']),
            ]
        );

        echo '<div class="notice notice-success is-dismissible"><p>';
        echo esc_html__('Staff portal settings saved.', 'jm-referral-system');
        if (! empty($result['path_changed'])) {
            echo ' ';
            echo esc_html__('Rewrite rules were flushed for the portal base path.', 'jm-referral-system');
        }
        echo '</p></div>';

        if (! empty($result['conflict'])) {
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo esc_html((string) $result['conflict']);
            echo '</p></div>';
        }
    }

    private function render_staff_portal_settings(): void
    {
        $settings = PortalSettings::all();
        $portal_url = PortalUrls::home();

        echo '<h2>' . esc_html__('Staff Portal', 'jm-referral-system') . '</h2>';
        echo '<p>';
        echo esc_html__(
            'Optional frontend portal for staff. Disabled by default. Reuses existing capabilities, AccessPolicy, and referral services. WordPress Admin remains available for administrators.',
            'jm-referral-system'
        );
        echo '</p>';

        if (! empty($settings['enabled'])) {
            echo '<p><strong>' . esc_html__('Portal URL:', 'jm-referral-system') . '</strong> ';
            echo '<a href="' . esc_url($portal_url) . '">' . esc_html($portal_url) . '</a></p>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=jm-referrals-settings')) . '">';
        wp_nonce_field('jmrs_save_staff_portal_settings', 'jmrs_staff_portal_settings_nonce');

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row">' . esc_html__('Enable Staff Portal', 'jm-referral-system') . '</th><td>';
        echo '<label><input type="checkbox" name="jmrs_portal_enabled" value="1" ' . checked(! empty($settings['enabled']), true, false) . ' /> ';
        echo esc_html__('Enable the staff portal rewrite routes', 'jm-referral-system') . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Organisation branding', 'jm-referral-system') . '</th><td>';
        echo '<p class="description">';
        echo esc_html__(
            'Portal title, organisation name, logo, colours, and support contact are managed under Organisation and Branding above.',
            'jm-referral-system'
        );
        echo '</p></td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_portal_base_path">' . esc_html__('Portal Base Path', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_portal_base_path" id="jmrs_portal_base_path" value="' . esc_attr((string) $settings['base_path']) . '" />';
        echo '<p class="description">' . esc_html__('URL slug only (default: staff-portal). Changing this flushes rewrite rules once.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_portal_login_redirect_url">' . esc_html__('Login Redirect URL', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="url" class="regular-text" name="jmrs_portal_login_redirect_url" id="jmrs_portal_login_redirect_url" value="' . esc_attr((string) $settings['login_redirect_url']) . '" />';
        echo '<p class="description">' . esc_html__('Optional. Staff login redirect when redirect_to is not a portal URL. Leave blank to use the portal dashboard.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Redirect JMRS Staff Away From wp-admin', 'jm-referral-system') . '</th><td>';
        echo '<label><input type="checkbox" name="jmrs_portal_redirect_wp_admin" value="1" ' . checked(! empty($settings['redirect_wp_admin']), true, false) . ' /> ';
        echo esc_html__('Send non-administrator staff from wp-admin screens to the portal (keep off until tested)', 'jm-referral-system') . '</label>';
        echo '<p class="description">' . esc_html__('Does not block WordPress Administrators. AJAX, admin-post, secure downloads, exports, and profile screens remain allowed.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '</tbody></table>';

        submit_button(
            __('Save Staff Portal Settings', 'jm-referral-system'),
            'primary',
            'jmrs_save_staff_portal_settings',
            false
        );
        echo '</form>';
    }

    private function maybe_save_pipeline_internal_targets(): void
    {
        if (! isset($_POST['jmrs_save_pipeline_internal_targets'])) {
            return;
        }

        check_admin_referer('jmrs_save_pipeline_internal_targets', 'jmrs_pipeline_internal_targets_nonce');

        $input = [];
        foreach (\JMReferral\Pipeline\PipelineInternalTargets::configurable_stages() as $slug) {
            $key = 'jmrs_target_' . $slug;
            $input[$slug] = isset($_POST[$key])
                ? sanitize_text_field(wp_unslash((string) $_POST[$key]))
                : '';
        }

        \JMReferral\Pipeline\PipelineInternalTargets::update($input);

        echo '<div class="notice notice-success is-dismissible"><p>';
        echo esc_html__('Pipeline internal targets saved successfully.', 'jm-referral-system');
        echo '</p></div>';
    }

    private function render_pipeline_internal_targets(): void
    {
        $targets = \JMReferral\Pipeline\PipelineInternalTargets::all();

        echo '<h2>' . esc_html__('Pipeline Internal Targets', 'jm-referral-system') . '</h2>';
        echo '<p>';
        echo esc_html__(
            'Optional internal targets used to highlight referrals that have been waiting longer than expected. These are operational targets, not contractual SLAs.',
            'jm-referral-system'
        );
        echo '</p>';
        echo '<p class="description">';
        echo esc_html__(
            'Leave a field blank (or zero) to disable the target for that stage. Changing targets recalculates dashboard attention immediately without rewriting referral records.',
            'jm-referral-system'
        );
        echo '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=jm-referrals-settings')) . '">';
        wp_nonce_field('jmrs_save_pipeline_internal_targets', 'jmrs_pipeline_internal_targets_nonce');

        echo '<table class="form-table" role="presentation"><tbody>';

        foreach (\JMReferral\Pipeline\PipelineInternalTargets::configurable_stages() as $slug) {
            $hours = $targets[$slug] ?? null;
            $field_id = 'jmrs_target_' . $slug;
            $label = \JMReferral\Pipeline\PipelineStage::label($slug);
            echo '<tr><th scope="row"><label for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label></th><td>';
            echo '<input type="number" class="small-text" min="0" max="' . esc_attr((string) \JMReferral\Pipeline\PipelineInternalTargets::MAX_HOURS) . '" step="1" name="' . esc_attr($field_id) . '" id="' . esc_attr($field_id) . '" value="' . esc_attr(null === $hours ? '' : (string) $hours) . '" /> ';
            echo esc_html__('hours', 'jm-referral-system');
            echo '</td></tr>';
        }

        echo '</tbody></table>';

        submit_button(
            __('Save Pipeline Internal Targets', 'jm-referral-system'),
            'primary',
            'jmrs_save_pipeline_internal_targets',
            false
        );
        echo '</form>';
    }
}
