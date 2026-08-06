<?php
/**
 * Front page — the Tempus homepage.
 *
 * WordPress uses this template automatically when a static page is set
 * as the front page under Settings → Reading. Header/footer chrome comes
 * from the Kadence parent (header & footer builders); each section is a
 * partial in /template-parts/home/ so they stay small and reorderable.
 *
 * @package Tempus_Kadence
 */

get_header();
?>

<main id="content" class="tz-home">
	<?php
	get_template_part( 'template-parts/home/hero' );
	get_template_part( 'template-parts/home/pursuits' );
	get_template_part( 'template-parts/home/featured-bottles' );
	get_template_part( 'template-parts/home/rituals' );
	get_template_part( 'template-parts/home/membership' );
	?>
</main>

<?php
get_footer();
