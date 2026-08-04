<?php
/**
 * Edit medication template.
 *
 * @package JMReferral
 *
 * @var array<string, mixed> $medication
 * @var array<string, mixed> $referral
 * @var array<string, string> $medication_data
 * @var array<string, string> $errors
 * @var array<string, string> $status_labels
 * @var array<string, string> $route_labels
 * @var string $back_url
 * @var int $medication_id
 * @var int $referral_id
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$medication_id   = absint( $medication['id'] ?? 0 );
$referral_id     = absint( $referral['id'] ?? 0 );
$medication_data = is_array( $medication_data ?? null ) ? $medication_data : array();
$errors          = is_array( $errors ?? null ) ? $errors : array();
$status_labels   = is_array( $status_labels ?? null ) ? $status_labels : array();
$route_labels    = is_array( $route_labels ?? null ) ? $route_labels : array();
$back_url        = (string) ( $back_url ?? '' );

$jmrs_med_value = static function ( string $key ) use ( $medication_data ): string {
	return (string) ( $medication_data[ $key ] ?? '' );
};
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Edit Medication', 'jm-referral-system' ); ?></h1>
	<p>
		<a href="<?php echo esc_url( $back_url ); ?>"><?php echo esc_html__( '&larr; Back to referral', 'jm-referral-system' ); ?></a>
	</p>

	<form method="post" action="">
		<?php wp_nonce_field( 'jmrs_save_medication_' . $referral_id, 'jmrs_medication_nonce' ); ?>
		<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
		<input type="hidden" name="jmrs_medication_id" value="<?php echo esc_attr( (string) $medication_id ); ?>" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="jmrs_medication_name"><?php echo esc_html__( 'Medication Name', 'jm-referral-system' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" name="jmrs_medication_name" id="jmrs_medication_name" value="<?php echo esc_attr( $jmrs_med_value( 'medication_name' ) ); ?>" required />
					<?php if ( ! empty( $errors['medication_name'] ) ) : ?>
						<p class="description" style="color:#b32d2e;"><?php echo esc_html( $errors['medication_name'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jmrs_medication_strength"><?php echo esc_html__( 'Strength', 'jm-referral-system' ); ?></label></th>
				<td><input type="text" class="regular-text" name="jmrs_medication_strength" id="jmrs_medication_strength" value="<?php echo esc_attr( $jmrs_med_value( 'strength' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="jmrs_medication_dosage"><?php echo esc_html__( 'Dosage', 'jm-referral-system' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" name="jmrs_medication_dosage" id="jmrs_medication_dosage" value="<?php echo esc_attr( $jmrs_med_value( 'dosage' ) ); ?>" required />
					<?php if ( ! empty( $errors['dosage'] ) ) : ?>
						<p class="description" style="color:#b32d2e;"><?php echo esc_html( $errors['dosage'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jmrs_medication_route"><?php echo esc_html__( 'Route', 'jm-referral-system' ); ?></label></th>
				<td>
					<select name="jmrs_medication_route" id="jmrs_medication_route" required>
						<option value=""><?php echo esc_html__( 'Select route', 'jm-referral-system' ); ?></option>
						<?php foreach ( $route_labels as $value => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $jmrs_med_value( 'route' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( ! empty( $errors['route'] ) ) : ?>
						<p class="description" style="color:#b32d2e;"><?php echo esc_html( $errors['route'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jmrs_medication_frequency"><?php echo esc_html__( 'Frequency', 'jm-referral-system' ); ?></label></th>
				<td><input type="text" class="regular-text" name="jmrs_medication_frequency" id="jmrs_medication_frequency" value="<?php echo esc_attr( $jmrs_med_value( 'frequency' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="jmrs_medication_instructions"><?php echo esc_html__( 'Instructions', 'jm-referral-system' ); ?></label></th>
				<td><textarea class="large-text" rows="3" name="jmrs_medication_instructions" id="jmrs_medication_instructions"><?php echo esc_textarea( $jmrs_med_value( 'instructions' ) ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="jmrs_medication_start_date"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></label></th>
				<td>
					<input type="date" name="jmrs_medication_start_date" id="jmrs_medication_start_date" value="<?php echo esc_attr( $jmrs_med_value( 'start_date' ) ); ?>" />
					<?php if ( ! empty( $errors['start_date'] ) ) : ?>
						<p class="description" style="color:#b32d2e;"><?php echo esc_html( $errors['start_date'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jmrs_medication_end_date"><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></label></th>
				<td>
					<input type="date" name="jmrs_medication_end_date" id="jmrs_medication_end_date" value="<?php echo esc_attr( $jmrs_med_value( 'end_date' ) ); ?>" />
					<?php if ( ! empty( $errors['end_date'] ) ) : ?>
						<p class="description" style="color:#b32d2e;"><?php echo esc_html( $errors['end_date'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jmrs_medication_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label></th>
				<td>
					<select name="jmrs_medication_status" id="jmrs_medication_status" required>
						<?php foreach ( $status_labels as $value => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $jmrs_med_value( 'medication_status' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( ! empty( $errors['medication_status'] ) ) : ?>
						<p class="description" style="color:#b32d2e;"><?php echo esc_html( $errors['medication_status'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jmrs_medication_prescribing_source"><?php echo esc_html__( 'Prescribing Source', 'jm-referral-system' ); ?></label></th>
				<td><input type="text" class="regular-text" name="jmrs_medication_prescribing_source" id="jmrs_medication_prescribing_source" value="<?php echo esc_attr( $jmrs_med_value( 'prescribing_source' ) ); ?>" /></td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Medication', 'jm-referral-system' ), 'primary', 'jmrs_save_medication' ); ?>
	</form>
</div>
