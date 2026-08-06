<?php
/**
 * Portal schedule visit generation form.
 *
 * Field names match admin so ScheduleController::attempt_generate() can be reused.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$errors      = is_array( $errors ?? null ) ? $errors : array();
$schedule    = is_array( $schedule ?? null ) ? $schedule : array();
$referral    = is_array( $referral ?? null ) ? $referral : array();
$referral_id = absint( $referral['id'] ?? 0 );
$schedule_id = absint( $schedule['id'] ?? 0 );
$start_date  = (string) ( $start_date ?? '' );
$end_date    = (string) ( $end_date ?? '' );
$form_action = (string) ( $form_action ?? '' );
$cancel_url  = (string) ( $cancel_url ?? '' );

$field_error = static function ( array $errors, string $key ): void {
	if ( ! isset( $errors[ $key ] ) ) {
		return;
	}
	echo '<p class="jmrs-portal-field-error">' . esc_html( (string) $errors[ $key ] ) . '</p>';
};
?>
<?php if ( ! empty( $errors ) ) : ?>
	<div class="jmrs-portal-notice jmrs-portal-notice--error" role="alert">
		<p><strong><?php echo esc_html__( 'Please fix the following errors:', 'jm-referral-system' ); ?></strong></p>
		<ul>
			<?php foreach ( $errors as $message ) : ?>
				<li><?php echo esc_html( (string) $message ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<section class="jmrs-portal-section">
	<h2 class="jmrs-portal-section__title"><?php echo esc_html( (string) ( $schedule['schedule_name'] ?? '' ) ); ?></h2>
	<p class="jmrs-portal-muted">
		<?php echo esc_html__( 'Generates care visits for this schedule within the selected date range. Existing visits and occurrences outside the schedule range are skipped automatically.', 'jm-referral-system' ); ?>
	</p>

	<form
		class="jmrs-portal-form"
		method="post"
		action="<?php echo esc_url( $form_action ); ?>"
		data-jmrs-confirm="<?php echo esc_attr__( 'Generate visits for this schedule and date range?', 'jm-referral-system' ); ?>"
	>
		<?php wp_nonce_field( 'jmrs_generate_schedule_visits_' . $schedule_id, 'jmrs_generate_schedule_nonce' ); ?>
		<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
		<input type="hidden" name="jmrs_schedule_id" value="<?php echo esc_attr( (string) $schedule_id ); ?>" />

		<div class="jmrs-portal-form-grid">
			<div class="jmrs-portal-field">
				<label for="generation_start_date"><?php echo esc_html__( 'From', 'jm-referral-system' ); ?></label>
				<input type="date" name="generation_start_date" id="generation_start_date" value="<?php echo esc_attr( $start_date ); ?>" required />
				<?php $field_error( $errors, 'generation_start_date' ); ?>
			</div>
			<div class="jmrs-portal-field">
				<label for="generation_end_date"><?php echo esc_html__( 'To', 'jm-referral-system' ); ?></label>
				<input type="date" name="generation_end_date" id="generation_end_date" value="<?php echo esc_attr( $end_date ); ?>" required />
				<?php $field_error( $errors, 'generation_end_date' ); ?>
			</div>
		</div>

		<p class="jmrs-portal-actions">
			<button type="submit" name="jmrs_generate_schedule_visits" value="1" class="jmrs-button jmrs-button--primary">
				<?php echo esc_html__( 'Generate Visits', 'jm-referral-system' ); ?>
			</button>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
				<?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?>
			</a>
		</p>
	</form>
</section>
