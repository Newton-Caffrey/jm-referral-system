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
$pipeline_panel             = is_array( $pipeline_panel ?? null ) ? $pipeline_panel : array();
$interest_form              = is_array( $interest_form ?? null ) ? $interest_form : array();
$interest_milestone         = is_array( $interest_milestone ?? null ) ? $interest_milestone : null;
$scheduling_panel           = is_array( $scheduling_panel ?? null ) ? $scheduling_panel : array();
$scheduling_errors          = is_array( $scheduling_errors ?? null ) ? $scheduling_errors : array();
$force_reschedule_form      = ! empty( $force_reschedule_form );
$package_cost_panel         = is_array( $package_cost_panel ?? null ) ? $package_cost_panel : array();
$package_cost_errors        = is_array( $package_cost_errors ?? null ) ? $package_cost_errors : array();
$show_prepare_form          = ! empty( $show_prepare_form );
$show_send_form             = ! empty( $show_send_form );
$pipeline_override_notice   = is_array( $pipeline_override_notice ?? null ) ? $pipeline_override_notice : null;
$interest_notice            = is_array( $interest_notice ?? null ) ? $interest_notice : null;
$scheduling_notice          = is_array( $scheduling_notice ?? null ) ? $scheduling_notice : null;
$package_cost_notice        = is_array( $package_cost_notice ?? null ) ? $package_cost_notice : null;
$la_decision_panel          = is_array( $la_decision_panel ?? null ) ? $la_decision_panel : array();
$la_decision_errors         = is_array( $la_decision_errors ?? null ) ? $la_decision_errors : array();
$show_la_decision_form      = ! empty( $show_la_decision_form );
$la_decision_notice         = is_array( $la_decision_notice ?? null ) ? $la_decision_notice : null;
$non_proceeding_panel       = is_array( $non_proceeding_panel ?? null ) ? $non_proceeding_panel : array();
$show_non_proceeding_form   = ! empty( $show_non_proceeding_form );
$non_proceeding_notice      = is_array( $non_proceeding_notice ?? null ) ? $non_proceeding_notice : null;
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
$can_manage_care_team       = ! empty( $can_manage_care_team );
$care_team_new_url          = (string) ( $care_team_new_url ?? '' );
$care_team_members          = is_array( $care_team_members ?? null ) ? $care_team_members : array();
$care_team_roles            = is_array( $care_team_roles ?? null ) ? $care_team_roles : array();
$care_team_statuses         = is_array( $care_team_statuses ?? null ) ? $care_team_statuses : array();
$can_view_visits            = ! empty( $can_view_visits );
$can_manage_visits          = ! empty( $can_manage_visits );
$visit_new_url              = (string) ( $visit_new_url ?? '' );
$care_visits                = is_array( $care_visits ?? null ) ? $care_visits : array();
$visit_status_labels        = is_array( $visit_status_labels ?? null ) ? $visit_status_labels : array();
$can_view_schedules         = ! empty( $can_view_schedules );
$can_manage_schedules       = ! empty( $can_manage_schedules );
$schedule_new_url           = (string) ( $schedule_new_url ?? '' );
$schedules                  = is_array( $schedules ?? null ) ? $schedules : array();
$schedule_repeat_labels     = is_array( $schedule_repeat_labels ?? null ) ? $schedule_repeat_labels : array();
$schedule_status_labels     = is_array( $schedule_status_labels ?? null ) ? $schedule_status_labels : array();
$can_view_medications       = ! empty( $can_view_medications );
$can_manage_medications     = ! empty( $can_manage_medications );
$medication_new_url         = (string) ( $medication_new_url ?? '' );
$medications                = is_array( $medications ?? null ) ? $medications : array();
$medication_status_labels   = is_array( $medication_status_labels ?? null ) ? $medication_status_labels : array();
$medication_route_labels    = is_array( $medication_route_labels ?? null ) ? $medication_route_labels : array();
$care_plan_reviews          = is_array( $care_plan_reviews ?? null ) ? $care_plan_reviews : array();
$care_plan_review_outcome_labels = is_array( $care_plan_review_outcome_labels ?? null ) ? $care_plan_review_outcome_labels : array();
$can_review_care_plan       = ! empty( $can_review_care_plan );
$care_plan_review_url       = (string) ( $care_plan_review_url ?? '' );
$activities                 = is_array( $activities ?? null ) ? $activities : array();
$submission_channel_label   = (string) ( $submission_channel_label ?? '' );
$is_public_referral         = ! empty( $is_public_referral );
$referrer_type_label        = (string) ( $referrer_type_label ?? '' );
$list_url                   = (string) ( $list_url ?? '' );
$can_edit_referral          = ! empty( $can_edit_referral );
$edit_url                   = (string) ( $edit_url ?? '' );
$can_archive_referral       = ! empty( $can_archive_referral );
$can_restore_referral       = ! empty( $can_restore_referral );
$updated_notice             = ! empty( $updated_notice );
$retention_notice           = is_array( $retention_notice ?? null ) ? $retention_notice : null;
$clinical_notice            = is_array( $clinical_notice ?? null ) ? $clinical_notice : null;
$archived_list_url          = (string) ( $archived_list_url ?? '' );
$referral_id                = absint( $referral['id'] ?? 0 );
$can_edit_assessment        = ! empty( $can_edit_assessment );
$assessment_url             = (string) ( $assessment_url ?? '' );
$can_manage_care_plan       = ! empty( $can_manage_care_plan );
$care_plan_url              = (string) ( $care_plan_url ?? '' );
$has_assessment             = ! empty( $has_assessment );

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
$quick_actions   = is_array( $quick_actions ?? null ) ? $quick_actions : array();
$jmrs_partials_path = JMRS_PLUGIN_PATH . 'templates/portal/partials/';
?>
<?php include $jmrs_partials_path . 'client-summary.php'; ?>

<?php if ( $updated_notice ) : ?>
	<?php
	$notice_type    = 'success';
	$notice_message = __( 'Referral updated successfully.', 'jm-referral-system' );
	unset( $notice_actions );
	include $jmrs_partials_path . 'notice.php';
	?>
<?php endif; ?>

<?php if ( is_array( $retention_notice ) && ! empty( $retention_notice['message'] ) ) : ?>
	<?php
	$notice_type    = (string) ( $retention_notice['type'] ?? 'success' );
	$notice_message = (string) $retention_notice['message'];
	$notice_actions = ( 'success' === $notice_type && isset( $_GET['jmrs_archived'] ) && '' !== $archived_list_url )
		? array( array( __( 'View archived referrals', 'jm-referral-system' ), $archived_list_url ) )
		: array();
	include $jmrs_partials_path . 'notice.php';
	?>
<?php endif; ?>

<?php if ( is_array( $clinical_notice ) && ! empty( $clinical_notice['message'] ) ) : ?>
	<?php
	$notice_type    = (string) ( $clinical_notice['type'] ?? 'success' );
	$notice_message = (string) $clinical_notice['message'];
	unset( $notice_actions );
	include $jmrs_partials_path . 'notice.php';
	?>
<?php endif; ?>

<?php if ( $is_archived ) : ?>
	<?php
	$notice_type    = 'warning';
	$archived_meta  = array();
	if ( '' !== $archived_at ) {
		$archived_meta[] = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $archived_at );
	}
	if ( '' !== $archived_by_name ) {
		$archived_meta[] = $archived_by_name;
	}
	$notice_message = __( 'This referral is archived.', 'jm-referral-system' );
	if ( ! empty( $archived_meta ) ) {
		$notice_message .= ' ' . implode( ' — ', $archived_meta );
	}
	if ( '' !== $archive_reason ) {
		$notice_message .= ' ' . sprintf(
			/* translators: %s: archive reason */
			__( 'Reason: %s', 'jm-referral-system' ),
			$archive_reason
		);
	}
	unset( $notice_actions );
	include $jmrs_partials_path . 'notice.php';
	?>
<?php endif; ?>

<div class="jmrs-portal-quick-actions jmrs-portal-quick-actions--referral">
	<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $list_url ); ?>"><?php echo esc_html__( 'Back to list', 'jm-referral-system' ); ?></a>
	<?php foreach ( $quick_actions as $quick_action ) : ?>
		<?php
		$qa_label = (string) ( $quick_action['label'] ?? '' );
		$qa_url   = (string) ( $quick_action['url'] ?? '' );
		$qa_class = (string) ( $quick_action['class'] ?? 'jmrs-button jmrs-button--secondary' );
		?>
		<?php if ( '' !== $qa_label && '' !== $qa_url ) : ?>
			<a class="<?php echo esc_attr( $qa_class ); ?>" href="<?php echo esc_url( $qa_url ); ?>"><?php echo esc_html( $qa_label ); ?></a>
		<?php endif; ?>
	<?php endforeach; ?>
	<?php if ( $can_archive_referral ) : ?>
		<a class="jmrs-button jmrs-button--secondary" href="#jmrs-archive-referral"><?php echo esc_html__( 'Archive', 'jm-referral-system' ); ?></a>
	<?php endif; ?>
	<?php if ( $can_restore_referral && $referral_id > 0 ) : ?>
		<form class="jmrs-portal-inline-form" method="post" action="" data-jmrs-confirm="<?php echo esc_attr__( 'Restore this archived referral?', 'jm-referral-system' ); ?>">
			<?php wp_nonce_field( 'jmrs_restore_referral_' . $referral_id, 'jmrs_restore_nonce' ); ?>
			<input type="hidden" name="referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
			<button type="submit" name="jmrs_restore_referral" value="1" class="jmrs-button jmrs-button--primary">
				<?php echo esc_html__( 'Restore', 'jm-referral-system' ); ?>
			</button>
		</form>
	<?php endif; ?>
</div>

<?php if ( is_array( $pipeline_override_notice ) && ! empty( $pipeline_override_notice['message'] ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--<?php echo esc_attr( (string) ( $pipeline_override_notice['type'] ?? 'info' ) ); ?>">
		<?php echo esc_html( (string) $pipeline_override_notice['message'] ); ?>
	</div>
<?php endif; ?>

<?php if ( is_array( $interest_notice ) && ! empty( $interest_notice['message'] ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--<?php echo esc_attr( (string) ( $interest_notice['type'] ?? 'info' ) ); ?>">
		<?php echo esc_html( (string) $interest_notice['message'] ); ?>
	</div>
<?php endif; ?>

<?php if ( is_array( $scheduling_notice ) && ! empty( $scheduling_notice['message'] ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--<?php echo esc_attr( (string) ( $scheduling_notice['type'] ?? 'info' ) ); ?>">
		<?php echo esc_html( (string) $scheduling_notice['message'] ); ?>
	</div>
<?php endif; ?>

<?php if ( is_array( $package_cost_notice ) && ! empty( $package_cost_notice['message'] ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--<?php echo esc_attr( (string) ( $package_cost_notice['type'] ?? 'info' ) ); ?>">
		<?php echo esc_html( (string) $package_cost_notice['message'] ); ?>
	</div>
<?php endif; ?>

<?php if ( is_array( $la_decision_notice ) && ! empty( $la_decision_notice['message'] ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--<?php echo esc_attr( (string) ( $la_decision_notice['type'] ?? 'info' ) ); ?>">
		<?php echo esc_html( (string) $la_decision_notice['message'] ); ?>
	</div>
<?php endif; ?>

<?php if ( is_array( $non_proceeding_notice ) && ! empty( $non_proceeding_notice['message'] ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--<?php echo esc_attr( (string) ( $non_proceeding_notice['type'] ?? 'info' ) ); ?>">
		<?php echo esc_html( (string) $non_proceeding_notice['message'] ); ?>
	</div>
<?php endif; ?>

<?php if ( is_array( $care_commenced_notice ) && ! empty( $care_commenced_notice['message'] ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--<?php echo esc_attr( (string) ( $care_commenced_notice['type'] ?? 'info' ) ); ?>">
		<?php echo esc_html( (string) $care_commenced_notice['message'] ); ?>
	</div>
<?php endif; ?>

<?php
$context              = 'portal';
$override_form_action = '';
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
	$form_action = '';
	$context     = 'portal';
	include JMRS_PLUGIN_PATH . 'templates/referrals/partials/assessment-scheduling.php';
}
?>

<?php
if ( ! empty( $package_cost_panel['show_panel'] ) ) {
	$form_action = '';
	$context     = 'portal';
	include JMRS_PLUGIN_PATH . 'templates/referrals/partials/package-cost.php';
}
?>

<?php
if ( ! empty( $la_decision_panel['show_panel'] ) ) {
	$form_action           = '';
	$context               = 'portal';
	$la_decision_errors    = is_array( $la_decision_errors ?? null ) ? $la_decision_errors : array();
	$show_la_decision_form = ! empty( $show_la_decision_form );
	include JMRS_PLUGIN_PATH . 'templates/referrals/partials/la-decision.php';
}
?>

<?php
if ( ! empty( $non_proceeding_panel['show_panel'] ) ) {
	$form_action              = '';
	$context                  = 'portal';
	$show_non_proceeding_form = ! empty( $show_non_proceeding_form );
	include JMRS_PLUGIN_PATH . 'templates/referrals/partials/non-proceeding.php';
}
?>

<?php
if ( ! empty( $transition_panel['show_panel'] ) ) {
	$form_action        = '';
	$context            = 'portal';
	$transition_errors  = is_array( $transition_errors ?? null ) ? $transition_errors : array();
	$show_commence_form = ! empty( $show_commence_form );
	include JMRS_PLUGIN_PATH . 'templates/referrals/partials/transition-planning.php';
}
?>

<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-summary">
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

<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-client">
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

<?php include JMRS_PLUGIN_PATH . 'templates/portal/partials/care-setting-panels.php'; ?>

<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-referrer">
	<h2 id="jmrs-portal-ref-referrer" class="jmrs-portal-section__title"><?php echo esc_html__( 'Referrer', 'jm-referral-system' ); ?></h2>
	<div class="jmrs-portal-dl-grid jmrs-portal-dl-grid--referrer">
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
	<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-care">
		<h2 id="jmrs-portal-ref-care" class="jmrs-portal-section__title"><?php echo esc_html__( 'Care requirements', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-prose"><?php echo nl2br( esc_html( $care_needs ) ); ?></div>
	</section>
<?php endif; ?>

<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-docs">
	<h2 id="jmrs-portal-ref-docs" class="jmrs-portal-section__title"><?php echo esc_html__( 'Documents', 'jm-referral-system' ); ?></h2>
	<?php if ( ! $can_download_documents ) : ?>
		<?php
		$empty_title   = '';
		$empty_message = __( 'You do not have permission to download documents.', 'jm-referral-system' );
		unset( $empty_actions );
		include $jmrs_partials_path . 'empty-state.php';
		?>
	<?php elseif ( empty( $documents ) ) : ?>
		<?php
		$empty_title   = '';
		$empty_message = __( 'No documents uploaded yet.', 'jm-referral-system' );
		unset( $empty_actions );
		include $jmrs_partials_path . 'empty-state.php';
		?>
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
<section class="jmrs-portal-section jmrs-portal-clinical jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-assessment">
	<?php
	$section_title  = __( 'Assessment', 'jm-referral-system' );
	$section_id     = 'jmrs-portal-ref-assessment';
	$section_badge  = null;
	if ( null !== $assessment ) {
		$outcome_key_badge = (string) ( $assessment_data['outcome'] ?? '' );
		$outcome_badge     = $assessment_outcomes[ $outcome_key_badge ] ?? $outcome_key_badge;
		$section_badge     = '' !== $outcome_badge ? (string) $outcome_badge : null;
	}
	$section_actions = ( $can_edit_assessment && '' !== $assessment_url )
		? array(
			array(
				null === $assessment ? __( 'Create Assessment', 'jm-referral-system' ) : __( 'Edit Assessment', 'jm-referral-system' ),
				$assessment_url,
			),
		)
		: array();
	include $jmrs_partials_path . 'section-header.php';
	?>
	<?php if ( null === $assessment ) : ?>
		<?php
		$empty_title   = '';
		$empty_message = __( 'No assessment recorded.', 'jm-referral-system' );
		$empty_actions = ( $can_edit_assessment && '' !== $assessment_url )
			? array( array( __( 'Create Assessment', 'jm-referral-system' ), $assessment_url, 'jmrs-button jmrs-button--primary' ) )
			: array();
		include $jmrs_partials_path . 'empty-state.php';
		?>
	<?php else : ?>
		<?php
		$outcome_key            = $jmrs_assessment_value( 'outcome' );
		$outcome_label          = $assessment_outcomes[ $outcome_key ] ?? $outcome_key;
		$assessment_date_value  = $jmrs_assessment_value( 'assessment_date' );
		$assessment_next_review = $jmrs_assessment_value( 'next_review_date' );
		?>
		<div class="jmrs-portal-summary-card">
			<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Assessment Overview', 'jm-referral-system' ); ?></h3>
			<dl class="jmrs-portal-summary">
				<div>
					<dt><?php echo esc_html__( 'Assessment Date', 'jm-referral-system' ); ?></dt>
					<dd>
						<?php
						echo '' !== $assessment_date_value
							? esc_html( mysql2date( get_option( 'date_format' ), $assessment_date_value ) )
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
						echo $jmrs_assessment_has_value( $assessment_next_review )
							? esc_html( mysql2date( get_option( 'date_format' ), $assessment_next_review ) )
							: esc_html__( 'Not recorded', 'jm-referral-system' );
						?>
					</dd>
				</div>
			</dl>
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
				<div class="jmrs-portal-summary-card">
					<h3 class="jmrs-portal-subsection__title"><?php echo esc_html( (string) $section_title ); ?></h3>
					<?php foreach ( $visible_fields as $visible_field ) : ?>
						<div class="jmrs-portal-summary-block">
							<h4 class="jmrs-portal-summary-block__title"><?php echo esc_html( (string) $visible_field['label'] ); ?></h4>
							<p class="jmrs-portal-prose"><?php echo nl2br( esc_html( (string) $visible_field['value'] ) ); ?></p>
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
			<div class="jmrs-portal-summary-card">
				<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Proposed Care Package', 'jm-referral-system' ); ?></h3>
				<dl class="jmrs-portal-summary">
					<?php foreach ( $package_visible as $package_field ) : ?>
						<div<?php echo ! empty( $package_field['multiline'] ) ? ' class="jmrs-portal-summary__wide"' : ''; ?>>
							<dt><?php echo esc_html( (string) $package_field['label'] ); ?></dt>
							<dd class="<?php echo ! empty( $package_field['multiline'] ) ? 'jmrs-portal-prose' : ''; ?>">
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
				</dl>
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
			<div class="jmrs-portal-summary-card">
				<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Summary and Recommendations', 'jm-referral-system' ); ?></h3>
				<?php foreach ( $summary_visible as $summary_field ) : ?>
					<div class="jmrs-portal-summary-block">
						<h4 class="jmrs-portal-summary-block__title"><?php echo esc_html( (string) $summary_field['label'] ); ?></h4>
						<p class="jmrs-portal-prose"><?php echo nl2br( esc_html( (string) $summary_field['value'] ) ); ?></p>
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
	<section class="jmrs-portal-section jmrs-portal-clinical jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-careplan">
		<?php
		$section_title = __( 'Care Plan', 'jm-referral-system' );
		$section_id    = 'jmrs-portal-ref-careplan';
		$section_badge = null;
		if ( null !== $care_plan ) {
			$plan_status_badge = (string) ( $care_plan_data['plan_status'] ?? '' );
			$plan_badge_label  = $care_plan_statuses[ $plan_status_badge ] ?? $plan_status_badge;
			$section_badge     = '' !== $plan_badge_label ? (string) $plan_badge_label : null;
		}
		$section_actions = array();
		if ( $can_manage_care_plan && '' !== $care_plan_url ) {
			$section_actions[] = array(
				null === $care_plan ? __( 'Create Care Plan', 'jm-referral-system' ) : __( 'Edit Care Plan', 'jm-referral-system' ),
				$care_plan_url,
			);
		}
		if ( $can_review_care_plan && '' !== $care_plan_review_url ) {
			$section_actions[] = array( __( 'Review Care Plan', 'jm-referral-system' ), $care_plan_review_url );
		}
		include $jmrs_partials_path . 'section-header.php';
		?>
		<?php if ( null === $care_plan ) : ?>
			<?php
			$empty_title   = '';
			$empty_message = __( 'No care plan recorded.', 'jm-referral-system' );
			$empty_actions = array();
			if ( $can_manage_care_plan && '' !== $care_plan_url && $has_assessment ) {
				$empty_actions[] = array( __( 'Generate from Assessment', 'jm-referral-system' ), $care_plan_url );
			} elseif ( $can_manage_care_plan && '' !== $care_plan_url ) {
				$empty_actions[] = array( __( 'Create Care Plan', 'jm-referral-system' ), $care_plan_url, 'jmrs-button jmrs-button--primary' );
			}
			include $jmrs_partials_path . 'empty-state.php';
			?>
		<?php else : ?>
			<?php
			$plan_status       = $jmrs_care_plan_value( 'plan_status' );
			$plan_label        = $care_plan_statuses[ $plan_status ] ?? $plan_status;
			$start_date_value  = $jmrs_care_plan_value( 'start_date' );
			$review_date_value = $jmrs_care_plan_value( 'review_date' );
			?>
			<div class="jmrs-portal-summary-card">
				<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Care Plan Overview', 'jm-referral-system' ); ?></h3>
				<dl class="jmrs-portal-summary">
					<div>
						<dt><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></dt>
						<dd><span class="jmrs-portal-badge"><?php echo esc_html( $plan_label ); ?></span></dd>
					</div>
					<div>
						<dt><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></dt>
						<dd>
							<?php
							echo '' !== trim( $start_date_value )
								? esc_html( mysql2date( get_option( 'date_format' ), $start_date_value ) )
								: esc_html__( 'Not recorded', 'jm-referral-system' );
							?>
						</dd>
					</div>
					<div>
						<dt><?php echo esc_html__( 'Review Date', 'jm-referral-system' ); ?></dt>
						<dd>
							<?php
							echo '' !== trim( $review_date_value )
								? esc_html( mysql2date( get_option( 'date_format' ), $review_date_value ) )
								: esc_html__( 'Not recorded', 'jm-referral-system' );
							?>
						</dd>
					</div>
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
				</dl>
			</div>

			<?php
			$content_blocks = array();
			foreach ( $care_plan_content_fields as $field_key => $field_label ) {
				$field_value = $jmrs_care_plan_value( $field_key );
				if ( '' === trim( $field_value ) ) {
					continue;
				}
				$content_blocks[] = array(
					'label'     => $field_label,
					'value'     => $field_value,
					'short'     => in_array( $field_key, $care_plan_short_fields, true ),
				);
			}
			?>
			<?php if ( ! empty( $content_blocks ) ) : ?>
				<div class="jmrs-portal-summary-card">
					<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Care Plan Content', 'jm-referral-system' ); ?></h3>
					<?php foreach ( $content_blocks as $block ) : ?>
						<?php if ( ! empty( $block['short'] ) ) : ?>
							<dl class="jmrs-portal-summary">
								<div>
									<dt><?php echo esc_html( (string) $block['label'] ); ?></dt>
									<dd><?php echo esc_html( (string) $block['value'] ); ?></dd>
								</div>
							</dl>
						<?php else : ?>
							<div class="jmrs-portal-summary-block">
								<h4 class="jmrs-portal-summary-block__title"><?php echo esc_html( (string) $block['label'] ); ?></h4>
								<p class="jmrs-portal-prose"><?php echo nl2br( esc_html( (string) $block['value'] ) ); ?></p>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="jmrs-portal-summary-card">
				<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Review History', 'jm-referral-system' ); ?></h3>
				<?php if ( empty( $care_plan_reviews ) ) : ?>
					<p class="jmrs-portal-muted"><?php echo esc_html__( 'No reviews recorded yet.', 'jm-referral-system' ); ?></p>
				<?php else : ?>
					<div class="jmrs-portal-table-wrap">
						<table class="jmrs-portal-table">
							<thead>
								<tr>
									<th scope="col"><?php echo esc_html__( 'Review Date', 'jm-referral-system' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Next Review Date', 'jm-referral-system' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Reviewed By', 'jm-referral-system' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $care_plan_reviews as $review_row ) : ?>
									<?php
									$review_outcome_key  = (string) ( $review_row['outcome'] ?? '' );
									$review_outcome_text = $care_plan_review_outcome_labels[ $review_outcome_key ] ?? $review_outcome_key;
									$review_date_raw     = (string) ( $review_row['review_date'] ?? '' );
									$next_review_raw     = (string) ( $review_row['next_review_date'] ?? '' );
									$review_notes        = (string) ( $review_row['notes'] ?? '' );
									?>
									<tr>
										<td data-label="<?php echo esc_attr__( 'Review Date', 'jm-referral-system' ); ?>">
											<?php echo esc_html( '' !== $review_date_raw ? mysql2date( get_option( 'date_format' ), $review_date_raw ) : '—' ); ?>
										</td>
										<td data-label="<?php echo esc_attr__( 'Outcome', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge"><?php echo esc_html( (string) $review_outcome_text ); ?></span></td>
										<td data-label="<?php echo esc_attr__( 'Next Review Date', 'jm-referral-system' ); ?>">
											<?php echo esc_html( '' !== trim( $next_review_raw ) ? mysql2date( get_option( 'date_format' ), $next_review_raw ) : '—' ); ?>
										</td>
										<td data-label="<?php echo esc_attr__( 'Reviewed By', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $review_row['reviewer_name'] ?? '' ) ?: '—' ); ?></td>
										<td data-label="<?php echo esc_attr__( 'Notes', 'jm-referral-system' ); ?>"><?php echo '' !== trim( $review_notes ) ? nl2br( esc_html( $review_notes ) ) : '—'; ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $can_view_care_team ) : ?>
	<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-team">
		<?php
		$section_title  = __( 'Care team', 'jm-referral-system' );
		$section_id     = 'jmrs-portal-ref-team';
		$section_badge  = null;
		$section_actions = ( $can_manage_care_team && '' !== $care_team_new_url )
			? array( array( __( 'Add Team Member', 'jm-referral-system' ), $care_team_new_url ) )
			: array();
		include $jmrs_partials_path . 'section-header.php';
		?>
		<?php if ( empty( $care_team_members ) ) : ?>
			<?php
			$empty_title   = '';
			$empty_message = __( 'No care team members assigned.', 'jm-referral-system' );
			$empty_actions = ( $can_manage_care_team && '' !== $care_team_new_url )
				? array( array( __( 'Add Team Member', 'jm-referral-system' ), $care_team_new_url, 'jmrs-button jmrs-button--primary' ) )
				: array();
			include $jmrs_partials_path . 'empty-state.php';
			?>
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
							<?php if ( $can_manage_care_team ) : ?>
								<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
							<?php endif; ?>
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
							$member_edit_url = (string) ( $member['edit_url'] ?? '' );
							?>
							<tr>
								<td data-label="<?php echo esc_attr__( 'Staff Member', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $member['staff_name'] ?? '' ) ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Team Role', 'jm-referral-system' ); ?>"><?php echo esc_html( $role_label ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Primary', 'jm-referral-system' ); ?>"><?php echo $is_primary ? esc_html__( 'Yes', 'jm-referral-system' ) : esc_html__( 'No', 'jm-referral-system' ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge"><?php echo esc_html( $status_lab ); ?></span></td>
								<td data-label="<?php echo esc_attr__( 'Start Date', 'jm-referral-system' ); ?>"><?php echo esc_html( $start_display ); ?></td>
								<td data-label="<?php echo esc_attr__( 'End Date', 'jm-referral-system' ); ?>"><?php echo esc_html( $end_display ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Notes', 'jm-referral-system' ); ?>"><?php echo '' !== trim( $member_notes ) ? nl2br( esc_html( $member_notes ) ) : '—'; ?></td>
								<?php if ( $can_manage_care_team ) : ?>
									<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
										<?php if ( '' !== $member_edit_url ) : ?>
											<a class="jmrs-portal-link" href="<?php echo esc_url( $member_edit_url ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></a>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $can_view_visits ) : ?>
	<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-visits">
		<?php
		$section_title  = __( 'Recent visits', 'jm-referral-system' );
		$section_id     = 'jmrs-portal-ref-visits';
		$section_badge  = null;
		$section_actions = ( $can_manage_visits && '' !== $visit_new_url )
			? array( array( __( 'Schedule Visit', 'jm-referral-system' ), $visit_new_url ) )
			: array();
		include $jmrs_partials_path . 'section-header.php';
		?>
		<?php if ( empty( $care_visits ) ) : ?>
			<?php
			$empty_title   = '';
			$empty_message = __( 'No visits scheduled.', 'jm-referral-system' );
			$empty_actions = ( $can_manage_visits && '' !== $visit_new_url )
				? array( array( __( 'Schedule Visit', 'jm-referral-system' ), $visit_new_url, 'jmrs-button jmrs-button--primary' ) )
				: array();
			include $jmrs_partials_path . 'empty-state.php';
			?>
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
							<th scope="col"><?php echo esc_html__( 'Service Location', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
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
							$visit_edit_url    = (string) ( $visit_row['edit_url'] ?? '' );
							$visit_execute_url = (string) ( $visit_row['execute_url'] ?? '' );
							$visit_review_url  = (string) ( $visit_row['review_url'] ?? '' );
							$location_short    = (string) ( $visit_row['service_location_short'] ?? '' );
							?>
							<tr>
								<td data-label="<?php echo esc_attr__( 'Date', 'jm-referral-system' ); ?>"><?php echo esc_html( $visit_date_display ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Time', 'jm-referral-system' ); ?>"><?php echo esc_html( $time_display ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Assigned Staff', 'jm-referral-system' ); ?>"><?php echo '' !== $assigned_name ? esc_html( $assigned_name ) : '—'; ?></td>
								<td data-label="<?php echo esc_attr__( 'Visit Type', 'jm-referral-system' ); ?>"><?php echo '' !== trim( $visit_type ) ? esc_html( $visit_type ) : '—'; ?></td>
								<td data-label="<?php echo esc_attr__( 'Source', 'jm-referral-system' ); ?>"><?php echo esc_html( $source_label ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge"><?php echo esc_html( $status_label ); ?></span></td>
								<td data-label="<?php echo esc_attr__( 'Service Location', 'jm-referral-system' ); ?>"><?php echo '' !== $location_short ? esc_html( $location_short ) : '—'; ?></td>
								<td data-label="<?php echo esc_attr__( 'Outcome', 'jm-referral-system' ); ?>"><?php echo '' !== $outcome_label ? esc_html( $outcome_label ) : '—'; ?></td>
								<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
									<?php
									$visit_actions = array();
									if ( '' !== $visit_execute_url ) {
										$visit_actions[] = array( $visit_execute_url, __( 'Execute', 'jm-referral-system' ) );
									}
									if ( '' !== $visit_review_url ) {
										$visit_actions[] = array( $visit_review_url, __( 'Review', 'jm-referral-system' ) );
									}
									if ( '' !== $visit_edit_url ) {
										$visit_actions[] = array( $visit_edit_url, __( 'Edit', 'jm-referral-system' ) );
									}
									?>
									<?php if ( empty( $visit_actions ) ) : ?>
										—
									<?php else : ?>
										<?php foreach ( $visit_actions as $index => $visit_action ) : ?>
											<?php echo $index > 0 ? ' · ' : ''; ?><a class="jmrs-portal-link" href="<?php echo esc_url( $visit_action[0] ); ?>"><?php echo esc_html( $visit_action[1] ); ?></a>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $can_view_schedules ) : ?>
	<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-schedules">
		<?php
		$section_title  = __( 'Schedules', 'jm-referral-system' );
		$section_id     = 'jmrs-portal-ref-schedules';
		$section_badge  = null;
		$section_actions = ( $can_manage_schedules && '' !== $schedule_new_url )
			? array( array( __( 'Add Schedule', 'jm-referral-system' ), $schedule_new_url ) )
			: array();
		include $jmrs_partials_path . 'section-header.php';
		?>
		<?php if ( empty( $schedules ) ) : ?>
			<?php
			$empty_title   = '';
			$empty_message = __( 'No schedules configured.', 'jm-referral-system' );
			$empty_actions = ( $can_manage_schedules && '' !== $schedule_new_url )
				? array( array( __( 'Add Schedule', 'jm-referral-system' ), $schedule_new_url, 'jmrs-button jmrs-button--primary' ) )
				: array();
			include $jmrs_partials_path . 'empty-state.php';
			?>
		<?php else : ?>
			<div class="jmrs-portal-table-wrap">
				<table class="jmrs-portal-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Repeats', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'End Date', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Generated Visits', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $schedules as $schedule_row ) : ?>
							<?php
							$repeat_key       = (string) ( $schedule_row['repeat_type'] ?? '' );
							$status_key       = (string) ( $schedule_row['status'] ?? '' );
							$repeat_label     = $schedule_repeat_labels[ $repeat_key ] ?? ucfirst( $repeat_key );
							$status_label     = $schedule_status_labels[ $status_key ] ?? ucfirst( $status_key );
							$sched_start_raw  = (string) ( $schedule_row['start_date'] ?? '' );
							$sched_end_raw    = (string) ( $schedule_row['end_date'] ?? '' );
							$generated_count  = absint( $schedule_row['generated_visit_count'] ?? 0 );
							$schedule_edit_url     = (string) ( $schedule_row['edit_url'] ?? '' );
							$schedule_generate_url = (string) ( $schedule_row['generate_url'] ?? '' );
							?>
							<tr>
								<td data-label="<?php echo esc_attr__( 'Name', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $schedule_row['schedule_name'] ?? '' ) ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Repeats', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) $repeat_label ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Start Date', 'jm-referral-system' ); ?>">
									<?php echo esc_html( '' !== $sched_start_raw ? mysql2date( get_option( 'date_format' ), $sched_start_raw ) : '—' ); ?>
								</td>
								<td data-label="<?php echo esc_attr__( 'End Date', 'jm-referral-system' ); ?>">
									<?php echo esc_html( '' !== $sched_end_raw ? mysql2date( get_option( 'date_format' ), $sched_end_raw ) : '—' ); ?>
								</td>
								<td data-label="<?php echo esc_attr__( 'Status', 'jm-referral-system' ); ?>"><span class="jmrs-portal-badge"><?php echo esc_html( (string) $status_label ); ?></span></td>
								<td data-label="<?php echo esc_attr__( 'Generated Visits', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) $generated_count ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
									<?php if ( '' === $schedule_edit_url && '' === $schedule_generate_url ) : ?>
										—
									<?php else : ?>
										<?php if ( '' !== $schedule_generate_url ) : ?>
											<a class="jmrs-portal-link" href="<?php echo esc_url( $schedule_generate_url ); ?>"><?php echo esc_html__( 'Generate Visits', 'jm-referral-system' ); ?></a>
										<?php endif; ?>
										<?php if ( '' !== $schedule_generate_url && '' !== $schedule_edit_url ) : ?>
											 · 
										<?php endif; ?>
										<?php if ( '' !== $schedule_edit_url ) : ?>
											<a class="jmrs-portal-link" href="<?php echo esc_url( $schedule_edit_url ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></a>
										<?php endif; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $can_view_medications ) : ?>
	<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-meds">
		<?php
		$section_title  = __( 'Medications', 'jm-referral-system' );
		$section_id     = 'jmrs-portal-ref-meds';
		$section_badge  = null;
		$section_actions = ( $can_manage_medications && '' !== $medication_new_url )
			? array( array( __( 'Add Medication', 'jm-referral-system' ), $medication_new_url ) )
			: array();
		include $jmrs_partials_path . 'section-header.php';
		?>
		<?php if ( empty( $medications ) ) : ?>
			<?php
			$empty_title   = '';
			$empty_message = __( 'No medications recorded.', 'jm-referral-system' );
			$empty_actions = ( $can_manage_medications && '' !== $medication_new_url )
				? array( array( __( 'Add Medication', 'jm-referral-system' ), $medication_new_url, 'jmrs-button jmrs-button--primary' ) )
				: array();
			include $jmrs_partials_path . 'empty-state.php';
			?>
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
							<?php if ( $can_manage_medications ) : ?>
								<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $medications as $med ) : ?>
							<?php
							$status_key  = (string) ( $med['medication_status'] ?? '' );
							$route_key   = (string) ( $med['route'] ?? '' );
							$status_text = $medication_status_labels[ $status_key ] ?? $status_key;
							$route_text  = $medication_route_labels[ $route_key ] ?? $route_key;
							$med_edit_url = (string) ( $med['edit_url'] ?? '' );
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
								<?php if ( $can_manage_medications ) : ?>
									<td data-label="<?php echo esc_attr__( 'Actions', 'jm-referral-system' ); ?>">
										<?php if ( '' !== $med_edit_url ) : ?>
											<a class="jmrs-portal-link" href="<?php echo esc_url( $med_edit_url ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></a>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-portal-ref-activity">
	<h2 id="jmrs-portal-ref-activity" class="jmrs-portal-section__title"><?php echo esc_html__( 'Activity Timeline', 'jm-referral-system' ); ?></h2>
	<?php if ( empty( $activities ) ) : ?>
		<?php
		$empty_title   = '';
		$empty_message = __( 'No activity recorded for this referral.', 'jm-referral-system' );
		unset( $empty_actions );
		include $jmrs_partials_path . 'empty-state.php';
		?>
	<?php else : ?>
		<ol class="jmrs-portal-timeline">
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
				<li class="jmrs-portal-timeline__item">
					<span class="jmrs-portal-timeline__marker" aria-hidden="true"></span>
					<div class="jmrs-portal-timeline__content">
						<div class="jmrs-portal-timeline__head">
							<span class="jmrs-portal-timeline__action"><?php echo esc_html( $action_display ); ?></span>
							<?php if ( '' !== $activity_display ) : ?>
								<time class="jmrs-portal-timeline__time"><?php echo esc_html( $activity_display ); ?></time>
							<?php endif; ?>
						</div>
						<?php if ( '' !== $description ) : ?>
							<p class="jmrs-portal-timeline__description"><?php echo esc_html( $description ); ?></p>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</section>

<?php if ( $can_archive_referral && $referral_id > 0 ) : ?>
	<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-archive-referral-title" id="jmrs-archive-referral">
		<h2 id="jmrs-archive-referral-title" class="jmrs-portal-section__title"><?php echo esc_html__( 'Archive Referral', 'jm-referral-system' ); ?></h2>
		<p class="jmrs-portal-muted">
			<?php echo esc_html__( 'Archive this referral? The record will remain available under Archived Referrals.', 'jm-referral-system' ); ?>
		</p>
		<form
			class="jmrs-portal-form"
			method="post"
			action=""
			data-jmrs-confirm="<?php echo esc_attr__( 'Archive this referral? The record will remain available under Archived Referrals.', 'jm-referral-system' ); ?>"
		>
			<?php wp_nonce_field( 'jmrs_archive_referral_' . $referral_id, 'jmrs_archive_nonce' ); ?>
			<input type="hidden" name="referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
			<input type="hidden" name="jmrs_portal_redirect" value="<?php echo esc_url( \JMReferral\Portal\PortalUrls::referral( $referral_id ) ); ?>" />
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_archive_reason"><?php echo esc_html__( 'Archive reason', 'jm-referral-system' ); ?></label>
				<textarea
					name="archive_reason"
					id="jmrs_archive_reason"
					rows="3"
					required
				></textarea>
			</div>
			<p class="jmrs-portal-actions">
				<button type="submit" name="jmrs_archive_referral" value="1" class="jmrs-button jmrs-button--danger">
					<?php echo esc_html__( 'Archive Referral', 'jm-referral-system' ); ?>
				</button>
			</p>
		</form>
	</section>
<?php endif; ?>
