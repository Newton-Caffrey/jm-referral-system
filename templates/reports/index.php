<?php
/**
 * Operational reports admin page.
 *
 * @package JMReferral
 *
 * @var array<string, int> $kpis
 * @var array<string, string> $range_labels
 * @var string $filter_range
 * @var string $filter_start
 * @var string $filter_end
 * @var array<string, string> $filter_errors
 * @var string $reports_url
 * @var string $alerts_url
 * @var array<int, array<string, mixed>> $sections
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kpis          = is_array( $kpis ?? null ) ? $kpis : array();
$range_labels  = is_array( $range_labels ?? null ) ? $range_labels : array();
$filter_range  = (string) ( $filter_range ?? 'this_month' );
$filter_start  = (string) ( $filter_start ?? '' );
$filter_end    = (string) ( $filter_end ?? '' );
$filter_errors = is_array( $filter_errors ?? null ) ? $filter_errors : array();
$reports_url   = (string) ( $reports_url ?? '' );
$alerts_url    = (string) ( $alerts_url ?? '' );
$sections      = is_array( $sections ?? null ) ? $sections : array();

$kpi = static function ( string $key ) use ( $kpis ): int {
	return absint( $kpis[ $key ] ?? 0 );
};
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Reports', 'jm-referral-system' ); ?></h1>

	<style>
		.jmrs-report-filters {
			background: #fff;
			border: 1px solid #c3c4c7;
			padding: 12px 16px;
			margin: 16px 0 20px;
		}
		.jmrs-report-filters .jmrs-filter-row {
			display: flex;
			flex-wrap: wrap;
			gap: 12px;
			align-items: flex-end;
		}
		.jmrs-report-filters label {
			display: block;
			font-weight: 600;
			margin-bottom: 4px;
		}
		.jmrs-report-filters .description { margin: 4px 0 0; color: #b32d2e; }
		.jmrs-report-section {
			margin: 0 0 28px;
		}
		.jmrs-report-section h2 {
			margin: 0 0 12px;
			font-size: 1.15em;
		}
		.jmrs-report-section h3 {
			margin: 16px 0 8px;
			font-size: 1em;
		}
		.jmrs-report-kpis {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
			gap: 12px;
		}
		.jmrs-report-kpi {
			background: #fff;
			border: 1px solid #c3c4c7;
			box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
			padding: 14px;
		}
		.jmrs-report-kpi-number {
			display: block;
			font-size: 26px;
			font-weight: 600;
			line-height: 1.2;
			color: #1d2327;
		}
		.jmrs-report-kpi-label {
			display: block;
			margin-top: 4px;
			color: #646970;
			font-size: 13px;
		}
		.jmrs-report-kpi.is-warning .jmrs-report-kpi-number { color: #996800; }
		.jmrs-report-kpi.is-critical .jmrs-report-kpi-number { color: #b32d2e; }
		.jmrs-report-range-note {
			margin: 0 0 16px;
			color: #646970;
		}
		.jmrs-report-dataset {
			background: #fff;
			border: 1px solid #c3c4c7;
			padding: 12px 16px;
			margin: 0 0 16px;
		}
		.jmrs-report-dataset-note {
			margin: 0 0 10px;
			color: #646970;
			font-size: 13px;
		}
		.jmrs-report-dataset-grid {
			display: grid;
			grid-template-columns: minmax(220px, 1fr) minmax(220px, 1.2fr);
			gap: 16px;
		}
		@media (max-width: 782px) {
			.jmrs-report-dataset-grid { grid-template-columns: 1fr; }
		}
		.jmrs-chart-placeholder {
			border: 1px dashed #c3c4c7;
			background: #f6f7f7;
			padding: 12px;
		}
		.jmrs-chart-placeholder-title {
			margin: 0 0 8px;
			font-size: 12px;
			font-weight: 600;
			color: #646970;
			text-transform: uppercase;
			letter-spacing: 0.03em;
		}
		.jmrs-chart-bar-row {
			display: grid;
			grid-template-columns: minmax(80px, 140px) 1fr auto;
			gap: 8px;
			align-items: center;
			margin: 0 0 6px;
		}
		.jmrs-chart-bar-label {
			font-size: 12px;
			color: #1d2327;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.jmrs-chart-bar-track {
			background: #dcdcde;
			height: 10px;
			border-radius: 2px;
			overflow: hidden;
		}
		.jmrs-chart-bar-fill {
			display: block;
			height: 100%;
			background: #2271b1;
			min-width: 0;
		}
		.jmrs-chart-bar-value {
			font-size: 12px;
			font-weight: 600;
			color: #1d2327;
			min-width: 2.5em;
			text-align: right;
		}
		.jmrs-chart-empty {
			margin: 0;
			color: #646970;
			font-size: 13px;
		}
	</style>

	<form method="get" action="" class="jmrs-report-filters">
		<input type="hidden" name="page" value="jm-referrals-reports" />
		<div class="jmrs-filter-row">
			<div>
				<label for="jmrs_report_range"><?php echo esc_html__( 'Date Range', 'jm-referral-system' ); ?></label>
				<select name="jmrs_report_range" id="jmrs_report_range">
					<?php foreach ( $range_labels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $filter_range, (string) $value ); ?>>
							<?php echo esc_html( (string) $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="jmrs_report_start"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></label>
				<input
					type="date"
					name="jmrs_report_start"
					id="jmrs_report_start"
					value="<?php echo esc_attr( $filter_start ); ?>"
				/>
				<?php if ( isset( $filter_errors['start_date'] ) ) : ?>
					<p class="description"><?php echo esc_html( $filter_errors['start_date'] ); ?></p>
				<?php endif; ?>
			</div>
			<div>
				<label for="jmrs_report_end"><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></label>
				<input
					type="date"
					name="jmrs_report_end"
					id="jmrs_report_end"
					value="<?php echo esc_attr( $filter_end ); ?>"
				/>
				<?php if ( isset( $filter_errors['end_date'] ) ) : ?>
					<p class="description"><?php echo esc_html( $filter_errors['end_date'] ); ?></p>
				<?php endif; ?>
			</div>
			<div>
				<?php submit_button( __( 'Apply', 'jm-referral-system' ), 'primary', '', false ); ?>
				<a class="button" href="<?php echo esc_url( $reports_url ); ?>">
					<?php echo esc_html__( 'Reset', 'jm-referral-system' ); ?>
				</a>
			</div>
		</div>
		<p class="description" style="margin-top:10px;color:#646970;">
			<?php echo esc_html__( 'Start and end dates are used when Custom Range is selected. Snapshot KPIs (Active Clients, Active Care Plans, Operational Alerts) are not limited by the date range.', 'jm-referral-system' ); ?>
		</p>
	</form>

	<?php if ( '' !== $filter_start && '' !== $filter_end && empty( $filter_errors ) ) : ?>
		<p class="jmrs-report-range-note">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: start date, 2: end date */
					__( 'Showing data from %1$s to %2$s.', 'jm-referral-system' ),
					$filter_start,
					$filter_end
				)
			);
			?>
		</p>
	<?php endif; ?>

	<section class="jmrs-report-section">
		<h2><?php echo esc_html__( 'Referrals', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-report-kpis">
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) $kpi( 'referrals_total' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Total', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) $kpi( 'referrals_new' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'New in Range', 'jm-referral-system' ); ?></span>
			</div>
		</div>
	</section>

	<section class="jmrs-report-section">
		<h2><?php echo esc_html__( 'Clients', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-report-kpis">
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) $kpi( 'active_clients' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Active Clients', 'jm-referral-system' ); ?></span>
			</div>
		</div>
	</section>

	<section class="jmrs-report-section">
		<h2><?php echo esc_html__( 'Assessments', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-report-kpis">
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) $kpi( 'assessments_completed' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Completed', 'jm-referral-system' ); ?></span>
			</div>
		</div>
	</section>

	<section class="jmrs-report-section">
		<h2><?php echo esc_html__( 'Care Plans', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-report-kpis">
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) $kpi( 'care_plans_active' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Active', 'jm-referral-system' ); ?></span>
			</div>
		</div>
	</section>

	<section class="jmrs-report-section">
		<h2><?php echo esc_html__( 'Visits', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-report-kpis">
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) $kpi( 'visits_scheduled' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Scheduled', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) $kpi( 'visits_completed' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Completed', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi is-warning">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) $kpi( 'visits_missed' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Missed', 'jm-referral-system' ); ?></span>
			</div>
		</div>
	</section>

	<section class="jmrs-report-section">
		<h2><?php echo esc_html__( 'Medication', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-report-kpis">
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) $kpi( 'medication_administrations' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Administrations', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi is-warning">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) $kpi( 'medication_exceptions' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Exceptions', 'jm-referral-system' ); ?></span>
			</div>
		</div>
	</section>

	<section class="jmrs-report-section">
		<h2><?php echo esc_html__( 'Compliance', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-report-kpis">
			<div class="jmrs-report-kpi is-critical">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( (string) $kpi( 'operational_alerts' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Operational Alerts', 'jm-referral-system' ); ?></span>
			</div>
		</div>
		<?php if ( '' !== $alerts_url ) : ?>
			<p>
				<a href="<?php echo esc_url( $alerts_url ); ?>">
					<?php echo esc_html__( 'View Operational Alerts', 'jm-referral-system' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</section>

	<?php if ( ! empty( $sections ) ) : ?>
		<hr />
		<h2><?php echo esc_html__( 'Trends & Analytics', 'jm-referral-system' ); ?></h2>

		<?php foreach ( $sections as $section ) : ?>
			<?php
			$section_title = (string) ( $section['title'] ?? '' );
			$datasets      = is_array( $section['datasets'] ?? null ) ? $section['datasets'] : array();
			?>
			<section class="jmrs-report-section">
				<h2><?php echo esc_html( $section_title ); ?></h2>

				<?php foreach ( $datasets as $dataset ) : ?>
					<?php
					$dataset_title = (string) ( $dataset['title'] ?? '' );
					$dataset_note  = (string) ( $dataset['note'] ?? '' );
					$dataset_rows  = is_array( $dataset['rows'] ?? null ) ? $dataset['rows'] : array();
					$chart         = is_array( $dataset['chart'] ?? null ) ? $dataset['chart'] : array();
					$chart_labels  = is_array( $chart['labels'] ?? null ) ? $chart['labels'] : array();
					$chart_values  = is_array( $chart['values'] ?? null ) ? $chart['values'] : array();
					$chart_max     = isset( $chart['max'] ) ? (float) $chart['max'] : 0.0;
					$export        = is_array( $dataset['export'] ?? null ) ? $dataset['export'] : array();
					$export_cols   = is_array( $export['columns'] ?? null ) ? $export['columns'] : array();
					$export_rows   = is_array( $export['rows'] ?? null ) ? $export['rows'] : array();
					?>
					<div
						class="jmrs-report-dataset"
						data-dataset-id="<?php echo esc_attr( (string) ( $dataset['id'] ?? '' ) ); ?>"
						data-chart-labels="<?php echo esc_attr( wp_json_encode( $chart_labels ) ); ?>"
						data-chart-values="<?php echo esc_attr( wp_json_encode( $chart_values ) ); ?>"
						data-export-columns="<?php echo esc_attr( wp_json_encode( $export_cols ) ); ?>"
						data-export-rows="<?php echo esc_attr( wp_json_encode( $export_rows ) ); ?>"
					>
						<h3><?php echo esc_html( $dataset_title ); ?></h3>
						<?php if ( '' !== $dataset_note ) : ?>
							<p class="jmrs-report-dataset-note"><?php echo esc_html( $dataset_note ); ?></p>
						<?php endif; ?>

						<div class="jmrs-report-dataset-grid">
							<div>
								<table class="widefat striped">
									<thead>
										<tr>
											<th scope="col"><?php echo esc_html__( 'Label', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Value', 'jm-referral-system' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php if ( empty( $dataset_rows ) ) : ?>
											<tr>
												<td colspan="2"><?php echo esc_html__( 'No data for this range.', 'jm-referral-system' ); ?></td>
											</tr>
										<?php else : ?>
											<?php foreach ( $dataset_rows as $row ) : ?>
												<tr>
													<td><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></td>
													<td><?php echo esc_html( (string) ( $row['value'] ?? 0 ) ); ?></td>
												</tr>
											<?php endforeach; ?>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
							<div class="jmrs-chart-placeholder" aria-label="<?php echo esc_attr__( 'Chart placeholder', 'jm-referral-system' ); ?>">
								<p class="jmrs-chart-placeholder-title"><?php echo esc_html__( 'Chart placeholder', 'jm-referral-system' ); ?></p>
								<?php if ( empty( $chart_labels ) ) : ?>
									<p class="jmrs-chart-empty"><?php echo esc_html__( 'No chart data.', 'jm-referral-system' ); ?></p>
								<?php else : ?>
									<?php foreach ( $chart_labels as $idx => $label ) : ?>
										<?php
										$value   = isset( $chart_values[ $idx ] ) ? (float) $chart_values[ $idx ] : 0.0;
										$percent = $chart_max > 0 ? min( 100, ( $value / $chart_max ) * 100 ) : 0;
										?>
										<div class="jmrs-chart-bar-row">
											<span class="jmrs-chart-bar-label" title="<?php echo esc_attr( (string) $label ); ?>">
												<?php echo esc_html( (string) $label ); ?>
											</span>
											<span class="jmrs-chart-bar-track">
												<span class="jmrs-chart-bar-fill" style="width: <?php echo esc_attr( (string) round( $percent, 1 ) ); ?>%;"></span>
											</span>
											<span class="jmrs-chart-bar-value"><?php echo esc_html( (string) $value ); ?></span>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
