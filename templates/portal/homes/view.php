<?php
/**
 * Portal home operational dashboard (Phase 2E).
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$home              = is_array( $home ?? null ) ? $home : array();
$home_name         = (string) ( $home_name ?? '' );
$location          = (string) ( $location ?? '' );
$manager_name      = (string) ( $manager_name ?? '' );
$status_label      = (string) ( $status_label ?? '' );
$capacity          = isset( $capacity ) ? absint( $capacity ) : 0;
$occupied          = isset( $occupied ) ? absint( $occupied ) : 0;
$vacant            = isset( $vacant ) ? absint( $vacant ) : 0;
$occupancy_pct     = isset( $occupancy_pct ) ? (float) $occupancy_pct : 0.0;
$bedrooms          = is_array( $bedrooms ?? null ) ? $bedrooms : array();
$residents         = is_array( $residents ?? null ) ? $residents : array();
$upcoming_visits   = is_array( $upcoming_visits ?? null ) ? $upcoming_visits : array();
$attention         = is_array( $attention ?? null ) ? $attention : array( 'items' => array(), 'total' => 0 );
$attention_items   = is_array( $attention['items'] ?? null ) ? $attention['items'] : array();
$can_manage        = ! empty( $can_manage );
$can_place         = ! empty( $can_place );
$can_view_visits   = ! empty( $can_view_visits );
$home_is_active    = ! empty( $home_is_active );
$edit_url          = (string) ( $edit_url ?? '' );
$add_bedroom_url   = (string) ( $add_bedroom_url ?? '' );
$place_url         = (string) ( $place_url ?? '' );
$vacancies_url     = (string) ( $vacancies_url ?? '' );
$list_url          = (string) ( $list_url ?? '' );
$notice            = is_array( $notice ?? null ) ? $notice : null;
$phone             = (string) ( $home['phone'] ?? '' );
$notes             = (string) ( $home['notes'] ?? '' );
$jmrs_partials     = JMRS_PLUGIN_PATH . 'templates/portal/partials/';
?>
<?php if ( is_array( $notice ) && ! empty( $notice['message'] ) ) : ?>
	<?php
	$notice_type    = (string) ( $notice['type'] ?? 'success' );
	$notice_message = (string) $notice['message'];
	$notice_actions = array();
	include $jmrs_partials . 'notice.php';
	?>
<?php endif; ?>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-home-summary">
	<?php
	$section_title   = $home_name !== '' ? $home_name : __( 'Home', 'jm-referral-system' );
	$section_id      = 'jmrs-portal-home-summary';
	$section_badge   = $status_label;
	$section_actions = array();
	if ( '' !== $edit_url ) {
		$section_actions[] = array( __( 'Edit Home', 'jm-referral-system' ), $edit_url, 'jmrs-button jmrs-button--primary' );
	}
	if ( '' !== $add_bedroom_url ) {
		$section_actions[] = array( __( 'Add Bedroom', 'jm-referral-system' ), $add_bedroom_url, 'jmrs-button jmrs-button--secondary' );
	}
	if ( '' !== $place_url ) {
		$section_actions[] = array( __( 'Place Resident', 'jm-referral-system' ), $place_url, 'jmrs-button jmrs-button--secondary' );
	}
	if ( '' !== $vacancies_url ) {
		$section_actions[] = array( __( 'View Vacancies', 'jm-referral-system' ), $vacancies_url, 'jmrs-button jmrs-button--secondary' );
	}
	if ( '' !== $list_url ) {
		$section_actions[] = array( __( 'Back to Homes', 'jm-referral-system' ), $list_url, 'jmrs-button jmrs-button--secondary' );
	}
	include $jmrs_partials . 'section-header.php';
	?>

	<dl class="jmrs-portal-summary">
		<div>
			<dt><?php echo esc_html__( 'Address', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( $location ); ?></dd>
		</div>
		<?php if ( '' !== $phone ) : ?>
			<div>
				<dt><?php echo esc_html__( 'Phone', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $phone ); ?></dd>
			</div>
		<?php endif; ?>
		<div>
			<dt><?php echo esc_html__( 'Manager', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $manager_name ? $manager_name : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></dt>
			<dd><span class="jmrs-portal-badge"><?php echo esc_html( $status_label ); ?></span></dd>
		</div>
		<?php if ( '' !== $notes ) : ?>
			<div class="jmrs-portal-summary__wide">
				<dt><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></dt>
				<dd><?php echo nl2br( esc_html( $notes ) ); ?></dd>
			</div>
		<?php endif; ?>
	</dl>

	<div class="jmrs-portal-kpi-grid" role="group" aria-label="<?php echo esc_attr__( 'Occupancy metrics', 'jm-referral-system' ); ?>">
		<?php
		$kpi_value = (string) $capacity;
		$kpi_label = __( 'Capacity', 'jm-referral-system' );
		$kpi_href  = '';
		$kpi_tone  = 'default';
		include $jmrs_partials . 'kpi-card.php';

		$kpi_value = (string) $occupied;
		$kpi_label = __( 'Occupied', 'jm-referral-system' );
		include $jmrs_partials . 'kpi-card.php';

		$kpi_value = (string) $vacant;
		$kpi_label = __( 'Vacant', 'jm-referral-system' );
		include $jmrs_partials . 'kpi-card.php';

		$kpi_value = rtrim( rtrim( number_format( $occupancy_pct, 1, '.', '' ), '0' ), '.' ) . '%';
		$kpi_label = __( 'Occupancy', 'jm-referral-system' );
		include $jmrs_partials . 'kpi-card.php';
		?>
	</div>

	<?php if ( ! $home_is_active && $can_manage ) : ?>
		<p class="jmrs-portal-muted">
			<?php echo esc_html__( 'This home is inactive. New bedrooms and placements are blocked until the home is reactivated.', 'jm-referral-system' ); ?>
		</p>
	<?php endif; ?>
</section>

<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-home-attention">
	<?php
	$section_title   = __( 'Operational Attention', 'jm-referral-system' );
	$section_id      = 'jmrs-portal-home-attention';
	$section_badge   = ! empty( $attention_items ) ? (string) absint( $attention['total'] ?? count( $attention_items ) ) : '';
	$section_actions = array();
	include $jmrs_partials . 'section-header.php';
	?>
	<?php if ( empty( $attention_items ) ) : ?>
		<p class="jmrs-portal-muted"><?php echo esc_html__( 'No current operational attention items.', 'jm-referral-system' ); ?></p>
	<?php else : ?>
		<ul class="jmrs-portal-activity">
			<?php foreach ( $attention_items as $item ) : ?>
				<li>
					<strong><?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?></strong>
					<span class="jmrs-portal-badge"><?php echo esc_html( (string) absint( $item['count'] ?? 0 ) ); ?></span>
					<span class="jmrs-portal-muted"><?php echo esc_html( (string) ( $item['description'] ?? '' ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="jmrs-portal-muted">
			<?php echo esc_html__( 'Open a resident care record from Current Residents to review or act on these items.', 'jm-referral-system' ); ?>
		</p>
	<?php endif; ?>
</section>

<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-home-residents">
	<?php
	$section_title   = __( 'Current Residents', 'jm-referral-system' );
	$section_id      = 'jmrs-portal-home-residents';
	$section_badge   = (string) count( $residents );
	$section_actions = array();
	if ( '' !== $place_url ) {
		$section_actions[] = array( __( 'Place Resident', 'jm-referral-system' ), $place_url, 'jmrs-button jmrs-button--primary' );
	}
	include $jmrs_partials . 'section-header.php';
	?>

	<?php if ( empty( $residents ) ) : ?>
		<?php
		$empty_title   = __( 'No clients are currently placed in this home.', 'jm-referral-system' );
		$empty_message = __( 'Active Supported Living placements appear here.', 'jm-referral-system' );
		$empty_actions = array();
		if ( '' !== $place_url ) {
			$empty_actions[] = array( __( 'Place Resident', 'jm-referral-system' ), $place_url, 'jmrs-button jmrs-button--primary' );
		}
		include $jmrs_partials . 'empty-state.php';
		?>
	<?php else : ?>
		<div class="jmrs-portal-table-wrap">
			<table class="jmrs-portal-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Resident', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Referral Number', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Bedroom', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Move-in Date', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Care Setting', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Next Visit', 'jm-referral-system' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $residents as $resident ) : ?>
						<?php
						$view_url         = (string) ( $resident['view_url'] ?? '' );
						$next_visit_label = (string) ( $resident['next_visit_label'] ?? '' );
						?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'Resident', 'jm-referral-system' ); ?>">
								<?php echo esc_html( (string) ( $resident['client_name'] ?? '' ) ); ?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Referral Number', 'jm-referral-system' ); ?>">
								<?php echo esc_html( (string) ( $resident['referral_number'] ?? '—' ) ); ?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Bedroom', 'jm-referral-system' ); ?>">
								<?php echo esc_html( (string) ( $resident['room_label'] ?? '—' ) ); ?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Move-in Date', 'jm-referral-system' ); ?>">
								<?php echo esc_html( (string) ( $resident['move_in_date'] ?? '—' ) ); ?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Care Setting', 'jm-referral-system' ); ?>">
								<span class="jmrs-portal-badge"><?php echo esc_html( (string) ( $resident['care_setting_label'] ?? '' ) ); ?></span>
							</td>
							<td data-label="<?php echo esc_attr__( 'Next Visit', 'jm-referral-system' ); ?>">
								<?php echo esc_html( '' !== $next_visit_label ? $next_visit_label : '—' ); ?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
								<?php if ( '' !== $view_url ) : ?>
									<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $view_url ); ?>">
										<?php echo esc_html__( 'View Care Record', 'jm-referral-system' ); ?>
									</a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-home-bedrooms">
	<?php
	$section_title   = __( 'Bedrooms', 'jm-referral-system' );
	$section_id      = 'jmrs-portal-home-bedrooms';
	$section_badge   = '';
	$section_actions = array();
	if ( '' !== $add_bedroom_url ) {
		$section_actions[] = array( __( 'Add Bedroom', 'jm-referral-system' ), $add_bedroom_url, 'jmrs-button jmrs-button--primary' );
	}
	include $jmrs_partials . 'section-header.php';
	?>

	<?php if ( empty( $bedrooms ) ) : ?>
		<?php
		$empty_title   = __( 'No bedrooms have been added to this home yet.', 'jm-referral-system' );
		$empty_message = __( 'Capacity is based on active bedrooms only.', 'jm-referral-system' );
		$empty_actions = array();
		if ( '' !== $add_bedroom_url ) {
			$empty_actions[] = array( __( 'Add Bedroom', 'jm-referral-system' ), $add_bedroom_url, 'jmrs-button jmrs-button--primary' );
		}
		include $jmrs_partials . 'empty-state.php';
		?>
	<?php else : ?>
		<?php
		$has_vacant_active = false;
		foreach ( $bedrooms as $bedroom_check ) {
			if ( empty( $bedroom_check['is_occupied'] ) && empty( $bedroom_check['is_inactive'] ) && 'active' === ( $bedroom_check['status'] ?? '' ) ) {
				$has_vacant_active = true;
				break;
			}
		}
		?>
		<?php if ( ! $has_vacant_active && $home_is_active ) : ?>
			<p class="jmrs-portal-muted"><?php echo esc_html__( 'No vacant bedrooms are currently available.', 'jm-referral-system' ); ?></p>
		<?php endif; ?>
		<div class="jmrs-portal-table-wrap">
			<table class="jmrs-portal-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Room', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Floor', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Occupancy', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Resident', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Since', 'jm-referral-system' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $bedrooms as $bedroom ) : ?>
						<?php
						$edit_bedroom_url = (string) ( $bedroom['edit_url'] ?? '' );
						$floor            = (string) ( $bedroom['floor'] ?? '' );
						$is_occupied      = ! empty( $bedroom['is_occupied'] );
						$is_inactive      = ! empty( $bedroom['is_inactive'] );
						$occupancy_label  = (string) ( $bedroom['occupancy_label'] ?? '' );
						$client_name      = (string) ( $bedroom['client_name'] ?? '' );
						$client_url       = (string) ( $bedroom['client_url'] ?? '' );
						$place_bedroom_url = (string) ( $bedroom['place_url'] ?? '' );
						$move_in_date     = (string) ( $bedroom['move_in_date'] ?? '' );
						?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'Room', 'jm-referral-system' ); ?>">
								<?php echo esc_html( (string) ( $bedroom['room_label'] ?? '' ) ); ?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Floor', 'jm-referral-system' ); ?>"><?php echo esc_html( '' !== $floor ? $floor : '—' ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Occupancy', 'jm-referral-system' ); ?>">
								<span class="jmrs-portal-badge"><?php echo esc_html( $occupancy_label ); ?></span>
							</td>
							<td data-label="<?php echo esc_attr__( 'Resident', 'jm-referral-system' ); ?>">
								<?php echo esc_html( $is_occupied && '' !== $client_name ? $client_name : '—' ); ?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Since', 'jm-referral-system' ); ?>">
								<?php echo esc_html( $is_occupied && '' !== $move_in_date ? $move_in_date : '—' ); ?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
								<div class="jmrs-portal-row-actions">
									<?php if ( $is_occupied && '' !== $client_url ) : ?>
										<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $client_url ); ?>"><?php echo esc_html__( 'View Client', 'jm-referral-system' ); ?></a>
									<?php endif; ?>
									<?php if ( '' !== $place_bedroom_url ) : ?>
										<a class="jmrs-button jmrs-button--primary" href="<?php echo esc_url( $place_bedroom_url ); ?>"><?php echo esc_html__( 'Place Resident', 'jm-referral-system' ); ?></a>
									<?php endif; ?>
									<?php if ( '' !== $edit_bedroom_url ) : ?>
										<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $edit_bedroom_url ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></a>
									<?php endif; ?>
									<?php if ( $is_inactive && '' === $edit_bedroom_url && '' === $place_bedroom_url && '' === $client_url ) : ?>
										—
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

<?php if ( $can_view_visits ) : ?>
	<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-home-upcoming">
		<?php
		$section_title   = __( 'Upcoming Visits', 'jm-referral-system' );
		$section_id      = 'jmrs-portal-home-upcoming';
		$section_badge   = '';
		$section_actions = array();
		include $jmrs_partials . 'section-header.php';
		?>
		<p class="jmrs-portal-muted">
			<?php
			printf(
				/* translators: %d: number of days */
				esc_html__( 'Visits for current residents over the next %d days.', 'jm-referral-system' ),
				(int) \JMReferral\Homes\HomeDashboardService::UPCOMING_VISIT_DAYS
			);
			?>
		</p>
		<?php if ( empty( $upcoming_visits ) ) : ?>
			<p class="jmrs-portal-muted"><?php echo esc_html__( 'No upcoming visits for current residents.', 'jm-referral-system' ); ?></p>
		<?php else : ?>
			<div class="jmrs-portal-table-wrap">
				<table class="jmrs-portal-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Date / Time', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Resident', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Bedroom', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Assigned Staff', 'jm-referral-system' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $upcoming_visits as $visit ) : ?>
							<?php
							$visit_url = (string) ( $visit['view_url'] ?? '' );
							$staff     = (string) ( $visit['assigned_name'] ?? '' );
							$room      = (string) ( $visit['room_label'] ?? '' );
							?>
							<tr>
								<td data-label="<?php echo esc_attr__( 'Date / Time', 'jm-referral-system' ); ?>">
									<?php echo esc_html( (string) ( $visit['when_label'] ?? '' ) ); ?>
								</td>
								<td data-label="<?php echo esc_attr__( 'Resident', 'jm-referral-system' ); ?>">
									<?php echo esc_html( (string) ( $visit['client_name'] ?? '' ) ); ?>
								</td>
								<td data-label="<?php echo esc_attr__( 'Bedroom', 'jm-referral-system' ); ?>">
									<?php echo esc_html( '' !== $room ? $room : '—' ); ?>
								</td>
								<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>">
									<span class="jmrs-portal-badge"><?php echo esc_html( (string) ( $visit['status_label'] ?? '' ) ); ?></span>
								</td>
								<td data-label="<?php echo esc_attr__( 'Assigned Staff', 'jm-referral-system' ); ?>">
									<?php echo esc_html( '' !== $staff ? $staff : '—' ); ?>
								</td>
								<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
									<?php if ( '' !== $visit_url ) : ?>
										<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $visit_url ); ?>"><?php echo esc_html__( 'View Visit', 'jm-referral-system' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>
