<?php
/**
 * Standardized section header: title + optional badge + optional actions.
 *
 * Renders the `<h2>` (or heading level via $section_heading_level) plus the
 * surrounding flex row. Callers wrap this partial's output inside their own
 * `<section aria-labelledby="...">` element and continue with the section body.
 *
 * @var string                                                   $section_title
 * @var string                                                   $section_id     Id used for the heading element (referenced by aria-labelledby).
 * @var string|null                                              $section_badge  Optional pre-labelled badge text.
 * @var array<int, array{0: string, 1: string, 2?: string}>|null $section_actions Each item: [label, url, class].
 * @var int|null                                                 $section_heading_level
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$section_title          = (string) ( $section_title ?? '' );
$section_id             = (string) ( $section_id ?? '' );
$section_badge          = isset( $section_badge ) ? (string) $section_badge : '';
$section_actions        = is_array( $section_actions ?? null ) ? $section_actions : array();
$section_heading_level  = isset( $section_heading_level ) ? max( 2, min( 4, (int) $section_heading_level ) ) : 2;
$section_heading_tag    = 'h' . $section_heading_level;
?>
<div class="jmrs-portal-section__header">
	<div class="jmrs-portal-section__heading">
		<<?php echo esc_html( $section_heading_tag ); ?> <?php echo '' !== $section_id ? 'id="' . esc_attr( $section_id ) . '"' : ''; ?> class="jmrs-portal-section__title"><?php echo esc_html( $section_title ); ?></<?php echo esc_html( $section_heading_tag ); ?>>
		<?php if ( '' !== $section_badge ) : ?>
			<span class="jmrs-portal-badge"><?php echo esc_html( $section_badge ); ?></span>
		<?php endif; ?>
	</div>
	<?php if ( ! empty( $section_actions ) ) : ?>
		<div class="jmrs-portal-section__actions">
			<?php foreach ( $section_actions as $section_action ) : ?>
				<?php
				$action_label = (string) ( $section_action[0] ?? '' );
				$action_url   = (string) ( $section_action[1] ?? '' );
				$action_class = (string) ( $section_action[2] ?? 'jmrs-button jmrs-button--secondary' );
				?>
				<?php if ( '' !== $action_label && '' !== $action_url ) : ?>
					<a class="<?php echo esc_attr( $action_class ); ?>" href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $action_label ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
