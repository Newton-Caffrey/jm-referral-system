<?php
/**
 * Transition Planning / Care Commencement panel (admin + portal).
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

$transition_panel  = is_array( $transition_panel ?? null ) ? $transition_panel : array();
$referral_id       = isset( $referral_id ) ? absint( $referral_id ) : 0;
$form_action       = isset( $form_action ) ? (string) $form_action : '';
$context           = isset( $context ) ? (string) $context : 'admin';
$transition_errors = is_array( $transition_errors ?? null ) ? $transition_errors : array();
$show_commence_form = ! empty( $show_commence_form );
$is_portal         = 'portal' === $context;

if ( $referral_id <= 0 || empty( $transition_panel['show_panel'] ) ) {
	return;
}

$field = static function ( string $key ) use ( $transition_panel ): string {
	return (string) ( $transition_panel[ $key ] ?? '' );
};

$is_commenced = ! empty( $transition_panel['is_care_commenced'] );
$can_commence = ! empty( $transition_panel['can_commence'] );
$care_setting = $field( 'care_setting' );
$ops_links    = is_array( $transition_panel['ops_links'] ?? null ) ? $transition_panel['ops_links'] : array();
$soft_warnings = is_array( $transition_panel['soft_warnings'] ?? null ) ? $transition_panel['soft_warnings'] : array();
$hard_blockers = is_array( $transition_panel['hard_blockers'] ?? null ) ? $transition_panel['hard_blockers'] : array();
?>
<div class="jmrs-transition-planning" style="margin: 1.25em 0; padding: 1em 1.25em; border: 1px solid #2271b1; background: #f0f6fc;">
	<h2 style="margin-top: 0;">
		<?php
		echo esc_html(
			$is_commenced
				? __( 'Care Commenced', 'jm-referral-system' )
				: __( 'Transition Planning', 'jm-referral-system' )
		);
		?>
	</h2>

	<?php if ( $is_commenced ) : ?>
		<p>
			<strong><?php echo esc_html__( 'Date / Time:', 'jm-referral-system' ); ?></strong>
			<?php echo esc_html( $field( 'care_commenced_at' ) ); ?>
		</p>
		<?php if ( '' !== $field( 'care_commenced_by_name' ) ) : ?>
			<p>
				<strong><?php echo esc_html__( 'Recorded By:', 'jm-referral-system' ); ?></strong>
				<?php echo esc_html( $field( 'care_commenced_by_name' ) ); ?>
			</p>
		<?php endif; ?>
		<p>
			<strong><?php echo esc_html__( 'Care Setting:', 'jm-referral-system' ); ?></strong>
			<?php echo esc_html( $field( 'care_setting_label' ) ); ?>
		</p>
		<?php if ( 'supported_living' === $care_setting && '' !== $field( 'placement_home_name' ) ) : ?>
			<p>
				<strong><?php echo esc_html__( 'Current Placement:', 'jm-referral-system' ); ?></strong>
				<?php
				echo esc_html(
					trim(
						$field( 'placement_home_name' )
						. ( '' !== $field( 'placement_room_label' ) ? ' — ' . $field( 'placement_room_label' ) : '' )
					)
				);
				?>
			</p>
		<?php elseif ( 'own_home' === $care_setting && '' !== $field( 'service_location_label' ) ) : ?>
			<p>
				<strong><?php echo esc_html__( 'Service Location:', 'jm-referral-system' ); ?></strong>
				<?php echo esc_html( $field( 'service_location_label' ) ); ?>
			</p>
		<?php endif; ?>

		<p class="description">
			<?php echo esc_html__( 'Acquisition is complete. Care operations continue on this record.', 'jm-referral-system' ); ?>
		</p>

		<?php if ( ! empty( $ops_links ) ) : ?>
			<p>
				<?php if ( ! empty( $ops_links['care_plan'] ) ) : ?>
					<a class="<?php echo $is_portal ? 'jmrs-portal-btn jmrs-portal-btn--secondary' : 'button'; ?>" href="<?php echo esc_url( (string) $ops_links['care_plan'] ); ?>"><?php echo esc_html__( 'Care Plan', 'jm-referral-system' ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $ops_links['care_team'] ) ) : ?>
					<a class="<?php echo $is_portal ? 'jmrs-portal-btn jmrs-portal-btn--secondary' : 'button'; ?>" href="<?php echo esc_url( (string) $ops_links['care_team'] ); ?>"><?php echo esc_html__( 'Care Team', 'jm-referral-system' ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $ops_links['schedules'] ) ) : ?>
					<a class="<?php echo $is_portal ? 'jmrs-portal-btn jmrs-portal-btn--secondary' : 'button'; ?>" href="<?php echo esc_url( (string) $ops_links['schedules'] ); ?>"><?php echo esc_html__( 'Schedules', 'jm-referral-system' ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $ops_links['visits'] ) ) : ?>
					<a class="<?php echo $is_portal ? 'jmrs-portal-btn jmrs-portal-btn--secondary' : 'button'; ?>" href="<?php echo esc_url( (string) $ops_links['visits'] ); ?>"><?php echo esc_html__( 'Visits', 'jm-referral-system' ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $ops_links['medications'] ) ) : ?>
					<a class="<?php echo $is_portal ? 'jmrs-portal-btn jmrs-portal-btn--secondary' : 'button'; ?>" href="<?php echo esc_url( (string) $ops_links['medications'] ); ?>"><?php echo esc_html__( 'Medications', 'jm-referral-system' ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $ops_links['documents'] ) ) : ?>
					<a class="<?php echo $is_portal ? 'jmrs-portal-btn jmrs-portal-btn--secondary' : 'button'; ?>" href="<?php echo esc_url( (string) $ops_links['documents'] ); ?>"><?php echo esc_html__( 'Documents', 'jm-referral-system' ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>

	<?php else : ?>

		<p>
			<strong><?php echo esc_html__( 'Local Authority Approval', 'jm-referral-system' ); ?></strong>
			<?php if ( ! empty( $transition_panel['la_approved'] ) ) : ?>
				— <?php echo esc_html__( 'Approved', 'jm-referral-system' ); ?>
			<?php else : ?>
				— <?php echo esc_html__( 'Required', 'jm-referral-system' ); ?>
			<?php endif; ?>
		</p>

		<p>
			<strong><?php echo esc_html__( 'Funding Confirmed', 'jm-referral-system' ); ?></strong>
			— <?php echo esc_html( $field( 'funding_confirmed_label' ) ); ?>
		</p>

		<p>
			<strong><?php echo esc_html__( 'Care Setting', 'jm-referral-system' ); ?></strong>
			— <?php echo esc_html( $field( 'care_setting_label' ) ); ?>
			<?php if ( ! empty( $transition_panel['care_setting_required'] ) && '' !== $field( 'care_setting_url' ) ) : ?>
				<br />
				<a href="<?php echo esc_url( $field( 'care_setting_url' ) ); ?>">
					<?php echo esc_html__( 'Choose Supported Living or Own Home', 'jm-referral-system' ); ?>
				</a>
			<?php endif; ?>
		</p>

		<?php if ( 'supported_living' === $care_setting ) : ?>
			<p>
				<strong><?php echo esc_html__( 'Placement', 'jm-referral-system' ); ?></strong>
				<?php if ( ! empty( $transition_panel['placement_ready'] ) ) : ?>
					— <?php echo esc_html__( 'Ready / Active', 'jm-referral-system' ); ?>
					<?php if ( '' !== $field( 'placement_home_name' ) ) : ?>
						<br />
						<?php
						echo esc_html(
							trim(
								$field( 'placement_home_name' )
								. ( '' !== $field( 'placement_room_label' ) ? ' — ' . $field( 'placement_room_label' ) : '' )
							)
						);
						?>
					<?php endif; ?>
					<?php if ( '' !== $field( 'placement_move_in_date' ) ) : ?>
						<br />
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: move-in date */
								__( 'Move-In Date: %s', 'jm-referral-system' ),
								$field( 'placement_move_in_date' )
							)
						);
						?>
					<?php endif; ?>
				<?php else : ?>
					— <?php echo esc_html__( 'Placement Required', 'jm-referral-system' ); ?>
					<?php if ( '' !== $field( 'place_resident_url' ) ) : ?>
						<br />
						<a class="<?php echo $is_portal ? 'jmrs-portal-btn' : 'button button-primary'; ?>" href="<?php echo esc_url( $field( 'place_resident_url' ) ); ?>">
							<?php echo esc_html__( 'Place Resident', 'jm-referral-system' ); ?>
						</a>
					<?php endif; ?>
				<?php endif; ?>
			</p>
		<?php elseif ( 'own_home' === $care_setting ) : ?>
			<p>
				<strong><?php echo esc_html__( 'Service Location', 'jm-referral-system' ); ?></strong>
				— <?php echo esc_html__( 'Own Home', 'jm-referral-system' ); ?>
			</p>
			<?php if ( '' !== $field( 'own_home_address_summary' ) ) : ?>
				<p>
					<strong><?php echo esc_html__( 'Address', 'jm-referral-system' ); ?></strong>
					— <?php echo esc_html( $field( 'own_home_address_summary' ) ); ?>
				</p>
			<?php endif; ?>
		<?php endif; ?>

		<p>
			<strong><?php echo esc_html__( 'Care Plan', 'jm-referral-system' ); ?></strong>
			— <?php echo esc_html( $field( 'care_plan_status_label' ) ); ?>
		</p>
		<p>
			<strong><?php echo esc_html__( 'Care Team', 'jm-referral-system' ); ?></strong>
			—
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
		</p>
		<p>
			<strong><?php echo esc_html__( 'Schedule', 'jm-referral-system' ); ?></strong>
			—
			<?php
			echo ! empty( $transition_panel['schedule_ready'] )
				? esc_html__( 'Active schedule exists', 'jm-referral-system' )
				: esc_html__( 'No active schedule', 'jm-referral-system' );
			?>
		</p>
		<p>
			<strong><?php echo esc_html__( 'Care Commencement', 'jm-referral-system' ); ?></strong>
			— <?php echo esc_html__( 'Not yet confirmed', 'jm-referral-system' ); ?>
		</p>

		<?php if ( ! empty( $soft_warnings ) ) : ?>
			<div style="margin: 0.75em 0; padding: 0.75em 1em; border-left: 4px solid #dba617; background: #fcf9e8;">
				<p style="margin: 0 0 0.35em;"><strong><?php echo esc_html__( 'Operational warnings', 'jm-referral-system' ); ?></strong></p>
				<ul style="margin: 0;">
					<?php foreach ( $soft_warnings as $warning ) : ?>
						<li><?php echo esc_html( (string) $warning ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $hard_blockers ) && ! $can_commence ) : ?>
			<ul style="color:#b32d2e;">
				<?php foreach ( $hard_blockers as $blocker ) : ?>
					<li><?php echo esc_html( (string) $blocker ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $transition_errors ) ) : ?>
			<ul style="color:#b32d2e;">
				<?php foreach ( $transition_errors as $err ) : ?>
					<li><?php echo esc_html( (string) $err ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $can_commence && ! $show_commence_form ) : ?>
			<p>
				<?php if ( $is_portal ) : ?>
					<a class="jmrs-portal-btn" href="<?php echo esc_url( add_query_arg( 'jmrs_commence', '1' ) ); ?>">
						<?php echo esc_html__( 'Confirm Care Commenced', 'jm-referral-system' ); ?>
					</a>
				<?php else : ?>
					<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'jmrs_commence', '1' ) ); ?>">
						<?php echo esc_html__( 'Confirm Care Commenced', 'jm-referral-system' ); ?>
					</a>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php if ( $can_commence && $show_commence_form ) : ?>
			<form method="post" action="<?php echo '' !== $form_action ? esc_url( $form_action ) : ''; ?>" style="margin-top: 1em; padding-top: 0.75em; border-top: 1px solid #c3c4c7;" id="jmrs-care-commence-form">
				<?php wp_nonce_field( 'jmrs_confirm_care_commenced_' . $referral_id, 'jmrs_confirm_care_commenced_nonce' ); ?>
				<input type="hidden" name="jmrs_confirm_care_commenced" value="1" />
				<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

				<p>
					<label for="jmrs_care_commenced_at"><strong><?php echo esc_html__( 'Care Commencement Date / Time', 'jm-referral-system' ); ?></strong></label><br />
					<input
						type="datetime-local"
						id="jmrs_care_commenced_at"
						name="jmrs_care_commenced_at"
						value="<?php echo esc_attr( $field( 'default_commenced_at' ) ); ?>"
						required
					/>
				</p>

				<?php if ( ! empty( $transition_panel['requires_funding_ack'] ) ) : ?>
					<p style="padding: 0.75em 1em; border-left: 4px solid #d63638; background: #fcf0f1;">
						<label>
							<input type="checkbox" name="jmrs_funding_acknowledge" value="1" required />
							<?php echo esc_html__( 'I confirm that JM has decided to commence care even though funding is not confirmed / not recorded.', 'jm-referral-system' ); ?>
						</label>
					</p>
				<?php endif; ?>

				<p>
					<button type="submit" class="<?php echo $is_portal ? 'jmrs-portal-btn' : 'button button-primary'; ?>" id="jmrs-care-commence-submit">
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
</div>
