<?php
/**
 * Cancel meeting confirmation (Phase 4B.2.2).
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral    = is_array( $referral ?? null ) ? $referral : array();
$referral_id = absint( $referral['id'] ?? 0 );
$meeting     = is_array( $meeting ?? null ) ? $meeting : array();
$meeting_id  = absint( $meeting_id ?? 0 );
$form_action = (string) ( $form_action ?? '' );
$cancel_url  = (string) ( $cancel_url ?? '' );
$status      = (string) ( $meeting['status'] ?? '' );
?>
<section class="jmrs-portal-section jmrs-portal-panel">
	<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Cancel meeting', 'jm-referral-system' ); ?></h2>
	<div class="jmrs-portal-notice jmrs-portal-notice--warning" role="status">
		<p>
			<strong><?php echo esc_html__( 'This will cancel the meeting.', 'jm-referral-system' ); ?></strong>
			<?php echo esc_html__( 'The meeting record and attendees are kept for history. Completed meetings cannot be cancelled. This action cannot reopen a cancelled meeting later through normal workflows.', 'jm-referral-system' ); ?>
		</p>
	</div>
	<p>
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: meeting type, 2: status */
				__( 'Meeting: %1$s (%2$s)', 'jm-referral-system' ),
				(string) ( \JMReferral\Meeting\ReferralMeeting::type_label( (string) ( $meeting['meeting_type'] ?? '' ) ) ),
				(string) ( \JMReferral\Meeting\ReferralMeeting::status_label( $status ) )
			)
		);
		?>
	</p>
</section>

<form class="jmrs-portal-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
	<?php wp_nonce_field( 'jmrs_save_meeting_' . $referral_id, 'jmrs_meeting_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
	<input type="hidden" name="jmrs_meeting_id" value="<?php echo esc_attr( (string) $meeting_id ); ?>" />

	<div class="jmrs-portal-actions">
		<button type="submit" class="jmrs-button jmrs-button--danger" name="jmrs_save_meeting" value="1">
			<?php echo esc_html__( 'Confirm cancellation', 'jm-referral-system' ); ?>
		</button>
		<?php if ( '' !== $cancel_url ) : ?>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>"><?php echo esc_html__( 'Keep meeting', 'jm-referral-system' ); ?></a>
		<?php endif; ?>
	</div>
</form>
