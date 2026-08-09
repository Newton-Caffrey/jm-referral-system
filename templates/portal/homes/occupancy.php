<?php
/**
 * Portal vacancies / occupancy operational board.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$homes        = is_array( $homes ?? null ) ? $homes : array();
$summary      = is_array( $summary ?? null ) ? $summary : array();
$filters      = is_array( $filters ?? null ) ? $filters : array();
$home_options = is_array( $home_options ?? null ) ? $home_options : array();
$form_action  = (string) ( $form_action ?? '' );
$list_notice  = is_array( $list_notice ?? null ) ? $list_notice : null;
$can_manage   = ! empty( $can_manage );

$capacity = absint( $summary['capacity'] ?? 0 );
$occupied = absint( $summary['occupied'] ?? 0 );
$vacant   = absint( $summary['vacant'] ?? 0 );
$pct      = (float) ( $summary['occupancy_pct'] ?? 0 );

$home_id = absint( $filters['home_id'] ?? 0 );
$vacancy = (string) ( $filters['vacancy'] ?? 'all' );
$search  = (string) ( $filters['search'] ?? '' );
?>
<?php if ( is_array( $list_notice ) && ! empty( $list_notice['message'] ) ) : ?>
	<?php
	$notice_type    = (string) ( $list_notice['type'] ?? 'success' );
	$notice_message = (string) $list_notice['message'];
	$notice_actions = array();
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/notice.php';
	?>
<?php endif; ?>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-occ-summary">
	<h2 id="jmrs-portal-occ-summary" class="jmrs-portal-section__title"><?php echo esc_html__( 'Estate summary', 'jm-referral-system' ); ?></h2>
	<div class="jmrs-portal-kpi-grid">
		<div class="jmrs-portal-kpi-card">
			<span class="jmrs-portal-kpi-card__label"><?php echo esc_html__( 'Total Active Bedrooms', 'jm-referral-system' ); ?></span>
			<span class="jmrs-portal-kpi-card__value"><?php echo esc_html( (string) $capacity ); ?></span>
		</div>
		<div class="jmrs-portal-kpi-card">
			<span class="jmrs-portal-kpi-card__label"><?php echo esc_html__( 'Occupied', 'jm-referral-system' ); ?></span>
			<span class="jmrs-portal-kpi-card__value"><?php echo esc_html( (string) $occupied ); ?></span>
		</div>
		<div class="jmrs-portal-kpi-card">
			<span class="jmrs-portal-kpi-card__label"><?php echo esc_html__( 'Vacant', 'jm-referral-system' ); ?></span>
			<span class="jmrs-portal-kpi-card__value"><?php echo esc_html( (string) $vacant ); ?></span>
		</div>
		<div class="jmrs-portal-kpi-card">
			<span class="jmrs-portal-kpi-card__label"><?php echo esc_html__( 'Occupancy %', 'jm-referral-system' ); ?></span>
			<span class="jmrs-portal-kpi-card__value"><?php echo esc_html( (string) $pct ); ?>%</span>
		</div>
	</div>
</section>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-occ-filters">
	<h2 id="jmrs-portal-occ-filters" class="screen-reader-text"><?php echo esc_html__( 'Filters', 'jm-referral-system' ); ?></h2>
	<form class="jmrs-portal-filters" method="get" action="<?php echo esc_url( $form_action ); ?>">
		<div class="jmrs-portal-filters__row">
			<p>
				<label for="jmrs_home_id"><?php echo esc_html__( 'Home', 'jm-referral-system' ); ?></label>
				<select name="jmrs_home_id" id="jmrs_home_id">
					<option value="0"><?php echo esc_html__( 'All homes', 'jm-referral-system' ); ?></option>
					<?php foreach ( $home_options as $option ) : ?>
						<option value="<?php echo esc_attr( (string) absint( $option['id'] ?? 0 ) ); ?>" <?php selected( $home_id, absint( $option['id'] ?? 0 ) ); ?>>
							<?php echo esc_html( (string) ( $option['name'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="jmrs_vacancy"><?php echo esc_html__( 'Vacancy', 'jm-referral-system' ); ?></label>
				<select name="jmrs_vacancy" id="jmrs_vacancy">
					<option value="all" <?php selected( $vacancy, 'all' ); ?>><?php echo esc_html__( 'All', 'jm-referral-system' ); ?></option>
					<option value="vacant" <?php selected( $vacancy, 'vacant' ); ?>><?php echo esc_html__( 'Has vacancies', 'jm-referral-system' ); ?></option>
					<option value="occupied" <?php selected( $vacancy, 'occupied' ); ?>><?php echo esc_html__( 'Has occupied rooms', 'jm-referral-system' ); ?></option>
				</select>
			</p>
			<p>
				<label for="jmrs_search"><?php echo esc_html__( 'Search', 'jm-referral-system' ); ?></label>
				<input type="search" name="jmrs_search" id="jmrs_search" value="<?php echo esc_attr( $search ); ?>" />
			</p>
			<p class="jmrs-portal-filters__submit">
				<button type="submit" class="jmrs-button jmrs-button--primary"><?php echo esc_html__( 'Apply', 'jm-referral-system' ); ?></button>
			</p>
		</div>
	</form>
</section>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-occ-results">
	<?php
	$section_title   = __( 'Homes', 'jm-referral-system' );
	$section_id      = 'jmrs-portal-occ-results';
	$section_badge   = '';
	$section_actions = array();
	if ( $can_manage ) {
		$section_actions[] = array( __( 'Place Resident', 'jm-referral-system' ), \JMReferral\Portal\PortalUrls::occupancy_place(), 'jmrs-button jmrs-button--primary' );
	}
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
	?>

	<?php if ( empty( $homes ) ) : ?>
		<?php
		$empty_title   = __( 'No homes match the current filters.', 'jm-referral-system' );
		$empty_message = __( 'Try a different home, vacancy filter, or search term.', 'jm-referral-system' );
		$empty_actions = array();
		include JMRS_PLUGIN_PATH . 'templates/portal/partials/empty-state.php';
		?>
	<?php else : ?>
		<div class="jmrs-portal-table-wrap">
			<table class="jmrs-portal-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Home', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Capacity', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Occupied', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Vacant', 'jm-referral-system' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $homes as $row ) : ?>
						<?php
						$view_url = (string) ( $row['view_url'] ?? '' );
						?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'Home', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $row['name'] ?? '' ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Capacity', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) absint( $row['capacity'] ?? 0 ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Occupied', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) absint( $row['occupied'] ?? 0 ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Vacant', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) absint( $row['vacant'] ?? 0 ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
								<?php if ( '' !== $view_url ) : ?>
									<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html__( 'View Home', 'jm-referral-system' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
