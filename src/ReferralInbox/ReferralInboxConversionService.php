<?php

namespace JMReferral\ReferralInbox;

use JMReferral\Database\TransactionEngineGuard;
use JMReferral\Referral\ReferralNumberLock;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Referral\ReferralService;

/**
 * Atomic Inbox → referral conversion (Phase 5D.5).
 *
 * Either one referral is created and the Inbox row is accepted and linked,
 * or the transaction rolls back and the Inbox row is unchanged.
 * Email is sent only after commit.
 */
class ReferralInboxConversionService
{
    public function __construct(
        private ReferralInboxRepository $inbox_repository,
        private ReferralInboxService $inbox_service,
        private ReferralInboxPreparationService $preparation,
        private ReferralService $referral_service,
        private ReferralRepository $referral_repository,
        private ReferralNumberLock $number_lock,
        private TransactionEngineGuard $engine_guard
    ) {
    }

    /**
     * @param array<string, string> $values Sanitized preparation values.
     */
    public function commit(int $inbox_id, array $values, bool $can_assign, int $actor_id, bool $confirmed = true): ReferralInboxConversionResult
    {
        if (! $confirmed) {
            return ReferralInboxConversionResult::of(
                ReferralInboxConversionResult::VALIDATION_ERROR,
                0,
                '',
                '',
                [
                    'confirmation' => __('Please confirm that you have reviewed the referral details.', 'jm-referral-system'),
                ],
                $values
            );
        }

        if (! $this->engine_guard->critical_tables_are_transactional()) {
            return ReferralInboxConversionResult::of(ReferralInboxConversionResult::TRANSACTION_UNAVAILABLE);
        }

        global $wpdb;

        $transaction_open = false;
        $number_held      = false;
        $committed        = false;
        $result           = ReferralInboxConversionResult::of(ReferralInboxConversionResult::PERSISTENCE_ERROR);

        try {
            $wpdb->query('START TRANSACTION');
            $transaction_open = true;

            $locked = $this->inbox_repository->find_for_update($inbox_id);
            if (null === $locked) {
                $result = ReferralInboxConversionResult::of(ReferralInboxConversionResult::NOT_FOUND);
            } else {
                $classified = $this->classify_locked($locked);
                if (null !== $classified) {
                    $result = $classified;
                } elseif (! $this->number_lock->acquire()) {
                    $result = ReferralInboxConversionResult::of(ReferralInboxConversionResult::LOCK_TIMEOUT);
                } else {
                    $number_held = true;
                    if (! $can_assign) {
                        $values['assigned_to'] = '0';
                    }
                    $errors      = $this->preparation->revalidate_values($values, $can_assign);
                    if ([] !== $errors) {
                        $result = ReferralInboxConversionResult::of(
                            ReferralInboxConversionResult::VALIDATION_ERROR,
                            0,
                            '',
                            '',
                            $errors,
                            $values
                        );
                    } else {
                        $result = $this->insert_and_accept($inbox_id, $values, $actor_id);
                        if (ReferralInboxConversionResult::SUCCESS === $result->outcome()) {
                            $wpdb->query('COMMIT');
                            $transaction_open = false;
                            $committed        = true;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $result = ReferralInboxConversionResult::of(ReferralInboxConversionResult::PERSISTENCE_ERROR);
        } finally {
            if ($transaction_open) {
                $wpdb->query('ROLLBACK');
                $transaction_open = false;
            }
            if ($number_held) {
                $this->number_lock->release();
                $number_held = false;
            }
        }

        if (! $committed || ReferralInboxConversionResult::SUCCESS !== $result->outcome()) {
            return $result;
        }

        $warning = '';
        if (! $this->referral_service->dispatch_created_notification($result->referral_id())) {
            $warning = ReferralInboxConversionResult::WARNING_ASSIGNMENT_EMAIL;
        }

        return ReferralInboxConversionResult::of(
            ReferralInboxConversionResult::SUCCESS,
            $result->referral_id(),
            $result->referral_number(),
            $warning
        );
    }

    /**
     * Read-only view of an Inbox row that is already accepted and linked.
     */
    public function existing(int $inbox_id): ?ReferralInboxConversionResult
    {
        $item = $this->inbox_service->find($inbox_id);
        if (null === $item) {
            return null;
        }

        return $this->classify_locked($item);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function classify_locked(array $item): ?ReferralInboxConversionResult
    {
        $status = (string) ($item['status'] ?? '');
        $link   = (int) ($item['linked_referral_id'] ?? 0);

        if (ReferralInboxStatus::ACCEPTED === $status && $link > 0) {
            $referral = $this->referral_repository->find($link);
            $number   = is_array($referral) ? (string) ($referral['referral_number'] ?? '') : '';

            return ReferralInboxConversionResult::of(
                ReferralInboxConversionResult::ALREADY_CONVERTED,
                $link,
                $number
            );
        }

        if (ReferralInboxStatus::ACCEPTED === $status && $link <= 0) {
            return ReferralInboxConversionResult::of(ReferralInboxConversionResult::INCONSISTENT_STATE);
        }

        if (ReferralInboxStatus::NEEDS_REVIEW === $status && $link > 0) {
            return ReferralInboxConversionResult::of(
                ReferralInboxConversionResult::INCONSISTENT_LINK,
                $link
            );
        }

        if (ReferralInboxStatus::NEEDS_REVIEW === $status && $link <= 0) {
            return null;
        }

        return ReferralInboxConversionResult::of(ReferralInboxConversionResult::INVALID_STATE);
    }

    /**
     * @param array<string, string> $values
     */
    private function insert_and_accept(int $inbox_id, array $values, int $actor_id): ReferralInboxConversionResult
    {
        $created = $this->referral_service->create_database_effects($this->referral_input($values));
        if (false === $created) {
            return ReferralInboxConversionResult::of(ReferralInboxConversionResult::CREATE_FAILED);
        }

        $accepted = $this->inbox_service->markAccepted($inbox_id, (int) $created['id'], $actor_id);
        $accept_result = (string) ($accepted['result'] ?? '');
        if (ReferralInboxResult::SUCCESS !== $accept_result) {
            $outcome = ReferralInboxResult::CONFLICT === $accept_result
                ? ReferralInboxConversionResult::CONFLICT
                : ReferralInboxConversionResult::ACCEPT_FAILED;

            return ReferralInboxConversionResult::of($outcome);
        }

        return ReferralInboxConversionResult::of(
            ReferralInboxConversionResult::SUCCESS,
            (int) $created['id'],
            (string) $created['referral_number']
        );
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private function referral_input(array $values): array
    {
        return [
            'client_name'            => (string) ($values['client_name'] ?? ''),
            'client_email'           => (string) ($values['client_email'] ?? ''),
            'client_phone'           => (string) ($values['client_phone'] ?? ''),
            'referrer_name'          => (string) ($values['referrer_name'] ?? ''),
            'referrer_email'         => (string) ($values['referrer_email'] ?? ''),
            'referrer_organisation'  => (string) ($values['referrer_organisation'] ?? ''),
            'service_type_id'        => (string) ($values['service_type_id'] ?? '0'),
            'referral_source'        => (string) ($values['referral_source'] ?? ''),
            'priority'               => (string) ($values['priority'] ?? ''),
            'assigned_to'            => (string) ($values['assigned_to'] ?? '0'),
            'notes'                  => (string) ($values['notes'] ?? ''),
            'status'                 => 'new',
            'submission_channel'     => 'admin',
        ];
    }
}
