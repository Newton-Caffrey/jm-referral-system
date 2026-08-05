<?php
/**
 * Portal referral list.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referrals           = is_array( $referrals ?? null ) ? $referrals : array();
$filters             = is_array( $filters ?? null ) ? $filters : array();
$assignable_users    = is_array( $assignable_users ?? null ) ? $assignable_users : array();
$scope_to_assigned   = ! empty( $scope_to_assigned );
$can_filter_assignee = ! empty( $can_filter_assignee );
$can_filter_archive  = ! empty( $can_filter_archive );
$per_page            = isset( $per_page ) ? absint( $per_page ) : 20;
$page                = isset( $page ) ? absint( $page ) : 1;
$total               = isset( $total ) ? absint( $total ) : 0;
$from                = isset( $from ) ? absint( $from ) : 0;
$to                  = isset( $to ) ? absint( $to ) : 0;
$pagination_links    = (string) ( $pagination_links ?? '' );
$form_action         = (string) ( $form_action ?? '' );
$allowed_per_page    = is_array( $allowed_per_page ?? null ) ? $allowed_per_page : array( 20, 50, 100 );
$status_options      = is_array( $status_options ?? null ) ? $status_options : array();
$priority_options    = is_array( $priority_options ?? null ) ? $priority_options : array();

$search        = (string) ( $filters['search'] ?? '' );
$status        = (string) ( $filters['status'] ?? '' );
$priority      = (string) ( $filters['priority'] ?? '' );
$assigned_to   = absint( $filters['assigned_to'] ?? 0 );
$archive_scope = (string) ( $filters['archive_scope'] ?? 'active' );
?>
<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-list-filters">
	<h2 id="jmrs-portal-list-filters" class="screen-reader-text"><?php echo esc_html__( 'Filters', 'jm-referral-system' ); ?></h2>

	<form class="jmrs-portal-filters" method="get" action="<?php echo esc_url( $form_action ); ?>">
		<div class="jmrs-portal-filters__row">
			<p>
				<label for="jmrs_search"><?php echo esc_html__( 'Search', 'jm-referral-system' ); ?></label>
				<input type="search" name="jmrs_search" id="jmrs_search" value="<?php echo esc_attr( $search ); ?>" />
			</p>
			<p>
				<label for="jmrs_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
				<select name="jmrs_status" id="jmrs_status">
					<option value=""><?php echo esc_html__( 'All statuses', 'jm-referral-system' ); ?></option>
					<?php foreach ( $status_options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $status, (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="jmrs_priority"><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?></label>
				<select name="jmrs_priority" id="jmrs_priority">
					<option value=""><?php echo esc_html__( 'All priorities', 'jm-referral-system' ); ?></option>
					<?php foreach ( $priority_options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $priority, (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php if ( $can_filter_assignee ) : ?>
				<p>
					<label for="jmrs_assigned_to"><?php echo esc_html__( 'Assigned staff', 'jm-referral-system' ); ?></label>
					<select name="jmrs_assigned_to" id="jmrs_assigned_to">
						<option value="0"><?php echo esc_html__( 'All staff', 'jm-referral-system' ); ?></option>
						<?php foreach ( $assignable_users as $user_row ) : ?>
							<?php
							$uid   = absint( $user_row['id'] ?? 0 );
							$uname = (string) ( $user_row['display_name'] ?? '' );
							?>
							<option value="<?php echo esc_attr( (string) $uid ); ?>" <?php selected( $assigned_to, $uid ); ?>><?php echo esc_html( $uname ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>
			<?php if ( $can_filter_archive ) : ?>
				<p>
					<label for="jmrs_archive_scope"><?php echo esc_html__( 'Archive', 'jm-referral-system' ); ?></label>
					<select name="jmrs_archive_scope" id="jmrs_archive_scope">
						<option value="active" <?php selected( $archive_scope, 'active' ); ?>><?php echo esc_html__( 'Active', 'jm-referral-system' ); ?></option>
						<option value="archived" <?php selected( $archive_scope, 'archived' ); ?>><?php echo esc_html__( 'Archived', 'jm-referral-system' ); ?></option>
						<option value="all" <?php selected( $archive_scope, 'all' ); ?>><?php echo esc_html__( 'All', 'jm-referral-system' ); ?></option>
					</select>
				</p>
			<?php endif; ?>
			<p>
				<label for="jmrs_per_page"><?php echo esc_html__( 'Page size', 'jm-referral-system' ); ?></label>
				<select name="jmrs_per_page" id="jmrs_per_page">
					<?php foreach ( $allowed_per_page as $size ) : ?>
						<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( $per_page, (int) $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="jmrs-portal-filters__submit">
				<button type="submit" class="jmrs-portal-btn jmrs-portal-btn--primary"><?php echo esc_html__( 'Apply', 'jm-referral-system' ); ?></button>
			</p>
		</div>
	</form>
</section>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-list-results">
	<div class="jmrs-portal-section__meta">
		<h2 id="jmrs-portal-list-results" class="jmrs-portal-section__title"><?php echo esc_html__( 'Results', 'jm-referral-system' ); ?></h2>
		<p class="jmrs-portal-muted">
			<?php
			printf(
				/* translators: 1: from, 2: to, 3: total */
				esc_html__( 'Showing %1$s–%2$s of %3$s', 'jm-referral-system' ),
				esc_html( (string) $from ),
				esc_html( (string) $to ),
				esc_html( (string) $total )
			);
			?>
		</p>
	</div>

	<?php if ( empty( $referrals ) ) : ?>
		<div class="jmrs-portal-empty">
			<p><?php echo esc_html__( 'No referrals match these filters.', 'jm-referral-system' ); ?></p>
		</div>
	<?php else : ?>
		<div class="jmrs-portal-table-wrap">
			<table class="jmrs-portal-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Number', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Service', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Stage', 'jm-referral-system' ); ?></th>
						<?php if ( ! $scope_to_assigned ) : ?>
							<th scope="col"><?php echo esc_html__( 'Assigned', 'jm-referral-system' ); ?></th>
						<?php endif; ?>
						<th scope="col"><span class="screen-reader-text"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $referrals as $row ) : ?>
						<?php
						$portal_url = (string) ( $row['portal_url'] ?? '' );
						$client     = trim( (string) ( $row['client_first_name'] ?? '' ) . ' ' . (string) ( $row['client_last_name'] ?? '' ) );
						if ( '' === $client ) {
							$client = (string) ( $row['client_name'] ?? '' );
						}
						$is_arch    = ! empty( $row['is_archived'] );
						?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'Number', 'jm-referral-system' ); ?>">
								<?php echo esc_html( (string) ( $row['referral_number'] ?? '' ) ); ?>
								<?php if ( $is_arch ) : ?>
									<span class="jmrs-portal-badge jmrs-portal-badge--archive"><?php echo esc_html__( 'Archived', 'jm-referral-system' ); ?></span>
								<?php endif; ?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Client', 'jm-referral-system' ); ?>"><?php echo esc_html( $client ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Service', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $row['service_name'] ?? '' ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge"><?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) ( $row['status'] ?? '' ) ) ) ); ?></span></td>
							<td data-label="<?php echo esc_attr__( 'Priority', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge jmrs-portal-badge--priority"><?php echo esc_html( ucfirst( (string) ( $row['priority'] ?? '' ) ) ); ?></span></td>
							<td data-label="<?php echo esc_attr__( 'Stage', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $row['workflow_stage_name'] ?? '' ) ); ?></td>
							<?php if ( ! $scope_to_assigned ) : ?>
								<td data-label="<?php echo esc_attr__( 'Assigned', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $row['assigned_to_name'] ?? '' ) ); ?></td>
							<?php endif; ?>
							<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
								<?php if ( '' !== $portal_url ) : ?>
									<a class="jmrs-portal-link" href="<?php echo esc_url( $portal_url ); ?>"><?php echo esc_html__( 'View', 'jm-referral-system' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $pagination_links ) : ?>
		<nav class="jmrs-portal-pagination" aria-label="<?php echo esc_attr__( 'Referral list pagination', 'jm-referral-system' ); ?>">
			<?php echo wp_kses_post( $pagination_links ); ?>
		</nav>
	<?php endif; ?>
</section>
