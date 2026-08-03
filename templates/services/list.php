<?php
/**
 * Service types list template.
 *
 * @package JMReferral
 *
 * @var array<int, array<string, mixed>> $service_types Service type rows.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$service_types = is_array( $service_types ?? null ) ? $service_types : array();
$add_url       = admin_url( 'admin.php?page=jm-referrals-service-types-add' );
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Service Types', 'jm-referral-system' ); ?></h1>
	<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action">
		<?php echo esc_html__( 'Add New', 'jm-referral-system' ); ?>
	</a>
	<hr class="wp-header-end" />

	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Slug', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Created Date', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $service_types ) ) : ?>
				<tr class="no-items">
					<td colspan="5"><?php echo esc_html__( 'No service types found.', 'jm-referral-system' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $service_types as $service_type ) : ?>
					<?php
					$service_type_id = absint( $service_type['id'] ?? 0 );
					$name            = (string) ( $service_type['name'] ?? '' );
					$slug            = (string) ( $service_type['slug'] ?? '' );
					$status          = (string) ( $service_type['status'] ?? '' );
					$created_at      = (string) ( $service_type['created_at'] ?? '' );
					$created_display = '' !== $created_at
						? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at )
						: '';
					$edit_url        = \JMReferral\Services\ServiceTypeController::get_edit_url( $service_type_id );
					$delete_url      = \JMReferral\Services\ServiceTypeController::get_delete_url( $service_type_id );
					?>
					<tr>
						<td><strong><?php echo esc_html( $name ); ?></strong></td>
						<td><code><?php echo esc_html( $slug ); ?></code></td>
						<td><?php echo esc_html( ucfirst( $status ) ); ?></td>
						<td><?php echo esc_html( $created_display ); ?></td>
						<td>
							<span class="jmrs-actions">
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></a>
								|
								<a
									href="<?php echo esc_url( $delete_url ); ?>"
									class="submitdelete"
									onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this service type?', 'jm-referral-system' ) ); ?>');"
								>
									<?php echo esc_html__( 'Delete', 'jm-referral-system' ); ?>
								</a>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
