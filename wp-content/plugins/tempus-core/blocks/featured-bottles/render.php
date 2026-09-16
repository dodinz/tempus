<?php
/**
 * Block: Tempus — Featured Bottles.
 *
 * Ported from the legacy theme partial template-parts/home/featured-bottles.php.
 * Pulls real WooCommerce products: either those tagged "Featured", or a
 * hand-picked set chosen in ACF (featured_source = manual). Falls back to
 * recent products so the section is never empty during setup. The
 * WooCommerce queries are kept intact from the original partial.
 *
 * @package Tempus_Core
 *
 * @var array $block      Block settings and attributes.
 * @var bool  $is_preview True while rendering the editor preview.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kick = get_field( 'featured_kicker' ) ?: 'The Cellar';
$title = get_field( 'featured_title' ) ?: 'Featured Bottles';
$sub  = get_field( 'featured_subtitle' ) ?: 'A rotating selection from the shelf — the rare, the aged, the quietly perfect.';

if ( ! function_exists( 'wc_get_products' ) ) {
	return; // WooCommerce inactive.
}

$source   = get_field( 'featured_source' ) ?: 'tag';
$products = array();

if ( 'manual' === $source && function_exists( 'get_field' ) ) {
	$picked = get_field( 'featured_products' );
	if ( $picked ) {
		foreach ( $picked as $p ) {
			$products[] = is_object( $p ) ? wc_get_product( $p->ID ) : wc_get_product( $p );
		}
	}
}

if ( empty( $products ) ) {
	// Tagged "Featured", newest first; fall back to any 4 recent products.
	$products = wc_get_products( array(
		'status'  => 'publish',
		'limit'   => 4,
		'tag'     => array( 'featured' ),
		'orderby' => 'date',
		'order'   => 'DESC',
	) );
	if ( empty( $products ) ) {
		$products = wc_get_products( array( 'status' => 'publish', 'limit' => 4, 'orderby' => 'date', 'order' => 'DESC' ) );
	}
}

$classes = array( 'tz-section' );
if ( ! empty( $block['className'] ) ) {
	$classes[] = $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$classes[] = 'align' . $block['align'];
}
$anchor = ! empty( $block['anchor'] ) ? $block['anchor'] : 'shop';
$reveal = 'tz-reveal' . ( ! empty( $is_preview ) ? ' is-visible' : '' );

// The card markup is shared with every WooCommerce product grid and lives in
// the child theme (woocommerce/tempus/bottle-card.php). Without it, show the
// setup hint rather than an empty grid.
$card_template = wc_locate_template( 'tempus/bottle-card.php' );
if ( ! file_exists( $card_template ) ) {
	$products = array();
}
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" id="<?php echo esc_attr( $anchor ); ?>">
	<div class="tz-container">
		<div class="tz-heading <?php echo esc_attr( $reveal ); ?>">
			<span class="tz-kicker"><?php echo esc_html( $kick ); ?></span>
			<h2 class="tz-heading__title"><?php echo esc_html( $title ); ?></h2>
			<p class="tz-heading__subtitle"><?php echo esc_html( $sub ); ?></p>
		</div>

		<?php if ( ! empty( $products ) ) : ?>
			<div class="tz-grid-4">
				<?php foreach ( $products as $bottle ) :
					if ( ! $bottle ) { continue; }
					wc_get_template( 'tempus/bottle-card.php', array( 'bottle' => $bottle, 'extra_class' => $reveal ) );
				endforeach; ?>
			</div>
		<?php else : ?>
			<p style="text-align:center;color:var(--ink-faint)">
				<?php if ( ! file_exists( $card_template ) ) : ?>
					The bottle card template is missing &mdash; activate the Tempus Kadence theme.
				<?php else : ?>
					Add products and tag them &ldquo;Featured&rdquo; to populate this section.
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>
</section>
