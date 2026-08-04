<?php

namespace JMReferral\Permissions;

class Capabilities
{
    public const VIEW_DASHBOARD = 'jmrs_view_dashboard';
    public const VIEW_REFERRALS = 'jmrs_view_referrals';
    public const CREATE_REFERRALS = 'jmrs_create_referrals';
    public const EDIT_REFERRALS = 'jmrs_edit_referrals';
    public const DELETE_REFERRALS = 'jmrs_delete_referrals';
    public const ASSIGN_REFERRALS = 'jmrs_assign_referrals';
    public const ADD_NOTES = 'jmrs_add_notes';
    public const EXPORT_REFERRALS = 'jmrs_export_referrals';
    public const UPLOAD_DOCUMENTS = 'jmrs_upload_documents';
    public const DOWNLOAD_DOCUMENTS = 'jmrs_download_documents';
    public const VIEW_CARE_PLANS = 'jmrs_view_care_plans';
    public const MANAGE_CARE_PLANS = 'jmrs_manage_care_plans';
    public const REVIEW_CARE_PLANS = 'jmrs_review_care_plans';
    public const VIEW_VISITS = 'jmrs_view_visits';
    public const MANAGE_VISITS = 'jmrs_manage_visits';
    public const EXECUTE_VISITS = 'jmrs_execute_visits';
    public const VIEW_CARE_TEAM = 'jmrs_view_care_team';
    public const MANAGE_CARE_TEAM = 'jmrs_manage_care_team';
    public const VIEW_SCHEDULES = 'jmrs_view_schedules';
    public const MANAGE_SCHEDULES = 'jmrs_manage_schedules';
    public const MANAGE_SERVICE_TYPES = 'jmrs_manage_service_types';
    public const MANAGE_WORKFLOW_STAGES = 'jmrs_manage_workflow_stages';
    public const MANAGE_SETTINGS = 'jmrs_manage_settings';
    public const VIEW_OPERATIONAL_ALERTS = 'jmrs_view_operational_alerts';

    /**
     * Returns every plugin capability.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::VIEW_DASHBOARD,
            self::VIEW_REFERRALS,
            self::CREATE_REFERRALS,
            self::EDIT_REFERRALS,
            self::DELETE_REFERRALS,
            self::ASSIGN_REFERRALS,
            self::ADD_NOTES,
            self::EXPORT_REFERRALS,
            self::UPLOAD_DOCUMENTS,
            self::DOWNLOAD_DOCUMENTS,
            self::VIEW_CARE_PLANS,
            self::MANAGE_CARE_PLANS,
            self::REVIEW_CARE_PLANS,
            self::VIEW_VISITS,
            self::MANAGE_VISITS,
            self::EXECUTE_VISITS,
            self::VIEW_CARE_TEAM,
            self::MANAGE_CARE_TEAM,
            self::VIEW_SCHEDULES,
            self::MANAGE_SCHEDULES,
            self::MANAGE_SERVICE_TYPES,
            self::MANAGE_WORKFLOW_STAGES,
            self::MANAGE_SETTINGS,
            self::VIEW_OPERATIONAL_ALERTS,
        ];
    }

    /**
     * Grants all JMRS capabilities to the administrator role.
     *
     * Safe to call repeatedly. Does not remove manage_options.
     */
    public static function grant_to_administrators(): void
    {
        $role = get_role('administrator');

        if (! $role instanceof \WP_Role) {
            return;
        }

        foreach (self::all() as $capability) {
            $role->add_cap($capability);
        }
    }

    /**
     * Removes all JMRS capabilities from the administrator role.
     *
     * Intended for uninstall/cleanup only — not plugin deactivation.
     */
    public static function revoke_from_administrators(): void
    {
        $role = get_role('administrator');

        if (! $role instanceof \WP_Role) {
            return;
        }

        foreach (self::all() as $capability) {
            $role->remove_cap($capability);
        }
    }

    /**
     * Whether the current user has the given plugin capability.
     */
    public static function current_user_can(string $capability): bool
    {
        return current_user_can($capability);
    }
}
