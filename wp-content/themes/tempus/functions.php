<?php
/**
 * Tempus theme bootstrap.
 *
 * @package Tempus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'TEMPUS_VERSION', '1.0.0' );
define( 'TEMPUS_DIR', get_template_directory() );
define( 'TEMPUS_URI', get_template_directory_uri() );

/**
 * Theme supports & registrations.
 */
function tempus_setup() {
	// Let WordPress manage the document title.
	add_theme_support( 'title-tag' );

	// Featured images on posts/products.
	add_theme_support( 'post-thumbnails' );

	// Logo in the Customizer (optional; brand supplies real logo files).
	add_theme_support( 'custom-logo', array(
		'height'      => 40,
		'width'       => 160,
		'flex-width'  => true,
		'flex-height' => true,
	) );

	// Output valid HTML5 for core markup.
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );

	// WooCommerce support + product gallery features.
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	// Navigation menus that map to the mockup.
	register_nav_menus( array(
		'primary'      => __( 'Primary Navigation', 'tempus' ),
		'footer-shop'  => __( 'Footer — Shop', 'tempus' ),
		'footer-explore' => __( 'Footer — Explore', 'tempus' ),
		'footer-connect' => __( 'Footer — Connect', 'tempus' ),
	) );
}
add_action( 'after_setup_theme', 'tempus_setup' );

/**
 * Enqueue styles & scripts.
 * tokens.css loads first so main.css can consume the variables.
 */
function tempus_assets() {
	wp_enqueue_style( 'tempus-tokens', TEMPUS_URI . '/assets/css/tokens.css', array(), TEMPUS_VERSION );
	wp_enqueue_style( 'tempus-main', TEMPUS_URI . '/assets/css/main.css', array( 'tempus-tokens' ), TEMPUS_VERSION );

	// style.css (theme header) — kept last, mostly for the header block.
	wp_enqueue_style( 'tempus-style', get_stylesheet_uri(), array( 'tempus-main' ), TEMPUS_VERSION );

	wp_enqueue_script( 'tempus-main', TEMPUS_URI . '/assets/js/main.js', array(), TEMPUS_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'tempus_assets' );

/**
 * Modular includes.
 */
require_once TEMPUS_DIR . '/inc/woocommerce.php';
require_once TEMPUS_DIR . '/inc/taxonomy.php';

// ACF field groups are only registered if ACF (free or Pro) is active.
if ( function_exists( 'acf_add_local_field_group' ) ) {
	require_once TEMPUS_DIR . '/inc/acf-fields.php';
}

/**
 * Small helper: safely echo an ACF field with a fallback,
 * so the theme still renders sensibly before content is entered.
 */
function tempus_field( $selector, $fallback = '', $post_id = false ) {
	if ( function_exists( 'get_field' ) ) {
		$val = get_field( $selector, $post_id );
		if ( ! empty( $val ) ) {
			return $val;
		}
	}
	return $fallback;
}
