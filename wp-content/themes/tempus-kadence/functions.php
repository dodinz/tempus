<?php
/**
 * Tempus Kadence child theme bootstrap.
 *
 * Presentation layer only — site functionality (taxonomy seeding, ACF
 * homepage fields, product badges, shop tweaks) lives in the Tempus Core
 * plugin. Kadence supplies header/footer/nav chrome and WooCommerce
 * support; this child supplies the Tempus design system on top.
 *
 * @package Tempus_Kadence
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'TEMPUS_KADENCE_VERSION', '1.0.0' );

/**
 * Enqueue styles & scripts.
 *
 * Cascade order matters:
 *   tokens.css          — design tokens (single source of truth)
 *   kadence-bridge.css  — maps Kadence's Global Style variables
 *                         (--global-palette1..9, fonts, content width)
 *                         onto the tokens, and restyles Kadence chrome
 *   main.css            — tz- components (hero, cards, membership, …)
 *
 * Priority 20 so everything lands after Kadence's own styles and the
 * bridge wins the :root cascade.
 */
function tempus_kadence_assets() {
	$uri = get_stylesheet_directory_uri();

	wp_enqueue_style( 'tempus-tokens', $uri . '/assets/css/tokens.css', array(), TEMPUS_KADENCE_VERSION );
	wp_enqueue_style( 'tempus-kadence-bridge', $uri . '/assets/css/kadence-bridge.css', array( 'tempus-tokens' ), TEMPUS_KADENCE_VERSION );
	wp_enqueue_style( 'tempus-main', $uri . '/assets/css/main.css', array( 'tempus-kadence-bridge' ), TEMPUS_KADENCE_VERSION );

	// style.css (theme header) — kept last, mostly for the header block.
	wp_enqueue_style( 'tempus-kadence-style', get_stylesheet_uri(), array( 'tempus-main' ), TEMPUS_KADENCE_VERSION );

	wp_enqueue_script( 'tempus-main', $uri . '/assets/js/main.js', array(), TEMPUS_KADENCE_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'tempus_kadence_assets', 20 );

/**
 * Single-product page styling — loaded only on WooCommerce product pages.
 *
 * Depends on 'tempus-tokens' so tokens.css loads first: product-page.css
 * reads the brand colours from those CSS variables, so the product page
 * tracks the homepage design automatically.
 */
function tempus_kadence_product_assets() {
	if ( function_exists( 'is_product' ) && is_product() ) {
		wp_enqueue_style(
			'tempus-product-page',
			get_stylesheet_directory_uri() . '/assets/css/product-page.css',
			array( 'tempus-tokens' ),
			TEMPUS_KADENCE_VERSION
		);
	}
}
add_action( 'wp_enqueue_scripts', 'tempus_kadence_product_assets', 20 );

/**
 * -----------------------------------------------------------------
 * Kadence Global Styles ← Tempus tokens
 * -----------------------------------------------------------------
 * Seed Kadence's Global Color palette with the Tempus values so the
 * Customizer swatches and the block-editor color pickers show the brand
 * palette. The values MUST mirror assets/css/tokens.css — tokens.css is
 * the single source of truth; this is a one-time projection of it into
 * the Kadence UI. (The front-end never depends on this seed: the bridge
 * stylesheet re-declares --global-palette1..9 from the tokens.)
 *
 * Slot semantics follow Kadence's convention:
 *   1 accent · 2 accent alt/hover · 3 strongest text · 4 body text
 *   5 muted text · 6 subtle borders · 7 card bg · 8 alt bg · 9 page bg
 *
 * To re-seed after editing tokens: `wp option delete tempus_kadence_palette_seeded`
 * then re-activate the theme.
 */
function tempus_kadence_seed_palette() {
	if ( get_option( 'tempus_kadence_palette_seeded' ) ) {
		return;
	}

	$palette = array(
		array( 'color' => '#c8922a', 'slug' => 'palette1', 'name' => 'Tempus Gold' ),
		array( 'color' => '#e8b84b', 'slug' => 'palette2', 'name' => 'Gold Deep (Hover)' ),
		array( 'color' => '#f0e6d3', 'slug' => 'palette3', 'name' => 'Parchment (Strongest Text)' ),
		array( 'color' => '#c9b99a', 'slug' => 'palette4', 'name' => 'Parchment Muted (Body Text)' ),
		array( 'color' => '#8d7f66', 'slug' => 'palette5', 'name' => 'Ink Faint (Muted Text)' ),
		array( 'color' => '#2a2620', 'slug' => 'palette6', 'name' => 'Outline (Hairline Borders)' ),
		array( 'color' => '#1e1a15', 'slug' => 'palette7', 'name' => 'Espresso (Card Surface)' ),
		array( 'color' => '#16130d', 'slug' => 'palette8', 'name' => 'Surface Low (Alt Background)' ),
		array( 'color' => '#0a0806', 'slug' => 'palette9', 'name' => 'Near Black (Page Background)' ),
	);

	$value = wp_json_encode( array(
		'palette'        => $palette,
		'second-palette' => $palette,
		'third-palette'  => $palette,
		'active'         => 'palette',
	) );

	// Standalone palette option (read by Kadence Blocks and friends).
	update_option( 'kadence_global_palette', $value );

	// Kadence theme customizer store — merge, don't clobber other settings.
	$opts = get_option( 'kadence_customizer', array() );
	if ( ! is_array( $opts ) ) {
		$opts = array();
	}
	$opts['global_palette'] = $value;
	update_option( 'kadence_customizer', $opts );

	update_option( 'tempus_kadence_palette_seeded', 1 );
}
add_action( 'after_switch_theme', 'tempus_kadence_seed_palette' );

/**
 * -----------------------------------------------------------------
 * Tempus Core plugin safety net
 * -----------------------------------------------------------------
 * Templates call tempus_field() / tempus_product_badge() from the plugin.
 * If the plugin is deactivated, degrade gracefully to the built-in
 * fallback copy instead of fataling, and nag in wp-admin.
 */
if ( ! function_exists( 'tempus_field' ) ) {
	function tempus_field( $selector, $fallback = '', $post_id = false ) {
		return $fallback;
	}
}

function tempus_kadence_plugin_notice() {
	if ( defined( 'TEMPUS_CORE_VERSION' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>Tempus:</strong> the <em>Tempus Core</em> plugin is not active. Homepage fields, product badges and taxonomy seeding are unavailable — activate it under Plugins.</p></div>';
}
add_action( 'admin_notices', 'tempus_kadence_plugin_notice' );
