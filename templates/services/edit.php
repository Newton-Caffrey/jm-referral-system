<?php
/**
 * Edit Service Type form template.
 *
 * @package JMReferral
 *
 * @var array<string, mixed>  $service_type Service type row.
 * @var array<string, string> $data         Form values.
 * @var array<string, string> $errors       Validation errors.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$service_type = is_array( $service_type ?? null ) ? $service_type : array();
$data         = is_array( $data ?? null ) ? $data : array();
$errors       = is_array( $errors ?? null ) ? $errors : array();

$service_type_id = absint( $service_type['id'] ?? 0 );
$slug            = (string) ( $service_type['slug'] ?? '' );
$name            = $data['name'] ?? '';
$description     = $data['description'] ?? '';
$status          = $data['status'] ?? 'active';
$list_url        = admin_url( 'admin.php?page=jm-referrals-service-types' );
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Edit Service Type', 'jm-referral-system' ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'jmrs_edit_service_type_' . $service_type_id, 'jmrs_edit_service_type_nonce' ); ?>
		<input type="hidden" name="jmrs_service_type_id" value="<?php echo esc_attr( (string) $service_type_id ); ?>" />

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
						<input
							type="text"
							name="jmrs_name"
							id="jmrs_name"
							class="regular-text"
							value="<?php echo esc_attr( $name ); ?>"
							required
						/>
						<?php if ( isset( $errors['name'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['name'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_description"><?php echo esc_html__( 'Description', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<textarea
							name="jmrs_description"
							id="jmrs_description"
							class="large-text"
							rows="4"
						><?php echo esc_textarea( $description ); ?></textarea>
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
			</tbody>
		</table>

		<?php
		submit_button(
			__( 'Update Service Type', 'jm-referral-system' ),
			'primary',
			'jmrs_update_service_type'
		);
		?>
		<a class="button button-secondary" href="<?php echo esc_url( $list_url ); ?>">
			<?php echo esc_html__( 'Back to Service Types', 'jm-referral-system' ); ?>
		</a>
	</form>
</div>
