<?php

namespace JMReferral\Homes;

use JMReferral\CarePlan\ReferralCarePlanRepository;
use JMReferral\Medication\MedicationAdministrationRepository;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Portal\PortalUrls;
use JMReferral\Referral\CareSetting;
use JMReferral\Users\UserProvider;
use JMReferral\Visits\CareVisitRepository;
use JMReferral\Visits\CareVisitService;

/**
 * Read model for Supported Living Home operational dashboard (Phase 2E).
 *
 * Aggregates existing occupancy / visit / review / MAR data for CURRENT
 * active residents only. Does not mutate clinical records.
 */
class HomeDashboardService
{
    public const UPCOMING_VISIT_DAYS = 7;

    public const UPCOMING_VISIT_LIMIT = 40;

    public function __construct(
        private HomeService $home_service,
        private BedroomService $bedroom_service,
        private OccupancyService $occupancy_service,
        private CareVisitRepository $visit_repository,
        private ReferralCarePlanRepository $care_plan_repository,
        private MedicationAdministrationRepository $medication_administration_repository,
        private AccessPolicy $access_policy,
        private UserProvider $user_provider
    ) {
    }

    /**
     * Full dashboard payload for a home.
     *
     * @param array<string, mixed> $home
     * @return array<string, mixed>
     */
    public function build(array $home): array
    {
        $home_id    = absint($home['id'] ?? 0);
        $can_manage = Capabilities::current_user_can(Capabilities::MANAGE_HOMES);
        $can_place  = Capabilities::current_user_can(Capabilities::MANAGE_OCCUPANCIES);
        $can_view_visits = Capabilities::current_user_can(Capabilities::VIEW_VISITS);
        $can_view_alerts = Capabilities::current_user_can(Capabilities::VIEW_OPERATIONAL_ALERTS);
        $can_manage_visits = Capabilities::current_user_can(Capabilities::MANAGE_VISITS);
        $can_view_care_plans = Capabilities::current_user_can(Capabilities::VIEW_CARE_PLANS);
        $can_view_medications = Capabilities::current_user_can(Capabilities::VIEW_MEDICATIONS)
            || Capabilities::current_user_can(Capabilities::ADMINISTER_MEDICATIONS);

        $capacity = $this->home_service->capacity($home_id);
        $metrics  = $this->occupancy_service->metrics_for_home($home_id, $capacity);

        $occupancy_by_bedroom = $this->occupancy_service->active_by_bedroom_for_home($home_id);
        $enriched_list        = $this->occupancy_service->enrich_rows(array_values($occupancy_by_bedroom));
        $enriched_by_bedroom  = [];
        foreach ($enriched_list as $occ_row) {
            $enriched_by_bedroom[absint($occ_row['bedroom_id'] ?? 0)] = $occ_row;
        }

        $residents = $this->build_residents($enriched_list);
        $visible_referral_ids = [];
        foreach ($residents as $resident) {
            if (! empty($resident['can_view_referral'])) {
                $visible_referral_ids[] = absint($resident['referral_id'] ?? 0);
            }
        }
        $visible_referral_ids = array_values(array_filter($visible_referral_ids));

        $upcoming = $can_view_visits
            ? $this->build_upcoming_visits($visible_referral_ids, $residents)
            : [];

        $next_visit_by_referral = [];
        foreach ($upcoming as $visit) {
            $rid = absint($visit['referral_id'] ?? 0);
            if ($rid > 0 && ! isset($next_visit_by_referral[$rid])) {
                $next_visit_by_referral[$rid] = $visit;
            }
        }
        foreach ($residents as $index => $resident) {
            $rid = absint($resident['referral_id'] ?? 0);
            $next = $next_visit_by_referral[$rid] ?? null;
            $residents[$index]['next_visit_label'] = null !== $next
                ? (string) ($next['when_label'] ?? '')
                : '';
            $residents[$index]['next_visit_url'] = null !== $next
                ? (string) ($next['view_url'] ?? '')
                : '';
        }

        $bedrooms = $this->build_bedrooms(
            $home,
            $home_id,
            $enriched_by_bedroom,
            $can_manage,
            $can_place
        );

        $attention = $this->build_attention(
            $visible_referral_ids,
            $can_view_alerts,
            $can_view_care_plans,
            $can_manage_visits,
            $can_view_medications
        );

        $manager_id   = absint($home['manager_user_id'] ?? 0);
        $manager_name = $manager_id > 0 ? $this->user_provider->get_display_name($manager_id) : '';

        return [
            'metrics'            => $metrics,
            'capacity'           => $metrics['capacity'],
            'occupied'           => $metrics['occupied'],
            'vacant'             => $metrics['vacant'],
            'occupancy_pct'      => $metrics['occupancy_pct'],
            'manager_name'       => $manager_name,
            'residents'          => $residents,
            'bedrooms'           => $bedrooms,
            'upcoming_visits'    => $upcoming,
            'attention'          => $attention,
            'can_manage'         => $can_manage,
            'can_place'          => $can_place,
            'can_view_visits'    => $can_view_visits,
            'home_is_active'     => 'active' === ($home['status'] ?? ''),
            'edit_url'           => $can_manage ? PortalUrls::home_edit($home_id) : '',
            'add_bedroom_url'    => ($can_manage && 'active' === ($home['status'] ?? ''))
                ? PortalUrls::bedroom_new($home_id)
                : '',
            'place_url'          => ($can_place && 'active' === ($home['status'] ?? ''))
                ? PortalUrls::occupancy_place(['home_id' => $home_id])
                : '',
            'vacancies_url'      => add_query_arg('jmrs_home_id', $home_id, PortalUrls::occupancy()),
            'list_url'           => PortalUrls::homes(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $enriched_occupancies
     * @return array<int, array<string, mixed>>
     */
    private function build_residents(array $enriched_occupancies): array
    {
        $rows = [];
        foreach ($enriched_occupancies as $occ) {
            $referral_id = absint($occ['referral_id'] ?? 0);
            $referral    = is_array($occ['referral'] ?? null) ? $occ['referral'] : [
                'id'          => $referral_id,
                'assigned_to' => absint($occ['assigned_to'] ?? 0),
            ];

            $can_view = $referral_id > 0 && $this->access_policy->can_view_referral($referral);
            $care_setting = $occ['care_setting'] ?? null;

            $rows[] = [
                'occupancy_id'       => absint($occ['id'] ?? 0),
                'referral_id'        => $referral_id,
                'bedroom_id'         => absint($occ['bedroom_id'] ?? 0),
                'room_label'         => (string) ($occ['room_label'] ?? ''),
                'move_in_date'       => (string) ($occ['move_in_date'] ?? ''),
                'care_setting'       => $care_setting,
                'care_setting_label' => CareSetting::label(
                    null === $care_setting || '' === trim((string) $care_setting)
                        ? null
                        : (string) $care_setting
                ),
                'can_view_referral'  => $can_view,
                'client_name'        => $can_view
                    ? (string) ($occ['client_name'] ?? '')
                    : __('Restricted', 'jm-referral-system'),
                'referral_number'    => $can_view
                    ? (string) ($occ['referral_number'] ?? '')
                    : '',
                'view_url'           => $can_view && $referral_id > 0
                    ? PortalUrls::referral($referral_id)
                    : '',
                'next_visit_label'   => '',
                'next_visit_url'     => '',
            ];
        }

        usort(
            $rows,
            static function (array $a, array $b): int {
                return strnatcasecmp((string) ($a['room_label'] ?? ''), (string) ($b['room_label'] ?? ''));
            }
        );

        return $rows;
    }

    /**
     * @param array<string, mixed> $home
     * @param array<int, array<string, mixed>> $enriched_by_bedroom
     * @return array<int, array<string, mixed>>
     */
    private function build_bedrooms(
        array $home,
        int $home_id,
        array $enriched_by_bedroom,
        bool $can_manage,
        bool $can_place
    ): array {
        $bedrooms = $this->bedroom_service->list_by_home($home_id);
        $home_active = 'active' === ($home['status'] ?? '');

        foreach ($bedrooms as $index => $bedroom) {
            $bedroom_id = absint($bedroom['id'] ?? 0);
            $status     = (string) ($bedroom['status'] ?? '');
            $occ        = $enriched_by_bedroom[$bedroom_id] ?? null;
            $is_occupied = null !== $occ;
            $is_inactive = 'inactive' === $status;

            $referral_id = $is_occupied ? absint($occ['referral_id'] ?? 0) : 0;
            $referral    = $is_occupied && is_array($occ['referral'] ?? null)
                ? $occ['referral']
                : ['id' => $referral_id, 'assigned_to' => absint($occ['assigned_to'] ?? 0)];
            $can_view = $is_occupied && $referral_id > 0 && $this->access_policy->can_view_referral($referral);

            if ($is_inactive) {
                $occupancy_label = __('Inactive', 'jm-referral-system');
            } elseif ($is_occupied) {
                $occupancy_label = __('Occupied', 'jm-referral-system');
            } else {
                $occupancy_label = __('Vacant', 'jm-referral-system');
            }

            $bedrooms[$index]['edit_url'] = $can_manage
                ? PortalUrls::bedroom_edit($home_id, $bedroom_id)
                : '';
            $bedrooms[$index]['status_label'] = BedroomService::status_labels()[$status]
                ?? ucfirst($status);
            $bedrooms[$index]['is_occupied']     = $is_occupied;
            $bedrooms[$index]['is_inactive']     = $is_inactive;
            $bedrooms[$index]['occupancy_label'] = $occupancy_label;
            $bedrooms[$index]['client_name']     = $is_occupied
                ? ($can_view ? (string) ($occ['client_name'] ?? '') : __('Restricted', 'jm-referral-system'))
                : '';
            $bedrooms[$index]['move_in_date']    = $is_occupied ? (string) ($occ['move_in_date'] ?? '') : '';
            $bedrooms[$index]['client_url']      = ($can_view && $referral_id > 0)
                ? PortalUrls::referral($referral_id)
                : '';
            $bedrooms[$index]['place_url']       = (
                $can_place
                && ! $is_occupied
                && ! $is_inactive
                && $home_active
            )
                ? PortalUrls::occupancy_place(
                    [
                        'home_id'    => $home_id,
                        'bedroom_id' => $bedroom_id,
                    ]
                )
                : '';
        }

        return $bedrooms;
    }

    /**
     * @param array<int, int> $visible_referral_ids
     * @param array<int, array<string, mixed>> $residents
     * @return array<int, array<string, mixed>>
     */
    private function build_upcoming_visits(array $visible_referral_ids, array $residents): array
    {
        if ([] === $visible_referral_ids) {
            return [];
        }

        $from = current_time('Y-m-d');
        $to   = gmdate('Y-m-d', strtotime($from . ' +' . self::UPCOMING_VISIT_DAYS . ' days') ?: time());

        $rows = $this->visit_repository->get_upcoming_for_referral_ids(
            $visible_referral_ids,
            $from,
            $to,
            self::UPCOMING_VISIT_LIMIT
        );

        $name_by_referral = [];
        $bedroom_by_referral = [];
        foreach ($residents as $resident) {
            $rid = absint($resident['referral_id'] ?? 0);
            if ($rid <= 0) {
                continue;
            }
            if (! empty($resident['can_view_referral'])) {
                $name_by_referral[$rid] = (string) ($resident['client_name'] ?? '');
            }
            $bedroom_by_referral[$rid] = (string) ($resident['room_label'] ?? '');
        }

        $status_labels = CareVisitService::status_labels();
        $assignee_ids  = [];
        foreach ($rows as $row) {
            $uid = absint($row['assigned_user_id'] ?? 0);
            if ($uid > 0) {
                $assignee_ids[] = $uid;
            }
        }
        $assignee_names = $this->user_provider->get_display_names_by_ids($assignee_ids);
        $can_see_staff  = ! $this->access_policy->should_scope_to_assigned()
            || Capabilities::current_user_can(Capabilities::MANAGE_VISITS);

        $out = [];
        foreach ($rows as $row) {
            $referral_id = absint($row['referral_id'] ?? 0);
            $visit_id    = absint($row['id'] ?? 0);
            if ($referral_id <= 0 || $visit_id <= 0) {
                continue;
            }

            $visit_date = (string) ($row['visit_date'] ?? '');
            $start_time = (string) ($row['start_time'] ?? '');
            $when_label = trim($visit_date . ('' !== $start_time ? ' ' . substr($start_time, 0, 5) : ''));
            if ($visit_date === $from && '' !== $start_time) {
                $when_label = sprintf(
                    /* translators: %s: time HH:MM */
                    __('Today %s', 'jm-referral-system'),
                    substr($start_time, 0, 5)
                );
            }

            $status = (string) ($row['visit_status'] ?? '');
            $assigned_user_id = absint($row['assigned_user_id'] ?? 0);

            $out[] = [
                'id'              => $visit_id,
                'referral_id'     => $referral_id,
                'visit_date'      => $visit_date,
                'start_time'      => $start_time,
                'when_label'      => $when_label,
                'client_name'     => $name_by_referral[$referral_id]
                    ?? $this->occupancy_service->client_display_name($row),
                'room_label'      => $bedroom_by_referral[$referral_id] ?? '',
                'status'          => $status,
                'status_label'    => $status_labels[$status] ?? ucfirst(str_replace('_', ' ', $status)),
                'assigned_name'   => ($can_see_staff && $assigned_user_id > 0)
                    ? (string) ($assignee_names[$assigned_user_id] ?? '')
                    : '',
                'view_url'        => PortalUrls::visit_edit($referral_id, $visit_id),
            ];
        }

        return $out;
    }

    /**
     * @param array<int, int> $visible_referral_ids
     * @return array{
     *     items: array<int, array{key: string, label: string, count: int, description: string}>,
     *     total: int
     * }
     */
    private function build_attention(
        array $visible_referral_ids,
        bool $can_view_alerts,
        bool $can_view_care_plans,
        bool $can_manage_visits,
        bool $can_view_medications
    ): array {
        $items = [];

        if ([] === $visible_referral_ids || ! $can_view_alerts) {
            return ['items' => [], 'total' => 0];
        }

        if ($can_view_care_plans) {
            $overdue = $this->care_plan_repository->count_overdue_reviews_for_referral_ids(
                $visible_referral_ids,
                current_time('Y-m-d')
            );
            if ($overdue > 0) {
                $items[] = [
                    'key'         => 'care_plan_review_overdue',
                    'label'       => __('Care Plan Reviews', 'jm-referral-system'),
                    'count'       => $overdue,
                    'description' => sprintf(
                        /* translators: %d: overdue count */
                        _n(
                            '%d due / overdue',
                            '%d due / overdue',
                            $overdue,
                            'jm-referral-system'
                        ),
                        $overdue
                    ),
                ];
            }
        }

        if ($can_manage_visits) {
            $awaiting = $this->visit_repository->count_awaiting_review_for_referral_ids($visible_referral_ids);
            if ($awaiting > 0) {
                $items[] = [
                    'key'         => 'visit_awaiting_review',
                    'label'       => __('Visits Awaiting Manager Review', 'jm-referral-system'),
                    'count'       => $awaiting,
                    'description' => sprintf(
                        /* translators: %d: visit count */
                        _n('%d visit', '%d visits', $awaiting, 'jm-referral-system'),
                        $awaiting
                    ),
                ];
            }
        }

        if ($can_view_medications) {
            $med_count = $this->medication_administration_repository->count_exceptions_for_date_and_referral_ids(
                current_time('Y-m-d'),
                $visible_referral_ids
            );
            if ($med_count > 0) {
                $items[] = [
                    'key'         => 'medication_administration_exception',
                    'label'       => __('Medication / MAR Attention', 'jm-referral-system'),
                    'count'       => $med_count,
                    'description' => sprintf(
                        /* translators: %d: exception count */
                        _n('%d exception today', '%d exceptions today', $med_count, 'jm-referral-system'),
                        $med_count
                    ),
                ];
            }
        }

        $total = 0;
        foreach ($items as $item) {
            $total += absint($item['count'] ?? 0);
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
