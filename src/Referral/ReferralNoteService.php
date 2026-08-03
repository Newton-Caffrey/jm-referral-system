<?php

namespace JMReferral\Referral;

use JMReferral\Permissions\AccessPolicy;

class ReferralNoteService
{
    public function __construct(
        private ReferralNoteRepository $note_repository,
        private ReferralRepository $referral_repository,
        private ReferralActivityService $activity_service,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Validates and saves an internal staff note.
     *
     * @return array{id: int}|array{errors: array<string, string>}|false
     */
    public function add_note(int $referral_id, string $note): array|false
    {
        $note   = trim($note);
        $errors = $this->validate($referral_id, $note);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $id = $this->note_repository->create(
            [
                'referral_id' => $referral_id,
                'user_id'     => get_current_user_id(),
                'note'        => $note,
            ]
        );

        if (false === $id) {
            return false;
        }

        $this->activity_service->log_note_added($referral_id);

        return ['id' => $id];
    }

    /**
     * Returns notes for a referral.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_notes(int $referral_id): array
    {
        return $this->note_repository->get_by_referral_id($referral_id);
    }

    /**
     * @return array<string, string>
     */
    private function validate(int $referral_id, string $note): array
    {
        $errors = [];

        $referral = $referral_id > 0 ? $this->referral_repository->find($referral_id) : null;

        if (null === $referral) {
            $errors['referral_id'] = __('Referral not found.', 'jm-referral-system');
        } elseif (! $this->access_policy->can_view_referral($referral)) {
            $errors['referral_id'] = __('You do not have permission to add notes to this referral.', 'jm-referral-system');
        }

        if ('' === $note) {
            $errors['note'] = __('Please enter a note.', 'jm-referral-system');
        }

        return $errors;
    }
}
