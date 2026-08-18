<?php
/**
 * Plugin Name:       Tempus Core
 * Plugin URI:        https://tempusrituals.com
 * Description:       Core functionality for Tempus — Whisky, Cigar & Vinyl. Product taxonomy seeding, homepage ACF fields, WooCommerce badges & shop tweaks, and the age-gate hook point. Presentation lives in the Tempus Kadence child theme.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Dindo Ballecer
 * Author URI:        https://tempusrituals.com
 * License:           GNU General Public License v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tempus-core
 * WC requires at least: 8.0
 * WC tested up to:   9.0
 *
 * @package Tempus_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'TEMPUS_CORE_VERSION', '1.0.0' );
define( 'TEMPUS_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'TEMPUS_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * The legacy standalone "tempus" theme bundles this same functionality in
 * its /inc directory. While that theme is still active, loading our copies
 * would redeclare its functions and double-fire its hooks — so the plugin
 * stands down and lets the theme win. Once the site has switched to the
 * Tempus Kadence child theme, the plugin takes over (and the legacy theme
 * can be deleted).
 *
 * @return bool
 */
function tempus_core_legacy_theme_active() {
	return 'tempus' === get_template();
}

if ( ! tempus_core_legacy_theme_active() ) {
	require_once TEMPUS_CORE_DIR . 'includes/helpers.php';
	require_once TEMPUS_CORE_DIR . 'includes/taxonomy.php';
	require_once TEMPUS_CORE_DIR . 'includes/woocommerce.php';
	require_once TEMPUS_CORE_DIR . 'includes/product-page.php';
	require_once TEMPUS_CORE_DIR . 'includes/homepage-pattern.php';
	require_once TEMPUS_CORE_DIR . 'includes/blocks.php';
}

/**
 * Seed the catalog structure on plugin activation, mirroring what the
 * legacy theme did on theme switch. Safe to re-run.
 */
function tempus_core_activate() {
	require_once TEMPUS_CORE_DIR . 'includes/taxonomy.php';
	if ( function_exists( 'tempus_seed_taxonomy' ) ) {
		tempus_seed_taxonomy();
	}
}
register_activation_hook( __FILE__, 'tempus_core_activate' );

/**
 * Declare WooCommerce HPOS (custom order tables) compatibility — we never
 * touch order storage directly.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_filter( 'kadence_blocks_google_fonts_array', 'tempus_register_custom_fonts' );
add_filter( 'kadence_theme_google_fonts_array', 'tempus_register_custom_fonts' );

function tempus_register_custom_fonts( $fonts ) {
	$fonts['Maharlika'] = array(
		'label'    => 'Maharlika',
		'variants' => array( '400' ),
	);
	$fonts['Proxima Nova Condensed'] = array(
		'label'    => 'Proxima Nova Condensed',
		'variants' => array( '400', '700' ),
	);
	return $fonts;
}