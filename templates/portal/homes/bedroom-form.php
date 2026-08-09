<?php
/**
 * Portal supported living bedroom create/edit form.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data          = is_array( $data ?? null ) ? $data : array();
$errors        = is_array( $errors ?? null ) ? $errors : array();
$status_labels = is_array( $status_labels ?? null ) ? $status_labels : array();
$form_action   = (string) ( $form_action ?? '' );
$cancel_url    = (string) ( $cancel_url ?? '' );
$home_id       = isset( $home_id ) ? absint( $home_id ) : 0;
$bedroom_id    = isset( $bedroom_id ) ? absint( $bedroom_id ) : 0;
$home_name     = (string) ( $home_name ?? '' );
$is_create     = ! empty( $is_create );
$home_is_active = ! empty( $home_is_active );

$val = static function ( array $data, string $key ): string {
	return (string) ( $data[ $key ] ?? '' );
};

$field_error = static function ( array $errors, string $key ): void {
	if ( ! isset( $errors[ $key ] ) ) {
		return;
	}
	echo '<p class="jmrs-portal-field-error" id="jmrs-err-' . esc_attr( $key ) . '">' . esc_html( (string) $errors[ $key ] ) . '</p>';
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
	<?php wp_nonce_field( 'jmrs_save_bedroom_' . $home_id . '_' . $bedroom_id, 'jmrs_bedroom_nonce' ); ?>
	<input type="hidden" name="jmrs_home_id" value="<?php echo esc_attr( (string) $home_id ); ?>" />
	<input type="hidden" name="jmrs_bedroom_id" value="<?php echo esc_attr( (string) $bedroom_id ); ?>" />
	<input type="hidden" name="jmrs_save_bedroom" value="1" />

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title">
			<?php echo esc_html( $is_create ? __( 'Add Bedroom', 'jm-referral-system' ) : __( 'Edit Bedroom', 'jm-referral-system' ) ); ?>
		</h2>
		<p class="jmrs-portal-muted">
			<?php
			printf(
				/* translators: %s: home name */
				esc_html__( 'Home: %s', 'jm-referral-system' ),
				esc_html( $home_name )
			);
			?>
		</p>

		<?php if ( ! $home_is_active ) : ?>
			<p class="jmrs-portal-muted">
				<?php echo esc_html__( 'This home is inactive. You can edit existing bedrooms, but new active bedrooms cannot be added until the home is reactivated.', 'jm-referral-system' ); ?>
			</p>
		<?php endif; ?>

		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_bedroom_room_label"><?php echo esc_html__( 'Room Label', 'jm-referral-system' ); ?> <span aria-hidden="true">*</span></label>
				<input type="text" name="jmrs_bedroom_room_label" id="jmrs_bedroom_room_label" value="<?php echo esc_attr( $val( $data, 'room_label' ) ); ?>" required aria-required="true" <?php echo isset( $errors['room_label'] ) ? 'aria-invalid="true" aria-describedby="jmrs-err-room_label"' : ''; ?> />
				<?php $field_error( $errors, 'room_label' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_bedroom_floor"><?php echo esc_html__( 'Floor', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_bedroom_floor" id="jmrs_bedroom_floor" value="<?php echo esc_attr( $val( $data, 'floor' ) ); ?>" />
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_bedroom_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
				<select name="jmrs_bedroom_status" id="jmrs_bedroom_status" required>
					<?php foreach ( $status_labels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $val( $data, 'status' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'status' ); ?>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_bedroom_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_bedroom_notes" id="jmrs_bedroom_notes" rows="4"><?php echo esc_textarea( $val( $data, 'notes' ) ); ?></textarea>
			</div>
		</div>
	</section>

	<p class="jmrs-portal-actions">
		<button type="submit" class="jmrs-button jmrs-button--primary">
			<?php echo esc_html( $is_create ? __( 'Create Bedroom', 'jm-referral-system' ) : __( 'Save Bedroom', 'jm-referral-system' ) ); ?>
		</button>
		<?php if ( '' !== $cancel_url ) : ?>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>"><?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?></a>
		<?php endif; ?>
	</p>
</form>
