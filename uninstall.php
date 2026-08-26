<?php
/**
 * Uninstall cleanup for J&M Referral System.
 *
 * Runs only when the plugin is deleted from WordPress.
 * Does not run on deactivate.
 *
 * Default: remove JM roles/capabilities only; preserve custom tables and private files.
 * Opt-in wipe: define('JMRS_DELETE_DATA_ON_UNINSTALL', true); in wp-config.php
 *
 * Multisite: this file runs in the context of the site where the plugin is deleted.
 * Network-wide deletion should be planned per site; tables use $wpdb->prefix for the
 * current blog. Legacy Media Library attachments are never deleted automatically.
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

use JMReferral\Database\Tables;
use JMReferral\Documents\PrivateDocumentStorage;
use JMReferral\Permissions\Capabilities;
use JMReferral\Permissions\Roles;

Roles::remove();
Capabilities::revoke_from_administrators();

if (! defined('JMRS_DELETE_DATA_ON_UNINSTALL') || true !== JMRS_DELETE_DATA_ON_UNINSTALL) {
    return;
}

global $wpdb;

$tables = [
    Tables::medication_administrations_table(),
    Tables::medications_table(),
    Tables::visit_tasks_table(),
    Tables::care_visits_table(),
    Tables::visit_schedules_table(),
    Tables::care_team_table(),
    Tables::care_plan_reviews_table(),
    Tables::care_plan_versions_table(),
    Tables::referral_care_plans_table(),
    Tables::referral_assessments_table(),
    Tables::referral_meeting_attendees_table(),
    Tables::referral_meetings_table(),
    Tables::referral_documents_table(),
    Tables::referral_notes_table(),
    Tables::referral_activity_table(),
    Tables::service_types_table(),
    Tables::workflow_stages_table(),
    Tables::referrals_table(),
];

foreach ($tables as $table) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted table names from Tables helpers.
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

delete_option('jmrs_db_version');

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('jmrs_') . '%',
        $wpdb->esc_like('_transient_jmrs_') . '%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_timeout_jmrs_') . '%'
    )
);

$storage = new PrivateDocumentStorage();
$root    = $storage->get_root_path();

if ('' !== $root && is_dir($root)) {
    jmrs_uninstall_rrmdir($root);
}

/**
 * Recursively removes a directory tree under private storage only.
 */
function jmrs_uninstall_rrmdir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if (! is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ('.' === $item || '..' === $item) {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            jmrs_uninstall_rrmdir($path);
            continue;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- uninstall cleanup.
        @unlink($path);
    }

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- uninstall cleanup.
    @rmdir($dir);
}
