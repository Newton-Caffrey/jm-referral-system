<?php
/**
 * Portal dashboard.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
?>
<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-dash-stats">
	<h2 id="jmrs-portal-dash-stats" class="jmrs-portal-section__title"><?php echo esc_html__( 'Overview', 'jm-referral-system' ); ?></h2>

	<div class="jmrs-portal-stat-grid">
		<?php if ( $scoped_to_assigned ) : ?>
			<?php if ( $show_my_active_clients ) : ?>
				<div class="jmrs-portal-stat-card">
					<span class="jmrs-portal-stat-card__value"><?php echo esc_html( (string) $my_active_clients_count ); ?></span>
					<span class="jmrs-portal-stat-card__label"><?php echo esc_html__( 'My Active Clients', 'jm-referral-system' ); ?></span>
				</div>
			<?php endif; ?>
			<div class="jmrs-portal-stat-card">
				<span class="jmrs-portal-stat-card__value"><?php echo esc_html( (string) ( $stats['total'] ?? 0 ) ); ?></span>
				<span class="jmrs-portal-stat-card__label"><?php echo esc_html__( 'My Referrals', 'jm-referral-system' ); ?></span>
			</div>
			<?php if ( $can_view_visits ) : ?>
				<div class="jmrs-portal-stat-card">
					<span class="jmrs-portal-stat-card__value"><?php echo esc_html( (string) $visits_today_count ); ?></span>
					<span class="jmrs-portal-stat-card__label"><?php echo esc_html__( "Today's Visits", 'jm-referral-system' ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $show_todays_completed ) : ?>
				<div class="jmrs-portal-stat-card">
					<span class="jmrs-portal-stat-card__value"><?php echo esc_html( (string) count( $todays_completed_visits ) ); ?></span>
					<span class="jmrs-portal-stat-card__label"><?php echo esc_html__( "Today's Completed Visits", 'jm-referral-system' ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $show_my_medication_exceptions ) : ?>
				<div class="jmrs-portal-stat-card">
					<span class="jmrs-portal-stat-card__value"><?php echo esc_html( (string) $my_medication_exceptions_today ); ?></span>
					<span class="jmrs-portal-stat-card__label"><?php echo esc_html__( 'My Medication Exceptions', 'jm-referral-system' ); ?></span>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<div class="jmrs-portal-stat-card">
				<span class="jmrs-portal-stat-card__value"><?php echo esc_html( (string) ( $stats['total'] ?? 0 ) ); ?></span>
				<span class="jmrs-portal-stat-card__label"><?php echo esc_html__( 'Total Active Referrals', 'jm-referral-system' ); ?></span>
			</div>
			<div class="jmrs-portal-stat-card">
				<span class="jmrs-portal-stat-card__value"><?php echo esc_html( (string) ( $stats['new'] ?? 0 ) ); ?></span>
				<span class="jmrs-portal-stat-card__label"><?php echo esc_html__( 'New Referrals', 'jm-referral-system' ); ?></span>
			</div>
			<?php if ( $can_view_visits ) : ?>
				<div class="jmrs-portal-stat-card">
					<span class="jmrs-portal-stat-card__value"><?php echo esc_html( (string) $visits_today_count ); ?></span>
					<span class="jmrs-portal-stat-card__label"><?php echo esc_html__( 'Visits Today', 'jm-referral-system' ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $show_awaiting_review ) : ?>
				<div class="jmrs-portal-stat-card">
					<span class="jmrs-portal-stat-card__value"><?php echo esc_html( (string) count( $awaiting_review_visits ) ); ?></span>
					<span class="jmrs-portal-stat-card__label"><?php echo esc_html__( 'Visits Awaiting Review', 'jm-referral-system' ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $show_operational_alerts && is_array( $operational_alerts ) ) : ?>
				<div class="jmrs-portal-stat-card">
					<span class="jmrs-portal-stat-card__value"><?php echo esc_html( (string) absint( $operational_alerts['counts']['total'] ?? 0 ) ); ?></span>
					<span class="jmrs-portal-stat-card__label"><?php echo esc_html__( 'Operational Alerts', 'jm-referral-system' ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $show_medication_exceptions ) : ?>
				<div class="jmrs-portal-stat-card">
					<span class="jmrs-portal-stat-card__value"><?php echo esc_html( (string) $medication_exceptions_today ); ?></span>
					<span class="jmrs-portal-stat-card__label"><?php echo esc_html__( 'Medication Exceptions Today', 'jm-referral-system' ); ?></span>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<p class="jmrs-portal-actions">
		<a class="jmrs-portal-btn jmrs-portal-btn--primary" href="<?php echo esc_url( $referrals_url ); ?>">
			<?php
			echo esc_html(
				$scoped_to_assigned
					? __( 'View My Referrals', 'jm-referral-system' )
					: __( 'View Referrals', 'jm-referral-system' )
			);
			?>
		</a>
	</p>
</section>

<?php if ( $can_view_visits && ! empty( $upcoming_visits ) ) : ?>
	<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-dash-visits">
		<h2 id="jmrs-portal-dash-visits" class="jmrs-portal-section__title"><?php echo esc_html__( 'Upcoming Visits', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-table-wrap">
			<table class="jmrs-portal-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Date', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Staff', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $upcoming_visits as $visit_row ) : ?>
						<?php
						$status_key   = (string) ( $visit_row['status'] ?? '' );
						$status_label = $visit_status_labels[ $status_key ] ?? $status_key;
						$ref_url      = (string) ( $visit_row['referral_url'] ?? '' );
						$client       = (string) ( $visit_row['client_name'] ?? '' );
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
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
<?php endif; ?>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-dash-recent">
	<h2 id="jmrs-portal-dash-recent" class="jmrs-portal-section__title">
		<?php
		echo esc_html(
			$scoped_to_assigned
				? __( 'My Referrals', 'jm-referral-system' )
				: __( 'Recent Referrals', 'jm-referral-system' )
		);
		?>
	</h2>

	<?php if ( empty( $recent ) ) : ?>
		<div class="jmrs-portal-empty">
			<p><?php echo esc_html__( 'No referrals to show.', 'jm-referral-system' ); ?></p>
		</div>
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
						$portal_url = (string) ( $row['portal_url'] ?? '' );
						$client     = trim( (string) ( $row['client_first_name'] ?? '' ) . ' ' . (string) ( $row['client_last_name'] ?? '' ) );
						if ( '' === $client ) {
							$client = (string) ( $row['client_name'] ?? '' );
						}
						?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'Number', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $row['referral_number'] ?? '' ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Client', 'jm-referral-system' ); ?>"><?php echo esc_html( $client ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge"><?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) ( $row['status'] ?? '' ) ) ) ); ?></span></td>
							<td data-label="<?php echo esc_attr__( 'Priority', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge jmrs-portal-badge--priority"><?php echo esc_html( ucfirst( (string) ( $row['priority'] ?? '' ) ) ); ?></span></td>
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
</section>
