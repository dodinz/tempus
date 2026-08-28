<?php
/**
 * Double opt-in confirmation.
 *
 * Flow:
 *   1. Submission stores the entry with post_status 'pending'.
 *   2. A single-use token is generated and emailed as a confirmation link.
 *   3. Clicking the link flips the entry to 'publish' and clears the token.
 *   4. Unconfirmed entries are purged after 30 days.
 *
 * Core's own 'pending' and 'publish' statuses are used deliberately rather
 * than custom ones — they give free admin filter links, and they are included
 * in post_status => 'any' queries, which custom statuses flagged
 * exclude_from_search are not. Fewer sharp edges later.
 *
 * @package Tempus_Core
 */

defined( 'ABSPATH' ) || exit;

const TEMPUS_CONFIRM_TTL_DAYS  = 7;   // Link lifetime.
const TEMPUS_CONFIRM_PURGE_DAYS = 30; // Unconfirmed entries deleted after this.

/**
 * Issue a fresh confirmation token for an entry.
 *
 * Any previously issued token is overwritten, so a resend silently
 * invalidates the older link.
 *
 * @param int $post_id Entry post ID.
 * @return string The raw token.
 */
function tempus_waitlist_issue_token( $post_id ) {

	$token = wp_generate_password( 40, false, false );

	update_post_meta( $post_id, '_tempus_confirm_token', $token );
	update_post_meta(
		$post_id,
		'_tempus_confirm_expires',
		time() + ( TEMPUS_CONFIRM_TTL_DAYS * DAY_IN_SECONDS )
	);

	return $token;
}

/**
 * Build the confirmation URL for an entry.
 *
 * The post ID is included so the lookup is a direct get_post() rather than a
 * meta query across the whole table.
 *
 * @param int    $post_id Entry post ID.
 * @param string $token   Raw token.
 * @return string
 */
function tempus_waitlist_confirm_url( $post_id, $token ) {

	$base = (string) apply_filters(
		'tempus_waitlist_confirm_page',
		home_url( '/' )
	);

	return add_query_arg( [
		'tempus_confirm' => rawurlencode( $token ),
		'wl'             => (int) $post_id,
	], $base );
}

/**
 * Handle a click on the confirmation link.
 */
add_action( 'template_redirect', function () {

	if ( empty( $_GET['tempus_confirm'] ) || empty( $_GET['wl'] ) ) {
		return;
	}

	$token   = sanitize_text_field( wp_unslash( $_GET['tempus_confirm'] ) );
	$post_id = absint( $_GET['wl'] );
	$entry   = get_post( $post_id );

	$result = 'invalid';

	if ( $entry && TEMPUS_WAITLIST_CPT === $entry->post_type ) {

		if ( 'publish' === $entry->post_status ) {

			// Already confirmed. Clicking an old link twice is not an error.
			$result = 'already';

		} else {

			$stored  = (string) get_post_meta( $post_id, '_tempus_confirm_token', true );
			$expires = (int) get_post_meta( $post_id, '_tempus_confirm_expires', true );

			// hash_equals, not ===, so the comparison is timing-safe.
			if ( $stored && hash_equals( $stored, $token ) ) {

				if ( $expires && time() > $expires ) {
					$result = 'expired';
				} else {
					wp_update_post( [
						'ID'          => $post_id,
						'post_status' => 'publish',
					] );

					// Token is single use — burn it.
					delete_post_meta( $post_id, '_tempus_confirm_token' );
					delete_post_meta( $post_id, '_tempus_confirm_expires' );
					update_post_meta( $post_id, '_tempus_confirmed_at', current_time( 'mysql' ) );

					$data = [
						'name'   => $entry->post_title,
						'email'  => get_post_meta( $post_id, '_tempus_email', true ),
						'mobile' => get_post_meta( $post_id, '_tempus_mobile', true ),
						'tier'   => get_post_meta( $post_id, '_tempus_tier', true ),
					];

					// Admin is notified on CONFIRMATION, not on submission —
					// an unconfirmed entry is not yet a real signup.
					tempus_waitlist_send_admin_notification( $data, $post_id );
					tempus_waitlist_send_welcome( $data );

					do_action( 'tempus_waitlist_confirmed', $post_id, $data );

					$result = 'confirmed';
				}
			}
		}
	}

	// Redirect so the token never stays in the address bar, browser history,
	// or any referer header sent to third-party scripts on the page.
	wp_safe_redirect(
		add_query_arg( 'tempus_waitlist', $result, remove_query_arg( [ 'tempus_confirm', 'wl' ] ) )
	);
	exit;
} );

/**
 * Render the confirmation outcome.
 *
 * Usage: [tempus_waitlist_confirmation]
 *
 * @return string
 */
function tempus_waitlist_confirmation_shortcode() {

	if ( empty( $_GET['tempus_waitlist'] ) ) {
		return '';
	}

	wp_enqueue_style( 'tempus-waitlist' );

	$state = sanitize_key( wp_unslash( $_GET['tempus_waitlist'] ) );

	$messages = [
		'confirmed' => [
			'is-success',
			__( 'Your place is confirmed. We will be in touch before the doors open.', 'tempus' ),
		],
		'already'   => [
			'is-success',
			__( 'You are already on the list. Nothing more to do.', 'tempus' ),
		],
		'expired'   => [
			'is-error',
			__( 'That confirmation link has expired. Please join the waitlist again and we will send a fresh one.', 'tempus' ),
		],
		'invalid'   => [
			'is-error',
			__( 'That confirmation link is not valid. Please join the waitlist again.', 'tempus' ),
		],
	];

	if ( ! isset( $messages[ $state ] ) ) {
		return '';
	}

	return sprintf(
		'<div class="tempus-waitlist"><div class="tempus-waitlist__status %1$s" role="status">%2$s</div></div>',
		esc_attr( $messages[ $state ][0] ),
		esc_html( $messages[ $state ][1] )
	);
}

add_shortcode( 'tempus_waitlist_confirmation', 'tempus_waitlist_confirmation_shortcode' );

/**
 * Schedule the cleanup job.
 */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'tempus_waitlist_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'tempus_waitlist_cleanup' );
	}
} );

/**
 * Delete unconfirmed entries past the purge window.
 *
 * Someone who never confirmed did not consent to being on your list. Holding
 * their details indefinitely is the kind of thing that is both bad hygiene and
 * awkward to explain under the Data Privacy Act.
 */
add_action( 'tempus_waitlist_cleanup', function () {

	$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( TEMPUS_CONFIRM_PURGE_DAYS * DAY_IN_SECONDS ) );

	$stale = get_posts( [
		'post_type'      => TEMPUS_WAITLIST_CPT,
		'post_status'    => 'pending',
		'posts_per_page' => 100,
		'fields'         => 'ids',
		'date_query'     => [ [ 'before' => $cutoff ] ],
	] );

	foreach ( $stale as $post_id ) {
		wp_delete_post( $post_id, true );
	}
} );
