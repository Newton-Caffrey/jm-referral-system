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

        // Treat empty or previous product default as the current Local Authority heading.
        if ('' === $heading || 'Make a Referral' === $heading) {
            return __('Local Authority Referral Form', 'jm-referral-system');
        }

        return $heading;
    }

    /**
     * Current product-default public referral intro (Local Authority audience).
     */
    public static function default_intro(): string
    {
        return __(
            "Use this form to securely refer an individual to J&M Healthcare for assessment and consideration of care and support services.\n\nCompleting this referral usually takes around 5–10 minutes.\n\nIf you do not know every answer, that is okay. Provide as much information as you can and our team will contact you if anything else is needed.",
            'jm-referral-system'
        );
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    public static function intro(?array $settings = null): string
    {
        $settings = $settings ?? PublicReferralSettings::all();
        $intro    = trim((string) ($settings['public_intro'] ?? ''));

        if ('' === $intro || self::is_legacy_product_intro($intro)) {
            return self::default_intro();
        }

        return $intro;
    }

    /**
     * True when saved intro equals a known former JMRS product default (not custom branding).
     */
    private static function is_legacy_product_intro(string $intro): bool
    {
        $normalized = self::normalize_intro_for_compare($intro);

        foreach (self::legacy_product_intros() as $legacy) {
            if ($normalized === self::normalize_intro_for_compare($legacy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Former product-default intro strings superseded by Local Authority wording.
     *
     * @return list<string>
     */
    private static function legacy_product_intros(): array
    {
        return [
            // Original welcome default (pre–Local Authority clarification).
            "We're here to help.\n\nCompleting this referral usually takes around 5–10 minutes.\n\nIf you do not know every answer, that is okay. Provide as much information as you can and our team will contact you if anything else is needed.",
            // Hyphen variant of the original (if en-dash was normalized on save).
            "We're here to help.\n\nCompleting this referral usually takes around 5-10 minutes.\n\nIf you do not know every answer, that is okay. Provide as much information as you can and our team will contact you if anything else is needed.",
            // Intermediate v1.3.1 draft: LA sentence only (before restoring timing / incomplete-answer guidance).
            'Use this form to securely refer an individual to J&M Healthcare for assessment and consideration of care and support services.',
        ];
    }

    private static function normalize_intro_for_compare(string $intro): string
    {
        $intro = str_replace(["\r\n", "\r"], "\n", $intro);
        $intro = trim($intro);
        // Treat en/em dashes like ASCII hyphen for "5–10" matching.
        $intro = str_replace(["\u{2013}", "\u{2014}"], '-', $intro);

        return $intro;
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
