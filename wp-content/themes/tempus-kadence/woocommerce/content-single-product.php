<?php
/**
 * Tempus — Single product content
 *
 * Overrides woocommerce/templates/content-single-product.php
 * WHERE THIS GOES: kadence-child/woocommerce/content-single-product.php
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * Only the HERO is rebuilt here. Everything below it (spec bar, tasting
 * notes, ritual card, related products) still renders through the normal
 * WooCommerce hooks, so tempus-core/inc/product-page.php keeps working
 * unchanged.
 *
 * Every do_action() call below is deliberate — third-party plugins hook
 * into these, and the age-verification and payment-proof work will too.
 * Do not remove them to tidy the file up.
 *
 * @version 3.6.0
 * @see WooCommerce → Status → Templates — if this file is ever flagged as
 *      outdated, diff it against the plugin original and merge by hand.
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( post_password_required() ) {
	echo get_the_password_form(); // WPCS: XSS ok.
	return;
}
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'tempus-product', $product ); ?>>

	<?php
	/**
	 * Fires notices, and anything plugins attach before the product.
	 * @hooked woocommerce_output_all_notices - 10
	 */
	do_action( 'woocommerce_before_single_product' );
	?>

	<section class="tempus-hero">
		<div class="tempus-hero-inner">

			<div class="tempus-hero-media">
				<?php
				/**
				 * The default gallery is unhooked in product-page.php, so this
				 * fires the sale flash and any plugin output, then we print our
				 * own image markup below.
				 *
				 * @hooked woocommerce_show_product_sale_flash - 10
				 * @hooked woocommerce_show_product_images     - 20 (REMOVED)
				 */
				do_action( 'woocommerce_before_single_product_summary' );

				$tempus_image_id = $product->get_image_id();
				?>
				<figure class="tempus-hero-figure">
					<?php
					if ( $tempus_image_id ) {
						echo wp_get_attachment_image(
							$tempus_image_id,
							'woocommerce_single',
							false,
							array(
								'class' => 'tempus-hero-image',
								'alt'   => esc_attr( $product->get_name() ),
							)
						);
					} else {
						echo wc_placeholder_img( 'woocommerce_single' ); // phpcs:ignore
					}
					?>
				</figure>

				<?php
				// Optional thumbnail strip — only renders if the product has
				// gallery images. Safe to delete if you never use them.
				$tempus_gallery_ids = $product->get_gallery_image_ids();

				if ( $tempus_gallery_ids ) : ?>
					<ul class="tempus-hero-thumbs">
						<?php foreach ( $tempus_gallery_ids as $tempus_gallery_id ) : ?>
							<li>
								<?php
								echo wp_get_attachment_image(
									$tempus_gallery_id,
									'woocommerce_gallery_thumbnail',
									false,
									array( 'alt' => '' )
								);
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="summary entry-summary tempus-hero-detail">
				<?php
				/**
				 * Everything in the right-hand column, in this order:
				 *
				 * @hooked tempus_product_eyebrow                      - 4
				 * @hooked woocommerce_template_single_title           - 5
				 * @hooked woocommerce_template_single_excerpt         - 9
				 * @hooked woocommerce_template_single_price           - 10
				 * @hooked woocommerce_template_single_add_to_cart     - 30
				 * @hooked tempus_stock_line                           - 31
				 *
				 * Note add_to_cart stays a hook call — it handles quantity,
				 * stock validation, nonces and variable products. Do not
				 * hand-roll the cart form.
				 */
				do_action( 'woocommerce_single_product_summary' );
				?>
			</div>

		</div><!-- .tempus-hero-inner -->
	</section><!-- .tempus-hero -->

	<?php
	/**
	 * Everything below the hero, unchanged from before.
	 *
	 * @hooked tempus_spec_bar          - 5
	 * @hooked tempus_notes_and_ritual  - 8
	 * @hooked tempus_related_open      - 19
	 * @hooked woocommerce_upsell_display / output_related_products - 15/20
	 * @hooked tempus_related_close     - 21
	 */
	do_action( 'woocommerce_after_single_product_summary' );
	?>

	<?php do_action( 'woocommerce_after_single_product' ); ?>

</div>
