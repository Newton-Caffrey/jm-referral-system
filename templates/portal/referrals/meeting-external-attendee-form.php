<?php
/**
 * Add / edit / correct external meeting participant (Phase 4B.2.4).
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral          = is_array( $referral ?? null ) ? $referral : array();
$referral_id       = absint( $referral['id'] ?? 0 );
$meeting           = is_array( $meeting ?? null ) ? $meeting : array();
$meeting_id        = absint( $meeting_id ?? 0 );
$attendee_id       = absint( $attendee_id ?? 0 );
$mode              = sanitize_key( (string) ( $mode ?? 'add' ) );
$data              = is_array( $data ?? null ) ? $data : array();
$errors            = is_array( $errors ?? null ) ? $errors : array();
$category_labels   = is_array( $category_labels ?? null ) ? $category_labels : array();
$attendance_labels = is_array( $attendance_labels ?? null ) ? $attendance_labels : array();
$can_view_contacts = ! empty( $can_view_contacts );
$form_action       = (string) ( $form_action ?? '' );
$cancel_url        = (string) ( $cancel_url ?? '' );

$is_add     = 'add' === $mode;
$is_correct = 'correct' === $mode;

$val = static function ( array $data, string $key ): string {
	return (string) ( $data[ $key ] ?? '' );
};

$field_error = static function ( array $errors, string $key ): void {
	if ( ! isset( $errors[ $key ] ) ) {
		return;
	}
	echo '<p class="jmrs-portal-field-error" id="jmrs-ext-error-' . esc_attr( $key ) . '">' . esc_html( (string) $errors[ $key ] ) . '</p>';
};

$heading = $is_add
	? __( 'Add external participant', 'jm-referral-system' )
	: ( $is_correct
		? __( 'Correct attendance', 'jm-referral-system' )
		: __( 'Edit external participant', 'jm-referral-system' ) );
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
			<?php echo esc_html__( 'After completion, you may correct attendance to attended, absent, or declined only. Participant identity and contact details cannot be changed.', 'jm-referral-system' ); ?>
		</p>
	<?php endif; ?>
</section>

<form class="jmrs-portal-form" method="post" action="<?php echo esc_url( $form_action ); ?>" novalidate>
	<?php wp_nonce_field( 'jmrs_save_meeting_external_attendee_' . $referral_id, 'jmrs_meeting_external_attendee_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
	<input type="hidden" name="jmrs_meeting_id" value="<?php echo esc_attr( (string) $meeting_id ); ?>" />
	<input type="hidden" name="jmrs_attendee_id" value="<?php echo esc_attr( (string) $attendee_id ); ?>" />

	<div class="jmrs-portal-form-grid">
		<?php if ( ! $is_correct ) : ?>
			<div class="jmrs-portal-field">
				<label for="jmrs_ext_display_name">
					<?php echo esc_html__( 'Participant name', 'jm-referral-system' ); ?>
					<span class="jmrs-required" aria-hidden="true">*</span>
					<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
				</label>
				<input
					type="text"
					name="jmrs_ext_display_name"
					id="jmrs_ext_display_name"
					maxlength="255"
					required
					aria-required="true"
					autocomplete="name"
					value="<?php echo esc_attr( $val( $data, 'display_name' ) ); ?>"
					<?php echo isset( $errors['display_name'] ) ? 'aria-invalid="true" aria-describedby="jmrs-ext-error-display_name"' : ''; ?>
				/>
				<?php $field_error( $errors, 'display_name' ); ?>
			</div>

			<div class="jmrs-portal-field">
				<label for="jmrs_ext_professional_role"><?php echo esc_html__( 'Professional role (optional)', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_ext_professional_role" id="jmrs_ext_professional_role" maxlength="150" value="<?php echo esc_attr( $val( $data, 'professional_role' ) ); ?>" />
				<?php $field_error( $errors, 'professional_role' ); ?>
			</div>

			<div class="jmrs-portal-field">
				<label for="jmrs_ext_organisation"><?php echo esc_html__( 'Organisation (optional)', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_ext_organisation" id="jmrs_ext_organisation" maxlength="255" autocomplete="organization" value="<?php echo esc_attr( $val( $data, 'organisation' ) ); ?>" />
				<?php $field_error( $errors, 'organisation' ); ?>
			</div>

			<?php if ( $can_view_contacts ) : ?>
				<div class="jmrs-portal-field">
					<label for="jmrs_ext_email"><?php echo esc_html__( 'Email (optional)', 'jm-referral-system' ); ?></label>
					<input
						type="email"
						name="jmrs_ext_email"
						id="jmrs_ext_email"
						maxlength="190"
						autocomplete="email"
						value="<?php echo esc_attr( $val( $data, 'email' ) ); ?>"
						<?php echo isset( $errors['email'] ) ? 'aria-invalid="true" aria-describedby="jmrs-ext-error-email"' : ''; ?>
					/>
					<?php $field_error( $errors, 'email' ); ?>
				</div>

				<div class="jmrs-portal-field">
					<label for="jmrs_ext_telephone"><?php echo esc_html__( 'Telephone (optional)', 'jm-referral-system' ); ?></label>
					<input
						type="tel"
						name="jmrs_ext_telephone"
						id="jmrs_ext_telephone"
						maxlength="50"
						autocomplete="tel"
						value="<?php echo esc_attr( $val( $data, 'telephone' ) ); ?>"
						<?php echo isset( $errors['telephone'] ) ? 'aria-invalid="true" aria-describedby="jmrs-ext-error-telephone"' : ''; ?>
					/>
					<?php $field_error( $errors, 'telephone' ); ?>
				</div>
			<?php endif; ?>

			<div class="jmrs-portal-field">
				<label for="jmrs_ext_participant_category">
					<?php echo esc_html__( 'Participant category', 'jm-referral-system' ); ?>
					<span class="jmrs-required" aria-hidden="true">*</span>
					<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
				</label>
				<select
					name="jmrs_ext_participant_category"
					id="jmrs_ext_participant_category"
					required
					aria-required="true"
					<?php echo isset( $errors['participant_category'] ) ? 'aria-invalid="true" aria-describedby="jmrs-ext-error-participant_category"' : ''; ?>
				>
					<option value=""><?php echo esc_html__( 'Select category', 'jm-referral-system' ); ?></option>
					<?php foreach ( $category_labels as $cat_value => $cat_label ) : ?>
						<option value="<?php echo esc_attr( (string) $cat_value ); ?>" <?php selected( $val( $data, 'participant_category' ), (string) $cat_value ); ?>>
							<?php echo esc_html( (string) $cat_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'participant_category' ); ?>
			</div>

			<div class="jmrs-portal-field">
				<label for="jmrs_ext_meeting_role">
					<?php echo esc_html__( 'Meeting role', 'jm-referral-system' ); ?>
					<span class="jmrs-required" aria-hidden="true">*</span>
					<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
				</label>
				<input
					type="text"
					name="jmrs_ext_meeting_role"
					id="jmrs_ext_meeting_role"
					maxlength="150"
					required
					aria-required="true"
					value="<?php echo esc_attr( $val( $data, 'meeting_role' ) ); ?>"
					<?php echo isset( $errors['meeting_role'] ) ? 'aria-invalid="true" aria-describedby="jmrs-ext-error-meeting_role"' : ''; ?>
				/>
				<?php $field_error( $errors, 'meeting_role' ); ?>
			</div>
		<?php else : ?>
			<div class="jmrs-portal-field">
				<span class="jmrs-portal-field__label"><?php echo esc_html__( 'Participant name', 'jm-referral-system' ); ?></span>
				<p class="jmrs-portal-readonly"><?php echo esc_html( '' !== $val( $data, 'display_name' ) ? $val( $data, 'display_name' ) : '—' ); ?></p>
			</div>
			<div class="jmrs-portal-field">
				<span class="jmrs-portal-field__label"><?php echo esc_html__( 'Professional role', 'jm-referral-system' ); ?></span>
				<p class="jmrs-portal-readonly"><?php echo esc_html( '' !== $val( $data, 'professional_role' ) ? $val( $data, 'professional_role' ) : '—' ); ?></p>
			</div>
			<div class="jmrs-portal-field">
				<span class="jmrs-portal-field__label"><?php echo esc_html__( 'Organisation', 'jm-referral-system' ); ?></span>
				<p class="jmrs-portal-readonly"><?php echo esc_html( '' !== $val( $data, 'organisation' ) ? $val( $data, 'organisation' ) : '—' ); ?></p>
			</div>
			<?php if ( $can_view_contacts ) : ?>
				<div class="jmrs-portal-field">
					<span class="jmrs-portal-field__label"><?php echo esc_html__( 'Email', 'jm-referral-system' ); ?></span>
					<p class="jmrs-portal-readonly"><?php echo esc_html( '' !== $val( $data, 'email' ) ? $val( $data, 'email' ) : '—' ); ?></p>
				</div>
				<div class="jmrs-portal-field">
					<span class="jmrs-portal-field__label"><?php echo esc_html__( 'Telephone', 'jm-referral-system' ); ?></span>
					<p class="jmrs-portal-readonly"><?php echo esc_html( '' !== $val( $data, 'telephone' ) ? $val( $data, 'telephone' ) : '—' ); ?></p>
				</div>
			<?php endif; ?>
			<div class="jmrs-portal-field">
				<span class="jmrs-portal-field__label"><?php echo esc_html__( 'Participant category', 'jm-referral-system' ); ?></span>
				<p class="jmrs-portal-readonly">
					<?php
					$cat = $val( $data, 'participant_category' );
					echo esc_html( isset( $category_labels[ $cat ] ) ? (string) $category_labels[ $cat ] : ( '' !== $cat ? $cat : '—' ) );
					?>
				</p>
			</div>
			<div class="jmrs-portal-field">
				<span class="jmrs-portal-field__label"><?php echo esc_html__( 'Meeting role', 'jm-referral-system' ); ?></span>
				<p class="jmrs-portal-readonly"><?php echo esc_html( '' !== $val( $data, 'meeting_role' ) ? $val( $data, 'meeting_role' ) : '—' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="jmrs-portal-field">
			<label for="jmrs_ext_attendance_status">
				<?php echo esc_html__( 'Attendance status', 'jm-referral-system' ); ?>
				<span class="jmrs-required" aria-hidden="true">*</span>
				<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
			</label>
			<select
				name="jmrs_ext_attendance_status"
				id="jmrs_ext_attendance_status"
				required
				aria-required="true"
				<?php echo isset( $errors['attendance_status'] ) ? 'aria-invalid="true" aria-describedby="jmrs-ext-error-attendance_status"' : ''; ?>
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
		<button type="submit" class="jmrs-button jmrs-button--primary" name="jmrs_save_meeting_external_attendee" value="1">
			<?php
			echo esc_html(
				$is_add
					? __( 'Add participant', 'jm-referral-system' )
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
