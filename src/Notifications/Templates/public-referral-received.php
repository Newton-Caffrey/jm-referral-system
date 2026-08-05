<?php
/**
 * Email template: public website referral received (ops inbox).
 *
 * @package JMReferral
 *
 * @var string $referral_number
 * @var string $client_name
 * @var string $service_required
 * @var string $public_priority
 * @var string $referrer_name
 * @var string $referrer_type
 * @var string $view_url
 * @var string $site_name
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8" />
	<title><?php echo esc_html__( 'New website referral', 'jm-referral-system' ); ?></title>
</head>
<body style="font-family: Arial, sans-serif; color: #1d2327; line-height: 1.5;">
	<p><?php echo esc_html__( 'A new referral was submitted via the public website form.', 'jm-referral-system' ); ?></p>

	<table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse;">
		<tr>
			<td><strong><?php echo esc_html__( 'Referral Number', 'jm-referral-system' ); ?>:</strong></td>
			<td><?php echo esc_html( (string) $referral_number ); ?></td>
		</tr>
		<tr>
			<td><strong><?php echo esc_html__( 'Client Name', 'jm-referral-system' ); ?>:</strong></td>
			<td><?php echo esc_html( (string) $client_name ); ?></td>
		</tr>
		<tr>
			<td><strong><?php echo esc_html__( 'Service requested', 'jm-referral-system' ); ?>:</strong></td>
			<td><?php echo esc_html( (string) $service_required ); ?></td>
		</tr>
		<tr>
			<td><strong><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?>:</strong></td>
			<td><?php echo esc_html( (string) ( $public_priority ?? $priority ?? '' ) ); ?></td>
		</tr>
		<tr>
			<td><strong><?php echo esc_html__( 'Referrer', 'jm-referral-system' ); ?>:</strong></td>
			<td><?php echo esc_html( (string) $referrer_name ); ?></td>
		</tr>
		<?php if ( ! empty( $referrer_type ) ) : ?>
		<tr>
			<td><strong><?php echo esc_html__( 'Referrer type', 'jm-referral-system' ); ?>:</strong></td>
			<td><?php echo esc_html( (string) $referrer_type ); ?></td>
		</tr>
		<?php endif; ?>
	</table>

	<p>
		<a href="<?php echo esc_url( (string) $view_url ); ?>">
			<?php echo esc_html__( 'Open referral in admin', 'jm-referral-system' ); ?>
		</a>
	</p>

	<p style="color:#646970;font-size:12px;">
		<?php echo esc_html__( 'Care requirement details are available in the secure admin view.', 'jm-referral-system' ); ?>
		<br />
		<?php echo esc_html( (string) $site_name ); ?>
	</p>
</body>
</html>
