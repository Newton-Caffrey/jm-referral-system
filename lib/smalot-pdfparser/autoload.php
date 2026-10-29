<?php
/**
 * On-demand loader for the bundled smalot/pdfparser library.
 *
 * Registers a class loader for the Smalot\PdfParser namespace only. It is
 * included by PdfTextReader when a PDF is read, not on every request.
 *
 * @package JMReferral
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('jmrs_register_pdfparser_autoloader')) {
    function jmrs_register_pdfparser_autoloader(): void
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $registered = true;
        $base       = __DIR__ . '/src/';

        spl_autoload_register(
            static function (string $class) use ($base): void {
                $prefix = 'Smalot\\PdfParser\\';
                if (0 !== strncmp($class, $prefix, strlen($prefix))) {
                    return;
                }

                if (1 !== preg_match('/^[A-Za-z0-9_\\\\]+$/', $class)) {
                    return;
                }

                $file = $base . str_replace('\\', '/', $class) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        );
    }
}

jmrs_register_pdfparser_autoloader();
