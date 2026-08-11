<?php

namespace JMReferral\Portal;

/**
 * Portal URL helpers.
 */
class PortalUrls
{
    public static function home(): string
    {
        return home_url('/' . PortalSettings::base_path() . '/');
    }

    public static function dashboard(): string
    {
        return self::home();
    }

    public static function management(): string
    {
        return home_url('/' . PortalSettings::base_path() . '/management/');
    }

    /**
     * @param array<string, scalar> $args
     */
    public static function management_with_args(array $args): string
    {
        return add_query_arg($args, self::management());
    }

    public static function referrals(): string
    {
        return home_url('/' . PortalSettings::base_path() . '/referrals/');
    }

    public static function referral(int $referral_id): string
    {
        return home_url('/' . PortalSettings::base_path() . '/referrals/' . max(0, $referral_id) . '/');
    }

    public static function referral_edit(int $referral_id): string
    {
        return self::referral_path($referral_id, 'edit');
    }

    public static function referral_assessment(int $referral_id): string
    {
        return self::referral_path($referral_id, 'assessment');
    }

    public static function referral_care_plan(int $referral_id): string
    {
        return self::referral_path($referral_id, 'care-plan');
    }

    public static function care_plan_review(int $referral_id): string
    {
        return self::referral_path($referral_id, 'care-plan/review');
    }

    public static function medication_new(int $referral_id): string
    {
        return self::referral_path($referral_id, 'medications/new');
    }

    public static function medication_edit(int $referral_id, int $medication_id): string
    {
        return self::referral_path($referral_id, 'medications/' . max(0, $medication_id) . '/edit');
    }

    public static function care_team_new(int $referral_id): string
    {
        return self::referral_path($referral_id, 'care-team/new');
    }

    public static function care_team_edit(int $referral_id, int $assignment_id): string
    {
        return self::referral_path($referral_id, 'care-team/' . max(0, $assignment_id) . '/edit');
    }

    public static function schedule_new(int $referral_id): string
    {
        return self::referral_path($referral_id, 'schedules/new');
    }

    public static function schedule_edit(int $referral_id, int $schedule_id): string
    {
        return self::referral_path($referral_id, 'schedules/' . max(0, $schedule_id) . '/edit');
    }

    public static function schedule_generate(int $referral_id, int $schedule_id): string
    {
        return self::referral_path($referral_id, 'schedules/' . max(0, $schedule_id) . '/generate');
    }

    public static function visit_new(int $referral_id): string
    {
        return self::referral_path($referral_id, 'visits/new');
    }

    public static function visit_edit(int $referral_id, int $visit_id): string
    {
        return self::referral_path($referral_id, 'visits/' . max(0, $visit_id) . '/edit');
    }

    public static function visit_execute(int $referral_id, int $visit_id): string
    {
        return self::referral_path($referral_id, 'visits/' . max(0, $visit_id) . '/execute');
    }

    public static function visit_review(int $referral_id, int $visit_id): string
    {
        return self::referral_path($referral_id, 'visits/' . max(0, $visit_id) . '/review');
    }

    public static function homes(): string
    {
        return home_url('/' . PortalSettings::base_path() . '/homes/');
    }

    public static function home_view(int $home_id): string
    {
        return home_url('/' . PortalSettings::base_path() . '/homes/' . max(0, $home_id) . '/');
    }

    public static function home_new(): string
    {
        return home_url('/' . PortalSettings::base_path() . '/homes/new/');
    }

    public static function home_edit(int $home_id): string
    {
        return home_url('/' . PortalSettings::base_path() . '/homes/' . max(0, $home_id) . '/edit/');
    }

    public static function bedroom_new(int $home_id): string
    {
        return home_url(
            '/' . PortalSettings::base_path()
            . '/homes/' . max(0, $home_id)
            . '/bedrooms/new/'
        );
    }

    public static function bedroom_edit(int $home_id, int $bedroom_id): string
    {
        return home_url(
            '/' . PortalSettings::base_path()
            . '/homes/' . max(0, $home_id)
            . '/bedrooms/' . max(0, $bedroom_id)
            . '/edit/'
        );
    }

    public static function occupancy(): string
    {
        return home_url('/' . PortalSettings::base_path() . '/occupancy/');
    }

    public static function occupancy_place(array $args = []): string
    {
        $url = home_url('/' . PortalSettings::base_path() . '/occupancy/place/');

        return [] === $args ? $url : add_query_arg($args, $url);
    }

    public static function occupancy_transfer(int $occupancy_id): string
    {
        return home_url(
            '/' . PortalSettings::base_path()
            . '/occupancy/' . max(0, $occupancy_id)
            . '/transfer/'
        );
    }

    public static function occupancy_end(int $occupancy_id): string
    {
        return home_url(
            '/' . PortalSettings::base_path()
            . '/occupancy/' . max(0, $occupancy_id)
            . '/end/'
        );
    }

    /**
     * @param array<string, scalar> $args
     */
    public static function referrals_with_args(array $args): string
    {
        return add_query_arg($args, self::referrals());
    }

    public static function is_portal_url(string $url): bool
    {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || '' === $path) {
            return false;
        }

        $base = '/' . trim(PortalSettings::base_path(), '/') . '/';

        return str_starts_with(trailingslashit($path), $base)
            || rtrim($path, '/') === rtrim($base, '/');
    }

    private static function referral_path(int $referral_id, string $suffix): string
    {
        $suffix = trim($suffix, '/');

        return home_url(
            '/' . PortalSettings::base_path()
            . '/referrals/' . max(0, $referral_id)
            . '/' . $suffix . '/'
        );
    }
}
