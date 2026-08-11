<?php
/**
 * Management Dashboard (Phase 2A) — presentation board over live JMRS data.
 *
 * @var array<string, mixed> $view
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$board = is_array( $view['board'] ?? null ) ? $view['board'] : array();

if ( empty( $board['show'] ) ) {
	?>
	<div class="jmrs-portal-notice jmrs-portal-notice--info" role="status">
		<p><?php echo esc_html__( 'The Management Dashboard is not available for your role.', 'jm-referral-system' ); ?></p>
	</div>
	<?php
	return;
}

$masthead   = is_array( $board['masthead'] ?? null ) ? $board['masthead'] : array();
$kpis       = is_array( $board['kpis'] ?? null ) ? $board['kpis'] : array();
$stages     = is_array( $board['stages'] ?? null ) ? $board['stages'] : array();
$funnel     = is_array( $board['funnel'] ?? null ) ? $board['funnel'] : array();
$actions    = is_array( $board['actions'] ?? null ) ? $board['actions'] : array();
$homes_data = is_array( $board['homes'] ?? null ) ? $board['homes'] : array();
$home_cards = is_array( $homes_data['homes'] ?? null ) ? $homes_data['homes'] : array();
$estate     = is_array( $homes_data['estate'] ?? null ) ? $homes_data['estate'] : null;
$ownership  = is_array( $board['ownership'] ?? null ) ? $board['ownership'] : array();
$mode       = (string) ( $board['mode'] ?? 'now' );
$show_homes = ! empty( $board['show_homes'] );
$show_own   = ! empty( $board['show_ownership'] );
$actions_high = absint( $board['actions_high'] ?? 0 );

$now_url     = \JMReferral\Portal\PortalUrls::management_with_args( array( 'jmrs_mgmt_mode' => 'now' ) );
$reached_url = \JMReferral\Portal\PortalUrls::management_with_args( array( 'jmrs_mgmt_mode' => 'reached' ) );

$funnel_json = wp_json_encode( $funnel );
if ( ! is_string( $funnel_json ) ) {
	$funnel_json = '[]';
}
?>
<div class="jmrs-mgmt" data-jmrs-mgmt>
	<header class="jmrs-mgmt__masthead">
		<div class="jmrs-mgmt__masthead-in">
			<div class="jmrs-mgmt__brand">
				<h1><?php echo esc_html( (string) ( $masthead['company'] ?? 'J&M Healthcare Services' ) ); ?></h1>
				<span class="jmrs-mgmt__sub"><?php echo esc_html( (string) ( $masthead['subtitle'] ?? '' ) ); ?></span>
			</div>
			<div class="jmrs-mgmt__meta">
				<div>
					<?php echo esc_html( (string) ( $masthead['period_label'] ?? '' ) ); ?>
					<b><?php echo esc_html( (string) ( $masthead['period_value'] ?? '' ) ); ?></b>
				</div>
				<div>
					<?php echo esc_html( (string) ( $masthead['updated_label'] ?? '' ) ); ?>
					<b><?php echo esc_html( (string) ( $masthead['updated_value'] ?? '' ) ); ?></b>
				</div>
				<div>
					<?php echo esc_html( (string) ( $masthead['audience'] ?? '' ) ); ?>
					<b><?php echo esc_html( (string) ( $masthead['audience_value'] ?? '' ) ); ?></b>
				</div>
			</div>
		</div>
	</header>

	<section class="jmrs-mgmt__figures" aria-label="<?php echo esc_attr__( 'Headline figures', 'jm-referral-system' ); ?>">
		<?php foreach ( $kpis as $kpi ) : ?>
			<?php if ( ! is_array( $kpi ) ) { continue; } ?>
			<div class="jmrs-mgmt__fig">
				<div class="jmrs-mgmt__fig-lab"><?php echo esc_html( (string) ( $kpi['label'] ?? '' ) ); ?></div>
				<div class="jmrs-mgmt__fig-val"><?php echo esc_html( (string) ( $kpi['value'] ?? '' ) ); ?></div>
				<div class="jmrs-mgmt__fig-note"><?php echo esc_html( (string) ( $kpi['note'] ?? '' ) ); ?></div>
			</div>
		<?php endforeach; ?>
	</section>

	<section class="jmrs-mgmt__funnel-card">
		<div class="jmrs-mgmt__card-head">
			<h2><?php echo esc_html__( 'Pipeline funnel', 'jm-referral-system' ); ?></h2>
			<span class="jmrs-mgmt__hint"><?php echo esc_html__( 'Segment depth reflects referrals that have reached each visual stage (from stage history). Select a stage to jump to its panel.', 'jm-referral-system' ); ?></span>
		</div>
		<svg class="jmrs-mgmt__funnel" id="jmrs-mgmt-funnel" viewBox="0 0 1200 168" role="img" aria-label="<?php echo esc_attr__( 'Referral pipeline funnel by visual stage', 'jm-referral-system' ); ?>" data-funnel="<?php echo esc_attr( $funnel_json ); ?>"></svg>
		<div class="jmrs-mgmt__funnel-legend" id="jmrs-mgmt-legend"></div>
	</section>

	<nav class="jmrs-mgmt__tabs jmrs-mgmt__no-print" role="tablist" aria-label="<?php echo esc_attr__( 'Management dashboard views', 'jm-referral-system' ); ?>">
		<button type="button" class="jmrs-mgmt__tab" role="tab" id="jmrs-tab-pipeline" aria-controls="jmrs-view-pipeline" aria-selected="true"><?php echo esc_html__( 'Pipeline stages', 'jm-referral-system' ); ?></button>
		<?php if ( $show_homes ) : ?>
			<button type="button" class="jmrs-mgmt__tab" role="tab" id="jmrs-tab-homes" aria-controls="jmrs-view-homes" aria-selected="false"><?php echo esc_html__( 'Homes & capacity', 'jm-referral-system' ); ?></button>
		<?php endif; ?>
		<?php if ( $show_own ) : ?>
			<button type="button" class="jmrs-mgmt__tab" role="tab" id="jmrs-tab-team" aria-controls="jmrs-view-team" aria-selected="false"><?php echo esc_html__( 'Ownership', 'jm-referral-system' ); ?></button>
		<?php endif; ?>
		<button type="button" class="jmrs-mgmt__tab" role="tab" id="jmrs-tab-recs" aria-controls="jmrs-view-recs" aria-selected="false">
			<?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?>
			<?php if ( $actions_high > 0 ) : ?>
				<span class="jmrs-mgmt__badge"><?php echo esc_html( (string) $actions_high ); ?></span>
			<?php endif; ?>
		</button>
		<div class="jmrs-mgmt__toolbar">
			<div class="jmrs-mgmt__seg" role="group" aria-label="<?php echo esc_attr__( 'Row filter', 'jm-referral-system' ); ?>">
				<a class="jmrs-mgmt__seg-btn<?php echo 'now' === $mode ? ' is-active' : ''; ?>" href="<?php echo esc_url( $now_url ); ?>" <?php echo 'now' === $mode ? 'aria-current="page"' : ''; ?>><?php echo esc_html__( 'Here now', 'jm-referral-system' ); ?></a>
				<a class="jmrs-mgmt__seg-btn<?php echo 'reached' === $mode ? ' is-active' : ''; ?>" href="<?php echo esc_url( $reached_url ); ?>" <?php echo 'reached' === $mode ? 'aria-current="page"' : ''; ?>><?php echo esc_html__( 'All who reached', 'jm-referral-system' ); ?></a>
			</div>
			<button type="button" class="jmrs-mgmt__btn jmrs-mgmt__btn--print" id="jmrs-mgmt-print"><?php echo esc_html__( 'Print', 'jm-referral-system' ); ?></button>
		</div>
	</nav>

	<section class="jmrs-mgmt__view" id="jmrs-view-pipeline" role="tabpanel" aria-labelledby="jmrs-tab-pipeline">
		<?php foreach ( $stages as $stage ) : ?>
			<?php
			if ( ! is_array( $stage ) ) {
				continue;
			}
			$key    = (string) ( $stage['key'] ?? '' );
			$colour = (string) ( $stage['colour'] ?? '#2B4C7E' );
			$order  = absint( $stage['order'] ?? 0 );
			$rows   = is_array( $stage['rows'] ?? null ) ? $stage['rows'] : array();
			?>
			<article class="jmrs-mgmt__panel" id="jmrs-stage-<?php echo esc_attr( $key ); ?>" style="border-left-color:<?php echo esc_attr( $colour ); ?>" data-stage-key="<?php echo esc_attr( $key ); ?>">
				<div class="jmrs-mgmt__panel-head">
					<div>
						<div class="jmrs-mgmt__panel-title">
							<span class="jmrs-mgmt__stage-no" style="background:<?php echo esc_attr( $colour ); ?>"><?php echo esc_html( sprintf( /* translators: %d: stage number */ __( 'STAGE %d', 'jm-referral-system' ), $order ) ); ?></span>
							<h3><?php echo esc_html( (string) ( $stage['name'] ?? '' ) ); ?></h3>
						</div>
						<p class="jmrs-mgmt__panel-q"><?php echo esc_html( (string) ( $stage['question'] ?? '' ) ); ?></p>
					</div>
					<div class="jmrs-mgmt__panel-stats">
						<div class="jmrs-mgmt__pstat">
							<div class="jmrs-mgmt__pstat-n"><?php echo esc_html( (string) absint( $stage['reached'] ?? 0 ) ); ?></div>
							<div class="jmrs-mgmt__pstat-l"><?php echo esc_html__( 'Reached stage', 'jm-referral-system' ); ?></div>
						</div>
						<div class="jmrs-mgmt__pstat">
							<div class="jmrs-mgmt__pstat-n" style="color:<?php echo esc_attr( $colour ); ?>"><?php echo esc_html( (string) absint( $stage['here_now'] ?? 0 ) ); ?></div>
							<div class="jmrs-mgmt__pstat-l"><?php echo esc_html__( 'Here now', 'jm-referral-system' ); ?></div>
						</div>
						<?php if ( (float) ( $stage['proposed_value'] ?? 0 ) > 0 ) : ?>
							<div class="jmrs-mgmt__pstat">
								<div class="jmrs-mgmt__pstat-n" style="color:<?php echo esc_attr( $colour ); ?>"><?php echo esc_html( is_numeric( $stage['proposed_value'] ) ? '£' . number_format_i18n( (float) $stage['proposed_value'], 2 ) : (string) $stage['proposed_value'] ); ?></div>
								<div class="jmrs-mgmt__pstat-l"><?php echo esc_html__( 'Proposed Package Value (here now)', 'jm-referral-system' ); ?></div>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( [] === $rows ) : ?>
					<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No referrals in this view.', 'jm-referral-system' ); ?></div>
				<?php else : ?>
					<div class="jmrs-mgmt__tbl-scroll">
						<table class="jmrs-mgmt__table">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
									<th><?php echo esc_html__( 'Canonical stage', 'jm-referral-system' ); ?></th>
									<th><?php echo esc_html__( 'Owner', 'jm-referral-system' ); ?></th>
									<th class="jmrs-mgmt__num"><?php echo esc_html__( 'Days in stage', 'jm-referral-system' ); ?></th>
									<?php if ( 'appointment_set' === $key ) : ?>
										<th><?php echo esc_html__( 'Scheduling status', 'jm-referral-system' ); ?></th>
									<?php elseif ( 'assessment' === $key ) : ?>
										<th><?php echo esc_html__( 'Assessment schedule', 'jm-referral-system' ); ?></th>
										<th><?php echo esc_html__( 'Assessor', 'jm-referral-system' ); ?></th>
									<?php endif; ?>
									<?php if ( in_array( $key, array( 'package_costing', 'authority_consideration', 'placement_transition' ), true ) ) : ?>
										<th class="jmrs-mgmt__num"><?php echo esc_html__( 'Proposed Package Value', 'jm-referral-system' ); ?></th>
									<?php endif; ?>
									<th><?php echo esc_html__( 'Open', 'jm-referral-system' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rows as $row ) : ?>
									<?php if ( ! is_array( $row ) ) { continue; } ?>
									<tr>
										<td>
											<span class="jmrs-mgmt__ref"><?php echo esc_html( (string) ( $row['referral_number'] ?? '' ) ); ?></span>
											<div class="jmrs-mgmt__role"><?php echo esc_html( (string) ( $row['client_name'] ?? '' ) ); ?></div>
										</td>
										<td><?php echo esc_html( (string) ( $row['stage_label'] ?? '' ) ); ?></td>
										<td class="jmrs-mgmt__who"><?php echo esc_html( (string) ( $row['owner_name'] ?? '' ) ); ?></td>
										<td class="jmrs-mgmt__num"><?php echo null !== ( $row['waiting_days'] ?? null ) ? esc_html( (string) (int) $row['waiting_days'] ) : '—'; ?></td>
										<?php if ( 'appointment_set' === $key ) : ?>
											<td>
												<span class="jmrs-mgmt__pill jmrs-mgmt__pill--warn"><?php echo esc_html__( 'Scheduling required', 'jm-referral-system' ); ?></span>
												<div class="jmrs-mgmt__role"><?php echo esc_html__( 'Appointment to arrange — not booked', 'jm-referral-system' ); ?></div>
											</td>
										<?php elseif ( 'assessment' === $key ) : ?>
											<td>
												<?php
												$sched = (string) ( $row['scheduled_at'] ?? '' );
												$loc   = (string) ( $row['location_name'] ?? '' );
												if ( '' !== $sched ) {
													echo esc_html( $sched );
													if ( '' !== $loc ) {
														echo '<div class="jmrs-mgmt__role">' . esc_html( $loc ) . '</div>';
													}
												} else {
													echo '<span class="jmrs-mgmt__pill jmrs-mgmt__pill--warn">' . esc_html__( 'No schedule on record', 'jm-referral-system' ) . '</span>';
												}
												?>
											</td>
											<td class="jmrs-mgmt__who"><?php echo esc_html( (string) ( $row['assessor_name'] ?? '—' ) ); ?></td>
										<?php endif; ?>
										<?php if ( in_array( $key, array( 'package_costing', 'authority_consideration', 'placement_transition' ), true ) ) : ?>
											<td class="jmrs-mgmt__num"><b><?php echo esc_html( (string) ( $row['proposed_value'] ?? '—' ) ); ?></b></td>
										<?php endif; ?>
										<td>
											<?php if ( '' !== (string) ( $row['view_url'] ?? '' ) ) : ?>
												<a class="jmrs-mgmt__link" href="<?php echo esc_url( (string) $row['view_url'] ); ?>"><?php echo esc_html__( 'View', 'jm-referral-system' ); ?></a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</section>

	<?php if ( $show_homes ) : ?>
		<section class="jmrs-mgmt__view" id="jmrs-view-homes" role="tabpanel" aria-labelledby="jmrs-tab-homes" hidden>
			<div class="jmrs-mgmt__card-head">
				<h2><?php echo esc_html__( 'Homes and capacity', 'jm-referral-system' ); ?></h2>
				<span class="jmrs-mgmt__hint">
					<?php
					if ( is_array( $estate ) ) {
						printf(
							/* translators: 1: occupied 2: capacity 3: percent */
							esc_html__( 'Estate occupancy %1$d of %2$d (%3$s%%). Derived from active bedrooms and occupancies.', 'jm-referral-system' ),
							absint( $estate['occupied'] ?? 0 ),
							absint( $estate['capacity'] ?? 0 ),
							esc_html( (string) ( $estate['occupancy_pct'] ?? 0 ) )
						);
					}
					?>
				</span>
			</div>
			<div class="jmrs-mgmt__homes-grid">
				<?php foreach ( $home_cards as $home ) : ?>
					<?php if ( ! is_array( $home ) ) { continue; } ?>
					<div class="jmrs-mgmt__home">
						<h4>
							<?php if ( '' !== (string) ( $home['view_url'] ?? '' ) ) : ?>
								<a href="<?php echo esc_url( (string) $home['view_url'] ); ?>"><?php echo esc_html( (string) ( $home['name'] ?? '' ) ); ?></a>
							<?php else : ?>
								<?php echo esc_html( (string) ( $home['name'] ?? '' ) ); ?>
							<?php endif; ?>
						</h4>
						<div class="jmrs-mgmt__area"><?php echo esc_html( (string) ( $home['area'] ?? '' ) ); ?></div>
						<div class="jmrs-mgmt__beds" aria-hidden="true">
							<?php
							$cap = absint( $home['capacity'] ?? 0 );
							$occ = absint( $home['occupied'] ?? 0 );
							for ( $i = 0; $i < $cap; $i++ ) {
								$class = $i < $occ ? ' is-filled' : '';
								echo '<span class="jmrs-mgmt__bed' . esc_attr( $class ) . '"></span>';
							}
							?>
						</div>
						<div class="jmrs-mgmt__home-row"><span><?php echo esc_html__( 'Capacity', 'jm-referral-system' ); ?></span><span><?php echo esc_html( (string) absint( $home['capacity'] ?? 0 ) ); ?></span></div>
						<div class="jmrs-mgmt__home-row"><span><?php echo esc_html__( 'Occupied', 'jm-referral-system' ); ?></span><span><?php echo esc_html( (string) absint( $home['occupied'] ?? 0 ) ); ?></span></div>
						<div class="jmrs-mgmt__home-row"><span><?php echo esc_html__( 'Vacant', 'jm-referral-system' ); ?></span><span><?php echo esc_html( (string) absint( $home['vacant'] ?? 0 ) ); ?></span></div>
						<div class="jmrs-mgmt__home-row"><span><?php echo esc_html__( 'Occupancy', 'jm-referral-system' ); ?></span><span><?php echo esc_html( (string) ( $home['occupancy_pct'] ?? 0 ) ); ?>%</span></div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $show_own ) : ?>
		<section class="jmrs-mgmt__view" id="jmrs-view-team" role="tabpanel" aria-labelledby="jmrs-tab-team" hidden>
			<article class="jmrs-mgmt__panel" style="border-left-color:#3F7D3A">
				<div class="jmrs-mgmt__panel-head">
					<div>
						<div class="jmrs-mgmt__panel-title"><h3><?php echo esc_html__( 'Current pipeline ownership', 'jm-referral-system' ); ?></h3></div>
						<p class="jmrs-mgmt__panel-q"><?php echo esc_html__( 'Live acquisition referrals by current assignee only.', 'jm-referral-system' ); ?></p>
					</div>
				</div>
				<div class="jmrs-mgmt__tbl-scroll">
					<table class="jmrs-mgmt__table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Team member', 'jm-referral-system' ); ?></th>
								<th class="jmrs-mgmt__num"><?php echo esc_html__( 'Referrals owned (live)', 'jm-referral-system' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $ownership as $own ) : ?>
								<?php if ( ! is_array( $own ) ) { continue; } ?>
								<tr>
									<td class="jmrs-mgmt__who"><?php echo esc_html( (string) ( $own['name'] ?? '' ) ); ?></td>
									<td class="jmrs-mgmt__num"><?php echo esc_html( (string) absint( $own['referrals_owned'] ?? 0 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</article>
		</section>
	<?php endif; ?>

	<section class="jmrs-mgmt__view" id="jmrs-view-recs" role="tabpanel" aria-labelledby="jmrs-tab-recs" hidden>
		<div class="jmrs-mgmt__card-head">
			<h2><?php echo esc_html__( 'Recommended actions', 'jm-referral-system' ); ?></h2>
		</div>
		<?php if ( [] === $actions ) : ?>
			<div class="jmrs-mgmt__rec jmrs-mgmt__rec--low">
				<div class="jmrs-mgmt__rec-head">
					<h4><?php echo esc_html__( 'Nothing needs attention', 'jm-referral-system' ); ?></h4>
					<span class="jmrs-mgmt__pill jmrs-mgmt__pill--ok"><?php echo esc_html__( 'OK', 'jm-referral-system' ); ?></span>
				</div>
				<p><?php echo esc_html__( 'No Needs Attention items for the current pipeline filters.', 'jm-referral-system' ); ?></p>
			</div>
		<?php else : ?>
			<?php foreach ( $actions as $action ) : ?>
				<?php
				if ( ! is_array( $action ) ) {
					continue;
				}
				$sev = (string) ( $action['severity'] ?? 'medium' );
				$rec_class = 'critical' === $sev || 'high' === $sev ? 'high' : ( 'medium' === $sev ? 'med' : 'low' );
				$pill = 'critical' === $sev || 'high' === $sev ? 'risk' : ( 'medium' === $sev ? 'warn' : 'ok' );
				$labels = is_array( $action['reason_labels'] ?? null ) ? $action['reason_labels'] : array();
				?>
				<div class="jmrs-mgmt__rec jmrs-mgmt__rec--<?php echo esc_attr( $rec_class ); ?>">
					<div class="jmrs-mgmt__rec-head">
						<h4>
							<?php echo esc_html( (string) ( $action['referral_number'] ?? '' ) ); ?>
							—
							<?php echo esc_html( (string) ( $action['client_name'] ?? '' ) ); ?>
						</h4>
						<span class="jmrs-mgmt__pill jmrs-mgmt__pill--<?php echo esc_attr( $pill ); ?>"><?php echo esc_html( ucfirst( $sev ) ); ?></span>
					</div>
					<p>
						<?php echo esc_html( (string) ( $action['stage_label'] ?? '' ) ); ?>
						<?php if ( '' !== (string) ( $action['waiting_label'] ?? '' ) ) : ?>
							· <?php echo esc_html( (string) $action['waiting_label'] ); ?>
						<?php endif; ?>
					</p>
					<?php if ( [] !== $labels ) : ?>
						<p><?php echo esc_html( implode( ' · ', array_map( 'strval', $labels ) ) ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== (string) ( $action['next_action'] ?? '' ) ) : ?>
						<div class="jmrs-mgmt__act"><?php echo esc_html( (string) $action['next_action'] ); ?>
							<?php if ( '' !== (string) ( $action['owner_name'] ?? '' ) ) : ?>
								— <?php echo esc_html( (string) $action['owner_name'] ); ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if ( '' !== (string) ( $action['view_url'] ?? '' ) ) : ?>
						<p><a class="jmrs-mgmt__link" href="<?php echo esc_url( (string) $action['view_url'] ); ?>"><?php echo esc_html__( 'Open referral', 'jm-referral-system' ); ?></a></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>
</div>
