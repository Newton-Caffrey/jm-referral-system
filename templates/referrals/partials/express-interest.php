<?php
/**
 * Express Interest response form (admin + portal).
 *
 * @package JMReferral
 *
 * @var int   $referral_id
 * @var array $interest_form
 * @var string $form_action Empty = current URL
 * @var string $context admin|portal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$interest_form = is_array( $interest_form ?? null ) ? $interest_form : array();
$referral_id   = isset( $referral_id ) ? absint( $referral_id ) : 0;
$form_action   = isset( $form_action ) ? (string) $form_action : '';
$context       = isset( $context ) ? (string) $context : 'admin';

if ( empty( $interest_form['can_express'] ) || $referral_id <= 0 ) {
	return;
}

$methods         = is_array( $interest_form['methods'] ?? null ) ? $interest_form['methods'] : array();
$default_method  = (string) ( $interest_form['default_method'] ?? 'phone' );
$email_available = ! empty( $interest_form['email_available'] );
$is_portal       = 'portal' === $context;
?>
<div class="jmrs-express-interest" style="margin: 1.25em 0; padding: 1em 1.25em; border: 1px solid #2271b1; background: #f0f6fc;">
	<h2 style="margin-top: 0;"><?php echo esc_html__( 'Express Interest', 'jm-referral-system' ); ?></h2>
	<p><?php echo esc_html__( 'Confirm that JM Healthcare wishes to progress this referral, and record how the Local Authority / referrer was notified.', 'jm-referral-system' ); ?></p>

	<p>
		<strong><?php echo esc_html__( 'Referral number:', 'jm-referral-system' ); ?></strong>
		<?php echo esc_html( (string) ( $interest_form['referral_number'] ?? '' ) ); ?>
	</p>
	<?php if ( '' !== (string) ( $interest_form['submitted_at'] ?? '' ) ) : ?>
		<p>
			<strong><?php echo esc_html__( 'Submitted:', 'jm-referral-system' ); ?></strong>
			<?php echo esc_html( (string) $interest_form['submitted_at'] ); ?>
		</p>
	<?php endif; ?>
	<p>
		<strong><?php echo esc_html__( 'Referrer:', 'jm-referral-system' ); ?></strong>
		<?php echo esc_html( (string) ( $interest_form['referrer_name'] ?? '' ) ); ?>
		<?php if ( '' !== (string) ( $interest_form['referrer_organisation'] ?? '' ) ) : ?>
			— <?php echo esc_html( (string) $interest_form['referrer_organisation'] ); ?>
		<?php endif; ?>
	</p>
	<p>
		<strong><?php echo esc_html__( 'Email:', 'jm-referral-system' ); ?></strong>
		<?php echo '' !== (string) ( $interest_form['referrer_email'] ?? '' ) ? esc_html( (string) $interest_form['referrer_email'] ) : esc_html__( 'Not available', 'jm-referral-system' ); ?>
	</p>
	<p>
		<strong><?php echo esc_html__( 'Phone:', 'jm-referral-system' ); ?></strong>
		<?php echo '' !== (string) ( $interest_form['referrer_phone'] ?? '' ) ? esc_html( (string) $interest_form['referrer_phone'] ) : esc_html__( 'Not available', 'jm-referral-system' ); ?>
	</p>

	<?php if ( ! $email_available ) : ?>
		<p class="description"><?php echo esc_html__( 'No valid referrer email is on file. Use phone or another communication method.', 'jm-referral-system' ); ?></p>
	<?php endif; ?>

	<form method="post" action="<?php echo '' !== $form_action ? esc_url( $form_action ) : ''; ?>">
		<?php wp_nonce_field( 'jmrs_express_interest_' . $referral_id, 'jmrs_express_interest_nonce' ); ?>
		<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

		<p><strong><?php echo esc_html__( 'Response method', 'jm-referral-system' ); ?></strong></p>
		<?php foreach ( $methods as $method_value => $method_label ) : ?>
			<label style="display:block; margin: 0.35em 0;">
				<input
					type="radio"
					name="jmrs_interest_method"
					value="<?php echo esc_attr( (string) $method_value ); ?>"
					<?php checked( $default_method, (string) $method_value ); ?>
					required
				/>
				<?php echo esc_html( (string) $method_label ); ?>
			</label>
		<?php endforeach; ?>

		<?php if ( $email_available ) : ?>
			<p class="description"><?php echo esc_html__( 'Email: sends the standard interest confirmation to the referrer email above. “Sent” means accepted for sending, not delivery proof.', 'jm-referral-system' ); ?></p>
		<?php endif; ?>

		<p>
			<label>
				<input type="checkbox" name="jmrs_interest_confirmed" value="1" />
				<?php echo esc_html__( 'I confirm that JM Healthcare’s interest has been communicated to the referrer (required for Phone / Other).', 'jm-referral-system' ); ?>
			</label>
		</p>

		<p>
			<label for="jmrs_interest_other_note"><?php echo esc_html__( 'Other channel reference (optional, short)', 'jm-referral-system' ); ?></label><br />
			<input
				type="text"
				name="jmrs_interest_other_note"
				id="jmrs_interest_other_note"
				class="<?php echo $is_portal ? '' : 'regular-text'; ?>"
				maxlength="190"
				placeholder="<?php echo esc_attr__( 'e.g. LA portal / secure message', 'jm-referral-system' ); ?>"
			/>
		</p>

		<?php
		if ( $is_portal ) {
			echo '<button type="submit" name="jmrs_express_interest" value="1" class="jmrs-button jmrs-button--primary">';
			echo esc_html__( 'Express Interest', 'jm-referral-system' );
			echo '</button>';
		} else {
			submit_button(
				__( 'Express Interest', 'jm-referral-system' ),
				'primary',
				'jmrs_express_interest',
				false
			);
		}
		?>
	</form>
</div>
