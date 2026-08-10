<?php
/**
 * Referral Pipeline overview + Needs Attention (portal + admin).
 *
 * @package JMReferral
 *
 * @var array $pipeline_dashboard
 * @var string $context portal|admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pipeline_dashboard = is_array( $pipeline_dashboard ?? null ) ? $pipeline_dashboard : array();
$context            = isset( $context ) ? (string) $context : 'portal';
$is_portal          = 'portal' === $context;

if ( empty( $pipeline_dashboard['show'] ) ) {
	return;
}

$stage_cards     = is_array( $pipeline_dashboard['stage_cards'] ?? null ) ? $pipeline_dashboard['stage_cards'] : array();
$outcome_cards   = is_array( $pipeline_dashboard['outcome_cards'] ?? null ) ? $pipeline_dashboard['outcome_cards'] : array();
$action_queue    = is_array( $pipeline_dashboard['action_queue'] ?? null ) ? $pipeline_dashboard['action_queue'] : array();
$needs_attention = is_array( $pipeline_dashboard['needs_attention'] ?? null ) ? $pipeline_dashboard['needs_attention'] : array();
$filters         = is_array( $pipeline_dashboard['filters'] ?? null ) ? $pipeline_dashboard['filters'] : array();
$filter_urls     = is_array( $pipeline_dashboard['filter_urls'] ?? null ) ? $pipeline_dashboard['filter_urls'] : array();
$stage_options   = is_array( $pipeline_dashboard['stage_filter_options'] ?? null ) ? $pipeline_dashboard['stage_filter_options'] : array();
$priority_options = is_array( $pipeline_dashboard['priority_options'] ?? null ) ? $pipeline_dashboard['priority_options'] : array();
$legacy_count    = absint( $pipeline_dashboard['legacy_count'] ?? 0 );
$legacy_url      = (string) ( $pipeline_dashboard['legacy_list_url'] ?? '' );
$sort_note       = (string) ( $pipeline_dashboard['sort_note'] ?? '' );
$attention_total = absint( $pipeline_dashboard['needs_attention_total'] ?? count( $needs_attention ) );

$form_action = $is_portal
	? (string) ( $filter_urls['all'] ?? '' )
	: admin_url( 'admin.php?page=jm-referrals' );
?>
<section class="<?php echo $is_portal ? 'jmrs-portal-section jmrs-portal-panel' : 'jmrs-pipeline-dashboard'; ?>" aria-labelledby="jmrs-referral-pipeline-heading">
	<h2 id="jmrs-referral-pipeline-heading" class="<?php echo $is_portal ? 'jmrs-portal-section__title' : 'jmrs-dashboard-section-title'; ?>">
		<?php echo esc_html__( 'Referral Pipeline', 'jm-referral-system' ); ?>
	</h2>

	<div class="<?php echo $is_portal ? 'jmrs-portal-kpi-grid' : 'jmrs-stats'; ?>">
		<?php foreach ( $stage_cards as $card ) : ?>
			<?php
			$count           = absint( $card['count'] ?? 0 );
			$attention_count = absint( $card['attention_count'] ?? 0 );
			$label           = (string) ( $card['label'] ?? '' );
			$list_url        = (string) ( $card['list_url'] ?? '' );
			?>
			<?php if ( $is_portal ) : ?>
				<?php
				$kpi_value = (string) $count;
				$kpi_label = $label . ( $attention_count > 0
					? ' — ' . sprintf(
						/* translators: %d: count */
						_n( '%d needs attention', '%d need attention', $attention_count, 'jm-referral-system' ),
						$attention_count
					)
					: '' );
				$kpi_href  = $list_url;
				$kpi_tone  = $attention_count > 0 ? 'warning' : 'default';
				include JMRS_PLUGIN_PATH . 'templates/portal/partials/kpi-card.php';
				?>
			<?php else : ?>
				<div class="jmrs-stat">
					<span class="jmrs-stat-number">
						<?php if ( '' !== $list_url ) : ?>
							<a href="<?php echo esc_url( $list_url ); ?>"><?php echo esc_html( (string) $count ); ?></a>
						<?php else : ?>
							<?php echo esc_html( (string) $count ); ?>
						<?php endif; ?>
					</span>
					<span class="jmrs-stat-label"><?php echo esc_html( $label ); ?></span>
					<?php if ( $attention_count > 0 ) : ?>
						<span class="description"><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d needs attention', '%d need attention', $attention_count, 'jm-referral-system' ), $attention_count ) ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<?php if ( ! empty( $outcome_cards ) ) : ?>
		<h3 class="<?php echo $is_portal ? 'jmrs-portal-section__subtitle' : 'jmrs-dashboard-section-title'; ?>">
			<?php echo esc_html__( 'Acquisition Outcomes', 'jm-referral-system' ); ?>
		</h3>
		<div class="<?php echo $is_portal ? 'jmrs-portal-kpi-grid' : 'jmrs-stats'; ?>">
			<?php foreach ( $outcome_cards as $card ) : ?>
				<?php
				$count    = absint( $card['count'] ?? 0 );
				$label    = (string) ( $card['label'] ?? '' );
				$list_url = (string) ( $card['list_url'] ?? '' );
				?>
				<?php if ( $is_portal ) : ?>
					<?php
					$kpi_value = (string) $count;
					$kpi_label = $label;
					$kpi_href  = $list_url;
					$kpi_tone  = 'default';
					include JMRS_PLUGIN_PATH . 'templates/portal/partials/kpi-card.php';
					?>
				<?php else : ?>
					<div class="jmrs-stat">
						<span class="jmrs-stat-number"><a href="<?php echo esc_url( $list_url ); ?>"><?php echo esc_html( (string) $count ); ?></a></span>
						<span class="jmrs-stat-label"><?php echo esc_html( $label ); ?></span>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( $legacy_count > 0 ) : ?>
				<?php if ( $is_portal ) : ?>
					<?php
					$kpi_value = (string) $legacy_count;
					$kpi_label = __( 'Legacy Workflow', 'jm-referral-system' );
					$kpi_href  = $legacy_url;
					$kpi_tone  = 'default';
					include JMRS_PLUGIN_PATH . 'templates/portal/partials/kpi-card.php';
					?>
				<?php else : ?>
					<div class="jmrs-stat">
						<span class="jmrs-stat-number"><a href="<?php echo esc_url( $legacy_url ); ?>"><?php echo esc_html( (string) $legacy_count ); ?></a></span>
						<span class="jmrs-stat-label"><?php echo esc_html__( 'Legacy Workflow', 'jm-referral-system' ); ?></span>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>

<section class="<?php echo $is_portal ? 'jmrs-portal-section jmrs-portal-panel' : 'jmrs-pipeline-dashboard'; ?>" aria-labelledby="jmrs-needs-attention-heading">
	<h2 id="jmrs-needs-attention-heading" class="<?php echo $is_portal ? 'jmrs-portal-section__title' : 'jmrs-dashboard-section-title'; ?>">
		<?php echo esc_html__( 'Needs Attention', 'jm-referral-system' ); ?>
		<span class="description">(<?php echo esc_html( (string) $attention_total ); ?>)</span>
	</h2>
	<p class="description"><?php echo esc_html__( 'Operational Attention — highlights exceptional conditions. Not clinical risk.', 'jm-referral-system' ); ?></p>
	<?php if ( '' !== $sort_note ) : ?>
		<p class="description"><?php echo esc_html( $sort_note ); ?></p>
	<?php endif; ?>

	<p class="jmrs-pipeline-quick-filters">
		<a class="<?php echo $is_portal ? 'jmrs-portal-btn jmrs-portal-btn--secondary' : 'button'; ?>" href="<?php echo esc_url( (string) ( $filter_urls['all'] ?? '' ) ); ?>"><?php echo esc_html__( 'All', 'jm-referral-system' ); ?></a>
		<a class="<?php echo $is_portal ? 'jmrs-portal-btn jmrs-portal-btn--secondary' : 'button'; ?>" href="<?php echo esc_url( (string) ( $filter_urls['my'] ?? '' ) ); ?>"><?php echo esc_html__( 'My Referrals', 'jm-referral-system' ); ?></a>
		<a class="<?php echo $is_portal ? 'jmrs-portal-btn jmrs-portal-btn--secondary' : 'button'; ?>" href="<?php echo esc_url( (string) ( $filter_urls['unassigned'] ?? '' ) ); ?>"><?php echo esc_html__( 'Unassigned', 'jm-referral-system' ); ?></a>
		<a class="<?php echo $is_portal ? 'jmrs-portal-btn jmrs-portal-btn--secondary' : 'button'; ?>" href="<?php echo esc_url( (string) ( $filter_urls['attention'] ?? '' ) ); ?>"><?php echo esc_html__( 'Needs Attention', 'jm-referral-system' ); ?></a>
	</p>

	<form method="get" action="<?php echo esc_url( $form_action ); ?>" class="jmrs-pipeline-filter-form" style="margin: 0.75em 0 1em;">
		<?php if ( ! $is_portal ) : ?>
			<input type="hidden" name="page" value="jm-referrals" />
		<?php endif; ?>
		<label for="jmrs_pipe_stage"><?php echo esc_html__( 'Pipeline Stage', 'jm-referral-system' ); ?></label>
		<select name="jmrs_pipe_stage" id="jmrs_pipe_stage">
			<?php foreach ( $stage_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) ( $filters['pipeline_stage'] ?? '' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<label for="jmrs_pipe_priority"><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?></label>
		<select name="jmrs_pipe_priority" id="jmrs_pipe_priority">
			<option value=""><?php echo esc_html__( 'All priorities', 'jm-referral-system' ); ?></option>
			<?php foreach ( $priority_options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) ( $filters['priority'] ?? '' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! empty( $filters['quick'] ) ) : ?>
			<input type="hidden" name="jmrs_pipe_quick" value="<?php echo esc_attr( (string) $filters['quick'] ); ?>" />
		<?php endif; ?>
		<button type="submit" class="<?php echo $is_portal ? 'jmrs-portal-btn jmrs-portal-btn--secondary' : 'button'; ?>"><?php echo esc_html__( 'Apply', 'jm-referral-system' ); ?></button>
	</form>

	<table class="<?php echo $is_portal ? 'jmrs-portal-table' : 'wp-list-table widefat fixed striped table-view-list'; ?>">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Stage', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Reason', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Owner', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Waiting', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Action', 'jm-referral-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $needs_attention ) ) : ?>
				<tr class="no-items">
					<td colspan="7"><?php echo esc_html__( 'No referrals currently need attention.', 'jm-referral-system' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $needs_attention as $item ) : ?>
					<?php
					$reason_labels = is_array( $item['reason_labels'] ?? null ) ? $item['reason_labels'] : array();
					$primary       = (string) ( $item['primary_reason_label'] ?? '' );
					$extra         = max( 0, count( $reason_labels ) - 1 );
					$reason_text   = $primary;
					if ( $extra > 0 ) {
						$reason_text .= ' +' . (string) $extra;
					}
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( (string) ( $item['referral_number'] ?? '' ) ); ?></strong><br />
							<?php echo esc_html( (string) ( $item['client_name'] ?? '' ) ); ?>
						</td>
						<td><?php echo esc_html( (string) ( $item['stage_label'] ?? '' ) ); ?></td>
						<td>
							<span><?php echo esc_html( $reason_text ); ?></span>
							<?php if ( 'target_exceeded' === (string) ( $item['primary_reason'] ?? '' ) || in_array( 'target_exceeded', (array) ( $item['reasons'] ?? array() ), true ) ) : ?>
								<br /><span class="description">
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: waiting, 2: target */
											__( 'Waiting %1$s / Internal Target %2$s', 'jm-referral-system' ),
											(string) ( $item['waiting_label'] ?? '' ),
											(string) ( $item['target_label'] ?? '' )
										)
									);
									?>
								</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) ( $item['owner_name'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $item['waiting_label'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $item['priority_label'] ?? '' ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( (string) ( $item['view_url'] ?? '' ) ); ?>">
								<?php echo esc_html( (string) ( $item['action_label'] ?? __( 'View', 'jm-referral-system' ) ) ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</section>

<section class="<?php echo $is_portal ? 'jmrs-portal-section jmrs-portal-panel' : 'jmrs-pipeline-dashboard'; ?>" aria-labelledby="jmrs-action-queue-heading">
	<h2 id="jmrs-action-queue-heading" class="<?php echo $is_portal ? 'jmrs-portal-section__title' : 'jmrs-dashboard-section-title'; ?>">
		<?php echo esc_html__( 'Active Pipeline Queue', 'jm-referral-system' ); ?>
	</h2>
	<p class="description"><?php echo esc_html__( 'All active acquisition referrals (not only exceptional attention items).', 'jm-referral-system' ); ?></p>

	<table class="<?php echo $is_portal ? 'jmrs-portal-table' : 'wp-list-table widefat fixed striped table-view-list'; ?>">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Pipeline Stage', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Next Action', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Owner', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Time Waiting', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Internal Target', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $action_queue ) ) : ?>
				<tr class="no-items">
					<td colspan="9"><?php echo esc_html__( 'No active acquisition referrals match the current filters.', 'jm-referral-system' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $action_queue as $item ) : ?>
					<tr>
						<td><code><?php echo esc_html( (string) ( $item['referral_number'] ?? '' ) ); ?></code></td>
						<td><?php echo esc_html( (string) ( $item['client_name'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $item['stage_label'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $item['next_action'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $item['priority_label'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $item['owner_name'] ?? '' ) ); ?></td>
						<td>
							<?php
							if ( ! empty( $item['waiting_known'] ) ) {
								echo esc_html(
									sprintf(
										/* translators: %s: waiting duration */
										__( 'Waiting %s', 'jm-referral-system' ),
										(string) ( $item['waiting_label'] ?? '' )
									)
								);
							} else {
								echo esc_html( (string) ( $item['waiting_label'] ?? '' ) );
							}
							?>
						</td>
						<td><?php echo esc_html( (string) ( $item['target_status_label'] ?? '—' ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( (string) ( $item['view_url'] ?? '' ) ); ?>">
								<?php echo esc_html__( 'View', 'jm-referral-system' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</section>
