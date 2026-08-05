<?php

namespace JMReferral\Documents;

use JMReferral\Permissions\Capabilities;

/**
 * Admin-only batch migration of legacy Media Library documents into private storage.
 */
class DocumentMigrationController
{
    private const NOTICE_TRANSIENT = 'jmrs_document_migration_notice_';

    public function __construct(
        private ReferralDocumentService $document_service
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_migrate']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    /**
     * Processes one batch of legacy → private copies from Settings.
     */
    public function handle_migrate(): void
    {
        if (! isset($_POST['jmrs_migrate_legacy_documents'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to migrate documents.', 'jm-referral-system'));
        }

        check_admin_referer('jmrs_migrate_legacy_documents', 'jmrs_migrate_legacy_documents_nonce');

        $result = $this->document_service->migrate_legacy_batch();

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_notice(
                [
                    'type'    => 'error',
                    'message' => (string) reset($result['errors']),
                ]
            );
            $this->redirect_to_settings();
        }

        $migrated = absint($result['migrated'] ?? 0);
        $skipped  = absint($result['skipped'] ?? 0);
        $failed   = absint($result['failed'] ?? 0);

        $this->store_notice(
            [
                'type'     => $failed > 0 ? 'warning' : 'success',
                'migrated' => $migrated,
                'skipped'  => $skipped,
                'failed'   => $failed,
            ]
        );

        $this->redirect_to_settings();
    }

    /**
     * Renders migration result notices on the Settings screen.
     */
    public function render_notices(): void
    {
        if (! $this->is_settings_screen()) {
            return;
        }

        $notice = get_transient(self::NOTICE_TRANSIENT . get_current_user_id());

        if (! is_array($notice)) {
            return;
        }

        delete_transient(self::NOTICE_TRANSIENT . get_current_user_id());

        $type = (string) ($notice['type'] ?? 'success');

        if (isset($notice['message'])) {
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>';
            echo esc_html((string) $notice['message']);
            echo '</p></div>';

            return;
        }

        $migrated = absint($notice['migrated'] ?? 0);
        $skipped  = absint($notice['skipped'] ?? 0);
        $failed   = absint($notice['failed'] ?? 0);

        $message = sprintf(
            /* translators: 1: migrated count, 2: skipped count, 3: failed count */
            __('Document migration batch complete. Migrated: %1$d. Skipped: %2$d. Failed: %3$d.', 'jm-referral-system'),
            $migrated,
            $skipped,
            $failed
        );

        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>';
        echo esc_html($message);
        echo '</p></div>';
    }

    /**
     * @param array<string, mixed> $notice
     */
    private function store_notice(array $notice): void
    {
        set_transient(
            self::NOTICE_TRANSIENT . get_current_user_id(),
            $notice,
            MINUTE_IN_SECONDS * 5
        );
    }

    private function redirect_to_settings(): void
    {
        wp_safe_redirect(
            add_query_arg(
                ['page' => 'jm-referrals-settings'],
                admin_url('admin.php')
            )
        );
        exit;
    }

    private function is_settings_screen(): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        return 'jm-referrals-settings' === $page;
    }
}
