<?php
/**
 * Admin dashboard template.
 *
 * @package JMReferral
 *
 * @var array{
 *     total: int,
 *     new: int,
 *     in_progress: int,
 *     completed: int,
 *     cancelled: int
 * } $stats Dashboard statistics.
 * @var array<int, array{id: int, name: string, stage_order: int, count: int}> $pipeline Workflow pipeline counts.
 * @var array<int, array<string, mixed>> $recent Recent referral rows.
 * @var bool $scoped_to_assigned Whether results are limited to the current user's assignments.
 * @var bool $can_view_visits Whether the user may view care visits.
 * @var array<int, array<string, mixed>> $upcoming_visits Upcoming care visits.
 * @var array<string, string> $visit_status_labels Visit status labels.
 * @var bool $show_my_active_clients Whether to show My Active Clients for Support Workers.
 * @var int $my_active_clients_count Active care-team client count for the current user.
 * @var bool $show_active_schedules Whether to show Active Schedules for managers.
 * @var int $active_schedules_count Count of active visit schedules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats              = is_array( $stats ?? null ) ? $stats : array();
$pipeline           = is_array( $pipeline ?? null ) ? $pipeline : array();
$recent             = is_array( $recent ?? null ) ? $recent : array();
$scoped_to_assigned = ! empty( $scoped_to_assigned );
$can_view_visits    = ! empty( $can_view_visits );
$upcoming_visits    = is_array( $upcoming_visits ?? null ) ? $upcoming_visits : array();
$visit_status_labels = is_array( $visit_status_labels ?? null ) ? $visit_status_labels : array();
$show_my_active_clients  = ! empty( $show_my_active_clients );
$my_active_clients_count = isset( $my_active_clients_count ) ? absint( $my_active_clients_count ) : 0;
$show_active_schedules   = ! empty( $show_active_schedules );
$active_schedules_count  = isset( $active_schedules_count ) ? absint( $active_schedules_count ) : 0;

$add_url  = admin_url( 'admin.php?page=jm-referrals-add' );
$list_url = admin_url( 'admin.php?page=jm-referrals-list' );
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Dashboard', 'jm-referral-system' ); ?></h1>

	<p class="jmrs-quick-actions">
		<a class="button button-primary" href="<?php echo esc_url( $add_url ); ?>">
			<?php echo esc_html__( 'Add New Referral', 'jm-referral-system' ); ?>
		</a>
		<a class="button" href="<?php echo esc_url( $list_url ); ?>">
			<?php
			echo esc_html(
				$scoped_to_assigned
					? __( 'View My Referrals', 'jm-referral-system' )
					: __( 'View All Referrals', 'jm-referral-system' )
			);
			?>
		</a>
	</p>

	<style>
		.jmrs-quick-actions { margin: 12px 0 20px; }
		.jmrs-quick-actions .button { margin-right: 8px; }
		.jmrs-stats {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
			gap: 12px;
			margin: 0 0 24px;
		}
		.jmrs-stat {
			background: #fff;
			border: 1px solid #c3c4c7;
			box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
			padding: 16px;
		}
		.jmrs-stat-number {
			display: block;
			font-size: 28px;
			font-weight: 600;
			line-height: 1.2;
			color: #1d2327;
		}
		.jmrs-stat-label {
			display: block;
			margin-top: 4px;
			color: #646970;
		}
		.jmrs-dashboard-section-title {
			margin: 0 0 12px;
			font-size: 1.1em;
		}
	</style>

	<div class="jmrs-stats">
		<div class="jmrs-stat">
			<span class="jmrs-stat-number"><?php echo esc_html( (string) ( $stats['total'] ?? 0 ) ); ?></span>
			<span class="jmrs-stat-label">
				<?php
				echo esc_html(
					$scoped_to_assigned
						? __( 'My Referrals', 'jm-referral-system' )
						: __( 'Total Referrals', 'jm-referral-system' )
				);
				?>
			</span>
		</div>
		<div class="jmrs-stat">
			<span class="jmrs-stat-number"><?php echo esc_html( (string) ( $stats['new'] ?? 0 ) ); ?></span>
			<span class="jmrs-stat-label"><?php echo esc_html__( 'New', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-stat">
			<span class="jmrs-stat-number"><?php echo esc_html( (string) ( $stats['in_progress'] ?? 0 ) ); ?></span>
			<span class="jmrs-stat-label"><?php echo esc_html__( 'In Progress', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-stat">
			<span class="jmrs-stat-number"><?php echo esc_html( (string) ( $stats['completed'] ?? 0 ) ); ?></span>
			<span class="jmrs-stat-label"><?php echo esc_html__( 'Completed', 'jm-referral-system' ); ?></span>
		</div>
		<div class="jmrs-stat">
			<span class="jmrs-stat-number"><?php echo esc_html( (string) ( $stats['cancelled'] ?? 0 ) ); ?></span>
			<span class="jmrs-stat-label"><?php echo esc_html__( 'Cancelled', 'jm-referral-system' ); ?></span>
		</div>
		<?php if ( $show_my_active_clients ) : ?>
			<div class="jmrs-stat">
				<span class="jmrs-stat-number"><?php echo esc_html( (string) $my_active_clients_count ); ?></span>
				<span class="jmrs-stat-label"><?php echo esc_html__( 'My Active Clients', 'jm-referral-system' ); ?></span>
			</div>
		<?php endif; ?>
		<?php if ( $show_active_schedules ) : ?>
			<div class="jmrs-stat">
				<span class="jmrs-stat-number"><?php echo esc_html( (string) $active_schedules_count ); ?></span>
				<span class="jmrs-stat-label"><?php echo esc_html__( 'Active Schedules', 'jm-referral-system' ); ?></span>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $show_medication_exceptions ) ) : ?>
			<div class="jmrs-stat">
				<span class="jmrs-stat-number"><?php echo esc_html( (string) absint( $medication_exceptions_today ?? 0 ) ); ?></span>
				<span class="jmrs-stat-label"><?php echo esc_html__( 'Medication Exceptions Today', 'jm-referral-system' ); ?></span>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $show_my_medication_exceptions ) ) : ?>
			<div class="jmrs-stat">
				<span class="jmrs-stat-number"><?php echo esc_html( (string) absint( $my_medication_exceptions_today ?? 0 ) ); ?></span>
				<span class="jmrs-stat-label"><?php echo esc_html__( 'My Medication Exceptions Today', 'jm-referral-system' ); ?></span>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $show_reports_shortcut ) && is_array( $reports_summary ?? null ) ) : ?>
		<?php
		$reports_url              = (string) ( $reports_summary['reports_url'] ?? '' );
		$reports_referrals_total  = absint( $reports_summary['referrals_total'] ?? 0 );
		$reports_visits_completed = absint( $reports_summary['visits_completed'] ?? 0 );
		$reports_alerts_total     = absint( $reports_summary['operational_alerts'] ?? 0 );
		?>
		<h2 class="jmrs-dashboard-section-title"><?php echo esc_html__( 'Reports', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-stats">
			<div class="jmrs-stat">
				<span class="jmrs-stat-number"><?php echo esc_html( (string) $reports_referrals_total ); ?></span>
				<span class="jmrs-stat-label"><?php echo esc_html__( 'Referrals This Month', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-stat">
				<span class="jmrs-stat-number"><?php echo esc_html( (string) $reports_visits_completed ); ?></span>
				<span class="jmrs-stat-label"><?php echo esc_html__( 'Visits Completed This Month', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-stat">
				<span class="jmrs-stat-number"><?php echo esc_html( (string) $reports_alerts_total ); ?></span>
				<span class="jmrs-stat-label"><?php echo esc_html__( 'Operational Alerts', 'jm-referral-system' ); ?></span>
			</div>
		</div>
		<?php if ( '' !== $reports_url ) : ?>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( $reports_url ); ?>">
					<?php echo esc_html__( 'Open Reports', 'jm-referral-system' ); ?>
				</a>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! empty( $show_operational_alerts ) && is_array( $operational_alerts ?? null ) ) : ?>
		<?php
		$alert_counts  = is_array( $operational_alerts['counts'] ?? null ) ? $operational_alerts['counts'] : array();
		$alert_grouped = is_array( $operational_alerts['grouped'] ?? null ) ? $operational_alerts['grouped'] : array();
		$view_all_url  = (string) ( $operational_alerts['view_all_url'] ?? '' );
		$critical_alerts = is_array( $alert_grouped['critical'] ?? null ) ? $alert_grouped['critical'] : array();
		$warning_alerts  = is_array( $alert_grouped['warning'] ?? null ) ? $alert_grouped['warning'] : array();
		$info_alerts     = is_array( $alert_grouped['information'] ?? null ) ? $alert_grouped['information'] : array();
		?>
		<h2 class="jmrs-dashboard-section-title"><?php echo esc_html__( 'Operational Alerts', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-stats">
			<div class="jmrs-stat">
				<span class="jmrs-stat-number"><?php echo esc_html( (string) absint( $alert_counts['critical'] ?? 0 ) ); ?></span>
				<span class="jmrs-stat-label"><?php echo esc_html__( 'Critical', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-stat">
				<span class="jmrs-stat-number"><?php echo esc_html( (string) absint( $alert_counts['warning'] ?? 0 ) ); ?></span>
				<span class="jmrs-stat-label"><?php echo esc_html__( 'Warning', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-stat">
				<span class="jmrs-stat-number"><?php echo esc_html( (string) absint( $alert_counts['information'] ?? 0 ) ); ?></span>
				<span class="jmrs-stat-label"><?php echo esc_html__( 'Information', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-stat">
				<span class="jmrs-stat-number"><?php echo esc_html( (string) absint( $alert_counts['total'] ?? 0 ) ); ?></span>
				<span class="jmrs-stat-label"><?php echo esc_html__( 'Total Alerts', 'jm-referral-system' ); ?></span>
			</div>
		</div>
		<p>
			<?php if ( '' !== $view_all_url ) : ?>
				<a class="button" href="<?php echo esc_url( $view_all_url ); ?>">
					<?php echo esc_html__( 'View All Alerts', 'jm-referral-system' ); ?>
				</a>
			<?php endif; ?>
		</p>
		<?php
		$jmrs_render_alert_table = static function ( string $heading, array $rows ): void {
			?>
			<h3 class="jmrs-dashboard-section-title"><?php echo esc_html( $heading ); ?></h3>
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Alert', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Client / Referral', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Due or Occurred', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Action', 'jm-referral-system' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr class="no-items">
							<td colspan="4"><?php echo esc_html__( 'No alerts in this group.', 'jm-referral-system' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rows as $alert ) : ?>
							<?php
							$title           = (string) ( $alert['title'] ?? '' );
							$client_name     = (string) ( $alert['client_name'] ?? '' );
							$referral_number = (string) ( $alert['referral_number'] ?? '' );
							$due_raw         = (string) ( $alert['occurred_or_due_date'] ?? '' );
							$action_url      = (string) ( $alert['action_url'] ?? '' );
							$action_label    = (string) ( $alert['action_label'] ?? __( 'View', 'jm-referral-system' ) );
							$client_display  = trim( $client_name );
							if ( '' !== $referral_number ) {
								$client_display = '' !== $client_display
									? $client_display . ' (' . $referral_number . ')'
									: $referral_number;
							}
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
							?>
							<tr>
								<td><?php echo esc_html( $title ); ?></td>
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
			<?php
		};

		$jmrs_render_alert_table( __( 'Critical Alerts', 'jm-referral-system' ), $critical_alerts );
		$jmrs_render_alert_table( __( 'Warnings', 'jm-referral-system' ), $warning_alerts );
		$jmrs_render_alert_table( __( 'Information', 'jm-referral-system' ), $info_alerts );
		?>
	<?php endif; ?>

	<h2 class="jmrs-dashboard-section-title"><?php echo esc_html__( 'Workflow Pipeline', 'jm-referral-system' ); ?></h2>
	<div class="jmrs-stats">
		<?php if ( empty( $pipeline ) ) : ?>
			<p><?php echo esc_html__( 'No workflow stages configured.', 'jm-referral-system' ); ?></p>
		<?php else : ?>
			<?php foreach ( $pipeline as $stage ) : ?>
				<div class="jmrs-stat">
					<span class="jmrs-stat-number"><?php echo esc_html( (string) ( $stage['count'] ?? 0 ) ); ?></span>
					<span class="jmrs-stat-label"><?php echo esc_html( (string) ( $stage['name'] ?? '' ) ); ?></span>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<?php if ( $can_view_visits ) : ?>
		<h2 class="jmrs-dashboard-section-title"><?php echo esc_html__( 'Upcoming Visits', 'jm-referral-system' ); ?></h2>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Date', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Time', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Assigned Staff', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $upcoming_visits ) ) : ?>
					<tr class="no-items">
						<td colspan="5"><?php echo esc_html__( 'No upcoming visits found.', 'jm-referral-system' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $upcoming_visits as $visit_row ) : ?>
						<?php
						$visit_date_raw = (string) ( $visit_row['visit_date'] ?? '' );
						$start_time_raw = (string) ( $visit_row['start_time'] ?? '' );
						$end_time_raw   = (string) ( $visit_row['end_time'] ?? '' );
						$client_name    = (string) ( $visit_row['client_name'] ?? '' );
						$assigned_name  = (string) ( $visit_row['assigned_staff_name'] ?? '' );
						$status_key     = (string) ( $visit_row['visit_status'] ?? '' );
						$status_label   = isset( $visit_status_labels[ $status_key ] )
							? (string) $visit_status_labels[ $status_key ]
							: ucfirst( str_replace( '_', ' ', $status_key ) );
						$visit_date_display = '' !== $visit_date_raw
							? mysql2date( get_option( 'date_format' ), $visit_date_raw )
							: '';
						$start_display = '' !== $start_time_raw ? substr( $start_time_raw, 0, 5 ) : '';
						$end_display   = '' !== $end_time_raw ? substr( $end_time_raw, 0, 5 ) : '';
						$time_display  = trim( $start_display . ( '' !== $end_display ? ' – ' . $end_display : '' ) );
						?>
						<tr>
							<td><?php echo esc_html( $visit_date_display ); ?></td>
							<td><?php echo esc_html( $time_display ); ?></td>
							<td><?php echo '' !== $client_name ? esc_html( $client_name ) : '—'; ?></td>
							<td><?php echo '' !== $assigned_name ? esc_html( $assigned_name ) : '—'; ?></td>
							<td><?php echo esc_html( $status_label ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( ! empty( $show_awaiting_review ) ) : ?>
		<?php
		$awaiting_review_visits = is_array( $awaiting_review_visits ?? null ) ? $awaiting_review_visits : array();
		?>
		<h2 class="jmrs-dashboard-section-title"><?php echo esc_html__( 'Visits Awaiting Review', 'jm-referral-system' ); ?></h2>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Date', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Assigned Staff', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $awaiting_review_visits ) ) : ?>
					<tr class="no-items">
						<td colspan="5"><?php echo esc_html__( 'No visits awaiting review.', 'jm-referral-system' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $awaiting_review_visits as $visit_row ) : ?>
						<?php
						$visit_date_raw = (string) ( $visit_row['visit_date'] ?? '' );
						$client_name    = (string) ( $visit_row['client_name'] ?? '' );
						$assigned_name  = (string) ( $visit_row['assigned_staff_name'] ?? '' );
						$outcome_label  = (string) ( $visit_row['outcome_label'] ?? '' );
						$referral_url   = (string) ( $visit_row['referral_url'] ?? '' );
						$visit_date_display = '' !== $visit_date_raw
							? mysql2date( get_option( 'date_format' ), $visit_date_raw )
							: '';
						?>
						<tr>
							<td><?php echo esc_html( $visit_date_display ); ?></td>
							<td><?php echo '' !== $client_name ? esc_html( $client_name ) : '—'; ?></td>
							<td><?php echo '' !== $assigned_name ? esc_html( $assigned_name ) : '—'; ?></td>
							<td><?php echo '' !== $outcome_label ? esc_html( $outcome_label ) : '—'; ?></td>
							<td>
								<?php if ( '' !== $referral_url ) : ?>
									<a href="<?php echo esc_url( $referral_url ); ?>">
										<?php echo esc_html__( 'View', 'jm-referral-system' ); ?>
									</a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( ! empty( $show_todays_completed ) ) : ?>
		<?php
		$todays_completed_visits = is_array( $todays_completed_visits ?? null ) ? $todays_completed_visits : array();
		?>
		<h2 class="jmrs-dashboard-section-title"><?php echo esc_html__( "Today's Completed Visits", 'jm-referral-system' ); ?></h2>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Date', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Duration', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $todays_completed_visits ) ) : ?>
					<tr class="no-items">
						<td colspan="5"><?php echo esc_html__( 'No completed visits today.', 'jm-referral-system' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $todays_completed_visits as $visit_row ) : ?>
						<?php
						$visit_date_raw = (string) ( $visit_row['visit_date'] ?? '' );
						$client_name    = (string) ( $visit_row['client_name'] ?? '' );
						$outcome_label  = (string) ( $visit_row['outcome_label'] ?? '' );
						$duration_mins  = absint( $visit_row['actual_duration_minutes'] ?? 0 );
						$referral_url   = (string) ( $visit_row['referral_url'] ?? '' );
						$visit_date_display = '' !== $visit_date_raw
							? mysql2date( get_option( 'date_format' ), $visit_date_raw )
							: '';
						?>
						<tr>
							<td><?php echo esc_html( $visit_date_display ); ?></td>
							<td><?php echo '' !== $client_name ? esc_html( $client_name ) : '—'; ?></td>
							<td><?php echo '' !== $outcome_label ? esc_html( $outcome_label ) : '—'; ?></td>
							<td>
								<?php
								echo esc_html(
									$duration_mins > 0
										? sprintf(
											/* translators: %d: duration in minutes */
											_n( '%d minute', '%d minutes', $duration_mins, 'jm-referral-system' ),
											$duration_mins
										)
										: '—'
								);
								?>
							</td>
							<td>
								<?php if ( '' !== $referral_url ) : ?>
									<a href="<?php echo esc_url( $referral_url ); ?>">
										<?php echo esc_html__( 'View', 'jm-referral-system' ); ?>
									</a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( ! empty( $show_top_outstanding_tasks ) ) : ?>
		<?php
		$top_outstanding_task_types = is_array( $top_outstanding_task_types ?? null ) ? $top_outstanding_task_types : array();
		?>
		<h2 class="jmrs-dashboard-section-title"><?php echo esc_html__( 'Top Outstanding Task Types', 'jm-referral-system' ); ?></h2>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Task', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Outstanding Count', 'jm-referral-system' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $top_outstanding_task_types ) ) : ?>
					<tr class="no-items">
						<td colspan="2"><?php echo esc_html__( 'No outstanding visit tasks.', 'jm-referral-system' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $top_outstanding_task_types as $task_type_row ) : ?>
						<?php
						$task_name  = (string) ( $task_type_row['task_name'] ?? '' );
						$task_count = absint( $task_type_row['count'] ?? 0 );
						?>
						<tr>
							<td><?php echo '' !== $task_name ? esc_html( $task_name ) : '—'; ?></td>
							<td><?php echo esc_html( (string) $task_count ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( ! empty( $show_todays_outstanding_tasks ) ) : ?>
		<?php
		$todays_outstanding_tasks = is_array( $todays_outstanding_tasks ?? null ) ? $todays_outstanding_tasks : array();
		?>
		<h2 class="jmrs-dashboard-section-title"><?php echo esc_html__( "Today's Outstanding Tasks", 'jm-referral-system' ); ?></h2>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Task', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $todays_outstanding_tasks ) ) : ?>
					<tr class="no-items">
						<td colspan="4"><?php echo esc_html__( 'No outstanding tasks for today.', 'jm-referral-system' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $todays_outstanding_tasks as $task_row ) : ?>
						<?php
						$task_name     = (string) ( $task_row['task_name'] ?? '' );
						$client_name   = (string) ( $task_row['client_name'] ?? '' );
						$task_status   = (string) ( $task_row['task_status'] ?? '' );
						$referral_url  = (string) ( $task_row['referral_url'] ?? '' );
						$status_labels = array(
							'pending'       => __( 'Pending', 'jm-referral-system' ),
							'not_completed' => __( 'Not Completed', 'jm-referral-system' ),
						);
						$status_label = isset( $status_labels[ $task_status ] )
							? $status_labels[ $task_status ]
							: $task_status;
						?>
						<tr>
							<td><?php echo '' !== $task_name ? esc_html( $task_name ) : '—'; ?></td>
							<td><?php echo '' !== $client_name ? esc_html( $client_name ) : '—'; ?></td>
							<td><?php echo '' !== $status_label ? esc_html( $status_label ) : '—'; ?></td>
							<td>
								<?php if ( '' !== $referral_url ) : ?>
									<a href="<?php echo esc_url( $referral_url ); ?>">
										<?php echo esc_html__( 'View', 'jm-referral-system' ); ?>
									</a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2 class="jmrs-dashboard-section-title">
		<?php
		echo esc_html(
			$scoped_to_assigned
				? __( 'My Recent Referrals', 'jm-referral-system' )
				: __( 'Recent Referrals', 'jm-referral-system' )
		);
		?>
	</h2>

	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Referral Number', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Client Name', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Service Required', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Workflow Stage', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Created Date', 'jm-referral-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $recent ) ) : ?>
				<tr class="no-items">
					<td colspan="6"><?php echo esc_html__( 'No referrals found.', 'jm-referral-system' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $recent as $referral ) : ?>
					<?php
					$referral_number  = (string) ( $referral['referral_number'] ?? '' );
					$client_name      = (string) ( $referral['client_name'] ?? '' );
					$service_required = (string) ( $referral['service_required'] ?? '' );
					$workflow_stage_name = (string) ( $referral['workflow_stage_name'] ?? '' );
					$status           = (string) ( $referral['status'] ?? '' );
					$created_at       = (string) ( $referral['created_at'] ?? '' );
					$created_display  = '' !== $created_at
						? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at )
						: '';
					$status_display   = ucfirst( str_replace( '_', ' ', $status ) );
					?>
					<tr>
						<td><strong><?php echo esc_html( $referral_number ); ?></strong></td>
						<td><?php echo esc_html( $client_name ); ?></td>
						<td><?php echo esc_html( $service_required ); ?></td>
						<td><?php echo '' !== $workflow_stage_name ? esc_html( $workflow_stage_name ) : '—'; ?></td>
						<td><?php echo esc_html( $status_display ); ?></td>
						<td><?php echo esc_html( $created_display ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
