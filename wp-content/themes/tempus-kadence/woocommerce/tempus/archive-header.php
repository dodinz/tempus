<?php
/**
 * Tempus — Product category/tag archive header.
 *
 * Eyebrow (parent category, else "The Collection"), title, term description
 * and the subcategory chips. Reuses the /shop collection-header styling so
 * both archive types share one header look. Kadence's own archive title is
 * hidden on these pages — see tempus_kadence_shop_layout() in functions.php.
 *
 * @package Tempus_Kadence
 */

defined( 'ABSPATH' ) || exit;

$tempus_term   = get_queried_object();
$tempus_chips  = function_exists( 'tempus_get_archive_chips' ) ? tempus_get_archive_chips() : array();
$tempus_parent = ( $tempus_term instanceof WP_Term && $tempus_term->parent ) ? get_term( $tempus_term->parent, $tempus_term->taxonomy ) : null;
?>
<header class="tempus-collection-header tempus-archive-header">
	<p class="tempus-collection-header__eyebrow">
		<?php
		echo ( $tempus_parent instanceof WP_Term )
			? esc_html( $tempus_parent->name )
			: esc_html__( 'The Collection', 'tempus-kadence' );
		?>
	</p>

	<h1 class="tempus-collection-header__title"><?php woocommerce_page_title(); ?></h1>

	<?php if ( $tempus_term instanceof WP_Term && $tempus_term->description && ! is_paged() ) : ?>
		<div class="tempus-collection-header__intro"><?php echo wp_kses_post( wpautop( $tempus_term->description ) ); ?></div>
	<?php endif; ?>

	<?php if ( $tempus_chips ) : ?>
		<nav class="tempus-chips" aria-label="<?php esc_attr_e( 'Filter by category', 'tempus-kadence' ); ?>">
			<?php foreach ( $tempus_chips as $tempus_chip ) : ?>
				<a class="tempus-chip<?php echo $tempus_chip['active'] ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( $tempus_chip['url'] ); ?>"
					<?php echo $tempus_chip['active'] ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( $tempus_chip['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</header>
