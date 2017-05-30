<?php
/*
Plugin Name: Megaventory Example
*/

////////////////////////////////////////////////////
// initialize plugin, etc. these must be here
if (!defined('ABSPATH')) { 
    exit; // Exit if accessed directly
}
require_once(ABSPATH . "wp-includes/pluggable.php");
////////////////////////////////////////////////////

require_once("api.php");
require_once("product.php");
require_once("client.php");

//normal cro would not work
define('ALTERNATE_WP_CRON', true);

//when lock is true, edited product will not update mv products
$save_product_lock = false;

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
	echo "<br><br> customerID: " . $order->get_customer_id();
	
	$id = $order->get_customer_id();
	$client = Client::wc_find($id);
	if ($client == null) {
		echo "CLIENT WAS NUL";
		$client = get_guest_mv_client();
	}
	
	place_sales_order($order, $client);
	
	var_dump($client);
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
	
	//on add / edit product
	add_action('save_post', 'mp_sync_on_product_save', 10, 3);
	
	//custom product columns
	add_filter('manage_edit-product_columns', 'add_mv_column', 15);
	add_action('manage_product_posts_custom_column', 'column', 10, 2);
	
	//styles
	add_action('init', 'register_style');
	add_action('admin_enqueue_scripts', 'enqueue_style'); //needed only in admin so far
	//add_action('wp_enqueue_scripts', 'enqueue_style'); //needed only in admin so far
	


}

function register_style() {
	wp_register_style('mv_style', plugins_url('/style/style.css', __FILE__), false, '1.0.0', 'all');
}

function enqueue_style(){
   wp_enqueue_style('mv_style');
}

function add_mv_column($columns){
	//mv stock must be after normal stock
	$temp = array();
	foreach ($columns as $key => $value) {
		$temp[$key] = $value;
		if ($key == "is_in_stock") {
			//add now
			$temp['mv_stock'] = __( 'Megaventory Qty'); 
		}
	}
	$columns = $temp;

	return $columns;
}

function column($column, $postid) {
    if ($column == 'mv_stock') {
        echo '<p>Main&nbsp;&nbsp;200&nbsp;0&nbsp;0&nbsp;0</p>';
    }
}

//product edit or create
function mp_sync_on_product_save($post_id, $post, $update) {
	global $save_product_lock;
	if (get_post_type($post_id) == 'product') { 
		if ($save_product_lock) return; //locked, don't do this
		wp_mail('mpanasiuk@megaventory.com', "We out here doing bad shit", $post_id);
		$product = Product::wc_find($post_id);
		$response = $product->mv_save();
	}
}

function plugin_setup_menu(){
	add_menu_page('Test Plugin Page', 'Test Plugin', 'manage_options', 'test-plugin', 'test_init');
}
 
// admin panel
function test_init(){
	echo '<form id="sync-mv-wc" method="post">';
	echo '<input type="checkbox" name="with_delete" /> with delete';
	echo '<input type="hidden" name="sync-mv-wc" value="true" />';
	echo '<input type="submit" value="Synchronize Products From MV to WC" />';
	echo '</form>';
	
	echo '<br><br>';
	
	echo '<form id="sync-wc-mv" method="post">';
	echo '<input type="checkbox" name="with_delete" /> with delete';
	echo '<input type="hidden" name="sync-wc-mv" value="true" />';
	echo '<input type="submit" value="Synchronize Products From WC to MV" />';
	echo '</form>';
	
	echo '<br><br>';
	
	echo '<form id="sync-clients" method="post">';
	//echo '<input type="checkbox" name="with_delete" /> with delete';
	echo '<input type="hidden" name="sync-clients" value="true" />';
	echo '<input type="submit" value="Synchronize Clients" />';
	echo '</form>';
	
	echo '<br><br>';
	
	echo '<form id="initialize" method="post">';
	echo '<input type="hidden" name="initialize" value="true" />';
	echo '<input type="submit" value="Initialize" />';
	echo '</form>';
	
	echo '<br><br>';
	
	echo '<form id="test" method="post">';
	echo '<input type="hidden" name="test" value="true" />';
	echo '<input type="submit" value="TEST" />';
	echo '</form>';

}

// sync button clicked
// code will only run correctly on 'init' hook
// otherwise, some wc variables are not correctly initialized
if (isset($_POST['sync-mv-wc'])) {
	add_action('init', 'synchronize_products_mv_wc');
} else if (isset($_POST['sync-wc-mv'])) {
	add_action('init', 'synchronize_products_wc_mv');
} else if (isset($_POST['sync-clients'])) {
	add_action('init', 'synchronize_clients');
} else if (isset($_POST['initialize'])) {
	add_action('init', 'initialize_integration');
} else if (isset($_POST['test'])) {
	add_action('init', 'test');
}

function synchronize_products_mv_wc() {
	global $save_product_lock;
	$save_product_lock = true;
	$mv_products = Product::mv_all();
	
	$with_delete = isset($_POST['with_delete']);
	if ($with_delete) {
		$wc_products = Product::wc_all();
		
		//if product is in wc_products, but not in mv_products, it can be deleted
		foreach ($wc_products as $wc_product) {
			$delete = true;
			foreach ($mv_products as $mv_product) {
				if ($wc_product->SKU == $mv_product->SKU) { //not to be deleted
					$delete = false;
					continue;
				}
			}
			if ($delete) {
				$wc_product->wc_destroy();
			}
		}
	}
	
	//save new values
	foreach ($mv_products as $mv_product) {
		$mv_product->wc_save();
	}
	
	
	$save_product_lock = false;
}

function synchronize_products_wc_mv() {
	// synchronize with delete?
	$with_delete = isset($_POST['with_delete']);
	
	$wc_products = Product::wc_all();
	
	foreach ($wc_products as $wc_product) {
		$wc_product->mv_save();
	}
}

function synchronize_clients() {
	// synchronize with delete?
	$with_delete = isset($_POST['with_delete']);
	//do delete later - discuss with kostis SupplierClientDeleteAction enum
	
	$wc_clients = Client::wc_all();
	
	foreach ($wc_clients as $wc_client) {
		$wc_client->mv_save();
	}
}


function initialize_integration() {
	$user_name = "WooCommerce_Guest";
	$id = username_exists($user_name);
	if (!$id) {
		$id = wp_create_user("WooCommerce_Guest", "Random Garbage", "WooCommerce@wordpress.com");
		update_user_meta($id, "first_name", "WooCommerce");
		update_user_meta($id, "last_name", "Guest");
	}
	
	$wc_main = Client::wc_find($id);
	
	var_dump($wc_main);
	$response = $wc_main->mv_save();
	var_dump($response);
	if ($response['InternalErrorCode'] == "SupplierClientAlreadyDeleted") {
		// client must be undeleted first and then updated
		Client::mv_undelete($response["entityID"]);
		$response["mvSupplierClient"]["SupplierClientID"] = $response["entityID"];
	}
	
	$id = -1;
	if ($response['mvSupplierClient'] == null) {
		$id = Client::mv_find_by_name("WooCommerce Guest")->MV_ID;
	} else {
		$id = $response["mvSupplierClient"]["SupplierClientID"];
	}
	
	$post = get_page_by_title("guest_id", ARRAY_A, "post");
	var_dump($post);
	if (!$post) {
		echo "POST NOT EXISTS";
		wp_insert_post(array
			(
				'post_title' => "guest_id",
				'post_content' => (string)$id
			)
		);
	} else {
		echo "POST EXISTS";
		$post["post_content"] = $id;
		wp_update_post($post);
	}
}

function get_guest_mv_client() {
	$post = get_page_by_title("guest_id", ARRAY_A, "post");
	$id = $post['post_content'];
	$client = Client::mv_find($id);
	return $client;
}

function test() {
	$clients = Client::mv_all();
	var_dump($clients[0]);
	$clients[0]->mv_destroy();
}

function pull_stock() {
	$products = Product::wc_all();
	var_dump($products);
	
	echo "<br>--------------------------------------<br>";
	
	foreach ($products as $prod) {
		$prod->sync_stock();
	}
}

//////// CRON //////////////////////////////////////////////////////////

// The activation hook
function isa_activation(){
    if(!wp_next_scheduled('pull_changes_event')){
        wp_schedule_event(time(), '5min', 'pull_changes_event');
    }
    if(!wp_next_scheduled('pull_stock_event')){
        wp_schedule_event(time(), '5min', 'pull_stock_event');
    }
}

register_activation_hook(__FILE__, 'isa_activation');

// The deactivation hook
function isa_deactivation(){
    if(wp_next_scheduled('pull_changes_event')){
        wp_clear_scheduled_hook('pull_changes_event');
    }
    if(wp_next_scheduled('pull_stock_event')){
        wp_clear_scheduled_hook('pull_stock_event');
    }
}

register_deactivation_hook( __FILE__, 'isa_deactivation');


// every 5 mins
function schedule($schedules) {
    $schedules['5min'] = array(
            'interval'  => 30, //30 secs for debug //5 * 60, //5min
            'display'   => __('Every 5 Minutes', 'textdomain')
    );
    return $schedules;
}

// add 5min to cron schedule
add_filter('cron_schedules', 'schedule');


// The WP Cron event callback function
function pull_changes() {
	$changes = pull_product_changes();
	
	if (count($changes) <= 0) {
		return;
	}
	
	$mv_categories = Product::mv_get_categories(); //is this needed?
	
	foreach ($changes['mvIntegrationUpdates'] as $change) {
		var_dump($change);
		if ($change["Entity"] == "product") {
			global $save_product_lock;
			$save_product_lock = true;
			if ($change["Action"] == "update" or $change["Action"] == "insert") {
				//get product new info
				$product = Product::mv_find($change['EntityIDs']);
				//save new info
				$product->wc_save();
				
				//delete integration update as it was already resolved
				remove_integration_update($change['IntegrationUpdateID']);
			}
			$save_product_lock = false;
		}
	}
}

//on event, run pull_changes function
add_action('pull_changes_event', 'pull_changes'); //PREVENT INFINITE LOOP IN DEBUG, TALK TO KOSTIS ABOUT THIS!!!!
add_action('pull_stock_event', 'pull_stock'); //PREVENT INFINITE LOOP IN DEBUG, TALK TO KOSTIS ABOUT THIS!!!!

?>
