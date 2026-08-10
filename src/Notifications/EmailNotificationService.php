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
     * @param array<int, string>   $attachments Absolute server filesystem paths only.
     *                                          Empty array = behaviour identical to pre-attachment API.
     */
    public function send(
        string $to,
        string $subject,
        string $template,
        array $vars = [],
        array $attachments = []
    ): bool {
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

        $safe_attachments = $this->filter_attachment_paths($attachments);

        try {
            if ([] === $safe_attachments) {
                return wp_mail($to, $subject, $body, $headers);
            }

            return wp_mail($to, $subject, $body, $headers, $safe_attachments);
        } catch (\Throwable $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- no PHI; mail transport only.
            error_log('[JMRS] email send failed (transport exception)');

            return false;
        }
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

    /**
     * Keeps only readable absolute paths (never request-supplied unverified strings beyond caller trust).
     *
     * @param array<int, string> $attachments
     * @return array<int, string>
     */
    private function filter_attachment_paths(array $attachments): array
    {
        $safe = [];

        foreach ($attachments as $path) {
            if (! is_string($path) || '' === $path) {
                continue;
            }

            // Reject relative / traversal-looking values; callers must resolve via PrivateDocumentStorage.
            if (str_contains($path, "\0") || str_contains($path, '..')) {
                continue;
            }

            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $safe[] = $path;
        }

        return $safe;
    }
}
