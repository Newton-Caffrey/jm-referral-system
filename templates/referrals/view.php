<?php
/**
 * Referral details / view template.
 *
 * @package JMReferral
 *
 * @var array<string, mixed>             $referral         Referral row.
 * @var array<int, array<string, mixed>> $activities       Activity rows.
 * @var array<int, array<string, mixed>> $notes            Internal note rows.
 * @var string                           $assigned_to_name Assignee display name.
 * @var string                           $service_name     Resolved service type name.
 * @var string                           $workflow_stage_name Resolved workflow stage name.
 * @var array<int, array<string, mixed>> $workflow_stages  Selectable workflow stages.
 * @var string                           $note_value       Sticky note form value.
 * @var array<string, string>            $note_errors      Note form validation errors.
 * @var array<int, array<string, mixed>> $documents        Document rows for the referral.
 * @var bool                             $can_upload_documents Whether the user may upload documents.
 * @var bool                             $can_download_documents Whether the user may download documents.
 * @var array<string, mixed>|null        $assessment       Existing assessment row, if any.
 * @var array<string, string>            $assessment_data  Assessment form values.
 * @var array<string, string>            $assessment_errors Assessment validation errors.
 * @var bool                             $can_edit_assessment Whether the user may create/update assessments.
 * @var string                           $assessor_name    Assessor display name.
 * @var array<string, string>            $assessment_outcomes Outcome value => label map.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral         = is_array( $referral ?? null ) ? $referral : array();
$activities       = is_array( $activities ?? null ) ? $activities : array();
$notes            = is_array( $notes ?? null ) ? $notes : array();
$assigned_to_name = isset( $assigned_to_name ) ? (string) $assigned_to_name : '';
$service_name     = isset( $service_name ) ? (string) $service_name : '';
$workflow_stage_name = isset( $workflow_stage_name ) ? (string) $workflow_stage_name : '';
$workflow_stages  = is_array( $workflow_stages ?? null ) ? $workflow_stages : array();
$note_value       = isset( $note_value ) ? (string) $note_value : '';
$note_errors      = is_array( $note_errors ?? null ) ? $note_errors : array();
$documents        = is_array( $documents ?? null ) ? $documents : array();
$can_upload_documents   = ! empty( $can_upload_documents );
$can_download_documents = ! empty( $can_download_documents );
$assessment             = is_array( $assessment ?? null ) ? $assessment : null;
$assessment_data        = is_array( $assessment_data ?? null ) ? $assessment_data : array();
$assessment_errors      = is_array( $assessment_errors ?? null ) ? $assessment_errors : array();
$can_edit_assessment    = ! empty( $can_edit_assessment );
$assessor_name          = isset( $assessor_name ) ? (string) $assessor_name : '';
$assessment_outcomes    = is_array( $assessment_outcomes ?? null ) ? $assessment_outcomes : array();

$assessment_date_value  = (string) ( $assessment_data['assessment_date'] ?? '' );
$assessment_outcome     = (string) ( $assessment_data['outcome'] ?? 'pending' );
$assessment_next_review = (string) ( $assessment_data['next_review_date'] ?? '' );
$outcome_label          = isset( $assessment_outcomes[ $assessment_outcome ] )
	? (string) $assessment_outcomes[ $assessment_outcome ]
	: ucfirst( str_replace( '_', ' ', $assessment_outcome ) );

$jmrs_assessment_value = static function ( string $key ) use ( $assessment_data ): string {
	return (string) ( $assessment_data[ $key ] ?? '' );
};

$jmrs_assessment_has_value = static function ( string $value ): bool {
	return '' !== trim( $value );
};

$can_view_care_plan           = ! empty( $can_view_care_plan );
$can_manage_care_plan         = ! empty( $can_manage_care_plan );
$can_review_care_plan         = ! empty( $can_review_care_plan );
$care_plan                    = is_array( $care_plan ?? null ) ? $care_plan : null;
$care_plan_data               = is_array( $care_plan_data ?? null ) ? $care_plan_data : array();
$care_plan_errors             = is_array( $care_plan_errors ?? null ) ? $care_plan_errors : array();
$show_care_plan_form          = ! empty( $show_care_plan_form );
$has_assessment               = ! empty( $has_assessment );
$care_plan_statuses           = is_array( $care_plan_statuses ?? null ) ? $care_plan_statuses : array();
$care_plan_created_by_name    = isset( $care_plan_created_by_name ) ? (string) $care_plan_created_by_name : '';
$care_plan_approved_by_name   = isset( $care_plan_approved_by_name ) ? (string) $care_plan_approved_by_name : '';
$care_plan_review_outcomes    = is_array( $care_plan_review_outcomes ?? null ) ? $care_plan_review_outcomes : array();
$care_plan_review_data        = is_array( $care_plan_review_data ?? null ) ? $care_plan_review_data : array();
$care_plan_review_errors      = is_array( $care_plan_review_errors ?? null ) ? $care_plan_review_errors : array();
$care_plan_reviews            = is_array( $care_plan_reviews ?? null ) ? $care_plan_reviews : array();
$care_plan_versions           = is_array( $care_plan_versions ?? null ) ? $care_plan_versions : array();
$can_view_visits              = ! empty( $can_view_visits );
$can_manage_visits            = ! empty( $can_manage_visits );
$care_visits                  = is_array( $care_visits ?? null ) ? $care_visits : array();
$care_visit_data              = is_array( $care_visit_data ?? null ) ? $care_visit_data : array();
$care_visit_errors            = is_array( $care_visit_errors ?? null ) ? $care_visit_errors : array();
$care_visit_statuses          = is_array( $care_visit_statuses ?? null ) ? $care_visit_statuses : array();
$assignable_users             = is_array( $assignable_users ?? null ) ? $assignable_users : array();
$can_view_care_team           = ! empty( $can_view_care_team );
$can_manage_care_team         = ! empty( $can_manage_care_team );
$care_team_members            = is_array( $care_team_members ?? null ) ? $care_team_members : array();
$care_team_data               = is_array( $care_team_data ?? null ) ? $care_team_data : array();
$care_team_errors             = is_array( $care_team_errors ?? null ) ? $care_team_errors : array();
$care_team_roles              = is_array( $care_team_roles ?? null ) ? $care_team_roles : array();
$care_team_statuses           = is_array( $care_team_statuses ?? null ) ? $care_team_statuses : array();
$care_team_assignable_users   = is_array( $care_team_assignable_users ?? null ) ? $care_team_assignable_users : array();

$jmrs_care_plan_value = static function ( string $key ) use ( $care_plan_data ): string {
	return (string) ( $care_plan_data[ $key ] ?? '' );
};

$jmrs_care_plan_review_value = static function ( string $key ) use ( $care_plan_review_data ): string {
	return (string) ( $care_plan_review_data[ $key ] ?? '' );
};

$jmrs_care_visit_value = static function ( string $key ) use ( $care_visit_data ): string {
	return (string) ( $care_visit_data[ $key ] ?? '' );
};

$jmrs_care_team_value = static function ( string $key ) use ( $care_team_data ): string {
	return (string) ( $care_team_data[ $key ] ?? '' );
};

$referral_id      = absint( $referral['id'] ?? 0 );
$referral_number  = (string) ( $referral['referral_number'] ?? '' );
$client_name      = (string) ( $referral['client_name'] ?? '' );
$client_email     = (string) ( $referral['client_email'] ?? '' );
$client_phone     = (string) ( $referral['client_phone'] ?? '' );
$referrer_name    = (string) ( $referral['referrer_name'] ?? '' );
$service_required = '' !== $service_name
	? $service_name
	: (string) ( $referral['service_required'] ?? '' );
$workflow_stage_id = (string) absint( $referral['workflow_stage_id'] ?? 0 );
$priority         = (string) ( $referral['priority'] ?? '' );
$status           = (string) ( $referral['status'] ?? '' );
$referral_source  = (string) ( $referral['referral_source'] ?? '' );
$source_label     = '' !== $referral_source
	? \JMReferral\Referral\ReferralSources::label( $referral_source )
	: '';
$care_start_date  = (string) ( $referral['care_start_date'] ?? '' );
$care_start_display = '' !== $care_start_date
	? mysql2date( get_option( 'date_format' ), $care_start_date )
	: '';
$preferred_contact_method = (string) ( $referral['preferred_contact_method'] ?? '' );
$contact_method_label = '' !== $preferred_contact_method
	? \JMReferral\Referral\PreferredContactMethods::label( $preferred_contact_method )
	: '';
$care_requirements = (string) ( $referral['care_requirements'] ?? '' );
$created_at       = (string) ( $referral['created_at'] ?? '' );
$created_display  = '' !== $created_at
	? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at )
	: '';
$priority_display = ucfirst( $priority );
$status_display   = ucfirst( str_replace( '_', ' ', $status ) );

$list_url = admin_url( 'admin.php?page=jm-referrals-list' );
$edit_url = \JMReferral\Referral\ReferralEditController::get_edit_url( $referral_id );
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Referral Details', 'jm-referral-system' ); ?></h1>

	<p>
		<a class="button" href="<?php echo esc_url( $list_url ); ?>">
			<?php echo esc_html__( 'Back to Referrals', 'jm-referral-system' ); ?>
		</a>
		<a class="button button-primary" href="<?php echo esc_url( $edit_url ); ?>">
			<?php echo esc_html__( 'Edit Referral', 'jm-referral-system' ); ?>
		</a>
	</p>

	<h2><?php echo esc_html__( 'Referral Information', 'jm-referral-system' ); ?></h2>
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Referral Number', 'jm-referral-system' ); ?></th>
				<td><code><?php echo esc_html( $referral_number ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Client Name', 'jm-referral-system' ); ?></th>
				<td><?php echo esc_html( $client_name ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Client Email', 'jm-referral-system' ); ?></th>
				<td>
					<?php if ( '' !== $client_email ) : ?>
						<a href="<?php echo esc_url( 'mailto:' . $client_email ); ?>"><?php echo esc_html( $client_email ); ?></a>
					<?php else : ?>
						—
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Client Phone', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $client_phone ? esc_html( $client_phone ) : '—'; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Referrer Name', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $referrer_name ? esc_html( $referrer_name ) : '—'; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Service Required', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $service_required ? esc_html( $service_required ) : '—'; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Workflow Stage', 'jm-referral-system' ); ?></th>
				<td>
					<form method="post" action="" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
						<?php wp_nonce_field( 'jmrs_update_workflow_stage_' . $referral_id, 'jmrs_update_workflow_stage_nonce' ); ?>
						<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
						<select name="jmrs_workflow_stage_id" id="jmrs_workflow_stage_id">
							<?php foreach ( $workflow_stages as $workflow_stage ) : ?>
								<option value="<?php echo esc_attr( (string) $workflow_stage['id'] ); ?>" <?php selected( $workflow_stage_id, (string) $workflow_stage['id'] ); ?>>
									<?php echo esc_html( (string) $workflow_stage['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php
						submit_button(
							__( 'Update Stage', 'jm-referral-system' ),
							'secondary',
							'jmrs_update_workflow_stage',
							false
						);
						?>
					</form>
					<?php if ( '' === $workflow_stage_name && empty( $workflow_stages ) ) : ?>
						—
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?></th>
				<td><?php echo esc_html( $priority_display ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
				<td><?php echo esc_html( $status_display ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Assigned To', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $assigned_to_name ? esc_html( $assigned_to_name ) : esc_html__( 'Unassigned', 'jm-referral-system' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Referral Source', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $source_label ? esc_html( $source_label ) : '—'; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Created Date', 'jm-referral-system' ); ?></th>
				<td><?php echo esc_html( $created_display ); ?></td>
			</tr>
		</tbody>
	</table>

	<h2><?php echo esc_html__( 'Care Requirements', 'jm-referral-system' ); ?></h2>
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Care Start Date', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $care_start_display ? esc_html( $care_start_display ) : '—'; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Preferred Contact Method', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $contact_method_label ? esc_html( $contact_method_label ) : '—'; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Care Requirements', 'jm-referral-system' ); ?></th>
				<td>
					<?php if ( '' !== $care_requirements ) : ?>
						<?php echo nl2br( esc_html( $care_requirements ) ); ?>
					<?php else : ?>
						—
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>

	<?php
	$assessment_heading = null !== $assessment
		? __( 'Assessment', 'jm-referral-system' )
		: __( 'Create Assessment', 'jm-referral-system' );

	$daily_living_fields = array(
		'mobility_support'      => __( 'Mobility Support', 'jm-referral-system' ),
		'personal_care_support' => __( 'Personal Care Support', 'jm-referral-system' ),
		'continence_support'    => __( 'Continence Support', 'jm-referral-system' ),
		'nutrition_hydration'   => __( 'Nutrition and Hydration', 'jm-referral-system' ),
		'medication_support'    => __( 'Medication Support', 'jm-referral-system' ),
	);
	$communication_fields = array(
		'communication_needs' => __( 'Communication Needs', 'jm-referral-system' ),
		'cognitive_needs'     => __( 'Cognitive Needs', 'jm-referral-system' ),
	);
	$home_safety_fields = array(
		'home_environment'   => __( 'Home Environment', 'jm-referral-system' ),
		'safeguarding_risks' => __( 'Safeguarding Risks', 'jm-referral-system' ),
		'equipment_required' => __( 'Equipment Required', 'jm-referral-system' ),
	);
	$support_network_fields = array(
		'family_support' => __( 'Family Support', 'jm-referral-system' ),
	);
	$care_package_textareas = array(
		'preferred_visit_times' => __( 'Preferred Visit Times', 'jm-referral-system' ),
	);
	$care_package_text = array(
		'visit_frequency' => __( 'Visit Frequency', 'jm-referral-system' ),
		'visit_duration'  => __( 'Visit Duration', 'jm-referral-system' ),
	);
	$summary_fields = array(
		'summary'         => __( 'Summary', 'jm-referral-system' ),
		'recommendations' => __( 'Recommendations', 'jm-referral-system' ),
	);
	?>
	<h2><?php echo esc_html( $assessment_heading ); ?></h2>

	<?php if ( ! $can_edit_assessment ) : ?>
		<?php if ( null === $assessment ) : ?>
			<p><?php echo esc_html__( 'No assessment recorded yet.', 'jm-referral-system' ); ?></p>
		<?php else : ?>
			<h3><?php echo esc_html__( 'Assessment Overview', 'jm-referral-system' ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Assessment Date', 'jm-referral-system' ); ?></th>
						<td>
							<?php
							echo '' !== $assessment_date_value
								? esc_html( mysql2date( get_option( 'date_format' ), $assessment_date_value ) )
								: '—';
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Assessor', 'jm-referral-system' ); ?></th>
						<td><?php echo '' !== $assessor_name ? esc_html( $assessor_name ) : '—'; ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></th>
						<td><?php echo esc_html( $outcome_label ); ?></td>
					</tr>
					<?php if ( $jmrs_assessment_has_value( $assessment_next_review ) ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Next Review Date', 'jm-referral-system' ); ?></th>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $assessment_next_review ) ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php
			$readonly_sections = array(
				__( 'Daily Living and Personal Care', 'jm-referral-system' ) => $daily_living_fields,
				__( 'Communication and Cognition', 'jm-referral-system' )   => $communication_fields,
				__( 'Home and Safety', 'jm-referral-system' )               => $home_safety_fields,
				__( 'Support Network', 'jm-referral-system' )               => $support_network_fields,
			);
			?>
			<?php foreach ( $readonly_sections as $section_title => $fields ) : ?>
				<?php
				$visible_fields = array();
				foreach ( $fields as $field_key => $field_label ) {
					$field_value = $jmrs_assessment_value( $field_key );
					if ( $jmrs_assessment_has_value( $field_value ) ) {
						$visible_fields[ $field_key ] = array(
							'label' => $field_label,
							'value' => $field_value,
						);
					}
				}
				?>
				<?php if ( ! empty( $visible_fields ) ) : ?>
					<h3><?php echo esc_html( (string) $section_title ); ?></h3>
					<table class="form-table" role="presentation">
						<tbody>
							<?php foreach ( $visible_fields as $visible_field ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( (string) $visible_field['label'] ); ?></th>
									<td><?php echo nl2br( esc_html( (string) $visible_field['value'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endforeach; ?>

			<?php
			$package_visible = array();
			foreach ( $care_package_text as $field_key => $field_label ) {
				$field_value = $jmrs_assessment_value( $field_key );
				if ( $jmrs_assessment_has_value( $field_value ) ) {
					$package_visible[ $field_key ] = array(
						'label' => $field_label,
						'value' => $field_value,
						'nl2br' => false,
					);
				}
			}
			foreach ( $care_package_textareas as $field_key => $field_label ) {
				$field_value = $jmrs_assessment_value( $field_key );
				if ( $jmrs_assessment_has_value( $field_value ) ) {
					$package_visible[ $field_key ] = array(
						'label' => $field_label,
						'value' => $field_value,
						'nl2br' => true,
					);
				}
			}
			?>
			<?php if ( ! empty( $package_visible ) ) : ?>
				<h3><?php echo esc_html__( 'Proposed Care Package', 'jm-referral-system' ); ?></h3>
				<table class="form-table" role="presentation">
					<tbody>
						<?php foreach ( $package_visible as $package_field ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( (string) $package_field['label'] ); ?></th>
								<td>
									<?php
									if ( ! empty( $package_field['nl2br'] ) ) {
										echo nl2br( esc_html( (string) $package_field['value'] ) );
									} else {
										echo esc_html( (string) $package_field['value'] );
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php
			$summary_visible = array();
			foreach ( $summary_fields as $field_key => $field_label ) {
				$field_value = $jmrs_assessment_value( $field_key );
				if ( $jmrs_assessment_has_value( $field_value ) ) {
					$summary_visible[ $field_key ] = array(
						'label' => $field_label,
						'value' => $field_value,
					);
				}
			}
			?>
			<?php if ( ! empty( $summary_visible ) ) : ?>
				<h3><?php echo esc_html__( 'Summary and Recommendations', 'jm-referral-system' ); ?></h3>
				<table class="form-table" role="presentation">
					<tbody>
						<?php foreach ( $summary_visible as $summary_field ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( (string) $summary_field['label'] ); ?></th>
								<td><?php echo nl2br( esc_html( (string) $summary_field['value'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	<?php else : ?>
		<form method="post" action="">
			<?php wp_nonce_field( 'jmrs_save_assessment_' . $referral_id, 'jmrs_save_assessment_nonce' ); ?>
			<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

			<h3><?php echo esc_html__( 'Assessment Overview', 'jm-referral-system' ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="jmrs_assessment_date"><?php echo esc_html__( 'Assessment Date', 'jm-referral-system' ); ?></label>
						</th>
						<td>
							<input
								type="date"
								name="jmrs_assessment_date"
								id="jmrs_assessment_date"
								value="<?php echo esc_attr( $assessment_date_value ); ?>"
								required
							/>
							<?php if ( isset( $assessment_errors['assessment_date'] ) ) : ?>
								<p class="description"><?php echo esc_html( $assessment_errors['assessment_date'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Assessor', 'jm-referral-system' ); ?></th>
						<td>
							<?php echo '' !== $assessor_name ? esc_html( $assessor_name ) : esc_html__( 'Current user', 'jm-referral-system' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="jmrs_assessment_outcome"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></label>
						</th>
						<td>
							<select name="jmrs_assessment_outcome" id="jmrs_assessment_outcome">
								<?php foreach ( $assessment_outcomes as $outcome_value => $outcome_text ) : ?>
									<option value="<?php echo esc_attr( (string) $outcome_value ); ?>" <?php selected( $assessment_outcome, (string) $outcome_value ); ?>>
										<?php echo esc_html( (string) $outcome_text ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php if ( isset( $assessment_errors['outcome'] ) ) : ?>
								<p class="description"><?php echo esc_html( $assessment_errors['outcome'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="jmrs_assessment_next_review_date"><?php echo esc_html__( 'Next Review Date', 'jm-referral-system' ); ?></label>
						</th>
						<td>
							<input
								type="date"
								name="jmrs_assessment_next_review_date"
								id="jmrs_assessment_next_review_date"
								value="<?php echo esc_attr( $assessment_next_review ); ?>"
							/>
							<?php if ( isset( $assessment_errors['next_review_date'] ) ) : ?>
								<p class="description"><?php echo esc_html( $assessment_errors['next_review_date'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<?php
			$editable_textarea_sections = array(
				__( 'Daily Living and Personal Care', 'jm-referral-system' ) => $daily_living_fields,
				__( 'Communication and Cognition', 'jm-referral-system' )   => $communication_fields,
				__( 'Home and Safety', 'jm-referral-system' )               => $home_safety_fields,
				__( 'Support Network', 'jm-referral-system' )               => $support_network_fields,
			);
			?>
			<?php foreach ( $editable_textarea_sections as $section_title => $fields ) : ?>
				<h3><?php echo esc_html( (string) $section_title ); ?></h3>
				<table class="form-table" role="presentation">
					<tbody>
						<?php foreach ( $fields as $field_key => $field_label ) : ?>
							<?php
							$field_id    = 'jmrs_assessment_' . $field_key;
							$field_value = $jmrs_assessment_value( $field_key );
							?>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( (string) $field_label ); ?></label>
								</th>
								<td>
									<textarea
										name="<?php echo esc_attr( $field_id ); ?>"
										id="<?php echo esc_attr( $field_id ); ?>"
										class="large-text"
										rows="3"
									><?php echo esc_textarea( $field_value ); ?></textarea>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>

			<h3><?php echo esc_html__( 'Proposed Care Package', 'jm-referral-system' ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
					<?php foreach ( $care_package_text as $field_key => $field_label ) : ?>
						<?php
						$field_id    = 'jmrs_assessment_' . $field_key;
						$field_value = $jmrs_assessment_value( $field_key );
						?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( (string) $field_label ); ?></label>
							</th>
							<td>
								<input
									type="text"
									class="regular-text"
									name="<?php echo esc_attr( $field_id ); ?>"
									id="<?php echo esc_attr( $field_id ); ?>"
									value="<?php echo esc_attr( $field_value ); ?>"
								/>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php foreach ( $care_package_textareas as $field_key => $field_label ) : ?>
						<?php
						$field_id    = 'jmrs_assessment_' . $field_key;
						$field_value = $jmrs_assessment_value( $field_key );
						?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( (string) $field_label ); ?></label>
							</th>
							<td>
								<textarea
									name="<?php echo esc_attr( $field_id ); ?>"
									id="<?php echo esc_attr( $field_id ); ?>"
									class="large-text"
									rows="3"
								><?php echo esc_textarea( $field_value ); ?></textarea>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php echo esc_html__( 'Summary and Recommendations', 'jm-referral-system' ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
					<?php foreach ( $summary_fields as $field_key => $field_label ) : ?>
						<?php
						$field_id    = 'jmrs_assessment_' . $field_key;
						$field_value = $jmrs_assessment_value( $field_key );
						?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( (string) $field_label ); ?></label>
							</th>
							<td>
								<textarea
									name="<?php echo esc_attr( $field_id ); ?>"
									id="<?php echo esc_attr( $field_id ); ?>"
									class="large-text"
									rows="4"
								><?php echo esc_textarea( $field_value ); ?></textarea>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			submit_button(
				__( 'Save Assessment', 'jm-referral-system' ),
				'primary',
				'jmrs_save_assessment'
			);
			?>
		</form>
	<?php endif; ?>

	<?php if ( $can_view_care_plan ) : ?>
		<?php
		$care_plan_status_value = $jmrs_care_plan_value( 'plan_status' );
		$care_plan_status_label = isset( $care_plan_statuses[ $care_plan_status_value ] )
			? (string) $care_plan_statuses[ $care_plan_status_value ]
			: ucfirst( str_replace( '_', ' ', $care_plan_status_value ) );

		$care_plan_content_fields = array(
			'visit_frequency'         => __( 'Visit Frequency', 'jm-referral-system' ),
			'visit_duration'          => __( 'Visit Duration', 'jm-referral-system' ),
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
		$care_plan_short_fields = array( 'visit_frequency', 'visit_duration' );
		?>
		<h2><?php echo esc_html__( 'Care Plan', 'jm-referral-system' ); ?></h2>

		<?php if ( $show_care_plan_form ) : ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'jmrs_save_care_plan_' . $referral_id, 'jmrs_save_care_plan_nonce' ); ?>
				<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
				<input type="hidden" name="jmrs_care_plan_assessment_id" value="<?php echo esc_attr( $jmrs_care_plan_value( 'assessment_id' ) ); ?>" />

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="jmrs_care_plan_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<select name="jmrs_care_plan_status" id="jmrs_care_plan_status">
									<?php foreach ( $care_plan_statuses as $status_value => $status_label ) : ?>
										<option value="<?php echo esc_attr( (string) $status_value ); ?>" <?php selected( $care_plan_status_value, (string) $status_value ); ?>>
											<?php echo esc_html( (string) $status_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ( isset( $care_plan_errors['plan_status'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_plan_errors['plan_status'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_care_plan_start_date"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<input
									type="date"
									name="jmrs_care_plan_start_date"
									id="jmrs_care_plan_start_date"
									value="<?php echo esc_attr( $jmrs_care_plan_value( 'start_date' ) ); ?>"
								/>
								<?php if ( isset( $care_plan_errors['start_date'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_plan_errors['start_date'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_care_plan_review_date"><?php echo esc_html__( 'Review Date', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<input
									type="date"
									name="jmrs_care_plan_review_date"
									id="jmrs_care_plan_review_date"
									value="<?php echo esc_attr( $jmrs_care_plan_value( 'review_date' ) ); ?>"
								/>
								<?php if ( isset( $care_plan_errors['review_date'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_plan_errors['review_date'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( null !== $care_plan ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Created By', 'jm-referral-system' ); ?></th>
								<td><?php echo '' !== $care_plan_created_by_name ? esc_html( $care_plan_created_by_name ) : '—'; ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Approved By', 'jm-referral-system' ); ?></th>
								<td><?php echo '' !== $care_plan_approved_by_name ? esc_html( $care_plan_approved_by_name ) : '—'; ?></td>
							</tr>
						<?php endif; ?>
						<?php foreach ( $care_plan_content_fields as $field_key => $field_label ) : ?>
							<?php
							$field_id    = 'jmrs_care_plan_' . $field_key;
							$field_value = $jmrs_care_plan_value( $field_key );
							$is_short    = in_array( $field_key, $care_plan_short_fields, true );
							?>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( (string) $field_label ); ?></label>
								</th>
								<td>
									<?php if ( $is_short ) : ?>
										<input
											type="text"
											class="regular-text"
											name="<?php echo esc_attr( $field_id ); ?>"
											id="<?php echo esc_attr( $field_id ); ?>"
											value="<?php echo esc_attr( $field_value ); ?>"
										/>
									<?php else : ?>
										<textarea
											name="<?php echo esc_attr( $field_id ); ?>"
											id="<?php echo esc_attr( $field_id ); ?>"
											class="large-text"
											rows="3"
										><?php echo esc_textarea( $field_value ); ?></textarea>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						<?php if ( null !== $care_plan ) : ?>
							<tr>
								<th scope="row">
									<label for="jmrs_care_plan_change_summary"><?php echo esc_html__( 'Change Summary', 'jm-referral-system' ); ?></label>
								</th>
								<td>
									<textarea
										name="jmrs_care_plan_change_summary"
										id="jmrs_care_plan_change_summary"
										class="large-text"
										rows="2"
										placeholder="<?php echo esc_attr__( 'Updated medication support and visit frequency after review.', 'jm-referral-system' ); ?>"
									><?php echo esc_textarea( $jmrs_care_plan_value( 'change_summary' ) ); ?></textarea>
									<p class="description"><?php echo esc_html__( 'Optional. Stored with the previous version when this care plan is updated.', 'jm-referral-system' ); ?></p>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>

				<?php
				submit_button(
					__( 'Save Care Plan', 'jm-referral-system' ),
					'primary',
					'jmrs_save_care_plan'
				);
				?>
			</form>
		<?php elseif ( null !== $care_plan ) : ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
						<td><?php echo esc_html( $care_plan_status_label ); ?></td>
					</tr>
					<?php
					$start_date_value  = $jmrs_care_plan_value( 'start_date' );
					$review_date_value = $jmrs_care_plan_value( 'review_date' );
					?>
					<?php if ( '' !== trim( $start_date_value ) ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></th>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $start_date_value ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( '' !== trim( $review_date_value ) ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Review Date', 'jm-referral-system' ); ?></th>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $review_date_value ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( '' !== $care_plan_created_by_name ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Created By', 'jm-referral-system' ); ?></th>
							<td><?php echo esc_html( $care_plan_created_by_name ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( '' !== $care_plan_approved_by_name ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Approved By', 'jm-referral-system' ); ?></th>
							<td><?php echo esc_html( $care_plan_approved_by_name ); ?></td>
						</tr>
					<?php endif; ?>
					<?php foreach ( $care_plan_content_fields as $field_key => $field_label ) : ?>
						<?php
						$field_value = $jmrs_care_plan_value( $field_key );
						if ( '' === trim( $field_value ) ) {
							continue;
						}
						$is_short = in_array( $field_key, $care_plan_short_fields, true );
						?>
						<tr>
							<th scope="row"><?php echo esc_html( (string) $field_label ); ?></th>
							<td>
								<?php
								if ( $is_short ) {
									echo esc_html( $field_value );
								} else {
									echo nl2br( esc_html( $field_value ) );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php elseif ( $can_manage_care_plan ) : ?>
			<p><?php echo esc_html__( 'No care plan has been created yet.', 'jm-referral-system' ); ?></p>
			<div class="jmrs-care-plan-actions">
				<?php if ( $has_assessment ) : ?>
					<form method="post" action="" style="display:inline-block; margin-right:8px;">
						<?php wp_nonce_field( 'jmrs_generate_care_plan_' . $referral_id, 'jmrs_generate_care_plan_nonce' ); ?>
						<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
						<?php
						submit_button(
							__( 'Generate Care Plan from Assessment', 'jm-referral-system' ),
							'secondary',
							'jmrs_generate_care_plan',
							false
						);
						?>
					</form>
				<?php endif; ?>
				<form method="post" action="" style="display:inline-block;">
					<?php wp_nonce_field( 'jmrs_blank_care_plan_' . $referral_id, 'jmrs_blank_care_plan_nonce' ); ?>
					<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
					<?php
					submit_button(
						__( 'Create Blank Care Plan', 'jm-referral-system' ),
						'secondary',
						'jmrs_blank_care_plan',
						false
					);
					?>
				</form>
			</div>
		<?php else : ?>
			<p><?php echo esc_html__( 'No care plan available.', 'jm-referral-system' ); ?></p>
		<?php endif; ?>

		<?php if ( null !== $care_plan && $can_review_care_plan ) : ?>
			<h2><?php echo esc_html__( 'Care Plan Review', 'jm-referral-system' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'jmrs_care_plan_review_' . $referral_id, 'jmrs_care_plan_review_nonce' ); ?>
				<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="jmrs_care_plan_review_date"><?php echo esc_html__( 'Review Date', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<input
									type="date"
									class="regular-text"
									name="jmrs_care_plan_review_date"
									id="jmrs_care_plan_review_date"
									value="<?php echo esc_attr( $jmrs_care_plan_review_value( 'review_date' ) ); ?>"
									required
								/>
								<?php if ( isset( $care_plan_review_errors['review_date'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_plan_review_errors['review_date'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_care_plan_review_outcome"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<select name="jmrs_care_plan_review_outcome" id="jmrs_care_plan_review_outcome" required>
									<option value=""><?php echo esc_html__( 'Select outcome', 'jm-referral-system' ); ?></option>
									<?php foreach ( $care_plan_review_outcomes as $outcome_value => $outcome_label ) : ?>
										<option value="<?php echo esc_attr( (string) $outcome_value ); ?>" <?php selected( $jmrs_care_plan_review_value( 'outcome' ), (string) $outcome_value ); ?>>
											<?php echo esc_html( (string) $outcome_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ( isset( $care_plan_review_errors['outcome'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_plan_review_errors['outcome'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_care_plan_review_notes"><?php echo esc_html__( 'Review Notes', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<textarea
									name="jmrs_care_plan_review_notes"
									id="jmrs_care_plan_review_notes"
									class="large-text"
									rows="4"
								><?php echo esc_textarea( $jmrs_care_plan_review_value( 'notes' ) ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_care_plan_review_next_date"><?php echo esc_html__( 'Next Review Date', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<input
									type="date"
									class="regular-text"
									name="jmrs_care_plan_review_next_date"
									id="jmrs_care_plan_review_next_date"
									value="<?php echo esc_attr( $jmrs_care_plan_review_value( 'next_review_date' ) ); ?>"
								/>
								<?php if ( isset( $care_plan_review_errors['next_review_date'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_plan_review_errors['next_review_date'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
				<?php
				submit_button(
					__( 'Add Review', 'jm-referral-system' ),
					'secondary',
					'jmrs_submit_care_plan_review'
				);
				?>
			</form>
		<?php endif; ?>

		<?php if ( null !== $care_plan && $can_view_care_plan ) : ?>
			<h2><?php echo esc_html__( 'Care Plan Reviews', 'jm-referral-system' ); ?></h2>
			<?php if ( empty( $care_plan_reviews ) ) : ?>
				<p><?php echo esc_html__( 'No care plan reviews recorded yet.', 'jm-referral-system' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped table-view-list">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Review Date', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Reviewed By', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Next Review Date', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Created Date', 'jm-referral-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $care_plan_reviews as $review_row ) : ?>
							<?php
							$review_date_raw      = (string) ( $review_row['review_date'] ?? '' );
							$next_review_raw      = (string) ( $review_row['next_review_date'] ?? '' );
							$created_at_raw       = (string) ( $review_row['created_at'] ?? '' );
							$outcome_key          = (string) ( $review_row['outcome'] ?? '' );
							$outcome_label        = isset( $care_plan_review_outcomes[ $outcome_key ] )
								? (string) $care_plan_review_outcomes[ $outcome_key ]
								: ucfirst( str_replace( '_', ' ', $outcome_key ) );
							$reviewed_by_name     = (string) ( $review_row['reviewed_by_name'] ?? '' );
							$review_notes         = (string) ( $review_row['notes'] ?? '' );
							$review_date_display  = '' !== $review_date_raw
								? mysql2date( get_option( 'date_format' ), $review_date_raw )
								: '';
							$next_review_display  = '' !== $next_review_raw
								? mysql2date( get_option( 'date_format' ), $next_review_raw )
								: '—';
							$created_display      = '' !== $created_at_raw
								? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at_raw )
								: '';
							?>
							<tr>
								<td><?php echo esc_html( $review_date_display ); ?></td>
								<td><?php echo esc_html( $outcome_label ); ?></td>
								<td><?php echo '' !== $reviewed_by_name ? esc_html( $reviewed_by_name ) : esc_html__( 'Unknown', 'jm-referral-system' ); ?></td>
								<td><?php echo '' !== trim( $review_notes ) ? nl2br( esc_html( $review_notes ) ) : '—'; ?></td>
								<td><?php echo esc_html( $next_review_display ); ?></td>
								<td><?php echo esc_html( $created_display ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Care Plan Version History', 'jm-referral-system' ); ?></h2>
			<?php if ( empty( $care_plan_versions ) ) : ?>
				<p><?php echo esc_html__( 'No previous versions yet. A snapshot is created when the care plan is updated.', 'jm-referral-system' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped table-view-list">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Version', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Created By', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Created Date', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Change Summary', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'View Snapshot', 'jm-referral-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $care_plan_versions as $version_row ) : ?>
							<?php
							$version_number     = absint( $version_row['version_number'] ?? 0 );
							$version_created_by = (string) ( $version_row['created_by_name'] ?? '' );
							$version_created_at = (string) ( $version_row['created_at'] ?? '' );
							$version_summary    = (string) ( $version_row['change_summary'] ?? '' );
							$version_view_url   = (string) ( $version_row['view_url'] ?? '' );
							$version_created_display = '' !== $version_created_at
								? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $version_created_at )
								: '';
							?>
							<tr>
								<td><?php echo esc_html( (string) $version_number ); ?></td>
								<td><?php echo '' !== $version_created_by ? esc_html( $version_created_by ) : esc_html__( 'Unknown', 'jm-referral-system' ); ?></td>
								<td><?php echo esc_html( $version_created_display ); ?></td>
								<td><?php echo '' !== trim( $version_summary ) ? esc_html( $version_summary ) : '—'; ?></td>
								<td>
									<?php if ( '' !== $version_view_url ) : ?>
										<a href="<?php echo esc_url( $version_view_url ); ?>">
											<?php echo esc_html__( 'View Snapshot', 'jm-referral-system' ); ?>
										</a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $can_view_care_team ) : ?>
		<h2><?php echo esc_html__( 'Care Team', 'jm-referral-system' ); ?></h2>

		<?php if ( $can_manage_care_team ) : ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'jmrs_save_care_team_' . $referral_id, 'jmrs_care_team_nonce' ); ?>
				<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
				<input type="hidden" name="jmrs_care_team_id" value="0" />
				<input type="hidden" name="jmrs_care_team_care_plan_id" value="<?php echo esc_attr( $jmrs_care_team_value( 'care_plan_id' ) ); ?>" />

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="jmrs_care_team_user_id"><?php echo esc_html__( 'Staff Member', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<select name="jmrs_care_team_user_id" id="jmrs_care_team_user_id" required>
									<option value=""><?php echo esc_html__( 'Select staff member', 'jm-referral-system' ); ?></option>
									<?php foreach ( $care_team_assignable_users as $user_row ) : ?>
										<option value="<?php echo esc_attr( (string) ( $user_row['id'] ?? 0 ) ); ?>" <?php selected( $jmrs_care_team_value( 'user_id' ), (string) ( $user_row['id'] ?? 0 ) ); ?>>
											<?php echo esc_html( (string) ( $user_row['display_name'] ?? '' ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ( isset( $care_team_errors['user_id'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_team_errors['user_id'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_care_team_role"><?php echo esc_html__( 'Team Role', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<select name="jmrs_care_team_role" id="jmrs_care_team_role" required>
									<option value=""><?php echo esc_html__( 'Select role', 'jm-referral-system' ); ?></option>
									<?php foreach ( $care_team_roles as $role_value => $role_label ) : ?>
										<option value="<?php echo esc_attr( (string) $role_value ); ?>" <?php selected( $jmrs_care_team_value( 'team_role' ), (string) $role_value ); ?>>
											<?php echo esc_html( (string) $role_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ( isset( $care_team_errors['team_role'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_team_errors['team_role'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Primary', 'jm-referral-system' ); ?></th>
							<td>
								<label for="jmrs_care_team_is_primary">
									<input
										type="checkbox"
										name="jmrs_care_team_is_primary"
										id="jmrs_care_team_is_primary"
										value="1"
										<?php checked( $jmrs_care_team_value( 'is_primary' ), '1' ); ?>
									/>
									<?php echo esc_html__( 'Primary Carer for this referral', 'jm-referral-system' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_care_team_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<select name="jmrs_care_team_status" id="jmrs_care_team_status">
									<?php foreach ( $care_team_statuses as $status_value => $status_label ) : ?>
										<option value="<?php echo esc_attr( (string) $status_value ); ?>" <?php selected( $jmrs_care_team_value( 'assignment_status' ), (string) $status_value ); ?>>
											<?php echo esc_html( (string) $status_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ( isset( $care_team_errors['assignment_status'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_team_errors['assignment_status'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_care_team_start_date"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<input
									type="date"
									class="regular-text"
									name="jmrs_care_team_start_date"
									id="jmrs_care_team_start_date"
									value="<?php echo esc_attr( $jmrs_care_team_value( 'start_date' ) ); ?>"
									required
								/>
								<?php if ( isset( $care_team_errors['start_date'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_team_errors['start_date'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_care_team_end_date"><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<input
									type="date"
									class="regular-text"
									name="jmrs_care_team_end_date"
									id="jmrs_care_team_end_date"
									value="<?php echo esc_attr( $jmrs_care_team_value( 'end_date' ) ); ?>"
								/>
								<?php if ( isset( $care_team_errors['end_date'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_team_errors['end_date'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_care_team_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<textarea name="jmrs_care_team_notes" id="jmrs_care_team_notes" class="large-text" rows="3"><?php echo esc_textarea( $jmrs_care_team_value( 'notes' ) ); ?></textarea>
							</td>
						</tr>
					</tbody>
				</table>

				<?php
				submit_button(
					__( 'Assign Team Member', 'jm-referral-system' ),
					'secondary',
					'jmrs_save_care_team'
				);
				?>
			</form>
		<?php endif; ?>

		<?php if ( empty( $care_team_members ) ) : ?>
			<p><?php echo esc_html__( 'No care team members assigned yet.', 'jm-referral-system' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Staff Member', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Team Role', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Primary', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></th>
						<?php if ( $can_manage_care_team ) : ?>
							<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $care_team_members as $member_row ) : ?>
						<?php
						$staff_name     = (string) ( $member_row['staff_name'] ?? '' );
						$role_key       = (string) ( $member_row['team_role'] ?? '' );
						$role_label     = isset( $care_team_roles[ $role_key ] )
							? (string) $care_team_roles[ $role_key ]
							: ucfirst( str_replace( '_', ' ', $role_key ) );
						$status_key     = (string) ( $member_row['assignment_status'] ?? '' );
						$status_label   = isset( $care_team_statuses[ $status_key ] )
							? (string) $care_team_statuses[ $status_key ]
							: ucfirst( str_replace( '_', ' ', $status_key ) );
						$is_primary     = ! empty( $member_row['is_primary'] );
						$start_raw      = (string) ( $member_row['start_date'] ?? '' );
						$end_raw        = (string) ( $member_row['end_date'] ?? '' );
						$notes          = (string) ( $member_row['notes'] ?? '' );
						$edit_url       = (string) ( $member_row['edit_url'] ?? '' );
						$start_display  = '' !== $start_raw ? mysql2date( get_option( 'date_format' ), $start_raw ) : '';
						$end_display    = '' !== $end_raw ? mysql2date( get_option( 'date_format' ), $end_raw ) : '—';
						?>
						<tr>
							<td><?php echo '' !== $staff_name ? esc_html( $staff_name ) : esc_html__( 'Unknown', 'jm-referral-system' ); ?></td>
							<td><?php echo esc_html( $role_label ); ?></td>
							<td><?php echo $is_primary ? esc_html__( 'Yes', 'jm-referral-system' ) : esc_html__( 'No', 'jm-referral-system' ); ?></td>
							<td><?php echo esc_html( $status_label ); ?></td>
							<td><?php echo esc_html( $start_display ); ?></td>
							<td><?php echo esc_html( $end_display ); ?></td>
							<td><?php echo '' !== trim( $notes ) ? nl2br( esc_html( $notes ) ) : '—'; ?></td>
							<?php if ( $can_manage_care_team ) : ?>
								<td>
									<?php if ( '' !== $edit_url ) : ?>
										<a href="<?php echo esc_url( $edit_url ); ?>">
											<?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?>
										</a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $can_view_visits ) : ?>
		<h2><?php echo esc_html__( 'Care Visits', 'jm-referral-system' ); ?></h2>

		<?php if ( $can_manage_visits ) : ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'jmrs_save_care_visit_' . $referral_id, 'jmrs_care_visit_nonce' ); ?>
				<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
				<input type="hidden" name="jmrs_visit_id" value="0" />
				<input type="hidden" name="jmrs_visit_care_plan_id" value="<?php echo esc_attr( $jmrs_care_visit_value( 'care_plan_id' ) ); ?>" />

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="jmrs_visit_date"><?php echo esc_html__( 'Visit Date', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<input
									type="date"
									class="regular-text"
									name="jmrs_visit_date"
									id="jmrs_visit_date"
									value="<?php echo esc_attr( $jmrs_care_visit_value( 'visit_date' ) ); ?>"
									required
								/>
								<?php if ( isset( $care_visit_errors['visit_date'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_visit_errors['visit_date'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_visit_start_time"><?php echo esc_html__( 'Start Time', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<input
									type="time"
									class="regular-text"
									name="jmrs_visit_start_time"
									id="jmrs_visit_start_time"
									value="<?php echo esc_attr( $jmrs_care_visit_value( 'start_time' ) ); ?>"
									required
								/>
								<?php if ( isset( $care_visit_errors['start_time'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_visit_errors['start_time'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_visit_end_time"><?php echo esc_html__( 'End Time', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<input
									type="time"
									class="regular-text"
									name="jmrs_visit_end_time"
									id="jmrs_visit_end_time"
									value="<?php echo esc_attr( $jmrs_care_visit_value( 'end_time' ) ); ?>"
									required
								/>
								<?php if ( isset( $care_visit_errors['end_time'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_visit_errors['end_time'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_visit_assigned_user_id"><?php echo esc_html__( 'Assigned Staff', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<select name="jmrs_visit_assigned_user_id" id="jmrs_visit_assigned_user_id">
									<option value="0"><?php echo esc_html__( '— Unassigned —', 'jm-referral-system' ); ?></option>
									<?php foreach ( $assignable_users as $user_row ) : ?>
										<option value="<?php echo esc_attr( (string) ( $user_row['id'] ?? 0 ) ); ?>" <?php selected( $jmrs_care_visit_value( 'assigned_user_id' ), (string) ( $user_row['id'] ?? 0 ) ); ?>>
											<?php echo esc_html( (string) ( $user_row['display_name'] ?? '' ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ( isset( $care_visit_errors['assigned_user_id'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_visit_errors['assigned_user_id'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_visit_type"><?php echo esc_html__( 'Visit Type', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									class="regular-text"
									name="jmrs_visit_type"
									id="jmrs_visit_type"
									value="<?php echo esc_attr( $jmrs_care_visit_value( 'visit_type' ) ); ?>"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_visit_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<select name="jmrs_visit_status" id="jmrs_visit_status">
									<?php foreach ( $care_visit_statuses as $status_value => $status_label ) : ?>
										<option value="<?php echo esc_attr( (string) $status_value ); ?>" <?php selected( $jmrs_care_visit_value( 'visit_status' ), (string) $status_value ); ?>>
											<?php echo esc_html( (string) $status_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ( isset( $care_visit_errors['visit_status'] ) ) : ?>
									<p class="description"><?php echo esc_html( $care_visit_errors['visit_status'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_visit_tasks"><?php echo esc_html__( 'Tasks', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<textarea name="jmrs_visit_tasks" id="jmrs_visit_tasks" class="large-text" rows="3"><?php echo esc_textarea( $jmrs_care_visit_value( 'tasks' ) ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="jmrs_visit_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<textarea name="jmrs_visit_notes" id="jmrs_visit_notes" class="large-text" rows="3"><?php echo esc_textarea( $jmrs_care_visit_value( 'notes' ) ); ?></textarea>
							</td>
						</tr>
					</tbody>
				</table>

				<?php
				submit_button(
					__( 'Save Visit', 'jm-referral-system' ),
					'secondary',
					'jmrs_save_care_visit'
				);
				?>
			</form>
		<?php endif; ?>

		<?php if ( empty( $care_visits ) ) : ?>
			<p><?php echo esc_html__( 'No care visits scheduled yet.', 'jm-referral-system' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Date', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Time', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Assigned Staff', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Visit Type', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
						<?php if ( $can_manage_visits ) : ?>
							<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $care_visits as $visit_row ) : ?>
						<?php
						$visit_date_raw   = (string) ( $visit_row['visit_date'] ?? '' );
						$start_time_raw   = (string) ( $visit_row['start_time'] ?? '' );
						$end_time_raw     = (string) ( $visit_row['end_time'] ?? '' );
						$visit_type       = (string) ( $visit_row['visit_type'] ?? '' );
						$status_key       = (string) ( $visit_row['visit_status'] ?? '' );
						$status_label     = isset( $care_visit_statuses[ $status_key ] )
							? (string) $care_visit_statuses[ $status_key ]
							: ucfirst( str_replace( '_', ' ', $status_key ) );
						$assigned_name    = (string) ( $visit_row['assigned_staff_name'] ?? '' );
						$edit_url         = (string) ( $visit_row['edit_url'] ?? '' );
						$visit_date_display = '' !== $visit_date_raw
							? mysql2date( get_option( 'date_format' ), $visit_date_raw )
							: '';
						$start_display = '' !== $start_time_raw ? substr( $start_time_raw, 0, 5 ) : '';
						$end_display   = '' !== $end_time_raw ? substr( $end_time_raw, 0, 5 ) : '';
						$time_display  = trim( $start_display . ( '' !== $end_display ? ' – ' . $end_display : '' ) );
						?>
						<tr>
							<td><?php echo esc_html( $visit_date_display ); ?></td>
							<td><?php echo esc_html( $time_display ); ?></td>
							<td><?php echo '' !== $assigned_name ? esc_html( $assigned_name ) : '—'; ?></td>
							<td><?php echo '' !== trim( $visit_type ) ? esc_html( $visit_type ) : '—'; ?></td>
							<td><?php echo esc_html( $status_label ); ?></td>
							<?php if ( $can_manage_visits ) : ?>
								<td>
									<?php if ( '' !== $edit_url ) : ?>
										<a href="<?php echo esc_url( $edit_url ); ?>">
											<?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?>
										</a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>

	<h2><?php echo esc_html__( 'Internal Notes', 'jm-referral-system' ); ?></h2>

	<?php if ( empty( $notes ) ) : ?>
		<p><?php echo esc_html__( 'No internal notes yet.', 'jm-referral-system' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Author', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Date/Time', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Note', 'jm-referral-system' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $notes as $note_row ) : ?>
					<?php
					$author_name     = (string) ( $note_row['author_name'] ?? '' );
					$note_created    = (string) ( $note_row['created_at'] ?? '' );
					$note_display    = '' !== $note_created
						? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $note_created )
						: '';
					$note_content    = (string) ( $note_row['note'] ?? '' );
					?>
					<tr>
						<td><?php echo '' !== $author_name ? esc_html( $author_name ) : esc_html__( 'Unknown', 'jm-referral-system' ); ?></td>
						<td><?php echo esc_html( $note_display ); ?></td>
						<td><?php echo nl2br( esc_html( $note_content ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'jmrs_add_note_' . $referral_id, 'jmrs_add_note_nonce' ); ?>
		<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="jmrs_note"><?php echo esc_html__( 'Add Note', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<textarea
							name="jmrs_note"
							id="jmrs_note"
							class="large-text"
							rows="4"
							required
						><?php echo esc_textarea( $note_value ); ?></textarea>
						<?php if ( isset( $note_errors['note'] ) ) : ?>
							<p class="description"><?php echo esc_html( $note_errors['note'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<?php
		submit_button(
			__( 'Add Note', 'jm-referral-system' ),
			'secondary',
			'jmrs_submit_note'
		);
		?>
	</form>

	<?php if ( ! empty( $can_download_documents ) || ! empty( $can_upload_documents ) ) : ?>
		<h2><?php echo esc_html__( 'Documents', 'jm-referral-system' ); ?></h2>

		<?php if ( ! empty( $can_download_documents ) ) : ?>
			<?php if ( empty( $documents ) ) : ?>
				<p><?php echo esc_html__( 'No documents uploaded yet.', 'jm-referral-system' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped table-view-list">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Filename', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'File Type', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'File Size', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Uploaded By', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Uploaded Date', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $documents as $document_row ) : ?>
							<?php
							$doc_name      = (string) ( $document_row['original_name'] ?? '' );
							$doc_mime      = (string) ( $document_row['mime_type'] ?? '' );
							$doc_size      = absint( $document_row['file_size'] ?? 0 );
							$doc_uploader  = (string) ( $document_row['uploaded_by_name'] ?? '' );
							$doc_created   = (string) ( $document_row['created_at'] ?? '' );
							$doc_display   = '' !== $doc_created
								? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $doc_created )
								: '';
							$download_url  = (string) ( $document_row['download_url'] ?? '' );
							$size_display  = size_format( $doc_size );
							?>
							<tr>
								<td><?php echo esc_html( $doc_name ); ?></td>
								<td><?php echo esc_html( $doc_mime ); ?></td>
								<td><?php echo esc_html( is_string( $size_display ) ? $size_display : (string) $doc_size ); ?></td>
								<td><?php echo '' !== $doc_uploader ? esc_html( $doc_uploader ) : esc_html__( 'Unknown', 'jm-referral-system' ); ?></td>
								<td><?php echo esc_html( $doc_display ); ?></td>
								<td>
									<?php if ( '' !== $download_url ) : ?>
										<a href="<?php echo esc_url( $download_url ); ?>">
											<?php echo esc_html__( 'Download', 'jm-referral-system' ); ?>
										</a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( ! empty( $can_upload_documents ) ) : ?>
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'jmrs_upload_document_' . $referral_id, 'jmrs_upload_document_nonce' ); ?>
				<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="jmrs_document"><?php echo esc_html__( 'Upload Document', 'jm-referral-system' ); ?></label>
							</th>
							<td>
								<input
									type="file"
									name="jmrs_document"
									id="jmrs_document"
									accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/png"
									required
								/>
								<p class="description">
									<?php echo esc_html__( 'Allowed types: PDF, DOC, DOCX, JPG, JPEG, PNG. Maximum size: 10 MB.', 'jm-referral-system' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php
				submit_button(
					__( 'Upload Document', 'jm-referral-system' ),
					'secondary',
					'jmrs_upload_document'
				);
				?>
			</form>
		<?php endif; ?>
	<?php endif; ?>

	<h2><?php echo esc_html__( 'Activity Timeline', 'jm-referral-system' ); ?></h2>
	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Date/Time', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Action', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Description', 'jm-referral-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $activities ) ) : ?>
				<tr class="no-items">
					<td colspan="3"><?php echo esc_html__( 'No activity recorded for this referral.', 'jm-referral-system' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $activities as $activity ) : ?>
					<?php
					$activity_created = (string) ( $activity['created_at'] ?? '' );
					$activity_display = '' !== $activity_created
						? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $activity_created )
						: '';
					$action           = (string) ( $activity['action'] ?? '' );
					$action_display   = ucfirst( str_replace( '_', ' ', $action ) );
					$description      = (string) ( $activity['description'] ?? '' );
					?>
					<tr>
						<td><?php echo esc_html( $activity_display ); ?></td>
						<td><?php echo esc_html( $action_display ); ?></td>
						<td><?php echo esc_html( $description ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
