<?php
/**
 * Portal place resident form.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data               = is_array( $data ?? null ) ? $data : array();
$errors             = is_array( $errors ?? null ) ? $errors : array();
$homes              = is_array( $homes ?? null ) ? $homes : array();
$vacant_bedrooms    = is_array( $vacant_bedrooms ?? null ) ? $vacant_bedrooms : array();
$selected_referral  = is_array( $selected_referral ?? null ) ? $selected_referral : null;
$client_results     = is_array( $client_results ?? null ) ? $client_results : array();
$form_action        = (string) ( $form_action ?? '' );
$cancel_url         = (string) ( $cancel_url ?? '' );

$val = static function ( array $data, string $key ): string {
	return (string) ( $data[ $key ] ?? '' );
};
?>
<?php if ( ! empty( $errors ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert">
		<p><strong><?php echo esc_html__( 'Please fix the following errors:', 'jm-referral-system' ); ?></strong></p>
		<ul>
			<?php foreach ( $errors as $message ) : ?>
				<li><?php echo esc_html( (string) $message ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<form class="jmrs-portal-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
	<?php wp_nonce_field( 'jmrs_save_placement', 'jmrs_occupancy_nonce' ); ?>
	<input type="hidden" name="jmrs_save_placement" value="1" />

	<section class="jmrs-portal-section">
		<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Place Resident', 'jm-referral-system' ); ?></h2>

		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_client_search"><?php echo esc_html__( 'Client', 'jm-referral-system' ); ?> <span aria-hidden="true">*</span></label>
				<?php if ( null !== $selected_referral ) : ?>
					<?php
					$sel_id   = absint( $selected_referral['id'] ?? 0 );
					$sel_num  = (string) ( $selected_referral['referral_number'] ?? '' );
					$sel_name = trim( (string) ( $selected_referral['client_first_name'] ?? '' ) . ' ' . (string) ( $selected_referral['client_last_name'] ?? '' ) );
					if ( '' === $sel_name ) {
						$sel_name = (string) ( $selected_referral['client_name'] ?? '' );
					}
					?>
					<p>
						<strong><?php echo esc_html( $sel_num . ( '' !== $sel_name ? ' — ' . $sel_name : '' ) ); ?></strong>
					</p>
					<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $sel_id ); ?>" />
				<?php else : ?>
					<input type="search" name="jmrs_client_search" id="jmrs_client_search" value="<?php echo esc_attr( $val( $data, 'client_search' ) ); ?>" placeholder="<?php echo esc_attr__( 'Referral number or client name', 'jm-referral-system' ); ?>" />
					<p class="jmrs-portal-actions">
						<button type="submit" name="jmrs_search_clients" value="1" class="jmrs-button jmrs-button--secondary"><?php echo esc_html__( 'Search clients', 'jm-referral-system' ); ?></button>
					</p>
					<?php if ( ! empty( $client_results ) ) : ?>
						<fieldset>
							<legend class="screen-reader-text"><?php echo esc_html__( 'Select client', 'jm-referral-system' ); ?></legend>
							<?php foreach ( $client_results as $result ) : ?>
								<?php $rid = absint( $result['id'] ?? 0 ); ?>
								<p>
									<label>
										<input type="radio" name="jmrs_selected_referral_id" value="<?php echo esc_attr( (string) $rid ); ?>" <?php checked( absint( $val( $data, 'referral_id' ) ), $rid ); ?> />
										<?php echo esc_html( (string) ( $result['referral_number'] ?? '' ) . ' — ' . (string) ( $result['client_name'] ?? '' ) ); ?>
									</label>
								</p>
							<?php endforeach; ?>
						</fieldset>
					<?php elseif ( '' !== $val( $data, 'client_search' ) ) : ?>
						<p class="jmrs-portal-muted"><?php echo esc_html__( 'No eligible clients found. Archived clients and those with an active placement are excluded.', 'jm-referral-system' ); ?></p>
					<?php endif; ?>
					<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( $val( $data, 'referral_id' ) ); ?>" />
				<?php endif; ?>
				<?php if ( isset( $errors['referral_id'] ) ) : ?>
					<p class="jmrs-portal-field-error" id="jmrs-err-referral_id"><?php echo esc_html( (string) $errors['referral_id'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="jmrs-portal-field">
				<label for="jmrs_home_id"><?php echo esc_html__( 'Home', 'jm-referral-system' ); ?> <span aria-hidden="true">*</span></label>
				<select name="jmrs_home_id" id="jmrs_home_id" required>
					<option value="0"><?php echo esc_html__( 'Select home', 'jm-referral-system' ); ?></option>
					<?php foreach ( $homes as $home_row ) : ?>
						<option value="<?php echo esc_attr( (string) absint( $home_row['id'] ?? 0 ) ); ?>" <?php selected( absint( $val( $data, 'home_id' ) ), absint( $home_row['id'] ?? 0 ) ); ?>>
							<?php echo esc_html( (string) ( $home_row['name'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" name="jmrs_reload_bedrooms" value="1" class="jmrs-button jmrs-button--secondary"><?php echo esc_html__( 'Load vacant bedrooms', 'jm-referral-system' ); ?></button>
				<?php if ( isset( $errors['home_id'] ) ) : ?>
					<p class="jmrs-portal-field-error"><?php echo esc_html( (string) $errors['home_id'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="jmrs-portal-field">
				<label for="jmrs_bedroom_id"><?php echo esc_html__( 'Bedroom', 'jm-referral-system' ); ?> <span aria-hidden="true">*</span></label>
				<select name="jmrs_bedroom_id" id="jmrs_bedroom_id" required>
					<option value="0"><?php echo esc_html__( 'Select vacant bedroom', 'jm-referral-system' ); ?></option>
					<?php if ( empty( $vacant_bedrooms ) && absint( $val( $data, 'home_id' ) ) > 0 ) : ?>
						<option value="0" disabled><?php echo esc_html__( 'No vacant bedrooms are currently available.', 'jm-referral-system' ); ?></option>
					<?php endif; ?>
					<?php foreach ( $vacant_bedrooms as $bedroom_row ) : ?>
						<option value="<?php echo esc_attr( (string) absint( $bedroom_row['id'] ?? 0 ) ); ?>" <?php selected( absint( $val( $data, 'bedroom_id' ) ), absint( $bedroom_row['id'] ?? 0 ) ); ?>>
							<?php
							printf(
								/* translators: %s: room label */
								esc_html__( '%s — Vacant', 'jm-referral-system' ),
								esc_html( (string) ( $bedroom_row['room_label'] ?? '' ) )
							);
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( isset( $errors['bedroom_id'] ) ) : ?>
					<p class="jmrs-portal-field-error"><?php echo esc_html( (string) $errors['bedroom_id'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="jmrs-portal-field">
				<label for="jmrs_move_in_date"><?php echo esc_html__( 'Move-in Date', 'jm-referral-system' ); ?> <span aria-hidden="true">*</span></label>
				<input type="date" name="jmrs_move_in_date" id="jmrs_move_in_date" value="<?php echo esc_attr( $val( $data, 'move_in_date' ) ); ?>" required />
				<?php if ( isset( $errors['move_in_date'] ) ) : ?>
					<p class="jmrs-portal-field-error"><?php echo esc_html( (string) $errors['move_in_date'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="jmrs-portal-field jmrs-portal-field--full">
				<label for="jmrs_placement_notes"><?php echo esc_html__( 'Placement Notes', 'jm-referral-system' ); ?></label>
				<textarea name="jmrs_placement_notes" id="jmrs_placement_notes" rows="3"><?php echo esc_textarea( $val( $data, 'notes' ) ); ?></textarea>
			</div>
		</div>
	</section>

	<p class="jmrs-portal-actions">
		<button type="submit" class="jmrs-button jmrs-button--primary"><?php echo esc_html__( 'Confirm Placement', 'jm-referral-system' ); ?></button>
		<?php if ( '' !== $cancel_url ) : ?>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>"><?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?></a>
		<?php endif; ?>
	</p>
</form>
