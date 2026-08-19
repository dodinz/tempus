<?php
/**
 * Homepage block pattern — scaffolds the five homepage sections as a
 * starter layout the client can restyle directly in the block editor.
 *
 * Replaces the old ACF "Tempus — Homepage" field group (see
 * includes/homepage-pattern.php's sibling removal in git history): instead
 * of editable-but-invisible-until-styled ACF fields, this drops real,
 * visible blocks onto the page that already carry the right structure —
 * Kadence Row Layout / Column for the section containers (so the client can
 * use Kadence's row/column controls for spacing, background, etc.), core
 * blocks for text/buttons, and the WooCommerce "Products by Tag/Category"
 * blocks for live product grids.
 *
 * @package Tempus_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build a `kadence/rowlayout` block (section container) wrapping the given
 * column markup.
 *
 * @param string $uid        Unique ID token for this row (any short,
 *                            CSS-safe string — Kadence just needs it
 *                            consistent between the block's JSON attrs and
 *                            the class names/id on the saved markup).
 * @param int    $columns    Number of columns in the row.
 * @param string $inner_html Already-built `wp:kadence/column` markup(s).
 * @param array  $args       Optional overrides: 'align' ('full'|'wide'),
 *                            'bgColor' (a global palette slug, e.g.
 *                            'palette9'), 'anchor' (HTML anchor / jump-link
 *                            id).
 * @return string
 */
function tempus_kb_row( $uid, $columns, $inner_html, $args = array() ) {
	$attrs = array_merge(
		array(
			'uniqueID' => $uid,
			'columns'  => $columns,
		),
		$args
	);

	$align         = isset( $args['align'] ) ? $args['align'] : 'none';
	$outer_classes = array( 'wp-block-kadence-rowlayout', 'align' . $align );

	$inner_classes = array( 'kt-row-layout-inner', 'kt-layout-id' . $uid );
	$style_attr    = '';
	if ( ! empty( $args['bgColor'] ) ) {
		$inner_classes[] = 'kt-row-has-bg';
		$style_attr      = ' style="background-color:var(--global-' . esc_attr( $args['bgColor'] ) . ')"';
	}

	$wrap_classes = array(
		'kt-row-column-wrap',
		'kt-has-' . absint( $columns ) . '-columns',
		'kt-gutter-default',
		'kt-tab-layout-inherit',
		'kt-m-colapse-left-to-right',
		'kt-mobile-layout-row',
	);

	$anchor_attr = ! empty( $args['anchor'] ) ? ' id="' . esc_attr( $args['anchor'] ) . '"' : '';

	return
		'<!-- wp:kadence/rowlayout ' . wp_json_encode( $attrs ) . " -->\n" .
		'<div class="' . esc_attr( implode( ' ', $outer_classes ) ) . '"' . $anchor_attr . '>' .
		'<div id="kt-layout-id' . esc_attr( $uid ) . '" class="' . esc_attr( implode( ' ', $inner_classes ) ) . '"' . $style_attr . '>' .
		'<div class="' . esc_attr( implode( ' ', $wrap_classes ) ) . '">' .
		$inner_html .
		'</div></div></div>' .
		"\n<!-- /wp:kadence/rowlayout -->\n";
}

/**
 * Build a `kadence/column` block (must live directly inside the column-wrap
 * markup produced by tempus_kb_row()).
 *
 * @param string $uid        Unique ID token for this column.
 * @param int    $col_index  1-based position of this column within its row.
 * @param string $inner_html Inner block markup (core blocks, etc.).
 * @return string
 */
function tempus_kb_column( $uid, $col_index, $inner_html ) {
	$attrs   = array(
		'id'       => $col_index,
		'uniqueID' => $uid,
	);
	$classes = array( 'wp-block-kadence-column', 'inner-column-' . absint( $col_index ), 'kadence-column' . $uid );

	return
		'<!-- wp:kadence/column ' . wp_json_encode( $attrs ) . " -->\n" .
		'<div class="' . esc_attr( implode( ' ', $classes ) ) . '">' .
		'<div class="kt-inside-inner-col">' . $inner_html . '</div>' .
		'</div>' .
		"\n<!-- /wp:kadence/column -->\n";
}

/**
 * Core heading block, colored from the Kadence global palette.
 *
 * @param string $text    Heading text (already safe to output as-is).
 * @param int    $level   Heading level 1-6.
 * @param int    $palette Global palette slot 1-9 (see tokens.css).
 * @param string $align   '', 'left', 'center', 'right'.
 * @return string
 */
function tempus_pattern_heading( $text, $level, $palette, $align = '' ) {
	$attrs = array(
		'level'     => $level,
		'textColor' => 'theme-palette' . $palette,
	);
	if ( $align ) {
		$attrs['textAlign'] = $align;
	}
	$classes = array( 'has-theme-palette' . $palette . '-color', 'has-text-color' );
	if ( $align ) {
		$classes[] = 'has-text-align-' . $align;
	}
	$tag = 'h' . $level;
	return '<!-- wp:heading ' . wp_json_encode( $attrs ) . ' -->' .
		'<' . $tag . ' class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $text . '</' . $tag . '>' .
		'<!-- /wp:heading -->';
}

/**
 * Core paragraph block, colored from the Kadence global palette.
 *
 * @param string $text
 * @param int    $palette Global palette slot 1-9.
 * @param string $align   '', 'left', 'center', 'right'.
 * @return string
 */
function tempus_pattern_paragraph( $text, $palette, $align = '' ) {
	$attrs = array(
		'textColor' => 'theme-palette' . $palette,
	);
	if ( $align ) {
		$attrs['textAlign'] = $align;
	}
	$classes = array( 'has-theme-palette' . $palette . '-color', 'has-text-color' );
	if ( $align ) {
		$classes[] = 'has-text-align-' . $align;
	}
	return '<!-- wp:paragraph ' . wp_json_encode( $attrs ) . ' -->' .
		'<p class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $text . '</p>' .
		'<!-- /wp:paragraph -->';
}

/**
 * A single core/button, wrapped in its own core/buttons group.
 *
 * @param string $label
 * @param string $url
 * @param bool   $primary   True = filled (bg = accent palette). False =
 *                          outline style.
 * @param string $justify   Flex justify-content for the wrapping
 *                           core/buttons: 'left'|'center'|'right'.
 * @return string
 */
function tempus_pattern_button( $label, $url, $primary = true, $justify = 'left' ) {
	if ( $primary ) {
		$btn_attrs   = array(
			'backgroundColor' => 'theme-palette1',
			'textColor'       => 'theme-palette9',
		);
		$link_classes = array( 'wp-block-button__link', 'has-theme-palette1-background-color', 'has-background', 'has-theme-palette9-color', 'has-text-color', 'wp-element-button' );
		$outer_class  = 'wp-block-button';
	} else {
		$btn_attrs   = array(
			'className'  => 'is-style-outline',
			'textColor'  => 'theme-palette1',
		);
		$link_classes = array( 'wp-block-button__link', 'has-theme-palette1-color', 'has-text-color', 'wp-element-button' );
		$outer_class  = 'wp-block-button is-style-outline';
	}

	$buttons_attrs = array();
	if ( 'left' !== $justify ) {
		$buttons_attrs['layout'] = array(
			'type'           => 'flex',
			'justifyContent' => $justify,
		);
	}

	$button_html =
		'<!-- wp:button ' . wp_json_encode( $btn_attrs ) . ' -->' .
		'<div class="' . esc_attr( $outer_class ) . '">' .
		'<a class="' . esc_attr( implode( ' ', $link_classes ) ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' .
		'</div>' .
		'<!-- /wp:button -->';

	return '<!-- wp:buttons ' . wp_json_encode( $buttons_attrs ) . " -->\n" .
		'<div class="wp-block-buttons">' . $button_html . '</div>' .
		"\n<!-- /wp:buttons -->\n";
}

/**
 * Assemble the full "Tempus — Homepage" pattern content.
 *
 * @return string
 */
function tempus_homepage_pattern_content() {
	$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );
	$shop_url = $shop_url ? $shop_url : home_url( '/shop/' );

	$content = '';

	/* =====================================================================
	 * SECTION 1: HERO
	 * ==================================================================== */
	$content .= "\n<!-- ============ SECTION: HERO ============ -->\n";
	$hero_inner  = tempus_pattern_paragraph( 'Whisky &middot; Cigar &middot; Vinyl', 1, 'left' );
	$hero_inner .= tempus_pattern_heading( "A place to savor life&#8217;s most refined pleasure", 1, 3 );
	$hero_inner .= tempus_pattern_paragraph( 'Rare bottles, hand-rolled cigars, and analog sound &mdash; delivered to your door or poured at the bar.', 4 );
	$hero_inner .= tempus_pattern_button( 'Shop the Collection', $shop_url, true );
	$hero_inner .= tempus_pattern_button( 'Explore Rituals', '#tempus-rituals', false );

	$content .= tempus_kb_row(
		'tzhero',
		1,
		tempus_kb_column( 'tzherocol', 1, $hero_inner ),
		array(
			'align'   => 'full',
			'bgColor' => 'palette9',
		)
	);

	/* =====================================================================
	 * SECTION 2: THREE PURSUITS
	 * ==================================================================== */
	$content .= "\n<!-- ============ SECTION: THREE PURSUITS ============ -->\n";
	$pursuits_head_inner  = tempus_pattern_paragraph( 'The Tempus Way', 1, 'center' );
	$pursuits_head_inner .= tempus_pattern_heading( 'Three pursuits, one evening', 2, 3, 'center' );

	$content .= tempus_kb_row(
		'tzpurshead',
		1,
		tempus_kb_column( 'tzpursheadcol', 1, $pursuits_head_inner )
	);

	$pursuits = array(
		array( '01', 'Whisky', 'Rare single malts and small-batch bourbons, poured by people who know their worth.' ),
		array( '02', 'Cigars', 'Hand-rolled, hand-picked &mdash; a slow burn for a slow evening.' ),
		array( '03', 'Vinyl', 'Analog sound for an analog pleasure. Every evening deserves a soundtrack.' ),
	);
	$pursuits_cols = '';
	foreach ( $pursuits as $i => $pursuit ) {
		list( $num, $title, $desc ) = $pursuit;
		$col_inner  = tempus_pattern_heading( $num, 3, 1 );
		$col_inner .= tempus_pattern_heading( $title, 4, 3 );
		$col_inner .= tempus_pattern_paragraph( $desc, 4 );
		$pursuits_cols .= tempus_kb_column( 'tzpursuit' . ( $i + 1 ), $i + 1, $col_inner );
	}
	$content .= tempus_kb_row( 'tzpursuits', 3, $pursuits_cols );

	/* =====================================================================
	 * SECTION 3: FEATURED BOTTLES
	 * ==================================================================== */
	$content .= "\n<!-- ============ SECTION: FEATURED BOTTLES ============ -->\n";
	$featured_head_inner  = tempus_pattern_paragraph( 'The Cellar', 1, 'center' );
	$featured_head_inner .= tempus_pattern_heading( 'Featured Bottles', 2, 3, 'center' );
	$featured_head_inner .= tempus_pattern_paragraph( 'A rotating selection from the shelf &mdash; the rare, the aged, the quietly perfect.', 4, 'center' );

	$content .= tempus_kb_row(
		'tzfeathead',
		1,
		tempus_kb_column( 'tzfeatheadcol', 1, $featured_head_inner )
	);

	// WooCommerce "Products by Tag" — dynamic block, no inner markup to
	// keep in sync, so it can't drift out of validation.
	$content .= '<!-- wp:woocommerce/product-tag ' . wp_json_encode(
		array(
			'columns'    => 4,
			'rows'       => 1,
			'tags'       => array( tempus_homepage_pattern_term_id( 'product_tag', 'featured' ) ),
			'tagOperator' => 'any',
		)
	) . " /-->\n";

	$content .= tempus_pattern_button( 'View All Products', $shop_url, true, 'center' );

	/* =====================================================================
	 * SECTION 4: RITUALS
	 * ==================================================================== */
	$content .= "\n<!-- ============ SECTION: RITUALS ============ -->\n";
	$rituals_head_inner  = tempus_pattern_paragraph( 'Curated Rituals', 1, 'center' );
	$rituals_head_inner .= tempus_pattern_heading( 'Composed for the Moment', 2, 3, 'center' );
	$rituals_head_inner .= tempus_pattern_paragraph( 'Each ritual pairs a whisky, a cigar, and a record &mdash; an evening, curated.', 4, 'center' );

	$content .= tempus_kb_row(
		'tzrithead',
		1,
		tempus_kb_column( 'tzritheadcol', 1, $rituals_head_inner ),
		array(
			'bgColor' => 'palette8',
			'anchor'  => 'tempus-rituals',
		)
	);

	$content .= '<!-- wp:woocommerce/product-category ' . wp_json_encode(
		array(
			'columns'      => 3,
			'rows'         => 1,
			'categories'   => array( tempus_homepage_pattern_term_id( 'product_cat', 'ritual-bundles' ) ),
			'catOperator'  => 'any',
		)
	) . " /-->\n";

	/* =====================================================================
	 * SECTION 5: MEMBERSHIP
	 * ==================================================================== */
	$content .= "\n<!-- ============ SECTION: MEMBERSHIP ============ -->\n";
	$member_left  = tempus_pattern_paragraph( 'Founding Members', 1 );
	$member_left .= tempus_pattern_heading( 'Secure your place before the doors open.', 2, 3 );
	$member_left .= tempus_pattern_paragraph( 'Founding membership is limited. Those who join before opening day lock in priority access to rare allocations, private tasting events, and rates that will never be offered again.', 4 );
	$member_left .= '<!-- wp:shortcode -->' .
		'<div class="wp-block-shortcode">[tempus_waitlist_form]</div>' .
		'<!-- /wp:shortcode -->';

	$tiers = array(
		array( 'Signature', 'Priority allocation access', '&#8369;2,500 / Month' ),
		array( 'Reserve', 'Private tasting events', '&#8369;5,000 / Month' ),
		array( "Founder&#8217;s Circle", 'All access + concierge sourcing', '&#8369;10,000 / Month' ),
	);
	$member_right = '';
	foreach ( $tiers as $i => $tier ) {
		list( $name, $perks, $price ) = $tier;
		$tier_inner  = tempus_pattern_heading( $name, 3, 3 );
		$tier_inner .= tempus_pattern_paragraph( $perks, 4 );
		$tier_inner .= tempus_pattern_paragraph( $price, 1 );
		$member_right .= tempus_kb_row(
			'tztier' . ( $i + 1 ),
			1,
			tempus_kb_column( 'tztier' . ( $i + 1 ) . 'col', 1, $tier_inner ),
			array( 'bgColor' => 'palette7' )
		);
	}

	$membership_cols  = tempus_kb_column( 'tzmemberl', 1, $member_left );
	$membership_cols .= tempus_kb_column( 'tzmemberr', 2, $member_right );
	$content         .= tempus_kb_row( 'tzmember', 2, $membership_cols );

	return $content;
}

/**
 * Look up a taxonomy term's ID by slug, with a safe fallback of 0 (which
 * simply resolves to "no term filter" for the WooCommerce product blocks,
 * so the pattern still inserts cleanly even before the term exists).
 *
 * @param string $taxonomy
 * @param string $slug
 * @return int
 */
function tempus_homepage_pattern_term_id( $taxonomy, $slug ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return 0;
	}
	$term = get_term_by( 'slug', $slug, $taxonomy );
	return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
}

/**
 * Register the "Tempus" pattern category and the homepage pattern itself.
 */
add_action(
	'init',
	function () {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		register_block_pattern_category(
			'tempus',
			array( 'label' => __( 'Tempus', 'tempus-core' ) )
		);

		register_block_pattern(
			'tempus-core/homepage',
			array(
				'title'       => __( 'Tempus — Homepage', 'tempus-core' ),
				'description' => __( 'Hero, Three Pursuits, Featured Bottles, Rituals, and Membership sections, scaffolded with Kadence Row Layout containers.', 'tempus-core' ),
				'categories'  => array( 'tempus' ),
				'content'     => tempus_homepage_pattern_content(),
			)
		);
	}
);
