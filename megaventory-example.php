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
define( 'WP_DEBUG', true );
////////////////////////////////////////////////////

require_once("cron.php");
require_once("api.php");
require_once("error.php");
require_once("product.php");
require_once("client.php");
require_once("tax.php");
require_once("coupon.php");


//normal cron would not work
define('ALTERNATE_WP_CRON', true);

//when lock is true, edited product will not update mv products
$save_product_lock = false;
$execute_lock = false; //this lock prevents all sync fro

$correct_currency = true;
$correct_connection = true;

 
$err_messages = array();

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
	global $correct_currency;
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
		add_action( 'profile_update', 'sync_on_profile_update', 10, 2 );
	} else {
		$execute_lock = true;
	}
	
	//wp_mail("bmodelski@megaventory.com", "product response", "HERE");
	//Coupon::MV_initialise();
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

//ERROR handling
function register_error($str1, $str2) {
	global $err_messages;
	$message = array(__($str1, 'sample-text-domain'), __($str2));
	
		
	$err_messages = array();
	array_push($err_messages, $message);
}

function sample_admin_notice__error() {
	global $err_messages;
	$class = 'notice notice-error';
	
	
	foreach ($err_messages as $msg) {
		printf('<div class="%1$s"><p>%2$s</p><p>%3$s</p></div>', esc_attr($class), esc_html($msg[0]), esc_html($msg[1]));
		
		
		/* ?>
		<div class="notice notice-success is-dismissible">
			<p><?php _e('Congratulations, you did it!', 'shapeSpace'); ?></p>
		</div>
		<?php  */
	} 
		
}

add_action('admin_notices', 'sample_admin_notice__error'); //warning about error

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
		wp_mail("mpanasiuk@megaventory.com", "product saving", var_export($product, true));
		$response = $product->mv_save();
		wp_mail("mpanasiuk@megaventory.com", "product response", var_export($response, true));
	}
}

function sync_on_profile_update($user_id, $old_user_data) {
	$user = Client::wc_find($user_id);
	$user->mv_save();
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
		$wc_product->sync_stock();
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
	/*
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
	*/
	
	$prod = Product::mv_find_by_sku('dvd-w-case-gdf1');
	var_dump($prod);
	echo "<br>-------------------------<br>";
	$prod2 = Product::wc_find_by_SKU('dvd-w-case-gdf1');
	var_dump($prod2);
	echo "<br>-------------------------<br>";
	
	$prod2->mv_save();
	
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


// The WP Cron event callback function'
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


/////////////////////////////// COUPONS ///////////////////////////////////////

function new_post($data, $postarr) { 
	//If it's not a new coupon being added, don't influence the process
	
	if ((($data['post_type'] != 'shop_coupon') or ($data['post_status'] != 'publish')))
		return $data;
	
	
	//Rate of a coupon is compulsory in MV, thereby has to be in WC as well
	if (empty($postarr['coupon_amount'])){ 
		register_error("Coupon amount", "You have to specify rate of the coupon.");
		return;
	}
	 
	if ($postarr['coupon_amount'] <= 0) {
		register_error("Coupon amount", "Coupon amount must be a positive number.");
	}
	
	return new_discount($data, $postarr); 
	
}
         
add_filter('wp_insert_post_data', 'new_post', '99', 2); 

function new_discount($data, $postarr) {
	//create and add coupon to megaventory
	
	wp_mail("bmodelski@megaventory.com", "new_discount", var_export($postarr, true));
	wp_mail("bmodelski@megaventory.com", "new_discount", var_export($data, true));
	
	$coupon = new Coupon;
	$coupon->name = $postarr['post_title'];
	$coupon->rate = $postarr['coupon_amount'];
	
	if (($postarr['discount_type'] == 'fixed_cart') or ($postarr['discount_type'] == 'fixed_product')) {
		$coupon->type = 'fixed';
	} else {
		$coupon->type = 'percent';
	}
	
	if ($coupon->MV_load_corresponding_obj_if_present()) {
		register_error("Coupon already present in db.", "Coupon already present in MV database. (?MessageBox here: do you want to update it's description?). Old description: $coupon->description.");
		$coupon->description = $postarr['excerpt']; // - Overwrite loaded value with user input.
													// - Should be whole content here, but for whatever 
													// reason fields responsible for that in $data, 
													// $postarr are always empty.
													
		$coupon->rate = $postarr['coupon_amount'];  // If the discount is fixed, then rate can be edited.

		$coupon->MV_update();
	} else {
		$coupon->description = $postarr['excerpt']; // 1. Overwrite loaded value.
													// 2. Should be whole content here, but for whatever 
													// reason fields responsible for that in $data, 
													// $postarr are always empty.		
		$coupon->MV_add();
	}		
	return $data; 
}



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
		  name varchar(200),
		  problem text NOT NULL,
		  message text,
		  type varchar(100),
		  code int,
		  PRIMARY KEY  (id)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		$return = dbDelta($sql);
    }
	
}

register_activation_hook(__FILE__, 'create_plugin_database_table');
 
function remove_db_table() {
    global $table_prefix, $wpdb;
	
	$table_name = $wpdb->prefix . "mvwc_errors"; 
	
	$sql = "DROP TABLE $table_name";
	$wpdb->query($sql);
	
	wp_mail("mpanasiuk@megaventory.com", "db_table", "aaaaaa");
}

function reset_mv_data() {
	wp_mail("mpanasiuk@megaventory.com", "mv_data", "bbbbbbb");
	$products = Product::wc_all_with_variable();
	$clients = Client::wc_all();
	
	foreach ($products as $product) {
		$product->wc_reset_mv_data();
	}
	
	foreach ($clients as $client) {
		$client->wc_reset_mv_data();
	}
	
	delete_option("mv_api_key");
}
 
register_deactivation_hook(__FILE__, 'remove_db_table');
register_deactivation_hook(__FILE__, 'reset_mv_data');



?>
