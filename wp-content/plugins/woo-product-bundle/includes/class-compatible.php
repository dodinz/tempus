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

			// Multi-currency discount conversion
			add_filter( 'woosb_get_discount_amount', [ $this, 'convert_discount_amount' ], 10, 2 );
			add_filter( 'woosb_cart_item_discount_amount', [ $this, 'convert_discount_amount' ], 10, 2 );

			// WPML
			if ( function_exists( 'wpml_loaded' ) && apply_filters( 'woosb_wpml_filters', true ) ) {
				add_filter( 'woosb_item_id', [ $this, 'wpml_item_id' ], 99 );
				add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'wpml_sync_cart_contents' ], 5 );
				add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'wpml_sync_cart_contents' ], 25 );
				add_action( 'woocommerce_before_calculate_totals', [ $this, 'wpml_sync_cart_contents_totals' ], 9998 );
				add_action( 'wcml_switch_currency', [ $this, 'wpml_on_switch_currency' ] );
				add_filter( 'woosb_remove_orphaned_bundled_product', [ $this, 'wpml_prevent_orphan_removal' ], 10, 3 );
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

		/**
		 * Translate product or variation ID for WPML.
		 *
		 * @param int $id Product or variation ID.
		 * @return int
		 */
		function wpml_item_id( $id ) {
			if ( ! empty( $id ) ) {
				$post_type = get_post_type( $id ) ?: 'product';

				return (int) apply_filters( 'wpml_object_id', (int) $id, $post_type, true );
			}

			return $id;
		}

		/**
		 * Convert discount amount using active multi-currency plugin exchange rate.
		 * Supports WPML/WCML, WOOCS/FOX, CURCY, and Aelia Currency Switcher.
		 *
		 * @param float $discount_amount Discount amount.
		 * @param array|WC_Product_Woosb|null $product_or_cart_item Context.
		 * @return float
		 */
		function convert_discount_amount( $discount_amount, $product_or_cart_item = null ) {
			$base_amount = 0.0;

			if ( is_array( $product_or_cart_item ) && isset( $product_or_cart_item['woosb_base_discount_amount'] ) ) {
				$base_amount = (float) $product_or_cart_item['woosb_base_discount_amount'];
			} elseif ( is_a( $product_or_cart_item, 'WC_Product' ) ) {
				$base_amount = (float) $product_or_cart_item->get_meta( 'woosb_discount_amount' );
			}

			if ( $base_amount <= 0 ) {
				$base_amount = (float) $discount_amount;
			}

			if ( $base_amount <= 0 ) {
				return 0.0;
			}

			// 1. WPML / WCML
			if ( function_exists( 'wpml_loaded' ) ) {
				$converted = (float) apply_filters( 'wcml_raw_price_amount', $base_amount );
				if ( $converted > 0 && $converted !== $base_amount ) {
					return $converted;
				}
				$converted = (float) apply_filters( 'wcml_raw_price_filter', $base_amount );
				if ( $converted > 0 && $converted !== $base_amount ) {
					return $converted;
				}
			}

			// 2. WOOCS (FOX - Currency Switcher Professional for WooCommerce)
			global $WOOCS;
			if ( isset( $WOOCS ) && method_exists( $WOOCS, 'woocs_exchange_value' ) ) {
				return (float) $WOOCS->woocs_exchange_value( $base_amount );
			}

			// 3. CURCY (WooCommerce Multi Currency)
			if ( function_exists( 'wmc_get_price' ) ) {
				return (float) wmc_get_price( $base_amount );
			}

			// 4. Aelia Currency Switcher
			if ( has_filter( 'wc_aelia_cs_convert' ) ) {
				return (float) apply_filters( 'wc_aelia_cs_convert', $base_amount, get_option( 'woocommerce_currency' ), get_woocommerce_currency() );
			}

			return (float) $discount_amount;
		}

		function wpml_convert_discount_amount( $discount_amount, $product_or_cart_item = null ) {
			return $this->convert_discount_amount( $discount_amount, $product_or_cart_item );
		}

		/**
		 * Clear cached bundle prices and recalculate totals when currency changes.
		 *
		 * @param string $currency Active currency.
		 */
		function wpml_on_switch_currency( $currency = '' ) {
			if ( did_action( 'woocommerce_load_cart_from_session' ) && ! empty( WC()->cart ) && ! WC()->cart->is_empty() ) {
				foreach ( WC()->cart->cart_contents as $key => $item ) {
					if ( isset( $item['woosb_price'] ) ) {
						unset( WC()->cart->cart_contents[ $key ]['woosb_price'] );
					}
				}

				WC()->cart->calculate_totals();
			}
		}

		/**
		 * Prevent accidental removal of child items if parent exists in translated cart.
		 *
		 * @param bool $remove Whether to remove orphaned item.
		 * @param string $cart_item_key Cart item key.
		 * @param array $cart_item Cart item data.
		 * @return bool
		 */
		function wpml_prevent_orphan_removal( $remove, $cart_item_key, $cart_item ) {
			if ( function_exists( 'wpml_loaded' ) && ! empty( WC()->cart ) && ! empty( WC()->cart->cart_contents ) ) {
				$bundle_id = $cart_item['woosb_bundle']['bundle_id'] ?? $cart_item['woosb_group_key'] ?? '';
				$parent_id = (int) ( $cart_item['woosb_bundle']['parent_id'] ?? $cart_item['woosb_parent_id'] ?? 0 );

				foreach ( WC()->cart->cart_contents as $key => $item ) {
					$is_parent = ! empty( $item['woosb_ids'] ) || ( ! empty( $item['woosb_bundle']['role'] ) && $item['woosb_bundle']['role'] === 'parent' );

					if ( $is_parent ) {
						$other_bundle_id = $item['woosb_bundle']['bundle_id'] ?? $item['woosb_group_key'] ?? '';

						// Match by bundle_id / group key
						if ( ! empty( $bundle_id ) && ! empty( $other_bundle_id ) && $bundle_id === $other_bundle_id ) {
							return false;
						}

						// Match by translated parent product ID
						if ( $parent_id > 0 ) {
							$trans_parent_id = (int) apply_filters( 'wpml_object_id', $parent_id, 'product', true );

							if ( $trans_parent_id === (int) $item['product_id'] ) {
								return false;
							}
						}
					}
				}
			}

			return $remove;
		}

		/**
		 * Re-sync cart items connections (parent-child keys and IDs) across language switches.
		 *
		 * @param WC_Cart|null $cart Cart instance.
		 */
		function wpml_sync_cart_contents( $cart = null ) {
			if ( ! function_exists( 'wpml_loaded' ) ) {
				return;
			}

			if ( is_null( $cart ) || ! is_a( $cart, 'WC_Cart' ) ) {
				$cart = WC()->cart;
			}

			if ( ! $cart || empty( $cart->cart_contents ) ) {
				return;
			}

			$parents  = [];
			$children = [];

			foreach ( $cart->cart_contents as $key => $item ) {
				$role = $item['woosb_bundle']['role'] ?? '';

				if ( $role === 'parent' || ! empty( $item['woosb_ids'] ) ) {
					$parents[ $key ] = $item;
				} elseif ( $role === 'child' || ! empty( $item['woosb_parent_id'] ) || ! empty( $item['woosb_parent_key'] ) || ! empty( $item['woosb_group_key'] ) ) {
					$children[ $key ] = $item;
				}
			}

			if ( empty( $parents ) ) {
				return;
			}

			foreach ( $parents as $parent_key => $parent_item ) {
				$parent_product_id = (int) $parent_item['product_id'];
				$parent_group_key  = $parent_item['woosb_group_key'] ?? '';
				$parent_bundle_id  = $parent_item['woosb_bundle']['bundle_id'] ?? $parent_group_key;
				$new_child_keys    = [];

				foreach ( $children as $child_key => $child_item ) {
					$is_match        = false;
					$child_bundle_id = $child_item['woosb_bundle']['bundle_id'] ?? $child_item['woosb_group_key'] ?? '';

					// 1. Match by unique bundle_id / group key if available
					if ( ! empty( $parent_bundle_id ) && ! empty( $child_bundle_id ) ) {
						if ( $parent_bundle_id === $child_bundle_id ) {
							$is_match = true;
						}
					}

					// 2. Match by direct parent key if already matching
					if ( ! $is_match && ! empty( $child_item['woosb_parent_key'] ) && $child_item['woosb_parent_key'] === $parent_key ) {
						$is_match = true;
					}

					// 3. Match by translated parent product ID
					if ( ! $is_match && ! empty( $child_item['woosb_parent_id'] ) ) {
						$translated_parent_id = (int) apply_filters( 'wpml_object_id', (int) $child_item['woosb_parent_id'], 'product', true );

						if ( $translated_parent_id === $parent_product_id ) {
							$is_match = true;
						}
					}

					if ( $is_match ) {
						// Update child references to current parent key and translated parent product ID
						$cart->cart_contents[ $child_key ]['woosb_parent_key'] = $parent_key;
						$cart->cart_contents[ $child_key ]['woosb_parent_id']  = $parent_product_id;
						$cart->cart_contents[ $child_key ]['woosb_key']        = $child_key;

						// Sync group key and bundle_id
						if ( ! empty( $parent_bundle_id ) ) {
							$cart->cart_contents[ $child_key ]['woosb_group_key'] = $parent_group_key ?: $parent_bundle_id;
							if ( isset( $cart->cart_contents[ $child_key ]['woosb_bundle'] ) ) {
								$cart->cart_contents[ $child_key ]['woosb_bundle']['bundle_id'] = $parent_bundle_id;
								$cart->cart_contents[ $child_key ]['woosb_bundle']['parent_id'] = $parent_product_id;
							}
						}

						// Sync pricing configuration from parent
						if ( isset( $parent_item['woosb_fixed_price'] ) ) {
							$cart->cart_contents[ $child_key ]['woosb_fixed_price'] = $parent_item['woosb_fixed_price'];
						}

						if ( isset( $parent_item['woosb_discount'] ) ) {
							$cart->cart_contents[ $child_key ]['woosb_discount'] = $parent_item['woosb_discount'];
						}

						if ( isset( $parent_item['woosb_discount_amount'] ) ) {
							$cart->cart_contents[ $child_key ]['woosb_discount_amount'] = $parent_item['woosb_discount_amount'];
						}

						if ( isset( $parent_item['woosb_base_discount_amount'] ) ) {
							$cart->cart_contents[ $child_key ]['woosb_base_discount_amount'] = $parent_item['woosb_base_discount_amount'];
						}

						$new_child_keys[] = $child_key;
					}
				}

				// Convert discount amount for active currency if WCML multi-currency is active
				if ( ! empty( $parent_item['woosb_base_discount_amount'] ) ) {
					$converted_discount = (float) apply_filters( 'wcml_raw_price_amount', $parent_item['woosb_base_discount_amount'] );
					$cart->cart_contents[ $parent_key ]['woosb_discount_amount'] = $converted_discount;
					if ( isset( $cart->cart_contents[ $parent_key ]['woosb_bundle'] ) ) {
						$cart->cart_contents[ $parent_key ]['woosb_bundle']['discount_amount'] = $converted_discount;
					}
					foreach ( $new_child_keys as $ck ) {
						$cart->cart_contents[ $ck ]['woosb_discount_amount'] = $converted_discount;
					}
				}

				// Update parent references
				$cart->cart_contents[ $parent_key ]['woosb_key']  = $parent_key;
				$cart->cart_contents[ $parent_key ]['woosb_keys'] = array_unique( $new_child_keys );

				// Translate product/variation IDs in parent's woosb_ids
				if ( ! empty( $parent_item['woosb_ids'] ) ) {
					if ( is_array( $parent_item['woosb_ids'] ) ) {
						foreach ( $parent_item['woosb_ids'] as $k => $item_arr ) {
							if ( ! empty( $item_arr['id'] ) ) {
								$post_type = get_post_type( (int) $item_arr['id'] ) ?: 'product';
								$trans_id  = (int) apply_filters( 'wpml_object_id', (int) $item_arr['id'], $post_type, true );

								if ( $trans_id ) {
									$cart->cart_contents[ $parent_key ]['woosb_ids'][ $k ]['id'] = $trans_id;
								}
							}
						}
					} elseif ( is_string( $parent_item['woosb_ids'] ) ) {
						$ids_items        = explode( ',', $parent_item['woosb_ids'] );
						$translated_items = [];

						foreach ( $ids_items as $item_str ) {
							if ( empty( $item_str ) ) {
								continue;
							}

							$parts = explode( '/', $item_str );

							if ( ! empty( $parts[0] ) && is_numeric( $parts[0] ) ) {
								$item_post_type = get_post_type( (int) $parts[0] ) ?: 'product';
								$trans_id       = (int) apply_filters( 'wpml_object_id', (int) $parts[0], $item_post_type, true );

								if ( $trans_id ) {
									$parts[0] = $trans_id;
								}
							}

							$translated_items[] = implode( '/', $parts );
						}

						$cart->cart_contents[ $parent_key ]['woosb_ids'] = implode( ',', $translated_items );
					}
				}
			}
		}

		/**
		 * Re-sync cart items right before woosb calculates totals.
		 *
		 * @param WC_Cart $cart Cart instance.
		 */
		function wpml_sync_cart_contents_totals( $cart ) {
			$this->wpml_sync_cart_contents( $cart );
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