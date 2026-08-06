<?php
/**
 * KPI stat card used on the portal dashboard.
 *
 * @var string      $kpi_value
 * @var string      $kpi_label
 * @var string|null $kpi_href Optional link making the whole card clickable.
 * @var string|null $kpi_tone default|warning|info
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kpi_value = (string) ( $kpi_value ?? '' );
$kpi_label = (string) ( $kpi_label ?? '' );
$kpi_href  = (string) ( $kpi_href ?? '' );
$kpi_tone  = (string) ( $kpi_tone ?? 'default' );

if ( ! in_array( $kpi_tone, array( 'default', 'warning', 'info' ), true ) ) {
	$kpi_tone = 'default';
}

$kpi_classes = 'jmrs-portal-kpi-card';
if ( 'default' !== $kpi_tone ) {
	$kpi_classes .= ' jmrs-portal-kpi-card--' . $kpi_tone;
}
?>
<?php if ( '' !== $kpi_href ) : ?>
	<a class="<?php echo esc_attr( $kpi_classes ); ?> jmrs-portal-kpi-card--link" href="<?php echo esc_url( $kpi_href ); ?>">
		<span class="jmrs-portal-kpi-card__value"><?php echo esc_html( $kpi_value ); ?></span>
		<span class="jmrs-portal-kpi-card__label"><?php echo esc_html( $kpi_label ); ?></span>
	</a>
<?php else : ?>
	<div class="<?php echo esc_attr( $kpi_classes ); ?>">
		<span class="jmrs-portal-kpi-card__value"><?php echo esc_html( $kpi_value ); ?></span>
		<span class="jmrs-portal-kpi-card__label"><?php echo esc_html( $kpi_label ); ?></span>
	</div>
<?php endif; ?>
