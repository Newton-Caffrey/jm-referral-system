<?php
/**
 * Portal schedule create/edit form.
 *
 * Field names match admin so ScheduleController::attempt_save() can be reused.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data           = is_array( $data ?? null ) ? $data : array();
$errors         = is_array( $errors ?? null ) ? $errors : array();
$repeat_labels  = is_array( $repeat_labels ?? null ) ? $repeat_labels : array();
$status_labels  = is_array( $status_labels ?? null ) ? $status_labels : array();
$weekday_labels = is_array( $weekday_labels ?? null ) ? $weekday_labels : array();
$team_options   = is_array( $team_options ?? null ) ? $team_options : array();
$selected_days  = is_array( $data['days_of_week'] ?? null ) ? $data['days_of_week'] : array();
$form_action    = (string) ( $form_action ?? '' );
$cancel_url     = (string) ( $cancel_url ?? '' );
$is_create      = ! empty( $is_create );
$referral       = is_array( $referral ?? null ) ? $referral : array();
$referral_id    = absint( $referral['id'] ?? 0 );
$schedule_id    = absint( $schedule_id ?? 0 );

$val = static function ( array $data, string $key ): string {
	$value = $data[ $key ] ?? '';
	return is_array( $value ) ? '' : (string) $value;
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
	<?php wp_nonce_field( 'jmrs_save_schedule_' . $referral_id, 'jmrs_schedule_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
	<input type="hidden" name="jmrs_schedule_id" value="<?php echo esc_attr( (string) $schedule_id ); ?>" />
	<input type="hidden" name="jmrs_schedule_care_plan_id" value="<?php echo esc_attr( $val( $data, 'care_plan_id' ) ); ?>" />

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Schedule Details', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_schedule_name"><?php echo esc_html__( 'Schedule Name', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_schedule_name" id="jmrs_schedule_name" value="<?php echo esc_attr( $val( $data, 'schedule_name' ) ); ?>" required />
				<?php $field_error( $errors, 'schedule_name' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_schedule_start_date"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></label>
				<input type="date" name="jmrs_schedule_start_date" id="jmrs_schedule_start_date" value="<?php echo esc_attr( $val( $data, 'start_date' ) ); ?>" required />
				<?php $field_error( $errors, 'start_date' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_schedule_end_date"><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></label>
				<input type="date" name="jmrs_schedule_end_date" id="jmrs_schedule_end_date" value="<?php echo esc_attr( $val( $data, 'end_date' ) ); ?>" />
				<?php $field_error( $errors, 'end_date' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_schedule_repeat_type"><?php echo esc_html__( 'Repeat Type', 'jm-referral-system' ); ?></label>
				<select name="jmrs_schedule_repeat_type" id="jmrs_schedule_repeat_type">
					<?php foreach ( $repeat_labels as $repeat_value => $repeat_label ) : ?>
						<option value="<?php echo esc_attr( (string) $repeat_value ); ?>" <?php selected( $val( $data, 'repeat_type' ), (string) $repeat_value ); ?>>
							<?php echo esc_html( (string) $repeat_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'repeat_type' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_schedule_repeat_interval"><?php echo esc_html__( 'Repeat Interval', 'jm-referral-system' ); ?></label>
				<input type="number" min="1" step="1" name="jmrs_schedule_repeat_interval" id="jmrs_schedule_repeat_interval" value="<?php echo esc_attr( $val( $data, 'repeat_interval' ) ); ?>" required />
				<p class="jmrs-portal-field__hint"><?php echo esc_html__( 'Minimum 1. For weekly schedules, 1 means every week.', 'jm-referral-system' ); ?></p>
				<?php $field_error( $errors, 'repeat_interval' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_schedule_start_time"><?php echo esc_html__( 'Start Time', 'jm-referral-system' ); ?></label>
				<input type="time" name="jmrs_schedule_start_time" id="jmrs_schedule_start_time" value="<?php echo esc_attr( $val( $data, 'start_time' ) ); ?>" required />
				<?php $field_error( $errors, 'start_time' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_schedule_end_time"><?php echo esc_html__( 'End Time', 'jm-referral-system' ); ?></label>
				<input type="time" name="jmrs_schedule_end_time" id="jmrs_schedule_end_time" value="<?php echo esc_attr( $val( $data, 'end_time' ) ); ?>" required />
				<?php $field_error( $errors, 'end_time' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_schedule_visit_type"><?php echo esc_html__( 'Visit Type', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_schedule_visit_type" id="jmrs_schedule_visit_type" value="<?php echo esc_attr( $val( $data, 'visit_type' ) ); ?>" />
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_schedule_team_assignment_id"><?php echo esc_html__( 'Assigned Care Team Member', 'jm-referral-system' ); ?></label>
				<select name="jmrs_schedule_team_assignment_id" id="jmrs_schedule_team_assignment_id">
					<option value="0"><?php echo esc_html__( '— Unassigned —', 'jm-referral-system' ); ?></option>
					<?php foreach ( $team_options as $team_row ) : ?>
						<option value="<?php echo esc_attr( (string) ( $team_row['id'] ?? 0 ) ); ?>" <?php selected( $val( $data, 'team_assignment_id' ), (string) ( $team_row['id'] ?? 0 ) ); ?>>
							<?php echo esc_html( (string) ( $team_row['label'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( empty( $team_options ) ) : ?>
					<p class="jmrs-portal-field__hint"><?php echo esc_html__( 'Add active care team members before assigning a schedule owner.', 'jm-referral-system' ); ?></p>
				<?php endif; ?>
				<?php $field_error( $errors, 'team_assignment_id' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_schedule_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
				<select name="jmrs_schedule_status" id="jmrs_schedule_status">
					<?php foreach ( $status_labels as $status_value => $status_label ) : ?>
						<option value="<?php echo esc_attr( (string) $status_value ); ?>" <?php selected( $val( $data, 'status' ), (string) $status_value ); ?>>
							<?php echo esc_html( (string) $status_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<span class="jmrs-portal-field__label"><?php echo esc_html__( 'Days of Week', 'jm-referral-system' ); ?></span>
				<div class="jmrs-portal-checkbox-group">
					<?php foreach ( $weekday_labels as $day_value => $day_label ) : ?>
						<label>
							<input
								type="checkbox"
								name="jmrs_schedule_days_of_week[]"
								value="<?php echo esc_attr( (string) $day_value ); ?>"
								<?php checked( in_array( (string) $day_value, $selected_days, true ) ); ?>
							/>
							<?php echo esc_html( (string) $day_label ); ?>
						</label>
					<?php endforeach; ?>
				</div>
				<?php $field_error( $errors, 'days_of_week' ); ?>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_schedule_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_schedule_notes" id="jmrs_schedule_notes" rows="3"><?php echo esc_textarea( $val( $data, 'notes' ) ); ?></textarea>
			</div>
		</div>
	</section>

	<p class="jmrs-portal-actions">
		<button type="submit" name="jmrs_save_schedule" value="1" class="jmrs-button jmrs-button--primary">
			<?php echo esc_html( $is_create ? __( 'Add Schedule', 'jm-referral-system' ) : __( 'Update Schedule', 'jm-referral-system' ) ); ?>
		</button>
		<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
			<?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?>
		</a>
	</p>
</form>
