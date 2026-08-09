<?php
/**
 * Portal transfer resident form.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data            = is_array( $data ?? null ) ? $data : array();
$errors          = is_array( $errors ?? null ) ? $errors : array();
$current         = is_array( $current ?? null ) ? $current : array();
$homes           = is_array( $homes ?? null ) ? $homes : array();
$vacant_bedrooms = is_array( $vacant_bedrooms ?? null ) ? $vacant_bedrooms : array();
$form_action     = (string) ( $form_action ?? '' );
$cancel_url      = (string) ( $cancel_url ?? '' );
$occupancy_id    = isset( $occupancy_id ) ? absint( $occupancy_id ) : 0;

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
	<?php wp_nonce_field( 'jmrs_save_transfer_' . $occupancy_id, 'jmrs_occupancy_nonce' ); ?>
	<input type="hidden" name="jmrs_save_transfer" value="1" />

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Transfer Resident', 'jm-referral-system' ); ?></h2>
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
				<label for="jmrs_new_home_id"><?php echo esc_html__( 'New Home', 'jm-referral-system' ); ?> <span aria-hidden="true">*</span></label>
				<select name="jmrs_new_home_id" id="jmrs_new_home_id" required>
					<option value="0"><?php echo esc_html__( 'Select home', 'jm-referral-system' ); ?></option>
					<?php foreach ( $homes as $home_row ) : ?>
						<option value="<?php echo esc_attr( (string) absint( $home_row['id'] ?? 0 ) ); ?>" <?php selected( absint( $val( $data, 'new_home_id' ) ), absint( $home_row['id'] ?? 0 ) ); ?>>
							<?php echo esc_html( (string) ( $home_row['name'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" name="jmrs_reload_bedrooms" value="1" class="jmrs-button jmrs-button--secondary"><?php echo esc_html__( 'Load vacant bedrooms', 'jm-referral-system' ); ?></button>
				<?php if ( isset( $errors['new_home_id'] ) ) : ?>
					<p class="jmrs-portal-field-error"><?php echo esc_html( (string) $errors['new_home_id'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="jmrs-portal-field">
				<label for="jmrs_new_bedroom_id"><?php echo esc_html__( 'New Bedroom', 'jm-referral-system' ); ?> <span aria-hidden="true">*</span></label>
				<select name="jmrs_new_bedroom_id" id="jmrs_new_bedroom_id" required>
					<option value="0"><?php echo esc_html__( 'Select vacant bedroom', 'jm-referral-system' ); ?></option>
					<?php if ( empty( $vacant_bedrooms ) && absint( $val( $data, 'new_home_id' ) ) > 0 ) : ?>
						<option value="0" disabled><?php echo esc_html__( 'No vacant bedrooms are currently available.', 'jm-referral-system' ); ?></option>
					<?php endif; ?>
					<?php foreach ( $vacant_bedrooms as $bedroom_row ) : ?>
						<option value="<?php echo esc_attr( (string) absint( $bedroom_row['id'] ?? 0 ) ); ?>" <?php selected( absint( $val( $data, 'new_bedroom_id' ) ), absint( $bedroom_row['id'] ?? 0 ) ); ?>>
							<?php
							printf(
								/* translators: %s: room label */
								esc_html__( '%s — Vacant', 'jm-referral-system' ),
								esc_html( (string) ( $bedroom_row['room_label'] ?? '' ) )
							);
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( isset( $errors['new_bedroom_id'] ) ) : ?>
					<p class="jmrs-portal-field-error"><?php echo esc_html( (string) $errors['new_bedroom_id'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="jmrs-portal-field">
				<label for="jmrs_transfer_date"><?php echo esc_html__( 'Transfer Date', 'jm-referral-system' ); ?> <span aria-hidden="true">*</span></label>
				<input type="date" name="jmrs_transfer_date" id="jmrs_transfer_date" value="<?php echo esc_attr( $val( $data, 'transfer_date' ) ); ?>" required />
				<?php if ( isset( $errors['transfer_date'] ) ) : ?>
					<p class="jmrs-portal-field-error"><?php echo esc_html( (string) $errors['transfer_date'] ); ?></p>
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
		</div>
	</section>

	<p class="jmrs-portal-actions">
		<button type="submit" class="jmrs-button jmrs-button--primary"><?php echo esc_html__( 'Confirm Transfer', 'jm-referral-system' ); ?></button>
		<?php if ( '' !== $cancel_url ) : ?>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>"><?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?></a>
		<?php endif; ?>
	</p>
</form>
