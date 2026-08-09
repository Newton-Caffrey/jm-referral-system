<?php
/**
 * Portal care visit create/edit form.
 *
 * Field names match admin so CareVisitController::attempt_save() can be reused.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data                  = is_array( $data ?? null ) ? $data : array();
$errors                = is_array( $errors ?? null ) ? $errors : array();
$assignable_users      = is_array( $assignable_users ?? null ) ? $assignable_users : array();
$status_labels         = is_array( $status_labels ?? null ) ? $status_labels : array();
$schedule_source_label = (string) ( $schedule_source_label ?? '' );
$form_action           = (string) ( $form_action ?? '' );
$cancel_url            = (string) ( $cancel_url ?? '' );
$is_create             = ! empty( $is_create );
$referral              = is_array( $referral ?? null ) ? $referral : array();
$referral_id           = absint( $referral['id'] ?? 0 );
$visit_id              = absint( $visit_id ?? 0 );

$val = static function ( array $data, string $key ): string {
	return (string) ( $data[ $key ] ?? '' );
};

$field_error = static function ( array $errors, string $key ): void {
	if ( ! isset( $errors[ $key ] ) ) {
		return;
	}
	echo '<p class="jmrs-portal-field-error">' . esc_html( (string) $errors[ $key ] ) . '</p>';
};

if ( isset( $service_location_panel ) && is_array( $service_location_panel ) ) {
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/service-location.php';
}
?>
<?php if ( '' !== $schedule_source_label ) : ?>
	<p class="jmrs-portal-muted">
		<strong><?php echo esc_html__( 'Source', 'jm-referral-system' ); ?>:</strong>
		<?php echo esc_html( $schedule_source_label ); ?>
	</p>
<?php endif; ?>

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
	<?php wp_nonce_field( 'jmrs_save_care_visit_' . $referral_id, 'jmrs_care_visit_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
	<input type="hidden" name="jmrs_visit_id" value="<?php echo esc_attr( (string) $visit_id ); ?>" />
	<input type="hidden" name="jmrs_visit_care_plan_id" value="<?php echo esc_attr( $val( $data, 'care_plan_id' ) ); ?>" />

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Visit Details', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_visit_date"><?php echo esc_html__( 'Visit Date', 'jm-referral-system' ); ?></label>
				<input type="date" name="jmrs_visit_date" id="jmrs_visit_date" value="<?php echo esc_attr( $val( $data, 'visit_date' ) ); ?>" required />
				<?php $field_error( $errors, 'visit_date' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_visit_start_time"><?php echo esc_html__( 'Start Time', 'jm-referral-system' ); ?></label>
				<input type="time" name="jmrs_visit_start_time" id="jmrs_visit_start_time" value="<?php echo esc_attr( $val( $data, 'start_time' ) ); ?>" required />
				<?php $field_error( $errors, 'start_time' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_visit_end_time"><?php echo esc_html__( 'End Time', 'jm-referral-system' ); ?></label>
				<input type="time" name="jmrs_visit_end_time" id="jmrs_visit_end_time" value="<?php echo esc_attr( $val( $data, 'end_time' ) ); ?>" required />
				<?php $field_error( $errors, 'end_time' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_visit_assigned_user_id"><?php echo esc_html__( 'Assigned Staff', 'jm-referral-system' ); ?></label>
				<select name="jmrs_visit_assigned_user_id" id="jmrs_visit_assigned_user_id">
					<option value="0"><?php echo esc_html__( '— Unassigned —', 'jm-referral-system' ); ?></option>
					<?php foreach ( $assignable_users as $user_row ) : ?>
						<option value="<?php echo esc_attr( (string) ( $user_row['id'] ?? 0 ) ); ?>" <?php selected( $val( $data, 'assigned_user_id' ), (string) ( $user_row['id'] ?? 0 ) ); ?>>
							<?php echo esc_html( (string) ( $user_row['display_name'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'assigned_user_id' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_visit_type"><?php echo esc_html__( 'Visit Type', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_visit_type" id="jmrs_visit_type" value="<?php echo esc_attr( $val( $data, 'visit_type' ) ); ?>" />
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_visit_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
				<select name="jmrs_visit_status" id="jmrs_visit_status">
					<?php foreach ( $status_labels as $status_value => $status_label ) : ?>
						<option value="<?php echo esc_attr( (string) $status_value ); ?>" <?php selected( $val( $data, 'visit_status' ), (string) $status_value ); ?>>
							<?php echo esc_html( (string) $status_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_visit_tasks"><?php echo esc_html__( 'Tasks', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_visit_tasks" id="jmrs_visit_tasks" rows="4"><?php echo esc_textarea( $val( $data, 'tasks' ) ); ?></textarea>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_visit_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_visit_notes" id="jmrs_visit_notes" rows="4"><?php echo esc_textarea( $val( $data, 'notes' ) ); ?></textarea>
			</div>
		</div>
	</section>

	<p class="jmrs-portal-actions">
		<button type="submit" name="jmrs_save_care_visit" value="1" class="jmrs-button jmrs-button--primary">
			<?php echo esc_html( $is_create ? __( 'Schedule Visit', 'jm-referral-system' ) : __( 'Update Visit', 'jm-referral-system' ) ); ?>
		</button>
		<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
			<?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?>
		</a>
	</p>
</form>
