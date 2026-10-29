<?php
/**
 * Add Existing Residents — overview (Phase 5F.1).
 *
 * @package JMReferral
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$new_url          = (string) ( $new_url ?? '' );
$import_url       = (string) ( $import_url ?? '' );
$homes_url        = (string) ( $homes_url ?? '' );
$properties       = is_array( $properties ?? null ) ? $properties : array();
$supported_living = ! empty( $supported_living );
?>
<section class="jmrs-portal-section jmrs-er-overview" aria-labelledby="jmrs-er-overview-heading">
	<?php
	$section_title   = __( 'Residents already in your care', 'jm-referral-system' );
	$section_id      = 'jmrs-er-overview-heading';
	$section_badge   = '';
	$section_actions = array();
	include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
	?>

	<p class="jmrs-er-intro">
		<?php echo esc_html__( 'Use this for people who already receive care and support from you. They are added straight to Care Started with their real admission and care start dates. No referral, assessment, costing or Local Authority decision steps are created, and they are not counted in referral reports.', 'jm-referral-system' ); ?>
	</p>
	<p class="jmrs-er-intro">
		<?php echo esc_html__( 'For someone who has not started with you yet, create a referral as usual.', 'jm-referral-system' ); ?>
	</p>

	<div class="jmrs-er-choices">
		<div class="jmrs-portal-summary-card jmrs-er-choice">
			<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Add one resident', 'jm-referral-system' ); ?></h3>
			<p><?php echo esc_html__( 'Fill in a form for a single person.', 'jm-referral-system' ); ?></p>
			<p><a class="jmrs-button jmrs-button--primary" href="<?php echo esc_url( $new_url ); ?>"><?php echo esc_html__( 'Add One Resident', 'jm-referral-system' ); ?></a></p>
		</div>
		<div class="jmrs-portal-summary-card jmrs-er-choice">
			<h3 class="jmrs-portal-subsection__title"><?php echo esc_html__( 'Import many residents', 'jm-referral-system' ); ?></h3>
			<p><?php echo esc_html__( 'Fill in the spreadsheet template and upload it. Every row is checked and shown to you before anything is saved.', 'jm-referral-system' ); ?></p>
			<p><a class="jmrs-button jmrs-button--primary" href="<?php echo esc_url( $import_url ); ?>"><?php echo esc_html__( 'Import from Spreadsheet', 'jm-referral-system' ); ?></a></p>
		</div>
	</div>
</section>

<?php if ( $supported_living ) : ?>
	<section class="jmrs-portal-section" aria-labelledby="jmrs-er-properties-heading">
		<?php
		$section_title   = __( 'Properties and free bedrooms', 'jm-referral-system' );
		$section_id      = 'jmrs-er-properties-heading';
		$section_badge   = '';
		$section_actions = '' !== $homes_url ? array( array( __( 'Manage Homes', 'jm-referral-system' ), $homes_url ) ) : array();
		include JMRS_PLUGIN_PATH . 'templates/portal/partials/section-header.php';
		?>
		<p class="jmrs-portal-muted">
			<?php echo esc_html__( 'Set up every property and bedroom before adding residents. In the spreadsheet, type property and bedroom names exactly as shown here.', 'jm-referral-system' ); ?>
		</p>

		<?php if ( empty( $properties ) ) : ?>
			<div class="jmrs-portal-empty">
				<p class="jmrs-portal-empty__title"><?php echo esc_html__( 'No active properties yet', 'jm-referral-system' ); ?></p>
				<p><?php echo esc_html__( 'Add your properties and bedrooms under Homes first.', 'jm-referral-system' ); ?></p>
			</div>
		<?php else : ?>
			<div class="jmrs-portal-table-wrap">
				<table class="jmrs-portal-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Property', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Free bedrooms', 'jm-referral-system' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Residents on the platform', 'jm-referral-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $properties as $property ) : ?>
							<tr>
								<td><?php echo esc_html( (string) ( $property['name'] ?? '' ) ); ?></td>
								<td>
									<?php
									$vacant = is_array( $property['vacant'] ?? null ) ? $property['vacant'] : array();
									echo esc_html( empty( $vacant ) ? __( 'None free', 'jm-referral-system' ) : implode( ', ', $vacant ) );
									?>
								</td>
								<td><?php echo esc_html( (string) absint( $property['occupied'] ?? 0 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>
