<?php
/**
 * Existing resident registration + funding panel on the client record (Phase 5F.1).
 *
 * @package JMReferral
 *
 * @var array|null $existing_resident_panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$panel = is_array( $existing_resident_panel ?? null ) ? $existing_resident_panel : null;
if ( null === $panel ) {
	return;
}

$is_existing = ! empty( $panel['is_existing'] );
$funding     = is_array( $panel['funding'] ?? null ) ? $panel['funding'] : null;
$text        = static function ( array $source, string $key ): string {
	return (string) ( $source[ $key ] ?? '' );
};
?>
<?php if ( ! empty( $panel['just_registered'] ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--success" role="status">
		<p>
			<strong><?php echo esc_html__( 'Existing resident added.', 'jm-referral-system' ); ?></strong>
			<?php echo esc_html__( 'They are at Care Started, and occupancy and dashboards are up to date. Next, add their care plan, risk assessments and documents below.', 'jm-referral-system' ); ?>
		</p>
	</div>
<?php endif; ?>

<section class="jmrs-portal-section jmrs-portal-panel jmrs-existing-resident" aria-labelledby="jmrs-existing-resident-heading">
	<?php
	$section_title   = $is_existing ? __( 'Existing resident', 'jm-referral-system' ) : __( 'Funding and support package', 'jm-referral-system' );
	$section_id      = 'jmrs-existing-resident-heading';
	$section_badge   = $is_existing ? __( 'Registered without referral history', 'jm-referral-system' ) : '';
	$section_actions = array();
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
	?>

	<?php if ( $is_existing ) : ?>
		<p class="jmrs-portal-muted">
			<?php echo esc_html__( 'This person was already receiving care when they were added to the platform, so there are no referral, assessment, costing or Local Authority decision records. They are not counted in referral reports.', 'jm-referral-system' ); ?>
		</p>
		<dl class="jmrs-portal-dl-grid">
			<div>
				<dt><?php echo esc_html__( 'Admission date', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $text( $panel, 'admission_date' ) ? $text( $panel, 'admission_date' ) : '—' ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Care started', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $text( $panel, 'care_started' ) ? $text( $panel, 'care_started' ) : '—' ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Added to the platform', 'jm-referral-system' ); ?></dt>
				<dd>
					<?php
					$added = $text( $panel, 'registered_at' );
					if ( '' !== $text( $panel, 'registered_by' ) ) {
						$added = sprintf(
							/* translators: 1: date, 2: staff name */
							__( '%1$s by %2$s', 'jm-referral-system' ),
							$added,
							$text( $panel, 'registered_by' )
						);
					}
					echo esc_html( '' !== $added ? $added : '—' );
					?>
				</dd>
			</div>
			<?php if ( '' !== $text( $panel, 'batch' ) ) : ?>
				<div>
					<dt><?php echo esc_html__( 'Import reference', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( $text( $panel, 'batch' ) ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>
	<?php endif; ?>

	<?php if ( null !== $funding ) : ?>
		<?php if ( $is_existing ) : ?>
			<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Funding and support package', 'jm-referral-system' ); ?></h3>
		<?php endif; ?>
		<dl class="jmrs-portal-dl-grid">
			<div>
				<dt><?php echo esc_html__( 'Funding type', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $text( $funding, 'type' ) ? $text( $funding, 'type' ) : '—' ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Funder', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $text( $funding, 'funder' ) ? $text( $funding, 'funder' ) : '—' ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Funding reference', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $text( $funding, 'reference' ) ? $text( $funding, 'reference' ) : '—' ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Funding start date', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $text( $funding, 'start' ) ? $text( $funding, 'start' ) : '—' ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Weekly package value', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $text( $funding, 'weekly' ) ? $text( $funding, 'weekly' ) : '—' ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Weekly support hours', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $text( $funding, 'hours' ) ? $text( $funding, 'hours' ) : '—' ); ?></dd>
			</div>
			<?php if ( '' !== $text( $funding, 'package' ) ) : ?>
				<div class="jmrs-portal-dl-grid__wide">
					<dt><?php echo esc_html__( 'Support package', 'jm-referral-system' ); ?></dt>
					<dd style="white-space: pre-wrap;"><?php echo esc_html( $text( $funding, 'package' ) ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>
	<?php elseif ( $is_existing ) : ?>
		<p class="jmrs-portal-muted"><?php echo esc_html__( 'No funding details were recorded when they were added.', 'jm-referral-system' ); ?></p>
	<?php endif; ?>
</section>
