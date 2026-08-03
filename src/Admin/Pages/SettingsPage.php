<?php

namespace JMReferral\Admin\Pages;

use JMReferral\Permissions\Capabilities;

class SettingsPage
{
    public function render(): void
    {
        if (! Capabilities::current_user_can(Capabilities::MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to manage settings.', 'jm-referral-system'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Settings', 'jm-referral-system') . '</h1>';
        echo '<p>' . esc_html__('Settings placeholder content.', 'jm-referral-system') . '</p>';
        echo '</div>';
    }
}
