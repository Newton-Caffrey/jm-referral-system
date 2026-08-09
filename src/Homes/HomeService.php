<?php

namespace JMReferral\Homes;

use JMReferral\Users\UserProvider;

class HomeService
{
    private const ALLOWED_STATUSES = [
        'active',
        'inactive',
    ];

    public function __construct(
        private HomeRepository $repository,
        private UserProvider $user_provider,
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
            'name'            => '',
            'address_line_1'  => '',
            'address_line_2'  => '',
            'city'            => '',
            'postcode'        => '',
            'phone'           => '',
            'manager_user_id' => '0',
            'status'          => 'active',
            'notes'           => '',
        ];
    }

    /**
     * @param array<string, mixed>|null $home
     * @return array<string, string>
     */
    public static function map_to_form_data(?array $home): array
    {
        if (null === $home) {
            return self::empty_form_data();
        }

        return [
            'name'            => (string) ($home['name'] ?? ''),
            'address_line_1'  => (string) ($home['address_line_1'] ?? ''),
            'address_line_2'  => (string) ($home['address_line_2'] ?? ''),
            'city'            => (string) ($home['city'] ?? ''),
            'postcode'        => (string) ($home['postcode'] ?? ''),
            'phone'           => (string) ($home['phone'] ?? ''),
            'manager_user_id' => (string) absint($home['manager_user_id'] ?? 0),
            'status'          => (string) ($home['status'] ?? 'active'),
            'notes'           => (string) ($home['notes'] ?? ''),
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
     * @param array{status?: string, search?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $homes = $this->repository->query($filters);
        if ([] === $homes) {
            return [];
        }

        $home_ids     = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $homes);
        $capacity_map = $this->repository->count_active_bedrooms_by_home_ids($home_ids);
        $occupied_map = null !== $this->occupancy_repository
            ? $this->occupancy_repository->count_active_by_home_ids($home_ids)
            : [];

        $manager_ids = [];
        foreach ($homes as $home) {
            $manager_id = absint($home['manager_user_id'] ?? 0);
            if ($manager_id > 0) {
                $manager_ids[] = $manager_id;
            }
        }
        $manager_names = $this->user_provider->get_display_names_by_ids($manager_ids);

        foreach ($homes as $index => $home) {
            $id = (int) ($home['id'] ?? 0);
            $capacity = $capacity_map[$id] ?? 0;
            $occupied = $occupied_map[$id] ?? 0;
            $metrics  = OccupancyService::compute_metrics($capacity, $occupied);
            $homes[$index]['active_bedroom_count'] = $capacity;
            $homes[$index]['capacity']             = $metrics['capacity'];
            $homes[$index]['occupied']             = $metrics['occupied'];
            $homes[$index]['vacant']               = $metrics['vacant'];
            $homes[$index]['occupancy_pct']        = $metrics['occupancy_pct'];
            $manager_id = absint($home['manager_user_id'] ?? 0);
            $homes[$index]['manager_name'] = $manager_id > 0
                ? (string) ($manager_names[$manager_id] ?? '')
                : '';
        }

        return $homes;
    }

    public function count_bedrooms(int $home_id): int
    {
        return $this->repository->count_bedrooms($home_id);
    }

    public function count_active_bedrooms(int $home_id): int
    {
        return $this->repository->count_bedrooms($home_id, 'active');
    }

    /**
     * Capacity for Phase 2B = number of active bedrooms.
     */
    public function capacity(int $home_id): int
    {
        return $this->count_active_bedrooms($home_id);
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
     * @param array<string, mixed> $input
     * @return array{id: int}|array{errors: array<string, string>}|false
     */
    public function create(array $input): array|false
    {
        $sanitized = $this->sanitize_input($input);
        $errors    = $this->validate($sanitized);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $now = current_time('mysql');
        $id  = $this->repository->insert(
            [
                'name'            => $sanitized['name'],
                'address_line_1'  => $sanitized['address_line_1'],
                'address_line_2'  => $sanitized['address_line_2'],
                'city'            => $sanitized['city'],
                'postcode'        => $sanitized['postcode'],
                'phone'           => $sanitized['phone'],
                'manager_user_id' => $sanitized['manager_user_id'],
                'status'          => $sanitized['status'],
                'notes'           => $sanitized['notes'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ]
        );

        if (false === $id) {
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

        $sanitized = $this->sanitize_input($input);
        $errors    = $this->validate($sanitized);

        if (
            'inactive' === ($sanitized['status'] ?? '')
            && 'active' === ($existing['status'] ?? '')
            && null !== $this->occupancy_repository
            && $this->occupancy_repository->count_active_for_home($id) > 0
        ) {
            $errors['status'] = __(
                'This home cannot be made inactive while it has active Supported Living placements. Transfer or move out all residents first.',
                'jm-referral-system'
            );
        }

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $updated = $this->repository->update(
            $id,
            [
                'name'            => $sanitized['name'],
                'address_line_1'  => $sanitized['address_line_1'],
                'address_line_2'  => $sanitized['address_line_2'],
                'city'            => $sanitized['city'],
                'postcode'        => $sanitized['postcode'],
                'phone'           => $sanitized['phone'],
                'manager_user_id' => $sanitized['manager_user_id'],
                'status'          => $sanitized['status'],
                'notes'           => $sanitized['notes'],
                'updated_at'      => current_time('mysql'),
            ]
        );

        if (! $updated) {
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
        $manager_id = absint($input['manager_user_id'] ?? 0);
        $address_2  = sanitize_text_field((string) ($input['address_line_2'] ?? ''));
        $phone      = sanitize_text_field((string) ($input['phone'] ?? ''));
        $notes      = sanitize_textarea_field((string) ($input['notes'] ?? ''));

        return [
            'name'            => sanitize_text_field((string) ($input['name'] ?? '')),
            'address_line_1'  => sanitize_text_field((string) ($input['address_line_1'] ?? '')),
            'address_line_2'  => '' !== $address_2 ? $address_2 : null,
            'city'            => sanitize_text_field((string) ($input['city'] ?? '')),
            'postcode'        => strtoupper(sanitize_text_field((string) ($input['postcode'] ?? ''))),
            'phone'           => '' !== $phone ? $phone : null,
            'manager_user_id' => $manager_id > 0 ? $manager_id : null,
            'status'          => sanitize_key((string) ($input['status'] ?? 'active')),
            'notes'           => '' !== $notes ? $notes : null,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $errors = [];

        if ('' === trim((string) ($input['name'] ?? ''))) {
            $errors['name'] = __('Home name is required.', 'jm-referral-system');
        }

        if ('' === trim((string) ($input['address_line_1'] ?? ''))) {
            $errors['address_line_1'] = __('Address line 1 is required.', 'jm-referral-system');
        }

        if ('' === trim((string) ($input['city'] ?? ''))) {
            $errors['city'] = __('City is required.', 'jm-referral-system');
        }

        if ('' === trim((string) ($input['postcode'] ?? ''))) {
            $errors['postcode'] = __('Postcode is required.', 'jm-referral-system');
        }

        $status = (string) ($input['status'] ?? '');
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors['status'] = __('Please select a valid status.', 'jm-referral-system');
        }

        $manager_id = absint($input['manager_user_id'] ?? 0);
        if ($manager_id > 0 && ! $this->user_provider->is_assignable($manager_id)) {
            $errors['manager_user_id'] = __('Please select a valid staff manager.', 'jm-referral-system');
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
