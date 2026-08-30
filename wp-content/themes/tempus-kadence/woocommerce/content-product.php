<?php
/**
 * Tempus — Product card (used in every product loop)
 *
 * Overrides woocommerce/templates/content-product.php
 * WHERE THIS GOES: kadence-child/woocommerce/content-product.php
 *
 * SCOPE WARNING
 * -------------
 * This template renders the card on the shop archive, category pages,
 * search results AND the related-products grid. Changing it changes all
 * of them. That's intentional — one card design everywhere — but check
 * the shop page after installing, not just the product page.
 *
 * WHY THE HOOKS ARE CALLED DIRECTLY
 * ---------------------------------
 * WooCommerce's original wraps the thumbnail and title in a single <a>
 * that opens on one hook and closes on another. That makes the mockup's
 * panel-inside-a-card structure impossible. So the render functions are
 * called directly here instead, and unhooked in product-page.php.
 *
 * The trade-off: plugins that inject into woocommerce_before_shop_loop_item
 * or woocommerce_after_shop_loop_item won't render. Nothing currently
 * installed uses them. Revisit if that changes.
 *
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

$tempus_permalink = get_permalink( $product->get_id() );
?>
<li <?php wc_product_class( 'tempus-card', $product ); ?>>

	<div class="tempus-card-frame">
		<?php
		// Gold corner chip — driven by the product tag.
		if ( function_exists( 'tempus_limited_badge' ) ) {
			tempus_limited_badge();
		}
		?>
		<a class="tempus-card-media" href="<?php echo esc_url( $tempus_permalink ); ?>" tabindex="-1" aria-hidden="true">
			<?php echo woocommerce_get_product_thumbnail( 'full' ); // phpcs:ignore ?>
		</a>
	</div>

	<div class="tempus-card-body">

		<?php
		if ( function_exists( 'tempus_loop_eyebrow' ) ) {
			tempus_loop_eyebrow();
		}
		?>

		<h2 class="tempus-card-title">
			<a href="<?php echo esc_url( $tempus_permalink ); ?>">
				<?php echo esc_html( $product->get_name() ); ?>
			</a>
		</h2>

		<?php
		if ( function_exists( 'tempus_loop_meta' ) ) {
			tempus_loop_meta();
		}
		?>

		<div class="tempus-card-foot">
			<?php
			woocommerce_template_loop_price();

			// Keep this a function call — it carries the correct URL,
			// ajax class, nonce and quantity handling per product type.
			woocommerce_template_loop_add_to_cart();
			?>
		</div>

	</div>

</li>
