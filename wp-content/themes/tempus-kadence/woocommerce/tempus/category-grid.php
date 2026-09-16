<?php
/**
 * Tempus — Shop chooser grid.
 *
 * @package Tempus_Kadence
 *
 * @var array[] $categories From tempus_get_shop_categories().
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $categories ) ) {
	return;
}
?>
<section class="tempus-cat-grid" aria-label="<?php esc_attr_e( 'Product categories', 'tempus-kadence' ); ?>">
	<?php
	foreach ( $categories as $category ) {
		wc_get_template( 'tempus/category-card.php', array( 'category' => $category ) );
	}
	?>
</section>
