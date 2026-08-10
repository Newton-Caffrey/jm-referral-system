<?php

namespace JMReferral\Pipeline;

/**
 * Interest response method / email status constants.
 */
class InterestResponse
{
    public const METHOD_EMAIL = 'email';
    public const METHOD_PHONE = 'phone';
    public const METHOD_OTHER = 'other';

    public const EMAIL_SENT = 'sent';
    public const EMAIL_FAILED = 'failed';
    public const EMAIL_NOT_APPLICABLE = 'not_applicable';

    /**
     * @return array<string, string>
     */
    public static function method_labels(): array
    {
        return [
            self::METHOD_EMAIL => __('Email', 'jm-referral-system'),
            self::METHOD_PHONE => __('Phone', 'jm-referral-system'),
            self::METHOD_OTHER => __('Other', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function methods(): array
    {
        return array_keys(self::method_labels());
    }

    public static function is_valid_method(string $method): bool
    {
        return in_array($method, self::methods(), true);
    }

    public static function method_label(string $method): string
    {
        $labels = self::method_labels();

        return $labels[$method] ?? $method;
    }

    public static function email_status_label(string $status): string
    {
        return match ($status) {
            self::EMAIL_SENT => __('Sent', 'jm-referral-system'),
            self::EMAIL_FAILED => __('Failed', 'jm-referral-system'),
            self::EMAIL_NOT_APPLICABLE => __('Not applicable', 'jm-referral-system'),
            default => $status,
        };
    }
}
