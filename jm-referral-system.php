<?php
/**
 * Plugin Name: J&M Referral System
 * Plugin URI: https://example.com
 * Description: Referral and Compliance Management System
 * Version: 1.2.0
 * Author: J&M Healthcare
 * Text Domain: jm-referral-system
 * License: Proprietary (pending final licence decision)
 * License URI: https://example.com
 */

if (!defined('ABSPATH')) {
    exit;
}

define('JMRS_VERSION', '1.2.0');
define('JMRS_PLUGIN_FILE', __FILE__);
define('JMRS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('JMRS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once JMRS_PLUGIN_PATH . 'vendor/autoload.php';

use JMReferral\Core\Activator;
use JMReferral\Core\Deactivator;
use JMReferral\Core\Plugin;

register_activation_hook(
    __FILE__,
    [Activator::class, 'activate']
);

register_deactivation_hook(
    __FILE__,
    [Deactivator::class, 'deactivate']
);

function jmrs_run_plugin()
{
    $plugin = new Plugin();
    $plugin->run();
}

jmrs_run_plugin();