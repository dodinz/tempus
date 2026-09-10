<?php
/*
Plugin Name: WPC Product Bundles for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Product Bundles is a plugin that helps you bundle a few products, offer them at a discount, and watch the sales go up!
Author: WPClever
Author URI: https://wpclever.net
Text Domain: woo-product-bundle
Domain Path: /languages/
Requires Plugins: woocommerce
Version: 8.6.5
Requires at least: 5.9
WC requires at least: 3.0
WC tested up to: 11.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WOOSB_VERSION' ) && define( 'WOOSB_VERSION', '8.6.5' );
! defined( 'WOOSB_LITE' ) && define( 'WOOSB_LITE', __FILE__ );
! defined( 'WOOSB_FILE' ) && define( 'WOOSB_FILE', __FILE__ );
! defined( 'WOOSB_URI' ) && define( 'WOOSB_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WOOSB_DIR' ) && define( 'WOOSB_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WOOSB_DOCS' ) && define( 'WOOSB_DOCS', 'https://doc.wpclever.net/woosb/' );
! defined( 'WOOSB_SUPPORT' ) && define( 'WOOSB_SUPPORT', 'https://wpclever.net/support/?utm_source=support&utm_medium=woosb&utm_campaign=wporg' );
! defined( 'WOOSB_REVIEWS' ) && define( 'WOOSB_REVIEWS', 'https://wordpress.org/support/plugin/woo-product-bundle/reviews/' );
! defined( 'WOOSB_CHANGELOG' ) && define( 'WOOSB_CHANGELOG', 'https://wordpress.org/plugins/woo-product-bundle/#developers' );
! defined( 'WOOSB_DISCUSSION' ) && define( 'WOOSB_DISCUSSION', 'https://wordpress.org/support/plugin/woo-product-bundle' );

// WPC Core
require_once __DIR__ . '/includes/wpc-core/wpc-core.php';
wpc_core_register( [
        'file'    => __FILE__,
        'version' => WOOSB_VERSION,
        'prefix'  => 'woosb',
] );

if ( ! function_exists( 'woosb_init' ) ) {
    add_action( 'plugins_loaded', 'woosb_init', 12 );

    function woosb_init() {
        if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
            add_action( 'admin_notices', 'woosb_notice_wc' );

            return null;
        }

        include_once 'includes/class-helper.php';
        include_once 'includes/class-product.php';
        include_once 'includes/class-woosb.php';
        include_once 'includes/class-blocks.php';
        include_once 'includes/class-compatible.php';
        include_once 'includes/class-backend.php';

        // start
        WPCleverWoosb();
        WPCleverWoosb_Backend();
    }
}

if ( ! function_exists( 'woosb_notice_wc' ) ) {
    function woosb_notice_wc() {
        ?>
        <div class="error">
            <p><strong>WPC Product Bundles</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
        <?php
    }
}
