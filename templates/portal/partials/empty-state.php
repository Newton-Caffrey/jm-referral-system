<?php
/**
 * Standardized empty-state block with optional call-to-action(s).
 *
 * @var string                                                   $empty_title
 * @var string                                                   $empty_message
 * @var array<int, array{0: string, 1: string, 2?: string}>|null $empty_actions Each item: [label, url, class].
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$empty_title    = (string) ( $empty_title ?? '' );
$empty_message  = (string) ( $empty_message ?? '' );
$empty_actions  = is_array( $empty_actions ?? null ) ? $empty_actions : array();
?>
<div class="jmrs-portal-empty">
	<?php if ( '' !== $empty_title ) : ?>
		<p class="jmrs-portal-empty__title"><?php echo esc_html( $empty_title ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $empty_message ) : ?>
		<p><?php echo esc_html( $empty_message ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $empty_actions ) ) : ?>
		<p class="jmrs-portal-actions">
			<?php foreach ( $empty_actions as $empty_action ) : ?>
				<?php
				$action_label = (string) ( $empty_action[0] ?? '' );
				$action_url   = (string) ( $empty_action[1] ?? '' );
				$action_class = (string) ( $empty_action[2] ?? 'jmrs-button jmrs-button--secondary' );
				?>
				<?php if ( '' !== $action_label && '' !== $action_url ) : ?>
					<a class="<?php echo esc_attr( $action_class ); ?>" href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $action_label ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>
</div>
