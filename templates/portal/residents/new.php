<?php
/**
 * Add one existing resident (Phase 5F.1).
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$values           = is_array( $values ?? null ) ? $values : array();
$errors           = is_array( $errors ?? null ) ? $errors : array();
$form_action      = (string) ( $form_action ?? '' );
$cancel_url       = (string) ( $cancel_url ?? '' );
$bedroom_groups   = is_array( $bedroom_groups ?? null ) ? $bedroom_groups : array();
$supported_living = ! empty( $supported_living );
$services         = is_array( $services ?? null ) ? $services : array();
$owners           = is_array( $owners ?? null ) ? $owners : array();
$authorities      = is_array( $authorities ?? null ) ? $authorities : array();
$funding_types    = is_array( $funding_types ?? null ) ? $funding_types : array();
$care_settings    = is_array( $care_settings ?? null ) ? $care_settings : array();

$val = static function ( string $key ) use ( $values ): string {
	return (string) ( $values[ $key ] ?? '' );
};

$err_id = static function ( string $key ): string {
	return 'jmrs-er-error-' . str_replace( '_', '-', $key );
};

$field_error = static function ( string $key ) use ( $errors, $err_id ): void {
	if ( isset( $errors[ $key ] ) ) {
		echo '<p class="jmrs-portal-field-error" id="' . esc_attr( $err_id( $key ) ) . '">' . esc_html( (string) $errors[ $key ] ) . '</p>';
	}
};

$aria = static function ( string $key, string $hint_id = '' ) use ( $errors, $err_id ): string {
	$ids = array();
	if ( '' !== $hint_id ) {
		$ids[] = $hint_id;
	}
	if ( isset( $errors[ $key ] ) ) {
		$ids[] = $err_id( $key );
	}
	$out = array() !== $ids ? ' aria-describedby="' . esc_attr( implode( ' ', $ids ) ) . '"' : '';
	if ( isset( $errors[ $key ] ) ) {
		$out .= ' aria-invalid="true"';
	}

	return $out;
};

$text_field = static function ( string $key, string $label, bool $required = false, string $type = 'text', string $hint = '', string $extra = '' ) use ( $val, $aria, $field_error ): void {
	$id      = 'jmrs_er_' . $key;
	$hint_id = '' !== $hint ? $id . '_hint' : '';
	?>
	<div class="jmrs-portal-field">
		<label for="<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( $label ); ?>
			<?php if ( $required ) : ?>
				<span aria-hidden="true">*</span><span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
			<?php endif; ?>
		</label>
		<?php if ( '' !== $hint ) : ?>
			<p class="jmrs-portal-field__hint" id="<?php echo esc_attr( $hint_id ); ?>"><?php echo esc_html( $hint ); ?></p>
		<?php endif; ?>
		<input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $val( $key ) ); ?>"<?php echo $required ? ' required aria-required="true"' : ''; ?><?php echo $aria( $key, $hint_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr. ?> <?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attributes. ?> />
		<?php $field_error( $key ); ?>
	</div>
	<?php
};

$today = gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) );
?>
<?php if ( ! empty( $errors ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert" tabindex="-1" id="jmrs-er-errors">
		<p><strong><?php echo esc_html__( 'Nothing has been saved. Please correct the following:', 'jm-referral-system' ); ?></strong></p>
		<ul>
			<?php foreach ( $errors as $message ) : ?>
				<li><?php echo esc_html( (string) $message ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<p class="jmrs-er-intro">
	<?php echo esc_html__( 'For a person who already receives care from you. They are added at Care Started with the dates you enter, without referral, assessment, costing or Local Authority decision steps.', 'jm-referral-system' ); ?>
</p>

<form class="jmrs-portal-form jmrs-er-form" method="post" action="<?php echo esc_url( $form_action ); ?>" novalidate>
	<?php wp_nonce_field( 'jmrs_register_existing_resident', 'jmrs_existing_resident_nonce' ); ?>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'About the person', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<?php $text_field( 'first_name', __( 'First name', 'jm-referral-system' ), true, 'text', '', 'autocomplete="off" maxlength="100"' ); ?>
			<?php $text_field( 'last_name', __( 'Last name', 'jm-referral-system' ), true, 'text', '', 'autocomplete="off" maxlength="100"' ); ?>
			<?php $text_field( 'date_of_birth', __( 'Date of birth', 'jm-referral-system' ), true, 'date', '', 'max="' . esc_attr( $today ) . '"' ); ?>
			<?php $text_field( 'phone', __( 'Phone', 'jm-referral-system' ), false, 'tel', '', 'maxlength="50"' ); ?>
			<?php $text_field( 'email', __( 'Email', 'jm-referral-system' ), false, 'email', '', 'maxlength="190"' ); ?>
		</div>
	</section>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Dates', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<?php $text_field( 'admission_date', __( 'Admission date', 'jm-referral-system' ), true, 'date', __( 'The day they joined your service.', 'jm-referral-system' ), 'max="' . esc_attr( $today ) . '"' ); ?>
			<?php $text_field( 'care_start_date', __( 'Care start date', 'jm-referral-system' ), true, 'date', __( 'The day your care and support began.', 'jm-referral-system' ), 'max="' . esc_attr( $today ) . '"' ); ?>
		</div>
	</section>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Where they receive care', 'jm-referral-system' ); ?></h2>

		<fieldset class="jmrs-portal-field jmrs-portal-field--full"<?php echo $aria( 'care_setting' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr. ?>>
			<legend class="jmrs-portal-field__label">
				<?php echo esc_html__( 'Care setting', 'jm-referral-system' ); ?>
				<span aria-hidden="true">*</span><span class="screen-reader-text"><?php echo esc_html__( 'required', 'jm-referral-system' ); ?></span>
			</legend>
			<?php foreach ( $care_settings as $setting_key => $setting_label ) : ?>
				<?php if ( 'supported_living' === $setting_key && ! $supported_living ) { continue; } ?>
				<label class="jmrs-portal-field--checkbox">
					<input type="radio" name="jmrs_er_care_setting" value="<?php echo esc_attr( (string) $setting_key ); ?>" <?php checked( $val( 'care_setting' ), (string) $setting_key ); ?> />
					<?php echo esc_html( (string) $setting_label ); ?>
				</label>
			<?php endforeach; ?>
			<?php $field_error( 'care_setting' ); ?>
		</fieldset>

		<?php if ( $supported_living ) : ?>
			<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Supported Living', 'jm-referral-system' ); ?></h3>
			<div class="jmrs-portal-form-grid">
				<div class="jmrs-portal-field">
					<label for="jmrs_er_bedroom_id"><?php echo esc_html__( 'Property and bedroom', 'jm-referral-system' ); ?></label>
					<p class="jmrs-portal-field__hint" id="jmrs_er_bedroom_id_hint"><?php echo esc_html__( 'Where they live now. Only free bedrooms are listed.', 'jm-referral-system' ); ?></p>
					<select name="jmrs_er_bedroom_id" id="jmrs_er_bedroom_id"<?php echo $aria( 'bedroom_id', 'jmrs_er_bedroom_id_hint' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr. ?>>
						<option value=""><?php echo esc_html__( '— Choose a bedroom —', 'jm-referral-system' ); ?></option>
						<?php foreach ( $bedroom_groups as $group ) : ?>
							<optgroup label="<?php echo esc_attr( (string) ( $group['home'] ?? '' ) ); ?>">
								<?php foreach ( (array) ( $group['bedrooms'] ?? array() ) as $room ) : ?>
									<option value="<?php echo esc_attr( (string) absint( $room['id'] ?? 0 ) ); ?>" <?php selected( $val( 'bedroom_id' ), (string) absint( $room['id'] ?? 0 ) ); ?>>
										<?php echo esc_html( (string) ( $group['home'] ?? '' ) . ' — ' . (string) ( $room['label'] ?? '' ) ); ?>
									</option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
					<?php if ( empty( $bedroom_groups ) ) : ?>
						<p class="jmrs-portal-muted"><?php echo esc_html__( 'There are no free bedrooms. Add the property and its bedrooms under Homes first.', 'jm-referral-system' ); ?></p>
					<?php endif; ?>
					<?php $field_error( 'bedroom_id' ); ?>
				</div>
				<?php $text_field( 'move_in_date', __( 'Moved into this bedroom', 'jm-referral-system' ), false, 'date', __( 'Only if different from the admission date, for example after a room move.', 'jm-referral-system' ), 'max="' . esc_attr( $today ) . '"' ); ?>
			</div>
		<?php endif; ?>

		<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Own Home address', 'jm-referral-system' ); ?></h3>
		<p class="jmrs-portal-muted"><?php echo esc_html__( 'Required when care is provided in their own home. Optional for Supported Living.', 'jm-referral-system' ); ?></p>
		<div class="jmrs-portal-form-grid">
			<?php $text_field( 'address_line_1', __( 'Address line 1', 'jm-referral-system' ), false, 'text', '', 'maxlength="255"' ); ?>
			<?php $text_field( 'address_line_2', __( 'Address line 2', 'jm-referral-system' ), false, 'text', '', 'maxlength="255"' ); ?>
			<?php $text_field( 'city', __( 'Town or city', 'jm-referral-system' ), false, 'text', '', 'maxlength="150"' ); ?>
			<?php $text_field( 'postcode', __( 'Postcode', 'jm-referral-system' ), false, 'text', '', 'maxlength="30"' ); ?>
		</div>
	</section>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Funding and support package', 'jm-referral-system' ); ?></h2>
		<p class="jmrs-portal-muted"><?php echo esc_html__( 'Optional. Leave blank if you will add it later.', 'jm-referral-system' ); ?></p>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_er_funding_type"><?php echo esc_html__( 'Funding type', 'jm-referral-system' ); ?></label>
				<select name="jmrs_er_funding_type" id="jmrs_er_funding_type"<?php echo $aria( 'funding_type' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr. ?>>
					<option value=""><?php echo esc_html__( '— Not recorded —', 'jm-referral-system' ); ?></option>
					<?php foreach ( $funding_types as $type_key => $type_label ) : ?>
						<option value="<?php echo esc_attr( (string) $type_key ); ?>" <?php selected( $val( 'funding_type' ), (string) $type_key ); ?>><?php echo esc_html( (string) $type_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( 'funding_type' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_er_local_authority_id"><?php echo esc_html__( 'Funding Local Authority', 'jm-referral-system' ); ?></label>
				<select name="jmrs_er_local_authority_id" id="jmrs_er_local_authority_id"<?php echo $aria( 'local_authority_id' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr. ?>>
					<option value=""><?php echo esc_html__( '— None —', 'jm-referral-system' ); ?></option>
					<?php foreach ( $authorities as $la ) : ?>
						<option value="<?php echo esc_attr( (string) absint( $la['id'] ?? 0 ) ); ?>" <?php selected( $val( 'local_authority_id' ), (string) absint( $la['id'] ?? 0 ) ); ?>><?php echo esc_html( (string) ( $la['name'] ?? '' ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( 'local_authority_id' ); ?>
			</div>
			<?php $text_field( 'funder_name', __( 'Other funder', 'jm-referral-system' ), false, 'text', __( 'For example an NHS ICB, or a council not in the list.', 'jm-referral-system' ), 'maxlength="255"' ); ?>
			<?php $text_field( 'funding_reference', __( 'Funding reference', 'jm-referral-system' ), false, 'text', '', 'maxlength="190"' ); ?>
			<?php $text_field( 'funding_start_date', __( 'Funding start date', 'jm-referral-system' ), false, 'date' ); ?>
			<?php $text_field( 'weekly_amount', __( 'Weekly package value (£)', 'jm-referral-system' ), false, 'text', '', 'inputmode="decimal" maxlength="12"' ); ?>
			<?php $text_field( 'weekly_support_hours', __( 'Weekly support hours', 'jm-referral-system' ), false, 'text', '', 'inputmode="decimal" maxlength="6"' ); ?>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_er_package_summary"><?php echo esc_html__( 'Support package', 'jm-referral-system' ); ?></label>
				<p class="jmrs-portal-field__hint" id="jmrs_er_package_summary_hint"><?php echo esc_html__( 'Short description of the commissioned support.', 'jm-referral-system' ); ?></p>
				<textarea name="jmrs_er_package_summary" id="jmrs_er_package_summary" rows="3" maxlength="2000"<?php echo $aria( 'package_summary', 'jmrs_er_package_summary_hint' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr. ?>><?php echo esc_textarea( $val( 'package_summary' ) ); ?></textarea>
				<?php $field_error( 'package_summary' ); ?>
			</div>
		</div>
	</section>

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Other details', 'jm-referral-system' ); ?></h2>
		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="jmrs_er_service_type_id"><?php echo esc_html__( 'Service', 'jm-referral-system' ); ?></label>
				<select name="jmrs_er_service_type_id" id="jmrs_er_service_type_id"<?php echo $aria( 'service_type_id' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr. ?>>
					<option value=""><?php echo esc_html__( '— Not set —', 'jm-referral-system' ); ?></option>
					<?php foreach ( $services as $service ) : ?>
						<option value="<?php echo esc_attr( (string) absint( $service['id'] ?? 0 ) ); ?>" <?php selected( $val( 'service_type_id' ), (string) absint( $service['id'] ?? 0 ) ); ?>><?php echo esc_html( (string) ( $service['name'] ?? '' ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( 'service_type_id' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="jmrs_er_assigned_to"><?php echo esc_html__( 'Owner', 'jm-referral-system' ); ?></label>
				<select name="jmrs_er_assigned_to" id="jmrs_er_assigned_to"<?php echo $aria( 'assigned_to' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr. ?>>
					<option value=""><?php echo esc_html__( 'Unassigned', 'jm-referral-system' ); ?></option>
					<?php foreach ( $owners as $owner ) : ?>
						<option value="<?php echo esc_attr( (string) absint( $owner['id'] ?? 0 ) ); ?>" <?php selected( $val( 'assigned_to' ), (string) absint( $owner['id'] ?? 0 ) ); ?>><?php echo esc_html( (string) ( $owner['display_name'] ?? '' ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php $field_error( 'assigned_to' ); ?>
			</div>
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_er_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_er_notes" id="jmrs_er_notes" rows="3" maxlength="5000"<?php echo $aria( 'notes' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr. ?>><?php echo esc_textarea( $val( 'notes' ) ); ?></textarea>
				<?php $field_error( 'notes' ); ?>
			</div>
		</div>
		<p class="jmrs-portal-muted"><?php echo esc_html__( 'After saving, add their care plan, risk assessments and documents from their record.', 'jm-referral-system' ); ?></p>
	</section>

	<div class="jmrs-portal-field jmrs-portal-field--checkbox jmrs-portal-field--full">
		<label>
			<input type="checkbox" name="jmrs_er_confirm" value="1"<?php echo $aria( 'confirm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr. ?> />
			<?php echo esc_html__( 'I confirm this person already receives care and support from us.', 'jm-referral-system' ); ?>
		</label>
		<?php $field_error( 'confirm' ); ?>
	</div>

	<p class="jmrs-prepare-actions">
		<button type="submit" class="jmrs-button jmrs-button--primary"><?php echo esc_html__( 'Add Resident', 'jm-referral-system' ); ?></button>
		<a class="jmrs-button" href="<?php echo esc_url( $cancel_url ); ?>"><?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?></a>
	</p>
</form>
