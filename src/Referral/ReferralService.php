<?php

namespace JMReferral\Referral;

use JMReferral\Notifications\NotificationService;
use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;
use JMReferral\Workflow\WorkflowStageService;

class ReferralService
{
    public function __construct(
        private ReferralRepository $repository,
        private ReferralNumberGenerator $number_generator,
        private ReferralActivityService $activity_service,
        private UserProvider $user_provider,
        private NotificationService $notification_service,
        private ServiceTypeService $service_type_service,
        private WorkflowStageService $workflow_stage_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Creates a new referral with generated number.
     *
     * Status defaults to "new" when not provided.
     *
     * @param array<string, string> $input Sanitized and validated form data.
     * @return array{id: int, referral_number: string}|false
     */
    public function create(array $input): array|false
    {
        $now                      = current_time('mysql');
        $referral_number          = $this->number_generator->generate();
        $status                   = $input['status'] ?? 'new';
        $assigned_to              = absint($input['assigned_to'] ?? 0);
        if (! Capabilities::current_user_can(Capabilities::ASSIGN_REFERRALS)) {
            $assigned_to = 0;
        }
        $referral_source          = (string) ($input['referral_source'] ?? '');
        $care_start_date          = (string) ($input['care_start_date'] ?? '');
        $preferred_contact_method = (string) ($input['preferred_contact_method'] ?? '');
        $care_requirements        = (string) ($input['care_requirements'] ?? '');
        $service_type_id          = absint($input['service_type_id'] ?? 0);
        $service_required         = $this->resolve_service_required($service_type_id);
        $default_stage            = $this->workflow_stage_service->get_default_stage();
        $workflow_stage_id        = $default_stage ? absint($default_stage['id'] ?? 0) : 0;

        if ('' === $status) {
            $status = 'new';
        }

        $data = [
            'referral_number'          => $referral_number,
            'client_name'              => $input['client_name'],
            'client_email'             => $input['client_email'] !== '' ? $input['client_email'] : null,
            'client_phone'             => $input['client_phone'] !== '' ? $input['client_phone'] : null,
            'service_required'         => $service_required,
            'service_type_id'          => $service_type_id > 0 ? $service_type_id : null,
            'workflow_stage_id'        => $workflow_stage_id > 0 ? $workflow_stage_id : null,
            'referrer_name'            => $input['referrer_name'] !== '' ? $input['referrer_name'] : null,
            'referrer_email'           => $input['referrer_email'] !== '' ? $input['referrer_email'] : null,
            'priority'                 => $input['priority'],
            'notes'                    => $input['notes'] !== '' ? $input['notes'] : null,
            'status'                   => $status,
            'assigned_to'              => $assigned_to > 0 ? $assigned_to : null,
            'referral_source'          => '' !== $referral_source ? $referral_source : null,
            'care_start_date'          => '' !== $care_start_date ? $care_start_date : null,
            'preferred_contact_method' => '' !== $preferred_contact_method ? $preferred_contact_method : null,
            'care_requirements'        => '' !== $care_requirements ? $care_requirements : null,
            'created_at'               => $now,
            'updated_at'               => $now,
        ];

        $id = $this->repository->insert($data);

        if (false === $id) {
            return false;
        }

        $this->activity_service->log_created($id);

        if ($assigned_to > 0) {
            $display_name = $this->user_provider->get_display_name($assigned_to);
            if ('' !== $display_name) {
                $this->activity_service->log_assigned($id, $display_name);
            }
        }

        $referral = $this->repository->find($id);
        if (is_array($referral)) {
            $this->notification_service->notify_referral_created($referral);
        }

        return [
            'id'              => $id,
            'referral_number' => $referral_number,
        ];
    }

    /**
     * Updates an existing referral.
     *
     * Does not modify referral_number or created_at.
     *
     * @param array<string, string> $input Sanitized and validated form data.
     */
    public function update(int $id, array $input): bool
    {
        $existing = $this->repository->find($id);

        if (null === $existing) {
            return false;
        }

        if (! $this->access_policy->can_mutate_referral($existing)) {
            return false;
        }

        $old_status            = (string) ($existing['status'] ?? '');
        $new_status            = (string) ($input['status'] ?? '');
        $old_assigned_to       = absint($existing['assigned_to'] ?? 0);
        $new_assigned_to       = absint($input['assigned_to'] ?? 0);
        if (! Capabilities::current_user_can(Capabilities::ASSIGN_REFERRALS)) {
            $new_assigned_to = $old_assigned_to;
        }
        $assignment_changed    = ($old_assigned_to !== $new_assigned_to);
        $old_workflow_stage_id = absint($existing['workflow_stage_id'] ?? 0);
        $new_workflow_stage_id = absint($input['workflow_stage_id'] ?? 0);

        $referral_source          = (string) ($input['referral_source'] ?? '');
        $care_start_date          = (string) ($input['care_start_date'] ?? '');
        $preferred_contact_method = (string) ($input['preferred_contact_method'] ?? '');
        $care_requirements        = (string) ($input['care_requirements'] ?? '');
        $service_type_id          = absint($input['service_type_id'] ?? 0);
        $service_required         = $this->resolve_service_required(
            $service_type_id,
            (string) ($existing['service_required'] ?? '')
        );

        $data = [
            'client_name'              => $input['client_name'],
            'client_email'             => $input['client_email'] !== '' ? $input['client_email'] : null,
            'client_phone'             => $input['client_phone'] !== '' ? $input['client_phone'] : null,
            'service_required'         => $service_required,
            'service_type_id'          => $service_type_id > 0 ? $service_type_id : null,
            'workflow_stage_id'        => $new_workflow_stage_id > 0 ? $new_workflow_stage_id : null,
            'referrer_name'            => $input['referrer_name'] !== '' ? $input['referrer_name'] : null,
            'referrer_email'           => $input['referrer_email'] !== '' ? $input['referrer_email'] : null,
            'priority'                 => $input['priority'],
            'notes'                    => $input['notes'] !== '' ? $input['notes'] : null,
            'status'                   => $input['status'],
            'assigned_to'              => $new_assigned_to > 0 ? $new_assigned_to : null,
            'referral_source'          => '' !== $referral_source ? $referral_source : null,
            'care_start_date'          => '' !== $care_start_date ? $care_start_date : null,
            'preferred_contact_method' => '' !== $preferred_contact_method ? $preferred_contact_method : null,
            'care_requirements'        => '' !== $care_requirements ? $care_requirements : null,
            'updated_at'               => current_time('mysql'),
        ];

        $updated = $this->repository->update($id, $data);

        if (! $updated) {
            return false;
        }

        $this->activity_service->log_updated($id);

        if ($old_status !== $new_status) {
            $this->activity_service->log_status_changed($id, $old_status, $new_status);
        }

        if ($old_workflow_stage_id !== $new_workflow_stage_id) {
            $old_stage_name = $this->resolve_workflow_stage_name($old_workflow_stage_id);
            $new_stage_name = $this->resolve_workflow_stage_name($new_workflow_stage_id);
            $this->activity_service->log_workflow_stage_changed($id, $old_stage_name, $new_stage_name);
        }

        if ($assignment_changed) {
            $this->log_assignment_change($id, $old_assigned_to, $new_assigned_to);
        }

        $referral = $this->repository->find($id);
        if (is_array($referral)) {
            if ($assignment_changed && $new_assigned_to > 0) {
                // Ensure notify uses the intended assignee even if a stale read occurs.
                $referral['assigned_to'] = $new_assigned_to;
                $this->notification_service->notify_referral_assigned($referral);
            }

            if ($old_status !== $new_status) {
                $this->notification_service->notify_status_changed($referral, $old_status, $new_status);
            }
        }

        return true;
    }

    /**
     * Updates only the workflow stage for a referral.
     */
    public function change_workflow_stage(int $id, int $workflow_stage_id): bool
    {
        $existing = $this->repository->find($id);

        if (null === $existing) {
            return false;
        }

        if (! $this->access_policy->can_mutate_referral($existing)) {
            return false;
        }

        $old_workflow_stage_id = absint($existing['workflow_stage_id'] ?? 0);

        if ($old_workflow_stage_id === $workflow_stage_id) {
            return true;
        }

        if ($workflow_stage_id <= 0 || ! $this->workflow_stage_service->is_selectable(
            $workflow_stage_id,
            $old_workflow_stage_id > 0 ? $old_workflow_stage_id : null
        )) {
            return false;
        }

        $data = [
            'client_name'              => $existing['client_name'],
            'client_email'             => $existing['client_email'],
            'client_phone'             => $existing['client_phone'],
            'service_required'         => $existing['service_required'],
            'service_type_id'          => $existing['service_type_id'],
            'workflow_stage_id'        => $workflow_stage_id,
            'referrer_name'            => $existing['referrer_name'],
            'referrer_email'           => $existing['referrer_email'],
            'priority'                 => $existing['priority'],
            'notes'                    => $existing['notes'],
            'status'                   => $existing['status'],
            'assigned_to'              => $existing['assigned_to'],
            'referral_source'          => $existing['referral_source'],
            'care_start_date'          => $existing['care_start_date'],
            'preferred_contact_method' => $existing['preferred_contact_method'],
            'care_requirements'        => $existing['care_requirements'],
            'updated_at'               => current_time('mysql'),
        ];

        $updated = $this->repository->update($id, $data);

        if (! $updated) {
            return false;
        }

        $this->activity_service->log_workflow_stage_changed(
            $id,
            $this->resolve_workflow_stage_name($old_workflow_stage_id),
            $this->resolve_workflow_stage_name($workflow_stage_id)
        );

        return true;
    }

    /**
     * Builds dashboard statistics and recent referral data.
     *
     * @return array{
     *     stats: array{
     *         total: int,
     *         new: int,
     *         in_progress: int,
     *         completed: int,
     *         cancelled: int
     *     },
     *     pipeline: array<int, array{id: int, name: string, stage_order: int, count: int}>,
     *     recent: array<int, array<string, mixed>>,
     *     scoped_to_assigned: bool
     * }
     */
    public function get_dashboard_data(int $recent_limit = 5): array
    {
        $access_assigned_to = $this->access_policy->get_assigned_user_constraint();
        $recent             = $this->repository->recent($recent_limit, $access_assigned_to);
        $service_type_ids   = [];
        $workflow_stage_ids = [];

        foreach ($recent as $referral) {
            $service_type_id = absint($referral['service_type_id'] ?? 0);
            if ($service_type_id > 0) {
                $service_type_ids[] = $service_type_id;
            }

            $workflow_stage_id = absint($referral['workflow_stage_id'] ?? 0);
            if ($workflow_stage_id > 0) {
                $workflow_stage_ids[] = $workflow_stage_id;
            }
        }

        $service_names = $this->service_type_service->get_names_by_ids($service_type_ids);
        $stage_names   = $this->workflow_stage_service->get_names_by_ids($workflow_stage_ids);

        foreach ($recent as $index => $referral) {
            $service_type_id = absint($referral['service_type_id'] ?? 0);
            if ($service_type_id > 0 && isset($service_names[$service_type_id])) {
                $recent[$index]['service_required'] = $service_names[$service_type_id];
            }

            $workflow_stage_id = absint($referral['workflow_stage_id'] ?? 0);
            $recent[$index]['workflow_stage_name'] = ($workflow_stage_id > 0 && isset($stage_names[$workflow_stage_id]))
                ? $stage_names[$workflow_stage_id]
                : '';

            $recent[$index]['can_edit'] = Capabilities::current_user_can(Capabilities::EDIT_REFERRALS)
                && $this->access_policy->can_mutate_referral($referral);
            $recent[$index]['can_delete'] = Capabilities::current_user_can(Capabilities::DELETE_REFERRALS)
                && $this->access_policy->can_edit_referral($referral);
            $recent[$index]['view_url'] = ReferralViewController::get_view_url(
                absint($referral['id'] ?? 0)
            );
            $recent[$index]['edit_url'] = ReferralEditController::get_edit_url(
                absint($referral['id'] ?? 0)
            );
            $recent[$index]['delete_url'] = ReferralListController::get_delete_url(
                absint($referral['id'] ?? 0)
            );
        }

        return [
            'stats' => [
                'total'       => $this->repository->countAll($access_assigned_to),
                'new'         => $this->repository->countByStatus('new', $access_assigned_to),
                'in_progress' => $this->repository->countByStatus('in_progress', $access_assigned_to),
                'completed'   => $this->repository->countByStatus('completed', $access_assigned_to),
                'cancelled'   => $this->repository->countByStatus('cancelled', $access_assigned_to),
            ],
            'pipeline'            => $this->workflow_stage_service->get_pipeline_counts($access_assigned_to),
            'recent'              => $recent,
            'scoped_to_assigned'  => null !== $access_assigned_to,
        ];
    }

    /**
     * Resolves the denormalized service name for storage/display compatibility.
     */
    private function resolve_service_required(int $service_type_id, string $fallback = ''): string
    {
        if ($service_type_id <= 0) {
            return $fallback;
        }

        $service_type = $this->service_type_service->find($service_type_id);

        if (null === $service_type) {
            return $fallback;
        }

        return (string) ($service_type['name'] ?? $fallback);
    }

    /**
     * Resolves a workflow stage display name.
     */
    private function resolve_workflow_stage_name(int $workflow_stage_id): string
    {
        if ($workflow_stage_id <= 0) {
            return __('None', 'jm-referral-system');
        }

        $stage = $this->workflow_stage_service->find($workflow_stage_id);

        if (null === $stage || '' === (string) ($stage['name'] ?? '')) {
            return __('Unknown', 'jm-referral-system');
        }

        return (string) $stage['name'];
    }

    /**
     * Logs assignment or reassignment activity.
     */
    private function log_assignment_change(int $referral_id, int $old_assigned_to, int $new_assigned_to): void
    {
        $unassigned_label = __('Unassigned', 'jm-referral-system');

        if ($old_assigned_to <= 0 && $new_assigned_to > 0) {
            $display_name = $this->user_provider->get_display_name($new_assigned_to);
            if ('' === $display_name) {
                $display_name = $unassigned_label;
            }
            $this->activity_service->log_assigned($referral_id, $display_name);
            return;
        }

        $old_name = $old_assigned_to > 0
            ? $this->user_provider->get_display_name($old_assigned_to)
            : $unassigned_label;
        $new_name = $new_assigned_to > 0
            ? $this->user_provider->get_display_name($new_assigned_to)
            : $unassigned_label;

        if ('' === $old_name) {
            $old_name = $unassigned_label;
        }
        if ('' === $new_name) {
            $new_name = $unassigned_label;
        }

        $this->activity_service->log_reassigned($referral_id, $old_name, $new_name);
    }
}
