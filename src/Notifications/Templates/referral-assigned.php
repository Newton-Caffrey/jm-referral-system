<?php
/**
 * Email template: referral assigned or reassigned.
 *
 * @package JMReferral
 *
 * @var string $referral_number
 * @var string $client_name
 * @var string $service_required
 * @var string $priority
 * @var string $status
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
	<title><?php echo esc_html__( 'Referral Assigned', 'jm-referral-system' ); ?></title>
</head>
<body style="font-family: Arial, sans-serif; color: #1d2327; line-height: 1.5;">
	<p><?php echo esc_html__( 'A referral has been assigned to you.', 'jm-referral-system' ); ?></p>

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
			<td><strong><?php echo esc_html__( 'Service Required', 'jm-referral-system' ); ?>:</strong></td>
			<td><?php echo esc_html( (string) $service_required ); ?></td>
		</tr>
		<tr>
			<td><strong><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?>:</strong></td>
			<td><?php echo esc_html( (string) $priority ); ?></td>
		</tr>
		<tr>
			<td><strong><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?>:</strong></td>
			<td><?php echo esc_html( (string) $status ); ?></td>
		</tr>
	</table>

	<p>
		<a href="<?php echo esc_url( (string) $view_url ); ?>">
			<?php echo esc_html__( 'View referral details', 'jm-referral-system' ); ?>
		</a>
	</p>

	<p style="color:#646970;font-size:12px;">
		<?php echo esc_html( (string) $site_name ); ?>
	</p>
</body>
</html>
