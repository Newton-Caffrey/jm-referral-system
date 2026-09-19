<?php
/**
 * Staff Portal Referral Inbox list (Phase 5B.3).
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$intro             = (string) ( $intro ?? '' );
$items             = is_array( $items ?? null ) ? $items : array();
$filters           = is_array( $filters ?? null ) ? $filters : array();
$status_tabs       = is_array( $status_tabs ?? null ) ? $status_tabs : array();
$total             = isset( $total ) ? absint( $total ) : 0;
$from              = isset( $from ) ? absint( $from ) : 0;
$to                = isset( $to ) ? absint( $to ) : 0;
$pagination_links  = (string) ( $pagination_links ?? '' );
$form_action       = (string) ( $form_action ?? '' );
$list_notice       = is_array( $list_notice ?? null ) ? $list_notice : null;
$has_active_filter = ! empty( $has_active_filter );
$la_label          = (string) ( $la_label ?? __( 'Local Authority', 'jm-referral-system' ) );

$search = (string) ( $filters['search'] ?? '' );
?>
<?php if ( is_array( $list_notice ) && ! empty( $list_notice['message'] ) ) : ?>
	<?php
	$notice_type    = (string) ( $list_notice['type'] ?? 'success' );
	$notice_message = (string) $list_notice['message'];
	$notice_actions = array();
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/notice.php';
	?>
<?php endif; ?>

<section class="jmrs-portal-section jmrs-inbox" aria-labelledby="jmrs-inbox-heading">
	<?php
	$section_title   = __( 'Referral Inbox', 'jm-referral-system' );
	$section_id      = 'jmrs-inbox-heading';
	$section_badge   = '';
	$section_actions = array();
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
	?>
	<?php if ( '' !== $intro ) : ?>
		<p class="jmrs-inbox__intro"><?php echo esc_html( $intro ); ?></p>
	<?php endif; ?>

	<nav class="jmrs-inbox-tabs" aria-label="<?php echo esc_attr__( 'Inbox status filters', 'jm-referral-system' ); ?>">
		<ul class="jmrs-inbox-tabs__list">
			<?php foreach ( $status_tabs as $tab ) : ?>
				<?php
				$tab_key     = (string) ( $tab['key'] ?? '' );
				$tab_label   = (string) ( $tab['label'] ?? '' );
				$tab_count   = absint( $tab['count'] ?? 0 );
				$tab_url     = (string) ( $tab['url'] ?? '' );
				$tab_current = ! empty( $tab['current'] );
				?>
				<li class="jmrs-inbox-tabs__item<?php echo $tab_current ? ' is-current' : ''; ?>">
					<a
						href="<?php echo esc_url( $tab_url ); ?>"
						<?php echo $tab_current ? ' aria-current="page"' : ''; ?>
					>
						<span class="jmrs-inbox-tabs__label"><?php echo esc_html( $tab_label ); ?></span>
						<span class="jmrs-inbox-tabs__count">(<?php echo esc_html( (string) $tab_count ); ?>)</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>

	<form class="jmrs-portal-filters" method="get" action="<?php echo esc_url( $form_action ); ?>">
		<?php if ( ! empty( $filters['status'] ) && 'all' !== $filters['status'] ) : ?>
			<input type="hidden" name="jmrs_inbox_status" value="<?php echo esc_attr( (string) $filters['status'] ); ?>" />
		<?php endif; ?>
		<div class="jmrs-portal-filters__row">
			<p>
				<label for="jmrs_inbox_search"><?php echo esc_html__( 'Search', 'jm-referral-system' ); ?></label>
				<input
					type="search"
					name="jmrs_inbox_search"
					id="jmrs_inbox_search"
					value="<?php echo esc_attr( $search ); ?>"
					placeholder="<?php echo esc_attr__( 'Subject, sender, or message ID', 'jm-referral-system' ); ?>"
				/>
			</p>
			<p class="jmrs-portal-filters__submit">
				<button type="submit" class="jmrs-button jmrs-button--primary"><?php echo esc_html__( 'Search', 'jm-referral-system' ); ?></button>
			</p>
		</div>
	</form>
</section>

<section class="jmrs-portal-section" aria-labelledby="jmrs-inbox-results">
	<h2 id="jmrs-inbox-results" class="screen-reader-text"><?php echo esc_html__( 'Inbox results', 'jm-referral-system' ); ?></h2>

	<?php if ( empty( $items ) ) : ?>
		<?php
		$empty_title   = $has_active_filter
			? __( 'No referral opportunities match the current filters.', 'jm-referral-system' )
			: __( 'No referral opportunities are currently waiting in the Inbox.', 'jm-referral-system' );
		$empty_message = $has_active_filter
			? __( 'Try a different status tab or search term.', 'jm-referral-system' )
			: __( 'Incoming opportunities will appear here when a configured intake source adds them.', 'jm-referral-system' );
		$empty_actions = array();
		include JMRS_PLUGIN_PATH . 'templates/portal/partials/empty-state.php';
		?>
	<?php else : ?>
		<p class="jmrs-inbox__summary" aria-live="polite">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: from, 2: to, 3: total */
					__( 'Showing %1$d–%2$d of %3$d', 'jm-referral-system' ),
					$from,
					$to,
					$total
				)
			);
			?>
		</p>
		<div class="jmrs-portal-table-wrap jmrs-inbox-table-wrap">
			<table class="jmrs-portal-table jmrs-inbox-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Received', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Sender', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Subject', 'jm-referral-system' ); ?></th>
						<th scope="col" class="jmrs-inbox-col--secondary"><?php echo esc_html( $la_label ); ?></th>
						<th scope="col" class="jmrs-inbox-col--secondary"><?php echo esc_html__( 'Detection', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
						<th scope="col" class="jmrs-inbox-col--secondary"><?php echo esc_html__( 'Attachments', 'jm-referral-system' ); ?></th>
						<th scope="col" class="jmrs-inbox-col--secondary"><?php echo esc_html__( 'Reviewed By', 'jm-referral-system' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $row ) : ?>
						<?php
						$view_url       = (string) ( $row['view_url'] ?? '' );
						$status_key     = (string) ( $row['status'] ?? '' );
						$status_label   = (string) ( $row['status_label'] ?? '' );
						$detection_key  = (string) ( $row['detection_key'] ?? '' );
						$detection_label = (string) ( $row['detection_label'] ?? '' );
						$att_count      = absint( $row['attachment_count'] ?? 0 );
						?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'Received', 'jm-referral-system' ); ?>">
								<?php echo esc_html( (string) ( $row['received_display'] ?? '—' ) ); ?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Sender', 'jm-referral-system' ); ?>">
								<?php echo esc_html( (string) ( $row['sender_display'] ?? '—' ) ); ?>
								<?php if ( 'fixture' === (string) ( $row['source_key'] ?? '' ) ) : ?>
									<span class="jmrs-inbox-badge jmrs-inbox-badge--fixture"><?php echo esc_html( (string) ( $row['source_label'] ?? '' ) ); ?></span>
								<?php endif; ?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Subject', 'jm-referral-system' ); ?>">
								<?php echo esc_html( (string) ( $row['subject'] ?? '' ) ); ?>
							</td>
							<td class="jmrs-inbox-col--secondary" data-label="<?php echo esc_attr( $la_label ); ?>">
								<?php echo esc_html( (string) ( $row['la_name'] ?? '—' ) ); ?>
							</td>
							<td class="jmrs-inbox-col--secondary" data-label="<?php echo esc_attr__( 'Detection', 'jm-referral-system' ); ?>">
								<span class="jmrs-inbox-badge jmrs-inbox-badge--detection jmrs-inbox-badge--detection-<?php echo esc_attr( $detection_key ); ?>">
									<?php echo esc_html( $detection_label ); ?>
								</span>
							</td>
							<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>">
								<span class="jmrs-inbox-badge jmrs-inbox-badge--status jmrs-inbox-badge--status-<?php echo esc_attr( $status_key ); ?>">
									<?php echo esc_html( $status_label ); ?>
								</span>
							</td>
							<td class="jmrs-inbox-col--secondary" data-label="<?php echo esc_attr__( 'Attachments', 'jm-referral-system' ); ?>">
								<?php echo esc_html( (string) $att_count ); ?>
							</td>
							<td class="jmrs-inbox-col--secondary" data-label="<?php echo esc_attr__( 'Reviewed By', 'jm-referral-system' ); ?>">
								<?php echo esc_html( (string) ( $row['reviewed_by'] ?? '—' ) ); ?>
							</td>
							<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
								<?php if ( '' !== $view_url ) : ?>
									<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $view_url ); ?>">
										<?php echo esc_html__( 'Open', 'jm-referral-system' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( '' !== $pagination_links ) : ?>
			<nav class="jmrs-portal-pagination" aria-label="<?php echo esc_attr__( 'Inbox list pagination', 'jm-referral-system' ); ?>">
				<?php echo wp_kses_post( $pagination_links ); ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</section>
