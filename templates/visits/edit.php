<?php
/**
 * Edit care visit template.
 *
 * @var array<string, mixed>  $visit
 * @var array<string, mixed>  $referral
 * @var array<string, string> $visit_data
 * @var array<string, string> $errors
 * @var array<int, array{id: int, display_name: string}> $assignable_users
 * @var array<string, string> $status_labels
 * @var string                $back_url
 * @var int                   $visit_id
 * @var int                   $referral_id
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral_id     = absint( $referral['id'] ?? 0 );
$visit_id        = absint( $visit['id'] ?? 0 );
$referral_number = (string) ( $referral['referral_number'] ?? '' );
$client_name     = (string) ( $referral['client_name'] ?? '' );
$visit_data      = is_array( $visit_data ?? null ) ? $visit_data : array();
$errors          = is_array( $errors ?? null ) ? $errors : array();
$assignable_users = is_array( $assignable_users ?? null ) ? $assignable_users : array();
$status_labels   = is_array( $status_labels ?? null ) ? $status_labels : array();

$jmrs_visit_value = static function ( string $key ) use ( $visit_data ): string {
	return (string) ( $visit_data[ $key ] ?? '' );
};
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Edit Care Visit', 'jm-referral-system' ); ?></h1>

	<p>
		<a href="<?php echo esc_url( $back_url ); ?>">
			<?php echo esc_html__( '← Back to Referral', 'jm-referral-system' ); ?>
		</a>
	</p>

	<p>
		<strong><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?>:</strong>
		<?php
		echo esc_html(
			trim(
				$referral_number . ( '' !== $client_name ? ' — ' . $client_name : '' )
			)
		);
		?>
	</p>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="notice notice-error">
			<p><?php echo esc_html__( 'Please fix the following errors:', 'jm-referral-system' ); ?></p>
			<ul>
				<?php foreach ( $errors as $message ) : ?>
					<li><?php echo esc_html( $message ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'jmrs_save_care_visit_' . $referral_id, 'jmrs_care_visit_nonce' ); ?>
		<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
		<input type="hidden" name="jmrs_visit_id" value="<?php echo esc_attr( (string) $visit_id ); ?>" />
		<input type="hidden" name="jmrs_visit_care_plan_id" value="<?php echo esc_attr( $jmrs_visit_value( 'care_plan_id' ) ); ?>" />

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="jmrs_visit_date"><?php echo esc_html__( 'Visit Date', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="date"
							class="regular-text"
							name="jmrs_visit_date"
							id="jmrs_visit_date"
							value="<?php echo esc_attr( $jmrs_visit_value( 'visit_date' ) ); ?>"
							required
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_visit_start_time"><?php echo esc_html__( 'Start Time', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="time"
							class="regular-text"
							name="jmrs_visit_start_time"
							id="jmrs_visit_start_time"
							value="<?php echo esc_attr( $jmrs_visit_value( 'start_time' ) ); ?>"
							required
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_visit_end_time"><?php echo esc_html__( 'End Time', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="time"
							class="regular-text"
							name="jmrs_visit_end_time"
							id="jmrs_visit_end_time"
							value="<?php echo esc_attr( $jmrs_visit_value( 'end_time' ) ); ?>"
							required
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_visit_assigned_user_id"><?php echo esc_html__( 'Assigned Staff', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_visit_assigned_user_id" id="jmrs_visit_assigned_user_id">
							<option value="0"><?php echo esc_html__( '— Unassigned —', 'jm-referral-system' ); ?></option>
							<?php foreach ( $assignable_users as $user_row ) : ?>
								<option value="<?php echo esc_attr( (string) ( $user_row['id'] ?? 0 ) ); ?>" <?php selected( $jmrs_visit_value( 'assigned_user_id' ), (string) ( $user_row['id'] ?? 0 ) ); ?>>
									<?php echo esc_html( (string) ( $user_row['display_name'] ?? '' ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_visit_type"><?php echo esc_html__( 'Visit Type', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							class="regular-text"
							name="jmrs_visit_type"
							id="jmrs_visit_type"
							value="<?php echo esc_attr( $jmrs_visit_value( 'visit_type' ) ); ?>"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_visit_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_visit_status" id="jmrs_visit_status">
							<?php foreach ( $status_labels as $status_value => $status_label ) : ?>
								<option value="<?php echo esc_attr( (string) $status_value ); ?>" <?php selected( $jmrs_visit_value( 'visit_status' ), (string) $status_value ); ?>>
									<?php echo esc_html( (string) $status_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_visit_tasks"><?php echo esc_html__( 'Tasks', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<textarea name="jmrs_visit_tasks" id="jmrs_visit_tasks" class="large-text" rows="4"><?php echo esc_textarea( $jmrs_visit_value( 'tasks' ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_visit_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<textarea name="jmrs_visit_notes" id="jmrs_visit_notes" class="large-text" rows="4"><?php echo esc_textarea( $jmrs_visit_value( 'notes' ) ); ?></textarea>
					</td>
				</tr>
			</tbody>
		</table>

		<?php
		submit_button(
			__( 'Save Visit', 'jm-referral-system' ),
			'primary',
			'jmrs_save_care_visit'
		);
		?>
	</form>
</div>
