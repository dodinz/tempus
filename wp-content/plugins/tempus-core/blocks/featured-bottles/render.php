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
				<?php foreach ( $products as $product ) :
					if ( ! $product ) { continue; }
					$pid   = $product->get_id();
					$badge = function_exists( 'tempus_product_badge' ) ? tempus_product_badge( $product ) : '';
					$cats  = wc_get_product_category_list( $pid );
					$link  = get_permalink( $pid );

					// Meta line: bottle_meta ACF field, else pa_origin · pa_abv attributes.
					$meta = function_exists( 'get_field' ) ? get_field( 'bottle_meta', $pid ) : '';
					if ( empty( $meta ) ) {
						$parts = array_filter( array(
							$product->get_attribute( 'pa_origin' ),
							$product->get_attribute( 'pa_abv' ),
						) );
						$meta = implode( ' · ', $parts );
					}
					?>
					<div class="tz-card <?php echo esc_attr( $reveal ); ?>">
						<a class="tz-card__media" href="<?php echo esc_url( $link ); ?>">
							<?php if ( $badge ) : ?><div class="tz-card__badge"><?php echo wp_kses_post( $badge ); ?></div><?php endif; ?>
							<?php echo $product->get_image('full'); ?>
						</a>
						<div class="tz-card__body">
							<span class="tz-card__cat"><?php echo wp_kses_post( wp_strip_all_tags( $cats ) ); ?></span>
							<h3 class="tz-card__name"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
							<?php if ( $meta ) : ?><p class="tz-card__meta"><?php echo esc_html( $meta ); ?></p><?php endif; ?>
							<div class="tz-card__foot">
								<div class="tz-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
								<div class="tz-card__actions">
									<a class="tz-btn tz-btn--sm tz-card__cart" href="<?php echo esc_url( $product->add_to_cart_url() ); ?>">Add to Cart</a>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p style="text-align:center;color:var(--ink-faint)">Add products and tag them &ldquo;Featured&rdquo; to populate this section.</p>
		<?php endif; ?>
	</div>
</section>
