<?php
/**
 * Shared helpers.
 *
 * @package Tempus_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'tempus_field' ) ) {
	/**
	 * Safely fetch an ACF field with a fallback, so templates still render
	 * sensibly before content is entered (or if ACF is deactivated).
	 *
	 * @param string    $selector ACF field name.
	 * @param mixed     $fallback Value returned when the field is empty/unavailable.
	 * @param int|false $post_id  Optional post ID (ACF semantics).
	 * @return mixed
	 */
	function tempus_field( $selector, $fallback = '', $post_id = false ) {
		if ( function_exists( 'get_field' ) ) {
			$val = get_field( $selector, $post_id );
			if ( ! empty( $val ) ) {
				return $val;
			}
		}
		return $fallback;
	}
}
