<?php
/**
 * Block: Tempus — Membership / Founding Members.
 *
 * Ported from the legacy theme partial template-parts/home/membership.php.
 * Left column: waitlist capture form (a shortcode from the member_form field
 * if provided, else a static disabled placeholder). Right column: membership
 * tier cards driven by the `tiers` ACF repeater, with a mockup fallback.
 *
 * @package Tempus_Core
 *
 * @var array $block      Block settings and attributes.
 * @var bool  $is_preview True while rendering the editor preview.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kick = get_field( 'member_kicker' ) ?: 'Founding Members';
$title = get_field( 'member_title' ) ?: 'Secure your place before the doors open.';
$body = get_field( 'member_body' ) ?: 'Founding membership is limited. Those who join before opening day lock in priority access to rare allocations, private tasting events, and rates that will never be offered again.';
$form = get_field( 'member_form' ) ?: ''; // shortcode string

$tiers = get_field( 'tiers' );
if ( empty( $tiers ) ) {
	$tiers = array(
		array( 'name' => 'Connoisseur', 'perks' => 'Monthly delivery · 10% off store · Priority access', 'price' => '₱3,500', 'cycle' => '/ Month', 'featured' => false ),
		array( 'name' => 'Reserve', 'perks' => 'Quarterly box · 20% off · Private tastings · Lounge access', 'price' => '₱8,500', 'cycle' => '/ Month', 'featured' => false ),
		array( 'name' => 'Founder', 'perks' => 'Everything in Reserve · Lifetime rate lock · Named chair', 'price' => '₱15,000', 'cycle' => '/ Month', 'featured' => true ),
	);
}

$classes = array( 'tz-membership' );
if ( ! empty( $block['className'] ) ) {
	$classes[] = $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$classes[] = 'align' . $block['align'];
}
$anchor = ! empty( $block['anchor'] ) ? $block['anchor'] : 'membership';
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" id="<?php echo esc_attr( $anchor ); ?>">
	<div class="tz-membership__ghost" aria-hidden="true">TEMPUS</div>
	<div class="tz-membership__inner">

		<div style="display:flex;flex-direction:column;gap:26px">
			<span class="tz-kicker"><?php echo esc_html( $kick ); ?></span>
			<h2 class="tz-membership__title"><?php echo esc_html( $title ); ?></h2>
			<p style="margin:0;font-size:17px;line-height:1.75;color:var(--on-surface-variant);max-width:520px"><?php echo esc_html( $body ); ?></p>
			<p style="margin:0;font-size:15px;font-weight:600;color:var(--gold-aged)">The lounge opens soon. The waitlist closes sooner.</p>

			<?php if ( $form ) : ?>
				<?php echo do_shortcode( $form ); ?>
			<?php else : ?>
				<!-- Static placeholder. Replace with a real WPForms/CF7 form via the
				     "Waitlist form shortcode" field so submissions are stored + emailed. -->
				<div class="tz-form">
					<div class="tz-form__row">
						<input class="tz-input" type="text" placeholder="Full Name" disabled>
						<input class="tz-input" type="email" placeholder="Email Address" disabled>
					</div>
					<input class="tz-input" type="tel" placeholder="Mobile Number" disabled>
					<button class="tz-btn tz-btn--primary tz-btn--lg tz-btn--full" disabled>Join the Waitlist</button>
					<p style="margin:0;font-size:12px;color:var(--ink-faint)">Connect a form plugin to activate this form.</p>
				</div>
			<?php endif; ?>
		</div>

		<div style="display:flex;flex-direction:column;gap:20px">
			<span class="tz-kicker">Membership Tiers</span>
			<div style="display:flex;flex-direction:column;gap:20px">
				<?php foreach ( $tiers as $t ) :
					$featured = ! empty( $t['featured'] );
					?>
					<div class="tz-tier<?php echo $featured ? ' tz-tier--featured' : ''; ?>">
						<div style="display:flex;flex-direction:column;gap:10px">
							<div style="display:flex;align-items:center;gap:14px">
								<span class="tz-tier__name"><?php echo esc_html( $t['name'] ); ?></span>
								<?php if ( $featured ) : ?><span class="tz-badge tz-badge--foil">Most Chosen</span><?php endif; ?>
							</div>
							<span class="tz-tier__perks"><?php echo esc_html( $t['perks'] ); ?></span>
						</div>
						<div style="text-align:right;white-space:nowrap">
							<div class="tz-tier__price"><?php echo esc_html( $t['price'] ); ?></div>
							<div class="tz-tier__cycle"><?php echo esc_html( $t['cycle'] ); ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

	</div>
</section>
