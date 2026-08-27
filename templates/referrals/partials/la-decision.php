<?php
/**
 * Local Authority Decision panel (admin + portal).
 *
 * @package JMReferral
 *
 * @var int    $referral_id
 * @var array  $la_decision_panel
 * @var string $form_action
 * @var string $context admin|portal
 * @var array  $la_decision_errors
 * @var bool   $show_la_decision_form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$la_decision_panel  = is_array( $la_decision_panel ?? null ) ? $la_decision_panel : array();
$referral_id        = isset( $referral_id ) ? absint( $referral_id ) : 0;
$form_action        = isset( $form_action ) ? (string) $form_action : '';
$context            = isset( $context ) ? (string) $context : 'admin';
$la_decision_errors = is_array( $la_decision_errors ?? null ) ? $la_decision_errors : array();
$show_la_decision_form = ! empty( $show_la_decision_form );
$is_portal          = 'portal' === $context;

if ( $referral_id <= 0 || empty( $la_decision_panel['show_panel'] ) ) {
	return;
}

$can_record   = ! empty( $la_decision_panel['can_record'] );
$has_decision = ! empty( $la_decision_panel['has_decision'] );
$decision_options = is_array( $la_decision_panel['decision_options'] ?? null ) ? $la_decision_panel['decision_options'] : array();
$declined_reasons = is_array( $la_decision_panel['declined_reasons'] ?? null ) ? $la_decision_panel['declined_reasons'] : array();
$np_reasons       = is_array( $la_decision_panel['not_proceeding_reasons'] ?? null ) ? $la_decision_panel['not_proceeding_reasons'] : array();
$funding_options  = is_array( $la_decision_panel['funding_options'] ?? null ) ? $la_decision_panel['funding_options'] : array();

$field = static function ( string $key ) use ( $la_decision_panel ): string {
	return (string) ( $la_decision_panel[ $key ] ?? '' );
};
?>
<div class="jmrs-la-decision" style="margin: 1.25em 0; padding: 1em 1.25em; border: 1px solid #2271b1; background: #f0f6fc;">
	<h2 style="margin-top: 0;"><?php echo esc_html__( 'Local Authority Decision', 'jm-referral-system' ); ?></h2>

	<?php if ( '' !== $field( 'package_cost_sent_at' ) ) : ?>
		<p>
			<strong><?php echo esc_html__( 'Package Cost:', 'jm-referral-system' ); ?></strong>
			<?php echo esc_html__( 'Sent', 'jm-referral-system' ); ?>
			<?php echo esc_html( $field( 'package_cost_sent_at' ) ); ?>
		</p>
		<?php if ( '' !== $field( 'package_cost_method' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Method:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'package_cost_method' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $field( 'package_cost_recipient' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Submitted To:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'package_cost_recipient' ) ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $has_decision ) : ?>
		<p class="jmrs-la-decision__terminal" role="status" style="margin: 0.75em 0; padding: 0.65em 0.85em; border-left: 3px solid #646970; background: #f6f7f7;">
			<strong><?php echo esc_html__( 'Recorded — read-only.', 'jm-referral-system' ); ?></strong>
			<?php echo esc_html__( 'This Local Authority decision has been recorded and cannot be edited. There is no reconsideration or reopen workflow in this release.', 'jm-referral-system' ); ?>
		</p>
		<p>
			<strong><?php echo esc_html__( 'Decision:', 'jm-referral-system' ); ?></strong>
			<?php echo esc_html( $field( 'decision_label' ) ); ?>
		</p>
		<?php if ( '' !== $field( 'decision_at' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Decision Date:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'decision_at' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $field( 'recorded_by_name' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Recorded By:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'recorded_by_name' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $field( 'recorded_at' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Recorded Date:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'recorded_at' ) ); ?></p>
		<?php endif; ?>
		<?php if ( 'approved' === $field( 'decision' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Funding Confirmed:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'funding_confirmed_label' ) ); ?></p>
			<?php if ( '' !== $field( 'funding_reference' ) ) : ?>
				<p><strong><?php echo esc_html__( 'Funding Reference:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'funding_reference' ) ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( '' !== $field( 'reason_label' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Reason:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'reason_label' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $field( 'decision_reference' ) ) : ?>
			<p><strong><?php echo esc_html__( 'Decision Reference:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $field( 'decision_reference' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== trim( $field( 'notes' ) ) ) : ?>
			<p>
				<strong><?php echo esc_html__( 'Notes:', 'jm-referral-system' ); ?></strong><br />
				<span class="jmrs-la-decision__notes" style="white-space: pre-wrap; word-break: break-word;"><?php echo esc_html( $field( 'notes' ) ); ?></span>
			</p>
		<?php endif; ?>
	<?php elseif ( ! empty( $la_decision_panel['is_awaiting'] ) ) : ?>
		<p>
			<strong><?php echo esc_html__( 'Current Status:', 'jm-referral-system' ); ?></strong>
			<?php echo esc_html__( 'Awaiting Decision', 'jm-referral-system' ); ?>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $la_decision_errors ) ) : ?>
		<ul style="color:#b32d2e;">
			<?php foreach ( $la_decision_errors as $err ) : ?>
				<li><?php echo esc_html( (string) $err ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $can_record && ! $show_la_decision_form ) : ?>
		<p>
			<?php if ( $is_portal ) : ?>
				<a class="jmrs-button jmrs-button--primary" href="<?php echo esc_url( add_query_arg( 'jmrs_la_decide', '1' ) ); ?>"><?php echo esc_html__( 'Record Local Authority Decision', 'jm-referral-system' ); ?></a>
			<?php else : ?>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'jmrs_la_decide', '1' ) ); ?>"><?php echo esc_html__( 'Record Local Authority Decision', 'jm-referral-system' ); ?></a>
			<?php endif; ?>
		</p>
	<?php elseif ( $can_record && $show_la_decision_form ) : ?>
		<form method="post" action="<?php echo '' !== $form_action ? esc_url( $form_action ) : ''; ?>" style="margin-top: 1em; padding-top: 0.75em; border-top: 1px solid #c3c4c7;" id="jmrs-la-decision-form">
			<?php wp_nonce_field( 'jmrs_record_la_decision_' . $referral_id, 'jmrs_record_la_decision_nonce' ); ?>
			<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

			<p><strong><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></strong></p>
			<?php foreach ( $decision_options as $value => $label ) : ?>
				<label style="display:block; margin: 0.35em 0;">
					<input type="radio" name="jmrs_la_decision" class="jmrs-la-outcome" value="<?php echo esc_attr( (string) $value ); ?>" required <?php checked( 'approved', (string) $value ); ?> />
					<?php echo esc_html( (string) $label ); ?>
				</label>
			<?php endforeach; ?>

			<p>
				<label for="jmrs_la_decision_at"><strong><?php echo esc_html__( 'Decision Date / Time', 'jm-referral-system' ); ?></strong></label><br />
				<input
					type="datetime-local"
					name="jmrs_la_decision_at"
					id="jmrs_la_decision_at"
					required
					value="<?php echo esc_attr( $field( 'default_decision_at' ) ); ?>"
				/>
			</p>

			<div class="jmrs-la-approved-fields" style="margin: 0.75em 0;">
				<p><strong><?php echo esc_html__( 'Funding Confirmed', 'jm-referral-system' ); ?></strong></p>
				<?php foreach ( $funding_options as $fvalue => $flabel ) : ?>
					<label style="display:block; margin: 0.25em 0;">
						<input type="radio" name="jmrs_la_funding_confirmed" value="<?php echo esc_attr( (string) $fvalue ); ?>" <?php checked( 'not_recorded', (string) $fvalue ); ?> />
						<?php echo esc_html( (string) $flabel ); ?>
					</label>
				<?php endforeach; ?>
				<p>
					<label for="jmrs_la_funding_reference"><?php echo esc_html__( 'Funding Reference (optional)', 'jm-referral-system' ); ?></label><br />
					<input type="text" name="jmrs_la_funding_reference" id="jmrs_la_funding_reference" maxlength="190" class="<?php echo $is_portal ? '' : 'regular-text'; ?>" />
				</p>
				<p>
					<label for="jmrs_la_decision_reference"><?php echo esc_html__( 'Decision Reference (optional)', 'jm-referral-system' ); ?></label><br />
					<input type="text" name="jmrs_la_decision_reference" id="jmrs_la_decision_reference" maxlength="190" class="<?php echo $is_portal ? '' : 'regular-text'; ?>" />
				</p>
			</div>

			<div class="jmrs-la-declined-fields" style="margin: 0.75em 0;" hidden>
				<p>
					<label for="jmrs_la_declined_reason"><strong><?php echo esc_html__( 'Reason', 'jm-referral-system' ); ?></strong></label><br />
					<select name="jmrs_la_declined_reason" id="jmrs_la_declined_reason">
						<option value=""><?php echo esc_html__( 'Select reason…', 'jm-referral-system' ); ?></option>
						<?php foreach ( $declined_reasons as $rvalue => $rlabel ) : ?>
							<option value="<?php echo esc_attr( (string) $rvalue ); ?>"><?php echo esc_html( (string) $rlabel ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="jmrs_la_declined_reference"><?php echo esc_html__( 'Decision Reference (optional)', 'jm-referral-system' ); ?></label><br />
					<input type="text" name="jmrs_la_declined_reference" id="jmrs_la_declined_reference" maxlength="190" class="<?php echo $is_portal ? '' : 'regular-text'; ?>" />
				</p>
			</div>

			<div class="jmrs-la-np-fields" style="margin: 0.75em 0;" hidden>
				<p>
					<label for="jmrs_la_np_reason"><strong><?php echo esc_html__( 'Reason', 'jm-referral-system' ); ?></strong></label><br />
					<select name="jmrs_la_np_reason" id="jmrs_la_np_reason">
						<option value=""><?php echo esc_html__( 'Select reason…', 'jm-referral-system' ); ?></option>
						<?php foreach ( $np_reasons as $rvalue => $rlabel ) : ?>
							<option value="<?php echo esc_attr( (string) $rvalue ); ?>"><?php echo esc_html( (string) $rlabel ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>

			<p>
				<label for="jmrs_la_notes"><?php echo esc_html__( 'Notes (optional, short operational only)', 'jm-referral-system' ); ?></label><br />
				<textarea name="jmrs_la_notes" id="jmrs_la_notes" rows="3" maxlength="500" class="<?php echo $is_portal ? '' : 'large-text'; ?>"></textarea>
			</p>
			<p class="description"><?php echo esc_html__( 'Do not record clinical detail here. Notes are not copied to the activity timeline.', 'jm-referral-system' ); ?></p>

			<?php
			if ( $is_portal ) {
				echo '<button type="submit" name="jmrs_record_la_decision" value="1" class="jmrs-button jmrs-button--primary">';
				echo esc_html__( 'Record Decision', 'jm-referral-system' );
				echo '</button>';
			} else {
				submit_button( __( 'Record Decision', 'jm-referral-system' ), 'primary', 'jmrs_record_la_decision', false );
			}
			?>
		</form>
		<script>
		(function () {
			var form = document.getElementById('jmrs-la-decision-form');
			if (!form) { return; }
			var approved = form.querySelector('.jmrs-la-approved-fields');
			var declined = form.querySelector('.jmrs-la-declined-fields');
			var np = form.querySelector('.jmrs-la-np-fields');
			function sync() {
				var selected = form.querySelector('input[name="jmrs_la_decision"]:checked');
				var v = selected ? selected.value : 'approved';
				if (approved) { approved.hidden = v !== 'approved'; }
				if (declined) { declined.hidden = v !== 'declined'; }
				if (np) { np.hidden = v !== 'not_proceeding'; }
			}
			form.querySelectorAll('.jmrs-la-outcome').forEach(function (el) {
				el.addEventListener('change', sync);
			});
			sync();
		})();
		</script>
	<?php endif; ?>
</div>
