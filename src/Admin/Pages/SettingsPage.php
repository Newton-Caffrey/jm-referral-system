<?php

namespace JMReferral\Admin\Pages;

use JMReferral\Documents\PrivateDocumentStorage;
use JMReferral\Documents\ReferralDocumentService;
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
}
