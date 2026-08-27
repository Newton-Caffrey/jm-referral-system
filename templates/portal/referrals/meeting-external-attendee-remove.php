<?php
/**
 * Confirm removal of an external meeting participant (Phase 4B.2.4).
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
$attendee          = is_array( $attendee ?? null ) ? $attendee : array();
$can_view_contacts = ! empty( $can_view_contacts );
$form_action       = (string) ( $form_action ?? '' );
$cancel_url        = (string) ( $cancel_url ?? '' );

$display_name = (string) ( $attendee['display_name'] ?? '' );
$pro_role     = (string) ( $attendee['professional_role'] ?? '' );
$organisation = (string) ( $attendee['organisation'] ?? '' );
$meeting_role = (string) ( $attendee['meeting_role'] ?? '' );
$attendance   = \JMReferral\Meeting\MeetingAttendee::attendance_status_label( (string) ( $attendee['attendance_status'] ?? '' ) );
$category     = \JMReferral\Meeting\MeetingAttendee::category_label( (string) ( $attendee['participant_category'] ?? '' ) );
$email        = $can_view_contacts ? (string) ( $attendee['email'] ?? '' ) : '';
$telephone    = $can_view_contacts ? (string) ( $attendee['telephone'] ?? '' ) : '';
?>
<section class="jmrs-portal-section jmrs-portal-panel">
	<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Remove external participant', 'jm-referral-system' ); ?></h2>
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
			<dt><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $display_name ? $display_name : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Professional role', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $pro_role ? $pro_role : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Organisation', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $organisation ? $organisation : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Category', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $category ? $category : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Meeting role', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $meeting_role ? $meeting_role : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Attendance', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $attendance ? $attendance : '—' ); ?></dd>
		</div>
		<?php if ( $can_view_contacts ) : ?>
			<div>
				<dt><?php echo esc_html__( 'Email', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $email ? $email : '—' ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Telephone', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $telephone ? $telephone : '—' ); ?></dd>
			</div>
		<?php endif; ?>
	</dl>
	<p>
		<?php echo esc_html__( 'This removes the participant from the meeting. The activity timeline will keep an audit entry that the removal occurred. No WordPress user account is involved or deleted.', 'jm-referral-system' ); ?>
	</p>
</section>

<form class="jmrs-portal-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
	<?php wp_nonce_field( 'jmrs_save_meeting_external_attendee_' . $referral_id, 'jmrs_meeting_external_attendee_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
	<input type="hidden" name="jmrs_meeting_id" value="<?php echo esc_attr( (string) $meeting_id ); ?>" />
	<input type="hidden" name="jmrs_attendee_id" value="<?php echo esc_attr( (string) $attendee_id ); ?>" />

	<div class="jmrs-portal-actions">
		<button type="submit" class="jmrs-button jmrs-button--danger" name="jmrs_save_meeting_external_attendee" value="1">
			<?php echo esc_html__( 'Confirm remove', 'jm-referral-system' ); ?>
		</button>
		<?php if ( '' !== $cancel_url ) : ?>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
				<?php echo esc_html__( 'Back to meeting', 'jm-referral-system' ); ?>
			</a>
		<?php endif; ?>
	</div>
</form>
