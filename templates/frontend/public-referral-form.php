<?php
/**
 * Public referral intake form.
 *
 * @package JMReferral
 *
 * @var bool $enabled
 * @var array<string, mixed> $settings
 * @var array<string, string> $values
 * @var array<string, string> $errors
 * @var bool $focus_errors
 * @var array<int, array<string, mixed>> $service_types
 * @var array<string, string> $referrer_types
 * @var array<string, string> $contact_methods
 * @var int $form_started
 * @var string $nonce_action
 * @var string $nonce_field
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enabled         = ! empty( $enabled );
$settings        = is_array( $settings ?? null ) ? $settings : array();
$values          = is_array( $values ?? null ) ? $values : array();
$errors          = is_array( $errors ?? null ) ? $errors : array();
$focus_errors    = ! empty( $focus_errors );
$service_types   = is_array( $service_types ?? null ) ? $service_types : array();
$referrer_types  = is_array( $referrer_types ?? null ) ? $referrer_types : array();
$contact_methods = is_array( $contact_methods ?? null ) ? $contact_methods : array();
$form_started    = isset( $form_started ) ? absint( $form_started ) : time();
$nonce_action    = isset( $nonce_action ) ? (string) $nonce_action : 'jmrs_public_referral_submit';
$nonce_field     = isset( $nonce_field ) ? (string) $nonce_field : 'jmrs_public_referral_nonce';

$privacy_url   = (string) ( $settings['privacy_notice_url'] ?? '' );
$allow_uploads = ! empty( $settings['allow_uploads'] );
$max_uploads   = absint( $settings['max_upload_count'] ?? 3 );
$max_mb        = absint( $settings['max_upload_size_mb'] ?? 10 );

$field_invalid = static function ( string $key ) use ( $errors ): bool {
	return isset( $errors[ $key ] );
};

$aria_invalid = static function ( string $key ) use ( $field_invalid ): string {
	return $field_invalid( $key ) ? 'true' : 'false';
};

$error_id = static function ( string $key ): string {
	return 'jmrs-err-' . $key;
};
?>
<div class="jmrs-public-referral"<?php echo $focus_errors ? ' data-jmrs-focus-errors="1"' : ''; ?>>
	<header class="jmrs-public-referral__header">
		<h2 class="jmrs-public-referral__title"><?php echo esc_html__( 'Make a Referral', 'jm-referral-system' ); ?></h2>
		<p class="jmrs-public-referral__intro">
			<?php echo esc_html__( 'Use this form to refer yourself or someone else to JM Healthcare. Fields marked with an asterisk (*) are required.', 'jm-referral-system' ); ?>
		</p>
	</header>

	<?php if ( ! $enabled ) : ?>
		<div class="jmrs-public-referral__notice jmrs-public-referral__notice--info" role="status">
			<p><?php echo esc_html__( 'Online referrals are not available at the moment. Please contact JM Healthcare directly.', 'jm-referral-system' ); ?></p>
		</div>
	<?php else : ?>

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
			data-jmrs-busy-label="<?php echo esc_attr__( 'Submitting…', 'jm-referral-system' ); ?>"
		>
			<?php wp_nonce_field( $nonce_action, $nonce_field ); ?>
			<input type="hidden" name="jmrs_form_started" value="<?php echo esc_attr( (string) $form_started ); ?>" />

			<?php // Honeypot — hidden from genuine users. ?>
			<div class="jmrs-public-referral__hp" aria-hidden="true">
				<label for="jmrs_website"><?php echo esc_html__( 'Website', 'jm-referral-system' ); ?></label>
				<input type="text" name="jmrs_website" id="jmrs_website" value="" tabindex="-1" autocomplete="off" />
			</div>

			<fieldset class="jmrs-public-referral__section">
				<legend><?php echo esc_html__( 'About the referrer', 'jm-referral-system' ); ?></legend>

				<div class="jmrs-public-referral__field" id="jmrs-field-referrer_type">
					<label for="jmrs_referrer_type">
						<?php echo esc_html__( 'Referrer type', 'jm-referral-system' ); ?>
						<span class="jmrs-public-referral__req" aria-hidden="true">*</span>
					</label>
					<select
						name="jmrs_referrer_type"
						id="jmrs_referrer_type"
						required
						aria-required="true"
						aria-invalid="<?php echo esc_attr( $aria_invalid( 'referrer_type' ) ); ?>"
						<?php echo $field_invalid( 'referrer_type' ) ? ' aria-describedby="' . esc_attr( $error_id( 'referrer_type' ) ) . '"' : ''; ?>
					>
						<option value=""><?php echo esc_html__( 'Select…', 'jm-referral-system' ); ?></option>
						<?php foreach ( $referrer_types as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $values['referrer_type'] ?? '', $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php if ( $field_invalid( 'referrer_type' ) ) : ?>
						<p class="jmrs-public-referral__field-error" id="<?php echo esc_attr( $error_id( 'referrer_type' ) ); ?>"><?php echo esc_html( $errors['referrer_type'] ); ?></p>
					<?php endif; ?>
				</div>

				<div class="jmrs-public-referral__field" id="jmrs-field-referrer_name">
					<label for="jmrs_referrer_name">
						<?php echo esc_html__( 'Your name', 'jm-referral-system' ); ?>
						<span class="jmrs-public-referral__req" aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						name="jmrs_referrer_name"
						id="jmrs_referrer_name"
						value="<?php echo esc_attr( $values['referrer_name'] ?? '' ); ?>"
						required
						aria-required="true"
						autocomplete="name"
						aria-invalid="<?php echo esc_attr( $aria_invalid( 'referrer_name' ) ); ?>"
					/>
					<?php if ( $field_invalid( 'referrer_name' ) ) : ?>
						<p class="jmrs-public-referral__field-error" id="<?php echo esc_attr( $error_id( 'referrer_name' ) ); ?>"><?php echo esc_html( $errors['referrer_name'] ); ?></p>
					<?php endif; ?>
				</div>

				<div class="jmrs-public-referral__field">
					<label for="jmrs_referrer_organisation"><?php echo esc_html__( 'Organisation', 'jm-referral-system' ); ?></label>
					<input type="text" name="jmrs_referrer_organisation" id="jmrs_referrer_organisation" value="<?php echo esc_attr( $values['referrer_organisation'] ?? '' ); ?>" autocomplete="organization" />
					<p class="jmrs-public-referral__help"><?php echo esc_html__( 'Optional — hospital, practice, local authority, or care provider name.', 'jm-referral-system' ); ?></p>
				</div>

				<div class="jmrs-public-referral__row">
					<div class="jmrs-public-referral__field" id="jmrs-field-referrer_email">
						<label for="jmrs_referrer_email"><?php echo esc_html__( 'Email', 'jm-referral-system' ); ?></label>
						<input
							type="email"
							name="jmrs_referrer_email"
							id="jmrs_referrer_email"
							value="<?php echo esc_attr( $values['referrer_email'] ?? '' ); ?>"
							autocomplete="email"
							aria-invalid="<?php echo esc_attr( $aria_invalid( 'referrer_email' ) ); ?>"
						/>
						<?php if ( $field_invalid( 'referrer_email' ) ) : ?>
							<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['referrer_email'] ); ?></p>
						<?php endif; ?>
					</div>
					<div class="jmrs-public-referral__field" id="jmrs-field-referrer_phone">
						<label for="jmrs_referrer_phone"><?php echo esc_html__( 'Phone', 'jm-referral-system' ); ?></label>
						<input type="tel" name="jmrs_referrer_phone" id="jmrs_referrer_phone" value="<?php echo esc_attr( $values['referrer_phone'] ?? '' ); ?>" autocomplete="tel" />
					</div>
				</div>
				<?php if ( $field_invalid( 'referrer_contact' ) ) : ?>
					<p class="jmrs-public-referral__field-error" id="jmrs-field-referrer_contact"><?php echo esc_html( $errors['referrer_contact'] ); ?></p>
				<?php else : ?>
					<p class="jmrs-public-referral__help"><?php echo esc_html__( 'Provide at least one contact method (email or phone).', 'jm-referral-system' ); ?></p>
				<?php endif; ?>

				<div class="jmrs-public-referral__field">
					<label for="jmrs_relationship_to_client"><?php echo esc_html__( 'Relationship to client', 'jm-referral-system' ); ?></label>
					<input type="text" name="jmrs_relationship_to_client" id="jmrs_relationship_to_client" value="<?php echo esc_attr( $values['relationship_to_client'] ?? '' ); ?>" />
				</div>
			</fieldset>

			<fieldset class="jmrs-public-referral__section">
				<legend><?php echo esc_html__( 'Client details', 'jm-referral-system' ); ?></legend>

				<div class="jmrs-public-referral__row">
					<div class="jmrs-public-referral__field" id="jmrs-field-client_first_name">
						<label for="jmrs_client_first_name">
							<?php echo esc_html__( 'First name', 'jm-referral-system' ); ?>
							<span class="jmrs-public-referral__req" aria-hidden="true">*</span>
						</label>
						<input type="text" name="jmrs_client_first_name" id="jmrs_client_first_name" value="<?php echo esc_attr( $values['client_first_name'] ?? '' ); ?>" required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'client_first_name' ) ); ?>" />
						<?php if ( $field_invalid( 'client_first_name' ) ) : ?>
							<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['client_first_name'] ); ?></p>
						<?php endif; ?>
					</div>
					<div class="jmrs-public-referral__field" id="jmrs-field-client_last_name">
						<label for="jmrs_client_last_name">
							<?php echo esc_html__( 'Last name', 'jm-referral-system' ); ?>
							<span class="jmrs-public-referral__req" aria-hidden="true">*</span>
						</label>
						<input type="text" name="jmrs_client_last_name" id="jmrs_client_last_name" value="<?php echo esc_attr( $values['client_last_name'] ?? '' ); ?>" required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'client_last_name' ) ); ?>" />
						<?php if ( $field_invalid( 'client_last_name' ) ) : ?>
							<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['client_last_name'] ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<div class="jmrs-public-referral__row">
					<div class="jmrs-public-referral__field" id="jmrs-field-client_email">
						<label for="jmrs_client_email"><?php echo esc_html__( 'Client email', 'jm-referral-system' ); ?></label>
						<input type="email" name="jmrs_client_email" id="jmrs_client_email" value="<?php echo esc_attr( $values['client_email'] ?? '' ); ?>" aria-invalid="<?php echo esc_attr( $aria_invalid( 'client_email' ) ); ?>" />
						<?php if ( $field_invalid( 'client_email' ) ) : ?>
							<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['client_email'] ); ?></p>
						<?php endif; ?>
					</div>
					<div class="jmrs-public-referral__field">
						<label for="jmrs_client_phone"><?php echo esc_html__( 'Client phone', 'jm-referral-system' ); ?></label>
						<input type="tel" name="jmrs_client_phone" id="jmrs_client_phone" value="<?php echo esc_attr( $values['client_phone'] ?? '' ); ?>" />
					</div>
				</div>

				<div class="jmrs-public-referral__field" id="jmrs-field-client_date_of_birth">
					<label for="jmrs_client_date_of_birth"><?php echo esc_html__( 'Date of birth', 'jm-referral-system' ); ?></label>
					<input type="date" name="jmrs_client_date_of_birth" id="jmrs_client_date_of_birth" value="<?php echo esc_attr( $values['client_date_of_birth'] ?? '' ); ?>" aria-invalid="<?php echo esc_attr( $aria_invalid( 'client_date_of_birth' ) ); ?>" />
					<?php if ( $field_invalid( 'client_date_of_birth' ) ) : ?>
						<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['client_date_of_birth'] ); ?></p>
					<?php endif; ?>
				</div>

				<div class="jmrs-public-referral__field">
					<label for="jmrs_address_line_1"><?php echo esc_html__( 'Address line 1', 'jm-referral-system' ); ?></label>
					<input type="text" name="jmrs_address_line_1" id="jmrs_address_line_1" value="<?php echo esc_attr( $values['address_line_1'] ?? '' ); ?>" autocomplete="address-line1" />
				</div>
				<div class="jmrs-public-referral__field">
					<label for="jmrs_address_line_2"><?php echo esc_html__( 'Address line 2', 'jm-referral-system' ); ?></label>
					<input type="text" name="jmrs_address_line_2" id="jmrs_address_line_2" value="<?php echo esc_attr( $values['address_line_2'] ?? '' ); ?>" autocomplete="address-line2" />
				</div>
				<div class="jmrs-public-referral__row">
					<div class="jmrs-public-referral__field">
						<label for="jmrs_city"><?php echo esc_html__( 'Town / City', 'jm-referral-system' ); ?></label>
						<input type="text" name="jmrs_city" id="jmrs_city" value="<?php echo esc_attr( $values['city'] ?? '' ); ?>" autocomplete="address-level2" />
					</div>
					<div class="jmrs-public-referral__field">
						<label for="jmrs_postcode"><?php echo esc_html__( 'Postcode', 'jm-referral-system' ); ?></label>
						<input type="text" name="jmrs_postcode" id="jmrs_postcode" value="<?php echo esc_attr( $values['postcode'] ?? '' ); ?>" autocomplete="postal-code" />
					</div>
				</div>
			</fieldset>

			<fieldset class="jmrs-public-referral__section">
				<legend><?php echo esc_html__( 'Care requirements', 'jm-referral-system' ); ?></legend>

				<div class="jmrs-public-referral__field" id="jmrs-field-service_type_id">
					<label for="jmrs_service_type_id">
						<?php echo esc_html__( 'Service type', 'jm-referral-system' ); ?>
						<span class="jmrs-public-referral__req" aria-hidden="true">*</span>
					</label>
					<select name="jmrs_service_type_id" id="jmrs_service_type_id" required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'service_type_id' ) ); ?>">
						<option value=""><?php echo esc_html__( 'Select…', 'jm-referral-system' ); ?></option>
						<?php foreach ( $service_types as $service_type ) : ?>
							<?php
							$sid   = absint( $service_type['id'] ?? 0 );
							$sname = (string) ( $service_type['name'] ?? '' );
							?>
							<option value="<?php echo esc_attr( (string) $sid ); ?>" <?php selected( (string) ( $values['service_type_id'] ?? '' ), (string) $sid ); ?>>
								<?php echo esc_html( $sname ); ?>
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
						<input type="date" name="jmrs_care_start_date" id="jmrs_care_start_date" value="<?php echo esc_attr( $values['care_start_date'] ?? '' ); ?>" aria-invalid="<?php echo esc_attr( $aria_invalid( 'care_start_date' ) ); ?>" />
						<?php if ( $field_invalid( 'care_start_date' ) ) : ?>
							<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['care_start_date'] ); ?></p>
						<?php endif; ?>
					</div>
					<div class="jmrs-public-referral__field" id="jmrs-field-preferred_contact_method">
						<label for="jmrs_preferred_contact_method"><?php echo esc_html__( 'Preferred contact method', 'jm-referral-system' ); ?></label>
						<select name="jmrs_preferred_contact_method" id="jmrs_preferred_contact_method" aria-invalid="<?php echo esc_attr( $aria_invalid( 'preferred_contact_method' ) ); ?>">
							<option value=""><?php echo esc_html__( 'Select…', 'jm-referral-system' ); ?></option>
							<?php foreach ( $contact_methods as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $values['preferred_contact_method'] ?? '', $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( $field_invalid( 'preferred_contact_method' ) ) : ?>
							<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['preferred_contact_method'] ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<div class="jmrs-public-referral__field" id="jmrs-field-priority">
					<span class="jmrs-public-referral__label">
						<?php echo esc_html__( 'Priority', 'jm-referral-system' ); ?>
						<span class="jmrs-public-referral__req" aria-hidden="true">*</span>
					</span>
					<div class="jmrs-public-referral__radios" role="radiogroup" aria-required="true">
						<label>
							<input type="radio" name="jmrs_priority" value="routine" <?php checked( $values['priority'] ?? 'routine', 'routine' ); ?> />
							<?php echo esc_html__( 'Routine', 'jm-referral-system' ); ?>
						</label>
						<label>
							<input type="radio" name="jmrs_priority" value="urgent" <?php checked( $values['priority'] ?? 'routine', 'urgent' ); ?> />
							<?php echo esc_html__( 'Urgent', 'jm-referral-system' ); ?>
						</label>
					</div>
					<p class="jmrs-public-referral__help"><?php echo esc_html__( 'Choose Urgent only when care is needed as soon as possible.', 'jm-referral-system' ); ?></p>
				</div>

				<div class="jmrs-public-referral__field" id="jmrs-field-care_requirements">
					<label for="jmrs_care_requirements">
						<?php echo esc_html__( 'Care requirements', 'jm-referral-system' ); ?>
						<span class="jmrs-public-referral__req" aria-hidden="true">*</span>
					</label>
					<textarea name="jmrs_care_requirements" id="jmrs_care_requirements" rows="5" required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'care_requirements' ) ); ?>"><?php echo esc_textarea( $values['care_requirements'] ?? '' ); ?></textarea>
					<?php if ( $field_invalid( 'care_requirements' ) ) : ?>
						<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['care_requirements'] ); ?></p>
					<?php endif; ?>
				</div>

				<div class="jmrs-public-referral__field">
					<label for="jmrs_additional_information"><?php echo esc_html__( 'Additional information', 'jm-referral-system' ); ?></label>
					<textarea name="jmrs_additional_information" id="jmrs_additional_information" rows="3"><?php echo esc_textarea( $values['additional_information'] ?? '' ); ?></textarea>
				</div>

				<?php if ( $allow_uploads ) : ?>
					<div class="jmrs-public-referral__field">
						<label for="jmrs_public_documents"><?php echo esc_html__( 'Supporting documents', 'jm-referral-system' ); ?></label>
						<input
							type="file"
							name="jmrs_public_documents[]"
							id="jmrs_public_documents"
							accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
							multiple
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
					</div>
				<?php endif; ?>
			</fieldset>

			<fieldset class="jmrs-public-referral__section">
				<legend><?php echo esc_html__( 'Consent', 'jm-referral-system' ); ?></legend>

				<div class="jmrs-public-referral__field jmrs-public-referral__checkbox" id="jmrs-field-consent_permission">
					<label>
						<input type="checkbox" name="jmrs_consent_permission" value="1" <?php checked( $values['consent_permission'] ?? '', '1' ); ?> required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'consent_permission' ) ); ?>" />
						<span><?php echo esc_html__( 'I confirm I have permission to share this information.', 'jm-referral-system' ); ?> <span class="jmrs-public-referral__req" aria-hidden="true">*</span></span>
					</label>
					<?php if ( $field_invalid( 'consent_permission' ) ) : ?>
						<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['consent_permission'] ); ?></p>
					<?php endif; ?>
				</div>

				<div class="jmrs-public-referral__field jmrs-public-referral__checkbox" id="jmrs-field-consent_assessment">
					<label>
						<input type="checkbox" name="jmrs_consent_assessment" value="1" <?php checked( $values['consent_assessment'] ?? '', '1' ); ?> required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'consent_assessment' ) ); ?>" />
						<span><?php echo esc_html__( 'I understand JM Healthcare will use this information to assess the referral.', 'jm-referral-system' ); ?> <span class="jmrs-public-referral__req" aria-hidden="true">*</span></span>
					</label>
					<?php if ( $field_invalid( 'consent_assessment' ) ) : ?>
						<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['consent_assessment'] ); ?></p>
					<?php endif; ?>
				</div>

				<div class="jmrs-public-referral__field jmrs-public-referral__checkbox" id="jmrs-field-consent_privacy">
					<label>
						<input type="checkbox" name="jmrs_consent_privacy" value="1" <?php checked( $values['consent_privacy'] ?? '', '1' ); ?> required aria-required="true" aria-invalid="<?php echo esc_attr( $aria_invalid( 'consent_privacy' ) ); ?>" />
						<span>
							<?php echo esc_html__( 'I agree to the privacy notice.', 'jm-referral-system' ); ?>
							<span class="jmrs-public-referral__req" aria-hidden="true">*</span>
							<?php if ( '' !== $privacy_url ) : ?>
								<a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html__( 'Read privacy notice', 'jm-referral-system' ); ?>
								</a>
							<?php endif; ?>
						</span>
					</label>
					<?php if ( $field_invalid( 'consent_privacy' ) ) : ?>
						<p class="jmrs-public-referral__field-error"><?php echo esc_html( $errors['consent_privacy'] ); ?></p>
					<?php endif; ?>
				</div>
			</fieldset>

			<div class="jmrs-public-referral__actions">
				<button type="submit" name="jmrs_public_referral_submit" value="1" class="jmrs-public-referral__submit">
					<?php echo esc_html__( 'Submit referral', 'jm-referral-system' ); ?>
				</button>
			</div>
		</form>
	<?php endif; ?>
</div>
