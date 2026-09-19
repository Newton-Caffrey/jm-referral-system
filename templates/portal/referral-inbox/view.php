<?php
/**
 * Staff Portal Referral Inbox detail (Phase 5B.3).
 *
 * Body preview is escaped plaintext only. No attachment downloads.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item                  = is_array( $item ?? null ) ? $item : array();
$inbox_id              = isset( $inbox_id ) ? absint( $inbox_id ) : 0;
$status                = (string) ( $status ?? '' );
$status_label          = (string) ( $status_label ?? '' );
$detection_label       = (string) ( $detection_label ?? '' );
$source_label          = (string) ( $source_label ?? '' );
$received_display      = (string) ( $received_display ?? '—' );
$reviewed_by_display   = (string) ( $reviewed_by_display ?? '—' );
$reviewed_at_display   = (string) ( $reviewed_at_display ?? '—' );
$ignored_by_display    = (string) ( $ignored_by_display ?? '—' );
$ignored_at_display    = (string) ( $ignored_at_display ?? '—' );
$accepted_by_display   = (string) ( $accepted_by_display ?? '—' );
$accepted_at_display   = (string) ( $accepted_at_display ?? '—' );
$la_name               = (string) ( $la_name ?? '—' );
$la_label              = (string) ( $la_label ?? __( 'Local Authority', 'jm-referral-system' ) );
$referral_label        = (string) ( $referral_label ?? __( 'Referral', 'jm-referral-system' ) );
$attachments           = is_array( $attachments ?? null ) ? $attachments : array();
$can_manage            = ! empty( $can_manage );
$can_start_review      = ! empty( $can_start_review );
$can_ignore            = ! empty( $can_ignore );
$can_duplicate         = ! empty( $can_duplicate );
$can_recover_error     = ! empty( $can_recover_error );
$is_terminal           = ! empty( $is_terminal );
$duplicate_of_id       = absint( $duplicate_of_id ?? 0 );
$duplicate_of_url      = (string) ( $duplicate_of_url ?? '' );
$duplicate_of_label    = (string) ( $duplicate_of_label ?? '' );
$linked_referral_id    = absint( $linked_referral_id ?? 0 );
$linked_referral_url   = (string) ( $linked_referral_url ?? '' );
$linked_referral_label = (string) ( $linked_referral_label ?? '' );
$duplicate_preview     = is_array( $duplicate_preview ?? null ) ? $duplicate_preview : null;
$dup_preview_id        = absint( $dup_preview_id ?? 0 );
$form_action           = (string) ( $form_action ?? '' );
$nonce_field           = (string) ( $nonce_field ?? '' );
$list_url              = (string) ( $list_url ?? '' );
$detail_notice         = is_array( $detail_notice ?? null ) ? $detail_notice : null;
$confirm_info          = (string) ( $confirm_info ?? '' );

$body_preview = (string) ( $item['body_preview'] ?? '' );
$sender_name  = (string) ( $item['sender_name'] ?? '' );
$sender_email = (string) ( $item['sender_email'] ?? '' );
$subject      = (string) ( $item['subject'] ?? '' );
$mailbox      = (string) ( $item['mailbox_identifier'] ?? '' );
$provider_mid = (string) ( $item['provider_message_id'] ?? '' );
$internet_mid = (string) ( $item['internet_message_id'] ?? '' );
$conversation = (string) ( $item['conversation_identifier'] ?? '' );
$error_code   = (string) ( $item['error_code'] ?? '' );
$error_msg    = (string) ( $item['error_message'] ?? '' );
$detection_key = (string) ( $item['detection_status'] ?? '' );
?>
<?php if ( is_array( $detail_notice ) && ! empty( $detail_notice['message'] ) ) : ?>
	<?php
	$notice_type    = (string) ( $detail_notice['type'] ?? 'success' );
	$notice_message = (string) $detail_notice['message'];
	$notice_actions = array();
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/notice.php';
	?>
<?php endif; ?>

<p class="jmrs-inbox-detail__back">
	<a href="<?php echo esc_url( $list_url ); ?>"><?php echo esc_html__( '← Back to Referral Inbox', 'jm-referral-system' ); ?></a>
</p>

<section class="jmrs-portal-section jmrs-inbox-detail" aria-labelledby="jmrs-inbox-detail-heading">
	<?php
	$section_title   = sprintf(
		/* translators: %d: inbox item ID */
		__( 'Referral Inbox #%d', 'jm-referral-system' ),
		$inbox_id
	);
	$section_id      = 'jmrs-inbox-detail-heading';
	$section_badge   = '';
	$section_actions = array();
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
	?>

	<div class="jmrs-inbox-detail__panel">
		<h3><?php echo esc_html__( 'Overview', 'jm-referral-system' ); ?></h3>
		<dl class="jmrs-inbox-detail__facts">
			<div>
				<dt><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></dt>
				<dd>
					<span class="jmrs-inbox-badge jmrs-inbox-badge--status jmrs-inbox-badge--status-<?php echo esc_attr( $status ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</span>
				</dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Detection', 'jm-referral-system' ); ?></dt>
				<dd>
					<span class="jmrs-inbox-badge jmrs-inbox-badge--detection jmrs-inbox-badge--detection-<?php echo esc_attr( $detection_key ); ?>">
						<?php echo esc_html( $detection_label ); ?>
					</span>
				</dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Received', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $received_display ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Source', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $source_label ); ?></dd>
			</div>
			<?php if ( '' !== $mailbox ) : ?>
				<div>
					<dt><?php echo esc_html__( 'Mailbox', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( $mailbox ); ?></dd>
				</div>
			<?php endif; ?>
			<div>
				<dt><?php echo esc_html( $la_label ); ?></dt>
				<dd><?php echo esc_html( $la_name ); ?></dd>
			</div>
		</dl>
	</div>

	<div class="jmrs-inbox-detail__panel">
		<h3><?php echo esc_html__( 'Message', 'jm-referral-system' ); ?></h3>
		<dl class="jmrs-inbox-detail__facts">
			<div>
				<dt><?php echo esc_html__( 'Sender name', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $sender_name ? $sender_name : '—' ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Sender email', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $sender_email ? $sender_email : '—' ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Subject', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $subject ? $subject : '—' ); ?></dd>
			</div>
		</dl>
		<h4 class="jmrs-inbox-detail__subheading"><?php echo esc_html__( 'Body preview', 'jm-referral-system' ); ?></h4>
		<pre class="jmrs-inbox-body-preview"><?php echo esc_html( '' !== $body_preview ? $body_preview : '—' ); ?></pre>
	</div>

	<?php if ( '' !== $provider_mid || '' !== $internet_mid || '' !== $conversation ) : ?>
		<div class="jmrs-inbox-detail__panel jmrs-inbox-detail__panel--muted">
			<h3><?php echo esc_html__( 'Identifiers', 'jm-referral-system' ); ?></h3>
			<p class="jmrs-inbox-detail__hint"><?php echo esc_html__( 'Technical identifiers for operational troubleshooting.', 'jm-referral-system' ); ?></p>
			<dl class="jmrs-inbox-detail__facts">
				<?php if ( '' !== $provider_mid ) : ?>
					<div>
						<dt><?php echo esc_html__( 'Provider message ID', 'jm-referral-system' ); ?></dt>
						<dd><code><?php echo esc_html( $provider_mid ); ?></code></dd>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $internet_mid ) : ?>
					<div>
						<dt><?php echo esc_html__( 'Internet Message-ID', 'jm-referral-system' ); ?></dt>
						<dd><code><?php echo esc_html( $internet_mid ); ?></code></dd>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $conversation ) : ?>
					<div>
						<dt><?php echo esc_html__( 'Conversation identifier', 'jm-referral-system' ); ?></dt>
						<dd><code><?php echo esc_html( $conversation ); ?></code></dd>
					</div>
				<?php endif; ?>
			</dl>
		</div>
	<?php endif; ?>

	<div class="jmrs-inbox-detail__panel">
		<h3><?php echo esc_html__( 'Attachments', 'jm-referral-system' ); ?></h3>
		<?php if ( empty( $attachments ) ) : ?>
			<p><?php echo esc_html__( 'No attachment metadata recorded for this opportunity.', 'jm-referral-system' ); ?></p>
		<?php else : ?>
			<div class="jmrs-portal-table-wrap">
				<table class="jmrs-portal-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Filename', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'MIME type', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Size', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Storage status', 'jm-referral-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $attachments as $attachment ) : ?>
							<tr>
								<td><?php echo esc_html( (string) ( $attachment['filename'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $attachment['mime_type'] ?? '—' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $attachment['size_display'] ?? '—' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $attachment['storage_label'] ?? '—' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="jmrs-inbox-detail__hint"><?php echo esc_html__( 'Attachment files are not available for download in this phase.', 'jm-referral-system' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="jmrs-inbox-detail__panel">
		<h3><?php echo esc_html__( 'Review', 'jm-referral-system' ); ?></h3>
		<dl class="jmrs-inbox-detail__facts">
			<div>
				<dt><?php echo esc_html__( 'Reviewed by', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $reviewed_by_display ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Reviewed at', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $reviewed_at_display ); ?></dd>
			</div>
		</dl>
	</div>

	<div class="jmrs-inbox-detail__panel">
		<h3><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></h3>
		<?php if ( 'accepted' === $status ) : ?>
			<dl class="jmrs-inbox-detail__facts">
				<div>
					<dt><?php echo esc_html__( 'Accepted by', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( $accepted_by_display ); ?></dd>
				</div>
				<div>
					<dt><?php echo esc_html__( 'Accepted at', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( $accepted_at_display ); ?></dd>
				</div>
				<div>
					<dt><?php echo esc_html( $referral_label ); ?></dt>
					<dd>
						<?php if ( '' !== $linked_referral_url && '' !== $linked_referral_label ) : ?>
							<a href="<?php echo esc_url( $linked_referral_url ); ?>">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: referral number or label */
										__( 'View %s', 'jm-referral-system' ),
										$linked_referral_label
									)
								);
								?>
							</a>
						<?php elseif ( $linked_referral_id > 0 ) : ?>
							<?php echo esc_html__( 'Linked referral is not available to your account.', 'jm-referral-system' ); ?>
						<?php else : ?>
							—
						<?php endif; ?>
					</dd>
				</div>
			</dl>
		<?php elseif ( 'ignored' === $status ) : ?>
			<dl class="jmrs-inbox-detail__facts">
				<div>
					<dt><?php echo esc_html__( 'Ignored by', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( $ignored_by_display ); ?></dd>
				</div>
				<div>
					<dt><?php echo esc_html__( 'Ignored at', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( $ignored_at_display ); ?></dd>
				</div>
			</dl>
		<?php elseif ( 'duplicate' === $status ) : ?>
			<p>
				<?php if ( '' !== $duplicate_of_url ) : ?>
					<a href="<?php echo esc_url( $duplicate_of_url ); ?>"><?php echo esc_html( $duplicate_of_label ); ?></a>
				<?php else : ?>
					<?php echo esc_html( '' !== $duplicate_of_label ? $duplicate_of_label : '—' ); ?>
				<?php endif; ?>
			</p>
		<?php elseif ( 'error' === $status ) : ?>
			<dl class="jmrs-inbox-detail__facts">
				<div>
					<dt><?php echo esc_html__( 'Error code', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( '' !== $error_code ? $error_code : '—' ); ?></dd>
				</div>
				<div>
					<dt><?php echo esc_html__( 'Error message', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( '' !== $error_msg ? $error_msg : '—' ); ?></dd>
				</div>
			</dl>
		<?php else : ?>
			<p class="jmrs-inbox-detail__hint"><?php echo esc_html__( 'No terminal outcome recorded yet.', 'jm-referral-system' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $can_manage && ! $is_terminal ) : ?>
		<div class="jmrs-inbox-detail__panel jmrs-inbox-detail__actions">
			<h3><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></h3>

			<?php if ( 'needs_review' === $status && '' !== $confirm_info ) : ?>
				<p class="jmrs-inbox-detail__hint"><?php echo esc_html( $confirm_info ); ?></p>
			<?php endif; ?>

			<?php if ( $can_start_review ) : ?>
				<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="jmrs-inbox-action-form">
					<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field HTML. ?>
					<input type="hidden" name="jmrs_inbox_action" value="start_review" />
					<button type="submit" class="jmrs-button jmrs-button--primary">
						<?php echo esc_html__( 'Start Review', 'jm-referral-system' ); ?>
					</button>
				</form>
			<?php endif; ?>

			<?php if ( $can_recover_error ) : ?>
				<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="jmrs-inbox-action-form">
					<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field HTML. ?>
					<input type="hidden" name="jmrs_inbox_action" value="recover_error" />
					<button type="submit" class="jmrs-button jmrs-button--primary">
						<?php echo esc_html__( 'Return to Review', 'jm-referral-system' ); ?>
					</button>
				</form>
			<?php endif; ?>

			<?php if ( $can_ignore ) : ?>
				<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="jmrs-inbox-action-form jmrs-inbox-action-form--ignore">
					<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field HTML. ?>
					<input type="hidden" name="jmrs_inbox_action" value="ignore" />
					<p>
						<label for="jmrs_inbox_ignore_confirm">
							<input
								type="checkbox"
								name="jmrs_inbox_ignore_confirm"
								id="jmrs_inbox_ignore_confirm"
								value="1"
								required
							/>
							<?php echo esc_html__( 'Are you sure you want to ignore this referral opportunity?', 'jm-referral-system' ); ?>
						</label>
					</p>
					<button type="submit" class="jmrs-button jmrs-button--danger">
						<?php echo esc_html__( 'Ignore Opportunity', 'jm-referral-system' ); ?>
					</button>
				</form>
			<?php endif; ?>

			<?php if ( $can_duplicate ) : ?>
				<form method="get" action="<?php echo esc_url( $form_action ); ?>" class="jmrs-inbox-action-form jmrs-inbox-dup-preview-form">
					<p>
						<label for="jmrs_dup_preview"><?php echo esc_html__( 'Preview duplicate target (Inbox ID)', 'jm-referral-system' ); ?></label>
						<input type="number" min="1" name="jmrs_dup_preview" id="jmrs_dup_preview" value="<?php echo $dup_preview_id > 0 ? esc_attr( (string) $dup_preview_id ) : ''; ?>" />
					</p>
					<button type="submit" class="jmrs-button jmrs-button--secondary">
						<?php echo esc_html__( 'Preview target', 'jm-referral-system' ); ?>
					</button>
				</form>

				<?php if ( is_array( $duplicate_preview ) ) : ?>
					<div class="jmrs-inbox-dup-preview" role="status">
						<p>
							<strong><?php echo esc_html__( 'Selected target', 'jm-referral-system' ); ?>:</strong>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: inbox id, 2: subject, 3: sender, 4: status */
									__( 'Inbox #%1$d — %2$s — %3$s (%4$s)', 'jm-referral-system' ),
									absint( $duplicate_preview['id'] ?? 0 ),
									(string) ( $duplicate_preview['subject'] ?? '' ),
									(string) ( $duplicate_preview['sender'] ?? '' ),
									(string) ( $duplicate_preview['status'] ?? '' )
								)
							);
							?>
						</p>
						<?php if ( ! empty( $duplicate_preview['url'] ) ) : ?>
							<p><a href="<?php echo esc_url( (string) $duplicate_preview['url'] ); ?>"><?php echo esc_html__( 'Open target', 'jm-referral-system' ); ?></a></p>
						<?php endif; ?>
					</div>
				<?php elseif ( $dup_preview_id > 0 ) : ?>
					<p class="jmrs-portal-notice jmrs-portal-notice--warning" role="status">
						<?php echo esc_html__( 'No Inbox item found for that ID.', 'jm-referral-system' ); ?>
					</p>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="jmrs-inbox-action-form">
					<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field HTML. ?>
					<input type="hidden" name="jmrs_inbox_action" value="mark_duplicate" />
					<p>
						<label for="jmrs_duplicate_of_id"><?php echo esc_html__( 'Mark as Duplicate Of', 'jm-referral-system' ); ?></label>
						<input
							type="number"
							min="1"
							name="jmrs_duplicate_of_id"
							id="jmrs_duplicate_of_id"
							required
							value="<?php echo is_array( $duplicate_preview ) ? esc_attr( (string) absint( $duplicate_preview['id'] ?? 0 ) ) : ( $dup_preview_id > 0 ? esc_attr( (string) $dup_preview_id ) : '' ); ?>"
						/>
					</p>
					<button type="submit" class="jmrs-button jmrs-button--secondary">
						<?php echo esc_html__( 'Mark as Duplicate', 'jm-referral-system' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
	<?php elseif ( $is_terminal ) : ?>
		<div class="jmrs-inbox-detail__panel">
			<p class="jmrs-inbox-detail__hint" role="status">
				<?php echo esc_html__( 'This opportunity is in a terminal state and is read-only.', 'jm-referral-system' ); ?>
			</p>
		</div>
	<?php endif; ?>
</section>
