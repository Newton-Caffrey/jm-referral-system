<?php
/**
 * Acquisition Pipeline report section.
 *
 * @package JMReferral
 *
 * @var array<string, mixed> $acquisition
 * @var string $acquisition_section_csv
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$acquisition             = is_array( $acquisition ?? null ) ? $acquisition : array();
$acquisition_section_csv = (string) ( $acquisition_section_csv ?? '' );

$funnel_rows       = is_array( $acquisition['funnel'] ?? null ) ? $acquisition['funnel'] : array();
$outcome_rows      = is_array( $acquisition['outcomes'] ?? null ) ? $acquisition['outcomes'] : array();
$active_stages     = is_array( $acquisition['active_stages'] ?? null ) ? $acquisition['active_stages'] : array();
$timing_rows       = is_array( $acquisition['timing'] ?? null ) ? $acquisition['timing'] : array();
$stage_durations   = is_array( $acquisition['stage_durations'] ?? null ) ? $acquisition['stage_durations'] : array();
$assessments       = is_array( $acquisition['assessments'] ?? null ) ? $acquisition['assessments'] : array();
$package_costs     = is_array( $acquisition['package_costs'] ?? null ) ? $acquisition['package_costs'] : array();
$funding           = is_array( $acquisition['funding'] ?? null ) ? $acquisition['funding'] : array();

$fmt_money = static function ( $value ): string {
	if ( null === $value || '' === $value ) {
		return __( 'Not Available', 'jm-referral-system' );
	}

	return 'GBP ' . number_format( (float) $value, 2 );
};

$fmt_pct = static function ( $pct ): string {
	if ( null === $pct ) {
		return '—';
	}

	return (string) $pct . '%';
};
?>
<section class="jmrs-report-section jmrs-report-section--period" id="jmrs-acquisition-pipeline">
	<div class="jmrs-report-section-header">
		<h2><?php echo esc_html__( 'Acquisition Pipeline', 'jm-referral-system' ); ?></h2>
		<span class="jmrs-report-badge jmrs-report-badge--period"><?php echo esc_html__( 'Selected Period', 'jm-referral-system' ); ?></span>
		<?php if ( '' !== $acquisition_section_csv ) : ?>
			<p class="jmrs-report-section-actions jmrs-no-print">
				<a class="button" href="<?php echo esc_url( $acquisition_section_csv ); ?>">
					<?php echo esc_html__( 'Export Acquisition CSV', 'jm-referral-system' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>

	<p class="jmrs-report-section-note">
		<strong><?php echo esc_html( (string) ( $acquisition['cohort_label'] ?? '' ) ); ?></strong>
	</p>
	<?php if ( ! empty( $acquisition['cohort_note'] ) ) : ?>
		<p class="jmrs-report-section-note"><?php echo esc_html( (string) $acquisition['cohort_note'] ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $acquisition['archive_note'] ) ) : ?>
		<p class="jmrs-report-section-note"><?php echo esc_html( (string) $acquisition['archive_note'] ); ?></p>
	<?php endif; ?>

	<div class="jmrs-report-kpis" role="list">
		<div class="jmrs-report-kpi" role="listitem">
			<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) absint( $acquisition['received_canonical'] ?? 0 ) ); ?></span>
			<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Canonical Referrals Received', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-report-kpi" role="listitem">
			<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) absint( $acquisition['received_legacy'] ?? 0 ) ); ?></span>
			<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Legacy / Pre-Pipeline Referrals', 'jm-referral-system' ); ?></span>
		</div>
	</div>

	<h3><?php echo esc_html__( 'Referral Funnel / Summary', 'jm-referral-system' ); ?></h3>
	<table class="widefat striped jmrs-report-table">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Milestone', 'jm-referral-system' ); ?></th>
				<th><?php echo esc_html__( 'Count', 'jm-referral-system' ); ?></th>
				<th><?php echo esc_html__( '% of Received', 'jm-referral-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $funnel_rows as $row ) : ?>
				<tr<?php echo ! empty( $row['branch'] ) ? ' class="jmrs-report-row--branch"' : ''; ?>>
					<td>
						<?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?>
						<?php if ( ! empty( $row['branch'] ) ) : ?>
							<span class="description"> — <?php echo esc_html__( 'branch', 'jm-referral-system' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( (string) absint( $row['count'] ?? 0 ) ); ?></td>
					<td><?php echo esc_html( $fmt_pct( $row['pct_of_received'] ?? null ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<div class="jmrs-report-chart-wrap">
		<canvas id="jmrs-chart-acquisition_funnel_chart" aria-label="<?php echo esc_attr__( 'Acquisition Funnel chart', 'jm-referral-system' ); ?>"></canvas>
	</div>

	<h3><?php echo esc_html__( 'Outcome Summary', 'jm-referral-system' ); ?></h3>
	<div class="jmrs-report-kpis" role="list">
		<?php foreach ( $outcome_rows as $row ) : ?>
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) absint( $row['count'] ?? 0 ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="jmrs-report-chart-wrap">
		<canvas id="jmrs-chart-acquisition_outcomes_chart" aria-label="<?php echo esc_attr__( 'Acquisition Outcomes chart', 'jm-referral-system' ); ?>"></canvas>
	</div>

	<h3><?php echo esc_html__( 'Timing Metrics', 'jm-referral-system' ); ?></h3>
	<table class="widefat striped jmrs-report-table">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Metric', 'jm-referral-system' ); ?></th>
				<th><?php echo esc_html__( 'Median', 'jm-referral-system' ); ?></th>
				<th><?php echo esc_html__( 'Average', 'jm-referral-system' ); ?></th>
				<th><?php echo esc_html__( 'Measured', 'jm-referral-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $timing_rows as $timing ) : ?>
				<tr>
					<td>
						<?php echo esc_html( (string) ( $timing['label'] ?? '' ) ); ?>
						<?php if ( ! empty( $timing['note'] ) ) : ?>
							<br /><span class="description"><?php echo esc_html( (string) $timing['note'] ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( (string) ( $timing['median_label'] ?? __( 'Not Available', 'jm-referral-system' ) ) ); ?></td>
					<td><?php echo esc_html( (string) ( $timing['average_label'] ?? __( 'Not Available', 'jm-referral-system' ) ) ); ?></td>
					<td><?php echo esc_html( (string) absint( $timing['count'] ?? 0 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( ! empty( $stage_durations ) ) : ?>
		<h3><?php echo esc_html__( 'Stage Durations (Completed Transitions)', 'jm-referral-system' ); ?></h3>
		<?php if ( ! empty( $acquisition['stage_duration_note'] ) ) : ?>
			<p class="jmrs-report-section-note"><?php echo esc_html( (string) $acquisition['stage_duration_note'] ); ?></p>
		<?php endif; ?>
		<table class="widefat striped jmrs-report-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Stage', 'jm-referral-system' ); ?></th>
					<th><?php echo esc_html__( 'Median', 'jm-referral-system' ); ?></th>
					<th><?php echo esc_html__( 'Average', 'jm-referral-system' ); ?></th>
					<th><?php echo esc_html__( 'Completed Transitions Measured', 'jm-referral-system' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $stage_durations as $stage_row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) ( $stage_row['label'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $stage_row['median_label'] ?? __( 'Not Available', 'jm-referral-system' ) ) ); ?></td>
						<td><?php echo esc_html( (string) ( $stage_row['average_label'] ?? __( 'Not Available', 'jm-referral-system' ) ) ); ?></td>
						<td><?php echo esc_html( (string) absint( $stage_row['count'] ?? 0 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h3><?php echo esc_html__( 'Assessment Outcomes', 'jm-referral-system' ); ?></h3>
	<p class="jmrs-report-section-note">
		<?php echo esc_html__( 'Latest assessment per referral. Pending is shown separately and is not a completed outcome.', 'jm-referral-system' ); ?>
	</p>
	<div class="jmrs-report-kpis" role="list">
		<div class="jmrs-report-kpi" role="listitem">
			<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) absint( $assessments['suitable'] ?? 0 ) ); ?></span>
			<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Suitable', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-report-kpi" role="listitem">
			<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) absint( $assessments['suitable_with_conditions'] ?? 0 ) ); ?></span>
			<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Suitable With Conditions', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-report-kpi" role="listitem">
			<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) absint( $assessments['not_suitable'] ?? 0 ) ); ?></span>
			<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Not Suitable', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-report-kpi" role="listitem">
			<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) absint( $assessments['pending'] ?? 0 ) ); ?></span>
			<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Pending (excluded from completed)', 'jm-referral-system' ); ?></span>
		</div>
	</div>

	<h3><?php echo esc_html__( 'Package Cost / Funding Summary', 'jm-referral-system' ); ?></h3>
	<div class="jmrs-report-kpis" role="list">
		<div class="jmrs-report-kpi" role="listitem">
			<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) absint( $package_costs['prepared'] ?? 0 ) ); ?></span>
			<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Prepared Package Costs', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-report-kpi" role="listitem">
			<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) absint( $package_costs['sent'] ?? 0 ) ); ?></span>
			<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Sent Package Costs', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-report-kpi" role="listitem">
			<span class="jmrs-report-kpi-number"><?php echo esc_html( $fmt_money( $package_costs['total_proposed'] ?? null ) ); ?></span>
			<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Total Proposed Package Value', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-report-kpi" role="listitem">
			<span class="jmrs-report-kpi-number"><?php echo esc_html( $fmt_money( $package_costs['average_proposed'] ?? null ) ); ?></span>
			<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Average Proposed Package Value', 'jm-referral-system' ); ?></span>
		</div>
	</div>
	<?php if ( ! empty( $package_costs['value_note'] ) ) : ?>
		<p class="jmrs-report-section-note"><?php echo esc_html( (string) $package_costs['value_note'] ); ?></p>
	<?php endif; ?>

	<div class="jmrs-report-kpis" role="list">
		<div class="jmrs-report-kpi" role="listitem">
			<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) absint( $funding['yes'] ?? 0 ) ); ?></span>
			<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Funding Confirmed: Yes', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-report-kpi" role="listitem">
			<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) absint( $funding['no'] ?? 0 ) ); ?></span>
			<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Funding Confirmed: No', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-report-kpi" role="listitem">
			<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) absint( $funding['not_recorded'] ?? 0 ) ); ?></span>
			<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Funding Confirmation Not Recorded', 'jm-referral-system' ); ?></span>
		</div>
	</div>
	<?php if ( ! empty( $funding['note'] ) ) : ?>
		<p class="jmrs-report-section-note"><?php echo esc_html( (string) $funding['note'] ); ?></p>
	<?php endif; ?>

	<h3><?php echo esc_html__( 'Current Cohort Pipeline', 'jm-referral-system' ); ?></h3>
	<p class="jmrs-report-section-note">
		<?php echo esc_html__( 'Active acquisition stages for referrals in this cohort that have not reached a terminal outcome.', 'jm-referral-system' ); ?>
	</p>
	<table class="widefat striped jmrs-report-table">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Pipeline Stage', 'jm-referral-system' ); ?></th>
				<th><?php echo esc_html__( 'Count', 'jm-referral-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $active_stages as $stage_row ) : ?>
				<tr>
					<td><?php echo esc_html( (string) ( $stage_row['label'] ?? '' ) ); ?></td>
					<td><?php echo esc_html( (string) absint( $stage_row['count'] ?? 0 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</section>
