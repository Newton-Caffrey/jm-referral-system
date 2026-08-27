<?php
/**
 * Portal assessment create/edit form, or read-only completed view (Phase 4E.1).
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
$is_completed_readonly = ! empty( $is_completed_readonly );
$assessor_name   = (string) ( $assessor_name ?? '' );
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

$has_value = static function ( string $value ): bool {
	return '' !== trim( $value );
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

$outcome_key   = $val( $data, 'outcome' );
$outcome_label = isset( $outcome_options[ $outcome_key ] )
	? (string) $outcome_options[ $outcome_key ]
	: $outcome_key;

if ( $is_completed_readonly ) :
	?>
	<div class="jmrs-portal-notice jmrs-portal-notice--info" role="status">
		<p><?php echo esc_html__( 'This assessment has been completed and is read-only.', 'jm-referral-system' ); ?></p>
	</div>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Assessment Overview', 'jm-referral-system' ); ?></h2>
		<dl class="jmrs-portal-summary">
			<div>
				<dt><?php echo esc_html__( 'Assessment Date', 'jm-referral-system' ); ?></dt>
				<dd>
					<?php
					$date = $val( $data, 'assessment_date' );
					echo '' !== $date
						? esc_html( mysql2date( get_option( 'date_format' ), $date ) )
						: esc_html__( 'Not recorded', 'jm-referral-system' );
					?>
				</dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Assessor', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $assessor_name ? $assessor_name : __( 'Not recorded', 'jm-referral-system' ) ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $outcome_label ? $outcome_label : __( 'Not recorded', 'jm-referral-system' ) ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Next Review Date', 'jm-referral-system' ); ?></dt>
				<dd>
					<?php
					$nrd = $val( $data, 'next_review_date' );
					echo $has_value( $nrd )
						? esc_html( mysql2date( get_option( 'date_format' ), $nrd ) )
						: esc_html__( 'Not recorded', 'jm-referral-system' );
					?>
				</dd>
			</div>
		</dl>
	</section>

	<?php foreach ( $long_sections as $section_title => $fields ) : ?>
		<?php
		$visible = array();
		foreach ( $fields as $field_key => $field_label ) {
			$field_value = $val( $data, $field_key );
			if ( $has_value( $field_value ) ) {
				$visible[ $field_key ] = array(
					'label' => $field_label,
					'value' => $field_value,
				);
			}
		}
		?>
		<?php if ( ! empty( $visible ) ) : ?>
			<section class="jmrs-portal-section">
				<h2 class="jmrs-portal-section__title"><?php echo esc_html( (string) $section_title ); ?></h2>
				<dl class="jmrs-portal-summary">
					<?php foreach ( $visible as $item ) : ?>
						<div>
							<dt><?php echo esc_html( (string) $item['label'] ); ?></dt>
							<dd><?php echo nl2br( esc_html( (string) $item['value'] ) ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</section>
		<?php endif; ?>
	<?php endforeach; ?>

	<?php
	$package_fields = array(
		'visit_frequency'       => array( __( 'Visit Frequency', 'jm-referral-system' ), false ),
		'visit_duration'        => array( __( 'Visit Duration', 'jm-referral-system' ), false ),
		'preferred_visit_times' => array( __( 'Preferred Visit Times', 'jm-referral-system' ), true ),
		'summary'               => array( __( 'Summary', 'jm-referral-system' ), true ),
		'recommendations'       => array( __( 'Recommendations', 'jm-referral-system' ), true ),
	);
	$package_visible = array();
	foreach ( $package_fields as $field_key => $meta ) {
		$field_value = $val( $data, $field_key );
		if ( $has_value( $field_value ) ) {
			$package_visible[] = array(
				'label' => $meta[0],
				'value' => $field_value,
				'nl2br' => $meta[1],
			);
		}
	}
	?>
	<?php if ( ! empty( $package_visible ) ) : ?>
		<section class="jmrs-portal-section">
			<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Care Package and Summary', 'jm-referral-system' ); ?></h2>
			<dl class="jmrs-portal-summary">
				<?php foreach ( $package_visible as $item ) : ?>
					<div>
						<dt><?php echo esc_html( (string) $item['label'] ); ?></dt>
						<dd>
							<?php
							echo ! empty( $item['nl2br'] )
								? nl2br( esc_html( (string) $item['value'] ) )
								: esc_html( (string) $item['value'] );
							?>
						</dd>
					</div>
				<?php endforeach; ?>
			</dl>
		</section>
	<?php endif; ?>

	<p class="jmrs-portal-actions">
		<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
			<?php echo esc_html__( 'Back to Referral', 'jm-referral-system' ); ?>
		</a>
	</p>
	<?php
	return;
endif;
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
					<?php foreach ( $outcome_options as $outcome_value => $outcome_option_label ) : ?>
						<option value="<?php echo esc_attr( (string) $outcome_value ); ?>" <?php selected( $val( $data, 'outcome' ), (string) $outcome_value ); ?>>
							<?php echo esc_html( (string) $outcome_option_label ); ?>
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
