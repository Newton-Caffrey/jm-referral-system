<?php
/**
 * Client summary header for referral-scoped portal pages.
 *
 * Presentation-only: reads variables already computed by the including
 * template (referrals/view.php and similar clinical templates) and falls
 * back gracefully when a variable is not present.
 *
 * @var array<string, mixed> $referral
 * @var string               $client_name
 * @var string               $client_dob_display
 * @var string               $address_display
 * @var bool                 $is_archived
 * @var string               $workflow_stage_name
 * @var string               $service_name
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral            = is_array( $referral ?? null ) ? $referral : array();
$client_name         = (string) ( $client_name ?? '' );
if ( '' === $client_name ) {
	$client_name = trim( (string) ( $referral['client_first_name'] ?? '' ) . ' ' . (string) ( $referral['client_last_name'] ?? '' ) );
}
if ( '' === $client_name ) {
	$client_name = (string) ( $referral['client_name'] ?? __( 'Unnamed client', 'jm-referral-system' ) );
}
$client_dob_display  = (string) ( $client_dob_display ?? '' );
$address_display     = (string) ( $address_display ?? '' );
$is_archived         = ! empty( $is_archived );
$workflow_stage_name = (string) ( $workflow_stage_name ?? '' );
$service_name        = (string) ( $service_name ?? '' );
$referral_number     = (string) ( $referral['referral_number'] ?? '' );
$status_key          = (string) ( $referral['status'] ?? '' );
$priority_key        = (string) ( $referral['priority'] ?? '' );
$status_label        = '' !== $status_key ? ucfirst( str_replace( '_', ' ', $status_key ) ) : '';
$priority_label      = '' !== $priority_key ? ucfirst( $priority_key ) : '';
?>
<div class="jmrs-portal-client-summary">
	<div class="jmrs-portal-client-summary__main">
		<h2 class="jmrs-portal-client-summary__name">
			<?php echo esc_html( $client_name ); ?>
			<?php if ( $is_archived ) : ?>
				<span class="jmrs-portal-badge jmrs-portal-badge--archive"><?php echo esc_html__( 'Archived', 'jm-referral-system' ); ?></span>
			<?php endif; ?>
		</h2>
		<p class="jmrs-portal-client-summary__meta">
			<?php if ( '' !== $referral_number ) : ?>
				<span class="jmrs-portal-client-summary__number"><?php echo esc_html( $referral_number ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $status_label ) : ?>
				<span class="jmrs-portal-badge"><?php echo esc_html( $status_label ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $priority_label ) : ?>
				<span class="jmrs-portal-badge jmrs-portal-badge--priority"><?php echo esc_html( $priority_label ); ?></span>
			<?php endif; ?>
		</p>
	</div>
	<dl class="jmrs-portal-client-summary__facts">
		<?php if ( '' !== $client_dob_display ) : ?>
			<div>
				<dt><?php echo esc_html__( 'Date of birth', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $client_dob_display ); ?></dd>
			</div>
		<?php endif; ?>
		<?php if ( '' !== $address_display && '—' !== $address_display ) : ?>
			<div>
				<dt><?php echo esc_html__( 'Address', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $address_display ); ?></dd>
			</div>
		<?php endif; ?>
		<?php if ( '' !== $workflow_stage_name ) : ?>
			<div>
				<dt><?php echo esc_html__( 'Stage', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $workflow_stage_name ); ?></dd>
			</div>
		<?php endif; ?>
		<?php if ( '' !== $service_name ) : ?>
			<div>
				<dt><?php echo esc_html__( 'Service', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $service_name ); ?></dd>
			</div>
		<?php endif; ?>
	</dl>
</div>
