<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverWoosb_Compatible' ) ) {
	class WPCleverWoosb_Compatible {
		protected static $instance = null;
		protected $helper = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		function __construct() {
			$this->helper = WPCleverWoosb_Helper();
			// WPC Add Product to Order
			add_action( 'wpcap_added_to_order', [ $this, 'wpcap_added_to_order' ], 99, 3 );

			// WPC Variations Radio Buttons
			add_filter( 'woovr_default_selector', [ $this, 'woovr_default_selector' ], 99, 4 );

			// WPC Smart Messages
			add_filter( 'wpcsm_locations', [ $this, 'wpcsm_locations' ] );

			// WPML
			if ( function_exists( 'wpml_loaded' ) && apply_filters( 'woosb_wpml_filters', true ) ) {
				add_filter( 'woosb_item_id', [ $this, 'wpml_item_id' ], 99 );
			}

			// PayPal
			add_filter( 'woocommerce_paypal_payments_simulate_cart_enabled', '__return_false' );
			add_filter( 'woocommerce_paypal_payments_simulate_cart_prevent_updates', '__return_false' );

			/*
			 * WooCommerce PDF Invoices & Packing Slips
			 * https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/
			 */

			if ( WPCleverWoosb_Helper()->get_setting( 'compatible_wcpdf_hide_bundles', 'no' ) === 'yes' ) {
				add_filter( 'wpo_wcpdf_order_items_data', [ $this, 'wcpdf_hide_bundles' ], 99 );
			}

			if ( WPCleverWoosb_Helper()->get_setting( 'compatible_wcpdf_hide_bundled', 'no' ) === 'yes' ) {
				add_filter( 'wpo_wcpdf_order_items_data', [ $this, 'wcpdf_hide_bundled' ], 99 );
			}

			/*
			 * WooCommerce PDF Invoices, Packing Slips, Delivery Notes & Shipping Labels
			 * https://en-gb.wordpress.org/plugins/print-invoices-packing-slip-labels-for-woocommerce/
			 */

			add_filter( 'wf_pklist_modify_meta_data', [ $this, 'pklist_hide_meta' ], 99, 1 );

			if ( WPCleverWoosb_Helper()->get_setting( 'compatible_pklist_hide_bundles', 'no' ) === 'yes' ) {
				add_filter( 'wf_pklist_alter_order_items', [ $this, 'pklist_order_hide_bundles' ], 99 );
				add_filter( 'wf_pklist_alter_package_order_items', [ $this, 'pklist_package_hide_bundles' ], 99 );
			}

			if ( WPCleverWoosb_Helper()->get_setting( 'compatible_pklist_hide_bundled', 'no' ) === 'yes' ) {
				add_filter( 'wf_pklist_alter_order_items', [ $this, 'pklist_order_hide_bundled' ], 99 );
				add_filter( 'wf_pklist_alter_package_order_items', [ $this, 'pklist_package_hide_bundled' ], 99 );
			}
		}

		function wpcap_added_to_order( $item_id, $order, $parsed_data ) {
			if ( empty( $parsed_data['woosb_ids'] ) ) {
				return;
			}

			$order_item = $order->get_item( $item_id );
			$quantity   = $order_item->get_quantity();

			if ( 'line_item' === $order_item->get_type() ) {
				$product = $order_item->get_product();

				if ( is_a( $product, 'WC_Product_Woosb' ) ) {
					$product_id = $product->get_id();
					$product->build_items( $parsed_data['woosb_ids'] );
					$items = $product->get_items();

					// get bundle info
					$fixed_price         = $product->is_fixed_price();
					$discount_amount     = $product->get_discount_amount();
					$discount_percentage = $product->get_discount_percentage();

					// add the bundle
					if ( ! $fixed_price ) {
						if ( $discount_amount ) {
							$product->set_price( - (float) $discount_amount );
						} else {
							$this->helper->set_price( $product, 0 );
						}
					}

					if ( $order_id = $order->add_product( $product, $quantity ) ) {
						$order_item = $order->get_item( $order_id );
						$order_item->update_meta_data( '_woosb_ids', $product->get_ids_str(), true );
						$order_item->save();

						foreach ( $items as $item ) {
							$_product = wc_get_product( $item['id'] );

							if ( ! $_product || in_array( $_product->get_type(), $this->helper::get_types(), true ) ) {
								continue;
							}

							if ( $fixed_price ) {
								$this->helper->set_price( $_product, 0 );
							} elseif ( $discount_percentage ) {
								$_price = (float) ( 100 - $discount_percentage ) * $this->helper->get_price( $_product ) / 100;
								$_price = apply_filters( 'woosb_product_price_before_set', $_price, $_product );
								$_product->set_price( $_price );
							}

							// add bundled products
							$_order_item_id = $order->add_product( $_product, $item['qty'] * $quantity );

							if ( ! $_order_item_id ) {
								continue;
							}

							$_order_item = $order->get_item( $_order_item_id );
							$_order_item->update_meta_data( '_woosb_parent_id', $product_id, true );
							$_order_item->save();
						}

						// remove the old bundle
						$order->remove_item( $item_id );
					}
				}

				$order->save();
			}
		}

		function woovr_default_selector( $selector, $product, $variation, $context ) {
			if ( isset( $context ) && ( $context === 'woosb' ) ) {
				if ( ( $selector_interface = $this->helper->get_setting( 'selector_interface', 'unset' ) ) && ( $selector_interface !== 'unset' ) ) {
					$selector = $selector_interface;
				}
			}

			return $selector;
		}

		function wpcsm_locations( $locations ) {
			$locations['WPC Product Bundles'] = [
				'woosb_before_wrap'       => esc_html__( 'Before bundled products', 'woo-product-bundle' ),
				'woosb_after_wrap'        => esc_html__( 'After bundled products', 'woo-product-bundle' ),
				'woosb_before_table'      => esc_html__( 'Before bundled products table', 'woo-product-bundle' ),
				'woosb_after_table'       => esc_html__( 'After bundled products table', 'woo-product-bundle' ),
				'woosb_before_item'       => esc_html__( 'Before bundled product', 'woo-product-bundle' ),
				'woosb_after_item'        => esc_html__( 'After bundled product', 'woo-product-bundle' ),
				'woosb_before_item_name'  => esc_html__( 'Before bundled product name', 'woo-product-bundle' ),
				'woosb_after_item_name'   => esc_html__( 'After bundled product name', 'woo-product-bundle' ),
				'woosb_before_item_price' => esc_html__( 'Before bundled product price', 'woo-product-bundle' ),
				'woosb_after_item_price'  => esc_html__( 'After bundled product price', 'woo-product-bundle' ),
				'woosb_before_bundles'    => esc_html__( 'Before bundles', 'woo-product-bundle' ),
				'woosb_after_bundles'     => esc_html__( 'After bundles', 'woo-product-bundle' ),
			];

			return $locations;
		}

		function wpml_item_id( $id ) {
			return apply_filters( 'wpml_object_id', $id, 'product', true );
		}

		/*
		 * WooCommerce PDF Invoices & Packing Slips
		 * https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/
		 */

		function wcpdf_hide_bundles( $data_list ) {
			foreach ( $data_list as $key => $data ) {
				$bundles = wc_get_order_item_meta( $data['item_id'], '_woosb_ids', true );

				if ( ! empty( $bundles ) ) {
					// hide bundles
					unset( $data_list[ $key ] );
				}
			}

			return $data_list;
		}

		function wcpdf_hide_bundled( $data_list ) {
			foreach ( $data_list as $key => $data ) {
				$bundled = wc_get_order_item_meta( $data['item_id'], '_woosb_parent_id', true );

				if ( ! empty( $bundled ) ) {
					// hide bundled
					unset( $data_list[ $key ] );
				}
			}

			return $data_list;
		}

		/*
		 * WooCommerce PDF Invoices, Packing Slips, Delivery Notes & Shipping Labels
		 * https://en-gb.wordpress.org/plugins/print-invoices-packing-slip-labels-for-woocommerce/
		 */

		// meta data

		function pklist_hide_meta( $meta_data ) {
			if ( array_key_exists( '_woosb_ids', $meta_data ) || array_key_exists( '_woosb_parent_id', $meta_data ) ) {
				$meta_data = [];
			}

			return $meta_data;
		}

		// invoice

		function pklist_order_hide_bundles( $order_items ) {
			foreach ( $order_items as $order_item_id => $order_item ) {
				if ( $order_item->meta_exists( '_woosb_ids' ) ) {
					unset( $order_items[ $order_item_id ] );
				}
			}

			return $order_items;
		}

		function pklist_order_hide_bundled( $order_items ) {
			foreach ( $order_items as $order_item_id => $order_item ) {
				if ( $order_item->meta_exists( '_woosb_parent_id' ) ) {
					unset( $order_items[ $order_item_id ] );
				}
			}

			return $order_items;
		}

		// package

		function pklist_package_hide_bundles( $order_package ) {
			foreach ( $order_package as $order_package_key => $order_package_item ) {
				if ( isset( $order_package_item['extra_meta_details'], $order_package_item['extra_meta_details']['_woosb_ids'] ) ) {
					unset( $order_package[ $order_package_key ] );
				}
			}

			return $order_package;
		}

		function pklist_package_hide_bundled( $order_package ) {
			foreach ( $order_package as $order_package_key => $order_package_item ) {
				if ( isset( $order_package_item['extra_meta_details'], $order_package_item['extra_meta_details']['_woosb_parent_id'] ) ) {
					unset( $order_package[ $order_package_key ] );
				}
			}

			return $order_package;
		}
	}

	return WPCleverWoosb_Compatible::instance();
}