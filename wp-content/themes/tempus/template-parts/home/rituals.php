<?php
/**
 * Homepage — Rituals (curated bundles).
 *
 * Each ritual is a WooCommerce Product Bundle (whisky + cigar + vinyl).
 * Selected via ACF relationship; falls back to mockup placeholders.
 *
 * @package Tempus
 */
$kick  = tempus_field( 'rituals_kicker', 'Curated Rituals' );
$title = tempus_field( 'rituals_title', 'Composed for the Moment' );
$sub   = tempus_field( 'rituals_subtitle', 'Each ritual pairs a whisky, a cigar, and a record — an evening, curated.' );

$bundles = array();
if ( function_exists( 'get_field' ) && function_exists( 'wc_get_product' ) ) {
	$picked = get_field( 'rituals_products' );
	if ( $picked ) {
		foreach ( $picked as $p ) {
			$prod = is_object( $p ) ? wc_get_product( $p->ID ) : wc_get_product( $p );
			if ( $prod ) {
				$bundles[] = array(
					'name'  => $prod->get_name(),
					'price' => $prod->get_price_html(),
					'img'   => wp_get_attachment_image_url( $prod->get_image_id(), 'large' ),
					'url'   => get_permalink( $prod->get_id() ),
					'desc'  => wp_trim_words( $prod->get_short_description(), 16 ),
				);
			}
		}
	}
}
if ( empty( $bundles ) ) {
	$bundles = array(
		array( 'name' => 'Unwind', 'price' => '', 'img' => '', 'url' => '#', 'desc' => 'Yamazaki 12 · My Father Le Bijou · Blue in Green' ),
		array( 'name' => 'Celebrate', 'price' => '', 'img' => '', 'url' => '#', 'desc' => 'Hibiki Harmony · Padrón 1926 · Kind of Blue' ),
		array( 'name' => 'Late Night', 'price' => '', 'img' => '', 'url' => '#', 'desc' => "Nikka Yoichi · My Father Le Bijou · 'Round Midnight" ),
	);
}
?>
<section class="tz-section tz-section--alt" id="rituals">
	<div class="tz-container">
		<div class="tz-heading tz-reveal">
			<span class="tz-kicker"><?php echo esc_html( $kick ); ?></span>
			<h2 class="tz-heading__title"><?php echo esc_html( $title ); ?></h2>
			<p class="tz-heading__subtitle"><?php echo esc_html( $sub ); ?></p>
		</div>
		<div class="tz-grid-3">
			<?php foreach ( $bundles as $b ) : ?>
				<a class="tz-card tz-reveal" href="<?php echo esc_url( $b['url'] ); ?>">
					<div class="tz-card__media" <?php if ( $b['img'] ) : ?>style="background-image:url('<?php echo esc_url( $b['img'] ); ?>');background-size:cover;background-position:center"<?php endif; ?>></div>
					<div class="tz-card__body">
						<span class="tz-card__cat">The Ritual</span>
						<h3 class="tz-card__name"><?php echo esc_html( $b['name'] ); ?></h3>
						<p class="tz-card__meta"><?php echo esc_html( $b['desc'] ); ?></p>
						<?php if ( $b['price'] ) : ?>
							<div class="tz-card__foot"><span class="tz-card__price"><?php echo wp_kses_post( $b['price'] ); ?></span><span class="tz-btn tz-btn--secondary tz-btn--sm">View</span></div>
						<?php endif; ?>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
