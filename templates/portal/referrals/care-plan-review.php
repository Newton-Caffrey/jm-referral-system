<?php
/**
 * Portal care plan review form.
 *
 * Field names match admin so ReferralCarePlanReviewController::attempt_review() can be reused.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data            = is_array( $data ?? null ) ? $data : array();
$errors          = is_array( $errors ?? null ) ? $errors : array();
$outcome_options = is_array( $outcome_options ?? null ) ? $outcome_options : array();
$form_action     = (string) ( $form_action ?? '' );
$cancel_url      = (string) ( $cancel_url ?? '' );
$referral        = is_array( $referral ?? null ) ? $referral : array();
$referral_id     = absint( $referral['id'] ?? 0 );

$val = static function ( array $data, string $key ): string {
	return (string) ( $data[ $key ] ?? '' );
};

$field_error = static function ( array $errors, string $key ): void {
	if ( ! isset( $errors[ $key ] ) ) {
		return;
	}
	echo '<p class="jmrs-portal-field-error">' . esc_html( (string) $errors[ $key ] ) . '</p>';
};
?>
<?php if ( ! empty( $errors ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert">
		<p><strong><?php echo esc_html__( 'Please fix the following errors:', 'jm-referral-system' ); ?></strong></p>
		<ul>
			<?php foreach ( $errors as $message ) : ?>
				<li><?php echo esc_html( (string) $message ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<form class="jmrs-portal-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
	<?php wp_nonce_field( 'jmrs_care_plan_review_' . $referral_id, 'jmrs_care_plan_review_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Care Plan Review', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_care_plan_review_date"><?php echo esc_html__( 'Review Date', 'jm-referral-system' ); ?></label>
				<input type="date" name="jmrs_care_plan_review_date" id="jmrs_care_plan_review_date" value="<?php echo esc_attr( $val( $data, 'review_date' ) ); ?>" required />
				<?php $field_error( $errors, 'review_date' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_care_plan_review_outcome"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></label>
				<select name="jmrs_care_plan_review_outcome" id="jmrs_care_plan_review_outcome" required>
					<option value=""><?php echo esc_html__( '— Select —', 'jm-referral-system' ); ?></option>
					<?php foreach ( $outcome_options as $outcome_value => $outcome_label ) : ?>
						<option value="<?php echo esc_attr( (string) $outcome_value ); ?>" <?php selected( $val( $data, 'outcome' ), (string) $outcome_value ); ?>>
							<?php echo esc_html( (string) $outcome_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'outcome' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_care_plan_review_next_date"><?php echo esc_html__( 'Next Review Date', 'jm-referral-system' ); ?></label>
				<input type="date" name="jmrs_care_plan_review_next_date" id="jmrs_care_plan_review_next_date" value="<?php echo esc_attr( $val( $data, 'next_review_date' ) ); ?>" />
				<?php $field_error( $errors, 'next_review_date' ); ?>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_care_plan_review_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_care_plan_review_notes" id="jmrs_care_plan_review_notes" rows="5"><?php echo esc_textarea( $val( $data, 'notes' ) ); ?></textarea>
				<?php $field_error( $errors, 'notes' ); ?>
			</div>
		</div>
	</section>

	<p class="jmrs-portal-actions">
		<button type="submit" name="jmrs_submit_care_plan_review" value="1" class="jmrs-button jmrs-button--primary">
			<?php echo esc_html__( 'Save Review', 'jm-referral-system' ); ?>
		</button>
		<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
			<?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?>
		</a>
	</p>
</form>
