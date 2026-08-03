<?php

namespace JMReferral\Referral;

class PreferredContactMethods
{
    /**
     * Returns allowed preferred contact method values mapped to display labels.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'phone'            => __('Phone', 'jm-referral-system'),
            'email'            => __('Email', 'jm-referral-system'),
            'family_contact'   => __('Family Contact', 'jm-referral-system'),
            'referrer_contact' => __('Referrer Contact', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowed(): array
    {
        return array_keys(self::options());
    }

    /**
     * Returns the display label for a contact method value.
     */
    public static function label(string $method): string
    {
        $options = self::options();

        return $options[$method] ?? '';
    }

    /**
     * Whether the value is a valid preferred contact method.
     */
    public static function is_valid(string $method): bool
    {
        return in_array($method, self::allowed(), true);
    }
}
