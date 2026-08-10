<?php
/**
 * Mark referral as Not Proceeding (admin + portal).
 *
 * @package JMReferral
 *
 * @var int    $referral_id
 * @var array  $non_proceeding_panel
 * @var string $form_action
 * @var string $context admin|portal
 * @var bool   $show_non_proceeding_form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$non_proceeding_panel = is_array( $non_proceeding_panel ?? null ) ? $non_proceeding_panel : array();
$referral_id          = isset( $referral_id ) ? absint( $referral_id ) : 0;
$form_action          = isset( $form_action ) ? (string) $form_action : '';
$context              = isset( $context ) ? (string) $context : 'admin';
$show_non_proceeding_form = ! empty( $show_non_proceeding_form );
$is_portal            = 'portal' === $context;

if ( $referral_id <= 0 || empty( $non_proceeding_panel['show_panel'] ) ) {
	return;
}

$can_mark   = ! empty( $non_proceeding_panel['can_mark'] );
$is_closed  = ! empty( $non_proceeding_panel['is_closed'] );
$reasons    = is_array( $non_proceeding_panel['reason_options'] ?? null ) ? $non_proceeding_panel['reason_options'] : array();
$suggested  = (string) ( $non_proceeding_panel['suggested_reason'] ?? '' );
?>
<div class="jmrs-non-proceeding" style="margin: 1.25em 0; padding: 1em 1.25em; border: 1px solid #d63638; background: #fcf0f1;">
	<h2 style="margin-top: 0;"><?php echo esc_html__( 'Not Proceeding', 'jm-referral-system' ); ?></h2>

	<?php if ( $is_closed ) : ?>
		<p>
			<strong><?php echo esc_html__( 'Pipeline:', 'jm-referral-system' ); ?></strong>
			<?php echo esc_html__( 'Not Proceeding', 'jm-referral-system' ); ?>
		</p>
		<?php if ( '' !== (string) ( $non_proceeding_panel['status_label'] ?? '' ) ) : ?>
			<p>
				<strong><?php echo esc_html__( 'Referral Status:', 'jm-referral-system' ); ?></strong>
				<?php echo esc_html( (string) $non_proceeding_panel['status_label'] ); ?>
			</p>
		<?php endif; ?>
		<?php if ( '' !== (string) ( $non_proceeding_panel['closed_reason_label'] ?? '' ) ) : ?>
			<p>
				<strong><?php echo esc_html__( 'Reason:', 'jm-referral-system' ); ?></strong>
				<?php echo esc_html( (string) $non_proceeding_panel['closed_reason_label'] ); ?>
			</p>
		<?php endif; ?>
		<?php if ( '' !== (string) ( $non_proceeding_panel['closed_at'] ?? '' ) ) : ?>
			<p>
				<strong><?php echo esc_html__( 'Recorded:', 'jm-referral-system' ); ?></strong>
				<?php echo esc_html( (string) $non_proceeding_panel['closed_at'] ); ?>
			</p>
		<?php endif; ?>
		<?php if ( '' !== (string) ( $non_proceeding_panel['closed_by_name'] ?? '' ) ) : ?>
			<p>
				<strong><?php echo esc_html__( 'Recorded By:', 'jm-referral-system' ); ?></strong>
				<?php echo esc_html( (string) $non_proceeding_panel['closed_by_name'] ); ?>
			</p>
		<?php endif; ?>
	<?php elseif ( $can_mark && ! $show_non_proceeding_form ) : ?>
		<p class="description"><?php echo esc_html__( 'Close the acquisition workflow without deleting existing records.', 'jm-referral-system' ); ?></p>
		<p>
			<?php if ( $is_portal ) : ?>
				<a class="jmrs-button" href="<?php echo esc_url( add_query_arg( 'jmrs_np_form', '1' ) ); ?>"><?php echo esc_html__( 'Mark as Not Proceeding', 'jm-referral-system' ); ?></a>
			<?php else : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'jmrs_np_form', '1' ) ); ?>"><?php echo esc_html__( 'Mark as Not Proceeding', 'jm-referral-system' ); ?></a>
			<?php endif; ?>
		</p>
	<?php elseif ( $can_mark && $show_non_proceeding_form ) : ?>
		<form method="post" action="<?php echo '' !== $form_action ? esc_url( $form_action ) : ''; ?>">
			<?php wp_nonce_field( 'jmrs_mark_not_proceeding_' . $referral_id, 'jmrs_mark_not_proceeding_nonce' ); ?>
			<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

			<p><strong><?php echo esc_html__( 'Mark Referral as Not Proceeding', 'jm-referral-system' ); ?></strong></p>
			<p class="description" style="color:#b32d2e;">
				<?php echo esc_html__( 'This closes the acquisition workflow for this referral. Existing records and documents will be retained.', 'jm-referral-system' ); ?>
			</p>
			<p>
				<label for="jmrs_np_reason"><strong><?php echo esc_html__( 'Reason', 'jm-referral-system' ); ?></strong></label><br />
				<select name="jmrs_np_reason" id="jmrs_np_reason" required>
					<option value=""><?php echo esc_html__( 'Select reason…', 'jm-referral-system' ); ?></option>
					<?php foreach ( $reasons as $code => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( $suggested, (string) $code ); ?>>
							<?php echo esc_html( (string) $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<?php if ( $is_portal ) : ?>
					<a class="jmrs-button" href="<?php echo esc_url( remove_query_arg( 'jmrs_np_form' ) ); ?>"><?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?></a>
					<button type="submit" name="jmrs_mark_not_proceeding" value="1" class="jmrs-button jmrs-button--primary">
						<?php echo esc_html__( 'Mark as Not Proceeding', 'jm-referral-system' ); ?>
					</button>
				<?php else : ?>
					<a class="button" href="<?php echo esc_url( remove_query_arg( 'jmrs_np_form' ) ); ?>"><?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?></a>
					<?php submit_button( __( 'Mark as Not Proceeding', 'jm-referral-system' ), 'primary', 'jmrs_mark_not_proceeding', false ); ?>
				<?php endif; ?>
			</p>
		</form>
	<?php endif; ?>
</div>
