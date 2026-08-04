<?php
/**
 * Operational alerts admin page.
 *
 * @package JMReferral
 *
 * @var array<int, array<string, mixed>> $alerts
 * @var array{critical: int, warning: int, information: int, total: int} $counts
 * @var array<string, string> $type_labels
 * @var array<string, string> $severity_labels
 * @var string $filter_severity
 * @var string $filter_type
 * @var string $filter_search
 * @var string $alerts_page_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$alerts          = is_array( $alerts ?? null ) ? $alerts : array();
$counts          = is_array( $counts ?? null ) ? $counts : array();
$type_labels     = is_array( $type_labels ?? null ) ? $type_labels : array();
$severity_labels = is_array( $severity_labels ?? null ) ? $severity_labels : array();
$filter_severity = (string) ( $filter_severity ?? '' );
$filter_type     = (string) ( $filter_type ?? '' );
$filter_search   = (string) ( $filter_search ?? '' );
$alerts_page_url = (string) ( $alerts_page_url ?? '' );

$critical_count    = absint( $counts['critical'] ?? 0 );
$warning_count     = absint( $counts['warning'] ?? 0 );
$information_count = absint( $counts['information'] ?? 0 );
$total_count       = absint( $counts['total'] ?? 0 );
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Operational Alerts', 'jm-referral-system' ); ?></h1>

	<style>
		.jmrs-alert-stats {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
			gap: 12px;
			margin: 16px 0 20px;
		}
		.jmrs-alert-stat {
			background: #fff;
			border: 1px solid #c3c4c7;
			box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
			padding: 14px;
		}
		.jmrs-alert-stat-number {
			display: block;
			font-size: 26px;
			font-weight: 600;
			line-height: 1.2;
		}
		.jmrs-alert-stat-label {
			color: #646970;
			font-size: 13px;
		}
		.jmrs-alert-stat.is-critical .jmrs-alert-stat-number { color: #b32d2e; }
		.jmrs-alert-stat.is-warning .jmrs-alert-stat-number { color: #996800; }
		.jmrs-alert-filters {
			background: #fff;
			border: 1px solid #c3c4c7;
			padding: 12px 16px;
			margin: 0 0 20px;
		}
		.jmrs-alert-filters .jmrs-filter-row {
			display: flex;
			flex-wrap: wrap;
			gap: 12px;
			align-items: flex-end;
		}
		.jmrs-alert-filters label {
			display: block;
			font-weight: 600;
			margin-bottom: 4px;
		}
		.jmrs-severity-critical { color: #b32d2e; font-weight: 600; }
		.jmrs-severity-warning { color: #996800; font-weight: 600; }
		.jmrs-severity-information { color: #2271b1; font-weight: 600; }
	</style>

	<div class="jmrs-alert-stats">
		<div class="jmrs-alert-stat is-critical">
			<span class="jmrs-alert-stat-number"><?php echo esc_html( (string) $critical_count ); ?></span>
			<span class="jmrs-alert-stat-label"><?php echo esc_html__( 'Critical', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-alert-stat is-warning">
			<span class="jmrs-alert-stat-number"><?php echo esc_html( (string) $warning_count ); ?></span>
			<span class="jmrs-alert-stat-label"><?php echo esc_html__( 'Warning', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-alert-stat">
			<span class="jmrs-alert-stat-number"><?php echo esc_html( (string) $information_count ); ?></span>
			<span class="jmrs-alert-stat-label"><?php echo esc_html__( 'Information', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-alert-stat">
			<span class="jmrs-alert-stat-number"><?php echo esc_html( (string) $total_count ); ?></span>
			<span class="jmrs-alert-stat-label"><?php echo esc_html__( 'Total Alerts', 'jm-referral-system' ); ?></span>
		</div>
	</div>

	<form class="jmrs-alert-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="jm-referrals-operational-alerts" />
		<div class="jmrs-filter-row">
			<div>
				<label for="jmrs_alert_severity"><?php echo esc_html__( 'Severity', 'jm-referral-system' ); ?></label>
				<select name="jmrs_alert_severity" id="jmrs_alert_severity">
					<option value=""><?php echo esc_html__( 'All severities', 'jm-referral-system' ); ?></option>
					<?php foreach ( $severity_labels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $filter_severity, (string) $value ); ?>>
							<?php echo esc_html( (string) $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="jmrs_alert_type"><?php echo esc_html__( 'Alert type', 'jm-referral-system' ); ?></label>
				<select name="jmrs_alert_type" id="jmrs_alert_type">
					<option value=""><?php echo esc_html__( 'All types', 'jm-referral-system' ); ?></option>
					<?php foreach ( $type_labels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $filter_type, (string) $value ); ?>>
							<?php echo esc_html( (string) $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="jmrs_alert_search"><?php echo esc_html__( 'Search', 'jm-referral-system' ); ?></label>
				<input
					type="search"
					name="jmrs_alert_search"
					id="jmrs_alert_search"
					value="<?php echo esc_attr( $filter_search ); ?>"
					placeholder="<?php echo esc_attr__( 'Referral number or client name', 'jm-referral-system' ); ?>"
				/>
			</div>
			<div>
				<button type="submit" class="button button-primary"><?php echo esc_html__( 'Filter', 'jm-referral-system' ); ?></button>
				<a class="button" href="<?php echo esc_url( $alerts_page_url ); ?>"><?php echo esc_html__( 'Reset', 'jm-referral-system' ); ?></a>
			</div>
		</div>
	</form>

	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Severity', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Alert', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Client / Referral', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Due or Occurred', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Action', 'jm-referral-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $alerts ) ) : ?>
				<tr class="no-items">
					<td colspan="5"><?php echo esc_html__( 'No operational alerts match your filters.', 'jm-referral-system' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $alerts as $alert ) : ?>
					<?php
					$severity_key   = (string) ( $alert['severity'] ?? '' );
					$severity_label = $severity_labels[ $severity_key ] ?? $severity_key;
					$title          = (string) ( $alert['title'] ?? '' );
					$description    = (string) ( $alert['description'] ?? '' );
					$client_name    = (string) ( $alert['client_name'] ?? '' );
					$referral_number = (string) ( $alert['referral_number'] ?? '' );
					$due_raw        = (string) ( $alert['occurred_or_due_date'] ?? '' );
					$action_url     = (string) ( $alert['action_url'] ?? '' );
					$action_label   = (string) ( $alert['action_label'] ?? __( 'View', 'jm-referral-system' ) );
					$severity_class = 'jmrs-severity-' . sanitize_html_class( $severity_key );

					$due_display = '—';
					if ( '' !== $due_raw ) {
						if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $due_raw ) === 1 ) {
							$due_display = mysql2date( get_option( 'date_format' ), $due_raw );
						} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}/', $due_raw ) === 1 ) {
							$due_display = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $due_raw );
						} else {
							$due_display = $due_raw;
						}
					}

					$client_display = trim( $client_name );
					if ( '' !== $referral_number ) {
						$client_display = '' !== $client_display
							? $client_display . ' (' . $referral_number . ')'
							: $referral_number;
					}
					?>
					<tr>
						<td><span class="<?php echo esc_attr( $severity_class ); ?>"><?php echo esc_html( (string) $severity_label ); ?></span></td>
						<td>
							<strong><?php echo esc_html( $title ); ?></strong>
							<?php if ( '' !== $description ) : ?>
								<br /><span class="description"><?php echo esc_html( $description ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo '' !== $client_display ? esc_html( $client_display ) : '—'; ?></td>
						<td><?php echo esc_html( $due_display ); ?></td>
						<td>
							<?php if ( '' !== $action_url ) : ?>
								<a href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $action_label ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
