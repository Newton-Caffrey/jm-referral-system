<?php

namespace JMReferral\Referral;

class ReferralActivityService
{
    public function __construct(
        private ReferralActivityRepository $repository
    ) {
    }

    /**
     * Logs that a referral was created.
     */
    public function log_created(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'created',
            __('Referral created', 'jm-referral-system')
        );
    }

    /**
     * Logs that a referral was updated.
     */
    public function log_updated(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'updated',
            __('Referral updated', 'jm-referral-system')
        );
    }

    /**
     * Logs that a referral status changed.
     */
    public function log_status_changed(int $referral_id, string $old_status, string $new_status): void
    {
        $description = sprintf(
            /* translators: 1: previous status, 2: new status */
            __('Status changed from %1$s to %2$s', 'jm-referral-system'),
            $old_status,
            $new_status
        );

        $this->log($referral_id, 'status_changed', $description);
    }

    /**
     * Logs that a referral was assigned.
     */
    public function log_assigned(int $referral_id, string $display_name): void
    {
        $description = sprintf(
            /* translators: %s: assignee display name */
            __('Referral assigned to %s', 'jm-referral-system'),
            $display_name
        );

        $this->log($referral_id, 'assigned', $description);
    }

    /**
     * Logs that a referral was reassigned.
     */
    public function log_reassigned(int $referral_id, string $old_name, string $new_name): void
    {
        $description = sprintf(
            /* translators: 1: previous assignee, 2: new assignee */
            __('Referral reassigned from %1$s to %2$s', 'jm-referral-system'),
            $old_name,
            $new_name
        );

        $this->log($referral_id, 'reassigned', $description);
    }

    /**
     * Logs that a referral workflow stage changed.
     */
    public function log_workflow_stage_changed(int $referral_id, string $old_stage, string $new_stage): void
    {
        $description = sprintf(
            /* translators: 1: previous workflow stage, 2: new workflow stage */
            __('Workflow stage changed from %1$s to %2$s', 'jm-referral-system'),
            $old_stage,
            $new_stage
        );

        $this->log($referral_id, 'workflow_stage_changed', $description);
    }

    /**
     * Logs that an internal note was added.
     */
    public function log_note_added(int $referral_id): void
    {
        $this->log(
            $referral_id,
            'note_added',
            __('Internal note added', 'jm-referral-system')
        );
    }

    /**
     * Logs that a document was uploaded.
     */
    public function log_document_uploaded(int $referral_id, string $original_filename): void
    {
        $description = sprintf(
            /* translators: %s: original uploaded filename */
            __('Document uploaded: %s', 'jm-referral-system'),
            $original_filename
        );

        $this->log($referral_id, 'document_uploaded', $description);
    }

    /**
     * Persists an activity entry for the current user.
     */
    private function log(int $referral_id, string $action, string $description): void
    {
        if ($referral_id <= 0 || '' === $action) {
            return;
        }

        $this->repository->create(
            [
                'referral_id' => $referral_id,
                'user_id'     => get_current_user_id(),
                'action'      => $action,
                'description' => $description,
            ]
        );
    }
}
