<?php
/**
 * Tempus — Designed empty state for product loops.
 *
 * Overrides woocommerce/templates/loop/no-products-found.php
 *
 * Reached when an empty category archive is visited directly (bookmark,
 * search engine) or a product search/filter returns nothing. Category and
 * tag archives get the same "Coming Soon" language as the shop chooser card.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package Tempus_Kadence
 * @version 7.8.0
 */

defined( 'ABSPATH' ) || exit;

$tempus_is_term = is_product_taxonomy() && ! is_search();
$tempus_term    = $tempus_is_term ? get_queried_object() : null;
$tempus_shop    = wc_get_page_permalink( 'shop' );
?>
<div class="woocommerce-no-products-found tempus-empty">
	<p class="tempus-empty__eyebrow">
		<?php
		echo $tempus_is_term
			? esc_html__( 'Coming soon', 'tempus-kadence' )
			: esc_html__( 'Nothing found', 'tempus-kadence' );
		?>
	</p>

	<h2 class="tempus-empty__title">
		<?php
		if ( $tempus_term instanceof WP_Term ) {
			echo esc_html( $tempus_term->name );
		} else {
			esc_html_e( 'No products match your selection', 'tempus-kadence' );
		}
		?>
	</h2>

	<p class="tempus-empty__text">
		<?php
		echo $tempus_is_term
			? esc_html__( 'We are still curating this part of the cellar. Check back soon — or begin somewhere else.', 'tempus-kadence' )
			: esc_html__( 'Try a different search, or return to the collection and choose where to begin.', 'tempus-kadence' );
		?>
	</p>

	<?php if ( $tempus_shop ) : ?>
		<a class="tempus-empty__link" href="<?php echo esc_url( $tempus_shop ); ?>">
			<span aria-hidden="true">&larr;</span>
			<?php esc_html_e( 'Back to the collection', 'tempus-kadence' ); ?>
		</a>
	<?php endif; ?>
</div>
