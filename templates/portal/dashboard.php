<?php
/**
 * Portal dashboard.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$welcome_name                  = (string) ( $welcome_name ?? '' );
$welcome_role                  = (string) ( $welcome_role ?? '' );
$stats                         = is_array( $stats ?? null ) ? $stats : array();
$recent                        = is_array( $recent ?? null ) ? $recent : array();
$scoped_to_assigned            = ! empty( $scoped_to_assigned );
$can_view_visits               = ! empty( $can_view_visits );
$upcoming_visits               = is_array( $upcoming_visits ?? null ) ? $upcoming_visits : array();
$visit_status_labels           = is_array( $visit_status_labels ?? null ) ? $visit_status_labels : array();
$show_my_active_clients        = ! empty( $show_my_active_clients );
$my_active_clients_count       = isset( $my_active_clients_count ) ? absint( $my_active_clients_count ) : 0;
$show_awaiting_review          = ! empty( $show_awaiting_review );
$awaiting_review_visits        = is_array( $awaiting_review_visits ?? null ) ? $awaiting_review_visits : array();
$show_todays_completed         = ! empty( $show_todays_completed );
$todays_completed_visits       = is_array( $todays_completed_visits ?? null ) ? $todays_completed_visits : array();
$show_operational_alerts       = ! empty( $show_operational_alerts );
$operational_alerts            = is_array( $operational_alerts ?? null ) ? $operational_alerts : null;
$show_medication_exceptions    = ! empty( $show_medication_exceptions );
$medication_exceptions_today   = isset( $medication_exceptions_today ) ? absint( $medication_exceptions_today ) : 0;
$show_my_medication_exceptions = ! empty( $show_my_medication_exceptions );
$my_medication_exceptions_today = isset( $my_medication_exceptions_today ) ? absint( $my_medication_exceptions_today ) : 0;
$visits_today_count            = isset( $visits_today_count ) ? absint( $visits_today_count ) : 0;
$referrals_url                 = (string) ( $referrals_url ?? '' );

$jmrs_partials_path = JMRS_PLUGIN_PATH . 'templates/portal/partials/';

/**
 * @param array<int, array{value: string, label: string, href?: string, tone?: string}> $cards
 */
$jmrs_render_kpi_grid = static function ( array $cards ) use ( $jmrs_partials_path ): void {
	?>
	<div class="jmrs-portal-kpi-grid">
		<?php foreach ( $cards as $card ) : ?>
			<?php
			$kpi_value = (string) ( $card['value'] ?? '' );
			$kpi_label = (string) ( $card['label'] ?? '' );
			$kpi_href  = (string) ( $card['href'] ?? '' );
			$kpi_tone  = (string) ( $card['tone'] ?? 'default' );
			include $jmrs_partials_path . 'kpi-card.php';
			?>
		<?php endforeach; ?>
	</div>
	<?php
};

$kpi_cards = array();
if ( $scoped_to_assigned ) {
	if ( $show_my_active_clients ) {
		$kpi_cards[] = array(
			'value' => (string) $my_active_clients_count,
			'label' => __( 'My Active Clients', 'jm-referral-system' ),
		);
	}
	$kpi_cards[] = array(
		'value' => (string) ( $stats['total'] ?? 0 ),
		'label' => __( 'My Referrals', 'jm-referral-system' ),
		'href'  => $referrals_url,
	);
	if ( $can_view_visits ) {
		$kpi_cards[] = array(
			'value' => (string) $visits_today_count,
			'label' => __( "Today's Visits", 'jm-referral-system' ),
		);
	}
	if ( $show_todays_completed ) {
		$kpi_cards[] = array(
			'value' => (string) count( $todays_completed_visits ),
			'label' => __( "Today's Completed Visits", 'jm-referral-system' ),
		);
	}
	if ( $show_my_medication_exceptions ) {
		$kpi_cards[] = array(
			'value' => (string) $my_medication_exceptions_today,
			'label' => __( 'My Medication Exceptions', 'jm-referral-system' ),
			'tone'  => $my_medication_exceptions_today > 0 ? 'warning' : 'default',
		);
	}
} else {
	$kpi_cards[] = array(
		'value' => (string) ( $stats['total'] ?? 0 ),
		'label' => __( 'Total Active Referrals', 'jm-referral-system' ),
		'href'  => $referrals_url,
	);
	$kpi_cards[] = array(
		'value' => (string) ( $stats['new'] ?? 0 ),
		'label' => __( 'New Referrals', 'jm-referral-system' ),
	);
	if ( $can_view_visits ) {
		$kpi_cards[] = array(
			'value' => (string) $visits_today_count,
			'label' => __( 'Visits Today', 'jm-referral-system' ),
		);
	}
	if ( $show_awaiting_review ) {
		$kpi_cards[] = array(
			'value' => (string) count( $awaiting_review_visits ),
			'label' => __( 'Visits Awaiting Review', 'jm-referral-system' ),
			'tone'  => count( $awaiting_review_visits ) > 0 ? 'info' : 'default',
		);
	}
	if ( $show_operational_alerts && is_array( $operational_alerts ) ) {
		$alert_total = absint( $operational_alerts['counts']['total'] ?? 0 );
		$kpi_cards[] = array(
			'value' => (string) $alert_total,
			'label' => __( 'Operational Alerts', 'jm-referral-system' ),
			'tone'  => $alert_total > 0 ? 'warning' : 'default',
		);
	}
	if ( $show_medication_exceptions ) {
		$kpi_cards[] = array(
			'value' => (string) $medication_exceptions_today,
			'label' => __( 'Medication Exceptions Today', 'jm-referral-system' ),
			'tone'  => $medication_exceptions_today > 0 ? 'warning' : 'default',
		);
	}
}
?>
<div class="jmrs-portal-dash">
	<header class="jmrs-portal-welcome">
		<div class="jmrs-portal-welcome__text">
			<h2 class="jmrs-portal-welcome__title">
				<?php
				if ( '' !== $welcome_name ) {
					printf(
						/* translators: %s: staff member's display name */
						esc_html__( 'Welcome, %s', 'jm-referral-system' ),
						esc_html( $welcome_name )
					);
				} else {
					echo esc_html__( 'Welcome', 'jm-referral-system' );
				}
				?>
			</h2>
			<?php if ( '' !== $welcome_role ) : ?>
				<p class="jmrs-portal-welcome__role"><?php echo esc_html( $welcome_role ); ?></p>
			<?php endif; ?>
		</div>
		<div class="jmrs-portal-quick-actions">
			<a class="jmrs-button jmrs-button--primary" href="<?php echo esc_url( $referrals_url ); ?>">
				<?php
				echo esc_html(
					$scoped_to_assigned
						? __( 'View My Referrals', 'jm-referral-system' )
						: __( 'View Referrals', 'jm-referral-system' )
				);
				?>
			</a>
			<?php if ( $can_view_visits ) : ?>
				<a class="jmrs-portal-link" href="<?php echo esc_url( $referrals_url ); ?>">
					<?php echo esc_html__( "View today's schedule", 'jm-referral-system' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</header>

	<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-dash-stats">
		<?php
		$section_title = __( 'Overview', 'jm-referral-system' );
		$section_id    = 'jmrs-portal-dash-stats';
		unset( $section_badge, $section_actions );
		include $jmrs_partials_path . 'section-header.php';
		?>
		<?php $jmrs_render_kpi_grid( $kpi_cards ); ?>
	</section>

	<?php
	$context            = 'portal';
	$pipeline_dashboard = is_array( $pipeline_dashboard ?? null ) ? $pipeline_dashboard : array();
	include JMRS_PLUGIN_PATH . 'templates/dashboard/partials/pipeline-overview.php';
	?>

	<?php if ( $can_view_visits ) : ?>
		<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-dash-visits">
			<?php
			$section_title = __( "Today's Schedule", 'jm-referral-system' );
			$section_id    = 'jmrs-portal-dash-visits';
			unset( $section_badge, $section_actions );
			include $jmrs_partials_path . 'section-header.php';
			?>
			<?php if ( empty( $upcoming_visits ) ) : ?>
				<?php
				$empty_title   = '';
				$empty_message = __( 'No visits scheduled in the coming days.', 'jm-referral-system' );
				unset( $empty_actions );
				include $jmrs_partials_path . 'empty-state.php';
				?>
			<?php else : ?>
				<div class="jmrs-portal-table-wrap">
					<table class="jmrs-portal-table">
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html__( 'Date', 'jm-referral-system' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Staff', 'jm-referral-system' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $upcoming_visits as $visit_row ) : ?>
								<?php
								$status_key   = (string) ( $visit_row['status'] ?? '' );
								$status_label = $visit_status_labels[ $status_key ] ?? $status_key;
								$ref_url      = (string) ( $visit_row['referral_url'] ?? '' );
								$client       = (string) ( $visit_row['client_name'] ?? '' );
								$execute_url  = (string) ( $visit_row['execute_url'] ?? '' );
								?>
								<tr>
									<td data-label="<?php echo esc_attr__( 'Date', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $visit_row['visit_date'] ?? '' ) ); ?></td>
									<td data-label="<?php echo esc_attr__( 'Client', 'jm-referral-system' ); ?>">
										<?php if ( '' !== $ref_url ) : ?>
											<a href="<?php echo esc_url( $ref_url ); ?>"><?php echo esc_html( '' !== $client ? $client : __( 'View referral', 'jm-referral-system' ) ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $client ); ?>
										<?php endif; ?>
									</td>
									<td data-label="<?php echo esc_attr__( 'Staff', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $visit_row['assigned_staff_name'] ?? '' ) ); ?></td>
									<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge"><?php echo esc_html( $status_label ); ?></span></td>
									<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
										<?php if ( '' !== $execute_url ) : ?>
											<a class="jmrs-portal-link" href="<?php echo esc_url( $execute_url ); ?>"><?php echo esc_html__( 'Execute', 'jm-referral-system' ); ?></a>
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
	<?php endif; ?>

	<?php if ( $show_awaiting_review ) : ?>
		<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-dash-awaiting-review">
			<?php
			$section_title = __( 'Visits Awaiting Review', 'jm-referral-system' );
			$section_id    = 'jmrs-portal-dash-awaiting-review';
			unset( $section_badge, $section_actions );
			include $jmrs_partials_path . 'section-header.php';
			?>
			<?php if ( empty( $awaiting_review_visits ) ) : ?>
				<?php
				$empty_title   = '';
				$empty_message = __( 'No visits waiting for review.', 'jm-referral-system' );
				unset( $empty_actions );
				include $jmrs_partials_path . 'empty-state.php';
				?>
			<?php else : ?>
				<div class="jmrs-portal-table-wrap">
					<table class="jmrs-portal-table">
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html__( 'Date', 'jm-referral-system' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Staff', 'jm-referral-system' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $awaiting_review_visits as $visit_row ) : ?>
								<?php
								$ref_url      = (string) ( $visit_row['referral_url'] ?? '' );
								$client       = (string) ( $visit_row['client_name'] ?? '' );
								$review_url   = (string) ( $visit_row['review_url'] ?? '' );
								$outcome_text = (string) ( $visit_row['outcome_label'] ?? '' );
								?>
								<tr>
									<td data-label="<?php echo esc_attr__( 'Date', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $visit_row['visit_date'] ?? '' ) ); ?></td>
									<td data-label="<?php echo esc_attr__( 'Client', 'jm-referral-system' ); ?>">
										<?php if ( '' !== $ref_url ) : ?>
											<a href="<?php echo esc_url( $ref_url ); ?>"><?php echo esc_html( '' !== $client ? $client : __( 'View referral', 'jm-referral-system' ) ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $client ); ?>
										<?php endif; ?>
									</td>
									<td data-label="<?php echo esc_attr__( 'Staff', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $visit_row['assigned_staff_name'] ?? '' ) ); ?></td>
									<td data-label="<?php echo esc_attr__( 'Outcome', 'jm-referral-system' ); ?>"><?php echo '' !== $outcome_text ? esc_html( $outcome_text ) : '—'; ?></td>
									<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
										<?php if ( '' !== $review_url ) : ?>
											<a class="jmrs-portal-link" href="<?php echo esc_url( $review_url ); ?>"><?php echo esc_html__( 'Review', 'jm-referral-system' ); ?></a>
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
	<?php endif; ?>

	<?php if ( $show_todays_completed ) : ?>
		<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-dash-completed">
			<?php
			$section_title = __( "Today's Completed Visits", 'jm-referral-system' );
			$section_id    = 'jmrs-portal-dash-completed';
			unset( $section_badge, $section_actions );
			include $jmrs_partials_path . 'section-header.php';
			?>
			<?php if ( empty( $todays_completed_visits ) ) : ?>
				<?php
				$empty_title   = '';
				$empty_message = __( 'No completed visits yet today.', 'jm-referral-system' );
				unset( $empty_actions );
				include $jmrs_partials_path . 'empty-state.php';
				?>
			<?php else : ?>
				<div class="jmrs-portal-table-wrap">
					<table class="jmrs-portal-table">
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $todays_completed_visits as $visit_row ) : ?>
								<?php
								$ref_url      = (string) ( $visit_row['referral_url'] ?? '' );
								$client       = (string) ( $visit_row['client_name'] ?? '' );
								$outcome_text = (string) ( $visit_row['outcome_label'] ?? '' );
								?>
								<tr>
									<td data-label="<?php echo esc_attr__( 'Client', 'jm-referral-system' ); ?>">
										<?php if ( '' !== $ref_url ) : ?>
											<a href="<?php echo esc_url( $ref_url ); ?>"><?php echo esc_html( '' !== $client ? $client : __( 'View referral', 'jm-referral-system' ) ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $client ); ?>
										<?php endif; ?>
									</td>
									<td data-label="<?php echo esc_attr__( 'Outcome', 'jm-referral-system' ); ?>"><?php echo '' !== $outcome_text ? esc_html( $outcome_text ) : '—'; ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-dash-recent">
		<?php
		$section_title = $scoped_to_assigned
			? __( 'My Referrals', 'jm-referral-system' )
			: __( 'Recent Activity', 'jm-referral-system' );
		$section_id = 'jmrs-portal-dash-recent';
		unset( $section_badge, $section_actions );
		include $jmrs_partials_path . 'section-header.php';
		?>

		<?php if ( empty( $recent ) ) : ?>
			<?php
			$empty_title   = '';
			$empty_message = __( 'No referrals to show.', 'jm-referral-system' );
			unset( $empty_actions );
			include $jmrs_partials_path . 'empty-state.php';
			?>
		<?php else : ?>
			<div class="jmrs-portal-table-wrap">
				<table class="jmrs-portal-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Number', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent as $row ) : ?>
							<?php
							$portal_url  = (string) ( $row['portal_url'] ?? '' );
							$edit_url    = (string) ( $row['edit_url'] ?? '' );
							$archive_url = (string) ( $row['archive_url'] ?? '' );
							$can_restore = ! empty( $row['can_restore'] );
							$is_arch     = ! empty( $row['is_archived'] );
							$row_id      = absint( $row['id'] ?? 0 );
							$client      = trim( (string) ( $row['client_first_name'] ?? '' ) . ' ' . (string) ( $row['client_last_name'] ?? '' ) );
							if ( '' === $client ) {
								$client = (string) ( $row['client_name'] ?? '' );
							}
							?>
							<tr>
								<td data-label="<?php echo esc_attr__( 'Number', 'jm-referral-system' ); ?>">
									<?php echo esc_html( (string) ( $row['referral_number'] ?? '' ) ); ?>
									<?php if ( $is_arch ) : ?>
										<span class="jmrs-portal-badge jmrs-portal-badge--archive"><?php echo esc_html__( 'Archived', 'jm-referral-system' ); ?></span>
									<?php endif; ?>
								</td>
								<td data-label="<?php echo esc_attr__( 'Client', 'jm-referral-system' ); ?>"><?php echo esc_html( $client ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge"><?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) ( $row['status'] ?? '' ) ) ) ); ?></span></td>
								<td data-label="<?php echo esc_attr__( 'Priority', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge jmrs-portal-badge--priority"><?php echo esc_html( ucfirst( (string) ( $row['priority'] ?? '' ) ) ); ?></span></td>
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
										<?php if ( $can_restore && $row_id > 0 && '' !== $portal_url ) : ?>
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
	</section>
</div>
