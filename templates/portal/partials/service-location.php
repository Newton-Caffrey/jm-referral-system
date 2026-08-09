<?php
/**
 * Reusable service location panel (no DB queries).
 *
 * Expects variables from ServiceLocationPresenter::panel_vars(), or a bag in
 * $service_location_panel which is extracted when present.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( isset( $service_location_panel ) && is_array( $service_location_panel ) ) {
	extract( $service_location_panel, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- scoped panel bag.
}

$service_location_heading             = (string) ( $service_location_heading ?? __( 'Service Location', 'jm-referral-system' ) );
$service_location_label               = (string) ( $service_location_label ?? '' );
$service_location_address_lines       = is_array( $service_location_address_lines ?? null ) ? $service_location_address_lines : array();
$service_location_show_warning        = ! empty( $service_location_show_warning );
$service_location_warning             = (string) ( $service_location_warning ?? '' );
$service_location_show_recorded_at    = ! empty( $service_location_show_recorded_at );
$service_location_recorded_at_display = (string) ( $service_location_recorded_at_display ?? '' );
$service_location_compact             = ! empty( $service_location_compact );
$service_location_unavailable         = (string) ( $service_location_unavailable ?? '' );
$service_location_secondary           = is_array( $service_location_secondary ?? null ) ? $service_location_secondary : null;
$service_location_secondary_heading   = (string) ( $service_location_secondary_heading ?? '' );
$panel_id                             = (string) ( $service_location_panel_id ?? 'jmrs-service-location' );
?>

<section class="jmrs-portal-section jmrs-portal-panel jmrs-service-location<?php echo $service_location_compact ? ' jmrs-service-location--compact' : ''; ?>" aria-labelledby="<?php echo esc_attr( $panel_id ); ?>">
	<?php if ( ! $service_location_compact ) : ?>
		<?php
		$section_title   = $service_location_heading;
		$section_id      = $panel_id;
		$section_badge   = '';
		$section_actions = array();
		include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
		?>
	<?php else : ?>
		<h3 class="jmrs-portal-summary-block__title" id="<?php echo esc_attr( $panel_id ); ?>"><?php echo esc_html( $service_location_heading ); ?></h3>
	<?php endif; ?>

	<?php if ( $service_location_show_warning && '' !== $service_location_warning ) : ?>
		<?php
		$notice_type    = 'warning';
		$notice_message = $service_location_warning;
		$notice_actions = array();
		include JMRS_PLUGIN_PATH . 'templates/portal/partials/notice.php';
		?>
	<?php endif; ?>

	<?php if ( '' !== $service_location_unavailable ) : ?>
		<p class="jmrs-service-location__unavailable"><?php echo esc_html( $service_location_unavailable ); ?></p>
	<?php else : ?>
		<div class="jmrs-service-location__body">
			<p class="jmrs-service-location__label"><strong><?php echo esc_html( $service_location_label ); ?></strong></p>
			<?php if ( ! empty( $service_location_address_lines ) ) : ?>
				<address class="jmrs-service-location__address">
					<?php foreach ( $service_location_address_lines as $address_line ) : ?>
						<span class="jmrs-service-location__address-line"><?php echo esc_html( (string) $address_line ); ?></span>
					<?php endforeach; ?>
				</address>
			<?php endif; ?>
			<?php if ( $service_location_show_recorded_at && '' !== $service_location_recorded_at_display ) : ?>
				<p class="jmrs-portal-muted jmrs-service-location__recorded">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: formatted datetime */
							__( 'Recorded: %s', 'jm-referral-system' ),
							$service_location_recorded_at_display
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( null !== $service_location_secondary ) : ?>
		<div class="jmrs-service-location__secondary">
			<?php if ( '' !== $service_location_secondary_heading ) : ?>
				<h3 class="jmrs-portal-summary-block__title"><?php echo esc_html( $service_location_secondary_heading ); ?></h3>
			<?php endif; ?>
			<p class="jmrs-service-location__label"><strong><?php echo esc_html( (string) ( $service_location_secondary['service_location_label'] ?? '' ) ); ?></strong></p>
			<?php
			$secondary_lines = is_array( $service_location_secondary['service_location_address_lines'] ?? null )
				? $service_location_secondary['service_location_address_lines']
				: array();
			?>
			<?php if ( ! empty( $secondary_lines ) ) : ?>
				<address class="jmrs-service-location__address">
					<?php foreach ( $secondary_lines as $address_line ) : ?>
						<span class="jmrs-service-location__address-line"><?php echo esc_html( (string) $address_line ); ?></span>
					<?php endforeach; ?>
				</address>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
