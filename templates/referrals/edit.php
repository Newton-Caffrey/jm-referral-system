<?php
/**
 * Edit Referral form template.
 *
 * @package JMReferral
 *
 * @var array<string, mixed>  $referral Referral row from the database.
 * @var array<string, string> $data     Form values.
 * @var array<string, string> $errors   Validation errors.
 * @var array<int, array{id: int, display_name: string}> $assignable_users Assignable WP users.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data             = is_array( $data ?? null ) ? $data : array();
$errors           = is_array( $errors ?? null ) ? $errors : array();
$referral         = is_array( $referral ?? null ) ? $referral : array();
$assignable_users = is_array( $assignable_users ?? null ) ? $assignable_users : array();
$service_types    = is_array( $service_types ?? null ) ? $service_types : array();
$workflow_stages  = is_array( $workflow_stages ?? null ) ? $workflow_stages : array();

$referral_id     = absint( $referral['id'] ?? 0 );
$referral_number = (string) ( $referral['referral_number'] ?? '' );
$created_at      = (string) ( $referral['created_at'] ?? '' );
$created_display = '' !== $created_at
	? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at )
	: '';

$client_name      = $data['client_name'] ?? '';
$client_email     = $data['client_email'] ?? '';
$client_phone     = $data['client_phone'] ?? '';
$service_type_id  = (string) ( $data['service_type_id'] ?? '0' );
$workflow_stage_id = (string) ( $data['workflow_stage_id'] ?? '0' );
$priority         = $data['priority'] ?? 'medium';
$referrer_name    = $data['referrer_name'] ?? '';
$referrer_email   = $data['referrer_email'] ?? '';
$notes            = $data['notes'] ?? '';
$status           = $data['status'] ?? 'new';
$assigned_to      = (string) ( $data['assigned_to'] ?? '0' );
$referral_source  = $data['referral_source'] ?? '';
$care_start_date  = $data['care_start_date'] ?? '';
$preferred_contact_method = $data['preferred_contact_method'] ?? '';
$care_requirements = $data['care_requirements'] ?? '';
$care_setting     = $data['care_setting'] ?? '';
$address_line_1   = $data['address_line_1'] ?? '';
$address_line_2   = $data['address_line_2'] ?? '';
$city             = $data['city'] ?? '';
$postcode         = $data['postcode'] ?? '';
$source_options   = \JMReferral\Referral\ReferralSources::options();
$contact_options  = \JMReferral\Referral\PreferredContactMethods::options();
$care_setting_options = \JMReferral\Referral\CareSetting::form_options();

$list_url = admin_url( 'admin.php?page=jm-referrals-list' );
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Edit Referral', 'jm-referral-system' ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'jmrs_edit_referral_' . $referral_id, 'jmrs_edit_referral_nonce' ); ?>
		<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

		<h2><?php echo esc_html__( 'Referral Details', 'jm-referral-system' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Referral Number', 'jm-referral-system' ); ?></th>
					<td>
						<code><?php echo esc_html( $referral_number ); ?></code>
						<p class="description"><?php echo esc_html__( 'Referral number cannot be changed.', 'jm-referral-system' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Created Date', 'jm-referral-system' ); ?></th>
					<td>
						<?php echo esc_html( $created_display ); ?>
						<p class="description"><?php echo esc_html__( 'Created date cannot be changed.', 'jm-referral-system' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Client Information', 'jm-referral-system' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="jmrs_client_name"><?php echo esc_html__( 'Client Name', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							name="jmrs_client_name"
							id="jmrs_client_name"
							class="regular-text"
							value="<?php echo esc_attr( $client_name ); ?>"
							required
						/>
						<?php if ( isset( $errors['client_name'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['client_name'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_client_email"><?php echo esc_html__( 'Client Email', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="email"
							name="jmrs_client_email"
							id="jmrs_client_email"
							class="regular-text"
							value="<?php echo esc_attr( $client_email ); ?>"
						/>
						<?php if ( isset( $errors['client_email'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['client_email'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_client_phone"><?php echo esc_html__( 'Client Phone', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							name="jmrs_client_phone"
							id="jmrs_client_phone"
							class="regular-text"
							value="<?php echo esc_attr( $client_phone ); ?>"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_address_line_1"><?php echo esc_html__( 'Address Line 1', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							name="jmrs_address_line_1"
							id="jmrs_address_line_1"
							class="regular-text"
							value="<?php echo esc_attr( $address_line_1 ); ?>"
							autocomplete="address-line1"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_address_line_2"><?php echo esc_html__( 'Address Line 2', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							name="jmrs_address_line_2"
							id="jmrs_address_line_2"
							class="regular-text"
							value="<?php echo esc_attr( $address_line_2 ); ?>"
							autocomplete="address-line2"
						/>
						<p class="description"><?php echo esc_html__( 'Optional.', 'jm-referral-system' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_city"><?php echo esc_html__( 'City', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							name="jmrs_city"
							id="jmrs_city"
							class="regular-text"
							value="<?php echo esc_attr( $city ); ?>"
							autocomplete="address-level2"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_postcode"><?php echo esc_html__( 'Postcode', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							name="jmrs_postcode"
							id="jmrs_postcode"
							class="regular-text"
							value="<?php echo esc_attr( $postcode ); ?>"
							autocomplete="postal-code"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_service_type_id"><?php echo esc_html__( 'Service Required', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_service_type_id" id="jmrs_service_type_id" required>
							<option value="0"><?php echo esc_html__( 'Select service…', 'jm-referral-system' ); ?></option>
							<?php foreach ( $service_types as $service_type ) : ?>
								<option value="<?php echo esc_attr( (string) $service_type['id'] ); ?>" <?php selected( $service_type_id, (string) $service_type['id'] ); ?>>
									<?php echo esc_html( (string) $service_type['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( isset( $errors['service_type_id'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['service_type_id'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_workflow_stage_id"><?php echo esc_html__( 'Workflow Stage', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_workflow_stage_id" id="jmrs_workflow_stage_id" required>
							<option value="0"><?php echo esc_html__( 'Select stage…', 'jm-referral-system' ); ?></option>
							<?php foreach ( $workflow_stages as $workflow_stage ) : ?>
								<option value="<?php echo esc_attr( (string) $workflow_stage['id'] ); ?>" <?php selected( $workflow_stage_id, (string) $workflow_stage['id'] ); ?>>
									<?php echo esc_html( (string) $workflow_stage['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( isset( $errors['workflow_stage_id'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['workflow_stage_id'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_priority"><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_priority" id="jmrs_priority">
							<option value="low" <?php selected( $priority, 'low' ); ?>><?php echo esc_html__( 'Low', 'jm-referral-system' ); ?></option>
							<option value="medium" <?php selected( $priority, 'medium' ); ?>><?php echo esc_html__( 'Medium', 'jm-referral-system' ); ?></option>
							<option value="high" <?php selected( $priority, 'high' ); ?>><?php echo esc_html__( 'High', 'jm-referral-system' ); ?></option>
							<option value="urgent" <?php selected( $priority, 'urgent' ); ?>><?php echo esc_html__( 'Urgent', 'jm-referral-system' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_status" id="jmrs_status">
							<option value="new" <?php selected( $status, 'new' ); ?>><?php echo esc_html__( 'New', 'jm-referral-system' ); ?></option>
							<option value="in_progress" <?php selected( $status, 'in_progress' ); ?>><?php echo esc_html__( 'In Progress', 'jm-referral-system' ); ?></option>
							<option value="completed" <?php selected( $status, 'completed' ); ?>><?php echo esc_html__( 'Completed', 'jm-referral-system' ); ?></option>
							<option value="cancelled" <?php selected( $status, 'cancelled' ); ?>><?php echo esc_html__( 'Cancelled', 'jm-referral-system' ); ?></option>
						</select>
						<?php if ( isset( $errors['status'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['status'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_assigned_to"><?php echo esc_html__( 'Assigned To', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_assigned_to" id="jmrs_assigned_to">
							<option value="0"><?php echo esc_html__( 'Unassigned', 'jm-referral-system' ); ?></option>
							<?php foreach ( $assignable_users as $user ) : ?>
								<option value="<?php echo esc_attr( (string) $user['id'] ); ?>" <?php selected( $assigned_to, (string) $user['id'] ); ?>>
									<?php echo esc_html( $user['display_name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( isset( $errors['assigned_to'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['assigned_to'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_referral_source"><?php echo esc_html__( 'Referral Source', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_referral_source" id="jmrs_referral_source" required>
							<option value=""><?php echo esc_html__( 'Select source…', 'jm-referral-system' ); ?></option>
							<?php foreach ( $source_options as $source_value => $source_label ) : ?>
								<option value="<?php echo esc_attr( $source_value ); ?>" <?php selected( $referral_source, $source_value ); ?>>
									<?php echo esc_html( $source_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( isset( $errors['referral_source'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['referral_source'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Referrer Information', 'jm-referral-system' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="jmrs_referrer_name"><?php echo esc_html__( 'Referrer Name', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							name="jmrs_referrer_name"
							id="jmrs_referrer_name"
							class="regular-text"
							value="<?php echo esc_attr( $referrer_name ); ?>"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_referrer_email"><?php echo esc_html__( 'Referrer Email', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="email"
							name="jmrs_referrer_email"
							id="jmrs_referrer_email"
							class="regular-text"
							value="<?php echo esc_attr( $referrer_email ); ?>"
						/>
						<?php if ( isset( $errors['referrer_email'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['referrer_email'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Care Requirements', 'jm-referral-system' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="jmrs_care_start_date"><?php echo esc_html__( 'Care Start Date', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input
							type="date"
							name="jmrs_care_start_date"
							id="jmrs_care_start_date"
							class="regular-text"
							value="<?php echo esc_attr( $care_start_date ); ?>"
						/>
						<?php if ( isset( $errors['care_start_date'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['care_start_date'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_care_setting"><?php echo esc_html__( 'Care Setting', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_care_setting" id="jmrs_care_setting">
							<?php foreach ( $care_setting_options as $setting_value => $setting_label ) : ?>
								<option value="<?php echo esc_attr( (string) $setting_value ); ?>" <?php selected( $care_setting, (string) $setting_value ); ?>>
									<?php echo esc_html( $setting_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php echo esc_html__( 'Where care is delivered. Independent of service type.', 'jm-referral-system' ); ?></p>
						<?php if ( isset( $errors['care_setting'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['care_setting'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_preferred_contact_method"><?php echo esc_html__( 'Preferred Contact Method', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_preferred_contact_method" id="jmrs_preferred_contact_method">
							<option value=""><?php echo esc_html__( 'Select method…', 'jm-referral-system' ); ?></option>
							<?php foreach ( $contact_options as $method_value => $method_label ) : ?>
								<option value="<?php echo esc_attr( $method_value ); ?>" <?php selected( $preferred_contact_method, $method_value ); ?>>
									<?php echo esc_html( $method_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( isset( $errors['preferred_contact_method'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['preferred_contact_method'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_care_requirements"><?php echo esc_html__( 'Care Requirements', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<textarea
							name="jmrs_care_requirements"
							id="jmrs_care_requirements"
							class="large-text"
							rows="5"
						><?php echo esc_textarea( $care_requirements ); ?></textarea>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Additional', 'jm-referral-system' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="jmrs_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<textarea
							name="jmrs_notes"
							id="jmrs_notes"
							class="large-text"
							rows="5"
						><?php echo esc_textarea( $notes ); ?></textarea>
					</td>
				</tr>
			</tbody>
		</table>

		<?php
		submit_button(
			__( 'Update Referral', 'jm-referral-system' ),
			'primary',
			'jmrs_update_referral'
		);
		?>
		<a class="button button-secondary" href="<?php echo esc_url( $list_url ); ?>">
			<?php echo esc_html__( 'Back to Referrals', 'jm-referral-system' ); ?>
		</a>
	</form>
</div>
