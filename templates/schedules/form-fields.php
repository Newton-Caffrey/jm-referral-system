<?php
/**
 * Shared schedule form fields.
 *
 * Expects: $jmrs_schedule_value callable, $repeat_labels, $status_labels,
 * $weekday_labels, $team_options, $selected_days, $schedule_errors (optional).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$repeat_labels   = is_array( $repeat_labels ?? null ) ? $repeat_labels : array();
$status_labels   = is_array( $status_labels ?? null ) ? $status_labels : array();
$weekday_labels  = is_array( $weekday_labels ?? null ) ? $weekday_labels : array();
$team_options    = is_array( $team_options ?? null ) ? $team_options : array();
$selected_days   = is_array( $selected_days ?? null ) ? $selected_days : array();
$schedule_errors = is_array( $schedule_errors ?? null ) ? $schedule_errors : array();
?>
<table class="form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row">
				<label for="jmrs_schedule_name"><?php echo esc_html__( 'Schedule Name', 'jm-referral-system' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					class="regular-text"
					name="jmrs_schedule_name"
					id="jmrs_schedule_name"
					value="<?php echo esc_attr( $jmrs_schedule_value( 'schedule_name' ) ); ?>"
					required
				/>
				<?php if ( isset( $schedule_errors['schedule_name'] ) ) : ?>
					<p class="description"><?php echo esc_html( $schedule_errors['schedule_name'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jmrs_schedule_start_date"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></label>
			</th>
			<td>
				<input
					type="date"
					class="regular-text"
					name="jmrs_schedule_start_date"
					id="jmrs_schedule_start_date"
					value="<?php echo esc_attr( $jmrs_schedule_value( 'start_date' ) ); ?>"
					required
				/>
				<?php if ( isset( $schedule_errors['start_date'] ) ) : ?>
					<p class="description"><?php echo esc_html( $schedule_errors['start_date'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jmrs_schedule_end_date"><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></label>
			</th>
			<td>
				<input
					type="date"
					class="regular-text"
					name="jmrs_schedule_end_date"
					id="jmrs_schedule_end_date"
					value="<?php echo esc_attr( $jmrs_schedule_value( 'end_date' ) ); ?>"
				/>
				<?php if ( isset( $schedule_errors['end_date'] ) ) : ?>
					<p class="description"><?php echo esc_html( $schedule_errors['end_date'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jmrs_schedule_repeat_type"><?php echo esc_html__( 'Repeat Type', 'jm-referral-system' ); ?></label>
			</th>
			<td>
				<select name="jmrs_schedule_repeat_type" id="jmrs_schedule_repeat_type">
					<?php foreach ( $repeat_labels as $repeat_value => $repeat_label ) : ?>
						<option value="<?php echo esc_attr( (string) $repeat_value ); ?>" <?php selected( $jmrs_schedule_value( 'repeat_type' ), (string) $repeat_value ); ?>>
							<?php echo esc_html( (string) $repeat_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( isset( $schedule_errors['repeat_type'] ) ) : ?>
					<p class="description"><?php echo esc_html( $schedule_errors['repeat_type'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jmrs_schedule_repeat_interval"><?php echo esc_html__( 'Repeat Interval', 'jm-referral-system' ); ?></label>
			</th>
			<td>
				<input
					type="number"
					class="small-text"
					name="jmrs_schedule_repeat_interval"
					id="jmrs_schedule_repeat_interval"
					min="1"
					step="1"
					value="<?php echo esc_attr( $jmrs_schedule_value( 'repeat_interval' ) ); ?>"
					required
				/>
				<p class="description"><?php echo esc_html__( 'Minimum 1. For weekly schedules, 1 means every week.', 'jm-referral-system' ); ?></p>
				<?php if ( isset( $schedule_errors['repeat_interval'] ) ) : ?>
					<p class="description"><?php echo esc_html( $schedule_errors['repeat_interval'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php echo esc_html__( 'Days of Week', 'jm-referral-system' ); ?></th>
			<td>
				<?php foreach ( $weekday_labels as $day_value => $day_label ) : ?>
					<label style="display:inline-block; margin-right:12px;">
						<input
							type="checkbox"
							name="jmrs_schedule_days_of_week[]"
							value="<?php echo esc_attr( (string) $day_value ); ?>"
							<?php checked( in_array( (string) $day_value, $selected_days, true ) ); ?>
						/>
						<?php echo esc_html( (string) $day_label ); ?>
					</label>
				<?php endforeach; ?>
				<?php if ( isset( $schedule_errors['days_of_week'] ) ) : ?>
					<p class="description"><?php echo esc_html( $schedule_errors['days_of_week'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jmrs_schedule_start_time"><?php echo esc_html__( 'Start Time', 'jm-referral-system' ); ?></label>
			</th>
			<td>
				<input
					type="time"
					class="regular-text"
					name="jmrs_schedule_start_time"
					id="jmrs_schedule_start_time"
					value="<?php echo esc_attr( $jmrs_schedule_value( 'start_time' ) ); ?>"
					required
				/>
				<?php if ( isset( $schedule_errors['start_time'] ) ) : ?>
					<p class="description"><?php echo esc_html( $schedule_errors['start_time'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jmrs_schedule_end_time"><?php echo esc_html__( 'End Time', 'jm-referral-system' ); ?></label>
			</th>
			<td>
				<input
					type="time"
					class="regular-text"
					name="jmrs_schedule_end_time"
					id="jmrs_schedule_end_time"
					value="<?php echo esc_attr( $jmrs_schedule_value( 'end_time' ) ); ?>"
					required
				/>
				<?php if ( isset( $schedule_errors['end_time'] ) ) : ?>
					<p class="description"><?php echo esc_html( $schedule_errors['end_time'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jmrs_schedule_visit_type"><?php echo esc_html__( 'Visit Type', 'jm-referral-system' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					class="regular-text"
					name="jmrs_schedule_visit_type"
					id="jmrs_schedule_visit_type"
					value="<?php echo esc_attr( $jmrs_schedule_value( 'visit_type' ) ); ?>"
				/>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jmrs_schedule_team_assignment_id"><?php echo esc_html__( 'Assigned Care Team Member', 'jm-referral-system' ); ?></label>
			</th>
			<td>
				<select name="jmrs_schedule_team_assignment_id" id="jmrs_schedule_team_assignment_id">
					<option value="0"><?php echo esc_html__( '— Unassigned —', 'jm-referral-system' ); ?></option>
					<?php foreach ( $team_options as $team_row ) : ?>
						<option value="<?php echo esc_attr( (string) ( $team_row['id'] ?? 0 ) ); ?>" <?php selected( $jmrs_schedule_value( 'team_assignment_id' ), (string) ( $team_row['id'] ?? 0 ) ); ?>>
							<?php echo esc_html( (string) ( $team_row['label'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( empty( $team_options ) ) : ?>
					<p class="description"><?php echo esc_html__( 'Add active care team members before assigning a schedule owner.', 'jm-referral-system' ); ?></p>
				<?php endif; ?>
				<?php if ( isset( $schedule_errors['team_assignment_id'] ) ) : ?>
					<p class="description"><?php echo esc_html( $schedule_errors['team_assignment_id'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jmrs_schedule_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
			</th>
			<td>
				<select name="jmrs_schedule_status" id="jmrs_schedule_status">
					<?php foreach ( $status_labels as $status_value => $status_label ) : ?>
						<option value="<?php echo esc_attr( (string) $status_value ); ?>" <?php selected( $jmrs_schedule_value( 'status' ), (string) $status_value ); ?>>
							<?php echo esc_html( (string) $status_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( isset( $schedule_errors['status'] ) ) : ?>
					<p class="description"><?php echo esc_html( $schedule_errors['status'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="jmrs_schedule_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
			</th>
			<td>
				<textarea name="jmrs_schedule_notes" id="jmrs_schedule_notes" class="large-text" rows="3"><?php echo esc_textarea( $jmrs_schedule_value( 'notes' ) ); ?></textarea>
			</td>
		</tr>
	</tbody>
</table>
