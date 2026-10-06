<?php
/**
 * Staff Portal referral form upload (Phase 5E.1).
 *
 * Uploads one Word or PDF referral form into the Referral Inbox.
 * Does not create a referral.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$error       = (string) ( $error ?? '' );
$form_action = (string) ( $form_action ?? '' );
$list_url    = (string) ( $list_url ?? '' );
$file_field  = (string) ( $file_field ?? 'jmrs_inbox_upload_file' );
$max_display = (string) ( $max_display ?? '' );
$max_bytes   = isset( $max_bytes ) ? absint( $max_bytes ) : 0;
$nonce_field = (string) ( $nonce_field ?? '' );
?>
<p class="jmrs-inbox-detail__back">
	<a href="<?php echo esc_url( $list_url ); ?>"><?php echo esc_html__( 'Back to Referral Inbox', 'jm-referral-system' ); ?></a>
</p>

<section class="jmrs-portal-section jmrs-inbox-upload" aria-labelledby="jmrs-inbox-upload-heading">
	<?php
	$section_title   = __( 'Upload Referral Form', 'jm-referral-system' );
	$section_id      = 'jmrs-inbox-upload-heading';
	$section_badge   = '';
	$section_actions = array();
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
	?>

	<p class="jmrs-inbox__intro">
		<?php echo esc_html__( 'Upload a completed referral form. The details are read from it and filled in for you to check before the referral is created.', 'jm-referral-system' ); ?>
	</p>

	<?php if ( '' !== $error ) : ?>
		<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert">
			<p id="jmrs-inbox-upload-error"><?php echo esc_html( $error ); ?></p>
		</div>
	<?php endif; ?>

	<form
		class="jmrs-portal-form jmrs-inbox-upload__form"
		method="post"
		action="<?php echo esc_url( $form_action ); ?>"
		enctype="multipart/form-data"
	>
		<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field HTML. ?>
		<?php if ( $max_bytes > 0 ) : ?>
			<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( (string) $max_bytes ); ?>" />
		<?php endif; ?>

		<div class="jmrs-portal-field jmrs-portal-field--full">
			<label for="<?php echo esc_attr( $file_field ); ?>">
				<?php echo esc_html__( 'Referral form', 'jm-referral-system' ); ?>
				<span aria-hidden="true">*</span>
				<span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
			</label>
			<input
				type="file"
				name="<?php echo esc_attr( $file_field ); ?>"
				id="<?php echo esc_attr( $file_field ); ?>"
				accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
				required
				aria-required="true"
				aria-describedby="jmrs-inbox-upload-notes<?php echo '' !== $error ? ' jmrs-inbox-upload-error' : ''; ?>"
				<?php echo '' !== $error ? 'aria-invalid="true"' : ''; ?>
			/>
		</div>

		<ul class="jmrs-inbox-upload__notes" id="jmrs-inbox-upload-notes">
			<li>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: maximum file size, e.g. 10 MB */
						__( 'Word (.docx) or PDF, up to %s. One form at a time.', 'jm-referral-system' ),
						$max_display
					)
				);
				?>
			</li>
			<li><?php echo esc_html__( 'Typed forms work best. A scanned or photographed form is stored, but its details cannot be read and must be entered by hand.', 'jm-referral-system' ); ?></li>
			<li><?php echo esc_html__( 'Nothing is created yet. You will review every field and confirm before the referral is saved.', 'jm-referral-system' ); ?></li>
			<li><?php echo esc_html__( 'The form is kept in private storage and added to the referral\'s documents when the referral is created.', 'jm-referral-system' ); ?></li>
		</ul>

		<p class="jmrs-prepare-actions">
			<button type="submit" class="jmrs-button jmrs-button--primary">
				<?php echo esc_html__( 'Upload and Read Form', 'jm-referral-system' ); ?>
			</button>
			<a class="jmrs-button" href="<?php echo esc_url( $list_url ); ?>">
				<?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?>
			</a>
		</p>
	</form>
</section>
