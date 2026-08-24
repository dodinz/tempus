<?php
/**
 * Waitlist storage: custom post type, admin list table, CSV export.
 *
 * @package Tempus_Core
 */

defined( 'ABSPATH' ) || exit;

const TEMPUS_WAITLIST_CPT = 'tempus_waitlist';

/**
 * Register the waitlist post type.
 *
 * Deliberately not public and not in REST: these are personal details of
 * people who have not consented to being listed anywhere on the front end.
 */
add_action( 'init', function () {

	register_post_type( TEMPUS_WAITLIST_CPT, [
		'labels' => [
			'name'               => __( 'Waitlist', 'tempus' ),
			'singular_name'      => __( 'Waitlist Entry', 'tempus' ),
			'menu_name'          => __( 'Waitlist', 'tempus' ),
			'search_items'       => __( 'Search Waitlist', 'tempus' ),
			'not_found'          => __( 'No signups yet.', 'tempus' ),
			'not_found_in_trash' => __( 'No signups in trash.', 'tempus' ),
		],
		'public'              => false,
		'publicly_queryable'  => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => false,
		'has_archive'         => false,
		'rewrite'             => false,
		'query_var'           => false,
		'exclude_from_search' => true,
		'menu_icon'           => 'dashicons-list-view',
		'menu_position'       => 26,
		'supports'            => [ 'title' ],
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		// Entries arrive via the REST endpoint only. Nobody hand-creates one.
		'capabilities'        => [ 'create_posts' => 'do_not_allow' ],
	] );
} );

/**
 * Write a submission to the CPT.
 *
 * De-duplicates on email so repeat submissions update rather than stack.
 *
 * @param array $data Sanitised values: name, email, mobile, tier, source.
 * @return int|WP_Error Post ID on success.
 */
function tempus_waitlist_store( array $data ) {

	$existing = get_posts( [
		'post_type'        => TEMPUS_WAITLIST_CPT,
		'post_status'      => 'any',
		'posts_per_page'   => 1,
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => false,
		'meta_query'       => [
			[
				'key'   => '_tempus_email',
				'value' => $data['email'],
			],
		],
	] );

	if ( $existing ) {
		$post_id = (int) $existing[0];
		wp_update_post( [
			'ID'         => $post_id,
			'post_title' => $data['name'] ?: $data['email'],
		] );
	} else {
		$post_id = wp_insert_post( [
			'post_type'   => TEMPUS_WAITLIST_CPT,
			'post_status' => 'publish',
			'post_title'  => $data['name'] ?: $data['email'],
		], true );
	}

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return is_wp_error( $post_id ) ? $post_id : new WP_Error( 'insert_failed', 'Could not save entry.' );
	}

	update_post_meta( $post_id, '_tempus_email', $data['email'] );
	update_post_meta( $post_id, '_tempus_mobile', $data['mobile'] );
	update_post_meta( $post_id, '_tempus_tier', $data['tier'] );
	update_post_meta( $post_id, '_tempus_source', $data['source'] );
	update_post_meta( $post_id, '_tempus_captured_at', current_time( 'mysql' ) );

	/**
	 * Fires after a waitlist entry is stored.
	 *
	 * @param int   $post_id Entry post ID.
	 * @param array $data    Sanitised submission data.
	 */
	do_action( 'tempus_waitlist_stored', $post_id, $data );

	return $post_id;
}

/* -------------------------------------------------------------------------
 * Admin list table
 * ---------------------------------------------------------------------- */

add_filter( 'manage_' . TEMPUS_WAITLIST_CPT . '_posts_columns', function ( $columns ) {
	return [
		'cb'            => $columns['cb'] ?? '',
		'title'         => __( 'Name', 'tempus' ),
		'tempus_email'  => __( 'Email', 'tempus' ),
		'tempus_mobile' => __( 'Mobile', 'tempus' ),
		'tempus_tier'   => __( 'Tier Interest', 'tempus' ),
		'date'          => __( 'Joined', 'tempus' ),
	];
} );

add_action( 'manage_' . TEMPUS_WAITLIST_CPT . '_posts_custom_column', function ( $column, $post_id ) {

	switch ( $column ) {
		case 'tempus_email':
			$email = get_post_meta( $post_id, '_tempus_email', true );
			if ( $email ) {
				printf(
					'<a href="%s">%s</a>',
					esc_url( 'mailto:' . $email ),
					esc_html( $email )
				);
			}
			break;

		case 'tempus_mobile':
			echo esc_html( get_post_meta( $post_id, '_tempus_mobile', true ) );
			break;

		case 'tempus_tier':
			$tier = get_post_meta( $post_id, '_tempus_tier', true );
			echo $tier ? esc_html( $tier ) : '<span style="color:#999">&mdash;</span>';
			break;
	}
}, 10, 2 );

add_filter( 'manage_edit-' . TEMPUS_WAITLIST_CPT . '_sortable_columns', function ( $columns ) {
	$columns['tempus_tier'] = 'tempus_tier';
	return $columns;
} );

add_action( 'pre_get_posts', function ( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( $query->get( 'post_type' ) !== TEMPUS_WAITLIST_CPT ) {
		return;
	}
	if ( $query->get( 'orderby' ) === 'tempus_tier' ) {
		$query->set( 'meta_key', '_tempus_tier' );
		$query->set( 'orderby', 'meta_value' );
	}
} );

/* -------------------------------------------------------------------------
 * CSV export
 * ---------------------------------------------------------------------- */

/**
 * Render the export button above the list table.
 */
add_action( 'manage_posts_extra_tablenav', function ( $which ) {

	global $typenow;

	if ( 'top' !== $which || TEMPUS_WAITLIST_CPT !== $typenow ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=tempus_waitlist_export' ),
		'tempus_waitlist_export',
		'tempus_nonce'
	);

	printf(
		'<div class="alignleft actions"><a href="%s" class="button button-primary">%s</a></div>',
		esc_url( $url ),
		esc_html__( 'Export CSV', 'tempus' )
	);
} );

/**
 * Stream the waitlist as a CSV download.
 */
add_action( 'admin_post_tempus_waitlist_export', function () {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to export the waitlist.', 'tempus' ), 403 );
	}

	check_admin_referer( 'tempus_waitlist_export', 'tempus_nonce' );

	$entries = get_posts( [
		'post_type'      => TEMPUS_WAITLIST_CPT,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'ASC',
	] );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=tempus-waitlist-' . gmdate( 'Y-m-d' ) . '.csv' );

	$out = fopen( 'php://output', 'w' );

	// UTF-8 BOM so Excel doesn't mangle accented names.
	fwrite( $out, "\xEF\xBB\xBF" );

	fputcsv( $out, [ 'Name', 'Email', 'Mobile', 'Tier Interest', 'Joined' ] );

	foreach ( $entries as $entry ) {
		fputcsv( $out, array_map( 'tempus_csv_cell', [
			$entry->post_title,
			get_post_meta( $entry->ID, '_tempus_email', true ),
			get_post_meta( $entry->ID, '_tempus_mobile', true ),
			get_post_meta( $entry->ID, '_tempus_tier', true ),
			get_post_meta( $entry->ID, '_tempus_captured_at', true ) ?: $entry->post_date,
		] ) );
	}

	fclose( $out );
	exit;
} );

/**
 * Neutralise CSV formula injection.
 *
 * A cell beginning = + - @ or a control character is executed as a formula by
 * Excel and Sheets. Prefixing a single quote forces it to be read as text.
 *
 * @param mixed $value Raw cell value.
 * @return string
 */
function tempus_csv_cell( $value ) {
	$value = (string) $value;
	if ( '' !== $value && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
		return "'" . $value;
	}
	return $value;
}
