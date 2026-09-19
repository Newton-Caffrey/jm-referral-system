<?php
/**
 * Edit Local Authority form template (includes sender rules).
 *
 * @package JMReferral
 *
 * @var array<string, mixed>           $authority
 * @var array<string, string>          $data
 * @var array<string, string>          $errors
 * @var array<int, array<string, mixed>> $rules
 * @var array<string, string>          $rule_data
 * @var array<string, string>          $rule_errors
 * @var array<int, string>             $rule_warnings
 * @var string                         $la_singular
 * @var string                         $la_plural
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$authority     = is_array( $authority ?? null ) ? $authority : array();
$data          = is_array( $data ?? null ) ? $data : array();
$errors        = is_array( $errors ?? null ) ? $errors : array();
$rules         = is_array( $rules ?? null ) ? $rules : array();
$rule_data     = is_array( $rule_data ?? null ) ? $rule_data : array();
$rule_errors   = is_array( $rule_errors ?? null ) ? $rule_errors : array();
$rule_warnings = is_array( $rule_warnings ?? null ) ? $rule_warnings : array();
$la_singular   = (string) ( $la_singular ?? 'Local Authority' );
$la_plural     = (string) ( $la_plural ?? 'Local Authorities' );

$authority_id  = absint( $authority['id'] ?? 0 );
$slug          = (string) ( $authority['slug'] ?? '' );
$name          = $data['name'] ?? '';
$status        = $data['status'] ?? 'active';
$contact_name  = $data['contact_name'] ?? '';
$contact_email = $data['contact_email'] ?? '';
$contact_phone = $data['contact_phone'] ?? '';
$website       = $data['website'] ?? '';
$notes         = $data['notes'] ?? '';
$rule_type     = $rule_data['rule_type'] ?? 'exact_email';
$rule_value    = $rule_data['rule_value'] ?? '';
$list_url      = admin_url( 'admin.php?page=jm-referrals-local-authorities' );
?>
<div class="wrap">
	<h1>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: local authority singular label */
				__( 'Edit %s', 'jm-referral-system' ),
				$la_singular
			)
		);
		?>
	</h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'jmrs_edit_local_authority_' . $authority_id, 'jmrs_edit_local_authority_nonce' ); ?>
		<input type="hidden" name="jmrs_local_authority_id" value="<?php echo esc_attr( (string) $authority_id ); ?>" />

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Slug', 'jm-referral-system' ); ?></th>
					<td>
						<code><?php echo esc_html( $slug ); ?></code>
						<p class="description"><?php echo esc_html__( 'Slug updates automatically when the name changes.', 'jm-referral-system' ); ?></p>
					</td>
				</tr>
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
						<p class="description"><?php echo esc_html__( 'Inactive organisations are preserved but are not used for active sender matching.', 'jm-referral-system' ); ?></p>
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
				__( 'Update %s', 'jm-referral-system' ),
				$la_singular
			),
			'primary',
			'jmrs_update_local_authority'
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

	<hr />

	<h2><?php echo esc_html__( 'Recognised sender rules', 'jm-referral-system' ); ?></h2>
	<p class="description">
		<?php echo esc_html__( 'Recognised sender rules help JMRS identify which organisation an incoming email may belong to. They do not independently prove email authenticity. Email From addresses can be spoofed; future email ingestion should also consider provider authentication signals where available. Mailbox connection is not active in this phase.', 'jm-referral-system' ); ?>
	</p>

	<?php if ( ! empty( $rule_warnings ) ) : ?>
		<div class="notice notice-warning inline">
			<ul>
				<?php foreach ( $rule_warnings as $warning ) : ?>
					<li><?php echo esc_html( (string) $warning ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Type', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Value', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rules ) ) : ?>
				<tr class="no-items">
					<td colspan="4"><?php echo esc_html__( 'No sender rules configured yet.', 'jm-referral-system' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $rules as $rule ) : ?>
					<?php
					$rule_id       = absint( $rule['id'] ?? 0 );
					$type          = (string) ( $rule['rule_type'] ?? '' );
					$value         = (string) ( $rule['rule_value'] ?? '' );
					$rule_status   = (string) ( $rule['status'] ?? '' );
					$type_label    = 'exact_email' === $type
						? __( 'Exact Email', 'jm-referral-system' )
						: __( 'Domain', 'jm-referral-system' );
					$toggle_action = 'active' === $rule_status ? 'deactivate_rule' : 'activate_rule';
					$toggle_label  = 'active' === $rule_status
						? __( 'Deactivate', 'jm-referral-system' )
						: __( 'Activate', 'jm-referral-system' );
					$toggle_url    = \JMReferral\LocalAuthority\LocalAuthorityController::get_rule_status_url( $authority_id, $rule_id, $toggle_action );
					$delete_url    = \JMReferral\LocalAuthority\LocalAuthorityController::get_delete_rule_url( $authority_id, $rule_id );
					?>
					<tr>
						<td><?php echo esc_html( $type_label ); ?></td>
						<td><code><?php echo esc_html( $value ); ?></code></td>
						<td><?php echo esc_html( ucfirst( $rule_status ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( $toggle_url ); ?>"><?php echo esc_html( $toggle_label ); ?></a>
							|
							<a
								href="<?php echo esc_url( $delete_url ); ?>"
								class="jmrs-button-danger"
								data-jmrs-confirm="<?php echo esc_attr__( 'Permanently delete this sender rule?', 'jm-referral-system' ); ?>"
							>
								<?php echo esc_html__( 'Delete', 'jm-referral-system' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<h3><?php echo esc_html__( 'Add sender rule', 'jm-referral-system' ); ?></h3>
	<form method="post" action="">
		<?php wp_nonce_field( 'jmrs_add_sender_rule_' . $authority_id, 'jmrs_add_sender_rule_nonce' ); ?>
		<input type="hidden" name="jmrs_local_authority_id" value="<?php echo esc_attr( (string) $authority_id ); ?>" />

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="jmrs_rule_type"><?php echo esc_html__( 'Rule type', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<select name="jmrs_rule_type" id="jmrs_rule_type">
							<option value="exact_email" <?php selected( $rule_type, 'exact_email' ); ?>><?php echo esc_html__( 'Exact Email', 'jm-referral-system' ); ?></option>
							<option value="domain" <?php selected( $rule_type, 'domain' ); ?>><?php echo esc_html__( 'Domain', 'jm-referral-system' ); ?></option>
						</select>
						<?php if ( isset( $rule_errors['rule_type'] ) ) : ?>
							<p class="description"><?php echo esc_html( $rule_errors['rule_type'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_rule_value"><?php echo esc_html__( 'Value', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input type="text" name="jmrs_rule_value" id="jmrs_rule_value" class="regular-text" value="<?php echo esc_attr( $rule_value ); ?>" placeholder="referrals@example.gov.uk or example.gov.uk" />
						<p class="description"><?php echo esc_html__( 'Exact email: full address. Domain: hostname only (for example coventry.gov.uk). Values are stored lowercase.', 'jm-referral-system' ); ?></p>
						<?php if ( isset( $rule_errors['rule_value'] ) ) : ?>
							<p class="description"><?php echo esc_html( $rule_errors['rule_value'] ); ?></p>
						<?php endif; ?>
						<?php if ( isset( $rule_errors['general'] ) ) : ?>
							<p class="description"><?php echo esc_html( $rule_errors['general'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Add sender rule', 'jm-referral-system' ), 'secondary', 'jmrs_submit_sender_rule' ); ?>
	</form>
</div>
