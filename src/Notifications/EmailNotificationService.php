<?php

namespace JMReferral\Notifications;

class EmailNotificationService
{
    public function __construct(
        private ?EmailTemplateResolver $template_resolver = null
    ) {
        $this->template_resolver ??= new EmailTemplateResolver();
    }

    /**
     * Sends an HTML email using a template file.
     *
     * @param array<string, mixed> $vars Template variables.
     */
    public function send(string $to, string $subject, string $template, array $vars = []): bool
    {
        $to = sanitize_email($to);

        if ('' === $to || ! is_email($to)) {
            return false;
        }

        $body = $this->render($template, $vars);

        if ('' === $body) {
            return false;
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Renders an email template to an HTML string.
     *
     * @param array<string, mixed> $vars Template variables.
     */
    public function render(string $template, array $vars = []): string
    {
        $path = $this->template_resolver->resolve($template);

        if (null === $path) {
            return '';
        }

        // Make variables available to the template.
        extract($vars, EXTR_SKIP);

        ob_start();

        try {
            include $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- generic only; no exception details.
            error_log('JM Referral System: an email template could not be rendered.');

            return '';
        }

        $content = ob_get_clean();

        return is_string($content) ? $content : '';
    }
}
