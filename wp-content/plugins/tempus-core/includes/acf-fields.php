<?php
/**
 * ACF field groups (registered in code, not the DB).
 *
 * Registering via acf_add_local_field_group keeps the config in version
 * control and identical across environments — the senior-dev default.
 * These fields attach to the page you set as the static front page,
 * so the client edits homepage copy without touching templates.
 *
 * Requires Advanced Custom Fields (free tier is enough for these;
 * the "repeater" fields marked below need ACF PRO).
 *
 * @package Tempus_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'acf/init', function () {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
		'key'    => 'group_tempus_home',
		'title'  => 'Tempus — Homepage',
		'fields' => array(

			/* ---------- HERO ---------- */
			array( 'key' => 'field_hero_tab', 'label' => 'HERO', 'name' => '', 'type' => 'tab' ),
			array( 'key' => 'field_hero_kicker', 'label' => 'Kicker', 'name' => 'hero_kicker', 'type' => 'text', 'default_value' => 'Whisky · Cigar · Vinyl' ),
			array( 'key' => 'field_hero_title', 'label' => 'Title', 'name' => 'hero_title', 'type' => 'text', 'default_value' => "A place to savor life's most refined pleasure" ),
			array( 'key' => 'field_hero_lead', 'label' => 'Lead paragraph', 'name' => 'hero_lead', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Rare bottles, hand-rolled cigars, and analog sound — delivered to your door or poured at the bar.' ),
			array( 'key' => 'field_hero_image', 'label' => 'Background image', 'name' => 'hero_image', 'type' => 'image', 'return_format' => 'url', 'preview_size' => 'medium' ),
			array( 'key' => 'field_hero_cta1_label', 'label' => 'Primary button label', 'name' => 'hero_cta1_label', 'type' => 'text', 'default_value' => 'Shop the Collection' ),
			array( 'key' => 'field_hero_cta1_url', 'label' => 'Primary button URL', 'name' => 'hero_cta1_url', 'type' => 'url' ),
			array( 'key' => 'field_hero_cta2_label', 'label' => 'Secondary button label', 'name' => 'hero_cta2_label', 'type' => 'text', 'default_value' => 'Explore Rituals' ),
			array( 'key' => 'field_hero_cta2_url', 'label' => 'Secondary button URL', 'name' => 'hero_cta2_url', 'type' => 'url' ),

			/* ---------- THREE PURSUITS ---------- */
			array( 'key' => 'field_pursuits_tab', 'label' => 'THREE PURSUITS', 'name' => '', 'type' => 'tab' ),
			array( 'key' => 'field_pursuits_kicker', 'label' => 'Kicker', 'name' => 'pursuits_kicker', 'type' => 'text', 'default_value' => 'The Tempus Way' ),
			array( 'key' => 'field_pursuits_title', 'label' => 'Section title', 'name' => 'pursuits_title', 'type' => 'text', 'default_value' => 'Three pursuits, one evening' ),
			array(
				'key' => 'field_pursuits', 'label' => 'Pursuits (3)', 'name' => 'pursuits',
				'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add pursuit', 'max' => 3, // ACF PRO
				'sub_fields' => array(
					array( 'key' => 'field_pursuit_num', 'label' => 'Number', 'name' => 'num', 'type' => 'text', 'default_value' => '01' ),
					array( 'key' => 'field_pursuit_title', 'label' => 'Title', 'name' => 'title', 'type' => 'text' ),
					array( 'key' => 'field_pursuit_desc', 'label' => 'Description', 'name' => 'desc', 'type' => 'textarea', 'rows' => 3 ),
				),
			),

			/* ---------- FEATURED BOTTLES ---------- */
			array( 'key' => 'field_featured_tab', 'label' => 'FEATURED BOTTLES', 'name' => '', 'type' => 'tab' ),
			array( 'key' => 'field_featured_kicker', 'label' => 'Kicker', 'name' => 'featured_kicker', 'type' => 'text', 'default_value' => 'The Cellar' ),
			array( 'key' => 'field_featured_title', 'label' => 'Title', 'name' => 'featured_title', 'type' => 'text', 'default_value' => 'Featured Bottles' ),
			array( 'key' => 'field_featured_subtitle', 'label' => 'Subtitle', 'name' => 'featured_subtitle', 'type' => 'text', 'default_value' => 'A rotating selection from the shelf — the rare, the aged, the quietly perfect.' ),
			array( 'key' => 'field_featured_source', 'label' => 'Products shown', 'name' => 'featured_source', 'type' => 'radio', 'choices' => array( 'tag' => 'Products tagged "Featured"', 'manual' => 'Hand-picked below' ), 'default_value' => 'tag' ),
			array( 'key' => 'field_featured_products', 'label' => 'Hand-picked products', 'name' => 'featured_products', 'type' => 'relationship', 'post_type' => array( 'product' ), 'max' => 4, 'filters' => array( 'search', 'taxonomy' ),
				'conditional_logic' => array( array( array( 'field' => 'field_featured_source', 'operator' => '==', 'value' => 'manual' ) ) ),
			),

			/* ---------- RITUALS ---------- */
			array( 'key' => 'field_rituals_tab', 'label' => 'RITUALS', 'name' => '', 'type' => 'tab' ),
			array( 'key' => 'field_rituals_kicker', 'label' => 'Kicker', 'name' => 'rituals_kicker', 'type' => 'text', 'default_value' => 'Curated Rituals' ),
			array( 'key' => 'field_rituals_title', 'label' => 'Title', 'name' => 'rituals_title', 'type' => 'text', 'default_value' => 'Composed for the Moment' ),
			array( 'key' => 'field_rituals_subtitle', 'label' => 'Subtitle', 'name' => 'rituals_subtitle', 'type' => 'text', 'default_value' => 'Each ritual pairs a whisky, a cigar, and a record — an evening, curated.' ),
			array( 'key' => 'field_rituals_products', 'label' => 'Ritual bundle products (3)', 'name' => 'rituals_products', 'type' => 'relationship', 'post_type' => array( 'product' ), 'max' => 3, 'filters' => array( 'search' ),
				'instructions' => 'Select the 3 bundle products (create these as WooCommerce Product Bundles).' ),

			/* ---------- MEMBERSHIP ---------- */
			array( 'key' => 'field_member_tab', 'label' => 'MEMBERSHIP', 'name' => '', 'type' => 'tab' ),
			array( 'key' => 'field_member_kicker', 'label' => 'Kicker', 'name' => 'member_kicker', 'type' => 'text', 'default_value' => 'Founding Members' ),
			array( 'key' => 'field_member_title', 'label' => 'Title', 'name' => 'member_title', 'type' => 'text', 'default_value' => 'Secure your place before the doors open.' ),
			array( 'key' => 'field_member_body', 'label' => 'Body', 'name' => 'member_body', 'type' => 'textarea', 'rows' => 3, 'default_value' => 'Founding membership is limited. Those who join before opening day lock in priority access to rare allocations, private tasting events, and rates that will never be offered again.' ),
			array( 'key' => 'field_member_form', 'label' => 'Waitlist form shortcode', 'name' => 'member_form', 'type' => 'text', 'instructions' => 'Paste the WPForms/CF7 shortcode for the Name/Email/Mobile waitlist form. Leave blank to show the static placeholder form.' ),
			array(
				'key' => 'field_tiers', 'label' => 'Membership tiers', 'name' => 'tiers',
				'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add tier', 'max' => 4, // ACF PRO
				'sub_fields' => array(
					array( 'key' => 'field_tier_name', 'label' => 'Name', 'name' => 'name', 'type' => 'text' ),
					array( 'key' => 'field_tier_perks', 'label' => 'Perks', 'name' => 'perks', 'type' => 'text' ),
					array( 'key' => 'field_tier_price', 'label' => 'Price', 'name' => 'price', 'type' => 'text' ),
					array( 'key' => 'field_tier_cycle', 'label' => 'Billing cycle label', 'name' => 'cycle', 'type' => 'text', 'default_value' => '/ Month' ),
					array( 'key' => 'field_tier_featured', 'label' => 'Featured ("Most Chosen")', 'name' => 'featured', 'type' => 'true_false', 'ui' => 1 ),
				),
			),
		),

		// Show these fields only on the page set as the static front page.
		'location' => array(
			array( array( 'param' => 'page_type', 'operator' => '==', 'value' => 'front_page' ) ),
		),
		'menu_order' => 0,
		'position'   => 'normal',
		'style'      => 'default',
	) );
} );
