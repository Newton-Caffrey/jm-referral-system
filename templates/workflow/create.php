<?php
/**
 * Add Workflow Stage form template.
 *
 * @package JMReferral
 *
 * @var array<string, string> $data   Sticky form values.
 * @var array<string, string> $errors Validation errors.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data   = is_array( $data ?? null ) ? $data : array();
$errors = is_array( $errors ?? null ) ? $errors : array();

$name        = $data['name'] ?? '';
$description = $data['description'] ?? '';
$stage_order = $data['stage_order'] ?? '0';
$status      = $data['status'] ?? 'active';
$list_url    = admin_url( 'admin.php?page=jm-referrals-workflow-stages' );
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Add Workflow Stage', 'jm-referral-system' ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'jmrs_add_workflow_stage', 'jmrs_add_workflow_stage_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="jmrs_name"><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input type="text" name="jmrs_name" id="jmrs_name" class="regular-text" value="<?php echo esc_attr( $name ); ?>" required />
						<?php if ( isset( $errors['name'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['name'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_stage_order"><?php echo esc_html__( 'Stage Order', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<input type="number" name="jmrs_stage_order" id="jmrs_stage_order" class="small-text" min="0" step="1" value="<?php echo esc_attr( $stage_order ); ?>" required />
						<?php if ( isset( $errors['stage_order'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['stage_order'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_description"><?php echo esc_html__( 'Description', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<textarea name="jmrs_description" id="jmrs_description" class="large-text" rows="4"><?php echo esc_textarea( $description ); ?></textarea>
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

		<?php submit_button( __( 'Add Workflow Stage', 'jm-referral-system' ), 'primary', 'jmrs_submit_workflow_stage' ); ?>
		<a class="button button-secondary" href="<?php echo esc_url( $list_url ); ?>">
			<?php echo esc_html__( 'Back to Workflow Stages', 'jm-referral-system' ); ?>
		</a>
	</form>
</div>
