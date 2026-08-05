<?php

namespace JMReferral\Core;

use JMReferral\Database\Migrator;
use JMReferral\Documents\PrivateDocumentStorage;
use JMReferral\Permissions\Capabilities;
use JMReferral\Permissions\Roles;
use JMReferral\Portal\PortalRouter;

class Activator
{
    public static function activate(): void
    {
        Migrator::maybe_migrate();
        Capabilities::grant_to_administrators();
        Roles::register();

        $storage = new PrivateDocumentStorage();
        $storage->ensure_ready();

        // Register portal rules (no-op when disabled) and flush once.
        PortalRouter::flush_rules();
    }
}
