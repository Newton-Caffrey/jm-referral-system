<?php

namespace JMReferral\Visits;

use JMReferral\Referral\CareSetting;

/**
 * Presentation helpers for ServiceLocation (no DB queries).
 */
class ServiceLocationPresenter
{
    /**
     * Short label for tables / compact lists (no full street address).
     */
    public static function short_label(ServiceLocation $location): string
    {
        if ($location->is_historical()
            && ServiceLocation::SOURCE_LEGACY_UNRECORDED === $location->source()
        ) {
            return __('Not Recorded', 'jm-referral-system');
        }

        if (! $location->is_resolved()) {
            return __('Unresolved', 'jm-referral-system');
        }

        $label = trim($location->label());
        if ('' !== $label) {
            return $label;
        }

        return __('Unresolved', 'jm-referral-system');
    }

    /**
     * Soft operational warning copy, or null when none needed.
     */
    public static function warning_message(ServiceLocation $location): ?string
    {
        if ($location->is_historical()) {
            return null;
        }

        $care_setting = $location->care_setting();

        if (CareSetting::is_unspecified($care_setting) && ! $location->is_resolved()) {
            return __('Care setting has not been specified.', 'jm-referral-system');
        }

        if (CareSetting::is_supported_living($care_setting) && ! $location->is_resolved()) {
            return __('No active Supported Living placement. Service location is unresolved.', 'jm-referral-system');
        }

        if (CareSetting::is_own_home($care_setting) && ! $location->is_address_complete()) {
            return __("Client's own-home address is incomplete.", 'jm-referral-system');
        }

        if (! $location->is_resolved()) {
            return __('Service location is unresolved.', 'jm-referral-system');
        }

        return null;
    }

    /**
     * Panel heading for a prepared location in a given UI context.
     *
     * @param 'referral'|'schedule'|'visit_current'|'visit_historical'|'execute'|'review'|'cancelled' $context
     */
    public static function heading(string $context, ServiceLocation $location): string
    {
        return match ($context) {
            'referral', 'schedule' => __('Current Service Location', 'jm-referral-system'),
            'visit_current' => __('Current Service Location', 'jm-referral-system'),
            'visit_historical', 'review' => __('Service Location at Time of Visit', 'jm-referral-system'),
            'execute' => __('Care Will Be Recorded At', 'jm-referral-system'),
            'cancelled' => __('Recorded Service Location', 'jm-referral-system'),
            default => __('Service Location', 'jm-referral-system'),
        };
    }

    /**
     * Whether this visit is cancelled/missed without an execution snapshot.
     *
     * @param array<string, mixed> $visit
     */
    public static function is_terminal_without_snapshot(array $visit): bool
    {
        $status = strtolower(trim((string) ($visit['visit_status'] ?? '')));
        if (! in_array($status, ['cancelled', 'missed'], true)) {
            return false;
        }

        if ('' !== trim((string) ($visit['visit_outcome'] ?? ''))) {
            return false;
        }

        return '' === trim((string) ($visit['service_location_recorded_at'] ?? ''))
            && '' === trim((string) ($visit['service_location_type'] ?? ''))
            && '' === trim((string) ($visit['service_location_label'] ?? ''));
    }

    /**
     * Format recorded_at for display.
     */
    public static function format_recorded_at(?string $recorded_at): string
    {
        if (null === $recorded_at || '' === trim($recorded_at)) {
            return '';
        }

        return (string) mysql2date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $recorded_at
        );
    }

    /**
     * @return array{lines: array<int, string>, has_address: bool}
     */
    public static function address_lines(ServiceLocation $location): array
    {
        $lines = [];
        foreach ([$location->address_line_1(), $location->address_line_2()] as $line) {
            if (null !== $line && '' !== $line) {
                $lines[] = $line;
            }
        }

        $city_post = trim(
            implode(
                ', ',
                array_filter([$location->city(), $location->postcode()])
            )
        );
        if ('' !== $city_post) {
            $lines[] = $city_post;
        }

        return [
            'lines'       => $lines,
            'has_address' => [] !== $lines,
        ];
    }

    /**
     * View bag for the service-location partial (no queries).
     *
     * @param array{
     *     heading?: string,
     *     show_warning?: bool,
     *     warning?: string|null,
     *     show_recorded_at?: bool,
     *     compact?: bool,
     *     unavailable_message?: string|null,
     *     secondary_heading?: string|null,
     *     secondary_location?: ServiceLocation|null
     * } $options
     * @return array<string, mixed>
     */
    public static function panel_vars(ServiceLocation $location, array $options = []): array
    {
        $warning = array_key_exists('warning', $options)
            ? $options['warning']
            : self::warning_message($location);

        $show_warning = array_key_exists('show_warning', $options)
            ? (bool) $options['show_warning']
            : (null !== $warning && '' !== $warning);

        $address = self::address_lines($location);

        return [
            'service_location'            => $location->to_array(),
            'service_location_heading'    => (string) ($options['heading'] ?? self::heading('referral', $location)),
            'service_location_label'      => $location->label(),
            'service_location_resolved'   => $location->is_resolved(),
            'service_location_historical' => $location->is_historical(),
            'service_location_address_lines' => $address['lines'],
            'service_location_has_address'   => $address['has_address'],
            'service_location_show_warning'  => $show_warning,
            'service_location_warning'       => $warning,
            'service_location_show_recorded_at' => ! empty($options['show_recorded_at'])
                && null !== $location->recorded_at(),
            'service_location_recorded_at_display' => self::format_recorded_at($location->recorded_at()),
            'service_location_compact'     => ! empty($options['compact']),
            'service_location_unavailable' => (string) ($options['unavailable_message'] ?? ''),
            'service_location_secondary_heading' => (string) ($options['secondary_heading'] ?? ''),
            'service_location_secondary'   => null !== ($options['secondary_location'] ?? null)
                && $options['secondary_location'] instanceof ServiceLocation
                ? self::panel_vars($options['secondary_location'], [
                    'heading'      => (string) ($options['secondary_heading'] ?? ''),
                    'show_warning' => false,
                    'compact'      => false,
                ])
                : null,
        ];
    }
}
