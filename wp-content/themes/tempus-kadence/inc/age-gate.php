<?php
/**
 * Tempus — Age Verification Gate
 *
 * Blocks the site behind a "Are you 21 or over?" modal until the visitor
 * confirms. The answer is remembered in a cookie for 30 days.
 *
 * Location: /wp-content/themes/kadence-child/inc/age-gate.php
 * Loaded by: a single require_once line in the child theme's functions.php
 *
 * Design: uses the tz- component classes and the tokens in tokens.css.
 * No raw hex values live in this feature.
 *
 * @package Tempus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Never run this file directly.
}

/* -------------------------------------------------------------------------
 * 1. Settings
 *
 * Everything you are likely to change lives here. Each value can also be
 * overridden from functions.php with the tempus_age_gate_settings filter,
 * so you never have to edit this file again after installing it.
 * ---------------------------------------------------------------------- */

function tempus_age_gate_settings() {

	$defaults = array(

		// Name of the cookie that remembers a successful answer.
		'cookie_name'    => 'tempus_age_verified',

		// How many days before the visitor is asked again.
		'cookie_days'    => 30,

		// Where "No" sends people who say they are underage.
		'exit_url'       => 'https://www.google.com',

		// Wordmark and tagline at the top of the panel.
		'brand'          => 'TEMPUS',
		'tagline'        => 'WHISKY · CIGAR · VINYL',

		// The question panel.
		'heading'        => 'Are you 21 or over?',
		'body'           => 'Tempus serves whisky and cigars. Philippine law requires you to be at least 21 years of age to browse or purchase.',
		'yes_label'      => 'Yes, I am 21 or over',
		'no_label'       => 'No',
		'legal'          => 'By entering you confirm your age and accept our Terms of Service and Privacy Policy. Please enjoy responsibly.',

		// The panel shown after someone answers "No".
		'deny_heading'   => 'Come back another time',
		'deny_body'      => 'We are sorry — you must be at least 21 to enter Tempus. Thank you for your honesty.',
		'deny_button'    => 'Leave this site',
		'deny_back'      => 'I answered that by mistake',

		// Let signed-in editors and administrators skip the gate while
		// they are building the site. Set to false to gate everyone.
		'skip_for_staff' => true,
	);

	return apply_filters( 'tempus_age_gate_settings', $defaults );
}

/* -------------------------------------------------------------------------
 * 2. Should the gate run on this request at all?
 * ---------------------------------------------------------------------- */

function tempus_age_gate_is_active() {

	// Never in the admin area, on AJAX/REST/cron calls, or in feeds.
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() ) {
		return false;
	}

	// Never inside the Customizer preview — a full-screen overlay would
	// make the page impossible to edit.
	if ( is_customize_preview() ) {
		return false;
	}

	$settings = tempus_age_gate_settings();

	// Optionally let signed-in staff straight through.
	if ( $settings['skip_for_staff'] && is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return false;
	}

	// Final escape hatch — for example to exclude a trade landing page:
	//   add_filter( 'tempus_age_gate_active', function ( $on ) {
	//       return is_page( 'trade-portal' ) ? false : $on;
	//   } );
	return apply_filters( 'tempus_age_gate_active', true );
}

/* -------------------------------------------------------------------------
 * 3. Load the stylesheet and script
 *
 * The stylesheet is enqueued (not inlined) so it lands in <head> and is
 * render-blocking — that is what stops the homepage flashing into view for
 * a split second before the overlay covers it.
 * ---------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'tempus_age_gate_assets', 20 );

function tempus_age_gate_assets() {

	if ( ! tempus_age_gate_is_active() ) {
		return;
	}

	$dir = get_stylesheet_directory() . '/assets/css/';
	$url = get_stylesheet_directory_uri() . '/assets/';

	wp_enqueue_style(
		'tempus-age-gate',
		$url . 'css/age-gate.css',
		array(), // Custom properties resolve whatever the load order, so no dependency needed.
		file_exists( $dir . 'age-gate.css' ) ? filemtime( $dir . 'age-gate.css' ) : '1.0.0'
	);

	wp_enqueue_script(
		'tempus-age-gate',
		$url . 'js/age-gate.js',
		array(),
		file_exists( get_stylesheet_directory() . '/assets/js/age-gate.js' )
			? filemtime( get_stylesheet_directory() . '/assets/js/age-gate.js' )
			: '1.0.0',
		true // load in the footer
	);

	$settings = tempus_age_gate_settings();

	wp_localize_script(
		'tempus-age-gate',
		'TempusAgeGate',
		array(
			'cookie'  => $settings['cookie_name'],
			'days'    => (int) $settings['cookie_days'],
			'exitUrl' => esc_url_raw( $settings['exit_url'] ),
		)
	);
}

/* -------------------------------------------------------------------------
 * 4. The tiny script that decides, before anything is painted, whether the
 *    overlay is shown
 *
 * It runs in <head> and does one job: if the cookie is missing it puts the
 * class "tz-gate-on" on the <html> element. The CSS keys off that class.
 *
 * Doing it in the browser rather than in PHP is deliberate: it keeps the
 * gate working on pages served from a cache such as WP Rocket or LiteSpeed,
 * where every visitor gets byte-identical HTML.
 * ---------------------------------------------------------------------- */

add_action( 'wp_head', 'tempus_age_gate_head_script', 1 );

function tempus_age_gate_head_script() {

	if ( ! tempus_age_gate_is_active() ) {
		return;
	}

	$settings = tempus_age_gate_settings();
	$cookie   = preg_replace( '/[^A-Za-z0-9_]/', '', $settings['cookie_name'] );

	?>
<script id="tempus-age-gate-boot">
(function () {
	var d = document, h = d.documentElement;
	try {
		/* "?age_gate=reset" in the URL always forces the gate back on. */
		var forced = d.location.search.indexOf( 'age_gate=reset' ) > -1;
		var passed = new RegExp( '(?:^|;\\s*)<?php echo $cookie; ?>=1' ).test( d.cookie );
		if ( forced || ! passed ) {
			h.className += ' tz-gate-on';
		}
	} catch ( e ) {
		h.className += ' tz-gate-on'; /* fail closed */
	}
})();
</script>
	<?php
}

/* -------------------------------------------------------------------------
 * 5. The overlay markup
 *
 * Printed immediately after <body> opens so it is the very first thing in
 * the document — nothing behind it can flash into view.
 *
 * Requires that the theme calls wp_body_open(). Kadence does.
 * ---------------------------------------------------------------------- */

add_action( 'wp_body_open', 'tempus_age_gate_markup' );

function tempus_age_gate_markup() {

	if ( ! tempus_age_gate_is_active() ) {
		return;
	}

	$s = tempus_age_gate_settings();

	?>
<div id="tz-age-gate" class="tz-age-gate" role="dialog" aria-modal="true"
     aria-labelledby="tz-age-gate-heading" aria-describedby="tz-age-gate-body">

	<div class="tz-age-gate__scrim" aria-hidden="true"></div>

	<div class="tz-age-gate__panel">

		<div class="tz-age-gate__brand">
			<span class="tz-age-gate__wordmark">
				<?php
					if ( function_exists( 'has_custom_logo' ) && has_custom_logo() ) {
						echo wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'full' );
					}
				?>
			</span>
			<span class="tz-kicker"><?php echo esc_html( $s['tagline'] ); ?></span>
		</div>

		<div class="tz-age-gate__rule" aria-hidden="true"><span></span></div>

		<!-- Step 1 — the question -->
		<div class="tz-age-gate__step" data-step="ask">

			<h2 id="tz-age-gate-heading" class="tz-age-gate__title">
				<?php echo esc_html( $s['heading'] ); ?>
			</h2>

			<p id="tz-age-gate-body" class="tz-age-gate__body">
				<?php echo esc_html( $s['body'] ); ?>
			</p>

			<div class="tz-age-gate__actions">
				<button type="button" class="tz-btn tz-btn--lg tz-btn--primary" data-age-gate="yes">
					<?php echo esc_html( $s['yes_label'] ); ?>
				</button>
				<button type="button" class="tz-btn tz-btn--lg tz-btn--secondary" data-age-gate="no">
					<?php echo esc_html( $s['no_label'] ); ?>
				</button>
			</div>

			<p class="tz-age-gate__legal"><?php echo esc_html( $s['legal'] ); ?></p>

			<noscript>
				<p class="tz-age-gate__legal tz-age-gate__legal--warn">
					This confirmation needs JavaScript. Please enable it in your browser to continue.
				</p>
			</noscript>
		</div>

		<!-- Step 2 — shown only after "No" -->
		<div class="tz-age-gate__step" data-step="deny" hidden>

			<h2 class="tz-age-gate__title"><?php echo esc_html( $s['deny_heading'] ); ?></h2>

			<p class="tz-age-gate__body"><?php echo esc_html( $s['deny_body'] ); ?></p>

			<div class="tz-age-gate__actions">
				<button type="button" class="tz-btn tz-btn--lg tz-btn--primary" data-age-gate="exit">
					<?php echo esc_html( $s['deny_button'] ); ?>
				</button>
			</div>

			<button type="button" class="tz-age-gate__link" data-age-gate="back">
				<?php echo esc_html( $s['deny_back'] ); ?>
			</button>
		</div>

	</div>
</div>

<noscript>
	<style>
		/* No JavaScript: keep the gate up, hide the buttons that cannot
		   work, and let the explanatory note take their place. */
		.tz-age-gate { display: flex !important; }
		.tz-age-gate__actions { display: none; }
	</style>
</noscript>
	<?php
}
