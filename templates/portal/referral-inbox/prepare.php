<?php
/**
 * Staff Portal referral preparation (Phase 5D.4).
 *
 * Validates a draft in the current response only. Does not create a referral.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inbox_id               = isset( $inbox_id ) ? absint( $inbox_id ) : 0;
$item                   = is_array( $item ?? null ) ? $item : array();
$show_form              = ! empty( $show_form );
$can_assign             = ! empty( $can_assign );
$notice                 = (string) ( $notice ?? '' );
$stale_message          = (string) ( $stale_message ?? '' );
$blocked_message        = (string) ( $blocked_message ?? '' );
$ready_message          = (string) ( $ready_message ?? '' );
$not_created_message    = (string) ( $not_created_message ?? '' );
$values                 = is_array( $values ?? null ) ? $values : array();
$errors                 = is_array( $errors ?? null ) ? $errors : array();
$warnings               = is_array( $warnings ?? null ) ? $warnings : array();
$alternatives           = is_array( $alternatives ?? null ) ? $alternatives : array();
$field_notes            = is_array( $field_notes ?? null ) ? $field_notes : array();
$service_hint           = (string) ( $service_hint ?? '' );
$priority_hint          = (string) ( $priority_hint ?? '' );
$authority_note         = (string) ( $authority_note ?? '' );
$authority_status_label = (string) ( $authority_status_label ?? '' );
$service_types          = is_array( $service_types ?? null ) ? $service_types : array();
$referral_sources       = is_array( $referral_sources ?? null ) ? $referral_sources : array();
$priorities             = is_array( $priorities ?? null ) ? $priorities : array();
$assignable_users       = is_array( $assignable_users ?? null ) ? $assignable_users : array();
$status                 = (string) ( $status ?? '' );
$status_label           = (string) ( $status_label ?? '' );
$detection_label        = (string) ( $detection_label ?? '' );
$detection_explanation  = (string) ( $detection_explanation ?? '' );
$received_display       = (string) ( $received_display ?? '—' );
$detail_url             = (string) ( $detail_url ?? '' );
$form_action            = (string) ( $form_action ?? '' );
$nonce_field            = (string) ( $nonce_field ?? '' );

$subject      = (string) ( $item['subject'] ?? '' );
$sender_name  = (string) ( $item['sender_name'] ?? '' );
$sender_email = (string) ( $item['sender_email'] ?? '' );
$body_preview = (string) ( $item['body_preview'] ?? '' );
$sender_line  = trim( $sender_name . ( '' !== $sender_email ? ' <' . $sender_email . '>' : '' ) );

$val = static function ( array $values, string $key ): string {
	return (string) ( $values[ $key ] ?? '' );
};

$described_by = static function ( string $key, array $errors, array $warnings, array $field_notes, string $extra = '' ): string {
	$ids = array();
	if ( '' !== $extra ) {
		$ids[] = $extra;
	}
	if ( isset( $field_notes[ $key ] ) && '' !== (string) $field_notes[ $key ] ) {
		$ids[] = 'jmrs-prepare-note-' . $key;
	}
	if ( isset( $warnings[ $key ] ) && '' !== (string) $warnings[ $key ] ) {
		$ids[] = 'jmrs-prepare-warning-' . $key;
	}
	if ( isset( $errors[ $key ] ) ) {
		$ids[] = 'jmrs-prepare-error-' . $key;
	}

	return implode( ' ', $ids );
};
?>
<p class="jmrs-inbox-detail__back">
	<a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html__( 'Back to Referral Inbox Item', 'jm-referral-system' ); ?></a>
</p>

<section class="jmrs-portal-section jmrs-prepare" aria-labelledby="jmrs-prepare-heading">
	<?php
	$section_title   = __( 'Prepare Referral', 'jm-referral-system' );
	$section_id      = 'jmrs-prepare-heading';
	$section_badge   = '';
	$section_actions = array();
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
	?>

	<?php if ( '' !== $notice ) : ?>
		<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $stale_message ) : ?>
		<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert">
			<p><?php echo esc_html( $stale_message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $ready_message ) : ?>
		<div class="jmrs-portal-notice jmrs-portal-notice--success" role="status">
			<p><?php echo esc_html( $ready_message ); ?></p>
			<?php if ( '' !== $not_created_message ) : ?>
				<p><?php echo esc_html( $not_created_message ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="jmrs-inbox-detail__panel">
		<h3><?php echo esc_html__( 'Source message', 'jm-referral-system' ); ?></h3>
		<dl class="jmrs-inbox-detail__facts">
			<div>
				<dt><?php echo esc_html__( 'Subject', 'jm-referral-system' ); ?></dt>
				<dd><?php echo '' !== $subject ? esc_html( $subject ) : '—'; ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Sender', 'jm-referral-system' ); ?></dt>
				<dd><?php echo '' !== $sender_line ? esc_html( $sender_line ) : '—'; ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Received', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( $received_display ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Inbox status', 'jm-referral-system' ); ?></dt>
				<dd>
					<span class="jmrs-inbox-badge jmrs-inbox-badge--status jmrs-inbox-badge--status-<?php echo esc_attr( $status ); ?>">
						<?php echo esc_html( '' !== $status_label ? $status_label : '—' ); ?>
					</span>
				</dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Detection', 'jm-referral-system' ); ?></dt>
				<dd>
					<?php echo esc_html( $detection_label ); ?>
					<?php if ( '' !== $detection_explanation ) : ?>
						<span class="jmrs-prepare-hint"><?php echo esc_html( $detection_explanation ); ?></span>
					<?php endif; ?>
				</dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Local Authority status', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $authority_status_label ? $authority_status_label : '—' ); ?></dd>
			</div>
		</dl>
		<?php if ( '' !== $body_preview ) : ?>
			<h4 class="jmrs-inbox-detail__subheading"><?php echo esc_html__( 'Message preview', 'jm-referral-system' ); ?></h4>
			<pre class="jmrs-inbox-body-preview"><?php echo esc_html( $body_preview ); ?></pre>
		<?php endif; ?>
		<?php if ( '' !== $detail_url ) : ?>
			<p class="jmrs-prepare-hint">
				<a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html__( 'Review Local Authority', 'jm-referral-system' ); ?></a>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( ! $show_form ) : ?>
		<div class="jmrs-inbox-detail__panel" role="status">
			<p><?php echo esc_html( $blocked_message ); ?></p>
			<?php if ( '' !== $detail_url ) : ?>
				<p>
					<a class="jmrs-button" href="<?php echo esc_url( $detail_url ); ?>">
						<?php echo esc_html__( 'Back to Referral Inbox Item', 'jm-referral-system' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<?php if ( ! empty( $errors ) ) : ?>
			<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert">
				<p><strong><?php echo esc_html__( 'Please fix the following errors:', 'jm-referral-system' ); ?></strong></p>
				<ul>
					<?php foreach ( $errors as $error_key => $error_message ) : ?>
						<li>
							<a href="#jmrs_prepare_<?php echo esc_attr( (string) $error_key ); ?>">
								<?php echo esc_html( (string) $error_message ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<form class="jmrs-portal-form jmrs-prepare-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field HTML. ?>
			<input type="hidden" name="jmrs_prepare_action" value="validate" />
			<input type="hidden" name="jmrs_prepare_inbox_id" value="<?php echo esc_attr( (string) $inbox_id ); ?>" />

			<section class="jmrs-portal-section" aria-labelledby="jmrs-prepare-client-heading">
				<h3 id="jmrs-prepare-client-heading"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></h3>
				<div class="jmrs-portal-form-grid">
					<?php
					$client_fields = array(
						'client_name'  => array( 'label' => __( 'Client Name', 'jm-referral-system' ), 'type' => 'text', 'required' => true ),
						'client_email' => array( 'label' => __( 'Client Email', 'jm-referral-system' ), 'type' => 'text', 'required' => false ),
						'client_phone' => array( 'label' => __( 'Client Phone', 'jm-referral-system' ), 'type' => 'tel', 'required' => false ),
					);
					foreach ( $client_fields as $key => $field ) :
						$field_id = 'jmrs_prepare_' . $key;
						$desc     = $described_by( $key, $errors, $warnings, $field_notes );
						?>
						<div class="jmrs-portal-field">
							<label for="<?php echo esc_attr( $field_id ); ?>">
								<?php echo esc_html( $field['label'] ); ?>
								<?php if ( $field['required'] ) : ?>
									<span aria-hidden="true">*</span>
									<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
								<?php endif; ?>
							</label>
							<input
								type="<?php echo esc_attr( $field['type'] ); ?>"
								<?php echo 'client_email' === $key ? 'inputmode="email" autocomplete="email"' : ''; ?>
								name="<?php echo esc_attr( $field_id ); ?>"
								id="<?php echo esc_attr( $field_id ); ?>"
								value="<?php echo esc_attr( $val( $values, $key ) ); ?>"
								<?php echo $field['required'] ? 'aria-required="true"' : ''; ?>
								<?php echo isset( $errors[ $key ] ) ? 'aria-invalid="true"' : ''; ?>
								<?php echo '' !== $desc ? 'aria-describedby="' . esc_attr( $desc ) . '"' : ''; ?>
							/>
							<?php if ( isset( $field_notes[ $key ] ) ) : ?>
								<p class="jmrs-prepare-hint" id="jmrs-prepare-note-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( (string) $field_notes[ $key ] ); ?></p>
							<?php endif; ?>
							<?php if ( isset( $warnings[ $key ] ) ) : ?>
								<p class="jmrs-prepare-hint" id="jmrs-prepare-warning-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( (string) $warnings[ $key ] ); ?></p>
							<?php endif; ?>
							<?php if ( isset( $alternatives[ $key ] ) && is_array( $alternatives[ $key ] ) && ! empty( $alternatives[ $key ] ) ) : ?>
								<div class="jmrs-prepare-alternatives">
									<p>
										<?php
										echo esc_html(
											match ( $key ) {
												'client_email' => __( 'Possible client emails found:', 'jm-referral-system' ),
												'client_phone' => __( 'Possible client phones found:', 'jm-referral-system' ),
												default        => __( 'Possible client names found:', 'jm-referral-system' ),
											}
										);
										?>
									</p>
									<ul>
										<?php foreach ( $alternatives[ $key ] as $alternative ) : ?>
											<li><?php echo esc_html( (string) $alternative ); ?></li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>
							<?php if ( isset( $errors[ $key ] ) ) : ?>
								<p class="jmrs-portal-field-error" id="jmrs-prepare-error-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( (string) $errors[ $key ] ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="jmrs-portal-section" aria-labelledby="jmrs-prepare-referrer-heading">
				<h3 id="jmrs-prepare-referrer-heading"><?php echo esc_html__( 'Referrer', 'jm-referral-system' ); ?></h3>
				<div class="jmrs-portal-form-grid">
					<?php
					$referrer_fields = array(
						'referrer_name'         => array( 'label' => __( 'Referrer Name', 'jm-referral-system' ), 'type' => 'text' ),
						'referrer_email'        => array( 'label' => __( 'Referrer Email', 'jm-referral-system' ), 'type' => 'text' ),
						'referrer_organisation' => array( 'label' => __( 'Referrer Organisation', 'jm-referral-system' ), 'type' => 'text' ),
					);
					foreach ( $referrer_fields as $key => $field ) :
						$field_id = 'jmrs_prepare_' . $key;
						$extra    = ( 'referrer_organisation' === $key && '' !== $authority_note ) ? 'jmrs-prepare-authority-note' : '';
						$desc     = $described_by( $key, $errors, $warnings, $field_notes, $extra );
						?>
						<div class="jmrs-portal-field">
							<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
							<input
								type="<?php echo esc_attr( $field['type'] ); ?>"
								<?php echo 'referrer_email' === $key ? 'inputmode="email" autocomplete="email"' : ''; ?>
								name="<?php echo esc_attr( $field_id ); ?>"
								id="<?php echo esc_attr( $field_id ); ?>"
								value="<?php echo esc_attr( $val( $values, $key ) ); ?>"
								<?php echo isset( $errors[ $key ] ) ? 'aria-invalid="true"' : ''; ?>
								<?php echo '' !== $desc ? 'aria-describedby="' . esc_attr( $desc ) . '"' : ''; ?>
							/>
							<?php if ( isset( $field_notes[ $key ] ) ) : ?>
								<p class="jmrs-prepare-hint" id="jmrs-prepare-note-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( (string) $field_notes[ $key ] ); ?></p>
							<?php endif; ?>
							<?php if ( 'referrer_organisation' === $key && '' !== $authority_note ) : ?>
								<p class="jmrs-prepare-hint" id="jmrs-prepare-authority-note"><?php echo esc_html( $authority_note ); ?></p>
							<?php endif; ?>
							<?php if ( isset( $warnings[ $key ] ) ) : ?>
								<p class="jmrs-prepare-hint" id="jmrs-prepare-warning-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( (string) $warnings[ $key ] ); ?></p>
							<?php endif; ?>
							<?php if ( isset( $errors[ $key ] ) ) : ?>
								<p class="jmrs-portal-field-error" id="jmrs-prepare-error-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( (string) $errors[ $key ] ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="jmrs-portal-section" aria-labelledby="jmrs-prepare-referral-heading">
				<h3 id="jmrs-prepare-referral-heading"><?php echo esc_html__( 'Referral details', 'jm-referral-system' ); ?></h3>
				<div class="jmrs-portal-form-grid">
					<div class="jmrs-portal-field">
						<label for="jmrs_prepare_service_type_id">
							<?php echo esc_html__( 'Service Type', 'jm-referral-system' ); ?>
							<span aria-hidden="true">*</span>
							<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
						</label>
						<select
							name="jmrs_prepare_service_type_id"
							id="jmrs_prepare_service_type_id"
							aria-required="true"
							<?php echo isset( $errors['service_type_id'] ) ? 'aria-invalid="true"' : ''; ?>
							<?php
							$service_desc = $described_by( 'service_type_id', $errors, $warnings, $field_notes, '' !== $service_hint ? 'jmrs-prepare-service-hint' : '' );
							echo '' !== $service_desc ? 'aria-describedby="' . esc_attr( $service_desc ) . '"' : '';
							?>
						>
							<option value=""><?php echo esc_html__( 'Select service type', 'jm-referral-system' ); ?></option>
							<?php foreach ( $service_types as $service_type ) : ?>
								<?php
								$service_id = absint( $service_type['id'] ?? 0 );
								if ( $service_id <= 0 ) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr( (string) $service_id ); ?>" <?php selected( $val( $values, 'service_type_id' ), (string) $service_id ); ?>>
									<?php echo esc_html( (string) ( $service_type['name'] ?? '' ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( '' !== $service_hint ) : ?>
							<p class="jmrs-prepare-hint" id="jmrs-prepare-service-hint">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: advisory service hint label */
										__( 'JMRS suggestion: %s', 'jm-referral-system' ),
										$service_hint
									)
								);
								?>
							</p>
						<?php endif; ?>
						<?php if ( isset( $warnings['service_type_id'] ) ) : ?>
							<p class="jmrs-prepare-hint" id="jmrs-prepare-warning-service_type_id"><?php echo esc_html( (string) $warnings['service_type_id'] ); ?></p>
						<?php endif; ?>
						<?php if ( isset( $errors['service_type_id'] ) ) : ?>
							<p class="jmrs-portal-field-error" id="jmrs-prepare-error-service_type_id"><?php echo esc_html( (string) $errors['service_type_id'] ); ?></p>
						<?php endif; ?>
					</div>

					<div class="jmrs-portal-field">
						<label for="jmrs_prepare_referral_source">
							<?php echo esc_html__( 'Referral Source', 'jm-referral-system' ); ?>
							<span aria-hidden="true">*</span>
							<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
						</label>
						<select
							name="jmrs_prepare_referral_source"
							id="jmrs_prepare_referral_source"
							aria-required="true"
							<?php echo isset( $errors['referral_source'] ) ? 'aria-invalid="true"' : ''; ?>
							<?php
							$source_desc = $described_by( 'referral_source', $errors, $warnings, $field_notes );
							echo '' !== $source_desc ? 'aria-describedby="' . esc_attr( $source_desc ) . '"' : '';
							?>
						>
							<option value=""><?php echo esc_html__( 'Select referral source', 'jm-referral-system' ); ?></option>
							<?php foreach ( $referral_sources as $source_value => $source_label ) : ?>
								<option value="<?php echo esc_attr( (string) $source_value ); ?>" <?php selected( $val( $values, 'referral_source' ), (string) $source_value ); ?>>
									<?php echo esc_html( (string) $source_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( isset( $errors['referral_source'] ) ) : ?>
							<p class="jmrs-portal-field-error" id="jmrs-prepare-error-referral_source"><?php echo esc_html( (string) $errors['referral_source'] ); ?></p>
						<?php endif; ?>
					</div>

					<div class="jmrs-portal-field">
						<label for="jmrs_prepare_priority">
							<?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?>
							<span aria-hidden="true">*</span>
							<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
						</label>
						<select
							name="jmrs_prepare_priority"
							id="jmrs_prepare_priority"
							aria-required="true"
							<?php echo isset( $errors['priority'] ) ? 'aria-invalid="true"' : ''; ?>
							<?php
							$priority_desc = $described_by( 'priority', $errors, $warnings, $field_notes, '' !== $priority_hint ? 'jmrs-prepare-priority-hint' : '' );
							echo '' !== $priority_desc ? 'aria-describedby="' . esc_attr( $priority_desc ) . '"' : '';
							?>
						>
							<option value=""><?php echo esc_html__( 'Select priority', 'jm-referral-system' ); ?></option>
							<?php foreach ( $priorities as $priority_value => $priority_label ) : ?>
								<option value="<?php echo esc_attr( (string) $priority_value ); ?>" <?php selected( $val( $values, 'priority' ), (string) $priority_value ); ?>>
									<?php echo esc_html( (string) $priority_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( '' !== $priority_hint ) : ?>
							<p class="jmrs-prepare-hint" id="jmrs-prepare-priority-hint">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: advisory priority hint label */
										__( 'JMRS suggestion: %s', 'jm-referral-system' ),
										$priority_hint
									)
								);
								?>
							</p>
						<?php endif; ?>
						<?php if ( isset( $warnings['priority'] ) ) : ?>
							<p class="jmrs-prepare-hint" id="jmrs-prepare-warning-priority"><?php echo esc_html( (string) $warnings['priority'] ); ?></p>
						<?php endif; ?>
						<?php if ( isset( $errors['priority'] ) ) : ?>
							<p class="jmrs-portal-field-error" id="jmrs-prepare-error-priority"><?php echo esc_html( (string) $errors['priority'] ); ?></p>
						<?php endif; ?>
					</div>

					<?php if ( $can_assign ) : ?>
						<div class="jmrs-portal-field">
							<label for="jmrs_prepare_assigned_to"><?php echo esc_html__( 'Assigned To', 'jm-referral-system' ); ?></label>
							<select
								name="jmrs_prepare_assigned_to"
								id="jmrs_prepare_assigned_to"
								<?php echo isset( $errors['assigned_to'] ) ? 'aria-invalid="true"' : ''; ?>
								<?php
								$assign_desc = $described_by( 'assigned_to', $errors, $warnings, $field_notes );
								echo '' !== $assign_desc ? 'aria-describedby="' . esc_attr( $assign_desc ) . '"' : '';
								?>
							>
								<option value="0"><?php echo esc_html__( 'Unassigned', 'jm-referral-system' ); ?></option>
								<?php foreach ( $assignable_users as $user ) : ?>
									<?php $user_id = absint( $user['id'] ?? 0 ); ?>
									<?php if ( $user_id <= 0 ) { continue; } ?>
									<option value="<?php echo esc_attr( (string) $user_id ); ?>" <?php selected( $val( $values, 'assigned_to' ), (string) $user_id ); ?>>
										<?php echo esc_html( (string) ( $user['display_name'] ?? '' ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php if ( isset( $errors['assigned_to'] ) ) : ?>
								<p class="jmrs-portal-field-error" id="jmrs-prepare-error-assigned_to"><?php echo esc_html( (string) $errors['assigned_to'] ); ?></p>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<div class="jmrs-portal-field jmrs-portal-field--full">
						<label for="jmrs_prepare_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
						<textarea name="jmrs_prepare_notes" id="jmrs_prepare_notes" rows="4" <?php echo isset( $errors['notes'] ) ? 'aria-invalid="true"' : ''; ?>><?php echo esc_textarea( $val( $values, 'notes' ) ); ?></textarea>
						<?php if ( isset( $errors['notes'] ) ) : ?>
							<p class="jmrs-portal-field-error" id="jmrs-prepare-error-notes"><?php echo esc_html( (string) $errors['notes'] ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<p class="jmrs-prepare-actions">
				<button type="submit" class="jmrs-button jmrs-button--primary">
					<?php echo esc_html__( 'Validate Details', 'jm-referral-system' ); ?>
				</button>
				<a class="jmrs-button" href="<?php echo esc_url( $detail_url ); ?>">
					<?php echo esc_html__( 'Back to Referral Inbox Item', 'jm-referral-system' ); ?>
				</a>
			</p>
		</form>
	<?php endif; ?>
</section>
