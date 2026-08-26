<?php
/**
 * Portal meeting detail (read-only). Phase 4B.2.1.
 *
 * @var array<string, mixed> $referral
 * @var bool                 $is_archived
 * @var array<string, mixed> $detail
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral     = is_array( $referral ?? null ) ? $referral : array();
$is_archived  = ! empty( $is_archived );
$detail       = is_array( $detail ?? null ) ? $detail : array();
$meeting      = is_array( $detail['meeting'] ?? null ) ? $detail['meeting'] : array();
$internal     = is_array( $detail['internal'] ?? null ) ? $detail['internal'] : array();
$external     = is_array( $detail['external'] ?? null ) ? $detail['external'] : array();
$can_view_contacts  = ! empty( $detail['can_view_contacts'] );
$list_url           = (string) ( $detail['list_url'] ?? '' );
$referral_url       = (string) ( $detail['referral_url'] ?? '' );

$date_format = (string) get_option( 'date_format' );
$time_format = (string) get_option( 'time_format' );
$dt_format   = trim( $date_format . ' ' . $time_format );

$fmt = static function ( string $raw ) use ( $dt_format ): string {
	return '' !== $raw ? (string) mysql2date( $dt_format, $raw ) : '—';
};

$status_key = sanitize_html_class( (string) ( $meeting['status'] ?? '' ) );
$url_raw    = $can_view_contacts ? (string) ( $meeting['online_meeting_url'] ?? '' ) : '';
?>
<?php if ( $is_archived ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--warning" role="status">
		<?php echo esc_html__( 'This referral is archived. Meetings are read-only.', 'jm-referral-system' ); ?>
	</div>
<?php endif; ?>

<div class="jmrs-portal-quick-actions jmrs-meeting-detail-actions">
	<?php if ( '' !== $list_url ) : ?>
		<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $list_url ); ?>"><?php echo esc_html__( 'Back to Meetings', 'jm-referral-system' ); ?></a>
	<?php endif; ?>
	<?php if ( '' !== $referral_url ) : ?>
		<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $referral_url ); ?>"><?php echo esc_html__( 'Back to Referral', 'jm-referral-system' ); ?></a>
	<?php endif; ?>
</div>

<section class="jmrs-portal-section jmrs-portal-panel jmrs-meeting-detail" aria-labelledby="jmrs-meeting-detail-title">
	<h2 id="jmrs-meeting-detail-title" class="jmrs-portal-section__title"><?php echo esc_html__( 'Meeting', 'jm-referral-system' ); ?></h2>
	<dl class="jmrs-meeting-detail__dl">
		<div><dt><?php echo esc_html__( 'Type', 'jm-referral-system' ); ?></dt><dd><?php echo esc_html( (string) ( $meeting['meeting_type_label'] ?? '—' ) ); ?></dd></div>
		<div>
			<dt><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></dt>
			<dd>
				<span class="jmrs-portal-badge jmrs-meeting-status jmrs-meeting-status--<?php echo esc_attr( $status_key ); ?>">
					<?php echo esc_html( (string) ( $meeting['status_label'] ?? '—' ) ); ?>
				</span>
			</dd>
		</div>
		<div><dt><?php echo esc_html__( 'Scheduled start', 'jm-referral-system' ); ?></dt><dd><?php echo esc_html( $fmt( (string) ( $meeting['scheduled_at'] ?? '' ) ) ); ?></dd></div>
		<div><dt><?php echo esc_html__( 'Scheduled end', 'jm-referral-system' ); ?></dt><dd><?php echo esc_html( $fmt( (string) ( $meeting['scheduled_end_at'] ?? '' ) ) ); ?></dd></div>
		<div><dt><?php echo esc_html__( 'Location type', 'jm-referral-system' ); ?></dt><dd><?php echo esc_html( (string) ( $meeting['location_type_label'] ?? '—' ) ); ?></dd></div>
		<div><dt><?php echo esc_html__( 'Location name', 'jm-referral-system' ); ?></dt><dd><?php echo esc_html( '' !== (string) ( $meeting['location_name'] ?? '' ) ? (string) $meeting['location_name'] : '—' ); ?></dd></div>
		<div><dt><?php echo esc_html__( 'Location address', 'jm-referral-system' ); ?></dt><dd><?php echo esc_html( '' !== (string) ( $meeting['location_address'] ?? '' ) ? (string) $meeting['location_address'] : '—' ); ?></dd></div>
		<?php if ( $can_view_contacts && '' !== $url_raw ) : ?>
			<div>
				<dt><?php echo esc_html__( 'Online meeting URL', 'jm-referral-system' ); ?></dt>
				<dd>
					<a href="<?php echo esc_url( $url_raw ); ?>" rel="noopener noreferrer" target="_blank"><?php echo esc_html( $url_raw ); ?></a>
				</dd>
			</div>
		<?php endif; ?>
		<div><dt><?php echo esc_html__( 'Purpose', 'jm-referral-system' ); ?></dt><dd><?php echo '' !== trim( (string) ( $meeting['purpose'] ?? '' ) ) ? esc_html( (string) $meeting['purpose'] ) : '—'; ?></dd></div>
		<div><dt><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></dt><dd><?php echo '' !== trim( (string) ( $meeting['outcome'] ?? '' ) ) ? esc_html( (string) $meeting['outcome'] ) : '—'; ?></dd></div>
		<div><dt><?php echo esc_html__( 'Created by', 'jm-referral-system' ); ?></dt><dd><?php echo esc_html( (string) ( $meeting['created_by_name'] ?? '—' ) ); ?></dd></div>
		<div><dt><?php echo esc_html__( 'Created', 'jm-referral-system' ); ?></dt><dd><?php echo esc_html( $fmt( (string) ( $meeting['created_at'] ?? '' ) ) ); ?></dd></div>
		<div><dt><?php echo esc_html__( 'Updated by', 'jm-referral-system' ); ?></dt><dd><?php echo esc_html( (string) ( $meeting['updated_by_name'] ?? '—' ) ); ?></dd></div>
		<div><dt><?php echo esc_html__( 'Updated', 'jm-referral-system' ); ?></dt><dd><?php echo esc_html( $fmt( (string) ( $meeting['updated_at'] ?? '' ) ) ); ?></dd></div>
		<?php if ( '' !== (string) ( $meeting['completed_at'] ?? '' ) ) : ?>
			<div><dt><?php echo esc_html__( 'Completed', 'jm-referral-system' ); ?></dt><dd><?php echo esc_html( $fmt( (string) $meeting['completed_at'] ) ); ?></dd></div>
		<?php endif; ?>
		<?php if ( '' !== (string) ( $meeting['cancelled_at'] ?? '' ) ) : ?>
			<div><dt><?php echo esc_html__( 'Cancelled', 'jm-referral-system' ); ?></dt><dd><?php echo esc_html( $fmt( (string) $meeting['cancelled_at'] ) ); ?></dd></div>
		<?php endif; ?>
	</dl>
</section>

<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-meeting-internal-title">
	<h2 id="jmrs-meeting-internal-title" class="jmrs-portal-section__title"><?php echo esc_html__( 'Internal attendees', 'jm-referral-system' ); ?></h2>
	<?php if ( empty( $internal ) ) : ?>
		<p><?php echo esc_html__( 'No internal attendees.', 'jm-referral-system' ); ?></p>
	<?php else : ?>
		<div class="jmrs-portal-table-wrap">
			<table class="jmrs-portal-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Staff', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Meeting role', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Attendance', 'jm-referral-system' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $internal as $row ) : ?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'Staff', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $row['display_name'] ?? '—' ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Meeting role', 'jm-referral-system' ); ?>"><?php echo esc_html( '' !== (string) ( $row['meeting_role'] ?? '' ) ? (string) $row['meeting_role'] : '—' ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Attendance', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $row['attendance_status_label'] ?? '—' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>

<section class="jmrs-portal-section jmrs-portal-panel" aria-labelledby="jmrs-meeting-external-title">
	<h2 id="jmrs-meeting-external-title" class="jmrs-portal-section__title"><?php echo esc_html__( 'External participants', 'jm-referral-system' ); ?></h2>
	<?php if ( empty( $external ) ) : ?>
		<p><?php echo esc_html__( 'No external participants.', 'jm-referral-system' ); ?></p>
	<?php else : ?>
		<div class="jmrs-portal-table-wrap">
			<table class="jmrs-portal-table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Name', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Role', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Organisation', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Category', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Meeting role', 'jm-referral-system' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Attendance', 'jm-referral-system' ); ?></th>
						<?php if ( $can_view_contacts ) : ?>
							<th scope="col"><?php echo esc_html__( 'Email', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Telephone', 'jm-referral-system' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $external as $row ) : ?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'Name', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $row['display_name'] ?? '—' ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Role', 'jm-referral-system' ); ?>"><?php echo esc_html( '' !== (string) ( $row['professional_role'] ?? '' ) ? (string) $row['professional_role'] : '—' ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Organisation', 'jm-referral-system' ); ?>"><?php echo esc_html( '' !== (string) ( $row['organisation'] ?? '' ) ? (string) $row['organisation'] : '—' ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Category', 'jm-referral-system' ); ?>"><?php echo esc_html( '' !== (string) ( $row['participant_category_label'] ?? '' ) ? (string) $row['participant_category_label'] : '—' ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Meeting role', 'jm-referral-system' ); ?>"><?php echo esc_html( '' !== (string) ( $row['meeting_role'] ?? '' ) ? (string) $row['meeting_role'] : '—' ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Attendance', 'jm-referral-system' ); ?>"><?php echo esc_html( (string) ( $row['attendance_status_label'] ?? '—' ) ); ?></td>
							<?php if ( $can_view_contacts ) : ?>
								<td data-label="<?php echo esc_attr__( 'Email', 'jm-referral-system' ); ?>"><?php echo esc_html( '' !== (string) ( $row['email'] ?? '' ) ? (string) $row['email'] : '—' ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Telephone', 'jm-referral-system' ); ?>"><?php echo esc_html( '' !== (string) ( $row['telephone'] ?? '' ) ? (string) $row['telephone'] : '—' ); ?></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
