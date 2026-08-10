<?php
/**
 * Workflow stages list template.
 *
 * @package JMReferral
 *
 * @var array<int, array<string, mixed>> $workflow_stages Workflow stage rows.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$workflow_stages = is_array( $workflow_stages ?? null ) ? $workflow_stages : array();
$add_url         = admin_url( 'admin.php?page=jm-referrals-workflow-stages-add' );
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Workflow Stages', 'jm-referral-system' ); ?></h1>
	<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add New', 'jm-referral-system' ); ?>
	</a>
	<hr class="wp-header-end" />

	<p class="description">
		<?php echo esc_html__( 'Canonical acquisition pipeline stages are system-protected (cannot be deleted or renamed). Legacy/custom stages remain editable.', 'jm-referral-system' ); ?>
	</p>

	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Order', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Slug', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Type', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $workflow_stages ) ) : ?>
				<tr class="no-items">
					<td colspan="6"><?php echo esc_html__( 'No workflow stages found.', 'jm-referral-system' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $workflow_stages as $workflow_stage ) : ?>
					<?php
					$workflow_stage_id = absint( $workflow_stage['id'] ?? 0 );
					$stage_order       = absint( $workflow_stage['stage_order'] ?? 0 );
					$name              = (string) ( $workflow_stage['name'] ?? '' );
					$slug              = (string) ( $workflow_stage['slug'] ?? '' );
					$status            = (string) ( $workflow_stage['status'] ?? '' );
					$is_system         = ! empty( $workflow_stage['is_system'] )
						|| \JMReferral\Pipeline\PipelineStage::is_canonical( $slug );
					$is_pipeline       = ! empty( $workflow_stage['is_pipeline_stage'] )
						&& \JMReferral\Pipeline\PipelineStage::is_canonical( $slug );
					$edit_url          = \JMReferral\Workflow\WorkflowStageController::get_edit_url( $workflow_stage_id );
					$delete_url        = \JMReferral\Workflow\WorkflowStageController::get_delete_url( $workflow_stage_id );
					$type_label        = $is_pipeline
						? __( 'Pipeline (system)', 'jm-referral-system' )
						: ( $is_system
							? __( 'System', 'jm-referral-system' )
							: __( 'Legacy / custom', 'jm-referral-system' ) );
					?>
					<tr>
						<td><?php echo esc_html( (string) $stage_order ); ?></td>
						<td><strong><?php echo esc_html( $name ); ?></strong></td>
						<td><code><?php echo esc_html( $slug ); ?></code></td>
						<td><?php echo esc_html( $type_label ); ?></td>
						<td><?php echo esc_html( ucfirst( $status ) ); ?></td>
						<td>
							<span class="jmrs-actions">
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></a>
								<?php if ( ! $is_system ) : ?>
									|
									<a
										href="<?php echo esc_url( $delete_url ); ?>"
										class="jmrs-button-danger" data-jmrs-confirm="<?php echo esc_attr__( 'Delete this workflow stage? This cannot be undone.', 'jm-referral-system' ); ?>"
									>
										<?php echo esc_html__( 'Delete', 'jm-referral-system' ); ?>
									</a>
								<?php endif; ?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
