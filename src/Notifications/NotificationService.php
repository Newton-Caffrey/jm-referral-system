<?php

namespace JMReferral\Notifications;

use JMReferral\Frontend\PublicBranding;
use JMReferral\Frontend\PublicReferralSettings;
use JMReferral\Frontend\ReferrerTypes;
use JMReferral\Referral\ReferralViewController;
use JMReferral\Users\UserProvider;

class NotificationService
{
    private const TEMPLATE_CREATED  = 'referral-created';
    private const TEMPLATE_ASSIGNED = 'referral-assigned';
    private const TEMPLATE_STATUS   = 'status-changed';
    private const TEMPLATE_PUBLIC_RECEIVED = 'public-referral-received';
    private const TEMPLATE_PUBLIC_CONFIRM  = 'public-referral-confirmation';
    private const TEMPLATE_INTEREST        = 'interest-expressed';
    private const TEMPLATE_PACKAGE_COST    = 'package-cost-sent';

    public function __construct(
        private EmailNotificationService $email_service,
        private UserProvider $user_provider
    ) {
    }

    /**
     * Notifies the assigned user that a new referral was created.
     *
     * @param array<string, mixed> $referral
     */
    public function notify_referral_created(array $referral): void
    {
        $assigned_to = absint($referral['assigned_to'] ?? 0);

        if ($assigned_to <= 0) {
            return;
        }

        $email = $this->user_provider->get_email($assigned_to);

        if ('' === $email) {
            return;
        }

        $context = $this->build_context($referral);

        $subject = sprintf(
            /* translators: %s: referral number */
            __('[%1$s] New referral assigned: %2$s', 'jm-referral-system'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            $context['referral_number']
        );

        $this->email_service->send($email, $subject, self::TEMPLATE_CREATED, $context);
    }

    /**
     * Notifies the new assignee that a referral was assigned to them.
     *
     * @param array<string, mixed> $referral
     */
    public function notify_referral_assigned(array $referral): void
    {
        $assigned_to = absint($referral['assigned_to'] ?? 0);

        if ($assigned_to <= 0) {
            return;
        }

        $email = $this->user_provider->get_email($assigned_to);

        if ('' === $email) {
            return;
        }

        $context = $this->build_context($referral);

        $subject = sprintf(
            /* translators: %s: referral number */
            __('[%1$s] Referral assigned to you: %2$s', 'jm-referral-system'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            $context['referral_number']
        );

        $this->email_service->send($email, $subject, self::TEMPLATE_ASSIGNED, $context);
    }

    /**
     * Notifies the assigned user that a referral status changed.
     *
     * @param array<string, mixed> $referral
     */
    public function notify_status_changed(array $referral, string $old_status, string $new_status): void
    {
        $assigned_to = absint($referral['assigned_to'] ?? 0);

        if ($assigned_to <= 0) {
            return;
        }

        $email = $this->user_provider->get_email($assigned_to);

        if ('' === $email) {
            return;
        }

        $context               = $this->build_context($referral);
        $context['old_status'] = $this->format_label($old_status);
        $context['new_status'] = $this->format_label($new_status);

        $subject = sprintf(
            /* translators: %s: referral number */
            __('[%1$s] Referral status updated: %2$s', 'jm-referral-system'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            $context['referral_number']
        );

        $this->email_service->send($email, $subject, self::TEMPLATE_STATUS, $context);
    }

    /**
     * Notifies the configured operations inbox about a public website referral.
     *
     * @param array<string, mixed> $referral
     */
    public function notify_public_referral_received(array $referral): void
    {
        $email = PublicReferralSettings::notification_email();

        if ('' === $email) {
            return;
        }

        $context = $this->build_public_ops_context($referral);

        $subject = sprintf(
            /* translators: %s: referral number */
            __('New website referral: %s', 'jm-referral-system'),
            $context['referral_number']
        );

        $this->email_service->send($email, $subject, self::TEMPLATE_PUBLIC_RECEIVED, $context);
    }

    /**
     * Sends a confirmation email to the public referrer when an email was supplied.
     *
     * @param array<string, mixed> $referral
     */
    public function notify_public_referral_confirmation(array $referral): void
    {
        $email = (string) ($referral['referrer_email'] ?? '');

        if ('' === $email || ! is_email($email)) {
            return;
        }

        $settings = PublicReferralSettings::all();
        $contact_email = (string) ($settings['contact_email'] ?? '');
        if ('' === $contact_email || ! is_email($contact_email)) {
            $contact_email = (string) get_option('admin_email');
        }

        $context = [
            'referral_number' => (string) ($referral['referral_number'] ?? ''),
            'referrer_name'   => (string) ($referral['referrer_name'] ?? ''),
            'site_name'       => PublicBranding::company_name($settings),
            'company_name'    => PublicBranding::company_name($settings),
            'site_url'        => home_url('/'),
            'admin_email'     => $contact_email,
            'contact_phone'   => (string) ($settings['contact_phone'] ?? ''),
        ];

        $subject = sprintf(
            /* translators: 1: site name, 2: referral number */
            __('[%1$s] We received your referral (%2$s)', 'jm-referral-system'),
            $context['site_name'],
            $context['referral_number']
        );

        $this->email_service->send($email, $subject, self::TEMPLATE_PUBLIC_CONFIRM, $context);
    }

    /**
     * Sends the Local Authority / referrer interest confirmation email.
     *
     * Returns true when wp_mail accepts the message for sending (not delivery proof).
     *
     * @param array<string, mixed> $referral
     */
    public function notify_interest_expressed(array $referral, string $to_email): bool
    {
        $to_email = sanitize_email($to_email);
        if ('' === $to_email || ! is_email($to_email)) {
            return false;
        }

        $settings = PublicReferralSettings::all();
        $contact_email = (string) ($settings['contact_email'] ?? '');
        if ('' === $contact_email || ! is_email($contact_email)) {
            $contact_email = (string) get_option('admin_email');
        }

        $company = PublicBranding::company_name($settings);
        $context = [
            'referral_number' => (string) ($referral['referral_number'] ?? ''),
            'referrer_name'   => (string) ($referral['referrer_name'] ?? ''),
            'site_name'       => $company,
            'company_name'    => $company,
            'site_url'        => home_url('/'),
            'admin_email'     => $contact_email,
            'contact_phone'   => (string) ($settings['contact_phone'] ?? ''),
        ];

        $subject = sprintf(
            /* translators: 1: company name, 2: referral number */
            __('%1$s — Interest in Referral %2$s', 'jm-referral-system'),
            $company,
            $context['referral_number']
        );

        return $this->email_service->send($to_email, $subject, self::TEMPLATE_INTEREST, $context);
    }

    /**
     * Sends Package Cost email to the referrer with a private-document attachment.
     *
     * Returns true when wp_mail accepts the message for sending (not delivery proof).
     *
     * @param array<string, mixed> $referral
     * @param array<int, string>   $attachment_paths Absolute readable server paths only.
     */
    public function notify_package_cost_sent(array $referral, string $to_email, array $attachment_paths): bool
    {
        $to_email = sanitize_email($to_email);
        if ('' === $to_email || ! is_email($to_email)) {
            return false;
        }

        if ([] === $attachment_paths) {
            return false;
        }

        $settings = PublicReferralSettings::all();
        $contact_email = (string) ($settings['contact_email'] ?? '');
        if ('' === $contact_email || ! is_email($contact_email)) {
            $contact_email = (string) get_option('admin_email');
        }

        $company = PublicBranding::company_name($settings);
        $context = [
            'referral_number' => (string) ($referral['referral_number'] ?? ''),
            'referrer_name'   => (string) ($referral['referrer_name'] ?? ''),
            'site_name'       => $company,
            'company_name'    => $company,
            'site_url'        => home_url('/'),
            'admin_email'     => $contact_email,
            'contact_phone'   => (string) ($settings['contact_phone'] ?? ''),
        ];

        $subject = sprintf(
            /* translators: 1: company name, 2: referral number */
            __('%1$s — Package Cost for Referral %2$s', 'jm-referral-system'),
            $company,
            $context['referral_number']
        );

        return $this->email_service->send(
            $to_email,
            $subject,
            self::TEMPLATE_PACKAGE_COST,
            $context,
            $attachment_paths
        );
    }

    /**
     * @param array<string, mixed> $referral
     * @return array<string, string>
     */
    private function build_context(array $referral): array
    {
        $referral_id = absint($referral['id'] ?? 0);

        return [
            'referral_number'  => (string) ($referral['referral_number'] ?? ''),
            'client_name'      => (string) ($referral['client_name'] ?? ''),
            'service_required' => (string) ($referral['service_required'] ?? ''),
            'priority'         => $this->format_label((string) ($referral['priority'] ?? '')),
            'status'           => $this->format_label((string) ($referral['status'] ?? '')),
            'view_url'         => ReferralViewController::get_view_url($referral_id),
            'site_name'        => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
        ];
    }

    /**
     * Summary context for ops — no full care requirements body.
     *
     * @param array<string, mixed> $referral
     * @return array<string, string>
     */
    private function build_public_ops_context(array $referral): array
    {
        $base = $this->build_context($referral);
        $referrer_type = (string) ($referral['referrer_type'] ?? '');

        return array_merge($base, [
            'referrer_name' => (string) ($referral['referrer_name'] ?? ''),
            'referrer_type' => '' !== $referrer_type
                ? ReferrerTypes::label($referrer_type)
                : '',
            'public_priority' => $this->format_label((string) ($referral['priority'] ?? '')),
        ]);
    }

    private function format_label(string $value): string
    {
        if ('' === $value) {
            return '';
        }

        return ucfirst(str_replace('_', ' ', $value));
    }
}
