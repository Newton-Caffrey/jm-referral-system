<?php
/**
 * Uninstall cleanup for J&M Referral System.
 *
 * Runs only when the plugin is deleted from WordPress.
 * Does not run on deactivate.
 *
 * @package JMReferral
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';

if (! is_readable($autoload)) {
    return;
}

require_once $autoload;

use JMReferral\Permissions\Capabilities;
use JMReferral\Permissions\Roles;

Roles::remove();
Capabilities::revoke_from_administrators();
