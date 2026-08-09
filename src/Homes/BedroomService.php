<?php

namespace JMReferral\Homes;

class BedroomService
{
    private const ALLOWED_STATUSES = [
        'active',
        'inactive',
    ];

    public function __construct(
        private BedroomRepository $repository,
        private HomeRepository $home_repository,
        private ?OccupancyRepository $occupancy_repository = null
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function status_labels(): array
    {
        return [
            'active'   => __('Active', 'jm-referral-system'),
            'inactive' => __('Inactive', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function empty_form_data(): array
    {
        return [
            'room_label' => '',
            'floor'      => '',
            'status'     => 'active',
            'notes'      => '',
        ];
    }

    /**
     * @param array<string, mixed>|null $bedroom
     * @return array<string, string>
     */
    public static function map_to_form_data(?array $bedroom): array
    {
        if (null === $bedroom) {
            return self::empty_form_data();
        }

        return [
            'room_label' => (string) ($bedroom['room_label'] ?? ''),
            'floor'      => (string) ($bedroom['floor'] ?? ''),
            'status'     => (string) ($bedroom['status'] ?? 'active'),
            'notes'      => (string) ($bedroom['notes'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list_by_home(int $home_id, ?string $status = null): array
    {
        return $this->repository->list_by_home($home_id, $status);
    }

    public function activate(int $id): array|false
    {
        return $this->set_status($id, 'active');
    }

    public function inactivate(int $id): array|false
    {
        return $this->set_status($id, 'inactive');
    }

    /**
     * Ensures the bedroom belongs to the given home.
     */
    public function belongs_to_home(int $bedroom_id, int $home_id): bool
    {
        $bedroom = $this->repository->find($bedroom_id);
        if (null === $bedroom) {
            return false;
        }

        return absint($bedroom['home_id'] ?? 0) === $home_id;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}|array{errors: array<string, string>}|false
     */
    public function create(int $home_id, array $input): array|false
    {
        $home = $this->home_repository->find($home_id);
        if (null === $home) {
            return [
                'errors' => [
                    'home_id' => __('The selected home does not exist.', 'jm-referral-system'),
                ],
            ];
        }

        $sanitized = $this->sanitize_input($input);
        $errors    = $this->validate($home_id, $sanitized, null, $home);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $now = current_time('mysql');
        $id  = $this->repository->insert(
            [
                'home_id'    => $home_id,
                'room_label' => $sanitized['room_label'],
                'floor'      => $sanitized['floor'],
                'status'     => $sanitized['status'],
                'notes'      => $sanitized['notes'],
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        if (false === $id) {
            // Likely unique-key collision race; surface as validation.
            if ($this->repository->room_label_exists($home_id, $sanitized['room_label'])) {
                return [
                    'errors' => [
                        'room_label' => __('A bedroom with this room label already exists in this home.', 'jm-referral-system'),
                    ],
                ];
            }

            return false;
        }

        return ['id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    public function update(int $id, array $input): array|false
    {
        $existing = $this->repository->find($id);
        if (null === $existing) {
            return false;
        }

        $home_id = absint($existing['home_id'] ?? 0);
        $home    = $this->home_repository->find($home_id);
        if (null === $home) {
            return [
                'errors' => [
                    'home_id' => __('The bedroom home relationship is invalid.', 'jm-referral-system'),
                ],
            ];
        }

        $sanitized = $this->sanitize_input($input);
        $errors    = $this->validate($home_id, $sanitized, $id, $home, true);

        if (
            'inactive' === ($sanitized['status'] ?? '')
            && 'active' === ($existing['status'] ?? '')
            && null !== $this->occupancy_repository
            && null !== $this->occupancy_repository->current_for_bedroom($id)
        ) {
            $errors['status'] = __(
                'This bedroom cannot be made inactive while it has an active resident. Transfer or move out the resident first.',
                'jm-referral-system'
            );
        }

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $updated = $this->repository->update(
            $id,
            [
                'room_label' => $sanitized['room_label'],
                'floor'      => $sanitized['floor'],
                'status'     => $sanitized['status'],
                'notes'      => $sanitized['notes'],
                'updated_at' => current_time('mysql'),
            ]
        );

        if (! $updated) {
            if ($this->repository->room_label_exists($home_id, $sanitized['room_label'], $id)) {
                return [
                    'errors' => [
                        'room_label' => __('A bedroom with this room label already exists in this home.', 'jm-referral-system'),
                    ],
                ];
            }

            return false;
        }

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function sanitize_input(array $input): array
    {
        $floor = sanitize_text_field((string) ($input['floor'] ?? ''));
        $notes = sanitize_textarea_field((string) ($input['notes'] ?? ''));

        return [
            'room_label' => sanitize_text_field((string) ($input['room_label'] ?? '')),
            'floor'      => '' !== $floor ? $floor : null,
            'status'     => sanitize_key((string) ($input['status'] ?? 'active')),
            'notes'      => '' !== $notes ? $notes : null,
        ];
    }

    /**
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $home
     * @return array<string, string>
     */
    private function validate(
        int $home_id,
        array $input,
        ?int $exclude_id,
        ?array $home,
        bool $is_update = false
    ): array {
        $errors = [];

        if ($home_id <= 0 || null === $home) {
            $errors['home_id'] = __('A valid home is required.', 'jm-referral-system');

            return $errors;
        }

        $room_label = trim((string) ($input['room_label'] ?? ''));
        if ('' === $room_label) {
            $errors['room_label'] = __('Room label is required.', 'jm-referral-system');
        } elseif ($this->repository->room_label_exists($home_id, $room_label, $exclude_id)) {
            $errors['room_label'] = __('A bedroom with this room label already exists in this home.', 'jm-referral-system');
        }

        $status = (string) ($input['status'] ?? '');
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors['status'] = __('Please select a valid status.', 'jm-referral-system');
        }

        // New bedrooms (and activating/creating under inactive homes) are rejected.
        if (! $is_update && 'inactive' === ($home['status'] ?? '')) {
            $errors['home_id'] = __('New bedrooms cannot be added to an inactive home.', 'jm-referral-system');
        }

        if ($is_update && 'active' === $status && 'inactive' === ($home['status'] ?? '')) {
            $existing = null !== $exclude_id ? $this->repository->find($exclude_id) : null;
            $was_active = null !== $existing && 'active' === ($existing['status'] ?? '');
            if (! $was_active) {
                $errors['status'] = __('Active bedrooms cannot be created under an inactive home.', 'jm-referral-system');
            }
        }

        return $errors;
    }

    /**
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    private function set_status(int $id, string $status): array|false
    {
        $existing = $this->repository->find($id);
        if (null === $existing) {
            return false;
        }

        $input = self::map_to_form_data($existing);
        $input['status'] = $status;

        return $this->update($id, $input);
    }
}
