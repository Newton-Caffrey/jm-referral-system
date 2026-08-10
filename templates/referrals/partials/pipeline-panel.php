<?php
/**
 * Referral acquisition pipeline panel (admin + portal).
 *
 * @package JMReferral
 *
 * @var array{
 *     is_pipeline: bool,
 *     is_legacy: bool,
 *     stage_id: int,
 *     stage_slug: string,
 *     stage_label: string,
 *     entered_at: string|null,
 *     waiting_label: string|null,
 *     next_action: string,
 *     owner_name: string,
 *     can_override: bool,
 *     override_options: array<int, array{id: int, slug: string, name: string}>
 * } $pipeline_panel
 * @var int    $referral_id
 * @var string $override_form_action Optional form action URL (portal). Empty = current admin URL.
 * @var string $context admin|portal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pipeline_panel = is_array( $pipeline_panel ?? null ) ? $pipeline_panel : array();
$referral_id    = isset( $referral_id ) ? absint( $referral_id ) : 0;
$context        = isset( $context ) ? (string) $context : 'admin';
$override_form_action = isset( $override_form_action ) ? (string) $override_form_action : '';

$is_pipeline = ! empty( $pipeline_panel['is_pipeline'] );
$is_legacy   = ! empty( $pipeline_panel['is_legacy'] );
$stage_label = (string) ( $pipeline_panel['stage_label'] ?? '' );
$entered_at  = $pipeline_panel['entered_at'] ?? null;
$waiting     = $pipeline_panel['waiting_label'] ?? null;
$next_action = (string) ( $pipeline_panel['next_action'] ?? '' );
$owner_name  = (string) ( $pipeline_panel['owner_name'] ?? '' );
$can_override = ! empty( $pipeline_panel['can_override'] );
$override_options = is_array( $pipeline_panel['override_options'] ?? null )
	? $pipeline_panel['override_options']
	: array();
?>
<div class="jmrs-pipeline-panel" style="margin: 1.25em 0; padding: 1em 1.25em; border: 1px solid #c3c4c7; background: #fff;">
	<h2 style="margin-top: 0;"><?php echo esc_html__( 'Referral Pipeline', 'jm-referral-system' ); ?></h2>

	<?php if ( $is_legacy ) : ?>
		<p>
			<strong><?php echo esc_html__( 'Current Stage:', 'jm-referral-system' ); ?></strong>
			<?php echo esc_html( $stage_label ); ?>
			<span style="display: inline-block; margin-left: 0.5em; padding: 0.15em 0.5em; background: #f0f0f1; border-radius: 3px; font-size: 12px;">
				<?php echo esc_html__( 'Legacy workflow stage', 'jm-referral-system' ); ?>
			</span>
		</p>
		<p class="description">
			<?php echo esc_html__( 'This referral has not entered the acquisition pipeline. Use Override Pipeline Stage (Manager/Admin) to place it on a canonical stage.', 'jm-referral-system' ); ?>
		</p>
	<?php elseif ( $is_pipeline ) : ?>
		<p><strong><?php echo esc_html__( 'Current Stage:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $stage_label ); ?></p>
		<p>
			<strong><?php echo esc_html__( 'Time Entered:', 'jm-referral-system' ); ?></strong>
			<?php
			echo null !== $entered_at && '' !== (string) $entered_at
				? esc_html( (string) $entered_at )
				: esc_html__( 'Not recorded', 'jm-referral-system' );
			?>
		</p>
		<p>
			<strong><?php echo esc_html__( 'Time Waiting:', 'jm-referral-system' ); ?></strong>
			<?php
			echo null !== $waiting && '' !== (string) $waiting
				? esc_html( (string) $waiting )
				: esc_html__( '—', 'jm-referral-system' );
			?>
		</p>
		<p><strong><?php echo esc_html__( 'Next Action:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $next_action ); ?></p>
	<?php else : ?>
		<p><?php echo esc_html__( 'No workflow stage is set for this referral.', 'jm-referral-system' ); ?></p>
	<?php endif; ?>

	<p><strong><?php echo esc_html__( 'Owner:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( $owner_name ); ?></p>

	<?php
	$interest_milestone = is_array( $interest_milestone ?? null ) ? $interest_milestone : null;
	if ( is_array( $interest_milestone ) && ! empty( $interest_milestone['recorded'] ) ) :
		?>
		<div style="margin-top: 1em; padding-top: 0.75em; border-top: 1px solid #dcdcde;">
			<h3 style="margin: 0 0 0.5em;"><?php echo esc_html__( 'Interest Response', 'jm-referral-system' ); ?></h3>
			<p><strong><?php echo esc_html__( 'Status:', 'jm-referral-system' ); ?></strong> <?php echo esc_html__( 'Recorded', 'jm-referral-system' ); ?></p>
			<p><strong><?php echo esc_html__( 'Method:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( (string) ( $interest_milestone['method_label'] ?? '' ) ); ?></p>
			<p><strong><?php echo esc_html__( 'Expressed:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( (string) ( $interest_milestone['expressed_at'] ?? '' ) ); ?></p>
			<p><strong><?php echo esc_html__( 'By:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( (string) ( $interest_milestone['expressed_by_name'] ?? '' ) ); ?></p>
			<?php if ( 'email' === (string) ( $interest_milestone['method'] ?? '' ) ) : ?>
				<p><strong><?php echo esc_html__( 'Email Status:', 'jm-referral-system' ); ?></strong> <?php echo esc_html( (string) ( $interest_milestone['email_status_label'] ?? '' ) ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $can_override && $referral_id > 0 && ! empty( $override_options ) ) : ?>
		<details style="margin-top: 1.25em; padding: 0.75em 1em; border: 1px dashed #d63638; background: #fcf0f1;">
			<summary style="cursor: pointer; font-weight: 600; color: #b32d2e;">
				<?php echo esc_html__( 'Override Pipeline Stage (exceptional)', 'jm-referral-system' ); ?>
			</summary>
			<p class="description" style="margin-top: 0.75em;">
				<?php echo esc_html__( 'Use only when a controlled correction is required. Normal progression will use business actions in later phases. A reason is required and will be audited.', 'jm-referral-system' ); ?>
			</p>
			<form method="post" action="<?php echo '' !== $override_form_action ? esc_url( $override_form_action ) : ''; ?>" style="margin-top: 0.75em;">
				<?php wp_nonce_field( 'jmrs_override_pipeline_stage_' . $referral_id, 'jmrs_override_pipeline_stage_nonce' ); ?>
				<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
				<p>
					<label for="jmrs_pipeline_target_slug"><strong><?php echo esc_html__( 'Target canonical stage', 'jm-referral-system' ); ?></strong></label><br />
					<select name="jmrs_pipeline_target_slug" id="jmrs_pipeline_target_slug" required>
						<option value=""><?php echo esc_html__( '— Select —', 'jm-referral-system' ); ?></option>
						<?php foreach ( $override_options as $option ) : ?>
							<option value="<?php echo esc_attr( (string) ( $option['slug'] ?? '' ) ); ?>">
								<?php echo esc_html( (string) ( $option['name'] ?? '' ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="jmrs_pipeline_override_reason"><strong><?php echo esc_html__( 'Reason', 'jm-referral-system' ); ?></strong></label><br />
					<input type="text" class="regular-text" name="jmrs_pipeline_override_reason" id="jmrs_pipeline_override_reason" maxlength="255" required />
				</p>
				<?php
				submit_button(
					__( 'Override Pipeline Stage', 'jm-referral-system' ),
					'secondary',
					'jmrs_override_pipeline_stage',
					false
				);
				?>
			</form>
		</details>
	<?php endif; ?>
</div>
