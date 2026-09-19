<?php

namespace JMReferral\Services;

use JMReferral\Database\Tables;

/**
 * Seeds a minimal neutral service catalogue for empty installs only.
 * Never overwrites existing rows (existing JM catalogues stay intact).
 */
class ServiceTypeSeeder
{
    /**
     * @return list<array{name: string, slug: string, description: string}>
     */
    public static function default_catalogue(): array
    {
        return [
            [
                'name'        => 'Home Care',
                'slug'        => 'home-care',
                'description' => 'Domiciliary / home care support.',
            ],
            [
                'name'        => 'Supported Living',
                'slug'        => 'supported-living',
                'description' => 'Supported living accommodation and support.',
            ],
            [
                'name'        => 'Residential Care',
                'slug'        => 'residential-care',
                'description' => 'Residential care services.',
            ],
        ];
    }

    /**
     * Inserts defaults only when the service types table has zero rows.
     */
    public static function seed_if_empty(): void
    {
        global $wpdb;

        $table = Tables::service_types_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is trusted.
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count > 0) {
            return;
        }

        $now = current_time('mysql');
        foreach (self::default_catalogue() as $row) {
            $wpdb->insert(
                $table,
                [
                    'name'        => $row['name'],
                    'slug'        => $row['slug'],
                    'description' => $row['description'],
                    'status'      => 'active',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );
        }
    }
}
