<?php

namespace JMReferral\Admin\Pages;

use JMReferral\Mailbox\MailboxConnectionConstants;
use JMReferral\Mailbox\MailboxConnectionStatus;
use JMReferral\Mailbox\MicrosoftConnectionService;
use JMReferral\Permissions\Capabilities;

/**
 * wp-admin Microsoft 365 connection settings (Phase 5C.1).
 *
 * Capability: jmrs_manage_settings. No Graph / OAuth / token calls.
 */
class Microsoft365SettingsPage
{
    private const NOTICE_TRANSIENT = 'jmrs_m365_settings_notice_';

    public function __construct(
        private MicrosoftConnectionService $service
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_post']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    public function render(): void
    {
        $this->require_capability();

        $view = $this->service->get_safe_view();
        $conn = is_array($view['connection'] ?? null) ? $view['connection'] : null;

        $tenant_id       = (string) ($conn['tenant_id'] ?? '');
        $client_id       = (string) ($conn['client_id'] ?? '');
        $mailbox_address = (string) ($conn['mailbox_address'] ?? '');
        $mailbox_type    = (string) ($conn['mailbox_type'] ?? MailboxConnectionConstants::MAILBOX_SHARED);
        $enabled         = (bool) ($conn['is_enabled'] ?? false);
        $has_secret      = (bool) ($view['has_client_secret'] ?? false);
        $encryption      = is_array($view['encryption'] ?? null) ? $view['encryption'] : ['status' => 'missing', 'message' => ''];
        $status_label    = $this->status_label((string) ($view['computed_status'] ?? MailboxConnectionStatus::NOT_CONFIGURED), $enabled);
        $page_url        = admin_url('admin.php?page=jm-referrals-microsoft-365');

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Microsoft 365', 'jm-referral-system') . '</h1>';

        echo '<p class="description">';
        echo esc_html__(
            'Configure a customer-owned Microsoft Entra application and referral mailbox for this JMRS installation. Microsoft verification and mailbox sync are added in a later phase.',
            'jm-referral-system'
        );
        echo '</p>';

        echo '<h2>' . esc_html__('Connection Status', 'jm-referral-system') . '</h2>';
        echo '<p><strong>' . esc_html($status_label) . '</strong></p>';

        echo '<h2>' . esc_html__('Microsoft 365', 'jm-referral-system') . '</h2>';
        echo '<form method="post" action="' . esc_url($page_url) . '" autocomplete="off">';
        wp_nonce_field('jmrs_m365_save', 'jmrs_m365_save_nonce');
        echo '<input type="hidden" name="jmrs_m365_action" value="save" />';

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="jmrs_m365_tenant_id">' . esc_html__('Tenant ID', 'jm-referral-system') . '</label></th>';
        echo '<td><input type="text" class="regular-text" name="tenant_id" id="jmrs_m365_tenant_id" value="' . esc_attr($tenant_id) . '" maxlength="64" autocomplete="off" />';
        echo '<p class="description">' . esc_html__('Directory (tenant) ID from the customer Entra app registration.', 'jm-referral-system') . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_m365_client_id">' . esc_html__('Application / Client ID', 'jm-referral-system') . '</label></th>';
        echo '<td><input type="text" class="regular-text" name="client_id" id="jmrs_m365_client_id" value="' . esc_attr($client_id) . '" maxlength="64" autocomplete="off" />';
        echo '<p class="description">' . esc_html__('Application (client) ID from the customer-owned single-tenant Entra application.', 'jm-referral-system') . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_m365_mailbox">' . esc_html__('Referral Mailbox Address', 'jm-referral-system') . '</label></th>';
        echo '<td><input type="email" class="regular-text" name="mailbox_address" id="jmrs_m365_mailbox" value="' . esc_attr($mailbox_address) . '" maxlength="190" autocomplete="off" />';
        echo '<p class="description">' . esc_html__('User or shared mailbox that receives referral emails.', 'jm-referral-system') . '</p></td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Mailbox Type', 'jm-referral-system') . '</th><td>';
        echo '<fieldset><legend class="screen-reader-text">' . esc_html__('Mailbox Type', 'jm-referral-system') . '</legend>';
        echo '<label><input type="radio" name="mailbox_type" value="shared"' . checked($mailbox_type, MailboxConnectionConstants::MAILBOX_SHARED, false) . ' /> ';
        echo esc_html__('Shared mailbox', 'jm-referral-system') . '</label><br />';
        echo '<label><input type="radio" name="mailbox_type" value="user"' . checked($mailbox_type, MailboxConnectionConstants::MAILBOX_USER, false) . ' /> ';
        echo esc_html__('User mailbox', 'jm-referral-system') . '</label>';
        echo '</fieldset></td></tr>';

        echo '<tr><th scope="row"><label for="jmrs_m365_client_secret">' . esc_html__('Client Secret', 'jm-referral-system') . '</label></th><td>';
        echo '<input type="password" class="regular-text" name="client_secret" id="jmrs_m365_client_secret" value="" autocomplete="new-password" />';
        if ($has_secret) {
            echo '<p class="description"><strong>' . esc_html__('Client secret stored securely', 'jm-referral-system') . '</strong>. ';
            echo esc_html__('Leave blank to keep the existing secret.', 'jm-referral-system') . '</p>';
        } else {
            echo '<p class="description">' . esc_html__('No client secret stored', 'jm-referral-system') . '</p>';
        }
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Encryption Status', 'jm-referral-system') . '</th><td>';
        echo '<strong>' . esc_html($this->encryption_label((string) ($encryption['status'] ?? ''))) . '</strong>';
        if (! empty($encryption['message'])) {
            echo '<p class="description">' . esc_html((string) $encryption['message']) . '</p>';
        }
        echo '</td></tr>';

        echo '</tbody></table>';

        submit_button(__('Save Microsoft 365 Configuration', 'jm-referral-system'), 'primary', 'submit', false);

        echo '</form>';

        echo '<h2>' . esc_html__('Actions', 'jm-referral-system') . '</h2>';

        // Test Connection — informational only in 5C.1.
        echo '<p>';
        echo '<button type="button" class="button" disabled="disabled">';
        echo esc_html__('Test Connection', 'jm-referral-system');
        echo '</button> ';
        echo '<span class="description">' . esc_html__('Microsoft verification is added in Phase 5C.2.', 'jm-referral-system') . '</span>';
        echo '</p>';

        if ($view['exists']) {
            if ($enabled) {
                echo '<form method="post" action="' . esc_url($page_url) . '" style="display:inline-block;margin-right:8px;">';
                wp_nonce_field('jmrs_m365_disable', 'jmrs_m365_disable_nonce');
                echo '<input type="hidden" name="jmrs_m365_action" value="disable" />';
                submit_button(__('Disable Integration', 'jm-referral-system'), 'secondary', 'submit', false);
                echo '</form>';
            } elseif (! empty($view['can_enable'])) {
                echo '<form method="post" action="' . esc_url($page_url) . '" style="display:inline-block;margin-right:8px;">';
                wp_nonce_field('jmrs_m365_enable', 'jmrs_m365_enable_nonce');
                echo '<input type="hidden" name="jmrs_m365_action" value="enable" />';
                submit_button(__('Enable Integration', 'jm-referral-system'), 'secondary', 'submit', false);
                echo '</form>';
            }

            echo '<form method="post" action="' . esc_url($page_url) . '" style="display:inline-block;" onsubmit="return confirm(\'';
            echo esc_js(__('Remove Microsoft 365 configuration and encrypted credentials? Referral Inbox and referrals are not deleted.', 'jm-referral-system'));
            echo '\');">';
            wp_nonce_field('jmrs_m365_remove', 'jmrs_m365_remove_nonce');
            echo '<input type="hidden" name="jmrs_m365_action" value="remove" />';
            echo '<input type="hidden" name="jmrs_m365_confirm_remove" value="1" />';
            submit_button(__('Remove Microsoft 365 Configuration', 'jm-referral-system'), 'delete', 'submit', false);
            echo '</form>';
        }

        echo '</div>';
    }

    public function handle_post(): void
    {
        if (! is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('jm-referrals-microsoft-365' !== $page) {
            return;
        }

        if ('POST' !== strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''))) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to manage settings.', 'jm-referral-system'));
        }

        $action = isset($_POST['jmrs_m365_action']) ? sanitize_key(wp_unslash($_POST['jmrs_m365_action'])) : '';

        switch ($action) {
            case 'save':
                $this->handle_save();
                break;
            case 'disable':
                $this->handle_disable();
                break;
            case 'enable':
                $this->handle_enable();
                break;
            case 'remove':
                $this->handle_remove();
                break;
            default:
                return;
        }
    }

    public function render_notices(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SETTINGS)) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('jm-referrals-microsoft-365' !== $page) {
            return;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        $key  = self::NOTICE_TRANSIENT . $user_id;
        $data = get_transient($key);
        delete_transient($key);

        if (! is_array($data) || empty($data['messages']) || ! is_array($data['messages'])) {
            return;
        }

        $type = ('error' === ($data['type'] ?? '')) ? 'error' : 'success';
        foreach ($data['messages'] as $message) {
            $message = (string) $message;
            if ('' === $message) {
                continue;
            }
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }

    private function handle_save(): void
    {
        check_admin_referer('jmrs_m365_save', 'jmrs_m365_save_nonce');

        $result = $this->service->save_microsoft_connection(
            [
                'tenant_id'       => isset($_POST['tenant_id']) ? sanitize_text_field(wp_unslash($_POST['tenant_id'])) : '',
                'client_id'       => isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '',
                'mailbox_address' => isset($_POST['mailbox_address']) ? sanitize_email(wp_unslash($_POST['mailbox_address'])) : '',
                'mailbox_type'    => isset($_POST['mailbox_type']) ? sanitize_key(wp_unslash($_POST['mailbox_type'])) : '',
                'client_secret'   => isset($_POST['client_secret']) ? (string) wp_unslash($_POST['client_secret']) : '',
            ]
        );

        if ($result['ok'] ?? false) {
            $this->flash('success', [__('Microsoft 365 configuration saved.', 'jm-referral-system')]);
        } else {
            $messages = $result['messages'] ?? [__('Could not save Microsoft 365 configuration.', 'jm-referral-system')];
            $this->flash('error', array_map('strval', $messages));
        }

        $this->redirect();
    }

    private function handle_disable(): void
    {
        check_admin_referer('jmrs_m365_disable', 'jmrs_m365_disable_nonce');

        $result = $this->service->disable();
        if ($result['ok'] ?? false) {
            $this->flash('success', [__('Microsoft 365 integration disabled.', 'jm-referral-system')]);
        } else {
            $this->flash('error', array_map('strval', $result['messages'] ?? [__('Could not disable integration.', 'jm-referral-system')]));
        }

        $this->redirect();
    }

    private function handle_enable(): void
    {
        check_admin_referer('jmrs_m365_enable', 'jmrs_m365_enable_nonce');

        $result = $this->service->enable();
        if ($result['ok'] ?? false) {
            $this->flash('success', [__('Microsoft 365 integration enabled.', 'jm-referral-system')]);
        } else {
            $this->flash('error', array_map('strval', $result['messages'] ?? [__('Could not enable integration.', 'jm-referral-system')]));
        }

        $this->redirect();
    }

    private function handle_remove(): void
    {
        check_admin_referer('jmrs_m365_remove', 'jmrs_m365_remove_nonce');

        $confirmed = isset($_POST['jmrs_m365_confirm_remove']) && '1' === (string) wp_unslash($_POST['jmrs_m365_confirm_remove']);
        if (! $confirmed) {
            $this->flash('error', [__('Removal requires confirmation.', 'jm-referral-system')]);
            $this->redirect();
        }

        $result = $this->service->remove();
        if ($result['ok'] ?? false) {
            $this->flash('success', [__('Microsoft 365 configuration removed.', 'jm-referral-system')]);
        } else {
            $this->flash('error', array_map('strval', $result['messages'] ?? [__('Could not remove configuration.', 'jm-referral-system')]));
        }

        $this->redirect();
    }

    /**
     * @param list<string> $messages
     */
    private function flash(string $type, array $messages): void
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        set_transient(
            self::NOTICE_TRANSIENT . $user_id,
            [
                'type'     => $type,
                'messages' => $messages,
            ],
            60
        );
    }

    private function redirect(): void
    {
        wp_safe_redirect(admin_url('admin.php?page=jm-referrals-microsoft-365'));
        exit;
    }

    private function require_capability(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to manage settings.', 'jm-referral-system'));
        }
    }

    private function status_label(string $status, bool $enabled): string
    {
        return match ($status) {
            MailboxConnectionStatus::NOT_CONFIGURED => __('Not configured', 'jm-referral-system'),
            MailboxConnectionStatus::CONFIGURED => $enabled
                ? __('Configured — not yet verified', 'jm-referral-system')
                : __('Configured — disabled', 'jm-referral-system'),
            MailboxConnectionStatus::DISABLED => __('Disabled', 'jm-referral-system'),
            MailboxConnectionStatus::CONNECTED => __('Configured — not yet verified', 'jm-referral-system'),
            MailboxConnectionStatus::ATTENTION_REQUIRED => __('Attention required', 'jm-referral-system'),
            MailboxConnectionStatus::REAUTHORIZATION_REQUIRED => __('Reauthorization required', 'jm-referral-system'),
            MailboxConnectionStatus::ERROR => __('Error', 'jm-referral-system'),
            default => __('Not configured', 'jm-referral-system'),
        };
    }

    private function encryption_label(string $status): string
    {
        return match ($status) {
            'ready' => __('Ready', 'jm-referral-system'),
            'missing' => __('Key missing', 'jm-referral-system'),
            'invalid' => __('Key invalid', 'jm-referral-system'),
            'unavailable' => __('Secure encryption unavailable', 'jm-referral-system'),
            default => __('Key missing', 'jm-referral-system'),
        };
    }
}
