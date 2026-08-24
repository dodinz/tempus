<?php
/**
 * Waitlist form rendering.
 *
 * Usage: [tempus_waitlist_form]
 *        [tempus_waitlist_form button="Join the Waitlist"]
 *
 * @package Tempus_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register front-end assets.
 *
 * They live in the plugin, not the child theme, so the whole feature travels
 * as one unit. tokens.css is a child-theme file, so every custom property
 * below carries a literal fallback — if the theme changes the form still
 * renders in brand colours rather than browser defaults.
 */
add_action( 'wp_enqueue_scripts', function () {

	wp_register_style(
		'tempus-waitlist',
		TEMPUS_CORE_URL . 'assets/css/waitlist.css',
		[],
		TEMPUS_WAITLIST_VERSION
	);

	wp_register_script(
		'tempus-waitlist',
		TEMPUS_CORE_URL . 'assets/js/waitlist.js',
		[],
		TEMPUS_WAITLIST_VERSION,
		true
	);

	wp_localize_script( 'tempus-waitlist', 'TempusWaitlist', [
		'endpoint' => esc_url_raw( rest_url( 'tempus/v1/waitlist' ) ),
	] );
} );

/**
 * Render the form.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function tempus_waitlist_form_shortcode( $atts = [] ) {

	$atts = shortcode_atts( [
		'button'  => __( 'Join the Waitlist', 'tempus' ),
		'consent' => __( 'By joining, you agree to receive updates about TEMPUS. No spam. Ever.', 'tempus' ),
	], $atts, 'tempus_waitlist_form' );

	wp_enqueue_style( 'tempus-waitlist' );
	wp_enqueue_script( 'tempus-waitlist' );

	$ts    = (string) time();
	$token = tempus_waitlist_token( $ts );
	$uid   = wp_unique_id( 'tempus-wl-' );

	ob_start();
	?>
	<div class="tempus-waitlist" data-tempus-waitlist>

		<form class="tempus-waitlist__form" novalidate>

			<input type="hidden" name="ts" value="<?php echo esc_attr( $ts ); ?>">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<input type="hidden" name="tier" value="" data-tempus-tier-input>

			<?php // Honeypot. Hidden from sight AND from the tab order AND from screen readers. ?>
			<div class="tempus-waitlist__hp" aria-hidden="true">
				<label for="<?php echo esc_attr( $uid ); ?>-website">Website</label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-website"
				       name="website" tabindex="-1" autocomplete="off">
			</div>

			<div class="tempus-waitlist__field tempus-waitlist__field--half">
				<label class="tempus-sr-only" for="<?php echo esc_attr( $uid ); ?>-name">
					<?php esc_html_e( 'Full Name', 'tempus' ); ?>
				</label>
				<input type="text"
				       id="<?php echo esc_attr( $uid ); ?>-name"
				       name="name"
				       placeholder="<?php esc_attr_e( 'Full Name', 'tempus' ); ?>"
				       autocomplete="name"
				       required>
				<p class="tempus-waitlist__error" data-error-for="name"></p>
			</div>

			<div class="tempus-waitlist__field tempus-waitlist__field--half">
				<label class="tempus-sr-only" for="<?php echo esc_attr( $uid ); ?>-email">
					<?php esc_html_e( 'Email Address', 'tempus' ); ?>
				</label>
				<input type="email"
				       id="<?php echo esc_attr( $uid ); ?>-email"
				       name="email"
				       placeholder="<?php esc_attr_e( 'Email Address', 'tempus' ); ?>"
				       autocomplete="email"
				       required>
				<p class="tempus-waitlist__error" data-error-for="email"></p>
			</div>

			<div class="tempus-waitlist__field tempus-waitlist__field--full">
				<label class="tempus-sr-only" for="<?php echo esc_attr( $uid ); ?>-mobile">
					<?php esc_html_e( 'Mobile Number', 'tempus' ); ?>
				</label>
				<input type="tel"
				       id="<?php echo esc_attr( $uid ); ?>-mobile"
				       name="mobile"
				       placeholder="<?php esc_attr_e( 'Mobile Number', 'tempus' ); ?>"
				       autocomplete="tel"
				       required>
				<p class="tempus-waitlist__error" data-error-for="mobile"></p>
			</div>

			<p class="tempus-waitlist__tier-readout" data-tempus-tier-readout></p>

			<div class="tempus-waitlist__field tempus-waitlist__field--full">
				<button type="submit" class="tempus-waitlist__submit">
					<span data-submit-label><?php echo esc_html( $atts['button'] ); ?></span>
				</button>
			</div>

		</form>

		<?php // Announced to screen readers the moment it is populated. ?>
		<div class="tempus-waitlist__status" role="status" aria-live="polite" data-tempus-status></div>

		<?php if ( $atts['consent'] ) : ?>
			<p class="tempus-waitlist__consent"><?php echo esc_html( $atts['consent'] ); ?></p>
		<?php endif; ?>

	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'tempus_waitlist_form', 'tempus_waitlist_form_shortcode' );
