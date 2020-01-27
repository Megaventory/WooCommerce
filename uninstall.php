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
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-coupon.php';

$products = Product::wc_all_with_variable();
$clients  = Client::wc_all();
$coupons  = Coupon::WC_all();

foreach ( $products as $product ) {

	$product->wc_reset_mv_data();
}

foreach ( $clients as $client ) {

	if ( null !== $client ) {

		$client->wc_reset_mv_data();
	}
}

delete_option( 'megaventory_api_key' );
delete_option( 'megaventory_api_host' );
delete_option( 'is_megaventory_initialized' );
delete_option( 'woocommerce_guest' );
delete_option( 'default-megaventory-inventory-location' );
delete_option( 'megaventory_initialized_time' );

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
