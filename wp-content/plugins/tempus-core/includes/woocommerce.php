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

if ( ! function_exists( 'tempus_get_shop_categories' ) ) {
	/**
	 * Top-level product categories for the /shop chooser, including empty ones.
	 *
	 * Counts: on the front end WooCommerce swaps $term->count for its
	 * `product_count_product_cat` term meta (wc_change_term_counts), which
	 * already rolls child-term products up into the parent and counts each
	 * visible product once. pad_counts is kept as the core-side equivalent for
	 * any context where Woo's override doesn't run.
	 *
	 * Memoised per request — the collection header and the grid both call it.
	 *
	 * @return array[] Each item: term, count, coming_soon, url, image_id, children, count_label.
	 */
	function tempus_get_shop_categories() {
		static $out = null;
		if ( null !== $out ) {
			return $out;
		}

		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => false,  // Keeps empty categories — they render as Coming Soon.
			'pad_counts' => true,   // Child-term products roll up into the parent.
			'orderby'    => 'menu_order', // Woo maps this to the drag-handle order.
			'exclude'    => array( (int) get_option( 'default_product_cat', 0 ) ),
		) );

		$out = array();
		if ( is_wp_error( $terms ) ) {
			return $out;
		}

		foreach ( $terms as $term ) {
			$count = (int) $term->count;
			$url   = get_term_link( $term );

			$children = get_terms( array(
				'taxonomy'   => 'product_cat',
				'parent'     => $term->term_id,
				'hide_empty' => false,
				'orderby'    => 'menu_order',
				'number'     => 5,
			) );

			$out[] = array(
				'term'        => $term,
				'count'       => $count,
				'coming_soon' => 0 === $count,
				'url'         => is_wp_error( $url ) ? '' : $url,
				'image_id'    => (int) get_term_meta( $term->term_id, 'thumbnail_id', true ),
				'children'    => is_wp_error( $children ) ? array() : $children,
				// Lower case on purpose — uppercasing is presentation (shop-archive.css).
				'count_label' => sprintf(
					/* translators: %1$s: product count, %2$s: "product" or "products" */
					'%1$s %2$s',
					number_format_i18n( $count ),
					_n( 'product', 'products', $count, 'tempus-core' )
				),
			);
		}

		return $out;
	}
}

if ( ! function_exists( 'tempus_get_catalog_count' ) ) {
	/**
	 * Distinct catalog-visible products for the "In the cellar" stat.
	 *
	 * Not a sum of category counts — a product in two top-level categories
	 * would be counted twice. visibility=catalog also drops hidden products
	 * such as the "Complete the Ritual" bundle.
	 *
	 * @return int
	 */
	function tempus_get_catalog_count() {
		$count = get_transient( 'tempus_catalog_count' );

		if ( false === $count ) {
			$ids = wc_get_products( array(
				'status'     => 'publish',
				'visibility' => 'catalog',
				'limit'      => -1,
				'return'     => 'ids',
			) );

			$count = count( $ids );
			set_transient( 'tempus_catalog_count', $count, DAY_IN_SECONDS );
		}

		return (int) $count;
	}
}

/**
 * Drop the cached cellar count whenever the catalog changes.
 */
function tempus_flush_catalog_count() {
	delete_transient( 'tempus_catalog_count' );
}
add_action( 'save_post_product', 'tempus_flush_catalog_count' );
add_action( 'trashed_post', 'tempus_flush_catalog_count' );
add_action( 'untrashed_post', 'tempus_flush_catalog_count' );
add_action( 'woocommerce_update_product', 'tempus_flush_catalog_count' );

if ( ! function_exists( 'tempus_get_bottle_card_data' ) ) {
	/**
	 * Everything the shared bottle card needs beyond what WC_Product gives.
	 *
	 * Used by woocommerce/tempus/bottle-card.php in the child theme, which is
	 * rendered by both the Featured Bottles block and every product loop — so
	 * the homepage and the archives can't drift apart.
	 *
	 * @param WC_Product $product Product.
	 * @return array{badge:string,category:string,meta:string} badge is HTML (tz-badge), the rest plain text.
	 */
	function tempus_get_bottle_card_data( WC_Product $product ) {
		$id = $product->get_id();

		// Category label: the deepest assigned category ("Japanese" rather than "Whisky").
		$category = '';
		$terms    = get_the_terms( $id, 'product_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$default = (int) get_option( 'default_product_cat', 0 );
			$terms   = array_filter( $terms, function ( $term ) use ( $default ) {
				return (int) $term->term_id !== $default;
			} );
			usort( $terms, function ( $a, $b ) {
				return count( get_ancestors( $b->term_id, 'product_cat' ) ) - count( get_ancestors( $a->term_id, 'product_cat' ) );
			} );
			if ( $terms ) {
				$category = $terms[0]->name;
			}
		}

		// Meta line: ACF bottle_meta, else Origin/Region · ABV, else the first
		// two visible attributes (cigars and vinyl carry their own sets).
		$meta = function_exists( 'get_field' ) ? trim( (string) get_field( 'bottle_meta', $id ) ) : '';
		if ( '' === $meta ) {
			$bits = array_filter( array(
				$product->get_attribute( 'pa_origin' ) ?: $product->get_attribute( 'pa_region' ),
				$product->get_attribute( 'pa_abv' ),
			) );

			if ( empty( $bits ) ) {
				foreach ( $product->get_attributes() as $attribute ) {
					if ( ! $attribute->get_visible() ) {
						continue;
					}
					$value = $product->get_attribute( $attribute->get_name() );
					if ( $value ) {
						$bits[] = $value;
					}
					if ( count( $bits ) >= 2 ) {
						break;
					}
				}
			}

			$meta = str_replace( ', ', ' · ', implode( ' · ', $bits ) );
		}

		return array(
			'badge'    => tempus_product_badge( $product ),
			'category' => $category,
			'meta'     => $meta,
		);
	}
}

/**
 * Loop button label: "Add to Cart" only when one click can actually add it.
 * Loop buttons only — the single-product button uses a different filter.
 */
add_filter( 'woocommerce_product_add_to_cart_text', function ( $text, $product ) {
	if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) {
		return __( 'Add to Cart', 'tempus-core' );
	}
	return __( 'View', 'tempus-core' );
}, 10, 2 );

/**
 * Give the loop add-to-cart link the Tempus card button classes. WooCommerce
 * still builds the link itself, so stock checks and AJAX keep working.
 */
add_filter( 'woocommerce_loop_add_to_cart_args', function ( $args ) {
	$args['class'] = trim( $args['class'] . ' tz-btn tz-btn--sm tz-card__cart' );
	return $args;
} );

if ( ! function_exists( 'tempus_get_archive_chips' ) ) {
	/**
	 * Subcategory chips for a product category archive: "All" + the children
	 * of the top category. On a child archive (/whisky/japanese/) it shows the
	 * siblings, with the current one active.
	 *
	 * @return array[] Each item: label, url, active. Empty when there's nothing to filter by.
	 */
	function tempus_get_archive_chips() {
		$term = get_queried_object();

		if ( ! $term instanceof WP_Term || 'product_cat' !== $term->taxonomy ) {
			return array();
		}

		$parent_id = $term->parent ? (int) $term->parent : (int) $term->term_id;

		$children = get_terms( array(
			'taxonomy'   => 'product_cat',
			'parent'     => $parent_id,
			'hide_empty' => true, // Never offer a chip that leads to an empty page.
			'orderby'    => 'menu_order',
		) );

		if ( is_wp_error( $children ) || empty( $children ) ) {
			return array();
		}

		$parent_url = get_term_link( $parent_id, 'product_cat' );
		$chips      = array(
			array(
				'label'  => __( 'All', 'tempus-core' ),
				'url'    => is_wp_error( $parent_url ) ? '' : $parent_url,
				'active' => (int) $term->term_id === $parent_id,
			),
		);

		foreach ( $children as $child ) {
			$url     = get_term_link( $child );
			$chips[] = array(
				'label'  => $child->name,
				'url'    => is_wp_error( $url ) ? '' : $url,
				'active' => (int) $child->term_id === (int) $term->term_id,
			);
		}

		return $chips;
	}
}

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
