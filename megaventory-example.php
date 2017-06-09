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

require_once("cron.php");
require_once("api.php");
require_once("error.php");
require_once("product.php");
require_once("client.php");
require_once("tax.php");
require_once("error.php");

//normal cron would not work
define('ALTERNATE_WP_CRON', true);

//when lock is true, edited product will not update mv products
$save_product_lock = false;
$execute_lock = false; //this lock prevents all sync fro

//////////////// PLUGIN INITIALIZATION //////////////////////////////////////////////////

// main. This code is executed only if woocommerce is an installed and activated plugin.
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	//configure admin panel
	add_action('admin_menu', 'plugin_setup_menu');

	//custom product columns (display stock in product table)
	add_filter('manage_edit-product_columns', 'add_mv_column', 15);
	add_action('manage_product_posts_custom_column', 'column', 10, 2);

	//styles
	add_action('init', 'register_style');
	add_action('admin_enqueue_scripts', 'enqueue_style'); //needed only in admin so far
	//add_action('wp_enqueue_scripts', 'enqueue_style'); //for outside admin if needed, uncomment
	
	//if main currency of wc and mv are different, halt all sync!
	$correct_currency = get_default_currency() == get_option("woocommerce_currency");
	if (!$correct_currency) {
		add_action('admin_notices', 'sample_admin_notice__error'); //warning about error
	}
	
	$can_execute = $correct_currency;
	if ($can_execute) {
		//placed order
		add_action('woocommerce_thankyou', 'order_placed', 111, 1);

		//on add / edit product
		add_action('save_post', 'sync_on_product_save', 10, 3);
	} else {
		$execute_lock = true;
	}
}

//link style.css
function register_style() {
	wp_register_style('mv_style', plugins_url('/style/style.css', __FILE__), false, '1.0.0', 'all');
}
function enqueue_style(){
	wp_enqueue_style('mv_style');
}

//add mv stock column in product table
function add_mv_column($columns){
	//mv stock must be after normal stock
	$temp = array();
	foreach ($columns as $key => $value) {
		$temp[$key] = $value;
		if ($key == "is_in_stock") {
			$temp['mv_stock'] = __('Megaventory Qty');
		}
	}
	$columns = $temp;

	return $columns;
}

//MV stock column in products table
function column($column, $postid) {
    if ($column == 'mv_stock') {
		//get product by id
        $prod = Product::wc_find($postid);
    
		//no stock
		if (!is_array($prod->mv_qty)) {
			echo "No stock";
			return;
		}
		
		//build stock table
		echo '<table class="qty-row">';
		foreach ($prod->mv_qty as $qty) {
			$formatted_string = '<tr>';
			//$qty[inventory name, total, on-hand, non-shipped, non-allocated, non-received]
			$qty = explode(";", $qty);
			$formatted_string .= '<td colspan="2"><span>' . $qty[0] . '</span></td>';
			$formatted_string .= '<td class="mv-tooltip"><span class="tooltiptext">Total</span><span>' . $qty[1] . '</span></td>';
			$formatted_string .= '<td class="mv-tooltip"><span class="tooltiptext">On Hand</span><span class="qty-on-hand">(' . $qty[2] . ')</span></td>';
			$formatted_string .= '<td class="mv-tooltip"><span class="tooltiptext">Non-shipped</span><span class="qty-non-shipped">' . $qty[3] . '</span></td>';
			$formatted_string .= '<td class="mv-tooltip"><span class="tooltiptext">Non-allocated</span><span class="qty-non-allocated">' . $qty[4] . '</span></td>';
			$formatted_string .= '<td class="mv-tooltip"><span class="tooltiptext">Non-received</span<span class="qty-non-received">' . $qty[5] . '</span></td>';

			$formatted_string .= '</tr>';

			echo $formatted_string;
		}
		echo '</table>';
    }
}

function plugin_setup_menu(){
	//plugin tab
	add_menu_page('Test Plugin Page', 'Test Plugin', 'manage_options', 'test-plugin', 'test_init');
}

//////////////////////////////// ADMIN PANEL ///////////////////////////////////////////////////////////////

// admin panel
function test_init(){
	/*
	echo '<form id="sync-mv-wc" method="post">';
	echo '<input type="checkbox" name="with_delete" /> with delete';
	echo '<input type="hidden" name="sync-mv-wc" value="true" />';
	echo '<input type="submit" value="Synchronize Products From MV to WC" />';
	echo '</form>';

	echo '<br><br>';
	*/
	
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
	
	echo '<form id="api_key_form" method="post">';
	echo '<label for="api_key">Megaventory API key: </label>';
	echo '<input type="text" name="api_key" value="' . get_api_key() . '"/>';
	echo '<input type="submit" value="set key"/>';
	echo '</form>';
	
	echo '<br><br>';

	echo '<form id="test" method="post">';
	echo '<input type="hidden" name="test" value="true" />';
	echo '<input type="submit" value="TEST" />';
	echo '</form>';
	
	
	/////// ERROR TABLE ///////
	global $wpdb;
	$table_name = $wpdb->prefix . "mvwc_errors"; 
	$errors = $wpdb->get_results("SELECT * FROM $table_name;");
	
	//var_dump($errors);
	
	usort($errors, "error_cmp");
	$errors = array_reverse($errors);
	
	$table = '
		<table>
			<tr>
				<th>ID</th>
				<th>Entity MV_ID</th>
				<th>Entity WC_ID</th>
				<th>Created at</th>
				<th>Error type</th>
				<th>Entity name</th>
				<th>Problem</th>
				<th>Full message</th>
				<th>Error code</th>
			</tr>';
			foreach ($errors as $error) {
				$str = '<tr>';
				
				$str .= '<td>' . $error->id . '</td>';
				$str .= '<td>' . $error->mv_id . '</td>';
				$str .= '<td>' . $error->wc_id . '</td>';
				$str .= '<td>' . $error->created_at . '</td>';
				$str .= '<td>' . $error->type . '</td>';
				$str .= '<td>' . $error->name . '</td>';
				$str .= '<td>' . $error->problem . '</td>';
				$str .= '<td>' . $error->message . '</td>';
				$str .= '<td>' . $error->code . '</td>';
				
				$str .= '</tr>';
				$table .= $str;
			}
	$table .= '</table>';
	echo $table;
}

//error comparator - sort by date
function error_cmp($a, $b) {
    return strcmp($a->created_at, $b->created_at);
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
} else if (isset($_POST['api_key'])) {
	add_action('init', 'set_api_key');
}

function set_api_key() {
	$key = $_POST['api_key'];	
	update_option("mv_api_key", (string)$key);
}

////////////////////// SYNC //////////////////////////////////////////

//product edit or create
function sync_on_product_save($post_id, $post, $update) {
	global $save_product_lock;
	
	if (get_post_type($post_id) == 'product') {
		if ($save_product_lock) return; //locked, don't do this
		$product = Product::wc_find($post_id);
		if ($product->SKU == null) return; //no details yet provided, no need to save (will only cause errors at this point)
		$response = $product->mv_save();
	}
}


//synchronize from mv to wc. This is deprecated and should be deleted on release
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

//synchronize products from wc to mv
function synchronize_products_wc_mv() {
	// synchronize with delete?
	//with delete is deprecated - product may be deleted from wc, but still phisically exists in the stock
	//client must be informed that it is better to hide wc products, and delete them when the stock is depleted
	$with_delete = isset($_POST['with_delete']);

	//get all wc products and save them to mv
	//products without mv id will find respective product by SKU
	//products that do not exist in MV will be created
	$wc_products = Product::wc_all();

	foreach ($wc_products as $wc_product) {
		$wc_product->mv_save();
	}
}

//push clients from mv to wc
function synchronize_clients() {
	// synchronize with delete?
	$with_delete = isset($_POST['with_delete']);
	//do delete later - discuss with Kostis if it is necessary

	//get all wc clients and save them in mv, creating new ones if needed
	//refer to Client::mv_save() to find out how conflicting names are resolved
	$wc_clients = Client::wc_all();

	foreach ($wc_clients as $wc_client) {
		$wc_client->mv_save();
	}
}

//initial integration of plugin
//creates guest user
//should map MV_IDs by SKU/////////////////////////////////////////////////////////////////////////////////////////////////
function initialize_integration() {
	//Create guest client in wc if does not exist yet.
	$user_name = "WooCommerce_Guest";
	$id = username_exists($user_name);
	if (!$id) {
		$id = wp_create_user("WooCommerce_Guest", "Random Garbage", "WooCommerce@wordpress.com");
		update_user_meta($id, "first_name", "WooCommerce");
		update_user_meta($id, "last_name", "Guest");
	}

	//save the client to mv. undelete if necessary
	$wc_main = Client::wc_find($id);
	$response = $wc_main->mv_save();
	
	//store id for reference
	update_option("woocommerce_guest", (string)$wc_main->WC_ID);
}

function test() {
	echo '<div style="margin:auto;width:50%">';
	/*
	foreach (Product::wc_all_with_variable() as $prod) {
		echo "<br>-------------------<br>";
		echo $prod->SKU;
		if ($prod->SKU == "shoe-013") {
			var_dump(get_post($prod->WC_ID));
			echo "<br><br>";
			var_dump(new WC_Product_Variation($prod->WC_ID));
		}
	}
	*/
	echo "<br>--------<br>";
	$taxes = Tax::wc_all();
	var_dump($taxes);
	echo "<br>--------<br>";
	var_dump(Tax::wc_find($taxes[0]->WC_ID));
	echo "<br>--------<br>";
	var_dump(Tax::mv_all());
	echo "<br>--------<br>";
	var_dump(Tax::mv_find(4));
	echo "<br>--------<br>";
	var_dump(Tax::mv_find_by_name('aasd'));
	
	foreach ($taxes as $tax) {
		echo "<br>///////////////////////</br>";
		var_dump($tax->mv_save());
		echo "<br>///////////////////////</br>";
	}
	
	echo '</div>';
}

// this function will be called everytime an order is finalized
function order_placed($order_id){
    $order = wc_get_order($order_id);
	
	$id = $order->get_customer_id();
	$client = Client::wc_find($id);
	if ($client == null) { //get guest
		$client = get_guest_mv_client();
	}
	
	//place order through API
	$returned = place_sales_order($order, $client);
	
	if ($returned['mvSalesOrder'] == null) {
		//error happened. It needs to be reported
		$args = array
		(
			'type' => 'error',
			'entity_name' => 'oder by: ' . $order->get_billing_first_name() . " " . $order->get_billing_last_name(),
			'entity_id' => array('wc' => $order->get_order_number()),
			'problem' => 'Order not placed in MV',
			'full_msg' => $returned['ResponseStatus']['Message'],
			'error_code' => $returned['ResponseStatus']['ErrorCode']
		);
		$e = new MVWC_Error($args);
	}
}

//////// CRON //////////////////////////////////////////////////////////

register_activation_hook(__FILE__, 'cron_activation');

register_deactivation_hook( __FILE__, 'cron_deactivation');

// add 5min to cron schedule
add_filter('cron_schedules', 'schedule');


// The WP Cron event callback function
function pull_changes() {
	global $execute_lock;
	if ($execute_lock) { //log info about sync being prevented
		$args = array
		(
			'problem' => "Currencies are not matching",
			'full_msg' => "Auto sync failed. Currencies between MV and WC do not match",
			'type' => "fatal"
		);
		$er = new MVWC_Error($args);
		return;
	}
	
	$changes = pull_product_changes();

	if (count($changes) <= 0) { //no need to do anything if there are no changes
		return;
	}

	$mv_categories = Product::mv_get_categories(); //is this needed? - maybe use this when optimizing later

	foreach ($changes['mvIntegrationUpdates'] as $change) {
		if ($change["Entity"] == "product") {
			global $save_product_lock;
			$save_product_lock = true; //prevent changes from mv to be pushed back to mv again (prevent infinite loop of updates)
			
			if ($change["Action"] == "update" or $change["Action"] == "insert") { //new product created, or details changed
				//get product new info
				$product = Product::mv_find($change['EntityIDs']);
				//save new info
				$product->wc_save(); 
				
			} else if ($change["Action"] == "delete") { //delete the product
				//already deleted from mv
				$data = json_decode($change['JsonData'], true);
				$product = Product::wc_find_by_SKU($data['ProductSKU']);
				if ($product != null) $product->wc_destroy();
			}
			
			//delete integration update as it was already resolved
			remove_integration_update($change['IntegrationUpdateID']);
			
			$save_product_lock = false;
			
		} elseif ($change["Entity"] == "stock") { //stock changed
			$id = json_decode($change['JsonData'], true)[0]['productID'];
			$product = Product::mv_find($id);
			$product->sync_stock();
			$data = remove_integration_update($change['IntegrationUpdateID']);
		} elseif ($change['Entity'] == 'document') { //order changed
			global $document_status, $translate_order_status;
			$jsondata = json_decode($change['JsonData'], true);
			if ($jsondata['DocumentTypeAbbreviation'] != "SO") continue; // only salesorder
			$status = $document_status[$jsondata['DocumentStatus']];
			$order = new WC_Order($jsondata['DocumentReferenceNo']);
			$order->set_status($translate_order_status[$status]);
			$order->save();
			$data = remove_integration_update($change['IntegrationUpdateID']);
		}
	}
}

//on event, run pull_changes function
add_action('pull_changes_event', 'pull_changes'); 

//////////////////////////////////////// DB ////////////////////////////

function create_plugin_database_table() {
    global $table_prefix, $wpdb;

    $tblname = 'pin';
    $wp_track_table = $table_prefix . "$tblname ";

    #Check to see if the table exists already, if not, then create it

    if($wpdb->get_var("show tables like '$wp_track_table'") != $wp_track_table) {
		$table_name = $wpdb->prefix . "mvwc_errors"; 
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		  wc_id int,
		  mv_id int,
		  name varchar(40),
		  problem text NOT NULL,
		  message text,
		  type varchar(20),
		  code int,
		  PRIMARY KEY  (id)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		$return = dbDelta($sql);
    }
	
	wp_mail("mpanasiuk@megaventory.com", "activating broom", var_export($return, true)); 
}

register_activation_hook(__FILE__, 'create_plugin_database_table');
 
function remove_db_table() {
    global $table_prefix, $wpdb;
	
	$table_name = $wpdb->prefix . "mvwc_errors"; 
	
	$sql = "DROP TABLE $table_name";
	$wpdb->query($sql);
}
 
register_deactivation_hook(__FILE__, 'remove_db_table');

//////////////////////////////////////////////////////////////////////////////////////////////////////

function sample_admin_notice__error() {
	$class = 'notice notice-error';
	$message = __('MEGAVENTORY ERROR! Currencies in woocommerce and megaventory do not match! Megaventory plugin will halt until this issue is resolved!', 'sample-text-domain');
	$message2 = __('If you are sure that the currency is correct, please refresh until this warning disappears.');
	
	printf('<div class="%1$s"><p>%2$s</p><p>%3$s</p></div>', esc_attr($class), esc_html($message), esc_html($message2)); 
}

?>
