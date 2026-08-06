<?php
/**
 * Portal care plan create/edit form (or start actions when no plan exists).
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data            = is_array( $data ?? null ) ? $data : array();
$errors          = is_array( $errors ?? null ) ? $errors : array();
$status_options  = is_array( $status_options ?? null ) ? $status_options : array();
$form_action     = (string) ( $form_action ?? '' );
$cancel_url      = (string) ( $cancel_url ?? '' );
$show_start      = ! empty( $show_start );
$has_assessment  = ! empty( $has_assessment );
$is_create       = ! empty( $is_create );
$referral        = is_array( $referral ?? null ) ? $referral : array();
$care_plan       = is_array( $care_plan ?? null ) ? $care_plan : null;
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

$content_fields = array(
	'preferred_visit_times'   => __( 'Preferred Visit Times', 'jm-referral-system' ),
	'personal_care_tasks'     => __( 'Personal Care Tasks', 'jm-referral-system' ),
	'mobility_support'        => __( 'Mobility Support', 'jm-referral-system' ),
	'medication_support'      => __( 'Medication Support', 'jm-referral-system' ),
	'nutrition_support'       => __( 'Nutrition Support', 'jm-referral-system' ),
	'communication_support'   => __( 'Communication Support', 'jm-referral-system' ),
	'continence_support'      => __( 'Continence Support', 'jm-referral-system' ),
	'social_support'          => __( 'Social Support', 'jm-referral-system' ),
	'equipment_required'      => __( 'Equipment Required', 'jm-referral-system' ),
	'risks_and_safeguards'    => __( 'Risks and Safeguards', 'jm-referral-system' ),
	'goals'                   => __( 'Goals', 'jm-referral-system' ),
	'additional_instructions' => __( 'Additional Instructions', 'jm-referral-system' ),
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

<?php if ( $show_start ) : ?>
	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Create Care Plan', 'jm-referral-system' ); ?></h2>
		<p class="jmrs-portal-muted">
			<?php echo esc_html__( 'Start from an assessment or create a blank care plan. This is separate from editing core referral details.', 'jm-referral-system' ); ?>
		</p>
		<div class="jmrs-portal-actions">
			<?php if ( $has_assessment ) : ?>
				<form method="post" action="<?php echo esc_url( $form_action ); ?>">
					<?php wp_nonce_field( 'jmrs_generate_care_plan_' . $referral_id, 'jmrs_generate_care_plan_nonce' ); ?>
					<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
					<button type="submit" name="jmrs_generate_care_plan" value="1" class="jmrs-button jmrs-button--primary">
						<?php echo esc_html__( 'Generate from Assessment', 'jm-referral-system' ); ?>
					</button>
				</form>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php wp_nonce_field( 'jmrs_blank_care_plan_' . $referral_id, 'jmrs_blank_care_plan_nonce' ); ?>
				<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
				<button type="submit" name="jmrs_blank_care_plan" value="1" class="jmrs-button jmrs-button--secondary">
					<?php echo esc_html__( 'Create Care Plan', 'jm-referral-system' ); ?>
				</button>
			</form>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
				<?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?>
			</a>
		</div>
	</section>
<?php else : ?>
	<form class="jmrs-portal-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
		<?php wp_nonce_field( 'jmrs_save_care_plan_' . $referral_id, 'jmrs_save_care_plan_nonce' ); ?>
		<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
		<input type="hidden" name="jmrs_care_plan_assessment_id" value="<?php echo esc_attr( $val( $data, 'assessment_id' ) ); ?>" />

		<section class="jmrs-portal-section">
			<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Care Plan Overview', 'jm-referral-system' ); ?></h2>
			<div class="jmrs-portal-form-grid">
				<div class="jmrs-portal-field">
					<label for="jmrs_care_plan_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
					<select name="jmrs_care_plan_status" id="jmrs_care_plan_status">
						<?php foreach ( $status_options as $status_value => $status_label ) : ?>
							<option value="<?php echo esc_attr( (string) $status_value ); ?>" <?php selected( $val( $data, 'plan_status' ), (string) $status_value ); ?>>
								<?php echo esc_html( (string) $status_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php $field_error( $errors, 'plan_status' ); ?>
				</div>
				<div class="jmrs-portal-field">
					<label for="jmrs_care_plan_start_date"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></label>
					<input type="date" name="jmrs_care_plan_start_date" id="jmrs_care_plan_start_date" value="<?php echo esc_attr( $val( $data, 'start_date' ) ); ?>" />
					<?php $field_error( $errors, 'start_date' ); ?>
				</div>
				<div class="jmrs-portal-field">
					<label for="jmrs_care_plan_review_date"><?php echo esc_html__( 'Review Date', 'jm-referral-system' ); ?></label>
					<input type="date" name="jmrs_care_plan_review_date" id="jmrs_care_plan_review_date" value="<?php echo esc_attr( $val( $data, 'review_date' ) ); ?>" />
					<?php $field_error( $errors, 'review_date' ); ?>
				</div>
				<div class="jmrs-portal-field">
					<label for="jmrs_care_plan_visit_frequency"><?php echo esc_html__( 'Visit Frequency', 'jm-referral-system' ); ?></label>
					<input type="text" name="jmrs_care_plan_visit_frequency" id="jmrs_care_plan_visit_frequency" value="<?php echo esc_attr( $val( $data, 'visit_frequency' ) ); ?>" />
				</div>
				<div class="jmrs-portal-field">
					<label for="jmrs_care_plan_visit_duration"><?php echo esc_html__( 'Visit Duration', 'jm-referral-system' ); ?></label>
					<input type="text" name="jmrs_care_plan_visit_duration" id="jmrs_care_plan_visit_duration" value="<?php echo esc_attr( $val( $data, 'visit_duration' ) ); ?>" />
				</div>
				<?php if ( ! $is_create ) : ?>
					<div class="jmrs-portal-field jmrs-portal-field--full">
						<label for="jmrs_care_plan_change_summary"><?php echo esc_html__( 'Change Summary', 'jm-referral-system' ); ?></label>
						<textarea name="jmrs_care_plan_change_summary" id="jmrs_care_plan_change_summary" rows="3"><?php echo esc_textarea( $val( $data, 'change_summary' ) ); ?></textarea>
						<?php $field_error( $errors, 'change_summary' ); ?>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<section class="jmrs-portal-section">
			<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Care Plan Content', 'jm-referral-system' ); ?></h2>
			<div class="jmrs-portal-form-grid">
				<?php foreach ( $content_fields as $field_key => $field_label ) : ?>
					<div class="jmrs-portal-field jmrs-portal-field--full">
						<label for="jmrs_care_plan_<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( (string) $field_label ); ?></label>
						<textarea
							name="jmrs_care_plan_<?php echo esc_attr( $field_key ); ?>"
							id="jmrs_care_plan_<?php echo esc_attr( $field_key ); ?>"
							rows="4"
						><?php echo esc_textarea( $val( $data, $field_key ) ); ?></textarea>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<p class="jmrs-portal-actions">
			<button type="submit" name="jmrs_save_care_plan" value="1" class="jmrs-button jmrs-button--primary">
				<?php echo esc_html( $is_create ? __( 'Create Care Plan', 'jm-referral-system' ) : __( 'Update Care Plan', 'jm-referral-system' ) ); ?>
			</button>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
				<?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?>
			</a>
		</p>
	</form>
<?php endif; ?>
