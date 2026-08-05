<?php

namespace JMReferral\Documents;

/**
 * Private filesystem storage for referral documents.
 *
 * Files live under wp-content/uploads/jmrs-private/ and must only be served
 * through the plugin download controller. Direct URL access is denied via
 * .htaccess where Apache/mod_authz is available; that is not guaranteed on
 * every host, so controller-mediated downloads remain mandatory.
 */
class PrivateDocumentStorage
{
    public const DIRECTORY_NAME = 'jmrs-private';

    public const STORAGE_LEGACY = 'legacy_attachment';

    public const STORAGE_PRIVATE = 'private_file';

    /**
     * Absolute path to the private storage root, or empty string on failure.
     */
    public function get_root_path(): string
    {
        $uploads = wp_get_upload_dir();

        if (! empty($uploads['error']) || empty($uploads['basedir'])) {
            return '';
        }

        return trailingslashit((string) $uploads['basedir']) . self::DIRECTORY_NAME;
    }

    /**
     * Ensures the private root and protection files exist.
     *
     * @return true|\WP_Error
     */
    public function ensure_ready()
    {
        $root = $this->get_root_path();

        if ('' === $root) {
            return new \WP_Error(
                'jmrs_private_storage',
                __('Private document storage is not available.', 'jm-referral-system')
            );
        }

        if (! wp_mkdir_p($root)) {
            return new \WP_Error(
                'jmrs_private_storage',
                __('Unable to create private document storage.', 'jm-referral-system')
            );
        }

        $this->write_protection_files($root);

        return true;
    }

    /**
     * Creates year/month subdirectory under the private root.
     *
     * @return string Absolute directory path, or empty on failure.
     */
    public function ensure_month_directory(?int $timestamp = null): string
    {
        $ready = $this->ensure_ready();

        if (is_wp_error($ready)) {
            return '';
        }

        $timestamp = $timestamp ?? time();
        $subdir    = gmdate('Y/m', $timestamp);
        $path      = trailingslashit($this->get_root_path()) . $subdir;

        if (! wp_mkdir_p($path)) {
            return '';
        }

        $this->write_protection_files($path);

        return $path;
    }

    /**
     * Builds a relative path from a year/month directory and stored filename.
     */
    public function build_relative_path(string $stored_name, ?int $timestamp = null): string
    {
        $timestamp   = $timestamp ?? time();
        $stored_name = $this->sanitize_stored_name($stored_name);

        return gmdate('Y/m', $timestamp) . '/' . $stored_name;
    }

    /**
     * Resolves a relative path to an absolute path only if it stays inside the private root.
     */
    public function resolve_safe_path(string $relative_path): ?string
    {
        $relative_path = $this->normalize_relative_path($relative_path);

        if (null === $relative_path) {
            return null;
        }

        $ready = $this->ensure_ready();

        if (is_wp_error($ready)) {
            return null;
        }

        $root = $this->get_root_path();
        $real_root = realpath($root);

        if (false === $real_root) {
            return null;
        }

        $candidate = $root . '/' . $relative_path;
        $real_path = realpath($candidate);

        if (false === $real_path || ! is_readable($real_path) || ! is_file($real_path)) {
            return null;
        }

        $real_root = rtrim(str_replace('\\', '/', $real_root), '/') . '/';
        $real_path_norm = str_replace('\\', '/', $real_path);

        if (! str_starts_with($real_path_norm, $real_root)) {
            return null;
        }

        return $real_path;
    }

    /**
     * Whether an absolute path is inside the private root.
     */
    public function is_path_within_private_root(string $file_path): bool
    {
        $root = $this->get_root_path();
        $real_root = realpath($root);
        $real_path = realpath($file_path);

        if (false === $real_root || false === $real_path) {
            return false;
        }

        $real_root = rtrim(str_replace('\\', '/', $real_root), '/') . '/';
        $real_path = str_replace('\\', '/', $real_path);

        return str_starts_with($real_path, $real_root);
    }

    /**
     * Generates a randomized stored filename with a safe extension.
     */
    public function generate_stored_name(string $extension): string
    {
        $extension = strtolower(preg_replace('/[^a-z0-9]/i', '', $extension) ?? '');

        if ('' === $extension) {
            $extension = 'bin';
        }

        try {
            $random = bin2hex(random_bytes(16));
        } catch (\Exception $e) {
            $random = wp_generate_password(32, false, false);
        }

        return $random . '.' . $extension;
    }

    /**
     * @return string|null Normalized relative path, or null if unsafe.
     */
    public function normalize_relative_path(string $relative_path): ?string
    {
        $relative_path = str_replace("\0", '', $relative_path);
        $relative_path = str_replace('\\', '/', $relative_path);
        $relative_path = ltrim($relative_path, '/');

        if ('' === $relative_path) {
            return null;
        }

        if (str_contains($relative_path, '..')) {
            return null;
        }

        if (! preg_match('#^\d{4}/\d{2}/[A-Za-z0-9._-]+$#', $relative_path)) {
            return null;
        }

        return $relative_path;
    }

    private function sanitize_stored_name(string $stored_name): string
    {
        $stored_name = str_replace("\0", '', $stored_name);
        $stored_name = basename(str_replace('\\', '/', $stored_name));

        return sanitize_file_name($stored_name);
    }

    private function write_protection_files(string $directory): void
    {
        $directory = trailingslashit($directory);

        $htaccess = $directory . '.htaccess';
        if (! file_exists($htaccess)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- bootstrap protection file.
            file_put_contents(
                $htaccess,
                "# JMRS private documents — deny direct web access.\n"
                . "# Plugin downloads must still use the secure controller.\n"
                . "# .htaccess may not apply on all hosts (e.g. some nginx setups).\n"
                . "<IfModule mod_authz_core.c>\n"
                . "\tRequire all denied\n"
                . "</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n"
                . "\tOrder deny,allow\n"
                . "\tDeny from all\n"
                . "</IfModule>\n"
            );
        }

        $index_php = $directory . 'index.php';
        if (! file_exists($index_php)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($index_php, "<?php\n// Silence is golden.\n");
        }

        $index_html = $directory . 'index.html';
        if (! file_exists($index_html)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($index_html, "");
        }
    }
}
