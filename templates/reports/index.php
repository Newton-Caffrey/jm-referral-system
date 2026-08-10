<?php
/**
 * Operational reports admin page.
 *
 * @package JMReferral
 *
 * @var array<string, int|float|null> $kpis
 * @var array<string, mixed> $supported_living
 * @var array<string, mixed> $vacancy
 * @var array<string, mixed> $placement_movements
 * @var array<string, mixed> $acquisition
 * @var array<string, mixed> $visit_filters
 * @var array<string, string> $range_labels
 * @var string $filter_range
 * @var string $filter_start
 * @var string $filter_end
 * @var array<string, string> $filter_errors
 * @var int $filter_home_id
 * @var string $filter_visit_care_context
 * @var int $filter_visit_home_id
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
$supported_living    = is_array( $supported_living ?? null ) ? $supported_living : array();
$vacancy             = is_array( $vacancy ?? null ) ? $vacancy : array();
$placement_movements = is_array( $placement_movements ?? null ) ? $placement_movements : array();
$acquisition         = is_array( $acquisition ?? null ) ? $acquisition : array();
$visit_filters       = is_array( $visit_filters ?? null ) ? $visit_filters : array();
$range_labels        = is_array( $range_labels ?? null ) ? $range_labels : array();
$filter_range        = (string) ( $filter_range ?? 'this_month' );
$filter_start        = (string) ( $filter_start ?? '' );
$filter_end          = (string) ( $filter_end ?? '' );
$filter_errors       = is_array( $filter_errors ?? null ) ? $filter_errors : array();
$filter_home_id      = absint( $filter_home_id ?? ( $vacancy['home_id'] ?? 0 ) );
$filter_visit_care_context = (string) ( $filter_visit_care_context ?? ( $visit_filters['care_context'] ?? 'all' ) );
$filter_visit_home_id      = absint( $filter_visit_home_id ?? ( $visit_filters['home_id'] ?? 0 ) );
$visit_care_options        = is_array( $visit_filters['care_context_options'] ?? null )
	? $visit_filters['care_context_options']
	: array();
$visit_home_options        = is_array( $visit_filters['homes_options'] ?? null )
	? $visit_filters['homes_options']
	: array();
$visit_home_invalid        = ! empty( $visit_filters['invalid_home'] );
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

$sl_metric = static function ( string $key ) use ( $supported_living ): string {
	$value = $supported_living[ $key ] ?? 0;
	if ( is_float( $value ) ) {
		return (string) $value;
	}

	return (string) absint( $value );
};

$format_pct = static function ( float $pct ): string {
	$formatted = rtrim( rtrim( number_format( $pct, 1, '.', '' ), '0' ), '.' );
	if ( '' === $formatted ) {
		$formatted = '0';
	}

	return $formatted . '%';
};

$sl_occupancy_pct = static function () use ( $supported_living, $format_pct ): string {
	return $format_pct( (float) ( $supported_living['occupancy_percent'] ?? 0 ) );
};

$vacancy_metrics     = is_array( $vacancy['metrics'] ?? null ) ? $vacancy['metrics'] : array();
$vacancy_rows        = is_array( $vacancy['rows'] ?? null ) ? $vacancy['rows'] : array();
$vacancy_homes       = is_array( $vacancy['homes_options'] ?? null ) ? $vacancy['homes_options'] : array();
$vacancy_can_view    = ! empty( $vacancy['can_view_detail'] );
$vacancy_empty       = (string) ( $vacancy['empty_message'] ?? '' );
$vacancy_invalid     = ! empty( $vacancy['home_invalid'] );
$vacancy_section_csv = (string) ( $section_export_urls['supported_living_vacancies'] ?? '' );
$movements_section_csv = (string) ( $section_export_urls['placement_movements'] ?? '' );
$movements_rows      = is_array( $placement_movements['rows'] ?? null ) ? $placement_movements['rows'] : array();
$movements_empty     = (string) ( $placement_movements['empty_message'] ?? __( 'No placement movements were recorded during this period.', 'jm-referral-system' ) );
$movements_ui_limited = ! empty( $placement_movements['ui_limited'] );

$vacancy_metric = static function ( string $key ) use ( $vacancy_metrics ): string {
	$value = $vacancy_metrics[ $key ] ?? 0;
	if ( is_float( $value ) ) {
		return (string) $value;
	}

	return (string) absint( $value );
};

$movement_metric = static function ( string $key ) use ( $placement_movements ): string {
	return (string) absint( $placement_movements[ $key ] ?? 0 );
};

$sl_active_homes = absint( $supported_living['active_homes'] ?? 0 );

$sl_section         = null;
$movements_section  = null;
$acquisition_section = null;
$remaining_sections = array();
foreach ( $sections as $candidate_section ) {
	$candidate_id = (string) ( $candidate_section['id'] ?? '' );
	if ( 'acquisition_pipeline' === $candidate_id ) {
		$acquisition_section = $candidate_section;
		continue;
	}
	if ( 'supported_living_snapshot' === $candidate_id ) {
		$sl_section = $candidate_section;
		continue;
	}
	if ( 'supported_living_vacancies' === $candidate_id ) {
		continue;
	}
	if ( 'placement_movements' === $candidate_id ) {
		$movements_section = $candidate_section;
		continue;
	}
	$remaining_sections[] = $candidate_section;
}
$sl_section_csv          = (string) ( $section_export_urls['supported_living_snapshot'] ?? '' );
$acquisition_section_csv = (string) ( $section_export_urls['acquisition_pipeline'] ?? '' );
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

		<fieldset class="jmrs-report-filter-group">
			<legend><?php echo esc_html__( 'Report Period', 'jm-referral-system' ); ?></legend>
			<div class="jmrs-filter-row">
				<div class="jmrs-filter-field">
					<label for="jmrs_report_range"><?php echo esc_html__( 'Date Range', 'jm-referral-system' ); ?></label>
					<select name="jmrs_report_range" id="jmrs_report_range">
						<?php foreach ( $range_labels as $value => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $filter_range, (string) $value ); ?>>
								<?php echo esc_html( (string) $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="jmrs-filter-field">
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
				<div class="jmrs-filter-field">
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
				<div class="jmrs-filter-field jmrs-filter-field--actions">
					<?php submit_button( __( 'Apply', 'jm-referral-system' ), 'primary', '', false ); ?>
					<a class="button" href="<?php echo esc_url( $reports_url ); ?>">
						<?php echo esc_html__( 'Reset Filters', 'jm-referral-system' ); ?>
					</a>
				</div>
			</div>
			<p class="description jmrs-report-filter-hint">
				<?php echo esc_html__( 'Applies to Placement Movements, Visit Analytics, and existing referral / medication / task / staff / compliance reports. Current Snapshot and Vacancy figures are not limited by this date range.', 'jm-referral-system' ); ?>
			</p>
		</fieldset>

		<fieldset class="jmrs-report-filter-group">
			<legend><?php echo esc_html__( 'Vacancy Home', 'jm-referral-system' ); ?></legend>
			<div class="jmrs-filter-row">
				<div class="jmrs-filter-field">
					<label for="jmrs_report_home"><?php echo esc_html__( 'Supported Living Home', 'jm-referral-system' ); ?></label>
					<select name="jmrs_report_home" id="jmrs_report_home" <?php disabled( ! $vacancy_can_view ); ?>>
						<option value="0" <?php selected( $filter_home_id, 0 ); ?>>
							<?php echo esc_html__( 'All Active Homes', 'jm-referral-system' ); ?>
						</option>
						<?php foreach ( $vacancy_homes as $home_option ) : ?>
							<?php
							$option_id   = absint( $home_option['id'] ?? 0 );
							$option_name = (string) ( $home_option['name'] ?? '' );
							if ( $option_id <= 0 ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( (string) $option_id ); ?>" <?php selected( $filter_home_id, $option_id ); ?>>
								<?php echo esc_html( $option_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<p class="description jmrs-report-filter-hint">
				<?php echo esc_html__( 'Applies to the Vacancy Report only. Does not affect Placement Movements or Visit Analytics.', 'jm-referral-system' ); ?>
			</p>
			<?php if ( $vacancy_invalid ) : ?>
				<p class="description"><?php echo esc_html__( 'The selected home was invalid or inactive. Showing all active homes.', 'jm-referral-system' ); ?></p>
			<?php endif; ?>
		</fieldset>

		<fieldset class="jmrs-report-filter-group">
			<legend><?php echo esc_html__( 'Visit Analytics Filters', 'jm-referral-system' ); ?></legend>
			<div class="jmrs-filter-row jmrs-filter-row--visit-context">
				<div class="jmrs-filter-field">
					<label for="jmrs_visit_care_context"><?php echo esc_html__( 'Visit Care Delivery', 'jm-referral-system' ); ?></label>
					<select name="jmrs_visit_care_context" id="jmrs_visit_care_context">
						<?php foreach ( $visit_care_options as $ctx_value => $ctx_label ) : ?>
							<option value="<?php echo esc_attr( (string) $ctx_value ); ?>" <?php selected( $filter_visit_care_context, (string) $ctx_value ); ?>>
								<?php echo esc_html( (string) $ctx_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="jmrs-filter-field">
					<label for="jmrs_visit_home"><?php echo esc_html__( 'Visit Home', 'jm-referral-system' ); ?></label>
					<select name="jmrs_visit_home" id="jmrs_visit_home">
						<option value="0" <?php selected( $filter_visit_home_id, 0 ); ?>>
							<?php echo esc_html__( 'All Homes', 'jm-referral-system' ); ?>
						</option>
						<?php foreach ( $visit_home_options as $visit_home_option ) : ?>
							<?php
							$v_home_id   = absint( $visit_home_option['id'] ?? 0 );
							$v_home_name = (string) ( $visit_home_option['name'] ?? '' );
							if ( $v_home_id <= 0 ) {
								continue;
							}
							if ( ! empty( $visit_home_option['is_inactive'] ) ) {
								$v_home_name .= ' ' . __( '(Inactive)', 'jm-referral-system' );
							}
							?>
							<option value="<?php echo esc_attr( (string) $v_home_id ); ?>" <?php selected( $filter_visit_home_id, $v_home_id ); ?>>
								<?php echo esc_html( $v_home_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<p class="description jmrs-report-filter-hint">
				<?php echo esc_html__( 'Applies to Visit KPIs, Visit Analytics, visit-linked Task metrics, and visit-completed Staff metrics only. Separate from Vacancy Home.', 'jm-referral-system' ); ?>
			</p>
			<?php if ( $visit_home_invalid ) : ?>
				<p class="description"><?php echo esc_html__( 'The selected Visit home was invalid for this date range. Showing all homes.', 'jm-referral-system' ); ?></p>
			<?php endif; ?>
		</fieldset>
	</form>

	<?php
	$visit_filters_active = ! empty( $visit_filters['is_active'] );
	$visit_kpi_total      = absint( $kpis['visits_scheduled'] ?? 0 )
		+ absint( $kpis['visits_completed'] ?? 0 )
		+ absint( $kpis['visits_missed'] ?? 0 );
	$visit_filter_empty   = $visit_filters_active && 0 === $visit_kpi_total;
	?>

	<?php if ( '' !== $filter_start && '' !== $filter_end && empty( $filter_errors ) ) : ?>
		<p class="jmrs-report-range-note">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: start date, 2: end date */
					__( 'Period reports showing data from %1$s to %2$s. Current Snapshot and Vacancy sections remain as of today.', 'jm-referral-system' ),
					$filter_start,
					$filter_end
				)
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( $visit_filters_active ) : ?>
		<p class="jmrs-report-filter-active-note">
			<?php
			$visit_ctx_label  = (string) ( $visit_filters['care_context_label'] ?? '' );
			$visit_home_label = trim( (string) ( $visit_filters['home_name'] ?? '' ) );
			if ( '' === $visit_home_label ) {
				$visit_home_label = __( 'All Homes', 'jm-referral-system' );
			}
			echo esc_html(
				sprintf(
					/* translators: 1: care delivery label, 2: home label */
					__( 'Visit filters active: Care Delivery = %1$s; Home = %2$s.', 'jm-referral-system' ),
					$visit_ctx_label,
					$visit_home_label
				)
			);
			?>
		</p>
	<?php endif; ?>

	<?php
	include JMRS_PLUGIN_PATH . 'templates/reports/partials/acquisition.php';
	?>

	<section class="jmrs-report-section jmrs-report-section--snapshot" id="jmrs-supported-living-snapshot">
		<div class="jmrs-report-section-header">
			<h2><?php echo esc_html__( 'Supported Living — Current Snapshot', 'jm-referral-system' ); ?></h2>
			<span class="jmrs-report-badge jmrs-report-badge--snapshot"><?php echo esc_html__( 'Current Snapshot', 'jm-referral-system' ); ?></span>
			<?php if ( '' !== $sl_section_csv ) : ?>
				<p class="jmrs-report-section-actions jmrs-no-print">
					<a class="button" href="<?php echo esc_url( $sl_section_csv ); ?>">
						<?php echo esc_html__( 'Export Section CSV', 'jm-referral-system' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<p class="jmrs-report-snapshot-note">
			<?php echo esc_html__( 'Current estate and care-delivery position as of today. These figures are not affected by the selected report date range.', 'jm-referral-system' ); ?>
		</p>

		<?php if ( 0 === $sl_active_homes ) : ?>
			<p class="jmrs-report-empty-state">
				<?php echo esc_html__( 'No active Supported Living homes have been added.', 'jm-referral-system' ); ?>
			</p>
		<?php endif; ?>

		<h3><?php echo esc_html__( 'Estate', 'jm-referral-system' ); ?></h3>
		<div class="jmrs-report-kpis" role="list">
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $sl_metric( 'active_homes' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Active Homes', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $sl_metric( 'capacity' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Capacity', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $sl_metric( 'occupied' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Occupied', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $sl_metric( 'vacant' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Vacant', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $sl_occupancy_pct() ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Occupancy %', 'jm-referral-system' ); ?></span>
			</div>
		</div>

		<h3><?php echo esc_html__( 'Care Delivery', 'jm-referral-system' ); ?></h3>
		<div class="jmrs-report-kpis" role="list">
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $sl_metric( 'supported_living' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Supported Living Clients', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $sl_metric( 'awaiting_placement' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Awaiting Placement', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $sl_metric( 'own_home' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( "Client's Own Home", 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $sl_metric( 'not_specified' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Not Specified', 'jm-referral-system' ); ?></span>
			</div>
		</div>

		<?php
		$sl_datasets = is_array( $sl_section['datasets'] ?? null ) ? $sl_section['datasets'] : array();
		foreach ( $sl_datasets as $dataset ) :
			if ( ! empty( $dataset['ui_hidden'] ) ) {
				continue;
			}

			$dataset_id      = (string) ( $dataset['id'] ?? '' );
			$dataset_title   = (string) ( $dataset['title'] ?? '' );
			$dataset_note    = (string) ( $dataset['note'] ?? '' );
			$dataset_rows    = is_array( $dataset['rows'] ?? null ) ? $dataset['rows'] : array();
			$table_columns   = is_array( $dataset['table_columns'] ?? null ) ? $dataset['table_columns'] : array();
			$table_rows      = is_array( $dataset['table_rows'] ?? null ) ? $dataset['table_rows'] : array();
			$empty_message   = (string) ( $dataset['empty_message'] ?? __( 'No data available for this period.', 'jm-referral-system' ) );
			$chart_enabled   = ! empty( $dataset['chart_enabled'] );
			$chart_has_data  = ! empty( $dataset['chart_has_data'] );
			$use_multi_table = ! empty( $table_columns );
			$aria_label      = sprintf(
				/* translators: %s: dataset title */
				__( 'Chart for %s', 'jm-referral-system' ),
				$dataset_title
			);
			$col_count = $use_multi_table ? count( $table_columns ) : 2;
			?>
			<div class="jmrs-report-dataset" id="jmrs-dataset-<?php echo esc_attr( $dataset_id ); ?>">
				<h3><?php echo esc_html( $dataset_title ); ?></h3>
				<?php if ( '' !== $dataset_note ) : ?>
					<p class="jmrs-report-dataset-note"><?php echo esc_html( $dataset_note ); ?></p>
				<?php endif; ?>

				<div class="jmrs-report-dataset-grid">
					<div class="jmrs-report-table-wrap">
						<table class="widefat striped" aria-describedby="jmrs-dataset-<?php echo esc_attr( $dataset_id ); ?>">
							<thead>
								<tr>
									<?php if ( $use_multi_table ) : ?>
										<?php foreach ( $table_columns as $column_label ) : ?>
											<th scope="col"><?php echo esc_html( (string) $column_label ); ?></th>
										<?php endforeach; ?>
									<?php else : ?>
										<th scope="col"><?php echo esc_html__( 'Label', 'jm-referral-system' ); ?></th>
										<th scope="col"><?php echo esc_html__( 'Value', 'jm-referral-system' ); ?></th>
									<?php endif; ?>
								</tr>
							</thead>
							<tbody>
								<?php if ( $use_multi_table ) : ?>
									<?php if ( empty( $table_rows ) ) : ?>
										<tr>
											<td colspan="<?php echo esc_attr( (string) max( 1, $col_count ) ); ?>">
												<?php echo esc_html( $empty_message ); ?>
											</td>
										</tr>
									<?php else : ?>
										<?php foreach ( $table_rows as $row ) : ?>
											<tr>
												<?php
												$cells = is_array( $row ) ? $row : array();
												for ( $i = 0; $i < $col_count; $i++ ) {
													echo '<td>' . esc_html( (string) ( $cells[ $i ] ?? '' ) ) . '</td>';
												}
												?>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								<?php elseif ( empty( $dataset_rows ) ) : ?>
									<tr>
										<td colspan="2"><?php echo esc_html( $empty_message ); ?></td>
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
								<?php echo esc_html( $empty_message ); ?>
							</p>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</section>

	<section class="jmrs-report-section jmrs-report-section--snapshot" id="jmrs-vacancy-report">
		<div class="jmrs-report-section-header">
			<h2><?php echo esc_html__( 'Vacancy Report — Current Snapshot', 'jm-referral-system' ); ?></h2>
			<span class="jmrs-report-badge jmrs-report-badge--snapshot"><?php echo esc_html__( 'Current Snapshot', 'jm-referral-system' ); ?></span>
			<?php if ( $vacancy_can_view && '' !== $vacancy_section_csv ) : ?>
				<p class="jmrs-report-section-actions jmrs-no-print">
					<a class="button" href="<?php echo esc_url( $vacancy_section_csv ); ?>">
						<?php echo esc_html__( 'Export Vacancy CSV', 'jm-referral-system' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<p class="jmrs-report-snapshot-note">
			<?php echo esc_html__( 'Current vacant bedrooms in active homes. These figures are not affected by the selected report date range. Vacancy Home filter applies here only.', 'jm-referral-system' ); ?>
		</p>

		<?php if ( ! $vacancy_can_view ) : ?>
			<p class="jmrs-report-empty-state">
				<?php echo esc_html__( 'Detailed vacancy reporting requires permission to view Supported Living homes.', 'jm-referral-system' ); ?>
			</p>
		<?php else : ?>
			<div class="jmrs-report-kpis" role="list">
				<div class="jmrs-report-kpi" role="listitem">
					<span class="jmrs-report-kpi-number"><?php echo esc_html( $vacancy_metric( 'capacity' ) ); ?></span>
					<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Capacity', 'jm-referral-system' ); ?></span>
				</div>
				<div class="jmrs-report-kpi" role="listitem">
					<span class="jmrs-report-kpi-number"><?php echo esc_html( $vacancy_metric( 'occupied' ) ); ?></span>
					<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Occupied', 'jm-referral-system' ); ?></span>
				</div>
				<div class="jmrs-report-kpi" role="listitem">
					<span class="jmrs-report-kpi-number"><?php echo esc_html( $vacancy_metric( 'vacant' ) ); ?></span>
					<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Vacant', 'jm-referral-system' ); ?></span>
				</div>
				<div class="jmrs-report-kpi" role="listitem">
					<span class="jmrs-report-kpi-number"><?php echo esc_html( $format_pct( (float) ( $vacancy_metrics['occupancy_percent'] ?? 0 ) ) ); ?></span>
					<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Occupancy %', 'jm-referral-system' ); ?></span>
				</div>
			</div>

			<div class="jmrs-report-dataset jmrs-report-dataset--vacancy">
				<h3><?php echo esc_html__( 'Current Vacancies', 'jm-referral-system' ); ?></h3>
				<p class="jmrs-report-dataset-note">
					<?php echo esc_html__( 'Vacant Since is the most recent recorded occupancy end date for the bedroom. Bedrooms with no occupancy history show “Never occupied”.', 'jm-referral-system' ); ?>
				</p>
				<div class="jmrs-report-table-wrap">
					<table class="widefat striped jmrs-report-vacancy-table">
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html__( 'Home', 'jm-referral-system' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Bedroom', 'jm-referral-system' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Location', 'jm-referral-system' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Vacant Since', 'jm-referral-system' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $vacancy_rows ) ) : ?>
								<tr>
									<td colspan="5"><?php echo esc_html( $vacancy_empty ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $vacancy_rows as $vacancy_row ) : ?>
									<tr>
										<td><?php echo esc_html( (string) ( $vacancy_row['home_name'] ?? '' ) ); ?></td>
										<td><?php echo esc_html( (string) ( $vacancy_row['room_label'] ?? '' ) ); ?></td>
										<td><?php echo esc_html( (string) ( $vacancy_row['location'] ?? '' ) ); ?></td>
										<td><?php echo esc_html( (string) ( $vacancy_row['vacant_since_label'] ?? '' ) ); ?></td>
										<td><?php echo esc_html( (string) ( $vacancy_row['status_label'] ?? '' ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>
	</section>

	<section class="jmrs-report-section jmrs-report-section--period" id="jmrs-placement-movements">
		<div class="jmrs-report-section-header">
			<h2><?php echo esc_html__( 'Placement Movements — Selected Period', 'jm-referral-system' ); ?></h2>
			<span class="jmrs-report-badge jmrs-report-badge--period"><?php echo esc_html__( 'Selected Period', 'jm-referral-system' ); ?></span>
			<?php if ( '' !== $movements_section_csv ) : ?>
				<p class="jmrs-report-section-actions jmrs-no-print">
					<a class="button" href="<?php echo esc_url( $movements_section_csv ); ?>">
						<?php echo esc_html__( 'Export Movements CSV', 'jm-referral-system' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<p class="jmrs-report-snapshot-note">
			<?php echo esc_html__( 'Movement figures are based on placement events recorded in JMRS during the selected period. Counts use when the event was logged (activity.created_at), not backdated move-in or move-out dates. Vacancy Home and Visit filters do not apply here.', 'jm-referral-system' ); ?>
		</p>

		<div class="jmrs-report-kpis" role="list">
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $movement_metric( 'new_placements' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'New Placements', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $movement_metric( 'transfers' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Transfers', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $movement_metric( 'placements_ended' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Placements Ended', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-report-kpi" role="listitem">
				<span class="jmrs-report-kpi-number"><?php echo esc_html( $movement_metric( 'total_events' ) ); ?></span>
				<span class="jmrs-report-kpi-label"><?php echo esc_html__( 'Total Placement Events', 'jm-referral-system' ); ?></span>
			</div>
		</div>

		<?php
		$movements_datasets = is_array( $movements_section['datasets'] ?? null ) ? $movements_section['datasets'] : array();
		foreach ( $movements_datasets as $dataset ) {
			if ( ! empty( $dataset['ui_hidden'] ) ) {
				continue;
			}
			if ( 'placement_movements_detail' === (string) ( $dataset['id'] ?? '' ) ) {
				continue;
			}

			$dataset_id     = (string) ( $dataset['id'] ?? '' );
			$dataset_title  = (string) ( $dataset['title'] ?? '' );
			$dataset_note   = (string) ( $dataset['note'] ?? '' );
			$dataset_rows   = is_array( $dataset['rows'] ?? null ) ? $dataset['rows'] : array();
			$chart_enabled  = ! empty( $dataset['chart_enabled'] );
			$chart_has_data = ! empty( $dataset['chart_has_data'] );
			$aria_label     = sprintf(
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
					<div class="jmrs-report-table-wrap">
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
										<td colspan="2"><?php echo esc_html( $movements_empty ); ?></td>
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
								<?php echo esc_html( $movements_empty ); ?>
							</p>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}
		?>

		<div class="jmrs-report-dataset jmrs-report-dataset--movements">
			<h3><?php echo esc_html__( 'Placement Movements — Selected Period', 'jm-referral-system' ); ?></h3>
			<?php if ( $movements_ui_limited ) : ?>
				<p class="jmrs-report-dataset-note">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: row limit */
							__( 'Showing the latest %d events. Export Movements CSV for the full result set.', 'jm-referral-system' ),
							absint( $placement_movements['ui_limit'] ?? 100 )
						)
					);
					?>
				</p>
			<?php endif; ?>
			<div class="jmrs-report-table-wrap">
				<table class="widefat striped jmrs-report-movements-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Recorded Date', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Event', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Referral Number', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Details', 'jm-referral-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $movements_rows ) ) : ?>
							<tr>
								<td colspan="5"><?php echo esc_html( $movements_empty ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $movements_rows as $movement_row ) : ?>
								<tr>
									<td><?php echo esc_html( (string) ( $movement_row['recorded_date_label'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( (string) ( $movement_row['event_label'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( (string) ( $movement_row['referral_number'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( (string) ( $movement_row['client_name'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( (string) ( $movement_row['details'] ?? '' ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>

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

	<section class="jmrs-report-section jmrs-report-section--period" id="jmrs-visits-kpis">
		<div class="jmrs-report-section-header">
			<h2><?php echo esc_html__( 'Visits', 'jm-referral-system' ); ?></h2>
			<span class="jmrs-report-badge jmrs-report-badge--period"><?php echo esc_html__( 'Selected Period', 'jm-referral-system' ); ?></span>
		</div>
		<p class="jmrs-report-snapshot-note">
			<?php echo esc_html__( 'Executed visits are reported against the service location recorded when care was delivered. Upcoming visits use the client\'s current service location. Legacy, missed or cancelled visits without a recorded historical location may appear as Location Not Recorded.', 'jm-referral-system' ); ?>
		</p>
		<?php if ( $visit_filter_empty ) : ?>
			<p class="jmrs-report-empty-state">
				<?php echo esc_html__( 'No visits match the selected care-delivery filters.', 'jm-referral-system' ); ?>
			</p>
		<?php endif; ?>
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

	<?php if ( ! empty( $remaining_sections ) ) : ?>
		<hr />
		<h2><?php echo esc_html__( 'Trends & Analytics', 'jm-referral-system' ); ?></h2>

		<?php foreach ( $remaining_sections as $section ) : ?>
			<?php
			$section_id    = (string) ( $section['id'] ?? '' );
			$section_title = (string) ( $section['title'] ?? '' );
			$datasets      = is_array( $section['datasets'] ?? null ) ? $section['datasets'] : array();
			$section_csv   = (string) ( $section_export_urls[ $section_id ] ?? '' );
			?>
			<section class="jmrs-report-section<?php echo 'visit_analytics' === $section_id ? ' jmrs-report-section--period' : ''; ?>" id="jmrs-section-<?php echo esc_attr( $section_id ); ?>">
				<div class="jmrs-report-section-header">
					<h2><?php echo esc_html( $section_title ); ?></h2>
					<?php if ( 'visit_analytics' === $section_id ) : ?>
						<span class="jmrs-report-badge jmrs-report-badge--period"><?php echo esc_html__( 'Selected Period', 'jm-referral-system' ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $section_csv ) : ?>
						<p class="jmrs-report-section-actions jmrs-no-print">
							<a class="button" href="<?php echo esc_url( $section_csv ); ?>">
								<?php echo esc_html__( 'Export Section CSV', 'jm-referral-system' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>

				<?php
				$section_notes = is_array( $section['notes'] ?? null ) ? $section['notes'] : array();
				foreach ( $section_notes as $section_note ) :
					$section_note = trim( (string) $section_note );
					if ( '' === $section_note ) {
						continue;
					}
					?>
					<p class="jmrs-report-section-note"><?php echo esc_html( $section_note ); ?></p>
				<?php endforeach; ?>

				<?php if ( 'visit_analytics' === $section_id && $visit_filter_empty ) : ?>
					<p class="jmrs-report-empty-state">
						<?php echo esc_html__( 'No visits match the selected care-delivery filters.', 'jm-referral-system' ); ?>
					</p>
				<?php endif; ?>

				<?php foreach ( $datasets as $dataset ) : ?>
					<?php
					$dataset_id     = (string) ( $dataset['id'] ?? '' );
					$dataset_title  = (string) ( $dataset['title'] ?? '' );
					$dataset_note   = (string) ( $dataset['note'] ?? '' );
					$dataset_rows   = is_array( $dataset['rows'] ?? null ) ? $dataset['rows'] : array();
					$empty_message  = (string) ( $dataset['empty_message'] ?? '' );
					if ( '' === $empty_message ) {
						$empty_message = ( 'visit_analytics' === $section_id && $visit_filter_empty )
							? __( 'No visits match the selected care-delivery filters.', 'jm-referral-system' )
							: __( 'No data available for this period.', 'jm-referral-system' );
					}
					$chart_enabled  = ! empty( $dataset['chart_enabled'] );
					$chart_has_data = ! empty( $dataset['chart_has_data'] );
					$aria_label     = sprintf(
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
							<div class="jmrs-report-table-wrap">
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
												<td colspan="2"><?php echo esc_html( $empty_message ); ?></td>
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
										<?php echo esc_html( $empty_message ); ?>
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
