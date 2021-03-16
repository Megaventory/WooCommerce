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
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/order.php';

$products = Product::wc_get_all_products();

Client::delete_default_client();
$clients = Client::wc_get_all_clients();

foreach ( $products as $product ) {

	$product->wc_delete_mv_data();
}

foreach ( $clients as $client ) {

	if ( null !== $client ) {

		$client->wc_reset_mv_data();
	}
}

delete_mv_data_from_orders();

delete_option( 'correct_currency' );
delete_option( 'correct_connection' );
delete_option( 'correct_megaventory_apikey' );
delete_option( 'last_valid_api_key' );

delete_option( 'mv_session_messages' );
delete_option( 'mv_location_id_to_abbr' );

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

global $wpdb;

$return = $wpdb->query( "ALTER TABLE {$wpdb->prefix}woocommerce_tax_rates DROP COLUMN mv_id;" ); // db call ok. no-cache ok. @codingStandardsIgnoreLine.


$error_table_name   = $wpdb->prefix . 'megaventory_errors_log';
$success_table_name = $wpdb->prefix . 'megaventory_success_log';

$apikeys_table_name = $wpdb->prefix . 'megaventory_api_keys';
$notices_table_name = $wpdb->prefix . 'megaventory_notices_log';


$wpdb->query( "DROP TABLE {$wpdb->prefix}megaventory_errors_log" ); // db call ok. no-cache ok. @codingStandardsIgnoreLine.

$wpdb->query( "DROP TABLE {$wpdb->prefix}megaventory_success_log" ); // db call ok. no-cache ok. @codingStandardsIgnoreLine.

$wpdb->query( "DROP TABLE {$wpdb->prefix}megaventory_api_keys" ); // db call ok. no-cache ok. @codingStandardsIgnoreLine.

$wpdb->query( "DROP TABLE {$wpdb->prefix}megaventory_notices_log" ); // db call ok. no-cache ok. @codingStandardsIgnoreLine.
