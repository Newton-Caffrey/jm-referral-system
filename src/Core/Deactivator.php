<?php

namespace JMReferral\Core;

/**
 * Deactivation intentionally leaves roles and capabilities in place.
 *
 * Cleanup of JM roles and JMRS capabilities happens in uninstall.php only.
 */
class Deactivator
{
    public static function deactivate(): void
    {
        // Intentionally empty — do not remove roles or capabilities on deactivate.
    }
}