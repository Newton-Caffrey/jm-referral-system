<?php
/**
 * Portal 404.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dashboard_url = (string) ( $dashboard_url ?? '' );
$logout_url    = (string) ( $logout_url ?? '' );
?>
<section class="jmrs-portal-section jmrs-portal-error" aria-labelledby="jmrs-portal-404-title">
	<h2 id="jmrs-portal-404-title" class="jmrs-portal-section__title"><?php echo esc_html__( 'Not found', 'jm-referral-system' ); ?></h2>
	<p><?php echo esc_html__( 'The page you requested could not be found, or you do not have access to it.', 'jm-referral-system' ); ?></p>
	<p class="jmrs-portal-actions">
		<?php if ( '' !== $dashboard_url ) : ?>
			<a class="jmrs-portal-btn jmrs-portal-btn--primary" href="<?php echo esc_url( $dashboard_url ); ?>"><?php echo esc_html__( 'Return to Dashboard', 'jm-referral-system' ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $logout_url ) : ?>
			<a class="jmrs-portal-btn" href="<?php echo esc_url( $logout_url ); ?>"><?php echo esc_html__( 'Log out', 'jm-referral-system' ); ?></a>
		<?php endif; ?>
	</p>
</section>
