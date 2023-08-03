<?php
/**
 * Megaventory class.
 *
 * @package megaventory
 * @since 2.3.1
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Megaventory;

/**
 * Initialize the Megaventory plugin.
 */
class Megaventory {

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Update Megaventory API key and host.
	 *
	 * @return void
	 */
	public static function update_apikey_and_host() {

		global $mv_admin_slug;

		if ( isset( $_POST['api_key'], $_POST['update-credentials-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['update-credentials-nonce'] ), 'update-credentials-nonce' ) ) {

			update_option( 'megaventory_api_key', trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) );

			update_option( 'api_key_is_set', 1 );
		}

		if ( isset( $_POST['api_host'], $_POST['update-credentials-nonce'] ) &&
			wp_verify_nonce( sanitize_key( $_POST['update-credentials-nonce'] ), 'update-credentials-nonce' ) ) {

			\Megaventory\API::set_api_host( trim( sanitize_text_field( wp_unslash( $_POST['api_host'] ) ) ) );
		}

		$status = self::check_status();

		if ( $status && get_option( 'new_mv_api_key' ) ) {
			\Megaventory\API::log_apikey( \Megaventory\API::get_api_key() );
		}

		wp_safe_redirect( admin_url( 'admin.php' ) . '?page=' . $mv_admin_slug );
	}

	/**
	 * Check plugin connection.
	 *
	 * @return bool
	 */
	public static function check_status() {

		$attempts = get_option( 'failed_connection_attempts', 0 );

		$response = \Megaventory\API::check_key();

		if ( ! get_option( 'correct_connection' ) ) {

			return false;
		}

		if ( ! get_option( 'api_key_is_set' ) ) {

			return false;
		}

		if ( 0 === (int) $response['ResponseStatus']['ErrorCode'] ) {

			$api_key = get_option( 'megaventory_api_key' );
			\Megaventory\API::log_apikey( $api_key );

			update_option( 'correct_megaventory_apikey', 1 );
			update_option( 'failed_connection_attempts', 0 );
			update_option( 'do_megaventory_requests', 1 );
		} else {

			++$attempts;
			if ( Models\MV_Constants::MAX_FAILED_CONNECTION_ATTEMPTS === $attempts ) {
				update_option( 'do_megaventory_requests', 0 );
				update_option( 'failed_connection_attempts', 0 );

				return false;
			}
			update_option( 'failed_connection_attempts', $attempts );
			update_option( 'correct_megaventory_apikey', 0 );
		}

		if ( ! get_option( 'correct_megaventory_apikey' ) ) {

			if ( false !== strpos( $response['ResponseStatus']['Message'], 'Your Account has expired.' ) ) {
				update_option( 'mv_account_expired', 1 );
			} else {
				update_option( 'mv_account_expired', 0 );
			}

			$megaventory_currency = get_option( 'primary_megaventory_currency', false );

			$currency_status = ( $megaventory_currency && ( get_woocommerce_currency() === $megaventory_currency ) );

			update_option( 'correct_currency', $currency_status );

			return false;
		}

		if ( null !== $response['ResponseStatus']['Message'] && strpos( $response['ResponseStatus']['Message'], 'Administrator' ) === false ) {

			update_option( 'mv_account_admin', 0 );

			return false;
		} else {
			update_option( 'mv_account_admin', 1 );
		}

		$integration_enabled = \Megaventory\API::check_if_integration_is_enabled();

		if ( ! $integration_enabled ) {

			update_option( 'mv_woo_integration_enabled', 0 );

			return false;
		} else {
			update_option( 'mv_woo_integration_enabled', 1 );
		}

		$correct_currency = \Megaventory\API::get_default_currency() === get_option( 'woocommerce_currency' );
		update_option( 'correct_currency', $correct_currency );

		if ( ! get_option( 'correct_currency' ) ) {

			return false;
		}

		$initialized = (bool) get_option( 'is_megaventory_initialized' );

		if ( ! $initialized ) {

			return false;
		}

		$last_key = get_option( 'megaventory_api_key' );

		$current_database_id_of_api_key = explode( '@', $last_key )[1];

		$last_valid_database_id_of_api_key = explode( '@', get_option( 'last_valid_api_key' ) )[1];

		if ( trim( $current_database_id_of_api_key ) !== trim( $last_valid_database_id_of_api_key ) ) {

			// New account has been used. Revert back to the uninitialized state.
			// In short: set initialization flag to false, and reset megaventory data.
			update_option( 'new_mv_api_key', 1 );
			update_option( 'is_megaventory_initialized', 0 );

			$products = Models\Product::wc_get_all_products();

			foreach ( $products as $product ) {
				$product->wc_delete_mv_data();
			}

			$clients = Models\Client::wc_get_all_clients();

			foreach ( $clients as $client ) {
				$client->wc_reset_mv_data();
			}

			\Megaventory\Models\Order::delete_mv_data_from_orders();
		} else {
			update_option( 'new_mv_api_key', 0 );
		}

		return true;
	}

	/**
	 * This code checks if woocommerce is an installed and activated plugin.
	 */
	public function is_woocommerce_plugin_enabled() {

		if ( ! ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ||
			is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) ) {

			return false;
		}

		/* configure admin panel */
		add_action( 'admin_menu', '\Megaventory\Megaventory::plugin_setup_menu' );

		/* custom product columns (display stock in product table) */
		add_filter( 'manage_edit-product_columns', '\Megaventory\Megaventory_Loader::add_quantity_column_to_product_table', 15 );
		add_action( 'manage_product_posts_custom_column', '\Megaventory\Megaventory_Loader::show_quantity_value_in_column', 10, 2 );

		/* purchase price product column */
		add_filter( 'manage_edit-product_columns', '\Megaventory\Megaventory_Loader::add_purchase_price_column_to_product_table', 15 );
		add_action( 'manage_product_posts_custom_column', '\Megaventory\Megaventory_Loader::show_purchase_price_value_in_column', 10, 2 );

		add_filter( 'manage_edit-shop_order_columns', '\Megaventory\Megaventory_Loader::add_megaventory_column_in_orders_list', 20 );
		add_action( 'manage_shop_order_posts_custom_column', '\Megaventory\Megaventory_Loader::show_megaventory_order_info_in_column', 10, 2 );

		/* Product purchase price field */
		add_action( 'woocommerce_product_options_pricing', '\Megaventory\Megaventory_Loader::add_purchase_price_for_simple_product' );
		add_action( 'woocommerce_process_product_meta', '\Megaventory\Controllers\Product::save_purchase_price' );
		add_action( 'woocommerce_variation_options_pricing', '\Megaventory\Megaventory_Loader::add_purchase_price_for_variation_product', 10, 3 );
		add_action( 'woocommerce_save_product_variation', '\Megaventory\Controllers\Product::save_variation_purchase_price', 10, 2 );

		/* styles */
		add_action( 'init', '\Megaventory\Megaventory::register_style' );
		add_action( 'admin_enqueue_scripts', '\Megaventory\Megaventory::enqueue_style' ); // Needed only in admin so far.

		if ( Models\MV_Constants::CHECK_STATUS_VALUE === random_int( Models\MV_Constants::RANDOM_NUMBER_MIN, Models\MV_Constants::RANDOM_NUMBER_MAX ) &&
			get_option( 'do_megaventory_requests', true ) ) {
			// Might check multiple times according to the logic and the resources needed from the API.
			self::check_status();
		}

		if ( ! get_option( 'do_megaventory_requests', true ) ) {
			\Megaventory\Helpers\Admin_Notifications::register_api_suspension_error();
		}

		if ( ! get_option( 'empty_megaventory_apikey' ) && get_option( 'do_megaventory_requests' ) && get_option( 'correct_currency' ) ) {

			$are_megaventory_products_synchronized = (bool) get_option( 'are_megaventory_products_synchronized', null );
			$are_megaventory_clients_synchronized  = (bool) get_option( 'are_megaventory_clients_synchronized', null );
			$are_megaventory_coupons_synchronized  = (bool) get_option( 'are_megaventory_coupons_synchronized', null );
			$is_megaventory_stock_adjusted         = (bool) get_option( 'is_megaventory_stock_adjusted', null );
			$is_megaventory_initialized            = (bool) get_option( 'is_megaventory_initialized', false );

			$is_megaventory_setup_complete = $is_megaventory_initialized &&
										$are_megaventory_products_synchronized &&
										$are_megaventory_clients_synchronized &&
										$are_megaventory_coupons_synchronized &&
										$is_megaventory_stock_adjusted;

			if ( $is_megaventory_setup_complete ) {
				add_action( 'woocommerce_order_status_processing', '\Megaventory\Controllers\Order::handle_order_placement', 10, 1 );
				add_action( 'woocommerce_order_status_on-hold', '\Megaventory\Controllers\Order::handle_order_placement', 10, 1 );
				add_action( 'woocommerce_order_status_cancelled', '\Megaventory\Controllers\Order::order_cancelled_handler', 10, 1 );
			}

			if ( $are_megaventory_products_synchronized ) {
				/* Product add/edit, delete */
				add_action( 'woocommerce_update_product', '\Megaventory\Controllers\Product::sync_on_product_save', 99, 1 );
				add_action( 'before_delete_post', '\Megaventory\Controllers\Product::delete_product_handler', 10, 2 );
				add_action( 'woocommerce_new_product', '\Megaventory\Controllers\Product::new_product_from_import', 10, 2 );
			}

			if ( $are_megaventory_clients_synchronized ) {
				/* Customer add, edit, delete */
				add_action( 'user_register', '\Megaventory\Controllers\Client::sync_on_profile_create', 10, 1 );
				add_action( 'profile_update', '\Megaventory\Controllers\Client::sync_on_profile_update', 10, 2 );
				add_action( 'delete_user', '\Megaventory\Controllers\Client::delete_client_handler', 10, 2 );
			}

			if ( $are_megaventory_coupons_synchronized ) {
				/* coupon add/edit  */
				add_action( 'woocommerce_new_coupon', '\Megaventory\Controllers\Coupon::on_coupon_update', 10, 2 );
				add_action( 'woocommerce_update_coupon', '\Megaventory\Controllers\Coupon::on_coupon_update', 10, 2 );
			}

			/* tax add/edit  */
			add_action( 'woocommerce_tax_rate_added', '\Megaventory\Controllers\Tax::on_tax_update', 10, 2 );
			add_action( 'woocommerce_tax_rate_updated', '\Megaventory\Controllers\Tax::on_tax_update', 10, 2 );

		}

		/* warning about error,warning,success */
		add_action( 'admin_notices', '\Megaventory\Helpers\Admin_Notifications::sample_admin_notice_error' );
		add_action( 'admin_notices', '\Megaventory\Helpers\Admin_Notifications::sample_admin_notice_warning' );
		add_action( 'admin_notices', '\Megaventory\Helpers\Admin_Notifications::sample_admin_notice_success' );
		add_action( 'admin_notices', '\Megaventory\Helpers\Admin_Notifications::sample_admin_database_notices' );

	}

	/**
	 * Set admin notice.
	 *
	 * @return void
	 */
	public static function plugin_activated_reset_basic_options() {

		/* Create transient data */
		set_transient( 'plugin_activation_notice', 1, 5 );
		update_option( 'api_key_is_set', 0 );

		update_option( 'megaventory_api_key', '' );

		delete_option( 'correct_currency' );
		delete_option( 'correct_connection' );
		delete_option( 'correct_megaventory_apikey' );
		delete_option( 'last_valid_api_key' );

		delete_option( 'mv_session_messages' );
		delete_option( Models\MV_Constants::MV_LOCATION_ID_TO_ABBREVIATION );

	}

	/**
	 * Javascript hooks.
	 */
	public function define_javascript_hooks() {

		add_action( 'admin_enqueue_scripts', '\Megaventory\Megaventory::enqueue_javascript_files' );

		add_action( 'wp_ajax_megaventory_import', '\Megaventory\Controllers\Synchronization::megaventory_import' );
		add_action( 'wp_ajax_nopriv_megaventory_import', '\Megaventory\Controllers\Synchronization::megaventory_import' );

		add_action( 'wp_ajax_megaventory_change_alternate_cron_status', '\Megaventory\Controllers\Integration_Updates::megaventory_change_alternate_cron_status' );
		add_action( 'wp_ajax_nopriv_megaventory_change_alternate_cron_status', '\Megaventory\Controllers\Integration_Updates::megaventory_change_alternate_cron_status' );

		add_action( 'wp_ajax_megaventory_toggle_order_delay', '\Megaventory\Controllers\Order::megaventory_toggle_order_delay' );
		add_action( 'wp_ajax_nopriv_megaventory_toggle_order_delay', '\Megaventory\Controllers\Order::megaventory_toggle_order_delay' );

		add_action( 'wp_ajax_megaventory_change_default_location', '\Megaventory\Controllers\Location::megaventory_change_default_location' );
		add_action( 'wp_ajax_nopriv_megaventory_change_default_location', '\Megaventory\Controllers\Location::megaventory_change_default_location' );

		add_action( 'wp_ajax_megaventory_include_location', '\Megaventory\Controllers\Location::megaventory_include_location' );
		add_action( 'wp_ajax_nopriv_megaventory_include_location', '\Megaventory\Controllers\Location::megaventory_include_location' );

		add_action( 'wp_ajax_megaventory_exclude_location', '\Megaventory\Controllers\Location::megaventory_exclude_location' );
		add_action( 'wp_ajax_nopriv_megaventory_exclude_location', '\Megaventory\Controllers\Location::megaventory_exclude_location' );

		add_action( 'wp_ajax_megaventory_pull_integration_updates', '\Megaventory\Controllers\Integration_Updates::megaventory_pull_integration_updates' );
		add_action( 'wp_ajax_nopriv_megaventory_pull_integration_updates', '\Megaventory\Controllers\Integration_Updates::megaventory_pull_integration_updates' );

		add_action( 'wp_ajax_megaventory_sync_stock_to_mv', '\Megaventory\Controllers\Stock::megaventory_sync_stock_to_mv' );
		add_action( 'wp_ajax_nopriv_megaventory_sync_stock_to_mv', '\Megaventory\Controllers\Stock::megaventory_sync_stock_to_mv' );

		add_action( 'wp_ajax_megaventory_sync_stock_from_mv', '\Megaventory\Controllers\Stock::megaventory_sync_stock_from_mv' );
		add_action( 'wp_ajax_nopriv_megaventory_sync_stock_from_mv', '\Megaventory\Controllers\Stock::megaventory_sync_stock_from_mv' );

		add_action( 'wp_ajax_megaventory_skip_stock_synchronization', '\Megaventory\Controllers\Stock::megaventory_skip_stock_synchronization' );
		add_action( 'wp_ajax_nopriv_megaventory_skip_stock_synchronization', '\Megaventory\Controllers\Stock::megaventory_skip_stock_synchronization' );

		add_action( 'wp_ajax_synchronize_order_to_megaventory_manually', '\Megaventory\Controllers\Order::synchronize_order_to_megaventory_manually' );
		add_action( 'wp_ajax_nopriv_synchronize_order_to_megaventory_manually', '\Megaventory\Controllers\Order::synchronize_order_to_megaventory_manually' );

		add_action( 'wp_ajax_megaventory_change_shipping_zones_option', '\Megaventory\Controllers\Shipping_Zones::megaventory_change_shipping_zones_option' );
		add_action( 'wp_ajax_nopriv_megaventory_change_shipping_zones_option', '\Megaventory\Controllers\Shipping_Zones::megaventory_change_shipping_zones_option' );

		add_action( 'wp_ajax_megaventory_save_shipping_zones_priority_order', '\Megaventory\Controllers\Shipping_Zones::megaventory_save_shipping_zones_priority_order' );
		add_action( 'wp_ajax_nopriv_megaventory_save_shipping_zones_priority_order', '\Megaventory\Controllers\Shipping_Zones::megaventory_save_shipping_zones_priority_order' );

		add_action( 'wp_ajax_megaventory_delete_success_logs', '\Megaventory\Controllers\Logs::megaventory_delete_success_logs' );
		add_action( 'wp_ajax_nopriv_megaventory_delete_success_logs', '\Megaventory\Controllers\Logs::megaventory_delete_success_logs' );

		add_action( 'wp_ajax_megaventory_delete_error_logs', '\Megaventory\Controllers\Logs::megaventory_delete_error_logs' );
		add_action( 'wp_ajax_nopriv_megaventory_delete_error_logs', '\Megaventory\Controllers\Logs::megaventory_delete_error_logs' );

		add_action( 'wp_ajax_megaventory_update_extra_fee_sku', '\Megaventory\Controllers\Product::megaventory_update_extra_fee_sku' );
		add_action( 'wp_ajax_nopriv_megaventory_update_extra_fee_sku', '\Megaventory\Controllers\Product::megaventory_update_extra_fee_sku' );

		add_action( 'wp_ajax_megaventory_update_payment_method_mappings', '\Megaventory\Controllers\Order::megaventory_update_payment_method_mappings' );
		add_action( 'wp_ajax_nopriv_megaventory_update_payment_method_mappings', '\Megaventory\Controllers\Order::megaventory_update_payment_method_mappings' );

		/* Plugin Upgrade hook */

		add_action( 'upgrader_process_complete', '\Megaventory\Megaventory::upgrade_plugin', 10, 2 );
	}

	/**
	 * Javascript files.
	 */
	public static function enqueue_javascript_files() {

		$nonce = wp_create_nonce( 'async-nonce' );

		wp_enqueue_script( 'jquery-ui-sortable' ); // jQuery UI Sortable. Required for shipping zone/location priority UI.

		wp_enqueue_script( 'ajaxCallImport', plugins_url( '/js/ajaxCallImport.js', __FILE__ ), array(), '2.5.0', true );
		wp_enqueue_script( 'ajaxCallInitialize', plugins_url( '/js/ajaxCallInitialize.js', __FILE__ ), array(), '2.5.0', true );
		wp_enqueue_script( 'ajaxWpCronStatus', plugins_url( '/js/ajaxWpCronStatus.js', __FILE__ ), array(), '2.5.0', true );
		wp_enqueue_script( 'ajaxShippingZones', plugins_url( '/js/ajaxShippingZones.js', __FILE__ ), array(), '2.5.0', true );
		wp_enqueue_script( 'ajaxLocation', plugins_url( '/js/ajaxLocation.js', __FILE__ ), array(), '2.5.0', true );
		wp_enqueue_script( 'ajaxLogs', plugins_url( '/js/ajaxLogs.js', __FILE__ ), array(), '2.5.0', true );
		wp_enqueue_script( 'ajaxProduct', plugins_url( '/js/ajaxProduct.js', __FILE__ ), array(), '2.5.0', true );
		wp_enqueue_script( 'ajaxPayment', plugins_url( '/js/ajaxPayment.js', __FILE__ ), array(), '2.5.0', true );

		$nonce_array = array(
			'nonce' => $nonce,
		);

		wp_localize_script( 'ajaxCallImport', 'mv_ajax_object', $nonce_array );
		wp_localize_script( 'ajaxCallInitialize', 'mv_ajax_object', $nonce_array );
		wp_localize_script( 'ajaxWpCronStatus', 'mv_ajax_object', $nonce_array );
		wp_localize_script( 'ajaxShippingZones', 'mv_ajax_object', $nonce_array );
		wp_localize_script( 'ajaxLocation', 'mv_ajax_object', $nonce_array );
		wp_localize_script( 'ajaxLogs', 'mv_ajax_object', $nonce_array );
		wp_localize_script( 'ajaxProduct', 'mv_ajax_object', $nonce_array );
		wp_localize_script( 'ajaxPayment', 'mv_ajax_object', $nonce_array );
	}

	/**
	 * Link CSS.
	 *
	 * @return void
	 */
	public static function register_style() {
		wp_register_style( 'mv_style', plugins_url( '/assets/css/style.css', __FILE__ ), array(), '2.5.0', 'all' );
		wp_register_style( 'mv_style_fonts', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css', array(), '2.0.7', 'all' );
	}

	/**
	 * Enqueue CSS stylesheet.
	 *
	 * @return void
	 */
	public static function enqueue_style() {
		wp_enqueue_style( 'mv_style' );
		wp_enqueue_style( 'mv_style_fonts' );
	}

	/**
	 * Megaventory Plugin page.
	 *
	 * @return void
	 */
	public static function plugin_setup_menu() {

		$mv_admin_slug = 'megaventory-plugin';

		add_menu_page( 'Megaventory', 'Megaventory', 'manage_options', $mv_admin_slug, '\Megaventory\Admin\Dashboard::generate_megaventory_admin_dashboard', plugin_dir_url( __FILE__ ) . 'assets/images/mv.png', 30 );
	}

	/**
	 * DataBase creation.
	 *
	 * @return void
	 */
	public static function create_plugin_database_table() {

		global $wpdb;

		$error_table_name = $wpdb->prefix . 'megaventory_errors_log';

		$success_table_name = $wpdb->prefix . 'megaventory_success_log';

		$apikeys_table_name = $wpdb->prefix . 'megaventory_api_keys';

		$notices_table_name = $wpdb->prefix . 'megaventory_notices_log';

		/* Check to see if the table exists already, if not, then create it */

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		/* create error table if this does not exists */
		$charset_collate = $wpdb->get_charset_collate();

		$sql_error_table = "CREATE TABLE $error_table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			wc_id int,
			mv_id int,
			name varchar(200),
			problem text NOT NULL,
			message text,
			type varchar(100),
			code int,
			json_object text,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $error_table_name ), ARRAY_A ); // db call ok. no-cache ok.
		if ( count( $existing_table ) === 0 ) {

			dbDelta( $sql_error_table );
		}
		/**
		 * Dont use it, uses esc_like https://developer.wordpress.org/reference/classes/wpdb/esc_like/ !
		 * maybe_create_table.
		 */

		/* create success table if this does not exists */

		$sql_success_table = "CREATE TABLE $success_table_name(
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			wc_id int,
			mv_id int,
			type varchar(50),
			name varchar(200),
			transaction_status text NOT NULL,
			message text,
			code int,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $success_table_name ), ARRAY_A ); // db call ok. no-cache ok.
		if ( count( $existing_table ) === 0 ) {

			dbDelta( $sql_success_table );
		}

		/* create api_keys table if this does not exists */
		$sql_apikeys_table = "CREATE TABLE $apikeys_table_name(
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			api_key varchar(200),
			PRIMARY KEY  (id)
		) $charset_collate;";

		$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $apikeys_table_name ), ARRAY_A ); // db call ok. no-cache ok.
		if ( count( $existing_table ) === 0 ) {

			dbDelta( $sql_apikeys_table );
		}

		/* create notices table if this does not exists */
		$sql_notices_table = "CREATE TABLE $notices_table_name(
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			type varchar(50),
			message varchar(200),
			PRIMARY KEY  (id)
		) $charset_collate;";

		$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $notices_table_name ), ARRAY_A ); // db call ok. no-cache ok.
		if ( count( $existing_table ) === 0 ) {

			dbDelta( $sql_notices_table );
		}

		$existing_columns = $wpdb->get_col( 'DESC ' . $wpdb->prefix . 'woocommerce_tax_rates', 0 ); // db call ok. no-cache ok.
		$column_found     = false;
		foreach ( $existing_columns as $column_name ) {

			if ( 'mv_id' === $column_name ) {
				$column_found = true;
			}
		}
		if ( ! $column_found ) {

			$wpdb->query( "ALTER TABLE {$wpdb->prefix}woocommerce_tax_rates ADD mv_id INT;" ); // db call ok. no-cache ok. @codingStandardsIgnoreLine.
		}

	}

	/**
	 * Plugin upgrade hook.
	 *
	 * @param \WP_Upgrader $upgrader_object as WP_Upgrader.
	 * @param array        $options as array.
	 */
	public static function upgrade_plugin( $upgrader_object, $options ) {

		if ( array_key_exists( 'plugins', $options ) && in_array( plugin_basename( __FILE__ ), $options['plugins'], true ) && ( get_option( 'correct_key', false ) ) ) {

			update_option( 'correct_megaventory_apikey', get_option( 'correct_key' ) );
			delete_option( 'correct_key' );
		}
	}
}
