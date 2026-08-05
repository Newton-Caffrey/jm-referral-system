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
 * @var array<string, mixed> $branding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral_number = isset( $referral_number ) ? (string) $referral_number : '';
$upload_partial  = ! empty( $upload_partial );
$generic_success = ! empty( $generic_success );
$success_message = isset( $success_message ) ? trim( (string) $success_message ) : '';
if ( '' === $success_message ) {
	$success_message = (string) \JMReferral\Frontend\PublicReferralSettings::defaults()['success_message'];
}
$branding        = is_array( $branding ?? null ) ? $branding : \JMReferral\Frontend\PublicBranding::all();
$company_name    = (string) ( $branding['company_name'] ?? \JMReferral\Frontend\PublicBranding::company_name() );
$primary         = (string) ( $branding['primary_colour'] ?? \JMReferral\Frontend\PublicBranding::DEFAULT_PRIMARY_COLOUR );
$contact_phone   = (string) ( $branding['contact_phone'] ?? '' );
$contact_email   = (string) ( $branding['contact_email'] ?? '' );
$next_steps_raw  = (string) ( $branding['success_next_steps'] ?? '' );
$next_steps      = array_values(
	array_filter(
		array_map( 'trim', preg_split( '/\r\n|\r|\n/', $next_steps_raw ) ?: array() )
	)
);
?>
<div class="jmrs-public-referral jmrs-public-referral--success" style="<?php echo esc_attr( '--jmrs-primary:' . $primary . ';' ); ?>">
	<div class="jmrs-public-referral__receipt" role="status" tabindex="-1" id="jmrs-public-success">
		<h2 class="jmrs-public-referral__title"><?php echo esc_html__( 'Referral Received', 'jm-referral-system' ); ?></h2>
		<p class="jmrs-public-referral__company"><?php echo esc_html( $company_name ); ?></p>
		<p><?php echo esc_html( $success_message ); ?></p>

		<?php if ( '' !== $referral_number ) : ?>
			<p class="jmrs-public-referral__reference">
				<strong><?php echo esc_html__( 'Your reference number:', 'jm-referral-system' ); ?></strong>
				<code class="jmrs-public-referral__ref-code"><?php echo esc_html( $referral_number ); ?></code>
			</p>
			<p class="jmrs-public-referral__help">
				<?php echo esc_html__( 'Please keep this reference for your records. You may print or save this page.', 'jm-referral-system' ); ?>
			</p>
		<?php elseif ( $generic_success ) : ?>
			<p class="jmrs-public-referral__help">
				<?php echo esc_html__( 'Your referral was received. If you need a reference number, please contact us using the details below or check your confirmation email.', 'jm-referral-system' ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $upload_partial ) : ?>
			<p class="jmrs-public-referral__notice-inline">
				<?php echo esc_html__( 'Your referral was saved, but one or more documents could not be uploaded. Our team may contact you if supporting files are still needed.', 'jm-referral-system' ); ?>
			</p>
		<?php endif; ?>

		<h3 class="jmrs-public-referral__subtitle"><?php echo esc_html__( 'What happens next?', 'jm-referral-system' ); ?></h3>
		<?php if ( ! empty( $next_steps ) ) : ?>
			<ul class="jmrs-public-referral__next-steps">
				<?php foreach ( $next_steps as $step_line ) : ?>
					<li><?php echo esc_html( $step_line ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( '' !== $contact_phone || '' !== $contact_email ) : ?>
			<div class="jmrs-public-referral__contact">
				<h3 class="jmrs-public-referral__subtitle"><?php echo esc_html__( 'Contact', 'jm-referral-system' ); ?></h3>
				<?php if ( '' !== $contact_phone ) : ?>
					<p><?php echo esc_html__( 'Phone:', 'jm-referral-system' ); ?> <a href="<?php echo esc_url( 'tel:' . preg_replace( '/\s+/', '', $contact_phone ) ); ?>"><?php echo esc_html( $contact_phone ); ?></a></p>
				<?php endif; ?>
				<?php if ( '' !== $contact_email && is_email( $contact_email ) ) : ?>
					<p><?php echo esc_html__( 'Email:', 'jm-referral-system' ); ?> <a href="<?php echo esc_url( 'mailto:' . $contact_email ); ?>"><?php echo esc_html( $contact_email ); ?></a></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="jmrs-public-referral__actions">
			<button type="button" class="jmrs-public-referral__btn jmrs-public-referral__btn--primary" data-jmrs-print>
				<?php echo esc_html__( 'Print / Save Reference', 'jm-referral-system' ); ?>
			</button>
		</div>

		<p class="jmrs-public-referral__help">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: company name */
					__( 'Thank you for contacting %s.', 'jm-referral-system' ),
					$company_name
				)
			);
			?>
		</p>
	</div>
</div>
