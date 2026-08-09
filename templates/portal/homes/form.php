<?php
/**
 * Portal supported living home create/edit form.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data             = is_array( $data ?? null ) ? $data : array();
$errors           = is_array( $errors ?? null ) ? $errors : array();
$status_labels    = is_array( $status_labels ?? null ) ? $status_labels : array();
$manager_options  = is_array( $manager_options ?? null ) ? $manager_options : array();
$form_action      = (string) ( $form_action ?? '' );
$cancel_url       = (string) ( $cancel_url ?? '' );
$home_id          = isset( $home_id ) ? absint( $home_id ) : 0;
$is_create        = ! empty( $is_create );

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
	<?php wp_nonce_field( 'jmrs_save_home_' . $home_id, 'jmrs_home_nonce' ); ?>
	<input type="hidden" name="jmrs_home_id" value="<?php echo esc_attr( (string) $home_id ); ?>" />
	<input type="hidden" name="jmrs_save_home" value="1" />

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Home details', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_home_name"><?php echo esc_html__( 'Home Name', 'jm-referral-system' ); ?> <span aria-hidden="true">*</span></label>
				<input type="text" name="jmrs_home_name" id="jmrs_home_name" value="<?php echo esc_attr( $val( $data, 'name' ) ); ?>" required aria-required="true" <?php echo isset( $errors['name'] ) ? 'aria-invalid="true" aria-describedby="jmrs-err-name"' : ''; ?> />
				<?php $field_error( $errors, 'name' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_home_address_line_1"><?php echo esc_html__( 'Address Line 1', 'jm-referral-system' ); ?> <span aria-hidden="true">*</span></label>
				<input type="text" name="jmrs_home_address_line_1" id="jmrs_home_address_line_1" value="<?php echo esc_attr( $val( $data, 'address_line_1' ) ); ?>" required aria-required="true" <?php echo isset( $errors['address_line_1'] ) ? 'aria-invalid="true" aria-describedby="jmrs-err-address_line_1"' : ''; ?> />
				<?php $field_error( $errors, 'address_line_1' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_home_address_line_2"><?php echo esc_html__( 'Address Line 2', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_home_address_line_2" id="jmrs_home_address_line_2" value="<?php echo esc_attr( $val( $data, 'address_line_2' ) ); ?>" />
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_home_city"><?php echo esc_html__( 'City', 'jm-referral-system' ); ?> <span aria-hidden="true">*</span></label>
				<input type="text" name="jmrs_home_city" id="jmrs_home_city" value="<?php echo esc_attr( $val( $data, 'city' ) ); ?>" required aria-required="true" <?php echo isset( $errors['city'] ) ? 'aria-invalid="true" aria-describedby="jmrs-err-city"' : ''; ?> />
				<?php $field_error( $errors, 'city' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_home_postcode"><?php echo esc_html__( 'Postcode', 'jm-referral-system' ); ?> <span aria-hidden="true">*</span></label>
				<input type="text" name="jmrs_home_postcode" id="jmrs_home_postcode" value="<?php echo esc_attr( $val( $data, 'postcode' ) ); ?>" required aria-required="true" autocomplete="postal-code" <?php echo isset( $errors['postcode'] ) ? 'aria-invalid="true" aria-describedby="jmrs-err-postcode"' : ''; ?> />
				<?php $field_error( $errors, 'postcode' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_home_phone"><?php echo esc_html__( 'Phone', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_home_phone" id="jmrs_home_phone" value="<?php echo esc_attr( $val( $data, 'phone' ) ); ?>" autocomplete="tel" />
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_home_manager_user_id"><?php echo esc_html__( 'Manager', 'jm-referral-system' ); ?></label>
				<select name="jmrs_home_manager_user_id" id="jmrs_home_manager_user_id" <?php echo isset( $errors['manager_user_id'] ) ? 'aria-invalid="true" aria-describedby="jmrs-err-manager_user_id"' : ''; ?>>
					<option value="0"><?php echo esc_html__( '— None —', 'jm-referral-system' ); ?></option>
					<?php foreach ( $manager_options as $user_row ) : ?>
						<?php
						$uid   = absint( $user_row['id'] ?? 0 );
						$uname = (string) ( $user_row['display_name'] ?? '' );
						?>
						<option value="<?php echo esc_attr( (string) $uid ); ?>" <?php selected( absint( $val( $data, 'manager_user_id' ) ), $uid ); ?>><?php echo esc_html( $uname ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'manager_user_id' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_home_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
				<select name="jmrs_home_status" id="jmrs_home_status" required>
					<?php foreach ( $status_labels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $val( $data, 'status' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'status' ); ?>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_home_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_home_notes" id="jmrs_home_notes" rows="4"><?php echo esc_textarea( $val( $data, 'notes' ) ); ?></textarea>
			</div>
		</div>
	</section>

	<p class="jmrs-portal-actions">
		<button type="submit" class="jmrs-button jmrs-button--primary">
			<?php echo esc_html( $is_create ? __( 'Create Home', 'jm-referral-system' ) : __( 'Save Home', 'jm-referral-system' ) ); ?>
		</button>
		<?php if ( '' !== $cancel_url ) : ?>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>"><?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?></a>
		<?php endif; ?>
	</p>
</form>
