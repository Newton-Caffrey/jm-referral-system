<?php
/**
 * Package Cost prepare / send panel (admin + portal).
 *
 * @package JMReferral
 *
 * @var int    $referral_id
 * @var array  $package_cost_panel
 * @var string $form_action
 * @var string $context admin|portal
 * @var array  $package_cost_errors
 * @var bool   $show_prepare_form
 * @var bool   $show_send_form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$package_cost_panel  = is_array( $package_cost_panel ?? null ) ? $package_cost_panel : array();
$referral_id         = isset( $referral_id ) ? absint( $referral_id ) : 0;
$form_action         = isset( $form_action ) ? (string) $form_action : '';
$context             = isset( $context ) ? (string) $context : 'admin';
$package_cost_errors = is_array( $package_cost_errors ?? null ) ? $package_cost_errors : array();
$show_prepare_form   = ! empty( $show_prepare_form );
$show_send_form      = ! empty( $show_send_form );
$is_portal           = 'portal' === $context;

if ( $referral_id <= 0 || empty( $package_cost_panel['show_panel'] ) ) {
	return;
}

$can_prepare      = ! empty( $package_cost_panel['can_prepare'] );
$can_send         = ! empty( $package_cost_panel['can_send'] );
$can_edit         = ! empty( $package_cost_panel['can_edit'] );
$is_prepared      = ! empty( $package_cost_panel['is_prepared'] );
$is_sent          = ! empty( $package_cost_panel['is_sent'] );
$has_record       = ! empty( $package_cost_panel['has_record'] );
$email_available  = ! empty( $package_cost_panel['email_available'] );
$send_methods     = is_array( $package_cost_panel['send_methods'] ?? null ) ? $package_cost_panel['send_methods'] : array();
$default_method   = $email_available && isset( $send_methods['email'] ) ? 'email' : (string) array_key_first( $send_methods );

$field = static function ( string $key ) use ( $package_cost_panel ): string {
	return (string) ( $package_cost_panel[ $key ] ?? '' );
};

// First-time prepare: show form immediately.
if ( $can_prepare && ! $has_record ) {
	$show_prepare_form = true;
}

$render_prepare_form = $can_prepare && $show_prepare_form;
?>
<div class="jmrs-package-cost" style="margin: 1.25em 0; padding: 1em 1.25em; border: 1px solid #2271b1; background: #f0f6fc;">
	<h2 style="margin-top: 0;"><?php echo esc_html__( 'Package Cost', 'jm-referral-system' ); ?></h2>

	<p>
		<strong><?php echo esc_html__( 'Status:', 'jm-referral-system' ); ?></strong>
		<?php echo esc_html( $field( 'status_label' ) ); ?>
	</p>

	<?php if ( $is_sent ) : ?>
		<p class="jmrs-package-cost__terminal" role="status" style="margin: 0.75em 0; padding: 0.65em 0.85em; border-left: 3px solid #646970; background: #f6f7f7;">
			<strong><?php echo esc_html__( 'Sent — read-only.', 'jm-referral-system' ); ?></strong>
			<?php echo esc_html__( 'This Package Cost has been submitted and cannot be edited. There is no reopen or revision workflow in this release.', 'jm-referral-system' ); ?>
		</p>
	<?php endif; ?>

	<?php if ( $is_prepared || $is_sent ) : ?>
		<?php if ( '' !== $field( 'prepared_at' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Prepared:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'prepared_at' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $field( 'prepared_by_name' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Prepared By:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'prepared_by_name' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $field( 'package_total_display' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Proposed Package Total:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'package_total_display' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $field( 'document_name' ) ) : ?>
			<p>
				<strong><?php echo esc_html__( 'Document:', 'jm-referral-system' ); ?></strong>
				<?php echo esc_html( $field( 'document_name' ) ); ?>
				<?php if ( '' !== $field( 'document_download_url' ) ) : ?>
					—
					<a href="<?php echo esc_url( $field( 'document_download_url' ) ); ?>">
						<?php echo esc_html__( 'View / Download', 'jm-referral-system' ); ?>
					</a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $is_sent ) : ?>
		<?php if ( '' !== $field( 'send_method_label' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Method:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'send_method_label' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $field( 'recipient' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Recipient:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'recipient' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $field( 'sent_at' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Sent:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'sent_at' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $field( 'sent_by_name' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Sent By:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'sent_by_name' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $field( 'email_status_label' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Email Status:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'email_status_label' ) ); ?></p>
			<p class="description"><?php echo esc_html__( '“Sent” means the mailer accepted the message for sending. It is not delivery or read proof.', 'jm-referral-system' ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $field( 'submission_reference' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Reference:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'submission_reference' ) ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! empty( $package_cost_errors ) ) : ?>
		<ul style="color:#b32d2e;">
			<?php foreach ( $package_cost_errors as $err ) : ?>
				<li><?php echo esc_html( (string) $err ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $render_prepare_form ) : ?>
		<form method="post" enctype="multipart/form-data" action="<?php echo '' !== $form_action ? esc_url( $form_action ) : ''; ?>" style="margin-top: 1em;">
			<?php wp_nonce_field( 'jmrs_prepare_package_cost_' . $referral_id, 'jmrs_prepare_package_cost_nonce' ); ?>
			<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
			<p>
				<label for="jmrs_package_total"><strong><?php echo esc_html__( 'Proposed Package Total', 'jm-referral-system' ); ?></strong></label><br />
				<span>£</span>
				<input
					type="text"
					name="jmrs_package_total"
					id="jmrs_package_total"
					inputmode="decimal"
					class="<?php echo $is_portal ? '' : 'regular-text'; ?>"
					value="<?php echo esc_attr( $field( 'package_total' ) ); ?>"
					placeholder="2450.00"
				/>
			</p>
			<p class="description"><?php echo esc_html__( 'Optional. Enter the proposed package amount in GBP. Do not include billing frequency in the number.', 'jm-referral-system' ); ?></p>
			<p>
				<label for="jmrs_package_cost_document"><strong><?php echo esc_html__( 'Package Cost Document', 'jm-referral-system' ); ?></strong></label><br />
				<input type="file" name="jmrs_package_cost_document" id="jmrs_package_cost_document" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" <?php echo $has_record ? '' : 'required'; ?> />
			</p>
			<p class="description"><?php echo esc_html__( 'PDF, DOC, or DOCX. Required for first preparation; optional when replacing before send.', 'jm-referral-system' ); ?></p>
			<?php
			$label = $has_record ? __( 'Update Package Cost', 'jm-referral-system' ) : __( 'Save Package Cost', 'jm-referral-system' );
			if ( $is_portal ) {
				echo '<button type="submit" name="jmrs_prepare_package_cost" value="1" class="jmrs-button jmrs-button--primary">';
				echo esc_html( $label );
				echo '</button>';
			} else {
				submit_button( $label, 'primary', 'jmrs_prepare_package_cost', false );
			}
			?>
		</form>
	<?php elseif ( $can_prepare && ! $has_record ) : ?>
		<p>
			<?php if ( $is_portal ) : ?>
				<a class="jmrs-button jmrs-button--primary" href="<?php echo esc_url( add_query_arg( 'jmrs_pc_prepare', '1' ) ); ?>"><?php echo esc_html__( 'Prepare Package Cost', 'jm-referral-system' ); ?></a>
			<?php else : ?>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'jmrs_pc_prepare', '1' ) ); ?>"><?php echo esc_html__( 'Prepare Package Cost', 'jm-referral-system' ); ?></a>
			<?php endif; ?>
		</p>
	<?php elseif ( $can_edit && ! $render_prepare_form ) : ?>
		<p>
			<?php if ( $is_portal ) : ?>
				<a class="jmrs-button" href="<?php echo esc_url( add_query_arg( 'jmrs_pc_prepare', '1' ) ); ?>"><?php echo esc_html__( 'Edit Before Sending', 'jm-referral-system' ); ?></a>
			<?php else : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'jmrs_pc_prepare', '1' ) ); ?>"><?php echo esc_html__( 'Edit Before Sending', 'jm-referral-system' ); ?></a>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php if ( $can_send && ! $show_send_form ) : ?>
		<p>
			<?php if ( $is_portal ) : ?>
				<a class="jmrs-button jmrs-button--primary" href="<?php echo esc_url( add_query_arg( 'jmrs_pc_send', '1' ) ); ?>"><?php echo esc_html__( 'Send Package Cost', 'jm-referral-system' ); ?></a>
			<?php else : ?>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'jmrs_pc_send', '1' ) ); ?>"><?php echo esc_html__( 'Send Package Cost', 'jm-referral-system' ); ?></a>
			<?php endif; ?>
		</p>
	<?php elseif ( $can_send && $show_send_form ) : ?>
		<form method="post" action="<?php echo '' !== $form_action ? esc_url( $form_action ) : ''; ?>" style="margin-top: 1em; padding-top: 0.75em; border-top: 1px solid #c3c4c7;" id="jmrs-package-cost-send-form">
			<?php wp_nonce_field( 'jmrs_send_package_cost_' . $referral_id, 'jmrs_send_package_cost_nonce' ); ?>
			<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

			<p>
				<strong><?php echo esc_html__( 'Local Authority / Referrer:', 'jm-referral-system' ); ?></strong>
				<?php echo esc_html( $field( 'referrer_name' ) ); ?>
				<?php if ( '' !== $field( 'referrer_organisation' ) ) : ?>
					— <?php echo esc_html( $field( 'referrer_organisation' ) ); ?>
				<?php endif; ?>
			</p>
			<?php if ( '' !== $field( 'package_total_display' ) ) : ?>
				<p>
					<strong><?php echo esc_html__( 'Package Total:', 'jm-referral-system' ); ?></strong>
					<?php echo esc_html( $field( 'package_total_display' ) ); ?>
				</p>
			<?php endif; ?>
			<p>
				<strong><?php echo esc_html__( 'Package Cost Document:', 'jm-referral-system' ); ?></strong>
				<?php echo esc_html( $field( 'document_name' ) ); ?>
			</p>

			<?php if ( ! $email_available ) : ?>
				<p class="description"><?php echo esc_html( $field( 'email_unavailable_note' ) ); ?></p>
			<?php endif; ?>

			<p><strong><?php echo esc_html__( 'Submission Method', 'jm-referral-system' ); ?></strong></p>
			<?php foreach ( $send_methods as $method_value => $method_label ) : ?>
				<label style="display:block; margin: 0.35em 0;">
					<input
						type="radio"
						name="jmrs_package_send_method"
						class="jmrs-pc-method"
						value="<?php echo esc_attr( (string) $method_value ); ?>"
						required
						<?php checked( $default_method, (string) $method_value ); ?>
					/>
					<?php echo esc_html( (string) $method_label ); ?>
				</label>
			<?php endforeach; ?>

			<div class="jmrs-pc-email-panel" style="margin: 0.75em 0; padding: 0.75em; background: #fff; border-left: 3px solid #2271b1;" <?php echo ( 'email' === $default_method ) ? '' : 'hidden'; ?>>
				<p><strong><?php echo esc_html__( 'JMRS will send this email:', 'jm-referral-system' ); ?></strong></p>
				<p>
					<strong><?php echo esc_html__( 'Recipient:', 'jm-referral-system' ); ?></strong>
					<?php echo esc_html( $field( 'referrer_email' ) ); ?>
				</p>
				<p>
					<strong><?php echo esc_html__( 'Attachment:', 'jm-referral-system' ); ?></strong>
					<?php echo esc_html( $field( 'document_name' ) ); ?>
				</p>
				<p class="description"><?php echo esc_html__( 'Clicking the button below WILL SEND the email with the current Package Cost document attached. “Sent” means accepted for sending, not delivery proof.', 'jm-referral-system' ); ?></p>
			</div>

			<div class="jmrs-pc-manual-panel" style="margin: 0.75em 0;" <?php echo ( 'email' === $default_method ) ? 'hidden' : ''; ?>>
				<p class="jmrs-pc-portal-note description" <?php echo ( 'secure_portal' === $default_method ) ? '' : 'hidden'; ?>>
					<?php echo esc_html__( 'Submit the Package Cost through the Local Authority portal, then confirm below.', 'jm-referral-system' ); ?>
				</p>
				<p class="jmrs-pc-other-note description" <?php echo ( 'other' === $default_method ) ? '' : 'hidden'; ?>>
					<?php echo esc_html__( 'Submit the Package Cost through another approved document-capable channel, then confirm below.', 'jm-referral-system' ); ?>
				</p>
				<p>
					<label for="jmrs_package_recipient"><strong><?php echo esc_html__( 'Recipient / Destination (optional)', 'jm-referral-system' ); ?></strong></label><br />
					<input
						type="text"
						name="jmrs_package_recipient"
						id="jmrs_package_recipient"
						maxlength="190"
						class="<?php echo $is_portal ? '' : 'regular-text'; ?>"
						placeholder="<?php echo esc_attr__( 'Portal name or destination', 'jm-referral-system' ); ?>"
					/>
				</p>
				<p>
					<label for="jmrs_package_submission_reference"><?php echo esc_html__( 'Submission Reference (optional)', 'jm-referral-system' ); ?></label><br />
					<input
						type="text"
						name="jmrs_package_submission_reference"
						id="jmrs_package_submission_reference"
						maxlength="190"
						class="<?php echo $is_portal ? '' : 'regular-text'; ?>"
					/>
				</p>
				<p>
					<label>
						<input type="checkbox" name="jmrs_package_sent_confirmed" id="jmrs_package_sent_confirmed" value="1" <?php echo ( 'email' === $default_method ) ? '' : 'required'; ?> />
						<span class="jmrs-pc-confirm-portal" <?php echo ( 'secure_portal' === $default_method ) ? '' : 'hidden'; ?>>
							<?php echo esc_html__( 'I confirm the Package Cost has been submitted.', 'jm-referral-system' ); ?>
						</span>
						<span class="jmrs-pc-confirm-other" <?php echo ( 'other' === $default_method ) ? '' : 'hidden'; ?>>
							<?php echo esc_html__( 'I confirm the Package Cost has been submitted through another approved channel.', 'jm-referral-system' ); ?>
						</span>
						<span class="jmrs-pc-confirm-fallback" <?php echo in_array( $default_method, array( 'secure_portal', 'other' ), true ) ? 'hidden' : ''; ?>>
							<?php echo esc_html__( 'I confirm that the Package Cost has been submitted to the Local Authority.', 'jm-referral-system' ); ?>
						</span>
					</label>
				</p>
			</div>

			<?php
			$submit_email  = __( 'Send Package Cost by Email', 'jm-referral-system' );
			$submit_portal = __( 'Record Portal Submission', 'jm-referral-system' );
			$submit_other  = __( 'Record Submission', 'jm-referral-system' );
			$initial_label = 'email' === $default_method
				? $submit_email
				: ( 'secure_portal' === $default_method ? $submit_portal : $submit_other );

			if ( $is_portal ) {
				echo '<button type="submit" name="jmrs_send_package_cost" value="1" class="jmrs-button jmrs-button--primary" id="jmrs-pc-submit">';
				echo esc_html( $initial_label );
				echo '</button>';
			} else {
				submit_button( $initial_label, 'primary', 'jmrs_send_package_cost', false, array( 'id' => 'jmrs-pc-submit' ) );
			}
			?>
		</form>
		<script>
		(function () {
			var form = document.getElementById('jmrs-package-cost-send-form');
			if (!form) { return; }
			var emailPanel = form.querySelector('.jmrs-pc-email-panel');
			var manualPanel = form.querySelector('.jmrs-pc-manual-panel');
			var confirm = document.getElementById('jmrs-package_sent_confirmed');
			var submit = document.getElementById('jmrs-pc-submit');
			var labels = {
				email: <?php echo wp_json_encode( $submit_email ); ?>,
				secure_portal: <?php echo wp_json_encode( $submit_portal ); ?>,
				other: <?php echo wp_json_encode( $submit_other ); ?>
			};
			function sync() {
				var selected = form.querySelector('input[name="jmrs_package_send_method"]:checked');
				var method = selected ? selected.value : '';
				if (emailPanel) { emailPanel.hidden = method !== 'email'; }
				if (manualPanel) { manualPanel.hidden = method === 'email'; }
				form.querySelectorAll('.jmrs-pc-portal-note').forEach(function (el) { el.hidden = method !== 'secure_portal'; });
				form.querySelectorAll('.jmrs-pc-other-note').forEach(function (el) { el.hidden = method !== 'other'; });
				form.querySelectorAll('.jmrs-pc-confirm-portal').forEach(function (el) { el.hidden = method !== 'secure_portal'; });
				form.querySelectorAll('.jmrs-pc-confirm-other').forEach(function (el) { el.hidden = method !== 'other'; });
				form.querySelectorAll('.jmrs-pc-confirm-fallback').forEach(function (el) {
					el.hidden = method === 'secure_portal' || method === 'other' || method === 'email';
				});
				if (confirm) {
					confirm.required = method !== 'email';
					if (method === 'email') { confirm.checked = false; }
				}
				if (submit && labels[method]) {
					if (submit.tagName === 'INPUT') { submit.value = labels[method]; }
					else { submit.textContent = labels[method]; }
				}
			}
			form.querySelectorAll('.jmrs-pc-method').forEach(function (el) {
				el.addEventListener('change', sync);
			});
			sync();
		})();
		</script>
	<?php endif; ?>
</div>
