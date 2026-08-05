<?php

namespace JMReferral\Referral;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;

/**
 * Archive-first retention: permanent delete only when no dependent clinical records exist.
 */
class ReferralRetentionService
{
    public function __construct(
        private ReferralRepository $referral_repository,
        private ReferralDependencyRepository $dependency_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Whether the referral row is archived.
     *
     * @param array<string, mixed>|null $referral
     */
    public function is_archived(?array $referral): bool
    {
        if (! is_array($referral)) {
            return false;
        }

        $archived_at = $referral['archived_at'] ?? null;

        return null !== $archived_at && '' !== (string) $archived_at;
    }

    /**
     * Dependency counts used for deletion gating and UI messaging.
     *
     * @return array{
     *     notes: int,
     *     documents: int,
     *     assessment: int,
     *     care_plan: int,
     *     care_plan_versions: int,
     *     care_plan_reviews: int,
     *     care_team_assignments: int,
     *     schedules: int,
     *     visits: int,
     *     visit_tasks: int,
     *     medications: int,
     *     medication_administrations: int,
     *     activity_entries: int,
     *     total_blocking: int
     * }
     */
    public function get_dependency_summary(int $referral_id): array
    {
        $notes         = $this->dependency_repository->count_notes($referral_id);
        $documents     = $this->dependency_repository->count_documents($referral_id);
        $assessment    = $this->dependency_repository->count_assessments($referral_id);
        $care_plan     = $this->dependency_repository->count_care_plans($referral_id);
        $versions      = $this->dependency_repository->count_care_plan_versions($referral_id);
        $reviews       = $this->dependency_repository->count_care_plan_reviews($referral_id);
        $care_team     = $this->dependency_repository->count_care_team($referral_id);
        $schedules     = $this->dependency_repository->count_schedules($referral_id);
        $visits        = $this->dependency_repository->count_visits($referral_id);
        $visit_tasks   = $this->dependency_repository->count_visit_tasks($referral_id);
        $medications   = $this->dependency_repository->count_medications($referral_id);
        $administrations = $this->dependency_repository->count_medication_administrations($referral_id);
        // Initial "created" activity alone does not block permanent deletion.
        $activity      = $this->dependency_repository->count_blocking_activity($referral_id);

        $total = $notes + $documents + $assessment + $care_plan + $versions + $reviews
            + $care_team + $schedules + $visits + $visit_tasks + $medications
            + $administrations + $activity;

        return [
            'notes'                       => $notes,
            'documents'                   => $documents,
            'assessment'                  => $assessment,
            'care_plan'                   => $care_plan,
            'care_plan_versions'          => $versions,
            'care_plan_reviews'           => $reviews,
            'care_team_assignments'       => $care_team,
            'schedules'                   => $schedules,
            'visits'                      => $visits,
            'visit_tasks'                 => $visit_tasks,
            'medications'                 => $medications,
            'medication_administrations'  => $administrations,
            'activity_entries'            => $activity,
            'total_blocking'              => $total,
        ];
    }

    /**
     * Whether permanent deletion is allowed for this referral.
     */
    public function can_permanently_delete(int $referral_id): bool
    {
        if ($referral_id <= 0) {
            return false;
        }

        $summary = $this->get_dependency_summary($referral_id);

        return 0 === (int) ($summary['total_blocking'] ?? 0);
    }

    /**
     * Archives a referral. Preserves all child records.
     *
     * @return array{success: bool, message: string}
     */
    public function archive(int $referral_id, string $reason, ?int $user_id = null): array
    {
        $user_id = null === $user_id ? get_current_user_id() : $user_id;
        $reason  = trim(sanitize_textarea_field($reason));

        if ($referral_id <= 0 || $user_id <= 0) {
            return [
                'success' => false,
                'message' => __('Unable to archive the referral.', 'jm-referral-system'),
            ];
        }

        if ('' === $reason) {
            return [
                'success' => false,
                'message' => __('An archive reason is required.', 'jm-referral-system'),
            ];
        }

        if (! user_can($user_id, Capabilities::ARCHIVE_REFERRALS)) {
            return [
                'success' => false,
                'message' => __('You do not have permission.', 'jm-referral-system'),
            ];
        }

        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral || ! $this->access_policy->can_edit_referral($referral, $user_id)) {
            return [
                'success' => false,
                'message' => __('You do not have permission.', 'jm-referral-system'),
            ];
        }

        if ($this->is_archived($referral)) {
            return [
                'success' => false,
                'message' => __('This referral is already archived.', 'jm-referral-system'),
            ];
        }

        $updated = $this->referral_repository->set_archive_state(
            $referral_id,
            current_time('mysql'),
            $user_id,
            $reason
        );

        if (! $updated) {
            return [
                'success' => false,
                'message' => __('Unable to archive the referral.', 'jm-referral-system'),
            ];
        }

        $this->activity_service->log_action(
            $referral_id,
            'referral_archived',
            __('Referral archived', 'jm-referral-system')
        );

        return [
            'success' => true,
            'message' => __('Referral archived successfully.', 'jm-referral-system'),
        ];
    }

    /**
     * Restores an archived referral.
     *
     * @return array{success: bool, message: string}
     */
    public function restore(int $referral_id, ?int $user_id = null): array
    {
        $user_id = null === $user_id ? get_current_user_id() : $user_id;

        if ($referral_id <= 0 || $user_id <= 0) {
            return [
                'success' => false,
                'message' => __('Unable to restore the referral.', 'jm-referral-system'),
            ];
        }

        if (! user_can($user_id, Capabilities::RESTORE_REFERRALS)) {
            return [
                'success' => false,
                'message' => __('You do not have permission.', 'jm-referral-system'),
            ];
        }

        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral || ! $this->access_policy->can_view_referral($referral, $user_id)) {
            return [
                'success' => false,
                'message' => __('You do not have permission.', 'jm-referral-system'),
            ];
        }

        if (! $this->is_archived($referral)) {
            return [
                'success' => false,
                'message' => __('This referral is not archived.', 'jm-referral-system'),
            ];
        }

        $updated = $this->referral_repository->set_archive_state($referral_id, null, null, null);

        if (! $updated) {
            return [
                'success' => false,
                'message' => __('Unable to restore the referral.', 'jm-referral-system'),
            ];
        }

        $this->activity_service->log_action(
            $referral_id,
            'referral_restored',
            __('Referral restored', 'jm-referral-system')
        );

        return [
            'success' => true,
            'message' => __('Referral restored successfully.', 'jm-referral-system'),
        ];
    }

    /**
     * Permanently deletes a referral only when no blocking dependents exist.
     *
     * @return array{success: bool, message: string, blocked: bool}
     */
    public function permanently_delete(int $referral_id, ?int $user_id = null): array
    {
        $user_id = null === $user_id ? get_current_user_id() : $user_id;

        if ($referral_id <= 0 || $user_id <= 0) {
            return [
                'success' => false,
                'blocked' => false,
                'message' => __('Unable to delete the referral.', 'jm-referral-system'),
            ];
        }

        if (! user_can($user_id, Capabilities::DELETE_REFERRALS)) {
            return [
                'success' => false,
                'blocked' => false,
                'message' => __('You do not have permission.', 'jm-referral-system'),
            ];
        }

        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral || ! $this->access_policy->can_edit_referral($referral, $user_id)) {
            return [
                'success' => false,
                'blocked' => false,
                'message' => __('You do not have permission.', 'jm-referral-system'),
            ];
        }

        if (! $this->can_permanently_delete($referral_id)) {
            return [
                'success' => false,
                'blocked' => true,
                'message' => __(
                    'This referral contains linked records and cannot be permanently deleted. Archive it instead.',
                    'jm-referral-system'
                ),
            ];
        }

        global $wpdb;

        $wpdb->query('START TRANSACTION');

        try {
            // Safe empty-referral metadata: bootstrap activity only (no clinical children remain).
            $this->dependency_repository->delete_activity_for_referral($referral_id);

            if (! $this->can_permanently_delete($referral_id)) {
                $wpdb->query('ROLLBACK');

                return [
                    'success' => false,
                    'blocked' => true,
                    'message' => __(
                        'This referral contains linked records and cannot be permanently deleted. Archive it instead.',
                        'jm-referral-system'
                    ),
                ];
            }

            $deleted = $this->referral_repository->delete($referral_id);

            if (! $deleted) {
                $wpdb->query('ROLLBACK');

                return [
                    'success' => false,
                    'blocked' => false,
                    'message' => __('Unable to delete the referral.', 'jm-referral-system'),
                ];
            }

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');

            return [
                'success' => false,
                'blocked' => false,
                'message' => __('Unable to delete the referral.', 'jm-referral-system'),
            ];
        }

        return [
            'success' => true,
            'blocked' => false,
            'message' => __('Referral deleted successfully.', 'jm-referral-system'),
        ];
    }
}
