<?php
/**
 * Homepage — Three Pursuits.
 * @package Tempus
 */
$kick  = tempus_field( 'pursuits_kicker', 'The Tempus Way' );
$title = tempus_field( 'pursuits_title', 'Three pursuits, one evening' );

// Pull repeater rows if present; else fall back to the mockup content.
$rows = function_exists( 'get_field' ) ? get_field( 'pursuits' ) : null;
if ( empty( $rows ) ) {
	$rows = array(
		array( 'num' => '01', 'title' => 'Whisky', 'desc' => "Rare single malts and curated flights — Japanese and Scotch — poured with intention and a sommelier's card." ),
		array( 'num' => '02', 'title' => 'Cigar',  'desc' => 'Cuban and New World cigars, hand-rolled and cared for in our custom-built humidor. The slow burn, savored.' ),
		array( 'num' => '03', 'title' => 'Vinyl',  'desc' => 'The warmth of analog sound — curated playlists and live vinyl nights that turn hours into moments.' ),
	);
}
?>
<section class="tz-section" id="about">
	<div class="tz-container">
		<div class="tz-heading tz-reveal">
			<span class="tz-kicker"><?php echo esc_html( $kick ); ?></span>
			<h2 class="tz-heading__title"><?php echo esc_html( $title ); ?></h2>
		</div>
		<div class="tz-pursuits">
			<?php foreach ( $rows as $r ) : ?>
				<div class="tz-pursuit tz-reveal">
					<span class="tz-pursuit__num"><?php echo esc_html( $r['num'] ); ?></span>
					<h3 class="tz-pursuit__title"><?php echo esc_html( $r['title'] ); ?></h3>
					<p class="tz-pursuit__desc"><?php echo esc_html( $r['desc'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
