<?php
/**
 * Portal referral view (read-only).
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral                   = is_array( $referral ?? null ) ? $referral : array();
$assigned_to_name           = (string) ( $assigned_to_name ?? '' );
$service_name               = (string) ( $service_name ?? '' );
$workflow_stage_name        = (string) ( $workflow_stage_name ?? '' );
$is_archived                = ! empty( $is_archived );
$archived_at                = (string) ( $archived_at ?? '' );
$archive_reason             = (string) ( $archive_reason ?? '' );
$archived_by_name           = (string) ( $archived_by_name ?? '' );
$documents                  = is_array( $documents ?? null ) ? $documents : array();
$can_download_documents     = ! empty( $can_download_documents );
$assessment                 = is_array( $assessment ?? null ) ? $assessment : null;
$assessor_name              = (string) ( $assessor_name ?? '' );
$assessment_outcomes        = is_array( $assessment_outcomes ?? null ) ? $assessment_outcomes : array();
$can_view_care_plan         = ! empty( $can_view_care_plan );
$care_plan                  = is_array( $care_plan ?? null ) ? $care_plan : null;
$care_plan_statuses         = is_array( $care_plan_statuses ?? null ) ? $care_plan_statuses : array();
$care_plan_created_by_name  = (string) ( $care_plan_created_by_name ?? '' );
$care_plan_approved_by_name = (string) ( $care_plan_approved_by_name ?? '' );
$can_view_care_team         = ! empty( $can_view_care_team );
$care_team_members          = is_array( $care_team_members ?? null ) ? $care_team_members : array();
$care_team_roles            = is_array( $care_team_roles ?? null ) ? $care_team_roles : array();
$care_team_statuses         = is_array( $care_team_statuses ?? null ) ? $care_team_statuses : array();
$can_view_visits            = ! empty( $can_view_visits );
$care_visits                = is_array( $care_visits ?? null ) ? $care_visits : array();
$visit_status_labels        = is_array( $visit_status_labels ?? null ) ? $visit_status_labels : array();
$can_view_medications       = ! empty( $can_view_medications );
$medications                = is_array( $medications ?? null ) ? $medications : array();
$medication_status_labels   = is_array( $medication_status_labels ?? null ) ? $medication_status_labels : array();
$medication_route_labels    = is_array( $medication_route_labels ?? null ) ? $medication_route_labels : array();
$activities                 = is_array( $activities ?? null ) ? $activities : array();
$submission_channel_label   = (string) ( $submission_channel_label ?? '' );
$is_public_referral         = ! empty( $is_public_referral );
$referrer_type_label        = (string) ( $referrer_type_label ?? '' );
$list_url                   = (string) ( $list_url ?? '' );

$client_name = trim( (string) ( $referral['client_first_name'] ?? '' ) . ' ' . (string) ( $referral['client_last_name'] ?? '' ) );
if ( '' === $client_name ) {
	$client_name = (string) ( $referral['client_name'] ?? '' );
}
$care_needs = (string) ( $referral['care_requirements'] ?? '' );
$address_parts = array_filter(
	array(
		(string) ( $referral['address_line_1'] ?? '' ),
		(string) ( $referral['address_line_2'] ?? '' ),
		(string) ( $referral['city'] ?? '' ),
		(string) ( $referral['postcode'] ?? '' ),
	)
);
$address_display = ! empty( $address_parts ) ? implode( ', ', $address_parts ) : '—';
$client_dob      = (string) ( $referral['client_date_of_birth'] ?? '' );
$client_dob_display = '' !== $client_dob
	? mysql2date( get_option( 'date_format' ), $client_dob )
	: '—';
?>
<p class="jmrs-portal-actions">
	<a class="jmrs-portal-btn" href="<?php echo esc_url( $list_url ); ?>"><?php echo esc_html__( 'Back to list', 'jm-referral-system' ); ?></a>
</p>

<?php if ( $is_archived ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--warning" role="status">
		<p>
			<strong><?php echo esc_html__( 'Archived referral', 'jm-referral-system' ); ?></strong>
			<?php if ( '' !== $archived_at ) : ?>
				— <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $archived_at ) ); ?>
			<?php endif; ?>
			<?php if ( '' !== $archived_by_name ) : ?>
				(<?php echo esc_html( $archived_by_name ); ?>)
			<?php endif; ?>
		</p>
		<?php if ( '' !== $archive_reason ) : ?>
			<p><?php echo esc_html( $archive_reason ); ?></p>
		<?php endif; ?>
	</div>
<?php endif; ?>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-ref-summary">
	<h2 id="jmrs-portal-ref-summary" class="jmrs-portal-section__title"><?php echo esc_html__( 'Summary', 'jm-referral-system' ); ?></h2>
	<div class="jmrs-portal-dl-grid">
		<div>
			<dt><?php echo esc_html__( 'Referral number', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( (string) ( $referral['referral_number'] ?? '' ) ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></dt>
			<dd><span class="jmrs-portal-badge"><?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) ( $referral['status'] ?? '' ) ) ) ); ?></span></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?></dt>
			<dd><span class="jmrs-portal-badge jmrs-portal-badge--priority"><?php echo esc_html( ucfirst( (string) ( $referral['priority'] ?? '' ) ) ); ?></span></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Workflow stage', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $workflow_stage_name ? $workflow_stage_name : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Service', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $service_name ? $service_name : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Assigned to', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $assigned_to_name ? $assigned_to_name : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Source', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $submission_channel_label ? $submission_channel_label : '—' ); ?></dd>
		</div>
	</div>
</section>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-ref-client">
	<h2 id="jmrs-portal-ref-client" class="jmrs-portal-section__title"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?></h2>
	<div class="jmrs-portal-dl-grid">
		<div>
			<dt><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $client_name ? $client_name : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Date of birth', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( $client_dob_display ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Phone', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( (string) ( $referral['client_phone'] ?? '—' ) ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Email', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( (string) ( $referral['client_email'] ?? '—' ) ); ?></dd>
		</div>
		<div class="jmrs-portal-dl-grid__wide">
			<dt><?php echo esc_html__( 'Address', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( $address_display ); ?></dd>
		</div>
	</div>
</section>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-ref-referrer">
	<h2 id="jmrs-portal-ref-referrer" class="jmrs-portal-section__title"><?php echo esc_html__( 'Referrer', 'jm-referral-system' ); ?></h2>
	<div class="jmrs-portal-dl-grid">
		<div>
			<dt><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( (string) ( $referral['referrer_name'] ?? '—' ) ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Email', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( (string) ( $referral['referrer_email'] ?? '—' ) ); ?></dd>
		</div>
		<?php if ( $is_public_referral ) : ?>
			<div>
				<dt><?php echo esc_html__( 'Type', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $referrer_type_label ? $referrer_type_label : '—' ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Organisation', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( (string) ( $referral['referrer_organisation'] ?? '—' ) ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Phone', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( (string) ( $referral['referrer_phone'] ?? '—' ) ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Consent version', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( (string) ( $referral['public_consent_version'] ?? '—' ) ); ?></dd>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php if ( '' !== trim( $care_needs ) ) : ?>
	<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-ref-care">
		<h2 id="jmrs-portal-ref-care" class="jmrs-portal-section__title"><?php echo esc_html__( 'Care requirements', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-prose"><?php echo nl2br( esc_html( $care_needs ) ); ?></div>
	</section>
<?php endif; ?>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-ref-docs">
	<h2 id="jmrs-portal-ref-docs" class="jmrs-portal-section__title"><?php echo esc_html__( 'Documents', 'jm-referral-system' ); ?></h2>
	<?php if ( ! $can_download_documents ) : ?>
		<div class="jmrs-portal-empty"><p><?php echo esc_html__( 'You do not have permission to download documents.', 'jm-referral-system' ); ?></p></div>
	<?php elseif ( empty( $documents ) ) : ?>
		<div class="jmrs-portal-empty"><p><?php echo esc_html__( 'No documents uploaded yet.', 'jm-referral-system' ); ?></p></div>
	<?php else : ?>
		<div class="jmrs-portal-table-wrap">
			<table class="jmrs-portal-table">
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
					<?php foreach ( $documents as $doc ) : ?>
						<?php
						$doc_name     = (string) ( $doc['original_name'] ?? '' );
						$doc_mime     = (string) ( $doc['mime_type'] ?? '' );
						$doc_size     = absint( $doc['file_size'] ?? 0 );
						$doc_uploader = (string) ( $doc['uploaded_by_name'] ?? '' );
						$doc_created  = (string) ( $doc['created_at'] ?? '' );
						$doc_display  = '' !== $doc_created
							? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $doc_created )
							: '';
						$download_url = (string) ( $doc['download_url'] ?? '' );
						$size_display = size_format( $doc_size );
						?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'Filename', 'jm-referral-system' ); ?>"><?php echo esc_html( $doc_name ); ?></td>
							<td data-label="<?php echo esc_attr__( 'File Type', 'jm-referral-system' ); ?>"><?php echo esc_html( $doc_mime ); ?></td>
							<td data-label="<?php echo esc_attr__( 'File Size', 'jm-referral-system' ); ?>"><?php echo esc_html( is_string( $size_display ) ? $size_display : (string) $doc_size ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Uploaded By', 'jm-referral-system' ); ?>"><?php echo '' !== $doc_uploader ? esc_html( $doc_uploader ) : esc_html__( 'Unknown', 'jm-referral-system' ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Uploaded Date', 'jm-referral-system' ); ?>"><?php echo esc_html( $doc_display ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
								<?php if ( '' !== $download_url ) : ?>
									<a class="jmrs-portal-link" href="<?php echo esc_url( $download_url ); ?>"><?php echo esc_html__( 'Download', 'jm-referral-system' ); ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>

<?php
$assessment_data = is_array( $assessment_data ?? null ) ? $assessment_data : array();
$jmrs_assessment_value = static function ( string $key ) use ( $assessment_data ): string {
	return (string) ( $assessment_data[ $key ] ?? '' );
};
$jmrs_assessment_has_value = static function ( string $value ): bool {
	return '' !== trim( $value );
};
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
$care_package_text = array(
	'visit_frequency' => __( 'Visit Frequency', 'jm-referral-system' ),
	'visit_duration'  => __( 'Visit Duration', 'jm-referral-system' ),
);
$care_package_textareas = array(
	'preferred_visit_times' => __( 'Preferred Visit Times', 'jm-referral-system' ),
);
$summary_fields = array(
	'summary'         => __( 'Summary', 'jm-referral-system' ),
	'recommendations' => __( 'Recommendations', 'jm-referral-system' ),
);
?>
<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-ref-assessment">
	<h2 id="jmrs-portal-ref-assessment" class="jmrs-portal-section__title"><?php echo esc_html__( 'Assessment', 'jm-referral-system' ); ?></h2>
	<?php if ( null === $assessment ) : ?>
		<div class="jmrs-portal-empty"><p><?php echo esc_html__( 'No assessment recorded.', 'jm-referral-system' ); ?></p></div>
	<?php else : ?>
		<?php
		$outcome_key            = $jmrs_assessment_value( 'outcome' );
		$outcome_label          = $assessment_outcomes[ $outcome_key ] ?? $outcome_key;
		$assessment_date_value  = $jmrs_assessment_value( 'assessment_date' );
		$assessment_next_review = $jmrs_assessment_value( 'next_review_date' );
		?>
		<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Assessment Overview', 'jm-referral-system' ); ?></h3>
		<div class="jmrs-portal-dl-grid">
			<div>
				<dt><?php echo esc_html__( 'Assessment Date', 'jm-referral-system' ); ?></dt>
				<dd>
					<?php
					echo '' !== $assessment_date_value
						? esc_html( mysql2date( get_option( 'date_format' ), $assessment_date_value ) )
						: '—';
					?>
				</dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Assessor', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $assessor_name ? $assessor_name : '—' ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></dt>
				<dd><?php echo esc_html( '' !== $outcome_label ? $outcome_label : '—' ); ?></dd>
			</div>
			<?php if ( $jmrs_assessment_has_value( $assessment_next_review ) ) : ?>
				<div>
					<dt><?php echo esc_html__( 'Next Review Date', 'jm-referral-system' ); ?></dt>
					<dd><?php echo esc_html( mysql2date( get_option( 'date_format' ), $assessment_next_review ) ); ?></dd>
				</div>
			<?php endif; ?>
		</div>

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
					$visible_fields[] = array(
						'label' => $field_label,
						'value' => $field_value,
					);
				}
			}
			?>
			<?php if ( ! empty( $visible_fields ) ) : ?>
				<h3 class="jmrs-portal-subsection__title"><?php echo esc_html( (string) $section_title ); ?></h3>
				<div class="jmrs-portal-dl-grid">
					<?php foreach ( $visible_fields as $visible_field ) : ?>
						<div class="jmrs-portal-dl-grid__wide">
							<dt><?php echo esc_html( (string) $visible_field['label'] ); ?></dt>
							<dd class="jmrs-portal-prose"><?php echo nl2br( esc_html( (string) $visible_field['value'] ) ); ?></dd>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php
		$package_visible = array();
		foreach ( array_merge( $care_package_text, $care_package_textareas ) as $field_key => $field_label ) {
			$field_value = $jmrs_assessment_value( $field_key );
			if ( $jmrs_assessment_has_value( $field_value ) ) {
				$package_visible[] = array(
					'label'     => $field_label,
					'value'     => $field_value,
					'multiline' => isset( $care_package_textareas[ $field_key ] ),
				);
			}
		}
		?>
		<?php if ( ! empty( $package_visible ) ) : ?>
			<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Proposed Care Package', 'jm-referral-system' ); ?></h3>
			<div class="jmrs-portal-dl-grid">
				<?php foreach ( $package_visible as $package_field ) : ?>
					<div<?php echo ! empty( $package_field['multiline'] ) ? ' class="jmrs-portal-dl-grid__wide"' : ''; ?>>
						<dt><?php echo esc_html( (string) $package_field['label'] ); ?></dt>
						<dd>
							<?php
							if ( ! empty( $package_field['multiline'] ) ) {
								echo nl2br( esc_html( (string) $package_field['value'] ) );
							} else {
								echo esc_html( (string) $package_field['value'] );
							}
							?>
						</dd>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php
		$summary_visible = array();
		foreach ( $summary_fields as $field_key => $field_label ) {
			$field_value = $jmrs_assessment_value( $field_key );
			if ( $jmrs_assessment_has_value( $field_value ) ) {
				$summary_visible[] = array(
					'label' => $field_label,
					'value' => $field_value,
				);
			}
		}
		?>
		<?php if ( ! empty( $summary_visible ) ) : ?>
			<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Summary and Recommendations', 'jm-referral-system' ); ?></h3>
			<div class="jmrs-portal-dl-grid">
				<?php foreach ( $summary_visible as $summary_field ) : ?>
					<div class="jmrs-portal-dl-grid__wide">
						<dt><?php echo esc_html( (string) $summary_field['label'] ); ?></dt>
						<dd class="jmrs-portal-prose"><?php echo nl2br( esc_html( (string) $summary_field['value'] ) ); ?></dd>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</section>

<?php
$care_plan_data = is_array( $care_plan_data ?? null ) ? $care_plan_data : array();
$jmrs_care_plan_value = static function ( string $key ) use ( $care_plan_data ): string {
	return (string) ( $care_plan_data[ $key ] ?? '' );
};
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
<?php if ( $can_view_care_plan ) : ?>
	<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-ref-careplan">
		<h2 id="jmrs-portal-ref-careplan" class="jmrs-portal-section__title"><?php echo esc_html__( 'Care plan', 'jm-referral-system' ); ?></h2>
		<?php if ( null === $care_plan ) : ?>
			<div class="jmrs-portal-empty"><p><?php echo esc_html__( 'No care plan recorded.', 'jm-referral-system' ); ?></p></div>
		<?php else : ?>
			<?php
			$plan_status       = $jmrs_care_plan_value( 'plan_status' );
			$plan_label        = $care_plan_statuses[ $plan_status ] ?? $plan_status;
			$start_date_value  = $jmrs_care_plan_value( 'start_date' );
			$review_date_value = $jmrs_care_plan_value( 'review_date' );
			?>
			<div class="jmrs-portal-dl-grid">
				<div>
					<dt><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></dt>
					<dd><span class="jmrs-portal-badge"><?php echo esc_html( $plan_label ); ?></span></dd>
				</div>
				<?php if ( '' !== trim( $start_date_value ) ) : ?>
					<div>
						<dt><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></dt>
						<dd><?php echo esc_html( mysql2date( get_option( 'date_format' ), $start_date_value ) ); ?></dd>
					</div>
				<?php endif; ?>
				<?php if ( '' !== trim( $review_date_value ) ) : ?>
					<div>
						<dt><?php echo esc_html__( 'Review Date', 'jm-referral-system' ); ?></dt>
						<dd><?php echo esc_html( mysql2date( get_option( 'date_format' ), $review_date_value ) ); ?></dd>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $care_plan_created_by_name ) : ?>
					<div>
						<dt><?php echo esc_html__( 'Created By', 'jm-referral-system' ); ?></dt>
						<dd><?php echo esc_html( $care_plan_created_by_name ); ?></dd>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $care_plan_approved_by_name ) : ?>
					<div>
						<dt><?php echo esc_html__( 'Approved By', 'jm-referral-system' ); ?></dt>
						<dd><?php echo esc_html( $care_plan_approved_by_name ); ?></dd>
					</div>
				<?php endif; ?>
				<?php foreach ( $care_plan_content_fields as $field_key => $field_label ) : ?>
					<?php
					$field_value = $jmrs_care_plan_value( $field_key );
					if ( '' === trim( $field_value ) ) {
						continue;
					}
					$is_short = in_array( $field_key, $care_plan_short_fields, true );
					?>
					<div<?php echo $is_short ? '' : ' class="jmrs-portal-dl-grid__wide"'; ?>>
						<dt><?php echo esc_html( (string) $field_label ); ?></dt>
						<dd>
							<?php
							if ( $is_short ) {
								echo esc_html( $field_value );
							} else {
								echo nl2br( esc_html( $field_value ) );
							}
							?>
						</dd>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $can_view_care_team ) : ?>
	<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-ref-team">
		<h2 id="jmrs-portal-ref-team" class="jmrs-portal-section__title"><?php echo esc_html__( 'Care team', 'jm-referral-system' ); ?></h2>
		<?php if ( empty( $care_team_members ) ) : ?>
			<div class="jmrs-portal-empty"><p><?php echo esc_html__( 'No care team members assigned.', 'jm-referral-system' ); ?></p></div>
		<?php else : ?>
			<div class="jmrs-portal-table-wrap">
				<table class="jmrs-portal-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Staff Member', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Team Role', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Primary', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $care_team_members as $member ) : ?>
							<?php
							$role_key      = (string) ( $member['team_role'] ?? '' );
							$status_key    = (string) ( $member['assignment_status'] ?? '' );
							$role_label    = $care_team_roles[ $role_key ] ?? ucfirst( str_replace( '_', ' ', $role_key ) );
							$status_lab    = $care_team_statuses[ $status_key ] ?? ucfirst( str_replace( '_', ' ', $status_key ) );
							$is_primary    = ! empty( $member['is_primary'] );
							$start_raw     = (string) ( $member['start_date'] ?? '' );
							$end_raw       = (string) ( $member['end_date'] ?? '' );
							$member_notes  = (string) ( $member['notes'] ?? '' );
							$start_display = '' !== $start_raw ? mysql2date( get_option( 'date_format' ), $start_raw ) : '';
							$end_display   = '' !== $end_raw ? mysql2date( get_option( 'date_format' ), $end_raw ) : '—';
							?>
							<tr>
								<td data-label="<?php echo esc_attr__( 'Staff Member', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $member['staff_name'] ?? '' ) ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Team Role', 'jm-referral-system' ); ?>"><?php echo esc_html( $role_label ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Primary', 'jm-referral-system' ); ?>"><?php echo $is_primary ? esc_html__( 'Yes', 'jm-referral-system' ) : esc_html__( 'No', 'jm-referral-system' ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge"><?php echo esc_html( $status_lab ); ?></span></td>
								<td data-label="<?php echo esc_attr__( 'Start Date', 'jm-referral-system' ); ?>"><?php echo esc_html( $start_display ); ?></td>
								<td data-label="<?php echo esc_attr__( 'End Date', 'jm-referral-system' ); ?>"><?php echo esc_html( $end_display ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Notes', 'jm-referral-system' ); ?>"><?php echo '' !== trim( $member_notes ) ? nl2br( esc_html( $member_notes ) ) : '—'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $can_view_visits ) : ?>
	<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-ref-visits">
		<h2 id="jmrs-portal-ref-visits" class="jmrs-portal-section__title"><?php echo esc_html__( 'Recent visits', 'jm-referral-system' ); ?></h2>
		<?php if ( empty( $care_visits ) ) : ?>
			<div class="jmrs-portal-empty"><p><?php echo esc_html__( 'No visits scheduled.', 'jm-referral-system' ); ?></p></div>
		<?php else : ?>
			<div class="jmrs-portal-table-wrap">
				<table class="jmrs-portal-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Date', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Time', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Assigned Staff', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Visit Type', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Source', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $care_visits as $visit_row ) : ?>
							<?php
							$visit_date_raw     = (string) ( $visit_row['visit_date'] ?? '' );
							$start_time_raw     = (string) ( $visit_row['start_time'] ?? '' );
							$end_time_raw       = (string) ( $visit_row['end_time'] ?? '' );
							$visit_type         = (string) ( $visit_row['visit_type'] ?? '' );
							$status_key         = (string) ( $visit_row['visit_status'] ?? '' );
							$status_label       = $visit_status_labels[ $status_key ] ?? ucfirst( str_replace( '_', ' ', $status_key ) );
							$assigned_name      = (string) ( $visit_row['assigned_staff_name'] ?? '' );
							$source_label       = (string) ( $visit_row['source_label'] ?? __( 'Manual', 'jm-referral-system' ) );
							$outcome_label      = (string) ( $visit_row['outcome_label'] ?? '' );
							$visit_date_display = '' !== $visit_date_raw
								? mysql2date( get_option( 'date_format' ), $visit_date_raw )
								: '';
							$start_display = '' !== $start_time_raw ? substr( $start_time_raw, 0, 5 ) : '';
							$end_display   = '' !== $end_time_raw ? substr( $end_time_raw, 0, 5 ) : '';
							$time_display  = trim( $start_display . ( '' !== $end_display ? ' – ' . $end_display : '' ) );
							?>
							<tr>
								<td data-label="<?php echo esc_attr__( 'Date', 'jm-referral-system' ); ?>"><?php echo esc_html( $visit_date_display ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Time', 'jm-referral-system' ); ?>"><?php echo esc_html( $time_display ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Assigned Staff', 'jm-referral-system' ); ?>"><?php echo '' !== $assigned_name ? esc_html( $assigned_name ) : '—'; ?></td>
								<td data-label="<?php echo esc_attr__( 'Visit Type', 'jm-referral-system' ); ?>"><?php echo '' !== trim( $visit_type ) ? esc_html( $visit_type ) : '—'; ?></td>
								<td data-label="<?php echo esc_attr__( 'Source', 'jm-referral-system' ); ?>"><?php echo esc_html( $source_label ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge"><?php echo esc_html( $status_label ); ?></span></td>
								<td data-label="<?php echo esc_attr__( 'Outcome', 'jm-referral-system' ); ?>"><?php echo '' !== $outcome_label ? esc_html( $outcome_label ) : '—'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $can_view_medications ) : ?>
	<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-ref-meds">
		<h2 id="jmrs-portal-ref-meds" class="jmrs-portal-section__title"><?php echo esc_html__( 'Medications', 'jm-referral-system' ); ?></h2>
		<?php if ( empty( $medications ) ) : ?>
			<div class="jmrs-portal-empty"><p><?php echo esc_html__( 'No medications recorded.', 'jm-referral-system' ); ?></p></div>
		<?php else : ?>
			<div class="jmrs-portal-table-wrap">
				<table class="jmrs-portal-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Strength', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Dosage', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Route', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Frequency', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $medications as $med ) : ?>
							<?php
							$status_key  = (string) ( $med['medication_status'] ?? '' );
							$route_key   = (string) ( $med['route'] ?? '' );
							$status_text = $medication_status_labels[ $status_key ] ?? $status_key;
							$route_text  = $medication_route_labels[ $route_key ] ?? $route_key;
							?>
							<tr>
								<td data-label="<?php echo esc_attr__( 'Name', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $med['medication_name'] ?? '' ) ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Strength', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $med['strength'] ?? '' ) ?: '—' ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Dosage', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $med['dosage'] ?? '' ) ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Route', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) $route_text ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Frequency', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $med['frequency'] ?? '' ) ?: '—' ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge"><?php echo esc_html( (string) $status_text ); ?></span></td>
								<td data-label="<?php echo esc_attr__( 'Start Date', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $med['start_date'] ?? '' ) ?: '—' ); ?></td>
								<td data-label="<?php echo esc_attr__( 'End Date', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $med['end_date'] ?? '' ) ?: '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<section class="jmrs-portal-section" aria-labelledby="jmrs-portal-ref-activity">
	<h2 id="jmrs-portal-ref-activity" class="jmrs-portal-section__title"><?php echo esc_html__( 'Activity Timeline', 'jm-referral-system' ); ?></h2>
	<?php if ( empty( $activities ) ) : ?>
		<div class="jmrs-portal-empty"><p><?php echo esc_html__( 'No activity recorded for this referral.', 'jm-referral-system' ); ?></p></div>
	<?php else : ?>
		<div class="jmrs-portal-table-wrap">
			<table class="jmrs-portal-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Date/Time', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Action', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Description', 'jm-referral-system' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $activities as $activity ) : ?>
						<?php
						$activity_created = (string) ( $activity['created_at'] ?? '' );
						$activity_display = '' !== $activity_created
							? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $activity_created )
							: '';
						$action         = (string) ( $activity['action'] ?? '' );
						$action_display = ucfirst( str_replace( '_', ' ', $action ) );
						$description    = (string) ( $activity['description'] ?? '' );
						?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'Date/Time', 'jm-referral-system' ); ?>"><?php echo esc_html( $activity_display ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Action', 'jm-referral-system' ); ?>"><?php echo esc_html( $action_display ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Description', 'jm-referral-system' ); ?>"><?php echo esc_html( $description ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
