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
	</div>

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
