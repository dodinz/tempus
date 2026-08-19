<?php
/**
 * Block: Tempus — Hero.
 *
 * Ported from the legacy theme partial template-parts/home/hero.php.
 * ACF fields replace tempus_field(); WooCommerce shop permalink is the
 * CTA fallback so the section is never empty during setup.
 *
 * @package Tempus_Core
 *
 * @var array  $block      Block settings and attributes (anchor, className, align…).
 * @var string $content    Inner block HTML (unused — dynamic block).
 * @var bool   $is_preview True while rendering the editor preview.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$img   = get_field( 'hero_image' ) ?: '';
$kick  = get_field( 'hero_kicker' ) ?: 'Whisky · Cigar · Vinyl';
$title = get_field( 'hero_title' ) ?: "A place to savor life's most refined pleasure";
$lead  = get_field( 'hero_lead' ) ?: 'Rare bottles, hand-rolled cigars, and analog sound — delivered to your door or poured at the bar.';
$c1l   = get_field( 'hero_cta1_label' ) ?: 'Shop the Collection';
$c1u   = get_field( 'hero_cta1_url' ) ?: ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) );
$c2l   = get_field( 'hero_cta2_label' ) ?: 'Explore Rituals';
$c2u   = get_field( 'hero_cta2_url' ) ?: '#rituals';

// Block chrome: merge Gutenberg anchor / custom class / alignment.
$classes = array( 'tz-hero' );
if ( ! empty( $block['className'] ) ) {
	$classes[] = $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$classes[] = 'align' . $block['align'];
}
$anchor = ! empty( $block['anchor'] ) ? $block['anchor'] : '';

// .tz-reveal starts at opacity:0 and is unhidden by JS on the front end;
// force it visible in the editor preview so the block isn't blank.
$reveal = 'tz-reveal' . ( ! empty( $is_preview ) ? ' is-visible' : '' );
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?><?php echo $img ? ' style="background-image:url(\'' . esc_url( $img ) . '\')"' : ''; ?>>
	<div class="tz-hero__scrim-1"></div>
	<div class="tz-hero__scrim-2"></div>
	<div class="tz-hero__inner <?php echo esc_attr( $reveal ); ?>">
		<span class="tz-kicker"><?php echo esc_html( $kick ); ?></span>
		<h1 class="tz-hero__title"><?php echo esc_html( $title ); ?></h1>
		<p class="tz-hero__lead"><?php echo esc_html( $lead ); ?></p>
		<div class="tz-hero__cta">
			<a class="tz-btn tz-btn--primary tz-btn--lg" href="<?php echo esc_url( $c1u ); ?>"><?php echo esc_html( $c1l ); ?></a>
			<a class="tz-btn tz-btn--secondary tz-btn--lg" href="<?php echo esc_url( $c2u ); ?>"><?php echo esc_html( $c2l ); ?></a>
		</div>
	</div>
</section>
