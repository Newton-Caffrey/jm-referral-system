<?php
/**
 * Portal supported living homes list.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$homes          = is_array( $homes ?? null ) ? $homes : array();
$filters        = is_array( $filters ?? null ) ? $filters : array();
$status_options = is_array( $status_options ?? null ) ? $status_options : array();
$can_manage     = ! empty( $can_manage );
$form_action    = (string) ( $form_action ?? '' );
$new_url        = (string) ( $new_url ?? '' );
$list_notice    = is_array( $list_notice ?? null ) ? $list_notice : null;

$search = (string) ( $filters['search'] ?? '' );
$status = (string) ( $filters['status'] ?? 'active' );
?>
<?php if ( is_array( $list_notice ) && ! empty( $list_notice['message'] ) ) : ?>
	<?php
	$notice_type    = (string) ( $list_notice['type'] ?? 'success' );
	$notice_message = (string) $list_notice['message'];
	$notice_actions = array();
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/notice.php';
	?>
<?php endif; ?>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-homes-filters">
	<?php
	$section_title   = __( 'Homes', 'jm-referral-system' );
	$section_id      = 'jmrs-portal-homes-filters';
	$section_badge   = '';
	$section_actions = array();
	if ( '' !== $new_url ) {
		$section_actions[] = array(
			__( 'Add Home', 'jm-referral-system' ),
			$new_url,
			'jmrs-button jmrs-button--primary',
		);
	}
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
	?>

	<form class="jmrs-portal-filters" method="get" action="<?php echo esc_url( $form_action ); ?>">
		<div class="jmrs-portal-filters__row">
			<p>
				<label for="jmrs_search"><?php echo esc_html__( 'Search', 'jm-referral-system' ); ?></label>
				<input
					type="search"
					name="jmrs_search"
					id="jmrs_search"
					value="<?php echo esc_attr( $search ); ?>"
					placeholder="<?php echo esc_attr__( 'Name, city, or postcode', 'jm-referral-system' ); ?>"
				/>
			</p>
			<p>
				<label for="jmrs_home_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
				<select name="jmrs_home_status" id="jmrs_home_status">
					<?php foreach ( $status_options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $status, (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="jmrs-portal-filters__submit">
				<button type="submit" class="jmrs-button jmrs-button--primary"><?php echo esc_html__( 'Apply', 'jm-referral-system' ); ?></button>
			</p>
		</div>
	</form>
</section>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-homes-results">
	<h2 id="jmrs-portal-homes-results" class="screen-reader-text"><?php echo esc_html__( 'Results', 'jm-referral-system' ); ?></h2>

	<?php if ( empty( $homes ) ) : ?>
		<?php
		$has_filters   = '' !== $search || 'active' !== $status;
		$empty_title   = $has_filters
			? __( 'No homes match the current filters.', 'jm-referral-system' )
			: __( 'No supported living homes have been added yet.', 'jm-referral-system' );
		$empty_message = $has_filters
			? __( 'Try a different status or search term.', 'jm-referral-system' )
			: __( 'Add a home to begin recording bedrooms and capacity.', 'jm-referral-system' );
		$empty_actions = array();
		if ( '' !== $new_url && ! $has_filters ) {
			$empty_actions[] = array(
				__( 'Add First Home', 'jm-referral-system' ),
				$new_url,
				'jmrs-button jmrs-button--primary',
			);
		}
		include JMRS_PLUGIN_PATH . 'templates/portal/partials/empty-state.php';
		?>
	<?php else : ?>
		<div class="jmrs-portal-table-wrap">
			<table class="jmrs-portal-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Home', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Location', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Manager', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Capacity', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Occupied', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Vacant', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Occupancy %', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $homes as $row ) : ?>
						<?php
						$view_url     = (string) ( $row['view_url'] ?? '' );
						$edit_url     = (string) ( $row['edit_url'] ?? '' );
						$status_key   = (string) ( $row['status'] ?? '' );
						$status_label = (string) ( $row['status_label'] ?? ucfirst( $status_key ) );
						$capacity     = absint( $row['capacity'] ?? 0 );
						$occupied     = absint( $row['occupied'] ?? 0 );
						$vacant       = absint( $row['vacant'] ?? max( 0, $capacity - $occupied ) );
						$occupancy_pct = isset( $row['occupancy_pct'] )
							? (float) $row['occupancy_pct']
							: ( $capacity > 0 ? round( ( $occupied / $capacity ) * 100, 1 ) : 0.0 );
						$pct_display  = rtrim( rtrim( number_format( $occupancy_pct, 1, '.', '' ), '0' ), '.' ) . '%';
						?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'Home', 'jm-referral-system' ); ?>">
								<?php if ( '' !== $view_url ) : ?>
									<a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( (string) ( $row['name'] ?? '' ) ); ?></a>
								<?php else : ?>
									<?php echo esc_html( (string) ( $row['name'] ?? '' ) ); ?>
								<?php endif; ?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Location', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $row['location'] ?? '' ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Manager', 'jm-referral-system' ); ?>">
								<?php
								$manager = (string) ( $row['manager_name'] ?? '' );
								echo esc_html( '' !== $manager ? $manager : '—' );
								?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Capacity', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) $capacity ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Occupied', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) $occupied ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Vacant', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) $vacant ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Occupancy %', 'jm-referral-system' ); ?>"><?php echo esc_html( $pct_display ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>">
								<span class="jmrs-portal-badge"><?php echo esc_html( $status_label ); ?></span>
							</td>
							<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
								<div class="jmrs-portal-row-actions">
									<?php if ( '' !== $view_url ) : ?>
										<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html__( 'View', 'jm-referral-system' ); ?></a>
									<?php endif; ?>
									<?php if ( '' !== $edit_url ) : ?>
										<a class="jmrs-button jmrs-button--primary" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></a>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
