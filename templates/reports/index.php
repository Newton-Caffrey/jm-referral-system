<?php
/**
 * Operational reports admin page.
 *
 * @package JMReferral
 *
 * @var array<string, int|float|null> $kpis
 * @var array<string, string> $range_labels
 * @var string $filter_range
 * @var string $filter_start
 * @var string $filter_end
 * @var array<string, string> $filter_errors
 * @var string $reports_url
 * @var string $alerts_url
 * @var array<int, array<string, mixed>> $sections
 * @var string $full_export_url
 * @var array<string, string> $section_export_urls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kpis                = is_array( $kpis ?? null ) ? $kpis : array();
$range_labels        = is_array( $range_labels ?? null ) ? $range_labels : array();
$filter_range        = (string) ( $filter_range ?? 'this_month' );
$filter_start        = (string) ( $filter_start ?? '' );
$filter_end          = (string) ( $filter_end ?? '' );
$filter_errors       = is_array( $filter_errors ?? null ) ? $filter_errors : array();
$reports_url         = (string) ( $reports_url ?? '' );
$alerts_url          = (string) ( $alerts_url ?? '' );
$sections            = is_array( $sections ?? null ) ? $sections : array();
$full_export_url     = (string) ( $full_export_url ?? '' );
$section_export_urls = is_array( $section_export_urls ?? null ) ? $section_export_urls : array();

$kpi = static function ( string $key ) use ( $kpis ): string {
	$value = $kpis[ $key ] ?? 0;
	if ( is_float( $value ) ) {
		return (string) $value;
	}

	return (string) absint( $value );
};
?>
<div class="wrap jmrs-reports-wrap">
	<h1><?php echo esc_html__( 'Reports', 'jm-referral-system' ); ?></h1>

	<div class="jmrs-report-print-meta">
		<p>
			<strong><?php echo esc_html__( 'J&M Referral System Reports', 'jm-referral-system' ); ?></strong><br />
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: start date, 2: end date */
					__( 'Period: %1$s to %2$s', 'jm-referral-system' ),
					$filter_start,
					$filter_end
				)
			);
			?>
		</p>
	</div>

	<div class="jmrs-report-toolbar jmrs-no-print">
		<?php if ( '' !== $full_export_url ) : ?>
			<a class="button button-primary" href="<?php echo esc_url( $full_export_url ); ?>">
				<?php echo esc_html__( 'Export Full Report CSV', 'jm-referral-system' ); ?>
			</a>
		<?php endif; ?>
		<button type="button" class="button" onclick="window.print();">
			<?php echo esc_html__( 'Print Report', 'jm-referral-system' ); ?>
		</button>
	</div>

	<form method="get" action="" class="jmrs-report-filters jmrs-no-print">
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
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $kpi( 'referrals_total' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Total', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $kpi( 'referrals_new' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'New in Range', 'jm-referral-system' ); ?></span>
			</div>
		</div>
	</section>

	<section class="jmrs-report-section">
		<h2><?php echo esc_html__( 'Clients', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-report-kpis">
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $kpi( 'active_clients' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Active Clients', 'jm-referral-system' ); ?></span>
			</div>
		</div>
	</section>

	<section class="jmrs-report-section">
		<h2><?php echo esc_html__( 'Assessments', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-report-kpis">
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $kpi( 'assessments_completed' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Completed', 'jm-referral-system' ); ?></span>
			</div>
		</div>
	</section>

	<section class="jmrs-report-section">
		<h2><?php echo esc_html__( 'Care Plans', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-report-kpis">
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $kpi( 'care_plans_active' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Active', 'jm-referral-system' ); ?></span>
			</div>
		</div>
	</section>

	<section class="jmrs-report-section">
		<h2><?php echo esc_html__( 'Visits', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-report-kpis">
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $kpi( 'visits_scheduled' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Scheduled', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $kpi( 'visits_completed' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Completed', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi is-warning">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $kpi( 'visits_missed' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Missed', 'jm-referral-system' ); ?></span>
			</div>
		</div>
	</section>

	<section class="jmrs-report-section">
		<h2><?php echo esc_html__( 'Medication', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-report-kpis">
			<div class="jmrs-report-kpi">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $kpi( 'medication_administrations' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Administrations', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi is-warning">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $kpi( 'medication_exceptions' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Exceptions', 'jm-referral-system' ); ?></span>
			</div>
		</div>
	</section>

	<section class="jmrs-report-section">
		<h2><?php echo esc_html__( 'Compliance', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-report-kpis">
			<div class="jmrs-report-kpi is-critical">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $kpi( 'operational_alerts' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Operational Alerts', 'jm-referral-system' ); ?></span>
			</div>
		</div>
		<?php if ( '' !== $alerts_url ) : ?>
			<p class="jmrs-no-print">
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
			$section_id    = (string) ( $section['id'] ?? '' );
			$section_title = (string) ( $section['title'] ?? '' );
			$datasets      = is_array( $section['datasets'] ?? null ) ? $section['datasets'] : array();
			$section_csv   = (string) ( $section_export_urls[ $section_id ] ?? '' );
			?>
			<section class="jmrs-report-section" id="jmrs-section-<?php echo esc_attr( $section_id ); ?>">
				<div class="jmrs-report-section-header">
					<h2><?php echo esc_html( $section_title ); ?></h2>
					<?php if ( '' !== $section_csv ) : ?>
						<p class="jmrs-report-section-actions jmrs-no-print" style="margin:0;">
							<a class="button" href="<?php echo esc_url( $section_csv ); ?>">
								<?php echo esc_html__( 'Export Section CSV', 'jm-referral-system' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>

				<?php foreach ( $datasets as $dataset ) : ?>
					<?php
					$dataset_id      = (string) ( $dataset['id'] ?? '' );
					$dataset_title   = (string) ( $dataset['title'] ?? '' );
					$dataset_note    = (string) ( $dataset['note'] ?? '' );
					$dataset_rows    = is_array( $dataset['rows'] ?? null ) ? $dataset['rows'] : array();
					$chart_enabled   = ! empty( $dataset['chart_enabled'] );
					$chart_has_data  = ! empty( $dataset['chart_has_data'] );
					$aria_label      = sprintf(
						/* translators: %s: dataset title */
						__( 'Chart for %s', 'jm-referral-system' ),
						$dataset_title
					);
					?>
					<div class="jmrs-report-dataset" id="jmrs-dataset-<?php echo esc_attr( $dataset_id ); ?>">
						<h3><?php echo esc_html( $dataset_title ); ?></h3>
						<?php if ( '' !== $dataset_note ) : ?>
							<p class="jmrs-report-dataset-note"><?php echo esc_html( $dataset_note ); ?></p>
						<?php endif; ?>

						<div class="jmrs-report-dataset-grid">
							<div>
								<table class="widefat striped" aria-describedby="jmrs-dataset-<?php echo esc_attr( $dataset_id ); ?>">
									<thead>
										<tr>
											<th scope="col"><?php echo esc_html__( 'Label', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Value', 'jm-referral-system' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php if ( empty( $dataset_rows ) ) : ?>
											<tr>
												<td colspan="2"><?php echo esc_html__( 'No data available for this period.', 'jm-referral-system' ); ?></td>
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

							<?php if ( $chart_enabled ) : ?>
								<div class="jmrs-chart-wrap">
									<canvas
										id="jmrs-chart-<?php echo esc_attr( $dataset_id ); ?>"
										role="img"
										aria-label="<?php echo esc_attr( $aria_label ); ?>"
										<?php echo $chart_has_data ? '' : 'class="jmrs-chart-hidden"'; ?>
									>
										<?php echo esc_html__( 'Chart visualisation requires JavaScript. See the summary table for values.', 'jm-referral-system' ); ?>
									</canvas>
									<p
										id="jmrs-chart-empty-<?php echo esc_attr( $dataset_id ); ?>"
										class="jmrs-chart-empty"
										<?php echo $chart_has_data ? 'hidden' : ''; ?>
									>
										<?php echo esc_html__( 'No data available for this period.', 'jm-referral-system' ); ?>
									</p>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
