<?php
declare( strict_types=1 );
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverWoosb_Helper' ) ) {
	class WPCleverWoosb_Helper {
		protected static $instance = null;
		protected static $settings = [];
		protected static $localization = [];
		protected static $image_size = 'woocommerce_thumbnail';
		protected static $types = [
			'bundle',
			'woosb',
			'composite',
			'grouped',
			'woosg',
			'external',
			'variable',
			'variation'
		];
		protected static $bundles_cache = [];
		protected static $bundled_cache = [];

		public static function instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		function __construct() {
			// settings
			self::$settings = (array) get_option( 'woosb_settings', [] );
			// localization
			self::$localization = (array) get_option( 'woosb_localization', [] );
		}

		public static function get_image_size(): string {
			return (string) apply_filters( 'woosb_image_size', self::$image_size );
		}

		public static function get_types(): array {
			return (array) apply_filters( 'woosb_types', self::$types );
		}

		public static function round_price( $price ): float {
			static $decimals = null;

			$price         = (float) $price;
			$rounded_price = $price;
			$should_round  = ! apply_filters( 'woosb_ignore_round_price', false );

			if ( apply_filters( 'woosb_round_price', $should_round ) ) {
				if ( $decimals === null ) {
					$decimals = (int) apply_filters( 'woosb_price_decimals', wc_get_price_decimals() );
				}

				$rounded_price = round( $price, $decimals );
			}

			return (float) apply_filters( 'woosb_rounded_price', $rounded_price, $price );
		}

		public static function get_price( $product, $min_or_max = 'min', $for_display = false ) {
			if ( self::get_setting( 'bundled_price_from', 'sale_price' ) === 'regular_price' ) {
				if ( $product->is_type( 'variable' ) ) {
					if ( $min_or_max === 'max' ) {
						$price = $product->get_variation_regular_price( 'max', $for_display );
					} else {
						$price = $product->get_variation_regular_price( 'min', $for_display );
					}
				} else {
					$price = $product->get_regular_price();
				}
			} else {
				if ( $product->is_type( 'variable' ) ) {
					if ( $min_or_max === 'max' ) {
						$price = $product->get_variation_price( 'max', $for_display );
					} else {
						$price = $product->get_variation_price( 'min', $for_display );
					}
				} else {
					$price = $product->get_price();
				}
			}

			return apply_filters( 'woosb_get_price', (float) $price, $product, $min_or_max );
		}

		public static function get_price_to_display( $product, $qty = 1, $min_or_max = 'min' ) {
			if ( is_array( $qty ) ) {
				$qty = array_merge( [ 'price' => self::get_price( $product, $min_or_max ), 'qty' => 1 ], $qty );

				return apply_filters( 'woosb_get_price_to_display', (float) wc_get_price_to_display( $product, [
					'price' => $qty['price'],
					'qty'   => $qty['qty']
				] ), $product, $qty, $min_or_max );
			} else {
				return apply_filters( 'woosb_get_price_to_display', (float) wc_get_price_to_display( $product, [
					'price' => self::get_price( $product, $min_or_max ),
					'qty'   => $qty
				] ), $product, $qty, $min_or_max );
			}
		}

		public static function set_price( $product, $price = 0 ) {
			$product->set_regular_price( $price );
			$product->set_sale_price( $price );
			$product->set_price( $price );
		}

		public static function is_in_stock( $product ) {
			if ( $product->is_type( 'variable' ) ) {
				return $product->child_is_in_stock() || $product->child_is_on_backorder();
			} else {
				return $product->is_in_stock();
			}
		}

		public static function has_enough_stock( $product, $qty ) {
			if ( ! is_a( $product, 'WC_Product' ) ) {
				return false;
			}

			if ( $product->is_type( 'variable' ) && apply_filters( 'woosb_check_variations_stock', true ) ) {
				$variations = $product->get_available_variations( 'objects' );

				foreach ( $variations as $variation ) {
					if ( $variation->has_enough_stock( $qty ) ) {
						return true;
					}
				}

				return false;
			} else {
				return $product->has_enough_stock( $qty );
			}
		}

		public static function get_stock_quantity( $product ) {
			if ( ! is_a( $product, 'WC_Product' ) ) {
				return null;
			}

			if ( $product->is_type( 'variable' ) && apply_filters( 'woosb_check_variations_stock', true ) ) {
				$stock_quantity = null;
				$variations     = $product->get_available_variations( 'objects' );

				foreach ( $variations as $variation ) {
					if ( ( $variation->get_stock_quantity() !== null ) && ( $variation->get_stock_quantity() > (float) $stock_quantity ) ) {
						$stock_quantity = $variation->get_stock_quantity();
					}
				}

				return $stock_quantity;
			} else {
				return $product->get_stock_quantity();
			}
		}

		public static function sanitize_array( $arr ) {
			foreach ( (array) $arr as $k => $v ) {
				if ( is_array( $v ) ) {
					$arr[ $k ] = self::sanitize_array( $v );
				} else {
					$arr[ $k ] = sanitize_post_field( 'post_content', $v, 0, 'db' );
				}
			}

			return $arr;
		}

		public static function get_product_id_from_sku( $sku, $old_id = 0 ) {
			if ( $old_id && ( $parent_id = wp_get_post_parent_id( $old_id ) ) && ( $parent = wc_get_product( $parent_id ) ) ) {
				$parent_sku = $parent->get_sku();
			} else {
				$parent_sku = '';
			}

			if ( ! empty( $sku ) && ( $sku !== $parent_sku ) && ( $new_id = wc_get_product_id_by_sku( $sku ) ) ) {
				return $new_id;
			}

			return 0;
		}

		public static function clean_ids( $ids ) {
			return apply_filters( 'woosb_clean_ids', $ids );
		}

		public static function clean( $var ) {
			if ( is_array( $var ) ) {
				return array_map( [ __CLASS__, 'clean' ], $var );
			} else {
				return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
			}
		}

		public static function minify_items( $items ) {
			$minify_items = [];

			foreach ( $items as $item ) {
				if ( ! empty( $item['id'] ) ) {
					if ( empty( $minify_items ) ) {
						$minify_items[] = $item;
					} else {
						$has_item = false;

						foreach ( $minify_items as $key => $minify_item ) {
							if ( ( $minify_item['id'] === $item['id'] ) && ( $minify_item['attrs'] === $item['attrs'] ) ) {
								$minify_items[ $key ]['qty'] += $item['qty'];
								$has_item                    = true;
								break;
							}
						}

						if ( ! $has_item ) {
							$minify_items[] = $item;
						}
					}
				}
			}

			return apply_filters( 'woosb_minify_items', $minify_items, $items );
		}

		public static function get_settings() {
			return apply_filters( 'woosb_get_settings', self::$settings );
		}

		public static function get_setting( $name, $default = false ) {
			if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
				$setting = self::$settings[ $name ];
			} else {
				$setting = get_option( 'woosb_' . $name, $default );
			}

			return apply_filters( 'woosb_get_setting', $setting, $name, $default );
		}

		public static function localization( $key = '', $default = '' ) {
			$str = '';

			if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
				$str = self::$localization[ $key ];
			} elseif ( ! empty( $default ) ) {
				$str = $default;
			}

			return apply_filters( 'woosb_localization_' . $key, $str );
		}

		public static function generate_key() {
			$key         = '';
			$key_str     = apply_filters( 'woosb_key_characters', 'abcdefghijklmnopqrstuvwxyz0123456789' );
			$key_str_len = strlen( $key_str );

			for ( $i = 0; $i < apply_filters( 'woosb_key_length', 4 ); $i ++ ) {
				$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
			}

			if ( is_numeric( $key ) ) {
				$key = self::generate_key();
			}

			return apply_filters( 'woosb_generate_key', $key );
		}

		public static function data_attributes( $attrs ) {
			$attrs_arr = [];

			foreach ( $attrs as $key => $attr ) {
				$attrs_arr[] = 'data-' . sanitize_title( str_replace( 'data-', '', $key ) ) . '="' . esc_attr( $attr ) . '"';
			}

			return implode( ' ', $attrs_arr );
		}

		public static function get_bundled( $ids, $product = null ) {
			if ( empty( $ids ) ) {
				return apply_filters( 'woosb_get_bundled', [], $product );
			}

			// Use request cache
			$cache_key = is_array( $ids ) ? md5( serialize( $ids ) ) : $ids;
			if ( isset( self::$bundled_cache[ $cache_key ] ) ) {
				return apply_filters( 'woosb_get_bundled', self::$bundled_cache[ $cache_key ], $product );
			}

			$bundled    = [];
			$product_id = 0;

			// Use a null coalescing operator for cleaner product_id assignment
			if ( is_a( $product, 'WC_Product' ) ) {
				$product_id = $product->get_id();
			} elseif ( is_numeric( $product ) ) {
				$product_id = $product;
			}

			// Cache post meta values to reduce database calls
			$meta_cache = [
				'min'         => get_post_meta( $product_id, 'woosb_limit_each_min', true ),
				'min_default' => get_post_meta( $product_id, 'woosb_limit_each_min_default', true ) === 'on',
				'max'         => get_post_meta( $product_id, 'woosb_limit_each_max', true )
			];

			$use_sku = apply_filters( 'woosb_use_sku', false );

			if ( is_array( $ids ) ) {
				// Process array format
				foreach ( $ids as $key => $item ) {
					$item = array_merge( [
						'id'    => 0,
						'sku'   => '',
						'qty'   => 0,
						'attrs' => []
					], $item );

					if ( $use_sku && ! empty( $item['sku'] ) ) {
						$new_id = self::get_product_id_from_sku( $item['sku'] );

						if ( $new_id ) {
							$item['id'] = $new_id;
						}
					}

					if ( ! isset( $item['min'] ) ) {
						$item['min'] = $meta_cache['min'];

						if ( $meta_cache['min_default'] ) {
							$item['min'] = (float) $item['qty'];
						}

						$item['max'] = $meta_cache['max'];
					}

					$bundled[ is_numeric( $key ) ? self::generate_key() : $key ] = $item;
				}
			} else {
				// Process string format
				$items = array_filter( explode( ',', $ids ) );

				foreach ( $items as $item ) {
					$data = explode( '/', $item );
					$id   = rawurldecode( $data[0] ?? 0 );

					// Determine key and quantity
					if ( isset( $data[1] ) ) {
						if ( is_numeric( $data[1] ) && ! isset( $data[2] ) ) {
							$key = self::generate_key();
							$qty = (float) $data[1];
						} else {
							$key = $data[1];
							$qty = (float) ( $data[2] ?? 1 );
						}
					} else {
						$key = self::generate_key();
						$qty = 1;
					}

					// Handle SKU or ID
					if ( ! is_numeric( $id ) ) {
						$sku = $id;
						$id  = wc_get_product_id_by_sku( ltrim( $id, 'sku-' ) );
					} else {
						$item_product = wc_get_product( $id );
						$sku          = $item_product ? $item_product->get_sku() : '';
					}

					if ( $id ) {
						$bundled[ $key ] = [
							'id'  => $id,
							'sku' => $sku,
							'qty' => $qty
						];
					}
				}
			}

			self::$bundled_cache[ $cache_key ] = $bundled;

			return apply_filters( 'woosb_get_bundled', $bundled, $product );
		}

		public static function get_bundles( $product_id, $per_page = 500, $offset = 0, $context = 'view' ) {
			// Use request cache
			if ( isset( self::$bundles_cache[ $product_id ] ) ) {
				return apply_filters( 'woosb_get_bundles', self::$bundles_cache[ $product_id ], $product_id );
			}

			$bundles = [];
			$sku     = get_post_meta( $product_id, '_sku', true );

			// Prepare search patterns at once
			$search_patterns = [];

			// Add product ID patterns
			$id_str            = $product_id . '/';
			$search_patterns[] = [
				'key'     => 'woosb_ids',
				'value'   => '"' . $product_id . '"',
				'compare' => 'LIKE'
			];
			$search_patterns[] = [
				'key'     => 'woosb_ids',
				'value'   => ',' . $id_str,
				'compare' => 'LIKE'
			];
			$search_patterns[] = [
				'key'     => 'woosb_ids',
				'value'   => '^' . $id_str,
				'compare' => 'REGEXP'
			];

			// Add SKU patterns if valid
			if ( ! empty( $sku ) && ! is_numeric( $sku ) ) {
				$sku_str = $sku . '/';
			} else {
				$sku     = 'woosb';
				$sku_str = 'woosb/';
			}

			$search_patterns[] = [
				'key'     => 'woosb_ids',
				'value'   => '"' . $sku . '"',
				'compare' => 'LIKE'
			];
			$search_patterns[] = [
				'key'     => 'woosb_ids',
				'value'   => ',' . $sku_str,
				'compare' => 'LIKE'
			];
			$search_patterns[] = [
				'key'     => 'woosb_ids',
				'value'   => '^' . $sku_str,
				'compare' => 'REGEXP'
			];

			// Build query args
			$query_args = [
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'offset'                 => $offset,
				'no_found_rows'          => true, // Skip counting total rows when pagination is not needed
				'update_post_meta_cache' => false, // Skip loading post-meta data we don't need
				'update_post_term_cache' => false, // Skip loading term data we don't need
				'tax_query'              => [
					[
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => [ 'woosb' ],
						'operator' => 'IN',
					]
				],
				'meta_query'             => array_merge(
					[ 'relation' => 'OR' ],
					$search_patterns
				)
			];

			$query = new WP_Query( $query_args );

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$_product = wc_get_product( get_the_ID() );

					if ( ! $_product || ( ( $context !== 'edit' ) &&
					                      ! apply_filters( 'woosb_bundles_visible', $_product->is_visible(), $_product ) ) ) {
						continue;
					}

					$bundles[] = $_product;
				}

				wp_reset_postdata(); // Use wp_reset_postdata() instead of wp_reset_query()
			}

			self::$bundles_cache[ $product_id ] = $bundles;

			return apply_filters( 'woosb_get_bundles', $bundles, $product_id );
		}
	}

	function WPCleverWoosb_Helper() {
		return WPCleverWoosb_Helper::instance();
	}
}