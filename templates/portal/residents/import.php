<?php
/**
 * Import existing residents from a spreadsheet (Phase 5F.1).
 *
 * Stages: upload → preview (checked rows) → done, or failed.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stage         = (string) ( $stage ?? 'upload' );
$error         = (string) ( $error ?? '' );
$preview       = is_array( $preview ?? null ) ? $preview : null;
$result        = is_array( $result ?? null ) ? $result : null;
$form_action   = (string) ( $form_action ?? '' );
$overview_url  = (string) ( $overview_url ?? '' );
$template_csv  = (string) ( $template_csv ?? '' );
$template_xlsx = (string) ( $template_xlsx ?? '' );
$max_rows      = absint( $max_rows ?? 0 );
$max_size      = (string) ( $max_size ?? '' );
$columns       = is_array( $columns ?? null ) ? $columns : array();

$upload_form = static function ( string $button_label ) use ( $form_action, $error ): void {
	?>
	<form class="jmrs-portal-form jmrs-er-upload" method="post" action="<?php echo esc_url( $form_action ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( 'jmrs_resident_import', 'jmrs_resident_import_nonce' ); ?>
		<input type="hidden" name="jmrs_import_action" value="preview" />
		<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( (string) \JMReferral\Residents\ExistingResident::MAX_IMPORT_BYTES ); ?>" />
		<div class="jmrs-portal-field jmrs-portal-field--full">
			<label for="jmrs_import_file">
				<?php echo esc_html__( 'Completed spreadsheet', 'jm-referral-system' ); ?>
				<span aria-hidden="true">*</span><span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
			</label>
			<input type="file" name="jmrs_import_file" id="jmrs_import_file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required aria-required="true"<?php echo '' !== $error ? ' aria-invalid="true" aria-describedby="jmrs-import-error"' : ''; ?> />
		</div>
		<p class="jmrs-prepare-actions">
			<button type="submit" class="jmrs-button jmrs-button--primary"><?php echo esc_html( $button_label ); ?></button>
		</p>
	</form>
	<?php
};
?>
<p class="jmrs-inbox-detail__back">
	<a href="<?php echo esc_url( $overview_url ); ?>"><?php echo esc_html__( 'Back to Add Existing Residents', 'jm-referral-system' ); ?></a>
</p>

<?php if ( 'done' === $stage && null !== $result ) : ?>
	<?php $created = is_array( $result['created'] ?? null ) ? $result['created'] : array(); ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--success" role="status">
		<p>
			<strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of residents */
						_n( '%d existing resident was added.', '%d existing residents were added.', count( $created ), 'jm-referral-system' ),
						count( $created )
					)
				);
				?>
			</strong>
			<?php echo esc_html__( 'Occupancy and dashboards are already up to date. Next, add each person\'s care plan, risk assessments and documents from their record.', 'jm-referral-system' ); ?>
		</p>
	</div>
	<section class="jmrs-portal-section" aria-labelledby="jmrs-import-done-heading">
		<h2 class="jmrs-portal-section__title" id="jmrs-import-done-heading"><?php echo esc_html__( 'Residents added', 'jm-referral-system' ); ?></h2>
		<p class="jmrs-portal-muted">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: import reference */
					__( 'Import reference: %s', 'jm-referral-system' ),
					(string) ( $result['batch'] ?? '' )
				)
			);
			?>
		</p>
		<div class="jmrs-portal-table-wrap">
			<table class="jmrs-portal-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Row', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Record number', 'jm-referral-system' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $created as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) absint( $row['row'] ?? 0 ) ); ?></td>
							<td><a href="<?php echo esc_url( (string) ( $row['url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $row['name'] ?? '' ) ); ?></a></td>
							<td><?php echo esc_html( (string) ( $row['referral_number'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p><a class="jmrs-button" href="<?php echo esc_url( $form_action ); ?>"><?php echo esc_html__( 'Import Another Spreadsheet', 'jm-referral-system' ); ?></a></p>
	</section>
	<?php return; ?>
<?php endif; ?>

<?php if ( 'failed' === $stage && null !== $result ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert">
		<p><strong><?php echo esc_html( (string) ( $result['message'] ?? '' ) ); ?></strong></p>
		<?php $row_errors = is_array( $result['row_errors'] ?? null ) ? $result['row_errors'] : array(); ?>
		<?php if ( ! empty( $row_errors ) ) : ?>
			<ul>
				<?php foreach ( $row_errors as $row_number => $messages ) : ?>
					<?php foreach ( (array) $messages as $message ) : ?>
						<li>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: row number, 2: message */
									__( 'Row %1$d — %2$s', 'jm-referral-system' ),
									(int) $row_number,
									(string) $message
								)
							);
							?>
						</li>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<p><?php echo esc_html__( 'Correct the spreadsheet and upload it again.', 'jm-referral-system' ); ?></p>
	</div>
<?php endif; ?>

<?php if ( 'preview' === $stage && null !== $preview ) : ?>
	<?php
	$total     = absint( $preview['total'] ?? 0 );
	$ready     = absint( $preview['ready'] ?? 0 );
	$all_ready = $total > 0 && $ready === $total && '' !== (string) ( $preview['token'] ?? '' );
	$rows      = is_array( $preview['rows'] ?? null ) ? $preview['rows'] : array();
	$ignored   = is_array( $preview['ignored_columns'] ?? null ) ? $preview['ignored_columns'] : array();
	$skipped   = absint( $preview['skipped_examples'] ?? 0 );
	?>
	<section class="jmrs-portal-section" aria-labelledby="jmrs-import-preview-heading">
		<h2 class="jmrs-portal-section__title" id="jmrs-import-preview-heading"><?php echo esc_html__( 'Check before importing', 'jm-referral-system' ); ?></h2>

		<?php if ( $all_ready ) : ?>
			<div class="jmrs-portal-notice jmrs-portal-notice--success" role="status">
				<p>
					<strong>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of residents */
								_n( '%d resident is ready to import.', 'All %d residents are ready to import.', $total, 'jm-referral-system' ),
								$total
							)
						);
						?>
					</strong>
					<?php echo esc_html__( 'Nothing has been saved yet. Check the list, then confirm.', 'jm-referral-system' ); ?>
				</p>
			</div>
		<?php else : ?>
			<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert">
				<p>
					<strong>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: rows with problems, 2: total rows */
								__( '%1$d of %2$d rows need correcting.', 'jm-referral-system' ),
								$total - $ready,
								$total
							)
						);
						?>
					</strong>
					<?php echo esc_html__( 'Nothing has been saved. Residents are only imported when every row is correct, so you never end up with half an import. Correct the rows below in your spreadsheet and upload it again.', 'jm-referral-system' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( $skipped > 0 ) : ?>
			<p class="jmrs-portal-muted"><?php echo esc_html__( 'The example row from the template was skipped.', 'jm-referral-system' ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $ignored ) ) : ?>
			<p class="jmrs-portal-muted">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: list of column headings */
						__( 'These columns were not recognised and were ignored: %s.', 'jm-referral-system' ),
						implode( ', ', array_map( 'strval', $ignored ) )
					)
				);
				?>
			</p>
		<?php endif; ?>

		<div class="jmrs-portal-table-wrap">
			<table class="jmrs-portal-table jmrs-import-preview">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Row', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Care setting', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Property / address', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Funding', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Check', 'jm-referral-system' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$row_errors = is_array( $row['errors'] ?? null ) ? $row['errors'] : array();
						$row_notes  = is_array( $row['notes'] ?? null ) ? $row['notes'] : array();
						?>
						<tr class="<?php echo empty( $row_errors ) ? 'jmrs-import-row--ok' : 'jmrs-import-row--error'; ?>">
							<td><?php echo esc_html( (string) absint( $row['row'] ?? 0 ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['name'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['setting'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['location'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( '' !== (string) ( $row['funding'] ?? '' ) ? (string) $row['funding'] : '—' ); ?></td>
							<td>
								<?php if ( empty( $row_errors ) ) : ?>
									<span class="jmrs-portal-badge"><?php echo esc_html__( 'Ready', 'jm-referral-system' ); ?></span>
								<?php else : ?>
									<ul class="jmrs-import-errors">
										<?php foreach ( $row_errors as $message ) : ?>
											<li><?php echo esc_html( (string) $message ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
								<?php foreach ( $row_notes as $note ) : ?>
									<p class="jmrs-portal-muted"><?php echo esc_html( (string) $note ); ?></p>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $all_ready ) : ?>
			<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="jmrs-prepare-actions">
				<?php wp_nonce_field( 'jmrs_resident_import', 'jmrs_resident_import_nonce' ); ?>
				<input type="hidden" name="jmrs_import_token" value="<?php echo esc_attr( (string) $preview['token'] ); ?>" />
				<button type="submit" name="jmrs_import_action" value="confirm" class="jmrs-button jmrs-button--primary">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of residents */
							_n( 'Import %d Resident', 'Import %d Residents', $total, 'jm-referral-system' ),
							$total
						)
					);
					?>
				</button>
				<button type="submit" name="jmrs_import_action" value="cancel" class="jmrs-button"><?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?></button>
			</form>
			<p class="jmrs-portal-muted"><?php echo esc_html__( 'This check is kept for 30 minutes. After that, upload the spreadsheet again.', 'jm-referral-system' ); ?></p>
		<?php endif; ?>
	</section>

	<?php if ( ! $all_ready ) : ?>
		<section class="jmrs-portal-section" aria-labelledby="jmrs-import-again-heading">
			<h2 class="jmrs-portal-section__title" id="jmrs-import-again-heading"><?php echo esc_html__( 'Upload the corrected spreadsheet', 'jm-referral-system' ); ?></h2>
			<?php $upload_form( __( 'Upload and Check', 'jm-referral-system' ) ); ?>
		</section>
	<?php endif; ?>
	<?php return; ?>
<?php endif; ?>

<section class="jmrs-portal-section" aria-labelledby="jmrs-import-heading">
	<h2 class="jmrs-portal-section__title" id="jmrs-import-heading"><?php echo esc_html__( 'Import existing residents from a spreadsheet', 'jm-referral-system' ); ?></h2>

	<?php if ( '' !== $error ) : ?>
		<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert">
			<p id="jmrs-import-error"><?php echo esc_html( $error ); ?></p>
		</div>
	<?php endif; ?>

	<ol class="jmrs-er-intro jmrs-import-steps">
		<li>
			<?php echo esc_html__( 'Download the template:', 'jm-referral-system' ); ?>
			<a href="<?php echo esc_url( $template_xlsx ); ?>"><?php echo esc_html__( 'Excel template (.xlsx)', 'jm-referral-system' ); ?></a>
			<?php echo esc_html__( 'or', 'jm-referral-system' ); ?>
			<a href="<?php echo esc_url( $template_csv ); ?>"><?php echo esc_html__( 'CSV template', 'jm-referral-system' ); ?></a>.
		</li>
		<li><?php echo esc_html__( 'Add one resident per row. Type property and bedroom names exactly as they appear under Homes.', 'jm-referral-system' ); ?></li>
		<li><?php echo esc_html__( 'Upload it here. Every row is checked and shown to you. Nothing is saved until you confirm.', 'jm-referral-system' ); ?></li>
	</ol>

	<p class="jmrs-portal-muted">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: maximum rows, 2: maximum size */
				__( 'Up to %1$d residents and %2$s per file. Excel (.xlsx) or CSV. Only the sheet named "Residents" (or the first sheet) is read. The file is read and then discarded; it is not stored.', 'jm-referral-system' ),
				$max_rows,
				$max_size
			)
		);
		?>
	</p>

	<?php $upload_form( __( 'Upload and Check', 'jm-referral-system' ) ); ?>
</section>

<section class="jmrs-portal-section" aria-labelledby="jmrs-import-columns-heading">
	<h2 class="jmrs-portal-section__title" id="jmrs-import-columns-heading"><?php echo esc_html__( 'Spreadsheet columns', 'jm-referral-system' ); ?></h2>
	<p class="jmrs-portal-muted"><?php echo esc_html__( 'Dates can be typed as day/month/year (01/03/2021) or entered as Excel dates.', 'jm-referral-system' ); ?></p>
	<div class="jmrs-portal-table-wrap">
		<table class="jmrs-portal-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Column', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Required', 'jm-referral-system' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $columns as $column ) : ?>
					<tr>
						<td><?php echo esc_html( (string) ( $column['heading'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $column['required'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $column['help'] ?? '' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
