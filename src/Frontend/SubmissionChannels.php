<?php

namespace JMReferral\Frontend;

/**
 * Referral submission channel labels.
 */
class SubmissionChannels
{
    public const ADMIN = 'admin';
    public const PUBLIC_WEBSITE = 'public_website';
    /** Phase 5F.1: client already receiving care, registered without a referral history. */
    public const EXISTING_RESIDENT = 'existing_resident';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::ADMIN          => __('Admin', 'jm-referral-system'),
            self::PUBLIC_WEBSITE => __('Website', 'jm-referral-system'),
            self::EXISTING_RESIDENT => __('Existing resident (registered)', 'jm-referral-system'),
        ];
    }

    public static function label(string $channel): string
    {
        $options = self::options();

        if (isset($options[$channel])) {
            return $options[$channel];
        }

        if ('' === $channel) {
            return $options[self::ADMIN];
        }

        return ucfirst(str_replace('_', ' ', $channel));
    }

    public static function is_public(string $channel): bool
    {
        return self::PUBLIC_WEBSITE === $channel;
    }
}
