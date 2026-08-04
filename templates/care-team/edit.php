<?php
/**
 * Edit care team assignment template.
 *
 * @var array<string, mixed>  $assignment
 * @var array<string, mixed>  $referral
 * @var array<string, string> $assignment_data
 * @var array<string, string> $errors
 * @var array<int, array{id: int, display_name: string}> $assignable_users
 * @var array<string, string> $role_labels
 * @var array<string, string> $status_labels
 * @var string                $back_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral_id      = absint( $referral['id'] ?? 0 );
$assignment_id    = absint( $assignment['id'] ?? 0 );
$referral_number  = (string) ( $referral['referral_number'] ?? '' );
$client_name      = (string) ( $referral['client_name'] ?? '' );
$assignment_data  = is_array( $assignment_data ?? null ) ? $assignment_data : array();
$errors           = is_array( $errors ?? null ) ? $errors : array();
$assignable_users = is_array( $assignable_users ?? null ) ? $assignable_users : array();
$role_labels      = is_array( $role_labels ?? null ) ? $role_labels : array();
$status_labels    = is_array( $status_labels ?? null ) ? $status_labels : array();

$jmrs_team_value = static function ( string $key ) use ( $assignment_data ): string {
	return (string) ( $assignment_data[ $key ] ?? '' );
};
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Edit Care Team Assignment', 'jm-referral-system' ); ?></h1>

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
		<?php wp_nonce_field( 'jmrs_save_care_team_' . $referral_id, 'jmrs_care_team_nonce' ); ?>
		<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
		<input type="hidden" name="jmrs_care_team_id" value="<?php echo esc_attr( (string) $assignment_id ); ?>" />
		<input type="hidden" name="jmrs_care_team_care_plan_id" value="<?php echo esc_attr( $jmrs_team_value( 'care_plan_id' ) ); ?>" />

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="jmrs_care_team_user_id"><?php echo esc_html__( 'Staff Member', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_care_team_user_id" id="jmrs_care_team_user_id" required>
							<option value=""><?php echo esc_html__( 'Select staff member', 'jm-referral-system' ); ?></option>
							<?php foreach ( $assignable_users as $user_row ) : ?>
								<option value="<?php echo esc_attr( (string) ( $user_row['id'] ?? 0 ) ); ?>" <?php selected( $jmrs_team_value( 'user_id' ), (string) ( $user_row['id'] ?? 0 ) ); ?>>
									<?php echo esc_html( (string) ( $user_row['display_name'] ?? '' ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_care_team_role"><?php echo esc_html__( 'Team Role', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_care_team_role" id="jmrs_care_team_role" required>
							<option value=""><?php echo esc_html__( 'Select role', 'jm-referral-system' ); ?></option>
							<?php foreach ( $role_labels as $role_value => $role_label ) : ?>
								<option value="<?php echo esc_attr( (string) $role_value ); ?>" <?php selected( $jmrs_team_value( 'team_role' ), (string) $role_value ); ?>>
									<?php echo esc_html( (string) $role_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Primary', 'jm-referral-system' ); ?></th>
					<td>
						<label for="jmrs_care_team_is_primary">
							<input
								type="checkbox"
								name="jmrs_care_team_is_primary"
								id="jmrs_care_team_is_primary"
								value="1"
								<?php checked( $jmrs_team_value( 'is_primary' ), '1' ); ?>
							/>
							<?php echo esc_html__( 'Primary Carer for this referral', 'jm-referral-system' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_care_team_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_care_team_status" id="jmrs_care_team_status">
							<?php foreach ( $status_labels as $status_value => $status_label ) : ?>
								<option value="<?php echo esc_attr( (string) $status_value ); ?>" <?php selected( $jmrs_team_value( 'assignment_status' ), (string) $status_value ); ?>>
									<?php echo esc_html( (string) $status_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_care_team_start_date"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="date"
							class="regular-text"
							name="jmrs_care_team_start_date"
							id="jmrs_care_team_start_date"
							value="<?php echo esc_attr( $jmrs_team_value( 'start_date' ) ); ?>"
							required
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_care_team_end_date"><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="date"
							class="regular-text"
							name="jmrs_care_team_end_date"
							id="jmrs_care_team_end_date"
							value="<?php echo esc_attr( $jmrs_team_value( 'end_date' ) ); ?>"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_care_team_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<textarea name="jmrs_care_team_notes" id="jmrs_care_team_notes" class="large-text" rows="4"><?php echo esc_textarea( $jmrs_team_value( 'notes' ) ); ?></textarea>
					</td>
				</tr>
			</tbody>
		</table>

		<?php
		submit_button(
			__( 'Save Assignment', 'jm-referral-system' ),
			'primary',
			'jmrs_save_care_team'
		);
		?>
	</form>
</div>
