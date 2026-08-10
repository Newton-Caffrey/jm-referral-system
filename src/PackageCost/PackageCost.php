<?php

namespace JMReferral\PackageCost;

/**
 * Package Cost submission constants and labels.
 */
class PackageCost
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PREPARED = 'prepared';
    public const STATUS_SENT = 'sent';

    public const METHOD_EMAIL = 'email';
    public const METHOD_SECURE_PORTAL = 'secure_portal';
    public const METHOD_OTHER = 'other';

    public const CURRENCY_GBP = 'GBP';

    /**
     * Document extensions allowed for Package Cost attachments.
     *
     * @var array<int, string>
     */
    public const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx'];

    /**
     * @return array<string, string>
     */
    public static function status_labels(): array
    {
        return [
            self::STATUS_DRAFT    => __('Draft', 'jm-referral-system'),
            self::STATUS_PREPARED => __('Prepared', 'jm-referral-system'),
            self::STATUS_SENT     => __('Sent', 'jm-referral-system'),
        ];
    }

    public static function status_label(string $status): string
    {
        $labels = self::status_labels();

        return $labels[$status] ?? $status;
    }

    /**
     * @return array<string, string>
     */
    public static function send_method_labels(): array
    {
        return [
            self::METHOD_EMAIL         => __('Email', 'jm-referral-system'),
            self::METHOD_SECURE_PORTAL => __('Secure Local Authority Portal', 'jm-referral-system'),
            self::METHOD_OTHER         => __('Other', 'jm-referral-system'),
        ];
    }

    public static function send_method_label(string $method): string
    {
        $labels = self::send_method_labels();

        return $labels[$method] ?? $method;
    }

    public static function is_valid_send_method(string $method): bool
    {
        return array_key_exists($method, self::send_method_labels());
    }

    public static function is_sent(string $status): bool
    {
        return self::STATUS_SENT === $status;
    }

    public static function is_prepared(string $status): bool
    {
        return self::STATUS_PREPARED === $status;
    }

    /**
     * Formats a GBP amount for display (e.g. £2,450.00).
     */
    public static function format_total(?string $amount, string $currency = self::CURRENCY_GBP): string
    {
        if (null === $amount || '' === trim($amount)) {
            return '';
        }

        $value = (float) $amount;
        $formatted = number_format($value, 2, '.', ',');

        if (self::CURRENCY_GBP === strtoupper($currency)) {
            return '£' . $formatted;
        }

        return $currency . ' ' . $formatted;
    }
}
