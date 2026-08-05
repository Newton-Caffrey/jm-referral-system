<?php

namespace JMReferral\Notifications;

use JMReferral\Referral\ReferralViewController;
use JMReferral\Users\UserProvider;

class NotificationService
{
    private const TEMPLATE_CREATED  = 'referral-created';
    private const TEMPLATE_ASSIGNED = 'referral-assigned';
    private const TEMPLATE_STATUS   = 'status-changed';

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

    private function format_label(string $value): string
    {
        if ('' === $value) {
            return '';
        }

        return ucfirst(str_replace('_', ' ', $value));
    }
}
