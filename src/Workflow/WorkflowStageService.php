<?php

namespace JMReferral\Workflow;

use JMReferral\Pipeline\PipelineStage;
use JMReferral\Referral\ReferralRepository;

class WorkflowStageService
{
    private const ALLOWED_STATUSES = [
        'active',
        'inactive',
    ];

    public function __construct(
        private WorkflowStageRepository $repository,
        private ReferralRepository $referral_repository
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->repository->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_active(): array
    {
        return $this->repository->find_active();
    }

    /**
     * Returns active stages, ensuring the selected stage is included even if inactive.
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
                $order_a = (int) ($a['stage_order'] ?? 0);
                $order_b = (int) ($b['stage_order'] ?? 0);

                if ($order_a === $order_b) {
                    return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                }

                return $order_a <=> $order_b;
            }
        );

        return $options;
    }

    /**
     * Legacy/custom stages only (excludes canonical pipeline stages).
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_legacy_options_for_referral(?int $selected_id = null): array
    {
        $options = $this->repository->find_active_legacy();

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
                $order_a = (int) ($a['stage_order'] ?? 0);
                $order_b = (int) ($b['stage_order'] ?? 0);

                if ($order_a === $order_b) {
                    return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                }

                return $order_a <=> $order_b;
            }
        );

        return $options;
    }

    public function is_system_stage(array $stage): bool
    {
        return ! empty($stage['is_system']) || PipelineStage::is_canonical((string) ($stage['slug'] ?? ''));
    }

    public function is_pipeline_stage(array $stage): bool
    {
        $slug = (string) ($stage['slug'] ?? '');

        return ! empty($stage['is_pipeline_stage']) && PipelineStage::is_canonical($slug);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }

    /**
     * Default stage for new referrals (canonical Interest Response Required when seeded).
     *
     * @return array<string, mixed>|null
     */
    public function get_default_stage(): ?array
    {
        return $this->repository->find_first_active();
    }

    /**
     * Whether a workflow stage can be selected on a referral form.
     *
     * Canonical pipeline stages are not newly selectable via generic dropdowns.
     */
    public function is_selectable(int $id, ?int $current_id = null): bool
    {
        $stage = $this->repository->find($id);

        if (null === $stage) {
            return false;
        }

        if ($this->is_pipeline_stage($stage)) {
            return null !== $current_id && $current_id === $id;
        }

        if ('active' === ($stage['status'] ?? '')) {
            return true;
        }

        return null !== $current_id && $current_id === $id;
    }

    /**
     * @param array<string, string> $input
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

        if (PipelineStage::is_canonical($slug)) {
            return [
                'errors' => [
                    'name' => __('That name resolves to a reserved pipeline stage slug. Please choose a different name.', 'jm-referral-system'),
                ],
            ];
        }

        $id = $this->repository->insert(
            [
                'name'              => $name,
                'slug'              => $slug,
                'description'       => '' !== trim($input['description'] ?? '') ? trim($input['description']) : null,
                'stage_order'       => absint($input['stage_order'] ?? 0),
                'status'            => $input['status'],
                'is_system'         => 0,
                'is_pipeline_stage' => 0,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]
        );

        if (false === $id) {
            return false;
        }

        return ['id' => $id];
    }

    /**
     * @param array<string, string> $input
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    public function update(int $id, array $input): array|false
    {
        $existing = $this->repository->find($id);

        if (null === $existing) {
            return false;
        }

        if ($this->is_system_stage($existing)) {
            $description = '' !== trim($input['description'] ?? '') ? trim($input['description']) : null;
            $stage_order = absint($input['stage_order'] ?? ($existing['stage_order'] ?? 0));

            $updated = $this->repository->update(
                $id,
                [
                    'name'              => (string) ($existing['name'] ?? ''),
                    'slug'              => (string) ($existing['slug'] ?? ''),
                    'description'       => $description,
                    'stage_order'       => $stage_order,
                    'status'            => 'active',
                    'is_system'         => 1,
                    'is_pipeline_stage' => ! empty($existing['is_pipeline_stage']) ? 1 : 0,
                    'updated_at'        => current_time('mysql'),
                ]
            );

            if (! $updated) {
                return false;
            }

            return ['ok' => true];
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

        if (PipelineStage::is_canonical($slug)) {
            return [
                'errors' => [
                    'name' => __('That name resolves to a reserved pipeline stage slug. Please choose a different name.', 'jm-referral-system'),
                ],
            ];
        }

        $updated = $this->repository->update(
            $id,
            [
                'name'              => $name,
                'slug'              => $slug,
                'description'       => '' !== trim($input['description'] ?? '') ? trim($input['description']) : null,
                'stage_order'       => absint($input['stage_order'] ?? 0),
                'status'            => $input['status'],
                'is_system'         => 0,
                'is_pipeline_stage' => 0,
                'updated_at'        => current_time('mysql'),
            ]
        );

        if (! $updated) {
            return false;
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    public function delete(int $id): array|false
    {
        $existing = $this->repository->find($id);

        if (null === $existing) {
            return false;
        }

        if ($this->is_system_stage($existing)) {
            return [
                'errors' => [
                    'general' => __('System pipeline stages cannot be deleted.', 'jm-referral-system'),
                ],
            ];
        }

        $usage_count = $this->referral_repository->count_by_workflow_stage_id($id);

        if ($usage_count > 0) {
            return [
                'errors' => [
                    'general' => __('This workflow stage cannot be deleted because it is used by one or more referrals.', 'jm-referral-system'),
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
     * @param array<int, int> $ids
     * @return array<int, string>
     */
    public function get_names_by_ids(array $ids): array
    {
        return $this->repository->get_names_by_ids($ids);
    }

    /**
     * Builds pipeline counts for the dashboard.
     *
     * @param int|null $access_assigned_to Optional record-level assignee constraint.
     * @return array<int, array{id: int, name: string, stage_order: int, count: int}>
     */
    public function get_pipeline_counts(?int $access_assigned_to = null): array
    {
        $stages   = $this->repository->all();
        $counts   = $this->referral_repository->count_grouped_by_workflow_stage($access_assigned_to);
        $pipeline = [];

        foreach ($stages as $stage) {
            $id = (int) ($stage['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $pipeline[] = [
                'id'          => $id,
                'name'        => (string) ($stage['name'] ?? ''),
                'stage_order' => (int) ($stage['stage_order'] ?? 0),
                'count'       => (int) ($counts[$id] ?? 0),
            ];
        }

        return $pipeline;
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $errors = [];

        if ('' === trim($input['name'] ?? '')) {
            $errors['name'] = __('Stage name is required.', 'jm-referral-system');
        }

        if (! isset($input['stage_order']) || '' === (string) $input['stage_order']) {
            $errors['stage_order'] = __('Stage order is required.', 'jm-referral-system');
        } elseif ((int) $input['stage_order'] < 0) {
            $errors['stage_order'] = __('Stage order must be zero or greater.', 'jm-referral-system');
        }

        $status = (string) ($input['status'] ?? '');
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors['status'] = __('Please select a valid status.', 'jm-referral-system');
        }

        return $errors;
    }

    private function unique_slug(string $name, ?int $exclude_id = null): string
    {
        $base = sanitize_title($name);

        if ('' === $base) {
            $base = 'workflow-stage';
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
