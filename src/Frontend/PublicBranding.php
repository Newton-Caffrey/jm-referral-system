<?php

namespace JMReferral\Frontend;

/**
 * Centralized public-facing branding for the referral wizard and success receipt.
 */
class PublicBranding
{
    public const DEFAULT_COMPANY_NAME = 'JM Healthcare';
    public const DEFAULT_PRIMARY_COLOUR = '#0b5f4b';

    /**
     * @return array{
     *   company_name: string,
     *   heading: string,
     *   intro: string,
     *   contact_phone: string,
     *   contact_email: string,
     *   primary_colour: string,
     *   success_next_steps: string
     * }
     */
    public static function all(): array
    {
        $settings = PublicReferralSettings::all();

        return [
            'company_name'       => self::company_name($settings),
            'heading'            => self::heading($settings),
            'intro'              => self::intro($settings),
            'contact_phone'      => (string) ($settings['contact_phone'] ?? ''),
            'contact_email'      => (string) ($settings['contact_email'] ?? ''),
            'primary_colour'     => self::primary_colour($settings),
            'success_next_steps' => self::success_next_steps($settings),
        ];
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    public static function company_name(?array $settings = null): string
    {
        $settings = $settings ?? PublicReferralSettings::all();
        $name     = trim((string) ($settings['company_name'] ?? ''));

        return '' !== $name ? $name : self::DEFAULT_COMPANY_NAME;
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    public static function heading(?array $settings = null): string
    {
        $settings = $settings ?? PublicReferralSettings::all();
        $heading  = trim((string) ($settings['public_heading'] ?? ''));

        return '' !== $heading
            ? $heading
            : __('Make a Referral', 'jm-referral-system');
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    public static function intro(?array $settings = null): string
    {
        $settings = $settings ?? PublicReferralSettings::all();
        $intro    = trim((string) ($settings['public_intro'] ?? ''));

        if ('' !== $intro) {
            return $intro;
        }

        return __(
            "We're here to help.\n\nCompleting this referral usually takes around 5–10 minutes.\n\nIf you do not know every answer, that is okay. Provide as much information as you can and our team will contact you if anything else is needed.",
            'jm-referral-system'
        );
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    public static function primary_colour(?array $settings = null): string
    {
        $settings = $settings ?? PublicReferralSettings::all();
        $colour   = strtolower(trim((string) ($settings['primary_colour'] ?? '')));

        if (preg_match('/^#[0-9a-f]{6}$/', $colour)) {
            return $colour;
        }

        return self::DEFAULT_PRIMARY_COLOUR;
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    public static function success_next_steps(?array $settings = null): string
    {
        $settings = $settings ?? PublicReferralSettings::all();
        $text     = trim((string) ($settings['success_next_steps'] ?? ''));

        if ('' !== $text) {
            return $text;
        }

        return __(
            "Our team will review your referral.\nWe may contact you using the details you provided.\nPlease quote your reference number in any follow-up.",
            'jm-referral-system'
        );
    }

    /**
     * Maps a public form field key to the earliest wizard step index (1–5).
     * Step 0 is Welcome (never opened for validation errors).
     *
     * @param array<string, string> $errors
     */
    public static function earliest_error_step(array $errors): int
    {
        if ([] === $errors) {
            return 1;
        }

        $map = [
            'referrer_type'            => 1,
            'referrer_name'            => 1,
            'referrer_organisation'    => 1,
            'referrer_email'           => 1,
            'referrer_phone'           => 1,
            'referrer_contact'         => 1,
            'relationship_to_client'   => 1,
            'client_first_name'        => 2,
            'client_last_name'         => 2,
            'client_email'             => 2,
            'client_phone'             => 2,
            'client_date_of_birth'     => 2,
            'address_line_1'           => 2,
            'address_line_2'           => 2,
            'city'                     => 2,
            'postcode'                 => 2,
            'service_type_id'          => 3,
            'care_start_date'          => 3,
            'preferred_contact_method' => 3,
            'priority'                 => 3,
            'care_requirements'        => 3,
            'additional_information'   => 3,
            'documents'                => 4,
            'consent_permission'       => 5,
            'consent_assessment'       => 5,
            'consent_privacy'          => 5,
            'form'                     => 5,
        ];

        $earliest = 5;

        foreach (array_keys($errors) as $key) {
            $step = $map[(string) $key] ?? 5;
            if ($step < $earliest) {
                $earliest = $step;
            }
        }

        return max(1, min(5, $earliest));
    }
}
