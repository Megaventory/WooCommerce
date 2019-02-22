<?php
require_once("product.php");
require_once("client.php");
require_once("coupon.php");

$products = Product::wc_all_with_variable();
$clients = Client::wc_all();
$coupons = Coupon::WC_all();

foreach ($products as $product) {
	$product->wc_reset_mv_data();
}

foreach ($clients as $client) {
	if($client!=null)
		$client->wc_reset_mv_data();
}

delete_option("mv_api_key");
delete_option("mv_api_host");
delete_option("mv_initialized");
delete_option("woocommerce_guest");

global $wpdb;
$sql = "ALTER TABLE {$wpdb->prefix}woocommerce_tax_rates DROP COLUMN mv_id;";
$return = $wpdb->query($sql);

$error_table_name = $wpdb->prefix . "mvwc_errors";
$success_table_name = $wpdb->prefix . "success_log";;
$apikeys_table_name = $wpdb->prefix . "api_keys";
$notices_table_name = $wpdb->prefix . "notices";


$sql = "DROP TABLE $error_table_name";
$wpdb->query($sql);
$sql = "DROP TABLE $success_table_name";
$wpdb->query($sql);
$sql = "DROP TABLE $apikeys_table_name";
$wpdb->query($sql);
$sql = "DROP TABLE $notices_table_name";
$wpdb->query($sql);
?>