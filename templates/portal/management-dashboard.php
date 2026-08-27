<?php
/**
 * Management Dashboard — presentation board over live JMRS data (Phase 4A).
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
$ops          = is_array( $board['operational'] ?? null ) ? $board['operational'] : array();
$show_ops     = ! empty( $ops['show'] );
$ops_attn     = is_array( $ops['needs_attention_extra'] ?? null ) ? $ops['needs_attention_extra'] : array();
$ops_attn_n   = count( $ops_attn );

$now_url     = \JMReferral\Portal\PortalUrls::management_with_args( array( 'jmrs_mgmt_mode' => 'now' ) );
$reached_url = \JMReferral\Portal\PortalUrls::management_with_args( array( 'jmrs_mgmt_mode' => 'reached' ) );

$funnel_json = wp_json_encode( $funnel );
if ( ! is_string( $funnel_json ) ) {
	$funnel_json = '[]';
}

/**
 * @param array<string, mixed> $row
 */
$jmrs_mgmt_client_cell = static function ( array $row ): void {
	?>
	<td>
		<span class="jmrs-mgmt__ref"><?php echo esc_html( (string) ( $row['referral_number'] ?? '' ) ); ?></span>
		<div class="jmrs-mgmt__role"><?php echo esc_html( (string) ( $row['client_initials'] ?? '—' ) ); ?></div>
	</td>
	<?php
};
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
		<?php if ( $show_ops ) : ?>
			<button type="button" class="jmrs-mgmt__tab" role="tab" id="jmrs-tab-ops" aria-controls="jmrs-view-ops" aria-selected="false"><?php echo esc_html__( 'Operations', 'jm-referral-system' ); ?></button>
		<?php endif; ?>
		<?php if ( $show_homes ) : ?>
			<button type="button" class="jmrs-mgmt__tab" role="tab" id="jmrs-tab-homes" aria-controls="jmrs-view-homes" aria-selected="false"><?php echo esc_html__( 'Homes & capacity', 'jm-referral-system' ); ?></button>
		<?php endif; ?>
		<?php if ( $show_own ) : ?>
			<button type="button" class="jmrs-mgmt__tab" role="tab" id="jmrs-tab-team" aria-controls="jmrs-view-team" aria-selected="false"><?php echo esc_html__( 'Ownership', 'jm-referral-system' ); ?></button>
		<?php endif; ?>
		<button type="button" class="jmrs-mgmt__tab" role="tab" id="jmrs-tab-recs" aria-controls="jmrs-view-recs" aria-selected="false">
			<?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?>
			<?php if ( ( $actions_high + $ops_attn_n ) > 0 ) : ?>
				<span class="jmrs-mgmt__badge"><?php echo esc_html( (string) ( $actions_high + $ops_attn_n ) ); ?></span>
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

				<?php if ( ! empty( $stage['rows_note'] ) ) : ?>
					<p class="jmrs-mgmt__trunc-note" role="status"><?php echo esc_html( (string) $stage['rows_note'] ); ?></p>
				<?php endif; ?>

				<?php if ( [] === $rows ) : ?>
					<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No referrals in this view.', 'jm-referral-system' ); ?></div>
				<?php else : ?>
					<div class="jmrs-mgmt__tbl-scroll">
						<table class="jmrs-mgmt__table">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
									<th><?php echo esc_html__( 'Funding / referrer', 'jm-referral-system' ); ?></th>
									<?php if ( 'now' === $mode ) : ?>
										<th class="jmrs-mgmt__num"><?php echo esc_html__( 'Days in stage', 'jm-referral-system' ); ?></th>
									<?php else : ?>
										<th><?php echo esc_html__( 'First reached', 'jm-referral-system' ); ?></th>
									<?php endif; ?>
									<?php if ( 'la_referrals' === $key ) : ?>
										<th><?php echo esc_html__( 'Received', 'jm-referral-system' ); ?></th>
										<th><?php echo esc_html__( 'Interest response', 'jm-referral-system' ); ?></th>
									<?php elseif ( 'appointment_set' === $key ) : ?>
										<th><?php echo esc_html__( 'Scheduling', 'jm-referral-system' ); ?></th>
										<th><?php echo esc_html__( 'Assessor', 'jm-referral-system' ); ?></th>
									<?php elseif ( 'assessment' === $key ) : ?>
										<th><?php echo esc_html__( 'Assessment', 'jm-referral-system' ); ?></th>
										<th><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></th>
										<th><?php echo esc_html__( 'Assessor', 'jm-referral-system' ); ?></th>
									<?php elseif ( 'package_costing' === $key ) : ?>
										<th class="jmrs-mgmt__num"><?php echo esc_html__( 'Proposed Package Value', 'jm-referral-system' ); ?></th>
										<th><?php echo esc_html__( 'Package Cost', 'jm-referral-system' ); ?></th>
									<?php elseif ( 'authority_consideration' === $key ) : ?>
										<th class="jmrs-mgmt__num"><?php echo esc_html__( 'Proposed Package Value', 'jm-referral-system' ); ?></th>
										<th><?php echo esc_html__( 'Authority status', 'jm-referral-system' ); ?></th>
										<th class="jmrs-mgmt__num"><?php echo esc_html__( 'Days awaiting', 'jm-referral-system' ); ?></th>
									<?php elseif ( 'placement_transition' === $key ) : ?>
										<th><?php echo esc_html__( 'Placement', 'jm-referral-system' ); ?></th>
										<th class="jmrs-mgmt__num"><?php echo esc_html__( 'Proposed Package Value', 'jm-referral-system' ); ?></th>
									<?php endif; ?>
									<th><?php echo esc_html__( 'Owner', 'jm-referral-system' ); ?></th>
									<th><?php echo esc_html__( 'Open', 'jm-referral-system' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rows as $row ) : ?>
									<?php if ( ! is_array( $row ) ) { continue; } ?>
									<tr>
										<?php $jmrs_mgmt_client_cell( $row ); ?>
										<td><?php echo esc_html( (string) ( $row['funding_label'] ?? '—' ) ); ?></td>
										<?php if ( 'now' === $mode ) : ?>
											<td class="jmrs-mgmt__num"><?php echo null !== ( $row['waiting_days'] ?? null ) ? esc_html( (string) (int) $row['waiting_days'] ) : '—'; ?></td>
										<?php else : ?>
											<td><?php echo esc_html( (string) ( $row['first_reached_label'] ?? '—' ) ); ?></td>
										<?php endif; ?>

										<?php if ( 'la_referrals' === $key ) : ?>
											<td>
												<?php echo esc_html( (string) ( $row['received_label'] ?? '—' ) ); ?>
												<?php if ( null !== ( $row['days_since_received'] ?? null ) ) : ?>
													<div class="jmrs-mgmt__role">
														<?php
														printf(
															/* translators: %d: days since referral received */
															esc_html__( '%d days since received', 'jm-referral-system' ),
															(int) $row['days_since_received']
														);
														?>
													</div>
												<?php endif; ?>
											</td>
											<td>
												<?php echo esc_html( (string) ( $row['interest_state'] ?? '—' ) ); ?>
												<div class="jmrs-mgmt__role">
													<?php echo esc_html( (string) ( $row['interest_response_date'] ?? '—' ) ); ?>
													<?php if ( '—' !== (string) ( $row['interest_response_method'] ?? '—' ) ) : ?>
														· <?php echo esc_html( (string) $row['interest_response_method'] ); ?>
													<?php endif; ?>
												</div>
												<?php if ( '—' !== (string) ( $row['interest_response_recipient'] ?? '—' ) ) : ?>
													<div class="jmrs-mgmt__role"><?php echo esc_html( (string) $row['interest_response_recipient'] ); ?></div>
												<?php endif; ?>
											</td>
										<?php elseif ( 'appointment_set' === $key ) : ?>
											<td>
												<span class="jmrs-mgmt__pill jmrs-mgmt__pill--warn"><?php echo esc_html( (string) ( $row['scheduling_status'] ?? __( 'Scheduling required', 'jm-referral-system' ) ) ); ?></span>
												<?php if ( '—' !== (string) ( $row['scheduled_date_label'] ?? '—' ) ) : ?>
													<div class="jmrs-mgmt__role">
														<?php echo esc_html( (string) $row['scheduled_date_label'] ); ?>
														<?php if ( '—' !== (string) ( $row['scheduled_time_label'] ?? '—' ) ) : ?>
															· <?php echo esc_html( (string) $row['scheduled_time_label'] ); ?>
														<?php endif; ?>
													</div>
												<?php endif; ?>
												<?php if ( '' !== (string) ( $row['location_name'] ?? '' ) ) : ?>
													<div class="jmrs-mgmt__role">
														<?php echo esc_html( (string) ( $row['location_type_label'] ?? '' ) ); ?>
														<?php if ( '' !== (string) ( $row['location_type_label'] ?? '' ) ) : ?> · <?php endif; ?>
														<?php echo esc_html( (string) $row['location_name'] ); ?>
													</div>
												<?php endif; ?>
												<?php if ( '' !== (string) ( $row['assessment_contact_name'] ?? '' ) ) : ?>
													<div class="jmrs-mgmt__role"><?php echo esc_html( (string) $row['assessment_contact_name'] ); ?></div>
												<?php endif; ?>
											</td>
											<td class="jmrs-mgmt__who"><?php echo esc_html( (string) ( $row['assessor_name'] ?? '—' ) ); ?></td>
										<?php elseif ( 'assessment' === $key ) : ?>
											<td>
												<?php echo esc_html( (string) ( $row['assessment_date_label'] ?? '—' ) ); ?>
												<?php if ( '—' !== (string) ( $row['scheduled_date_label'] ?? '—' ) ) : ?>
													<div class="jmrs-mgmt__role">
														<?php echo esc_html( (string) $row['scheduled_date_label'] ); ?>
														<?php if ( '—' !== (string) ( $row['scheduled_time_label'] ?? '—' ) ) : ?>
															· <?php echo esc_html( (string) $row['scheduled_time_label'] ); ?>
														<?php endif; ?>
													</div>
												<?php endif; ?>
												<?php if ( '' !== (string) ( $row['location_name'] ?? '' ) ) : ?>
													<div class="jmrs-mgmt__role"><?php echo esc_html( (string) $row['location_name'] ); ?></div>
												<?php endif; ?>
												<div class="jmrs-mgmt__role"><?php echo esc_html( (string) ( $row['assessment_status_label'] ?? '' ) ); ?></div>
											</td>
											<td><?php echo esc_html( (string) ( $row['outcome_label'] ?? '—' ) ); ?></td>
											<td class="jmrs-mgmt__who"><?php echo esc_html( (string) ( $row['assessor_name'] ?? '—' ) ); ?></td>
										<?php elseif ( 'package_costing' === $key ) : ?>
											<td class="jmrs-mgmt__num"><b><?php echo esc_html( (string) ( $row['proposed_value'] ?? '—' ) ); ?></b></td>
											<td>
												<?php echo esc_html( (string) ( $row['package_status_label'] ?? '—' ) ); ?>
												<div class="jmrs-mgmt__role">
													<?php echo esc_html__( 'Prepared', 'jm-referral-system' ); ?>:
													<?php echo esc_html( (string) ( $row['prepared_at_label'] ?? '—' ) ); ?>
													<?php if ( '—' !== (string) ( $row['prepared_by_name'] ?? '—' ) ) : ?>
														· <?php echo esc_html( (string) $row['prepared_by_name'] ); ?>
													<?php endif; ?>
												</div>
												<div class="jmrs-mgmt__role">
													<?php echo esc_html__( 'Sent', 'jm-referral-system' ); ?>:
													<?php echo esc_html( (string) ( $row['sent_at_label'] ?? '—' ) ); ?>
													<?php if ( '—' !== (string) ( $row['send_method_label'] ?? '—' ) ) : ?>
														· <?php echo esc_html( (string) $row['send_method_label'] ); ?>
													<?php endif; ?>
												</div>
												<?php if ( '—' !== (string) ( $row['package_recipient'] ?? '—' ) ) : ?>
													<div class="jmrs-mgmt__role"><?php echo esc_html( (string) $row['package_recipient'] ); ?></div>
												<?php endif; ?>
												<?php if ( '—' !== (string) ( $row['submission_reference'] ?? '—' ) ) : ?>
													<div class="jmrs-mgmt__role"><?php echo esc_html( (string) $row['submission_reference'] ); ?></div>
												<?php endif; ?>
											</td>
										<?php elseif ( 'authority_consideration' === $key ) : ?>
											<td class="jmrs-mgmt__num"><b><?php echo esc_html( (string) ( $row['proposed_value'] ?? '—' ) ); ?></b></td>
											<td>
												<?php echo esc_html( (string) ( $row['authority_status_label'] ?? '—' ) ); ?>
												<div class="jmrs-mgmt__role">
													<?php echo esc_html__( 'Sent', 'jm-referral-system' ); ?>:
													<?php echo esc_html( (string) ( $row['package_sent_label'] ?? '—' ) ); ?>
												</div>
												<?php if ( '—' !== (string) ( $row['package_recipient'] ?? '—' ) ) : ?>
													<div class="jmrs-mgmt__role"><?php echo esc_html( (string) $row['package_recipient'] ); ?></div>
												<?php endif; ?>
												<?php if ( '—' !== (string) ( $row['send_method_label'] ?? '—' ) ) : ?>
													<div class="jmrs-mgmt__role"><?php echo esc_html( (string) $row['send_method_label'] ); ?></div>
												<?php endif; ?>
												<?php if ( '—' !== (string) ( $row['submission_reference'] ?? '—' ) ) : ?>
													<div class="jmrs-mgmt__role"><?php echo esc_html( (string) $row['submission_reference'] ); ?></div>
												<?php endif; ?>
											</td>
											<td class="jmrs-mgmt__num"><?php echo null !== ( $row['days_awaiting_authority'] ?? null ) ? esc_html( (string) (int) $row['days_awaiting_authority'] ) : '—'; ?></td>
										<?php elseif ( 'placement_transition' === $key ) : ?>
											<td>
												<?php echo esc_html( (string) ( $row['home_name'] ?? '—' ) ); ?>
												<?php if ( '—' !== (string) ( $row['bedroom_label'] ?? '—' ) ) : ?>
													<div class="jmrs-mgmt__role"><?php echo esc_html( (string) $row['bedroom_label'] ); ?></div>
												<?php endif; ?>
												<?php if ( '—' !== (string) ( $row['destination_setting'] ?? '—' ) ) : ?>
													<div class="jmrs-mgmt__role"><?php echo esc_html( (string) $row['destination_setting'] ); ?></div>
												<?php endif; ?>
												<div class="jmrs-mgmt__role">
													<?php echo esc_html( (string) ( $row['move_in_label'] ?? '—' ) ); ?>
													<?php if ( '' !== (string) ( $row['move_in_note'] ?? '' ) ) : ?>
														· <?php echo esc_html( (string) $row['move_in_note'] ); ?>
													<?php endif; ?>
												</div>
											</td>
											<td class="jmrs-mgmt__num"><b><?php echo esc_html( (string) ( $row['proposed_value'] ?? '—' ) ); ?></b></td>
										<?php endif; ?>

										<td class="jmrs-mgmt__who"><?php echo esc_html( (string) ( $row['owner_name'] ?? '' ) ); ?></td>
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

	<?php if ( $show_ops ) : ?>
		<?php
		$status_cards    = is_array( $ops['status_cards'] ?? null ) ? $ops['status_cards'] : array();
		$workflow_stages = is_array( $ops['workflow_stages'] ?? null ) ? $ops['workflow_stages'] : array();
		$unassigned      = is_array( $ops['unassigned'] ?? null ) ? $ops['unassigned'] : array();
		$workloads       = is_array( $ops['workloads'] ?? null ) ? $ops['workloads'] : array();
		$wl_owners       = is_array( $workloads['owners'] ?? null ) ? $workloads['owners'] : array();
		$wl_champions    = is_array( $workloads['champions'] ?? null ) ? $workloads['champions'] : array();
		$wl_leads        = is_array( $workloads['transition_leads'] ?? null ) ? $workloads['transition_leads'] : array();
		$upcoming_mtgs   = is_array( $ops['upcoming_meetings'] ?? null ) ? $ops['upcoming_meetings'] : array();
		$past_mtgs       = is_array( $ops['past_meetings'] ?? null ) ? $ops['past_meetings'] : array();
		$recent_refs     = is_array( $ops['recent_referrals'] ?? null ) ? $ops['recent_referrals'] : array();
		$recent_act      = is_array( $ops['recent_activity'] ?? null ) ? $ops['recent_activity'] : array();
		$assess_ops      = is_array( $ops['assessments'] ?? null ) ? $ops['assessments'] : array();
		$assess_upcoming = is_array( $assess_ops['upcoming_list'] ?? null ) ? $assess_ops['upcoming_list'] : array();
		$assess_past     = is_array( $assess_ops['past_list'] ?? null ) ? $assess_ops['past_list'] : array();
		$assess_outcomes = is_array( $assess_ops['outcomes'] ?? null ) ? $assess_ops['outcomes'] : array();
		$pkg_ops         = is_array( $ops['package_costing'] ?? null ) ? $ops['package_costing'] : array();
		$pkg_prepared    = is_array( $pkg_ops['prepared_list'] ?? null ) ? $pkg_ops['prepared_list'] : array();
		$pkg_sent        = is_array( $pkg_ops['sent_list'] ?? null ) ? $pkg_ops['sent_list'] : array();
		$defs            = is_array( $ops['definitions'] ?? null ) ? $ops['definitions'] : array();
		?>
		<section class="jmrs-mgmt__view" id="jmrs-view-ops" role="tabpanel" aria-labelledby="jmrs-tab-ops" hidden>
			<div class="jmrs-mgmt__card-head">
				<h2><?php echo esc_html__( 'Operational overview', 'jm-referral-system' ); ?></h2>
				<span class="jmrs-mgmt__hint">
					<?php echo esc_html( (string) ( $defs['active'] ?? '' ) ); ?>
					<?php if ( ! empty( $defs['upcoming_meetings'] ) ) : ?>
						· <?php echo esc_html( (string) $defs['upcoming_meetings'] ); ?>
					<?php endif; ?>
				</span>
			</div>

			<section class="jmrs-mgmt__figures jmrs-mgmt__figures--ops" aria-label="<?php echo esc_attr__( 'Operational status cards', 'jm-referral-system' ); ?>">
				<?php foreach ( $status_cards as $card ) : ?>
					<?php if ( ! is_array( $card ) ) { continue; } ?>
					<div class="jmrs-mgmt__fig">
						<div class="jmrs-mgmt__fig-lab"><?php echo esc_html( (string) ( $card['label'] ?? '' ) ); ?></div>
						<div class="jmrs-mgmt__fig-val"><?php echo esc_html( (string) ( $card['value'] ?? '0' ) ); ?></div>
						<div class="jmrs-mgmt__fig-note"><?php echo esc_html( (string) ( $card['note'] ?? '' ) ); ?></div>
					</div>
				<?php endforeach; ?>
			</section>

			<article class="jmrs-mgmt__panel jmrs-mgmt__ops-panel" style="border-left-color:#2B4C7E">
				<div class="jmrs-mgmt__panel-head">
					<div>
						<div class="jmrs-mgmt__panel-title"><h3><?php echo esc_html__( 'Workflow stage distribution', 'jm-referral-system' ); ?></h3></div>
						<p class="jmrs-mgmt__panel-q"><?php echo esc_html__( 'Visible non-archived referrals by current workflow stage.', 'jm-referral-system' ); ?></p>
					</div>
				</div>
				<?php if ( [] === $workflow_stages ) : ?>
					<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No referrals in this workflow stage.', 'jm-referral-system' ); ?></div>
				<?php else : ?>
					<ul class="jmrs-mgmt__stage-bars" role="list">
						<?php foreach ( $workflow_stages as $stage_row ) : ?>
							<?php
							if ( ! is_array( $stage_row ) ) {
								continue;
							}
							$scount = absint( $stage_row['count'] ?? 0 );
							$spct   = absint( $stage_row['pct'] ?? 0 );
							$sname  = (string) ( $stage_row['name'] ?? '' );
							?>
							<li class="jmrs-mgmt__stage-bar">
								<div class="jmrs-mgmt__stage-bar-meta">
									<span class="jmrs-mgmt__stage-bar-name"><?php echo esc_html( $sname ); ?></span>
									<span class="jmrs-mgmt__stage-bar-count">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: referral count */
												_n( '%d referral', '%d referrals', $scount, 'jm-referral-system' ),
												$scount
											)
										);
										?>
									</span>
								</div>
								<div class="jmrs-mgmt__stage-bar-track" role="presentation">
									<span class="jmrs-mgmt__stage-bar-fill" style="width:<?php echo esc_attr( (string) $spct ); ?>%"></span>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</article>

			<article class="jmrs-mgmt__panel jmrs-mgmt__ops-panel" style="border-left-color:#B4791A">
				<div class="jmrs-mgmt__panel-head">
					<div>
						<div class="jmrs-mgmt__panel-title"><h3><?php echo esc_html__( 'Unassigned responsibilities', 'jm-referral-system' ); ?></h3></div>
						<p class="jmrs-mgmt__panel-q"><?php echo esc_html__( 'Active non-archived referrals missing a responsibility role. Not labelled overdue.', 'jm-referral-system' ); ?></p>
					</div>
				</div>
				<div class="jmrs-mgmt__unassigned-grid" role="list">
					<div class="jmrs-mgmt__unassigned" role="listitem">
						<div class="jmrs-mgmt__fig-lab"><?php echo esc_html__( 'No Referral owner', 'jm-referral-system' ); ?></div>
						<div class="jmrs-mgmt__fig-val"><?php echo esc_html( (string) absint( $unassigned['owner'] ?? 0 ) ); ?></div>
					</div>
					<div class="jmrs-mgmt__unassigned" role="listitem">
						<div class="jmrs-mgmt__fig-lab"><?php echo esc_html__( 'No Champion', 'jm-referral-system' ); ?></div>
						<div class="jmrs-mgmt__fig-val"><?php echo esc_html( (string) absint( $unassigned['champion'] ?? 0 ) ); ?></div>
					</div>
					<div class="jmrs-mgmt__unassigned" role="listitem">
						<div class="jmrs-mgmt__fig-lab"><?php echo esc_html__( 'No Transition lead', 'jm-referral-system' ); ?></div>
						<div class="jmrs-mgmt__fig-val"><?php echo esc_html( (string) absint( $unassigned['transition_lead'] ?? 0 ) ); ?></div>
					</div>
				</div>
			</article>

			<div class="jmrs-mgmt__ops-grid">
				<article class="jmrs-mgmt__panel jmrs-mgmt__ops-panel" style="border-left-color:#3F7D3A">
					<div class="jmrs-mgmt__panel-head">
						<div class="jmrs-mgmt__panel-title"><h3><?php echo esc_html__( 'Workload — Referral owners', 'jm-referral-system' ); ?></h3></div>
					</div>
					<?php if ( [] === $wl_owners ) : ?>
						<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No active referrals in scope.', 'jm-referral-system' ); ?></div>
					<?php else : ?>
						<div class="jmrs-mgmt__tbl-scroll">
							<table class="jmrs-mgmt__table">
								<thead>
									<tr>
										<th scope="col"><?php echo esc_html__( 'Staff member', 'jm-referral-system' ); ?></th>
										<th scope="col" class="jmrs-mgmt__num"><?php echo esc_html__( 'Referrals', 'jm-referral-system' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $wl_owners as $row ) : ?>
										<?php if ( ! is_array( $row ) ) { continue; } ?>
										<tr>
											<td class="jmrs-mgmt__who"><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></td>
											<td class="jmrs-mgmt__num"><?php echo esc_html( (string) absint( $row['count'] ?? 0 ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</article>

				<article class="jmrs-mgmt__panel jmrs-mgmt__ops-panel" style="border-left-color:#3F7D3A">
					<div class="jmrs-mgmt__panel-head">
						<div class="jmrs-mgmt__panel-title"><h3><?php echo esc_html__( 'Workload — Champions', 'jm-referral-system' ); ?></h3></div>
					</div>
					<?php if ( [] === $wl_champions ) : ?>
						<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No active referrals in scope.', 'jm-referral-system' ); ?></div>
					<?php else : ?>
						<div class="jmrs-mgmt__tbl-scroll">
							<table class="jmrs-mgmt__table">
								<thead>
									<tr>
										<th scope="col"><?php echo esc_html__( 'Staff member', 'jm-referral-system' ); ?></th>
										<th scope="col" class="jmrs-mgmt__num"><?php echo esc_html__( 'Referrals', 'jm-referral-system' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $wl_champions as $row ) : ?>
										<?php if ( ! is_array( $row ) ) { continue; } ?>
										<tr>
											<td class="jmrs-mgmt__who"><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></td>
											<td class="jmrs-mgmt__num"><?php echo esc_html( (string) absint( $row['count'] ?? 0 ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</article>

				<article class="jmrs-mgmt__panel jmrs-mgmt__ops-panel" style="border-left-color:#3F7D3A">
					<div class="jmrs-mgmt__panel-head">
						<div class="jmrs-mgmt__panel-title"><h3><?php echo esc_html__( 'Workload — Transition leads', 'jm-referral-system' ); ?></h3></div>
					</div>
					<?php if ( [] === $wl_leads ) : ?>
						<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No active referrals in scope.', 'jm-referral-system' ); ?></div>
					<?php else : ?>
						<div class="jmrs-mgmt__tbl-scroll">
							<table class="jmrs-mgmt__table">
								<thead>
									<tr>
										<th scope="col"><?php echo esc_html__( 'Staff member', 'jm-referral-system' ); ?></th>
										<th scope="col" class="jmrs-mgmt__num"><?php echo esc_html__( 'Referrals', 'jm-referral-system' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $wl_leads as $row ) : ?>
										<?php if ( ! is_array( $row ) ) { continue; } ?>
										<tr>
											<td class="jmrs-mgmt__who"><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></td>
											<td class="jmrs-mgmt__num"><?php echo esc_html( (string) absint( $row['count'] ?? 0 ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</article>
			</div>

			<article class="jmrs-mgmt__panel jmrs-mgmt__ops-panel" style="border-left-color:#2B4C7E">
				<div class="jmrs-mgmt__panel-head">
					<div>
						<div class="jmrs-mgmt__panel-title"><h3><?php echo esc_html__( 'Upcoming meetings', 'jm-referral-system' ); ?></h3></div>
						<p class="jmrs-mgmt__panel-q"><?php echo esc_html( (string) ( $defs['upcoming_meetings'] ?? '' ) ); ?></p>
					</div>
				</div>
				<?php if ( [] === $upcoming_mtgs ) : ?>
					<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No upcoming meetings.', 'jm-referral-system' ); ?></div>
				<?php else : ?>
					<div class="jmrs-mgmt__tbl-scroll">
						<table class="jmrs-mgmt__table">
							<thead>
								<tr>
									<th scope="col"><?php echo esc_html__( 'When', 'jm-referral-system' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Type', 'jm-referral-system' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Open', 'jm-referral-system' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $upcoming_mtgs as $mtg ) : ?>
									<?php if ( ! is_array( $mtg ) ) { continue; } ?>
									<tr>
										<td><?php echo esc_html( (string) ( $mtg['scheduled_label'] ?? '—' ) ); ?></td>
										<td><?php echo esc_html( (string) ( $mtg['meeting_type'] ?? '' ) ); ?></td>
										<td><span class="jmrs-mgmt__ref"><?php echo esc_html( (string) ( $mtg['referral_number'] ?? '' ) ); ?></span></td>
										<td><?php echo esc_html( (string) ( $mtg['status_label'] ?? '' ) ); ?></td>
										<td>
											<?php if ( '' !== (string) ( $mtg['detail_url'] ?? '' ) ) : ?>
												<a class="jmrs-mgmt__link" href="<?php echo esc_url( (string) $mtg['detail_url'] ); ?>"><?php echo esc_html__( 'View meeting', 'jm-referral-system' ); ?></a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</article>

			<article class="jmrs-mgmt__panel jmrs-mgmt__ops-panel" style="border-left-color:#B03030">
				<div class="jmrs-mgmt__panel-head">
					<div>
						<div class="jmrs-mgmt__panel-title"><h3><?php echo esc_html__( 'Past scheduled meetings', 'jm-referral-system' ); ?></h3></div>
						<p class="jmrs-mgmt__panel-q"><?php echo esc_html( (string) ( $defs['past_meetings'] ?? '' ) ); ?></p>
					</div>
				</div>
				<?php if ( [] === $past_mtgs ) : ?>
					<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No past scheduled meetings.', 'jm-referral-system' ); ?></div>
				<?php else : ?>
					<div class="jmrs-mgmt__tbl-scroll">
						<table class="jmrs-mgmt__table">
							<thead>
								<tr>
									<th scope="col"><?php echo esc_html__( 'Scheduled', 'jm-referral-system' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Type', 'jm-referral-system' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Open', 'jm-referral-system' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $past_mtgs as $mtg ) : ?>
									<?php if ( ! is_array( $mtg ) ) { continue; } ?>
									<tr>
										<td><?php echo esc_html( (string) ( $mtg['scheduled_label'] ?? '—' ) ); ?></td>
										<td><?php echo esc_html( (string) ( $mtg['meeting_type'] ?? '' ) ); ?></td>
										<td><span class="jmrs-mgmt__ref"><?php echo esc_html( (string) ( $mtg['referral_number'] ?? '' ) ); ?></span></td>
										<td>
											<?php if ( '' !== (string) ( $mtg['detail_url'] ?? '' ) ) : ?>
												<a class="jmrs-mgmt__link" href="<?php echo esc_url( (string) $mtg['detail_url'] ); ?>"><?php echo esc_html__( 'View meeting', 'jm-referral-system' ); ?></a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</article>

			<article class="jmrs-mgmt__panel jmrs-mgmt__ops-panel" style="border-left-color:#3F7D3A">
				<div class="jmrs-mgmt__panel-head">
					<div>
						<div class="jmrs-mgmt__panel-title"><h3><?php echo esc_html__( 'Assessments', 'jm-referral-system' ); ?></h3></div>
						<p class="jmrs-mgmt__panel-q"><?php echo esc_html( (string) ( $defs['assessment'] ?? '' ) ); ?></p>
					</div>
				</div>
				<div class="jmrs-mgmt__unassigned-grid" role="list">
					<div class="jmrs-mgmt__unassigned" role="listitem">
						<div class="jmrs-mgmt__fig-lab"><?php echo esc_html__( 'Scheduled assessments', 'jm-referral-system' ); ?></div>
						<div class="jmrs-mgmt__fig-val"><?php echo esc_html( (string) absint( $assess_ops['scheduled_count'] ?? 0 ) ); ?></div>
						<div class="jmrs-mgmt__fig-note"><?php echo esc_html( (string) ( $defs['scheduled_assessments'] ?? '' ) ); ?></div>
					</div>
					<div class="jmrs-mgmt__unassigned" role="listitem">
						<div class="jmrs-mgmt__fig-lab"><?php echo esc_html__( 'Past scheduled assessments', 'jm-referral-system' ); ?></div>
						<div class="jmrs-mgmt__fig-val"><?php echo esc_html( (string) absint( $assess_ops['past_count'] ?? 0 ) ); ?></div>
						<div class="jmrs-mgmt__fig-note"><?php echo esc_html( (string) ( $defs['past_assessments'] ?? '' ) ); ?></div>
					</div>
					<div class="jmrs-mgmt__unassigned" role="listitem">
						<div class="jmrs-mgmt__fig-lab"><?php echo esc_html__( 'Completed assessments', 'jm-referral-system' ); ?></div>
						<div class="jmrs-mgmt__fig-val"><?php echo esc_html( (string) absint( $assess_ops['completed_count'] ?? 0 ) ); ?></div>
						<div class="jmrs-mgmt__fig-note"><?php echo esc_html( (string) ( $defs['completed_assessments'] ?? '' ) ); ?></div>
					</div>
				</div>
				<?php if ( [] !== $assess_outcomes ) : ?>
					<h4 class="jmrs-mgmt__subhead"><?php echo esc_html__( 'Outcome distribution', 'jm-referral-system' ); ?></h4>
					<ul class="jmrs-mgmt__outcome-list" role="list">
						<?php foreach ( $assess_outcomes as $oc ) : ?>
							<?php if ( ! is_array( $oc ) ) { continue; } ?>
							<li>
								<span><?php echo esc_html( (string) ( $oc['label'] ?? '' ) ); ?></span>
								<span class="jmrs-mgmt__num"><?php echo esc_html( (string) absint( $oc['count'] ?? 0 ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<div class="jmrs-mgmt__ops-grid jmrs-mgmt__ops-grid--2" style="margin-top:16px;margin-bottom:0;">
					<div>
						<h4 class="jmrs-mgmt__subhead"><?php echo esc_html__( 'Upcoming scheduled assessments', 'jm-referral-system' ); ?></h4>
						<?php if ( [] === $assess_upcoming ) : ?>
							<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No upcoming scheduled assessments.', 'jm-referral-system' ); ?></div>
						<?php else : ?>
							<div class="jmrs-mgmt__tbl-scroll">
								<table class="jmrs-mgmt__table">
									<thead>
										<tr>
											<th scope="col"><?php echo esc_html__( 'When', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Assessor', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Open', 'jm-referral-system' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $assess_upcoming as $row ) : ?>
											<?php if ( ! is_array( $row ) ) { continue; } ?>
											<tr>
												<td><?php echo esc_html( (string) ( $row['scheduled_label'] ?? '—' ) ); ?></td>
												<td><span class="jmrs-mgmt__ref"><?php echo esc_html( (string) ( $row['referral_number'] ?? '' ) ); ?></span></td>
												<td class="jmrs-mgmt__who"><?php echo esc_html( (string) ( $row['assessor_name'] ?? '' ) ); ?></td>
												<td>
													<?php if ( '' !== (string) ( $row['url'] ?? '' ) ) : ?>
														<a class="jmrs-mgmt__link" href="<?php echo esc_url( (string) $row['url'] ); ?>"><?php echo esc_html__( 'View', 'jm-referral-system' ); ?></a>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>
					<div>
						<h4 class="jmrs-mgmt__subhead"><?php echo esc_html__( 'Past scheduled assessments', 'jm-referral-system' ); ?></h4>
						<?php if ( [] === $assess_past ) : ?>
							<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No past scheduled assessments.', 'jm-referral-system' ); ?></div>
						<?php else : ?>
							<div class="jmrs-mgmt__tbl-scroll">
								<table class="jmrs-mgmt__table">
									<thead>
										<tr>
											<th scope="col"><?php echo esc_html__( 'Scheduled', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Assessor', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Open', 'jm-referral-system' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $assess_past as $row ) : ?>
											<?php if ( ! is_array( $row ) ) { continue; } ?>
											<tr>
												<td><?php echo esc_html( (string) ( $row['scheduled_label'] ?? '—' ) ); ?></td>
												<td><span class="jmrs-mgmt__ref"><?php echo esc_html( (string) ( $row['referral_number'] ?? '' ) ); ?></span></td>
												<td class="jmrs-mgmt__who"><?php echo esc_html( (string) ( $row['assessor_name'] ?? '' ) ); ?></td>
												<td>
													<?php if ( '' !== (string) ( $row['url'] ?? '' ) ) : ?>
														<a class="jmrs-mgmt__link" href="<?php echo esc_url( (string) $row['url'] ); ?>"><?php echo esc_html__( 'View', 'jm-referral-system' ); ?></a>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</article>

			<article class="jmrs-mgmt__panel jmrs-mgmt__ops-panel" style="border-left-color:#1F5A8A">
				<div class="jmrs-mgmt__panel-head">
					<div>
						<div class="jmrs-mgmt__panel-title"><h3><?php echo esc_html__( 'Package Costing', 'jm-referral-system' ); ?></h3></div>
						<p class="jmrs-mgmt__panel-q"><?php echo esc_html( (string) ( $defs['package_costing'] ?? '' ) ); ?></p>
					</div>
				</div>
				<div class="jmrs-mgmt__unassigned-grid" role="list">
					<div class="jmrs-mgmt__unassigned" role="listitem">
						<div class="jmrs-mgmt__fig-lab"><?php echo esc_html__( 'Package cost required', 'jm-referral-system' ); ?></div>
						<div class="jmrs-mgmt__fig-val"><?php echo esc_html( (string) absint( $pkg_ops['required_count'] ?? 0 ) ); ?></div>
						<div class="jmrs-mgmt__fig-note"><?php echo esc_html( (string) ( $defs['package_cost_required'] ?? '' ) ); ?></div>
					</div>
					<div class="jmrs-mgmt__unassigned" role="listitem">
						<div class="jmrs-mgmt__fig-lab"><?php echo esc_html__( 'Prepared packages', 'jm-referral-system' ); ?></div>
						<div class="jmrs-mgmt__fig-val"><?php echo esc_html( (string) absint( $pkg_ops['prepared_count'] ?? 0 ) ); ?></div>
						<div class="jmrs-mgmt__fig-note"><?php echo esc_html( (string) ( $defs['prepared_packages'] ?? '' ) ); ?></div>
					</div>
					<div class="jmrs-mgmt__unassigned" role="listitem">
						<div class="jmrs-mgmt__fig-lab"><?php echo esc_html__( 'Sent packages', 'jm-referral-system' ); ?></div>
						<div class="jmrs-mgmt__fig-val"><?php echo esc_html( (string) absint( $pkg_ops['sent_count'] ?? 0 ) ); ?></div>
						<div class="jmrs-mgmt__fig-note"><?php echo esc_html( (string) ( $defs['sent_packages'] ?? '' ) ); ?></div>
					</div>
					<div class="jmrs-mgmt__unassigned" role="listitem">
						<div class="jmrs-mgmt__fig-lab"><?php echo esc_html__( 'Awaiting LA decision', 'jm-referral-system' ); ?></div>
						<div class="jmrs-mgmt__fig-val"><?php echo esc_html( (string) absint( $pkg_ops['awaiting_la_count'] ?? 0 ) ); ?></div>
						<div class="jmrs-mgmt__fig-note"><?php echo esc_html( (string) ( $defs['awaiting_la_decision'] ?? '' ) ); ?></div>
					</div>
				</div>

				<div class="jmrs-mgmt__ops-grid jmrs-mgmt__ops-grid--2" style="margin-top:16px;margin-bottom:0;">
					<div>
						<h4 class="jmrs-mgmt__subhead"><?php echo esc_html__( 'Prepared packages', 'jm-referral-system' ); ?></h4>
						<?php if ( [] === $pkg_prepared ) : ?>
							<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No prepared packages.', 'jm-referral-system' ); ?></div>
						<?php else : ?>
							<div class="jmrs-mgmt__tbl-scroll">
								<table class="jmrs-mgmt__table">
									<thead>
										<tr>
											<th scope="col"><?php echo esc_html__( 'Prepared', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Open', 'jm-referral-system' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $pkg_prepared as $row ) : ?>
											<?php if ( ! is_array( $row ) ) { continue; } ?>
											<tr>
												<td><?php echo esc_html( (string) ( $row['when_label'] ?? '—' ) ); ?></td>
												<td><span class="jmrs-mgmt__ref"><?php echo esc_html( (string) ( $row['referral_number'] ?? '' ) ); ?></span></td>
												<td><?php echo esc_html( (string) ( $row['status_label'] ?? '' ) ); ?></td>
												<td>
													<?php if ( '' !== (string) ( $row['url'] ?? '' ) ) : ?>
														<a class="jmrs-mgmt__link" href="<?php echo esc_url( (string) $row['url'] ); ?>"><?php echo esc_html__( 'View', 'jm-referral-system' ); ?></a>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>
					<div>
						<h4 class="jmrs-mgmt__subhead"><?php echo esc_html__( 'Sent packages', 'jm-referral-system' ); ?></h4>
						<?php if ( [] === $pkg_sent ) : ?>
							<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No sent packages.', 'jm-referral-system' ); ?></div>
						<?php else : ?>
							<div class="jmrs-mgmt__tbl-scroll">
								<table class="jmrs-mgmt__table">
									<thead>
										<tr>
											<th scope="col"><?php echo esc_html__( 'Sent', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
											<th scope="col"><?php echo esc_html__( 'Open', 'jm-referral-system' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $pkg_sent as $row ) : ?>
											<?php if ( ! is_array( $row ) ) { continue; } ?>
											<tr>
												<td><?php echo esc_html( (string) ( $row['when_label'] ?? '—' ) ); ?></td>
												<td><span class="jmrs-mgmt__ref"><?php echo esc_html( (string) ( $row['referral_number'] ?? '' ) ); ?></span></td>
												<td><?php echo esc_html( (string) ( $row['status_label'] ?? '' ) ); ?></td>
												<td>
													<?php if ( '' !== (string) ( $row['url'] ?? '' ) ) : ?>
														<a class="jmrs-mgmt__link" href="<?php echo esc_url( (string) $row['url'] ); ?>"><?php echo esc_html__( 'View', 'jm-referral-system' ); ?></a>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</article>

			<div class="jmrs-mgmt__ops-grid jmrs-mgmt__ops-grid--2">
				<article class="jmrs-mgmt__panel jmrs-mgmt__ops-panel" style="border-left-color:#5B6B7B">
					<div class="jmrs-mgmt__panel-head">
						<div class="jmrs-mgmt__panel-title"><h3><?php echo esc_html__( 'Recent referrals', 'jm-referral-system' ); ?></h3></div>
					</div>
					<?php if ( [] === $recent_refs ) : ?>
						<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No recent referrals.', 'jm-referral-system' ); ?></div>
					<?php else : ?>
						<div class="jmrs-mgmt__tbl-scroll">
							<table class="jmrs-mgmt__table">
								<thead>
									<tr>
										<th scope="col"><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
										<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
										<th scope="col"><?php echo esc_html__( 'Created', 'jm-referral-system' ); ?></th>
										<th scope="col"><?php echo esc_html__( 'Open', 'jm-referral-system' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $recent_refs as $ref ) : ?>
										<?php if ( ! is_array( $ref ) ) { continue; } ?>
										<tr>
											<td><span class="jmrs-mgmt__ref"><?php echo esc_html( (string) ( $ref['referral_number'] ?? '' ) ); ?></span></td>
											<td><?php echo esc_html( (string) ( $ref['status'] ?? '' ) ); ?></td>
											<td><?php echo esc_html( (string) ( $ref['created_label'] ?? '—' ) ); ?></td>
											<td>
												<?php if ( '' !== (string) ( $ref['url'] ?? '' ) ) : ?>
													<a class="jmrs-mgmt__link" href="<?php echo esc_url( (string) $ref['url'] ); ?>"><?php echo esc_html__( 'View', 'jm-referral-system' ); ?></a>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</article>

				<article class="jmrs-mgmt__panel jmrs-mgmt__ops-panel" style="border-left-color:#5B6B7B">
					<div class="jmrs-mgmt__panel-head">
						<div class="jmrs-mgmt__panel-title"><h3><?php echo esc_html__( 'Recent activity', 'jm-referral-system' ); ?></h3></div>
					</div>
					<?php if ( [] === $recent_act ) : ?>
						<div class="jmrs-mgmt__empty"><?php echo esc_html__( 'No recent activity available.', 'jm-referral-system' ); ?></div>
					<?php else : ?>
						<ul class="jmrs-mgmt__activity" role="list">
							<?php foreach ( $recent_act as $act ) : ?>
								<?php if ( ! is_array( $act ) ) { continue; } ?>
								<li class="jmrs-mgmt__activity-item">
									<div class="jmrs-mgmt__activity-main">
										<?php if ( '' !== (string) ( $act['url'] ?? '' ) ) : ?>
											<a class="jmrs-mgmt__link jmrs-mgmt__ref" href="<?php echo esc_url( (string) $act['url'] ); ?>"><?php echo esc_html( (string) ( $act['referral_number'] ?? '' ) ); ?></a>
										<?php else : ?>
											<span class="jmrs-mgmt__ref"><?php echo esc_html( (string) ( $act['referral_number'] ?? '' ) ); ?></span>
										<?php endif; ?>
										<span class="jmrs-mgmt__activity-desc"><?php echo esc_html( (string) ( $act['description'] ?? '' ) ); ?></span>
									</div>
									<div class="jmrs-mgmt__activity-meta">
										<?php if ( '' !== (string) ( $act['actor'] ?? '' ) ) : ?>
											<span><?php echo esc_html( (string) $act['actor'] ); ?></span>
											<span aria-hidden="true"> · </span>
										<?php endif; ?>
										<span><?php echo esc_html( (string) ( $act['when_label'] ?? '' ) ); ?></span>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</article>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $show_homes ) : ?>
		<section class="jmrs-mgmt__view" id="jmrs-view-homes" role="tabpanel" aria-labelledby="jmrs-tab-homes" hidden>
			<div class="jmrs-mgmt__card-head">
				<h2><?php echo esc_html__( 'Homes and capacity', 'jm-referral-system' ); ?></h2>
				<span class="jmrs-mgmt__hint">
					<?php
					if ( is_array( $estate ) ) {
						printf(
							/* translators: 1: occupied now 2: capacity 3: pct today 4: future move-ins 5: projected 6: projected pct */
							esc_html__( 'Estate today: %1$d of %2$d occupied (%3$s%%). Confirmed future move-ins: %4$d. Projected: %5$d (%6$s%%).', 'jm-referral-system' ),
							absint( $estate['occupied_now'] ?? $estate['occupied'] ?? 0 ),
							absint( $estate['capacity'] ?? 0 ),
							esc_html( (string) ( $estate['occupancy_pct'] ?? 0 ) ),
							absint( $estate['future_move_ins'] ?? 0 ),
							absint( $estate['projected'] ?? 0 ),
							esc_html( (string) ( $estate['projected_pct'] ?? 0 ) )
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
							$occ = absint( $home['occupied_now'] ?? $home['occupied'] ?? 0 );
							for ( $i = 0; $i < $cap; $i++ ) {
								$class = $i < $occ ? ' is-filled' : '';
								echo '<span class="jmrs-mgmt__bed' . esc_attr( $class ) . '"></span>';
							}
							?>
						</div>
						<div class="jmrs-mgmt__home-row"><span><?php echo esc_html__( 'Capacity', 'jm-referral-system' ); ?></span><span><?php echo esc_html( (string) absint( $home['capacity'] ?? 0 ) ); ?></span></div>
						<div class="jmrs-mgmt__home-row"><span><?php echo esc_html__( 'Occupied now', 'jm-referral-system' ); ?></span><span><?php echo esc_html( (string) absint( $home['occupied_now'] ?? $home['occupied'] ?? 0 ) ); ?></span></div>
						<div class="jmrs-mgmt__home-row"><span><?php echo esc_html__( 'Vacancies today', 'jm-referral-system' ); ?></span><span><?php echo esc_html( (string) absint( $home['vacancies_today'] ?? $home['vacant'] ?? 0 ) ); ?></span></div>
						<div class="jmrs-mgmt__home-row"><span><?php echo esc_html__( 'Confirmed future move-ins', 'jm-referral-system' ); ?></span><span><?php echo esc_html( (string) absint( $home['future_move_ins'] ?? 0 ) ); ?></span></div>
						<div class="jmrs-mgmt__home-row"><span><?php echo esc_html__( 'Projected occupancy', 'jm-referral-system' ); ?></span><span><?php echo esc_html( (string) absint( $home['projected'] ?? 0 ) ); ?></span></div>
						<div class="jmrs-mgmt__home-row"><span><?php echo esc_html__( 'Occupancy today', 'jm-referral-system' ); ?></span><span><?php echo esc_html( (string) ( $home['occupancy_pct'] ?? 0 ) ); ?>%</span></div>
						<div class="jmrs-mgmt__home-row"><span><?php echo esc_html__( 'Projected %', 'jm-referral-system' ); ?></span><span><?php echo esc_html( (string) ( $home['projected_pct'] ?? 0 ) ); ?>%</span></div>
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
			<h2><?php echo esc_html__( 'Needs attention', 'jm-referral-system' ); ?></h2>
			<span class="jmrs-mgmt__hint"><?php echo esc_html__( 'Objective items from stored pipeline and meeting data only.', 'jm-referral-system' ); ?></span>
		</div>
		<?php if ( [] === $actions && [] === $ops_attn ) : ?>
			<div class="jmrs-mgmt__rec jmrs-mgmt__rec--low">
				<div class="jmrs-mgmt__rec-head">
					<h4><?php echo esc_html__( 'Nothing needs attention', 'jm-referral-system' ); ?></h4>
					<span class="jmrs-mgmt__pill jmrs-mgmt__pill--ok"><?php echo esc_html__( 'OK', 'jm-referral-system' ); ?></span>
				</div>
				<p><?php echo esc_html__( 'No Needs Attention items for the current pipeline filters.', 'jm-referral-system' ); ?></p>
			</div>
		<?php else : ?>
			<?php if ( [] !== $ops_attn ) : ?>
				<div class="jmrs-mgmt__card-head">
					<h3><?php echo esc_html__( 'Past scheduled meetings', 'jm-referral-system' ); ?></h3>
				</div>
				<?php foreach ( $ops_attn as $attn ) : ?>
					<?php if ( ! is_array( $attn ) ) { continue; } ?>
					<?php
					$attn_url = (string) ( $attn['url'] ?? '' );
					$is_meeting_attn = '' !== $attn_url;
					?>
					<div class="jmrs-mgmt__rec jmrs-mgmt__rec--med">
						<div class="jmrs-mgmt__rec-head">
							<h4><?php echo esc_html( (string) ( $attn['title'] ?? '' ) ); ?></h4>
							<span class="jmrs-mgmt__pill jmrs-mgmt__pill--warn">
								<?php echo esc_html( $is_meeting_attn ? __( 'Scheduled', 'jm-referral-system' ) : __( 'Unassigned', 'jm-referral-system' ) ); ?>
							</span>
						</div>
						<?php if ( '' !== (string) ( $attn['detail'] ?? '' ) ) : ?>
							<p><?php echo esc_html( (string) $attn['detail'] ); ?></p>
						<?php endif; ?>
						<?php if ( $is_meeting_attn ) : ?>
							<p><a class="jmrs-mgmt__link" href="<?php echo esc_url( $attn_url ); ?>"><?php echo esc_html__( 'Open meeting', 'jm-referral-system' ); ?></a></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
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
							<?php echo esc_html( (string) ( $action['client_initials'] ?? '—' ) ); ?>
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
