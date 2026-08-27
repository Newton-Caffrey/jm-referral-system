<?php
/**
 * Assessment appointment schedule / reschedule / needs-rescheduling UI.
 *
 * @package JMReferral
 *
 * @var int         $referral_id
 * @var array       $scheduling_panel
 * @var string      $form_action
 * @var string      $context admin|portal
 * @var string      $assessment_url
 * @var array       $scheduling_errors
 * @var bool        $force_reschedule_form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$scheduling_panel       = is_array( $scheduling_panel ?? null ) ? $scheduling_panel : array();
$referral_id            = isset( $referral_id ) ? absint( $referral_id ) : 0;
$form_action            = isset( $form_action ) ? (string) $form_action : '';
$context                = isset( $context ) ? (string) $context : 'admin';
$assessment_url         = isset( $assessment_url ) ? (string) $assessment_url : '';
$scheduling_errors      = is_array( $scheduling_errors ?? null ) ? $scheduling_errors : array();
$force_reschedule_form  = ! empty( $force_reschedule_form );
$is_portal              = 'portal' === $context;

if ( $referral_id <= 0 ) {
	return;
}

$can_schedule           = ! empty( $scheduling_panel['can_schedule'] );
$can_reschedule         = ! empty( $scheduling_panel['can_reschedule'] );
$can_needs_rescheduling = ! empty( $scheduling_panel['can_needs_rescheduling'] );
$has_appointment        = ! empty( $scheduling_panel['has_appointment'] );
$stage_slug             = (string) ( $scheduling_panel['stage_slug'] ?? '' );
$eligible_assessors     = is_array( $scheduling_panel['eligible_assessors'] ?? null ) ? $scheduling_panel['eligible_assessors'] : array();
$location_types         = is_array( $scheduling_panel['location_types'] ?? null ) ? $scheduling_panel['location_types'] : array();

$show_appointment_card = $has_appointment && in_array(
	$stage_slug,
	array( 'assessment_scheduled', 'assessment_to_schedule', 'assessment_review_required', 'package_cost_required' ),
	true
);
$show_reschedule_form = $can_reschedule && ( $force_reschedule_form || ! empty( $scheduling_errors ) );

if ( ! $can_schedule && ! $can_reschedule && ! $show_appointment_card ) {
	return;
}

$field_value = static function ( string $key ) use ( $scheduling_panel ): string {
	return (string) ( $scheduling_panel[ $key ] ?? '' );
};

$form_is_reschedule = ! $can_schedule && ( $show_reschedule_form || $can_reschedule );
?>
<div class="jmrs-assessment-scheduling" style="margin: 1.25em 0; padding: 1em 1.25em; border: 1px solid #2271b1; background: #f0f6fc;">
	<h2 style="margin-top: 0;"><?php echo esc_html__( 'Assessment Appointment', 'jm-referral-system' ); ?></h2>

	<?php if ( ! empty( $scheduling_panel['assessment_completed'] ) ) : ?>
		<p class="description" role="status">
			<?php echo esc_html__( 'This assessment has been completed and is read-only. Scheduling cannot be changed.', 'jm-referral-system' ); ?>
		</p>
	<?php endif; ?>

	<?php if ( $show_appointment_card && ! $can_schedule ) : ?>
		<p>
			<strong><?php echo esc_html__( 'Status:', 'jm-referral-system' ); ?></strong>
			<?php
			if ( ! empty( $scheduling_panel['assessment_completed'] ) && ! empty( $scheduling_panel['is_not_suitable'] ) ) {
				echo esc_html__( 'Completed — Not Suitable', 'jm-referral-system' );
			} elseif ( ! empty( $scheduling_panel['assessment_completed'] ) ) {
				echo esc_html(
					sprintf(
						/* translators: %s: outcome label */
						__( 'Completed — %s', 'jm-referral-system' ),
						(string) ( $scheduling_panel['assessment_outcome_label'] ?? '' )
					)
				);
			} elseif ( 'assessment_review_required' === $stage_slug ) {
				echo esc_html__( 'Outcome review required', 'jm-referral-system' );
			} elseif ( 'assessment_scheduled' === $stage_slug ) {
				echo esc_html__( 'Scheduled', 'jm-referral-system' );
			} elseif ( 'assessment_to_schedule' === $stage_slug ) {
				echo esc_html__( 'Needs rescheduling', 'jm-referral-system' );
			} else {
				echo esc_html__( 'Recorded', 'jm-referral-system' );
			}
			?>
		</p>
		<?php if ( '' !== (string) ( $scheduling_panel['not_suitable_next_action'] ?? '' ) ) : ?>
			<p>
				<strong><?php echo esc_html__( 'Next Action:', 'jm-referral-system' ); ?></strong>
				<?php echo esc_html( (string) $scheduling_panel['not_suitable_next_action'] ); ?>
			</p>
		<?php endif; ?>
		<p><strong><?php echo esc_html__( 'Date:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field_value( 'scheduled_date_display' ) ); ?></p>
		<p><strong><?php echo esc_html__( 'Time:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field_value( 'scheduled_time_display' ) ); ?></p>
		<p><strong><?php echo esc_html__( 'Assessor:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field_value( 'assessor_name' ) ); ?></p>
		<p>
			<strong><?php echo esc_html__( 'Location:', 'jm-referral-system' ); ?></strong>
			<?php echo esc_html( $field_value( 'location_type_label' ) ); ?>
			<?php if ( '' !== $field_value( 'location_name' ) ) : ?>
				— <?php echo esc_html( $field_value( 'location_name' ) ); ?>
			<?php endif; ?>
		</p>
		<?php if ( '' !== $field_value( 'location_address' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Address:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field_value( 'location_address' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $field_value( 'contact_name' ) || '' !== $field_value( 'contact_phone' ) || '' !== $field_value( 'contact_email' ) ) : ?>
			<p>
				<strong><?php echo esc_html__( 'Contact:', 'jm-referral-system' ); ?></strong>
				<?php echo esc_html( $field_value( 'contact_name' ) ); ?>
				<?php if ( '' !== $field_value( 'contact_phone' ) ) : ?>
					· <?php echo esc_html( $field_value( 'contact_phone' ) ); ?>
				<?php endif; ?>
				<?php if ( '' !== $field_value( 'contact_email' ) ) : ?>
					· <?php echo esc_html( $field_value( 'contact_email' ) ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php if ( '' !== $assessment_url ) : ?>
			<p>
				<?php if ( $is_portal ) : ?>
					<a class="jmrs-button" href="<?php echo esc_url( $assessment_url ); ?>"><?php echo esc_html__( 'Open Assessment', 'jm-referral-system' ); ?></a>
				<?php else : ?>
					<a class="button" href="<?php echo esc_url( $assessment_url ); ?>"><?php echo esc_html__( 'Open Assessment', 'jm-referral-system' ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $can_schedule || $show_reschedule_form ) : ?>
		<?php if ( $can_schedule ) : ?>
			<p><?php echo esc_html__( 'Schedule the assessment appointment. This advances the pipeline to Assessment Scheduled.', 'jm-referral-system' ); ?></p>
		<?php else : ?>
			<p><?php echo esc_html__( 'Update the appointment details. The pipeline stage remains Assessment Scheduled.', 'jm-referral-system' ); ?></p>
		<?php endif; ?>

		<p class="description"><?php echo esc_html__( 'Past appointment times are allowed for administrative correction, but please double-check before saving.', 'jm-referral-system' ); ?></p>

		<?php if ( ! empty( $scheduling_errors ) ) : ?>
			<ul style="color:#b32d2e;">
				<?php foreach ( $scheduling_errors as $err ) : ?>
					<li><?php echo esc_html( (string) $err ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<form method="post" action="<?php echo '' !== $form_action ? esc_url( $form_action ) : ''; ?>">
			<?php
			$nonce_action = $form_is_reschedule ? 'jmrs_reschedule_assessment_' . $referral_id : 'jmrs_schedule_assessment_' . $referral_id;
			$nonce_name   = $form_is_reschedule ? 'jmrs_reschedule_assessment_nonce' : 'jmrs_schedule_assessment_nonce';
			wp_nonce_field( $nonce_action, $nonce_name );
			?>
			<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

			<p>
				<label for="jmrs_scheduled_date"><strong><?php echo esc_html__( 'Assessment Date', 'jm-referral-system' ); ?></strong></label><br />
				<input type="date" name="jmrs_scheduled_date" id="jmrs_scheduled_date" required value="<?php echo esc_attr( $field_value( 'scheduled_date' ) ); ?>" />
			</p>
			<p>
				<label for="jmrs_scheduled_time"><strong><?php echo esc_html__( 'Assessment Time', 'jm-referral-system' ); ?></strong></label><br />
				<input type="time" name="jmrs_scheduled_time" id="jmrs_scheduled_time" required value="<?php echo esc_attr( $field_value( 'scheduled_time' ) ); ?>" />
			</p>
			<p>
				<label for="jmrs_assessor_user_id"><strong><?php echo esc_html__( 'Assessor', 'jm-referral-system' ); ?></strong></label><br />
				<select name="jmrs_assessor_user_id" id="jmrs_assessor_user_id" required>
					<option value=""><?php echo esc_html__( '— Select —', 'jm-referral-system' ); ?></option>
					<?php foreach ( $eligible_assessors as $assessor ) : ?>
						<option value="<?php echo esc_attr( (string) ( $assessor['id'] ?? '' ) ); ?>" <?php selected( (int) $field_value( 'assessor_user_id' ), (int) ( $assessor['id'] ?? 0 ) ); ?>>
							<?php echo esc_html( (string) ( $assessor['display_name'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="jmrs_assessment_location_type"><strong><?php echo esc_html__( 'Location Type', 'jm-referral-system' ); ?></strong></label><br />
				<select name="jmrs_assessment_location_type" id="jmrs_assessment_location_type" required>
					<option value=""><?php echo esc_html__( '— Select —', 'jm-referral-system' ); ?></option>
					<?php foreach ( $location_types as $type_value => $type_label ) : ?>
						<option value="<?php echo esc_attr( (string) $type_value ); ?>" <?php selected( $field_value( 'location_type' ), (string) $type_value ); ?>>
							<?php echo esc_html( (string) $type_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="jmrs_assessment_location_name"><strong><?php echo esc_html__( 'Location Name', 'jm-referral-system' ); ?></strong></label><br />
				<input type="text" name="jmrs_assessment_location_name" id="jmrs_assessment_location_name" maxlength="190" required class="<?php echo $is_portal ? '' : 'regular-text'; ?>" value="<?php echo esc_attr( $field_value( 'location_name' ) ); ?>" />
			</p>
			<p>
				<label for="jmrs_assessment_location_address"><?php echo esc_html__( 'Location Address', 'jm-referral-system' ); ?></label><br />
				<textarea name="jmrs_assessment_location_address" id="jmrs_assessment_location_address" rows="2" maxlength="2000" class="<?php echo $is_portal ? '' : 'large-text'; ?>"><?php echo esc_textarea( $field_value( 'location_address' ) ); ?></textarea>
			</p>
			<p>
				<label for="jmrs_assessment_contact_name"><?php echo esc_html__( 'Contact Name', 'jm-referral-system' ); ?></label><br />
				<input type="text" name="jmrs_assessment_contact_name" id="jmrs_assessment_contact_name" maxlength="190" class="<?php echo $is_portal ? '' : 'regular-text'; ?>" value="<?php echo esc_attr( $field_value( 'contact_name' ) ); ?>" />
			</p>
			<p>
				<label for="jmrs_assessment_contact_phone"><?php echo esc_html__( 'Contact Phone', 'jm-referral-system' ); ?></label><br />
				<input type="text" name="jmrs_assessment_contact_phone" id="jmrs_assessment_contact_phone" maxlength="50" class="<?php echo $is_portal ? '' : 'regular-text'; ?>" value="<?php echo esc_attr( $field_value( 'contact_phone' ) ); ?>" />
			</p>
			<p>
				<label for="jmrs_assessment_contact_email"><?php echo esc_html__( 'Contact Email', 'jm-referral-system' ); ?></label><br />
				<input type="email" name="jmrs_assessment_contact_email" id="jmrs_assessment_contact_email" maxlength="190" class="<?php echo $is_portal ? '' : 'regular-text'; ?>" value="<?php echo esc_attr( $field_value( 'contact_email' ) ); ?>" />
			</p>
			<p>
				<label for="jmrs_scheduling_notes"><?php echo esc_html__( 'Scheduling Notes', 'jm-referral-system' ); ?></label><br />
				<textarea name="jmrs_scheduling_notes" id="jmrs_scheduling_notes" rows="2" maxlength="2000" class="<?php echo $is_portal ? '' : 'large-text'; ?>"><?php echo esc_textarea( $field_value( 'scheduling_notes' ) ); ?></textarea>
			</p>

			<?php
			$submit_name  = $form_is_reschedule ? 'jmrs_reschedule_assessment' : 'jmrs_schedule_assessment';
			$submit_label = $form_is_reschedule
				? __( 'Reschedule Assessment', 'jm-referral-system' )
				: __( 'Schedule Assessment', 'jm-referral-system' );
			if ( $is_portal ) {
				echo '<button type="submit" name="' . esc_attr( $submit_name ) . '" value="1" class="jmrs-button jmrs-button--primary">';
				echo esc_html( $submit_label );
				echo '</button>';
			} else {
				submit_button( $submit_label, 'primary', $submit_name, false );
			}
			?>
		</form>
	<?php elseif ( $can_reschedule ) : ?>
		<details style="margin-top: 0.75em;">
			<summary style="cursor:pointer;font-weight:600;"><?php echo esc_html__( 'Reschedule Assessment', 'jm-referral-system' ); ?></summary>
			<form method="post" action="<?php echo '' !== $form_action ? esc_url( $form_action ) : ''; ?>" style="margin-top: 0.75em;">
				<?php wp_nonce_field( 'jmrs_reschedule_assessment_' . $referral_id, 'jmrs_reschedule_assessment_nonce' ); ?>
				<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
				<p class="description"><?php echo esc_html__( 'Past appointment times are allowed for administrative correction, but please double-check before saving.', 'jm-referral-system' ); ?></p>
				<p>
					<label for="jmrs_scheduled_date_r"><strong><?php echo esc_html__( 'Assessment Date', 'jm-referral-system' ); ?></strong></label><br />
					<input type="date" name="jmrs_scheduled_date" id="jmrs_scheduled_date_r" required value="<?php echo esc_attr( $field_value( 'scheduled_date' ) ); ?>" />
				</p>
				<p>
					<label for="jmrs_scheduled_time_r"><strong><?php echo esc_html__( 'Assessment Time', 'jm-referral-system' ); ?></strong></label><br />
					<input type="time" name="jmrs_scheduled_time" id="jmrs_scheduled_time_r" required value="<?php echo esc_attr( $field_value( 'scheduled_time' ) ); ?>" />
				</p>
				<p>
					<label for="jmrs_assessor_user_id_r"><strong><?php echo esc_html__( 'Assessor', 'jm-referral-system' ); ?></strong></label><br />
					<select name="jmrs_assessor_user_id" id="jmrs_assessor_user_id_r" required>
						<option value=""><?php echo esc_html__( '— Select —', 'jm-referral-system' ); ?></option>
						<?php foreach ( $eligible_assessors as $assessor ) : ?>
							<option value="<?php echo esc_attr( (string) ( $assessor['id'] ?? '' ) ); ?>" <?php selected( (int) $field_value( 'assessor_user_id' ), (int) ( $assessor['id'] ?? 0 ) ); ?>>
								<?php echo esc_html( (string) ( $assessor['display_name'] ?? '' ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="jmrs_assessment_location_type_r"><strong><?php echo esc_html__( 'Location Type', 'jm-referral-system' ); ?></strong></label><br />
					<select name="jmrs_assessment_location_type" id="jmrs_assessment_location_type_r" required>
						<option value=""><?php echo esc_html__( '— Select —', 'jm-referral-system' ); ?></option>
						<?php foreach ( $location_types as $type_value => $type_label ) : ?>
							<option value="<?php echo esc_attr( (string) $type_value ); ?>" <?php selected( $field_value( 'location_type' ), (string) $type_value ); ?>>
								<?php echo esc_html( (string) $type_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="jmrs_assessment_location_name_r"><strong><?php echo esc_html__( 'Location Name', 'jm-referral-system' ); ?></strong></label><br />
					<input type="text" name="jmrs_assessment_location_name" id="jmrs_assessment_location_name_r" maxlength="190" required class="<?php echo $is_portal ? '' : 'regular-text'; ?>" value="<?php echo esc_attr( $field_value( 'location_name' ) ); ?>" />
				</p>
				<p>
					<label for="jmrs_assessment_location_address_r"><?php echo esc_html__( 'Location Address', 'jm-referral-system' ); ?></label><br />
					<textarea name="jmrs_assessment_location_address" id="jmrs_assessment_location_address_r" rows="2" maxlength="2000" class="<?php echo $is_portal ? '' : 'large-text'; ?>"><?php echo esc_textarea( $field_value( 'location_address' ) ); ?></textarea>
				</p>
				<p>
					<label for="jmrs_assessment_contact_name_r"><?php echo esc_html__( 'Contact Name', 'jm-referral-system' ); ?></label><br />
					<input type="text" name="jmrs_assessment_contact_name" id="jmrs_assessment_contact_name_r" maxlength="190" class="<?php echo $is_portal ? '' : 'regular-text'; ?>" value="<?php echo esc_attr( $field_value( 'contact_name' ) ); ?>" />
				</p>
				<p>
					<label for="jmrs_assessment_contact_phone_r"><?php echo esc_html__( 'Contact Phone', 'jm-referral-system' ); ?></label><br />
					<input type="text" name="jmrs_assessment_contact_phone" id="jmrs_assessment_contact_phone_r" maxlength="50" class="<?php echo $is_portal ? '' : 'regular-text'; ?>" value="<?php echo esc_attr( $field_value( 'contact_phone' ) ); ?>" />
				</p>
				<p>
					<label for="jmrs_assessment_contact_email_r"><?php echo esc_html__( 'Contact Email', 'jm-referral-system' ); ?></label><br />
					<input type="email" name="jmrs_assessment_contact_email" id="jmrs_assessment_contact_email_r" maxlength="190" class="<?php echo $is_portal ? '' : 'regular-text'; ?>" value="<?php echo esc_attr( $field_value( 'contact_email' ) ); ?>" />
				</p>
				<p>
					<label for="jmrs_scheduling_notes_r"><?php echo esc_html__( 'Scheduling Notes', 'jm-referral-system' ); ?></label><br />
					<textarea name="jmrs_scheduling_notes" id="jmrs_scheduling_notes_r" rows="2" maxlength="2000" class="<?php echo $is_portal ? '' : 'large-text'; ?>"><?php echo esc_textarea( $field_value( 'scheduling_notes' ) ); ?></textarea>
				</p>
				<?php
				if ( $is_portal ) {
					echo '<button type="submit" name="jmrs_reschedule_assessment" value="1" class="jmrs-button jmrs-button--primary">';
					echo esc_html__( 'Reschedule Assessment', 'jm-referral-system' );
					echo '</button>';
				} else {
					submit_button( __( 'Reschedule Assessment', 'jm-referral-system' ), 'primary', 'jmrs_reschedule_assessment', false );
				}
				?>
			</form>
		</details>
	<?php endif; ?>

	<?php if ( $can_needs_rescheduling ) : ?>
		<form method="post" action="<?php echo '' !== $form_action ? esc_url( $form_action ) : ''; ?>" style="margin-top: 1em; padding-top: 0.75em; border-top: 1px solid #c3c4c7;">
			<?php wp_nonce_field( 'jmrs_assessment_needs_rescheduling_' . $referral_id, 'jmrs_assessment_needs_rescheduling_nonce' ); ?>
			<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
			<p>
				<label for="jmrs_needs_reschedule_reason"><strong><?php echo esc_html__( 'Mark as Needs Rescheduling', 'jm-referral-system' ); ?></strong></label><br />
				<input
					type="text"
					name="jmrs_needs_reschedule_reason"
					id="jmrs_needs_reschedule_reason"
					class="<?php echo $is_portal ? '' : 'regular-text'; ?>"
					maxlength="500"
					required
					placeholder="<?php echo esc_attr__( 'Short operational reason', 'jm-referral-system' ); ?>"
				/>
			</p>
			<?php
			if ( $is_portal ) {
				echo '<button type="submit" name="jmrs_assessment_needs_rescheduling" value="1" class="jmrs-button">';
				echo esc_html__( 'Mark as Needs Rescheduling', 'jm-referral-system' );
				echo '</button>';
			} else {
				submit_button( __( 'Mark as Needs Rescheduling', 'jm-referral-system' ), 'secondary', 'jmrs_assessment_needs_rescheduling', false );
			}
			?>
		</form>
	<?php endif; ?>
</div>
