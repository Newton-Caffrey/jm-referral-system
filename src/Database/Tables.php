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
     * Returns the referral pipeline stage history table name with the WordPress prefix.
     */
    public static function referral_stage_history_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_referral_stage_history';
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
     * Returns the referral care plans table name with the WordPress prefix.
     */
    public static function referral_care_plans_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_referral_care_plans';
    }

    /**
     * Returns the care plan versions table name with the WordPress prefix.
     */
    public static function care_plan_versions_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_care_plan_versions';
    }

    /**
     * Returns the care plan reviews table name with the WordPress prefix.
     */
    public static function care_plan_reviews_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_care_plan_reviews';
    }

    /**
     * Returns the care visits table name with the WordPress prefix.
     */
    public static function care_visits_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_care_visits';
    }

    /**
     * Returns the care team table name with the WordPress prefix.
     */
    public static function care_team_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_care_team';
    }

    /**
     * Returns the visit schedules table name with the WordPress prefix.
     */
    public static function visit_schedules_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_visit_schedules';
    }

    /**
     * Returns the visit tasks table name with the WordPress prefix.
     */
    public static function visit_tasks_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_visit_tasks';
    }

    /**
     * Returns the medications table name with the WordPress prefix.
     */
    public static function medications_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_medications';
    }

    /**
     * Returns the medication administrations table name with the WordPress prefix.
     */
    public static function medication_administrations_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_medication_administrations';
    }

    /**
     * Returns the supported living homes table name with the WordPress prefix.
     */
    public static function homes_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_homes';
    }

    /**
     * Returns the supported living bedrooms table name with the WordPress prefix.
     */
    public static function bedrooms_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_bedrooms';
    }

    /**
     * Returns the supported living occupancies table name with the WordPress prefix.
     */
    public static function occupancies_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_occupancies';
    }

    /**
     * Package cost submission records table.
     */
    public static function referral_package_costs_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_referral_package_costs';
    }

    /**
     * Local Authority / funding decision records table.
     */
    public static function referral_la_decisions_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jmrs_referral_la_decisions';
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
        self::create_referral_stage_history_table($charset);
        self::create_referral_documents_table($charset);
        self::create_referral_assessments_table($charset);
        self::create_referral_care_plans_table($charset);
        self::create_care_plan_versions_table($charset);
        self::create_care_plan_reviews_table($charset);
        self::create_care_visits_table($charset);
        self::create_care_team_table($charset);
        self::create_visit_schedules_table($charset);
        self::create_visit_tasks_table($charset);
        self::create_medications_table($charset);
        self::create_medication_administrations_table($charset);
        self::create_homes_table($charset);
        self::create_bedrooms_table($charset);
        self::create_occupancies_table($charset);
        self::create_referral_package_costs_table($charset);
        self::create_referral_la_decisions_table($charset);
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
            client_first_name VARCHAR(100) NULL,
            client_last_name VARCHAR(100) NULL,
            client_date_of_birth DATE NULL,
            address_line_1 VARCHAR(255) NULL,
            address_line_2 VARCHAR(255) NULL,
            city VARCHAR(150) NULL,
            postcode VARCHAR(30) NULL,
            referrer_type VARCHAR(50) NULL,
            referrer_organisation VARCHAR(255) NULL,
            referrer_phone VARCHAR(50) NULL,
            relationship_to_client VARCHAR(150) NULL,
            submission_channel VARCHAR(50) NOT NULL DEFAULT 'admin',
            public_consent_at DATETIME NULL,
            public_consent_version VARCHAR(50) NULL,
            archived_at DATETIME NULL,
            archived_by BIGINT UNSIGNED NULL,
            archive_reason TEXT NULL,
            care_setting VARCHAR(50) NULL,
            workflow_stage_entered_at DATETIME NULL,
            next_action_due_at DATETIME NULL,
            interest_expressed_at DATETIME NULL,
            interest_expressed_by BIGINT UNSIGNED NULL,
            interest_response_method VARCHAR(30) NULL,
            interest_response_recipient VARCHAR(190) NULL,
            interest_email_status VARCHAR(30) NULL,
            interest_email_sent_at DATETIME NULL,
            care_commenced_at DATETIME NULL,
            care_commenced_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY priority (priority),
            KEY assigned_to (assigned_to),
            KEY referral_source (referral_source),
            KEY service_type_id (service_type_id),
            KEY workflow_stage_id (workflow_stage_id),
            KEY submission_channel (submission_channel),
            KEY archived_at (archived_at),
            KEY archived_by (archived_by),
            KEY care_setting (care_setting),
            KEY created_at (created_at),
            KEY workflow_stage_entered_at (workflow_stage_entered_at),
            KEY next_action_due_at (next_action_due_at),
            KEY interest_expressed_at (interest_expressed_at),
            KEY archived_at_status (archived_at, status),
            KEY status_priority (status, priority),
            KEY assigned_to_archived_at (assigned_to, archived_at)
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
            KEY action (action),
            KEY referral_id_created_at (referral_id, created_at)
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
            KEY user_id (user_id),
            KEY referral_id_created_at (referral_id, created_at)
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
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            is_pipeline_stage TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY stage_order (stage_order),
            KEY is_pipeline_stage (is_pipeline_stage),
            KEY is_system (is_system)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the referral pipeline stage history table.
     */
    private static function create_referral_stage_history_table(string $charset): void
    {
        $table = self::referral_stage_history_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT UNSIGNED NOT NULL,
            from_stage_id BIGINT UNSIGNED NULL,
            from_stage_slug VARCHAR(100) NULL,
            to_stage_id BIGINT UNSIGNED NOT NULL,
            to_stage_slug VARCHAR(100) NOT NULL,
            changed_by BIGINT UNSIGNED NULL,
            change_type VARCHAR(50) NOT NULL,
            reason VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY referral_id (referral_id),
            KEY to_stage_id (to_stage_id),
            KEY created_at (created_at),
            KEY referral_id_created_at (referral_id, created_at)
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
            attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            uploaded_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            storage_type VARCHAR(50) NOT NULL DEFAULT 'legacy_attachment',
            relative_path VARCHAR(500) NULL,
            stored_name VARCHAR(255) NULL,
            checksum_sha256 VARCHAR(64) NULL,
            PRIMARY KEY  (id),
            KEY referral_id (referral_id),
            KEY attachment_id (attachment_id),
            KEY uploaded_by (uploaded_by),
            KEY storage_type (storage_type),
            KEY referral_id_created_at (referral_id, created_at)
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
            scheduled_at DATETIME NULL,
            assessment_location_type VARCHAR(40) NULL,
            assessment_location_name VARCHAR(190) NULL,
            assessment_location_address TEXT NULL,
            assessment_contact_name VARCHAR(190) NULL,
            assessment_contact_phone VARCHAR(50) NULL,
            assessment_contact_email VARCHAR(190) NULL,
            scheduling_notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY referral_id (referral_id),
            KEY assessor_user_id (assessor_user_id),
            KEY outcome (outcome),
            KEY scheduled_at (scheduled_at)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the referral care plans table.
     */
    private static function create_referral_care_plans_table(string $charset): void
    {
        $table = self::referral_care_plans_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT UNSIGNED NOT NULL,
            assessment_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            approved_by BIGINT UNSIGNED NULL,
            plan_status VARCHAR(50) NOT NULL DEFAULT 'draft',
            start_date DATE NULL,
            review_date DATE NULL,
            visit_frequency VARCHAR(100) NULL,
            visit_duration VARCHAR(100) NULL,
            preferred_visit_times LONGTEXT NULL,
            personal_care_tasks LONGTEXT NULL,
            mobility_support LONGTEXT NULL,
            medication_support LONGTEXT NULL,
            nutrition_support LONGTEXT NULL,
            communication_support LONGTEXT NULL,
            continence_support LONGTEXT NULL,
            social_support LONGTEXT NULL,
            equipment_required LONGTEXT NULL,
            risks_and_safeguards LONGTEXT NULL,
            goals LONGTEXT NULL,
            additional_instructions LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY referral_id (referral_id),
            KEY assessment_id (assessment_id),
            KEY created_by (created_by),
            KEY approved_by (approved_by),
            KEY plan_status (plan_status),
            KEY plan_status_review_date (plan_status, review_date)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the care plan versions table.
     */
    private static function create_care_plan_versions_table(string $charset): void
    {
        $table = self::care_plan_versions_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            care_plan_id BIGINT UNSIGNED NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            snapshot LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            change_summary TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY care_plan_version (care_plan_id, version_number),
            KEY care_plan_id (care_plan_id),
            KEY created_by (created_by),
            KEY created_at (created_at)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the care plan reviews table.
     */
    private static function create_care_plan_reviews_table(string $charset): void
    {
        $table = self::care_plan_reviews_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            care_plan_id BIGINT UNSIGNED NOT NULL,
            reviewed_by BIGINT UNSIGNED NOT NULL,
            review_date DATE NOT NULL,
            outcome VARCHAR(50) NOT NULL,
            notes LONGTEXT NULL,
            next_review_date DATE NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY care_plan_id (care_plan_id),
            KEY reviewed_by (reviewed_by),
            KEY review_date (review_date),
            KEY outcome (outcome)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the care visits table.
     */
    private static function create_care_visits_table(string $charset): void
    {
        $table = self::care_visits_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT UNSIGNED NOT NULL,
            care_plan_id BIGINT UNSIGNED NULL,
            assigned_user_id BIGINT UNSIGNED NULL,
            schedule_id BIGINT UNSIGNED NULL,
            schedule_occurrence_date DATE NULL,
            generation_key VARCHAR(191) NULL,
            visit_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            visit_status VARCHAR(50) NOT NULL DEFAULT 'scheduled',
            visit_type VARCHAR(100) NULL,
            tasks LONGTEXT NULL,
            notes LONGTEXT NULL,
            completed_at DATETIME NULL,
            arrival_time DATETIME NULL,
            departure_time DATETIME NULL,
            actual_duration_minutes INT NULL,
            visit_outcome VARCHAR(50) NULL,
            tasks_completed LONGTEXT NULL,
            tasks_not_completed LONGTEXT NULL,
            client_response LONGTEXT NULL,
            wellbeing_observations LONGTEXT NULL,
            incident_report LONGTEXT NULL,
            manager_review_notes LONGTEXT NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            service_location_type VARCHAR(50) NULL,
            service_location_label VARCHAR(255) NULL,
            service_address_line_1 VARCHAR(255) NULL,
            service_address_line_2 VARCHAR(255) NULL,
            service_city VARCHAR(150) NULL,
            service_postcode VARCHAR(30) NULL,
            service_home_id BIGINT UNSIGNED NULL,
            service_bedroom_id BIGINT UNSIGNED NULL,
            service_occupancy_id BIGINT UNSIGNED NULL,
            service_location_recorded_at DATETIME NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY generation_key (generation_key),
            KEY referral_id (referral_id),
            KEY care_plan_id (care_plan_id),
            KEY assigned_user_id (assigned_user_id),
            KEY schedule_id (schedule_id),
            KEY schedule_occurrence_date (schedule_occurrence_date),
            KEY visit_date (visit_date),
            KEY visit_status (visit_status),
            KEY visit_outcome (visit_outcome),
            KEY reviewed_by (reviewed_by),
            KEY referral_id_visit_date (referral_id, visit_date),
            KEY assigned_user_visit_date_status (assigned_user_id, visit_date, visit_status),
            KEY schedule_id_occurrence_date (schedule_id, schedule_occurrence_date),
            KEY visit_status_visit_date (visit_status, visit_date),
            KEY reviewed_at_visit_status (reviewed_at, visit_status)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the care team table.
     */
    private static function create_care_team_table(string $charset): void
    {
        $table = self::care_team_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT UNSIGNED NOT NULL,
            care_plan_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            team_role VARCHAR(100) NOT NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            assignment_status VARCHAR(50) NOT NULL DEFAULT 'active',
            start_date DATE NOT NULL,
            end_date DATE NULL,
            notes LONGTEXT NULL,
            assigned_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY referral_id (referral_id),
            KEY care_plan_id (care_plan_id),
            KEY user_id (user_id),
            KEY assignment_status (assignment_status),
            KEY referral_id_assignment_status (referral_id, assignment_status),
            KEY user_id_assignment_status (user_id, assignment_status)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the visit schedules table.
     */
    private static function create_visit_schedules_table(string $charset): void
    {
        $table = self::visit_schedules_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT UNSIGNED NOT NULL,
            care_plan_id BIGINT UNSIGNED NULL,
            team_assignment_id BIGINT UNSIGNED NULL,
            schedule_name VARCHAR(255) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NULL,
            repeat_type VARCHAR(50) NOT NULL,
            repeat_interval INT UNSIGNED NOT NULL DEFAULT 1,
            days_of_week VARCHAR(191) NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            visit_type VARCHAR(100) NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            notes LONGTEXT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY referral_id (referral_id),
            KEY care_plan_id (care_plan_id),
            KEY team_assignment_id (team_assignment_id),
            KEY status (status),
            KEY referral_id_status (referral_id, status),
            KEY status_start_end (status, start_date, end_date)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the visit tasks table.
     */
    private static function create_visit_tasks_table(string $charset): void
    {
        $table = self::visit_tasks_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            visit_id BIGINT UNSIGNED NOT NULL,
            task_name VARCHAR(255) NOT NULL,
            task_status VARCHAR(50) NOT NULL DEFAULT 'pending',
            task_notes LONGTEXT NULL,
            display_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY visit_id (visit_id),
            KEY task_status (task_status),
            KEY display_order (display_order),
            KEY visit_id_task_status (visit_id, task_status)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the medications table.
     */
    private static function create_medications_table(string $charset): void
    {
        $table = self::medications_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT UNSIGNED NOT NULL,
            medication_name VARCHAR(255) NOT NULL,
            strength VARCHAR(100) NULL,
            dosage VARCHAR(255) NOT NULL,
            route VARCHAR(100) NOT NULL,
            frequency VARCHAR(255) NULL,
            instructions LONGTEXT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            medication_status VARCHAR(50) NOT NULL DEFAULT 'active',
            prescribing_source VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY referral_id (referral_id),
            KEY medication_status (medication_status),
            KEY start_date (start_date),
            KEY end_date (end_date),
            KEY referral_id_medication_status (referral_id, medication_status)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the medication administrations table.
     */
    private static function create_medication_administrations_table(string $charset): void
    {
        $table = self::medication_administrations_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            medication_id BIGINT UNSIGNED NOT NULL,
            referral_id BIGINT UNSIGNED NOT NULL,
            visit_id BIGINT UNSIGNED NOT NULL,
            administered_by BIGINT UNSIGNED NOT NULL,
            scheduled_time DATETIME NULL,
            administered_time DATETIME NULL,
            administration_status VARCHAR(50) NOT NULL,
            dose_given VARCHAR(255) NULL,
            notes LONGTEXT NULL,
            reason_code VARCHAR(100) NULL,
            witness_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY medication_visit_scheduled (medication_id, visit_id, scheduled_time),
            KEY medication_id (medication_id),
            KEY referral_id (referral_id),
            KEY visit_id (visit_id),
            KEY administered_by (administered_by),
            KEY administration_status (administration_status),
            KEY administered_time (administered_time),
            KEY visit_id_administration_status (visit_id, administration_status),
            KEY referral_id_administered_time (referral_id, administered_time),
            KEY administered_by_administered_time (administered_by, administered_time)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the supported living homes table.
     */
    private static function create_homes_table(string $charset): void
    {
        $table = self::homes_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            address_line_1 VARCHAR(255) NOT NULL,
            address_line_2 VARCHAR(255) NULL,
            city VARCHAR(100) NOT NULL,
            postcode VARCHAR(20) NOT NULL,
            phone VARCHAR(50) NULL,
            manager_user_id BIGINT UNSIGNED NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            notes LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY manager_user_id (manager_user_id),
            KEY name (name)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the supported living bedrooms table.
     */
    private static function create_bedrooms_table(string $charset): void
    {
        $table = self::bedrooms_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            home_id BIGINT UNSIGNED NOT NULL,
            room_label VARCHAR(100) NOT NULL,
            floor VARCHAR(100) NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            notes LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY home_id_room_label (home_id, room_label),
            KEY home_id (home_id),
            KEY status (status),
            KEY home_id_status (home_id, status)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the supported living occupancies table.
     */
    private static function create_occupancies_table(string $charset): void
    {
        $table = self::occupancies_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT UNSIGNED NOT NULL,
            home_id BIGINT UNSIGNED NOT NULL,
            bedroom_id BIGINT UNSIGNED NOT NULL,
            move_in_date DATE NOT NULL,
            move_out_date DATE NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            notes LONGTEXT NULL,
            end_reason VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            ended_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            ended_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY referral_id_status (referral_id, status),
            KEY bedroom_id_status (bedroom_id, status),
            KEY home_id_status (home_id, status),
            KEY move_in_date (move_in_date),
            KEY status (status),
            KEY referral_id (referral_id),
            KEY bedroom_id (bedroom_id),
            KEY home_id (home_id)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the package cost submission records table.
     */
    private static function create_referral_package_costs_table(string $charset): void
    {
        $table = self::referral_package_costs_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT UNSIGNED NOT NULL,
            document_id BIGINT UNSIGNED NULL,
            package_total DECIMAL(12,2) NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'GBP',
            prepared_at DATETIME NULL,
            prepared_by BIGINT UNSIGNED NULL,
            sent_at DATETIME NULL,
            sent_by BIGINT UNSIGNED NULL,
            send_method VARCHAR(30) NULL,
            recipient VARCHAR(190) NULL,
            submission_reference VARCHAR(190) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY referral_id (referral_id),
            KEY document_id (document_id),
            KEY status (status),
            KEY referral_id_status (referral_id, status),
            KEY prepared_at (prepared_at),
            KEY sent_at (sent_at)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Creates or updates Local Authority decision records table.
     */
    private static function create_referral_la_decisions_table(string $charset): void
    {
        $table = self::referral_la_decisions_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id BIGINT UNSIGNED NOT NULL,
            package_cost_id BIGINT UNSIGNED NULL,
            decision VARCHAR(30) NOT NULL,
            decision_at DATETIME NOT NULL,
            recorded_by BIGINT UNSIGNED NULL,
            funding_confirmed TINYINT(1) NULL,
            funding_reference VARCHAR(190) NULL,
            decision_reference VARCHAR(190) NULL,
            reason_code VARCHAR(50) NULL,
            notes VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY referral_id (referral_id),
            KEY package_cost_id (package_cost_id),
            KEY decision (decision),
            KEY referral_id_decision (referral_id, decision),
            KEY decision_at (decision_at)
        ) {$charset};";

        dbDelta($sql);
    }
}
