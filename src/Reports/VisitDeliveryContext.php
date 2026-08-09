<?php

namespace JMReferral\Reports;

use JMReferral\Database\Tables;
use JMReferral\Referral\CareSetting;
use JMReferral\Visits\CareVisitService;
use JMReferral\Visits\ServiceLocation;

/**
 * Visit delivery-context filter helpers for report SQL (Phase 2G.4).
 *
 * Classification:
 * - Snapshot present → historical service_location_* columns
 * - Open (scheduled/confirmed/in_progress) without snapshot → current care_setting / occupancy
 * - Terminal without snapshot → Location Not Recorded (never current Home)
 */
class VisitDeliveryContext
{
    public const ALL = 'all';
    public const SUPPORTED_LIVING = 'supported_living';
    public const OWN_HOME = 'own_home';
    public const UNRESOLVED = 'unresolved';

    /**
     * @return array<string, string>
     */
    public static function care_context_labels(): array
    {
        $care = CareSetting::options();

        return [
            self::ALL              => __('All', 'jm-referral-system'),
            self::SUPPORTED_LIVING => (string) ($care[CareSetting::SUPPORTED_LIVING] ?? __('Supported Living', 'jm-referral-system')),
            self::OWN_HOME         => (string) ($care[CareSetting::OWN_HOME] ?? __("Client's Own Home", 'jm-referral-system')),
            self::UNRESOLVED       => __('Unresolved / Location Not Recorded', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowed_care_contexts(): array
    {
        return array_keys(self::care_context_labels());
    }

    /**
     * @param array{care_context?: string, home_id?: int|string} $raw
     * @return array{care_context: string, home_id: int, is_active: bool}
     */
    public static function normalize(array $raw): array
    {
        $context = sanitize_key((string) ($raw['care_context'] ?? self::ALL));
        if (! in_array($context, self::allowed_care_contexts(), true)) {
            $context = self::ALL;
        }

        $home_id = absint($raw['home_id'] ?? 0);

        // Home filter is only meaningful for Supported Living / All (estate home matching).
        if (self::OWN_HOME === $context || self::UNRESOLVED === $context) {
            $home_id = 0;
        }

        $is_active = self::ALL !== $context || $home_id > 0;

        return [
            'care_context' => $context,
            'home_id'      => $home_id,
            'is_active'    => $is_active,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function open_visit_statuses(): array
    {
        return [
            CareVisitService::STATUS_SCHEDULED,
            CareVisitService::STATUS_CONFIRMED,
            CareVisitService::STATUS_IN_PROGRESS,
        ];
    }

    /**
     * SQL boolean: visit has a recorded service-location snapshot type.
     */
    public static function sql_has_snapshot(string $visit_alias = 'v'): string
    {
        return "(NULLIF(TRIM(COALESCE({$visit_alias}.service_location_type, '')), '') IS NOT NULL)";
    }

    /**
     * @param array{care_context: string, home_id: int, is_active: bool}|null $filters
     */
    public static function needs_occupancy_join(?array $filters): bool
    {
        if (null === $filters || empty($filters['is_active'])) {
            return false;
        }

        return true;
    }

    /**
     * LEFT JOIN active occupancy for open-visit current-location matching.
     */
    public static function occupancy_join_sql(string $referral_alias = 'r', string $occ_alias = 'o_ctx'): string
    {
        $occ = Tables::occupancies_table();

        return " LEFT JOIN {$occ} {$occ_alias}
            ON {$occ_alias}.referral_id = {$referral_alias}.id
            AND {$occ_alias}.status = 'active'";
    }

    /**
     * Appends visit delivery-context predicates. No-op when filters inactive.
     *
     * @param array{care_context: string, home_id: int, is_active: bool}|null $filters
     * @param array<int|string, mixed>                                         $params
     */
    public static function append_filters(
        string &$sql,
        array &$params,
        ?array $filters,
        string $visit_alias = 'v',
        string $referral_alias = 'r',
        string $occ_alias = 'o_ctx'
    ): void {
        if (null === $filters || empty($filters['is_active'])) {
            return;
        }

        $context = (string) ($filters['care_context'] ?? self::ALL);
        $home_id = (int) ($filters['home_id'] ?? 0);

        $has_snap = self::sql_has_snapshot($visit_alias);
        $open     = self::open_visit_statuses();
        $open_ph  = implode(', ', array_fill(0, count($open), '%s'));

        $open_pred = "{$visit_alias}.visit_status IN ({$open_ph})";

        if (self::SUPPORTED_LIVING === $context) {
            $sql .= " AND (
                ({$has_snap} AND {$visit_alias}.service_location_type = %s)
                OR (
                    NOT {$has_snap}
                    AND {$open_pred}
                    AND {$referral_alias}.care_setting = %s
                    AND {$occ_alias}.id IS NOT NULL
                )
            )";
            $params = array_merge(
                $params,
                [ServiceLocation::TYPE_SUPPORTED_LIVING],
                $open,
                [CareSetting::SUPPORTED_LIVING]
            );
        } elseif (self::OWN_HOME === $context) {
            $sql .= " AND (
                ({$has_snap} AND {$visit_alias}.service_location_type = %s)
                OR (
                    NOT {$has_snap}
                    AND {$open_pred}
                    AND {$referral_alias}.care_setting = %s
                )
            )";
            $params = array_merge(
                $params,
                [ServiceLocation::TYPE_OWN_HOME],
                $open,
                [CareSetting::OWN_HOME]
            );
        } elseif (self::UNRESOLVED === $context) {
            // Snapshot unresolved OR terminal/open without snapshot that is currently unresolved.
            $sql .= " AND (
                ({$has_snap} AND {$visit_alias}.service_location_type = %s)
                OR (
                    NOT {$has_snap}
                    AND {$open_pred}
                    AND (
                        {$referral_alias}.care_setting IS NULL
                        OR {$referral_alias}.care_setting = ''
                        OR (
                            {$referral_alias}.care_setting = %s
                            AND {$occ_alias}.id IS NULL
                        )
                    )
                )
                OR (
                    NOT {$has_snap}
                    AND {$visit_alias}.visit_status NOT IN ({$open_ph})
                )
            )";
            $params = array_merge(
                $params,
                [ServiceLocation::TYPE_UNRESOLVED],
                $open,
                [CareSetting::SUPPORTED_LIVING],
                $open
            );
        }

        if ($home_id > 0) {
            $sql .= " AND (
                ({$has_snap} AND {$visit_alias}.service_home_id = %d)
                OR (
                    NOT {$has_snap}
                    AND {$open_pred}
                    AND {$occ_alias}.home_id = %d
                )
            )";
            $params[] = $home_id;
            $params   = array_merge($params, $open);
            $params[] = $home_id;
        }
    }
}
