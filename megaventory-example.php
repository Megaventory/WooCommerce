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

$correct_currency;
$correct_connection;
$correct_key;
$err_messages = array();

function sess_start() {
    if (!session_id())
		session_start();

	if ($_SESSION["errs"] == null) { 
		$_SESSION["errs"] = array();
	} else if (count($_SESSION["errs"]) > 0) {
		foreach ($_SESSION["errs"] as $err) {
			register_error($err[0], $err[1]);
		}
		$_SESSION["errs"] = array();
		wp_mail("mpanasiuk@megaventory.com", "jobwelldone", "jobdone");
	}
}
add_action('init','sess_start');



 
$err_messages = array();

//////////////// PLUGIN INITIALIZATION //////////////////////////////////////////////////

function register_error($str1, $str2) {
	global $err_messages;
	$message = array(__($str1, 'sample-text-domain'), __($str2));
	array_push($err_messages, $message);
}

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
	 
	//halt sync?
	global $correct_currency, $correct_connection, $correct_key;
	$can_execute = true;
	
	$correct_connection = check_connectivity();
	if ($can_execute and !$correct_connection) {
		register_error('MEGAVENTORY ERROR! No connection to megaventory!', 'Check if Wordpress and Megaventory servers are online');
		$can_execute = false;
	}
	
	$correct_key = check_key();
	if ($can_execute and !$correct_key) {
		register_error("MEGAVENTORY Error! Invalid API KEY", "Please check if the API key is correct");
		$can_execute = false;
	}
	
	$correct_currency = get_default_currency() == get_option("woocommerce_currency");
	if ($can_execute and !$correct_currency) {
		register_error('MEGAVENTORY ERROR! Currencies in woocommerce and megaventory do not match! Megaventory plugin will halt until this issue is resolved!', 'If you are sure that the currency is correct, please refresh until this warning disappears.');
		$can_execute = false;
	}
	
	if ($can_execute) {
		//placed order
		add_action('woocommerce_thankyou', 'order_placed', 111, 1);

		//on add / edit product
		add_action('save_post', 'sync_on_product_save', 10, 3);
		add_action('profile_update', 'sync_on_profile_update', 10, 2);
		
		//tax
		// add the action 
		add_action('woocommerce_tax_rate_updated', 'on_tax_update', 10, 2); 
		add_action('woocommerce_tax_rate_added', 'on_tax_update', 10, 2); 
	} else {
		$execute_lock = true;
	}
	add_action('admin_notices', 'sample_admin_notice__error'); //warning about error
} else { //no woocommerce detected
	//untested
	register_error('Woocommerce not detected', 'Megaventory plugin cannot operate without woocommerce');
	add_action('admin_notices', 'sample_admin_notice__error'); //warning about error
}

// define the woocommerce_tax_rate_updated callback 
function on_tax_update($tax_rate_id, $tax_rate) { 
	//wp_mail("mpanasiuk@megaventory.com", "old_tax", var_export($tax_rate, true));
	//wp_mail("mpanasiuk@megaventory.com", "new_tax", var_export(Tax::wc_find($tax_rate_id), true));
	
	$tax = Tax::wc_find($tax_rate_id);
	if (!$tax) return;
	
	$wc_taxes = Tax::wc_all();
	
	$can_save = true;
	foreach ($wc_taxes as $wc_tax) {
		if ($wc_tax->WC_ID == $tax->WC_ID) continue;
		wp_mail("mpanasiuk@megaventory.com", "sesja", var_export($_SESSION, true));
		
		if ($wc_tax->name == $tax->name and (float)$wc_tax->rate == (float)$tax->rate and $wc_tax->WC_ID != $tax->WC_ID) { //if name is taken by different tax
			$tax->wc_delete();
			array_push($_SESSION["errs"], array("Cannot add a new tax with same name and rate", "Please try again with different details"));
			wp_mail("mpanasiuk@megaventory.com", "sesja2", var_export($_SESSION, true));
			return;
		}
	}
	
	//can add, but cannot change rate afterswards - keep updated with MV
	$tax2 = null;
	if ($tax->MV_ID != null or $tax2 = Tax::mv_find_by_name_and_rate($tax->name, $tax->rate)) {
		if (!$tax2) 
			$tax2 = Tax::mv_find($tax->MV_ID);
		
		$tax2->WC_ID = $tax->WC_ID;
		$tax = $tax2;
		$tax->wc_save();
	} else { //creating new tax in MV
		$saved = $tax->mv_save();
		if (!$saved) {
			$tax->wc_delete(); //not saved
		}
	}
	
	return;
	
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
	add_menu_page('Megaventory plugin', 'Megaventory', 'manage_options', 'megaventory-plugin', 'panel_init', plugin_dir_url( __FILE__ ).'mv3.png');
}

//////////////////////////////// ADMIN PANEL ///////////////////////////////////////////////////////////////

// admin panel
function panel_init(){
	/*
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
	*/
	
	
	/////// ERROR TABLE ///////
	global $wpdb;
	$table_name = $wpdb->prefix . "mvwc_errors"; 
	$errors = $wpdb->get_results("SELECT * FROM $table_name;");
	
	//var_dump($errors);
	
	usort($errors, "error_cmp");
	$errors = array_reverse($errors);
	
	$error_table = '
		<h2>Error log</h2>
		<table id="error-log" class="wp-list-table widefat fixed striped posts">
			<tr>
				<th id="id">id</th>
				<th id="id">mv id</th>
				<th id="id">wc id</th>
				<th>Created at</th>
				<th id="type">Error type</th>
				<th>Entity name</th>
				<th id="problem">Problem</th>
				<th id="full-msg">Full message</th>
				<th id="code">Code</th>
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
				$error_table .= $str;
			}
	$error_table .= '</table>';
	
	$taxes = Tax::wc_all();
	$tax_table = '
		<h2>Taxes</h2>
		<table id="taxes" class="wp-list-table widefat fixed striped posts">
			<tr>
				<th id="id">id</th>
				<th>mv id</th>
				<th>name</th>
				<th>rate</th>
			</tr>';
			foreach ($taxes as $tax) {
				$str = '<tr>';
				
				$str .= '<td>' . $tax->WC_ID . '</td>';
				$str .= '<td>' . $tax->MV_ID . '</td>';
				$str .= '<td>' . $tax->name . '</td>';
				$str .= '<td>' . $tax->rate . '</td>';
				
				$str .= '</tr>';
				$tax_table .= $str;
			}
			
	$tax_table .= "</table>";
	
	global $correct_connection, $correct_currency, $correct_key;
	$initialized = (bool)get_option("mv_initialized");
	$html = '
		<div class="mv-admin">
		<h1>Megaventory</h1>
		
		<div class="mv-row row-main">
			<div class="mv-col">
				<h3>Status</h3>
				<div class="mv-status">
					<ul class="mv-status">
						<li class="mv-li-left">Connection:</li><li>'.($correct_connection ? '&check;' : '&cross;').'</li>
						<li class="mv-li-left">Key: </li><li>'.($correct_key ? '&check;' : '&cross;').'</li>
						<li class="mv-li-left">Currency: </li><li>'.($correct_currency ? '&check;' : '&cross;').'</li>
						<li class="mv-li-left">Initialized: </li><li>'.($initialized ? '&check;' : '&cross;').'</li>
					</ul>
				</div>
			</div>
			<div class="mv-col">
				<h3>Setup</h3>
				<div class="mv-row">
					<div class="mv-form">
						<form id="options" method="post">
							<div class="mv-form-body">
								<p>
									<label for="api_key">Megaventory API key: </label>
									<input type="text" name="api_key" value="' . get_api_key() . '"/>
								</p>
								</p>
									<label for="api_key">Megaventory API host: </label>
									<input type="text" name="api_host" value="' . get_api_host() . '"/>
								</p>
							</div>
							<div class="mv-form-bottom">
								<input type="submit" value="update"/>
							</div>
						</form>
					</div>
				</div>
			</div>
			<div class="mv-col">
				<h3>Initialization</h3>
				<div class="wrap-init">
				
					<form id="initialize" method="post">
						<input type="hidden" name="initialize" value="true" />
						<input type="submit" value="'.($initialized ? 'Reinitialize' : 'Initialize').'" />
					</form>
					<form id="sync-wc-mg" method="post">
						<input type="hidden" name="sync-wc-mv" />
						<input type="submit" value="Import Products from WC to MV" />
					</form>
					<form id="sync-clients" method="post">
						<input type="hidden" name="sync-clients" value="true" />
						<input type="submit" value="Import Clients from WC to MV" />
					</form>
					<form id="sync-coupons" method="post">
						<input type="hidden" name="sync-coupons" value="true" />
						<input type="submit" value="Synchronize coupons" />
					</form>
				</div>
			</div>
		</div>
		
		<div class="mv-row row-main">
			'.$error_table.'
		</div>
		
		<div class="mv-row row-main">
			'.$tax_table.'
		</div>
		
		<div class="mv-row row-main">
			<!--<div class="mv-col">-->
				<form id="test" method="post">
					<input type="hidden" name="test" value="true" />
					<input type="submit" value="TEST" />
				</form>
			<!--</div>-->
		</div>
		
		</div>
	';
	
	echo $html;
	
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
}
if (isset($_POST['sync-wc-mv'])) {
	add_action('init', 'synchronize_products_wc_mv');
}
if (isset($_POST['sync-clients'])) {
	add_action('init', 'synchronize_clients');
}
if (isset($_POST['initialize'])) {
	add_action('init', 'initialize_integration');
}
if (isset($_POST['test'])) {
	add_action('init', 'test');
}
if (isset($_POST['api_key'])) {
	add_action('init', 'set_api_key');
}
if (isset($_POST['api_host'])) {
	add_action('init', 'set_api_host');
}
if (isset($_POST['sync-coupons'])) {
	add_action('init', 'sync_coupons');
}

function sync_coupons() {		
	remove_filter('wp_insert_post_data', 'new_post', 99, 2); 
	
	register_error("Synchronization MV to WC.", Coupon::MV_to_WC());
	$coupons = Coupon::WC_all();
	
	$all = 0;
	$added = 0;
	
	foreach ($coupons as $coupon) {
		$all = $all + 1;
		if ($coupon->MV_save())
			$added = $added + 1;
	}
	register_error("Synchronization WC to MV.", "Added " . $added . " coupons out of " . $all . " discounts found in WC.");
	add_filter('wp_insert_post_data', 'new_post', 99, 2); 
}

function set_api_key() {
	$key = $_POST['api_key'];	
	update_option("mv_api_key", (string)$key);
}

function set_api_host() {
	$host = $_POST['api_host'];	
	if(substr($host, -1) != '/') {
		$host .= '/';
	}
	update_option("mv_api_host", (string)$host);
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
function map_existing_products_by_SKU() {
	$products = Product::wc_all();
	
	foreach ($products as $wc_product) {
		$mv_product = Product::mv_find_by_sku($wc_product->SKU);
		if ($mv_product) {
			update_post_meta($wc_product->WC_ID, 'MV_ID', $mv_product->MV_ID);
		}
	}
}

function map_existing_clients_by_email() {
	$clients = Client::wc_all();
	
	foreach ($clients as $wc_client) {
		$mv_client = Client::mv_find_by_email($wc_client->email);
		if ($mv_client) {
			echo $mv_client->email;
			update_user_meta($mv_client->MV_ID, 'MV_ID', $mv_client->MV_ID);
		}
	}
}

function initialize_taxes() {
	$wc_taxes = Tax::wc_all();
	$mv_taxes = Tax::mv_all();
	
	
	foreach ($wc_taxes as $wc_tax) {
		$mv_tax = null;
		foreach ($mv_taxes as $tax) { //check if exists
			if ($wc_tax->equals($tax)) {
				$mv_tax = $tax;
				break;
			}
		}
		
		if ($mv_tax != null and $wc_tax->rate == $mv_tax->rate) { //tax already exists in MV
			//update in wc from mv
			$mv_tax->WC_ID = $wc_tax->WC_ID;
			$mv_tax->wc_save();
			
			echo "saving: ";
			var_dump($mv_tax);
			echo "<br>----------------<br>";
		} else {
			//save to mv from wc
			$wc_tax->MV_ID = null;
			$wc_tax->mv_save();
		}
	}
	
	var_dump($wc_taxes);
	echo "<br>----------<br>";
	var_dump($mv_names);
}

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
	
	map_existing_products_by_SKU();
	map_existing_clients_by_email();
	initialize_taxes();
	
	
	//store id for reference
	update_option("woocommerce_guest", (string)$wc_main->WC_ID);
	update_option("mv_initialized", (string)true);
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
	//$tax = new Tax();
	//$tax->name = "tax";
	//$tax->rate = 20;
	//$tax->description = "hello";
	//$tax->mv_save();
	//initialize_taxes();
	
	//initialize_taxes();
	
	//$t = new Tax();
	//$t->name = "ooooo3";
	//$t->rate = 79;
	//$t->mv_save();
	//$t->wc_save();
	
	//var_dump($t);
	
	/*
	$tax1 = new Tax();
	$tax1->name = "new mv tax4";
	$tax1->description = "new mv tax desc";
	$tax1->rate = 20.0;
	$tax1->mv_save();
	
	$tax2 = new Tax();
	$tax2->name = "new wc tax4";
	$tax2->rate = 20.0;
	$tax2->wc_save();
	
	echo "<br>-------- IDS --------<br>";
	echo $tax1->MV_ID;
	echo "<br>";
	echo $tax2->WC_ID;
	echo "<br>----------------------<br>";
	*/
	
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
			
			if ($change["Action"] == "update" /* only care about update | or $change["Action"] == "insert" */) { //new product created, or details changed
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
		$data['post_status'] = 'draft';
		return $data;
	}
	 
	if (($postarr['coupon_amount'] <= 0) or ($postarr['coupon_amount'] > 100)) {
		register_error("Coupon amount", "Coupon amount must be a positive number smaller or equal to 100.");
		$data['post_status'] = 'draft';
		return $data;
	}
	
	return new_discount($data, $postarr); 
}
         
add_filter('wp_insert_post_data', 'new_post', 99, 2); 

function new_discount($data, $postarr) {
	//create and add coupon to megaventory
	
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
		
		wp_mail("bmodelski@megaventory.com", "new_discount", "IN AS FOUND");
		if ($postarr['original_post_status'] == 'auto-draft') {
			register_error("Code restricted", "Coupon " . $coupon->name . " with " . $coupon->rate . " rate is already present in Megaventory and can be copied here only through synchronisation (available in Megaventory plugin)."); 			
			$data['post_status'] = 'draft';
		} else {
			register_error("Coupon " . $coupon->name . " already present in MV db.", "Coupon already present in MV database. Its old description: " . $coupon->description . " will be updated to " . $postarr['post_excerpt'] . "."); 
			$coupon->description = $postarr['post_excerpt']; // - Overwrite loaded value with user input.
														// - Should be whole content here, but for whatever 
														// reason fields responsible for that in $data, 
														// $postarr are always empty.
														
								
			$coupon->rate = $postarr['coupon_amount'];  // If the discount is fixed, then rate can be edited.

			$coupon->MV_update();
		}
	} else {
		
		$coupon->description = $postarr['post_excerpt']; // 1. Overwrite loaded value.
													// 2. Should be whole content here, but for whatever 
													// reason fields responsible for that in $data, 
													// $postarr are always empty.		
		if ($coupon->MV_save()) {
			register_error("Synchronization", "Coupon " . $coupon->name . " has been added to Megaventory.");
		} else {
			register_error("Code restricted", "Coupon " . $coupon->name . " had already been added to your Megaventory account and then deleted. Unfortunately this name ccanot be reused. Please choose a different one."); 
			$data['post_status'] = 'draft';
		}
	}		 
	return $data; 
}



//////////////////////////////////////// DB ////////////////////////////
function create_plugin_database_table() {
    global $table_prefix, $wpdb;

    $tblname = 'pin'; 
    $wp_track_table = $table_prefix . "$tblname ";

    #Check to see if the table exists already, if not, then create it

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
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

		$return = dbDelta($sql);
    }
	
	$sql = "ALTER TABLE {$wpdb->prefix}woocommerce_tax_rates ADD mv_id INT;";
	$return = $wpdb->query($sql);
	
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
	delete_option("mv_api_host");
	delete_option("mv_initialized");
	
	global $wpdb;
	$sql = "ALTER TABLE {$wpdb->prefix}woocommerce_tax_rates DROP COLUMN mv_id;";
	$return = $wpdb->query($sql);
}
 
register_deactivation_hook(__FILE__, 'remove_db_table');
register_deactivation_hook(__FILE__, 'reset_mv_data');


function sample_admin_notice__error() {
	global $err_messages;
	$class = 'notice notice-error';
	
	foreach ($err_messages as $msg) {
		printf('<div class="%1$s"><p>%2$s</p><p>%3$s</p></div>', esc_attr($class), esc_html($msg[0]), esc_html($msg[1]));
		
	} 
}

?>
