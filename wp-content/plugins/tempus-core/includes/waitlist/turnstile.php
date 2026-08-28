<?php
/**
 * Cloudflare Turnstile verification.
 *
 * Entirely optional. If the two constants below are not defined the whole
 * module no-ops and the form falls back to honeypot + token + timing + rate
 * limit, which is still a working defence.
 *
 * Define these in wp-config.php, NOT here — the secret must never sit in a
 * file that gets committed:
 *
 *   define( 'TEMPUS_TURNSTILE_SITEKEY', '0x4AAA...' );
 *   define( 'TEMPUS_TURNSTILE_SECRET',  '0x4AAA...' );
 *
 * @package Tempus_Core
 */

defined( 'ABSPATH' ) || exit;

const TEMPUS_TURNSTILE_ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

/**
 * Is Turnstile configured?
 *
 * @return bool
 */
function tempus_turnstile_enabled() {
	return defined( 'TEMPUS_TURNSTILE_SITEKEY' )
		&& defined( 'TEMPUS_TURNSTILE_SECRET' )
		&& TEMPUS_TURNSTILE_SITEKEY
		&& TEMPUS_TURNSTILE_SECRET;
}

/**
 * Verify a Turnstile response token with Cloudflare.
 *
 * FAIL-OPEN: if Cloudflare is unreachable or times out, this returns true and
 * the submission proceeds. A Cloudflare outage must never cost you a founding
 * member. To make it fail closed instead, change the `return true` inside the
 * is_wp_error() branch to `return false`.
 *
 * @param string $token The cf-turnstile-response value from the client.
 * @return bool
 */
function tempus_turnstile_verify( $token ) {

	if ( ! tempus_turnstile_enabled() ) {
		return true;
	}

	$token = trim( (string) $token );

	// An absent token is a real failure, not an outage. Fail closed here.
	if ( '' === $token ) {
		return false;
	}

	$response = wp_remote_post( TEMPUS_TURNSTILE_ENDPOINT, [
		'timeout' => 5,
		'body'    => [
			'secret'   => TEMPUS_TURNSTILE_SECRET,
			'response' => $token,
			'remoteip' => tempus_waitlist_client_ip(),
		],
	] );

	if ( is_wp_error( $response ) ) {
		// Network problem on our side — do not punish the visitor.
		return true;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $body ) ) {
		return true;
	}

	return ! empty( $body['success'] );
}
