<?php

namespace JMReferral\Residents;

use JMReferral\Database\Tables;
use JMReferral\Database\TransactionEngineGuard;
use JMReferral\Frontend\SubmissionChannels;
use JMReferral\Homes\BedroomRepository;
use JMReferral\Homes\HomeRepository;
use JMReferral\Homes\OccupancyRepository;
use JMReferral\Permissions\Capabilities;
use JMReferral\Pipeline\PipelineStage;
use JMReferral\Pipeline\ReferralPipelineService;
use JMReferral\Pipeline\ReferralStageHistoryRepository;
use JMReferral\Referral\CareSetting;
use JMReferral\Referral\ReferralActivityRepository;
use JMReferral\Referral\ReferralNumberGenerator;
use JMReferral\Referral\ReferralNumberLock;
use JMReferral\Referral\ReferralRepository;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;

/**
 * Phase 5F.1: registers clients who already receive care, without a referral history.
 *
 * For each resident, in one transaction:
 * - one care record placed directly at Care Started, with the real admission and care start dates;
 * - their current Supported Living placement (bedroom + move-in date), when applicable;
 * - their funding arrangement and support package, when given;
 * - one stage-history row (change type existing_resident) and one timeline entry that say
 *   exactly what happened.
 *
 * It never writes interest, assessment, package cost or Local Authority decision records,
 * and sends no emails. A bulk import is all-or-nothing: one failure saves nobody.
 */
class ExistingResidentRegistrationService
{
    public const ACTIVITY_ACTION = 'existing_resident_registered';

    public function __construct(
        private ExistingResidentValidator $validator,
        private ReferralRepository $referral_repository,
        private ReferralNumberGenerator $number_generator,
        private ReferralNumberLock $number_lock,
        private ReferralPipelineService $pipeline_service,
        private ReferralStageHistoryRepository $history_repository,
        private ReferralActivityRepository $activity_repository,
        private OccupancyRepository $occupancy_repository,
        private BedroomRepository $bedroom_repository,
        private HomeRepository $home_repository,
        private FundingArrangementRepository $funding_repository,
        private ServiceTypeService $service_type_service,
        private UserProvider $user_provider,
        private TransactionEngineGuard $engine_guard
    ) {
    }

    /**
     * Checks one resident without saving anything.
     *
     * @param array<string, mixed> $input
     * @return array<string, string> Errors keyed by field (empty when valid).
     */
    public function check_one(array $input): array
    {
        return $this->validator->validate($input)['errors'];
    }

    /**
     * @param array<string, mixed> $input One resident's raw values.
     * @return array{ok: true, created: array<int, array{row: int, id: int, referral_number: string, name: string}>}
     *     |array{ok: false, error: string, message: string, row_errors: array<int, array<string, string>>}
     */
    public function register_one(array $input, int $actor_id): array
    {
        return $this->register_many([1 => $input], $actor_id, null);
    }

    /**
     * @param array<int, array<string, mixed>> $rows Raw values keyed by spreadsheet row number.
     * @return array{ok: true, created: array<int, array{row: int, id: int, referral_number: string, name: string}>}
     *     |array{ok: false, error: string, message: string, row_errors: array<int, array<string, string>>}
     */
    public function register_many(array $rows, int $actor_id, ?string $batch): array
    {
        if ($actor_id <= 0 || ! user_can($actor_id, Capabilities::REGISTER_EXISTING_RESIDENTS)) {
            return $this->fail('capability_denied', __('You do not have permission to register existing residents.', 'jm-referral-system'));
        }

        if ([] === $rows) {
            return $this->fail('empty', __('There are no residents to register.', 'jm-referral-system'));
        }

        if (count($rows) > ExistingResident::MAX_IMPORT_ROWS) {
            return $this->fail(
                'too_many',
                sprintf(
                    /* translators: %d: maximum rows */
                    __('A single import can register up to %d residents. Split the spreadsheet and import it in parts.', 'jm-referral-system'),
                    ExistingResident::MAX_IMPORT_ROWS
                )
            );
        }

        // Always re-check on save: a bedroom may have been filled since the preview.
        $checked = $this->validator->validate_batch($rows);
        if ([] !== $checked['errors']) {
            return $this->fail(
                'validation',
                __('Some details need correcting. Nothing has been saved.', 'jm-referral-system'),
                $checked['errors']
            );
        }

        if (! $this->engine_guard->tables_are_transactional($this->written_tables())) {
            return $this->fail(
                'transaction_unavailable',
                __('The database cannot guarantee an all-or-nothing save, so nothing was saved. Please contact support.', 'jm-referral-system')
            );
        }

        $stage = $this->pipeline_service->resolve_canonical_stage(PipelineStage::CARE_COMMENCED);
        $stage_id = is_array($stage) ? absint($stage['id'] ?? 0) : 0;
        if ($stage_id <= 0) {
            return $this->fail('stage_missing', __('The Care Started stage is missing from the workflow settings. Nothing was saved.', 'jm-referral-system'));
        }

        global $wpdb;

        $transaction_open = false;
        $lock_held        = false;
        $created          = [];
        $failure          = $this->fail('persistence', __('The residents could not be saved. Nothing has been saved.', 'jm-referral-system'));

        try {
            $wpdb->query('START TRANSACTION');
            $transaction_open = true;

            if (! $this->number_lock->acquire()) {
                $failure = $this->fail('lock_timeout', __('The system is busy creating another record. Please try again in a moment. Nothing has been saved.', 'jm-referral-system'));
            } else {
                $lock_held = true;
                $ok        = true;

                foreach ($checked['values'] as $row_number => $values) {
                    $result = $this->write_one((int) $row_number, $values, $actor_id, $stage_id, $batch);
                    if (isset($result['row_errors'])) {
                        $failure = $this->fail(
                            'validation',
                            __('Some details need correcting. Nothing has been saved.', 'jm-referral-system'),
                            [(int) $row_number => $result['row_errors']]
                        );
                        $ok = false;
                        break;
                    }
                    if (false === $result) {
                        $ok = false;
                        break;
                    }
                    $created[] = $result;
                }

                if ($ok) {
                    $committed = $wpdb->query('COMMIT');
                    if (false !== $committed) {
                        $transaction_open = false;

                        return ['ok' => true, 'created' => $created];
                    }
                }
            }
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- technical failure only.
                error_log('JMRS: existing resident registration failed: ' . $e->getMessage());
            }
        } finally {
            if ($transaction_open) {
                $wpdb->query('ROLLBACK');
            }
            if ($lock_held) {
                $this->number_lock->release();
            }
        }

        return $failure;
    }

    /**
     * Tables written by a registration. All must support transactions.
     *
     * @return array<int, string>
     */
    public function written_tables(): array
    {
        return [
            Tables::referrals_table(),
            Tables::referral_activity_table(),
            Tables::referral_stage_history_table(),
            Tables::occupancies_table(),
            Tables::bedrooms_table(),
            Tables::funding_arrangements_table(),
        ];
    }

    /**
     * Writes one resident inside the open transaction.
     *
     * @param array<string, string> $v Validated values.
     * @return array{row: int, id: int, referral_number: string, name: string}|array{row_errors: array<string, string>}|false
     */
    private function write_one(int $row_number, array $v, int $actor_id, int $stage_id, ?string $batch): array|false
    {
        $now        = current_time('mysql');
        $setting    = $v['care_setting'];
        $bedroom    = null;
        $home       = null;
        $bedroom_id = absint($v['bedroom_id'] ?? 0);

        if (CareSetting::SUPPORTED_LIVING === $setting) {
            // Lock the bedroom and re-check it is still free before placing anyone in it.
            $bedroom = $this->occupancy_repository->lock_bedroom_row($bedroom_id);
            $home    = is_array($bedroom) ? $this->home_repository->find(absint($bedroom['home_id'] ?? 0)) : null;
            if (null === $bedroom || null === $home
                || 'active' !== (string) ($bedroom['status'] ?? '')
                || 'active' !== (string) ($home['status'] ?? '')
            ) {
                return ['row_errors' => ['bedroom_id' => __('That bedroom is no longer available.', 'jm-referral-system')]];
            }
            if (null !== $this->occupancy_repository->current_for_bedroom($bedroom_id)) {
                return ['row_errors' => ['bedroom_id' => __('That bedroom was given to another resident while you were checking. Choose another bedroom.', 'jm-referral-system')]];
            }
        }

        $first = $v['first_name'];
        $last  = $v['last_name'];
        $name  = trim($first . ' ' . $last);

        $service_type_id  = absint($v['service_type_id'] ?? 0);
        $service          = $service_type_id > 0 ? $this->service_type_service->find($service_type_id) : null;
        $assigned_to      = absint($v['assigned_to'] ?? 0);
        $referral_number  = $this->number_generator->generate();
        $care_commenced   = $v['care_start_date'] . ' 00:00:00';

        $id = $this->referral_repository->insert(
            [
                'referral_number'          => $referral_number,
                'client_name'              => $name,
                'client_email'             => '' !== $v['email'] ? $v['email'] : null,
                'client_phone'             => '' !== $v['phone'] ? $v['phone'] : null,
                'service_required'         => is_array($service) ? (string) ($service['name'] ?? '') : null,
                'service_type_id'          => $service_type_id > 0 ? $service_type_id : null,
                'workflow_stage_id'        => $stage_id,
                'referrer_name'            => null,
                'referrer_email'           => null,
                'priority'                 => 'medium',
                'notes'                    => '' !== $v['notes'] ? $v['notes'] : null,
                'status'                   => 'in_progress',
                'assigned_to'              => $assigned_to > 0 ? $assigned_to : null,
                'referral_source'          => null,
                'care_start_date'          => $v['care_start_date'],
                'preferred_contact_method' => null,
                'care_requirements'        => null,
                'client_first_name'        => $first,
                'client_last_name'         => $last,
                'client_date_of_birth'     => $v['date_of_birth'],
                'address_line_1'           => '' !== $v['address_line_1'] ? $v['address_line_1'] : null,
                'address_line_2'           => '' !== $v['address_line_2'] ? $v['address_line_2'] : null,
                'city'                     => '' !== $v['city'] ? $v['city'] : null,
                'postcode'                 => '' !== $v['postcode'] ? $v['postcode'] : null,
                'submission_channel'       => SubmissionChannels::EXISTING_RESIDENT,
                'workflow_stage_entered_at'=> $care_commenced,
                'next_action_due_at'       => null,
                'created_at'               => $now,
                'updated_at'               => $now,
            ]
        );
        if (false === $id || $id <= 0) {
            return false;
        }

        $applied = $this->referral_repository->apply_existing_resident_registration(
            $id,
            [
                'record_origin'      => ExistingResident::ORIGIN,
                'admission_date'     => $v['admission_date'],
                'care_commenced_at'  => $care_commenced,
                'care_setting'       => $setting,
                'workflow_stage_id'  => $stage_id,
                'registered_at'      => $now,
                'registered_by'      => $actor_id,
                'registration_batch' => $batch,
            ]
        );
        if (! $applied) {
            return false;
        }

        $reason = sprintf(
            /* translators: %s: care start date */
            __('Registered as an existing resident. Care started %s.', 'jm-referral-system'),
            DateInput::display($v['care_start_date'])
        );
        $history_id = $this->history_repository->insert(
            [
                'referral_id'     => $id,
                'from_stage_id'   => null,
                'from_stage_slug' => null,
                'to_stage_id'     => $stage_id,
                'to_stage_slug'   => PipelineStage::CARE_COMMENCED,
                'changed_by'      => $actor_id,
                'change_type'     => PipelineStage::CHANGE_EXISTING_RESIDENT,
                'reason'          => $reason,
                'created_at'      => $now,
            ]
        );
        if (false === $history_id) {
            return false;
        }

        $placement_text = '';
        if (CareSetting::SUPPORTED_LIVING === $setting && is_array($bedroom) && is_array($home)) {
            $occupancy_id = $this->occupancy_repository->insert(
                [
                    'referral_id'   => $id,
                    'home_id'       => absint($home['id'] ?? 0),
                    'bedroom_id'    => $bedroom_id,
                    'move_in_date'  => $v['move_in_date'],
                    'move_out_date' => null,
                    'status'        => 'active',
                    'notes'         => null,
                    'end_reason'    => null,
                    'created_by'    => $actor_id,
                    'ended_by'      => null,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                    'ended_at'      => null,
                ]
            );
            if (false === $occupancy_id) {
                return false;
            }
            $placement_text = sprintf(
                /* translators: 1: property, 2: room, 3: move-in date */
                __('Living at %1$s, %2$s since %3$s.', 'jm-referral-system'),
                (string) ($home['name'] ?? ''),
                (string) ($bedroom['room_label'] ?? ''),
                DateInput::display($v['move_in_date'])
            );
        } elseif (CareSetting::OWN_HOME === $setting) {
            $placement_text = __('Receives care in their own home.', 'jm-referral-system');
        }

        $funding_text = '';
        if ('' !== $v['funding_type']) {
            $funding_id = $this->funding_repository->insert(
                [
                    'referral_id'          => $id,
                    'funding_type'         => $v['funding_type'],
                    'local_authority_id'   => absint($v['local_authority_id']) > 0 ? absint($v['local_authority_id']) : null,
                    'funder_name'          => '' !== $v['funder_name'] ? $v['funder_name'] : null,
                    'funding_reference'    => '' !== $v['funding_reference'] ? $v['funding_reference'] : null,
                    'start_date'           => '' !== $v['funding_start_date'] ? $v['funding_start_date'] : null,
                    'weekly_amount'        => '' !== $v['weekly_amount'] ? $v['weekly_amount'] : null,
                    'weekly_support_hours' => '' !== $v['weekly_support_hours'] ? $v['weekly_support_hours'] : null,
                    'package_summary'      => '' !== $v['package_summary'] ? $v['package_summary'] : null,
                    'source'               => ExistingResident::FUNDING_SOURCE,
                    'created_by'           => $actor_id,
                ]
            );
            if (false === $funding_id) {
                return false;
            }
            $funding_text = sprintf(
                /* translators: %s: funding type label */
                __('Funding recorded: %s.', 'jm-referral-system'),
                ExistingResident::funding_type_label($v['funding_type'])
            );
        }

        $parts = [
            sprintf(
                /* translators: 1: admission date, 2: care start date */
                __('Registered as an existing resident (admitted %1$s; care started %2$s).', 'jm-referral-system'),
                DateInput::display($v['admission_date']),
                DateInput::display($v['care_start_date'])
            ),
        ];
        if ('' !== $placement_text) {
            $parts[] = $placement_text;
        }
        if ('' !== $funding_text) {
            $parts[] = $funding_text;
        }
        if ($assigned_to > 0) {
            $owner = $this->user_provider->get_display_name($assigned_to);
            if ('' !== $owner) {
                $parts[] = sprintf(
                    /* translators: %s: staff name */
                    __('Owner: %s.', 'jm-referral-system'),
                    $owner
                );
            }
        }
        $parts[] = null !== $batch && '' !== $batch
            ? __('Added by bulk import. No referral, assessment, costing or Local Authority decision history was created.', 'jm-referral-system')
            : __('No referral, assessment, costing or Local Authority decision history was created.', 'jm-referral-system');

        $activity_id = $this->activity_repository->create(
            [
                'referral_id' => $id,
                'user_id'     => $actor_id,
                'action'      => self::ACTIVITY_ACTION,
                'description' => implode(' ', $parts),
            ]
        );
        if (false === $activity_id) {
            return false;
        }

        return [
            'row'             => $row_number,
            'id'              => $id,
            'referral_number' => $referral_number,
            'name'            => $name,
        ];
    }

    /**
     * @param array<int, array<string, string>> $row_errors
     * @return array{ok: false, error: string, message: string, row_errors: array<int, array<string, string>>}
     */
    private function fail(string $error, string $message, array $row_errors = []): array
    {
        return [
            'ok'         => false,
            'error'      => $error,
            'message'    => $message,
            'row_errors' => $row_errors,
        ];
    }
}
