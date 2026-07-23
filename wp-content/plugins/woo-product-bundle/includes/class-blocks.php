<?php
defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class for integrating with WooCommerce Blocks
 */
class WPCleverWoosb_Blocks_IntegrationInterface implements IntegrationInterface {
	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'woosb-blocks';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
		wp_enqueue_style(
			'woosb-blocks',
			$this->get_url( 'blocks', 'css' ),
			[],
			WOOSB_VERSION
		);

		wp_register_script(
			'woosb-blocks',
			$this->get_url( 'blocks', 'js' ),
			[ 'wc-blocks-checkout' ],
			WOOSB_VERSION,
			true
		);

		wp_set_script_translations(
			'woosb-blocks',
			'woo-product-bundle',
			WOOSB_DIR . 'languages'
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return [ 'woosb-blocks' ];
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return [];
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return [
			'edit_label' => WPCleverWoosb_Helper()->localization( 'cart_item_edit', esc_html__( 'Edit', 'woo-product-bundle' ) ),
		];
	}

	public function get_url( $file, $ext ) {
		return plugins_url( $this->get_path( $ext ) . $file . '.' . $ext, WOOSB_FILE );
	}

	protected function get_path( $ext ) {
		return 'css' === $ext ? 'assets/css/' : 'assets/js/';
	}
}

if ( ! class_exists( 'WPCleverWoosb_Blocks' ) ) {
	class WPCleverWoosb_Blocks {
		function __construct() {
			add_filter( 'rest_request_after_callbacks', [ $this, 'cart_item_data' ], 10, 3 );
			add_filter( 'woocommerce_hydration_request_after_callbacks', [ $this, 'cart_item_data' ], 10, 3 );
			add_action(
				'woocommerce_blocks_mini-cart_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new WPCleverWoosb_Blocks_IntegrationInterface() );
				}
			);
			add_action(
				'woocommerce_blocks_cart_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new WPCleverWoosb_Blocks_IntegrationInterface() );
				}
			);
			add_action(
				'woocommerce_blocks_checkout_block_registration',
				function ( $integration_registry ) {
					$integration_registry->register( new WPCleverWoosb_Blocks_IntegrationInterface() );
				}
			);
		}

		function cart_item_data( $response, $server, $request ) {
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			if ( ! str_contains( $request->get_route(), 'wc/store' ) ) {
				return $response;
			}

			$data = $response->get_data();

			if ( empty( $data['items'] ) ) {
				return $response;
			}

			if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
				return $response;
			}

			$cart_contents    = WC()->cart->get_cart();
			$hide_bundled     = WPCleverWoosb_Helper()->get_setting( 'hide_bundled', 'no' ) !== 'no';
			$hide_bundle_name = WPCleverWoosb_Helper()->get_setting( 'hide_bundle_name', 'no' ) !== 'no';
			$edit_link        = WPCleverWoosb_Helper()->get_setting( 'edit_link', 'no' ) === 'yes';

			foreach ( $data['items'] as &$item_data ) {
				$cart_item_key = $item_data['key'];
				$cart_item     = $cart_contents[ $cart_item_key ] ?? null;

				if ( ! empty( $cart_item['woosb_ids'] ) ) {
					$item_data['woosb_bundles'] = true;

					// Pass edit URL to Cart Block when edit link setting is enabled
					if ( $edit_link && is_a( $cart_item['data'], 'WC_Product_Woosb' ) && ( $cart_item['data']->has_optional() || $cart_item['data']->has_variables() ) ) {
						$item_data['woosb_edit_url'] = apply_filters( 'woosb_cart_item_edit_url', add_query_arg( [
							'edit' => base64_encode( $cart_item['woosb_ids'] ),
							'key'  => $cart_item_key,
						], $cart_item['data']->get_permalink() ), $cart_item, $cart_item_key );
					}
				}

				if ( ! empty( $cart_item['woosb_parent_id'] ) ) {
					$item_data['woosb_bundled']             = true;
					$item_data['quantity_limits']->editable = false;

					if ( ! $hide_bundle_name ) {
						$item_data['name'] = get_the_title( $cart_item['woosb_parent_id'] ) . apply_filters( 'woosb_name_separator', ' &rarr; ' ) . $item_data['name'];
					}

					if ( $hide_bundled ) {
						$item_data['woosb_hide_bundled'] = true;
					}
				}

				if ( ! empty( $cart_item['woosb_fixed_price'] ) ) {
					$item_data['woosb_fixed_price'] = true;
				}

				if ( ! empty( $cart_item['woosb_price'] ) ) {
					$item_data['woosb_price'] = $cart_item['woosb_price'];
				}
			}

			$response->set_data( $data );

			return $response;
		}
	}

	new WPCleverWoosb_Blocks();
}