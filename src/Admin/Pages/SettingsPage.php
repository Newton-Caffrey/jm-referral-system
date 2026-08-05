<?php

namespace JMReferral\Admin\Pages;

use JMReferral\Documents\PrivateDocumentStorage;
use JMReferral\Documents\ReferralDocumentService;
use JMReferral\Permissions\Capabilities;

class SettingsPage
{
    public function __construct(
        private ?ReferralDocumentService $document_service = null
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

        echo '</div>';
    }
}
