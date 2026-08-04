<?php
/**
 * Edit visit schedule template.
 *
 * @var array<string, mixed> $schedule
 * @var array<string, mixed> $referral
 * @var array<string, mixed> $schedule_data
 * @var array<string, string> $errors
 * @var array<string, string> $repeat_labels
 * @var array<string, string> $status_labels
 * @var array<string, string> $weekday_labels
 * @var array<int, array{id: int, label: string}> $team_options
 * @var string $back_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral_id     = absint( $referral['id'] ?? 0 );
$schedule_id     = absint( $schedule['id'] ?? 0 );
$referral_number = (string) ( $referral['referral_number'] ?? '' );
$client_name     = (string) ( $referral['client_name'] ?? '' );
$schedule_data   = is_array( $schedule_data ?? null ) ? $schedule_data : array();
$errors          = is_array( $errors ?? null ) ? $errors : array();
$repeat_labels   = is_array( $repeat_labels ?? null ) ? $repeat_labels : array();
$status_labels   = is_array( $status_labels ?? null ) ? $status_labels : array();
$weekday_labels  = is_array( $weekday_labels ?? null ) ? $weekday_labels : array();
$team_options    = is_array( $team_options ?? null ) ? $team_options : array();
$selected_days   = is_array( $schedule_data['days_of_week'] ?? null ) ? $schedule_data['days_of_week'] : array();

$jmrs_schedule_value = static function ( string $key ) use ( $schedule_data ): string {
	$value = $schedule_data[ $key ] ?? '';
	return is_array( $value ) ? '' : (string) $value;
};
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Edit Schedule', 'jm-referral-system' ); ?></h1>

	<p>
		<a href="<?php echo esc_url( $back_url ); ?>">
			<?php echo esc_html__( '← Back to Referral', 'jm-referral-system' ); ?>
		</a>
	</p>

	<p>
		<strong><?php echo esc_html__( 'Referral', 'jm-referral-system' ); ?>:</strong>
		<?php
		echo esc_html(
			trim(
				$referral_number . ( '' !== $client_name ? ' — ' . $client_name : '' )
			)
		);
		?>
	</p>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="notice notice-error">
			<p><?php echo esc_html__( 'Please fix the following errors:', 'jm-referral-system' ); ?></p>
			<ul>
				<?php foreach ( $errors as $message ) : ?>
					<li><?php echo esc_html( $message ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'jmrs_save_schedule_' . $referral_id, 'jmrs_schedule_nonce' ); ?>
		<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
		<input type="hidden" name="jmrs_schedule_id" value="<?php echo esc_attr( (string) $schedule_id ); ?>" />
		<input type="hidden" name="jmrs_schedule_care_plan_id" value="<?php echo esc_attr( $jmrs_schedule_value( 'care_plan_id' ) ); ?>" />

		<?php include JMRS_PLUGIN_PATH . 'templates/schedules/form-fields.php'; ?>

		<?php
		submit_button(
			__( 'Save Schedule', 'jm-referral-system' ),
			'primary',
			'jmrs_save_schedule'
		);
		?>
	</form>
</div>
