<?php

namespace JMReferral\Services;

use JMReferral\Referral\ReferralRepository;

class ServiceTypeService
{
    private const ALLOWED_STATUSES = [
        'active',
        'inactive',
    ];

    public function __construct(
        private ServiceTypeRepository $repository,
        private ReferralRepository $referral_repository
    ) {
    }

    /**
     * Returns all service types.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->repository->all();
    }

    /**
     * Returns active service types for referral dropdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_active(): array
    {
        return $this->repository->find_active();
    }

    /**
     * Returns active service types, ensuring the selected type is included even if inactive.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_options_for_referral(?int $selected_id = null): array
    {
        $options = $this->repository->find_active();

        if (null === $selected_id || $selected_id <= 0) {
            return $options;
        }

        foreach ($options as $option) {
            if ((int) ($option['id'] ?? 0) === $selected_id) {
                return $options;
            }
        }

        $selected = $this->repository->find($selected_id);
        if (null === $selected) {
            return $options;
        }

        $options[] = $selected;

        usort(
            $options,
            static function (array $a, array $b): int {
                return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            }
        );

        return $options;
    }

    /**
     * Finds a service type by ID.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }

    /**
     * Whether a service type can be selected on a referral form.
     */
    public function is_selectable(int $id, ?int $current_id = null): bool
    {
        $service_type = $this->repository->find($id);

        if (null === $service_type) {
            return false;
        }

        if ('active' === ($service_type['status'] ?? '')) {
            return true;
        }

        return null !== $current_id && $current_id === $id;
    }

    /**
     * Creates a service type.
     *
     * @param array<string, string> $input Sanitized form data.
     * @return array{id: int}|array{errors: array<string, string>}|false
     */
    public function create(array $input): array|false
    {
        $errors = $this->validate($input);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $now  = current_time('mysql');
        $name = trim($input['name']);
        $slug = $this->unique_slug($name);

        $id = $this->repository->insert(
            [
                'name'        => $name,
                'slug'        => $slug,
                'description' => '' !== trim($input['description'] ?? '') ? trim($input['description']) : null,
                'status'      => $input['status'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ]
        );

        if (false === $id) {
            return false;
        }

        return ['id' => $id];
    }

    /**
     * Updates a service type.
     *
     * @param array<string, string> $input Sanitized form data.
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    public function update(int $id, array $input): array|false
    {
        $existing = $this->repository->find($id);

        if (null === $existing) {
            return false;
        }

        $errors = $this->validate($input);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $name = trim($input['name']);
        $slug = (string) ($existing['slug'] ?? '');

        if ($name !== (string) ($existing['name'] ?? '')) {
            $slug = $this->unique_slug($name, $id);
        }

        $updated = $this->repository->update(
            $id,
            [
                'name'        => $name,
                'slug'        => $slug,
                'description' => '' !== trim($input['description'] ?? '') ? trim($input['description']) : null,
                'status'      => $input['status'],
                'updated_at'  => current_time('mysql'),
            ]
        );

        if (! $updated) {
            return false;
        }

        return ['ok' => true];
    }

    /**
     * Deletes a service type when it is not in use.
     *
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    public function delete(int $id): array|false
    {
        $existing = $this->repository->find($id);

        if (null === $existing) {
            return false;
        }

        $usage_count = $this->referral_repository->count_by_service_type_id($id);

        if ($usage_count > 0) {
            return [
                'errors' => [
                    'general' => __('This service type cannot be deleted because it is used by one or more referrals.', 'jm-referral-system'),
                ],
            ];
        }

        $deleted = $this->repository->delete($id);

        if (! $deleted) {
            return false;
        }

        return ['ok' => true];
    }

    /**
     * Resolves display names for a list of service type IDs.
     *
     * @param array<int, int> $ids
     * @return array<int, string>
     */
    public function get_names_by_ids(array $ids): array
    {
        return $this->repository->get_names_by_ids($ids);
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $errors = [];

        if ('' === trim($input['name'] ?? '')) {
            $errors['name'] = __('Service name is required.', 'jm-referral-system');
        }

        $status = (string) ($input['status'] ?? '');
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors['status'] = __('Please select a valid status.', 'jm-referral-system');
        }

        return $errors;
    }

    /**
     * Builds a unique slug from a service name.
     */
    private function unique_slug(string $name, ?int $exclude_id = null): string
    {
        $base = sanitize_title($name);

        if ('' === $base) {
            $base = 'service';
        }

        $slug  = $base;
        $index = 2;

        while (true) {
            $existing = $this->repository->find_by_slug($slug);

            if (null === $existing) {
                return $slug;
            }

            if (null !== $exclude_id && (int) ($existing['id'] ?? 0) === $exclude_id) {
                return $slug;
            }

            $slug = $base . '-' . $index;
            ++$index;
        }
    }
}
