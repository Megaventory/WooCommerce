<?php
/*
Plugin Name: Megaventory Example
*/

if (!defined('ABSPATH')) { 
    exit; // Exit if accessed directly
}
require_once( ABSPATH . "wp-includes/pluggable.php" );
require_once("classes.php");

function order_placed($order_id){
    $order = wc_get_order($order_id);
    var_dump($order);
    echo "<br><br>";
    
    foreach ($order->get_items() as $value) {
		echo $value;
		$product = new WC_Product($value['product_id']);
		echo $product->get_sku();
	}
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	
	$hook_to = 'woocommerce_thankyou';
	$what_to_hook = 'order_placed';
	$prioriy = 111;
	$num_of_arg = 1;    
	add_action($hook_to, $what_to_hook, $prioriy, $num_of_arg);
	
	add_action('admin_menu', 'test_plugin_setup_menu');
}

function test_plugin_setup_menu(){
        add_menu_page( 'Test Plugin Page', 'Test Plugin', 'manage_options', 'test-plugin', 'test_init' );
}
 
function test_init(){
	echo '<form id="sync-categories-form" method="post">';
	echo '<input type="hidden" name="sync-categories" value="true" />';
	echo '<input type="submit" value="Synchronize Products" />';
	echo '</form>';
}

if (isset($_POST['sync-categories'])) {
	synchronize_categories();
}

function synchronize_categories() {
	$API_KEY = "827bc7518941837b@m65192";
	$megaventory_url = "https://api.megaventory.com/v2017a/json/reply/";
	$jsonurl = $megaventory_url . "ProductCategoryGet" . "?APIKEY=" . $API_KEY;
	$jsoncat = file_get_contents($jsonurl);
	//echo gettype($jsoncat);
	
	//echo "categories";
	//echo $jsoncat;
	
	//$data = json_decode($jsoncat, true);
	//var_dump($data['mvProductCategories']);
	
	$jsonurl = $megaventory_url . "ProductGet" . "?APIKEY=" . $API_KEY;
	$jsonprod = file_get_contents($jsonurl);
	$jsonprod = json_decode($jsonprod, true);
	
	foreach ($jsonprod['mvProducts'] as $product) {
		echo "<div>";
		
		echo $product['ProductID'];
		echo $product['ProductType'];
		echo $product['ProductSKU'];
		echo $product['ProductEAN'];
		echo $product['ProductDescription'];
		echo $product['ProductVersion'];
		echo $product['ProductLongDescription'];
		echo $product['ProductCategoryID'];
		
		//even more after dat
		
		echo "</div>";
		
		create_product($product['ProductSKU'], $product['ProductDescription'], $product['ProductSellingPrice']);
	}

}

function create_product($SKU, $description, $price) {
	$post_id = wp_insert_post( array(
		'post_title' => $SKU,
		'post_content' => $description,
		'post_status' => 'publish',
		'post_type' => "product",
    ));
    
    wp_set_object_terms( $post_id, 'Races', 'product_cat' );
	wp_set_object_terms($post_id, 'simple', 'product_type');

	update_post_meta( $post_id, '_visibility', 'visible' );
	update_post_meta( $post_id, '_stock_status', 'instock');
	update_post_meta( $post_id, 'total_sales', '0');
	update_post_meta( $post_id, '_downloadable', 'yes');
	update_post_meta( $post_id, '_virtual', 'yes');
	update_post_meta( $post_id, '_regular_price', $price );
	//update_post_meta( $post_id, '_sale_price', "0" );
	update_post_meta( $post_id, '_purchase_note', "" );
	update_post_meta( $post_id, '_featured', "no" );
	update_post_meta( $post_id, '_weight', "" );
	update_post_meta( $post_id, '_length', "" );
	update_post_meta( $post_id, '_width', "" );
	update_post_meta( $post_id, '_height', "" );
	update_post_meta($post_id, '_sku', $SKU);
	update_post_meta( $post_id, '_product_attributes', array());
	update_post_meta( $post_id, '_sale_price_dates_from', "" );
	update_post_meta( $post_id, '_sale_price_dates_to', "" );
	update_post_meta( $post_id, '_price', $price );
	update_post_meta( $post_id, '_sold_individually', "" );
	update_post_meta( $post_id, '_manage_stock', "no" );
	update_post_meta( $post_id, '_backorders', "no" );
	update_post_meta( $post_id, '_stock', "" );
}

?>
