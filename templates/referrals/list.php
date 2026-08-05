<?php
/**
 * Referrals list template.
 *
 * @package JMReferral
 *
 * @var array<int, array<string, mixed>> $referrals Referral rows.
 * @var array{
 *     search: string,
 *     status: string,
 *     priority: string,
 *     assigned_to: int,
 *     archive_scope?: string
 * } $filters Active filters.
 * @var array<int, array{id: int, display_name: string}> $assignable_users Assignable users.
 * @var int $total Total matching referrals.
 * @var int $page Current page.
 * @var int $per_page Page size.
 * @var int $from Display range start.
 * @var int $to Display range end.
 * @var int $total_pages Total pages.
 * @var string|false|null $pagination_links paginate_links() output.
 * @var string $export_url Nonce-protected CSV export URL.
 * @var bool $scope_to_assigned Whether the list is limited to the current user's assignments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referrals          = is_array( $referrals ?? null ) ? $referrals : array();
$filters            = is_array( $filters ?? null ) ? $filters : array();
$assignable_users   = is_array( $assignable_users ?? null ) ? $assignable_users : array();
$total              = isset( $total ) ? absint( $total ) : count( $referrals );
$page               = isset( $page ) ? max( 1, absint( $page ) ) : 1;
$per_page           = isset( $per_page ) ? absint( $per_page ) : 20;
$from               = isset( $from ) ? absint( $from ) : 0;
$to                 = isset( $to ) ? absint( $to ) : 0;
$total_pages        = isset( $total_pages ) ? max( 1, absint( $total_pages ) ) : 1;
$pagination_links   = $pagination_links ?? '';
$export_url         = isset( $export_url ) ? (string) $export_url : '';
$scope_to_assigned  = ! empty( $scope_to_assigned );

$search        = (string) ( $filters['search'] ?? '' );
$status        = (string) ( $filters['status'] ?? '' );
$priority      = (string) ( $filters['priority'] ?? '' );
$assigned_to   = (string) absint( $filters['assigned_to'] ?? 0 );
$archive_scope = (string) ( $filters['archive_scope'] ?? 'active' );

$reset_url = admin_url( 'admin.php?page=jm-referrals-list' );

$jmrs_render_list_pagination = static function ( string $select_id ) use ( $from, $to, $total, $per_page, $pagination_links ) {
	?>
	<div class="tablenav">
		<div class="alignleft actions">
			<label for="<?php echo esc_attr( $select_id ); ?>" class="screen-reader-text"><?php echo esc_html__( 'Referrals per page', 'jm-referral-system' ); ?></label>
			<select name="jmrs_per_page" id="<?php echo esc_attr( $select_id ); ?>" onchange="if (this.form) { var p=this.form.querySelector('input[name=paged]'); if(p){p.value='1';} this.form.submit(); }">
				<?php foreach ( \JMReferral\Referral\ReferralFilters::ALLOWED_PER_PAGE as $size ) : ?>
					<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( $per_page, $size ); ?>>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of rows per page */
								__( '%d per page', 'jm-referral-system' ),
								$size
							)
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="tablenav-pages">
			<span class="displaying-num">
				<?php
				if ( $total > 0 ) {
					echo esc_html(
						sprintf(
							/* translators: 1: first item number, 2: last item number, 3: total referrals */
							__( 'Displaying %1$s–%2$s of %3$s referrals', 'jm-referral-system' ),
							number_format_i18n( $from ),
							number_format_i18n( $to ),
							number_format_i18n( $total )
						)
					);
				} else {
					echo esc_html__( 'Displaying 0 referrals', 'jm-referral-system' );
				}
				?>
			</span>
			<?php if ( is_string( $pagination_links ) && '' !== $pagination_links ) : ?>
				<span class="pagination-links"><?php echo $pagination_links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() HTML. ?></span>
			<?php endif; ?>
		</div>
		<br class="clear" />
	</div>
	<?php
};
?>
<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php
		echo esc_html(
			$scope_to_assigned
				? __( 'My Referrals', 'jm-referral-system' )
				: __( 'Referrals', 'jm-referral-system' )
		);
		?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=jm-referrals-add' ) ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add New', 'jm-referral-system' ); ?>
	</a>
	<hr class="wp-header-end" />

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="jmrs-filters" id="jmrs-referrals-filter">
		<input type="hidden" name="page" value="jm-referrals-list" />
		<input type="hidden" name="paged" value="1" />

		<div class="tablenav top">
			<div class="alignleft actions">
				<label class="screen-reader-text" for="jmrs_search"><?php echo esc_html__( 'Search referrals', 'jm-referral-system' ); ?></label>
				<input
					type="search"
					name="jmrs_search"
					id="jmrs_search"
					value="<?php echo esc_attr( $search ); ?>"
					placeholder="<?php echo esc_attr__( 'Search referrals…', 'jm-referral-system' ); ?>"
				/>

				<label class="screen-reader-text" for="jmrs_archive_scope"><?php echo esc_html__( 'Archive filter', 'jm-referral-system' ); ?></label>
				<select name="jmrs_archive_scope" id="jmrs_archive_scope">
					<option value="active" <?php selected( $archive_scope, 'active' ); ?>><?php echo esc_html__( 'Active', 'jm-referral-system' ); ?></option>
					<option value="archived" <?php selected( $archive_scope, 'archived' ); ?>><?php echo esc_html__( 'Archived', 'jm-referral-system' ); ?></option>
					<option value="all" <?php selected( $archive_scope, 'all' ); ?>><?php echo esc_html__( 'All', 'jm-referral-system' ); ?></option>
				</select>

				<label class="screen-reader-text" for="jmrs_status"><?php echo esc_html__( 'Filter by status', 'jm-referral-system' ); ?></label>
				<select name="jmrs_status" id="jmrs_status">
					<option value=""><?php echo esc_html__( 'All Statuses', 'jm-referral-system' ); ?></option>
					<option value="new" <?php selected( $status, 'new' ); ?>><?php echo esc_html__( 'New', 'jm-referral-system' ); ?></option>
					<option value="in_progress" <?php selected( $status, 'in_progress' ); ?>><?php echo esc_html__( 'In Progress', 'jm-referral-system' ); ?></option>
					<option value="completed" <?php selected( $status, 'completed' ); ?>><?php echo esc_html__( 'Completed', 'jm-referral-system' ); ?></option>
					<option value="cancelled" <?php selected( $status, 'cancelled' ); ?>><?php echo esc_html__( 'Cancelled', 'jm-referral-system' ); ?></option>
				</select>

				<label class="screen-reader-text" for="jmrs_priority"><?php echo esc_html__( 'Filter by priority', 'jm-referral-system' ); ?></label>
				<select name="jmrs_priority" id="jmrs_priority">
					<option value=""><?php echo esc_html__( 'All Priorities', 'jm-referral-system' ); ?></option>
					<option value="low" <?php selected( $priority, 'low' ); ?>><?php echo esc_html__( 'Low', 'jm-referral-system' ); ?></option>
					<option value="medium" <?php selected( $priority, 'medium' ); ?>><?php echo esc_html__( 'Medium', 'jm-referral-system' ); ?></option>
					<option value="high" <?php selected( $priority, 'high' ); ?>><?php echo esc_html__( 'High', 'jm-referral-system' ); ?></option>
					<option value="urgent" <?php selected( $priority, 'urgent' ); ?>><?php echo esc_html__( 'Urgent', 'jm-referral-system' ); ?></option>
				</select>

				<?php if ( ! $scope_to_assigned ) : ?>
					<label class="screen-reader-text" for="jmrs_assigned_to"><?php echo esc_html__( 'Filter by assignee', 'jm-referral-system' ); ?></label>
					<select name="jmrs_assigned_to" id="jmrs_assigned_to">
						<option value="0"><?php echo esc_html__( 'All Assignees', 'jm-referral-system' ); ?></option>
						<?php foreach ( $assignable_users as $user ) : ?>
							<option value="<?php echo esc_attr( (string) $user['id'] ); ?>" <?php selected( $assigned_to, (string) $user['id'] ); ?>>
								<?php echo esc_html( $user['display_name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>

				<?php submit_button( __( 'Apply Filters', 'jm-referral-system' ), '', 'jmrs_filter', false ); ?>
				<a class="button" href="<?php echo esc_url( $reset_url ); ?>">
					<?php echo esc_html__( 'Reset Filters', 'jm-referral-system' ); ?>
				</a>
				<a class="button button-primary" href="<?php echo esc_url( $export_url ); ?>">
					<?php echo esc_html__( 'Export CSV', 'jm-referral-system' ); ?>
				</a>
			</div>
			<br class="clear" />
		</div>

		<?php $jmrs_render_list_pagination( 'jmrs_per_page_top' ); ?>
	</form>

	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Referral Number', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Client Name', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Service Required', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Workflow Stage', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Source', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Assigned To', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Created Date', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $referrals ) ) : ?>
				<tr class="no-items">
					<td colspan="10"><?php echo \JMReferral\Support\UiHelper::empty_state( __( 'No referrals found.', 'jm-referral-system' ), '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=jm-referrals-add' ) ) . '">' . esc_html__( 'Add New Referral', 'jm-referral-system' ) . '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $referrals as $referral ) : ?>
					<?php
					$referral_id      = absint( $referral['id'] ?? 0 );
					$referral_number  = (string) ( $referral['referral_number'] ?? '' );
					$client_name      = (string) ( $referral['client_name'] ?? '' );
					$service_required = (string) ( $referral['service_name'] ?? $referral['service_required'] ?? '' );
					$workflow_stage_name = (string) ( $referral['workflow_stage_name'] ?? '' );
					$priority_value   = (string) ( $referral['priority'] ?? '' );
					$status_value     = (string) ( $referral['status'] ?? '' );
					$source_value     = (string) ( $referral['referral_source'] ?? '' );
					$source_label     = '' !== $source_value
						? \JMReferral\Referral\ReferralSources::label( $source_value )
						: '';
					$submission_channel = (string) ( $referral['submission_channel'] ?? 'admin' );
					$is_website_channel = \JMReferral\Frontend\SubmissionChannels::is_public( $submission_channel );
					$assigned_to_name = (string) ( $referral['assigned_to_name'] ?? '' );
					$created_at       = (string) ( $referral['created_at'] ?? '' );
					$created_display  = '' !== $created_at
						? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at )
						: '';
					$edit_url         = \JMReferral\Referral\ReferralEditController::get_edit_url( $referral_id );
					$view_url         = \JMReferral\Referral\ReferralViewController::get_view_url( $referral_id );
					$restore_url      = \JMReferral\Referral\ReferralListController::get_restore_url( $referral_id );
					$archive_url      = $view_url . '#jmrs-archive-referral';
					$can_edit         = ! empty( $referral['can_edit'] );
					$can_archive      = ! empty( $referral['can_archive'] );
					$can_restore      = ! empty( $referral['can_restore'] );
					$is_archived      = ! empty( $referral['is_archived'] );
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $referral_number ); ?></strong>
							<?php if ( $is_archived ) : ?>
								<br /><?php echo \JMReferral\Support\UiHelper::badge( __( 'Archived', 'jm-referral-system' ), 'archive' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $client_name ); ?></td>
						<td><?php echo esc_html( $service_required ); ?></td>
						<td><?php echo '' !== $workflow_stage_name ? esc_html( $workflow_stage_name ) : '—'; ?></td>
						<td><?php echo \JMReferral\Support\UiHelper::priority_badge( $priority_value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?></td>
						<td><?php echo \JMReferral\Support\UiHelper::status_badge( $status_value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?></td>
						<td>
							<?php echo '' !== $source_label ? esc_html( $source_label ) : '—'; ?>
							<?php if ( $is_website_channel ) : ?>
								<br /><?php echo \JMReferral\Support\UiHelper::badge( __( 'Website', 'jm-referral-system' ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?>
							<?php endif; ?>
						</td>
						<td><?php echo '' !== $assigned_to_name ? esc_html( $assigned_to_name ) : esc_html__( 'Unassigned', 'jm-referral-system' ); ?></td>
						<td><?php echo esc_html( $created_display ); ?></td>
						<td>
							<span class="jmrs-actions">
								<?php
								$action_links = array();
								$action_links[] = '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'jm-referral-system' ) . '</a>';
								if ( $can_edit ) {
									$action_links[] = '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'jm-referral-system' ) . '</a>';
								}
								if ( $can_archive ) {
									$action_links[] = '<a href="' . esc_url( $archive_url ) . '">' . esc_html__( 'Archive', 'jm-referral-system' ) . '</a>';
								}
								if ( $can_restore ) {
									$action_links[] = '<a href="' . esc_url( $restore_url ) . '" data-jmrs-confirm="' . esc_attr__( 'Restore this archived referral?', 'jm-referral-system' ) . '" data-jmrs-busy="' . esc_attr__( 'Restoring...', 'jm-referral-system' ) . '">' . esc_html__( 'Restore', 'jm-referral-system' ) . '</a>';
								}
								echo implode( ' | ', $action_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- links built with esc_url/esc_html above.
								?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Referral Number', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Client Name', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Service Required', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Workflow Stage', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Source', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Assigned To', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Created Date', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
			</tr>
		</tfoot>
	</table>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="jmrs-filters-bottom">
		<input type="hidden" name="page" value="jm-referrals-list" />
		<input type="hidden" name="paged" value="1" />
		<?php if ( '' !== $search ) : ?>
			<input type="hidden" name="jmrs_search" value="<?php echo esc_attr( $search ); ?>" />
		<?php endif; ?>
		<?php if ( '' !== $status ) : ?>
			<input type="hidden" name="jmrs_status" value="<?php echo esc_attr( $status ); ?>" />
		<?php endif; ?>
		<?php if ( '' !== $priority ) : ?>
			<input type="hidden" name="jmrs_priority" value="<?php echo esc_attr( $priority ); ?>" />
		<?php endif; ?>
		<?php if ( '0' !== $assigned_to && '' !== $assigned_to ) : ?>
			<input type="hidden" name="jmrs_assigned_to" value="<?php echo esc_attr( $assigned_to ); ?>" />
		<?php endif; ?>
		<?php if ( 'active' !== $archive_scope ) : ?>
			<input type="hidden" name="jmrs_archive_scope" value="<?php echo esc_attr( $archive_scope ); ?>" />
		<?php endif; ?>
		<?php $jmrs_render_list_pagination( 'jmrs_per_page_bottom' ); ?>
	</form>
</div>
