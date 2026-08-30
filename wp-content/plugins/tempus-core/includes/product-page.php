<?php
/**
 * TEMPUS — WooCommerce Single Product Customisations
 * -------------------------------------------------
 * WHERE THIS GOES: save as  tempus-core/includes/product-page.php
 * then add this line to the main tempus-core plugin file, alongside the
 * existing require_once lines for taxonomy.php / woocommerce.php:
 *
 *     require_once plugin_dir_path( __FILE__ ) . 'includes/product-page.php';
 *
 * This lives in the plugin (not the child theme) for the same reason
 * taxonomy.php and woocommerce.php do — it's product behaviour, not
 * presentation, so it survives any future theme change.
 *
 * Requires: WooCommerce, ACF Pro (field group "Tempus Product Details").
 */

defined( 'ABSPATH' ) || exit;


/* =========================================================
 * 1. CLEAN UP THE DEFAULT WOOCOMMERCE LAYOUT
 * Removes the bits the Tempus design doesn't use, and moves
 * the short description above the price.
 * ======================================================= */
add_action( 'wp', 'tempus_product_cleanup' );
function tempus_product_cleanup() {
	if ( ! is_product() ) {
		return;
	}

	// Star rating and the SKU/category meta line — not in the design.
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );

	// The grey "Description / Reviews" tab strip — replaced by our own section.
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );

	// Default gallery — the custom content-single-product.php template prints its own hero image.
	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );

	// Move short description from below the price (20) to above it (9).
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
	add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 9 );
}


/* =========================================================
 * 2. EYEBROW ABOVE THE TITLE
 * e.g. "JAPANESE BLENDED WHISKY · RARE"
 * Uses the ACF field, falls back to the product category.
 * ======================================================= */
add_action( 'woocommerce_single_product_summary', 'tempus_product_eyebrow', 4 );
function tempus_product_eyebrow() {
	$eyebrow = function_exists( 'get_field' ) ? get_field( 'product_eyebrow' ) : '';

	if ( ! $eyebrow ) {
		$terms = get_the_terms( get_the_ID(), 'product_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$eyebrow = $terms[0]->name;
		}
	}

	if ( $eyebrow ) {
		echo '<p class="tempus-eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}
}


/* =========================================================
 * 3. "SAVE $21" BADGE NEXT TO THE PRICE
 * Calculated automatically from regular price minus sale price.
 * ======================================================= */
add_filter( 'woocommerce_get_price_html', 'tempus_save_badge', 20, 2 );
function tempus_save_badge( $html, $product ) {

	// Only on the main product being viewed — not the related-products grid.
	if ( ! is_product() || $product->get_id() !== get_queried_object_id() ) {
		return $html;
	}
	if ( ! $product->is_on_sale() || $product->is_type( 'variable' ) ) {
		return $html;
	}

	$save = (float) $product->get_regular_price() - (float) $product->get_sale_price();
	if ( $save <= 0 ) {
		return $html;
	}

	return $html . '<span class="tempus-save">Save ' . wp_strip_all_tags( wc_price( $save ) ) . '</span>';
}


/* =========================================================
 * 4. STOCK LINE UNDER THE ADD TO CART BUTTON
 * ======================================================= */
add_action( 'woocommerce_single_product_summary', 'tempus_stock_line', 31 );
function tempus_stock_line() {
	global $product;
	if ( ! $product ) {
		return;
	}

	if ( $product->is_in_stock() ) {
		echo '<p class="tempus-stock is-in"><span class="tempus-dot"></span>In Stock · Ships in 48h</p>';
	} else {
		echo '<p class="tempus-stock is-out"><span class="tempus-dot"></span>Currently Unavailable</p>';
	}
}


/* =========================================================
 * 5. THE SPEC BAR (Region / Distillery / Cask / ABV / Bottle)
 * Loops through whatever product attributes are ticked
 * "Visible on the product page" — so cigars and vinyl
 * automatically show THEIR own labels instead.
 * Shows a maximum of 5.
 * ======================================================= */
add_action( 'woocommerce_after_single_product_summary', 'tempus_spec_bar', 5 );
function tempus_spec_bar() {
	global $product;
	if ( ! $product ) {
		return;
	}

	$rows = array();

	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute->get_visible() ) {
			continue;
		}
		$value = $product->get_attribute( $attribute->get_name() );
		if ( $value ) {
			$rows[] = array(
				'label' => wc_attribute_label( $attribute->get_name() ),
				'value' => str_replace( ', ', ' · ', $value ),
			);
		}
	}

	if ( empty( $rows ) ) {
		return;
	}

	$rows = array_slice( $rows, 0, 5 );

	echo '<div class="tempus-specbar">';
	foreach ( $rows as $row ) {
		echo '<div class="tempus-spec">';
		echo '<span class="tempus-spec-label">' . esc_html( $row['label'] ) . '</span>';
		echo '<span class="tempus-spec-value">' . esc_html( $row['value'] ) . '</span>';
		echo '</div>';
	}
	echo '</div>';
}


/* =========================================================
 * 6. TASTING NOTES + "COMPLETE THE RITUAL" TWO-COLUMN BLOCK
 * ======================================================= */
add_action( 'woocommerce_after_single_product_summary', 'tempus_notes_and_ritual', 8 );
function tempus_notes_and_ritual() {
	global $product;
	if ( ! $product ) {
		return;
	}

	$acf     = function_exists( 'get_field' );
	$heading = $acf ? get_field( 'tasting_heading' ) : '';
	$body    = $acf ? get_field( 'tasting_notes' ) : '';
	$label   = $acf ? get_field( 'ritual_label' ) : '';
	$items   = $acf ? get_field( 'ritual_items' ) : array();
	$bundle  = $acf ? get_field( 'ritual_bundle' ) : null;

	// Fall back to the main product description if the ACF field is empty.
	if ( ! $body ) {
		$body = get_post_field( 'post_content', $product->get_id() );
	}

	if ( ! $body && ! $bundle ) {
		return;
	}

	echo '<section class="tempus-story"><div class="tempus-story-inner">';

	/* ---- LEFT COLUMN: tasting notes ---- */
	echo '<div class="tempus-notes">';
	echo '<p class="tempus-eyebrow">Tasting Notes</p>';
	if ( $heading ) {
		echo '<h2 class="tempus-notes-title">' . esc_html( $heading ) . '</h2>';
	}
	echo '<div class="tempus-notes-body">' . wp_kses_post( wpautop( $body ) ) . '</div>';
	echo '<ul class="tempus-trust">';
	echo '<li class="tempus-trust-lock">Secure Checkout</li>';
	echo '<li class="tempus-trust-check">Age Verified 21+</li>';
	echo '</ul>';
	echo '</div>';

	/* ---- RIGHT COLUMN: the ritual bundle card ---- */
	if ( $bundle ) {
		$bundle_id      = is_object( $bundle ) ? $bundle->ID : (int) $bundle;
		$bundle_product = wc_get_product( $bundle_id );

		if ( $bundle_product ) {
			echo '<aside class="tempus-ritual">';

			echo '<p class="tempus-eyebrow">Complete the Ritual';
			if ( $label ) {
				echo ' · ' . esc_html( $label );
			}
			echo '</p>';

			if ( $items ) {
				echo '<div class="tempus-ritual-items">';
				$i = 0;
				foreach ( $items as $item ) {
					$item_id = is_object( $item ) ? $item->ID : (int) $item;

					if ( $i > 0 ) {
						echo '<span class="tempus-plus">+</span>';
					}

					// Caption uses the product's category (WHISKY / CIGAR / VINYL).
					$cats    = get_the_terms( $item_id, 'product_cat' );
					$caption = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : get_the_title( $item_id );

					echo '<figure class="tempus-ritual-item">';
					echo '<a href="' . esc_url( get_permalink( $item_id ) ) . '">';
					echo get_the_post_thumbnail( $item_id, 'woocommerce_thumbnail' );
					echo '</a>';
					echo '<figcaption>' . esc_html( $caption ) . '</figcaption>';
					echo '</figure>';

					$i++;
				}
				echo '</div>';
			}

			echo '<div class="tempus-ritual-foot">';
			echo '<span class="tempus-ritual-price">' . wp_kses_post( $bundle_product->get_price_html() ) . '</span>';
			echo '<a class="tempus-ritual-btn" href="' . esc_url( $bundle_product->add_to_cart_url() ) . '">Add Ritual</a>';
			echo '</div>';

			echo '</aside>';
		}
	}

	echo '</div></section>';
}


/* =========================================================
 * 7. RELATED PRODUCTS — "You May Also Savor"
 * ======================================================= */
add_filter( 'woocommerce_product_related_products_heading', 'tempus_related_heading' );
function tempus_related_heading() {
	return 'You May Also Savor';
}

add_filter( 'woocommerce_output_related_products_args', 'tempus_related_args' );
function tempus_related_args( $args ) {
	$args['posts_per_page'] = 3;
	$args['columns']        = 3;
	return $args;
}

// Wrap the related section so we can position the "View All" link.
add_action( 'woocommerce_after_single_product_summary', 'tempus_related_open', 19 );
add_action( 'woocommerce_after_single_product_summary', 'tempus_related_close', 21 );

function tempus_related_open() {
	echo '<section class="tempus-related">';
	echo '<a class="tempus-viewall" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">View All &rarr;</a>';
}
function tempus_related_close() {
	echo '</section>';
}


/* =========================================================
 * 8. PRODUCT CARD EXTRAS (used in the related grid AND the shop page)
 * Adds the small eyebrow above the name and the meta line below it.
 * ======================================================= */
function tempus_loop_eyebrow() {
	global $product;

	$eyebrow = function_exists( 'get_field' ) ? get_field( 'product_eyebrow', $product->get_id() ) : '';
	if ( ! $eyebrow ) {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$eyebrow = $terms[0]->name;
		}
	}

	if ( $eyebrow ) {
		echo '<p class="tempus-card-eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}
}

function tempus_loop_meta() {
	global $product;

	// Prefer the two attributes the design calls for — Region and ABV —
	// looked up by their taxonomy slugs (verified in the DB: pa_region, pa_abv).
	$bits   = array();
	$region = $product->get_attribute( 'pa_region' );
	$abv    = $product->get_attribute( 'pa_abv' );

	if ( $region ) {
		$bits[] = str_replace( ', ', ' · ', $region );
	}
	if ( $abv ) {
		$bits[] = str_replace( ', ', ' · ', $abv );
	}

	// Neither found (e.g. cigars, vinyl, which carry their own attribute
	// set) — fall back to the first two visible attributes so those product
	// types still show something.
	if ( empty( $bits ) ) {
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute->get_visible() ) {
				continue;
			}
			$value = $product->get_attribute( $attribute->get_name() );
			if ( $value ) {
				$bits[] = str_replace( ', ', ' · ', $value );
			}
			if ( count( $bits ) >= 2 ) {
				break;
			}
		}
	}

	if ( $bits ) {
		echo '<p class="tempus-card-meta">' . esc_html( implode( ' · ', $bits ) ) . '</p>';
	}
}

// "LIMITED" corner badge — driven by a product tag called "Limited".
function tempus_limited_badge() {
	global $product;
	if ( has_term( 'limited', 'product_tag', $product->get_id() ) ) {
		echo '<span class="tempus-limited">Limited</span>';
	}
}


/* =========================================================
 * 9. AMBIENT HERO BACKGROUND IMAGE (per product, via ACF)
 * ======================================================= */
add_action( 'wp_head', 'tempus_hero_background', 99 );
function tempus_hero_background() {
	if ( ! is_product() || ! function_exists( 'get_field' ) ) {
		return;
	}

	$image = get_field( 'hero_background' );
	if ( ! $image ) {
		return;
	}

	$url = is_array( $image ) ? $image['url'] : $image;

	echo '<style id="tempus-hero-bg">.single-product div.product{--tempus-hero-image:url(' . esc_url( $url ) . ');}</style>';
}
