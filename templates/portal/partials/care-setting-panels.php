<?php
/**
 * Care setting + Supported Living / Own-Home panels on referral view.
 *
 * @package JMReferral
 *
 * @var array<string, mixed>|null $service_location_panel Prepared by ServiceLocationPresenter::panel_vars().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$care_setting_label          = (string) ( $care_setting_label ?? \JMReferral\Referral\CareSetting::label( $referral['care_setting'] ?? null ) );
$is_own_home_setting         = ! empty( $is_own_home_setting );
$is_supported_living_setting = ! empty( $is_supported_living_setting );
$is_care_setting_unspecified = ! empty( $is_care_setting_unspecified );
$own_home_address_incomplete = ! empty( $own_home_address_incomplete );
$address_warning_notice      = ! empty( $address_warning_notice );
$can_edit_referral           = ! empty( $can_edit_referral );
$edit_url                    = (string) ( $edit_url ?? '' );
$current_placement           = is_array( $current_placement ?? null ) ? $current_placement : null;
$placement_history           = is_array( $placement_history ?? null ) ? $placement_history : array();
$can_view_placement          = ! empty( $can_view_placement );
$can_manage_occupancies      = ! empty( $can_manage_occupancies );
$place_url                   = (string) ( $place_url ?? '' );
$transfer_url                = (string) ( $transfer_url ?? '' );
$end_placement_url           = (string) ( $end_placement_url ?? '' );
$placement_notice            = is_array( $placement_notice ?? null ) ? $placement_notice : null;
$service_location_panel      = is_array( $service_location_panel ?? null ) ? $service_location_panel : null;

$show_sl_placement     = $can_view_placement && ( $is_supported_living_setting || null !== $current_placement || ( ! $is_own_home_setting && ! empty( $placement_history ) ) );
$show_own_home_history = $is_own_home_setting && $can_view_placement && ! empty( $placement_history );
?>

<?php if ( $address_warning_notice || $own_home_address_incomplete ) : ?>
	<?php
	$notice_type    = 'warning';
	$notice_message = __( "Client's home address is incomplete. Update the address before delivering home-based care.", 'jm-referral-system' );
	$notice_actions = array();
	if ( $can_edit_referral && '' !== $edit_url ) {
		$notice_actions[] = array( __( 'Edit Referral', 'jm-referral-system' ), $edit_url, 'jmrs-button jmrs-button--secondary' );
	}
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/notice.php';
	?>
<?php endif; ?>

<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-care-setting">
	<?php
	$section_title   = __( 'Care Setting', 'jm-referral-system' );
	$section_id      = 'jmrs-portal-ref-care-setting';
	$section_badge   = $care_setting_label;
	$section_actions = array();
	if ( $can_edit_referral && '' !== $edit_url ) {
		$section_actions[] = array(
			$is_care_setting_unspecified
				? __( 'Set Care Setting', 'jm-referral-system' )
				: __( 'Change Care Setting', 'jm-referral-system' ),
			$edit_url,
			'jmrs-button jmrs-button--secondary',
		);
	}
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
	?>
	<div class="jmrs-portal-dl-grid">
		<div>
			<dt><?php echo esc_html__( 'Care Setting', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( $care_setting_label ); ?></dd>
		</div>
		<?php if ( $is_supported_living_setting && null !== $current_placement ) : ?>
			<div>
				<dt><?php echo esc_html__( 'Current Placement', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( (string) ( $current_placement['home_name'] ?? '' ) . ' — ' . (string) ( $current_placement['room_label'] ?? '' ) ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Since', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( (string) ( $current_placement['move_in_date'] ?? '—' ) ); ?></dd>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( null !== $service_location_panel ) : ?>
		<?php
		$loc_heading = (string) ( $service_location_panel['service_location_heading'] ?? __( 'Current Service Location', 'jm-referral-system' ) );
		$loc_label   = (string) ( $service_location_panel['service_location_label'] ?? '' );
		$loc_lines   = is_array( $service_location_panel['service_location_address_lines'] ?? null )
			? $service_location_panel['service_location_address_lines']
			: array();
		$loc_warn    = ! empty( $service_location_panel['service_location_show_warning'] )
			? (string) ( $service_location_panel['service_location_warning'] ?? '' )
			: '';
		?>
		<h3 class="jmrs-portal-summary-block__title"><?php echo esc_html( $loc_heading ); ?></h3>
		<?php if ( '' !== $loc_warn ) : ?>
			<?php
			$notice_type    = 'warning';
			$notice_message = $loc_warn;
			$notice_actions = array();
			include JMRS_PLUGIN_PATH . 'templates/portal/partials/notice.php';
			?>
		<?php endif; ?>
		<p class="jmrs-service-location__label"><strong><?php echo esc_html( $loc_label ); ?></strong></p>
		<?php if ( ! empty( $loc_lines ) ) : ?>
			<address class="jmrs-service-location__address">
				<?php foreach ( $loc_lines as $address_line ) : ?>
					<span class="jmrs-service-location__address-line"><?php echo esc_html( (string) $address_line ); ?></span>
				<?php endforeach; ?>
			</address>
		<?php endif; ?>
	<?php endif; ?>
</section>

<?php if ( $is_own_home_setting ) : ?>
	<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-own-home">
		<?php
		$section_title   = __( 'Own-Home Support', 'jm-referral-system' );
		$section_id      = 'jmrs-portal-ref-own-home';
		$section_badge   = '';
		$section_actions = array();
		if ( $can_edit_referral && '' !== $edit_url ) {
			$section_actions[] = array( __( 'Edit Address', 'jm-referral-system' ), $edit_url, 'jmrs-button jmrs-button--secondary' );
		}
		include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
		?>
		<p class="jmrs-portal-muted"><?php echo esc_html__( 'Care is delivered at the client\'s own home. The client address is the own-home service location.', 'jm-referral-system' ); ?></p>
	</section>
<?php endif; ?>

<?php if ( $show_sl_placement ) : ?>
	<?php if ( is_array( $placement_notice ) && ! empty( $placement_notice['message'] ) ) : ?>
		<?php
		$notice_type    = (string) ( $placement_notice['type'] ?? 'success' );
		$notice_message = (string) $placement_notice['message'];
		$notice_actions = array();
		include JMRS_PLUGIN_PATH . 'templates/portal/partials/notice.php';
		?>
	<?php endif; ?>

	<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-placement">
		<?php
		$section_title   = __( 'Supported Living', 'jm-referral-system' );
		$section_id      = 'jmrs-portal-ref-placement';
		$section_badge   = null !== $current_placement ? __( 'Active', 'jm-referral-system' ) : __( 'No active placement', 'jm-referral-system' );
		$section_actions = array();
		if ( '' !== $place_url ) {
			$section_actions[] = array( __( 'Place Resident', 'jm-referral-system' ), $place_url, 'jmrs-button jmrs-button--primary' );
		}
		if ( '' !== $transfer_url ) {
			$section_actions[] = array( __( 'Transfer', 'jm-referral-system' ), $transfer_url, 'jmrs-button jmrs-button--secondary' );
		}
		if ( '' !== $end_placement_url ) {
			$section_actions[] = array( __( 'End Placement', 'jm-referral-system' ), $end_placement_url, 'jmrs-button jmrs-button--secondary' );
		}
		include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
		?>

		<?php if ( null !== $current_placement ) : ?>
			<h3 class="jmrs-portal-summary-block__title"><?php echo esc_html__( 'Current Placement', 'jm-referral-system' ); ?></h3>
			<div class="jmrs-portal-dl-grid">
				<div>
					<dt><?php echo esc_html__( 'Home', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( (string) ( $current_placement['home_name'] ?? '—' ) ); ?></dd>
				</div>
				<div>
					<dt><?php echo esc_html__( 'Bedroom', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( (string) ( $current_placement['room_label'] ?? '—' ) ); ?></dd>
				</div>
				<div>
					<dt><?php echo esc_html__( 'Move-in', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( (string) ( $current_placement['move_in_date'] ?? '—' ) ); ?></dd>
				</div>
			</div>
		<?php else : ?>
			<?php
			$empty_title   = __( 'No active placement', 'jm-referral-system' );
			$empty_message = __( 'Supported Living — awaiting placement. Place this client when a suitable bedroom is available.', 'jm-referral-system' );
			$empty_actions = array();
			if ( '' !== $place_url ) {
				$empty_actions[] = array( __( 'Place Resident', 'jm-referral-system' ), $place_url, 'jmrs-button jmrs-button--primary' );
			}
			include JMRS_PLUGIN_PATH . 'templates/portal/partials/empty-state.php';
			?>
		<?php endif; ?>

		<h3 class="jmrs-portal-summary-block__title"><?php echo esc_html__( 'Placement History', 'jm-referral-system' ); ?></h3>
		<?php if ( empty( $placement_history ) ) : ?>
			<p class="jmrs-portal-muted"><?php echo esc_html__( 'No previous Supported Living placements.', 'jm-referral-system' ); ?></p>
		<?php else : ?>
			<ul class="jmrs-portal-activity">
				<?php foreach ( $placement_history as $history_row ) : ?>
					<?php
					$is_active_row = 'active' === ( $history_row['status'] ?? '' );
					$range         = (string) ( $history_row['move_in_date'] ?? '' );
					if ( $is_active_row ) {
						$range .= ' → ' . __( 'present', 'jm-referral-system' );
					} else {
						$range .= ' → ' . (string) ( $history_row['move_out_date'] ?? '' );
					}
					?>
					<li>
						<strong><?php echo esc_html( (string) ( $history_row['home_name'] ?? '' ) . ' — ' . (string) ( $history_row['room_label'] ?? '' ) ); ?></strong>
						<span class="jmrs-portal-badge"><?php echo esc_html( $is_active_row ? __( 'Active', 'jm-referral-system' ) : __( 'Ended', 'jm-referral-system' ) ); ?></span>
						<span class="jmrs-portal-muted"><?php echo esc_html( $range ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
<?php elseif ( $show_own_home_history ) : ?>
	<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-placement-history">
		<?php
		$section_title   = __( 'Supported Living History', 'jm-referral-system' );
		$section_id      = 'jmrs-portal-ref-placement-history';
		$section_badge   = '';
		$section_actions = array();
		include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
		?>
		<p class="jmrs-portal-muted"><?php echo esc_html__( 'Previous Supported Living placements are retained for audit.', 'jm-referral-system' ); ?></p>
		<ul class="jmrs-portal-activity">
			<?php foreach ( $placement_history as $history_row ) : ?>
				<?php
				$is_active_row = 'active' === ( $history_row['status'] ?? '' );
				$range         = (string) ( $history_row['move_in_date'] ?? '' );
				if ( $is_active_row ) {
					$range .= ' → ' . __( 'present', 'jm-referral-system' );
				} else {
					$range .= ' → ' . (string) ( $history_row['move_out_date'] ?? '' );
				}
				?>
				<li>
					<strong><?php echo esc_html( (string) ( $history_row['home_name'] ?? '' ) . ' — ' . (string) ( $history_row['room_label'] ?? '' ) ); ?></strong>
					<span class="jmrs-portal-badge"><?php echo esc_html( $is_active_row ? __( 'Active', 'jm-referral-system' ) : __( 'Ended', 'jm-referral-system' ) ); ?></span>
					<span class="jmrs-portal-muted"><?php echo esc_html( $range ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>
