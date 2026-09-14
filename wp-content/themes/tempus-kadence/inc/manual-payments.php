<?php
/**
 * Tempus Rituals — Manual payment workflow.
 *
 * Bank Transfer / GCash / Maya, with a "Pending Verification" order status
 * and a payment confirmation step that happens AFTER the order is placed —
 * because until the order exists, the customer has no amount to send and no
 * reference to quote.
 *
 * Loaded from functions.php with:
 *   require_once get_stylesheet_directory() . '/inc/manual-payments.php';
 *
 * @package Tempus
 * @version 2.0
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1. Settings
 *
 * Account numbers, GCash numbers and QR codes are NOT here on purpose —
 * they live in WooCommerce → Settings → Payments so the client can edit
 * them without touching code. Only structural choices live in this array.
 * ---------------------------------------------------------------------- */

function tempus_mp_config() {

	return apply_filters(
		'tempus_mp_config',
		array(

			// Slug + label for the custom order status.
			'status_slug'      => 'pending-verify',
			'status_label'     => __( 'Pending Verification', 'tempus' ),

			// The payment window, in hours.
			'window_hours'     => 48,   // What the customer is told.
			'reminder_hours'   => 24,   // Nudge if nothing has arrived by now.
			'expire_hours'     => 72,   // Auto-cancel. 0 disables it entirely.

			// A receipt image, a reference number, or both — either satisfies us.
			'allow_reference'  => true,
			'allow_upload'     => true,

			// Upload limits.
			'max_upload_mb'    => 5,
			'allowed_ext'      => array( 'jpg', 'jpeg', 'png', 'webp', 'pdf' ),

			// Hold inventory the moment the order is placed.
			'reduce_stock'     => true,

			// Folder inside /wp-content/uploads/ where receipts are stored.
			'proof_dirname'    => 'tempus-payment-proofs',
		)
	);
}

/** Full status key, e.g. "wc-pending-verify". */
function tempus_mp_status() {
	$c = tempus_mp_config();
	return 'wc-' . $c['status_slug'];
}

/** Bare status key, e.g. "pending-verify". */
function tempus_mp_status_key() {
	$c = tempus_mp_config();
	return $c['status_slug'];
}

/** The three gateway IDs this file creates. */
function tempus_mp_gateway_ids() {
	return array( 'tempus_bank', 'tempus_gcash', 'tempus_maya' );
}

/** Bail out politely if WooCommerce is not active. */
function tempus_mp_woo_active() {
	return class_exists( 'WooCommerce' );
}


/* -------------------------------------------------------------------------
 * 2. The "Pending Verification" order status
 * ---------------------------------------------------------------------- */

add_action( 'init', 'tempus_mp_register_status' );
function tempus_mp_register_status() {

	$c = tempus_mp_config();

	register_post_status(
		tempus_mp_status(),
		array(
			'label'                     => $c['status_label'],
			'public'                    => false,
			'internal'                  => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: order count */
			'label_count'               => _n_noop(
				'Pending Verification <span class="count">(%s)</span>',
				'Pending Verification <span class="count">(%s)</span>',
				'tempus'
			),
		)
	);
}

/** Put it in the status dropdown, immediately after "Pending payment". */
add_filter( 'wc_order_statuses', 'tempus_mp_add_status_to_dropdown' );
function tempus_mp_add_status_to_dropdown( $statuses ) {

	$c   = tempus_mp_config();
	$out = array();

	foreach ( $statuses as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'wc-pending' === $key ) {
			$out[ tempus_mp_status() ] = $c['status_label'];
		}
	}

	if ( ! isset( $out[ tempus_mp_status() ] ) ) {
		$out[ tempus_mp_status() ] = $c['status_label'];
	}

	return $out;
}

/** HPOS: register the status for the orders table too. */
add_filter( 'woocommerce_register_shop_order_post_statuses', 'tempus_mp_register_hpos_status' );
function tempus_mp_register_hpos_status( $statuses ) {

	$c = tempus_mp_config();

	$statuses[ tempus_mp_status() ] = array(
		'label'                     => $c['status_label'],
		'public'                    => false,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop(
			'Pending Verification <span class="count">(%s)</span>',
			'Pending Verification <span class="count">(%s)</span>',
			'tempus'
		),
	);

	return $statuses;
}

/** The order is still editable while we are waiting for money. */
add_filter( 'wc_order_is_editable', 'tempus_mp_status_is_editable', 10, 2 );
function tempus_mp_status_is_editable( $editable, $order ) {
	return $order->has_status( tempus_mp_status_key() ) ? true : $editable;
}

/** Colour it amber in the admin orders list. */
add_action( 'admin_head', 'tempus_mp_status_admin_colour' );
function tempus_mp_status_admin_colour() {
	?>
	<style>
		.order-status.status-<?php echo esc_attr( tempus_mp_status_key() ); ?>,
		mark.order-status.status-<?php echo esc_attr( tempus_mp_status_key() ); ?> {
			background: #f8dda7;
			color: #94660c;
		}
	</style>
	<?php
}


/* -------------------------------------------------------------------------
 * 3. The payment deadline
 * ---------------------------------------------------------------------- */

/** When this order's payment window closes, as a unix timestamp. */
function tempus_mp_due_at( $order ) {

	$stored = (int) $order->get_meta( '_tempus_mp_due_at' );

	if ( $stored ) {
		return $stored;
	}

	$c       = tempus_mp_config();
	$created = $order->get_date_created();
	$base    = $created ? $created->getTimestamp() : time();

	return $base + ( (int) $c['window_hours'] * HOUR_IN_SECONDS );
}

/** The deadline in words, in the shop's own timezone. */
function tempus_mp_due_text( $order ) {
	return wp_date( 'l j F, g:i a', tempus_mp_due_at( $order ) );
}


/* -------------------------------------------------------------------------
 * 4. Emails
 *
 * WooCommerce only fires emails for its own statuses, so we trigger the two
 * we want by hand, and reword them from "on hold" into something that tells
 * the customer they still owe us money.
 * ---------------------------------------------------------------------- */

add_action( 'woocommerce_order_status_' . tempus_mp_status_key(), 'tempus_mp_trigger_emails', 10, 2 );
function tempus_mp_trigger_emails( $order_id, $order = null ) {

	if ( ! tempus_mp_woo_active() ) {
		return;
	}

	$order = $order ? $order : wc_get_order( $order_id );
	if ( ! $order || ! tempus_mp_is_manual_order( $order ) ) {
		return;
	}

	// Only ever send this pair once per order.
	if ( $order->get_meta( '_tempus_mp_emails_sent' ) ) {
		return;
	}

	$emails = WC()->mailer()->get_emails();

	if ( isset( $emails['WC_Email_New_Order'] ) ) {
		$emails['WC_Email_New_Order']->trigger( $order_id, $order );
	}
	if ( isset( $emails['WC_Email_Customer_On_Hold_Order'] ) ) {
		$emails['WC_Email_Customer_On_Hold_Order']->trigger( $order_id, $order );
	}

	$order->update_meta_data( '_tempus_mp_emails_sent', current_time( 'mysql' ) );
	$order->save();
}

/**
 * Reword the customer email. "On hold" tells them nothing; "complete your
 * payment" tells them there is something left for them to do.
 */
add_filter( 'woocommerce_email_subject_customer_on_hold_order', 'tempus_mp_email_subject', 10, 2 );
function tempus_mp_email_subject( $subject, $order ) {

	if ( $order && $order->has_status( tempus_mp_status_key() ) && tempus_mp_is_manual_order( $order ) ) {
		return sprintf(
			/* translators: 1: order number, 2: site name */
			__( 'Complete your payment for order #%1$s — %2$s', 'tempus' ),
			$order->get_order_number(),
			get_bloginfo( 'name' )
		);
	}

	return $subject;
}

add_filter( 'woocommerce_email_heading_customer_on_hold_order', 'tempus_mp_email_heading', 10, 2 );
function tempus_mp_email_heading( $heading, $order ) {

	if ( $order && $order->has_status( tempus_mp_status_key() ) && tempus_mp_is_manual_order( $order ) ) {
		return __( 'One step left — complete your payment', 'tempus' );
	}

	return $heading;
}

/**
 * The payment instructions inside the customer email: amount, reference,
 * deadline, account details, and the link back to confirm.
 */
add_action( 'woocommerce_email_before_order_table', 'tempus_mp_email_instructions', 10, 4 );
function tempus_mp_email_instructions( $order, $sent_to_admin, $plain_text = false, $email = null ) {

	if ( $sent_to_admin || ! tempus_mp_is_manual_order( $order ) ) {
		return;
	}
	if ( ! $order->has_status( tempus_mp_status_key() ) ) {
		return;
	}

	$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
	$gateway  = isset( $gateways[ $order->get_payment_method() ] ) ? $gateways[ $order->get_payment_method() ] : null;

	$details = $gateway ? $gateway->get_option( 'account_details' ) : '';
	$total   = wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) );
	/* translators: %s: order number */
	$ref     = sprintf( __( 'Order #%s', 'tempus' ), $order->get_order_number() );
	$due     = tempus_mp_due_text( $order );
	$link    = tempus_mp_payment_url( $order );
	$done    = tempus_mp_has_submission( $order );

	if ( $plain_text ) {

		echo "\n" . esc_html__( 'COMPLETE YOUR PAYMENT', 'tempus' ) . "\n\n";
		echo esc_html( sprintf( __( 'Amount to send: %s', 'tempus' ), $total ) ) . "\n";
		echo esc_html( sprintf( __( 'Reference: %s', 'tempus' ), $ref ) ) . "\n";
		echo esc_html( sprintf( __( 'Please pay by: %s', 'tempus' ), $due ) ) . "\n\n";
		echo esc_html( wp_strip_all_tags( $details ) ) . "\n\n";

		if ( ! $done ) {
			echo esc_html__( 'Once you have paid, confirm it here:', 'tempus' ) . "\n";
			echo esc_url_raw( $link ) . "\n\n";
		}
		return;
	}

	echo '<div style="margin:0 0 24px;padding:20px 22px;border:1px solid #d8c9a3;background:#faf7f0;">';

	echo '<p style="margin:0 0 14px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;font-size:12px;color:#8a6d2f;">'
		. esc_html__( 'Complete your payment', 'tempus' ) . '</p>';

	echo '<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 14px;font-size:15px;line-height:1.9;">';
	echo '<tr><td style="padding-right:18px;color:#7a7364;">' . esc_html__( 'Amount to send', 'tempus' ) . '</td>';
	echo '<td><strong style="font-size:19px;">' . esc_html( $total ) . '</strong></td></tr>';
	echo '<tr><td style="padding-right:18px;color:#7a7364;">' . esc_html__( 'Reference', 'tempus' ) . '</td>';
	echo '<td><strong>' . esc_html( $ref ) . '</strong></td></tr>';
	echo '<tr><td style="padding-right:18px;color:#7a7364;">' . esc_html__( 'Please pay by', 'tempus' ) . '</td>';
	echo '<td><strong>' . esc_html( $due ) . '</strong></td></tr>';
	echo '</table>';

	if ( $details ) {
		echo '<div style="padding:14px 16px;background:#fff;border:1px solid #e7ddc6;font-size:14px;line-height:1.9;">';
		echo wp_kses_post( wpautop( $details ) );
		echo '</div>';
	}

	echo '</div>';

	if ( ! $done ) {
		echo '<p style="margin:0 0 8px;">' . esc_html__( 'Already paid? Let us know so we can release your order:', 'tempus' ) . '</p>';
		echo '<p style="margin:0 0 28px;">';
		echo '<a href="' . esc_url( $link ) . '" style="display:inline-block;padding:13px 26px;background:#b8964f;color:#12100c;text-decoration:none;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;font-size:13px;">';
		echo esc_html__( 'Confirm my payment', 'tempus' ) . '</a></p>';
	}
}

/**
 * The reminder, sent once, to anyone who has not told us anything yet.
 */
function tempus_mp_send_reminder( $order ) {

	$total = wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) );
	/* translators: %s: order number */
	$ref   = sprintf( __( 'Order #%s', 'tempus' ), $order->get_order_number() );
	$link  = tempus_mp_payment_url( $order );
	$due   = tempus_mp_due_text( $order );

	$subject = sprintf(
		/* translators: 1: order number */
		__( 'Still holding your order #%s', 'tempus' ),
		$order->get_order_number()
	);

	$heading = __( 'Your order is still waiting', 'tempus' );

	$message  = '<p>' . esc_html( sprintf( __( 'Hello %s,', 'tempus' ), $order->get_billing_first_name() ) ) . '</p>';
	$message .= '<p>' . esc_html__( 'We are still holding the items in your order. We have not seen your payment yet — if you have already sent it, let us know and we will release the order straight away.', 'tempus' ) . '</p>';

	$message .= '<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;font-size:15px;line-height:1.9;">';
	$message .= '<tr><td style="padding-right:18px;color:#7a7364;">' . esc_html__( 'Amount to send', 'tempus' ) . '</td><td><strong style="font-size:19px;">' . esc_html( $total ) . '</strong></td></tr>';
	$message .= '<tr><td style="padding-right:18px;color:#7a7364;">' . esc_html__( 'Reference', 'tempus' ) . '</td><td><strong>' . esc_html( $ref ) . '</strong></td></tr>';
	$message .= '<tr><td style="padding-right:18px;color:#7a7364;">' . esc_html__( 'Held until', 'tempus' ) . '</td><td><strong>' . esc_html( $due ) . '</strong></td></tr>';
	$message .= '</table>';

	$message .= '<p><a href="' . esc_url( $link ) . '" style="display:inline-block;padding:13px 26px;background:#b8964f;color:#12100c;text-decoration:none;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;font-size:13px;">'
		. esc_html__( 'Pay or confirm payment', 'tempus' ) . '</a></p>';

	$message .= '<p style="color:#7a7364;font-size:13px;">' . esc_html__( 'If you no longer want the order, you can simply ignore this message and it will be released automatically.', 'tempus' ) . '</p>';

	$mailer = WC()->mailer();
	$mailer->send( $order->get_billing_email(), $subject, $mailer->wrap_message( $heading, $message ) );

	$order->update_meta_data( '_tempus_mp_reminder_sent', current_time( 'mysql' ) );
	$order->add_order_note( __( 'Payment reminder sent to the customer.', 'tempus' ) );
	$order->save();
}


/* -------------------------------------------------------------------------
 * 5. Where receipts are stored
 * ---------------------------------------------------------------------- */

/** Create /wp-content/uploads/tempus-payment-proofs/ and lock it down. */
function tempus_mp_ensure_dir() {

	$c      = tempus_mp_config();
	$upload = wp_upload_dir();
	$dir    = trailingslashit( $upload['basedir'] ) . $c['proof_dirname'];

	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		$rules  = "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n";
		$rules .= "<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n";
		file_put_contents( $dir . '/.htaccess', $rules ); // phpcs:ignore
	}

	if ( ! file_exists( $dir . '/index.html' ) ) {
		file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore
	}

	return $dir;
}

/** Point wp_handle_upload() at our folder instead of the year/month one. */
function tempus_mp_upload_dir( $dirs ) {

	$c = tempus_mp_config();

	$dirs['subdir'] = '/' . $c['proof_dirname'];
	$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
	$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];

	return $dirs;
}

/** Unguessable filename — the second lock on the door. */
function tempus_mp_unique_filename( $dir, $name, $ext ) {
	return 'proof-' . gmdate( 'Ymd' ) . '-' . wp_generate_password( 24, false, false ) . $ext;
}

/** Only the extensions we said we allow. */
function tempus_mp_allowed_mimes() {

	$c     = tempus_mp_config();
	$all   = wp_get_mime_types();
	$mimes = array();

	foreach ( $all as $exts => $mime ) {
		foreach ( explode( '|', $exts ) as $ext ) {
			if ( in_array( $ext, $c['allowed_ext'], true ) ) {
				$mimes[ $exts ] = $mime;
				break;
			}
		}
	}

	return $mimes;
}

/**
 * Take one $_FILES entry, validate it, move it into the protected folder.
 *
 * @return array|WP_Error array( path, name, type ) relative to the uploads dir.
 */
function tempus_mp_handle_upload( $file ) {

	$c = tempus_mp_config();

	if ( empty( $file ) || ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'tempus_mp_nofile', __( 'No file was received. Please try again.', 'tempus' ) );
	}

	if ( ! empty( $file['error'] ) ) {
		return new WP_Error( 'tempus_mp_phperror', __( 'The upload failed. The file may be too large for the server.', 'tempus' ) );
	}

	$max = (int) $c['max_upload_mb'] * 1024 * 1024;
	if ( $file['size'] > $max ) {
		return new WP_Error(
			'tempus_mp_toobig',
			sprintf(
				/* translators: %d: megabytes */
				__( 'That file is larger than %dMB. Please send a smaller screenshot.', 'tempus' ),
				(int) $c['max_upload_mb']
			)
		);
	}

	tempus_mp_ensure_dir();

	add_filter( 'upload_dir', 'tempus_mp_upload_dir' );

	$moved = wp_handle_upload(
		$file,
		array(
			'test_form'                => false,
			'mimes'                    => tempus_mp_allowed_mimes(),
			'unique_filename_callback' => 'tempus_mp_unique_filename',
		)
	);

	remove_filter( 'upload_dir', 'tempus_mp_upload_dir' );

	if ( isset( $moved['error'] ) ) {
		return new WP_Error( 'tempus_mp_rejected', __( 'That file type is not accepted. Please send a JPG, PNG, WEBP or PDF.', 'tempus' ) );
	}

	$upload = wp_upload_dir();

	return array(
		// Stored relative to the uploads folder so the site can be moved.
		'path' => ltrim( str_replace( $upload['basedir'], '', $moved['file'] ), '/' ),
		'name' => sanitize_file_name( $file['name'] ),
		'type' => $moved['type'],
	);
}

/** Delete a stored receipt (used when a customer replaces one). */
function tempus_mp_delete_file( $relative_path ) {

	if ( ! $relative_path ) {
		return;
	}

	$upload = wp_upload_dir();
	$real   = realpath( trailingslashit( $upload['basedir'] ) . ltrim( $relative_path, '/' ) );
	$base   = realpath( $upload['basedir'] );

	// Never step outside the uploads folder.
	if ( ! $real || ! $base || 0 !== strpos( $real, $base ) ) {
		return;
	}

	wp_delete_file( $real );
}


/* -------------------------------------------------------------------------
 * 6. Reading what the customer has told us
 * ---------------------------------------------------------------------- */

/** Read the receipt stored on an order. */
function tempus_mp_get_proof( $order ) {

	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order );
	}
	if ( ! $order ) {
		return null;
	}

	$path = $order->get_meta( '_tempus_proof_path' );
	if ( ! $path ) {
		return null;
	}

	return array(
		'path'     => $path,
		'name'     => $order->get_meta( '_tempus_proof_name' ),
		'type'     => $order->get_meta( '_tempus_proof_type' ),
		'uploaded' => $order->get_meta( '_tempus_proof_uploaded' ),
	);
}

/** Read the reference number the customer typed, if any. */
function tempus_mp_get_reference( $order ) {

	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order );
	}

	return $order ? $order->get_meta( '_tempus_payment_reference' ) : '';
}

/** Has the customer given us anything at all — a receipt or a reference? */
function tempus_mp_has_submission( $order ) {
	return (bool) ( tempus_mp_get_proof( $order ) || tempus_mp_get_reference( $order ) );
}

/** Was this order paid by one of our manual methods? */
function tempus_mp_is_manual_order( $order ) {

	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order );
	}

	return $order && in_array( $order->get_payment_method(), tempus_mp_gateway_ids(), true );
}

/** The customer-facing URL where they confirm payment. Guest-safe. */
function tempus_mp_payment_url( $order ) {
	return $order->get_checkout_order_received_url();
}

/** Write receipt details onto an order (HPOS-safe). */
function tempus_mp_attach_proof( $order, $proof ) {

	$order->update_meta_data( '_tempus_proof_path', $proof['path'] );
	$order->update_meta_data( '_tempus_proof_name', $proof['name'] );
	$order->update_meta_data( '_tempus_proof_type', $proof['type'] );
	$order->update_meta_data( '_tempus_proof_uploaded', current_time( 'mysql' ) );
}


/* -------------------------------------------------------------------------
 * 7. The confirmation form
 *
 * One form, one place: the order confirmation page. The customer is a guest,
 * so the order key already in that page's URL is what authorises them —
 * no account, no password, and it still works days later from the email.
 * ---------------------------------------------------------------------- */

function tempus_mp_render_payment_form( $order ) {

	if ( ! $order ) {
		return;
	}

	$c      = tempus_mp_config();
	$accept = '.' . implode( ',.', $c['allowed_ext'] );
	$id     = 'tempus-pay-' . $order->get_id();
	?>
	<form class="tempus-confirm"
		data-order="<?php echo esc_attr( $order->get_id() ); ?>"
		data-key="<?php echo esc_attr( $order->get_order_key() ); ?>">

		<p class="tempus-confirm__title"><?php esc_html_e( 'Already paid? Confirm it here', 'tempus' ); ?></p>
		<p class="tempus-confirm__hint">
			<?php esc_html_e( 'Send us either one — whichever is easier on your phone.', 'tempus' ); ?>
		</p>

		<?php if ( ! empty( $c['allow_reference'] ) ) : ?>
			<div class="tempus-confirm__field">
				<label for="<?php echo esc_attr( $id ); ?>-ref">
					<?php esc_html_e( 'Reference number', 'tempus' ); ?>
				</label>
				<input type="text"
					id="<?php echo esc_attr( $id ); ?>-ref"
					class="tempus-confirm__ref"
					inputmode="numeric"
					autocomplete="off"
					maxlength="64"
					placeholder="<?php esc_attr_e( 'e.g. 1029384756123', 'tempus' ); ?>">
				<span class="tempus-confirm__sub">
					<?php esc_html_e( 'The number on your GCash, Maya or bank confirmation screen.', 'tempus' ); ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $c['allow_reference'] ) && ! empty( $c['allow_upload'] ) ) : ?>
			<p class="tempus-confirm__or"><span><?php esc_html_e( 'or', 'tempus' ); ?></span></p>
		<?php endif; ?>

		<?php if ( ! empty( $c['allow_upload'] ) ) : ?>
			<div class="tempus-confirm__field">
				<label class="tempus-confirm__file" for="<?php echo esc_attr( $id ); ?>-file">
					<span class="tempus-confirm__file-text"><?php esc_html_e( 'Attach a screenshot', 'tempus' ); ?></span>
					<input type="file"
						id="<?php echo esc_attr( $id ); ?>-file"
						class="tempus-confirm__input"
						accept="<?php echo esc_attr( $accept ); ?>">
				</label>
				<span class="tempus-confirm__filename"></span>
				<span class="tempus-confirm__sub">
					<?php
					printf(
						/* translators: 1: file types, 2: max size in MB */
						esc_html__( '%1$s, up to %2$dMB.', 'tempus' ),
						esc_html( strtoupper( implode( ', ', $c['allowed_ext'] ) ) ),
						(int) $c['max_upload_mb']
					);
					?>
				</span>
			</div>
		<?php endif; ?>

		<button type="submit" class="tempus-confirm__submit">
			<?php esc_html_e( "I've sent the payment", 'tempus' ); ?>
		</button>

		<p class="tempus-confirm__status" role="status" aria-live="polite"></p>
	</form>
	<?php
}


/* -------------------------------------------------------------------------
 * 8. The order confirmation page
 *
 * WooCommerce's default here says "Thank you, your order has been received",
 * which reads as finished. It is not finished — we have not been paid. So
 * this panel leads with the one step that is left.
 * ---------------------------------------------------------------------- */

/** Replace the default "thank you" line while a payment is outstanding. */
add_filter( 'woocommerce_thankyou_order_received_text', 'tempus_mp_received_text', 10, 2 );
function tempus_mp_received_text( $text, $order ) {

	if ( ! $order || ! tempus_mp_is_manual_order( $order ) ) {
		return $text;
	}
	if ( ! $order->has_status( tempus_mp_status_key() ) ) {
		return $text;
	}

	if ( tempus_mp_has_submission( $order ) ) {
		return esc_html__( 'Thank you — we have your payment details and are verifying them now.', 'tempus' );
	}

	return esc_html__( 'Your order is reserved. One step left.', 'tempus' );
}

add_action( 'woocommerce_thankyou', 'tempus_mp_payment_panel', 5 );
function tempus_mp_payment_panel( $order_id ) {

	$order = wc_get_order( $order_id );

	if ( ! $order || ! tempus_mp_is_manual_order( $order ) ) {
		return;
	}
	if ( ! $order->has_status( tempus_mp_status_key() ) ) {
		return;
	}

	$gateways  = WC()->payment_gateways()->payment_gateways();
	$gateway   = isset( $gateways[ $order->get_payment_method() ] ) ? $gateways[ $order->get_payment_method() ] : null;
	$submitted = tempus_mp_has_submission( $order );
	$total     = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );
	/* translators: %s: order number */
	$ref       = sprintf( __( 'Order #%s', 'tempus' ), $order->get_order_number() );

	echo '<section class="tempus-pay-panel' . ( $submitted ? ' is-received' : ' is-awaiting' ) . '">';

	if ( $submitted ) {

		echo '<p class="tempus-pay-panel__eyebrow">' . esc_html( $ref ) . '</p>';
		echo '<h2 class="tempus-pay-panel__title">' . esc_html__( 'Payment details received', 'tempus' ) . '</h2>';
		echo '<p class="tempus-pay-panel__lede">' . esc_html__( 'We are checking it against our account now. You will get an email the moment it is confirmed — usually within one business day.', 'tempus' ) . '</p>';

		$reference = tempus_mp_get_reference( $order );
		$proof     = tempus_mp_get_proof( $order );

		echo '<ul class="tempus-pay-panel__receipt">';
		if ( $reference ) {
			echo '<li><span>' . esc_html__( 'Reference given', 'tempus' ) . '</span><strong>' . esc_html( $reference ) . '</strong></li>';
		}
		if ( $proof ) {
			echo '<li><span>' . esc_html__( 'Screenshot', 'tempus' ) . '</span><strong>' . esc_html( $proof['name'] ) . '</strong></li>';
		}
		echo '</ul>';

		echo '</section>';
		return;
	}

	// --- Still waiting for payment ---

	echo '<p class="tempus-pay-panel__eyebrow">' . esc_html( $ref ) . ' · ' . esc_html__( 'reserved for you', 'tempus' ) . '</p>';

	echo '<h2 class="tempus-pay-panel__title">';
	printf(
		/* translators: %s: formatted order total */
		esc_html__( 'One step left — send %s', 'tempus' ),
		wp_kses_post( $total )
	);
	echo '</h2>';

	echo '<p class="tempus-pay-panel__deadline">';
	printf(
		/* translators: %s: date and time */
		esc_html__( 'Please pay by %s. We are holding your order until then.', 'tempus' ),
		'<strong>' . esc_html( tempus_mp_due_text( $order ) ) . '</strong>'
	);
	echo '</p>';

	// The three steps, stated plainly.
	echo '<ol class="tempus-pay-steps">';
	echo '<li>' . sprintf(
		/* translators: %s: formatted order total */
		esc_html__( 'Send %s to the account below.', 'tempus' ),
		wp_kses_post( $total )
	) . '</li>';
	echo '<li>' . sprintf(
		/* translators: %s: order reference */
		esc_html__( 'Put %s in the notes or reference field.', 'tempus' ),
		'<strong>' . esc_html( $ref ) . '</strong>'
	) . '</li>';
	echo '<li>' . esc_html__( 'Come back to this page and confirm below.', 'tempus' ) . '</li>';
	echo '</ol>';

	if ( $gateway ) {
		$details = $gateway->get_option( 'account_details' );
		$qr      = $gateway->get_option( 'qr_image' );

		if ( $details ) {
			echo '<div class="tempus-pay-details">' . wp_kses_post( wpautop( $details ) ) . '</div>';
		}
		if ( $qr ) {
			echo '<div class="tempus-pay-qr">';
			echo '<img src="' . esc_url( $qr ) . '" alt="' . esc_attr__( 'Payment QR code', 'tempus' ) . '">';
			echo '<span>' . esc_html__( 'Scan with your app', 'tempus' ) . '</span>';
			echo '</div>';
		}
	}

	// The part that matters if they are about to leave for their banking app.
	echo '<p class="tempus-pay-panel__saveit">';
	esc_html_e( 'Leaving to pay? We have emailed you this page — you can come back to it any time from that email.', 'tempus' );
	echo '</p>';

	tempus_mp_render_payment_form( $order );

	echo '</section>';
}


/* -------------------------------------------------------------------------
 * 9. Receiving the confirmation
 * ---------------------------------------------------------------------- */

add_action( 'wc_ajax_tempus_mp_confirm', 'tempus_mp_ajax_confirm' );
add_action( 'wc_ajax_nopriv_tempus_mp_confirm', 'tempus_mp_ajax_confirm' );

function tempus_mp_ajax_confirm() {

	check_ajax_referer( 'tempus_mp_confirm', 'nonce' );

	$c        = tempus_mp_config();
	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	$key      = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';
	$order    = $order_id ? wc_get_order( $order_id ) : null;

	// The order key in the page URL is what stands in for a login.
	if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) ) {
		wp_send_json_error(
			array( 'message' => __( 'We could not match that order. Please use the link in your confirmation email.', 'tempus' ) )
		);
	}

	if ( ! $order->has_status( tempus_mp_status_key() ) ) {
		wp_send_json_error(
			array( 'message' => __( 'This order is no longer awaiting payment. If you think that is wrong, please contact us.', 'tempus' ) )
		);
	}

	$reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
	$reference = trim( preg_replace( '/\s+/', ' ', $reference ) );
	$has_file  = ! empty( $c['allow_upload'] )
		&& isset( $_FILES['tempus_proof'] )
		&& ! empty( $_FILES['tempus_proof']['name'] ); // phpcs:ignore

	if ( '' === $reference && ! $has_file ) {
		wp_send_json_error(
			array( 'message' => __( 'Please enter your reference number or attach a screenshot.', 'tempus' ) )
		);
	}

	$parts = array();

	if ( '' !== $reference ) {
		$order->update_meta_data( '_tempus_payment_reference', $reference );
		$parts[] = sprintf(
			/* translators: %s: reference number */
			__( 'reference %s', 'tempus' ),
			$reference
		);
	}

	if ( $has_file ) {

		$result = tempus_mp_handle_upload( $_FILES['tempus_proof'] ); // phpcs:ignore

		if ( is_wp_error( $result ) ) {
			// A bad file should not throw away a perfectly good reference number.
			if ( '' === $reference ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
		} else {
			$existing = tempus_mp_get_proof( $order );
			if ( $existing ) {
				tempus_mp_delete_file( $existing['path'] );
			}
			tempus_mp_attach_proof( $order, $result );
			$parts[] = sprintf(
				/* translators: %s: file name */
				__( 'screenshot %s', 'tempus' ),
				$result['name']
			);
		}
	}

	$order->add_order_note(
		sprintf(
			/* translators: %s: what the customer sent, e.g. "reference 12345" */
			__( 'Customer confirmed payment — %s.', 'tempus' ),
			implode( ', ', $parts )
		)
	);
	$order->save();

	tempus_mp_notify_admin_of_confirmation( $order );

	wp_send_json_success(
		array(
			'message' => __( 'Thank you — we are verifying your payment now.', 'tempus' ),
			'reload'  => true,
		)
	);
}

/** Tell the shop a confirmation has landed. */
function tempus_mp_notify_admin_of_confirmation( $order ) {

	$to = apply_filters( 'tempus_mp_admin_email', get_option( 'admin_email' ) );

	$subject = sprintf(
		/* translators: 1: site name, 2: order number */
		__( '[%1$s] Payment confirmed by customer — order #%2$s', 'tempus' ),
		get_bloginfo( 'name' ),
		$order->get_order_number()
	);

	$reference = tempus_mp_get_reference( $order );
	$proof     = tempus_mp_get_proof( $order );

	$body  = sprintf(
		/* translators: 1: order number, 2: order total */
		__( "Order #%1\$s — the customer says they have paid %2\$s.", 'tempus' ),
		$order->get_order_number(),
		wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) )
	) . "\n\n";

	if ( $reference ) {
		$body .= sprintf( __( 'Reference given: %s', 'tempus' ), $reference ) . "\n";
	}
	if ( $proof ) {
		$body .= sprintf( __( 'Screenshot attached: %s', 'tempus' ), $proof['name'] ) . "\n";
	}

	$body .= "\n" . __( 'Check it against your account, then verify here:', 'tempus' ) . "\n";
	$body .= $order->get_edit_order_url();

	wp_mail( $to, $subject, $body );
}


/* -------------------------------------------------------------------------
 * 10. Admin — see what they sent, verify the payment
 * ---------------------------------------------------------------------- */

/** Serve a receipt only to someone who can edit orders. */
add_action( 'admin_post_tempus_mp_proof', 'tempus_mp_serve_proof' );
function tempus_mp_serve_proof() {

	$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		wp_die( esc_html__( 'You are not allowed to view this file.', 'tempus' ), 403 );
	}
	check_admin_referer( 'tempus_mp_proof_' . $order_id );

	$order = wc_get_order( $order_id );
	$proof = $order ? tempus_mp_get_proof( $order ) : null;

	if ( ! $proof ) {
		wp_die( esc_html__( 'No receipt on this order.', 'tempus' ), 404 );
	}

	$upload = wp_upload_dir();
	$full   = realpath( trailingslashit( $upload['basedir'] ) . $proof['path'] );

	if ( ! $full || 0 !== strpos( $full, realpath( $upload['basedir'] ) ) || ! file_exists( $full ) ) {
		wp_die( esc_html__( 'That file is missing.', 'tempus' ), 404 );
	}

	nocache_headers();
	header( 'Content-Type: ' . $proof['type'] );
	header( 'Content-Length: ' . filesize( $full ) );
	header( 'Content-Disposition: inline; filename="' . basename( $proof['name'] ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $full ); // phpcs:ignore
	exit;
}

/** Link to a receipt, signed for the current admin. */
function tempus_mp_proof_url( $order_id ) {
	return wp_nonce_url(
		admin_url( 'admin-post.php?action=tempus_mp_proof&order_id=' . absint( $order_id ) ),
		'tempus_mp_proof_' . absint( $order_id )
	);
}

add_action( 'add_meta_boxes', 'tempus_mp_add_metabox', 40 );
function tempus_mp_add_metabox() {

	if ( ! function_exists( 'wc_get_page_screen_id' ) ) {
		return;
	}

	add_meta_box(
		'tempus_mp_verify',
		__( 'Payment Verification', 'tempus' ),
		'tempus_mp_metabox',
		wc_get_page_screen_id( 'shop-order' ),
		'side',
		'high'
	);
}

function tempus_mp_metabox( $post_or_order ) {

	$order = ( $post_or_order instanceof WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;

	if ( ! $order || ! tempus_mp_is_manual_order( $order ) ) {
		echo '<p>' . esc_html__( 'This order did not use a manual payment method.', 'tempus' ) . '</p>';
		return;
	}

	$proof     = tempus_mp_get_proof( $order );
	$reference = tempus_mp_get_reference( $order );
	$label     = $order->get_meta( '_tempus_payment_method_label' );

	echo '<p><strong>' . esc_html__( 'Method:', 'tempus' ) . '</strong> '
		. esc_html( $label ? $label : $order->get_payment_method_title() ) . '</p>';

	echo '<p><strong>' . esc_html__( 'Amount:', 'tempus' ) . '</strong> '
		. wp_kses_post( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) . '</p>';

	if ( $reference ) {
		echo '<p><strong>' . esc_html__( 'Reference given:', 'tempus' ) . '</strong><br>';
		echo '<code style="font-size:14px;user-select:all;">' . esc_html( $reference ) . '</code></p>';
	}

	if ( $proof ) {
		$url = tempus_mp_proof_url( $order->get_id() );

		if ( 0 === strpos( (string) $proof['type'], 'image/' ) ) {
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">';
			echo '<img src="' . esc_url( $url ) . '" alt="" style="max-width:100%;height:auto;border:1px solid #ddd;margin-bottom:8px;">';
			echo '</a>';
		}

		echo '<p><a class="button" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Open receipt', 'tempus' ) . '</a></p>';
	}

	if ( ! $proof && ! $reference ) {
		echo '<p style="color:#b32d2e;"><strong>' . esc_html__( 'Customer has not confirmed payment yet.', 'tempus' ) . '</strong></p>';

		if ( $order->has_status( tempus_mp_status_key() ) ) {
			echo '<p style="color:#666;font-size:11px;">';
			printf(
				/* translators: %s: date and time */
				esc_html__( 'Held until %s.', 'tempus' ),
				esc_html( tempus_mp_due_text( $order ) )
			);
			if ( $order->get_meta( '_tempus_mp_reminder_sent' ) ) {
				echo '<br>' . esc_html__( 'Reminder already sent.', 'tempus' );
			}
			echo '</p>';
		}
	}

	if ( $order->has_status( tempus_mp_status_key() ) ) {
		$verify_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=tempus_mp_verify&order_id=' . $order->get_id() ),
			'tempus_mp_verify_' . $order->get_id()
		);
		echo '<p><a class="button button-primary" href="' . esc_url( $verify_url ) . '">'
			. esc_html__( 'Mark payment verified', 'tempus' ) . '</a></p>';
		echo '<p style="color:#666;font-size:11px;">'
			. esc_html__( 'Moves the order to Processing and sends the customer their confirmation.', 'tempus' ) . '</p>';
	}
}

/** One-click verify. */
add_action( 'admin_post_tempus_mp_verify', 'tempus_mp_do_verify' );
function tempus_mp_do_verify() {

	$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'tempus' ), 403 );
	}
	check_admin_referer( 'tempus_mp_verify_' . $order_id );

	$order = wc_get_order( $order_id );

	if ( $order ) {
		$user = wp_get_current_user();
		$order->update_meta_data( '_tempus_verified_by', $user->display_name );
		$order->update_meta_data( '_tempus_verified_at', current_time( 'mysql' ) );
		$order->save();
		$order->payment_complete();
		$order->add_order_note(
			sprintf(
				/* translators: %s: admin display name */
				__( 'Payment verified manually by %s.', 'tempus' ),
				$user->display_name
			),
			false,
			true
		);
	}

	wp_safe_redirect( $order ? $order->get_edit_order_url() : admin_url() );
	exit;
}

/** "Payment" column in the orders list — HPOS and legacy. */
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'tempus_mp_order_column' );
add_filter( 'manage_edit-shop_order_columns', 'tempus_mp_order_column' );
function tempus_mp_order_column( $columns ) {

	$out = array();

	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'order_status' === $key ) {
			$out['tempus_proof'] = __( 'Payment', 'tempus' );
		}
	}

	return $out;
}

add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'tempus_mp_order_column_value', 10, 2 );
function tempus_mp_order_column_value( $column, $order ) {

	if ( 'tempus_proof' !== $column ) {
		return;
	}

	if ( ! tempus_mp_is_manual_order( $order ) ) {
		echo '<span style="color:#ccc;">&mdash;</span>';
		return;
	}

	$proof     = tempus_mp_get_proof( $order );
	$reference = tempus_mp_get_reference( $order );

	if ( $proof ) {
		echo '<a href="' . esc_url( tempus_mp_proof_url( $order->get_id() ) ) . '" target="_blank" rel="noopener" title="'
			. esc_attr__( 'Open receipt', 'tempus' ) . '" style="color:#2e7d32;font-size:16px;text-decoration:none;">&#10003;</a>';
		if ( $reference ) {
			echo ' <span style="color:#666;font-size:11px;">' . esc_html( $reference ) . '</span>';
		}
	} elseif ( $reference ) {
		echo '<span style="color:#2e7d32;font-size:16px;" title="' . esc_attr__( 'Reference number given', 'tempus' ) . '">&#10003;</span>';
		echo ' <span style="color:#666;font-size:11px;">' . esc_html( $reference ) . '</span>';
	} else {
		echo '<span style="color:#b32d2e;font-size:16px;" title="' . esc_attr__( 'Nothing received yet', 'tempus' ) . '">&#8226;</span>';
	}
}

add_action( 'manage_shop_order_posts_custom_column', 'tempus_mp_order_column_value_legacy', 10, 2 );
function tempus_mp_order_column_value_legacy( $column, $post_id ) {
	$order = wc_get_order( $post_id );
	if ( $order ) {
		tempus_mp_order_column_value( $column, $order );
	}
}


/* -------------------------------------------------------------------------
 * 11. The hourly sweep — remind, then release
 * ---------------------------------------------------------------------- */

add_action( 'init', 'tempus_mp_schedule_sweep' );
function tempus_mp_schedule_sweep() {

	// Clear the old daily job from version 1 if it is still hanging around.
	$legacy = wp_next_scheduled( 'tempus_mp_expire_orders' );
	if ( $legacy ) {
		wp_unschedule_event( $legacy, 'tempus_mp_expire_orders' );
	}

	if ( ! wp_next_scheduled( 'tempus_mp_sweep' ) ) {
		wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'hourly', 'tempus_mp_sweep' );
	}
}

add_action( 'tempus_mp_sweep', 'tempus_mp_run_sweep' );
function tempus_mp_run_sweep() {

	$c = tempus_mp_config();

	if ( ! tempus_mp_woo_active() ) {
		return;
	}

	$orders = wc_get_orders(
		array(
			'status'         => tempus_mp_status_key(),
			'limit'          => 100,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'payment_method' => tempus_mp_gateway_ids(),
		)
	);

	foreach ( $orders as $order ) {

		// Anyone who has told us something is a human's problem, never a robot's.
		if ( tempus_mp_has_submission( $order ) ) {
			continue;
		}

		$created = $order->get_date_created();
		if ( ! $created ) {
			continue;
		}

		$age_hours = ( time() - $created->getTimestamp() ) / HOUR_IN_SECONDS;

		// Release the stock.
		if ( ! empty( $c['expire_hours'] ) && $age_hours >= (int) $c['expire_hours'] ) {
			$order->update_status(
				'cancelled',
				sprintf(
					/* translators: %d: hours */
					__( 'Automatically released — no payment confirmed within %d hours.', 'tempus' ),
					(int) $c['expire_hours']
				)
			);
			continue;
		}

		// Or nudge them, once.
		if ( ! empty( $c['reminder_hours'] )
			&& $age_hours >= (int) $c['reminder_hours']
			&& ! $order->get_meta( '_tempus_mp_reminder_sent' ) ) {
			tempus_mp_send_reminder( $order );
		}
	}
}


/* -------------------------------------------------------------------------
 * 12. Load the gateways, the stylesheet and the script
 * ---------------------------------------------------------------------- */

/**
 * Load the gateway classes. Not hooked to plugins_loaded: this file lives in
 * the theme, and functions.php runs AFTER plugins_loaded has already fired,
 * so that hook would never reach us. Called from the gateway filter instead,
 * which only runs once WooCommerce is fully loaded.
 */
function tempus_mp_load_gateways() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return false;
	}

	require_once get_stylesheet_directory() . '/inc/class-tempus-manual-gateway.php';

	return true;
}

add_filter( 'woocommerce_payment_gateways', 'tempus_mp_register_gateways' );
function tempus_mp_register_gateways( $gateways ) {

	if ( ! tempus_mp_load_gateways() ) {
		return $gateways;
	}

	$gateways[] = 'Tempus_Gateway_Bank';
	$gateways[] = 'Tempus_Gateway_GCash';
	$gateways[] = 'Tempus_Gateway_Maya';

	return $gateways;
}

add_action( 'wp_enqueue_scripts', 'tempus_mp_assets' );
function tempus_mp_assets() {

	if ( ! tempus_mp_woo_active() ) {
		return;
	}
	if ( ! is_checkout() && ! is_order_received_page() ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	wp_enqueue_style(
		'tempus-manual-payments',
		$uri . '/assets/css/manual-payments.css',
		array(),
		file_exists( $dir . '/assets/css/manual-payments.css' ) ? filemtime( $dir . '/assets/css/manual-payments.css' ) : '2.0.0'
	);

	// The script is only needed where the confirmation form lives.
	if ( ! is_order_received_page() ) {
		return;
	}

	wp_enqueue_script(
		'tempus-manual-payments',
		$uri . '/assets/js/manual-payments.js',
		array( 'jquery' ),
		file_exists( $dir . '/assets/js/manual-payments.js' ) ? filemtime( $dir . '/assets/js/manual-payments.js' ) : '2.0.0',
		true
	);

	$c = tempus_mp_config();

	wp_localize_script(
		'tempus-manual-payments',
		'tempusMP',
		array(
			'ajaxUrl'  => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'nonce'    => wp_create_nonce( 'tempus_mp_confirm' ),
			'maxBytes' => (int) $c['max_upload_mb'] * 1024 * 1024,
			'i18n'     => array(
				'sending'  => __( 'Sending…', 'tempus' ),
				'tooBig'   => sprintf(
					/* translators: %d: megabytes */
					__( 'That file is larger than %dMB. Please send a smaller screenshot.', 'tempus' ),
					(int) $c['max_upload_mb']
				),
				'empty'    => __( 'Please enter your reference number or attach a screenshot.', 'tempus' ),
				'failed'   => __( 'Something went wrong. Please try again.', 'tempus' ),
				'attach'   => __( 'Attach a screenshot', 'tempus' ),
				'submit'   => __( "I've sent the payment", 'tempus' ),
			),
		)
	);
}
