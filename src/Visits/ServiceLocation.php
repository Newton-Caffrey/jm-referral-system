<?php

namespace JMReferral\Visits;

/**
 * Resolved or historical service location (Phase 2F).
 *
 * Current (unexecuted) locations come from ServiceLocationResolver.
 * Historical snapshots are frozen onto jmrs_care_visits at execution.
 */
class ServiceLocation
{
    public const TYPE_SUPPORTED_LIVING = 'supported_living';
    public const TYPE_OWN_HOME = 'own_home';
    public const TYPE_UNRESOLVED = 'unresolved';

    public const SOURCE_OCCUPANCY = 'occupancy';
    public const SOURCE_REFERRAL_ADDRESS = 'referral_address';
    public const SOURCE_VISIT_SNAPSHOT = 'visit_snapshot';
    public const SOURCE_UNRESOLVED = 'unresolved';
    public const SOURCE_LEGACY_UNRECORDED = 'legacy_unrecorded';

    /**
     * @param array{
     *     care_setting?: string|null,
     *     type?: string|null,
     *     source?: string,
     *     resolved?: bool,
     *     label?: string,
     *     address_line_1?: string|null,
     *     address_line_2?: string|null,
     *     city?: string|null,
     *     postcode?: string|null,
     *     home_id?: int|null,
     *     bedroom_id?: int|null,
     *     occupancy_id?: int|null,
     *     home_name?: string|null,
     *     room_label?: string|null,
     *     address_complete?: bool,
     *     recorded_at?: string|null,
     *     is_historical?: bool
     * } $data
     */
    public function __construct(private array $data)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return [
            'care_setting'     => $this->care_setting(),
            'type'             => $this->type(),
            'source'           => $this->source(),
            'resolved'         => $this->is_resolved(),
            'label'            => $this->label(),
            'address_line_1'   => $this->address_line_1(),
            'address_line_2'   => $this->address_line_2(),
            'city'             => $this->city(),
            'postcode'         => $this->postcode(),
            'home_id'          => $this->home_id(),
            'bedroom_id'       => $this->bedroom_id(),
            'occupancy_id'     => $this->occupancy_id(),
            'home_name'        => $this->home_name(),
            'room_label'       => $this->room_label(),
            'address_complete' => $this->is_address_complete(),
            'recorded_at'      => $this->recorded_at(),
            'is_historical'    => $this->is_historical(),
        ];
    }

    public function care_setting(): ?string
    {
        $value = $this->data['care_setting'] ?? null;

        return null === $value || '' === trim((string) $value) ? null : (string) $value;
    }

    public function type(): string
    {
        $type = (string) ($this->data['type'] ?? self::TYPE_UNRESOLVED);

        return '' !== $type ? $type : self::TYPE_UNRESOLVED;
    }

    public function source(): string
    {
        return (string) ($this->data['source'] ?? self::SOURCE_UNRESOLVED);
    }

    public function is_resolved(): bool
    {
        return ! empty($this->data['resolved']);
    }

    public function label(): string
    {
        return (string) ($this->data['label'] ?? '');
    }

    public function address_line_1(): ?string
    {
        return $this->nullable_string($this->data['address_line_1'] ?? null);
    }

    public function address_line_2(): ?string
    {
        return $this->nullable_string($this->data['address_line_2'] ?? null);
    }

    public function city(): ?string
    {
        return $this->nullable_string($this->data['city'] ?? null);
    }

    public function postcode(): ?string
    {
        return $this->nullable_string($this->data['postcode'] ?? null);
    }

    public function home_id(): ?int
    {
        $id = absint($this->data['home_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public function bedroom_id(): ?int
    {
        $id = absint($this->data['bedroom_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public function occupancy_id(): ?int
    {
        $id = absint($this->data['occupancy_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public function home_name(): ?string
    {
        return $this->nullable_string($this->data['home_name'] ?? null);
    }

    public function room_label(): ?string
    {
        return $this->nullable_string($this->data['room_label'] ?? null);
    }

    public function is_address_complete(): bool
    {
        return ! empty($this->data['address_complete']);
    }

    public function recorded_at(): ?string
    {
        return $this->nullable_string($this->data['recorded_at'] ?? null);
    }

    public function is_historical(): bool
    {
        return ! empty($this->data['is_historical']);
    }

    /**
     * Payload for persisting an execution-time snapshot onto jmrs_care_visits.
     *
     * @return array<string, mixed>
     */
    public function to_snapshot_row(string $recorded_at): array
    {
        return [
            'service_location_type'         => $this->type(),
            'service_location_label'        => $this->label() !== '' ? $this->label() : null,
            'service_address_line_1'        => $this->address_line_1(),
            'service_address_line_2'        => $this->address_line_2(),
            'service_city'                  => $this->city(),
            'service_postcode'              => $this->postcode(),
            'service_home_id'               => $this->home_id(),
            'service_bedroom_id'            => $this->bedroom_id(),
            'service_occupancy_id'          => $this->occupancy_id(),
            'service_location_recorded_at'  => $recorded_at,
        ];
    }

    private function nullable_string(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim((string) $value);

        return '' !== $value ? $value : null;
    }
}
