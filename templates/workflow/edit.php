<?php
/**
 * Edit Workflow Stage form template.
 *
 * @package JMReferral
 *
 * @var array<string, mixed>  $workflow_stage Workflow stage row.
 * @var array<string, string> $data           Form values.
 * @var array<string, string> $errors         Validation errors.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$workflow_stage = is_array( $workflow_stage ?? null ) ? $workflow_stage : array();
$data           = is_array( $data ?? null ) ? $data : array();
$errors         = is_array( $errors ?? null ) ? $errors : array();

$workflow_stage_id = absint( $workflow_stage['id'] ?? 0 );
$slug              = (string) ( $workflow_stage['slug'] ?? '' );
$name              = $data['name'] ?? '';
$description       = $data['description'] ?? '';
$stage_order       = $data['stage_order'] ?? '0';
$status            = $data['status'] ?? 'active';
$is_system         = ! empty( $workflow_stage['is_system'] )
	|| \JMReferral\Pipeline\PipelineStage::is_canonical( $slug );
$list_url          = admin_url( 'admin.php?page=jm-referrals-workflow-stages' );
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Edit Workflow Stage', 'jm-referral-system' ); ?></h1>

	<?php if ( $is_system ) : ?>
		<div class="notice notice-info inline">
			<p><?php echo esc_html__( 'This is a system pipeline stage. Name, slug, and active status are locked. You may adjust description and display order only.', 'jm-referral-system' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'jmrs_edit_workflow_stage_' . $workflow_stage_id, 'jmrs_edit_workflow_stage_nonce' ); ?>
		<input type="hidden" name="jmrs_workflow_stage_id" value="<?php echo esc_attr( (string) $workflow_stage_id ); ?>" />

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Slug', 'jm-referral-system' ); ?></th>
					<td>
						<code><?php echo esc_html( $slug ); ?></code>
						<?php if ( $is_system ) : ?>
							<p class="description"><?php echo esc_html__( 'Canonical slug cannot be changed.', 'jm-referral-system' ); ?></p>
						<?php else : ?>
							<p class="description"><?php echo esc_html__( 'Slug updates automatically when the name changes.', 'jm-referral-system' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="jmrs_name"><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></label>
					</th>
					<td>
						<?php if ( $is_system ) : ?>
							<input type="text" id="jmrs_name" class="regular-text" value="<?php echo esc_attr( $name ); ?>" disabled />
							<input type="hidden" name="jmrs_name" value="<?php echo esc_attr( $name ); ?>" />
						<?php else : ?>
							<input type="text" name="jmrs_name" id="jmrs_name" class="regular-text" value="<?php echo esc_attr( $name ); ?>" required />
						<?php endif; ?>
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
						<?php if ( $is_system ) : ?>
							<input type="hidden" name="jmrs_status" value="active" />
							<code><?php echo esc_html__( 'Active (locked)', 'jm-referral-system' ); ?></code>
						<?php else : ?>
							<select name="jmrs_status" id="jmrs_status">
								<option value="active" <?php selected( $status, 'active' ); ?>><?php echo esc_html__( 'Active', 'jm-referral-system' ); ?></option>
								<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php echo esc_html__( 'Inactive', 'jm-referral-system' ); ?></option>
							</select>
						<?php endif; ?>
						<?php if ( isset( $errors['status'] ) ) : ?>
							<p class="description"><?php echo esc_html( $errors['status'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Update Workflow Stage', 'jm-referral-system' ), 'primary', 'jmrs_update_workflow_stage' ); ?>
		<a class="button button-secondary" href="<?php echo esc_url( $list_url ); ?>">
			<?php echo esc_html__( 'Back to Workflow Stages', 'jm-referral-system' ); ?>
		</a>
	</form>
</div>
