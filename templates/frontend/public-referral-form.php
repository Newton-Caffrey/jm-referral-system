<?php
/**
 * Public referral multi-step wizard (single native POST form).
 *
 * @package JMReferral
 *
 * @var bool $enabled
 * @var array<string, mixed> $settings
 * @var array<string, mixed> $branding
 * @var array<string, string> $values
 * @var array<string, string> $errors
 * @var bool $focus_errors
 * @var int $initial_step
 * @var array<int, array<string, mixed>> $service_types
 * @var array<string, string> $referrer_types
 * @var array<string, string> $contact_methods
 * @var array<int, string> $org_referrer_types
 * @var array<int, string> $personal_referrer_types
 * @var int $form_started
 * @var string $nonce_action
 * @var string $nonce_field
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enabled         = ! empty( $enabled );
$settings        = is_array( $settings ?? null ) ? $settings : array();
$branding        = is_array( $branding ?? null ) ? $branding : \JMReferral\Frontend\PublicBranding::all();
$values          = is_array( $values ?? null ) ? $values : array();
$errors          = is_array( $errors ?? null ) ? $errors : array();
$focus_errors    = ! empty( $focus_errors );
$initial_step    = isset( $initial_step ) ? absint( $initial_step ) : 0;
$service_types   = is_array( $service_types ?? null ) ? $service_types : array();
$referrer_types  = is_array( $referrer_types ?? null ) ? $referrer_types : array();
$contact_methods = is_array( $contact_methods ?? null ) ? $contact_methods : array();
$org_referrer_types = is_array( $org_referrer_types ?? null ) ? $org_referrer_types : array( 'hospital', 'gp', 'social_worker', 'local_authority', 'care_provider', 'other' );
$form_started    = isset( $form_started ) ? absint( $form_started ) : time();
$nonce_action    = isset( $nonce_action ) ? (string) $nonce_action : 'jmrs_public_referral_submit';
$nonce_field     = isset( $nonce_field ) ? (string) $nonce_field : 'jmrs_public_referral_nonce';

$company_name  = (string) ( $branding['company_name'] ?? 'JM Healthcare' );
$heading       = (string) ( $branding['heading'] ?? __( 'Local Authority Referral Form', 'jm-referral-system' ) );
$intro         = (string) ( $branding['intro'] ?? '' );
$primary       = (string) ( $branding['primary_colour'] ?? '#0b5f4b' );
$privacy_url   = (string) ( $settings['privacy_notice_url'] ?? '' );
$allow_uploads = ! empty( $settings['allow_uploads'] );
$max_uploads   = absint( $settings['max_upload_count'] ?? 3 );
$max_mb        = absint( $settings['max_upload_size_mb'] ?? 10 );

$progress_steps = array(
	1 => __( 'About You', 'jm-referral-system' ),
	2 => __( 'Person Needing Care', 'jm-referral-system' ),
	3 => __( 'Care Needs', 'jm-referral-system' ),
	4 => __( 'Documents', 'jm-referral-system' ),
	5 => __( 'Review & Submit', 'jm-referral-system' ),
);

$field_invalid = static function ( string $key ) use ( $errors ): bool {
	return isset( $errors[ $key ] );
};

$aria_invalid = static function ( string $key ) use ( $field_invalid ): string {
	return $field_invalid( $key ) ? 'true' : 'false';
};

$v = static function ( string $key ) use ( $values ): string {
	return (string) ( $values[ $key ] ?? '' );
};

$send_label = sprintf(
	/* translators: %s: company name */
	__( 'Send Referral to %s', 'jm-referral-system' ),
	$company_name
);

$root_style = '--jmrs-primary:' . $primary . ';';
?>
<div
	class="jmrs-public-referral"
	style="<?php echo esc_attr( $root_style ); ?>"
	data-jmrs-initial-step="<?php echo esc_attr( (string) $initial_step ); ?>"
	data-jmrs-company="<?php echo esc_attr( $company_name ); ?>"
	data-jmrs-org-types="<?php echo esc_attr( wp_json_encode( array_values( $org_referrer_types ) ) ); ?>"
	data-jmrs-max-uploads="<?php echo esc_attr( (string) $max_uploads ); ?>"
	data-jmrs-max-upload-mb="<?php echo esc_attr( (string) $max_mb ); ?>"
	<?php echo $focus_errors ? ' data-jmrs-focus-errors="1"' : ''; ?>
>
	<?php if ( ! $enabled ) : ?>
		<div class="jmrs-public-referral__notice jmrs-public-referral__notice--info" role="status">
			<p><?php echo esc_html__( 'Online referrals are not available at the moment. Please contact us directly.', 'jm-referral-system' ); ?></p>
		</div>
	<?php else : ?>

		<div class="jmrs-public-referral__progress jmrs-public-referral__wizard-only" hidden>
			<p class="jmrs-public-referral__progress-compact" id="jmrs-progress-compact" aria-live="polite"></p>
			<div class="jmrs-public-referral__progress-bar" aria-hidden="true">
				<span class="jmrs-public-referral__progress-bar-fill" id="jmrs-progress-bar-fill"></span>
			</div>
			<ol class="jmrs-public-referral__steps" id="jmrs-wizard-progress">
				<?php foreach ( $progress_steps as $num => $label ) : ?>
					<li class="jmrs-public-referral__step-item" data-step="<?php echo esc_attr( (string) $num ); ?>">
						<span class="jmrs-public-referral__step-marker" aria-hidden="true"><?php echo esc_html( (string) $num ); ?></span>
						<span class="jmrs-public-referral__step-label"><?php echo esc_html( $label ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>

		<div id="jmrs-wizard-live" class="jmrs-public-referral__live screen-reader-text" aria-live="polite" aria-atomic="true"></div>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="jmrs-public-referral__notice jmrs-public-referral__notice--error" id="jmrs-public-error-summary" tabindex="-1" role="alert">
				<p><strong><?php echo esc_html__( 'Please fix the following errors:', 'jm-referral-system' ); ?></strong></p>
				<ul>
					<?php foreach ( $errors as $key => $message ) : ?>
						<li>
							<a href="#jmrs-field-<?php echo esc_attr( (string) $key ); ?>">
								<?php echo esc_html( (string) $message ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<form
			class="jmrs-public-referral__form"
			id="jmrs-field-form"
			method="post"
			action=""
			enctype="multipart/form-data"
			novalidate
			data-jmrs-busy-label="<?php echo esc_attr__( 'Sending…', 'jm-referral-system' ); ?>"
		>
			<?php wp_nonce_field( $nonce_action, $nonce_field ); ?>
			<input type="hidden" name="jmrs_form_started" value="<?php echo esc_attr( (string) $form_started ); ?>" />

			<div class="jmrs-public-referral__hp" aria-hidden="true">
				<label for="jmrs_website"><?php echo esc_html__( 'Website', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_website" id="jmrs_website" value="" tabindex="-1" autocomplete="off" />
			</div>

			<?php // Step 0 — Welcome ?>
			<section class="jmrs-public-referral__panel" data-jmrs-step="0" id="jmrs-step-0">
				<h2 class="jmrs-public-referral__title" id="jmrs-step-0-heading" tabindex="-1"><?php echo esc_html( $heading ); ?></h2>
				<div class="jmrs-public-referral__intro">
					<?php echo wp_kses_post( wpautop( esc_html( $intro ) ) ); ?>
				</div>
				<div class="jmrs-public-referral__nav jmrs-public-referral__wizard-only" hidden>
					<button type="button" class="jmrs-public-referral__btn jmrs-public-referral__btn--primary" data-jmrs-start>
						<?php echo esc_html__( 'Start Local Authority Referral', 'jm-referral-system' ); ?>
					</button>
				</div>
			</section>

			<?php // Step 1 — About You ?>
			<section class="jmrs-public-referral__panel" data-jmrs-step="1" id="jmrs-step-1">
				<h2 class="jmrs-public-referral__title" id="jmrs-step-1-heading" tabindex="-1" data-jmrs-heading-personal="<?php echo esc_attr__( 'Tell us about yourself', 'jm-referral-system' ); ?>" data-jmrs-heading-org="<?php echo esc_attr__( 'Tell us about the referring organisation', 'jm-referral-system' ); ?>">
					<?php echo esc_html__( 'Tell us about yourself', 'jm-referral-system' ); ?>
				</h2>
				<fieldset class="jmrs-public-referral__card">
					<legend class="screen-reader-text"><?php echo esc_html__( 'About you', 'jm-referral-system' ); ?></legend>

					<div class="jmrs-public-referral__field" id="jmrs-field-referrer_type">
						<label for="jmrs_referrer_type">
							<?php echo esc_html__( 'Referrer type', 'jm-referral-system' ); ?>
							<span class="jmrs-public-referral__req" aria-hidden="true">*</span>
						</label>
						<select name="jmrs_referrer_type" id="jmrs_referrer_type" required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'referrer_type' ) ); ?>" data-jmrs-referrer-type>
							<option value=""><?php echo esc_html__( 'Select…', 'jm-referral-system' ); ?></option>
							<?php foreach ( $referrer_types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $v( 'referrer_type' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( $field_invalid( 'referrer_type' ) ) : ?>
							<p class="jmrs-public-referral__field-error" data-jmrs-error-for="jmrs_referrer_type"><?php echo esc_html( $errors['referrer_type'] ); ?></p>
						<?php endif; ?>
					</div>

					<div class="jmrs-public-referral__field" id="jmrs-field-referrer_name">
						<label for="jmrs_referrer_name">
							<?php echo esc_html__( 'Your name', 'jm-referral-system' ); ?>
							<span class="jmrs-public-referral__req" aria-hidden="true">*</span>
						</label>
						<input type="text" name="jmrs_referrer_name" id="jmrs_referrer_name" value="<?php echo esc_attr( $v( 'referrer_name' ) ); ?>" required aria-required="true" autocomplete="name" aria-invalid="<?php echo esc_attr( $aria_invalid( 'referrer_name' ) ); ?>" />
						<?php if ( $field_invalid( 'referrer_name' ) ) : ?>
							<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['referrer_name'] ); ?></p>
						<?php endif; ?>
					</div>

					<div class="jmrs-public-referral__field" id="jmrs-field-referrer_organisation" data-jmrs-org-field>
						<label for="jmrs_referrer_organisation"><?php echo esc_html__( 'Organisation', 'jm-referral-system' ); ?></label>
						<input type="text" name="jmrs_referrer_organisation" id="jmrs_referrer_organisation" value="<?php echo esc_attr( $v( 'referrer_organisation' ) ); ?>" autocomplete="organization" />
						<p class="jmrs-public-referral__help"><?php echo esc_html__( 'Hospital, practice, local authority, or care provider name when relevant.', 'jm-referral-system' ); ?></p>
					</div>

					<div class="jmrs-public-referral__row">
						<div class="jmrs-public-referral__field" id="jmrs-field-referrer_email">
							<label for="jmrs_referrer_email"><?php echo esc_html__( 'Email', 'jm-referral-system' ); ?></label>
							<input type="email" name="jmrs_referrer_email" id="jmrs_referrer_email" value="<?php echo esc_attr( $v( 'referrer_email' ) ); ?>" autocomplete="email" aria-invalid="<?php echo esc_attr( $aria_invalid( 'referrer_email' ) ); ?>" />
							<?php if ( $field_invalid( 'referrer_email' ) ) : ?>
								<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['referrer_email'] ); ?></p>
							<?php endif; ?>
						</div>
						<div class="jmrs-public-referral__field" id="jmrs-field-referrer_phone">
							<label for="jmrs_referrer_phone"><?php echo esc_html__( 'Phone', 'jm-referral-system' ); ?></label>
							<input type="tel" name="jmrs_referrer_phone" id="jmrs_referrer_phone" value="<?php echo esc_attr( $v( 'referrer_phone' ) ); ?>" autocomplete="tel" />
						</div>
					</div>
					<p class="jmrs-public-referral__help" id="jmrs-field-referrer_contact"><?php echo esc_html__( 'Provide at least one contact method (email or phone).', 'jm-referral-system' ); ?></p>
					<?php if ( $field_invalid( 'referrer_contact' ) ) : ?>
						<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['referrer_contact'] ); ?></p>
					<?php endif; ?>

					<div class="jmrs-public-referral__field">
						<label for="jmrs_relationship_to_client"><?php echo esc_html__( 'Relationship to client', 'jm-referral-system' ); ?></label>
						<input type="text" name="jmrs_relationship_to_client" id="jmrs_relationship_to_client" value="<?php echo esc_attr( $v( 'relationship_to_client' ) ); ?>" />
					</div>
				</fieldset>
			</section>

			<?php // Step 2 — Person Needing Care ?>
			<section class="jmrs-public-referral__panel" data-jmrs-step="2" id="jmrs-step-2">
				<h2 class="jmrs-public-referral__title" id="jmrs-step-2-heading" tabindex="-1"><?php echo esc_html__( 'Who needs support?', 'jm-referral-system' ); ?></h2>
				<p class="jmrs-public-referral__help"><?php echo esc_html__( 'If you do not know every detail, provide what you can.', 'jm-referral-system' ); ?></p>
				<fieldset class="jmrs-public-referral__card">
					<legend class="screen-reader-text"><?php echo esc_html__( 'Person needing care', 'jm-referral-system' ); ?></legend>
					<div class="jmrs-public-referral__row">
						<div class="jmrs-public-referral__field" id="jmrs-field-client_first_name">
							<label for="jmrs_client_first_name"><?php echo esc_html__( 'First name', 'jm-referral-system' ); ?> <span class="jmrs-public-referral__req" aria-hidden="true">*</span></label>
							<input type="text" name="jmrs_client_first_name" id="jmrs_client_first_name" value="<?php echo esc_attr( $v( 'client_first_name' ) ); ?>" required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'client_first_name' ) ); ?>" />
							<?php if ( $field_invalid( 'client_first_name' ) ) : ?>
								<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['client_first_name'] ); ?></p>
							<?php endif; ?>
						</div>
						<div class="jmrs-public-referral__field" id="jmrs-field-client_last_name">
							<label for="jmrs_client_last_name"><?php echo esc_html__( 'Last name', 'jm-referral-system' ); ?> <span class="jmrs-public-referral__req" aria-hidden="true">*</span></label>
							<input type="text" name="jmrs_client_last_name" id="jmrs_client_last_name" value="<?php echo esc_attr( $v( 'client_last_name' ) ); ?>" required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'client_last_name' ) ); ?>" />
							<?php if ( $field_invalid( 'client_last_name' ) ) : ?>
								<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['client_last_name'] ); ?></p>
							<?php endif; ?>
						</div>
					</div>
					<div class="jmrs-public-referral__row">
						<div class="jmrs-public-referral__field" id="jmrs-field-client_email">
							<label for="jmrs_client_email"><?php echo esc_html__( 'Client email', 'jm-referral-system' ); ?></label>
							<input type="email" name="jmrs_client_email" id="jmrs_client_email" value="<?php echo esc_attr( $v( 'client_email' ) ); ?>" aria-invalid="<?php echo esc_attr( $aria_invalid( 'client_email' ) ); ?>" />
							<?php if ( $field_invalid( 'client_email' ) ) : ?>
								<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['client_email'] ); ?></p>
							<?php endif; ?>
						</div>
						<div class="jmrs-public-referral__field">
							<label for="jmrs_client_phone"><?php echo esc_html__( 'Client phone', 'jm-referral-system' ); ?></label>
							<input type="tel" name="jmrs_client_phone" id="jmrs_client_phone" value="<?php echo esc_attr( $v( 'client_phone' ) ); ?>" />
						</div>
					</div>
					<div class="jmrs-public-referral__field" id="jmrs-field-client_date_of_birth">
						<label for="jmrs_client_date_of_birth"><?php echo esc_html__( 'Date of birth', 'jm-referral-system' ); ?></label>
						<input type="date" name="jmrs_client_date_of_birth" id="jmrs_client_date_of_birth" value="<?php echo esc_attr( $v( 'client_date_of_birth' ) ); ?>" aria-invalid="<?php echo esc_attr( $aria_invalid( 'client_date_of_birth' ) ); ?>" />
						<?php if ( $field_invalid( 'client_date_of_birth' ) ) : ?>
							<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['client_date_of_birth'] ); ?></p>
						<?php endif; ?>
					</div>
					<div class="jmrs-public-referral__field">
						<label for="jmrs_address_line_1"><?php echo esc_html__( 'Address line 1', 'jm-referral-system' ); ?></label>
						<input type="text" name="jmrs_address_line_1" id="jmrs_address_line_1" value="<?php echo esc_attr( $v( 'address_line_1' ) ); ?>" autocomplete="address-line1" />
					</div>
					<div class="jmrs-public-referral__field">
						<label for="jmrs_address_line_2"><?php echo esc_html__( 'Address line 2', 'jm-referral-system' ); ?></label>
						<input type="text" name="jmrs_address_line_2" id="jmrs_address_line_2" value="<?php echo esc_attr( $v( 'address_line_2' ) ); ?>" autocomplete="address-line2" />
					</div>
					<div class="jmrs-public-referral__row">
						<div class="jmrs-public-referral__field">
							<label for="jmrs_city"><?php echo esc_html__( 'Town / City', 'jm-referral-system' ); ?></label>
							<input type="text" name="jmrs_city" id="jmrs_city" value="<?php echo esc_attr( $v( 'city' ) ); ?>" autocomplete="address-level2" />
						</div>
						<div class="jmrs-public-referral__field">
							<label for="jmrs_postcode"><?php echo esc_html__( 'Postcode', 'jm-referral-system' ); ?></label>
							<input type="text" name="jmrs_postcode" id="jmrs_postcode" value="<?php echo esc_attr( $v( 'postcode' ) ); ?>" autocomplete="postal-code" />
						</div>
					</div>
				</fieldset>
			</section>

			<?php // Step 3 — Care Needs ?>
			<section class="jmrs-public-referral__panel" data-jmrs-step="3" id="jmrs-step-3">
				<h2 class="jmrs-public-referral__title" id="jmrs-step-3-heading" tabindex="-1"><?php echo esc_html__( 'How can we help?', 'jm-referral-system' ); ?></h2>
				<fieldset class="jmrs-public-referral__card">
					<legend class="screen-reader-text"><?php echo esc_html__( 'Care needs', 'jm-referral-system' ); ?></legend>
					<div class="jmrs-public-referral__field" id="jmrs-field-service_type_id">
						<label for="jmrs_service_type_id"><?php echo esc_html__( 'Service type', 'jm-referral-system' ); ?> <span class="jmrs-public-referral__req" aria-hidden="true">*</span></label>
						<select name="jmrs_service_type_id" id="jmrs_service_type_id" required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'service_type_id' ) ); ?>">
							<option value=""><?php echo esc_html__( 'Select…', 'jm-referral-system' ); ?></option>
							<?php foreach ( $service_types as $service_type ) : ?>
								<?php $sid = absint( $service_type['id'] ?? 0 ); ?>
								<option value="<?php echo esc_attr( (string) $sid ); ?>" <?php selected( $v( 'service_type_id' ), (string) $sid ); ?>>
									<?php echo esc_html( (string) ( $service_type['name'] ?? '' ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( $field_invalid( 'service_type_id' ) ) : ?>
							<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['service_type_id'] ); ?></p>
						<?php endif; ?>
					</div>
					<div class="jmrs-public-referral__row">
						<div class="jmrs-public-referral__field" id="jmrs-field-care_start_date">
							<label for="jmrs_care_start_date"><?php echo esc_html__( 'Preferred care start date', 'jm-referral-system' ); ?></label>
							<input type="date" name="jmrs_care_start_date" id="jmrs_care_start_date" value="<?php echo esc_attr( $v( 'care_start_date' ) ); ?>" aria-invalid="<?php echo esc_attr( $aria_invalid( 'care_start_date' ) ); ?>" />
							<?php if ( $field_invalid( 'care_start_date' ) ) : ?>
								<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['care_start_date'] ); ?></p>
							<?php endif; ?>
						</div>
						<div class="jmrs-public-referral__field" id="jmrs-field-preferred_contact_method">
							<label for="jmrs_preferred_contact_method"><?php echo esc_html__( 'Preferred contact method', 'jm-referral-system' ); ?></label>
							<select name="jmrs_preferred_contact_method" id="jmrs_preferred_contact_method" aria-invalid="<?php echo esc_attr( $aria_invalid( 'preferred_contact_method' ) ); ?>">
								<option value=""><?php echo esc_html__( 'Select…', 'jm-referral-system' ); ?></option>
								<?php foreach ( $contact_methods as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $v( 'preferred_contact_method' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php if ( $field_invalid( 'preferred_contact_method' ) ) : ?>
								<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['preferred_contact_method'] ); ?></p>
							<?php endif; ?>
						</div>
					</div>
					<div class="jmrs-public-referral__field" id="jmrs-field-priority">
						<span class="jmrs-public-referral__label"><?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?> <span class="jmrs-public-referral__req" aria-hidden="true">*</span></span>
						<div class="jmrs-public-referral__radios" role="radiogroup" aria-required="true">
							<label><input type="radio" name="jmrs_priority" value="routine" <?php checked( $v( 'priority' ) ?: 'routine', 'routine' ); ?> /> <?php echo esc_html__( 'Routine', 'jm-referral-system' ); ?></label>
							<label><input type="radio" name="jmrs_priority" value="urgent" <?php checked( $v( 'priority' ), 'urgent' ); ?> /> <?php echo esc_html__( 'Urgent', 'jm-referral-system' ); ?></label>
						</div>
						<p class="jmrs-public-referral__help"><?php echo esc_html__( 'Choose Urgent only when care is needed as soon as possible.', 'jm-referral-system' ); ?></p>
					</div>
					<div class="jmrs-public-referral__field" id="jmrs-field-care_requirements">
						<label for="jmrs_care_requirements"><?php echo esc_html__( 'Care requirements', 'jm-referral-system' ); ?> <span class="jmrs-public-referral__req" aria-hidden="true">*</span></label>
						<textarea name="jmrs_care_requirements" id="jmrs_care_requirements" rows="5" required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'care_requirements' ) ); ?>"><?php echo esc_textarea( $v( 'care_requirements' ) ); ?></textarea>
						<p class="jmrs-public-referral__help"><?php echo esc_html__( 'Describe the support needed in your own words. Everyday language is fine.', 'jm-referral-system' ); ?></p>
						<?php if ( $field_invalid( 'care_requirements' ) ) : ?>
							<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['care_requirements'] ); ?></p>
						<?php endif; ?>
					</div>
					<div class="jmrs-public-referral__field">
						<label for="jmrs_additional_information"><?php echo esc_html__( 'Additional information', 'jm-referral-system' ); ?></label>
						<textarea name="jmrs_additional_information" id="jmrs_additional_information" rows="3"><?php echo esc_textarea( $v( 'additional_information' ) ); ?></textarea>
					</div>
				</fieldset>
			</section>

			<?php // Step 4 — Documents ?>
			<section class="jmrs-public-referral__panel" data-jmrs-step="4" id="jmrs-step-4">
				<h2 class="jmrs-public-referral__title" id="jmrs-step-4-heading" tabindex="-1"><?php echo esc_html__( 'Supporting Documents', 'jm-referral-system' ); ?></h2>
				<div class="jmrs-public-referral__card">
					<p><?php echo esc_html__( 'If you have hospital discharge papers, assessments or other helpful documents, you can upload them now.', 'jm-referral-system' ); ?></p>
					<p class="jmrs-public-referral__help"><?php echo esc_html__( 'This step is optional. Do not worry if you do not have any documents.', 'jm-referral-system' ); ?></p>
					<?php if ( $allow_uploads ) : ?>
						<div class="jmrs-public-referral__field" id="jmrs-field-documents">
							<label for="jmrs_public_documents"><?php echo esc_html__( 'Upload files', 'jm-referral-system' ); ?></label>
							<input
								type="file"
								name="jmrs_public_documents[]"
								id="jmrs_public_documents"
								accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
								multiple
								data-jmrs-files
							/>
							<p class="jmrs-public-referral__help">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: max file count, 2: max MB */
										__( 'Optional. Up to %1$d files (PDF, DOC, DOCX, JPG, PNG). Maximum %2$d MB each.', 'jm-referral-system' ),
										$max_uploads,
										$max_mb
									)
								);
								?>
							</p>
							<ul class="jmrs-public-referral__file-summary" id="jmrs-file-summary" aria-live="polite"></ul>
						</div>
					<?php else : ?>
						<p class="jmrs-public-referral__help"><?php echo esc_html__( 'Document uploads are not enabled for this form. You can continue without them.', 'jm-referral-system' ); ?></p>
					<?php endif; ?>
				</div>
			</section>

			<?php // Step 5 — Review & Submit ?>
			<section class="jmrs-public-referral__panel" data-jmrs-step="5" id="jmrs-step-5">
				<h2 class="jmrs-public-referral__title" id="jmrs-step-5-heading" tabindex="-1"><?php echo esc_html__( 'Review & Submit', 'jm-referral-system' ); ?></h2>
				<p class="jmrs-public-referral__help"><?php echo esc_html__( 'Please check your details before sending. You can edit any section.', 'jm-referral-system' ); ?></p>

				<div class="jmrs-public-referral__summary-grid" data-jmrs-review>
					<article class="jmrs-public-referral__summary-card" data-summary="about">
						<header>
							<h3><?php echo esc_html__( 'About You', 'jm-referral-system' ); ?></h3>
							<button type="button" class="jmrs-public-referral__link-btn jmrs-public-referral__wizard-only" hidden data-jmrs-edit="1" aria-label="<?php echo esc_attr__( 'Edit about you', 'jm-referral-system' ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></button>
						</header>
						<dl class="jmrs-public-referral__summary-dl" id="jmrs-summary-about"></dl>
					</article>
					<article class="jmrs-public-referral__summary-card" data-summary="person">
						<header>
							<h3><?php echo esc_html__( 'Person Needing Care', 'jm-referral-system' ); ?></h3>
							<button type="button" class="jmrs-public-referral__link-btn jmrs-public-referral__wizard-only" hidden data-jmrs-edit="2" aria-label="<?php echo esc_attr__( 'Edit person needing care', 'jm-referral-system' ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></button>
						</header>
						<dl class="jmrs-public-referral__summary-dl" id="jmrs-summary-person"></dl>
					</article>
					<article class="jmrs-public-referral__summary-card" data-summary="care">
						<header>
							<h3><?php echo esc_html__( 'Care Needs', 'jm-referral-system' ); ?></h3>
							<button type="button" class="jmrs-public-referral__link-btn jmrs-public-referral__wizard-only" hidden data-jmrs-edit="3" aria-label="<?php echo esc_attr__( 'Edit care needs', 'jm-referral-system' ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></button>
						</header>
						<dl class="jmrs-public-referral__summary-dl" id="jmrs-summary-care"></dl>
					</article>
					<article class="jmrs-public-referral__summary-card" data-summary="docs">
						<header>
							<h3><?php echo esc_html__( 'Documents', 'jm-referral-system' ); ?></h3>
							<button type="button" class="jmrs-public-referral__link-btn jmrs-public-referral__wizard-only" hidden data-jmrs-edit="4" aria-label="<?php echo esc_attr__( 'Edit documents', 'jm-referral-system' ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></button>
						</header>
						<div id="jmrs-summary-docs"></div>
					</article>
				</div>

				<fieldset class="jmrs-public-referral__card" id="jmrs-consent-block">
					<legend><?php echo esc_html__( 'Consent', 'jm-referral-system' ); ?></legend>
					<div class="jmrs-public-referral__field jmrs-public-referral__checkbox" id="jmrs-field-consent_permission">
						<label>
							<input type="checkbox" name="jmrs_consent_permission" value="1" <?php checked( $v( 'consent_permission' ), '1' ); ?> required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'consent_permission' ) ); ?>" />
							<span><?php echo esc_html__( 'I confirm I have permission to share this information.', 'jm-referral-system' ); ?> <span class="jmrs-public-referral__req" aria-hidden="true">*</span></span>
						</label>
						<?php if ( $field_invalid( 'consent_permission' ) ) : ?>
							<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['consent_permission'] ); ?></p>
						<?php endif; ?>
					</div>
					<div class="jmrs-public-referral__field jmrs-public-referral__checkbox" id="jmrs-field-consent_assessment">
						<label>
							<input type="checkbox" name="jmrs_consent_assessment" value="1" <?php checked( $v( 'consent_assessment' ), '1' ); ?> required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'consent_assessment' ) ); ?>" />
							<span><?php echo esc_html__( 'I understand this information will be used to assess the referral.', 'jm-referral-system' ); ?> <span class="jmrs-public-referral__req" aria-hidden="true">*</span></span>
						</label>
						<?php if ( $field_invalid( 'consent_assessment' ) ) : ?>
							<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['consent_assessment'] ); ?></p>
						<?php endif; ?>
					</div>
					<div class="jmrs-public-referral__field jmrs-public-referral__checkbox" id="jmrs-field-consent_privacy">
						<label>
							<input type="checkbox" name="jmrs_consent_privacy" value="1" <?php checked( $v( 'consent_privacy' ), '1' ); ?> required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'consent_privacy' ) ); ?>" />
							<span>
								<?php echo esc_html__( 'I agree to the privacy notice.', 'jm-referral-system' ); ?>
								<span class="jmrs-public-referral__req" aria-hidden="true">*</span>
								<?php if ( '' !== $privacy_url ) : ?>
									<a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Read privacy notice', 'jm-referral-system' ); ?></a>
								<?php endif; ?>
							</span>
						</label>
						<?php if ( $field_invalid( 'consent_privacy' ) ) : ?>
							<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['consent_privacy'] ); ?></p>
						<?php endif; ?>
					</div>
				</fieldset>

				<div class="jmrs-public-referral__actions jmrs-public-referral__nojs-submit">
					<button type="submit" name="jmrs_public_referral_submit" value="1" class="jmrs-public-referral__btn jmrs-public-referral__btn--primary jmrs-public-referral__submit">
						<?php echo esc_html( $send_label ); ?>
					</button>
				</div>
			</section>

			<div class="jmrs-public-referral__nav jmrs-public-referral__wizard-only" id="jmrs-wizard-nav" hidden>
				<button type="button" class="jmrs-public-referral__btn jmrs-public-referral__btn--secondary" data-jmrs-back>
					<?php echo esc_html__( 'Back', 'jm-referral-system' ); ?>
				</button>
				<button type="button" class="jmrs-public-referral__btn jmrs-public-referral__btn--primary" data-jmrs-continue>
					<?php echo esc_html__( 'Continue', 'jm-referral-system' ); ?>
				</button>
				<button type="submit" name="jmrs_public_referral_submit" value="1" class="jmrs-public-referral__btn jmrs-public-referral__btn--primary jmrs-public-referral__submit" data-jmrs-final-submit hidden>
					<?php echo esc_html( $send_label ); ?>
				</button>
			</div>
		</form>
	<?php endif; ?>
</div>
