<?php

namespace JMReferral\Homes;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Referral\CareSetting;
use JMReferral\Referral\ReferralActivityService;
use JMReferral\Referral\ReferralRepository;

/**
 * Authoritative Supported Living occupancy mutations (Phase 2C).
 *
 * Concurrency strategy:
 * - START TRANSACTION
 * - SELECT referral FOR UPDATE (serialises placements for that care record)
 * - SELECT destination bedroom FOR UPDATE (serialises placements into that room)
 * - For transfer/end: SELECT current occupancy FOR UPDATE
 * - Re-check active occupancy constraints inside the transaction
 * - Mutate, then COMMIT (ROLLBACK on any failure)
 */
class OccupancyService
{
    public function __construct(
        private OccupancyRepository $repository,
        private HomeRepository $home_repository,
        private BedroomRepository $bedroom_repository,
        private ReferralRepository $referral_repository,
        private AccessPolicy $access_policy,
        private ReferralActivityService $activity_service
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function current_for_referral(int $referral_id): ?array
    {
        return $this->repository->current_for_referral($referral_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function current_for_bedroom(int $bedroom_id): ?array
    {
        return $this->repository->current_for_bedroom($bedroom_id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history_for_referral(int $referral_id): array
    {
        return $this->repository->history_for_referral($referral_id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function current_for_home(int $home_id): array
    {
        return $this->repository->current_for_home($home_id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function active_by_bedroom_for_home(int $home_id): array
    {
        return $this->repository->active_by_bedroom_for_home($home_id);
    }

    /**
     * @param array<int, int> $home_ids
     * @return array<int, int>
     */
    public function count_active_by_home_ids(array $home_ids): array
    {
        return $this->repository->count_active_by_home_ids($home_ids);
    }

    public function count_active_for_home(int $home_id): int
    {
        return $this->repository->count_active_for_home($home_id);
    }

    /**
     * Shared occupancy metrics used by Homes list, vacancy board, and Home Dashboard.
     *
     * @return array{capacity: int, occupied: int, vacant: int, occupancy_pct: float}
     */
    public static function compute_metrics(int $capacity, int $occupied): array
    {
        $capacity = max(0, $capacity);
        $occupied = max(0, $occupied);
        $vacant   = max(0, $capacity - $occupied);

        return [
            'capacity'      => $capacity,
            'occupied'      => $occupied,
            'vacant'        => $vacant,
            'occupancy_pct' => $capacity > 0 ? round(($occupied / $capacity) * 100, 1) : 0.0,
        ];
    }

    /**
     * Metrics for a single home (capacity = active bedrooms).
     *
     * @return array{capacity: int, occupied: int, vacant: int, occupancy_pct: float}
     */
    public function metrics_for_home(int $home_id, int $capacity): array
    {
        return self::compute_metrics($capacity, $this->count_active_for_home($home_id));
    }

    public function bedroom_has_any_occupancy(int $bedroom_id): bool
    {
        return $this->repository->bedroom_has_any_occupancy($bedroom_id);
    }

    /**
     * @return array{capacity: int, occupied: int, vacant: int, occupancy_pct: float}
     */
    public function estate_summary(): array
    {
        $summary = $this->repository->estate_summary();
        $capacity = (int) $summary['capacity'];
        $occupied = (int) $summary['occupied'];
        $vacant   = (int) $summary['vacant'];

        return [
            'capacity'       => $capacity,
            'occupied'       => $occupied,
            'vacant'         => $vacant,
            'occupancy_pct'  => self::compute_metrics($capacity, $occupied)['occupancy_pct'],
        ];
    }

    /**
     * Active vacant bedrooms for a home (for placement selectors).
     *
     * @return array<int, array<string, mixed>>
     */
    public function vacant_active_bedrooms(int $home_id, ?int $exclude_bedroom_id = null): array
    {
        $bedrooms = $this->bedroom_repository->list_by_home($home_id, 'active');
        if ([] === $bedrooms) {
            return [];
        }

        $bedroom_ids = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $bedrooms);
        $occupied    = array_fill_keys($this->repository->occupied_bedroom_ids($bedroom_ids), true);
        $vacant      = [];

        foreach ($bedrooms as $bedroom) {
            $id = absint($bedroom['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (null !== $exclude_bedroom_id && $id === $exclude_bedroom_id) {
                continue;
            }
            if (isset($occupied[$id])) {
                continue;
            }
            $vacant[] = $bedroom;
        }

        return $vacant;
    }

    /**
     * Place a care record into a vacant supported living bedroom.
     *
     * @param array<string, mixed> $input
     * @return array{id: int}|array{errors: array<string, string>}|false
     */
    public function place_resident(array $input, ?int $actor_user_id = null): array|false
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $referral_id   = absint($input['referral_id'] ?? 0);
        $home_id       = absint($input['home_id'] ?? 0);
        $bedroom_id    = absint($input['bedroom_id'] ?? 0);
        $move_in_date  = $this->normalize_date((string) ($input['move_in_date'] ?? ''));
        $notes         = sanitize_textarea_field((string) ($input['notes'] ?? ''));

        $errors = $this->validate_placement_inputs($referral_id, $home_id, $bedroom_id, $move_in_date, $actor_user_id);
        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            if (! $this->repository->lock_referral_row($referral_id)) {
                $wpdb->query('ROLLBACK');

                return [
                    'errors' => [
                        'referral_id' => __('The selected client care record was not found.', 'jm-referral-system'),
                    ],
                ];
            }

            $locked_bedroom = $this->repository->lock_bedroom_row($bedroom_id);
            if (null === $locked_bedroom) {
                $wpdb->query('ROLLBACK');

                return [
                    'errors' => [
                        'bedroom_id' => __('The selected bedroom was not found.', 'jm-referral-system'),
                    ],
                ];
            }

            $errors = $this->revalidate_placement_locked(
                $referral_id,
                $home_id,
                $locked_bedroom,
                $move_in_date,
                $actor_user_id
            );
            if (! empty($errors)) {
                $wpdb->query('ROLLBACK');

                return ['errors' => $errors];
            }

            $now = current_time('mysql');
            $id  = $this->repository->insert(
                [
                    'referral_id'   => $referral_id,
                    'home_id'       => $home_id,
                    'bedroom_id'    => $bedroom_id,
                    'move_in_date'  => $move_in_date,
                    'move_out_date' => null,
                    'status'        => 'active',
                    'notes'         => '' !== $notes ? $notes : null,
                    'end_reason'    => null,
                    'created_by'    => $actor_user_id,
                    'ended_by'      => null,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                    'ended_at'      => null,
                ]
            );

            if (false === $id) {
                $wpdb->query('ROLLBACK');

                return false;
            }

            // Explicit Supported Living placement classifies NULL care_setting.
            $locked_referral = $this->referral_repository->find($referral_id);
            $current_setting = is_array($locked_referral) ? ($locked_referral['care_setting'] ?? null) : null;
            if (CareSetting::is_unspecified($current_setting)) {
                if (! $this->referral_repository->update_care_setting($referral_id, CareSetting::SUPPORTED_LIVING)) {
                    $wpdb->query('ROLLBACK');

                    return false;
                }
            }

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- technical failure only.
                error_log('JMRS: place_resident failed: ' . $e->getMessage());
            }

            return false;
        }

        $home    = $this->home_repository->find($home_id);
        $bedroom = $this->bedroom_repository->find($bedroom_id);
        $this->activity_service->log_action(
            $referral_id,
            'placement_started',
            sprintf(
                /* translators: 1: home name, 2: room label */
                __('Placed at %1$s — %2$s.', 'jm-referral-system'),
                (string) ($home['name'] ?? __('Home', 'jm-referral-system')),
                (string) ($bedroom['room_label'] ?? __('Bedroom', 'jm-referral-system'))
            )
        );

        return ['id' => $id];
    }

    /**
     * Atomically end current placement and create a new one.
     *
     * @param array<string, mixed> $input
     * @return array{id: int}|array{errors: array<string, string>}|false
     */
    public function transfer_resident(int $occupancy_id, array $input, ?int $actor_user_id = null): array|false
    {
        $actor_user_id   = $actor_user_id ?? get_current_user_id();
        $new_home_id     = absint($input['new_home_id'] ?? $input['home_id'] ?? 0);
        $new_bedroom_id  = absint($input['new_bedroom_id'] ?? $input['bedroom_id'] ?? 0);
        $transfer_date   = $this->normalize_date((string) ($input['transfer_date'] ?? $input['move_in_date'] ?? ''));
        $notes           = sanitize_textarea_field((string) ($input['notes'] ?? ''));
        $end_reason      = sanitize_text_field((string) ($input['end_reason'] ?? __('Transferred', 'jm-referral-system')));

        if ($occupancy_id <= 0) {
            return [
                'errors' => [
                    'general' => __('Current placement was not found.', 'jm-referral-system'),
                ],
            ];
        }

        if ('' === $transfer_date) {
            return [
                'errors' => [
                    'transfer_date' => __('Transfer date is required.', 'jm-referral-system'),
                ],
            ];
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        $old_home_name    = '';
        $old_room_label   = '';
        $new_home_name    = '';
        $new_room_label   = '';
        $referral_id      = 0;
        $new_occupancy_id = 0;

        try {
            $current = $this->repository->lock_occupancy_row($occupancy_id);
            if (null === $current || 'active' !== ($current['status'] ?? '')) {
                $wpdb->query('ROLLBACK');

                return [
                    'errors' => [
                        'general' => __('There is no active placement to transfer.', 'jm-referral-system'),
                    ],
                ];
            }

            $referral_id = absint($current['referral_id'] ?? 0);
            if (! $this->repository->lock_referral_row($referral_id)) {
                $wpdb->query('ROLLBACK');

                return [
                    'errors' => [
                        'referral_id' => __('The client care record was not found.', 'jm-referral-system'),
                    ],
                ];
            }

            $referral = $this->referral_repository->find($referral_id);
            if (null === $referral || ! $this->access_policy->can_mutate_referral($referral, $actor_user_id)) {
                $wpdb->query('ROLLBACK');

                return [
                    'errors' => [
                        'referral_id' => __('You cannot change placement for this client.', 'jm-referral-system'),
                    ],
                ];
            }

            $move_in = (string) ($current['move_in_date'] ?? '');
            if ($transfer_date < $move_in) {
                $wpdb->query('ROLLBACK');

                return [
                    'errors' => [
                        'transfer_date' => __('Transfer date cannot be before the current move-in date.', 'jm-referral-system'),
                    ],
                ];
            }

            if ($new_bedroom_id === absint($current['bedroom_id'] ?? 0)) {
                $wpdb->query('ROLLBACK');

                return [
                    'errors' => [
                        'new_bedroom_id' => __('Select a different bedroom for the transfer.', 'jm-referral-system'),
                    ],
                ];
            }

            $locked_bedroom = $this->repository->lock_bedroom_row($new_bedroom_id);
            if (null === $locked_bedroom) {
                $wpdb->query('ROLLBACK');

                return [
                    'errors' => [
                        'new_bedroom_id' => __('The destination bedroom was not found.', 'jm-referral-system'),
                    ],
                ];
            }

            $errors = $this->revalidate_destination_locked($new_home_id, $locked_bedroom, $new_bedroom_id);
            if (! empty($errors)) {
                $wpdb->query('ROLLBACK');

                return ['errors' => $errors];
            }

            $old_home    = $this->home_repository->find(absint($current['home_id'] ?? 0));
            $old_bedroom = $this->bedroom_repository->find(absint($current['bedroom_id'] ?? 0));
            $new_home    = $this->home_repository->find($new_home_id);
            $old_home_name  = (string) ($old_home['name'] ?? '');
            $old_room_label = (string) ($old_bedroom['room_label'] ?? '');
            $new_home_name  = (string) ($new_home['name'] ?? '');
            $new_room_label = (string) ($locked_bedroom['room_label'] ?? '');

            $now     = current_time('mysql');
            $ended   = $this->repository->end(
                $occupancy_id,
                [
                    'move_out_date' => $transfer_date,
                    'end_reason'    => '' !== $end_reason ? $end_reason : null,
                    'notes'         => '' !== $notes ? $notes : ($current['notes'] ?? null),
                    'ended_by'      => $actor_user_id,
                    'ended_at'      => $now,
                    'updated_at'    => $now,
                ]
            );

            if (! $ended) {
                $wpdb->query('ROLLBACK');

                return false;
            }

            $new_occupancy_id = $this->repository->insert(
                [
                    'referral_id'   => $referral_id,
                    'home_id'       => $new_home_id,
                    'bedroom_id'    => $new_bedroom_id,
                    'move_in_date'  => $transfer_date,
                    'move_out_date' => null,
                    'status'        => 'active',
                    'notes'         => '' !== $notes ? $notes : null,
                    'end_reason'    => null,
                    'created_by'    => $actor_user_id,
                    'ended_by'      => null,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                    'ended_at'      => null,
                ]
            );

            if (false === $new_occupancy_id) {
                $wpdb->query('ROLLBACK');

                return false;
            }

            // Transfers remain Supported Living; repair legacy NULL if present.
            if (! CareSetting::is_supported_living($referral['care_setting'] ?? null)) {
                if (! $this->referral_repository->update_care_setting($referral_id, CareSetting::SUPPORTED_LIVING)) {
                    $wpdb->query('ROLLBACK');

                    return false;
                }
            }

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- technical failure only.
                error_log('JMRS: transfer_resident failed: ' . $e->getMessage());
            }

            return false;
        }

        $this->activity_service->log_action(
            $referral_id,
            'placement_transferred',
            sprintf(
                /* translators: 1: old home, 2: old room, 3: new home, 4: new room */
                __('Transferred from %1$s — %2$s to %3$s — %4$s.', 'jm-referral-system'),
                $old_home_name !== '' ? $old_home_name : __('Home', 'jm-referral-system'),
                $old_room_label !== '' ? $old_room_label : __('Bedroom', 'jm-referral-system'),
                $new_home_name !== '' ? $new_home_name : __('Home', 'jm-referral-system'),
                $new_room_label !== '' ? $new_room_label : __('Bedroom', 'jm-referral-system')
            )
        );

        return ['id' => (int) $new_occupancy_id];
    }

    /**
     * End an active occupancy (move out). Historical row is retained.
     *
     * @param array<string, mixed> $input
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    public function end_occupancy(int $occupancy_id, array $input, ?int $actor_user_id = null): array|false
    {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        $move_out_date = $this->normalize_date((string) ($input['move_out_date'] ?? ''));
        $end_reason    = sanitize_text_field((string) ($input['end_reason'] ?? ''));
        $notes         = sanitize_textarea_field((string) ($input['notes'] ?? ''));

        if ($occupancy_id <= 0) {
            return [
                'errors' => [
                    'general' => __('Placement was not found.', 'jm-referral-system'),
                ],
            ];
        }

        if ('' === $move_out_date) {
            return [
                'errors' => [
                    'move_out_date' => __('Move-out date is required.', 'jm-referral-system'),
                ],
            ];
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        $referral_id = 0;
        $home_name   = '';
        $room_label  = '';

        try {
            $current = $this->repository->lock_occupancy_row($occupancy_id);
            if (null === $current || 'active' !== ($current['status'] ?? '')) {
                $wpdb->query('ROLLBACK');

                return [
                    'errors' => [
                        'general' => __('There is no active placement to end.', 'jm-referral-system'),
                    ],
                ];
            }

            $referral_id = absint($current['referral_id'] ?? 0);
            if (! $this->repository->lock_referral_row($referral_id)) {
                $wpdb->query('ROLLBACK');

                return [
                    'errors' => [
                        'referral_id' => __('The client care record was not found.', 'jm-referral-system'),
                    ],
                ];
            }

            $referral = $this->referral_repository->find($referral_id);
            if (null === $referral || ! $this->access_policy->can_mutate_referral($referral, $actor_user_id)) {
                $wpdb->query('ROLLBACK');

                return [
                    'errors' => [
                        'referral_id' => __('You cannot change placement for this client.', 'jm-referral-system'),
                    ],
                ];
            }

            $move_in = (string) ($current['move_in_date'] ?? '');
            if ($move_out_date < $move_in) {
                $wpdb->query('ROLLBACK');

                return [
                    'errors' => [
                        'move_out_date' => __('Move-out date cannot be before the move-in date.', 'jm-referral-system'),
                    ],
                ];
            }

            $home    = $this->home_repository->find(absint($current['home_id'] ?? 0));
            $bedroom = $this->bedroom_repository->find(absint($current['bedroom_id'] ?? 0));
            $home_name  = (string) ($home['name'] ?? '');
            $room_label = (string) ($bedroom['room_label'] ?? '');

            $now   = current_time('mysql');
            $ended = $this->repository->end(
                $occupancy_id,
                [
                    'move_out_date' => $move_out_date,
                    'end_reason'    => '' !== $end_reason ? $end_reason : null,
                    'notes'         => '' !== $notes ? $notes : ($current['notes'] ?? null),
                    'ended_by'      => $actor_user_id,
                    'ended_at'      => $now,
                    'updated_at'    => $now,
                ]
            );

            if (! $ended) {
                $wpdb->query('ROLLBACK');

                return false;
            }

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- technical failure only.
                error_log('JMRS: end_occupancy failed: ' . $e->getMessage());
            }

            return false;
        }

        $this->activity_service->log_action(
            $referral_id,
            'placement_ended',
            sprintf(
                /* translators: 1: home name, 2: room label */
                __('Supported Living placement ended (%1$s — %2$s).', 'jm-referral-system'),
                $home_name !== '' ? $home_name : __('Home', 'jm-referral-system'),
                $room_label !== '' ? $room_label : __('Bedroom', 'jm-referral-system')
            )
        );

        return ['ok' => true];
    }

    /**
     * Enrich occupancy rows with home/bedroom/client labels (batch-friendly).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function enrich_rows(array $rows): array
    {
        if ([] === $rows) {
            return [];
        }

        $home_ids     = [];
        $bedroom_ids  = [];
        $referral_ids = [];
        foreach ($rows as $row) {
            $home_ids[]     = absint($row['home_id'] ?? 0);
            $bedroom_ids[]  = absint($row['bedroom_id'] ?? 0);
            $referral_ids[] = absint($row['referral_id'] ?? 0);
        }

        $homes     = $this->load_homes_map($home_ids);
        $bedrooms  = $this->load_bedrooms_map($bedroom_ids);
        $referrals = $this->load_referrals_map($referral_ids);

        foreach ($rows as $index => $row) {
            $home_id     = absint($row['home_id'] ?? 0);
            $bedroom_id  = absint($row['bedroom_id'] ?? 0);
            $referral_id = absint($row['referral_id'] ?? 0);
            $home        = $homes[$home_id] ?? null;
            $bedroom     = $bedrooms[$bedroom_id] ?? null;
            $referral    = $referrals[$referral_id] ?? null;

            $rows[$index]['home_name']         = (string) ($home['name'] ?? '');
            $rows[$index]['room_label']        = (string) ($bedroom['room_label'] ?? '');
            $rows[$index]['client_name']       = $this->client_display_name($referral);
            $rows[$index]['referral_number']   = (string) ($referral['referral_number'] ?? '');
            $rows[$index]['care_setting']      = is_array($referral) ? ($referral['care_setting'] ?? null) : null;
            $rows[$index]['assigned_to']       = is_array($referral) ? absint($referral['assigned_to'] ?? 0) : 0;
            $rows[$index]['referral']          = $referral;
            $rows[$index]['status_label']      = 'active' === ($row['status'] ?? '')
                ? __('Occupied', 'jm-referral-system')
                : __('Ended', 'jm-referral-system');
        }

        return $rows;
    }

    public function client_display_name(?array $referral): string
    {
        if (null === $referral) {
            return '';
        }

        $name = trim(
            (string) ($referral['client_first_name'] ?? '') . ' ' . (string) ($referral['client_last_name'] ?? '')
        );
        if ('' === $name) {
            $name = (string) ($referral['client_name'] ?? '');
        }

        return $name;
    }

    private function normalize_date(string $value): string
    {
        $value = trim($value);
        if ('' === $value) {
            return '';
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (! $dt || $dt->format('Y-m-d') !== $value) {
            return '';
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private function validate_placement_inputs(
        int $referral_id,
        int $home_id,
        int $bedroom_id,
        string $move_in_date,
        int $actor_user_id
    ): array {
        $errors = [];

        if ($referral_id <= 0) {
            $errors['referral_id'] = __('Please select a client.', 'jm-referral-system');
        }

        if ($home_id <= 0) {
            $errors['home_id'] = __('Please select a home.', 'jm-referral-system');
        }

        if ($bedroom_id <= 0) {
            $errors['bedroom_id'] = __('Please select a bedroom.', 'jm-referral-system');
        }

        if ('' === $move_in_date) {
            $errors['move_in_date'] = __('Move-in date is required.', 'jm-referral-system');
        }

        if (! empty($errors)) {
            return $errors;
        }

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral) {
            $errors['referral_id'] = __('The selected client care record was not found.', 'jm-referral-system');
        } elseif ($this->access_policy->is_referral_archived($referral)) {
            $errors['referral_id'] = __('Archived clients cannot be placed in Supported Living.', 'jm-referral-system');
        } elseif (! $this->access_policy->can_mutate_referral($referral, $actor_user_id)) {
            $errors['referral_id'] = __('You cannot change placement for this client.', 'jm-referral-system');
        } elseif (CareSetting::is_own_home($referral['care_setting'] ?? null)) {
            $errors['referral_id'] = __(
                'This client is classified as Client\'s Own Home. Change the care setting to Supported Living before placing in a JM bedroom.',
                'jm-referral-system'
            );
        }

        $home = $this->home_repository->find($home_id);
        if (null === $home) {
            $errors['home_id'] = __('The selected home was not found.', 'jm-referral-system');
        } elseif ('active' !== ($home['status'] ?? '')) {
            $errors['home_id'] = __('Residents can only be placed in an active home.', 'jm-referral-system');
        }

        $bedroom = $this->bedroom_repository->find($bedroom_id);
        if (null === $bedroom) {
            $errors['bedroom_id'] = __('The selected bedroom was not found.', 'jm-referral-system');
        } elseif ('active' !== ($bedroom['status'] ?? '')) {
            $errors['bedroom_id'] = __('Residents can only be placed in an active bedroom.', 'jm-referral-system');
        } elseif (absint($bedroom['home_id'] ?? 0) !== $home_id) {
            $errors['bedroom_id'] = __('The bedroom does not belong to the selected home.', 'jm-referral-system');
        }

        if (null !== $this->repository->current_for_bedroom($bedroom_id)) {
            $errors['bedroom_id'] = __('This bedroom already has an active resident.', 'jm-referral-system');
        }

        if (null !== $this->repository->current_for_referral($referral_id)) {
            $errors['referral_id'] = __('This client already has an active Supported Living placement.', 'jm-referral-system');
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $locked_bedroom
     * @return array<string, string>
     */
    private function revalidate_placement_locked(
        int $referral_id,
        int $home_id,
        array $locked_bedroom,
        string $move_in_date,
        int $actor_user_id
    ): array {
        unset($move_in_date);

        $errors = [];

        $referral = $this->referral_repository->find($referral_id);
        if (null === $referral || $this->access_policy->is_referral_archived($referral)) {
            $errors['referral_id'] = __('Archived clients cannot be placed in Supported Living.', 'jm-referral-system');
        } elseif (! $this->access_policy->can_mutate_referral($referral, $actor_user_id)) {
            $errors['referral_id'] = __('You cannot change placement for this client.', 'jm-referral-system');
        } elseif (CareSetting::is_own_home($referral['care_setting'] ?? null)) {
            $errors['referral_id'] = __(
                'This client is classified as Client\'s Own Home. Change the care setting to Supported Living before placing in a JM bedroom.',
                'jm-referral-system'
            );
        }

        $home = $this->home_repository->find($home_id);
        if (null === $home || 'active' !== ($home['status'] ?? '')) {
            $errors['home_id'] = __('Residents can only be placed in an active home.', 'jm-referral-system');
        }

        if ('active' !== ($locked_bedroom['status'] ?? '')) {
            $errors['bedroom_id'] = __('Residents can only be placed in an active bedroom.', 'jm-referral-system');
        } elseif (absint($locked_bedroom['home_id'] ?? 0) !== $home_id) {
            $errors['bedroom_id'] = __('The bedroom does not belong to the selected home.', 'jm-referral-system');
        }

        $bedroom_id = absint($locked_bedroom['id'] ?? 0);
        if (null !== $this->repository->current_for_bedroom($bedroom_id)) {
            $errors['bedroom_id'] = __('This bedroom already has an active resident.', 'jm-referral-system');
        }

        if (null !== $this->repository->current_for_referral($referral_id)) {
            $errors['referral_id'] = __('This client already has an active Supported Living placement.', 'jm-referral-system');
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $locked_bedroom
     * @return array<string, string>
     */
    private function revalidate_destination_locked(int $home_id, array $locked_bedroom, int $bedroom_id): array
    {
        $errors = [];

        $home = $this->home_repository->find($home_id);
        if (null === $home || 'active' !== ($home['status'] ?? '')) {
            $errors['new_home_id'] = __('Residents can only be transferred to an active home.', 'jm-referral-system');
        }

        if ('active' !== ($locked_bedroom['status'] ?? '')) {
            $errors['new_bedroom_id'] = __('Residents can only be transferred to an active bedroom.', 'jm-referral-system');
        } elseif (absint($locked_bedroom['home_id'] ?? 0) !== $home_id) {
            $errors['new_bedroom_id'] = __('The bedroom does not belong to the selected home.', 'jm-referral-system');
        }

        if (null !== $this->repository->current_for_bedroom($bedroom_id)) {
            $errors['new_bedroom_id'] = __('The destination bedroom already has an active resident.', 'jm-referral-system');
        }

        return $errors;
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function load_homes_map(array $ids): array
    {
        $map = [];
        foreach (array_unique(array_filter(array_map('absint', $ids))) as $id) {
            $row = $this->home_repository->find($id);
            if (null !== $row) {
                $map[$id] = $row;
            }
        }

        return $map;
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function load_bedrooms_map(array $ids): array
    {
        $map = [];
        foreach (array_unique(array_filter(array_map('absint', $ids))) as $id) {
            $row = $this->bedroom_repository->find($id);
            if (null !== $row) {
                $map[$id] = $row;
            }
        }

        return $map;
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function load_referrals_map(array $ids): array
    {
        $map = [];
        foreach (array_unique(array_filter(array_map('absint', $ids))) as $id) {
            $row = $this->referral_repository->find($id);
            if (null !== $row) {
                $map[$id] = $row;
            }
        }

        return $map;
    }
}
