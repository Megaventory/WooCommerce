<?php
/**
 * Plugin Name: Megaventory
 * Version: 2.2.28
 * Text Domain: megaventory
 * Plugin URI: https://woocommerce.com/products/megaventory-inventory-management/
 * Woo: 5262358:dc7211c200c570406fc919a8b34465f9
 * Description: Integration between WooCommerce and Megaventory.
 *
 * @package megaventory
 * @since 1.0.0
 *
 * WC requires at least: 3.0
 * WC tested up to: 6.1.1
 * Requires at least: 4.4
 * Tested up to: 5.9.0
 * Stable tag: 5.9.0
 * Requires PHP: 7.0
 *
 * Author: Megaventory
 * Author URI: https://megaventory.com/
 * Developer: Megaventory
 * Developer URI: https://github.com/Megaventory/WooCommerce
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2020 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Prevent direct file access to plugin files
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( 300 > (int) ini_get( 'max_execution_time' ) ) {

	set_time_limit( 300 ); // So if the script has already run for 15 seconds and set_time_limit(30) is called, then it would run for a total of 30+15 = 45 seconds.
}
/**
 ** With this code we can track all our request.
 **
 ** add_filter( 'http_request_args', 'http_request_args_custom', 10, 2 );
 **
 ** /**
 ** * Increase request timeout for Megaventory requests.
 ** *
 ** * @param array  $request as array.
 ** * @param string $url Request url.
 ** * @return array
 **
 ** function http_request_args_custom( $request, $url ) {
 **
 ** if ( false !== strpos( $url, get_api_host() ) ) {
 ** // write to file
 ** }
 ** return $request;
 ** }
*/

define( 'MEGAVENTORY__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/cron.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-error.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-errors.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-product.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-client.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-tax.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-coupon.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-location.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/order.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'admin/admin-template.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/ajax-sync.php';

/*scripts hooks*/
add_action( 'admin_enqueue_scripts', 'ajax_calls' );

add_action( 'wp_ajax_asyncImport', 'async_import' );
add_action( 'wp_ajax_nopriv_asyncImport', 'async_import' );

add_action( 'wp_ajax_alternateWpCronStatus', 'change_alternate_cron_status' );
add_action( 'wp_ajax_nopriv_alternateWpCronStatus', 'change_alternate_cron_status' );

add_action( 'wp_ajax_changeAdjustmentDocumentStatusOption', 'change_adjustment_status_option' );
add_action( 'wp_ajax_nopriv_changeAdjustmentDocumentStatusOption', 'change_adjustment_status_option' );

add_action( 'wp_ajax_changeDefaultMegaventoryLocation', 'change_default_megaventory_location' );
add_action( 'wp_ajax_nopriv_changeDefaultMegaventoryLocation', 'change_default_megaventory_location' );

add_action( 'wp_ajax_pull_integration_updates', 'pull_integration_updates' );
add_action( 'wp_ajax_nopriv_pull_integration_updates', 'pull_integration_updates' );

add_action( 'wp_ajax_sync_stock_to_megaventory', 'sync_stock_to_megaventory' );
add_action( 'wp_ajax_nopriv_sync_stock_to_megaventory', 'sync_stock_to_megaventory' );

add_action( 'wp_ajax_sync_stock_from_megaventory', 'sync_stock_from_megaventory' );
add_action( 'wp_ajax_nopriv_sync_stock_from_megaventory', 'sync_stock_from_megaventory' );

add_action( 'wp_ajax_skip_stock_synchronization', 'skip_stock_synchronization' );
add_action( 'wp_ajax_nopriv_skip_stock_synchronization', 'skip_stock_synchronization' );

add_action( 'wp_ajax_sync_order', 'sync_order' );
add_action( 'wp_ajax_nopriv_sync_order', 'sync_order' );

/* Plugin Upgrade hook */

add_action( 'upgrader_process_complete', 'upgrade_plugin', 10, 2 );

$mv_admin_slug = 'megaventory-plugin';

/* when lock is true, edited product will not update mv products */
$save_product_lock = false;
$execute_lock      = false; // this lock prevents all sync between WC and MV.

update_option( 'last_valid_api_key', get_last_valid_api_key() );

$home_url   = get_home_url();
$plugin_url = $home_url . '/wp-admin/admin.php?page=megaventory-plugin';

/**
 * Starts the session.
 *
 * @return void
 */
function sess_start() {
	if ( ! headers_sent() && '' === session_id() ) {
		session_start();
	}
}

add_action( 'init', 'sess_start', 1 );

if ( get_option( 'megaventory_alternate_wp_cron', false ) && get_option( 'correct_megaventory_apikey', false ) && get_option( 'correct_connection', false ) ) {
	define( 'ALTERNATE_WP_CRON', true );
}

/**
 * PLUGIN INITIALIZATION
 */

/**
 * Registration errors.
 *
 * @param string $str1 as string message.
 * @param string $str2 as string message.
 * @return void
 */
function register_error( $str1 = null, $str2 = null ) {

	$session_messages = get_option( 'mv_session_messages' );

	if ( ! isset( $session_messages ) ) {
		$session_messages = array();
	}
	$errs = empty( $session_messages['errors'] ) ? array() : $session_messages['errors'];

	if ( ! is_array( $errs ) ) {
		$errs = array();
	}

	if ( null !== $str1 && ! in_array( $str1, $errs, true ) ) {
		array_push( $errs, $str1 );
	}

	if ( null !== $str2 && ! in_array( $str2, $errs, true ) ) {
		array_push( $errs, $str2 );
	}

	$session_messages['errors'] = $errs;

	update_option( 'mv_session_messages', $session_messages );
}

/**
 * Registration errors.
 *
 * @param string $str1 as string message.
 * @param string $str2 as string message.
 * @return void
 */
function register_warning( $str1 = null, $str2 = null ) {

	$session_messages = get_option( 'mv_session_messages' );

	if ( ! isset( $session_messages ) ) {
		$session_messages = array();
	}
	$warns = empty( $session_messages['warnings'] ) ? array() : $session_messages['warnings'];

	if ( ! is_array( $warns ) ) {
		$warns = array();
	}

	if ( null !== $str1 && ! in_array( $str1, $warns, true ) ) {
		array_push( $warns, $str1 );
	}

	if ( null !== $str2 && ! in_array( $str2, $warns, true ) ) {
		array_push( $warns, $str2 );
	}

	$session_messages['warnings'] = $warns;

	update_option( 'mv_session_messages', $session_messages );
}

/**
 * Registration successes.
 *
 * @param string $str1 as string message.
 * @param string $str2 as string message.
 * @return void
 */
function register_success( $str1 = null, $str2 = null ) {

	$session_messages = get_option( 'mv_session_messages' );

	if ( ! isset( $session_messages ) ) {
		$session_messages = array();
	}
	$succs = empty( $session_messages['successes'] ) ? array() : $session_messages['successes'];

	if ( ! is_array( $succs ) ) {
		$succs = array();
	}

	if ( null !== $str1 && ! in_array( $str1, $succs, true ) ) {
		array_push( $succs, $str1 );
	}

	if ( null !== $str2 && ! in_array( $str2, $succs, true ) ) {
		array_push( $succs, $str2 );
	}

	$session_messages['successes'] = $succs;

	update_option( 'mv_session_messages', $session_messages );
}

/**
 * Admin errors.
 *
 * @return void
 */
function sample_admin_notice_error() {

	$class = 'notice notice-error';

	global $pagenow;

	if ( 'admin.php' === $pagenow ) {

		$session_messages = get_option( 'mv_session_messages' );

		if ( ! isset( $session_messages ) ) {
			$session_messages = array();
		}

		$errs = ( isset( $session_messages['errors'] ) ? $session_messages['errors'] : array() );

		if ( null !== $errs && count( $errs ) > 0 ) {

			foreach ( $errs as $err ) {

				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $err ) );
			}

			unset( $session_messages['errors'] );

			update_option( 'mv_session_messages', $session_messages );
		}
	}
}

/**
 * Admin warnings.
 *
 * @return void
 */
function sample_admin_notice_warning() {

	$class = 'notice notice-warning';

	global $pagenow;

	if ( 'admin.php' === $pagenow ) {

		$session_messages = get_option( 'mv_session_messages' );

		if ( ! isset( $session_messages ) ) {
			$session_messages = array();
		}

		if ( ! isset( $session_messages['warnings'] ) ) {
			$warns = array();
		}

		$warns = ( isset( $session_messages['warnings'] ) ? $session_messages['warnings'] : array() );

		if ( null !== $warns && count( $warns ) > 0 ) {

			foreach ( $warns as $warn ) {
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $warn ) );
			}

			unset( $session_messages['warnings'] );

			update_option( 'mv_session_messages', $session_messages );
		}
	}
}

/**
 * Admin successes.
 *
 * @return void
 */
function sample_admin_notice_success() {
	$class = 'notice notice-success';

	global $pagenow;

	if ( 'admin.php' === $pagenow ) {

		$session_messages = get_option( 'mv_session_messages' );

		if ( ! isset( $session_messages ) ) {
			$session_messages = array();
		}

		$succs = ( isset( $session_messages['successes'] ) ? $session_messages['successes'] : array() );

		if ( null !== $succs && count( $succs ) > 0 ) {

			foreach ( $succs as $succ ) {

				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $succ ) );
			}

			unset( $session_messages['successes'] );

			update_option( 'mv_session_messages', $session_messages );
		}
	}
}

/**
 * API call suspended for excessive failed request.
 */
function register_api_suspension_error() {
	register_warning( 'Unable to verify Megaventory API key', 'Please check your API key and try again. All Megaventory synchronization tasks have been disabled due to excessive failed requests. Please ensure your Megaventory account is active and enter a valid API key or disable the plugin if you are not planning on using the integration.' );
}

/**
 * Admin database notices.
 *
 * @return void
 */
function sample_admin_database_notices() {

	$success_class = 'notice notice-success';

	$error_class = 'notice notice-error';

	$notice_class = 'notice notice-info';

	global $wpdb;

	$notices = (array) $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}megaventory_notices_log ORDER BY id ASC LIMIT 50;" ); // db call ok. no-cache ok.

	global $pagenow;

	foreach ( $notices as $notice ) {

		if ( 'success' === $notice->type ) {

			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $success_class ), esc_html( $notice->message ) );
		}

		if ( 'error' === $notice->type ) {

			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $error_class ), esc_html( $notice->message ) );
		}

		if ( 'notice' === $notice->type ) {

			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $notice_class ), esc_html( $notice->message ) );
		}
	}

	$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}megaventory_notices_log" ); // db call ok. no-cache ok.
}

/**
 * Plugin upgrade hook.
 *
 * @param WP_Upgrader $upgrader_object as WP_Upgrader.
 * @param array       $options as array.
 */
function upgrade_plugin( $upgrader_object, $options ) {
	if ( array_key_exists( 'plugins', $options ) && in_array( plugin_basename( __FILE__ ), $options['plugins'], true ) && ( get_option( 'correct_key', false ) ) ) {
		update_option( 'correct_megaventory_apikey', get_option( 'correct_key' ) );
		delete_option( 'correct_key' );
	}
}

/**
 * This code is executed only if woocommerce is an installed and activated plugin.
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) || is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) {

	/* configure admin panel */
	add_action( 'admin_menu', 'plugin_setup_menu' );

	/* custom product columns (display stock in product table) */
	add_filter( 'manage_edit-product_columns', 'add_quantity_column_to_product_table', 15 );
	add_action( 'manage_product_posts_custom_column', 'column', 10, 2 );

	/* purchase price product column */
	add_filter( 'manage_edit-product_columns', 'add_purchase_price_column_to_product_table', 15 );
	add_action( 'manage_product_posts_custom_column', 'purchase_price_column', 10, 2 );

	add_filter( 'manage_edit-shop_order_columns', 'megaventory_orders_list_column', 20 );
	add_action( 'manage_shop_order_posts_custom_column', 'display_megaventory_order_info', 10, 2 );

	/* Product purchase price field */
	add_action( 'woocommerce_product_options_pricing', 'purchase_price_option' );
	add_action( 'woocommerce_process_product_meta', 'save_purchase_price' );
	add_action( 'woocommerce_variation_options_pricing', 'purchase_price_variation_option', 10, 3 );
	add_action( 'woocommerce_save_product_variation', 'save_variation_purchase_price', 10, 2 );

	/* styles */
	add_action( 'init', 'register_style' );
	add_action( 'admin_enqueue_scripts', 'enqueue_style' ); // Needed only in admin so far.

	if ( MV_Constants::CHECK_STATUS_VALUE === random_int( MV_Constants::RANDOM_NUMBER_MIN, MV_Constants::RANDOM_NUMBER_MAX ) && get_option( 'do_megaventory_requests', true ) ) {
		// Might check multiple times according to the logic and the resources needed from the API.
		check_status();
	}

	if ( ! get_option( 'do_megaventory_requests', true ) ) {
		register_api_suspension_error();
	}

	if ( ! get_option( 'empty_megaventory_apikey' ) && get_option( 'do_megaventory_requests' ) && get_option( 'correct_currency' ) ) {

		add_action( 'woocommerce_order_status_processing', 'order_placed', 10, 1 );
		add_action( 'woocommerce_order_status_on-hold', 'order_placed', 10, 1 );
		add_action( 'woocommerce_order_status_cancelled', 'order_cancelled_handler', 10, 1 );

		/* Product add/edit, delete */
		add_action( 'woocommerce_update_product', 'sync_on_product_save', 99, 1 );
		add_action( 'before_delete_post', 'delete_product_handler', 10, 2 );
		add_action( 'woocommerce_new_product', 'new_product_from_import', 10, 2 );

		/* Customer add, edit, delete */
		add_action( 'user_register', 'sync_on_profile_create', 10, 1 );
		add_action( 'profile_update', 'sync_on_profile_update', 10, 2 );
		add_action( 'delete_user', 'delete_client_handler', 10, 2 );

		/* tax add/edit  */
		add_action( 'woocommerce_tax_rate_added', 'on_tax_update', 10, 2 );
		add_action( 'woocommerce_tax_rate_updated', 'on_tax_update', 10, 2 );

		/* coupon add/edit  */
		add_action( 'woocommerce_new_coupon', 'on_coupon_update', 10, 2 );
		add_action( 'woocommerce_update_coupon', 'on_coupon_update', 10, 2 );

	} else {

		$execute_lock = true;
	}

	/* warning about error,warning,success */
	add_action( 'admin_notices', 'sample_admin_notice_error' );
	add_action( 'admin_notices', 'sample_admin_notice_warning' );
	add_action( 'admin_notices', 'sample_admin_notice_success' );
	add_action( 'admin_notices', 'sample_admin_database_notices' );

} else { /* no woocommerce detected */

	register_error( 'Woocommerce not detected', 'Megaventory plugin cannot operate without woocommerce' );
	add_action( 'admin_notices', 'sample_admin_notice_error' );
}

/**
 * Add purchase price product option.
 *
 * @return void
 */
function purchase_price_option() {

	$product_id = get_the_ID();

	$product = Product::wc_find_product( $product_id );

	$options = array(
		'id'          => 'purchase_price',
		'value'       => get_post_meta( $product_id, 'purchase_price', true ),
		'label'       => __( 'Purchase price', 'textdomain' ) . ' (' . get_woocommerce_currency_symbol( get_woocommerce_currency() ) . ')',
		'data_type'   => 'price',
		'desc_tip'    => true,
		'description' => 'This is the purchase price of the product(the price that the supplier is charging you to supply you with this product excluding taxes).',
	);

	if ( ! empty( $product->mv_id ) ) {
		$options['custom_attributes'] = array( 'readonly' => 'readonly' ); // Enabling read only.
		$options['description']       = 'The value cannot change, since the product has been synchronized to Megaventory. You can change the purchase price on your Megaventory account.';
	}
	echo wp_kses(
		woocommerce_wp_text_input(
			$options
		),
		array(
			'input'    => array(),
			'textarea' => array(),
		)
	);
}

/**
 * Add purchase price product option.
 *
 * @param int     $loop as int.
 * @param array   $variation_data as array.
 * @param WP_Post $variation as WP_Post.
 *
 * @return void
 */
function purchase_price_variation_option( $loop, $variation_data, $variation ) {

	$product_id = $variation->ID;

	$product = Product::wc_find_product( $product_id );

	$options = array(
		'id'          => 'purchase_price[' . $loop . ']',
		'value'       => get_post_meta( $product_id, 'purchase_price', true ),
		'label'       => __( 'Purchase price', 'textdomain' ) . ' (' . get_woocommerce_currency_symbol( get_woocommerce_currency() ) . ')',
		'data_type'   => 'price',
		'desc_tip'    => true,
		'description' => 'This is the purchase price of the product(the price that the supplier is charging you to supply you with this product excluding taxes).',
	);

	if ( ! empty( $product->mv_id ) ) {
		$options['custom_attributes'] = array( 'readonly' => 'readonly' ); // Enabling read only.
		$options['description']       = 'The value cannot change, since the product has been synchronized to Megaventory. You can change the purchase price on your Megaventory account.';
	}

	echo wp_kses(
		woocommerce_wp_text_input(
			$options
		),
		array(
			'input'    => array(),
			'textarea' => array(),
		)
	);
}

/**
 * Save purchase price.
 *
 * @param int $post_id as int.
 *
 * @return void
 */
function save_purchase_price( $post_id ) {
	if ( isset( $_POST['woocommerce_meta_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) && ! empty( $_POST['purchase_price'] ) ) {
		update_post_meta( $post_id, 'purchase_price', sanitize_text_field( wp_unslash( $_POST['purchase_price'] ) ) );
	}
}

/**
 * Save purchase price.
 *
 * @param int $variation_id as int.
 * @param int $i as int, array index.
 *
 * @return void
 */
function save_variation_purchase_price( $variation_id, $i ) {

	// PHPCS needs nonce verification to get data from $_POST.
	// The nonce field is missing.
	// So the below code is added to bypass this.
	if ( isset( $_POST['woocommerce_meta_nonce'] ) ) {

		wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' );
	}

	if ( ! empty( $_POST['purchase_price'] ) && ! empty( $_POST['purchase_price'][ $i ] ) ) {
		update_post_meta( $variation_id, 'purchase_price', sanitize_text_field( wp_unslash( $_POST['purchase_price'][ $i ] ) ) );
	}
}

/**
 * Check plugin connection.
 *
 * @return bool
 */
function check_status() {

	$attempts = get_option( 'failed_connection_attempts', 0 );

	$response = check_key();

	if ( ! get_option( 'correct_connection' ) ) {

		return false;
	}

	if ( ! get_transient( 'api_key_is_set' ) ) {

		return false;
	}

	if ( 0 === (int) $response['ResponseStatus']['ErrorCode'] ) {

		$api_key = get_option( 'megaventory_api_key' );
		log_apikey( $api_key );

		update_option( 'correct_megaventory_apikey', 1 );
		update_option( 'failed_connection_attempts', 0 );
		update_option( 'do_megaventory_requests', 1 );
	} else {

		++$attempts;
		if ( MV_Constants::MAX_FAILED_CONNECTION_ATTEMPTS === $attempts ) {
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

	$integration_enabled = check_if_integration_is_enabled();

	if ( ! $integration_enabled ) {

		update_option( 'mv_woo_integration_enabled', 0 );

		return false;
	} else {
		update_option( 'mv_woo_integration_enabled', 1 );
	}

	$correct_currency = get_default_currency() === get_option( 'woocommerce_currency' );
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

		$products = Product::wc_get_all_products();

		foreach ( $products as $product ) {
			$product->wc_delete_mv_data();
		}

		$clients = Client::wc_get_all_clients();

		foreach ( $clients as $client ) {
			$client->wc_reset_mv_data();
		}

		delete_mv_data_from_orders();
	} else {
		update_option( 'new_mv_api_key', 0 );
	}

	return true;
}

/**
 * Scripts registration.
 *
 * @return void
 */
function ajax_calls() {

	$nonce = wp_create_nonce( 'async-nonce' );

	wp_enqueue_script( 'ajaxCallImport', plugins_url( '/js/ajaxCallImport.js', __FILE__ ), array(), '2.0.10', true );
	wp_enqueue_script( 'ajaxCallInitialize', plugins_url( '/js/ajaxCallInitialize.js', __FILE__ ), array(), '2.0.10', true );
	wp_enqueue_script( 'ajaxWpCronStatus', plugins_url( '/js/ajaxWpCronStatus.js', __FILE__ ), array(), '2.0.10', true );
	wp_enqueue_script( 'ajaxDocStatusChange', plugins_url( '/js/ajaxDocStatusChange.js', __FILE__ ), array(), '2.0.10', true );
	$nonce_array = array(
		'nonce' => $nonce,
	);

	wp_localize_script( 'ajaxCallImport', 'mv_ajax_object', $nonce_array );
	wp_localize_script( 'ajaxCallInitialize', 'mv_ajax_object', $nonce_array );
	wp_localize_script( 'ajaxWpCronStatus', 'mv_ajax_object', $nonce_array );
}
/**
 * Link CSS.
 *
 * @return void
 */
function register_style() {
	wp_register_style( 'mv_style', plugins_url( '/assets/css/style.css', __FILE__ ), false, '2.0.38', 'all' );
	wp_register_style( 'mv_style_fonts', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css', false, '2.0.7', 'all' );
}

/**
 * Enqueue CSS stylesheet.
 *
 * @return void
 */
function enqueue_style() {
	wp_enqueue_style( 'mv_style' );
	wp_enqueue_style( 'mv_style_fonts' );
}

/**
 * Add Megaventory stock column in product table.
 *
 * @param array $columns as table columns.
 * @return array
 */
function add_quantity_column_to_product_table( $columns ) {

	/* Megaventory stock column must be after normal stock column */
	$temp = array();

	foreach ( $columns as $key => $value ) {

		$temp[ $key ] = $value;

		if ( 'is_in_stock' === $key ) {
			$temp['megaventory_stock'] = __( 'Megaventory Quantity' );
		}
	}
	$columns = $temp;

	return $columns;
}

/**
 * Add purchase price column in product table.
 *
 * @param array $columns as table columns.
 * @return array
 */
function add_purchase_price_column_to_product_table( $columns ) {

	/* Megaventory purchase price column must be after price column */
	$temp = array();

	foreach ( $columns as $key => $value ) {

		$temp[ $key ] = $value;

		if ( 'price' === $key ) {
			$temp['purchase_price'] = __( 'Purchase Price' );
		}
	}
	$columns = $temp;

	return $columns;
}

add_action( 'admin_notices', 'sample_admin_notice_error' );

/**
 * Megaventory stock column in product's table.
 *
 * @param array $column as column in product table.
 * @param int   $prod_id as product id.
 * @return void
 */
function column( $column, $prod_id ) {

	if ( 'megaventory_stock' === $column ) {

		$wc_product = wc_get_product( $prod_id );

		$mv_qty = array();

		if ( 'variable' === $wc_product->get_type() ) {

			$variants_ids = $wc_product->get_children();

			$variant_skus = array();

			foreach ( $variants_ids as $variant_id ) {

				$wc_variation_product = new WC_Product_Variation( $variant_id );

				$product = Product::wc_variation_convert( $wc_variation_product, $wc_product );

				if ( ! is_array( $product->mv_qty ) || 0 === count( $product->mv_qty ) ) {

					continue;
				}

				if ( in_array( $product->sku, $variant_skus, true ) ) {
					continue;
				}

				$variant_skus[] = $product->sku;

				foreach ( $product->mv_qty as $key => $value ) {

					if ( array_key_exists( $key, $mv_qty ) ) {
						$variable_values    = explode( ';', $mv_qty[ $key ] );
						$simple_prod_values = explode( ';', $value );

						$variable_values[1] += $simple_prod_values[1];
						$variable_values[2] += $simple_prod_values[2];
						$variable_values[3] += $simple_prod_values[3];
						$variable_values[4] += $simple_prod_values[4];
						$variable_values[5] += $simple_prod_values[5];
						$variable_values[6] += $simple_prod_values[6];

						$mv_qty[ $key ] = $variable_values[0] . ';' . $variable_values[1] . ';' . $variable_values[2] . ';' . $variable_values[3] . ';' . $variable_values[4] . ';' . $variable_values[5] . ';' . $variable_values[6];
					} else {
						$mv_qty[ $key ] = $value;
					}
				}
			}
		} elseif ( 'simple' === $wc_product->get_type() ) {

			/* get product by id */
			$prod   = Product::wc_find_product( $prod_id );
			$mv_qty = $prod->mv_qty;

		} else {
			// Empty megaventory_stock column for anything else.
			return;
		}

		/* no stock */
		if ( ! is_array( $mv_qty ) || 0 === count( $mv_qty ) ) {

			echo 'No stock';

			return;
		}
		/* build stock table */
		?>
		<table class="qty-row">
		<?php foreach ( $mv_qty as $key => $qty ) : ?>
			<tr>
			<?php
			$mv_location_id_to_abbr = get_option( 'mv_location_id_to_abbr' );

			if ( empty( $mv_location_id_to_abbr[ $key ] ) ) {
				continue;
			}

			$inventory_name = $mv_location_id_to_abbr[ $key ];
			?>
			<?php $qty = explode( ';', $qty ); ?>
				<td colspan="2"><span><?php echo esc_attr( $inventory_name ); ?></span></td>
				<td class="mv-tooltip" title="Total"><span><?php echo esc_attr( $qty[1] ); ?></span></td>
				<td class="mv-tooltip" title="On Hand"><span class="qty-on-hand">(<?php echo esc_attr( $qty[2] ); ?>)</span></td>
				<td class="mv-tooltip" title="Non-shipped Quantity in Sales Orders"><span class="qty-non-shipped"><?php echo esc_attr( $qty[3] ); ?></span></td>
				<td class="mv-tooltip" title="Non-Allocated Quantity in Production Orders"><span class="qty-non-allocated"><?php echo esc_attr( $qty[4] ); ?></span></td>
				<td class="mv-tooltip" title="Non-Received Quantity in Purchase Orders"><span class="qty-non-received"><?php echo esc_attr( $qty[5] ); ?></span></td>
				<td class="mv-tooltip" title="Non-Received Quantity in Production Orders"><span class="qty-non-received"><?php echo esc_attr( $qty[6] ); ?></span></td>
			</tr>
		<?php endforeach; ?>
		</table>
		<?php
	}
}

/**
 * Purchase Price column in product's table.
 *
 * @param array $column as column in product table.
 * @param int   $prod_id as product id.
 * @return void
 */
function purchase_price_column( $column, $prod_id ) {

	if ( 'purchase_price' === $column ) {
		$purchase_price = get_post_meta( $prod_id, 'purchase_price', true );

		if ( empty( $purchase_price ) ) {
			echo wp_kses( '–', array() );
		} else {
			echo wp_kses( get_woocommerce_currency_symbol( get_woocommerce_currency() ) . $purchase_price, array() );
		}
	}
}

/**
 * Add Megaventory column in orders list.
 *
 * @param array $columns order list columns.
 * @return array
 */
function megaventory_orders_list_column( $columns ) {
	$columns['megaventory_order_column'] = __( 'Megaventory Order' );
	return $columns;
}

/**
 * Display Megaventory sales order id, or button to synchronize manually the order.
 *
 * @param array $column   as column in order grid.
 * @param int   $order_id as product id.
 * @return void
 */
function display_megaventory_order_info( $column, $order_id ) {

	if ( 'megaventory_order_column' !== $column ) {
		return;
	}

	$megaventory_order_id   = (int) get_post_meta( $order_id, 'order_sent_to_megaventory', true );
	$megaventory_order_name = (string) get_post_meta( $order_id, 'megaventory_order_name', true );

	if ( 0 < $megaventory_order_id ) {

		if ( ! empty( $megaventory_order_name ) ) {

			echo 'Megaventory order: ' . esc_attr( $megaventory_order_name );
		} else {

			echo 'Megaventory order id: ' . esc_attr( $megaventory_order_id );
		}
	} elseif ( get_option( 'correct_megaventory_apikey' ) && get_option( 'is_megaventory_initialized' ) ) {
		?>

		<span id ='orderToSync_<?php echo esc_attr( $order_id ); ?>' class='Padd10' onclick='SyncOrder(<?php echo esc_attr( $order_id ); ?>)'><a href="#">Synchronize</a></span>

		<?php
	}

}

/**
 * Megaventory Plugin page.
 *
 * @return void
 */
function plugin_setup_menu() {

	global $mv_admin_slug;

	add_menu_page( 'Megaventory plugin', 'Megaventory', 'manage_options', $mv_admin_slug, 'generate_admin_page', plugin_dir_url( __FILE__ ) . 'assets/images/mv.png', 30 );
}

/**
 * Update Megaventory API key and host.
 *
 * @return void
 */
function do_post() {

	global $mv_admin_slug;

	if ( isset( $_POST['api_key'], $_POST['update-credentials-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['update-credentials-nonce'] ), 'update-credentials-nonce' ) ) {

		update_option( 'megaventory_api_key', trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) );

		set_transient( 'api_key_is_set', 1 );
	}

	if ( isset( $_POST['api_host'], $_POST['update-credentials-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['update-credentials-nonce'] ), 'update-credentials-nonce' ) ) {
		set_api_host( trim( sanitize_text_field( wp_unslash( $_POST['api_host'] ) ) ) );
	}

	$status = check_status();

	if ( $status && get_option( 'new_mv_api_key' ) ) {
		log_apikey( get_api_key() );
	}

	wp_safe_redirect( admin_url( 'admin.php' ) . '?page=' . $mv_admin_slug );
}

add_action( 'admin_post_megaventory', 'do_post' );

/**
 * Get last inserted Megaventory API key.
 *
 * @return string
 */
function get_last_valid_api_key() {

	global $wpdb;

	/*
		This is reference for quick search for query below: megaventory_api_keys .
	*/

	$apikeys_table_name = $wpdb->prefix . 'megaventory_api_keys';

	$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $apikeys_table_name ), ARRAY_A ); // db call ok. no-cache ok.
	if ( count( $existing_table ) === 0 ) {

		return '';
	}

	$last_valid_apikey_array = $wpdb->get_results(
		"
			SELECT api_key 
			FROM {$wpdb->prefix}megaventory_api_keys 
			ORDER BY id 
			DESC LIMIT 1 "
	); // db call ok; no-cache ok.

	if ( ! empty( $last_valid_apikey_array ) ) {

		$last_valid_apikey = $last_valid_apikey_array[0]->api_key;

	} else {

		$last_valid_apikey = '';
	}

	return $last_valid_apikey;
}

/**
 * Setting up Megaventory API host.
 *
 * @param string $host as Megaventory host.
 * @return void
 */
function set_api_host( $host ) {

	if ( '/' !== substr( $host, -1 ) ) {

		$host .= '/';
	}

	update_option( 'megaventory_api_host', (string) $host );
}

// SYNCHRONIZE FUNCTIONS ON CREATE/UPDATE.

/**
 * Product edit or create.
 *
 * @param int $prod_id as product id.
 * @return void
 */
function sync_on_product_save( $prod_id ) {

	if ( 'product' !== get_post_type( $prod_id ) ) {
		return;
	}

	$wc_product = wc_get_product( $prod_id );

	if ( 'publish' !== $wc_product->get_status() && 'future' !== $wc_product->get_status() ) {
		return;
	}

	if ( 'variable' === $wc_product->get_type() ) {

		Product::update_variable_product_in_megaventory( $wc_product );

	} else {

		$product = Product::wc_find_product( $prod_id );
		$product->mv_save();
	}
}

/**
 * Product create from CSV Import.
 *
 * @param int        $product_id as product id.
 * @param WC_Product $product as WC_Product.
 * @return void
 */
function new_product_from_import( $product_id, $product ) {
	sync_on_product_save( $product_id );
}

/**
 * Delete product event handler.
 *
 * @param int   $product_id as product id.
 * @param array $wp_post as array.
 * @return void
 */
function delete_product_handler( $product_id, $wp_post ) {

	if ( 'product' !== get_post_type( $product_id ) && 'product_variation' !== get_post_type( $product_id ) ) {
		return;
	}

	$wc_product = wc_get_product( $product_id );

	// variations will trigger the same hook.
	if ( 'simple' === $wc_product->get_type() || 'variation' === $wc_product->get_type() ) {

		$product = Product::wc_find_product( $product_id );
		$product->delete_product_in_megaventory();
	}
}

/**
 * Updates a client to Megaventory.
 *
 * @param int     $user_id as user id.
 * @param WP_User $old_user_data as user data.
 * @return void
 */
function sync_on_profile_update( $user_id, $old_user_data ) {

	$user = Client::wc_find( $user_id );

	if ( isset( $user ) ) {

		$user->mv_save();
	}
}

/**
 * Insert a client to Megaventory.
 *
 * @param int $user_id as user id.
 * @return void
 */
function sync_on_profile_create( $user_id ) {

	$user = Client::wc_find( $user_id );

	/* we want to save only customer/subscriber users in megaventory */

	if ( isset( $user ) ) {

		$user->mv_save();
	}
}

/**
 * Delete client event handler.
 *
 * @param int      $client_id as client id.
 * @param int|null $reassign ID of the user to whom posts will be automatically re-assigned (if one was selected).
 * @return void
 */
function delete_client_handler( $client_id, $reassign ) {

	$client = Client::wc_find( $client_id );

	// Is not a client/subscriber.
	if ( null === $client ) {
		return;
	}

	$client->delete_client_in_megaventory();

}

/**
 * Define the woocommerce_tax_rate_added / woocommerce_tax_rate_updated callback.
 *
 * @param int    $tax_rate_id as tax id.
 * @param double $tax_rate as tax rate.
 * @return void
 */
function on_tax_update( $tax_rate_id, $tax_rate ) {

	$tax = Tax::wc_find_tax( $tax_rate_id );
	if ( ! $tax ) {
		return;
	}

	$mv_tax = Tax::mv_find_by_name_and_rate( $tax->name, $tax->rate );

	if ( null !== $mv_tax ) {

		$tax->mv_id = $mv_tax->mv_id;

		$tax->wc_save();
	} else {
		/* creating new tax in MV */

		$tax->description = 'Woocommerce ' . $tax->type . ' tax';
		$tax->mv_save();

	}
}

/**
 * Define the woocommerce_create_coupon/woocommerce_update_coupon callback.
 *
 * @param int $coupon_id as tax id.
 * @return void
 */
function on_coupon_update( $coupon_id ) {

	$coupon = Coupon::wc_find_coupon( $coupon_id );

	if ( null === $coupon ) {
		return;
	}

	if ( 'percent' !== $coupon->type ) {
		return;
	}

	$coupon->description = 'Woocommerce ' . $coupon->type . ' coupon';
	$coupon->mv_save();
}

/**
 * Initialization of taxes.
 *
 * @return void
 */
function initialize_taxes() {

	$wc_taxes = Tax::wc_all();
	$mv_taxes = Tax::mv_all();

	foreach ( $wc_taxes as $wc_tax ) {

		$mv_tax = null;

		foreach ( $mv_taxes as $tax ) { // Check if exists.

			if ( $wc_tax->name === $tax->name && $wc_tax->rate === $tax->rate ) {

				$mv_tax = $tax;
				break;
			}
		}

		if ( null !== $mv_tax ) { // Tax already exists in Megaventory.

			/* Update in wooCommerce from Megaventory */

			$mv_tax->wc_id = $wc_tax->wc_id;
			$mv_tax->wc_save();

		} else {

			/* Save to Megaventory from WooCommerce */

			$wc_tax->mv_id = null;
			$wc_tax->mv_save();
		}
	}
}

/**
 * This function will be called every time an order item is created.
 *
 * @param int $order_id As int.
 * @return void
 */
function order_placed( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( ! get_post_meta( $order->get_id(), 'order_sent_to_megaventory', true ) ) {

		$id     = $order->get_customer_id();
		$client = Client::wc_find( $id );

		if ( $client && empty( $client->mv_id ) ) {
			$client->mv_save(); // make sure id exists.
		}

		if ( null === $client || null === $client->mv_id || '' === $client->mv_id ) { // Get guest.

			$client = get_guest_mv_client();
		}

		$returned = array();
		try {

			if ( get_post_meta( $order->get_id(), 'megaventory_order_processing', true ) ) {

				return; // Exit if already processed.
			}
			update_post_meta( $order->get_id(), 'megaventory_order_processing', 1 );

			/* place order through Megaventory API */
			$returned = place_sales_order( $order, $client );

		} catch ( \Error $ex ) {

			delete_post_meta( $order->get_id(), 'megaventory_order_processing' );

			$args = array(
				'type'        => 'error',
				'entity_name' => 'order by: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'entity_id'   => array( 'wc' => $order->get_id() ),
				'problem'     => 'Order not placed in Megaventory.',
				'full_msg'    => $ex->getMessage(),
				'error_code'  => 500,
				'json_object' => '',
			);

			$e = new MVWC_Error( $args );

			return;
		}

		if ( '0' !== $returned['ResponseStatus']['ErrorCode'] || ! array_key_exists( 'mvSalesOrder', $returned ) ) {
			// Error happened. It needs to be reported.

			delete_post_meta( $order->get_id(), 'megaventory_order_processing' );

			$args = array(
				'type'        => 'error',
				'entity_name' => 'order by: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'entity_id'   => array( 'wc' => $order->get_id() ),
				'problem'     => 'Order not placed in Megaventory.',
				'full_msg'    => $returned['ResponseStatus']['Message'],
				'error_code'  => $returned['ResponseStatus']['ErrorCode'],
				'json_object' => $returned['json_object'],
			);

			$e = new MVWC_Error( $args );

			return;
		}

		$args = array(
			'entity_id'          => array(
				'wc' => $order->get_id(),
				'mv' => $returned['mvSalesOrder']['SalesOrderId'],
			),
			'entity_type'        => 'order',
			'entity_name'        => $returned['mvSalesOrder']['SalesOrderTypeAbbreviation'] . ' ' . $returned['mvSalesOrder']['SalesOrderNo'],
			'transaction_status' => 'Insert',
			'full_msg'           => 'The order has been placed to your Megaventory account',
			'success_code'       => 1,
		);

		$e = new MVWC_Success( $args );

		/*
			Hooks:
			woocommerce_new_order hook, comes with no items. Because items are not saved in DB yet..
			woocommerce_thankyou hook, can be ignored if checkout from paypal.
		*/
		update_post_meta( $order->get_id(), 'order_sent_to_megaventory', $returned['mvSalesOrder']['SalesOrderId'] );
		update_post_meta( $order->get_id(), 'megaventory_order_name', $returned['mvSalesOrder']['SalesOrderTypeAbbreviation'] . ' ' . $returned['mvSalesOrder']['SalesOrderNo'] );

		delete_post_meta( $order->get_id(), 'megaventory_order_processing' );
	}
}

/**
 * WooCommerce Order Cancellation handler
 *
 * @param int $order_id as WooCommerce order id.
 * @return void
 */
function order_cancelled_handler( $order_id ) {

	$order = wc_get_order( $order_id );

	// Checking if has been synchronized.
	if ( empty( get_post_meta( $order->get_id(), 'order_sent_to_megaventory', true ) ) ) {

		return;
	}
	try {

		cancel_sales_order( $order );

	} catch ( \Error $ex ) {

		$args = array(
			'type'        => 'error',
			'entity_name' => 'order by: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'entity_id'   => array( 'wc' => $order->get_id() ),
			'problem'     => 'Order Cancellation failed in Megaventory.',
			'full_msg'    => $ex->getMessage(),
			'error_code'  => 500,
			'json_object' => '',
		);

		$e = new MVWC_Error( $args );

		return;
	}

}

// END SYNCHRONIZE FUNCTIONS ON CREATE/UPDATE.

// PLUGIN ACTIVATION TRIGGERS.

register_activation_hook( __FILE__, 'create_plugin_database_table' );

/**
 * DataBase creation.
 *
 * @return void
 */
function create_plugin_database_table() {

	global $table_prefix, $wpdb,$error_table_name;

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

register_activation_hook( __FILE__, 'admin_notice_plugin_activation' );

/**
 * Set admin notice.
 *
 * @return void
 */
function admin_notice_plugin_activation() {

	/* Create transient data */
	set_transient( 'plugin_activation_notice', 1, 5 );
	set_transient( 'api_key_is_set', 0 );

	update_option( 'megaventory_api_key', '' );

	delete_option( 'correct_currency' );
	delete_option( 'correct_connection' );
	delete_option( 'correct_megaventory_apikey' );
	delete_option( 'last_valid_api_key' );

	delete_option( 'mv_session_messages' );
	delete_option( 'mv_location_id_to_abbr' );

}

add_action( 'admin_notices', 'plugin_activation_admin_notice' );

/**
 * Set notice in admin panel when plug in is activated.
 *
 * @return void
 */
function plugin_activation_admin_notice() {
	global $plugin_url;

	/* Check transient */
	if ( get_transient( 'plugin_activation_notice' ) ) {
		?>
		<div class="updated notice is-dismissible">
			<p>The Megaventory plugin is now activated! Visit the Megaventory <a href =<?php echo esc_url( $plugin_url ); ?>>plugin section</a> to enter your API key and initialize the synchronization to get started.</p>
		</div>
		<?php
		delete_transient( 'plugin_activation_notice' );
	}
}

/* CRON */

register_activation_hook( __FILE__, 'cron_activation' );

register_deactivation_hook( __FILE__, 'cron_deactivation' );

/***************** END PLUGIN ACTIVATION TRIGGERS *****************/

/***************** CRON FUNCTIONS *****************/

/**
 * Every 1 min check.
 *
 * @param array $schedules as tasks.
 * @return array
 */
function schedule( $schedules ) {

	$schedules['1min'] = array(
		'interval' => 1 * 60, /* 1 * 60, //1min */
		'display'  => __( 'Every Minute', 'textdomain' ),
	);

	return $schedules;
}

/* Add 1min to cron schedule */
add_filter( 'cron_schedules', 'schedule' ); // @codingStandardsIgnoreLine. It is critical to maintain updated inventory/stock levels in wooCommerce

/* on event, run pull_changes function */
add_action( 'pull_changes_event', 'pull_changes' );

/**
 * The WordPress Cron event callback function.
 *
 * @return void
 */
function pull_changes() {

	if ( ! ( get_option( 'is_megaventory_initialized' ) && get_option( 'correct_currency' ) && get_option( 'correct_connection' ) && get_option( 'correct_megaventory_apikey' ) ) ) {

		return;
	}

	$changes = get_integration_updates();

	if ( count( $changes['mvIntegrationUpdates'] ) === 0 ) { // No need to do anything if there are no changes.

		return;
	}

	foreach ( $changes['mvIntegrationUpdates'] as $change ) {

		if ( 'product' === $change['Entity'] ) {

			if ( 'update' === $change['Action'] ) {

				$mv_product_id  = $change['EntityIDs'];
				$wc_product_ids = get_post_meta_by_key_value( 'mv_id', $mv_product_id );

				if ( empty( $wc_product_ids ) ) {

					remove_integration_update( $change['IntegrationUpdateID'] );
					continue;
				}

				$mv_product = json_decode( $change['JsonData'], true );

				if ( ! is_array( $mv_product ) || ! isset( $mv_product['ProductPurchasePrice'] ) ) {

					remove_integration_update( $change['IntegrationUpdateID'] );
					continue;
				}

				foreach ( $wc_product_ids as $wc_product_id ) {

					$product = Product::wc_find_product( $wc_product_id );

					if ( null === $product ) {

						continue;
					}

					update_post_meta( $wc_product_id, 'purchase_price', str_replace( '.', ',', (string) $mv_product['ProductPurchasePrice'] ) );
				}
			}

			remove_integration_update( $change['IntegrationUpdateID'] );

		} elseif ( 'stock' === $change['Entity'] ) { // stock changed.

			$prods = json_decode( $change['JsonData'], true );

			$mv_location_id_to_abbr = get_option( 'mv_location_id_to_abbr' );

			foreach ( $prods as $prod ) {

				if ( ! array_key_exists( $prod['inventory_id'], $mv_location_id_to_abbr ) ) {
					continue;
				}

				$id = $prod['productID'];

				// An array of product Ids should be expected here to cover also the
				// extreme case of different woocommerce products having the same product SKU.
				// By doing this, whenever there is a stock update from Megaventory,
				// all the products sharing the same SKU will update their woocommerce stock.
				$post_meta_ids = get_post_meta_by_key_value( 'mv_id', $id );

				if ( empty( $post_meta_ids ) ) {
					continue;
				}

				foreach ( $post_meta_ids as $post_meta_id ) {

					$wc_product = wc_get_product( $post_meta_id );

					if ( false === $wc_product || null === $wc_product ) {
						continue;
					}

					Product::sync_stock_update( $wc_product, $prod, $change['IntegrationUpdateID'] );
				}
			}

			$data = remove_integration_update( $change['IntegrationUpdateID'] );

		} elseif ( 'document' === $change['Entity'] ) { // Order changed.

			$mv_document_status_mappings = MV_Constants::MV_DOCUMENT_STATUS_MAPPINGS;

			$wc_order_status_mappings = MV_Constants::MV_DOCUMENT_STATUS_TO_WC_ORDER_STATUS_MAPPINGS;

			$json_data = json_decode( $change['JsonData'], true );

			$order_template = MV_Constants::MV_DEFAULT_SALES_ORDER_TEMPLATE;

			if ( $order_template !== $json_data['DocumentTypeId'] ) {

				continue; // only sales order.
			}

			if ( ! array_key_exists( (int) $json_data['DocumentStatus'], $mv_document_status_mappings ) || ! array_key_exists( $mv_document_status_mappings[ (int) $json_data['DocumentStatus'] ], $wc_order_status_mappings ) ) {
				continue; // If a mapping does not exist, order status should not be modified.
			}

			$status = $mv_document_status_mappings[ (int) $json_data['DocumentStatus'] ];
			$order  = wc_get_order( $json_data['DocumentReferenceNo'] );

			if ( false !== $order ) {

				$order->set_status( $wc_order_status_mappings[ $status ] );

				$order->save();
			}

			$data = remove_integration_update( $change['IntegrationUpdateID'] );
		}
	}
}

/**
 * Get post ids by value and key.
 *
 * @param string $key as post key.
 * @param int    $value as post value.
 * @return array
 */
function get_post_meta_by_key_value( $key, $value ) {

	global $wpdb;
	$meta = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->postmeta . ' WHERE meta_key=%s AND meta_value=%d', array( $key, $value ) ), ARRAY_A ); // db call ok. no-cache ok.

	if ( is_array( $meta ) && ! empty( $meta ) && isset( $meta[0] ) ) {

		$wc_product_ids = array_map(
			function( $meta_data ) {
				return (int) $meta_data['post_id'];
			},
			$meta
		);

		return $wc_product_ids;
	}
	return array();
}
