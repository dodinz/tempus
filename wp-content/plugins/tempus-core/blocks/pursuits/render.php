<?php
/**
 * Block: Tempus — Three Pursuits.
 *
 * Ported from the legacy theme partial template-parts/home/pursuits.php.
 * The `pursuits` ACF repeater drives the columns; falls back to the mockup
 * content so the section is never empty during setup.
 *
 * @package Tempus_Core
 *
 * @var array $block      Block settings and attributes.
 * @var bool  $is_preview True while rendering the editor preview.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kick  = get_field( 'pursuits_kicker' ) ?: 'The Tempus Way';
$title = get_field( 'pursuits_title' ) ?: 'Three pursuits, one evening';

$rows = get_field( 'pursuits' );
if ( empty( $rows ) ) {
	$rows = array(
		array( 'num' => '01', 'title' => 'Whisky', 'desc' => "Rare single malts and curated flights — Japanese and Scotch — poured with intention and a sommelier's card." ),
		array( 'num' => '02', 'title' => 'Cigar',  'desc' => 'Cuban and New World cigars, hand-rolled and cared for in our custom-built humidor. The slow burn, savored.' ),
		array( 'num' => '03', 'title' => 'Vinyl',  'desc' => 'The warmth of analog sound — curated playlists and live vinyl nights that turn hours into moments.' ),
	);
}

$classes = array( 'tz-section' );
if ( ! empty( $block['className'] ) ) {
	$classes[] = $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$classes[] = 'align' . $block['align'];
}
$anchor = ! empty( $block['anchor'] ) ? $block['anchor'] : 'about';
$reveal = 'tz-reveal' . ( ! empty( $is_preview ) ? ' is-visible' : '' );
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" id="<?php echo esc_attr( $anchor ); ?>">
	<div class="tz-container">
		<div class="tz-heading <?php echo esc_attr( $reveal ); ?>">
			<span class="tz-kicker"><?php echo esc_html( $kick ); ?></span>
			<h2 class="tz-heading__title"><?php echo esc_html( $title ); ?></h2>
		</div>
		<div class="tz-pursuits">
			<?php foreach ( $rows as $r ) : ?>
				<div class="tz-pursuit <?php echo esc_attr( $reveal ); ?>">
					<span class="tz-pursuit__num"><?php echo esc_html( $r['num'] ); ?></span>
					<h3 class="tz-pursuit__title"><?php echo esc_html( $r['title'] ); ?></h3>
					<p class="tz-pursuit__desc"><?php echo esc_html( $r['desc'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
