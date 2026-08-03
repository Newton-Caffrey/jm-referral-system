<?php

namespace JMReferral\Database;

class Tables
{
    /**
     * Returns the referrals table name with the WordPress prefix.
     */
    public static function referrals_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_referrals';
    }

    /**
     * Returns the referral activity table name with the WordPress prefix.
     */
    public static function referral_activity_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_referral_activity';
    }

    /**
     * Returns the referral notes table name with the WordPress prefix.
     */
    public static function referral_notes_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_referral_notes';
    }

    /**
     * Returns the service types table name with the WordPress prefix.
     */
    public static function service_types_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_service_types';
    }

    /**
     * Returns the workflow stages table name with the WordPress prefix.
     */
    public static function workflow_stages_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_workflow_stages';
    }

    /**
     * Returns the referral documents table name with the WordPress prefix.
     */
    public static function referral_documents_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_referral_documents';
    }

    /**
     * Returns the referral assessments table name with the WordPress prefix.
     */
    public static function referral_assessments_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_referral_assessments';
    }

    /**
     * Creates or updates plugin database tables using dbDelta.
     */
    public static function create(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        self::create_referrals_table($charset);
        self::create_referral_activity_table($charset);
        self::create_referral_notes_table($charset);
        self::create_service_types_table($charset);
        self::create_workflow_stages_table($charset);
        self::create_referral_documents_table($charset);
        self::create_referral_assessments_table($charset);
    }

    /**
     * Creates or updates the referrals table.
     */
    private static function create_referrals_table(string $charset): void
    {
        $table = self::referrals_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_number VARCHAR(50) NOT NULL,
            client_name VARCHAR(255) NOT NULL,
            client_email VARCHAR(255) NULL,
            client_phone VARCHAR(50) NULL,
            service_required VARCHAR(255) NULL,
            referrer_name VARCHAR(255) NULL,
            referrer_email VARCHAR(255) NULL,
            priority VARCHAR(50) DEFAULT 'medium',
            notes TEXT NULL,
            status VARCHAR(50) DEFAULT 'new',
            assigned_to BIGINT UNSIGNED NULL,
            referral_source VARCHAR(50) NULL,
            care_start_date DATE NULL,
            preferred_contact_method VARCHAR(50) NULL,
            care_requirements LONGTEXT NULL,
            service_type_id BIGINT UNSIGNED NULL,
            workflow_stage_id BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY priority (priority),
            KEY assigned_to (assigned_to),
            KEY referral_source (referral_source),
            KEY service_type_id (service_type_id),
            KEY workflow_stage_id (workflow_stage_id)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the referral activity table.
     */
    private static function create_referral_activity_table(string $charset): void
    {
        $table = self::referral_activity_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(100) NOT NULL,
            description TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY referral_id (referral_id),
            KEY user_id (user_id),
            KEY action (action)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the referral notes table.
     */
    private static function create_referral_notes_table(string $charset): void
    {
        $table = self::referral_notes_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            note LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY referral_id (referral_id),
            KEY user_id (user_id)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the service types table.
     */
    private static function create_service_types_table(string $charset): void
    {
        $table = self::service_types_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT NULL,
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY status (status)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the workflow stages table.
     */
    private static function create_workflow_stages_table(string $charset): void
    {
        $table = self::workflow_stages_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT NULL,
            stage_order INT NOT NULL DEFAULT 0,
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY stage_order (stage_order)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the referral documents table.
     */
    private static function create_referral_documents_table(string $charset): void
    {
        $table = self::referral_documents_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT UNSIGNED NOT NULL,
            attachment_id BIGINT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            uploaded_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY referral_id (referral_id),
            KEY attachment_id (attachment_id),
            KEY uploaded_by (uploaded_by)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the referral assessments table.
     */
    private static function create_referral_assessments_table(string $charset): void
    {
        $table = self::referral_assessments_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT UNSIGNED NOT NULL,
            assessor_user_id BIGINT UNSIGNED NOT NULL,
            assessment_date DATE NULL,
            outcome VARCHAR(50) NOT NULL DEFAULT 'pending',
            summary LONGTEXT NULL,
            recommendations LONGTEXT NULL,
            next_review_date DATE NULL,
            mobility_support LONGTEXT NULL,
            personal_care_support LONGTEXT NULL,
            medication_support LONGTEXT NULL,
            nutrition_hydration LONGTEXT NULL,
            communication_needs LONGTEXT NULL,
            cognitive_needs LONGTEXT NULL,
            continence_support LONGTEXT NULL,
            home_environment LONGTEXT NULL,
            safeguarding_risks LONGTEXT NULL,
            equipment_required LONGTEXT NULL,
            family_support LONGTEXT NULL,
            visit_frequency VARCHAR(100) NULL,
            visit_duration VARCHAR(100) NULL,
            preferred_visit_times LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY referral_id (referral_id),
            KEY assessor_user_id (assessor_user_id),
            KEY outcome (outcome)
        ) {$charset};";

        dbDelta($sql);
    }
}
