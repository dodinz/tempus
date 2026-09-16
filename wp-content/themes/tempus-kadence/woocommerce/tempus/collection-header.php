<?php
/**
 * Tempus — Shop chooser header ("Choose Where to Begin").
 *
 * Eyebrow, title, intro and the cellar stat. Developer-owned copy.
 * Rendered from archive-product.php on /shop only.
 *
 * @package Tempus_Kadence
 */

defined( 'ABSPATH' ) || exit;

$tempus_catalog_count  = function_exists( 'tempus_get_catalog_count' ) ? tempus_get_catalog_count() : 0;
$tempus_category_count = function_exists( 'tempus_get_shop_categories' ) ? count( tempus_get_shop_categories() ) : 0;
?>
<header class="tempus-collection-header">
	<p class="tempus-collection-header__eyebrow"><?php esc_html_e( 'The Collection', 'tempus-kadence' ); ?></p>

	<h1 class="tempus-collection-header__title"><?php esc_html_e( 'Choose Where to Begin', 'tempus-kadence' ); ?></h1>

	<p class="tempus-collection-header__intro">
		<?php esc_html_e( 'Rare whisky, hand-rolled cigars and records worth the needle. Take your time — every pursuit starts with a single choice.', 'tempus-kadence' ); ?>
	</p>

	<?php if ( $tempus_catalog_count || $tempus_category_count ) : ?>
		<dl class="tempus-collection-header__stats">
			<?php if ( $tempus_catalog_count ) : ?>
				<div class="tempus-collection-header__stat">
					<dt class="tempus-collection-header__stat-label"><?php esc_html_e( 'In the cellar', 'tempus-kadence' ); ?></dt>
					<dd class="tempus-collection-header__stat-value"><?php echo esc_html( number_format_i18n( $tempus_catalog_count ) ); ?></dd>
				</div>
			<?php endif; ?>
			<?php if ( $tempus_category_count ) : ?>
				<div class="tempus-collection-header__stat">
					<dt class="tempus-collection-header__stat-label">
						<?php echo esc_html( _n( 'Category', 'Categories', $tempus_category_count, 'tempus-kadence' ) ); ?>
					</dt>
					<dd class="tempus-collection-header__stat-value"><?php echo esc_html( number_format_i18n( $tempus_category_count ) ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>
	<?php endif; ?>
</header>
