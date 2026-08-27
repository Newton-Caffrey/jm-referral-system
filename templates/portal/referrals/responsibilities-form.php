<?php
/**
 * Manage referral owner, champion and transition lead (Phase 4C.1).
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral      = is_array( $referral ?? null ) ? $referral : array();
$referral_id   = absint( $referral['id'] ?? 0 );
$data          = is_array( $data ?? null ) ? $data : array();
$errors        = is_array( $errors ?? null ) ? $errors : array();
$staff_options = is_array( $staff_options ?? null ) ? $staff_options : array();
$form_action   = (string) ( $form_action ?? '' );
$cancel_url    = (string) ( $cancel_url ?? '' );

$val = static function ( array $data, string $key ): int {
	return absint( $data[ $key ] ?? 0 );
};

$field_error = static function ( array $errors, string $key ): void {
	if ( ! isset( $errors[ $key ] ) ) {
		return;
	}
	echo '<p class="jmrs-portal-field-error" id="jmrs-resp-error-' . esc_attr( $key ) . '">' . esc_html( (string) $errors[ $key ] ) . '</p>';
};

$unassigned_label = __( 'Unassigned', 'jm-referral-system' );
?>
<?php if ( ! empty( $errors ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert">
		<p><?php echo esc_html__( 'Please correct the highlighted fields.', 'jm-referral-system' ); ?></p>
		<?php if ( isset( $errors['form'] ) ) : ?>
			<p><?php echo esc_html( (string) $errors['form'] ); ?></p>
		<?php endif; ?>
	</div>
<?php endif; ?>

<section class="jmrs-portal-section jmrs-portal-panel jmrs-responsibilities-form">
	<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Manage responsibilities', 'jm-referral-system' ); ?></h2>
	<p class="description">
		<?php echo esc_html__( 'Assign the referral owner, champion and transition lead. The same staff member may hold more than one responsibility. Champion and transition lead do not change referral access.', 'jm-referral-system' ); ?>
	</p>

	<form class="jmrs-portal-form" method="post" action="<?php echo esc_url( $form_action ); ?>" novalidate>
		<?php wp_nonce_field( 'jmrs_save_responsibilities_' . $referral_id, 'jmrs_responsibilities_nonce' ); ?>
		<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_resp_assigned_to"><?php echo esc_html__( 'Referral owner', 'jm-referral-system' ); ?></label>
				<select
					name="jmrs_resp_assigned_to"
					id="jmrs_resp_assigned_to"
					<?php echo isset( $errors['assigned_to'] ) ? 'aria-invalid="true" aria-describedby="jmrs-resp-error-assigned_to"' : ''; ?>
				>
					<option value="0"><?php echo esc_html( $unassigned_label ); ?></option>
					<?php foreach ( $staff_options as $option ) : ?>
						<option value="<?php echo esc_attr( (string) absint( $option['id'] ?? 0 ) ); ?>" <?php selected( $val( $data, 'assigned_to' ), absint( $option['id'] ?? 0 ) ); ?>>
							<?php echo esc_html( (string) ( $option['label'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'assigned_to' ); ?>
			</div>

			<div class="jmrs-portal-field">
				<label for="jmrs_resp_champion_user_id"><?php echo esc_html__( 'Champion', 'jm-referral-system' ); ?></label>
				<select
					name="jmrs_resp_champion_user_id"
					id="jmrs_resp_champion_user_id"
					<?php echo isset( $errors['champion_user_id'] ) ? 'aria-invalid="true" aria-describedby="jmrs-resp-error-champion_user_id"' : ''; ?>
				>
					<option value="0"><?php echo esc_html( $unassigned_label ); ?></option>
					<?php foreach ( $staff_options as $option ) : ?>
						<option value="<?php echo esc_attr( (string) absint( $option['id'] ?? 0 ) ); ?>" <?php selected( $val( $data, 'champion_user_id' ), absint( $option['id'] ?? 0 ) ); ?>>
							<?php echo esc_html( (string) ( $option['label'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'champion_user_id' ); ?>
			</div>

			<div class="jmrs-portal-field">
				<label for="jmrs_resp_transition_lead_user_id"><?php echo esc_html__( 'Transition lead', 'jm-referral-system' ); ?></label>
				<select
					name="jmrs_resp_transition_lead_user_id"
					id="jmrs_resp_transition_lead_user_id"
					<?php echo isset( $errors['transition_lead_user_id'] ) ? 'aria-invalid="true" aria-describedby="jmrs-resp-error-transition_lead_user_id"' : ''; ?>
				>
					<option value="0"><?php echo esc_html( $unassigned_label ); ?></option>
					<?php foreach ( $staff_options as $option ) : ?>
						<option value="<?php echo esc_attr( (string) absint( $option['id'] ?? 0 ) ); ?>" <?php selected( $val( $data, 'transition_lead_user_id' ), absint( $option['id'] ?? 0 ) ); ?>>
							<?php echo esc_html( (string) ( $option['label'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'transition_lead_user_id' ); ?>
			</div>
		</div>

		<div class="jmrs-portal-actions">
			<button type="submit" class="jmrs-button jmrs-button--primary" name="jmrs_save_responsibilities" value="1">
				<?php echo esc_html__( 'Save responsibilities', 'jm-referral-system' ); ?>
			</button>
			<?php if ( '' !== $cancel_url ) : ?>
				<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
					<?php echo esc_html__( 'Back to referral', 'jm-referral-system' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</form>
</section>
