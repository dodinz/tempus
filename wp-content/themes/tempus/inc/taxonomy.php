<?php
/**
 * Seed WooCommerce product categories & tags in code.
 *
 * WooCommerce already registers the `product_cat` and `product_tag`
 * taxonomies — we only insert the terms so the store structure is
 * reproducible across environments (dev → staging → prod) instead of
 * being hand-created in each admin. Runs once on theme switch; safe to
 * re-run (wp_insert_term is a no-op if the term exists).
 *
 * @package Tempus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tempus_seed_taxonomy() {

	// --- Hierarchical categories: parent => [children] ---
	$categories = array(
		'Spirits'       => array( 'Scotch', 'Bourbon', 'Japanese Whisky', 'Irish' ),
		'Cigars'        => array( 'Cuban', 'New World', 'Philippine' ),
		'Vinyl'         => array( 'Jazz', 'Blues', 'Classics' ),
		'Accessories'   => array(),
		'Ritual Bundles'=> array(),
	);

	foreach ( $categories as $parent => $children ) {
		$parent_term = term_exists( $parent, 'product_cat' );
		if ( ! $parent_term ) {
			$parent_term = wp_insert_term( $parent, 'product_cat' );
		}
		if ( is_wp_error( $parent_term ) ) {
			continue;
		}
		$parent_id = is_array( $parent_term ) ? (int) $parent_term['term_id'] : (int) $parent_term;

		foreach ( $children as $child ) {
			if ( ! term_exists( $child, 'product_cat' ) ) {
				wp_insert_term( $child, 'product_cat', array( 'parent' => $parent_id ) );
			}
		}
	}

	// --- Cross-cutting tags (drive card badges + homepage queries) ---
	$tags = array( 'Featured', 'New Arrival', 'Rare', 'Limited' );
	foreach ( $tags as $tag ) {
		if ( ! term_exists( $tag, 'product_tag' ) ) {
			wp_insert_term( $tag, 'product_tag' );
		}
	}
}

// Seed when the theme is activated.
add_action( 'after_switch_theme', 'tempus_seed_taxonomy' );

/**
 * OPTIONAL: register global product attributes used across the catalog
 * (ABV, Country of Origin, etc. from the PID). Attributes let customers
 * filter and keep product data structured. Uncomment if you want them
 * created in code rather than via Products → Attributes.
 */
/*
function tempus_seed_attributes() {
	if ( ! function_exists( 'wc_create_attribute' ) ) return;
	$attributes = array(
		'ABV'               => 'abv',
		'Country of Origin' => 'origin',
		'Region'            => 'region',
		'Cigar Strength'    => 'strength',
	);
	foreach ( $attributes as $label => $slug ) {
		if ( wc_attribute_taxonomy_id_by_name( $slug ) ) continue;
		wc_create_attribute( array(
			'name'         => $label,
			'slug'         => $slug,
			'type'         => 'select',
			'order_by'     => 'menu_order',
			'has_archives' => false,
		) );
	}
}
add_action( 'after_switch_theme', 'tempus_seed_attributes' );
*/
