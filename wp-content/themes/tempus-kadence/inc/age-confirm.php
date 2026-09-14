<?php
/**
 * Tempus Rituals — Checkout Age Confirmation.
 *
 * PID §4 (Compliance), §7 (Customer Features: "Confirm legal age") and
 * §11 (Legal Requirements: "Checkout Confirmation").
 *
 * This is NOT the entry popup. The popup is a doorman who remembers your
 * face for 30 days and has no idea what you bought afterwards. This is the
 * register you sign at the counter: one affirmation, attached to one order,
 * with the time, the IP, and the exact words that were on screen.
 *
 * Because PID §11 records "Delivery Verification: Not Required", this is the
 * only per-order age record the business will hold. It is therefore enforced
 * server-side, not merely in the browser.
 *
 * Loaded from functions.php with:
 *   require_once get_stylesheet_directory() . '/inc/age-confirm.php';
 *
 * @package Tempus
 * @version 1.0
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1. Settings
 * ---------------------------------------------------------------------- */

function tempus_ac_config() {

	return apply_filters(
		'tempus_ac_config',
		array(

			// 21 covers cigars under RA 11467 as well as whisky — the
			// stricter of the two rules, applied once. Same as the entry gate.
			'min_age'           => 21,

			'checkout_label'    => __( 'I confirm that I am 21 years of age or older.', 'tempus' ),
			'checkout_error'    => __( 'Please confirm that you are 21 years of age or older before placing your order.', 'tempus' ),

			'reservation_label' => __( 'I confirm that I am 21 years of age or older.', 'tempus' ),
			'reservation_error' => __( 'Please confirm that you are 21 years of age or older.', 'tempus' ),

			'legal_note'        => __( 'Philippine law sets 21 as the minimum age for tobacco products (RA 11467). We may ask to see ID on delivery or collection.', 'tempus' ),

			// Stored alongside the affirmation as supporting evidence.
			'record_ip'         => true,
		)
	);
}

/** The form field name, used on checkout and on the reservation form. */
function tempus_ac_field() {
	return 'tempus_age_confirm';
}


/* -------------------------------------------------------------------------
 * 2. The checkbox itself
 * ---------------------------------------------------------------------- */

/**
 * Render the confirmation.
 *
 * @param string $context 'checkout' or 'reservation'.
 */
function tempus_ac_render( $context = 'checkout' ) {

	$c     = tempus_ac_config();
	$label = 'checkout' === $context ? $c['checkout_label'] : $c['reservation_label'];
	$id    = 'tempus-ac-' . $context;

	// Preserve the tick if checkout reloads with a validation error.
	$checked = ! empty( $_POST[ tempus_ac_field() ] ); // phpcs:ignore WordPress.Security.NonceVerification
	?>
	<div class="tempus-ac" data-context="<?php echo esc_attr( $context ); ?>">

		<label class="tempus-ac__row" for="<?php echo esc_attr( $id ); ?>">
			<input type="checkbox"
				class="tempus-ac__input"
				name="<?php echo esc_attr( tempus_ac_field() ); ?>"
				id="<?php echo esc_attr( $id ); ?>"
				value="1"
				required
				<?php checked( $checked ); ?>>
			<span class="tempus-ac__label">
				<?php echo esc_html( $label ); ?>
				<abbr class="required" title="<?php esc_attr_e( 'required', 'tempus' ); ?>">*</abbr>
			</span>
		</label>

		<?php if ( ! empty( $c['legal_note'] ) ) : ?>
			<p class="tempus-ac__note"><?php echo esc_html( $c['legal_note'] ); ?></p>
		<?php endif; ?>

		<p class="tempus-ac__error" role="alert"></p>
	</div>
	<?php
}

/**
 * Put it directly above Place Order, beside the Terms & Conditions tick.
 *
 * Deliberately its own checkbox rather than folded into the T&C one:
 * "I agree to the terms" is materially weaker evidence of an age
 * affirmation than a separate, explicit statement about age.
 */
add_action( 'woocommerce_review_order_before_submit', 'tempus_ac_checkout_field', 5 );
function tempus_ac_checkout_field() {
	tempus_ac_render( 'checkout' );
}


/* -------------------------------------------------------------------------
 * 3. Enforcement — on the server, where it cannot be clicked away
 * ---------------------------------------------------------------------- */

add_action( 'woocommerce_checkout_process', 'tempus_ac_validate' );
function tempus_ac_validate() {

	$c = tempus_ac_config();

	if ( empty( $_POST[ tempus_ac_field() ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		wc_add_notice( $c['checkout_error'], 'error' );
	}
}

/**
 * Record it on the order.
 *
 * The wording is stored with the affirmation on purpose. If the copy is
 * reworded next year, an order placed today must still show what its
 * customer actually read and agreed to.
 */
add_action( 'woocommerce_checkout_create_order', 'tempus_ac_save', 10, 2 );
function tempus_ac_save( $order, $data ) {

	if ( empty( $_POST[ tempus_ac_field() ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$c = tempus_ac_config();

	$order->update_meta_data( '_tempus_age_confirmed', 'yes' );
	$order->update_meta_data( '_tempus_age_minimum', (int) $c['min_age'] );
	$order->update_meta_data( '_tempus_age_confirmed_at', current_time( 'mysql' ) );
	$order->update_meta_data( '_tempus_age_confirmed_gmt', current_time( 'mysql', true ) );
	$order->update_meta_data( '_tempus_age_confirmed_text', $c['checkout_label'] );

	if ( ! empty( $c['record_ip'] ) && class_exists( 'WC_Geolocation' ) ) {
		$order->update_meta_data( '_tempus_age_confirmed_ip', WC_Geolocation::get_ip_address() );
	}
}

/** Leave a plain-language trace in the order's own history. */
add_action( 'woocommerce_checkout_order_processed', 'tempus_ac_order_note', 20, 3 );
function tempus_ac_order_note( $order_id, $posted_data, $order = null ) {

	$order = $order ? $order : wc_get_order( $order_id );

	if ( ! $order || 'yes' !== $order->get_meta( '_tempus_age_confirmed' ) ) {
		return;
	}

	$c  = tempus_ac_config();
	$ip = $order->get_meta( '_tempus_age_confirmed_ip' );

	$order->add_order_note(
		sprintf(
			/* translators: 1: minimum age, 2: IP address or "not recorded" */
			__( 'Customer confirmed at checkout that they are %1$d or older. Recorded from IP %2$s.', 'tempus' ),
			(int) $c['min_age'],
			$ip ? $ip : __( 'not recorded', 'tempus' )
		)
	);
}


/* -------------------------------------------------------------------------
 * 4. Reading it back
 * ---------------------------------------------------------------------- */

/** Everything recorded about this order's age affirmation, or null. */
function tempus_ac_get_record( $order ) {

	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order );
	}
	if ( ! $order || 'yes' !== $order->get_meta( '_tempus_age_confirmed' ) ) {
		return null;
	}

	return array(
		'min_age' => (int) $order->get_meta( '_tempus_age_minimum' ),
		'at'      => $order->get_meta( '_tempus_age_confirmed_at' ),
		'gmt'     => $order->get_meta( '_tempus_age_confirmed_gmt' ),
		'text'    => $order->get_meta( '_tempus_age_confirmed_text' ),
		'ip'      => $order->get_meta( '_tempus_age_confirmed_ip' ),
	);
}


/* -------------------------------------------------------------------------
 * 5. Where anyone would look for it
 * ---------------------------------------------------------------------- */

/** On the order edit screen, under the billing address. */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'tempus_ac_admin_display', 20 );
function tempus_ac_admin_display( $order ) {

	$record = tempus_ac_get_record( $order );

	echo '<div class="tempus-ac-admin" style="margin-top:14px;padding-top:12px;border-top:1px solid #eee;">';
	echo '<p style="margin:0 0 4px;"><strong>' . esc_html__( 'Age confirmation', 'tempus' ) . '</strong></p>';

	if ( ! $record ) {
		echo '<p style="margin:0;color:#b32d2e;">' . esc_html__( 'Not recorded on this order.', 'tempus' ) . '</p>';
		echo '<p style="margin:4px 0 0;color:#666;font-size:11px;">'
			. esc_html__( 'Orders placed before this feature was installed, or created manually in the admin, will show this.', 'tempus' ) . '</p>';
		echo '</div>';
		return;
	}

	echo '<p style="margin:0;color:#2e7d32;">';
	printf(
		/* translators: %d: minimum age */
		esc_html__( 'Confirmed %d or older', 'tempus' ),
		(int) $record['min_age']
	);
	echo '</p>';

	echo '<p style="margin:4px 0 0;color:#666;font-size:11px;">';
	echo esc_html( $record['at'] );
	if ( $record['ip'] ) {
		echo '<br>' . esc_html__( 'IP:', 'tempus' ) . ' ' . esc_html( $record['ip'] );
	}
	if ( $record['text'] ) {
		echo '<br><em>&ldquo;' . esc_html( $record['text'] ) . '&rdquo;</em>';
	}
	echo '</p>';

	echo '</div>';
}

/** On the admin order email, and on the order details table for the record. */
add_action( 'woocommerce_email_order_meta', 'tempus_ac_email_display', 20, 3 );
function tempus_ac_email_display( $order, $sent_to_admin, $plain_text = false ) {

	if ( ! $sent_to_admin ) {
		return;
	}

	$record = tempus_ac_get_record( $order );
	if ( ! $record ) {
		return;
	}

	$line = sprintf(
		/* translators: 1: minimum age, 2: timestamp */
		__( 'Age confirmation: customer confirmed %1$d or older at checkout on %2$s.', 'tempus' ),
		(int) $record['min_age'],
		$record['at']
	);

	if ( $plain_text ) {
		echo "\n" . esc_html( $line ) . "\n";
	} else {
		echo '<p style="margin:0 0 16px;color:#555;font-size:13px;">' . esc_html( $line ) . '</p>';
	}
}


/* -------------------------------------------------------------------------
 * 6. The reservation form
 *
 * The reservation plugin has not been chosen yet, so this is offered as a
 * shortcode that drops into any form builder accepting HTML, plus a browser
 * guard that blocks submission until it is ticked.
 *
 * Honest limitation, stated here so it is not discovered later: unlike the
 * checkout, this cannot be enforced server-side until we know which plugin
 * handles reservations. See section 7 of the guide.
 * ---------------------------------------------------------------------- */

add_shortcode( 'tempus_age_confirm', 'tempus_ac_shortcode' );
function tempus_ac_shortcode( $atts ) {

	$atts = shortcode_atts(
		array( 'context' => 'reservation' ),
		$atts,
		'tempus_age_confirm'
	);

	ob_start();
	tempus_ac_render( 'reservation' === $atts['context'] ? 'reservation' : 'checkout' );
	return ob_get_clean();
}


/* -------------------------------------------------------------------------
 * 7. Assets
 * ---------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'tempus_ac_assets', 20 );
function tempus_ac_assets() {

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	// Checkout always; anywhere else only if the shortcode is on the page.
	$needed = function_exists( 'is_checkout' ) && is_checkout();

	if ( ! $needed ) {
		$post = get_post();
		$needed = $post && has_shortcode( (string) $post->post_content, 'tempus_age_confirm' );
	}

	/**
	 * If the reservation form is built with a page builder that stores its
	 * content outside post_content, the check above will miss it. Force the
	 * assets on with:  add_filter( 'tempus_ac_load_assets', '__return_true' );
	 */
	if ( ! apply_filters( 'tempus_ac_load_assets', $needed ) ) {
		return;
	}

	wp_enqueue_style(
		'tempus-age-confirm',
		$uri . '/assets/css/age-confirm.css',
		array(),
		file_exists( $dir . '/assets/css/age-confirm.css' ) ? filemtime( $dir . '/assets/css/age-confirm.css' ) : '1.0.0'
	);

	wp_enqueue_script(
		'tempus-age-confirm',
		$uri . '/assets/js/age-confirm.js',
		array( 'jquery' ),
		file_exists( $dir . '/assets/js/age-confirm.js' ) ? filemtime( $dir . '/assets/js/age-confirm.js' ) : '1.0.0',
		true
	);

	$c = tempus_ac_config();

	wp_localize_script(
		'tempus-age-confirm',
		'tempusAC',
		array(
			'checkoutError'    => $c['checkout_error'],
			'reservationError' => $c['reservation_error'],
		)
	);
}
