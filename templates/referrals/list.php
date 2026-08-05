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
 *     assigned_to: int
 * } $filters Active filters.
 * @var array<int, array{id: int, display_name: string}> $assignable_users Assignable users.
 * @var int $total Total matching referrals.
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
$export_url         = isset( $export_url ) ? (string) $export_url : '';
$scope_to_assigned  = ! empty( $scope_to_assigned );

$search      = (string) ( $filters['search'] ?? '' );
$status      = (string) ( $filters['status'] ?? '' );
$priority    = (string) ( $filters['priority'] ?? '' );
$assigned_to = (string) absint( $filters['assigned_to'] ?? 0 );

$reset_url = admin_url( 'admin.php?page=jm-referrals-list' );
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

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="jmrs-filters">
		<input type="hidden" name="page" value="jm-referrals-list" />

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
	</form>

	<p class="description">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: number of matching referrals */
				_n( '%d referral found.', '%d referrals found.', $total, 'jm-referral-system' ),
				$total
			)
		);
		?>
	</p>

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
					<td colspan="10"><?php echo esc_html__( 'No referrals found.', 'jm-referral-system' ); ?></td>
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
					$assigned_to_name = (string) ( $referral['assigned_to_name'] ?? '' );
					$created_at       = (string) ( $referral['created_at'] ?? '' );
					$created_display  = '' !== $created_at
						? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at )
						: '';
					$delete_url       = \JMReferral\Referral\ReferralListController::get_delete_url( $referral_id );
					$edit_url         = \JMReferral\Referral\ReferralEditController::get_edit_url( $referral_id );
					$view_url         = \JMReferral\Referral\ReferralViewController::get_view_url( $referral_id );
					$can_edit         = ! empty( $referral['can_edit'] );
					$can_delete       = ! empty( $referral['can_delete'] );
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $referral_number ); ?></strong>
						</td>
						<td><?php echo esc_html( $client_name ); ?></td>
						<td><?php echo esc_html( $service_required ); ?></td>
						<td><?php echo '' !== $workflow_stage_name ? esc_html( $workflow_stage_name ) : '—'; ?></td>
						<td><?php echo esc_html( ucfirst( $priority_value ) ); ?></td>
						<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status_value ) ) ); ?></td>
						<td><?php echo '' !== $source_label ? esc_html( $source_label ) : '—'; ?></td>
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
								if ( $can_delete ) {
									$action_links[] = '<a href="' . esc_url( $delete_url ) . '" class="submitdelete" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete this referral?', 'jm-referral-system' ) ) . '\');">' . esc_html__( 'Delete', 'jm-referral-system' ) . '</a>';
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
</div>
