<?php
/**
 * Footer.
 *
 * @package Tempus
 */
?>
<footer class="tz-footer">
	<div class="tz-footer__inner">

		<div>
			<?php
			$footer_logo = tempus_field( 'footer_logo' );
			if ( $footer_logo ) {
				echo '<img class="tz-footer__logo" src="' . esc_url( $footer_logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
			} else {
				echo '<span style="font-family:var(--font-display);font-size:32px;letter-spacing:.18em;color:var(--white)">TEMPUS</span>';
			}
			?>
		</div>

		<div>
			<div class="tz-footer__col-title">Shop</div>
			<div class="tz-footer__links">
				<?php
				if ( has_nav_menu( 'footer-shop' ) ) {
					wp_nav_menu( array( 'theme_location' => 'footer-shop', 'container' => false, 'items_wrap' => '%3$s' ) );
				} else {
					$shop = wc_get_page_permalink( 'shop' );
					echo '<a href="' . esc_url( $shop ) . '">All Products</a>';
					echo '<a href="' . esc_url( get_term_link( 'spirits', 'product_cat' ) ) . '">Spirits</a>';
					echo '<a href="' . esc_url( get_term_link( 'cigars', 'product_cat' ) ) . '">Cigars</a>';
					echo '<a href="' . esc_url( get_term_link( 'vinyl', 'product_cat' ) ) . '">Vinyl</a>';
				}
				?>
			</div>
		</div>

		<div>
			<div class="tz-footer__col-title">Explore</div>
			<div class="tz-footer__links">
				<?php
				if ( has_nav_menu( 'footer-explore' ) ) {
					wp_nav_menu( array( 'theme_location' => 'footer-explore', 'container' => false, 'items_wrap' => '%3$s' ) );
				} else {
					echo '<a href="#rituals">Rituals</a><a href="#">New Arrivals</a><a href="#about">About</a><a href="#">The Lounge</a>';
				}
				?>
			</div>
		</div>

		<div>
			<div class="tz-footer__col-title">Connect</div>
			<div class="tz-footer__links">
				<?php
				if ( has_nav_menu( 'footer-connect' ) ) {
					wp_nav_menu( array( 'theme_location' => 'footer-connect', 'container' => false, 'items_wrap' => '%3$s' ) );
				} else {
					echo '<a href="#">Reserve</a><a href="#">Inquiries</a><a href="#">Instagram</a><a href="#">Newsletter</a>';
				}
				?>
			</div>
		</div>
	</div>

	<div class="tz-footer__legal">
		<span>&copy; <?php echo esc_html( date( 'Y' ) ); ?> Tempus. Please savor responsibly. 21+.</span>
		<span>Time well spent. The art of slowing down.</span>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
