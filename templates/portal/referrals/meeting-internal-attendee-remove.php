<?php
/**
 * Confirm removal of an internal meeting attendee (Phase 4B.2.3).
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
$staff_display_name = (string) ( $staff_display_name ?? '' );
$meeting_role       = (string) ( $meeting_role ?? '' );
$attendance_label   = (string) ( $attendance_label ?? '' );
$form_action        = (string) ( $form_action ?? '' );
$cancel_url         = (string) ( $cancel_url ?? '' );
?>
<section class="jmrs-portal-section jmrs-portal-panel">
	<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Remove internal attendee', 'jm-referral-system' ); ?></h2>
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
	<dl class="jmrs-meeting-detail__dl">
		<div>
			<dt><?php echo esc_html__( 'Staff', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $staff_display_name ? $staff_display_name : __( 'Unavailable user', 'jm-referral-system' ) ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Meeting role', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $meeting_role ? $meeting_role : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Attendance', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $attendance_label ? $attendance_label : '—' ); ?></dd>
		</div>
	</dl>
	<p>
		<?php echo esc_html__( 'This removes the attendee from the meeting. The activity timeline will keep an audit entry that the removal occurred. This does not delete the WordPress user account.', 'jm-referral-system' ); ?>
	</p>
</section>

<form class="jmrs-portal-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
	<?php wp_nonce_field( 'jmrs_save_meeting_attendee_' . $referral_id, 'jmrs_meeting_attendee_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
	<input type="hidden" name="jmrs_meeting_id" value="<?php echo esc_attr( (string) $meeting_id ); ?>" />
	<input type="hidden" name="jmrs_attendee_id" value="<?php echo esc_attr( (string) $attendee_id ); ?>" />

	<div class="jmrs-portal-actions">
		<button type="submit" class="jmrs-button jmrs-button--danger" name="jmrs_save_meeting_attendee" value="1">
			<?php echo esc_html__( 'Confirm remove', 'jm-referral-system' ); ?>
		</button>
		<?php if ( '' !== $cancel_url ) : ?>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
				<?php echo esc_html__( 'Back to meeting', 'jm-referral-system' ); ?>
			</a>
		<?php endif; ?>
	</div>
</form>
