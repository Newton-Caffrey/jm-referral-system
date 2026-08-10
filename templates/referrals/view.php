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
 * @var bool                             $can_edit_referral Whether the user may edit this referral.
 * @var bool                             $can_delete_referral Whether the user may delete this referral.
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
$activities_truncated = ! empty( $activities_truncated );
$notes            = is_array( $notes ?? null ) ? $notes : array();
$notes_truncated   = ! empty( $notes_truncated );
$care_plan_reviews_truncated  = ! empty( $care_plan_reviews_truncated );
$care_plan_versions_truncated = ! empty( $care_plan_versions_truncated );
$visits_total       = isset( $visits_total ) ? absint( $visits_total ) : 0;
$visits_page        = isset( $visits_page ) ? max( 1, absint( $visits_page ) ) : 1;
$visits_per_page    = isset( $visits_per_page ) ? absint( $visits_per_page ) : 20;
$visits_from        = isset( $visits_from ) ? absint( $visits_from ) : 0;
$visits_to          = isset( $visits_to ) ? absint( $visits_to ) : 0;
$visits_total_pages = isset( $visits_total_pages ) ? max( 1, absint( $visits_total_pages ) ) : 1;
$visits_pagination_links = $visits_pagination_links ?? '';
$assigned_to_name = isset( $assigned_to_name ) ? (string) $assigned_to_name : '';
$service_name     = isset( $service_name ) ? (string) $service_name : '';
$workflow_stage_name = isset( $workflow_stage_name ) ? (string) $workflow_stage_name : '';
$workflow_stages  = is_array( $workflow_stages ?? null ) ? $workflow_stages : array();
$pipeline_panel   = is_array( $pipeline_panel ?? null ) ? $pipeline_panel : array();
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
$can_view_schedules           = ! empty( $can_view_schedules );
$can_manage_schedules         = ! empty( $can_manage_schedules );
$visit_schedules              = is_array( $visit_schedules ?? null ) ? $visit_schedules : array();
$schedule_data                = is_array( $schedule_data ?? null ) ? $schedule_data : array();
$schedule_errors              = is_array( $schedule_errors ?? null ) ? $schedule_errors : array();
$schedule_repeat_labels       = is_array( $schedule_repeat_labels ?? null ) ? $schedule_repeat_labels : array();
$schedule_status_labels       = is_array( $schedule_status_labels ?? null ) ? $schedule_status_labels : array();
$schedule_weekday_labels      = is_array( $schedule_weekday_labels ?? null ) ? $schedule_weekday_labels : array();
$schedule_team_options        = is_array( $schedule_team_options ?? null ) ? $schedule_team_options : array();

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

$jmrs_schedule_value = static function ( string $key ) use ( $schedule_data ): string {
	$value = $schedule_data[ $key ] ?? '';
	return is_array( $value ) ? '' : (string) $value;
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
$submission_channel = (string) ( $referral['submission_channel'] ?? 'admin' );
$channel_label      = \JMReferral\Frontend\SubmissionChannels::label( $submission_channel );
$is_public_referral = \JMReferral\Frontend\SubmissionChannels::is_public( $submission_channel );
$referrer_type      = (string) ( $referral['referrer_type'] ?? '' );
$referrer_type_label = '' !== $referrer_type
	? \JMReferral\Frontend\ReferrerTypes::label( $referrer_type )
	: '';
$referrer_organisation  = (string) ( $referral['referrer_organisation'] ?? '' );
$referrer_phone         = (string) ( $referral['referrer_phone'] ?? '' );
$relationship_to_client = (string) ( $referral['relationship_to_client'] ?? '' );
$public_consent_at      = (string) ( $referral['public_consent_at'] ?? '' );
$public_consent_display = '' !== $public_consent_at
	? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $public_consent_at )
	: '';
$public_consent_version = (string) ( $referral['public_consent_version'] ?? '' );
$client_first_name      = (string) ( $referral['client_first_name'] ?? '' );
$client_last_name       = (string) ( $referral['client_last_name'] ?? '' );
$client_dob             = (string) ( $referral['client_date_of_birth'] ?? '' );
$client_dob_display     = '' !== $client_dob
	? mysql2date( get_option( 'date_format' ), $client_dob )
	: '';
$address_line_1 = (string) ( $referral['address_line_1'] ?? '' );
$address_line_2 = (string) ( $referral['address_line_2'] ?? '' );
$city           = (string) ( $referral['city'] ?? '' );
$postcode       = (string) ( $referral['postcode'] ?? '' );
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
$delete_url = \JMReferral\Referral\ReferralListController::get_delete_url( $referral_id );
$restore_url = \JMReferral\Referral\ReferralListController::get_restore_url( $referral_id );
$can_edit_referral = ! empty( $can_edit_referral );
$can_delete_referral = ! empty( $can_delete_referral );
$can_archive_referral = ! empty( $can_archive_referral );
$can_restore_referral = ! empty( $can_restore_referral );
$can_add_notes = ! empty( $can_add_notes );
$is_archived = ! empty( $is_archived );
$archived_by_name = isset( $archived_by_name ) ? (string) $archived_by_name : '';
$archived_at = isset( $archived_at ) ? (string) $archived_at : '';
$archive_reason = isset( $archive_reason ) ? (string) $archive_reason : '';
$archived_display = '' !== $archived_at
	? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $archived_at )
	: '';
?>
<div class="wrap">
	<h1>
		<?php echo esc_html__( 'Referral Details', 'jm-referral-system' ); ?>
		<?php
		if ( $is_archived ) {
			echo \JMReferral\Support\UiHelper::badge( __( 'Archived', 'jm-referral-system' ), 'archive' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
		}
		?>
	</h1>

	<p class="jmrs-page-actions">
		<a class="button" href="<?php echo esc_url( $list_url ); ?>">
			<?php echo esc_html__( 'Back to Referrals', 'jm-referral-system' ); ?>
		</a>
		<?php if ( $can_edit_referral ) : ?>
			<a class="button button-primary" href="<?php echo esc_url( $edit_url ); ?>">
				<?php echo esc_html__( 'Edit Referral', 'jm-referral-system' ); ?>
			</a>
		<?php endif; ?>
		<?php if ( $can_archive_referral ) : ?>
			<a class="button jmrs-button-danger" href="#jmrs-archive-referral">
				<?php echo esc_html__( 'Archive', 'jm-referral-system' ); ?>
			</a>
		<?php endif; ?>
		<?php if ( $can_restore_referral ) : ?>
			<a
				class="button button-primary"
				href="<?php echo esc_url( $restore_url ); ?>"
				data-jmrs-confirm="<?php echo esc_attr__( 'Restore this archived referral?', 'jm-referral-system' ); ?>"
				data-jmrs-busy="<?php echo esc_attr__( 'Restoring...', 'jm-referral-system' ); ?>"
			>
				<?php echo esc_html__( 'Restore', 'jm-referral-system' ); ?>
			</a>
		<?php endif; ?>
		<?php if ( $can_delete_referral ) : ?>
			<a
				class="button jmrs-button-danger"
				href="<?php echo esc_url( $delete_url ); ?>"
				data-jmrs-confirm="<?php echo esc_attr__( 'Permanently delete this empty referral? This cannot be undone.', 'jm-referral-system' ); ?>"
				data-jmrs-busy="<?php echo esc_attr__( 'Deleting...', 'jm-referral-system' ); ?>"
			>
				<?php echo esc_html__( 'Delete', 'jm-referral-system' ); ?>
			</a>
		<?php endif; ?>
	</p>

	<?php
	$context = 'admin';
	include JMRS_PLUGIN_PATH . 'templates/referrals/partials/pipeline-panel.php';
	?>

	<?php
	if ( ! empty( $interest_form['can_express'] ) ) {
		$form_action = '';
		include JMRS_PLUGIN_PATH . 'templates/referrals/partials/express-interest.php';
	}
	?>

	<?php
	if ( ! empty( $scheduling_panel['can_schedule'] )
		|| ! empty( $scheduling_panel['can_reschedule'] )
		|| ! empty( $scheduling_panel['has_appointment'] )
	) {
		$form_action           = '';
		$context               = 'admin';
		$assessment_url        = '#jmrs-assessment';
		$scheduling_errors     = is_array( $scheduling_errors ?? null ) ? $scheduling_errors : array();
		$force_reschedule_form = ! empty( $force_reschedule_form );
		include JMRS_PLUGIN_PATH . 'templates/referrals/partials/assessment-scheduling.php';
	}
	?>

	<?php
	if ( ! empty( $package_cost_panel['show_panel'] ) ) {
		$form_action           = '';
		$context               = 'admin';
		$package_cost_errors   = is_array( $package_cost_errors ?? null ) ? $package_cost_errors : array();
		$show_prepare_form     = ! empty( $show_prepare_form );
		$show_send_form        = ! empty( $show_send_form );
		include JMRS_PLUGIN_PATH . 'templates/referrals/partials/package-cost.php';
	}
	?>

	<?php
	if ( ! empty( $la_decision_panel['show_panel'] ) ) {
		$form_action             = '';
		$context                 = 'admin';
		$la_decision_errors      = is_array( $la_decision_errors ?? null ) ? $la_decision_errors : array();
		$show_la_decision_form   = ! empty( $show_la_decision_form );
		include JMRS_PLUGIN_PATH . 'templates/referrals/partials/la-decision.php';
	}
	?>

	<?php
	if ( ! empty( $non_proceeding_panel['show_panel'] ) ) {
		$form_action              = '';
		$context                  = 'admin';
		$show_non_proceeding_form = ! empty( $show_non_proceeding_form );
		include JMRS_PLUGIN_PATH . 'templates/referrals/partials/non-proceeding.php';
	}
	?>

	<?php
	if ( ! empty( $transition_panel['show_panel'] ) ) {
		$form_action        = '';
		$context            = 'admin';
		$transition_errors  = is_array( $transition_errors ?? null ) ? $transition_errors : array();
		$show_commence_form = ! empty( $show_commence_form );
		include JMRS_PLUGIN_PATH . 'templates/referrals/partials/transition-planning.php';
	}
	?>

	<?php if ( $is_archived ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php echo esc_html__( 'This referral is archived and read-only.', 'jm-referral-system' ); ?></strong>
			</p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: archive date, 2: archiver name */
						__( 'Archived on %1$s by %2$s.', 'jm-referral-system' ),
						$archived_display,
						'' !== $archived_by_name ? $archived_by_name : __( 'Unknown', 'jm-referral-system' )
					)
				);
				?>
			</p>
			<?php if ( '' !== trim( $archive_reason ) ) : ?>
				<p>
					<strong><?php echo esc_html__( 'Reason:', 'jm-referral-system' ); ?></strong>
					<?php echo esc_html( $archive_reason ); ?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

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
					<?php if ( ! empty( $pipeline_panel['is_pipeline'] ) ) : ?>
						<?php echo '' !== $workflow_stage_name ? esc_html( $workflow_stage_name ) : '—'; ?>
						<p class="description"><?php echo esc_html__( 'Canonical pipeline stage. Use the Referral Pipeline panel for override; business actions will advance the stage in later phases.', 'jm-referral-system' ); ?></p>
					<?php elseif ( $can_edit_referral && ! empty( $workflow_stages ) ) : ?>
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
					<p class="description"><?php echo esc_html__( 'Legacy workflow stages only. Use Override Pipeline Stage to enter the acquisition pipeline.', 'jm-referral-system' ); ?></p>
					<?php else : ?>
						<?php echo '' !== $workflow_stage_name ? esc_html( $workflow_stage_name ) : '—'; ?>
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
				<th scope="row"><?php echo esc_html__( 'Submission Channel', 'jm-referral-system' ); ?></th>
				<td><?php echo esc_html( $channel_label ); ?></td>
			</tr>
			<?php if ( $is_public_referral ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Referrer Type', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $referrer_type_label ? esc_html( $referrer_type_label ) : '—'; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Organisation', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $referrer_organisation ? esc_html( $referrer_organisation ) : '—'; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Referrer Phone', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $referrer_phone ? esc_html( $referrer_phone ) : '—'; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Relationship to Client', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $relationship_to_client ? esc_html( $relationship_to_client ) : '—'; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Consent Date', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $public_consent_display ? esc_html( $public_consent_display ) : '—'; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Consent Version', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $public_consent_version ? esc_html( $public_consent_version ) : '—'; ?></td>
			</tr>
			<?php if ( '' !== $client_first_name || '' !== $client_last_name || '' !== $client_dob_display || '' !== $address_line_1 || '' !== $city || '' !== $postcode ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Client address / DOB', 'jm-referral-system' ); ?></th>
				<td>
					<?php if ( '' !== $client_first_name || '' !== $client_last_name ) : ?>
						<?php echo esc_html( trim( $client_first_name . ' ' . $client_last_name ) ); ?><br />
					<?php endif; ?>
					<?php if ( '' !== $client_dob_display ) : ?>
						<?php echo esc_html__( 'DOB:', 'jm-referral-system' ); ?> <?php echo esc_html( $client_dob_display ); ?><br />
					<?php endif; ?>
					<?php
					$address_bits = array_filter( array( $address_line_1, $address_line_2, $city, $postcode ) );
					echo '' !== implode( ', ', $address_bits ) ? esc_html( implode( ', ', $address_bits ) ) : '—';
					?>
				</td>
			</tr>
			<?php endif; ?>
			<?php endif; ?>
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
	<h2 id="jmrs-assessment"><?php echo esc_html( $assessment_heading ); ?></h2>

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
			<?php echo \JMReferral\Support\UiHelper::empty_state( __( 'No care plans available.', 'jm-referral-system' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?>
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
			<?php if ( $care_plan_reviews_truncated ) : ?>
				<p class="description"><?php echo esc_html__( 'Showing the most recent 25 records.', 'jm-referral-system' ); ?></p>
			<?php endif; ?>
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
			<?php if ( $care_plan_versions_truncated ) : ?>
				<p class="description"><?php echo esc_html__( 'Showing the most recent 25 records.', 'jm-referral-system' ); ?></p>
			<?php endif; ?>
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

	<?php if ( ! empty( $can_view_medications ) ) : ?>
		<?php
		$medications                 = is_array( $medications ?? null ) ? $medications : array();
		$medication_status_labels    = is_array( $medication_status_labels ?? null ) ? $medication_status_labels : array();
		$medication_route_labels     = is_array( $medication_route_labels ?? null ) ? $medication_route_labels : array();
		$medication_errors           = is_array( $medication_errors ?? null ) ? $medication_errors : array();
		$medication_data             = is_array( $medication_data ?? null ) ? $medication_data : array();
		$can_manage_medications      = ! empty( $can_manage_medications );
		$show_inactive_medications   = ! empty( $show_inactive_medications );
		$jmrs_med_value              = static function ( string $key ) use ( $medication_data ): string {
			return (string) ( $medication_data[ $key ] ?? '' );
		};
		$toggle_url = add_query_arg(
			array(
				'page'                   => 'jm-referrals-view',
				'referral_id'            => $referral_id,
				'jmrs_show_inactive_meds'=> $show_inactive_medications ? '0' : '1',
			),
			admin_url( 'admin.php' )
		);
		?>
		<h2><?php echo esc_html__( 'Medication List', 'jm-referral-system' ); ?></h2>

		<p>
			<a href="<?php echo esc_url( $toggle_url ); ?>">
				<?php
				echo esc_html(
					$show_inactive_medications
						? __( 'Hide inactive medications', 'jm-referral-system' )
						: __( 'Show inactive medications', 'jm-referral-system' )
				);
				?>
			</a>
		</p>

		<?php if ( $can_manage_medications ) : ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'jmrs_save_medication_' . $referral_id, 'jmrs_medication_nonce' ); ?>
				<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
				<input type="hidden" name="jmrs_medication_id" value="0" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="jmrs_medication_name"><?php echo esc_html__( 'Medication Name', 'jm-referral-system' ); ?></label></th>
						<td><input type="text" class="regular-text" name="jmrs_medication_name" id="jmrs_medication_name" value="<?php echo esc_attr( $jmrs_med_value( 'medication_name' ) ); ?>" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="jmrs_medication_strength"><?php echo esc_html__( 'Strength', 'jm-referral-system' ); ?></label></th>
						<td><input type="text" class="regular-text" name="jmrs_medication_strength" id="jmrs_medication_strength" value="<?php echo esc_attr( $jmrs_med_value( 'strength' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="jmrs_medication_dosage"><?php echo esc_html__( 'Dosage', 'jm-referral-system' ); ?></label></th>
						<td><input type="text" class="regular-text" name="jmrs_medication_dosage" id="jmrs_medication_dosage" value="<?php echo esc_attr( $jmrs_med_value( 'dosage' ) ); ?>" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="jmrs_medication_route"><?php echo esc_html__( 'Route', 'jm-referral-system' ); ?></label></th>
						<td>
							<select name="jmrs_medication_route" id="jmrs_medication_route" required>
								<option value=""><?php echo esc_html__( 'Select route', 'jm-referral-system' ); ?></option>
								<?php foreach ( $medication_route_labels as $value => $label ) : ?>
									<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $jmrs_med_value( 'route' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jmrs_medication_frequency"><?php echo esc_html__( 'Frequency', 'jm-referral-system' ); ?></label></th>
						<td><input type="text" class="regular-text" name="jmrs_medication_frequency" id="jmrs_medication_frequency" value="<?php echo esc_attr( $jmrs_med_value( 'frequency' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="jmrs_medication_instructions"><?php echo esc_html__( 'Instructions', 'jm-referral-system' ); ?></label></th>
						<td><textarea class="large-text" rows="3" name="jmrs_medication_instructions" id="jmrs_medication_instructions"><?php echo esc_textarea( $jmrs_med_value( 'instructions' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="jmrs_medication_start_date"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></label></th>
						<td><input type="date" name="jmrs_medication_start_date" id="jmrs_medication_start_date" value="<?php echo esc_attr( $jmrs_med_value( 'start_date' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="jmrs_medication_end_date"><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></label></th>
						<td><input type="date" name="jmrs_medication_end_date" id="jmrs_medication_end_date" value="<?php echo esc_attr( $jmrs_med_value( 'end_date' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="jmrs_medication_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label></th>
						<td>
							<select name="jmrs_medication_status" id="jmrs_medication_status">
								<?php foreach ( $medication_status_labels as $value => $label ) : ?>
									<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $jmrs_med_value( 'medication_status' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jmrs_medication_prescribing_source"><?php echo esc_html__( 'Prescribing Source', 'jm-referral-system' ); ?></label></th>
						<td><input type="text" class="regular-text" name="jmrs_medication_prescribing_source" id="jmrs_medication_prescribing_source" value="<?php echo esc_attr( $jmrs_med_value( 'prescribing_source' ) ); ?>" /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Medication', 'jm-referral-system' ), 'secondary', 'jmrs_save_medication' ); ?>
			</form>
		<?php endif; ?>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Medication', 'jm-referral-system' ); ?></th>
					<th><?php echo esc_html__( 'Strength', 'jm-referral-system' ); ?></th>
					<th><?php echo esc_html__( 'Dosage', 'jm-referral-system' ); ?></th>
					<th><?php echo esc_html__( 'Route', 'jm-referral-system' ); ?></th>
					<th><?php echo esc_html__( 'Frequency', 'jm-referral-system' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
					<th><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></th>
					<th><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></th>
					<?php if ( $can_manage_medications ) : ?>
						<th><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $medications ) ) : ?>
					<tr class="no-items">
						<td colspan="<?php echo $can_manage_medications ? '9' : '8'; ?>"><?php echo esc_html__( 'No medications recorded.', 'jm-referral-system' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $medications as $med_row ) : ?>
						<?php
						$status_key  = (string) ( $med_row['medication_status'] ?? '' );
						$route_key   = (string) ( $med_row['route'] ?? '' );
						$status_text = $medication_status_labels[ $status_key ] ?? $status_key;
						$route_text  = $medication_route_labels[ $route_key ] ?? $route_key;
						$edit_url    = (string) ( $med_row['edit_url'] ?? '' );
						?>
						<tr>
							<td><?php echo esc_html( (string) ( $med_row['medication_name'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $med_row['strength'] ?? '' ) ?: '—' ); ?></td>
							<td><?php echo esc_html( (string) ( $med_row['dosage'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) $route_text ); ?></td>
							<td><?php echo esc_html( (string) ( $med_row['frequency'] ?? '' ) ?: '—' ); ?></td>
							<td><?php echo \JMReferral\Support\UiHelper::status_badge( $status_key, (string) $status_text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?></td>
							<td><?php echo esc_html( (string) ( $med_row['start_date'] ?? '' ) ?: '—' ); ?></td>
							<td><?php echo esc_html( (string) ( $med_row['end_date'] ?? '' ) ?: '—' ); ?></td>
							<?php if ( $can_manage_medications ) : ?>
								<td>
									<?php if ( '' !== $edit_url ) : ?>
										<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
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
			<?php echo \JMReferral\Support\UiHelper::empty_state( __( 'No care team members assigned.', 'jm-referral-system' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?>
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
						$member_notes   = (string) ( $member_row['notes'] ?? '' );
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
							<td><?php echo '' !== trim( $member_notes ) ? nl2br( esc_html( $member_notes ) ) : '—'; ?></td>
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

	<?php if ( $can_view_schedules ) : ?>
		<?php
		$repeat_labels      = $schedule_repeat_labels;
		$status_labels      = $schedule_status_labels;
		$weekday_labels     = $schedule_weekday_labels;
		$team_options       = $schedule_team_options;
		$selected_days      = is_array( $schedule_data['days_of_week'] ?? null ) ? $schedule_data['days_of_week'] : array();
		$schedule_errors    = $schedule_errors;
		?>
		<h2><?php echo esc_html__( 'Scheduling', 'jm-referral-system' ); ?></h2>

		<?php if ( $can_manage_schedules ) : ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'jmrs_save_schedule_' . $referral_id, 'jmrs_schedule_nonce' ); ?>
				<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
				<input type="hidden" name="jmrs_schedule_id" value="0" />
				<input type="hidden" name="jmrs_schedule_care_plan_id" value="<?php echo esc_attr( $jmrs_schedule_value( 'care_plan_id' ) ); ?>" />

				<?php include JMRS_PLUGIN_PATH . 'templates/schedules/form-fields.php'; ?>

				<?php
				submit_button(
					__( 'Save Schedule', 'jm-referral-system' ),
					'secondary',
					'jmrs_save_schedule'
				);
				?>
			</form>
		<?php endif; ?>

		<?php if ( empty( $visit_schedules ) ) : ?>
			<?php echo \JMReferral\Support\UiHelper::empty_state( __( 'No schedules available.', 'jm-referral-system' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Repeat', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Time', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Assigned Staff', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Dates', 'jm-referral-system' ); ?></th>
						<?php if ( $can_manage_schedules ) : ?>
							<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $visit_schedules as $schedule_row ) : ?>
						<?php
						$schedule_name   = (string) ( $schedule_row['schedule_name'] ?? '' );
						$repeat_key      = (string) ( $schedule_row['repeat_type'] ?? '' );
						$repeat_label    = isset( $schedule_repeat_labels[ $repeat_key ] )
							? (string) $schedule_repeat_labels[ $repeat_key ]
							: ucfirst( $repeat_key );
						$interval        = max( 1, absint( $schedule_row['repeat_interval'] ?? 1 ) );
						$repeat_display  = $repeat_label . ( $interval > 1 ? ' / ' . $interval : '' );
						$start_time_raw  = (string) ( $schedule_row['start_time'] ?? '' );
						$end_time_raw    = (string) ( $schedule_row['end_time'] ?? '' );
						$time_display    = trim(
							substr( $start_time_raw, 0, 5 )
							. ( '' !== $end_time_raw ? ' – ' . substr( $end_time_raw, 0, 5 ) : '' )
						);
						$status_key      = (string) ( $schedule_row['status'] ?? '' );
						$status_label    = isset( $schedule_status_labels[ $status_key ] )
							? (string) $schedule_status_labels[ $status_key ]
							: ucfirst( $status_key );
						$assigned_label  = (string) ( $schedule_row['assigned_label'] ?? '—' );
						$start_raw       = (string) ( $schedule_row['start_date'] ?? '' );
						$end_raw         = (string) ( $schedule_row['end_date'] ?? '' );
						$start_display   = '' !== $start_raw ? mysql2date( get_option( 'date_format' ), $start_raw ) : '';
						$end_display     = '' !== $end_raw ? mysql2date( get_option( 'date_format' ), $end_raw ) : '—';
						$dates_display   = trim( $start_display . ' → ' . $end_display );
						$edit_url        = (string) ( $schedule_row['edit_url'] ?? '' );
						$schedule_id_row = absint( $schedule_row['id'] ?? 0 );
						?>
						<tr>
							<td><?php echo esc_html( $schedule_name ); ?></td>
							<td><?php echo esc_html( $repeat_display ); ?></td>
							<td><?php echo esc_html( $time_display ); ?></td>
							<td><?php echo esc_html( $assigned_label ); ?></td>
							<td><?php echo esc_html( $status_label ); ?></td>
							<td><?php echo esc_html( $dates_display ); ?></td>
							<?php if ( $can_manage_schedules ) : ?>
								<td>
									<?php if ( '' !== $edit_url ) : ?>
										<a href="<?php echo esc_url( $edit_url ); ?>">
											<?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?>
										</a>
									<?php endif; ?>
									<?php
									$can_generate          = ! empty( $schedule_row['can_generate'] );
									$generated_visit_count = absint( $schedule_row['generated_visit_count'] ?? 0 );
									$gen_start_value       = (string) ( $schedule_row['generation_start_date'] ?? '' );
									$gen_end_value         = (string) ( $schedule_row['generation_end_date'] ?? '' );
									?>
									<?php if ( $schedule_id_row > 0 && $can_generate ) : ?>
										<form method="post" action="" class="jmrs-generate-form" style="margin-top:8px;" data-jmrs-confirm="<?php echo esc_attr__( 'Generate visits for this schedule and date range?', 'jm-referral-system' ); ?>">
											<?php wp_nonce_field( 'jmrs_generate_schedule_visits_' . $schedule_id_row, 'jmrs_generate_schedule_nonce' ); ?>
											<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
											<input type="hidden" name="jmrs_schedule_id" value="<?php echo esc_attr( (string) $schedule_id_row ); ?>" />
											<p style="margin:0 0 6px;">
												<label for="jmrs_generation_start_<?php echo esc_attr( (string) $schedule_id_row ); ?>">
													<?php echo esc_html__( 'Generate From', 'jm-referral-system' ); ?>
												</label><br />
												<input
													type="date"
													name="generation_start_date"
													id="jmrs_generation_start_<?php echo esc_attr( (string) $schedule_id_row ); ?>"
													value="<?php echo esc_attr( $gen_start_value ); ?>"
													required
												/>
											</p>
											<p style="margin:0 0 6px;">
												<label for="jmrs_generation_end_<?php echo esc_attr( (string) $schedule_id_row ); ?>">
													<?php echo esc_html__( 'Generate Until', 'jm-referral-system' ); ?>
												</label><br />
												<input
													type="date"
													name="generation_end_date"
													id="jmrs_generation_end_<?php echo esc_attr( (string) $schedule_id_row ); ?>"
													value="<?php echo esc_attr( $gen_end_value ); ?>"
													required
												/>
											</p>
											<?php
											submit_button(
												__( 'Generate Visits', 'jm-referral-system' ),
												'secondary small',
												'jmrs_generate_schedule_visits',
												false
											);
											?>
										</form>
										<p class="description" style="margin-top:6px;">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %d: number of generated visits */
													_n(
														'%d generated visit linked to this schedule.',
														'%d generated visits linked to this schedule.',
														$generated_visit_count,
														'jm-referral-system'
													),
													$generated_visit_count
												)
											);
											?>
										</p>
									<?php elseif ( $schedule_id_row > 0 ) : ?>
										<p class="description" style="margin-top:6px;">
											<?php echo esc_html__( 'Only active schedules can generate visits.', 'jm-referral-system' ); ?>
										</p>
										<p class="description">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %d: number of generated visits */
													_n(
														'%d generated visit linked to this schedule.',
														'%d generated visits linked to this schedule.',
														$generated_visit_count,
														'jm-referral-system'
													),
													$generated_visit_count
												)
											);
											?>
										</p>
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

		<?php
		$jmrs_render_visits_pagination = static function ( string $select_id ) use (
			$referral_id,
			$visits_from,
			$visits_to,
			$visits_total,
			$visits_per_page,
			$visits_pagination_links
		) {
			?>
			<div class="tablenav">
				<div class="alignleft actions">
					<form method="get" action="">
						<input type="hidden" name="page" value="jm-referrals-view" />
						<input type="hidden" name="referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
						<input type="hidden" name="jmrs_visits_page" value="1" />
						<label for="<?php echo esc_attr( $select_id ); ?>" class="screen-reader-text"><?php echo esc_html__( 'Visits per page', 'jm-referral-system' ); ?></label>
						<select name="jmrs_visits_per_page" id="<?php echo esc_attr( $select_id ); ?>" onchange="this.form.submit();">
							<?php foreach ( \JMReferral\Referral\ReferralViewController::VISITS_ALLOWED_PER_PAGE as $size ) : ?>
								<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( $visits_per_page, $size ); ?>>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: number of visits per page */
											__( '%d per page', 'jm-referral-system' ),
											$size
										)
									);
									?>
								</option>
							<?php endforeach; ?>
						</select>
					</form>
				</div>
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php
						if ( $visits_total > 0 ) {
							echo esc_html(
								sprintf(
									/* translators: 1: first visit number, 2: last visit number, 3: total visits */
									__( 'Displaying %1$s–%2$s of %3$s visits', 'jm-referral-system' ),
									number_format_i18n( $visits_from ),
									number_format_i18n( $visits_to ),
									number_format_i18n( $visits_total )
								)
							);
						} else {
							echo esc_html__( 'Displaying 0 visits', 'jm-referral-system' );
						}
						?>
					</span>
					<?php if ( is_string( $visits_pagination_links ) && '' !== $visits_pagination_links ) : ?>
						<span class="pagination-links"><?php echo $visits_pagination_links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() HTML. ?></span>
					<?php endif; ?>
				</div>
				<br class="clear" />
			</div>
			<?php
		};
		?>

		<?php if ( empty( $care_visits ) ) : ?>
			<?php echo \JMReferral\Support\UiHelper::empty_state( __( 'No visits scheduled.', 'jm-referral-system' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?>
		<?php else : ?>
			<?php $jmrs_render_visits_pagination( 'jmrs_visits_per_page_top' ); ?>
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Date', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Time', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Assigned Staff', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Visit Type', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Source', 'jm-referral-system' ); ?></th>
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
						$source_label     = (string) ( $visit_row['source_label'] ?? __( 'Manual', 'jm-referral-system' ) );
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
							<td><?php echo esc_html( $source_label ); ?></td>
							<td><?php echo \JMReferral\Support\UiHelper::status_badge( $status_key, $status_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?></td>
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
						<?php
						$visit_id_row       = absint( $visit_row['id'] ?? 0 );
						$can_execute_visit  = ! empty( $visit_row['can_execute'] );
						$can_review_visit   = ! empty( $visit_row['can_review'] );
						$is_executed_visit  = ! empty( $visit_row['is_executed'] );
						$is_reviewed_visit  = ! empty( $visit_row['is_reviewed'] );
						$execution_data     = is_array( $visit_row['execution_form_data'] ?? null ) ? $visit_row['execution_form_data'] : array();
						$execution_errors   = is_array( $visit_row['execution_errors'] ?? null ) ? $visit_row['execution_errors'] : array();
						$outcome_labels     = is_array( $visit_outcome_labels ?? null ) ? $visit_outcome_labels : array();
						$colspan            = $can_manage_visits ? 7 : 6;

						$jmrs_exec_value = static function ( string $key ) use ( $execution_data ): string {
							return (string) ( $execution_data[ $key ] ?? '' );
						};

						$arrival_display = (string) ( $visit_row['arrival_time'] ?? '' );
						$departure_display = (string) ( $visit_row['departure_time'] ?? '' );
						$duration_minutes = absint( $visit_row['actual_duration_minutes'] ?? 0 );
						$outcome_label = (string) ( $visit_row['outcome_label'] ?? '' );
						?>
						<?php if ( $can_execute_visit || $is_executed_visit ) : ?>
							<tr>
								<td colspan="<?php echo esc_attr( (string) $colspan ); ?>">
									<h3 style="margin:8px 0;"><?php echo esc_html__( 'Visit Execution', 'jm-referral-system' ); ?></h3>

									<?php if ( $can_execute_visit ) : ?>
										<form method="post" action="">
											<?php wp_nonce_field( 'jmrs_execute_care_visit_' . $visit_id_row, 'jmrs_execute_visit_nonce' ); ?>
											<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
											<input type="hidden" name="jmrs_visit_id" value="<?php echo esc_attr( (string) $visit_id_row ); ?>" />

											<table class="form-table" role="presentation">
												<tbody>
													<tr>
														<th scope="row">
															<label for="jmrs_visit_arrival_time_<?php echo esc_attr( (string) $visit_id_row ); ?>">
																<?php echo esc_html__( 'Arrival Time', 'jm-referral-system' ); ?>
															</label>
														</th>
														<td>
															<input
																type="datetime-local"
																name="jmrs_visit_arrival_time"
																id="jmrs_visit_arrival_time_<?php echo esc_attr( (string) $visit_id_row ); ?>"
																value="<?php echo esc_attr( str_replace( ' ', 'T', substr( $jmrs_exec_value( 'arrival_time' ), 0, 16 ) ) ); ?>"
																required
															/>
															<?php if ( isset( $execution_errors['arrival_time'] ) ) : ?>
																<p class="description"><?php echo esc_html( $execution_errors['arrival_time'] ); ?></p>
															<?php endif; ?>
														</td>
													</tr>
													<tr>
														<th scope="row">
															<label for="jmrs_visit_departure_time_<?php echo esc_attr( (string) $visit_id_row ); ?>">
																<?php echo esc_html__( 'Departure Time', 'jm-referral-system' ); ?>
															</label>
														</th>
														<td>
															<input
																type="datetime-local"
																name="jmrs_visit_departure_time"
																id="jmrs_visit_departure_time_<?php echo esc_attr( (string) $visit_id_row ); ?>"
																value="<?php echo esc_attr( str_replace( ' ', 'T', substr( $jmrs_exec_value( 'departure_time' ), 0, 16 ) ) ); ?>"
																required
															/>
															<?php if ( isset( $execution_errors['departure_time'] ) ) : ?>
																<p class="description"><?php echo esc_html( $execution_errors['departure_time'] ); ?></p>
															<?php endif; ?>
														</td>
													</tr>
													<tr>
														<th scope="row">
															<label for="jmrs_visit_outcome_<?php echo esc_attr( (string) $visit_id_row ); ?>">
																<?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?>
															</label>
														</th>
														<td>
															<select name="jmrs_visit_outcome" id="jmrs_visit_outcome_<?php echo esc_attr( (string) $visit_id_row ); ?>" required>
																<option value=""><?php echo esc_html__( '— Select —', 'jm-referral-system' ); ?></option>
																<?php foreach ( $outcome_labels as $outcome_value => $outcome_text ) : ?>
																	<option value="<?php echo esc_attr( (string) $outcome_value ); ?>" <?php selected( $jmrs_exec_value( 'visit_outcome' ), (string) $outcome_value ); ?>>
																		<?php echo esc_html( (string) $outcome_text ); ?>
																	</option>
																<?php endforeach; ?>
															</select>
															<?php if ( isset( $execution_errors['visit_outcome'] ) ) : ?>
																<p class="description"><?php echo esc_html( $execution_errors['visit_outcome'] ); ?></p>
															<?php endif; ?>
														</td>
													</tr>
													<tr>
														<th scope="row"><?php echo esc_html__( 'Tasks', 'jm-referral-system' ); ?></th>
														<td>
															<?php
															$visit_tasks       = is_array( $visit_row['visit_tasks'] ?? null ) ? $visit_row['visit_tasks'] : array();
															$task_status_labels = is_array( $visit_task_statuses ?? null ) ? $visit_task_statuses : array();
															?>
															<?php if ( empty( $visit_tasks ) ) : ?>
																<p class="description"><?php echo esc_html__( 'No care-plan tasks were generated for this visit.', 'jm-referral-system' ); ?></p>
															<?php else : ?>
																<table class="widefat striped" style="max-width:100%;">
																	<thead>
																		<tr>
																			<th><?php echo esc_html__( 'Task Name', 'jm-referral-system' ); ?></th>
																			<th><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
																			<th><?php echo esc_html__( 'Task Notes', 'jm-referral-system' ); ?></th>
																		</tr>
																	</thead>
																	<tbody>
																		<?php foreach ( $visit_tasks as $task_row ) : ?>
																			<?php
																			$task_id     = absint( $task_row['id'] ?? 0 );
																			$task_name   = (string) ( $task_row['task_name'] ?? '' );
																			$task_status = (string) ( $task_row['task_status'] ?? 'pending' );
																			$task_notes  = (string) ( $task_row['task_notes'] ?? '' );
																			?>
																			<tr>
																				<td><?php echo esc_html( $task_name ); ?></td>
																				<td>
																					<select name="jmrs_visit_tasks[<?php echo esc_attr( (string) $task_id ); ?>][task_status]">
																						<?php foreach ( $task_status_labels as $status_value => $status_text ) : ?>
																							<option value="<?php echo esc_attr( (string) $status_value ); ?>" <?php selected( $task_status, (string) $status_value ); ?>>
																								<?php echo esc_html( (string) $status_text ); ?>
																							</option>
																						<?php endforeach; ?>
																					</select>
																				</td>
																				<td>
																					<textarea
																						name="jmrs_visit_tasks[<?php echo esc_attr( (string) $task_id ); ?>][task_notes]"
																						class="large-text"
																						rows="2"
																					><?php echo esc_textarea( $task_notes ); ?></textarea>
																				</td>
																			</tr>
																		<?php endforeach; ?>
																	</tbody>
																</table>
															<?php endif; ?>
														</td>
													</tr>
													<?php if ( ! empty( $visit_row['can_administer_medications'] ) ) : ?>
														<?php
														$active_meds_for_visit = is_array( $visit_row['active_medications'] ?? null ) ? $visit_row['active_medications'] : array();
														$posted_meds          = is_array( $visit_row['posted_medications'] ?? null ) ? $visit_row['posted_medications'] : array();
														$admin_status_labels  = is_array( $administration_status_labels ?? null ) ? $administration_status_labels : array();
														$admin_reason_labels  = is_array( $administration_reason_labels ?? null ) ? $administration_reason_labels : array();
														$witness_users_list   = is_array( $witness_users ?? null ) ? $witness_users : array();
														$default_admin_time   = current_time( 'Y-m-d\TH:i' );
														?>
														<tr>
															<th scope="row"><?php echo esc_html__( 'Medication Administration', 'jm-referral-system' ); ?></th>
															<td>
																<table class="widefat striped" style="max-width:100%;">
																	<thead>
																		<tr>
																			<th><?php echo esc_html__( 'Medication', 'jm-referral-system' ); ?></th>
																			<th><?php echo esc_html__( 'Scheduled Time', 'jm-referral-system' ); ?></th>
																			<th><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
																			<th><?php echo esc_html__( 'Dose Given', 'jm-referral-system' ); ?></th>
																			<th><?php echo esc_html__( 'Reason Code', 'jm-referral-system' ); ?></th>
																			<th><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></th>
																			<th><?php echo esc_html__( 'Witness', 'jm-referral-system' ); ?></th>
																		</tr>
																	</thead>
																	<tbody>
																		<?php foreach ( $active_meds_for_visit as $med_row ) : ?>
																			<?php
																			$med_id      = absint( $med_row['id'] ?? 0 );
																			$posted      = is_array( $posted_meds[ $med_id ] ?? null ) ? $posted_meds[ $med_id ] : array();
																			$existing    = is_array( ( $visit_row['medication_admin_by_id'][ $med_id ] ?? null ) ) ? $visit_row['medication_admin_by_id'][ $med_id ] : array();
																			$status_val  = (string) ( $posted['administration_status'] ?? $existing['administration_status'] ?? '' );
																			$dose_val    = (string) ( $posted['dose_given'] ?? $existing['dose_given'] ?? '' );
																			$reason_val  = (string) ( $posted['reason_code'] ?? $existing['reason_code'] ?? '' );
																			$notes_val   = (string) ( $posted['notes'] ?? $existing['notes'] ?? '' );
																			$witness_val = (string) ( $posted['witness_user_id'] ?? $existing['witness_user_id'] ?? '' );
																			$sched_raw   = (string) ( $posted['scheduled_time'] ?? $existing['scheduled_time'] ?? '' );
																			$admin_raw   = (string) ( $posted['administered_time'] ?? $existing['administered_time'] ?? '' );
																			$sched_input = '' !== $sched_raw ? str_replace( ' ', 'T', substr( $sched_raw, 0, 16 ) ) : '';
																			$admin_input = '' !== $admin_raw ? str_replace( ' ', 'T', substr( $admin_raw, 0, 16 ) ) : $default_admin_time;
																			$route_key   = (string) ( $med_row['route'] ?? '' );
																			$route_label = is_array( $medication_route_labels ?? null ) && isset( $medication_route_labels[ $route_key ] )
																				? $medication_route_labels[ $route_key ]
																				: $route_key;
																			?>
																			<tr>
																				<td>
																					<strong><?php echo esc_html( (string) ( $med_row['medication_name'] ?? '' ) ); ?></strong><br />
																					<span class="description">
																						<?php
																						echo esc_html(
																							trim(
																								(string) ( $med_row['strength'] ?? '' ) . ' / ' .
																								(string) ( $med_row['dosage'] ?? '' ) . ' / ' .
																								(string) $route_label,
																								' /'
																							)
																						);
																						?>
																					</span>
																					<input type="hidden" name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][administered_time]" value="<?php echo esc_attr( $admin_input ); ?>" />
																				</td>
																				<td>
																					<input type="datetime-local" name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][scheduled_time]" value="<?php echo esc_attr( $sched_input ); ?>" />
																				</td>
																				<td>
																					<select name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][administration_status]">
																						<option value=""><?php echo esc_html__( 'Select', 'jm-referral-system' ); ?></option>
																						<?php foreach ( $admin_status_labels as $value => $label ) : ?>
																							<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $status_val, (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
																						<?php endforeach; ?>
																					</select>
																				</td>
																				<td>
																					<input type="text" class="regular-text" name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][dose_given]" value="<?php echo esc_attr( $dose_val ); ?>" />
																				</td>
																				<td>
																					<select name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][reason_code]">
																						<option value=""><?php echo esc_html__( 'None', 'jm-referral-system' ); ?></option>
																						<?php foreach ( $admin_reason_labels as $value => $label ) : ?>
																							<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $reason_val, (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
																						<?php endforeach; ?>
																					</select>
																				</td>
																				<td>
																					<textarea class="large-text" rows="2" name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][notes]"><?php echo esc_textarea( $notes_val ); ?></textarea>
																				</td>
																				<td>
																					<select name="jmrs_visit_medications[<?php echo esc_attr( (string) $med_id ); ?>][witness_user_id]">
																						<option value=""><?php echo esc_html__( 'None', 'jm-referral-system' ); ?></option>
																						<?php foreach ( $witness_users_list as $user_row ) : ?>
																							<?php
																							$uid   = absint( $user_row['id'] ?? 0 );
																							$uname = (string) ( $user_row['display_name'] ?? '' );
																							?>
																							<option value="<?php echo esc_attr( (string) $uid ); ?>" <?php selected( $witness_val, (string) $uid ); ?>><?php echo esc_html( $uname ); ?></option>
																						<?php endforeach; ?>
																					</select>
																				</td>
																			</tr>
																		<?php endforeach; ?>
																	</tbody>
																</table>
															</td>
														</tr>
													<?php endif; ?>
													<tr>
														<th scope="row">
															<label for="jmrs_visit_client_response_<?php echo esc_attr( (string) $visit_id_row ); ?>">
																<?php echo esc_html__( 'Client Response', 'jm-referral-system' ); ?>
															</label>
														</th>
														<td>
															<textarea name="jmrs_visit_client_response" id="jmrs_visit_client_response_<?php echo esc_attr( (string) $visit_id_row ); ?>" class="large-text" rows="3"><?php echo esc_textarea( $jmrs_exec_value( 'client_response' ) ); ?></textarea>
														</td>
													</tr>
													<tr>
														<th scope="row">
															<label for="jmrs_visit_wellbeing_observations_<?php echo esc_attr( (string) $visit_id_row ); ?>">
																<?php echo esc_html__( 'Wellbeing Observations', 'jm-referral-system' ); ?>
															</label>
														</th>
														<td>
															<textarea name="jmrs_visit_wellbeing_observations" id="jmrs_visit_wellbeing_observations_<?php echo esc_attr( (string) $visit_id_row ); ?>" class="large-text" rows="3"><?php echo esc_textarea( $jmrs_exec_value( 'wellbeing_observations' ) ); ?></textarea>
														</td>
													</tr>
													<tr>
														<th scope="row">
															<label for="jmrs_visit_incident_report_<?php echo esc_attr( (string) $visit_id_row ); ?>">
																<?php echo esc_html__( 'Incident Report', 'jm-referral-system' ); ?>
															</label>
														</th>
														<td>
															<textarea name="jmrs_visit_incident_report" id="jmrs_visit_incident_report_<?php echo esc_attr( (string) $visit_id_row ); ?>" class="large-text" rows="3"><?php echo esc_textarea( $jmrs_exec_value( 'incident_report' ) ); ?></textarea>
														</td>
													</tr>
												</tbody>
											</table>

											<?php
											submit_button(
												__( 'Complete Visit', 'jm-referral-system' ),
												'primary small',
												'jmrs_execute_care_visit',
												false,
												array(
													'data-jmrs-busy'    => __( 'Completing Visit...', 'jm-referral-system' ),
													'data-jmrs-confirm' => __( 'Complete this visit? Recorded tasks and medication administrations will be saved.', 'jm-referral-system' ),
												)
											);
											?>
										</form>
									<?php elseif ( $is_executed_visit ) : ?>
										<p>
											<strong><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?>:</strong>
											<?php echo esc_html( '' !== $outcome_label ? $outcome_label : '—' ); ?>
										</p>
										<p>
											<strong><?php echo esc_html__( 'Arrival', 'jm-referral-system' ); ?>:</strong>
											<?php
											echo esc_html(
												'' !== $arrival_display
													? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $arrival_display )
													: '—'
											);
											?>
										</p>
										<p>
											<strong><?php echo esc_html__( 'Departure', 'jm-referral-system' ); ?>:</strong>
											<?php
											echo esc_html(
												'' !== $departure_display
													? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $departure_display )
													: '—'
											);
											?>
										</p>
										<p>
											<strong><?php echo esc_html__( 'Duration', 'jm-referral-system' ); ?>:</strong>
											<?php
											echo esc_html(
												$duration_minutes > 0
													? sprintf(
														/* translators: %d: duration in minutes */
														_n( '%d minute', '%d minutes', $duration_minutes, 'jm-referral-system' ),
														$duration_minutes
													)
													: '—'
											);
											?>
										</p>
										<?php
										$task_summaries = is_array( $visit_row['task_summaries'] ?? null ) ? $visit_row['task_summaries'] : array();
										$completed_list = is_array( $task_summaries['completed'] ?? null ) ? $task_summaries['completed'] : array();
										$outstanding_list = is_array( $task_summaries['outstanding'] ?? null ) ? $task_summaries['outstanding'] : array();
										$refused_list = is_array( $task_summaries['refused'] ?? null ) ? $task_summaries['refused'] : array();
										?>
										<p><strong><?php echo esc_html__( 'Tasks Completed', 'jm-referral-system' ); ?>:</strong></p>
										<?php if ( empty( $completed_list ) ) : ?>
											<p>—</p>
										<?php else : ?>
											<ul>
												<?php foreach ( $completed_list as $task_label ) : ?>
													<li><?php echo esc_html( (string) $task_label ); ?></li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
										<p><strong><?php echo esc_html__( 'Tasks Outstanding', 'jm-referral-system' ); ?>:</strong></p>
										<?php if ( empty( $outstanding_list ) ) : ?>
											<p>—</p>
										<?php else : ?>
											<ul>
												<?php foreach ( $outstanding_list as $task_label ) : ?>
													<li><?php echo esc_html( (string) $task_label ); ?></li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
										<p><strong><?php echo esc_html__( 'Tasks Refused', 'jm-referral-system' ); ?>:</strong></p>
										<?php if ( empty( $refused_list ) ) : ?>
											<p>—</p>
										<?php else : ?>
											<ul>
												<?php foreach ( $refused_list as $task_label ) : ?>
													<li><?php echo esc_html( (string) $task_label ); ?></li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
										<?php if ( '' !== trim( (string) ( $visit_row['client_response'] ?? '' ) ) ) : ?>
											<p><strong><?php echo esc_html__( 'Client Response', 'jm-referral-system' ); ?>:</strong><br /><?php echo nl2br( esc_html( (string) $visit_row['client_response'] ) ); ?></p>
										<?php endif; ?>
										<?php if ( '' !== trim( (string) ( $visit_row['wellbeing_observations'] ?? '' ) ) ) : ?>
											<p><strong><?php echo esc_html__( 'Wellbeing Observations', 'jm-referral-system' ); ?>:</strong><br /><?php echo nl2br( esc_html( (string) $visit_row['wellbeing_observations'] ) ); ?></p>
										<?php endif; ?>
										<?php if ( '' !== trim( (string) ( $visit_row['incident_report'] ?? '' ) ) ) : ?>
											<p><strong><?php echo esc_html__( 'Incident Report', 'jm-referral-system' ); ?>:</strong><br /><?php echo nl2br( esc_html( (string) $visit_row['incident_report'] ) ); ?></p>
										<?php endif; ?>

										<?php if ( $can_review_visit || $is_reviewed_visit ) : ?>
											<h3 style="margin:16px 0 8px;"><?php echo esc_html__( 'Manager Review', 'jm-referral-system' ); ?></h3>
											<?php if ( $can_review_visit ) : ?>
												<form method="post" action="">
													<?php wp_nonce_field( 'jmrs_review_care_visit_' . $visit_id_row, 'jmrs_review_visit_nonce' ); ?>
													<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
													<input type="hidden" name="jmrs_visit_id" value="<?php echo esc_attr( (string) $visit_id_row ); ?>" />
													<p>
														<label for="jmrs_visit_manager_review_notes_<?php echo esc_attr( (string) $visit_id_row ); ?>">
															<?php echo esc_html__( 'Manager Review Notes', 'jm-referral-system' ); ?>
														</label><br />
														<textarea
															name="jmrs_visit_manager_review_notes"
															id="jmrs_visit_manager_review_notes_<?php echo esc_attr( (string) $visit_id_row ); ?>"
															class="large-text"
															rows="3"
															required
														><?php echo esc_textarea( $jmrs_exec_value( 'manager_review_notes' ) ); ?></textarea>
														<?php if ( isset( $execution_errors['manager_review_notes'] ) ) : ?>
															<span class="description"><?php echo esc_html( $execution_errors['manager_review_notes'] ); ?></span>
														<?php endif; ?>
													</p>
													<?php
													submit_button(
														__( 'Review Visit', 'jm-referral-system' ),
														'secondary small',
														'jmrs_review_care_visit',
														false,
														array(
															'data-jmrs-busy'    => __( 'Reviewing...', 'jm-referral-system' ),
															'data-jmrs-confirm' => __( 'Mark this visit as reviewed?', 'jm-referral-system' ),
														)
													);
													?>
												</form>
											<?php else : ?>
												<p>
													<strong><?php echo esc_html__( 'Reviewed by', 'jm-referral-system' ); ?>:</strong>
													<?php echo esc_html( (string) ( $visit_row['reviewed_by_name'] ?? '—' ) ); ?>
												</p>
												<p>
													<strong><?php echo esc_html__( 'Reviewed at', 'jm-referral-system' ); ?>:</strong>
													<?php
													$reviewed_at = (string) ( $visit_row['reviewed_at'] ?? '' );
													echo esc_html(
														'' !== $reviewed_at
															? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $reviewed_at )
															: '—'
													);
													?>
												</p>
												<?php if ( '' !== trim( (string) ( $visit_row['manager_review_notes'] ?? '' ) ) ) : ?>
													<p><strong><?php echo esc_html__( 'Manager Review Notes', 'jm-referral-system' ); ?>:</strong><br /><?php echo nl2br( esc_html( (string) $visit_row['manager_review_notes'] ) ); ?></p>
												<?php endif; ?>
											<?php endif; ?>
										<?php endif; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php $jmrs_render_visits_pagination( 'jmrs_visits_per_page_bottom' ); ?>
		<?php endif; ?>
	<?php endif; ?>

	<h2><?php echo esc_html__( 'Internal Notes', 'jm-referral-system' ); ?></h2>

	<?php if ( $notes_truncated ) : ?>
		<p class="description"><?php echo esc_html__( 'Showing the most recent 50 records.', 'jm-referral-system' ); ?></p>
	<?php endif; ?>

	<?php
	$notes_list = $notes ?? array();
	if ( is_string( $notes_list ) ) {
		$decoded_notes = json_decode( $notes_list, true );
		$notes_list    = ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded_notes ) )
			? $decoded_notes
			: array();
	} elseif ( ! is_array( $notes_list ) ) {
		$notes_list = array();
	}
	?>

	<?php if ( empty( $notes_list ) ) : ?>
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
				<?php foreach ( $notes_list as $note_row ) : ?>
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

	<?php if ( $can_add_notes ) : ?>
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
	<?php endif; ?>

	<?php if ( $can_archive_referral ) : ?>
		<h2 id="jmrs-archive-referral"><?php echo esc_html__( 'Archive Referral', 'jm-referral-system' ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'Archiving preserves all linked clinical and operational records. Permanent deletion is only allowed when a referral has no linked records.', 'jm-referral-system' ); ?>
		</p>
		<form method="post" action="" data-jmrs-confirm="<?php echo esc_attr__( 'Archive this referral? Clinical records will be preserved but the referral will become read-only.', 'jm-referral-system' ); ?>">
			<?php wp_nonce_field( 'jmrs_archive_referral_' . $referral_id, 'jmrs_archive_nonce' ); ?>
			<input type="hidden" name="referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="jmrs_archive_reason"><?php echo esc_html__( 'Archive reason', 'jm-referral-system' ); ?></label>
						</th>
						<td>
							<textarea
								name="archive_reason"
								id="jmrs_archive_reason"
								class="large-text"
								rows="3"
								required
							></textarea>
						</td>
					</tr>
				</tbody>
			</table>
			<?php
			submit_button(
				__( 'Archive Referral', 'jm-referral-system' ),
				'secondary',
				'jmrs_archive_referral'
			);
			?>
		</form>
	<?php endif; ?>

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
	<?php if ( $activities_truncated ) : ?>
		<p class="description"><?php echo esc_html__( 'Showing the most recent 50 records.', 'jm-referral-system' ); ?></p>
	<?php endif; ?>
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
