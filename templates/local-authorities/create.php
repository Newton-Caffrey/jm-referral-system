<?php
/**
 * Add Local Authority form template.
 *
 * @package JMReferral
 *
 * @var array<string, string> $data
 * @var array<string, string> $errors
 * @var string                $la_singular
 * @var string                $la_plural
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data        = is_array( $data ?? null ) ? $data : array();
$errors      = is_array( $errors ?? null ) ? $errors : array();
$la_singular = (string) ( $la_singular ?? 'Local Authority' );
$la_plural   = (string) ( $la_plural ?? 'Local Authorities' );

$name          = $data['name'] ?? '';
$status        = $data['status'] ?? 'active';
$contact_name  = $data['contact_name'] ?? '';
$contact_email = $data['contact_email'] ?? '';
$contact_phone = $data['contact_phone'] ?? '';
$website       = $data['website'] ?? '';
$notes         = $data['notes'] ?? '';
$list_url      = admin_url( 'admin.php?page=jm-referrals-local-authorities' );
?>
<div class="wrap">
	<h1>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: local authority singular label */
				__( 'Add %s', 'jm-referral-system' ),
				$la_singular
			)
		);
		?>
	</h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'jmrs_add_local_authority', 'jmrs_add_local_authority_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="jmrs_name"><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input type="text" name="jmrs_name" id="jmrs_name" class="regular-text" value="<?php echo esc_attr( $name ); ?>" maxlength="255" required />
						<?php if ( isset( $errors['name'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['name'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_status"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_status" id="jmrs_status">
							<option value="active" <?php selected( $status, 'active' ); ?>><?php echo esc_html__( 'Active', 'jm-referral-system' ); ?></option>
							<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php echo esc_html__( 'Inactive', 'jm-referral-system' ); ?></option>
						</select>
						<?php if ( isset( $errors['status'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['status'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_contact_name"><?php echo esc_html__( 'Contact name', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input type="text" name="jmrs_contact_name" id="jmrs_contact_name" class="regular-text" value="<?php echo esc_attr( $contact_name ); ?>" maxlength="255" />
						<?php if ( isset( $errors['contact_name'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['contact_name'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_contact_email"><?php echo esc_html__( 'Contact email', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input type="email" name="jmrs_contact_email" id="jmrs_contact_email" class="regular-text" value="<?php echo esc_attr( $contact_email ); ?>" maxlength="190" />
						<?php if ( isset( $errors['contact_email'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['contact_email'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_contact_phone"><?php echo esc_html__( 'Contact phone', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input type="text" name="jmrs_contact_phone" id="jmrs_contact_phone" class="regular-text" value="<?php echo esc_attr( $contact_phone ); ?>" maxlength="50" />
						<?php if ( isset( $errors['contact_phone'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['contact_phone'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_website"><?php echo esc_html__( 'Website', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input type="url" name="jmrs_website" id="jmrs_website" class="regular-text" value="<?php echo esc_attr( $website ); ?>" maxlength="255" placeholder="https://" />
						<?php if ( isset( $errors['website'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['website'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_notes"><?php echo esc_html__( 'Notes', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<textarea name="jmrs_notes" id="jmrs_notes" class="large-text" rows="4"><?php echo esc_textarea( $notes ); ?></textarea>
						<?php if ( isset( $errors['notes'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['notes'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<?php
		submit_button(
			sprintf(
				/* translators: %s: local authority singular label */
				__( 'Add %s', 'jm-referral-system' ),
				$la_singular
			),
			'primary',
			'jmrs_submit_local_authority'
		);
		?>
		<a class="button button-secondary" href="<?php echo esc_url( $list_url ); ?>">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: local authority plural label */
					__( 'Back to %s', 'jm-referral-system' ),
					$la_plural
				)
			);
			?>
		</a>
	</form>
</div>
