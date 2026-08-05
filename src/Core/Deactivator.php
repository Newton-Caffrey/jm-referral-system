<?php

namespace JMReferral\Core;

/**
 * Deactivation intentionally leaves roles and capabilities in place.
 *
 * Cleanup of JM roles and JMRS capabilities happens in uninstall.php only.
 * Portal settings are preserved.
 */
class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules(false);
    }
}