<?php
/**
 * Tempus — Shop chooser card.
 *
 * Two states off one flag:
 *   live         → whole card links to the category archive.
 *   coming soon  → no anchor, "Coming Soon" in the count slot, no CTA.
 * Missing image and zero products are independent: either one alone swaps
 * the media slot for the placeholder panel.
 *
 * @package Tempus_Kadence
 *
 * @var array $category One item from tempus_get_shop_categories().
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $category['term'] ) ) {
	return;
}

$tempus_term        = $category['term'];
$tempus_coming_soon = ! empty( $category['coming_soon'] );
$tempus_image_id    = (int) $category['image_id'];
$tempus_url         = $tempus_coming_soon ? '' : $category['url'];

$tempus_classes = array( 'tempus-cat-card' );
if ( $tempus_coming_soon ) {
	$tempus_classes[] = 'tempus-cat-card--coming-soon';
}

$tempus_image = $tempus_image_id
	? wp_get_attachment_image( $tempus_image_id, 'large', false, array( 'loading' => 'lazy' ) )
	: '';
?>
<article class="<?php echo esc_attr( implode( ' ', $tempus_classes ) ); ?>">
	<?php if ( $tempus_url ) : ?>
	<a class="tempus-cat-card__link" href="<?php echo esc_url( $tempus_url ); ?>">
	<?php else : ?>
	<div class="tempus-cat-card__link">
	<?php endif; ?>

		<div class="tempus-cat-card__media">
			<?php if ( $tempus_image ) : ?>
				<?php echo $tempus_image; // phpcs:ignore WordPress.Security.EscapeOutput -- core-generated attachment markup. ?>
			<?php else : ?>
				<div class="tempus-cat-card__placeholder">
					<span class="tempus-cat-card__placeholder-label"><?php esc_html_e( 'Category image to come', 'tempus-kadence' ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<div class="tempus-cat-card__body">
			<p class="tempus-cat-card__count">
				<?php
				echo $tempus_coming_soon
					? esc_html__( 'Coming soon', 'tempus-kadence' )
					: esc_html( $category['count_label'] );
				?>
			</p>

			<h2 class="tempus-cat-card__title"><?php echo esc_html( $tempus_term->name ); ?></h2>

			<?php if ( '' !== trim( $tempus_term->description ) ) : ?>
				<div class="tempus-cat-card__desc"><?php echo wp_kses_post( wpautop( $tempus_term->description ) ); ?></div>
			<?php endif; ?>

			<?php if ( ! empty( $category['children'] ) ) : ?>
				<ul class="tempus-cat-card__chips">
					<?php foreach ( $category['children'] as $tempus_child ) : ?>
						<li class="tempus-cat-card__chip"><?php echo esc_html( $tempus_child->name ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $tempus_url ) : ?>
				<span class="tempus-cat-card__cta">
					<?php
					/* translators: %s: category name */
					echo esc_html( sprintf( __( 'Explore %s', 'tempus-kadence' ), $tempus_term->name ) );
					?>
					<span class="tempus-cat-card__arrow" aria-hidden="true">&rarr;</span>
				</span>
			<?php endif; ?>
		</div>

	<?php if ( $tempus_url ) : ?>
	</a>
	<?php else : ?>
	</div>
	<?php endif; ?>
</article>
