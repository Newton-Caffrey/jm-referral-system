<?php
/**
 * Portal medication create/edit form.
 *
 * Field names match admin so MedicationController::attempt_save() can be reused.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data           = is_array( $data ?? null ) ? $data : array();
$errors         = is_array( $errors ?? null ) ? $errors : array();
$status_labels  = is_array( $status_labels ?? null ) ? $status_labels : array();
$route_labels   = is_array( $route_labels ?? null ) ? $route_labels : array();
$form_action    = (string) ( $form_action ?? '' );
$cancel_url     = (string) ( $cancel_url ?? '' );
$is_create      = ! empty( $is_create );
$referral       = is_array( $referral ?? null ) ? $referral : array();
$referral_id    = absint( $referral['id'] ?? 0 );
$medication_id  = absint( $medication_id ?? 0 );

$val = static function ( array $data, string $key ): string {
	return (string) ( $data[ $key ] ?? '' );
};

$field_error = static function ( array $errors, string $key ): void {
	if ( ! isset( $errors[ $key ] ) ) {
		return;
	}
	echo '<p class="jmrs-portal-field-error">' . esc_html( (string) $errors[ $key ] ) . '</p>';
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
	<?php wp_nonce_field( 'jmrs_save_medication_' . $referral_id, 'jmrs_medication_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
	<input type="hidden" name="jmrs_medication_id" value="<?php echo esc_attr( (string) $medication_id ); ?>" />

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Medication Details', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_medication_name"><?php echo esc_html__( 'Medication Name', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_medication_name" id="jmrs_medication_name" value="<?php echo esc_attr( $val( $data, 'medication_name' ) ); ?>" required />
				<?php $field_error( $errors, 'medication_name' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_medication_strength"><?php echo esc_html__( 'Strength', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_medication_strength" id="jmrs_medication_strength" value="<?php echo esc_attr( $val( $data, 'strength' ) ); ?>" />
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_medication_dosage"><?php echo esc_html__( 'Dosage', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_medication_dosage" id="jmrs_medication_dosage" value="<?php echo esc_attr( $val( $data, 'dosage' ) ); ?>" required />
				<?php $field_error( $errors, 'dosage' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_medication_route"><?php echo esc_html__( 'Route', 'jm-referral-system' ); ?></label>
				<select name="jmrs_medication_route" id="jmrs_medication_route" required>
					<option value=""><?php echo esc_html__( 'Select route', 'jm-referral-system' ); ?></option>
					<?php foreach ( $route_labels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $val( $data, 'route' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'route' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_medication_frequency"><?php echo esc_html__( 'Frequency', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_medication_frequency" id="jmrs_medication_frequency" value="<?php echo esc_attr( $val( $data, 'frequency' ) ); ?>" />
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_medication_start_date"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></label>
				<input type="date" name="jmrs_medication_start_date" id="jmrs_medication_start_date" value="<?php echo esc_attr( $val( $data, 'start_date' ) ); ?>" />
				<?php $field_error( $errors, 'start_date' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_medication_end_date"><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></label>
				<input type="date" name="jmrs_medication_end_date" id="jmrs_medication_end_date" value="<?php echo esc_attr( $val( $data, 'end_date' ) ); ?>" />
				<?php $field_error( $errors, 'end_date' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_medication_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
				<select name="jmrs_medication_status" id="jmrs_medication_status" required>
					<?php foreach ( $status_labels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $val( $data, 'medication_status' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'medication_status' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_medication_prescribing_source"><?php echo esc_html__( 'Prescribing Source', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_medication_prescribing_source" id="jmrs_medication_prescribing_source" value="<?php echo esc_attr( $val( $data, 'prescribing_source' ) ); ?>" />
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_medication_instructions"><?php echo esc_html__( 'Instructions', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_medication_instructions" id="jmrs_medication_instructions" rows="3"><?php echo esc_textarea( $val( $data, 'instructions' ) ); ?></textarea>
			</div>
		</div>
	</section>

	<p class="jmrs-portal-actions">
		<button type="submit" name="jmrs_save_medication" value="1" class="jmrs-button jmrs-button--primary">
			<?php echo esc_html( $is_create ? __( 'Add Medication', 'jm-referral-system' ) : __( 'Update Medication', 'jm-referral-system' ) ); ?>
		</button>
		<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
			<?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?>
		</a>
	</p>
</form>
