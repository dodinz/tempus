<?php
/**
 * ACF Blocks — the five homepage sections, ported from the legacy theme
 * partials into self-registering blocks under /blocks.
 *
 * Responsibilities:
 *   1. Register a "Tempus" block category (inserter grouping).
 *   2. Auto-register every /blocks/<name> folder that ships a block.json.
 *   3. Register one ACF local field group per block (location: block ==
 *      tempus/<name>), mirroring the field names read by each render.php.
 *   4. Enqueue the design tokens + section styles on the front end AND in
 *      the editor so block previews match the live site.
 *
 * @package Tempus_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1. Add a "Tempus" category to the block inserter.
 *
 * @param array $categories Registered block categories.
 * @return array
 */
function tempus_core_block_category( $categories ) {
	foreach ( $categories as $category ) {
		if ( isset( $category['slug'] ) && 'tempus' === $category['slug'] ) {
			return $categories; // Already present — don't duplicate.
		}
	}
	array_unshift(
		$categories,
		array(
			'slug'  => 'tempus',
			'title' => __( 'Tempus', 'tempus-core' ),
			'icon'  => null,
		)
	);
	return $categories;
}
add_filter( 'block_categories_all', 'tempus_core_block_category' );

/**
 * 2. Auto-register every block folder under /blocks that contains a
 * block.json. register_block_type() reads the metadata; ACF hooks its own
 * render pipeline in via the "acf" key (mode + renderTemplate).
 */
function tempus_core_register_blocks() {
	$blocks_dir = TEMPUS_CORE_DIR . 'blocks';
	if ( ! is_dir( $blocks_dir ) ) {
		return;
	}
	$folders = glob( $blocks_dir . '/*', GLOB_ONLYDIR );
	if ( empty( $folders ) ) {
		return;
	}
	foreach ( $folders as $dir ) {
		if ( file_exists( $dir . '/block.json' ) ) {
			register_block_type( $dir );
		}
	}
}
add_action( 'init', 'tempus_core_register_blocks' );

/**
 * 4. Load tokens.css + the tz- section styles (main.css) on the front end
 * and inside the editor so previews render with the brand design system.
 *
 * tokens.css and main.css are the single source of truth in the
 * tempus-kadence child theme; we reuse those files (rather than duplicate
 * brand colors here) via the theme file resolvers. On the front end the
 * child theme also enqueues them under its own handles — that overlap is
 * idempotent (identical rules), while the distinct handles below keep this
 * plugin's editor styles from disturbing the theme's carefully ordered
 * front-end cascade (tokens → bridge → main).
 */
function tempus_core_block_assets() {
	$ver = defined( 'TEMPUS_CORE_VERSION' ) ? TEMPUS_CORE_VERSION : false;

	if ( file_exists( get_theme_file_path( 'assets/css/tokens.css' ) ) ) {
		wp_enqueue_style( 'tempus-block-tokens', get_theme_file_uri( 'assets/css/tokens.css' ), array(), $ver );
	}
	if ( file_exists( get_theme_file_path( 'assets/css/main.css' ) ) ) {
		wp_enqueue_style( 'tempus-block-sections', get_theme_file_uri( 'assets/css/main.css' ), array( 'tempus-block-tokens' ), $ver );
	}
}
add_action( 'enqueue_block_assets', 'tempus_core_block_assets' );

/**
 * 3. ACF field groups — one per block, each with a location rule
 * `block == tempus/<name>`. Kept in their own file so the field
 * definitions stay the single source of truth for what each render.php
 * reads. Registers on acf/init; no-ops when ACF is inactive.
 */
require_once TEMPUS_CORE_DIR . 'includes/block-fields.php';
