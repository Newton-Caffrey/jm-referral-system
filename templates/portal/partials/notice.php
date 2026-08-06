<?php
/**
 * Standardized portal notice (success/warning/error/info).
 *
 * @var string                                                   $notice_type
 * @var string                                                   $notice_message
 * @var array<int, array{0: string, 1: string, 2?: string}>|null $notice_actions Each item: [label, url, class].
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice_type    = (string) ( $notice_type ?? 'success' );
$notice_message = (string) ( $notice_message ?? '' );
$notice_actions = is_array( $notice_actions ?? null ) ? $notice_actions : array();

if ( '' === trim( $notice_message ) ) {
	return;
}

$allowed_types = array( 'success', 'warning', 'error', 'info' );
if ( ! in_array( $notice_type, $allowed_types, true ) ) {
	$notice_type = 'info';
}
$notice_role = 'error' === $notice_type ? 'alert' : 'status';
?>
<div class="jmrs-portal-notice jmrs-portal-notice--<?php echo esc_attr( $notice_type ); ?>" role="<?php echo esc_attr( $notice_role ); ?>">
	<p><?php echo esc_html( $notice_message ); ?></p>
	<?php if ( ! empty( $notice_actions ) ) : ?>
		<p class="jmrs-portal-actions">
			<?php foreach ( $notice_actions as $notice_action ) : ?>
				<?php
				$action_label = (string) ( $notice_action[0] ?? '' );
				$action_url   = (string) ( $notice_action[1] ?? '' );
				$action_class = (string) ( $notice_action[2] ?? 'jmrs-button jmrs-button--secondary' );
				?>
				<?php if ( '' !== $action_label && '' !== $action_url ) : ?>
					<a class="<?php echo esc_attr( $action_class ); ?>" href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $action_label ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>
</div>
