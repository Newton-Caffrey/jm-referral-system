<?php

namespace JMReferral\Admin\Pages;

use JMReferral\Documents\PrivateDocumentStorage;
use JMReferral\Documents\ReferralDocumentService;
use JMReferral\Frontend\PublicReferralSettings;
use JMReferral\Permissions\Capabilities;
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
}
