<?php

namespace JMReferral\Core;

use JMReferral\Database\Migrator;
use JMReferral\Permissions\Capabilities;
use JMReferral\Permissions\Roles;

class Activator
{
    public static function activate(): void
    {
        Migrator::maybe_migrate();
        Capabilities::grant_to_administrators();
        Roles::register();
    }
}
