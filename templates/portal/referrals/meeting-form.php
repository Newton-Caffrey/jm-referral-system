<?php
/**
 * Portal meeting create / edit / schedule form.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral         = is_array( $referral ?? null ) ? $referral : array();
$referral_id      = absint( $referral['id'] ?? 0 );
$meeting_id       = absint( $meeting_id ?? 0 );
$data             = is_array( $data ?? null ) ? $data : array();
$errors           = is_array( $errors ?? null ) ? $errors : array();
$form_action      = (string) ( $form_action ?? '' );
$cancel_url       = (string) ( $cancel_url ?? '' );
$type_labels      = is_array( $type_labels ?? null ) ? $type_labels : array();
$location_labels  = is_array( $location_labels ?? null ) ? $location_labels : array();
$show_type        = ! empty( $show_type );
$show_purpose     = ! empty( $show_purpose );
$show_schedule    = ! empty( $show_schedule );
$show_location    = ! empty( $show_location );
$past_warning     = ! empty( $past_warning );
$submit_draft     = ! empty( $submit_draft );
$submit_scheduled = ! empty( $submit_scheduled );
$submit_label     = (string) ( $submit_label ?? __( 'Save', 'jm-referral-system' ) );
$status           = (string) ( $status ?? '' );
$mode             = (string) ( $mode ?? '' );
$require_schedule = $submit_scheduled || 'schedule' === $mode || 'reschedule' === $mode;

$val = static function ( array $data, string $key ): string {
	return (string) ( $data[ $key ] ?? '' );
};

$field_error = static function ( array $errors, string $key ): void {
	if ( ! isset( $errors[ $key ] ) ) {
		return;
	}
	echo '<p class="jmrs-portal-field-error" id="jmrs-meeting-error-' . esc_attr( $key ) . '">' . esc_html( (string) $errors[ $key ] ) . '</p>';
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

<?php if ( $past_warning ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--warning" role="status">
		<p>
			<?php
			echo esc_html__(
				'This meeting date is in the past. Continue only if you are recording a meeting retrospectively.',
				'jm-referral-system'
			);
			?>
		</p>
	</div>
<?php endif; ?>

<form class="jmrs-portal-form jmrs-meeting-form" method="post" action="<?php echo esc_url( $form_action ); ?>" novalidate>
	<?php wp_nonce_field( 'jmrs_save_meeting_' . $referral_id, 'jmrs_meeting_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
	<input type="hidden" name="jmrs_meeting_id" value="<?php echo esc_attr( (string) $meeting_id ); ?>" />

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Meeting details', 'jm-referral-system' ); ?></h2>
		<?php if ( '' !== $status ) : ?>
			<p>
				<span class="jmrs-portal-badge jmrs-meeting-status jmrs-meeting-status--<?php echo esc_attr( sanitize_html_class( $status ) ); ?>">
					<?php echo esc_html( \JMReferral\Meeting\ReferralMeeting::status_label( $status ) ); ?>
				</span>
			</p>
		<?php endif; ?>
		<div class="jmrs-portal-form-grid">
			<?php if ( $show_type ) : ?>
				<div class="jmrs-portal-field">
					<label for="jmrs_meeting_type">
						<?php echo esc_html__( 'Meeting type', 'jm-referral-system' ); ?>
						<span class="jmrs-required" aria-hidden="true">*</span>
						<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
					</label>
					<select name="jmrs_meeting_type" id="jmrs_meeting_type" required<?php echo isset( $errors['meeting_type'] ) ? ' aria-invalid="true" aria-describedby="jmrs-meeting-error-meeting_type"' : ''; ?>>
						<option value=""><?php echo esc_html__( 'Select type', 'jm-referral-system' ); ?></option>
						<?php foreach ( $type_labels as $type_value => $type_label ) : ?>
							<option value="<?php echo esc_attr( (string) $type_value ); ?>" <?php selected( $val( $data, 'meeting_type' ), (string) $type_value ); ?>>
								<?php echo esc_html( (string) $type_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php $field_error( $errors, 'meeting_type' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $show_purpose ) : ?>
				<div class="jmrs-portal-field jmrs-portal-field--full">
					<label for="jmrs_meeting_purpose">
						<?php echo esc_html__( 'Purpose', 'jm-referral-system' ); ?>
						<span class="jmrs-required" aria-hidden="true">*</span>
						<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
					</label>
					<input type="text" name="jmrs_meeting_purpose" id="jmrs_meeting_purpose" maxlength="255" required aria-required="true" value="<?php echo esc_attr( $val( $data, 'purpose' ) ); ?>"<?php echo isset( $errors['purpose'] ) ? ' aria-invalid="true" aria-describedby="jmrs-meeting-error-purpose"' : ''; ?> />
					<?php $field_error( $errors, 'purpose' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $show_schedule ) : ?>
				<p class="description jmrs-portal-field--full">
					<?php
					echo esc_html__(
						'Past dates are allowed when recording a meeting retrospectively. A warning appears when the entered start is in the past.',
						'jm-referral-system'
					);
					?>
				</p>
				<div class="jmrs-portal-field">
					<label for="jmrs_meeting_scheduled_date"><?php echo esc_html__( 'Scheduled date', 'jm-referral-system' ); ?><?php echo $require_schedule ? ' <span class="jmrs-required" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__( 'required', 'jm-referral-system' ) . '</span>' : ''; ?></label>
					<input type="date" name="jmrs_meeting_scheduled_date" id="jmrs_meeting_scheduled_date" value="<?php echo esc_attr( $val( $data, 'scheduled_date' ) ); ?>"<?php echo $require_schedule ? ' required aria-required="true"' : ''; ?><?php echo isset( $errors['scheduled_at'] ) ? ' aria-invalid="true" aria-describedby="jmrs-meeting-error-scheduled_at"' : ''; ?> />
					<?php $field_error( $errors, 'scheduled_at' ); ?>
				</div>
				<div class="jmrs-portal-field">
					<label for="jmrs_meeting_scheduled_time"><?php echo esc_html__( 'Start time', 'jm-referral-system' ); ?><?php echo $require_schedule ? ' <span class="jmrs-required" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__( 'required', 'jm-referral-system' ) . '</span>' : ''; ?></label>
					<input type="time" name="jmrs_meeting_scheduled_time" id="jmrs_meeting_scheduled_time" value="<?php echo esc_attr( $val( $data, 'scheduled_time' ) ); ?>"<?php echo $require_schedule ? ' required aria-required="true"' : ''; ?> />
				</div>
				<div class="jmrs-portal-field">
					<label for="jmrs_meeting_scheduled_end_date"><?php echo esc_html__( 'End date (optional)', 'jm-referral-system' ); ?></label>
					<input type="date" name="jmrs_meeting_scheduled_end_date" id="jmrs_meeting_scheduled_end_date" value="<?php echo esc_attr( $val( $data, 'scheduled_end_date' ) ); ?>" />
					<?php $field_error( $errors, 'scheduled_end_at' ); ?>
				</div>
				<div class="jmrs-portal-field">
					<label for="jmrs_meeting_scheduled_end_time"><?php echo esc_html__( 'End time (optional)', 'jm-referral-system' ); ?></label>
					<input type="time" name="jmrs_meeting_scheduled_end_time" id="jmrs_meeting_scheduled_end_time" value="<?php echo esc_attr( $val( $data, 'scheduled_end_time' ) ); ?>" />
				</div>
			<?php endif; ?>

			<?php if ( $show_location ) : ?>
				<div class="jmrs-portal-field">
					<label for="jmrs_meeting_location_type"><?php echo esc_html__( 'Location type', 'jm-referral-system' ); ?><?php echo $require_schedule ? ' <span class="jmrs-required" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__( 'required', 'jm-referral-system' ) . '</span>' : ''; ?></label>
					<select name="jmrs_meeting_location_type" id="jmrs_meeting_location_type"<?php echo $require_schedule ? ' required aria-required="true"' : ''; ?><?php echo isset( $errors['location_type'] ) ? ' aria-invalid="true" aria-describedby="jmrs-meeting-error-location_type"' : ''; ?>>
						<option value=""><?php echo esc_html__( 'Select location type', 'jm-referral-system' ); ?></option>
						<?php foreach ( $location_labels as $loc_value => $loc_label ) : ?>
							<option value="<?php echo esc_attr( (string) $loc_value ); ?>" <?php selected( $val( $data, 'location_type' ), (string) $loc_value ); ?>>
								<?php echo esc_html( (string) $loc_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php $field_error( $errors, 'location_type' ); ?>
				</div>
				<div class="jmrs-portal-field">
					<label for="jmrs_meeting_location_name"><?php echo esc_html__( 'Location name', 'jm-referral-system' ); ?></label>
					<input type="text" name="jmrs_meeting_location_name" id="jmrs_meeting_location_name" maxlength="255" value="<?php echo esc_attr( $val( $data, 'location_name' ) ); ?>" />
					<?php $field_error( $errors, 'location_name' ); ?>
				</div>
				<div class="jmrs-portal-field jmrs-portal-field--full">
					<label for="jmrs_meeting_location_address"><?php echo esc_html__( 'Location address', 'jm-referral-system' ); ?></label>
					<input type="text" name="jmrs_meeting_location_address" id="jmrs_meeting_location_address" maxlength="500" value="<?php echo esc_attr( $val( $data, 'location_address' ) ); ?>" />
					<?php $field_error( $errors, 'location_address' ); ?>
				</div>
				<div class="jmrs-portal-field jmrs-portal-field--full">
					<label for="jmrs_meeting_online_url"><?php echo esc_html__( 'Online meeting URL', 'jm-referral-system' ); ?></label>
					<input type="url" name="jmrs_meeting_online_url" id="jmrs_meeting_online_url" maxlength="500" value="<?php echo esc_attr( $val( $data, 'online_meeting_url' ) ); ?>" />
					<?php $field_error( $errors, 'online_meeting_url' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<div class="jmrs-portal-actions">
		<?php if ( $submit_draft && $submit_scheduled ) : ?>
			<button type="submit" class="jmrs-button jmrs-button--secondary" name="jmrs_save_meeting" value="draft">
				<?php echo esc_html__( 'Save as draft', 'jm-referral-system' ); ?>
			</button>
			<button type="submit" class="jmrs-button jmrs-button--primary" name="jmrs_save_meeting" value="scheduled">
				<?php echo esc_html__( 'Create as scheduled', 'jm-referral-system' ); ?>
			</button>
		<?php else : ?>
			<button type="submit" class="jmrs-button jmrs-button--primary" name="jmrs_save_meeting" value="1"><?php echo esc_html( $submit_label ); ?></button>
		<?php endif; ?>
		<?php if ( '' !== $cancel_url ) : ?>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>"><?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?></a>
		<?php endif; ?>
	</div>
</form>
<p class="description">
	<?php echo esc_html__( 'Dates and times use the WordPress site timezone. Meetings are separate from formal assessment appointments.', 'jm-referral-system' ); ?>
</p>
