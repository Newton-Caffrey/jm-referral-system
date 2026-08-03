<?php

namespace JMReferral\Notifications;

class EmailNotificationService
{
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
        $template = sanitize_file_name($template);
        $path     = JMRS_PLUGIN_PATH . 'src/Notifications/templates/' . $template . '.php';

        if (! is_readable($path)) {
            return '';
        }

        // Make variables available to the template.
        extract($vars, EXTR_SKIP);

        ob_start();
        include $path;
        $content = ob_get_clean();

        return is_string($content) ? $content : '';
    }
}
