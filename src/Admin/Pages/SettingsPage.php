<?php

namespace JMReferral\Admin\Pages;

use JMReferral\Documents\PrivateDocumentStorage;
use JMReferral\Documents\ReferralDocumentService;
use JMReferral\Frontend\PublicReferralSettings;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\PortalSettings;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\ReferralDependencyRepository;

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

    private function maybe_save_public_referral_settings(): void
    {
        if (! isset($_POST['jmrs_save_public_referral_settings'])) {
            return;
        }

        check_admin_referer('jmrs_save_public_referral_settings', 'jmrs_public_referral_settings_nonce');

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
                'company_name'       => isset($_POST['jmrs_company_name'])
                    ? wp_unslash((string) $_POST['jmrs_company_name'])
                    : '',
                'public_heading'     => isset($_POST['jmrs_public_heading'])
                    ? wp_unslash((string) $_POST['jmrs_public_heading'])
                    : '',
                'public_intro'       => isset($_POST['jmrs_public_intro'])
                    ? wp_unslash((string) $_POST['jmrs_public_intro'])
                    : '',
                'contact_phone'      => isset($_POST['jmrs_public_contact_phone'])
                    ? wp_unslash((string) $_POST['jmrs_public_contact_phone'])
                    : '',
                'contact_email'      => isset($_POST['jmrs_public_contact_email'])
                    ? wp_unslash((string) $_POST['jmrs_public_contact_email'])
                    : '',
                'primary_colour'     => isset($_POST['jmrs_primary_colour'])
                    ? wp_unslash((string) $_POST['jmrs_primary_colour'])
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

        echo '<tr><th scope="row"><label for="jmrs_company_name">' . esc_html__('Company Name', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_company_name" id="jmrs_company_name" value="' . esc_attr((string) $settings['company_name']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_public_heading">' . esc_html__('Public Referral Heading', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_public_heading" id="jmrs_public_heading" value="' . esc_attr((string) $settings['public_heading']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_public_intro">' . esc_html__('Public Referral Intro', 'jm-referral-system') . '</label></th><td>';
        echo '<textarea class="large-text" rows="5" name="jmrs_public_intro" id="jmrs_public_intro">' . esc_textarea((string) $settings['public_intro']) . '</textarea>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_public_contact_phone">' . esc_html__('Public Referral Contact Phone', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_public_contact_phone" id="jmrs_public_contact_phone" value="' . esc_attr((string) $settings['contact_phone']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_public_contact_email">' . esc_html__('Public Referral Contact Email', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="email" class="regular-text" name="jmrs_public_contact_email" id="jmrs_public_contact_email" value="' . esc_attr((string) $settings['contact_email']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_primary_colour">' . esc_html__('Primary Brand Colour', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_primary_colour" id="jmrs_primary_colour" value="' . esc_attr((string) $settings['primary_colour']) . '" placeholder="#0b5f4b" />';
        echo '<p class="description">' . esc_html__('Hex colour used by the public wizard (for example #0b5f4b).', 'jm-referral-system') . '</p>';
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

        $result = PortalSettings::update(
            [
                'enabled'            => ! empty($_POST['jmrs_portal_enabled']),
                'portal_name'        => isset($_POST['jmrs_portal_name'])
                    ? wp_unslash((string) $_POST['jmrs_portal_name'])
                    : PortalSettings::DEFAULT_PORTAL_NAME,
                'company_name'       => isset($_POST['jmrs_portal_company_name'])
                    ? wp_unslash((string) $_POST['jmrs_portal_company_name'])
                    : PortalSettings::DEFAULT_COMPANY_NAME,
                'base_path'          => isset($_POST['jmrs_portal_base_path'])
                    ? wp_unslash((string) $_POST['jmrs_portal_base_path'])
                    : PortalSettings::DEFAULT_BASE_PATH,
                'logo_url'           => isset($_POST['jmrs_portal_logo_url'])
                    ? wp_unslash((string) $_POST['jmrs_portal_logo_url'])
                    : '',
                'primary_colour'     => isset($_POST['jmrs_portal_primary_colour'])
                    ? wp_unslash((string) $_POST['jmrs_portal_primary_colour'])
                    : PortalSettings::DEFAULT_PRIMARY,
                'secondary_colour'   => isset($_POST['jmrs_portal_secondary_colour'])
                    ? wp_unslash((string) $_POST['jmrs_portal_secondary_colour'])
                    : PortalSettings::DEFAULT_SECONDARY,
                'support_email'      => isset($_POST['jmrs_portal_support_email'])
                    ? wp_unslash((string) $_POST['jmrs_portal_support_email'])
                    : '',
                'support_phone'      => isset($_POST['jmrs_portal_support_phone'])
                    ? wp_unslash((string) $_POST['jmrs_portal_support_phone'])
                    : '',
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
            'Optional frontend portal for JM staff. Disabled by default. Reuses existing capabilities, AccessPolicy, and referral services. WordPress Admin remains available for administrators.',
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

        echo '<tr><th scope="row"><label for="jmrs_portal_name">' . esc_html__('Portal Name', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_portal_name" id="jmrs_portal_name" value="' . esc_attr((string) $settings['portal_name']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_portal_company_name">' . esc_html__('Company Name', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_portal_company_name" id="jmrs_portal_company_name" value="' . esc_attr((string) $settings['company_name']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_portal_base_path">' . esc_html__('Portal Base Path', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_portal_base_path" id="jmrs_portal_base_path" value="' . esc_attr((string) $settings['base_path']) . '" />';
        echo '<p class="description">' . esc_html__('URL slug only (default: staff-portal). Changing this flushes rewrite rules once.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_portal_logo_url">' . esc_html__('Logo URL', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="url" class="regular-text" name="jmrs_portal_logo_url" id="jmrs_portal_logo_url" value="' . esc_attr((string) $settings['logo_url']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_portal_primary_colour">' . esc_html__('Primary Colour', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_portal_primary_colour" id="jmrs_portal_primary_colour" value="' . esc_attr((string) $settings['primary_colour']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_portal_secondary_colour">' . esc_html__('Secondary Colour', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_portal_secondary_colour" id="jmrs_portal_secondary_colour" value="' . esc_attr((string) $settings['secondary_colour']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_portal_support_email">' . esc_html__('Support Email', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="email" class="regular-text" name="jmrs_portal_support_email" id="jmrs_portal_support_email" value="' . esc_attr((string) $settings['support_email']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_portal_support_phone">' . esc_html__('Support Phone', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="text" class="regular-text" name="jmrs_portal_support_phone" id="jmrs_portal_support_phone" value="' . esc_attr((string) $settings['support_phone']) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_portal_login_redirect_url">' . esc_html__('Login Redirect URL', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="url" class="regular-text" name="jmrs_portal_login_redirect_url" id="jmrs_portal_login_redirect_url" value="' . esc_attr((string) $settings['login_redirect_url']) . '" />';
        echo '<p class="description">' . esc_html__('Optional. JM staff login redirect when redirect_to is not a portal URL. Leave blank to use the portal dashboard.', 'jm-referral-system') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Redirect JMRS Staff Away From wp-admin', 'jm-referral-system') . '</th><td>';
        echo '<label><input type="checkbox" name="jmrs_portal_redirect_wp_admin" value="1" ' . checked(! empty($settings['redirect_wp_admin']), true, false) . ' /> ';
        echo esc_html__('Send non-administrator JM staff from wp-admin screens to the portal (keep off until tested)', 'jm-referral-system') . '</label>';
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
