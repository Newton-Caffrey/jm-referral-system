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
