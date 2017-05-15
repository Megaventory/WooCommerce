<?php
/*
Plugin Name: Megaventory Example
*/

// initialize plugin, etc. these must be here
if (!defined('ABSPATH')) { 
    exit; // Exit if accessed directly
}
require_once( ABSPATH . "wp-includes/pluggable.php" );
require_once("megaventory.php");
require_once("woocommerce.php");

// initialize objects to connect to megaventory and woocommerce
$GLOBALS["MV"] = new Megaventory_sync();
$GLOBALS["WC"] = new Woocommerce_sync();

// this function will be called everytime an order is finalized
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

// main. This code is executed only if woocommerce is an installed and activated plugin.
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	$hook_to = 'woocommerce_thankyou';
	$what_to_hook = 'order_placed';
	$prioriy = 111;
	$num_of_arg = 1;  
	// hook order placed  
	add_action($hook_to, $what_to_hook, $prioriy, $num_of_arg);
	
	//configure admin panel
	add_action('admin_menu', 'plugin_setup_menu');
}

function plugin_setup_menu(){
	add_menu_page('Test Plugin Page', 'Test Plugin', 'manage_options', 'test-plugin', 'test_init');
}
 
// admin panel
function test_init(){
	echo '<form id="sync-mv-wc" method="post">';
	echo '<input type="checkbox" name="with_delete" /> with delete';
	echo '<input type="hidden" name="sync-mv-wc" value="true" />';
	echo '<input type="submit" value="Synchronize Products" />';
	echo '</form>';
	
	
	echo '<form id="sync-categories-form" method="post">';
	echo '<input type="checkbox" name="with_delete" /> with delete';
	echo '<input type="hidden" name="sync-mc-wc" value="true" />';
	echo '<input type="submit" value="Synchronize Products" />';
	echo '</form>';
}

// sync button clicked
if (isset($_POST['sync-mv-wc'])) {
	
	// code will only run correctly on 'init' hook
	// otherwise, some wc variables are not correctly initialized
	add_action('init', 'synchronize_products_mv_wc');
}

function synchronize_products_mv_wc() {
	// synchronize with delete?
	$with_delete = isset($_POST['with_delete']);
	
	// get MV prpducts and categories
	$prods = $GLOBALS["MV"]->get_products();
	$categories = $GLOBALS["MV"]->get_categories();
	
	// synchronize WC products and categories based on retrieved MV products and categories
	$GLOBALS["WC"]->synchronize_categories($categories, $with_delete);
	$GLOBALS["WC"]->synchronize_products($prods, $with_delete); 
}

?>
