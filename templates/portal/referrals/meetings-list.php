<?php
/**
 * Portal meeting list (read-only). Phase 4B.2.1.
 *
 * @var array<string, mixed> $referral
 * @var bool                 $is_archived
 * @var array<string, mixed> $list
 * @var string               $referral_url
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral      = is_array( $referral ?? null ) ? $referral : array();
$referral_id   = absint( $referral['id'] ?? 0 );
$referral_num  = (string) ( $referral['referral_number'] ?? '' );
$is_archived   = ! empty( $is_archived );
$referral_url  = (string) ( $referral_url ?? '' );
$list          = is_array( $list ?? null ) ? $list : array();
$counts        = is_array( $list['counts'] ?? null ) ? $list['counts'] : array();
$meetings      = is_array( $list['meetings'] ?? null ) ? $list['meetings'] : array();
$page          = max( 1, absint( $list['page'] ?? 1 ) );
$total_pages   = max( 1, absint( $list['total_pages'] ?? 1 ) );
$total         = absint( $list['total'] ?? 0 );
$can_manage    = ! empty( $can_manage );
$new_url       = (string) ( $new_url ?? '' );
$flash_notice  = is_array( $flash_notice ?? null ) ? $flash_notice : null;
$jmrs_partials = JMRS_PLUGIN_PATH . 'templates/portal/partials/';

$date_format = (string) get_option( 'date_format' );
$time_format = (string) get_option( 'time_format' );
$dt_format   = trim( $date_format . ' ' . $time_format );
?>
<?php if ( is_array( $flash_notice ) && ! empty( $flash_notice['message'] ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--<?php echo esc_attr( (string) ( $flash_notice['type'] ?? 'success' ) ); ?>" role="status">
		<p><?php echo esc_html( (string) $flash_notice['message'] ); ?></p>
	</div>
<?php endif; ?>
<?php if ( $is_archived ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--warning" role="status">
		<?php echo esc_html__( 'This referral is archived. Meetings are read-only.', 'jm-referral-system' ); ?>
	</div>
<?php endif; ?>

<div class="jmrs-portal-quick-actions">
	<?php if ( $can_manage && '' !== $new_url ) : ?>
		<a class="jmrs-button jmrs-button--primary" href="<?php echo esc_url( $new_url ); ?>"><?php echo esc_html__( 'Add meeting', 'jm-referral-system' ); ?></a>
	<?php endif; ?>
	<?php if ( '' !== $referral_url ) : ?>
		<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $referral_url ); ?>"><?php echo esc_html__( 'Back to Referral', 'jm-referral-system' ); ?></a>
	<?php endif; ?>
</div>

<section class="jmrs-portal-section jmrs-portal-panel jmrs-meetings-summary-counts" aria-labelledby="jmrs-meetings-counts-title">
	<h2 id="jmrs-meetings-counts-title" class="jmrs-portal-section__title"><?php echo esc_html__( 'Meetings', 'jm-referral-system' ); ?></h2>
	<p class="jmrs-meetings-counts">
		<span><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Total: %d', 'jm-referral-system' ), absint( $counts['total'] ?? 0 ) ) ); ?></span>
		<span><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Draft: %d', 'jm-referral-system' ), absint( $counts['draft'] ?? 0 ) ) ); ?></span>
		<span><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Scheduled: %d', 'jm-referral-system' ), absint( $counts['scheduled'] ?? 0 ) ) ); ?></span>
		<span><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Completed: %d', 'jm-referral-system' ), absint( $counts['completed'] ?? 0 ) ) ); ?></span>
		<span><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Cancelled: %d', 'jm-referral-system' ), absint( $counts['cancelled'] ?? 0 ) ) ); ?></span>
	</p>
	<p class="description">
		<?php
		echo esc_html__(
			'Referral meetings are separate from formal assessment appointments. Order: upcoming scheduled, past scheduled, drafts, completed, then cancelled.',
			'jm-referral-system'
		);
		?>
	</p>
</section>

<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-meetings-list-title">
	<h2 id="jmrs-meetings-list-title" class="screen-reader-text"><?php echo esc_html__( 'Meeting list', 'jm-referral-system' ); ?></h2>
	<?php if ( empty( $meetings ) ) : ?>
		<?php
		$empty_title   = '';
		$empty_message = __( 'No meetings recorded for this referral.', 'jm-referral-system' );
		$empty_actions = ( $can_manage && '' !== $new_url )
			? array( array( __( 'Add meeting', 'jm-referral-system' ), $new_url ) )
			: array();
		include $jmrs_partials . 'empty-state.php';
		?>
	<?php else : ?>
		<div class="jmrs-portal-table-wrap jmrs-meetings-table-wrap">
			<table class="jmrs-portal-table jmrs-meetings-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Type', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Scheduled', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Location', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Internal', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'External', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Created by', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Updated', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $meetings as $meeting_row ) : ?>
						<?php
						$scheduled_raw = (string) ( $meeting_row['scheduled_at'] ?? '' );
						$updated_raw   = (string) ( $meeting_row['updated_at'] ?? '' );
						$scheduled_disp = '' !== $scheduled_raw ? mysql2date( $dt_format, $scheduled_raw ) : '—';
						$updated_disp   = '' !== $updated_raw ? mysql2date( $dt_format, $updated_raw ) : '—';
						$status_key     = sanitize_html_class( (string) ( $meeting_row['status'] ?? '' ) );
						$detail_url     = (string) ( $meeting_row['detail_url'] ?? '' );
						?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'Type', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $meeting_row['meeting_type_label'] ?? '' ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>">
								<span class="jmrs-portal-badge jmrs-meeting-status jmrs-meeting-status--<?php echo esc_attr( $status_key ); ?>">
									<?php echo esc_html( (string) ( $meeting_row['status_label'] ?? '' ) ); ?>
								</span>
							</td>
							<td data-label="<?php echo esc_attr__( 'Scheduled', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) $scheduled_disp ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Location', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $meeting_row['location_summary'] ?? '—' ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Internal', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) absint( $meeting_row['internal_count'] ?? 0 ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'External', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) absint( $meeting_row['external_count'] ?? 0 ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Created by', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $meeting_row['created_by_name'] ?? '—' ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Updated', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) $updated_disp ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
								<?php if ( '' !== $detail_url ) : ?>
									<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html__( 'View', 'jm-referral-system' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( $total_pages > 1 ) : ?>
			<nav class="jmrs-meetings-pagination" aria-label="<?php echo esc_attr__( 'Meetings pagination', 'jm-referral-system' ); ?>">
				<?php if ( $page > 1 ) : ?>
					<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( add_query_arg( 'jmrs_meeting_page', $page - 1 ) ); ?>"><?php echo esc_html__( 'Previous', 'jm-referral-system' ); ?></a>
				<?php endif; ?>
				<span class="jmrs-meetings-pagination__status">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: current page 2: total pages 3: total meetings */
							__( 'Page %1$d of %2$d (%3$d meetings)', 'jm-referral-system' ),
							$page,
							$total_pages,
							$total
						)
					);
					?>
				</span>
				<?php if ( $page < $total_pages ) : ?>
					<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( add_query_arg( 'jmrs_meeting_page', $page + 1 ) ); ?>"><?php echo esc_html__( 'Next', 'jm-referral-system' ); ?></a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</section>
