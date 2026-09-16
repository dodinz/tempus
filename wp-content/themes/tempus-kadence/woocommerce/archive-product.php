<?php
/**
 * Tempus — Product archive (shop + product taxonomy archives)
 *
 * Overrides woocommerce/templates/archive-product.php
 *
 * BASE TEMPLATE
 * -------------
 * Kadence does not ship its own archive-product.php. It renders WooCommerce's
 * default template and injects its layout through hooks (see
 * kadence/inc/components/woocommerce/component.php):
 *   woocommerce_before_main_content → #primary / .site-container / main + archive header
 *   woocommerce_after_main_content  → closes main, sidebar, closes containers
 * So this file mirrors the WooCommerce default hook-for-hook. Never drop the
 * before/after main content actions — they ARE Kadence's layout wrapper.
 *
 * BRANCHING
 * ---------
 * /shop           → Tempus category chooser (no product loop).
 * everything else → standard WooCommerce loop, unchanged.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package Tempus_Kadence
 * @version 8.6.0
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

/**
 * CRITICAL: opens Kadence's content wrapper. Removing it breaks page width,
 * sidebar and header spacing on every product archive.
 */
do_action( 'woocommerce_before_main_content' );

// The chooser needs the Tempus Core data layer; without it, fall back to the
// normal loop so /shop never renders empty.
$tempus_show_chooser = is_shop() && ! is_search() && function_exists( 'tempus_get_shop_categories' );

if ( $tempus_show_chooser ) {

	wc_get_template( 'tempus/collection-header.php' );
	wc_get_template(
		'tempus/category-grid.php',
		array(
			'categories' => tempus_get_shop_categories(),
		)
	);

} else {

	/**
	 * Hook: woocommerce_shop_loop_header.
	 *
	 * @hooked woocommerce_product_taxonomy_archive_header - 10
	 */
	do_action( 'woocommerce_shop_loop_header' );

	if ( woocommerce_product_loop() ) {

		/**
		 * Hook: woocommerce_before_shop_loop.
		 *
		 * @hooked woocommerce_output_all_notices - 10
		 * @hooked Kadence archive_loop_top (result count + ordering) - 20
		 */
		do_action( 'woocommerce_before_shop_loop' );

		woocommerce_product_loop_start();

		if ( wc_get_loop_prop( 'total' ) ) {
			while ( have_posts() ) {
				the_post();

				/**
				 * Hook: woocommerce_shop_loop.
				 */
				do_action( 'woocommerce_shop_loop' );

				wc_get_template_part( 'content', 'product' );
			}
		}

		woocommerce_product_loop_end();

		/**
		 * Hook: woocommerce_after_shop_loop.
		 *
		 * @hooked woocommerce_pagination - 10
		 */
		do_action( 'woocommerce_after_shop_loop' );
	} else {
		/**
		 * Hook: woocommerce_no_products_found.
		 *
		 * @hooked wc_no_products_found - 10
		 */
		do_action( 'woocommerce_no_products_found' );
	}
}

/**
 * CRITICAL: closes Kadence's content wrapper (and prints its sidebar).
 */
do_action( 'woocommerce_after_main_content' );

/**
 * Hook: woocommerce_sidebar.
 *
 * @hooked woocommerce_get_sidebar - 10 (removed by Kadence)
 */
do_action( 'woocommerce_sidebar' );

get_footer( 'shop' );
