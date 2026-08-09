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
$scope_urls          = is_array( $scope_urls ?? null ) ? $scope_urls : array();
$empty_message       = (string) ( $empty_message ?? __( 'No referrals found.', 'jm-referral-system' ) );
$list_notice         = is_array( $list_notice ?? null ) ? $list_notice : null;
$archived_list_url   = (string) ( $archived_list_url ?? '' );
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
$care_setting  = (string) ( $filters['care_setting'] ?? '' );
$archive_scope = (string) ( $filters['archive_scope'] ?? ( $archive_scope ?? 'active' ) );
$care_setting_options = is_array( $care_setting_options ?? null ) ? $care_setting_options : array();

$scope_labels = array(
	'active'   => __( 'Active', 'jm-referral-system' ),
	'archived' => __( 'Archived', 'jm-referral-system' ),
	'all'      => __( 'All', 'jm-referral-system' ),
);
$current_scope_label = $scope_labels[ $archive_scope ] ?? $scope_labels['active'];
?>
<?php if ( is_array( $list_notice ) && ! empty( $list_notice['message'] ) ) : ?>
	<?php
	$notice_type = (string) ( $list_notice['type'] ?? 'success' );
	$notice_role = 'error' === $notice_type ? 'alert' : 'status';
	?>
	<div class="jmrs-portal-notice jmrs-portal-notice--<?php echo esc_attr( $notice_type ); ?>" role="<?php echo esc_attr( $notice_role ); ?>">
		<p><?php echo esc_html( (string) $list_notice['message'] ); ?></p>
		<?php if ( 'success' === $notice_type && isset( $_GET['jmrs_archived'] ) && '' !== $archived_list_url ) : ?>
			<p>
				<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $archived_list_url ); ?>">
					<?php echo esc_html__( 'View archived referrals', 'jm-referral-system' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $can_filter_archive ) : ?>
	<section class="jmrs-portal-section jmrs-portal-scope-section" aria-labelledby="jmrs-portal-archive-scope-label">
		<div class="jmrs-portal-scope" role="group" aria-labelledby="jmrs-portal-archive-scope-label">
			<span id="jmrs-portal-archive-scope-label" class="jmrs-portal-scope__label">
				<?php echo esc_html__( 'Show referrals', 'jm-referral-system' ); ?>
			</span>
			<div class="jmrs-portal-scope__options">
				<?php foreach ( $scope_labels as $scope_key => $scope_label ) : ?>
					<?php
					$scope_url   = (string) ( $scope_urls[ $scope_key ] ?? '' );
					$is_current  = $archive_scope === $scope_key;
					$option_class = 'jmrs-portal-scope__option' . ( $is_current ? ' is-current' : '' );
					?>
					<?php if ( '' !== $scope_url ) : ?>
						<a
							class="<?php echo esc_attr( $option_class ); ?>"
							href="<?php echo esc_url( $scope_url ); ?>"
							<?php echo $is_current ? 'aria-current="page"' : ''; ?>
						>
							<?php echo esc_html( $scope_label ); ?>
							<?php if ( $is_current ) : ?>
								<span class="screen-reader-text">
									<?php echo esc_html__( '(current filter)', 'jm-referral-system' ); ?>
								</span>
							<?php endif; ?>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<p class="jmrs-portal-scope__status" aria-live="polite">
				<?php
				printf(
					/* translators: %s: Active, Archived, or All */
					esc_html__( 'Currently showing: %s', 'jm-referral-system' ),
					esc_html( $current_scope_label )
				);
				?>
			</p>
		</div>
	</section>
<?php endif; ?>

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
			<p>
				<label for="jmrs_care_setting"><?php echo esc_html__( 'Care Setting', 'jm-referral-system' ); ?></label>
				<select name="jmrs_care_setting" id="jmrs_care_setting">
					<option value=""><?php echo esc_html__( 'All Care Settings', 'jm-referral-system' ); ?></option>
					<?php foreach ( $care_setting_options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $care_setting, (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
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
				<input type="hidden" name="jmrs_archive_scope" value="<?php echo esc_attr( $archive_scope ); ?>" />
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
				<button type="submit" class="jmrs-button jmrs-button--primary"><?php echo esc_html__( 'Apply', 'jm-referral-system' ); ?></button>
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
			<p><?php echo esc_html( $empty_message ); ?></p>
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
						<th scope="col"><?php echo esc_html__( 'Care Setting', 'jm-referral-system' ); ?></th>
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
						$portal_url  = (string) ( $row['portal_url'] ?? '' );
						$edit_url    = (string) ( $row['edit_url'] ?? '' );
						$archive_url = (string) ( $row['archive_url'] ?? '' );
						$can_restore = ! empty( $row['can_restore'] );
						$client      = trim( (string) ( $row['client_first_name'] ?? '' ) . ' ' . (string) ( $row['client_last_name'] ?? '' ) );
						if ( '' === $client ) {
							$client = (string) ( $row['client_name'] ?? '' );
						}
						$is_arch     = ! empty( $row['is_archived'] );
						$row_id      = absint( $row['id'] ?? 0 );
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
							<td data-label="<?php echo esc_attr__( 'Care Setting', 'jm-referral-system' ); ?>">
								<span class="jmrs-portal-badge"><?php echo esc_html( \JMReferral\Referral\CareSetting::label( isset( $row['care_setting'] ) ? (string) $row['care_setting'] : null ) ); ?></span>
							</td>
							<td data-label="<?php echo esc_attr__( 'Priority', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge jmrs-portal-badge--priority"><?php echo esc_html( ucfirst( (string) ( $row['priority'] ?? '' ) ) ); ?></span></td>
							<td data-label="<?php echo esc_attr__( 'Stage', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $row['workflow_stage_name'] ?? '' ) ); ?></td>
							<?php if ( ! $scope_to_assigned ) : ?>
								<td data-label="<?php echo esc_attr__( 'Assigned', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $row['assigned_to_name'] ?? '' ) ); ?></td>
							<?php endif; ?>
							<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
								<div class="jmrs-portal-row-actions">
									<?php if ( '' !== $portal_url ) : ?>
										<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $portal_url ); ?>"><?php echo esc_html__( 'View', 'jm-referral-system' ); ?></a>
									<?php endif; ?>
									<?php if ( '' !== $edit_url ) : ?>
										<a class="jmrs-button jmrs-button--primary" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></a>
									<?php endif; ?>
									<?php if ( '' !== $archive_url ) : ?>
										<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $archive_url ); ?>"><?php echo esc_html__( 'Archive', 'jm-referral-system' ); ?></a>
									<?php endif; ?>
									<?php if ( $can_restore && $row_id > 0 ) : ?>
										<form class="jmrs-portal-inline-form" method="post" action="<?php echo esc_url( $portal_url ); ?>" data-jmrs-confirm="<?php echo esc_attr__( 'Restore this archived referral?', 'jm-referral-system' ); ?>">
											<?php wp_nonce_field( 'jmrs_restore_referral_' . $row_id, 'jmrs_restore_nonce' ); ?>
											<input type="hidden" name="referral_id" value="<?php echo esc_attr( (string) $row_id ); ?>" />
											<button type="submit" name="jmrs_restore_referral" value="1" class="jmrs-button jmrs-button--secondary">
												<?php echo esc_html__( 'Restore', 'jm-referral-system' ); ?>
											</button>
										</form>
									<?php endif; ?>
								</div>
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
