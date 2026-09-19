<?php
/**
 * Local Authorities list template.
 *
 * @package JMReferral
 *
 * @var array<int, array<string, mixed>> $authorities
 * @var string                           $search
 * @var string                           $status
 * @var string                           $la_plural
 * @var string                           $la_singular
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$authorities = is_array( $authorities ?? null ) ? $authorities : array();
$search      = (string) ( $search ?? '' );
$status      = (string) ( $status ?? '' );
$la_plural   = (string) ( $la_plural ?? 'Local Authorities' );
$la_singular = (string) ( $la_singular ?? 'Local Authority' );
$add_url     = admin_url( 'admin.php?page=jm-referrals-local-authorities-add' );
$list_url    = admin_url( 'admin.php?page=jm-referrals-local-authorities' );
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $la_plural ); ?></h1>
	<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: local authority singular label */
				__( 'Add %s', 'jm-referral-system' ),
				$la_singular
			)
		);
		?>
	</a>
	<hr class="wp-header-end" />

	<p class="description">
		<?php echo esc_html__( 'Maintain commissioning organisations and recognised sender rules. This directory supports future automated referral recognition; mailbox connection is not active yet.', 'jm-referral-system' ); ?>
	</p>

	<form method="get" action="">
		<input type="hidden" name="page" value="jm-referrals-local-authorities" />
		<p class="search-box">
			<label class="screen-reader-text" for="jmrs-la-search"><?php echo esc_html__( 'Search', 'jm-referral-system' ); ?></label>
			<input type="search" id="jmrs-la-search" name="s" value="<?php echo esc_attr( $search ); ?>" />
			<label for="jmrs-la-status" class="screen-reader-text"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></label>
			<select name="status" id="jmrs-la-status">
				<option value="" <?php selected( $status, '' ); ?>><?php echo esc_html__( 'All statuses', 'jm-referral-system' ); ?></option>
				<option value="active" <?php selected( $status, 'active' ); ?>><?php echo esc_html__( 'Active', 'jm-referral-system' ); ?></option>
				<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php echo esc_html__( 'Inactive', 'jm-referral-system' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter', 'jm-referral-system' ), '', '', false ); ?>
			<?php if ( '' !== $search || '' !== $status ) : ?>
				<a class="button" href="<?php echo esc_url( $list_url ); ?>"><?php echo esc_html__( 'Reset', 'jm-referral-system' ); ?></a>
			<?php endif; ?>
		</p>
	</form>

	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Contact', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Sender Rules', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Updated', 'jm-referral-system' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Actions', 'jm-referral-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $authorities ) ) : ?>
				<tr class="no-items">
					<td colspan="6">
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: local authority plural label */
									__( 'No %s yet. Use this directory to maintain commissioning organisations. Later phases will use recognised sender rules to help identify which organisation an incoming email may belong to. Email intake is not connected yet.', 'jm-referral-system' ),
									strtolower( $la_plural )
								)
							);
							?>
						</p>
						<p>
							<a class="button button-primary" href="<?php echo esc_url( $add_url ); ?>">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: local authority singular label */
										__( 'Add %s', 'jm-referral-system' ),
										$la_singular
									)
								);
								?>
							</a>
						</p>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $authorities as $authority ) : ?>
					<?php
					$authority_id    = absint( $authority['id'] ?? 0 );
					$name            = (string) ( $authority['name'] ?? '' );
					$row_status      = (string) ( $authority['status'] ?? '' );
					$contact_name    = (string) ( $authority['contact_name'] ?? '' );
					$contact_email   = (string) ( $authority['contact_email'] ?? '' );
					$contact_display = trim( $contact_name . ( '' !== $contact_email ? ' <' . $contact_email . '>' : '' ) );
					$rule_count      = (int) ( $authority['rule_count'] ?? 0 );
					$updated_at      = (string) ( $authority['updated_at'] ?? '' );
					$updated_display = '' !== $updated_at
						? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $updated_at )
						: '';
					$edit_url        = \JMReferral\LocalAuthority\LocalAuthorityController::get_edit_url( $authority_id );
					$status_action   = 'active' === $row_status ? 'deactivate' : 'activate';
					$status_url      = \JMReferral\LocalAuthority\LocalAuthorityController::get_status_url( $authority_id, $status_action );
					$status_label    = 'active' === $row_status
						? __( 'Deactivate', 'jm-referral-system' )
						: __( 'Activate', 'jm-referral-system' );
					?>
					<tr>
						<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $name ); ?></a></strong></td>
						<td><?php echo esc_html( ucfirst( $row_status ) ); ?></td>
						<td><?php echo esc_html( '' !== $contact_display ? $contact_display : '—' ); ?></td>
						<td><?php echo esc_html( (string) $rule_count ); ?></td>
						<td><?php echo esc_html( $updated_display ); ?></td>
						<td>
							<span class="jmrs-actions">
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html__( 'Edit', 'jm-referral-system' ); ?></a>
								|
								<a href="<?php echo esc_url( $status_url ); ?>"><?php echo esc_html( $status_label ); ?></a>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
