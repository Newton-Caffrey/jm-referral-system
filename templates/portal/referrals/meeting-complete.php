<?php
/**
 * Complete meeting confirmation (Phase 4B.2.2).
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
$data               = is_array( $data ?? null ) ? $data : array();
$errors             = is_array( $errors ?? null ) ? $errors : array();
$form_action        = (string) ( $form_action ?? '' );
$cancel_url         = (string) ( $cancel_url ?? '' );
$attendance_warning       = ! empty( $attendance_warning );
$attendance_warning_count = absint( $attendance_warning_count ?? 0 );

$val = static function ( array $data, string $key ): string {
	return (string) ( $data[ $key ] ?? '' );
};
?>
<?php if ( ! empty( $errors ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert">
		<ul>
			<?php foreach ( $errors as $message ) : ?>
				<li><?php echo esc_html( (string) $message ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php if ( $attendance_warning ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--warning" role="status">
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: count of attendees still invited or confirmed */
					_n(
						'%d attendee attendance record is not finalised. You may still complete the meeting and update attendance afterward.',
						'%d attendee attendance records are not finalised. You may still complete the meeting and update attendance afterward.',
						$attendance_warning_count,
						'jm-referral-system'
					),
					$attendance_warning_count
				)
			);
			?>
		</p>
	</div>
<?php endif; ?>

<section class="jmrs-portal-section jmrs-portal-panel">
	<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Complete meeting', 'jm-referral-system' ); ?></h2>
	<p>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: meeting type */
				__( 'Mark this scheduled meeting (%s) as completed. Scheduled details and attendees are preserved.', 'jm-referral-system' ),
				(string) ( \JMReferral\Meeting\ReferralMeeting::type_label( (string) ( $meeting['meeting_type'] ?? '' ) ) )
			)
		);
		?>
	</p>
	<p class="description">
		<?php echo esc_html__( 'Outcome is an operational meeting summary, not a clinical assessment.', 'jm-referral-system' ); ?>
	</p>
</section>

<form class="jmrs-portal-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
	<?php wp_nonce_field( 'jmrs_save_meeting_' . $referral_id, 'jmrs_meeting_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
	<input type="hidden" name="jmrs_meeting_id" value="<?php echo esc_attr( (string) $meeting_id ); ?>" />

	<div class="jmrs-portal-field">
		<label for="jmrs_meeting_outcome"><?php echo esc_html__( 'Outcome (optional)', 'jm-referral-system' ); ?></label>
		<input type="text" name="jmrs_meeting_outcome" id="jmrs_meeting_outcome" maxlength="255" value="<?php echo esc_attr( $val( $data, 'outcome' ) ); ?>" />
	</div>

	<div class="jmrs-portal-actions">
		<button type="submit" class="jmrs-button jmrs-button--primary" name="jmrs_save_meeting" value="1">
			<?php echo esc_html__( 'Confirm complete', 'jm-referral-system' ); ?>
		</button>
		<?php if ( '' !== $cancel_url ) : ?>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>"><?php echo esc_html__( 'Back to meeting', 'jm-referral-system' ); ?></a>
		<?php endif; ?>
	</div>
</form>
