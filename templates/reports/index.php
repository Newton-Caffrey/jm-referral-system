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
</div>
