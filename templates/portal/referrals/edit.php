<?php
/**
 * Portal referral edit form.
 *
 * Field names and validation keys match the admin edit form so
 * ReferralEditController::attempt_update() / ReferralService::update() can be reused.
 *
 * @package JMReferral
 *
 * @var array<string, mixed>  $referral
 * @var array<string, string> $data
 * @var array<string, string> $errors
 * @var array<int, array{id: int, display_name: string}> $assignable_users
 * @var array<int, array<string, mixed>> $service_types
 * @var array<int, array<string, mixed>> $workflow_stages
 * @var string $form_action
 * @var string $cancel_url
 * @var bool   $can_assign
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
$form_action      = (string) ( $form_action ?? '' );
$cancel_url       = (string) ( $cancel_url ?? '' );
$can_assign       = ! empty( $can_assign );
$assigned_to_name = (string) ( $assigned_to_name ?? '' );

$referral_id     = absint( $referral['id'] ?? 0 );
$referral_number = (string) ( $referral['referral_number'] ?? '' );
$created_at      = (string) ( $referral['created_at'] ?? '' );
$created_display = '' !== $created_at
	? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at )
	: '';

$client_name              = $data['client_name'] ?? '';
$client_email             = $data['client_email'] ?? '';
$client_phone             = $data['client_phone'] ?? '';
$service_type_id          = (string) ( $data['service_type_id'] ?? '0' );
$workflow_stage_id        = (string) ( $data['workflow_stage_id'] ?? '0' );
$priority                 = $data['priority'] ?? 'medium';
$referrer_name            = $data['referrer_name'] ?? '';
$referrer_email           = $data['referrer_email'] ?? '';
$notes                    = $data['notes'] ?? '';
$status                   = $data['status'] ?? 'new';
$assigned_to              = (string) ( $data['assigned_to'] ?? '0' );
$referral_source          = $data['referral_source'] ?? '';
$care_start_date          = $data['care_start_date'] ?? '';
$preferred_contact_method = $data['preferred_contact_method'] ?? '';
$care_requirements        = $data['care_requirements'] ?? '';
$source_options           = \JMReferral\Referral\ReferralSources::options();
$contact_options          = \JMReferral\Referral\PreferredContactMethods::options();

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
	<?php wp_nonce_field( 'jmrs_edit_referral_' . $referral_id, 'jmrs_edit_referral_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

	<p class="jmrs-portal-form-note">
		<?php echo esc_html__( 'This page updates referral, client and referrer details. Assessment and Care Plan information is edited from their own sections on the Referral page.', 'jm-referral-system' ); ?>
	</p>
	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Referral Details', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<span class="jmrs-portal-field__label"><?php echo esc_html__( 'Referral Number', 'jm-referral-system' ); ?></span>
				<code><?php echo esc_html( $referral_number ); ?></code>
				<p class="jmrs-portal-field__hint"><?php echo esc_html__( 'Referral number cannot be changed.', 'jm-referral-system' ); ?></p>
			</div>
			<div class="jmrs-portal-field">
				<span class="jmrs-portal-field__label"><?php echo esc_html__( 'Created Date', 'jm-referral-system' ); ?></span>
				<span><?php echo esc_html( $created_display ); ?></span>
				<p class="jmrs-portal-field__hint"><?php echo esc_html__( 'Created date cannot be changed.', 'jm-referral-system' ); ?></p>
			</div>
		</div>
	</section>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Client Information', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_client_name"><?php echo esc_html__( 'Client Name', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_client_name" id="jmrs_client_name" value="<?php echo esc_attr( $client_name ); ?>" required />
				<?php $field_error( $errors, 'client_name' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_client_email"><?php echo esc_html__( 'Client Email', 'jm-referral-system' ); ?></label>
				<input type="email" name="jmrs_client_email" id="jmrs_client_email" value="<?php echo esc_attr( $client_email ); ?>" />
				<?php $field_error( $errors, 'client_email' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_client_phone"><?php echo esc_html__( 'Client Phone', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_client_phone" id="jmrs_client_phone" value="<?php echo esc_attr( $client_phone ); ?>" />
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_service_type_id"><?php echo esc_html__( 'Service Required', 'jm-referral-system' ); ?></label>
				<select name="jmrs_service_type_id" id="jmrs_service_type_id" required>
					<option value="0"><?php echo esc_html__( 'Select service…', 'jm-referral-system' ); ?></option>
					<?php foreach ( $service_types as $service_type ) : ?>
						<option value="<?php echo esc_attr( (string) $service_type['id'] ); ?>" <?php selected( $service_type_id, (string) $service_type['id'] ); ?>>
							<?php echo esc_html( (string) $service_type['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'service_type_id' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_workflow_stage_id"><?php echo esc_html__( 'Workflow Stage', 'jm-referral-system' ); ?></label>
				<select name="jmrs_workflow_stage_id" id="jmrs_workflow_stage_id" required>
					<option value="0"><?php echo esc_html__( 'Select stage…', 'jm-referral-system' ); ?></option>
					<?php foreach ( $workflow_stages as $workflow_stage ) : ?>
						<option value="<?php echo esc_attr( (string) $workflow_stage['id'] ); ?>" <?php selected( $workflow_stage_id, (string) $workflow_stage['id'] ); ?>>
							<?php echo esc_html( (string) $workflow_stage['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'workflow_stage_id' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_priority"><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?></label>
				<select name="jmrs_priority" id="jmrs_priority">
					<option value="low" <?php selected( $priority, 'low' ); ?>><?php echo esc_html__( 'Low', 'jm-referral-system' ); ?></option>
					<option value="medium" <?php selected( $priority, 'medium' ); ?>><?php echo esc_html__( 'Medium', 'jm-referral-system' ); ?></option>
					<option value="high" <?php selected( $priority, 'high' ); ?>><?php echo esc_html__( 'High', 'jm-referral-system' ); ?></option>
					<option value="urgent" <?php selected( $priority, 'urgent' ); ?>><?php echo esc_html__( 'Urgent', 'jm-referral-system' ); ?></option>
				</select>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
				<select name="jmrs_status" id="jmrs_status">
					<option value="new" <?php selected( $status, 'new' ); ?>><?php echo esc_html__( 'New', 'jm-referral-system' ); ?></option>
					<option value="in_progress" <?php selected( $status, 'in_progress' ); ?>><?php echo esc_html__( 'In Progress', 'jm-referral-system' ); ?></option>
					<option value="completed" <?php selected( $status, 'completed' ); ?>><?php echo esc_html__( 'Completed', 'jm-referral-system' ); ?></option>
					<option value="cancelled" <?php selected( $status, 'cancelled' ); ?>><?php echo esc_html__( 'Cancelled', 'jm-referral-system' ); ?></option>
				</select>
				<?php $field_error( $errors, 'status' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_assigned_to"><?php echo esc_html__( 'Assigned To', 'jm-referral-system' ); ?></label>
				<?php if ( $can_assign ) : ?>
					<select name="jmrs_assigned_to" id="jmrs_assigned_to">
						<option value="0"><?php echo esc_html__( 'Unassigned', 'jm-referral-system' ); ?></option>
						<?php foreach ( $assignable_users as $user ) : ?>
							<option value="<?php echo esc_attr( (string) $user['id'] ); ?>" <?php selected( $assigned_to, (string) $user['id'] ); ?>>
								<?php echo esc_html( $user['display_name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="hidden" name="jmrs_assigned_to" value="<?php echo esc_attr( $assigned_to ); ?>" />
					<span><?php echo esc_html( '' !== $assigned_to_name ? $assigned_to_name : __( 'Unassigned', 'jm-referral-system' ) ); ?></span>
					<p class="jmrs-portal-field__hint"><?php echo esc_html__( 'You do not have permission to reassign referrals.', 'jm-referral-system' ); ?></p>
				<?php endif; ?>
				<?php $field_error( $errors, 'assigned_to' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_referral_source"><?php echo esc_html__( 'Referral Source', 'jm-referral-system' ); ?></label>
				<select name="jmrs_referral_source" id="jmrs_referral_source" required>
					<option value=""><?php echo esc_html__( 'Select source…', 'jm-referral-system' ); ?></option>
					<?php foreach ( $source_options as $source_value => $source_label ) : ?>
						<option value="<?php echo esc_attr( $source_value ); ?>" <?php selected( $referral_source, $source_value ); ?>>
							<?php echo esc_html( $source_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'referral_source' ); ?>
			</div>
		</div>
	</section>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Referrer Information', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_referrer_name"><?php echo esc_html__( 'Referrer Name', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_referrer_name" id="jmrs_referrer_name" value="<?php echo esc_attr( $referrer_name ); ?>" />
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_referrer_email"><?php echo esc_html__( 'Referrer Email', 'jm-referral-system' ); ?></label>
				<input type="email" name="jmrs_referrer_email" id="jmrs_referrer_email" value="<?php echo esc_attr( $referrer_email ); ?>" />
				<?php $field_error( $errors, 'referrer_email' ); ?>
			</div>
		</div>
	</section>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Care Requirements', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_care_start_date"><?php echo esc_html__( 'Care Start Date', 'jm-referral-system' ); ?></label>
				<input type="date" name="jmrs_care_start_date" id="jmrs_care_start_date" value="<?php echo esc_attr( $care_start_date ); ?>" />
				<?php $field_error( $errors, 'care_start_date' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_preferred_contact_method"><?php echo esc_html__( 'Preferred Contact Method', 'jm-referral-system' ); ?></label>
				<select name="jmrs_preferred_contact_method" id="jmrs_preferred_contact_method">
					<option value=""><?php echo esc_html__( 'Select method…', 'jm-referral-system' ); ?></option>
					<?php foreach ( $contact_options as $method_value => $method_label ) : ?>
						<option value="<?php echo esc_attr( $method_value ); ?>" <?php selected( $preferred_contact_method, $method_value ); ?>>
							<?php echo esc_html( $method_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'preferred_contact_method' ); ?>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_care_requirements"><?php echo esc_html__( 'Care Requirements', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_care_requirements" id="jmrs_care_requirements" rows="5"><?php echo esc_textarea( $care_requirements ); ?></textarea>
			</div>
		</div>
	</section>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Additional', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_notes" id="jmrs_notes" rows="5"><?php echo esc_textarea( $notes ); ?></textarea>
			</div>
		</div>
	</section>

	<p class="jmrs-portal-actions">
		<button type="submit" name="jmrs_update_referral" value="1" class="jmrs-button jmrs-button--primary">
			<?php echo esc_html__( 'Update Referral', 'jm-referral-system' ); ?>
		</button>
		<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
			<?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?>
		</a>
	</p>
</form>
