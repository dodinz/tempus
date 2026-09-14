<?php
/**
 * Tempus Rituals — the manual payment gateway.
 *
 * One base class, three thin subclasses. Each subclass becomes its own
 * entry in WooCommerce → Settings → Payments, with its own account
 * details, its own QR code and its own on/off switch.
 *
 * @package Tempus
 */

defined( 'ABSPATH' ) || exit;

abstract class Tempus_Manual_Gateway extends WC_Payment_Gateway {

	/**
	 * Filled in by each subclass.
	 *
	 * @return array
	 */
	abstract protected function tempus_defaults();

	public function __construct() {

		$d = $this->tempus_defaults();

		$this->id                 = $d['id'];
		$this->method_title       = $d['method_title'];
		$this->method_description = $d['method_description'];
		$this->has_fields         = true;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', $d['title'] );
		$this->description = $this->get_option( 'description', $d['description'] );
		$this->icon        = $this->get_option( 'icon_url' ) ? esc_url( $this->get_option( 'icon_url' ) ) : '';

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/* ------------------------------------------------------------------
	 * Admin settings — this is where the client edits account numbers.
	 * --------------------------------------------------------------- */

	public function init_form_fields() {

		$d = $this->tempus_defaults();

		$this->form_fields = array(

			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'tempus' ),
				'type'    => 'checkbox',
				'label'   => sprintf(
					/* translators: %s: method name */
					__( 'Enable %s', 'tempus' ),
					$d['method_title']
				),
				'default' => 'no',
			),

			'title' => array(
				'title'       => __( 'Title', 'tempus' ),
				'type'        => 'text',
				'description' => __( 'What the customer sees as the name of this payment option at checkout.', 'tempus' ),
				'default'     => $d['title'],
				'desc_tip'    => true,
			),

			'description' => array(
				'title'       => __( 'Short description', 'tempus' ),
				'type'        => 'textarea',
				'description' => __( 'One line shown under the option when the customer selects it. Do not mention uploading here — that happens on the next screen.', 'tempus' ),
				'default'     => $d['description'],
				'desc_tip'    => true,
			),

			'account_details' => array(
				'title'       => __( 'Payment details', 'tempus' ),
				'type'        => 'textarea',
				'css'         => 'width:100%;height:160px;',
				'description' => __( 'The account name, number and any instructions. Shown at checkout, on the order confirmation page, and in the payment email. Basic HTML is allowed.', 'tempus' ),
				'default'     => $d['account_details'],
			),

			'qr_image' => array(
				'title'       => __( 'QR code image URL', 'tempus' ),
				'type'        => 'text',
				'description' => __( 'Optional. Upload the QR code to Media Library, copy its URL, paste it here.', 'tempus' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			'icon_url' => array(
				'title'       => __( 'Logo URL', 'tempus' ),
				'type'        => 'text',
				'description' => __( 'Optional small logo shown beside the option name at checkout.', 'tempus' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);
	}

	/* ------------------------------------------------------------------
	 * Checkout
	 * --------------------------------------------------------------- */

	/**
	 * What the customer sees at checkout.
	 *
	 * Deliberately NO upload field here. At this moment the customer has not
	 * paid — they cannot even know the final amount until this page has
	 * calculated it — so asking for a receipt asks for something that cannot
	 * exist yet. The account details are shown because they reassure; the
	 * confirming happens on the next screen, once there is an amount and a
	 * reference to quote.
	 */
	public function payment_fields() {

		echo '<div class="tempus-pay-fields">';

		if ( $this->description ) {
			echo '<div class="tempus-pay-blurb">' . wp_kses_post( wpautop( wptexturize( $this->description ) ) ) . '</div>';
		}

		$details = $this->get_option( 'account_details' );
		if ( $details ) {
			echo '<div class="tempus-pay-details">' . wp_kses_post( wpautop( $details ) ) . '</div>';
		}

		$qr = $this->get_option( 'qr_image' );
		if ( $qr ) {
			echo '<div class="tempus-pay-qr"><img src="' . esc_url( $qr ) . '" alt="' . esc_attr__( 'Payment QR code', 'tempus' ) . '"></div>';
		}

		echo '<p class="tempus-pay-next">';
		esc_html_e( 'You will get the exact amount, your reference number and a link to confirm your payment on the next screen — and by email.', 'tempus' );
		echo '</p>';

		echo '</div>';
	}

	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );
		$c     = tempus_mp_config();

		$order->update_meta_data( '_tempus_payment_method_label', $this->get_title() );

		// Start the clock the customer is told about.
		$order->update_meta_data(
			'_tempus_mp_due_at',
			time() + ( (int) $c['window_hours'] * HOUR_IN_SECONDS )
		);

		$order->save();

		$order->update_status(
			tempus_mp_status_key(),
			__( 'Awaiting manual verification of payment.', 'tempus' )
		);

		if ( ! empty( $c['reduce_stock'] ) ) {
			wc_reduce_stock_levels( $order_id );
		}

		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}


/* ---------------------------------------------------------------------
 * The three live methods.
 * ------------------------------------------------------------------ */

class Tempus_Gateway_Bank extends Tempus_Manual_Gateway {

	protected function tempus_defaults() {
		return array(
			'id'                 => 'tempus_bank',
			'method_title'       => __( 'Tempus — Bank Transfer', 'tempus' ),
			'method_description' => __( 'Customer transfers to your bank account and uploads the receipt. You verify it manually.', 'tempus' ),
			'title'              => __( 'Bank Transfer', 'tempus' ),
			'description'        => __( 'Transfer the total to the account below. We will confirm your order once the payment lands.', 'tempus' ),
			'account_details'    => "<strong>Bank:</strong> BPI\n<strong>Account name:</strong> Tempus Rituals Inc.\n<strong>Account number:</strong> 0000 0000 0000\n<strong>Branch:</strong> —",
		);
	}
}

class Tempus_Gateway_GCash extends Tempus_Manual_Gateway {

	protected function tempus_defaults() {
		return array(
			'id'                 => 'tempus_gcash',
			'method_title'       => __( 'Tempus — GCash', 'tempus' ),
			'method_description' => __( 'Customer sends via GCash and uploads the receipt. You verify it manually.', 'tempus' ),
			'title'              => __( 'GCash', 'tempus' ),
			'description'        => __( 'Send the total via GCash. We will confirm your order once the payment lands.', 'tempus' ),
			'account_details'    => "<strong>GCash name:</strong> Tempus Rituals\n<strong>GCash number:</strong> 09XX XXX XXXX",
		);
	}
}

class Tempus_Gateway_Maya extends Tempus_Manual_Gateway {

	protected function tempus_defaults() {
		return array(
			'id'                 => 'tempus_maya',
			'method_title'       => __( 'Tempus — Maya', 'tempus' ),
			'method_description' => __( 'Customer sends via Maya and uploads the receipt. You verify it manually.', 'tempus' ),
			'title'              => __( 'Maya', 'tempus' ),
			'description'        => __( 'Send the total via Maya. We will confirm your order once the payment lands.', 'tempus' ),
			'account_details'    => "<strong>Maya name:</strong> Tempus Rituals\n<strong>Maya number:</strong> 09XX XXX XXXX",
		);
	}
}
