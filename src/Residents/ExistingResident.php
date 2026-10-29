<?php

namespace JMReferral\Residents;

/**
 * Phase 5F.1: constants and labels for registering clients who already receive care.
 *
 * An existing resident is stored on the normal care record (jmrs_referrals) so every
 * clinical, occupancy and dashboard feature works unchanged. It is marked with
 * record_origin = existing_resident so referral (acquisition) reporting can leave it out.
 */
class ExistingResident
{
    public const ORIGIN = 'existing_resident';

    public const FUNDING_LOCAL_AUTHORITY = 'local_authority';
    public const FUNDING_NHS_CHC = 'nhs_chc';
    public const FUNDING_JOINT = 'joint';
    public const FUNDING_SELF = 'self_funded';
    public const FUNDING_OTHER = 'other';

    /** Funding arrangement source marker for rows written at registration. */
    public const FUNDING_SOURCE = 'existing_resident';

    public const MAX_IMPORT_ROWS = 500;
    public const MAX_IMPORT_BYTES = 1048576;

    public const NAME_MAX = 100;
    public const NOTES_MAX = 5000;
    public const PACKAGE_SUMMARY_MAX = 2000;
    public const REFERENCE_MAX = 190;
    public const FUNDER_NAME_MAX = 255;
    public const WEEKLY_AMOUNT_MAX = 100000;
    public const WEEKLY_HOURS_MAX = 168;

    /**
     * @return array<string, string>
     */
    public static function funding_type_labels(): array
    {
        return [
            self::FUNDING_LOCAL_AUTHORITY => __('Local Authority', 'jm-referral-system'),
            self::FUNDING_NHS_CHC         => __('NHS Continuing Healthcare', 'jm-referral-system'),
            self::FUNDING_JOINT           => __('Joint funded (Local Authority and NHS)', 'jm-referral-system'),
            self::FUNDING_SELF            => __('Self-funded', 'jm-referral-system'),
            self::FUNDING_OTHER           => __('Other', 'jm-referral-system'),
        ];
    }

    public static function funding_type_label(string $type): string
    {
        $labels = self::funding_type_labels();

        return $labels[$type] ?? '';
    }

    public static function is_valid_funding_type(string $type): bool
    {
        return isset(self::funding_type_labels()[$type]);
    }

    /**
     * Maps spreadsheet wording to a funding type key ('' when not recognised).
     */
    public static function funding_type_from_text(string $text): string
    {
        $key = strtolower(trim($text));
        $key = (string) preg_replace('/[^a-z0-9]+/', ' ', $key);
        $key = trim($key);

        if ('' === $key) {
            return '';
        }

        $map = [
            'local authority'                  => self::FUNDING_LOCAL_AUTHORITY,
            'la'                               => self::FUNDING_LOCAL_AUTHORITY,
            'council'                          => self::FUNDING_LOCAL_AUTHORITY,
            'local authority funded'           => self::FUNDING_LOCAL_AUTHORITY,
            'nhs'                              => self::FUNDING_NHS_CHC,
            'nhs chc'                          => self::FUNDING_NHS_CHC,
            'chc'                              => self::FUNDING_NHS_CHC,
            'nhs continuing healthcare'        => self::FUNDING_NHS_CHC,
            'continuing healthcare'            => self::FUNDING_NHS_CHC,
            'joint'                            => self::FUNDING_JOINT,
            'joint funded'                     => self::FUNDING_JOINT,
            'joint funded local authority and nhs' => self::FUNDING_JOINT,
            'self'                             => self::FUNDING_SELF,
            'self funded'                      => self::FUNDING_SELF,
            'private'                          => self::FUNDING_SELF,
            'other'                            => self::FUNDING_OTHER,
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        $underscored = str_replace(' ', '_', $key);

        return self::is_valid_funding_type($underscored) ? $underscored : '';
    }

    public static function is_existing_resident(?array $referral): bool
    {
        return is_array($referral) && self::ORIGIN === (string) ($referral['record_origin'] ?? '');
    }
}
