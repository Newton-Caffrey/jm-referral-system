<?php
/**
 * Read-only historical care plan snapshot.
 *
 * @var array<string, mixed> $version
 * @var array<string, mixed> $care_plan
 * @var array<string, mixed> $referral
 * @var array<string, mixed> $snapshot
 * @var string               $created_by_name
 * @var array<string, string> $status_labels
 * @var string               $back_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral_id     = absint( $referral['id'] ?? 0 );
$referral_number = (string) ( $referral['referral_number'] ?? '' );
$client_name     = (string) ( $referral['client_name'] ?? '' );
$version_number  = absint( $version['version_number'] ?? 0 );
$created_at      = (string) ( $version['created_at'] ?? '' );
$change_summary  = (string) ( $version['change_summary'] ?? '' );
$created_display = '' !== $created_at
	? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at )
	: '';

$plan_status       = (string) ( $snapshot['plan_status'] ?? '' );
$plan_status_label = isset( $status_labels[ $plan_status ] )
	? (string) $status_labels[ $plan_status ]
	: ucfirst( str_replace( '_', ' ', $plan_status ) );

$content_fields = array(
	'visit_frequency'         => __( 'Visit Frequency', 'jm-referral-system' ),
	'visit_duration'          => __( 'Visit Duration', 'jm-referral-system' ),
	'preferred_visit_times'   => __( 'Preferred Visit Times', 'jm-referral-system' ),
	'personal_care_tasks'     => __( 'Personal Care Tasks', 'jm-referral-system' ),
	'mobility_support'        => __( 'Mobility Support', 'jm-referral-system' ),
	'medication_support'      => __( 'Medication Support', 'jm-referral-system' ),
	'nutrition_support'       => __( 'Nutrition Support', 'jm-referral-system' ),
	'communication_support'   => __( 'Communication Support', 'jm-referral-system' ),
	'continence_support'      => __( 'Continence Support', 'jm-referral-system' ),
	'social_support'          => __( 'Social Support', 'jm-referral-system' ),
	'equipment_required'      => __( 'Equipment Required', 'jm-referral-system' ),
	'risks_and_safeguards'    => __( 'Risks and Safeguards', 'jm-referral-system' ),
	'goals'                   => __( 'Goals', 'jm-referral-system' ),
	'additional_instructions' => __( 'Additional Instructions', 'jm-referral-system' ),
);
$short_fields = array( 'visit_frequency', 'visit_duration' );

$start_date  = (string) ( $snapshot['start_date'] ?? '' );
$review_date = (string) ( $snapshot['review_date'] ?? '' );
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Care Plan Version Snapshot', 'jm-referral-system' ); ?></h1>

	<p>
		<a href="<?php echo esc_url( $back_url ); ?>">
			<?php echo esc_html__( '← Back to Referral', 'jm-referral-system' ); ?>
		</a>
	</p>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?></th>
				<td>
					<?php
					echo esc_html(
						trim(
							$referral_number . ( '' !== $client_name ? ' — ' . $client_name : '' )
						)
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Version', 'jm-referral-system' ); ?></th>
				<td><?php echo esc_html( (string) $version_number ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Created By', 'jm-referral-system' ); ?></th>
				<td><?php echo '' !== $created_by_name ? esc_html( $created_by_name ) : esc_html__( 'Unknown', 'jm-referral-system' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Created Date', 'jm-referral-system' ); ?></th>
				<td><?php echo esc_html( $created_display ); ?></td>
			</tr>
			<?php if ( '' !== trim( $change_summary ) ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Change Summary', 'jm-referral-system' ); ?></th>
					<td><?php echo esc_html( $change_summary ); ?></td>
				</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Status', 'jm-referral-system' ); ?></th>
				<td><?php echo esc_html( $plan_status_label ); ?></td>
			</tr>
			<?php if ( '' !== trim( $start_date ) ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Start Date', 'jm-referral-system' ); ?></th>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $start_date ) ); ?></td>
				</tr>
			<?php endif; ?>
			<?php if ( '' !== trim( $review_date ) ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Review Date', 'jm-referral-system' ); ?></th>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $review_date ) ); ?></td>
				</tr>
			<?php endif; ?>
			<?php foreach ( $content_fields as $field_key => $field_label ) : ?>
				<?php
				$field_value = (string) ( $snapshot[ $field_key ] ?? '' );
				if ( '' === trim( $field_value ) ) {
					continue;
				}
				$is_short = in_array( $field_key, $short_fields, true );
				?>
				<tr>
					<th scope="row"><?php echo esc_html( (string) $field_label ); ?></th>
					<td>
						<?php
						if ( $is_short ) {
							echo esc_html( $field_value );
						} else {
							echo nl2br( esc_html( $field_value ) );
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
