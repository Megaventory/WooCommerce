<?php
/**
 * Uninstall helper.
 *
 * @package megaventory
 * @since 1.0.0
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Imports.
 */
if ( ! defined( 'MEGAVENTORY__PLUGIN_DIR' ) ) {
	define( 'MEGAVENTORY__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-product.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-client.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mv-constants.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-order.php';

$products = \Megaventory\Models\Product::wc_get_all_products();

\Megaventory\Models\Client::delete_default_client();
$clients = \Megaventory\Models\Client::wc_get_all_clients();

foreach ( $products as $product ) {

	$product->wc_delete_mv_data();
}

foreach ( $clients as $client ) {

	if ( null !== $client ) {

		$client->wc_reset_mv_data();
	}
}

\Megaventory\Models\Order::delete_mv_data_from_orders();

delete_option( 'correct_currency' );
delete_option( 'correct_connection' );
delete_option( 'correct_megaventory_apikey' );
delete_option( 'do_megaventory_requests' );
delete_option( 'mv_account_expired' );
delete_option( 'mv_account_admin' );
delete_option( 'mv_woo_integration_enabled' );
delete_option( 'new_mv_api_key' );
delete_option( 'last_valid_api_key' );

delete_option( 'mv_session_messages' );
delete_option( \Megaventory\Models\MV_Constants::MV_LOCATION_ID_TO_ABBREVIATION );

delete_option( 'megaventory_api_key' );
delete_option( 'megaventory_api_host' );
delete_option( 'woocommerce_guest' );
delete_option( 'default-megaventory-inventory-location' );

delete_option( 'is_megaventory_initialized' );
delete_option( 'are_megaventory_products_synchronized' );
delete_option( 'are_megaventory_clients_synchronized' );
delete_option( 'are_megaventory_coupons_synchronized' );
delete_option( 'is_megaventory_stock_adjusted' );
delete_option( 'megaventory_alternate_wp_cron' );

delete_option( 'megaventory_initialized_time' );
delete_option( 'megaventory_products_synchronized_time' );
delete_option( 'megaventory_clients_synchronized_time' );
delete_option( 'megaventory_coupons_synchronized_time' );
delete_option( 'megaventory_stock_synchronized_time' );
delete_option( 'megaventory_adjustment_document_status_option' );

delete_option( \Megaventory\Models\MV_Constants::SHIPPING_ZONES_ENABLE_OPT );
delete_option( \Megaventory\Models\MV_Constants::SHIPPING_ZONES_PRIORITY_OPT );
delete_option( \Megaventory\Models\MV_Constants::MV_ORDERS_TO_SYNC_OPT );
delete_option( \Megaventory\Models\MV_Constants::MV_ORDER_DELAY_CHOICE_OPT );
delete_option( \Megaventory\Models\MV_Constants::MV_ORDER_DELAY_SECONDS_CHOICE_OPT );
delete_option( \Megaventory\Models\MV_Constants::MV_EXCLUDED_LOCATION_IDS_OPT );
delete_option( \Megaventory\Models\MV_Constants::MV_AUTO_ASSIGN_BATCH_NUMBERS_OPT );

global $wpdb;

$tax_rates_table     = "{$wpdb->prefix}woocommerce_tax_rates";
$tax_rates_mv_column = 'mv_id';

$existing_columns = $wpdb->get_col( 'DESC ' . $wpdb->prefix . 'woocommerce_tax_rates', 0 ); // phpcs:ignore 
$column_found     = false;

foreach ( $existing_columns as $column_name ) {

	if ( 'mv_id' === $column_name ) {
		$column_found = true;
		break;
	}
}

if ( $column_found ) {

	$return = $wpdb->query( $wpdb->prepare( 'ALTER TABLE %1s DROP COLUMN %1s;', array( $tax_rates_table, $tax_rates_mv_column ) ) ); // phpcs:ignore
}


$error_table_name   = "{$wpdb->prefix}megaventory_errors_log";
$success_table_name = "{$wpdb->prefix}megaventory_success_log";
$apikeys_table_name = "{$wpdb->prefix}megaventory_api_keys";
$notices_table_name = "{$wpdb->prefix}megaventory_notices_log";

$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $error_table_name ), ARRAY_A ); // phpcs:ignore
if ( 0 !== count( $existing_table ) ) {

	$wpdb->query( $wpdb->prepare( 'DROP TABLE %1s', $error_table_name ) ); // phpcs:ignore
}

$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $success_table_name ), ARRAY_A ); // phpcs:ignore
if ( 0 !== count( $existing_table ) ) {

	$wpdb->query( $wpdb->prepare( 'DROP TABLE %1s', $success_table_name ) ); // phpcs:ignore
}

$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $apikeys_table_name ), ARRAY_A ); // phpcs:ignore
if ( 0 !== count( $existing_table ) ) {

	$wpdb->query( $wpdb->prepare( 'DROP TABLE %1s', $apikeys_table_name ) ); // phpcs:ignore
}

$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $notices_table_name ), ARRAY_A ); // phpcs:ignore
if ( 0 !== count( $existing_table ) ) {

	$wpdb->query( $wpdb->prepare( 'DROP TABLE %1s', $notices_table_name ) ); // phpcs:ignore
}
