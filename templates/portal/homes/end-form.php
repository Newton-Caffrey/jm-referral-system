<?php
/**
 * Portal end placement form.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data         = is_array( $data ?? null ) ? $data : array();
$errors       = is_array( $errors ?? null ) ? $errors : array();
$current      = is_array( $current ?? null ) ? $current : array();
$form_action  = (string) ( $form_action ?? '' );
$cancel_url   = (string) ( $cancel_url ?? '' );
$occupancy_id = isset( $occupancy_id ) ? absint( $occupancy_id ) : 0;

$val = static function ( array $data, string $key ): string {
	return (string) ( $data[ $key ] ?? '' );
};
?>
<?php if ( ! empty( $errors ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert">
		<p><strong><?php echo esc_html__( 'Please fix the following errors:', 'jm-referral-system' ); ?></strong></p>
		<ul>
			<?php foreach ( $errors as $message ) : ?>
				<li><?php echo esc_html( (string) $message ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<form class="jmrs-portal-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
	<?php wp_nonce_field( 'jmrs_save_end_placement_' . $occupancy_id, 'jmrs_occupancy_nonce' ); ?>
	<input type="hidden" name="jmrs_save_end_placement" value="1" />

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'End Supported Living Placement', 'jm-referral-system' ); ?></h2>
		<p class="jmrs-portal-muted">
			<?php
			printf(
				/* translators: 1: home name, 2: room label */
				esc_html__( 'Current: %1$s — %2$s', 'jm-referral-system' ),
				esc_html( (string) ( $current['home_name'] ?? '' ) ),
				esc_html( (string) ( $current['room_label'] ?? '' ) )
			);
			?>
		</p>

		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_move_out_date"><?php echo esc_html__( 'Move-out Date', 'jm-referral-system' ); ?> <span aria-hidden="true">*</span></label>
				<input type="date" name="jmrs_move_out_date" id="jmrs_move_out_date" value="<?php echo esc_attr( $val( $data, 'move_out_date' ) ); ?>" required />
				<?php if ( isset( $errors['move_out_date'] ) ) : ?>
					<p class="jmrs-portal-field-error"><?php echo esc_html( (string) $errors['move_out_date'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="jmrs-portal-field">
				<label for="jmrs_end_reason"><?php echo esc_html__( 'Reason', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_end_reason" id="jmrs_end_reason" value="<?php echo esc_attr( $val( $data, 'end_reason' ) ); ?>" />
			</div>

			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_notes" id="jmrs_notes" rows="3"><?php echo esc_textarea( $val( $data, 'notes' ) ); ?></textarea>
			</div>

			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label>
					<input type="checkbox" name="jmrs_confirm_end" value="1" />
					<?php echo esc_html__( 'I confirm this placement should be ended. The bedroom will become vacant and the history will be retained.', 'jm-referral-system' ); ?>
				</label>
				<?php if ( isset( $errors['confirm'] ) ) : ?>
					<p class="jmrs-portal-field-error"><?php echo esc_html( (string) $errors['confirm'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<p class="jmrs-portal-actions">
		<button type="submit" class="jmrs-button jmrs-button--primary"><?php echo esc_html__( 'End Placement', 'jm-referral-system' ); ?></button>
		<?php if ( '' !== $cancel_url ) : ?>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>"><?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?></a>
		<?php endif; ?>
	</p>
</form>
