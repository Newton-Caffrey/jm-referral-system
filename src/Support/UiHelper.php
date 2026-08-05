<?php

namespace JMReferral\Support;

/**
 * Small UI helpers for consistent badges and empty states (no business logic).
 */
class UiHelper
{
    /**
     * Renders a status/priority/archive badge.
     *
     * @param string $label   Visible text.
     * @param string $variant One of: neutral, info, success, warning, danger, critical,
     *                        priority-low|medium|high|urgent, status-*, archive, alert-*.
     */
    public static function badge(string $label, string $variant = 'neutral'): string
    {
        $label = trim($label);
        if ('' === $label) {
            return '';
        }

        $variant = sanitize_html_class($variant);
        if ('' === $variant) {
            $variant = 'neutral';
        }

        return sprintf(
            '<span class="jmrs-badge jmrs-badge--%1$s">%2$s</span>',
            esc_attr($variant),
            esc_html($label)
        );
    }

    /**
     * Priority badge from raw priority key.
     */
    public static function priority_badge(string $priority): string
    {
        $priority = sanitize_key($priority);
        $labels   = [
            'low'    => __('Low', 'jm-referral-system'),
            'medium' => __('Medium', 'jm-referral-system'),
            'high'   => __('High', 'jm-referral-system'),
            'urgent' => __('Urgent', 'jm-referral-system'),
        ];

        if (! isset($labels[$priority])) {
            $text = ucfirst(str_replace('_', ' ', $priority));

            return '' !== $text ? self::badge($text, 'neutral') : '';
        }

        return self::badge($labels[$priority], 'priority-' . $priority);
    }

    /**
     * Generic status badge (referral / visit / care plan / medication).
     */
    public static function status_badge(string $status, string $label = ''): string
    {
        $status = sanitize_key($status);
        if ('' === $status) {
            return '';
        }

        if ('' === $label) {
            $label = ucfirst(str_replace('_', ' ', $status));
        }

        $map = [
            'new'           => 'info',
            'in_progress'   => 'info',
            'active'        => 'success',
            'completed'     => 'success',
            'scheduled'     => 'info',
            'paused'        => 'warning',
            'cancelled'     => 'danger',
            'missed'        => 'warning',
            'discontinued'  => 'neutral',
            'draft'         => 'neutral',
            'archived'      => 'archive',
            'given'         => 'success',
            'refused'       => 'warning',
            'omitted'       => 'warning',
            'error'         => 'danger',
            'unavailable'   => 'warning',
            'client_absent' => 'warning',
            'not_required'  => 'neutral',
        ];

        $variant = $map[$status] ?? 'neutral';

        return self::badge($label, $variant);
    }

    /**
     * Alert severity badge.
     */
    public static function alert_badge(string $severity, string $label = ''): string
    {
        $severity = sanitize_key($severity);
        if ('' === $label) {
            $label = ucfirst($severity);
        }

        $variant = 'neutral';
        if ('critical' === $severity) {
            $variant = 'alert-critical';
        } elseif ('warning' === $severity) {
            $variant = 'alert-warning';
        } elseif ('information' === $severity) {
            $variant = 'alert-information';
        }

        return self::badge($label, $variant);
    }

    /**
     * Empty-state block for tables/sections.
     *
     * @param string      $message Primary message.
     * @param string|null $action_html Optional safe HTML for a follow-up action (already escaped).
     */
    public static function empty_state(string $message, ?string $action_html = null): string
    {
        $html = '<div class="jmrs-empty-state" role="status">';
        $html .= '<p class="jmrs-empty-state__message">' . esc_html($message) . '</p>';

        if (null !== $action_html && '' !== trim($action_html)) {
            $html .= '<p class="jmrs-empty-state__action">' . $action_html . '</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
