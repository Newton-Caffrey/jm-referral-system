<?php
/**
 * Staff portal application shell.
 *
 * @var array<string, mixed> $branding
 * @var array<int, array{id: string, label: string, url: string, current: bool}> $nav_items
 * @var string $display_name
 * @var string $role_label
 * @var string $logout_url
 * @var string $page_title
 * @var array<int, array{label: string, url: string}> $breadcrumbs
 * @var string $content_template
 * @var array<string, mixed> $view
 * @var bool $show_alerts_indicator
 * @var int $alert_indicator_count
 * @var string $current_route
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$branding              = is_array( $branding ?? null ) ? $branding : array();
$nav_items             = is_array( $nav_items ?? null ) ? $nav_items : array();
$breadcrumbs           = is_array( $breadcrumbs ?? null ) ? $breadcrumbs : array();
$view                  = is_array( $view ?? null ) ? $view : array();
$portal_name           = (string) ( $branding['portal_name'] ?? 'Portal' );
$company_name          = (string) ( $branding['company_name'] ?? '' );
$logo_url              = (string) ( $branding['logo_url'] ?? '' );
$support_email         = (string) ( $branding['support_email'] ?? '' );
$support_phone         = (string) ( $branding['support_phone'] ?? '' );
$display_name          = (string) ( $display_name ?? '' );
$role_label            = (string) ( $role_label ?? '' );
$logout_url            = (string) ( $logout_url ?? '' );
$page_title            = (string) ( $page_title ?? '' );
$content_template      = (string) ( $content_template ?? '' );
$show_alerts_indicator = ! empty( $show_alerts_indicator );
$alert_indicator_count = isset( $alert_indicator_count ) ? absint( $alert_indicator_count ) : 0;
$document_title        = $page_title !== '' ? $page_title . ' — ' . $portal_name : $portal_name;

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $document_title ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="jmrs-portal-body">
<div class="jmrs-portal" id="jmrs-portal-root">
	<a class="jmrs-portal-skip" href="#jmrs-portal-main"><?php echo esc_html__( 'Skip to content', 'jm-referral-system' ); ?></a>

	<div class="jmrs-portal-shell">
		<button
			type="button"
			class="jmrs-portal-nav-toggle"
			id="jmrs-portal-nav-toggle"
			aria-controls="jmrs-portal-sidebar"
			aria-expanded="false"
		>
			<span class="jmrs-portal-nav-toggle__bars" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php echo esc_html__( 'Menu', 'jm-referral-system' ); ?></span>
		</button>
		<div class="jmrs-portal-backdrop" id="jmrs-portal-backdrop" hidden></div>

		<aside class="jmrs-portal-sidebar" id="jmrs-portal-sidebar" aria-label="<?php echo esc_attr__( 'Portal navigation', 'jm-referral-system' ); ?>">
			<div class="jmrs-portal-brand">
				<?php if ( '' !== $logo_url ) : ?>
					<img class="jmrs-portal-brand__logo" src="<?php echo esc_url( $logo_url ); ?>" alt="" />
				<?php endif; ?>
				<div class="jmrs-portal-brand__text">
					<span class="jmrs-portal-brand__name"><?php echo esc_html( $portal_name ); ?></span>
					<?php if ( '' !== $company_name ) : ?>
						<span class="jmrs-portal-brand__company"><?php echo esc_html( $company_name ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<nav class="jmrs-portal-nav" aria-label="<?php echo esc_attr__( 'Primary', 'jm-referral-system' ); ?>">
				<ul class="jmrs-portal-nav__list">
					<?php foreach ( $nav_items as $item ) : ?>
						<?php
						$is_current = ! empty( $item['current'] );
						$item_url   = (string) ( $item['url'] ?? '#' );
						$item_label = (string) ( $item['label'] ?? '' );
						?>
						<li class="jmrs-portal-nav__item<?php echo $is_current ? ' is-current' : ''; ?>">
							<a
								href="<?php echo esc_url( $item_url ); ?>"
								<?php echo $is_current ? ' aria-current="page"' : ''; ?>
							><?php echo esc_html( $item_label ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<?php if ( '' !== $support_email || '' !== $support_phone ) : ?>
				<div class="jmrs-portal-support">
					<span class="jmrs-portal-support__label"><?php echo esc_html__( 'Support', 'jm-referral-system' ); ?></span>
					<?php if ( '' !== $support_phone ) : ?>
						<a href="<?php echo esc_url( 'tel:' . preg_replace( '/\s+/', '', $support_phone ) ); ?>"><?php echo esc_html( $support_phone ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $support_email ) : ?>
						<a href="<?php echo esc_url( 'mailto:' . $support_email ); ?>"><?php echo esc_html( $support_email ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</aside>

		<div class="jmrs-portal-main-wrap">
			<header class="jmrs-portal-topbar">
				<div class="jmrs-portal-topbar__title-block">
					<?php if ( ! empty( $breadcrumbs ) ) : ?>
						<nav class="jmrs-portal-breadcrumbs" aria-label="<?php echo esc_attr__( 'Breadcrumb', 'jm-referral-system' ); ?>">
							<ol>
								<?php foreach ( $breadcrumbs as $crumb ) : ?>
									<?php
									$crumb_label = (string) ( $crumb['label'] ?? '' );
									$crumb_url   = (string) ( $crumb['url'] ?? '' );
									?>
									<li>
										<?php if ( '' !== $crumb_url ) : ?>
											<a href="<?php echo esc_url( $crumb_url ); ?>"><?php echo esc_html( $crumb_label ); ?></a>
										<?php else : ?>
											<span aria-current="page"><?php echo esc_html( $crumb_label ); ?></span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ol>
						</nav>
					<?php endif; ?>
					<h1 class="jmrs-portal-page-title"><?php echo esc_html( $page_title ); ?></h1>
				</div>

				<div class="jmrs-portal-topbar__actions">
					<?php if ( $show_alerts_indicator ) : ?>
						<span class="jmrs-portal-alert-indicator" title="<?php echo esc_attr__( 'Operational alerts', 'jm-referral-system' ); ?>">
							<span class="jmrs-portal-alert-indicator__label"><?php echo esc_html__( 'Alerts', 'jm-referral-system' ); ?></span>
							<?php if ( $alert_indicator_count > 0 ) : ?>
								<span class="jmrs-portal-alert-indicator__count"><?php echo esc_html( (string) $alert_indicator_count ); ?></span>
							<?php endif; ?>
						</span>
					<?php endif; ?>

					<div class="jmrs-portal-user">
						<span class="jmrs-portal-user__name"><?php echo esc_html( $display_name ); ?></span>
						<?php if ( '' !== $role_label ) : ?>
							<span class="jmrs-portal-user__role"><?php echo esc_html( $role_label ); ?></span>
						<?php endif; ?>
					</div>

					<?php if ( '' !== $logout_url ) : ?>
						<a class="jmrs-portal-logout" href="<?php echo esc_url( $logout_url ); ?>"><?php echo esc_html__( 'Log out', 'jm-referral-system' ); ?></a>
					<?php endif; ?>
				</div>
			</header>

			<main class="jmrs-portal-main" id="jmrs-portal-main" tabindex="-1">
				<?php
				if ( '' !== $content_template && is_readable( $content_template ) ) {
					extract( $view, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- prepared view model.
					include $content_template;
				}
				?>
			</main>
		</div>
	</div>
</div>
<?php wp_footer(); ?>
</body>
</html>
