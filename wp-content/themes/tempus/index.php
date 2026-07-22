<?php
/**
 * Fallback template (required by WordPress).
 * Used for any view without a more specific template.
 *
 * @package Tempus
 */
get_header();
?>
<main id="content" class="tz-container" style="padding-top:64px;padding-bottom:64px">
	<?php
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class(); ?>>
				<h1 class="tz-heading__title" style="margin-bottom:24px"><?php the_title(); ?></h1>
				<div class="tz-card__meta" style="font-size:16px;line-height:1.8"><?php the_content(); ?></div>
			</article>
			<?php
		endwhile;
		the_posts_pagination();
	else :
		echo '<p>' . esc_html__( 'Nothing found.', 'tempus' ) . '</p>';
	endif;
	?>
</main>
<?php
get_footer();
