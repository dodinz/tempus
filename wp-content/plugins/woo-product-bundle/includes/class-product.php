<?php
declare( strict_types=1 );
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Product_Woosb' ) && class_exists( 'WC_Product' ) ) {
	class WC_Product_Woosb extends WC_Product {
		protected $items = null;
		protected $bundled_products = [];
		protected $helper = null;

		public function __construct( $product = 0 ) {
			// Cache helper instance
			$this->helper = WPCleverWoosb_Helper();

			$this->supports[] = 'ajax_add_to_cart';
			parent::__construct( $product );

			// Preload all metadata at once
			$this->preload_meta();

			$this->build_items();
		}

		public function get_type(): string {
			return 'woosb';
		}

		public function add_to_cart_url(): string {
			$product_id = $this->get_id();

			$can_add_directly = $this->is_purchasable()
			                    && $this->is_in_stock()
			                    && ! $this->has_variables()
			                    && ! $this->has_optional();

			$url = $can_add_directly
				? remove_query_arg( 'added-to-cart', add_query_arg( 'add-to-cart', $product_id ) )
				: get_permalink( $product_id );

			return (string) apply_filters(
				'woosb_product_add_to_cart_url',
				apply_filters( 'woocommerce_product_add_to_cart_url', $url, $this ),
				$this
			);
		}

		public function add_to_cart_text(): string {
			if ( ! $this->is_purchasable() || ! $this->is_in_stock() ) {
				$text = $this->helper->localization( 'button_read', esc_html__( 'Read more', 'woo-product-bundle' ) );
			} else {
				$button_type  = ( ! $this->has_variables() && ! $this->has_optional() ) ? 'button_add' : 'button_select';
				$default_text = ( $button_type === 'button_add' ) ? esc_html__( 'Add to cart', 'woo-product-bundle' ) : esc_html__( 'Select options', 'woo-product-bundle' );
				$text         = $this->helper->localization( $button_type, $default_text );
			}

			return (string) apply_filters(
				'woosb_product_add_to_cart_text',
				apply_filters( 'woocommerce_product_add_to_cart_text', $text, $this ),
				$this
			);
		}

		public function single_add_to_cart_text(): string {
			$default_text = esc_html__( 'Add to cart', 'woo-product-bundle' );

			return (string) apply_filters(
				'woosb_product_single_add_to_cart_text',
				apply_filters( 'woocommerce_product_single_add_to_cart_text', $this->helper->localization( 'button_single', $default_text ), $this ),
				$this
			);
		}

		public function is_on_sale( $context = 'view' ): bool {
			if ( $this->is_fixed_price() ) {
				return parent::is_on_sale( $context );
			}

			// Cache discount values to avoid multiple method calls
			$discount_amount     = $this->get_discount_amount();
			$discount_percentage = $this->get_discount_percentage();

			// Return true if either discount is set, otherwise check parent
			return $discount_amount || $discount_percentage || parent::is_on_sale( $context );
		}

		public function get_regular_price( $context = 'view' ) {
			// Early return for non-view context or fixed price
			if ( $context !== 'view' || $this->is_fixed_price() ) {
				return parent::get_regular_price( $context );
			}

			$regular_price = 0;

			// Check items existence early
			if ( empty( $this->items ) ) {
				return $regular_price;
			}

			// Process items
			foreach ( $this->items as $item ) {
				// Get cached product object
				$_product = $this->get_bundled_product_object( $item['id'] );

				// Skip invalid products or woosb type
				if ( ! $_product || $_product->is_type( 'woosb' ) ) {
					continue;
				}

				// Calculate item price
				if ( $_product->is_type( 'variable' ) ) {
					$regular_price += (float) $_product->get_variation_regular_price( 'max' ) * (float) $item['qty'];
				} else {
					$regular_price += (float) $_product->get_regular_price() * (float) $item['qty'];
				}
			}

			return $regular_price ?: parent::get_regular_price( $context );
		}

		public function get_sale_price( $context = 'view' ) {
			// Early return for non-view context or fixed price
			if ( $context !== 'view' || $this->is_fixed_price() ) {
				return parent::get_sale_price( $context );
			}

			// Cache discount values
			$discount_amount     = $this->get_discount_amount();
			$discount_percentage = $this->get_discount_percentage();

			// Early return if no discount
			if ( ! $discount_amount && ! $discount_percentage ) {
				return '';
			}

			$sale_price = 0;

			// Check items existence early
			if ( empty( $this->items ) ) {
				return $sale_price;
			}

			// Process items
			foreach ( $this->items as $item ) {
				// Get cached product object
				$_product = $this->get_bundled_product_object( $item['id'] );

				// Skip invalid products or woosb type
				if ( ! $_product || $_product->is_type( 'woosb' ) ) {
					continue;
				}

				// Calculate item price
				$_price = (float) $this->helper->get_price( $_product ) * (float) $item['qty'];

				// Apply discount percentage if applicable
				if ( $discount_percentage ) {
					$sale_price += $this->helper->round_price( $_price * ( 100 - $discount_percentage ) / 100 );
				} else {
					$sale_price += $_price;
				}
			}

			// Apply a fixed discount amount if applicable
			return $discount_amount ? ( $sale_price - $discount_amount ) : $sale_price;
		}

		public function get_price( $context = 'view' ) {
			// Early return if not view context
			if ( $context !== 'view' ) {
				return parent::get_price( $context );
			}

			// Cache values to avoid multiple method calls
			$regular_price = (float) $this->get_regular_price();
			$parent_price  = (float) parent::get_price( $context );

			// Return '0' if either price is zero
			if ( $regular_price === 0.0 || $parent_price === 0.0 ) {
				return '0';
			}

			return parent::get_price( $context );
		}

		public function get_manage_stock( $context = 'view' ) {
			$parent_manage = parent::get_manage_stock( $context );

			// Early return if stock management is disabled globally or via filter
			if (
				'yes' !== get_option( 'woocommerce_manage_stock' ) ||
				apply_filters( 'woosb_disable_inventory_management', false )
			) {
				return $parent_manage;
			}

			// Early return if no items or has optional items
			if ( empty( $this->items ) || ( $this->has_optional() && ! apply_filters( 'woosb_manage_stock_optional_items', false ) ) ) {
				return $parent_manage;
			}

			$exclude_unpurchasable = $this->exclude_unpurchasable();

			foreach ( $this->items as $item ) {
				$product = $this->get_bundled_product_object( $item['id'] );

				// Skip invalid products or those meeting exclusion criteria
				if (
					! $product ||
					$product->is_type( 'woosb' ) ||
					( $exclude_unpurchasable &&
					  ( ! $product->is_purchasable() || ! $this->helper->is_in_stock( $product ) ) )
				) {
					continue;
				}

				// Return true if the product manages stock
				if ( $product->get_manage_stock( $context ) === true ) {
					return true;
				}

				// Check the parent product if this is a variation
				if ( $product->is_type( 'variation' ) ) {
					$parent_product = $this->get_bundled_product_object( $product->get_parent_id() );

					if ( $parent_product && $parent_product->get_manage_stock( $context ) === true ) {
						return true;
					}
				}
			}

			// Return parent manages stock setting if this product manages stock
			return $this->is_manage_stock() ? $parent_manage : false;
		}

		public function get_stock_status( $context = 'view' ) {
			$parent_status = parent::get_stock_status( $context );

			// Early return if inventory management is disabled
			if ( apply_filters( 'woosb_disable_inventory_management', false ) ) {
				return $parent_status;
			}

			// Early return if no items
			if ( empty( $this->items ) ) {
				return $parent_status;
			}

			$exclude_unpurchasable = $this->exclude_unpurchasable();
			$stock_status          = 'instock';
			$all_out_of_stock      = true;

			foreach ( $this->items as $item ) {
				// Skip if the product doesn't exist
				$_product = $this->get_bundled_product_object( $item['id'] );

				if ( ! $_product || $_product->is_type( 'woosb' ) ) {
					continue;
				}

				$_qty = (float) $item['qty'];

				if ( ! empty( $item['optional'] ) ) {
					$_qty = ! empty( $item['min'] ) ? (float) $item['min'] : 0;
				}

				// Cache commonly used method results
				$is_in_stock      = $this->helper->is_in_stock( $_product );
				$has_enough_stock = $this->helper->has_enough_stock( $_product, $_qty );

				if ( $is_in_stock && $has_enough_stock ) {
					$all_out_of_stock = false;
				}

				if ( $exclude_unpurchasable && ( ! $_product->is_purchasable() || ! $is_in_stock ) ) {
					continue;
				}

				if ( $_qty && ( $_product->get_stock_status( $context ) === 'outofstock' || ! $has_enough_stock ) ) {
					return 'outofstock';
				}

				if (
					$_product->get_stock_status( $context ) === 'onbackorder' ||
					( $_qty && ! $has_enough_stock && $_product->backorders_allowed() )
				) {
					$stock_status = 'onbackorder';
				}
			}

			if ( $all_out_of_stock ) {
				return 'outofstock';
			}

			if ( $this->is_manage_stock() ) {
				return $parent_status === 'instock' ? $stock_status : $parent_status;
			}

			return $stock_status;
		}

		public function get_stock_quantity( $context = 'view' ) {
			$parent_quantity = parent::get_stock_quantity( $context );

			// Early return if stock management is disabled
			if (
				'yes' !== get_option( 'woocommerce_manage_stock' ) ||
				apply_filters( 'woosb_disable_inventory_management', false )
			) {
				return $parent_quantity;
			}

			$product_id            = $this->id;
			$exclude_unpurchasable = $this->exclude_unpurchasable();
			$items                 = $this->items;

			// Early return if no items or has optional items
			if ( ! $items || ( $this->has_optional() && ! apply_filters( 'woosb_manage_stock_optional_items', false ) ) ) {
				if ( apply_filters( 'woosb_update_stock', true ) ) {
					update_post_meta( $product_id, '_stock', $parent_quantity );
				}

				return $parent_quantity;
			}

			$available_qty = [];

			foreach ( $items as $item ) {
				// Skip if quantity is not positive
				if ( $item['qty'] <= 0 ) {
					continue;
				}

				$_product = $this->get_bundled_product_object( $item['id'] );

				// Cache stock quantity to avoid multiple calls
				$stock_quantity = $this->helper->get_stock_quantity( $_product );

				// Skip invalid products or those not meeting criteria
				if (
					! $_product ||
					$_product->is_type( 'woosb' ) ||
					! $_product->get_manage_stock() ||
					$stock_quantity === null ||
					( $exclude_unpurchasable && ( ! $_product->is_purchasable() || ! $this->helper->is_in_stock( $_product ) ) )
				) {
					continue;
				}

				$available_qty[] = floor( $stock_quantity / (float) $item['qty'] );
			}

			// If no available quantities found, update and return the parent quantity
			if ( empty( $available_qty ) ) {
				if ( apply_filters( 'woosb_update_stock', true ) ) {
					update_post_meta( $product_id, '_stock', $parent_quantity );
				}

				return $parent_quantity;
			}

			// Find minimum available quantity without sorting a full array
			$min_available = min( $available_qty );

			// Use parent quantity if it's lower and stock is managed
			if ( $this->is_manage_stock() && $parent_quantity < $min_available ) {
				if ( apply_filters( 'woosb_update_stock', true ) ) {
					update_post_meta( $product_id, '_stock', $parent_quantity );
				}

				return $parent_quantity;
			}

			if ( apply_filters( 'woosb_update_stock', true ) ) {
				update_post_meta( $product_id, '_stock', $min_available );
			}

			return $min_available;
		}

		public function get_backorders( $context = 'view' ) {
			$parent_backorders = parent::get_backorders( $context );

			// Early return if inventory management is disabled
			if ( apply_filters( 'woosb_disable_inventory_management', false ) ) {
				return $parent_backorders;
			}

			// Early return if no items or has optional items
			if ( empty( $this->items ) || ( $this->has_optional() && ! apply_filters( 'woosb_manage_stock_optional_items', false ) ) ) {
				return $parent_backorders;
			}

			$backorders            = 'yes';
			$exclude_unpurchasable = $this->exclude_unpurchasable();

			foreach ( $this->items as $item ) {
				// Get product once
				$product = $this->get_bundled_product_object( $item['id'] ?: 0 );

				// Skip if the product doesn't meet criteria
				if ( ! $product || ! is_a( $product, 'WC_Product' ) || $product->is_type( 'woosb' ) ) {
					continue;
				}

				// Skip if the product doesn't meet criteria
				if ( ! $product->get_manage_stock() || ( $exclude_unpurchasable && ( ! $product->is_purchasable() || ! $this->helper->is_in_stock( $product ) ) ) ) {
					continue;
				}

				// Check backorders status
				$product_backorders = $product->get_backorders( $context );

				if ( $product_backorders === 'no' ) {
					return 'no';
				}

				if ( $product_backorders === 'notify' ) {
					$backorders = 'notify';
				}
			}

			// Simplified return logic
			if ( $this->is_manage_stock() ) {
				return $parent_backorders === 'yes' ? $backorders : $parent_backorders;
			}

			return $backorders;
		}

		public function get_sold_individually( $context = 'view' ) {
			$parent_individually = parent::get_sold_individually( $context );

			// Early return if inventory management is disabled
			if ( apply_filters( 'woosb_disable_inventory_management', false ) ) {
				return $parent_individually;
			}

			// Early return if no items or has optional items
			if ( empty( $this->items ) || ( $this->has_optional() && ! apply_filters( 'woosb_manage_stock_optional_items', false ) ) ) {
				return $parent_individually;
			}

			$exclude_unpurchasable = $this->exclude_unpurchasable();

			foreach ( $this->items as $item ) {
				$product = wc_get_product( $item['id'] );

				// Skip invalid products or those meeting exclusion criteria
				if (
					! $product ||
					$product->is_type( 'woosb' ) ||
					( $exclude_unpurchasable &&
					  ( ! $product->is_purchasable() || ! $this->helper->is_in_stock( $product ) ) )
				) {
					continue;
				}

				// Return true if any product is sold individually
				if ( $product->is_sold_individually() ) {
					return true;
				}
			}

			return $parent_individually;
		}

		public function needs_shipping() {
			return apply_filters( 'woocommerce_product_needs_shipping', ! $this->is_virtual() && ( $this->get_meta( 'woosb_shipping_fee' ) !== 'each' ), $this );
		}

		// extra functions

		public function has_variables() {
			// Early return if no items
			if ( empty( $this->items ) ) {
				return apply_filters( 'woosb_has_variables', false, $this );
			}

			// Use array_reduce for better performance
			$has_variables = array_reduce( $this->items, function ( $carry, $item ) {
				if ( $carry ) {
					return true;
				} // Skip if we already found a variable product

				if ( $product = $this->get_bundled_product_object( (int) $item['id'] ) ) {
					return $product->is_type( 'variable' ) ? true : $carry;
				}

				return $carry;
			}, false );

			return apply_filters( 'woosb_has_variables', $has_variables, $this );
		}

		public function has_optional() {
			// Early return if no items
			if ( empty( $this->items ) ) {
				return apply_filters( 'woosb_has_optional', false, $this );
			}

			// Use array_reduce for better performance
			$has_optional = array_reduce( $this->items, function ( $carry, $item ) {
				return $carry || ! empty( $item['optional'] );
			}, false );

			return apply_filters( 'woosb_has_optional', $has_optional, $this );
		}

		public function is_optional() {
			// new version 8.0
			return self::has_optional();
		}

		public function is_manage_stock() {
			return apply_filters( 'woosb_is_manage_stock', $this->get_meta( 'woosb_manage_stock' ) === 'on', $this );
		}

		public function is_fixed_price() {
			$disable_auto_price = $this->get_meta( 'woosb_disable_auto_price' ) ?: apply_filters( 'woosb_disable_auto_price_default', 'off' );

			return apply_filters( 'woosb_is_fixed_price', $disable_auto_price === 'on', $this );
		}

		public function exclude_unpurchasable() {
			// Get meta-value once
			$exclude_unpurchasable = $this->get_meta( 'woosb_exclude_unpurchasable' );

			// Check if we need to use the default setting
			if ( ! $exclude_unpurchasable || in_array( $exclude_unpurchasable, [ 'unset', 'default' ], true ) ) {
				$exclude_unpurchasable = $this->helper->get_setting( 'exclude_unpurchasable', 'no' );
			}

			return apply_filters( 'woosb_exclude_unpurchasable', $exclude_unpurchasable === 'yes', $this );
		}

		public function get_discount_amount() {
			// Early return if fixed price
			if ( $this->is_fixed_price() ) {
				return apply_filters( 'woosb_get_discount_amount', 0, $this );
			}

			// Get and cast discount amount in one step
			$discount_amount = (float) $this->get_meta( 'woosb_discount_amount' );

			return apply_filters( 'woosb_get_discount_amount', $discount_amount, $this );
		}

		public function get_discount_percentage() {
			// Early returns for fixed price or if discount amount exists
			if ( $this->is_fixed_price() || $this->get_discount_amount() ) {
				return apply_filters( 'woosb_get_discount_percentage', 0, $this );
			}

			// Get discount percentage
			$discount_percentage = $this->get_meta( 'woosb_discount' );

			// Validate discount percentage
			if ( is_numeric( $discount_percentage ) ) {
				$discount_percentage = (float) $discount_percentage;
				if ( $discount_percentage > 0 && $discount_percentage < 100 ) {
					return apply_filters( 'woosb_get_discount_percentage', $discount_percentage, $this );
				}
			}

			return apply_filters( 'woosb_get_discount_percentage', 0, $this );
		}

		public function get_discount() {
			$discount = $this->get_discount_amount() ?: $this->get_discount_percentage() . '%';

			return apply_filters( 'woosb_get_discount', $discount, $this );
		}

		public function get_ids() {
			return apply_filters( 'woosb_get_ids', $this->get_meta( 'woosb_ids' ), $this );
		}

		public function get_ids_str() {
			$ids = $this->get_ids();

			if ( ! is_array( $ids ) ) {
				return apply_filters( 'woosb_get_ids_str', $ids, $this );
			}

			$ids_str = implode( ',', array_map(
				function ( $key, $item ) {
					$use_sku    = apply_filters( 'woosb_use_sku', false );
					$product_id = $this->id;

					if ( $use_sku && ! empty( $item['sku'] ) ) {
						$new_id = $this->helper->get_product_id_from_sku( $item['sku'] );

						if ( $new_id ) {
							$item['id'] = $new_id;
						}
					}

					return ! empty( $item['id'] ) && ( $item['id'] != $product_id ) ? "{$item['id']}/{$key}/{$item['qty']}" : null;
				},
				array_keys( $ids ),
				$ids
			) );

			return apply_filters( 'woosb_get_ids_str', $ids_str, $this );
		}

		public function build_items( $ids = null ) {
			$items = [];
			$ids   = $ids ?: $this->get_meta( 'woosb_ids' );

			// Early return if no IDs
			if ( empty( $ids ) ) {
				$this->items = $items;

				return;
			}

			$product_id = $this->id;

			if ( is_array( $ids ) ) {
				// Process array format (v7.0+)
				// Cache meta values for better performance
				$limit_each_min         = $this->get_meta( 'woosb_limit_each_min' );
				$limit_each_min_default = $this->get_meta( 'woosb_limit_each_min_default' ) === 'on';
				$limit_each_max         = $this->get_meta( 'woosb_limit_each_max' );
				$use_sku                = apply_filters( 'woosb_use_sku', false );

				foreach ( $ids as $key => $item ) {
					// Set default values
					$item = array_merge( [
						'id'    => 0,
						'sku'   => '',
						'qty'   => 0,
						'attrs' => []
					], $item );

					// Process SKU if enabled
					if ( $use_sku && ! empty( $item['sku'] ) ) {
						$new_id = $this->helper->get_product_id_from_sku( $item['sku'] );

						if ( $new_id ) {
							$item['id'] = $new_id;
						}
					}

					if ( $item['id'] == $product_id ) {
						// prevent infinity loop
						continue;
					}

					// Set min/max values if not set (v8.0+)
					if ( ! isset( $item['min'] ) ) {
						$item['min'] = $limit_each_min_default ? (float) $item['qty'] : $limit_each_min;
						$item['max'] = $limit_each_max;
					}

					$item['id']  = apply_filters( 'woosb_item_id', $item['id'] );
					$item['sku'] = apply_filters( 'woosb_item_sku', $item['sku'] );

					$items[ $key ] = $item;
				}
			} else {
				// Process string format
				$ids_arr = explode( ',', $ids );

				if ( ! empty( $ids_arr ) ) {
					foreach ( $ids_arr as $ids_item ) {
						if ( empty( $ids_item ) ) {
							continue;
						}

						$data = explode( '/', $ids_item );
						$id   = rawurldecode( $data[0] ?? 0 );

						if ( empty( $id ) ) {
							continue;
						}

						// Get product ID and SKU
						if ( ! is_numeric( $id ) ) {
							// Process SKU
							$sku = $id;
							$id  = wc_get_product_id_by_sku( ltrim( $id, 'sku-' ) );
						} else {
							// Process ID
							$product = $this->get_bundled_product_object( (int) $id );
							$sku     = $product ? $product->get_sku() : '';
						}

						if ( $id == $product_id ) {
							// prevent infinity loop
							continue;
						}

						// Get key and quantity
						$key = isset( $data[1] )
							? ( is_numeric( $data[1] ) && ! isset( $data[2] )
								? $this->helper->generate_key()
								: $data[1] )
							: $this->helper->generate_key();

						$qty = isset( $data[1] )
							? ( is_numeric( $data[1] ) && ! isset( $data[2] )
								? (float) $data[1]
								: (float) ( $data[2] ?? 1 ) )
							: 1;

						// Build item array
						$items[ $key ] = [
							'id'    => apply_filters( 'woosb_item_id', $id ),
							'sku'   => apply_filters( 'woosb_item_sku', $sku ),
							'qty'   => $qty,
							'attrs' => isset( $data[3] )
								? (array) json_decode( rawurldecode( $data[3] ) )
								: []
						];
					}
				}
			}

			$this->items = $items;
		}

		protected function preload_meta() {
			// WC 3.0+ handles meta caching in Data Store, but we can ensure it's loaded
			$this->get_meta_data();
		}

		public function get_items() {
			return apply_filters( 'woosb_get_items', $this->items, $this );
		}

		/**
		 * Get cached bundled product object
		 *
		 * @param int $product_id
		 *
		 * @return WC_Product|null
		 */
		protected function get_bundled_product_object( $product_id ) {
			if ( ! isset( $this->bundled_products[ $product_id ] ) ) {
				$this->bundled_products[ $product_id ] = wc_get_product( $product_id );
			}

			return $this->bundled_products[ $product_id ] ?: null;
		}
	}
}
