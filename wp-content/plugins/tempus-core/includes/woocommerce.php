<?php
/**
 * WooCommerce integration for Tempus.
 *
 * @package Tempus_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'tempus_product_badge' ) ) {
	/**
	 * Render a Rare/Limited badge on shop + single product, driven by the
	 * product tags seeded in taxonomy.php. Keeps badge logic in one place so
	 * both the WooCommerce loop and the homepage cards agree.
	 *
	 * Markup uses the `tz-badge` classes styled by the theme's main.css.
	 *
	 * @param int|WC_Product $product Product or ID.
	 * @return string Badge HTML or empty string.
	 */
	function tempus_product_badge( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
		if ( ! $product ) {
			return '';
		}
		$id = $product->get_id();

		if ( has_term( 'Limited', 'product_tag', $id ) ) {
			return '<span class="tz-badge tz-badge--foil">Limited</span>';
		}
		if ( has_term( 'Rare', 'product_tag', $id ) ) {
			return '<span class="tz-badge tz-badge--gold">Rare</span>';
		}
		if ( has_term( 'New Arrival', 'product_tag', $id ) ) {
			return '<span class="tz-badge tz-badge--gold">New</span>';
		}
		return '';
	}
}

if ( ! function_exists( 'tempus_loop_badge' ) ) {
	/**
	 * Echo the badge inside the WooCommerce product loop thumbnail.
	 */
	function tempus_loop_badge() {
		global $product;
		$badge = tempus_product_badge( $product );
		if ( $badge ) {
			echo '<div class="tz-card__badge">' . wp_kses_post( $badge ) . '</div>';
		}
	}
}
add_action( 'woocommerce_before_shop_loop_item_title', 'tempus_loop_badge', 8 );

/**
 * Products-per-row on shop archives (matches the 4-up cellar grid).
 */
add_filter( 'loop_shop_columns', function () { return 4; } );

/**
 * Products per page.
 */
add_filter( 'loop_shop_per_page', function () { return 12; } );

/**
 * ---------------------------------------------------------------
 * AGE VERIFICATION HOOK POINT (compliance — required before launch)
 * ---------------------------------------------------------------
 * The mockups don't include an age gate, but the PID requires an entry
 * popup + checkout confirmation for alcohol/tobacco. Implement site-wide.
 *
 * Recommended: a dedicated plugin (e.g. "Age Verification for
 * WooCommerce") so the gate is server-enforced and audit-friendly.
 * If building here instead, render the modal on wp_footer and enforce a
 * mandatory checkbox at checkout via woocommerce_checkout_process.
 *
 * Left as a documented stub so it is not forgotten.
 */
function tempus_core_age_gate_placeholder() {
	// Intentionally empty. Wire up the chosen age-verification method here.
}
add_action( 'wp_footer', 'tempus_core_age_gate_placeholder' );
