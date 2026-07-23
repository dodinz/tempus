<?php
declare( strict_types=1 );
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverWoosb_Backend' ) ) {
    class WPCleverWoosb_Backend {
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

            // Settings
            add_action( 'admin_init', [ $this, 'register_settings' ] );
            add_filter( 'pre_update_option', [ $this, 'last_saved' ], 10, 2 );
            add_action( 'admin_menu', [ $this, 'admin_menu' ] );

            // Enqueue backend scripts
            add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

            // Backend AJAX
            add_action( 'wp_ajax_woosb_update_search_settings', [ $this, 'ajax_update_search_settings' ] );
            add_action( 'wp_ajax_woosb_get_search_results', [ $this, 'ajax_get_search_results' ] );
            add_action( 'wp_ajax_woosb_import_export', [ $this, 'ajax_import_export' ] );
            add_action( 'wp_ajax_woosb_import_export_save', [ $this, 'ajax_import_export_save' ] );

            // Search query modifiers
            add_action( 'pre_get_posts', [ $this, 'search_sku' ] );
            add_action( 'pre_get_posts', [ $this, 'search_exact' ] );
            add_action( 'pre_get_posts', [ $this, 'search_sentence' ] );

            // Add to selector
            add_filter( 'product_type_selector', [ $this, 'product_type_selector' ] );

            // Product data tabs
            add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );

            // Product data panels
            add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panels' ] );
            add_action( 'woocommerce_process_product_meta_woosb', [ $this, 'process_product_meta_woosb' ] );

            // Admin order
            add_action( 'woocommerce_ajax_add_order_item_meta', [ $this, 'add_order_item_meta' ], 10, 3 );
            add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hidden_order_itemmeta' ] );
            add_action( 'woocommerce_before_order_itemmeta', [ $this, 'before_order_itemmeta' ], 10, 2 );

            // Add settings link
            add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
            add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

            // Admin
            add_filter( 'display_post_states', [ $this, 'display_post_states' ], 10, 2 );

            // Bulk action
            add_action( 'current_screen', [ $this, 'bulk_actions' ] );

            // Emails
            add_action( 'woocommerce_no_stock_notification', [ $this, 'no_stock_notification' ], 99 );
            add_action( 'woocommerce_low_stock_notification', [ $this, 'low_stock_notification' ], 99 );

            // Export
            add_filter( 'woocommerce_product_export_meta_value', [ $this, 'export_process' ], 10, 3 );

            // Import
            add_filter( 'woocommerce_product_import_pre_insert_product_object', [ $this, 'import_process' ], 10, 2 );
        }

        function register_settings() {
            // settings
            register_setting( 'woosb_settings', 'woosb_settings', [
                    'type'              => 'array',
                    'sanitize_callback' => [ 'WPCleverWoosb_Helper', 'sanitize_array' ],
            ] );
            // localization
            register_setting( 'woosb_localization', 'woosb_localization', [
                    'type'              => 'array',
                    'sanitize_callback' => [ 'WPCleverWoosb_Helper', 'sanitize_array' ],
            ] );
        }

        function last_saved( $value, $option ) {
            if ( $option == 'woosb_settings' || $option == 'woosb_localization' ) {
                $value['_last_saved']    = current_time( 'timestamp' );
                $value['_last_saved_by'] = get_current_user_id();
            }

            return $value;
        }

        function admin_menu() {
            add_submenu_page( 'wpclever', esc_html__( 'WPC Product Bundles', 'woo-product-bundle' ), esc_html__( 'Product Bundles', 'woo-product-bundle' ), 'manage_options', 'wpclever-woosb', [
                    $this,
                    'admin_menu_content'
            ] );
        }

        function admin_menu_content() {
            add_thickbox();
            $active_tab     = sanitize_key( wp_unslash( $_GET['tab'] ?? 'settings' ) );
            $active_section = sanitize_key( wp_unslash( $_GET['section'] ?? 'none' ) );
            $settings_class = 'wpclever_settings_page_content wpclever_settings_tab_' . $active_tab . ' wpclever_settings_section_' . $active_section;
            ?>
            <div class="wpclever_settings_page wrap">
                <div class="wpclever_settings_page_header">
                    <a class="wpclever_settings_page_header_logo" href="https://wpclever.net/" target="_blank"
                       title="Visit wpclever.net"></a>
                    <div class="wpclever_settings_page_header_text">
                        <div class="wpclever_settings_page_title">
                            <?php echo esc_html__( 'WPC Product Bundles', 'woo-product-bundle' ) . ' ' . esc_html( WOOSB_VERSION ) . ' ' . ( defined( 'WOOSB_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'woo-product-bundle' ) . '</span>' : '' ); ?>
                        </div>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
                                <?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'woo-product-bundle' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WOOSB_REVIEWS ); ?>"
                                   target="_blank"><?php esc_html_e( 'Reviews', 'woo-product-bundle' ); ?></a> |
                                <a href="<?php echo esc_url( WOOSB_CHANGELOG ); ?>"
                                   target="_blank"><?php esc_html_e( 'Changelog', 'woo-product-bundle' ); ?></a> |
                                <a href="<?php echo esc_url( WOOSB_DISCUSSION ); ?>"
                                   target="_blank"><?php esc_html_e( 'Discussion', 'woo-product-bundle' ); ?></a>
                            </p>
                        </div>
                    </div>
                </div>
                <h2></h2>
                <?php if ( isset( $_GET['settings-updated'] ) && sanitize_text_field( wp_unslash( $_GET['settings-updated'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Settings updated.', 'woo-product-bundle' ); ?></p>
                    </div>
                <?php } ?>
                <div class="wpclever_settings_page_nav">
                    <h2 class="nav-tab-wrapper">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woosb&tab=how' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'how' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                            <?php esc_html_e( 'How to use?', 'woo-product-bundle' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woosb&tab=settings' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'settings' && $active_section === 'none' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                            <?php esc_html_e( 'Settings', 'woo-product-bundle' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woosb&tab=localization' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                            <?php esc_html_e( 'Localization', 'woo-product-bundle' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woosb&tab=settings&section=compatible' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'settings' && $active_section === 'compatible' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                            <?php esc_html_e( 'Compatible', 'woo-product-bundle' ); ?>
                        </a> <a href="<?php echo esc_url( WOOSB_DOCS ); ?>" class="nav-tab" target="_blank">
                            <?php esc_html_e( 'Docs', 'woo-product-bundle' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woosb&tab=premium' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>"
                           style="color: #c9356e">
                            <?php esc_html_e( 'Premium Version', 'woo-product-bundle' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
                            <?php esc_html_e( 'Essential Kit', 'woo-product-bundle' ); ?>
                        </a>
                    </h2>
                </div>
                <div class="<?php echo esc_attr( $settings_class ); ?>">
                    <?php if ( $active_tab === 'how' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>
                                <?php esc_html_e( 'When creating the product, please choose product data is "Smart Bundle" then you can see the search field to start search and add products to the bundle.', 'woo-product-bundle' ); ?>
                            </p>
                            <p>
                                <img src="<?php echo esc_url( WOOSB_URI . 'assets/images/how-01.jpg' ); ?>" alt=""/>
                            </p>
                        </div>
                    <?php } elseif ( $active_tab === 'settings' ) {
                        $price_format          = $this->helper->get_setting( 'price_format', 'from_min' );
                        $price_from            = $this->helper->get_setting( 'bundled_price_from', 'sale_price' );
                        $bundled_position      = $this->helper->get_setting( 'bundled_position', 'above' );
                        $layout                = $this->helper->get_setting( 'layout', 'list' );
                        $variations_selector   = $this->helper->get_setting( 'variations_selector', 'default' );
                        $selector_interface    = $this->helper->get_setting( 'selector_interface', 'unset' );
                        $bundled_thumb         = $this->helper->get_setting( 'bundled_thumb', 'yes' );
                        $bundled_qty           = $this->helper->get_setting( 'bundled_qty', 'yes' );
                        $bundled_desc          = $this->helper->get_setting( 'bundled_description', 'no' );
                        $bundled_price         = $this->helper->get_setting( 'bundled_price', 'price' );
                        $bundled_link          = $this->helper->get_setting( 'bundled_link', 'yes' );
                        $plus_minus            = $this->helper->get_setting( 'plus_minus', 'no' );
                        $change_image          = $this->helper->get_setting( 'change_image', 'yes' );
                        $change_price          = $this->helper->get_setting( 'change_price', 'yes' );
                        $bundles_position      = $this->helper->get_setting( 'bundles_position', 'no' );
                        $coupon_restrictions   = $this->helper->get_setting( 'coupon_restrictions', 'no' );
                        $exclude_unpurchasable = $this->helper->get_setting( 'exclude_unpurchasable', 'no' );
                        $contents_count        = $this->helper->get_setting( 'cart_contents_count', 'bundle' );
                        $hide_bundle_name      = $this->helper->get_setting( 'hide_bundle_name', 'no' );
                        $hide_bundled          = $this->helper->get_setting( 'hide_bundled', 'no' );
                        $hide_bundled_order    = $this->helper->get_setting( 'hide_bundled_order', 'no' );
                        $hide_bundled_mc       = $this->helper->get_setting( 'hide_bundled_mini_cart', 'no' );
                        $edit_link             = $this->helper->get_setting( 'edit_link', 'no' );
                        $wcpdf_hide_bundles    = $this->helper->get_setting( 'compatible_wcpdf_hide_bundles', 'no' );
                        $wcpdf_hide_bundled    = $this->helper->get_setting( 'compatible_wcpdf_hide_bundled', 'no' );
                        $pklist_hide_bundles   = $this->helper->get_setting( 'compatible_pklist_hide_bundles', 'no' );
                        $pklist_hide_bundled   = $this->helper->get_setting( 'compatible_pklist_hide_bundled', 'no' );
                        ?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr class="heading show_if_section_none">
                                    <th colspan="2">
                                        <?php esc_html_e( 'General', 'woo-product-bundle' ); ?>
                                    </th>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Price format', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[price_format]" class="woosb_price_format">
                                                <option value="from_min" <?php selected( $price_format, 'from_min' ); ?>>
                                                    <?php esc_html_e( 'From min price', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="min_only" <?php selected( $price_format, 'min_only' ); ?>>
                                                    <?php esc_html_e( 'Min price only', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="min_max" <?php selected( $price_format, 'min_max' ); ?>>
                                                    <?php esc_html_e( 'Min - max', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="normal" <?php selected( $price_format, 'normal' ); ?>>
                                                    <?php esc_html_e( 'Regular and sale price', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="custom" <?php selected( $price_format, 'custom' ); ?>>
                                                    <?php esc_html_e( 'Custom', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Choose the price format for bundle on the shop/archive page.', 'woo-product-bundle' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="woosb_tr_show_if_price_format_custom">
                                    <th><?php esc_html_e( 'Default custom display price', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woosb_settings[price_format_custom]"
                                                   placeholder="<?php /* translators: dynamic price */
                                                   esc_attr_e( 'before %s after', 'woo-product-bundle' ); ?>"
                                                   value="<?php /* translators: dynamic price */
                                                   echo $this->helper->get_setting( 'price_format_custom', esc_html__( 'before %s after', 'woo-product-bundle' ) ); ?>"/>
                                        </label>
                                        <p class="description">
                                            <?php /* translators: dynamic price */
                                            esc_html_e( 'Use %s to show the dynamic price between your custom text. You still can overwrite it in each bundle.', 'woo-product-bundle' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Calculate bundled prices', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[bundled_price_from]">
                                                <option value="sale_price" <?php selected( $price_from, 'sale_price' ); ?>>
                                                    <?php esc_html_e( 'from Sale price', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="regular_price" <?php selected( $price_from, 'regular_price' ); ?>>
                                                    <?php esc_html_e( 'from Regular price', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Bundled pricing methods: from Sale price (default) or Regular price.', 'woo-product-bundle' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="heading show_if_section_none">
                                    <th colspan="2">
                                        <?php esc_html_e( 'Bundled products', 'woo-product-bundle' ); ?>
                                    </th>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Position', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[bundled_position]">
                                                <option value="above" <?php selected( $bundled_position, 'above' ); ?>>
                                                    <?php esc_html_e( 'Above the add to cart button', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="below" <?php selected( $bundled_position, 'below' ); ?>>
                                                    <?php esc_html_e( 'Under the add to cart button', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="below_title" <?php selected( $bundled_position, 'below_title' ); ?>>
                                                    <?php esc_html_e( 'Under the title', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="below_price" <?php selected( $bundled_position, 'below_price' ); ?>>
                                                    <?php esc_html_e( 'Under the price', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="below_excerpt" <?php selected( $bundled_position, 'below_excerpt' ); ?>>
                                                    <?php esc_html_e( 'Under the excerpt', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="tab" <?php selected( $bundled_position, 'tab' ); ?>>
                                                    <?php esc_html_e( 'In a new tab', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $bundled_position, 'no' ); ?>>
                                                    <?php esc_html_e( 'None (hide it)', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Choose the position to show the bundled products list.', 'woo-product-bundle' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Layout', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[layout]">
                                                <option value="list" <?php selected( $layout, 'list' ); ?>>
                                                    <?php esc_html_e( 'List', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="grid-2" <?php selected( $layout, 'grid-2' ); ?>>
                                                    <?php esc_html_e( 'Grid - 2 columns', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="grid-3" <?php selected( $layout, 'grid-3' ); ?>>
                                                    <?php esc_html_e( 'Grid - 3 columns', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="grid-4" <?php selected( $layout, 'grid-4' ); ?>>
                                                    <?php esc_html_e( 'Grid - 4 columns', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Variations selector', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <select name="woosb_settings[variations_selector]"
                                                    class="woosb_variations_selector">
                                                <option value="default" <?php selected( $variations_selector, 'default' ); ?>>
                                                    <?php esc_html_e( 'Default', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="woovr" <?php selected( $variations_selector, 'woovr' ); ?>>
                                                    <?php esc_html_e( 'Use WPC Variations Radio Buttons', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select>
                                        </label>
                                        <p class="woosb-notice">
                                            WPC Variations Radio Buttons is recommended if you encounter errors with the
                                            variation swatches you are using, or especially when products have many
                                            variations. Install
                                            <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-variations-radio-buttons&TB_iframe=true&width=800&height=550' ) ); ?>"
                                               class="thickbox" title="WPC Variations Radio Buttons">WPC Variations
                                                Radio Buttons</a> to make it work.
                                        </p>
                                        <div class="woosb_show_if_woovr" style="margin-top: 10px">
                                            <?php esc_html_e( 'Selector interface', 'woo-product-bundle' ); ?>
                                            <label> <select name="woosb_settings[selector_interface]">
                                                    <option value="unset" <?php selected( $selector_interface, 'unset' ); ?>>
                                                        <?php esc_html_e( 'Unset', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option value="ddslick" <?php selected( $selector_interface, 'ddslick' ); ?>>
                                                        <?php esc_html_e( 'ddSlick', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option value="select2" <?php selected( $selector_interface, 'select2' ); ?>>
                                                        <?php esc_html_e( 'Select2', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option value="default" <?php selected( $selector_interface, 'default' ); ?>>
                                                        <?php esc_html_e( 'Radio buttons', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option value="select" <?php selected( $selector_interface, 'select' ); ?>>
                                                        <?php esc_html_e( 'HTML select tag', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option value="grid-2" <?php selected( $selector_interface, 'grid-2' ); ?>>
                                                        <?php esc_html_e( 'Grid - 2 columns', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option value="grid-3" <?php selected( $selector_interface, 'grid-3' ); ?>>
                                                        <?php esc_html_e( 'Grid - 3 columns', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option value="grid-4" <?php selected( $selector_interface, 'grid-4' ); ?>>
                                                        <?php esc_html_e( 'Grid - 4 columns', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select> </label>
                                            <p class="description">
                                                <?php esc_html_e( 'Choose a selector interface that apply for variations of bundled products only.', 'woo-product-bundle' ); ?>
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Show thumbnail', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[bundled_thumb]">
                                                <option value="yes" <?php selected( $bundled_thumb, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $bundled_thumb, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Show quantity', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[bundled_qty]">
                                                <option value="yes" <?php selected( $bundled_qty, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $bundled_qty, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Show the quantity number before product name.', 'woo-product-bundle' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Show short description', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[bundled_description]">
                                                <option value="yes" <?php selected( $bundled_desc, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $bundled_desc, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Show price', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[bundled_price]">
                                                <option value="price" <?php selected( $bundled_price, 'price' ); ?>>
                                                    <?php esc_html_e( 'Price at the last', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="subtotal" <?php selected( $bundled_price, 'subtotal' ); ?>>
                                                    <?php esc_html_e( 'Subtotal at the last', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="price_under_name" <?php selected( $bundled_price, 'price_under_name' ); ?>><?php esc_html_e( 'Price under the product name', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="subtotal_under_name" <?php selected( $bundled_price, 'subtotal_under_name' ); ?>>
                                                    <?php esc_html_e( 'Subtotal under the product name', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $bundled_price, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Link to individual product', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[bundled_link]">
                                                <option value="yes" <?php selected( $bundled_link, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes, open in the same tab', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="yes_blank" <?php selected( $bundled_link, 'yes_blank' ); ?>>
                                                    <?php esc_html_e( 'Yes, open in the new tab', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="yes_popup" <?php selected( $bundled_link, 'yes_popup' ); ?>>
                                                    <?php esc_html_e( 'Yes, open quick view popup', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $bundled_link, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                        <p class="description">If you choose "Open quick view popup", please install
                                            <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>"
                                               class="thickbox" title="WPC Smart Quick View">WPC Smart Quick View</a> to
                                            make it work.
                                        </p>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Show plus/minus button', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[plus_minus]">
                                                <option value="yes" <?php selected( $plus_minus, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $plus_minus, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Show the plus/minus button for the quantity input.', 'woo-product-bundle' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Change image', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[change_image]">
                                                <option value="yes" <?php selected( $change_image, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $change_image, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Change the main product image when choosing the variation of bundled products.', 'woo-product-bundle' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Change price', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[change_price]" class="woosb_change_price">
                                                <option value="yes" <?php selected( $change_price, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="yes_custom" <?php selected( $change_price, 'yes_custom' ); ?>>
                                                    <?php esc_html_e( 'Yes, custom selector', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $change_price, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label> <label>
                                            <input type="text" name="woosb_settings[change_price_custom]"
                                                   value="<?php echo $this->helper->get_setting( 'change_price_custom', '.summary > .price' ); ?>"
                                                   placeholder=".summary > .price" class="woosb_change_price_custom"/>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e( 'Change the main product price when choosing the variation of bundled products. It uses JavaScript to change product price so it is very dependent on theme’s HTML. If it cannot find and update the product price, please contact us and we can help you find the right selector or adjust the JS file.', 'woo-product-bundle' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr class="heading show_if_section_none">
                                    <th>
                                        <?php esc_html_e( 'Bundles', 'woo-product-bundle' ); ?>
                                    </th>
                                    <td>
                                        <?php esc_html_e( 'Settings for bundles on the bundled product page.', 'woo-product-bundle' ); ?>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Position', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[bundles_position]">
                                                <option value="above" <?php selected( $bundles_position, 'above' ); ?>>
                                                    <?php esc_html_e( 'Above the add to cart button', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="below" <?php selected( $bundles_position, 'below' ); ?>>
                                                    <?php esc_html_e( 'Under the add to cart button', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="tab" <?php selected( $bundles_position, 'tab' ); ?>>
                                                    <?php esc_html_e( 'In a new tab', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $bundles_position, 'no' ); ?>>
                                                    <?php esc_html_e( 'None (hide it)', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Choose the position to show the bundles list.', 'woo-product-bundle' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="heading show_if_section_none">
                                    <th colspan="2">
                                        <?php esc_html_e( 'Cart & Checkout', 'woo-product-bundle' ); ?>
                                    </th>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Coupon restrictions', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[coupon_restrictions]">
                                                <option value="no" <?php selected( $coupon_restrictions, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="bundles" <?php selected( $coupon_restrictions, 'bundles' ); ?>>
                                                    <?php esc_html_e( 'Exclude bundles', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="bundled" <?php selected( $coupon_restrictions, 'bundled' ); ?>>
                                                    <?php esc_html_e( 'Exclude bundled products', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="both" <?php selected( $coupon_restrictions, 'both' ); ?>>
                                                    <?php esc_html_e( 'Exclude both bundles and bundled products', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Choose products you want to exclude from coupons.', 'woo-product-bundle' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Exclude un-purchasable products', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[exclude_unpurchasable]">
                                                <option value="yes" <?php selected( $exclude_unpurchasable, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $exclude_unpurchasable, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                        <p class="description">
                                            <?php esc_html_e( 'Make the bundle still purchasable when one of the bundled products is un-purchasable. These bundled products are excluded from the orders.', 'woo-product-bundle' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Cart contents count', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[cart_contents_count]">
                                                <option value="bundle" <?php selected( $contents_count, 'bundle' ); ?>>
                                                    <?php esc_html_e( 'Bundles only', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="bundled_products" <?php selected( $contents_count, 'bundled_products' ); ?>><?php esc_html_e( 'Bundled products only', 'woo-product-bundle' ); ?></option>
                                                <option value="both" <?php selected( $contents_count, 'both' ); ?>>
                                                    <?php esc_html_e( 'Both bundles and bundled products', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Hide bundle name before bundled products', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[hide_bundle_name]">
                                                <option value="yes" <?php selected( $hide_bundle_name, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $hide_bundle_name, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Hide bundled products on mini-cart', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[hide_bundled_mini_cart]">
                                                <option value="yes" <?php selected( $hide_bundled_mc, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $hide_bundled_mc, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Hide bundled products, just show the main product on mini-cart.', 'woo-product-bundle' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Hide bundled products on cart & checkout page', 'woo-product-bundle' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="woosb_settings[hide_bundled]">
                                                <option value="yes" <?php selected( $hide_bundled, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes, just show the main bundle', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="yes_text" <?php selected( $hide_bundled, 'yes_text' ); ?>>
                                                    <?php esc_html_e( 'Yes, but shortly list bundled sub-product names under the main bundle in one line', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="yes_list" <?php selected( $hide_bundled, 'yes_list' ); ?>>
                                                    <?php esc_html_e( 'Yes, but list bundled sub-product names under the main bundle in separate lines', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $hide_bundled, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="show_if_section_none">
                                    <th><?php esc_html_e( 'Hide bundled products on order details', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[hide_bundled_order]">
                                                <option value="yes" <?php selected( $hide_bundled_order, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes, just show the main bundle', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="yes_text" <?php selected( $hide_bundled_order, 'yes_text' ); ?>>
                                                    <?php esc_html_e( 'Yes, but shortly list bundled sub-product names under the main bundle in one line', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="yes_list" <?php selected( $hide_bundled_order, 'yes_list' ); ?>>
                                                    <?php esc_html_e( 'Yes, but list bundled sub-product names under the main bundle in separate lines', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $hide_bundled_order, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                        <p class="description">
                                            <?php esc_html_e( 'Hide bundled products, just show the main product on order details (order confirmation or emails).', 'woo-product-bundle' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Edit link (Beta)', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[edit_link]">
                                                <option value="yes" <?php selected( $edit_link, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $edit_link, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label> <span
                                                class="description"><?php esc_html_e( 'Enable the edit link for product bundles on the cart page.', 'woo-product-bundle' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="heading show_if_section_none">
                                    <th colspan="2">
                                        <?php esc_html_e( 'Search', 'woo-product-bundle' ); ?>
                                    </th>
                                </tr>
                                <?php $this->search_settings(); ?>
                                <tr class="heading show_if_section_compatible">
                                    <th colspan="2">
                                        <?php esc_html_e( 'WooCommerce PDF Invoices & Packing Slips', 'woo-product-bundle' ); ?>
                                        <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/"
                                           target="_blank"><span class="dashicons dashicons-external"></span></a>
                                    </th>
                                </tr>
                                <tr class="show_if_section_compatible">
                                    <th><?php esc_html_e( 'Hide bundles', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[compatible_wcpdf_hide_bundles]">
                                                <option value="yes" <?php selected( $wcpdf_hide_bundles, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $wcpdf_hide_bundles, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="show_if_section_compatible">
                                    <th><?php esc_html_e( 'Hide bundled products', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[compatible_wcpdf_hide_bundled]">
                                                <option value="yes" <?php selected( $wcpdf_hide_bundled, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $wcpdf_hide_bundled, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="heading show_if_section_compatible">
                                    <th colspan="2">
                                        <?php esc_html_e( 'WooCommerce PDF Invoices, Packing Slips, Delivery Notes & Shipping Labels', 'woo-product-bundle' ); ?>
                                        <a href="https://wordpress.org/plugins/print-invoices-packing-slip-labels-for-woocommerce/"
                                           target="_blank"><span class="dashicons dashicons-external"></span></a>
                                    </th>
                                </tr>
                                <tr class="show_if_section_compatible">
                                    <th><?php esc_html_e( 'Hide bundles', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[compatible_pklist_hide_bundles]">
                                                <option value="yes" <?php selected( $pklist_hide_bundles, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $pklist_hide_bundles, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="show_if_section_compatible">
                                    <th><?php esc_html_e( 'Hide bundled products', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label> <select name="woosb_settings[compatible_pklist_hide_bundled]">
                                                <option value="yes" <?php selected( $pklist_hide_bundled, 'yes' ); ?>>
                                                    <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                </option>
                                                <option value="no" <?php selected( $pklist_hide_bundled, 'no' ); ?>>
                                                    <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                </option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="submit show_if_section_all">
                                    <th colspan="2">
                                        <div class="wpclever_submit">
                                            <?php
                                            settings_fields( 'woosb_settings' );
                                            submit_button( '', 'primary', 'submit', false );

                                            if ( function_exists( 'wpc_last_saved' ) ) {
                                                wpc_last_saved( $this->helper->get_settings() );
                                            }
                                            ?>
                                        </div>
                                        <a style="display: none;" class="wpclever_export" data-key="woosb_settings"
                                           data-name="settings"
                                           href="#"><?php esc_html_e( 'import / export', 'woo-product-bundle' ); ?></a>
                                    </th>
                                </tr>
                            </table>
                        </form>
                    <?php } elseif ( $active_tab === 'localization' ) { ?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr class="heading">
                                    <th scope="row"><?php esc_html_e( 'General', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'woo-product-bundle' ); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Total text', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[total]" class="regular-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'total' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Bundle price:', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Selected text', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[selected]" class="regular-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'selected' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Selected:', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Saved text', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[saved]" class="regular-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'saved' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( '(saved [d])', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                        <span
                                                class="description"><?php esc_html_e( 'Use [d] to show the saved percentage or amount.', 'woo-product-bundle' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Choose an attribute', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[choose]" class="regular-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'choose' ) ); ?>"
                                                   placeholder="<?php /* translators: %s is the attribute name */
                                                   esc_attr_e( 'Choose %s', 'woo-product-bundle' ); ?>"/> </label>
                                        <span class="description"><?php /* translators: attribute name */
                                            esc_html_e( 'Use %s to show the attribute name.', 'woo-product-bundle' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Clear', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[clear]" class="regular-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'clear' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Clear', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
                                        <?php esc_html_e( '"Add to cart" button labels', 'woo-product-bundle' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Shop/archive page', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <div style="margin-bottom: 5px">
                                            <label>
                                                <input type="text" class="regular-text"
                                                       name="woosb_localization[button_add]"
                                                       value="<?php echo esc_attr( $this->helper->localization( 'button_add' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Add to cart', 'woo-product-bundle' ); ?>"/>
                                            </label>
                                            <span
                                                    class="description"><?php esc_html_e( 'For purchasable bundle.', 'woo-product-bundle' ); ?></span>
                                        </div>
                                        <div style="margin-bottom: 5px">
                                            <label>
                                                <input type="text" class="regular-text"
                                                       name="woosb_localization[button_select]"
                                                       value="<?php echo esc_attr( $this->helper->localization( 'button_select' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Select options', 'woo-product-bundle' ); ?>"/>
                                            </label>
                                            <span
                                                    class="description"><?php esc_html_e( 'For purchasable bundle and has variable product(s).', 'woo-product-bundle' ); ?></span>
                                        </div>
                                        <div>
                                            <label>
                                                <input type="text" class="regular-text"
                                                       name="woosb_localization[button_read]"
                                                       value="<?php echo esc_attr( $this->helper->localization( 'button_read' ) ); ?>"
                                                       placeholder="<?php esc_attr_e( 'Read more', 'woo-product-bundle' ); ?>"/>
                                            </label>
                                            <span
                                                    class="description"><?php esc_html_e( 'For un-purchasable bundle.', 'woo-product-bundle' ); ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Single product page', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[button_single]"
                                                   class="regular-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'button_single' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Add to cart', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
                                        <?php esc_html_e( 'Cart & Checkout', 'woo-product-bundle' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Bundles', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[bundles]" class="large-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'bundles' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Bundles', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Bundled products', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[bundled_products]"
                                                   class="large-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'bundled_products' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Bundled products', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php /* translators: %s is the product list */
                                        esc_html_e( 'Bundled products: %s', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[bundled_products_s]"
                                                   class="large-text" value="<?php /* translators: %s is the product list */
                                            echo esc_attr( $this->helper->localization( 'bundled_products_s' ) ); ?>"
                                                   placeholder="<?php /* translators: %s is the product list */
                                                   esc_attr_e( 'Bundled products: %s', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php /* translators: %s is the parent bundle name */
                                        esc_html_e( 'Bundled in: %s', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[bundled_in_s]"
                                                   class="large-text" value="<?php /* translators: %s is the parent bundle name */
                                            echo esc_attr( $this->helper->localization( 'bundled_in_s' ) ); ?>"
                                                   placeholder="<?php /* translators: %s is the parent bundle name */
                                                   esc_attr_e( 'Bundled in: %s', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Edit', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[cart_item_edit]"
                                                   class="large-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'cart_item_edit' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Edit', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Update', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[cart_item_update]"
                                                   class="large-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'cart_item_update' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Update', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
                                        <?php esc_html_e( 'Alert', 'woo-product-bundle' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Require selection', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[alert_selection]"
                                                   class="large-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'alert_selection' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Please select a purchasable variation for [name] before adding this bundle to the cart.', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Require purchasable', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[alert_unpurchasable]"
                                                   class="large-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'alert_unpurchasable' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Product [name] is unpurchasable. Please remove it before adding the bundle to the cart.', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Enforce a selection', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[alert_empty]" class="large-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'alert_empty' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Please choose at least one product before adding this bundle to the cart.', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Minimum required', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[alert_min]" class="large-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'alert_min' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Please choose at least a total quantity of [min] products before adding this bundle to the cart.', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Maximum reached', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[alert_max]" class="large-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'alert_max' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'Sorry, you can only choose at max a total quantity of [max] products before adding this bundle to the cart.', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Total minimum required', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[alert_total_min]"
                                                   class="large-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'alert_total_min' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'The total must meet the minimum amount of [min].', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Total maximum required', 'woo-product-bundle' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="woosb_localization[alert_total_max]"
                                                   class="large-text"
                                                   value="<?php echo esc_attr( $this->helper->localization( 'alert_total_max' ) ); ?>"
                                                   placeholder="<?php esc_attr_e( 'The total must meet the maximum amount of [max].', 'woo-product-bundle' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
                                        <div class="wpclever_submit">
                                            <?php
                                            settings_fields( 'woosb_localization' );
                                            submit_button( '', 'primary', 'submit', false );

                                            if ( function_exists( 'wpc_last_saved' ) ) {
                                                wpc_last_saved( get_option( 'woosb_localization', [] ) );
                                            }
                                            ?>
                                        </div>
                                        <a style="display: none;" class="wpclever_export" data-key="woosb_localization"
                                           data-name="settings"
                                           href="#"><?php esc_html_e( 'import / export', 'woo-product-bundle' ); ?></a>
                                    </th>
                                </tr>
                            </table>
                        </form>
                    <?php } elseif ( $active_tab === 'tools' ) { ?>
                        <table class="form-table">
                            <tr class="heading">
                                <th scope="row"><?php esc_html_e( 'Data Migration', 'woo-product-bundle' ); ?></th>
                                <td>
                                    <?php esc_html_e( 'If you have updated WPC Product Bundles from a version before 7.0.0, please run the Migrate tool once.', 'woo-product-bundle' ); ?>
                                    <?php
                                    echo '<p>';
                                    $num   = absint( wp_unslash( $_GET['num'] ?? 50 ) );
                                    $paged = absint( wp_unslash( $_GET['paged'] ?? 1 ) );

                                    if ( isset( $_GET['act'] ) && ( $_GET['act'] === 'migrate' ) ) {
                                        $args = [
                                                'post_type'      => 'product',
                                                'posts_per_page' => $num,
                                                'paged'          => $paged,
                                                'meta_query'     => [
                                                        [
                                                                'key'     => 'woosb_ids',
                                                                'compare' => 'EXISTS'
                                                        ]
                                                ]
                                        ];

                                        $posts = get_posts( $args );

                                        if ( ! empty( $posts ) ) {
                                            foreach ( $posts as $post ) {
                                                $_product = wc_get_product( $post->ID );
                                                $ids      = $_product ? $_product->get_meta( 'woosb_ids' ) : '';

                                                if ( ! empty( $ids ) && is_string( $ids ) ) {
                                                    $items     = explode( ',', $ids );
                                                    $new_items = [];

                                                    foreach ( $items as $item ) {
                                                        $item_data = explode( '/', $item );
                                                        $item_key  = $this->helper->generate_key();
                                                        $item_id   = absint( $item_data[0] ?? 0 );

                                                        if ( $item_product = wc_get_product( $item_id ) ) {
                                                            $item_sku = $item_product->get_sku();
                                                            $item_qty = (float) ( $item_data[1] ?? 1 );

                                                            $new_items[ $item_key ] = [
                                                                    'id'  => $item_id,
                                                                    'sku' => $item_sku,
                                                                    'qty' => $item_qty,
                                                            ];
                                                        }
                                                    }

                                                    update_post_meta( $post->ID, 'woosb_ids', $new_items );
                                                }
                                            }

                                            echo '<span style="color: #2271b1; font-weight: 700">' . esc_html__( 'Migrating...', 'woo-product-bundle' ) . '</span>';
                                            echo '<p class="description">' . esc_html__( 'Please wait until it has finished!', 'woo-product-bundle' ) . '</p>';
                                            ?>
                                            <script type="text/javascript">
                                                (function ($) {
                                                    $(function () {
                                                        setTimeout(function () {
                                                            window.location.href = '<?php echo esc_url_raw( admin_url( 'admin.php?page=wpclever-woosb&tab=tools&act=migrate&num=' . $num . '&paged=' . ( $paged + 1 ) ) ); ?>';
                                                        }, 1000);
                                                    });
                                                })(jQuery);
                                            </script>
                                        <?php } else {
                                            echo '<span style="color: #2271b1; font-weight: 700">' . esc_html__( 'Finished!', 'woo-product-bundle' ) . '</span>';
                                        }
                                    } else {
                                        echo '<a class="button btn" href="' . esc_url( admin_url( 'admin.php?page=wpclever-woosb&tab=tools&act=migrate' ) ) . '">' . esc_html__( 'Migrate', 'woo-product-bundle' ) . '</a>';
                                    }
                                    echo '</p>';
                                    ?>
                                </td>
                            </tr>
                        </table>
                    <?php } elseif ( $active_tab === 'premium' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>
                                Get the Premium Version just $29!
                                <a href="https://wpclever.net/downloads/product-bundles/?utm_source=pro&utm_medium=woosb&utm_campaign=wporg"
                                   target="_blank">https://wpclever.net/downloads/product-bundles/</a>
                            </p>
                            <p><strong>Extra features for Premium Version:</strong></p>
                            <ul style="margin-bottom: 0">
                                <li>- Add a variable product or a specific variation to a bundle.</li>
                                <li>- Insert heading/paragraph into a bundled products list.</li>
                                <li>- Get the lifetime update & premium support.</li>
                            </ul>
                        </div>
                    <?php } ?>
                </div><!-- /.wpclever_settings_page_content -->
                <div class="wpclever_settings_page_suggestion">
                    <div class="wpclever_settings_page_suggestion_label">
                        <span class="dashicons dashicons-yes-alt"></span> Suggestion
                    </div>
                    <div class="wpclever_settings_page_suggestion_content">
                        <div>
                            To display custom engaging real-time messages on any wished positions, please install
                            <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart
                                Messages</a> plugin. It's free!
                        </div>
                        <div>
                            Wanna save your precious time working on variations? Try our brand-new free plugin
                            <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC
                                Variation Bulk Editor</a> and
                            <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC
                                Variation Duplicator</a>.
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        function ajax_update_search_settings() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woosb-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

            $settings                      = (array) get_option( 'woosb_settings', [] );
            $settings['search_limit']      = (int) sanitize_text_field( wp_unslash( $_POST['limit'] ?? '' ) );
            $settings['search_sku']        = sanitize_text_field( wp_unslash( $_POST['sku'] ?? '' ) );
            $settings['search_id']         = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
            $settings['search_exact']      = sanitize_text_field( wp_unslash( $_POST['exact'] ?? '' ) );
            $settings['search_sentence']   = sanitize_text_field( wp_unslash( $_POST['sentence'] ?? '' ) );
            $settings['search_same']       = sanitize_text_field( wp_unslash( $_POST['same'] ?? '' ) );
            $settings['search_show_image'] = sanitize_text_field( wp_unslash( $_POST['show_image'] ?? '' ) );
            $settings['search_types']      = array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['types'] ?? [] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

            update_option( 'woosb_settings', $settings );
            wp_die();
        }

        function ajax_get_search_results() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woosb-security' ) ) {
                die( 'Permissions check failed!' );
            }

            $keyword   = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );
            $added_ids = explode( ',', $this->helper->clean_ids( wp_unslash( $_POST['ids'] ?? '' ) ) );
            $types     = $this->helper->get_setting( 'search_types', [ 'all' ] );

            if ( ( $this->helper->get_setting( 'search_id', 'no' ) === 'yes' ) && is_numeric( $keyword ) ) {
                // search by id
                $query_args = [
                        'p'         => absint( $keyword ),
                        'post_type' => 'product'
                ];
            } else {
                $limit = absint( $this->helper->get_setting( 'search_limit', 10 ) );

                if ( $limit < 1 ) {
                    $limit = 10;
                } elseif ( $limit > 500 ) {
                    $limit = 500;
                }

                $query_args = [
                        's'              => $keyword,
                        'is_woosb'       => true,
                        'post_type'      => 'product',
                        'post_status'    => [ 'publish', 'private' ],
                        'posts_per_page' => $limit
                ];

                if ( ! empty( $types ) && ! in_array( 'all', $types, true ) ) {
                    $product_types = $types;

                    if ( in_array( 'variation', $types, true ) ) {
                        $product_types[] = 'variable';
                    }

                    $query_args['tax_query'] = [
                            [
                                    'taxonomy' => 'product_type',
                                    'field'    => 'slug',
                                    'terms'    => $product_types,
                            ],
                    ];
                }

                if ( $this->helper->get_setting( 'search_same', 'no' ) !== 'yes' ) {
                    $query_args['post__not_in'] = array_map( 'absint', $added_ids );
                }
            }

            $query = new WP_Query( $query_args );

            if ( $query->have_posts() ) {
                echo '<div class="woosb-ul">';

                while ( $query->have_posts() ) {
                    $query->the_post();
                    $_product = wc_get_product( get_the_ID() );

                    if ( ! $_product || ! current_user_can( 'read_product', $_product->get_id() ) ) {
                        continue;
                    }

                    if ( ! $_product->is_type( 'variable' ) || in_array( 'variable', $types, true ) || in_array( 'all', $types, true ) ) {
                        $this->product_data_li( $_product, [ 'qty' => 1 ], '', true );
                    }

                    if ( $_product->is_type( 'variable' ) && ( empty( $types ) || in_array( 'all', $types, true ) || in_array( 'variation', $types, true ) ) ) {
                        // show all children
                        $children = $_product->get_children();

                        if ( is_array( $children ) && count( $children ) > 0 ) {
                            foreach ( $children as $child ) {
                                $child_product = wc_get_product( $child );
                                $this->product_data_li( $child_product, [ 'qty' => 1 ], '', true );
                            }
                        }
                    }
                }

                echo '</div>';
                wp_reset_postdata();
            } else {
                echo '<div class="woosb-ul"><span>' . /* translators: keyword */ sprintf( esc_html__( 'No results found for "%s"', 'woo-product-bundle' ), esc_html( $keyword ) ) . '</span></div>';
            }

            wp_die();
        }

        function product_type_selector( $types ) {
            $types['woosb'] = esc_html__( 'Smart bundle', 'woo-product-bundle' );

            return $types;
        }

        function product_data_tabs( $tabs ) {
            $tabs['woosb'] = [
                    'label'  => esc_html__( 'Bundled Products', 'woo-product-bundle' ),
                    'target' => 'woosb_settings',
                    'class'  => [ 'show_if_woosb' ],
            ];

            return $tabs;
        }

        function product_data_panels() {
            global $post, $thepostid, $product_object;

            if ( $product_object instanceof WC_Product ) {
                $product = $product_object;
            } else {
                $product = wc_get_product( $thepostid ?: ( $post->ID ?? 0 ) );
            }

            if ( ! $product ) {
                return;
            }

            $product_id = $product->get_id();

            if ( ! $product_id ) {
                ?>
                <div id='woosb_settings' class='panel woocommerce_options_panel woosb_table'>
                    <p style="padding: 0 12px; color: #c9356e"><?php esc_html_e( 'Product wasn\'t returned.', 'woo-product-bundle' ); ?>
                    </p>
                </div>
                <?php
                return;
            }

            if ( $product->get_meta( 'woosb_ids' ) ) {
                $ids = $product->get_meta( 'woosb_ids' );
            } elseif ( isset( $_GET['woosb_ids'] ) ) {
                $ids = implode( ',', explode( '.', sanitize_text_field( wp_unslash( $_GET['woosb_ids'] ?? '' ) ) ) );
            } else {
                $ids = '';
            }

            if ( ! empty( $_GET['woosb_ids'] ) ) {
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function ($) {
                        $('#product-type').val('woosb').trigger('change');
                    });
                </script>
                <?php
            }
            ?>
            <div id='woosb_settings' class='panel woocommerce_options_panel woosb_table'>
                <div id="woosb_search_settings" style="display: none"
                     data-title="<?php esc_html_e( 'Search settings', 'woo-product-bundle' ); ?>">
                    <table>
                        <?php $this->search_settings(); ?>
                        <tr>
                            <th></th>
                            <td>
                                <button id="woosb_search_settings_update" class="button button-primary">
                                    <?php esc_html_e( 'Update Options', 'woo-product-bundle' ); ?>
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
                <table>
                    <tr>
                        <th><?php esc_html_e( 'Search', 'woo-product-bundle' ); ?> (<a
                                    href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woosb&tab=settings#search' ) ); ?>"
                                    id="woosb_search_settings_btn"><?php esc_html_e( 'settings', 'woo-product-bundle' ); ?></a>)
                        </th>
                        <td>
                            <div class="w100">
								<span class="loading" id="woosb_loading"
                                      style="display: none;"><?php esc_html_e( 'searching...', 'woo-product-bundle' ); ?></span>
                                <label for="woosb_keyword"></label><input type="search" id="woosb_keyword"
                                                                          placeholder="<?php esc_attr_e( 'Type any keyword to search', 'woo-product-bundle' ); ?>"/>
                                <div id="woosb_results" class="woosb_results" style="display: none;"></div>
                            </div>
                        </td>
                    </tr>
                    <tr class="woosb_tr_space">
                        <th>
                            <?php esc_html_e( 'Selected', 'woo-product-bundle' ); ?>
                            <div class="woosb_tools">
                                <a href="#"
                                   class="woosb-import-export"><?php esc_html_e( 'import/export', 'woo-product-bundle' ); ?></a>
                            </div>
                        </th>
                        <td>
                            <div id="woosb_selected" class="woosb_selected">
                                <div class="woosb-ul">
                                    <?php
                                    if ( ! empty( $ids ) ) {
                                        $items = $this->helper->get_bundled( $ids, $product_id );

                                        if ( ! empty( $items ) ) {
                                            foreach ( $items as $key => $item ) {
                                                if ( ! empty( $item['id'] ) ) {
                                                    if ( apply_filters( 'woosb_use_sku', false ) && ! empty( $item['sku'] ) ) {
                                                        if ( $new_id = $this->helper->get_product_id_from_sku( $item['sku'] ) ) {
                                                            $item['id'] = $new_id;
                                                        }
                                                    }

                                                    $_product = wc_get_product( $item['id'] );

                                                    if ( ! $_product || in_array( $_product->get_type(), $this->helper::get_types(), true ) ) {
                                                        continue;
                                                    }

                                                    $this->product_data_li( $_product, $item, $key );
                                                } else {
                                                    // new version 7.0
                                                    $this->text_data_li( $item, $key );
                                                }
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr class="woosb_tr_space">
                        <th></th>
                        <td>
                            <div id="woosb_results_dummy" style="display: none;"></div>
                            <div class="woosb-actions">
                                <a href="https://wpclever.net/downloads/product-bundles/?utm_source=pro&utm_medium=woosb&utm_campaign=wporg"
                                   target="_blank" class="woosb_add_txt button"
                                   onclick="return confirm('This feature only available in Premium Version!\nBuy it now? Just $29')">
                                    <?php esc_html_e( '+ Add heading/paragraph', 'woo-product-bundle' ); ?>
                                </a>
                                <label for="woosb_bulk_actions"></label><select id="woosb_bulk_actions">
                                    <option value="none"><?php esc_html_e( 'Bulk actions', 'woo-product-bundle' ); ?></option>
                                    <option value="enable_optional">
                                        <?php esc_html_e( 'Enable "Custom quantity"', 'woo-product-bundle' ); ?>
                                    </option>
                                    <option value="disable_optional">
                                        <?php esc_html_e( 'Disable "Custom quantity"', 'woo-product-bundle' ); ?>
                                    </option>
                                    <option value="set_qty_default">
                                        <?php esc_html_e( 'Set default quantity', 'woo-product-bundle' ); ?>
                                    </option>
                                    <option value="set_qty_min"><?php esc_html_e( 'Set min quantity', 'woo-product-bundle' ); ?>
                                    </option>
                                    <option value="set_qty_max"><?php esc_html_e( 'Set max quantity', 'woo-product-bundle' ); ?>
                                    </option>
                                </select>
                            </div>
                        </td>
                    </tr>
                    <tr class="woosb_tr_space">
                        <th><?php echo esc_html__( 'Regular price', 'woo-product-bundle' ) . ' (' . esc_html( get_woocommerce_currency_symbol() ) . ')'; ?>
                        </th>
                        <td>
                            <span id="woosb_regular_price"></span>
                        </td>
                    </tr>
                    <tr class="woosb_tr_space">
                        <th><?php esc_html_e( 'Fixed price', 'woo-product-bundle' ); ?></th>
                        <td>
                            <?php $disable_auto_price = $product->get_meta( 'woosb_disable_auto_price' ) ?: apply_filters( 'woosb_disable_auto_price_default', 'off' ); ?>
                            <input id="woosb_disable_auto_price" name="woosb_disable_auto_price"
                                   type="checkbox" <?php echo esc_attr( $disable_auto_price === 'on' ? 'checked' : '' ); ?> />
                            <label
                                    for="woosb_disable_auto_price"><?php esc_html_e( 'Disable auto calculate price.', 'woo-product-bundle' ); ?></label>
                            <label><?php /* translators: set price link */
                                echo sprintf( esc_html__( 'If checked, %1$s click here to set price %2$s by manually.', 'woo-product-bundle' ), '<a id="woosb_set_regular_price">', '</a>' ); ?></label>
                        </td>
                    </tr>
                    <tr class="woosb_tr_space woosb_tr_show_if_auto_price">
                        <th><?php esc_html_e( 'Discount', 'woo-product-bundle' ); ?></th>
                        <td style="vertical-align: middle; line-height: 30px;">
                            <label for="woosb_discount"></label><input id="woosb_discount" name="woosb_discount"
                                                                       type="number"
                                                                       min="0" step="0.0001" max="99.9999"
                                                                       style="width: 80px"
                                                                       value="<?php echo esc_attr( $product->get_meta( 'woosb_discount' ) ); ?>"/>
                            <?php esc_html_e( '% or amount', 'woo-product-bundle' ); ?>
                            <label for="woosb_discount_amount"></label><input id="woosb_discount_amount"
                                                                              name="woosb_discount_amount" type="number"
                                                                              min="0" step="0.0001" style="width: 80px"
                                                                              value="<?php echo esc_attr( $product->get_meta( 'woosb_discount_amount' ) ); ?>"/>
                            <?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
                            . <?php esc_html_e( 'If you fill both, the amount will be used.', 'woo-product-bundle' ); ?>
                        </td>
                    </tr>
                    <tr class="woosb_tr_space">
                        <th><?php esc_html_e( 'Quantity limits', 'woo-product-bundle' ); ?></th>
                        <td>
                            <?php esc_html_e( 'Min', 'woo-product-bundle' ); ?>
                            <label>
                                <input name="woosb_limit_whole_min" type="number" min="1"
                                       style="width: 60px; float: none"
                                       value="<?php echo esc_attr( $product->get_meta( 'woosb_limit_whole_min' ) ); ?>"/>
                            </label>
                            <?php esc_html_e( 'Max', 'woo-product-bundle' ); ?>
                            <label>
                                <input name="woosb_limit_whole_max" type="number" min="1"
                                       style="width: 60px; float: none"
                                       value="<?php echo esc_attr( $product->get_meta( 'woosb_limit_whole_max' ) ); ?>"/>
                            </label>
                            <span class="woocommerce-help-tip"
                                  data-tip="<?php esc_attr_e( 'Limit the total quantity when the bundle includes optional products.', 'woo-product-bundle' ); ?>"></span>
                        </td>
                    </tr>
                    <tr class="woosb_tr_space">
                        <th><?php esc_html_e( 'Total limits', 'woo-product-bundle' ); ?></th>
                        <td>
                            <input id="woosb_total_limits" name="woosb_total_limits"
                                   type="checkbox" <?php echo esc_attr( $product->get_meta( 'woosb_total_limits' ) === 'on' ? 'checked' : '' ); ?> />
                            <label
                                    for="woosb_total_limits"><?php esc_html_e( 'Configure total limits for the current bundle.', 'woo-product-bundle' ); ?></label>
                            <span class="woocommerce-help-tip"
                                  data-tip="<?php esc_attr_e( 'When a bundle includes variable or optional products, bundle\'s price will vary depending on the item selection. Thus, this option can be enabled to limit the bundle total\'s min-max.', 'woo-product-bundle' ); ?>"></span>
                            <span class="woosb_show_if_total_limits">
								<?php esc_html_e( 'Min', 'woo-product-bundle' ); ?> <label
                                        for="woosb_total_limits_min"></label><input id="woosb_total_limits_min"
                                                                                    name="woosb_total_limits_min"
                                                                                    type="number" min="0"
                                                                                    style="width: 80px"
                                                                                    value="<?php echo esc_attr( $product->get_meta( 'woosb_total_limits_min' ) ); ?>"/>
								<?php esc_html_e( 'Max', 'woo-product-bundle' ); ?> <label
                                        for="woosb_total_limits_max"></label><input id="woosb_total_limits_max"
                                                                                    name="woosb_total_limits_max"
                                                                                    type="number" min="0"
                                                                                    style="width: 80px"
                                                                                    value="<?php echo esc_attr( $product->get_meta( 'woosb_total_limits_max' ) ); ?>"/>
								<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
							</span>
                        </td>
                    </tr>
                    <tr class="woosb_tr_space">
                        <th><?php esc_html_e( 'Shipping fee', 'woo-product-bundle' ); ?></th>
                        <td>
                            <?php $shipping_fee = $product->get_meta( 'woosb_shipping_fee' ); ?>
                            <label for="woosb_shipping_fee"></label><select id="woosb_shipping_fee"
                                                                            name="woosb_shipping_fee">
                                <option value="whole" <?php selected( $shipping_fee, 'whole' ); ?>>
                                    <?php esc_html_e( 'Apply to the main bundle product', 'woo-product-bundle' ); ?>
                                </option>
                                <option value="each" <?php selected( $shipping_fee, 'each' ); ?>>
                                    <?php esc_html_e( 'Apply to each bundled sub-product', 'woo-product-bundle' ); ?>
                                </option>
                                <option value="both" <?php selected( $shipping_fee, 'both' ); ?>>
                                    <?php esc_html_e( 'Apply to both bundle & bundled sub-product', 'woo-product-bundle' ); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <?php if ( ! apply_filters( 'woosb_disable_inventory_management', false ) ) { ?>
                        <tr class="woosb_tr_space">
                            <th><?php esc_html_e( 'Manage stock', 'woo-product-bundle' ); ?></th>
                            <td>
                                <input id="woosb_manage_stock" name="woosb_manage_stock"
                                       type="checkbox" <?php echo esc_attr( $product->get_meta( 'woosb_manage_stock' ) === 'on' ? 'checked' : '' ); ?> />
                                <label
                                        for="woosb_manage_stock"><?php esc_html_e( 'Enable stock management at bundle level.', 'woo-product-bundle' ); ?></label>
                                <span class="woocommerce-help-tip"
                                      data-tip="<?php esc_attr_e( 'By default, the bundle\' stock was calculated automatically from bundled products. After enabling, please press "Update" then you can change the stock settings on the "Inventory" tab.', 'woo-product-bundle' ); ?>"></span>
                            </td>
                        </tr>
                    <?php } ?>
                    <tr class="woosb_tr_space">
                        <th><?php esc_html_e( 'Custom display price', 'woo-product-bundle' ); ?></th>
                        <td>
                            <label>
                                <input type="text" name="woosb_custom_price"
                                       value="<?php echo esc_attr( $product->get_meta( 'woosb_custom_price' ) ); ?>"/>
                            </label> E.g: <code>From $10 to $100</code>.
                            <?php /* translators: dynamic price */
                            esc_html_e( 'You can use %s to show the dynamic price between your custom text.', 'woo-product-bundle' ); ?>
                        </td>
                    </tr>
                    <tr class="woosb_tr_space">
                        <th><?php esc_html_e( 'Exclude un-purchasable', 'woo-product-bundle' ); ?></th>
                        <td>
                            <?php $exclude_unpurchasable = $product->get_meta( 'woosb_exclude_unpurchasable' ) ?: 'unset'; ?>
                            <label> <select name="woosb_exclude_unpurchasable">
                                    <option value="unset" <?php selected( $exclude_unpurchasable, 'unset' ); ?>>
                                        <?php esc_html_e( 'Default', 'woo-product-bundle' ); ?>
                                    </option>
                                    <option value="yes" <?php selected( $exclude_unpurchasable, 'yes' ); ?>>
                                        <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                    </option>
                                    <option value="no" <?php selected( $exclude_unpurchasable, 'no' ); ?>>
                                        <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                    </option>
                                </select> </label>
                            <span class="woocommerce-help-tip"
                                  data-tip="<?php esc_attr_e( 'Make the bundle still purchasable when one of the bundled products is un-purchasable. These bundled products are excluded from the orders.', 'woo-product-bundle' ); ?>"></span>
                        </td>
                    </tr>
                    <tr class="woosb_tr_space">
                        <th><?php esc_html_e( 'Layout', 'woo-product-bundle' ); ?></th>
                        <td>
                            <?php $layout = $product->get_meta( 'woosb_layout' ) ?: 'unset'; ?>
                            <label> <select name="woosb_layout">
                                    <option value="unset" <?php selected( $layout, 'unset' ); ?>>
                                        <?php esc_html_e( 'Default', 'woo-product-bundle' ); ?>
                                    </option>
                                    <option value="list" <?php selected( $layout, 'list' ); ?>>
                                        <?php esc_html_e( 'List', 'woo-product-bundle' ); ?>
                                    </option>
                                    <option value="grid-2" <?php selected( $layout, 'grid-2' ); ?>>
                                        <?php esc_html_e( 'Grid - 2 columns', 'woo-product-bundle' ); ?>
                                    </option>
                                    <option value="grid-3" <?php selected( $layout, 'grid-3' ); ?>>
                                        <?php esc_html_e( 'Grid - 3 columns', 'woo-product-bundle' ); ?>
                                    </option>
                                    <option value="grid-4" <?php selected( $layout, 'grid-4' ); ?>>
                                        <?php esc_html_e( 'Grid - 4 columns', 'woo-product-bundle' ); ?>
                                    </option>
                                </select> </label>
                        </td>
                    </tr>
                    <tr class="woosb_tr_space">
                        <th><?php esc_html_e( 'Above text', 'woo-product-bundle' ); ?></th>
                        <td>
                            <div class="w100">
                                <label>
									<textarea
                                            name="woosb_before_text"><?php echo esc_textarea( $product->get_meta( 'woosb_before_text' ) ); ?></textarea>
                                </label>
                            </div>
                        </td>
                    </tr>
                    <tr class="woosb_tr_space">
                        <th><?php esc_html_e( 'Under text', 'woo-product-bundle' ); ?></th>
                        <td>
                            <div class="w100">
                                <label>
									<textarea
                                            name="woosb_after_text"><?php echo esc_textarea( $product->get_meta( 'woosb_after_text' ) ); ?></textarea>
                                </label>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            <?php
        }

        function product_data_li( $product, $item, $key = '', $search = false ) {
            if ( empty( $key ) ) {
                $key = $this->helper->generate_key();
            }

            $qty           = $item['qty'] ?? 1;
            $min           = $item['min'] ?? '';
            $max           = $item['max'] ?? '';
            $optional      = $item['optional'] ?? 0;
            $terms         = $item['terms'] ?? [];
            $product_id    = $product->get_id();
            $product_sku   = $product->get_sku();
            $product_name  = $product->get_name();
            $product_class = 'woosb-li woosb-li-product';

            if ( ! $product->is_in_stock() ) {
                $product_class .= ' out-of-stock';
            }

            if ( in_array( $product->get_type(), $this->helper::get_types(), true ) ) {
                $product_class .= ' disabled';
            }

            if ( class_exists( 'WPCleverWoopq' ) && ( WPCleverWoopq::get_setting( 'decimal', 'no' ) === 'yes' ) ) {
                $step = '0.000001';
            } else {
                $step = 1;
            }

            if ( $product->is_sold_individually() ) {
                $qty_input = '<input type="number" class="woosb_item_qty_input" name="' . esc_attr( 'woosb_ids[' . $key . '][qty]' ) . '" value="' . esc_attr( $qty ) . '" min="0" step="' . esc_attr( $step ) . '" max="1"/>';
            } else {
                $qty_input = '<input type="number" class="woosb_item_qty_input" name="' . esc_attr( 'woosb_ids[' . $key . '][qty]' ) . '" value="' . esc_attr( $qty ) . '" min="0" step="' . esc_attr( $step ) . '"/>';
            }

            $price = $this->helper->get_price_to_display( $product );
            $price = $this->helper->round_price( $price );

            $price_max = $this->helper->get_price_to_display( $product, 1, 'max' );
            $price_max = $this->helper->round_price( $price_max );

            // apply filter same as frontend
            $item_name = apply_filters( 'woosb_item_product_name', $product_name, $product );

            if ( $product->is_type( 'variation' ) ) {
                $edit_link = get_edit_post_link( $product->get_parent_id() );
            } else {
                $edit_link = get_edit_post_link( $product_id );
            }

            $product_info = apply_filters( 'woosb_item_product_info', $product->get_type() . '<br/>#' . $product_id, $product );

            if ( $this->helper->get_setting( 'search_show_image', 'yes' ) === 'yes' ) {
                $product_image = apply_filters( 'woosb_item_product_image', '<span class="img">' . $product->get_image( [
                                30,
                                30
                        ] ) . '</span>', $product );
            } else {
                $product_image = '';
            }

            $product_attrs = apply_filters( 'woosb_item_product_attrs', [
                    'key'       => $key,
                    'name'      => $product_name,
                    'sku'       => $product_sku,
                    'id'        => $product_id,
                    'price'     => $price,
                    'price-max' => $price_max,
            ], $product );

            echo '<div class="' . esc_attr( $product_class ) . '" ' . $this->helper->data_attributes( $product_attrs ) . '>';
            echo '<div class="woosb-li-head">';
            echo '<input type="hidden" name="' . esc_attr( 'woosb_ids[' . $key . '][id]' ) . '" value="' . esc_attr( $product_id ) . '"/><input type="hidden" name="' . esc_attr( 'woosb_ids[' . $key . '][sku]' ) . '" value="' . esc_attr( $product_sku ) . '"/>';
            echo '<span class="move"></span><span class="qty hint--right" aria-label="' . esc_html__( 'Default quantity', 'woo-product-bundle' ) . '">' . $qty_input . '</span>' . $product_image . '<span class="data">' . ( $product->get_status() === 'private' ? '<span class="info">private</span> ' : '' ) . '<span class="name">' . esc_html( $item_name ) . '</span><span class="info">' . $product->get_price_html() . '</span> ' . ( $product->is_sold_individually() ? '<span class="info">' . esc_html__( 'sold individually', 'woo-product-bundle' ) . '</span> ' : '' ) . '</span><span class="type"><a href="' . esc_url( $edit_link ) . '" target="_blank">' . $product_info . '</a></span> ';

            if ( $search ) {
                echo '<span class="woosb-remove hint--left" aria-label="' . esc_attr__( 'Add', 'woo-product-bundle' ) . '">+</span>';
            } else {
                echo '<span class="woosb-remove hint--left" aria-label="' . esc_attr__( 'Remove', 'woo-product-bundle' ) . '">×</span>';
            }

            echo '</div><!-- /woosb-li-head -->';
            echo '<div class="woosb-li-body">';
            echo '<div class="qty_config"><div class="qty_config_inner">';
            echo '<span><input type="checkbox" class="optional_checkbox" id="' . esc_attr( 'optional_checkbox_' . $key ) . '" name="' . esc_attr( 'woosb_ids[' . $key . '][optional]' ) . '" value="1" ' . checked( $optional, 1, false ) . '/> <label for="' . esc_attr( 'optional_checkbox_' . $key ) . '">' . esc_html__( 'Custom quantity', 'woo-product-bundle' ) . '</label></span>';
            echo '<span class="optional_min_max">' . esc_html__( 'Min', 'woo-product-bundle' ) . ' <input class="optional_min_input" type="number" name="' . esc_attr( 'woosb_ids[' . $key . '][min]' ) . '" value="' . esc_attr( $min ) . '"/> ' . esc_html__( 'Max', 'woo-product-bundle' ) . ' <input class="optional_max_input" type="number" name="' . esc_attr( 'woosb_ids[' . $key . '][max]' ) . '" value="' . esc_attr( $max ) . '"/></span>';
            echo '</div><!-- /qty_config_inner --></div><!-- /qty_config -->';

            if ( $product->is_type( 'variable' ) ) {
                // settings form
                $attributes = $product->get_variation_attributes();

                echo '<div class="terms_config">By default, all existing terms of the current attribute(s) are enabled for variations. Users can type in to choose some term(s) and enable certain variations only. If any box is left blank, all current terms of the corresponding attribute(s) will be used.';

                if ( is_array( $attributes ) && ( count( $attributes ) > 0 ) ) {
                    foreach ( $attributes as $attribute_name => $options ) {
                        echo '<div style="margin-top: 10px">';
                        echo '<div>' . esc_html( wc_attribute_label( $attribute_name ) ) . '</div>';

                        if ( ! empty( $options ) ) {
                            $attribute_name_st = sanitize_title( $attribute_name );
                            echo '<select class="woosb_select_multiple" name="' . esc_attr( 'woosb_ids[' . $key . '][terms][' . $attribute_name_st . '][]' ) . '" multiple>';

                            foreach ( $options as $option ) {
                                echo '<option value="' . esc_attr( $option ) . '" ' . ( isset( $terms[ $attribute_name_st ] ) && in_array( $option, $terms[ $attribute_name_st ] ) ? 'selected' : '' ) . '>' . esc_html( $option ) . '</option>';
                            }

                            echo '</select>';
                        }

                        echo '</div>';
                    }
                }

                echo '</div>';
            }

            echo '</div><!-- /woosb-li-body -->';
            echo '</div><!-- /woosb-li -->';
        }

        function text_data_li( $data = [], $key = '' ) {
            if ( empty( $key ) ) {
                $key = $this->helper->generate_key();
            }

            $data = array_merge( [ 'type' => 'h1', 'text' => '' ], $data );
            $type = '<select name="' . esc_attr( 'woosb_ids[' . $key . '][type]' ) . '"><option value="h1" ' . selected( $data['type'], 'h1', false ) . '>H1</option><option value="h2" ' . selected( $data['type'], 'h2', false ) . '>H2</option><option value="h3" ' . selected( $data['type'], 'h3', false ) . '>H3</option><option value="h4" ' . selected( $data['type'], 'h4', false ) . '>H4</option><option value="h5" ' . selected( $data['type'], 'h5', false ) . '>H5</option><option value="h6" ' . selected( $data['type'], 'h6', false ) . '>H6</option><option value="p" ' . selected( $data['type'], 'p', false ) . '>p</option><option value="span" ' . selected( $data['type'], 'span', false ) . '>span</option><option value="none" ' . selected( $data['type'], 'none', false ) . '>none</option></select>';

            echo '<div class="woosb-li woosb-li-text"><div class="woosb-li-head"><span class="move"></span><span class="tag">' . $type . '</span><span class="data"><input type="text" name="' . esc_attr( 'woosb_ids[' . $key . '][text]' ) . '" value="' . esc_attr( $data['text'] ) . '"/></span><span class="woosb-remove hint--left" aria-label="' . esc_html__( 'Remove', 'woo-product-bundle' ) . '">×</span></div></div>';
        }

        function process_product_meta_woosb( $post_id ) {
            if ( isset( $_POST['woosb_ids'] ) ) {
                update_post_meta( $post_id, 'woosb_ids', $this->helper->sanitize_array( wp_unslash( $_POST['woosb_ids'] ?? '' ) ) );
            } else {
                delete_post_meta( $post_id, 'woosb_ids' );
            }

            if ( isset( $_POST['woosb_disable_auto_price'] ) ) {
                update_post_meta( $post_id, 'woosb_disable_auto_price', 'on' );
            } else {
                update_post_meta( $post_id, 'woosb_disable_auto_price', 'off' );
            }

            if ( isset( $_POST['woosb_discount'] ) ) {
                update_post_meta( $post_id, 'woosb_discount', sanitize_text_field( wp_unslash( $_POST['woosb_discount'] ?? '' ) ) );
            } else {
                update_post_meta( $post_id, 'woosb_discount', 0 );
            }

            if ( isset( $_POST['woosb_discount_amount'] ) ) {
                update_post_meta( $post_id, 'woosb_discount_amount', sanitize_text_field( wp_unslash( $_POST['woosb_discount_amount'] ?? '' ) ) );
            } else {
                update_post_meta( $post_id, 'woosb_discount_amount', 0 );
            }

            if ( isset( $_POST['woosb_shipping_fee'] ) ) {
                update_post_meta( $post_id, 'woosb_shipping_fee', sanitize_text_field( wp_unslash( $_POST['woosb_shipping_fee'] ?? '' ) ) );
            }

            if ( isset( $_POST['woosb_manage_stock'] ) ) {
                update_post_meta( $post_id, 'woosb_manage_stock', 'on' );
            } else {
                update_post_meta( $post_id, 'woosb_manage_stock', 'off' );
            }

            if ( isset( $_POST['woosb_limit_whole_min'] ) ) {
                update_post_meta( $post_id, 'woosb_limit_whole_min', sanitize_text_field( wp_unslash( $_POST['woosb_limit_whole_min'] ?? '' ) ) );
            }

            if ( isset( $_POST['woosb_limit_whole_max'] ) ) {
                update_post_meta( $post_id, 'woosb_limit_whole_max', sanitize_text_field( wp_unslash( $_POST['woosb_limit_whole_max'] ?? '' ) ) );
            }

            if ( isset( $_POST['woosb_total_limits'] ) ) {
                update_post_meta( $post_id, 'woosb_total_limits', 'on' );
            } else {
                update_post_meta( $post_id, 'woosb_total_limits', 'off' );
            }

            if ( isset( $_POST['woosb_total_limits_min'] ) ) {
                update_post_meta( $post_id, 'woosb_total_limits_min', sanitize_text_field( wp_unslash( $_POST['woosb_total_limits_min'] ?? '' ) ) );
            }

            if ( isset( $_POST['woosb_total_limits_max'] ) ) {
                update_post_meta( $post_id, 'woosb_total_limits_max', sanitize_text_field( wp_unslash( $_POST['woosb_total_limits_max'] ?? '' ) ) );
            }

            if ( isset( $_POST['woosb_exclude_unpurchasable'] ) ) {
                update_post_meta( $post_id, 'woosb_exclude_unpurchasable', sanitize_text_field( wp_unslash( $_POST['woosb_exclude_unpurchasable'] ?? '' ) ) );
            }

            if ( isset( $_POST['woosb_layout'] ) ) {
                update_post_meta( $post_id, 'woosb_layout', sanitize_text_field( wp_unslash( $_POST['woosb_layout'] ?? '' ) ) );
            }

            if ( isset( $_POST['woosb_custom_price'] ) ) {
                update_post_meta( $post_id, 'woosb_custom_price', sanitize_post_field( 'post_content', wp_unslash( $_POST['woosb_custom_price'] ?? '' ), $post_id, 'display' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            }

            if ( isset( $_POST['woosb_before_text'] ) ) {
                update_post_meta( $post_id, 'woosb_before_text', sanitize_post_field( 'post_content', wp_unslash( $_POST['woosb_before_text'] ?? '' ), $post_id, 'display' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            }

            if ( isset( $_POST['woosb_after_text'] ) ) {
                update_post_meta( $post_id, 'woosb_after_text', sanitize_post_field( 'post_content', wp_unslash( $_POST['woosb_after_text'] ?? '' ), $post_id, 'display' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            }
        }

        function add_order_item_meta( $order_item_id, $order_item, $order ) {
            $quantity = $order_item->get_quantity();

            if ( 'line_item' === $order_item->get_type() ) {
                $product = $order_item->get_product();

                if ( $product && $product->is_type( 'woosb' ) && ( $items = $product->get_items() ) ) {
                    $product_id = $product->get_id();
                    $items      = $this->helper->minify_items( $items );

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
                        $order->remove_item( $order_item_id );
                    }
                }

                $order->save();
            }
        }

        function hidden_order_itemmeta( $hidden ) {
            return array_merge( $hidden, [
                    '_woosb_parent_id',
                    '_woosb_ids',
                    '_woosb_price',
                    'woosb_parent_id',
                    'woosb_ids',
                    'woosb_price'
            ] );
        }

        function before_order_itemmeta( $order_item_id, $order_item ) {
            // admin orders
            if ( ( $ids = $order_item->get_meta( '_woosb_ids' ) ) && ( $parent = $order_item->get_product() ) ) {
                $parent_id = $parent->get_id();
                $items     = $this->helper->get_bundled( $ids, $parent );
                $items_str = [];

                if ( is_array( $items ) && ! empty( $items ) ) {
                    foreach ( $items as $item ) {
                        if ( ! empty( $item['id'] ) ) {
                            if ( ! apply_filters( 'woosb_item_visible', true, $item['id'], $parent_id ) ) {
                                continue;
                            }

                            $items_str[] = apply_filters( 'woosb_admin_order_bundled_product_name', '<li>' . $item['qty'] . ' × ' . esc_html( get_the_title( $item['id'] ) ) . '</li>', $item );
                        }
                    }
                }

                $items_str = apply_filters( 'woosb_admin_order_bundled_product_names', '<ul>' . implode( '', $items_str ) . '</ul>', $items );

                echo apply_filters( 'woosb_before_admin_order_itemmeta_bundles', '<div class="woosb-itemmeta-bundles woosb-admin-itemmeta-bundles">' . /* translators: %s is the product list */ sprintf( $this->helper->localization( 'bundled_products_s', esc_html__( 'Bundled products: %s', 'woo-product-bundle' ) ), $items_str ) . '</div>', $order_item_id, $order_item );
            }

            if ( $parent_id = $order_item->get_meta( '_woosb_parent_id' ) ) {
                echo apply_filters( 'woosb_before_admin_order_itemmeta_bundled', '<div class="woosb-itemmeta-bundled woosb-admin-itemmeta-bundled">' . /* translators: %s is the parent bundle name */ sprintf( $this->helper->localization( 'bundled_in_s', esc_html__( 'Bundled in: %s', 'woo-product-bundle' ) ), esc_html( get_the_title( $parent_id ) ) ) . '</div>', $order_item_id, $order_item );
            }
        }

        function display_post_states( $states, $post ) {
            if ( 'product' == get_post_type( $post->ID ) ) {
                if ( ( $product = wc_get_product( $post->ID ) ) && $product->is_type( 'woosb' ) ) {
                    $count = 0;

                    if ( $ids_str = $product->get_ids_str() ) {
                        $ids_arr = explode( ',', $ids_str );
                        $count   = count( $ids_arr );
                    }

                    $states[] = apply_filters( 'woosb_post_states', '<span class="woosb-state">' . /* translators: bundle name */ sprintf( esc_html__( 'Bundle (%s)', 'woo-product-bundle' ), $count ) . '</span>', $count, $product );
                }
            }

            return $states;
        }

        function bulk_actions() {
            if ( current_user_can( 'edit_products' ) ) {
                add_filter( 'bulk_actions-edit-product', [ $this, 'bulk_actions_register' ] );
                add_filter( 'handle_bulk_actions-edit-product', [ $this, 'bulk_actions_handler' ], 10, 3 );
                add_action( 'admin_notices', [ $this, 'bulk_actions_notice' ] );
            }
        }

        function bulk_actions_register( $bulk_actions ) {
            $bulk_actions['woosb_create_bundle'] = esc_html__( 'Create a Smart bundle', 'woo-product-bundle' );

            return $bulk_actions;
        }

        function bulk_actions_handler( $redirect_to, $do_action, $post_ids ) {
            if ( $do_action !== 'woosb_create_bundle' ) {
                return $redirect_to;
            }

            $ids = implode( '.', $post_ids );

            return add_query_arg( 'woosb_ids', $ids, admin_url( 'post-new.php?post_type=product' ) );
        }

        function bulk_actions_notice() {
            if ( ! empty( $_REQUEST['woosb_ids'] ) ) {
                $ids = explode( '.', wp_unslash( $_REQUEST['woosb_ids'] ?? '' ) );
                echo '<div id="message" class="updated fade">' . /* translators: count */ sprintf( esc_html__( 'Added %s product(s) to this bundle.', 'woo-product-bundle' ), count( $ids ) ) . '</div>';
            }
        }

        function search_sku( $query ) {
            if ( $query->is_search && isset( $query->query['is_woosb'] ) && ( $this->helper->get_setting( 'search_sku', 'no' ) === 'yes' ) ) {
                global $wpdb;

                $sku = sanitize_text_field( $query->query['s'] );
                $ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value = %s;", $sku ) );

                if ( ! $ids ) {
                    return;
                }

                $posts = [];

                foreach ( $ids as $id ) {
                    $post = get_post( $id );

                    if ( $post->post_type === 'product_variation' ) {
                        $posts[] = $post->post_parent;
                    } else {
                        $posts[] = $post->ID;
                    }
                }

                unset( $query->query['s'], $query->query_vars['s'] );
                $query->set( 'post__in', $posts );
            }
        }

        function search_exact( $query ) {
            if ( $query->is_search && isset( $query->query['is_woosb'] ) && ( $this->helper->get_setting( 'search_exact', 'no' ) === 'yes' ) ) {
                $query->set( 'exact', true );
            }
        }

        function search_sentence( $query ) {
            if ( $query->is_search && isset( $query->query['is_woosb'] ) && ( $this->helper->get_setting( 'search_sentence', 'no' ) === 'yes' ) ) {
                $query->set( 'sentence', true );
            }
        }

        function search_settings() {
            $search_sku        = $this->helper->get_setting( 'search_sku', 'no' );
            $search_id         = $this->helper->get_setting( 'search_id', 'no' );
            $search_exact      = $this->helper->get_setting( 'search_exact', 'no' );
            $search_sentence   = $this->helper->get_setting( 'search_sentence', 'no' );
            $search_same       = $this->helper->get_setting( 'search_same', 'no' );
            $search_show_image = $this->helper->get_setting( 'search_show_image', 'yes' );
            ?>
            <tr class="show_if_section_none">
                <th><?php esc_html_e( 'Search limit', 'woo-product-bundle' ); ?></th>
                <td>
                    <label>
                        <input type="number" class="woosb_search_limit" name="woosb_settings[search_limit]"
                               value="<?php echo $this->helper->get_setting( 'search_limit', 10 ); ?>"/>
                    </label>
                </td>
            </tr>
            <tr class="show_if_section_none">
                <th><?php esc_html_e( 'Search by SKU', 'woo-product-bundle' ); ?></th>
                <td>
                    <label> <select name="woosb_settings[search_sku]" class="woosb_search_sku">
                            <option value="yes" <?php selected( $search_sku, 'yes' ); ?>>
                                <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                            </option>
                            <option value="no" <?php selected( $search_sku, 'no' ); ?>>
                                <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                            </option>
                        </select> </label>
                </td>
            </tr>
            <tr class="show_if_section_none">
                <th><?php esc_html_e( 'Search by ID', 'woo-product-bundle' ); ?></th>
                <td>
                    <label> <select name="woosb_settings[search_id]" class="woosb_search_id">
                            <option value="yes" <?php selected( $search_id, 'yes' ); ?>>
                                <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                            </option>
                            <option value="no" <?php selected( $search_id, 'no' ); ?>>
                                <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                            </option>
                        </select> </label>
                    <span
                            class="description"><?php esc_html_e( 'Search by ID when entering the numeric only.', 'woo-product-bundle' ); ?></span>
                </td>
            </tr>
            <tr class="show_if_section_none">
                <th><?php esc_html_e( 'Search exact', 'woo-product-bundle' ); ?></th>
                <td>
                    <label> <select name="woosb_settings[search_exact]" class="woosb_search_exact">
                            <option value="yes" <?php selected( $search_exact, 'yes' ); ?>>
                                <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                            </option>
                            <option value="no" <?php selected( $search_exact, 'no' ); ?>>
                                <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                            </option>
                        </select> </label>
                    <span
                            class="description"><?php esc_html_e( 'Match whole product title or content?', 'woo-product-bundle' ); ?></span>
                </td>
            </tr>
            <tr class="show_if_section_none">
                <th><?php esc_html_e( 'Search sentence', 'woo-product-bundle' ); ?></th>
                <td>
                    <label> <select name="woosb_settings[search_sentence]" class="woosb_search_sentence">
                            <option value="yes" <?php selected( $search_sentence, 'yes' ); ?>>
                                <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                            </option>
                            <option value="no" <?php selected( $search_sentence, 'no' ); ?>>
                                <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                            </option>
                        </select> </label>
                    <span class="description"><?php esc_html_e( 'Do a phrase search?', 'woo-product-bundle' ); ?></span>
                </td>
            </tr>
            <tr class="show_if_section_none">
                <th><?php esc_html_e( 'Accept same products', 'woo-product-bundle' ); ?></th>
                <td>
                    <label> <select name="woosb_settings[search_same]" class="woosb_search_same">
                            <option value="yes" <?php selected( $search_same, 'yes' ); ?>>
                                <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                            </option>
                            <option value="no" <?php selected( $search_same, 'no' ); ?>>
                                <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                            </option>
                        </select> </label>
                    <span
                            class="description"><?php esc_html_e( 'If yes, a product can be added many times.', 'woo-product-bundle' ); ?></span>
                </td>
            </tr>
            <tr class="show_if_section_none">
                <th><?php esc_html_e( 'Product types', 'woo-product-bundle' ); ?></th>
                <td>
                    <?php
                    $search_types  = $this->helper->get_setting( 'search_types', [ 'all' ] );
                    $product_types = wc_get_product_types();
                    $product_types = array_merge( [ 'all' => esc_html__( 'All', 'woo-product-bundle' ) ], $product_types );
                    $key_pos       = array_search( 'variable', array_keys( $product_types ) );

                    if ( $key_pos !== false ) {
                        $key_pos ++;
                        $second_array  = array_splice( $product_types, $key_pos );
                        $product_types = array_merge( $product_types, [ 'variation' => esc_html__( ' → Variation', 'woo-product-bundle' ) ], $second_array );
                    }

                    echo '<select name="woosb_settings[search_types][]" class="woosb_search_types" multiple style="width: 200px; height: 150px;">';

                    foreach ( $product_types as $key => $name ) {
                        echo '<option value="' . esc_attr( $key ) . '" ' . ( in_array( $key, $search_types, true ) ? 'selected' : '' ) . '>' . esc_html( $name ) . '</option>';
                    }

                    echo '</select>';
                    ?>
                </td>
            </tr>
            <tr class="show_if_section_none">
                <th><?php esc_html_e( 'Show image', 'woo-product-bundle' ); ?></th>
                <td>
                    <label> <select name="woosb_settings[search_show_image]" class="woosb_search_show_image">
                            <option value="yes" <?php selected( $search_show_image, 'yes' ); ?>>
                                <?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                            </option>
                            <option value="no" <?php selected( $search_show_image, 'no' ); ?>>
                                <?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                            </option>
                        </select> </label>
                </td>
            </tr>
            <?php
        }

        function admin_enqueue_scripts() {
            wp_enqueue_style( 'hint', WOOSB_URI . 'assets/css/hint.css', [], WOOSB_VERSION );
            wp_enqueue_style( 'woosb-backend', WOOSB_URI . 'assets/css/backend.css', [], WOOSB_VERSION );
            wp_enqueue_script( 'woosb-backend', WOOSB_URI . 'assets/js/backend.js', [
                    'jquery',
                    'jquery-ui-dialog',
                    'jquery-ui-sortable',
                    'selectWoo',
            ], WOOSB_VERSION, true );
            wp_localize_script(
                    'woosb-backend',
                    'woosb_vars',
                    [
                            'nonce'                    => wp_create_nonce( 'woosb-security' ),
                            'price_decimals'           => wc_get_price_decimals(),
                            'price_thousand_separator' => wc_get_price_thousand_separator(),
                            'price_decimal_separator'  => wc_get_price_decimal_separator(),
                            'round_price'              => apply_filters( 'woosb_round_price', ! apply_filters( 'woosb_ignore_round_price', false ) ),
                    ]
            );
        }

        function action_links( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WOOSB_FILE );
            }

            if ( $plugin === $file ) {
                $settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-woosb&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'woo-product-bundle' ) . '</a>';
                $links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-woosb&tab=premium' ) ) . '">' . esc_html__( 'Premium Version', 'woo-product-bundle' ) . '</a>';
                array_unshift( $links, $settings );
            }

            return (array) $links;
        }

        function row_meta( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WOOSB_FILE );
            }

            if ( $plugin === $file ) {
                $row_meta = [
                        'docs'    => '<a href="' . esc_url( WOOSB_DOCS ) . '" target="_blank">' . esc_html__( 'Docs', 'woo-product-bundle' ) . '</a>',
                        'support' => '<a href="' . esc_url( WOOSB_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'woo-product-bundle' ) . '</a>',
                ];

                return array_merge( $links, $row_meta );
            }

            return (array) $links;
        }

        function no_stock_notification( $product ) {
            if ( 'no' === get_option( 'woocommerce_notify_no_stock', 'yes' ) ) {
                return;
            }

            $message    = '';
            $subject    = sprintf( '[%s] %s', wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), esc_html__( 'Bundle(s) out of stock', 'woo-product-bundle' ) );
            $product_id = $product->get_id();

            if ( $bundles = $this->helper->get_bundles( $product_id ) ) {
                foreach ( $bundles as $bundle ) {
                    $message .= sprintf( /* translators: product name */ esc_html__( '%s is out of stock.', 'woo-product-bundle' ), html_entity_decode( esc_html( $bundle->get_formatted_name() ), ENT_QUOTES, get_bloginfo( 'charset' ) ) ) . ' <a href="' . esc_url( get_edit_post_link( $bundle->get_id() ) ) . '" target="_blank">#' . $bundle->get_id() . '</a><br/>';
                }

                $message .= sprintf( /* translators: product name */ esc_html__( '%s is out of stock.', 'woo-product-bundle' ), html_entity_decode( esc_html( $product->get_formatted_name() ), ENT_QUOTES, get_bloginfo( 'charset' ) ) ) . ' <a href="' . esc_url( get_edit_post_link( $product_id ) ) . '" target="_blank">#' . $product_id . '</a>';

                wp_mail(
                        apply_filters( 'woocommerce_email_recipient_no_stock', get_option( 'woocommerce_stock_email_recipient' ), $product, null ),
                        apply_filters( 'woocommerce_email_subject_no_stock', $subject, $product, null ),
                        apply_filters( 'woocommerce_email_content_no_stock', $message, $product ),
                        apply_filters( 'woocommerce_email_headers', 'Content-Type: text/html; charset=UTF-8', 'no_stock', $product, null ),
                        apply_filters( 'woocommerce_email_attachments', [], 'no_stock', $product, null )
                );
            }
        }

        function low_stock_notification( $product ) {
            if ( 'no' === get_option( 'woocommerce_notify_low_stock', 'yes' ) ) {
                return;
            }

            $message = '';
            $subject = sprintf( '[%s] %s', wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), esc_html__( 'Bundle(s) low in stock', 'woo-product-bundle' ) );

            $product_id = $product->get_id();
            if ( $bundles = $this->helper->get_bundles( $product_id ) ) {
                foreach ( $bundles as $bundle ) {
                    $message .= sprintf( /* translators: bundle name */ esc_html__( '%s is low in stock.', 'woo-product-bundle' ), html_entity_decode( esc_html( $bundle->get_formatted_name() ), ENT_QUOTES, get_bloginfo( 'charset' ) ) ) . ' <a href="' . esc_url( get_edit_post_link( $bundle->get_id() ) ) . '" target="_blank">#' . $bundle->get_id() . '</a><br/>';
                }

                $message .= sprintf( /* translators: product name */ esc_html__( '%1$s is low in stock. There are %2$d left.', 'woo-product-bundle' ), html_entity_decode( esc_html( $product->get_formatted_name() ), ENT_QUOTES, get_bloginfo( 'charset' ) ), html_entity_decode( esc_html( $product->get_stock_quantity() ) ) ) . ' <a href="' . esc_url( get_edit_post_link( $product_id ) ) . '" target="_blank">#' . $product_id . '</a>';

                wp_mail(
                        apply_filters( 'woocommerce_email_recipient_low_stock', get_option( 'woocommerce_stock_email_recipient' ), $product, null ),
                        apply_filters( 'woocommerce_email_subject_low_stock', $subject, $product, null ),
                        apply_filters( 'woocommerce_email_content_low_stock', $message, $product ),
                        apply_filters( 'woocommerce_email_headers', 'Content-Type: text/html; charset=UTF-8', 'low_stock', $product, null ),
                        apply_filters( 'woocommerce_email_attachments', [], 'low_stock', $product, null )
                );
            }
        }

        function export_process( $value, $meta, $product ) {
            if ( $meta->key === 'woosb_ids' ) {
                $ids = $product->get_meta( 'woosb_ids' );

                if ( ! empty( $ids ) && is_array( $ids ) ) {
                    return json_encode( $ids );
                }
            }

            return $value;
        }

        function import_process( $object, $data ) {
            if ( isset( $data['meta_data'] ) ) {
                foreach ( $data['meta_data'] as $meta ) {
                    if ( $meta['key'] === 'woosb_ids' ) {
                        $object->update_meta_data( 'woosb_ids', json_decode( $meta['value'], true ) );
                        break;
                    }
                }
            }

            return $object;
        }

        function ajax_import_export() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woosb-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

            $ids      = [];
            $ids_arr  = [];
            $ids_data = sanitize_post( wp_unslash( $_POST['ids'] ?? '' ) );
            parse_str( $ids_data, $ids_arr );

            if ( isset( $ids_arr['woosb_ids'] ) && is_array( $ids_arr['woosb_ids'] ) ) {
                $ids = $ids_arr['woosb_ids'];
            }

            echo '<textarea class="woosb_import_export_data" style="width: 100%; height: 200px">' . esc_textarea( ( ! empty( $ids ) ? wp_json_encode( $ids, JSON_PRETTY_PRINT ) : '' ) ) . '</textarea>';
            echo '<div style="display: flex; align-items: center"><button class="button button-primary woosb-import-export-save">' . esc_html__( 'Update', 'woo-product-bundle' ) . '</button>';
            echo '<span style="color: #ff4f3b; font-size: 10px; margin-left: 10px">' . esc_html__( '* All selected products will be replaced after pressing Update!', 'woo-product-bundle' ) . '</span>';
            echo '</div>';

            wp_die();
        }

        function ajax_import_export_save() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'woosb-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

            $ids = sanitize_textarea_field( wp_unslash( $_POST['ids'] ?? '' ) );

            if ( ! empty( $ids ) ) {
                $items = json_decode( stripcslashes( $ids ), true );

                if ( ! empty( $items ) ) {
                    foreach ( $items as $item_key => $item ) {
                        if ( ! empty( $item['id'] ) ) {
                            $_product = wc_get_product( $item['id'] );

                            if ( ! $_product || in_array( $_product->get_type(), $this->helper::get_types(), true ) ) {
                                continue;
                            }

                            $this->product_data_li( $_product, $item, $item_key );
                        } else {
                            $this->text_data_li( $item, $item_key );
                        }
                    }
                }
            }

            wp_die();
        }
    }

    function WPCleverWoosb_Backend() {
        return WPCleverWoosb_Backend::instance();
    }
}
