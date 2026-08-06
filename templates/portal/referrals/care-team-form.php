<?php
/**
 * Portal care team assignment create/edit form.
 *
 * Field names match admin so CareTeamController::attempt_save() can be reused.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data             = is_array( $data ?? null ) ? $data : array();
$errors           = is_array( $errors ?? null ) ? $errors : array();
$assignable_users = is_array( $assignable_users ?? null ) ? $assignable_users : array();
$role_labels      = is_array( $role_labels ?? null ) ? $role_labels : array();
$status_labels    = is_array( $status_labels ?? null ) ? $status_labels : array();
$form_action      = (string) ( $form_action ?? '' );
$cancel_url       = (string) ( $cancel_url ?? '' );
$is_create        = ! empty( $is_create );
$referral         = is_array( $referral ?? null ) ? $referral : array();
$referral_id      = absint( $referral['id'] ?? 0 );
$assignment_id    = absint( $assignment_id ?? 0 );

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
	<?php wp_nonce_field( 'jmrs_save_care_team_' . $referral_id, 'jmrs_care_team_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
	<input type="hidden" name="jmrs_care_team_id" value="<?php echo esc_attr( (string) $assignment_id ); ?>" />
	<input type="hidden" name="jmrs_care_team_care_plan_id" value="<?php echo esc_attr( $val( $data, 'care_plan_id' ) ); ?>" />

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Care Team Assignment', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_care_team_user_id"><?php echo esc_html__( 'Staff Member', 'jm-referral-system' ); ?></label>
				<select name="jmrs_care_team_user_id" id="jmrs_care_team_user_id" required>
					<option value=""><?php echo esc_html__( 'Select staff member', 'jm-referral-system' ); ?></option>
					<?php foreach ( $assignable_users as $user_row ) : ?>
						<option value="<?php echo esc_attr( (string) ( $user_row['id'] ?? 0 ) ); ?>" <?php selected( $val( $data, 'user_id' ), (string) ( $user_row['id'] ?? 0 ) ); ?>>
							<?php echo esc_html( (string) ( $user_row['display_name'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'user_id' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_care_team_role"><?php echo esc_html__( 'Team Role', 'jm-referral-system' ); ?></label>
				<select name="jmrs_care_team_role" id="jmrs_care_team_role" required>
					<option value=""><?php echo esc_html__( 'Select role', 'jm-referral-system' ); ?></option>
					<?php foreach ( $role_labels as $role_value => $role_label ) : ?>
						<option value="<?php echo esc_attr( (string) $role_value ); ?>" <?php selected( $val( $data, 'team_role' ), (string) $role_value ); ?>>
							<?php echo esc_html( (string) $role_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'team_role' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_care_team_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
				<select name="jmrs_care_team_status" id="jmrs_care_team_status">
					<?php foreach ( $status_labels as $status_value => $status_label ) : ?>
						<option value="<?php echo esc_attr( (string) $status_value ); ?>" <?php selected( $val( $data, 'assignment_status' ), (string) $status_value ); ?>>
							<?php echo esc_html( (string) $status_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--checkbox">
				<label for="jmrs_care_team_is_primary">
					<input
						type="checkbox"
						name="jmrs_care_team_is_primary"
						id="jmrs_care_team_is_primary"
						value="1"
						<?php checked( $val( $data, 'is_primary' ), '1' ); ?>
					/>
					<?php echo esc_html__( 'Primary Carer for this referral', 'jm-referral-system' ); ?>
				</label>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_care_team_start_date"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></label>
				<input type="date" name="jmrs_care_team_start_date" id="jmrs_care_team_start_date" value="<?php echo esc_attr( $val( $data, 'start_date' ) ); ?>" required />
				<?php $field_error( $errors, 'start_date' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_care_team_end_date"><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></label>
				<input type="date" name="jmrs_care_team_end_date" id="jmrs_care_team_end_date" value="<?php echo esc_attr( $val( $data, 'end_date' ) ); ?>" />
				<?php $field_error( $errors, 'end_date' ); ?>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_care_team_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_care_team_notes" id="jmrs_care_team_notes" rows="4"><?php echo esc_textarea( $val( $data, 'notes' ) ); ?></textarea>
			</div>
		</div>
	</section>

	<p class="jmrs-portal-actions">
		<button type="submit" name="jmrs_save_care_team" value="1" class="jmrs-button jmrs-button--primary">
			<?php echo esc_html( $is_create ? __( 'Assign Team Member', 'jm-referral-system' ) : __( 'Update Assignment', 'jm-referral-system' ) ); ?>
		</button>
		<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
			<?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?>
		</a>
	</p>
</form>
