<?php
/**
 * Portal visit manager review form.
 *
 * Field names match admin so CareVisitController::attempt_review() can be reused.
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data            = is_array( $data ?? null ) ? $data : array();
$errors          = is_array( $errors ?? null ) ? $errors : array();
$task_summaries  = is_array( $task_summaries ?? null ) ? $task_summaries : array();
$outcome_label   = (string) ( $outcome_label ?? '' );
$form_action     = (string) ( $form_action ?? '' );
$cancel_url      = (string) ( $cancel_url ?? '' );
$referral        = is_array( $referral ?? null ) ? $referral : array();
$visit           = is_array( $visit ?? null ) ? $visit : array();
$referral_id     = absint( $referral['id'] ?? 0 );
$visit_id        = absint( $visit['id'] ?? 0 );

$val = static function ( array $data, string $key ): string {
	return (string) ( $data[ $key ] ?? '' );
};

$completed_list   = is_array( $task_summaries['completed'] ?? null ) ? $task_summaries['completed'] : array();
$outstanding_list = is_array( $task_summaries['outstanding'] ?? null ) ? $task_summaries['outstanding'] : array();
$refused_list     = is_array( $task_summaries['refused'] ?? null ) ? $task_summaries['refused'] : array();

$date_time_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
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
	<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Visit Summary', 'jm-referral-system' ); ?></h2>
	<dl class="jmrs-portal-summary">
		<div>
			<dt><?php echo esc_html__( 'Outcome', 'jm-referral-system' ); ?></dt>
			<dd><?php echo esc_html( '' !== $outcome_label ? $outcome_label : '—' ); ?></dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Arrival', 'jm-referral-system' ); ?></dt>
			<dd>
				<?php
				$arrival = (string) ( $visit['arrival_time'] ?? '' );
				echo esc_html( '' !== $arrival ? mysql2date( $date_time_format, $arrival ) : '—' );
				?>
			</dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Departure', 'jm-referral-system' ); ?></dt>
			<dd>
				<?php
				$departure = (string) ( $visit['departure_time'] ?? '' );
				echo esc_html( '' !== $departure ? mysql2date( $date_time_format, $departure ) : '—' );
				?>
			</dd>
		</div>
		<div>
			<dt><?php echo esc_html__( 'Duration', 'jm-referral-system' ); ?></dt>
			<dd>
				<?php
				$duration_minutes = absint( $visit['actual_duration_minutes'] ?? 0 );
				echo esc_html(
					$duration_minutes > 0
						? sprintf(
							/* translators: %d: duration in minutes */
							_n( '%d minute', '%d minutes', $duration_minutes, 'jm-referral-system' ),
							$duration_minutes
						)
						: '—'
				);
				?>
			</dd>
		</div>
	</dl>

	<div class="jmrs-portal-summary-block">
		<p class="jmrs-portal-summary-block__title"><?php echo esc_html__( 'Tasks Completed', 'jm-referral-system' ); ?></p>
		<?php if ( empty( $completed_list ) ) : ?>
			<p class="jmrs-portal-muted">—</p>
		<?php else : ?>
			<ul>
				<?php foreach ( $completed_list as $task_label ) : ?>
					<li><?php echo esc_html( (string) $task_label ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<div class="jmrs-portal-summary-block">
		<p class="jmrs-portal-summary-block__title"><?php echo esc_html__( 'Tasks Outstanding', 'jm-referral-system' ); ?></p>
		<?php if ( empty( $outstanding_list ) ) : ?>
			<p class="jmrs-portal-muted">—</p>
		<?php else : ?>
			<ul>
				<?php foreach ( $outstanding_list as $task_label ) : ?>
					<li><?php echo esc_html( (string) $task_label ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<div class="jmrs-portal-summary-block">
		<p class="jmrs-portal-summary-block__title"><?php echo esc_html__( 'Tasks Refused', 'jm-referral-system' ); ?></p>
		<?php if ( empty( $refused_list ) ) : ?>
			<p class="jmrs-portal-muted">—</p>
		<?php else : ?>
			<ul>
				<?php foreach ( $refused_list as $task_label ) : ?>
					<li><?php echo esc_html( (string) $task_label ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

	<?php if ( '' !== trim( (string) ( $visit['client_response'] ?? '' ) ) ) : ?>
		<div class="jmrs-portal-summary-block">
			<p class="jmrs-portal-summary-block__title"><?php echo esc_html__( 'Client Response', 'jm-referral-system' ); ?></p>
			<p class="jmrs-portal-prose"><?php echo esc_html( (string) $visit['client_response'] ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( '' !== trim( (string) ( $visit['wellbeing_observations'] ?? '' ) ) ) : ?>
		<div class="jmrs-portal-summary-block">
			<p class="jmrs-portal-summary-block__title"><?php echo esc_html__( 'Wellbeing Observations', 'jm-referral-system' ); ?></p>
			<p class="jmrs-portal-prose"><?php echo esc_html( (string) $visit['wellbeing_observations'] ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( '' !== trim( (string) ( $visit['incident_report'] ?? '' ) ) ) : ?>
		<div class="jmrs-portal-summary-block">
			<p class="jmrs-portal-summary-block__title"><?php echo esc_html__( 'Incident Report', 'jm-referral-system' ); ?></p>
			<p class="jmrs-portal-prose"><?php echo esc_html( (string) $visit['incident_report'] ); ?></p>
		</div>
	<?php endif; ?>
</section>

<section class="jmrs-portal-section">
	<h2 class="jmrs-portal-section__title"><?php echo esc_html__( 'Manager Review', 'jm-referral-system' ); ?></h2>
	<form
		class="jmrs-portal-form"
		method="post"
		action="<?php echo esc_url( $form_action ); ?>"
		data-jmrs-confirm="<?php echo esc_attr__( 'Mark this visit as reviewed?', 'jm-referral-system' ); ?>"
	>
		<?php wp_nonce_field( 'jmrs_review_care_visit_' . $visit_id, 'jmrs_review_visit_nonce' ); ?>
		<input type="hidden" name="jmrs_referral_id" value="<?php echo esc_attr( (string) $referral_id ); ?>" />
		<input type="hidden" name="jmrs_visit_id" value="<?php echo esc_attr( (string) $visit_id ); ?>" />

		<div class="jmrs-portal-field jmrs-portal-field--full">
			<label for="jmrs_visit_manager_review_notes"><?php echo esc_html__( 'Manager Review Notes', 'jm-referral-system' ); ?></label>
			<textarea name="jmrs_visit_manager_review_notes" id="jmrs_visit_manager_review_notes" rows="4" required><?php echo esc_textarea( $val( $data, 'manager_review_notes' ) ); ?></textarea>
			<?php if ( isset( $errors['manager_review_notes'] ) ) : ?>
				<p class="jmrs-portal-field-error"><?php echo esc_html( $errors['manager_review_notes'] ); ?></p>
			<?php endif; ?>
		</div>

		<p class="jmrs-portal-actions">
			<button type="submit" name="jmrs_review_care_visit" value="1" class="jmrs-button jmrs-button--primary">
				<?php echo esc_html__( 'Review Visit', 'jm-referral-system' ); ?>
			</button>
			<a class="jmrs-button jmrs-button--secondary" href="<?php echo esc_url( $cancel_url ); ?>">
				<?php echo esc_html__( 'Cancel', 'jm-referral-system' ); ?>
			</a>
		</p>
	</form>
</section>
