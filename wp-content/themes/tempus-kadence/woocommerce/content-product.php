<?php
/**
 * Tempus — Product loop item → shared bottle card.
 *
 * Overrides woocommerce/templates/content-product.php
 *
 * SCOPE: every WooCommerce product grid — category/tag archives, search,
 * related products and cart cross-sells all render the same card as the
 * homepage Featured Bottles block (woocommerce/tempus/bottle-card.php).
 *
 * The per-item hooks (woocommerce_before_shop_loop_item etc.) are
 * intentionally not fired: they carry Kadence's card chrome, which is what
 * made archive cards look different from the homepage. Plugins that inject
 * into those hooks won't render — nothing installed uses them.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package Tempus_Kadence
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, WC_Product::class ) || ! $product->is_visible() ) {
	return;
}
?>
<li <?php wc_product_class( 'tz-grid__item', $product ); ?>>
	<?php wc_get_template( 'tempus/bottle-card.php', array( 'bottle' => $product ) ); ?>
</li>
