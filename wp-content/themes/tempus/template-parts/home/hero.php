<?php
/**
 * Homepage — Hero.
 * @package Tempus
 */
$img   = tempus_field( 'hero_image', TEMPUS_URI . '/assets/images/hero-lounge.jpg' );
$kick  = tempus_field( 'hero_kicker', 'Whisky · Cigar · Vinyl' );
$title = tempus_field( 'hero_title', "A place to savor life's most refined pleasure" );
$lead  = tempus_field( 'hero_lead', 'Rare bottles, hand-rolled cigars, and analog sound — delivered to your door or poured at the bar.' );
$c1l   = tempus_field( 'hero_cta1_label', 'Shop the Collection' );
$c1u   = tempus_field( 'hero_cta1_url', wc_get_page_permalink( 'shop' ) );
$c2l   = tempus_field( 'hero_cta2_label', 'Explore Rituals' );
$c2u   = tempus_field( 'hero_cta2_url', '#rituals' );
?>
<section class="tz-hero" style="background-image:url('<?php echo esc_url( $img ); ?>')">
	<div class="tz-hero__scrim-1"></div>
	<div class="tz-hero__scrim-2"></div>
	<div class="tz-hero__inner tz-reveal">
		<span class="tz-kicker"><?php echo esc_html( $kick ); ?></span>
		<h1 class="tz-hero__title"><?php echo esc_html( $title ); ?></h1>
		<p class="tz-hero__lead"><?php echo esc_html( $lead ); ?></p>
		<div class="tz-hero__cta">
			<a class="tz-btn tz-btn--primary tz-btn--lg" href="<?php echo esc_url( $c1u ); ?>"><?php echo esc_html( $c1l ); ?></a>
			<a class="tz-btn tz-btn--secondary tz-btn--lg" href="<?php echo esc_url( $c2u ); ?>"><?php echo esc_html( $c2l ); ?></a>
		</div>
	</div>
</section>
