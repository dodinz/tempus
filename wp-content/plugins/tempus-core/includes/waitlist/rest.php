<?php
/**
 * Waitlist REST endpoint.
 *
 * POST /wp-json/tempus/v1/waitlist
 *
 * @package Tempus_Core
 */

defined( 'ABSPATH' ) || exit;

const TEMPUS_WAITLIST_MIN_SECONDS = 2;    // Faster than this is a bot.
const TEMPUS_WAITLIST_MAX_PER_HOUR = 5;   // Per IP.

/**
 * Register the route.
 *
 * permission_callback is __return_true because this is a public form. The
 * actual gate is the signed token + honeypot + timing + rate limit below.
 * Returning true here without those checks would be an open write endpoint.
 */
add_action( 'rest_api_init', function () {

	register_rest_route( 'tempus/v1', '/waitlist', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'tempus_waitlist_handle_submission',
		'permission_callback' => '__return_true',
		'args'                => [
			'name'   => [ 'required' => true,  'type' => 'string' ],
			'email'  => [ 'required' => true,  'type' => 'string' ],
			'mobile' => [ 'required' => true,  'type' => 'string' ],
			'tier'   => [ 'required' => false, 'type' => 'string' ],
			'token'  => [ 'required' => true,  'type' => 'string' ],
			'ts'     => [ 'required' => true,  'type' => 'string' ],
			// Honeypot. Named to look tempting to a bot, must arrive empty.
			'website' => [ 'required' => false, 'type' => 'string' ],
		],
	] );
} );

/**
 * Issue an HMAC-signed render token.
 *
 * WHY NOT A NONCE: WordPress nonces are tied to a 12/24h tick and to the
 * current user. On a page-cached site the nonce baked into the cached HTML
 * goes stale and every submission fails with a confusing 403. This token is
 * stateless, survives caching indefinitely, and still proves the payload came
 * from markup we rendered — because a bot POSTing blind cannot forge the HMAC.
 *
 * @param string $ts Timestamp the form was rendered.
 * @return string
 */
function tempus_waitlist_token( $ts ) {
	return hash_hmac( 'sha256', $ts, wp_salt( 'tempus_waitlist' ) );
}

/**
 * Verify a submitted token against its timestamp.
 *
 * @param string $ts    Timestamp echoed back by the client.
 * @param string $token Token echoed back by the client.
 * @return bool
 */
function tempus_waitlist_verify_token( $ts, $token ) {
	return hash_equals( tempus_waitlist_token( $ts ), (string) $token );
}

/**
 * Best-effort client IP.
 *
 * Behind Cloudflare or a load balancer REMOTE_ADDR is the proxy, which would
 * rate-limit every visitor as if they were one person. Check the forwarded
 * headers first, but only ones the host actually sets.
 *
 * @return string
 */
function tempus_waitlist_client_ip() {

	$candidates = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];

	foreach ( $candidates as $key ) {
		if ( empty( $_SERVER[ $key ] ) ) {
			continue;
		}
		$ip = trim( explode( ',', wp_unslash( $_SERVER[ $key ] ) )[0] );
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}
	}

	return '0.0.0.0';
}

/**
 * Rolling per-IP rate limit backed by a transient.
 *
 * @return bool True if the caller has exceeded the limit.
 */
function tempus_waitlist_rate_limited() {

	$key   = 'tempus_wl_' . md5( tempus_waitlist_client_ip() );
	$count = (int) get_transient( $key );

	if ( $count >= TEMPUS_WAITLIST_MAX_PER_HOUR ) {
		return true;
	}

	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return false;
}

/**
 * Handle a waitlist submission.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function tempus_waitlist_handle_submission( WP_REST_Request $request ) {

	/*
	 * Silent rejections.
	 *
	 * Bots get a 200 and a plausible success message. Telling them exactly
	 * which check they failed is free tuning information — they'd iterate
	 * until they passed. A human will never hit these branches.
	 */
	$silent = new WP_REST_Response( [
		'success' => true,
		'message' => __( "You're on the list.", 'tempus' ),
	], 200 );

	// 1. Honeypot — a real browser never fills a field it cannot see.
	if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
		return $silent;
	}

	// 2. Signed token.
	$ts    = (string) $request->get_param( 'ts' );
	$token = (string) $request->get_param( 'token' );

	if ( ! ctype_digit( $ts ) || ! tempus_waitlist_verify_token( $ts, $token ) ) {
		return $silent;
	}

	// 3. Timing trap. Note there is no maximum age: a cached page can sit in
	//    a CDN for hours and the timestamp will legitimately be old.
	if ( ( time() - (int) $ts ) < TEMPUS_WAITLIST_MIN_SECONDS ) {
		return $silent;
	}

	// 4. Rate limit.
	if ( tempus_waitlist_rate_limited() ) {
		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Too many attempts. Please try again later.', 'tempus' ),
		], 429 );
	}

	/*
	 * Validation. These DO report back — a human mistyping their email needs
	 * to know which field to fix.
	 */
	$name   = sanitize_text_field( wp_unslash( (string) $request->get_param( 'name' ) ) );
	$email  = sanitize_email( wp_unslash( (string) $request->get_param( 'email' ) ) );
	$mobile = sanitize_text_field( wp_unslash( (string) $request->get_param( 'mobile' ) ) );
	$tier   = sanitize_text_field( wp_unslash( (string) $request->get_param( 'tier' ) ) );

	$errors = [];

	if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 80 ) {
		$errors['name'] = __( 'Please enter your full name.', 'tempus' );
	}

	if ( ! is_email( $email ) ) {
		$errors['email'] = __( 'Please enter a valid email address.', 'tempus' );
	}

	// Loose on purpose: 0917 123 4567, +63 917 123 4567 and (0917) 123-4567
	// are all things real people type.
	if ( ! preg_match( '/^[\d\s\+\-\(\)]{7,20}$/', $mobile ) ) {
		$errors['mobile'] = __( 'Please enter a valid mobile number.', 'tempus' );
	}

	// Link injection is the entire point of form spam. Treat silently.
	if ( preg_match( '#https?://|www\.|\[url#i', $name . ' ' . $mobile ) ) {
		return $silent;
	}

	if ( $errors ) {
		return new WP_REST_Response( [
			'success' => false,
			'errors'  => $errors,
			'message' => __( 'Please check the highlighted fields.', 'tempus' ),
		], 400 );
	}

	// Only tiers we actually offer.
	$allowed_tiers = (array) apply_filters(
		'tempus_waitlist_allowed_tiers',
		[ 'Connoisseur', 'Reserve', 'Founder' ]
	);

	if ( $tier && ! in_array( $tier, $allowed_tiers, true ) ) {
		$tier = '';
	}

	$data = [
		'name'   => $name,
		'email'  => $email,
		'mobile' => $mobile,
		'tier'   => $tier,
		'source' => esc_url_raw( (string) $request->get_header( 'referer' ) ),
	];

	$post_id = tempus_waitlist_store( $data );

	if ( is_wp_error( $post_id ) ) {
		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Something went wrong. Please try again.', 'tempus' ),
		], 500 );
	}

	// Mail failures must never cost us the signup — the row is already saved.
	tempus_waitlist_send_admin_notification( $data, $post_id );
	tempus_waitlist_send_welcome( $data );

	return new WP_REST_Response( [
		'success' => true,
		'message' => __( "You're on the list. We'll be in touch before the doors open.", 'tempus' ),
	], 200 );
}
