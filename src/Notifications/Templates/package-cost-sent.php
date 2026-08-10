<?php
/**
 * Email template: Package Cost sent to referrer / Local Authority.
 *
 * Minimal non-clinical content — referral reference only. Attachment carries the Package Cost.
 *
 * @package JMReferral
 *
 * @var string $referral_number
 * @var string $referrer_name
 * @var string $site_name
 * @var string $company_name
 * @var string $site_url
 * @var string $admin_email
 * @var string $contact_phone
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8" />
	<title><?php echo esc_html__( 'Package Cost', 'jm-referral-system' ); ?></title>
</head>
<body style="font-family: Arial, sans-serif; color: #1d2327; line-height: 1.5;">
	<p>
		<?php
		if ( ! empty( $referrer_name ) ) {
			echo esc_html(
				sprintf(
					/* translators: %s: referrer name */
					__( 'Dear %s,', 'jm-referral-system' ),
					(string) $referrer_name
				)
			);
		} else {
			echo esc_html__( 'Hello,', 'jm-referral-system' );
		}
		?>
	</p>

	<p>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: company name */
				__( 'Following completion of our assessment, please find attached the proposed Package Cost from %s for the referral referenced below.', 'jm-referral-system' ),
				(string) ( $company_name ?? $site_name )
			)
		);
		?>
	</p>

	<p>
		<strong><?php echo esc_html__( 'Referral reference:', 'jm-referral-system' ); ?></strong>
		<?php echo esc_html( (string) $referral_number ); ?>
	</p>

	<p><?php echo esc_html__( 'Please let us know if any further information is required.', 'jm-referral-system' ); ?></p>

	<p><?php echo esc_html__( 'Kind regards,', 'jm-referral-system' ); ?><br />
		<?php echo esc_html( (string) ( $company_name ?? $site_name ) ); ?>
	</p>

	<?php if ( ! empty( $contact_phone ) ) : ?>
		<p><?php echo esc_html__( 'Phone:', 'jm-referral-system' ); ?> <?php echo esc_html( (string) $contact_phone ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $admin_email ) && is_email( (string) $admin_email ) ) : ?>
		<p>
			<?php echo esc_html__( 'Email:', 'jm-referral-system' ); ?>
			<a href="<?php echo esc_url( 'mailto:' . (string) $admin_email ); ?>"><?php echo esc_html( (string) $admin_email ); ?></a>
		</p>
	<?php endif; ?>

	<p style="color:#646970;font-size:12px;">
		<?php echo esc_html( (string) ( $company_name ?? $site_name ) ); ?>
		·
		<a href="<?php echo esc_url( (string) $site_url ); ?>"><?php echo esc_html( (string) $site_url ); ?></a>
	</p>
</body>
</html>
