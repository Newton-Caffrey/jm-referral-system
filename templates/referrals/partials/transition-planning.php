<?php
/**
 * Transition Planning / Care Commencement panel (admin + portal).
 *
 * Phase 4H.1: derived readiness display only — no checklist or readiness %.
 *
 * @package JMReferral
 *
 * @var int    $referral_id
 * @var array  $transition_panel
 * @var string $form_action
 * @var string $context admin|portal
 * @var array  $transition_errors
 * @var bool   $show_commence_form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$transition_panel   = is_array( $transition_panel ?? null ) ? $transition_panel : array();
$referral_id        = isset( $referral_id ) ? absint( $referral_id ) : 0;
$form_action        = isset( $form_action ) ? (string) $form_action : '';
$context            = isset( $context ) ? (string) $context : 'admin';
$transition_errors  = is_array( $transition_errors ?? null ) ? $transition_errors : array();
$show_commence_form = ! empty( $show_commence_form );
$is_portal          = 'portal' === $context;

if ( $referral_id <= 0 || empty( $transition_panel['show_panel'] ) ) {
	return;
}

$field = static function ( string $key ) use ( $transition_panel ): string {
	return (string) ( $transition_panel[ $key ] ?? '' );
};

$is_commenced  = ! empty( $transition_panel['is_care_commenced'] );
$can_commence  = ! empty( $transition_panel['can_commence'] );
$care_setting  = $field( 'care_setting' );
$ops_links     = is_array( $transition_panel['ops_links'] ?? null ) ? $transition_panel['ops_links'] : array();
$soft_warnings = is_array( $transition_panel['soft_warnings'] ?? null ) ? $transition_panel['soft_warnings'] : array();
$hard_blockers = is_array( $transition_panel['hard_blockers'] ?? null ) ? $transition_panel['hard_blockers'] : array();
$btn_class     = $is_portal ? 'jmrs-portal-btn' : 'button button-primary';
$btn_secondary = $is_portal ? 'jmrs-portal-btn jmrs-portal-btn--secondary' : 'button';
?>
<section class="jmrs-transition-planning" aria-labelledby="jmrs-transition-planning-heading">
	<h2 id="jmrs-transition-planning-heading" class="jmrs-transition-planning__title">
		<?php
		echo esc_html(
			$is_commenced
				? __( 'Care Commenced', 'jm-referral-system' )
				: __( 'Transition Planning', 'jm-referral-system' )
		);
		?>
	</h2>

	<?php if ( $is_commenced ) : ?>
		<p class="jmrs-transition-planning__terminal-notice" role="status">
			<?php echo esc_html__( 'Recorded / read-only — care commencement is terminal. No correction or reopen path.', 'jm-referral-system' ); ?>
		</p>

		<dl class="jmrs-transition-planning__facts">
			<div>
				<dt><?php echo esc_html__( 'Pipeline stage', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $field( 'stage_label' ) ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Care commencement date / time', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $field( 'care_commenced_at' ) ); ?></dd>
			</div>
			<?php if ( '' !== $field( 'care_commenced_by_name' ) ) : ?>
				<div>
					<dt><?php echo esc_html__( 'Recorded by', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( $field( 'care_commenced_by_name' ) ); ?></dd>
				</div>
			<?php endif; ?>
			<div>
				<dt><?php echo esc_html__( 'Care setting', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $field( 'care_setting_label' ) ); ?></dd>
			</div>
			<?php if ( 'supported_living' === $care_setting && '' !== $field( 'placement_home_name' ) ) : ?>
				<div>
					<dt><?php echo esc_html__( 'Current placement', 'jm-referral-system' ); ?></dt>
					<dd class="jmrs-transition-planning__wrap">
						<?php
						echo esc_html(
							trim(
								$field( 'placement_home_name' )
								. ( '' !== $field( 'placement_room_label' ) ? ' — ' . $field( 'placement_room_label' ) : '' )
							)
						);
						?>
						<?php if ( '' !== $field( 'placement_move_in_date' ) ) : ?>
							<br />
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: move-in date */
									__( 'Move-in date: %s', 'jm-referral-system' ),
									$field( 'placement_move_in_date' )
								)
							);
							?>
						<?php endif; ?>
					</dd>
				</div>
			<?php elseif ( 'own_home' === $care_setting ) : ?>
				<div>
					<dt><?php echo esc_html__( 'Service location', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( $field( 'own_home_safe_summary' ) !== '' ? $field( 'own_home_safe_summary' ) : $field( 'service_location_label' ) ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>

		<p class="jmrs-transition-planning__note">
			<?php echo esc_html__( 'Acquisition is complete. Care operations continue on this record. Occupancy transfer or end remains available to users with occupancy-management permission.', 'jm-referral-system' ); ?>
		</p>

		<?php if ( ! empty( $ops_links ) ) : ?>
			<p class="jmrs-transition-planning__ops">
				<?php foreach ( array( 'care_plan' => __( 'Care Plan', 'jm-referral-system' ), 'care_team' => __( 'Care Team', 'jm-referral-system' ), 'schedules' => __( 'Schedules', 'jm-referral-system' ), 'visits' => __( 'Visits', 'jm-referral-system' ), 'medications' => __( 'Medications', 'jm-referral-system' ), 'documents' => __( 'Documents', 'jm-referral-system' ) ) as $key => $label ) : ?>
					<?php if ( ! empty( $ops_links[ $key ] ) ) : ?>
						<a class="<?php echo esc_attr( $btn_secondary ); ?>" href="<?php echo esc_url( (string) $ops_links[ $key ] ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endif; ?>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>

	<?php else : ?>

		<dl class="jmrs-transition-planning__facts">
			<div>
				<dt><?php echo esc_html__( 'Pipeline stage', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $field( 'stage_label' ) ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Local Authority approval', 'jm-referral-system' ); ?></dt>
				<dd>
					<?php
					echo ! empty( $transition_panel['la_approved'] )
						? esc_html__( 'Approved', 'jm-referral-system' )
						: esc_html__( 'Required — not approved', 'jm-referral-system' );
					?>
				</dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Package cost', 'jm-referral-system' ); ?></dt>
				<dd>
					<?php
					echo ! empty( $transition_panel['package_sent'] )
						? esc_html__( 'Sent', 'jm-referral-system' )
						: esc_html( $field( 'package_status_label' ) );
					?>
				</dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Funding confirmed', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $field( 'funding_confirmed_label' ) ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Care setting', 'jm-referral-system' ); ?></dt>
				<dd>
					<?php echo esc_html( $field( 'care_setting_label' ) ); ?>
					<?php if ( ! empty( $transition_panel['care_setting_required'] ) && '' !== $field( 'care_setting_url' ) ) : ?>
						<br />
						<a href="<?php echo esc_url( $field( 'care_setting_url' ) ); ?>">
							<?php echo esc_html__( 'Choose Supported Living or Own Home', 'jm-referral-system' ); ?>
						</a>
					<?php endif; ?>
				</dd>
			</div>
			<?php if ( '' !== $field( 'preferred_care_start_date' ) ) : ?>
				<div>
					<dt><?php echo esc_html__( 'Preferred / requested care start', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( $field( 'preferred_care_start_date' ) ); ?></dd>
				</div>
			<?php endif; ?>
			<div>
				<dt><?php echo esc_html__( 'Referral owner', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $field( 'owner_name' ) ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Champion', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $field( 'champion_name' ) ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Transition Lead', 'jm-referral-system' ); ?></dt>
				<dd>
					<?php echo esc_html( $field( 'transition_lead_name' ) ); ?>
					<br />
					<span class="jmrs-transition-planning__hint">
						<?php echo esc_html__( 'Assignment does not grant extra referral visibility or commencement permission.', 'jm-referral-system' ); ?>
					</span>
				</dd>
			</div>
		</dl>

		<?php if ( 'supported_living' === $care_setting ) : ?>
			<div class="jmrs-transition-planning__placement">
				<h3><?php echo esc_html__( 'Supported Living placement', 'jm-referral-system' ); ?></h3>
				<?php if ( ! empty( $transition_panel['placement_ready'] ) ) : ?>
					<p>
						<strong><?php echo esc_html__( 'Active occupancy', 'jm-referral-system' ); ?></strong>
						<span class="jmrs-transition-planning__wrap">
							—
							<?php
							echo esc_html(
								trim(
									$field( 'placement_home_name' )
									. ( '' !== $field( 'placement_room_label' ) ? ' — ' . $field( 'placement_room_label' ) : '' )
								)
							);
							?>
						</span>
						<?php if ( '' !== $field( 'placement_move_in_date' ) ) : ?>
							<br />
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: move-in date */
									__( 'Move-in date: %s', 'jm-referral-system' ),
									$field( 'placement_move_in_date' )
								)
							);
							?>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<p>
						<strong><?php echo esc_html__( 'Placement required', 'jm-referral-system' ); ?></strong>
						— <?php echo esc_html__( 'An active occupancy is required before Confirm Care Commenced.', 'jm-referral-system' ); ?>
					</p>
					<?php if ( ! empty( $transition_panel['show_place_prompt'] ) && '' !== $field( 'place_resident_url' ) ) : ?>
						<p>
							<a class="<?php echo esc_attr( $btn_class ); ?>" href="<?php echo esc_url( $field( 'place_resident_url' ) ); ?>">
								<?php echo esc_html__( 'Place Resident', 'jm-referral-system' ); ?>
							</a>
						</p>
						<p class="jmrs-transition-planning__hint">
							<?php echo esc_html__( 'Place Resident creates an active occupancy and does not advance the acquisition pipeline.', 'jm-referral-system' ); ?>
						</p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		<?php elseif ( 'own_home' === $care_setting ) : ?>
			<div class="jmrs-transition-planning__placement">
				<h3><?php echo esc_html__( 'Own Home', 'jm-referral-system' ); ?></h3>
				<p><?php echo esc_html__( 'No Supported Living occupancy is required. An active SL occupancy would block commencement.', 'jm-referral-system' ); ?></p>
				<?php if ( '' !== $field( 'own_home_address_summary' ) ) : ?>
					<p>
						<strong><?php echo esc_html__( 'Address on referral', 'jm-referral-system' ); ?></strong>
						— <span class="jmrs-transition-planning__wrap"><?php echo esc_html( $field( 'own_home_address_summary' ) ); ?></span>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="jmrs-transition-planning__readiness">
			<h3><?php echo esc_html__( 'Operational readiness (informational)', 'jm-referral-system' ); ?></h3>
			<ul>
				<li>
					<?php echo esc_html__( 'Care plan:', 'jm-referral-system' ); ?>
					<?php echo esc_html( $field( 'care_plan_status_label' ) ); ?>
				</li>
				<li>
					<?php echo esc_html__( 'Care team:', 'jm-referral-system' ); ?>
					<?php
					if ( ! empty( $transition_panel['care_team_ready'] ) ) {
						echo esc_html(
							sprintf(
								/* translators: %d: staff count */
								_n( '%d staff', '%d staff', (int) ( $transition_panel['care_team_count'] ?? 0 ), 'jm-referral-system' ),
								(int) ( $transition_panel['care_team_count'] ?? 0 )
							)
						);
					} else {
						echo esc_html__( 'Not configured', 'jm-referral-system' );
					}
					?>
				</li>
				<li>
					<?php echo esc_html__( 'Schedule:', 'jm-referral-system' ); ?>
					<?php
					echo ! empty( $transition_panel['schedule_ready'] )
						? esc_html__( 'Active schedule exists', 'jm-referral-system' )
						: esc_html__( 'No active schedule', 'jm-referral-system' );
					?>
				</li>
				<li>
					<?php echo esc_html__( 'Care commencement:', 'jm-referral-system' ); ?>
					<?php echo esc_html__( 'Not yet confirmed', 'jm-referral-system' ); ?>
				</li>
			</ul>
		</div>

		<?php if ( ! empty( $hard_blockers ) ) : ?>
			<div class="jmrs-transition-planning__blockers" role="alert">
				<h3><?php echo esc_html__( 'Hard blockers', 'jm-referral-system' ); ?></h3>
				<p class="jmrs-transition-planning__hint">
					<?php echo esc_html__( 'Care commencement cannot proceed until these are resolved.', 'jm-referral-system' ); ?>
				</p>
				<ul>
					<?php foreach ( $hard_blockers as $blocker ) : ?>
						<li><?php echo esc_html( (string) $blocker ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $soft_warnings ) ) : ?>
			<div class="jmrs-transition-planning__warnings">
				<h3><?php echo esc_html__( 'Soft warnings', 'jm-referral-system' ); ?></h3>
				<p class="jmrs-transition-planning__hint">
					<?php echo esc_html__( 'These do not hard-block commencement. Funding acknowledgement is required when funding is not confirmed.', 'jm-referral-system' ); ?>
				</p>
				<ul>
					<?php foreach ( $soft_warnings as $warning ) : ?>
						<li><?php echo esc_html( (string) $warning ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $transition_errors ) ) : ?>
			<div class="jmrs-transition-planning__errors" role="alert">
				<ul>
					<?php foreach ( $transition_errors as $err ) : ?>
						<li><?php echo esc_html( (string) $err ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="jmrs-transition-planning__next">
			<h3><?php echo esc_html__( 'Next action', 'jm-referral-system' ); ?></h3>
			<p><strong><?php echo esc_html( $field( 'next_action_label' ) ); ?></strong></p>
			<?php if ( '' !== $field( 'next_action_hint' ) ) : ?>
				<p class="jmrs-transition-planning__hint"><?php echo esc_html( $field( 'next_action_hint' ) ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $can_commence && ! $show_commence_form ) : ?>
			<p>
				<a class="<?php echo esc_attr( $btn_class ); ?>" href="<?php echo esc_url( add_query_arg( 'jmrs_commence', '1' ) ); ?>">
					<?php echo esc_html__( 'Confirm Care Commenced', 'jm-referral-system' ); ?>
				</a>
			</p>
		<?php endif; ?>

		<?php if ( $can_commence && $show_commence_form ) : ?>
			<form method="post" action="<?php echo '' !== $form_action ? esc_url( $form_action ) : ''; ?>" class="jmrs-transition-planning__form" id="jmrs-care-commence-form">
				<?php wp_nonce_field( 'jmrs_confirm_care_commenced_' . $referral_id, 'jmrs_confirm_care_commenced_nonce' ); ?>
				<input type="hidden" name="jmrs_confirm_care_commenced" value="1" />
				<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

				<p>
					<label for="jmrs_care_commenced_at">
						<strong><?php echo esc_html__( 'Care commencement date / time', 'jm-referral-system' ); ?></strong>
						<span class="jmrs-transition-planning__required"><?php echo esc_html__( '(required)', 'jm-referral-system' ); ?></span>
					</label><br />
					<input
						type="datetime-local"
						id="jmrs_care_commenced_at"
						name="jmrs_care_commenced_at"
						value="<?php echo esc_attr( $field( 'default_commenced_at' ) ); ?>"
						required
						aria-required="true"
						aria-describedby="jmrs-care-commenced-at-help"
					/>
					<span id="jmrs-care-commenced-at-help" class="jmrs-transition-planning__hint">
						<?php echo esc_html__( 'Must not be in the future. For Supported Living, must be on or after the move-in date.', 'jm-referral-system' ); ?>
					</span>
				</p>

				<?php if ( ! empty( $transition_panel['requires_funding_ack'] ) ) : ?>
					<p class="jmrs-transition-planning__ack">
						<label for="jmrs_funding_acknowledge">
							<input type="checkbox" id="jmrs_funding_acknowledge" name="jmrs_funding_acknowledge" value="1" required aria-required="true" />
							<?php echo esc_html__( 'I confirm that JM has decided to commence care even though funding is not confirmed / not recorded.', 'jm-referral-system' ); ?>
							<span class="jmrs-transition-planning__required"><?php echo esc_html__( '(required)', 'jm-referral-system' ); ?></span>
						</label>
					</p>
				<?php endif; ?>

				<p>
					<button type="submit" class="<?php echo esc_attr( $btn_class ); ?>" id="jmrs-care-commence-submit">
						<?php echo esc_html__( 'Confirm Care Commenced', 'jm-referral-system' ); ?>
					</button>
				</p>
			</form>
			<script>
			(function () {
				var form = document.getElementById('jmrs-care-commence-form');
				if (!form) { return; }
				form.addEventListener('submit', function () {
					var btn = document.getElementById('jmrs-care-commence-submit');
					if (btn) {
						btn.disabled = true;
					}
				});
			})();
			</script>
		<?php endif; ?>
	<?php endif; ?>
</section>
