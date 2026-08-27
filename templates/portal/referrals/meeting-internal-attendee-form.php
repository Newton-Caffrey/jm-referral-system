<?php
/**
 * Add / edit / correct internal meeting attendee (Phase 4B.2.3).
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral           = is_array( $referral ?? null ) ? $referral : array();
$referral_id        = absint( $referral['id'] ?? 0 );
$meeting            = is_array( $meeting ?? null ) ? $meeting : array();
$meeting_id         = absint( $meeting_id ?? 0 );
$attendee_id        = absint( $attendee_id ?? 0 );
$mode               = sanitize_key( (string) ( $mode ?? 'add' ) );
$data               = is_array( $data ?? null ) ? $data : array();
$errors             = is_array( $errors ?? null ) ? $errors : array();
$staff_options      = is_array( $staff_options ?? null ) ? $staff_options : array();
$attendance_labels  = is_array( $attendance_labels ?? null ) ? $attendance_labels : array();
$staff_display_name = (string) ( $staff_display_name ?? '' );
$form_action        = (string) ( $form_action ?? '' );
$cancel_url         = (string) ( $cancel_url ?? '' );

$is_add     = 'add' === $mode;
$is_correct = 'correct' === $mode;

$val = static function ( array $data, string $key ): string {
	return (string) ( $data[ $key ] ?? '' );
};

$field_error = static function ( array $errors, string $key ): void {
	if ( ! isset( $errors[ $key ] ) ) {
		return;
	}
	echo '<p class="jmrs-portal-field-error" id="jmrs-attendee-error-' . esc_attr( $key ) . '">' . esc_html( (string) $errors[ $key ] ) . '</p>';
};

$heading = $is_add
	? __( 'Add internal attendee', 'jm-referral-system' )
	: ( $is_correct
		? __( 'Correct attendance', 'jm-referral-system' )
		: __( 'Edit internal attendee', 'jm-referral-system' ) );
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

<section class="jmrs-portal-section jmrs-portal-panel">
	<h2 class="jmrs-portal-section__title"><?php echo esc_html( $heading ); ?></h2>
	<p>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: meeting type */
				__( 'Meeting: %s', 'jm-referral-system' ),
				(string) ( \JMReferral\Meeting\ReferralMeeting::type_label( (string) ( $meeting['meeting_type'] ?? '' ) ) )
			)
		);
		?>
	</p>
	<?php if ( $is_correct ) : ?>
		<p class="description">
			<?php echo esc_html__( 'After completion, you may correct attendance to attended, absent, or declined only. Staff identity and meeting role cannot be changed.', 'jm-referral-system' ); ?>
		</p>
	<?php endif; ?>
</section>

<form class="jmrs-portal-form jmrs-meeting-attendee-form" method="post" action="<?php echo esc_url( $form_action ); ?>" novalidate>
	<?php wp_nonce_field( 'jmrs_save_meeting_attendee_' . $referral_id, 'jmrs_meeting_attendee_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
	<input type="hidden" name="jmrs_meeting_id" value="<?php echo esc_attr( (string) $meeting_id ); ?>" />
	<input type="hidden" name="jmrs_attendee_id" value="<?php echo esc_attr( (string) $attendee_id ); ?>" />

	<div class="jmrs-portal-form-grid">
		<?php if ( $is_add ) : ?>
			<div class="jmrs-portal-field">
				<label for="jmrs_attendee_user_id">
					<?php echo esc_html__( 'Staff member', 'jm-referral-system' ); ?>
					<span class="jmrs-required" aria-hidden="true">*</span>
					<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
				</label>
				<select
					name="jmrs_attendee_user_id"
					id="jmrs_attendee_user_id"
					required
					aria-required="true"
					<?php echo isset( $errors['user_id'] ) ? 'aria-invalid="true" aria-describedby="jmrs-attendee-error-user_id"' : ''; ?>
				>
					<option value=""><?php echo esc_html__( 'Select staff member', 'jm-referral-system' ); ?></option>
					<?php foreach ( $staff_options as $user_row ) : ?>
						<option value="<?php echo esc_attr( (string) ( $user_row['id'] ?? 0 ) ); ?>" <?php selected( $val( $data, 'user_id' ), (string) ( $user_row['id'] ?? 0 ) ); ?>>
							<?php echo esc_html( (string) ( $user_row['display_name'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'user_id' ); ?>
			</div>
		<?php else : ?>
			<div class="jmrs-portal-field">
				<span class="jmrs-portal-field__label"><?php echo esc_html__( 'Staff member', 'jm-referral-system' ); ?></span>
				<p class="jmrs-portal-readonly"><?php echo esc_html( '' !== $staff_display_name ? $staff_display_name : __( 'Unavailable user', 'jm-referral-system' ) ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( ! $is_correct ) : ?>
			<div class="jmrs-portal-field">
				<label for="jmrs_attendee_meeting_role">
					<?php echo esc_html__( 'Meeting role', 'jm-referral-system' ); ?>
					<span class="jmrs-required" aria-hidden="true">*</span>
					<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
				</label>
				<input
					type="text"
					name="jmrs_attendee_meeting_role"
					id="jmrs_attendee_meeting_role"
					maxlength="150"
					required
					aria-required="true"
					value="<?php echo esc_attr( $val( $data, 'meeting_role' ) ); ?>"
					<?php echo isset( $errors['meeting_role'] ) ? 'aria-invalid="true" aria-describedby="jmrs-attendee-error-meeting_role"' : ''; ?>
				/>
				<p class="description"><?php echo esc_html__( 'Examples: J&M representative, Meeting lead, Observer, Assessor, Care coordinator.', 'jm-referral-system' ); ?></p>
				<?php $field_error( $errors, 'meeting_role' ); ?>
			</div>
		<?php else : ?>
			<div class="jmrs-portal-field">
				<span class="jmrs-portal-field__label"><?php echo esc_html__( 'Meeting role', 'jm-referral-system' ); ?></span>
				<p class="jmrs-portal-readonly"><?php echo esc_html( '' !== $val( $data, 'meeting_role' ) ? $val( $data, 'meeting_role' ) : '—' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="jmrs-portal-field">
			<label for="jmrs_attendee_attendance_status">
				<?php echo esc_html__( 'Attendance status', 'jm-referral-system' ); ?>
				<span class="jmrs-required" aria-hidden="true">*</span>
				<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
			</label>
			<select
				name="jmrs_attendee_attendance_status"
				id="jmrs_attendee_attendance_status"
				required
				aria-required="true"
				<?php echo isset( $errors['attendance_status'] ) ? 'aria-invalid="true" aria-describedby="jmrs-attendee-error-attendance_status"' : ''; ?>
			>
				<?php if ( $is_correct && ! isset( $attendance_labels[ $val( $data, 'attendance_status' ) ] ) ) : ?>
					<option value=""><?php echo esc_html__( 'Select final attendance', 'jm-referral-system' ); ?></option>
				<?php endif; ?>
				<?php foreach ( $attendance_labels as $status_value => $status_label ) : ?>
					<option value="<?php echo esc_attr( (string) $status_value ); ?>" <?php selected( $val( $data, 'attendance_status' ), (string) $status_value ); ?>>
						<?php echo esc_html( (string) $status_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php $field_error( $errors, 'attendance_status' ); ?>
		</div>
	</div>

	<div class="jmrs-portal-actions">
		<button type="submit" class="jmrs-button jmrs-button--primary" name="jmrs_save_meeting_attendee" value="1">
			<?php
			echo esc_html(
				$is_add
					? __( 'Add internal attendee', 'jm-referral-system' )
					: ( $is_correct
						? __( 'Save attendance', 'jm-referral-system' )
						: __( 'Save changes', 'jm-referral-system' ) )
			);
			?>
		</button>
		<?php if ( '' !== $cancel_url ) : ?>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
				<?php echo esc_html__( 'Back to meeting', 'jm-referral-system' ); ?>
			</a>
		<?php endif; ?>
	</div>
</form>
