<?php
/**
 * Header + sticky navigation.
 *
 * @package Tempus
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="tz-header">
	<div class="tz-header__inner">

		<?php
		// Use the Customizer custom logo if set; else a text wordmark.
		if ( has_custom_logo() ) {
			the_custom_logo();
		} else {
			echo '<a href="' . esc_url( home_url( '/' ) ) . '" class="tz-logo" style="font-family:var(--font-display);font-size:24px;letter-spacing:.2em;color:var(--white)">TEMPUS</a>';
		}
		?>

		<nav class="tz-nav" aria-label="<?php esc_attr_e( 'Primary', 'tempus' ); ?>">
			<?php
			// If a "primary" menu is assigned in Appearance → Menus, use it.
			// Otherwise fall back to the static structure from the mockup so
			// the theme looks right immediately on activation.
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'items_wrap'     => '%3$s',
					'walker'         => '', // default; style via .tz-nav a in CSS if desired
				) );
			} else {
				?>
				<a class="tz-nav__link is-active" href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>

				<div class="tz-nav__item">
					<a class="tz-nav__link" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Shop ▾</a>
					<div class="tz-dropdown">
						<div class="tz-dropdown__header">Curated selections for every moment</div>
						<a class="tz-dropdown__link" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">All Products</a>
						<a class="tz-dropdown__link" href="<?php echo esc_url( get_term_link( 'spirits', 'product_cat' ) ); ?>">Spirits<span>Scotch · Bourbon · Japanese · Irish</span></a>
						<a class="tz-dropdown__link" href="<?php echo esc_url( get_term_link( 'cigars', 'product_cat' ) ); ?>">Cigars<span>Cuban · New World · Philippine</span></a>
						<a class="tz-dropdown__link" href="<?php echo esc_url( get_term_link( 'vinyl', 'product_cat' ) ); ?>">Vinyl<span>Jazz · Blues · Classics</span></a>
						<a class="tz-dropdown__link" href="<?php echo esc_url( get_term_link( 'accessories', 'product_cat' ) ); ?>">Accessories</a>
					</div>
				</div>

				<a class="tz-nav__link" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">New Arrivals</a>

				<div class="tz-nav__item">
					<a class="tz-nav__link" href="#rituals">Rituals ▾</a>
					<div class="tz-dropdown">
						<div class="tz-dropdown__header">Time well spent. Choose your moment</div>
						<a class="tz-dropdown__link" href="#rituals">Unwind<span>Slow down and ease into the moment</span></a>
						<a class="tz-dropdown__link" href="#rituals">Celebrate<span>For nights that deserve more than a drink</span></a>
						<a class="tz-dropdown__link" href="#rituals">Late Night<span>Quiet, deep, and meant to linger</span></a>
					</div>
				</div>

				<a class="tz-nav__link" href="#about">About</a>
				<?php
			}
			?>
		</nav>

		<a class="tz-btn tz-btn--secondary tz-btn--sm" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Shop</a>

		<button class="tz-nav-toggle" aria-label="<?php esc_attr_e( 'Menu', 'tempus' ); ?>">☰</button>
	</div>
</header>
