<?php
/**
 * Portal visit execution form (arrival/departure, outcome, tasks, MAR).
 *
 * Field names match admin so CareVisitController::attempt_execute() can be reused.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data                     = is_array( $data ?? null ) ? $data : array();
$errors                   = is_array( $errors ?? null ) ? $errors : array();
$outcome_labels           = is_array( $outcome_labels ?? null ) ? $outcome_labels : array();
$visit_tasks              = is_array( $visit_tasks ?? null ) ? $visit_tasks : array();
$task_status_labels       = is_array( $task_status_labels ?? null ) ? $task_status_labels : array();
$can_show_mar             = ! empty( $can_show_mar );
$active_medications       = is_array( $active_medications ?? null ) ? $active_medications : array();
$posted_medications       = is_array( $posted_medications ?? null ) ? $posted_medications : array();
$medication_admin_by_id   = is_array( $medication_admin_by_id ?? null ) ? $medication_admin_by_id : array();
$medication_status_labels = is_array( $medication_status_labels ?? null ) ? $medication_status_labels : array();
$medication_reason_labels = is_array( $medication_reason_labels ?? null ) ? $medication_reason_labels : array();
$witness_users            = is_array( $witness_users ?? null ) ? $witness_users : array();
$form_action              = (string) ( $form_action ?? '' );
$cancel_url               = (string) ( $cancel_url ?? '' );
$referral                 = is_array( $referral ?? null ) ? $referral : array();
$visit                    = is_array( $visit ?? null ) ? $visit : array();
$referral_id              = absint( $referral['id'] ?? 0 );
$visit_id                 = absint( $visit['id'] ?? 0 );
$default_admin_time       = current_time( 'Y-m-d\TH:i' );

$val = static function ( array $data, string $key ): string {
	return (string) ( $data[ $key ] ?? '' );
};

$field_error = static function ( array $errors, string $key ): void {
	if ( ! isset( $errors[ $key ] ) ) {
		return;
	}
	echo '<p class="jmrs-portal-field-error">' . esc_html( (string) $errors[ $key ] ) . '</p>';
};

$to_local_input = static function ( string $value ): string {
	return '' !== $value ? str_replace( ' ', 'T', substr( $value, 0, 16 ) ) : '';
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

<form
	class="jmrs-portal-form"
	method="post"
	action="<?php echo esc_url( $form_action ); ?>"
	data-jmrs-confirm="<?php echo esc_attr__( 'Complete this visit? Recorded tasks and medication administrations will be saved.', 'jm-referral-system' ); ?>"
>
	<?php wp_nonce_field( 'jmrs_execute_care_visit_' . $visit_id, 'jmrs_execute_visit_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
	<input type="hidden" name="jmrs_visit_id" value="<?php echo esc_attr( (string) $visit_id ); ?>" />

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Visit Outcome', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_visit_arrival_time"><?php echo esc_html__( 'Arrival Time', 'jm-referral-system' ); ?></label>
				<input type="datetime-local" name="jmrs_visit_arrival_time" id="jmrs_visit_arrival_time" value="<?php echo esc_attr( $to_local_input( $val( $data, 'arrival_time' ) ) ); ?>" required />
				<?php $field_error( $errors, 'arrival_time' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_visit_departure_time"><?php echo esc_html__( 'Departure Time', 'jm-referral-system' ); ?></label>
				<input type="datetime-local" name="jmrs_visit_departure_time" id="jmrs_visit_departure_time" value="<?php echo esc_attr( $to_local_input( $val( $data, 'departure_time' ) ) ); ?>" required />
				<?php $field_error( $errors, 'departure_time' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_visit_outcome"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></label>
				<select name="jmrs_visit_outcome" id="jmrs_visit_outcome" required>
					<option value=""><?php echo esc_html__( '— Select —', 'jm-referral-system' ); ?></option>
					<?php foreach ( $outcome_labels as $outcome_value => $outcome_text ) : ?>
						<option value="<?php echo esc_attr( (string) $outcome_value ); ?>" <?php selected( $val( $data, 'visit_outcome' ), (string) $outcome_value ); ?>>
							<?php echo esc_html( (string) $outcome_text ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'visit_outcome' ); ?>
			</div>
		</div>
	</section>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Tasks', 'jm-referral-system' ); ?></h2>
		<?php if ( empty( $visit_tasks ) ) : ?>
			<div class="jmrs-portal-empty"><p><?php echo esc_html__( 'No care-plan tasks were generated for this visit.', 'jm-referral-system' ); ?></p></div>
		<?php else : ?>
			<div class="jmrs-portal-table-wrap">
				<table class="jmrs-portal-clinical-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Task Name', 'jm-referral-system' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
							<th><?php echo esc_html__( 'Task Notes', 'jm-referral-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $visit_tasks as $task_row ) : ?>
							<?php
							$task_id     = absint( $task_row['id'] ?? 0 );
							$task_name   = (string) ( $task_row['task_name'] ?? '' );
							$task_status = (string) ( $task_row['task_status'] ?? 'pending' );
							$task_notes  = (string) ( $task_row['task_notes'] ?? '' );
							?>
							<tr>
								<td data-label="<?php echo esc_attr__( 'Task Name', 'jm-referral-system' ); ?>"><?php echo esc_html( $task_name ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>">
									<select name="jmrs_visit_tasks[<?php echo esc_attr( (string) $task_id ); ?>][task_status]">
										<?php foreach ( $task_status_labels as $status_value => $status_text ) : ?>
											<option value="<?php echo esc_attr( (string) $status_value ); ?>" <?php selected( $task_status, (string) $status_value ); ?>>
												<?php echo esc_html( (string) $status_text ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td data-label="<?php echo esc_attr__( 'Task Notes', 'jm-referral-system' ); ?>">
									<textarea name="jmrs_visit_tasks[<?php echo esc_attr( (string) $task_id ); ?>][task_notes]" rows="2"><?php echo esc_textarea( $task_notes ); ?></textarea>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>

	<?php if ( $can_show_mar && ! empty( $active_medications ) ) : ?>
		<section class="jmrs-portal-section">
			<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Medication Administration', 'jm-referral-system' ); ?></h2>
			<div class="jmrs-portal-table-wrap">
				<table class="jmrs-portal-clinical-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Medication', 'jm-referral-system' ); ?></th>
							<th><?php echo esc_html__( 'Scheduled Time', 'jm-referral-system' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
							<th><?php echo esc_html__( 'Dose Given', 'jm-referral-system' ); ?></th>
							<th><?php echo esc_html__( 'Reason Code', 'jm-referral-system' ); ?></th>
							<th><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></th>
							<th><?php echo esc_html__( 'Witness', 'jm-referral-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $active_medications as $med_row ) : ?>
							<?php
							$med_id      = absint( $med_row['id'] ?? 0 );
							$posted      = is_array( $posted_medications[ $med_id ] ?? null ) ? $posted_medications[ $med_id ] : array();
							$existing    = is_array( $medication_admin_by_id[ $med_id ] ?? null ) ? $medication_admin_by_id[ $med_id ] : array();
							$status_val  = (string) ( $posted['administration_status'] ?? $existing['administration_status'] ?? '' );
							$dose_val    = (string) ( $posted['dose_given'] ?? $existing['dose_given'] ?? '' );
							$reason_val  = (string) ( $posted['reason_code'] ?? $existing['reason_code'] ?? '' );
							$notes_val   = (string) ( $posted['notes'] ?? $existing['notes'] ?? '' );
							$witness_val = (string) ( $posted['witness_user_id'] ?? $existing['witness_user_id'] ?? '' );
							$sched_raw   = (string) ( $posted['scheduled_time'] ?? $existing['scheduled_time'] ?? '' );
							$admin_raw   = (string) ( $posted['administered_time'] ?? $existing['administered_time'] ?? '' );
							$sched_input = $to_local_input( $sched_raw );
							$admin_input = '' !== $admin_raw ? $to_local_input( $admin_raw ) : $default_admin_time;
							?>
							<tr>
								<td data-label="<?php echo esc_attr__( 'Medication', 'jm-referral-system' ); ?>">
									<strong><?php echo esc_html( (string) ( $med_row['medication_name'] ?? '' ) ); ?></strong><br />
									<span class="jmrs-portal-muted">
										<?php
										echo esc_html(
											trim(
												(string) ( $med_row['strength'] ?? '' ) . ' / ' .
												(string) ( $med_row['dosage'] ?? '' ),
												' /'
											)
										);
										?>
									</span>
									<input type="hidden" name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][administered_time]" value="<?php echo esc_attr( $admin_input ); ?>" />
								</td>
								<td data-label="<?php echo esc_attr__( 'Scheduled Time', 'jm-referral-system' ); ?>">
									<input type="datetime-local" name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][scheduled_time]" value="<?php echo esc_attr( $sched_input ); ?>" />
								</td>
								<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>">
									<select name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][administration_status]">
										<option value=""><?php echo esc_html__( 'Select', 'jm-referral-system' ); ?></option>
										<?php foreach ( $medication_status_labels as $value => $label ) : ?>
											<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $status_val, (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td data-label="<?php echo esc_attr__( 'Dose Given', 'jm-referral-system' ); ?>">
									<input type="text" name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][dose_given]" value="<?php echo esc_attr( $dose_val ); ?>" />
								</td>
								<td data-label="<?php echo esc_attr__( 'Reason Code', 'jm-referral-system' ); ?>">
									<select name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][reason_code]">
										<option value=""><?php echo esc_html__( 'None', 'jm-referral-system' ); ?></option>
										<?php foreach ( $medication_reason_labels as $value => $label ) : ?>
											<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $reason_val, (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td data-label="<?php echo esc_attr__( 'Notes', 'jm-referral-system' ); ?>">
									<textarea rows="2" name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][notes]"><?php echo esc_textarea( $notes_val ); ?></textarea>
								</td>
								<td data-label="<?php echo esc_attr__( 'Witness', 'jm-referral-system' ); ?>">
									<select name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][witness_user_id]">
										<option value=""><?php echo esc_html__( 'None', 'jm-referral-system' ); ?></option>
										<?php foreach ( $witness_users as $user_row ) : ?>
											<?php
											$uid   = absint( $user_row['id'] ?? 0 );
											$uname = (string) ( $user_row['display_name'] ?? '' );
											?>
											<option value="<?php echo esc_attr( (string) $uid ); ?>" <?php selected( $witness_val, (string) $uid ); ?>><?php echo esc_html( $uname ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php endif; ?>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Visit Notes', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_visit_client_response"><?php echo esc_html__( 'Client Response', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_visit_client_response" id="jmrs_visit_client_response" rows="3"><?php echo esc_textarea( $val( $data, 'client_response' ) ); ?></textarea>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_visit_wellbeing_observations"><?php echo esc_html__( 'Wellbeing Observations', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_visit_wellbeing_observations" id="jmrs_visit_wellbeing_observations" rows="3"><?php echo esc_textarea( $val( $data, 'wellbeing_observations' ) ); ?></textarea>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_visit_incident_report"><?php echo esc_html__( 'Incident Report', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_visit_incident_report" id="jmrs_visit_incident_report" rows="3"><?php echo esc_textarea( $val( $data, 'incident_report' ) ); ?></textarea>
			</div>
		</div>
	</section>

	<p class="jmrs-portal-actions">
		<button type="submit" name="jmrs_execute_care_visit" value="1" class="jmrs-button jmrs-button--primary">
			<?php echo esc_html__( 'Complete Visit', 'jm-referral-system' ); ?>
		</button>
		<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
			<?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?>
		</a>
	</p>
</form>
