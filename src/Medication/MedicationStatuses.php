<?php

namespace JMReferral\Medication;

class MedicationStatuses
{
    public const ACTIVE = 'active';
    public const PAUSED = 'paused';
    public const DISCONTINUED = 'discontinued';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::ACTIVE,
            self::PAUSED,
            self::DISCONTINUED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ACTIVE        => __('Active', 'jm-referral-system'),
            self::PAUSED        => __('Paused', 'jm-referral-system'),
            self::DISCONTINUED  => __('Discontinued', 'jm-referral-system'),
        ];
    }

    public static function is_valid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
