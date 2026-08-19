<?php
/**
 * Tempus — ACF field groups for the five homepage blocks.
 *
 * Each group is attached to its block via a location rule
 * `block == tempus/<name>`, NOT to a page. Field `name` values match
 * exactly what each block's render.php reads via get_field().
 *
 * DEPENDENCIES:
 *  - Advanced Custom Fields (free) covers everything EXCEPT the two
 *    `repeater` fields (Pursuits, Membership tiers) which need ACF PRO.
 *  - Requires the five blocks to be registered as tempus/hero,
 *    tempus/pursuits, tempus/featured-bottles, tempus/rituals,
 *    tempus/membership.
 *
 * Fold this into includes/blocks.php, or require it from there.
 *
 * @package Tempus_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'acf/init', 'tempus_register_block_field_groups' );

function tempus_register_block_field_groups() {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	/* =========================================================
	 * 1) HERO  →  block: tempus/hero
	 *    render.php reads: hero_kicker, hero_title, hero_lead,
	 *    hero_image (URL), hero_cta1_label, hero_cta1_url,
	 *    hero_cta2_label, hero_cta2_url
	 * ========================================================= */
	acf_add_local_field_group( array(
		'key'      => 'group_tempus_hero',
		'title'    => 'Tempus Hero',
		'location' => array( array( array(
			'param' => 'block', 'operator' => '==', 'value' => 'tempus/hero',
		) ) ),
		'fields'   => array(
			array(
				'key' => 'field_hero_kicker', 'label' => 'Kicker', 'name' => 'hero_kicker',
				'type' => 'text', 'default_value' => 'Whisky · Cigar · Vinyl',
			),
			array(
				'key' => 'field_hero_title', 'label' => 'Title (H1)', 'name' => 'hero_title',
				'type' => 'text', 'required' => 1,
				'default_value' => "A place to savor life's most refined pleasure",
			),
			array(
				'key' => 'field_hero_lead', 'label' => 'Lead paragraph', 'name' => 'hero_lead',
				'type' => 'textarea', 'rows' => 2, 'new_lines' => '',
			),
			array(
				'key' => 'field_hero_image', 'label' => 'Background image', 'name' => 'hero_image',
				'type' => 'image', 'return_format' => 'url', 'preview_size' => 'medium', 'library' => 'all',
				'instructions' => 'Landscape, min 1600px wide. A dark scrim is applied automatically.',
			),
			array(
				'key' => 'field_hero_cta1_label', 'label' => 'Primary button — label', 'name' => 'hero_cta1_label',
				'type' => 'text', 'default_value' => 'Shop the Collection',
			),
			array(
				'key' => 'field_hero_cta1_url', 'label' => 'Primary button — URL', 'name' => 'hero_cta1_url',
				'type' => 'url',
			),
			array(
				'key' => 'field_hero_cta2_label', 'label' => 'Secondary button — label', 'name' => 'hero_cta2_label',
				'type' => 'text', 'default_value' => 'Explore Rituals',
			),
			array(
				'key' => 'field_hero_cta2_url', 'label' => 'Secondary button — URL', 'name' => 'hero_cta2_url',
				'type' => 'url',
			),
		),
	) );

	/* =========================================================
	 * 2) THREE PURSUITS  →  block: tempus/pursuits
	 *    render.php reads: pursuits_kicker, pursuits_title,
	 *    pursuits (repeater of: num, title, desc)
	 *    NOTE: repeater requires ACF PRO.
	 * ========================================================= */
	acf_add_local_field_group( array(
		'key'      => 'group_tempus_pursuits',
		'title'    => 'Tempus Three Pursuits',
		'location' => array( array( array(
			'param' => 'block', 'operator' => '==', 'value' => 'tempus/pursuits',
		) ) ),
		'fields'   => array(
			array(
				'key' => 'field_pursuits_kicker', 'label' => 'Kicker', 'name' => 'pursuits_kicker',
				'type' => 'text', 'default_value' => 'The Tempus Way',
			),
			array(
				'key' => 'field_pursuits_title', 'label' => 'Section title', 'name' => 'pursuits_title',
				'type' => 'text', 'default_value' => 'Three pursuits, one evening',
			),
			array(
				'key' => 'field_pursuits', 'label' => 'Pursuits', 'name' => 'pursuits',
				'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add pursuit',
				'min' => 3, 'max' => 3,
				'sub_fields' => array(
					array(
						'key' => 'field_pursuit_num', 'label' => 'Number', 'name' => 'num',
						'type' => 'text', 'default_value' => '01', 'wrapper' => array( 'width' => '20' ),
					),
					array(
						'key' => 'field_pursuit_title', 'label' => 'Title', 'name' => 'title',
						'type' => 'text', 'wrapper' => array( 'width' => '30' ),
					),
					array(
						'key' => 'field_pursuit_desc', 'label' => 'Description', 'name' => 'desc',
						'type' => 'textarea', 'rows' => 3, 'wrapper' => array( 'width' => '50' ),
					),
				),
			),
		),
	) );

	/* =========================================================
	 * 3) FEATURED BOTTLES  →  block: tempus/featured-bottles
	 *    render.php reads: featured_kicker, featured_title,
	 *    featured_subtitle, featured_source (tag|manual),
	 *    featured_products (relationship, only when source=manual)
	 * ========================================================= */
	acf_add_local_field_group( array(
		'key'      => 'group_tempus_featured',
		'title'    => 'Tempus Featured Bottles',
		'location' => array( array( array(
			'param' => 'block', 'operator' => '==', 'value' => 'tempus/featured-bottles',
		) ) ),
		'fields'   => array(
			array(
				'key' => 'field_featured_kicker', 'label' => 'Kicker', 'name' => 'featured_kicker',
				'type' => 'text', 'default_value' => 'The Cellar',
			),
			array(
				'key' => 'field_featured_title', 'label' => 'Title', 'name' => 'featured_title',
				'type' => 'text', 'default_value' => 'Featured Bottles',
			),
			array(
				'key' => 'field_featured_subtitle', 'label' => 'Subtitle', 'name' => 'featured_subtitle',
				'type' => 'text',
				'default_value' => 'A rotating selection from the shelf — the rare, the aged, the quietly perfect.',
			),
			array(
				'key' => 'field_featured_source', 'label' => 'Which products?', 'name' => 'featured_source',
				'type' => 'radio', 'default_value' => 'tag',
				'choices' => array(
					'tag'    => 'Automatic — products tagged "Featured"',
					'manual' => 'Hand-picked below',
				),
			),
			array(
				'key' => 'field_featured_products', 'label' => 'Hand-picked products', 'name' => 'featured_products',
				'type' => 'relationship', 'post_type' => array( 'product' ),
				'min' => 1, 'max' => 4, 'filters' => array( 'search', 'taxonomy' ),
				'return_format' => 'object',
				'conditional_logic' => array( array( array(
					'field' => 'field_featured_source', 'operator' => '==', 'value' => 'manual',
				) ) ),
			),
		),
	) );

	/* =========================================================
	 * 4) RITUALS  →  block: tempus/rituals
	 *    render.php reads: rituals_kicker, rituals_title,
	 *    rituals_subtitle, rituals_products (relationship, max 3)
	 * ========================================================= */
	acf_add_local_field_group( array(
		'key'      => 'group_tempus_rituals',
		'title'    => 'Tempus Rituals',
		'location' => array( array( array(
			'param' => 'block', 'operator' => '==', 'value' => 'tempus/rituals',
		) ) ),
		'fields'   => array(
			array(
				'key' => 'field_rituals_kicker', 'label' => 'Kicker', 'name' => 'rituals_kicker',
				'type' => 'text', 'default_value' => 'Curated Rituals',
			),
			array(
				'key' => 'field_rituals_title', 'label' => 'Title', 'name' => 'rituals_title',
				'type' => 'text', 'default_value' => 'Composed for the Moment',
			),
			array(
				'key' => 'field_rituals_subtitle', 'label' => 'Subtitle', 'name' => 'rituals_subtitle',
				'type' => 'text',
				'default_value' => 'Each ritual pairs a whisky, a cigar, and a record — an evening, curated.',
			),
			array(
				'key' => 'field_rituals_products', 'label' => 'Ritual bundle products', 'name' => 'rituals_products',
				'type' => 'relationship', 'post_type' => array( 'product' ),
				'min' => 0, 'max' => 3, 'filters' => array( 'search' ),
				'return_format' => 'object',
				'instructions' => 'Select the 3 bundle products (built with WooCommerce Product Bundles). Leave empty to show placeholder rituals.',
			),
		),
	) );

	/* =========================================================
	 * 5) MEMBERSHIP  →  block: tempus/membership
	 *    render.php reads: member_kicker, member_title, member_body,
	 *    member_form (shortcode string), tiers (repeater of:
	 *    name, perks, price, cycle, featured)
	 *    NOTE: repeater requires ACF PRO.
	 * ========================================================= */
	acf_add_local_field_group( array(
		'key'      => 'group_tempus_membership',
		'title'    => 'Tempus Membership',
		'location' => array( array( array(
			'param' => 'block', 'operator' => '==', 'value' => 'tempus/membership',
		) ) ),
		'fields'   => array(
			array(
				'key' => 'field_member_kicker', 'label' => 'Kicker', 'name' => 'member_kicker',
				'type' => 'text', 'default_value' => 'Founding Members',
			),
			array(
				'key' => 'field_member_title', 'label' => 'Title', 'name' => 'member_title',
				'type' => 'text', 'default_value' => 'Secure your place before the doors open.',
			),
			array(
				'key' => 'field_member_body', 'label' => 'Body', 'name' => 'member_body',
				'type' => 'textarea', 'rows' => 3, 'new_lines' => '',
				'default_value' => 'Founding membership is limited. Those who join before opening day lock in priority access to rare allocations, private tasting events, and rates that will never be offered again.',
			),
			array(
				'key' => 'field_member_form', 'label' => 'Waitlist form shortcode', 'name' => 'member_form',
				'type' => 'text',
				'instructions' => 'Paste the WPForms/CF7 shortcode for the Name/Email/Mobile waitlist form, e.g. [wpforms id="123"]. Leave blank to show the disabled placeholder form.',
			),
			array(
				'key' => 'field_tiers', 'label' => 'Membership tiers', 'name' => 'tiers',
				'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add tier',
				'min' => 1, 'max' => 4,
				'sub_fields' => array(
					array(
						'key' => 'field_tier_name', 'label' => 'Name', 'name' => 'name',
						'type' => 'text', 'wrapper' => array( 'width' => '25' ),
					),
					array(
						'key' => 'field_tier_perks', 'label' => 'Perks (one line)', 'name' => 'perks',
						'type' => 'text', 'wrapper' => array( 'width' => '40' ),
					),
					array(
						'key' => 'field_tier_price', 'label' => 'Price', 'name' => 'price',
						'type' => 'text', 'wrapper' => array( 'width' => '20' ),
						'instructions' => 'Include currency symbol, e.g. ₱3,500',
					),
					array(
						'key' => 'field_tier_cycle', 'label' => 'Cycle', 'name' => 'cycle',
						'type' => 'text', 'default_value' => '/ Month', 'wrapper' => array( 'width' => '15' ),
					),
					array(
						'key' => 'field_tier_featured', 'label' => 'Featured ("Most Chosen")', 'name' => 'featured',
						'type' => 'true_false', 'ui' => 1, 'default_value' => 0,
					),
				),
			),
		),
	) );
}
