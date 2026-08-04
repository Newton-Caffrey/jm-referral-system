<?php

namespace JMReferral\Permissions;

class Roles
{
    public const JM_ADMINISTRATOR = 'jmrs_administrator';
    public const REFERRAL_MANAGER = 'jmrs_referral_manager';
    public const CARE_COORDINATOR = 'jmrs_care_coordinator';
    public const ASSESSOR = 'jmrs_assessor';
    public const SUPPORT_WORKER = 'jmrs_support_worker';

    /**
     * Returns all custom JM role slugs.
     *
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * Returns role definitions keyed by slug.
     *
     * @return array<string, array{label: string, capabilities: array<int, string>}>
     */
    public static function definitions(): array
    {
        return [
            self::JM_ADMINISTRATOR => [
                'label'        => __('JM Administrator', 'jm-referral-system'),
                'capabilities' => Capabilities::all(),
            ],
            self::REFERRAL_MANAGER => [
                'label'        => __('Referral Manager', 'jm-referral-system'),
                'capabilities' => [
                    Capabilities::VIEW_DASHBOARD,
                    Capabilities::VIEW_REFERRALS,
                    Capabilities::CREATE_REFERRALS,
                    Capabilities::EDIT_REFERRALS,
                    Capabilities::DELETE_REFERRALS,
                    Capabilities::ASSIGN_REFERRALS,
                    Capabilities::ADD_NOTES,
                    Capabilities::EXPORT_REFERRALS,
                    Capabilities::UPLOAD_DOCUMENTS,
                    Capabilities::DOWNLOAD_DOCUMENTS,
                    Capabilities::VIEW_CARE_PLANS,
                    Capabilities::MANAGE_CARE_PLANS,
                    Capabilities::REVIEW_CARE_PLANS,
                    Capabilities::VIEW_VISITS,
                    Capabilities::MANAGE_VISITS,
                    Capabilities::EXECUTE_VISITS,
                    Capabilities::VIEW_CARE_TEAM,
                    Capabilities::MANAGE_CARE_TEAM,
                    Capabilities::VIEW_SCHEDULES,
                    Capabilities::MANAGE_SCHEDULES,
                    Capabilities::VIEW_OPERATIONAL_ALERTS,
                    Capabilities::VIEW_MEDICATIONS,
                    Capabilities::MANAGE_MEDICATIONS,
                    Capabilities::ADMINISTER_MEDICATIONS,
                ],
            ],
            self::CARE_COORDINATOR => [
                'label'        => __('Care Coordinator', 'jm-referral-system'),
                'capabilities' => [
                    Capabilities::VIEW_DASHBOARD,
                    Capabilities::VIEW_REFERRALS,
                    Capabilities::CREATE_REFERRALS,
                    Capabilities::EDIT_REFERRALS,
                    Capabilities::ASSIGN_REFERRALS,
                    Capabilities::ADD_NOTES,
                    Capabilities::UPLOAD_DOCUMENTS,
                    Capabilities::DOWNLOAD_DOCUMENTS,
                    Capabilities::VIEW_CARE_PLANS,
                    Capabilities::MANAGE_CARE_PLANS,
                    Capabilities::REVIEW_CARE_PLANS,
                    Capabilities::VIEW_VISITS,
                    Capabilities::MANAGE_VISITS,
                    Capabilities::EXECUTE_VISITS,
                    Capabilities::VIEW_CARE_TEAM,
                    Capabilities::MANAGE_CARE_TEAM,
                    Capabilities::VIEW_SCHEDULES,
                    Capabilities::MANAGE_SCHEDULES,
                    Capabilities::VIEW_OPERATIONAL_ALERTS,
                    Capabilities::VIEW_MEDICATIONS,
                    Capabilities::MANAGE_MEDICATIONS,
                    Capabilities::ADMINISTER_MEDICATIONS,
                ],
            ],
            self::ASSESSOR => [
                'label'        => __('Assessor', 'jm-referral-system'),
                'capabilities' => [
                    Capabilities::VIEW_REFERRALS,
                    Capabilities::EDIT_REFERRALS,
                    Capabilities::ADD_NOTES,
                    Capabilities::UPLOAD_DOCUMENTS,
                    Capabilities::DOWNLOAD_DOCUMENTS,
                    Capabilities::VIEW_CARE_PLANS,
                    Capabilities::MANAGE_CARE_PLANS,
                    Capabilities::REVIEW_CARE_PLANS,
                    Capabilities::VIEW_VISITS,
                    Capabilities::VIEW_CARE_TEAM,
                    Capabilities::VIEW_SCHEDULES,
                    Capabilities::VIEW_MEDICATIONS,
                    Capabilities::MANAGE_MEDICATIONS,
                ],
            ],
            self::SUPPORT_WORKER => [
                'label'        => __('Support Worker', 'jm-referral-system'),
                'capabilities' => [
                    Capabilities::VIEW_DASHBOARD,
                    Capabilities::VIEW_REFERRALS,
                    Capabilities::ADD_NOTES,
                    Capabilities::DOWNLOAD_DOCUMENTS,
                    Capabilities::VIEW_CARE_PLANS,
                    Capabilities::VIEW_VISITS,
                    Capabilities::EXECUTE_VISITS,
                    Capabilities::VIEW_CARE_TEAM,
                    Capabilities::VIEW_SCHEDULES,
                    Capabilities::VIEW_MEDICATIONS,
                    Capabilities::ADMINISTER_MEDICATIONS,
                ],
            ],
        ];
    }

    /**
     * Registers or upgrades all JM staff roles.
     *
     * Idempotent. Does not modify the core administrator role or reassign users.
     */
    public static function register(): void
    {
        foreach (self::definitions() as $slug => $definition) {
            self::ensure_role($slug, $definition['label'], $definition['capabilities']);
        }
    }

    /**
     * Upgrades existing JM roles to match current capability definitions.
     *
     * Alias of register() for clarity at call sites.
     */
    public static function upgrade(): void
    {
        self::register();
    }

    /**
     * Removes all custom JM roles.
     *
     * Intended for uninstall only — not plugin deactivation.
     */
    public static function remove(): void
    {
        foreach (self::slugs() as $slug) {
            if (get_role($slug) instanceof \WP_Role) {
                remove_role($slug);
            }
        }
    }

    /**
     * Creates a role when missing, otherwise syncs its JMRS capabilities.
     *
     * @param array<int, string> $capabilities
     */
    private static function ensure_role(string $slug, string $label, array $capabilities): void
    {
        $role = get_role($slug);

        if (! $role instanceof \WP_Role) {
            add_role(
                $slug,
                $label,
                self::capabilities_for_storage($capabilities)
            );
            return;
        }

        self::sync_capabilities($role, $capabilities);
    }

    /**
     * Adds missing JMRS caps and removes JMRS caps no longer assigned to the role.
     *
     * Leaves non-JMRS capabilities (e.g. read) untouched.
     *
     * @param array<int, string> $capabilities
     */
    private static function sync_capabilities(\WP_Role $role, array $capabilities): void
    {
        $intended = array_fill_keys($capabilities, true);

        // Ensure WordPress login/admin baseline remains present.
        $role->add_cap('read');

        foreach ($capabilities as $capability) {
            $role->add_cap($capability);
        }

        foreach (Capabilities::all() as $capability) {
            if (! isset($intended[$capability])) {
                $role->remove_cap($capability);
            }
        }
    }

    /**
     * Builds the capability map expected by add_role().
     *
     * @param array<int, string> $capabilities
     * @return array<string, bool>
     */
    private static function capabilities_for_storage(array $capabilities): array
    {
        $map = [
            'read' => true,
        ];

        foreach ($capabilities as $capability) {
            $map[$capability] = true;
        }

        return $map;
    }
}
