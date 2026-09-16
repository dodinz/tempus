<?php
/**
 * Tempus — Bottle card. ONE card for every product grid.
 *
 * Rendered by the Featured Bottles block (tempus-core/blocks/featured-bottles)
 * and by content-product.php (category/tag archives, search, related
 * products, cross-sells). Change the card here and every grid follows.
 * Styles: the PRODUCT GRID / CARDS section of assets/css/main.css.
 *
 * The variable is $bottle, not $product: wc_get_template() extracts args
 * into local scope, and $product would shadow WooCommerce's global.
 *
 * @package Tempus_Kadence
 *
 * @var WC_Product $bottle      Product to render.
 * @var string     $extra_class Optional extra classes on the card (e.g. the block's tz-reveal).
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $bottle ) || ! $bottle instanceof WC_Product || ! $bottle->is_visible() ) {
	return;
}

$tempus_data = function_exists( 'tempus_get_bottle_card_data' )
	? tempus_get_bottle_card_data( $bottle )
	: array( 'badge' => '', 'category' => '', 'meta' => '' );

$tempus_link = $bottle->get_permalink();

// WooCommerce's loop button reads the global $product — point it at this bottle, restore after.
$tempus_prev_product = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
$GLOBALS['product']  = $bottle; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
?>
<div class="tz-card<?php echo empty( $extra_class ) ? '' : ' ' . esc_attr( $extra_class ); ?>">
	<a class="tz-card__media" href="<?php echo esc_url( $tempus_link ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $tempus_data['badge'] ) : ?>
			<div class="tz-card__badge"><?php echo wp_kses_post( $tempus_data['badge'] ); ?></div>
		<?php endif; ?>
		<?php echo $bottle->get_image( 'full', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</a>
	<div class="tz-card__body">
		<?php if ( $tempus_data['category'] ) : ?>
			<span class="tz-card__cat"><?php echo esc_html( $tempus_data['category'] ); ?></span>
		<?php endif; ?>
		<h3 class="tz-card__name"><a href="<?php echo esc_url( $tempus_link ); ?>"><?php echo esc_html( $bottle->get_name() ); ?></a></h3>
		<?php if ( $tempus_data['meta'] ) : ?>
			<p class="tz-card__meta"><?php echo esc_html( $tempus_data['meta'] ); ?></p>
		<?php endif; ?>
		<div class="tz-card__foot">
			<div class="tz-card__price"><?php echo wp_kses_post( $bottle->get_price_html() ); ?></div>
			<div class="tz-card__actions">
				<?php
				// Never hand-build this link — Woo supplies the right URL, AJAX
				// classes and label per product type (classes/label: tempus-core).
				woocommerce_template_loop_add_to_cart();
				?>
			</div>
		</div>
	</div>
</div>
<?php
$GLOBALS['product'] = $tempus_prev_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
