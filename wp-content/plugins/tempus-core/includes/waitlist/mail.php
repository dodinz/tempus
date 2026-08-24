<?php
/**
 * Waitlist email.
 *
 * Both functions swallow failure deliberately. The CPT row is written before
 * either is called, so a dead SMTP relay costs you a notification, never a
 * signup.
 *
 * @package Tempus_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notify the site owner of a new signup.
 *
 * @param array $data    Sanitised submission data.
 * @param int   $post_id Entry post ID.
 * @return void
 */
function tempus_waitlist_send_admin_notification( array $data, $post_id ) {

	$to = (string) apply_filters(
		'tempus_waitlist_admin_email',
		get_option( 'admin_email' )
	);

	if ( ! is_email( $to ) ) {
		return;
	}

	$subject = sprintf(
		/* translators: %s: subscriber name */
		__( 'New founding member — %s', 'tempus' ),
		$data['name']
	);

	$body = implode( "\n", [
		__( 'A new founding member has joined the waitlist.', 'tempus' ),
		'',
		sprintf( '%s: %s', __( 'Name', 'tempus' ), $data['name'] ),
		sprintf( '%s: %s', __( 'Email', 'tempus' ), $data['email'] ),
		sprintf( '%s: %s', __( 'Mobile', 'tempus' ), $data['mobile'] ),
		sprintf( '%s: %s', __( 'Tier interest', 'tempus' ), $data['tier'] ?: __( 'Not specified', 'tempus' ) ),
		'',
		sprintf( '%s: %s', __( 'View entry', 'tempus' ), get_edit_post_link( $post_id, 'raw' ) ),
	] );

	// Reply-To lets you answer the subscriber straight from your inbox.
	$headers = [ 'Reply-To: ' . $data['name'] . ' <' . $data['email'] . '>' ];

	wp_mail( $to, $subject, $body, $headers );
}

/**
 * Send the subscriber a confirmation.
 *
 * @param array $data Sanitised submission data.
 * @return void
 */
function tempus_waitlist_send_welcome( array $data ) {

	$subject = (string) apply_filters(
		'tempus_waitlist_welcome_subject',
		__( 'Your place at TEMPUS is reserved', 'tempus' )
	);

	$greeting = $data['name']
		? sprintf( /* translators: %s: subscriber first name */ __( 'Dear %s,', 'tempus' ), explode( ' ', $data['name'] )[0] )
		: __( 'Hello,', 'tempus' );

	$tier_line = $data['tier']
		? sprintf(
			/* translators: %s: membership tier name */
			__( 'We have noted your interest in the %s tier.', 'tempus' ),
			$data['tier']
		)
		: '';

	$body = implode( "\n\n", array_filter( [
		$greeting,
		__( 'Thank you for joining the TEMPUS founding members waitlist. Your place is held.', 'tempus' ),
		$tier_line,
		__( 'Founding membership is limited. Those who join before opening day lock in priority access to rare allocations, private tasting events, and rates that will never be offered again.', 'tempus' ),
		__( 'We will be in touch before the doors open.', 'tempus' ),
		'—',
		get_bloginfo( 'name' ),
	] ) );

	$body = (string) apply_filters( 'tempus_waitlist_welcome_body', $body, $data );

	wp_mail( $data['email'], $subject, $body );
}
