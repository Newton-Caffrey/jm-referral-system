<?php
/**
 * Public referral success / receipt.
 *
 * @package JMReferral
 *
 * @var string $referral_number
 * @var bool $upload_partial
 * @var string $success_message
 * @var array<string, mixed> $settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral_number = isset( $referral_number ) ? (string) $referral_number : '';
$upload_partial  = ! empty( $upload_partial );
$success_message = isset( $success_message ) ? (string) $success_message : '';
$site_name       = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
?>
<div class="jmrs-public-referral jmrs-public-referral--success">
	<div class="jmrs-public-referral__notice jmrs-public-referral__notice--success" role="status" tabindex="-1" id="jmrs-public-success">
		<h2 class="jmrs-public-referral__title"><?php echo esc_html__( 'Referral received', 'jm-referral-system' ); ?></h2>
		<p><?php echo esc_html( $success_message ); ?></p>

		<?php if ( '' !== $referral_number ) : ?>
			<p class="jmrs-public-referral__reference">
				<strong><?php echo esc_html__( 'Your reference number:', 'jm-referral-system' ); ?></strong>
				<code class="jmrs-public-referral__ref-code"><?php echo esc_html( $referral_number ); ?></code>
			</p>
			<p class="jmrs-public-referral__help">
				<?php echo esc_html__( 'Please keep this reference for your records. You may print or save this page.', 'jm-referral-system' ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $upload_partial ) : ?>
			<p class="jmrs-public-referral__notice-inline">
				<?php echo esc_html__( 'Your referral was saved, but one or more documents could not be uploaded. Our team may contact you if supporting files are still needed.', 'jm-referral-system' ); ?>
			</p>
		<?php endif; ?>

		<h3 class="jmrs-public-referral__subtitle"><?php echo esc_html__( 'What happens next', 'jm-referral-system' ); ?></h3>
		<ul class="jmrs-public-referral__next-steps">
			<li><?php echo esc_html__( 'Our team will review your referral.', 'jm-referral-system' ); ?></li>
			<li><?php echo esc_html__( 'We may contact you using the details you provided.', 'jm-referral-system' ); ?></li>
			<li><?php echo esc_html__( 'Please quote your reference number in any follow-up.', 'jm-referral-system' ); ?></li>
		</ul>

		<div class="jmrs-public-referral__actions">
			<button type="button" class="jmrs-public-referral__print" onclick="window.print();">
				<?php echo esc_html__( 'Print / Save reference', 'jm-referral-system' ); ?>
			</button>
		</div>

		<p class="jmrs-public-referral__help">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: site name */
					__( 'Thank you for contacting %s.', 'jm-referral-system' ),
					$site_name
				)
			);
			?>
		</p>
	</div>
</div>
