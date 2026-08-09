<?php

namespace JMReferral\Visits;

use JMReferral\Homes\BedroomRepository;
use JMReferral\Homes\HomeRepository;
use JMReferral\Homes\OccupancyRepository;
use JMReferral\Referral\CareSetting;
use JMReferral\Referral\ReferralRepository;

/**
 * Read-only service location resolution (Phase 2F.1).
 *
 * Unexecuted visits → current referral location.
 * Executed visits (visit_outcome set) → historical snapshot when present.
 */
class ServiceLocationResolver
{
    public function __construct(
        private ReferralRepository $referral_repository,
        private OccupancyRepository $occupancy_repository,
        private HomeRepository $home_repository,
        private BedroomRepository $bedroom_repository
    ) {
    }

    public function resolve_for_referral(int $referral_id): ServiceLocation
    {
        if ($referral_id <= 0) {
            return $this->unresolved(
                null,
                __('Care setting not specified', 'jm-referral-system')
            );
        }

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            return $this->unresolved(
                null,
                __('Care setting not specified', 'jm-referral-system')
            );
        }

        return $this->resolve_from_referral($referral);
    }

    /**
     * @param array<string, mixed> $visit
     */
    public function resolve_for_visit(array $visit): ServiceLocation
    {
        // Verified execution gate: non-empty visit_outcome.
        if ($this->is_executed($visit)) {
            return $this->resolve_executed_visit($visit);
        }

        // Manual status=completed without visit_outcome is not verified execution.
        // Do not invent current location as historical.
        if ($this->is_manual_completed_without_outcome($visit)) {
            return $this->legacy_unrecorded();
        }

        $referral_id = absint($visit['referral_id'] ?? 0);

        return $this->resolve_for_referral($referral_id);
    }

    /**
     * Build a snapshot ServiceLocation from the current referral state for execution.
     *
     * Always returns a location suitable for freezing (resolved or unresolved).
     * Does not mutate referral/occupancy data.
     *
     * @param array<string, mixed> $referral
     */
    public function snapshot_for_execution(array $referral): ServiceLocation
    {
        $location = $this->resolve_from_referral($referral);

        if ($location->is_resolved()) {
            return $location;
        }

        $label = $location->label();
        if ('' === $label) {
            $label = __('Service location not resolved', 'jm-referral-system');
        }

        return new ServiceLocation(
            [
                'care_setting'     => $location->care_setting(),
                'type'             => ServiceLocation::TYPE_UNRESOLVED,
                'source'           => ServiceLocation::SOURCE_UNRESOLVED,
                'resolved'         => false,
                'label'            => $label,
                'address_line_1'   => null,
                'address_line_2'   => null,
                'city'             => null,
                'postcode'         => null,
                'home_id'          => null,
                'bedroom_id'       => null,
                'occupancy_id'     => null,
                'home_name'        => null,
                'room_label'       => null,
                'address_complete' => false,
                'is_historical'    => false,
            ]
        );
    }

    /**
     * @param array<string, mixed> $referral
     */
    private function resolve_from_referral(array $referral): ServiceLocation
    {
        $care_setting = $referral['care_setting'] ?? null;
        $care_setting = null === $care_setting || '' === trim((string) $care_setting)
            ? null
            : (string) $care_setting;

        if (CareSetting::is_unspecified($care_setting)) {
            return $this->unresolved(
                null,
                __('Care setting not specified', 'jm-referral-system')
            );
        }

        if (CareSetting::is_own_home($care_setting)) {
            return $this->resolve_own_home($referral);
        }

        if (CareSetting::is_supported_living($care_setting)) {
            return $this->resolve_supported_living($referral);
        }

        return $this->unresolved(
            $care_setting,
            __('Care setting not specified', 'jm-referral-system')
        );
    }

    /**
     * @param array<string, mixed> $referral
     */
    private function resolve_own_home(array $referral): ServiceLocation
    {
        $line1    = $this->trim_or_null($referral['address_line_1'] ?? null);
        $line2    = $this->trim_or_null($referral['address_line_2'] ?? null);
        $city     = $this->trim_or_null($referral['city'] ?? null);
        $postcode = $this->trim_or_null($referral['postcode'] ?? null);
        $complete = CareSetting::is_own_home_address_complete($referral);

        return new ServiceLocation(
            [
                'care_setting'     => CareSetting::OWN_HOME,
                'type'             => ServiceLocation::TYPE_OWN_HOME,
                'source'           => ServiceLocation::SOURCE_REFERRAL_ADDRESS,
                'resolved'         => true,
                'label'            => __("Client's Own Home", 'jm-referral-system'),
                'address_line_1'   => $line1,
                'address_line_2'   => $line2,
                'city'             => $city,
                'postcode'         => $postcode,
                'home_id'          => null,
                'bedroom_id'       => null,
                'occupancy_id'     => null,
                'home_name'        => null,
                'room_label'       => null,
                'address_complete' => $complete,
                'is_historical'    => false,
            ]
        );
    }

    /**
     * @param array<string, mixed> $referral
     */
    private function resolve_supported_living(array $referral): ServiceLocation
    {
        $referral_id = absint($referral['id'] ?? 0);
        $occupancy   = $referral_id > 0
            ? $this->occupancy_repository->current_for_referral($referral_id)
            : null;

        if (null === $occupancy) {
            return $this->unresolved(
                CareSetting::SUPPORTED_LIVING,
                __('No active Supported Living placement', 'jm-referral-system')
            );
        }

        $home_id    = absint($occupancy['home_id'] ?? 0);
        $bedroom_id = absint($occupancy['bedroom_id'] ?? 0);
        $occ_id     = absint($occupancy['id'] ?? 0);
        $home       = $home_id > 0 ? $this->home_repository->find($home_id) : null;
        $bedroom    = $bedroom_id > 0 ? $this->bedroom_repository->find($bedroom_id) : null;

        $home_name  = is_array($home) ? trim((string) ($home['name'] ?? '')) : '';
        $room_label = is_array($bedroom) ? trim((string) ($bedroom['room_label'] ?? '')) : '';

        $label_parts = array_filter([$home_name, $room_label]);
        $label       = [] !== $label_parts
            ? implode(' — ', $label_parts)
            : __('Supported Living', 'jm-referral-system');

        $line1    = is_array($home) ? $this->trim_or_null($home['address_line_1'] ?? null) : null;
        $line2    = is_array($home) ? $this->trim_or_null($home['address_line_2'] ?? null) : null;
        $city     = is_array($home) ? $this->trim_or_null($home['city'] ?? null) : null;
        $postcode = is_array($home) ? $this->trim_or_null($home['postcode'] ?? null) : null;

        $address_complete = '' !== (string) $line1
            && '' !== (string) $city
            && '' !== (string) $postcode;

        return new ServiceLocation(
            [
                'care_setting'     => CareSetting::SUPPORTED_LIVING,
                'type'             => ServiceLocation::TYPE_SUPPORTED_LIVING,
                'source'           => ServiceLocation::SOURCE_OCCUPANCY,
                'resolved'         => true,
                'label'            => $label,
                'address_line_1'   => $line1,
                'address_line_2'   => $line2,
                'city'             => $city,
                'postcode'         => $postcode,
                'home_id'          => $home_id > 0 ? $home_id : null,
                'bedroom_id'       => $bedroom_id > 0 ? $bedroom_id : null,
                'occupancy_id'     => $occ_id > 0 ? $occ_id : null,
                'home_name'        => '' !== $home_name ? $home_name : null,
                'room_label'       => '' !== $room_label ? $room_label : null,
                'address_complete' => $address_complete,
                'is_historical'    => false,
            ]
        );
    }

    /**
     * @param array<string, mixed> $visit
     */
    private function resolve_executed_visit(array $visit): ServiceLocation
    {
        $recorded_at = trim((string) ($visit['service_location_recorded_at'] ?? ''));
        $has_snapshot = '' !== $recorded_at
            || '' !== trim((string) ($visit['service_location_type'] ?? ''))
            || '' !== trim((string) ($visit['service_location_label'] ?? ''));

        if (! $has_snapshot) {
            return $this->legacy_unrecorded();
        }

        $type = trim((string) ($visit['service_location_type'] ?? ''));
        if ('' === $type) {
            $type = ServiceLocation::TYPE_UNRESOLVED;
        }

        $label = trim((string) ($visit['service_location_label'] ?? ''));
        if ('' === $label) {
            $label = __('Service location not resolved', 'jm-referral-system');
        }

        $line1    = $this->trim_or_null($visit['service_address_line_1'] ?? null);
        $line2    = $this->trim_or_null($visit['service_address_line_2'] ?? null);
        $city     = $this->trim_or_null($visit['service_city'] ?? null);
        $postcode = $this->trim_or_null($visit['service_postcode'] ?? null);

        return new ServiceLocation(
            [
                'care_setting'     => in_array($type, [ServiceLocation::TYPE_SUPPORTED_LIVING, ServiceLocation::TYPE_OWN_HOME], true)
                    ? $type
                    : null,
                'type'             => $type,
                'source'           => ServiceLocation::SOURCE_VISIT_SNAPSHOT,
                'resolved'         => ServiceLocation::TYPE_UNRESOLVED !== $type,
                'label'            => $label,
                'address_line_1'   => $line1,
                'address_line_2'   => $line2,
                'city'             => $city,
                'postcode'         => $postcode,
                'home_id'          => absint($visit['service_home_id'] ?? 0) ?: null,
                'bedroom_id'       => absint($visit['service_bedroom_id'] ?? 0) ?: null,
                'occupancy_id'     => absint($visit['service_occupancy_id'] ?? 0) ?: null,
                'home_name'        => null,
                'room_label'       => null,
                'address_complete' => null !== $line1 && null !== $city && null !== $postcode,
                'recorded_at'      => '' !== $recorded_at ? $recorded_at : null,
                'is_historical'    => true,
            ]
        );
    }

    private function unresolved(?string $care_setting, string $label): ServiceLocation
    {
        return new ServiceLocation(
            [
                'care_setting'     => $care_setting,
                'type'             => ServiceLocation::TYPE_UNRESOLVED,
                'source'           => ServiceLocation::SOURCE_UNRESOLVED,
                'resolved'         => false,
                'label'            => $label,
                'address_line_1'   => null,
                'address_line_2'   => null,
                'city'             => null,
                'postcode'         => null,
                'home_id'          => null,
                'bedroom_id'       => null,
                'occupancy_id'     => null,
                'home_name'        => null,
                'room_label'       => null,
                'address_complete' => false,
                'is_historical'    => false,
            ]
        );
    }

    /**
     * @param array<string, mixed> $visit
     */
    private function is_executed(array $visit): bool
    {
        return '' !== trim((string) ($visit['visit_outcome'] ?? ''));
    }

    /**
     * Status completed without visit_outcome (and no snapshot) is not verified execution.
     *
     * @param array<string, mixed> $visit
     */
    private function is_manual_completed_without_outcome(array $visit): bool
    {
        $status = strtolower(trim((string) ($visit['visit_status'] ?? '')));
        if ('completed' !== $status) {
            return false;
        }

        if ($this->is_executed($visit)) {
            return false;
        }

        $recorded_at = trim((string) ($visit['service_location_recorded_at'] ?? ''));

        return '' === $recorded_at
            && '' === trim((string) ($visit['service_location_type'] ?? ''))
            && '' === trim((string) ($visit['service_location_label'] ?? ''));
    }

    private function legacy_unrecorded(): ServiceLocation
    {
        return new ServiceLocation(
            [
                'care_setting'     => null,
                'type'             => ServiceLocation::TYPE_UNRESOLVED,
                'source'           => ServiceLocation::SOURCE_LEGACY_UNRECORDED,
                'resolved'         => false,
                'label'            => __('Location not recorded at time of visit', 'jm-referral-system'),
                'address_line_1'   => null,
                'address_line_2'   => null,
                'city'             => null,
                'postcode'         => null,
                'home_id'          => null,
                'bedroom_id'       => null,
                'occupancy_id'     => null,
                'home_name'        => null,
                'room_label'       => null,
                'address_complete' => false,
                'recorded_at'      => null,
                'is_historical'    => true,
            ]
        );
    }

    private function trim_or_null(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim((string) $value);

        return '' !== $value ? $value : null;
    }
}
