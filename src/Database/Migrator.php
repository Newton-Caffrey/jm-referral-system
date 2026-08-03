<?php

namespace JMReferral\Database;

use JMReferral\Permissions\Capabilities;
use JMReferral\Permissions\Roles;

class Migrator
{
    /**
     * Current database schema version.
     */
    public const DB_VERSION = '2.0.0';

    /**
     * Option key used to store the installed DB version.
     */
    public const OPTION_KEY = 'jmrs_db_version';

    /**
     * Runs pending database migrations when the installed version is behind.
     */
    public static function maybe_migrate(): void
    {
        $installed_version = (string) get_option(self::OPTION_KEY, '0');

        if (version_compare($installed_version, self::DB_VERSION, '>=')) {
            return;
        }

        self::migrate($installed_version);
        update_option(self::OPTION_KEY, self::DB_VERSION);
    }

    /**
     * Applies migrations required to reach the current schema version.
     *
     * @param string $from_version Currently installed database version.
     */
    private static function migrate(string $from_version): void
    {
        self::maybe_rename_legacy_table();

        // dbDelta creates missing tables and adds missing columns safely.
        Tables::create();

        if (version_compare($from_version, '1.7.0', '<')) {
            self::seed_default_workflow_stages();
        }

        if (version_compare($from_version, '1.8.0', '<')) {
            Capabilities::grant_to_administrators();
        }

        if (version_compare($from_version, '1.9.0', '<')) {
            Capabilities::grant_to_administrators();
            Roles::register();
        }

        if (version_compare($from_version, '2.0.0', '<')) {
            Capabilities::grant_to_administrators();
            Roles::register();
        }
    }

    /**
     * Seeds the default domiciliary care workflow stages when the table is empty.
     */
    private static function seed_default_workflow_stages(): void
    {
        global $wpdb;

        $table = Tables::workflow_stages_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        if ($count > 0) {
            return;
        }

        $now    = current_time('mysql');
        $stages = [
            ['New Referral', 'new-referral', 1],
            ['Initial Review', 'initial-review', 2],
            ['Assessment Pending', 'assessment-pending', 3],
            ['Assessment Scheduled', 'assessment-scheduled', 4],
            ['Assessment Completed', 'assessment-completed', 5],
            ['Care Plan Preparation', 'care-plan-preparation', 6],
            ['Care Started', 'care-started', 7],
            ['Ongoing Care', 'ongoing-care', 8],
            ['Completed', 'completed', 9],
        ];

        foreach ($stages as $stage) {
            $wpdb->insert(
                $table,
                [
                    'name'        => $stage[0],
                    'slug'        => $stage[1],
                    'description' => null,
                    'stage_order' => $stage[2],
                    'status'      => 'active',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                ]
            );
        }
    }

    /**
     * Renames the legacy jm_referrals table to jmrs_referrals when needed.
     *
     * Preserves existing data on installations created before the rename.
     */
    private static function maybe_rename_legacy_table(): void
    {
        global $wpdb;

        $legacy_table  = $wpdb->prefix . 'jm_referrals';
        $current_table = Tables::referrals_table();

        $legacy_exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($legacy_table))
        ) === $legacy_table;

        $current_exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($current_table))
        ) === $current_table;

        if ($legacy_exists && ! $current_exists) {
            // Table names are built from $wpdb->prefix and fixed identifiers only.
            $wpdb->query("RENAME TABLE {$legacy_table} TO {$current_table}");
        }
    }
}
