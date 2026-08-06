<?php
/**
 * Portal assessment create/edit form.
 *
 * Field names match admin so ReferralAssessmentController::attempt_save() can be reused.
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
$is_create       = ! empty( $is_create );
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

$long_sections = array(
	__( 'Daily Living and Personal Care', 'jm-referral-system' ) => array(
		'mobility_support'      => __( 'Mobility Support', 'jm-referral-system' ),
		'personal_care_support' => __( 'Personal Care Support', 'jm-referral-system' ),
		'continence_support'    => __( 'Continence Support', 'jm-referral-system' ),
		'nutrition_hydration'   => __( 'Nutrition and Hydration', 'jm-referral-system' ),
		'medication_support'    => __( 'Medication Support', 'jm-referral-system' ),
	),
	__( 'Communication and Cognition', 'jm-referral-system' ) => array(
		'communication_needs' => __( 'Communication Needs', 'jm-referral-system' ),
		'cognitive_needs'     => __( 'Cognitive Needs', 'jm-referral-system' ),
	),
	__( 'Home and Safety', 'jm-referral-system' ) => array(
		'home_environment'   => __( 'Home Environment', 'jm-referral-system' ),
		'safeguarding_risks' => __( 'Safeguarding Risks', 'jm-referral-system' ),
		'equipment_required' => __( 'Equipment Required', 'jm-referral-system' ),
	),
	__( 'Support Network', 'jm-referral-system' ) => array(
		'family_support' => __( 'Family Support', 'jm-referral-system' ),
	),
);
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
	<?php wp_nonce_field( 'jmrs_save_assessment_' . $referral_id, 'jmrs_save_assessment_nonce' ); ?>
	<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Assessment Overview', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_assessment_date"><?php echo esc_html__( 'Assessment Date', 'jm-referral-system' ); ?></label>
				<input type="date" name="jmrs_assessment_date" id="jmrs_assessment_date" value="<?php echo esc_attr( $val( $data, 'assessment_date' ) ); ?>" required />
				<?php $field_error( $errors, 'assessment_date' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_assessment_outcome"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></label>
				<select name="jmrs_assessment_outcome" id="jmrs_assessment_outcome">
					<?php foreach ( $outcome_options as $outcome_value => $outcome_label ) : ?>
						<option value="<?php echo esc_attr( (string) $outcome_value ); ?>" <?php selected( $val( $data, 'outcome' ), (string) $outcome_value ); ?>>
							<?php echo esc_html( (string) $outcome_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( $errors, 'outcome' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_assessment_next_review_date"><?php echo esc_html__( 'Next Review Date', 'jm-referral-system' ); ?></label>
				<input type="date" name="jmrs_assessment_next_review_date" id="jmrs_assessment_next_review_date" value="<?php echo esc_attr( $val( $data, 'next_review_date' ) ); ?>" />
				<?php $field_error( $errors, 'next_review_date' ); ?>
			</div>
		</div>
	</section>

	<?php foreach ( $long_sections as $section_title => $fields ) : ?>
		<section class="jmrs-portal-section">
			<h2 class="jmrs-portal-section__title"><?php echo esc_html( (string) $section_title ); ?></h2>
			<div class="jmrs-portal-form-grid">
				<?php foreach ( $fields as $field_key => $field_label ) : ?>
					<div class="jmrs-portal-field jmrs-portal-field--full">
						<label for="jmrs_assessment_<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( (string) $field_label ); ?></label>
						<textarea
							name="jmrs_assessment_<?php echo esc_attr( $field_key ); ?>"
							id="jmrs_assessment_<?php echo esc_attr( $field_key ); ?>"
							rows="4"
						><?php echo esc_textarea( $val( $data, $field_key ) ); ?></textarea>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Proposed Care Package', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_assessment_visit_frequency"><?php echo esc_html__( 'Visit Frequency', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_assessment_visit_frequency" id="jmrs_assessment_visit_frequency" value="<?php echo esc_attr( $val( $data, 'visit_frequency' ) ); ?>" />
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_assessment_visit_duration"><?php echo esc_html__( 'Visit Duration', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_assessment_visit_duration" id="jmrs_assessment_visit_duration" value="<?php echo esc_attr( $val( $data, 'visit_duration' ) ); ?>" />
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_assessment_preferred_visit_times"><?php echo esc_html__( 'Preferred Visit Times', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_assessment_preferred_visit_times" id="jmrs_assessment_preferred_visit_times" rows="3"><?php echo esc_textarea( $val( $data, 'preferred_visit_times' ) ); ?></textarea>
			</div>
		</div>
	</section>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Summary and Recommendations', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_assessment_summary"><?php echo esc_html__( 'Summary', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_assessment_summary" id="jmrs_assessment_summary" rows="5"><?php echo esc_textarea( $val( $data, 'summary' ) ); ?></textarea>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_assessment_recommendations"><?php echo esc_html__( 'Recommendations', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_assessment_recommendations" id="jmrs_assessment_recommendations" rows="5"><?php echo esc_textarea( $val( $data, 'recommendations' ) ); ?></textarea>
			</div>
		</div>
	</section>

	<p class="jmrs-portal-actions">
		<button type="submit" name="jmrs_save_assessment" value="1" class="jmrs-button jmrs-button--primary">
			<?php echo esc_html( $is_create ? __( 'Create Assessment', 'jm-referral-system' ) : __( 'Update Assessment', 'jm-referral-system' ) ); ?>
		</button>
		<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
			<?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?>
		</a>
	</p>
</form>
