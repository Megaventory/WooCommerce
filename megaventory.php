<?php
/**
 * Plugin Name: Megaventory
 * Version: 2.0.0
 * Text Domain: megaventory
 * Plugin URI: https://github.com/Megaventory/WooCommerce
 * Description: Integration between WooCommerce and Megaventory.
 *
 * @package megaventory
 * @since 1.0.0
 *
 * Woo: 5262358:dc7211c200c570406fc919a8b34465f9
 *
 * Author: Megaventory
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer: Megaventory
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2020 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Prevent direct file access to plugin files
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

set_time_limit( 20000 ); // 20000 seconds

define( 'MEGAVENTORY__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
/**
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/cron.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-error.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-errors.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-product.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-client.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-tax.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-coupon.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-location.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/order.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'admin/admin-template.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/ajax-sync.php';

/*scripts hooks*/
add_action( 'admin_enqueue_scripts', 'ajax_calls' );
add_action( 'wp_ajax_asyncImport', 'async_import' );
add_action( 'wp_ajax_nopriv_asyncImport', 'async_import' );
add_action( 'wp_ajax_changeDefaultMegaventoryLocation', 'change_default_megaventory_location' );
add_action( 'wp_ajax_nopriv_changeDefaultMegaventoryLocation', 'change_default_megaventory_location' );

$mv_admin_slug = 'megaventory-plugin';

/* when lock is true, edited product will not update mv products */
$save_product_lock = false;
$execute_lock      = false; // this lock prevents all sync between WC and MV.

$correct_currency;
$correct_connection;
$correct_key;

$last_valid_api_key = get_last_valid_api_key();
$home_url           = get_home_url();
$plugin_url         = $home_url . '/wp-admin/admin.php?page=megaventory-plugin';

/**
 * Starts the session.
 *
 * @return void
 */
function sess_start() {
	if ( ! headers_sent() && '' === session_id() ) {
		session_start();
	}
}

add_action( 'init', 'sess_start', 1 );

define( 'ALTERNATE_WP_CRON', true );

/**
 * PLUGIN INITIALIZATION
 */
$errs  = array();
$warns = array();
$succs = array();

/**
 * Registration errors.
 *
 * @param string $str1 as string message.
 * @param string $str2 as string message.
 * @return void
 */
function register_error( $str1 = null, $str2 = null ) {

	global $errs;

	if ( isset( $errs ) ) {

		global $errs;

		$message = array( $str1, $str2 );

		array_push( $errs, $message );
	}
}

/**
 * Registration errors.
 *
 * @param string $str1 as string message.
 * @param string $str2 as string message.
 * @return void
 */
function register_warning( $str1 = null, $str2 = null ) {

	global $warns;

	$message = array( $str1, $str2 );

	array_push( $warns, $message );

}

/**
 * Registration successes.
 *
 * @param string $str1 as string message.
 * @param string $str2 as string message.
 * @return void
 */
function register_success( $str1 = null, $str2 = null ) {

	global $succs;

	$message = array( $str1, $str2 );

	array_push( $succs, $message );
}

/**
 * Add to session errors.
 *
 * @return void
 */
function errors_to_session() {

	global $errs;

	$_SESSION['errs'] = $errs;
}

/**
 * Add to session warnings.
 *
 * @return void
 */
function warnings_to_session() {

	global $warns;

	$_SESSION['warns'] = $warns;
}

/**
 * Add to session successes.
 *
 * @return void
 */
function successes_to_session() {

	global $succs;

	$_SESSION['succs'] = $succs;

}

add_action( 'init', 'errors_to_session' );
add_action( 'init', 'warnings_to_session' );
add_action( 'init', 'successes_to_session' );

/**
 * This code is executed only if woocommerce is an installed and activated plugin.
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {

	/* configure admin panel */
	add_action( 'admin_menu', 'plugin_setup_menu' );


	/* custom product columns (display stock in product table) */
	add_filter( 'manage_edit-product_columns', 'add_quantity_column_to_product_table', 15 );
	add_action( 'manage_product_posts_custom_column', 'column', 10, 2 );

	/* styles */
	add_action( 'init', 'register_style' );
	add_action( 'admin_enqueue_scripts', 'enqueue_style' ); // Needed only in admin so far.

	/* halt sync? */
	$can_execute = check_status();

	if ( $can_execute ) {
		/*
			Placed order
			woocommerce_new_order hook, comes with no items data. Do not use it!
		*/
		add_action( 'woocommerce_thankyou', 'order_placed', 111, 1 );

		/* on add / edit product */
		add_action( 'save_post', 'sync_on_product_save', 99, 3 );
		add_action( 'profile_update', 'sync_on_profile_update', 10, 2 );
		add_action( 'user_register', 'sync_on_profile_create', 10, 1 );

		/* tax add the actions */
		add_action( 'woocommerce_tax_rate_updated', 'on_tax_update', 10, 2 );
		add_action( 'woocommerce_tax_rate_added', 'on_tax_update', 10, 2 );

	} else {
		$execute_lock = true;
	}

	/* warning about error,warning,success */
	add_action( 'admin_notices', 'sample_admin_notice_error' );
	add_action( 'admin_notices', 'sample_admin_notice_warning' );
	add_action( 'admin_notices', 'sample_admin_notice_success' );
	add_action( 'admin_notices', 'sample_admin_database_notices' );

} else { /* no woocommerce detected */

	register_error( 'Woocommerce not detected', 'Megaventory plugin cannot operate without woocommerce' );
	add_action( 'admin_notices', 'sample_admin_notice_error' );
}

/**
 * Check plugin connection.
 *
 * @return bool
 */
function check_status() {

	global $correct_currency, $correct_connection, $correct_key,$last_valid_api_key;
	global $connection_value,$currency_value,$key_value,$initialize_value; // Variables with html attributes.
	global $api_key_error_response_status_message;

	$connection_value = '&dash;';
	$key_value        = '&dash;';
	$currency_value   = '&dash;';
	$initialize_value = '&dash;';

	$correct_connection = check_connectivity();

	if ( ! $correct_connection ) {

		register_error( 'Megaventory error! No connection to Megaventory!', 'Check if WordPress and Megaventory servers are online' );

		$connection_value = '&cross;';

		return false;
	}

	$connection_value = '&check;';

	$correct_key = check_key();

	if ( ! get_transient( 'api_key_is_set' ) ) {

		register_warning( 'Welcome to Megaventory plugin!', "Please apply your API key to get started. You can find it in your Megaventory account under 'My Profile' where your user icon is." );

		return false;
	}

	if ( ! $correct_key ) {

		register_error( 'Megaventory error! Invalid API key!', $api_key_error_response_status_message );

		$key_value = '&cross;';

		return false;
	}

	if ( strpos( $api_key_error_response_status_message, 'Administrator' ) === false ) {

		register_error( "Megaventory error! WooCommerce integration needs administrator's credentials!", 'Please contact your Megaventory account administrator.' );

		$key_value = '&cross;';

		return false;
	}

	$integration_enabled = check_if_integration_is_enabled();

	if ( ! $integration_enabled ) {

		register_error( 'Megaventory error! WooCommerce integration is not enabled in your Megaventory account. ', "Please visit your Megaventory account and enable WooCommerce from the Account Integrations' area." );

		$key_value = '&cross;';

		return false;
	}

	$key_value = '&check;';

	$correct_currency = get_default_currency() === get_option( 'woocommerce_currency' );

	if ( ! $correct_currency ) {

		register_error( 'Megaventory error! Currencies in WooCommerce and Megaventory do not match! Megaventory plugin will halt until this issue is resolved!', 'If you are sure that the currency is correct, please refresh until this warning disappears.' );

		$currency_value = '&cross;';

		return false;
	}

	$currency_value = '&check;';

	$initialized = (bool) get_option( 'is_megaventory_initialized' );

	if ( ! $initialized ) {

		register_warning( 'You need to run the Initial Sync before any data synchronization takes place!' );

		$initialize_value = '&cross;';

		return false;
	}

	$initialize_value = '&check;';

	$last_key = get_option( 'megaventory_api_key' );

	$current_database_id_of_api_key = explode( '@', $last_key )[1];

	$last_valid_database_id_of_api_key = explode( '@', $last_valid_api_key )[1];

	if ( trim( $current_database_id_of_api_key ) !== trim( $last_valid_database_id_of_api_key ) ) {

		register_error( 'Megaventory Warning!', 'You have just added an API Key for a new account, please re-install Megaventory plugin.' );
	}

	return true;
}

/**
 * Define the woocommerce_tax_rate_updated callback.
 *
 * @param int    $tax_rate_id as tax id.
 * @param double $tax_rate as tax rate.
 * @return void
 */
function on_tax_update( $tax_rate_id, $tax_rate ) {

	$tax = Tax::wc_find( $tax_rate_id );
	if ( ! $tax ) {
		return;
	}

	$wc_taxes = Tax::wc_all();

	foreach ( $wc_taxes as $wc_tax ) {

		if ( $wc_tax->wc_id === $tax->wc_id ) {
			continue;
		}

		if ( $wc_tax->name === $tax->name && (float) $wc_tax->rate === (float) $tax->rate && $wc_tax->wc_id !== $tax->wc_id ) {
			// if name is taken by different tax.

			$tax->wc_delete();

			array_push( $_SESSION['errs'], array( 'Cannot add a new tax with same name and rate', 'Please try again with different details' ) );

			return;
		}
	}

	/* can add, but cannot change rate afterwards - keep updated with MV */
	$tax2 = Tax::mv_find_by_name_and_rate( $tax->name, $tax->rate );

	if ( null !== $tax->mv_id || null !== $tax2 ) {

		if ( ! $tax2 ) {
			$tax2 = Tax::mv_find( $tax->mv_id );
		}

		$tax2->wc_id = $tax->wc_id;
		$tax         = $tax2;
		$tax->wc_save();
	} else {
		/* creating new tax in MV */

		$tax->description = 'Woocommerce ' . $tax->type . ' tax';
		$saved            = $tax->mv_save();

		if ( ! $saved ) {

			$tax->wc_delete(); // not saved.

		} else {

			$tax->wc_save(); // save with new mv_id.
		}
	}
}

/**
 * Scripts registration.
 *
 * @return void
 */
function ajax_calls() {

	$nonce = wp_create_nonce( 'async-nonce' );

	wp_enqueue_script( 'ajaxCallImport', plugins_url( '/js/ajaxCallImport.js', __FILE__ ), array(), '2.0.0', true );
	wp_enqueue_script( 'ajaxCallInitialize', plugins_url( '/js/ajaxCallInitialize.js', __FILE__ ), array(), '2.0.0', true );

	$nonce_array = array(
		'nonce' => $nonce,
	);

	wp_localize_script( 'ajaxCallImport', 'ajax_object', $nonce_array );
	wp_localize_script( 'ajaxCallInitialize', 'ajax_object', $nonce_array );
}
/**
 * Link CSS.
 *
 * @return void
 */
function register_style() {
	wp_register_style( 'mv_style', plugins_url( '/assets/css/style.css', __FILE__ ), false, '2.0.0', 'all' );
}

/**
 * Enqueue CSS stylesheet.
 *
 * @return void
 */
function enqueue_style() {
	wp_enqueue_style( 'mv_style' );
}

/**
 * Add Megaventory stock column in product table.
 *
 * @param array $columns as table columns.
 * @return array
 */
function add_quantity_column_to_product_table( $columns ) {

	/* Megaventory stock column must be after normal stock column */
	$temp = array();

	foreach ( $columns as $key => $value ) {

		$temp[ $key ] = $value;

		if ( 'is_in_stock' === $key ) {
			$temp['megaventory_stock'] = __( 'Megaventory Quantity' );
		}
	}
	$columns = $temp;

	return $columns;
}

add_action( 'admin_notices', 'sample_admin_notice_error' );

/**
 * Megaventory stock column in product's table.
 *
 * @param array $column as column in product table.
 * @param int   $prod_id as product id.
 * @return void
 */
function column( $column, $prod_id ) {

	if ( 'megaventory_stock' === $column ) {

		$wc_product = wc_get_product( $prod_id );

		if ( 'variable' === $wc_product->get_type() || 'grouped' === $wc_product->get_type() ) {

			// Empty megaventory_stock column for variables.
			return;

		}

		/* get product by id */
		$prod = Product::wc_find( $prod_id );

		/* no stock */
		if ( ! is_array( $prod->mv_qty ) ) {

			echo 'No stock';

			return;
		}
		/* build stock table */
		?>
		<table class="qty-row">
		<?php foreach ( $prod->mv_qty as $qty ) : ?>
			<tr>
			<?php $qty = explode( ';', $qty ); ?>
				<td colspan="2"><span><?php echo esc_attr( $qty[0] ); ?></span></td>
				<td class="mv-tooltip"><span class="tooltiptext">Total</span><span><?php echo esc_attr( $qty[1] ); ?></span></td>
				<td class="mv-tooltip"><span class="tooltiptext">On Hand</span><span class="qty-on-hand">(<?php echo esc_attr( $qty[2] ); ?>)</span></td>
				<td class="mv-tooltip"><span class="tooltiptext">Non-shipped</span><span class="qty-non-shipped"><?php echo esc_attr( $qty[3] ); ?></span></td>
				<td class="mv-tooltip"><span class="tooltiptext">Non-allocated</span><span class="qty-non-allocated"><?php echo esc_attr( $qty[4] ); ?></span></td>
				<td class="mv-tooltip"><span class="tooltiptext">Non-received-POs</span><span class="qty-non-received"><?php echo esc_attr( $qty[5] ); ?></span></td>
				<td class="mv-tooltip"><span class="tooltiptext">Non-received-WOs</span><span class="qty-non-received"><?php echo esc_attr( $qty[6] ); ?></span></td>
			</tr>
		<?php endforeach; ?>
		</table>
		<?php
	}
}
/**
 * Megaventory Plugin page.
 *
 * @return void
 */
function plugin_setup_menu() {

	global $mv_admin_slug;

	add_menu_page( 'Megaventory plugin', 'Megaventory', 'manage_options', $mv_admin_slug, 'generate_admin_page', plugin_dir_url( __FILE__ ) . 'assets/images/mv.png', 30 );
}

/**
 * Check Megaventory API key and host before apply the post.
 *
 * @return void
 */
function do_post() {

	global $mv_admin_slug;

	if ( isset( $_POST['api_key'], $_POST['update-credentials-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['update-credentials-nonce'] ), 'update-credentials-nonce' ) ) {

		set_api_key( trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) );
		set_transient( 'api_key_is_set', true );
	}

	if ( isset( $_POST['api_host'], $_POST['update-credentials-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['update-credentials-nonce'] ), 'update-credentials-nonce' ) ) {
		set_api_host( trim( sanitize_text_field( wp_unslash( $_POST['api_host'] ) ) ) );
	}

	wp_safe_redirect( admin_url( 'admin.php' ) . '?page=' . $mv_admin_slug );
}

add_action( 'admin_post_megaventory', 'do_post' );

/**
 * Error comparator - sort by date.
 *
 * @param object $a as error object.
 * @param object $b as error object.
 * @return int
 */
function error_cmp( $a, $b ) {
	return strcmp( $a->created_at, $b->created_at );
}

/**
 * Synchronize Coupons.
 *
 * @return void
 */
function sync_coupons() {

	remove_filter( 'wp_insert_post_data', 'new_post', 99, 2 );

	$coupons = Coupon::wc_all();
	$all     = 0;
	$added   = 0;

	foreach ( $coupons as $coupon ) {
		++$all;
		if ( $coupon->mv_save() ) {
			++$added;
		}
	}

	register_error( 'Synchronization WooCommerce to Megaventory.', 'Added ' . $added . ' coupons out of ' . $all . ' discounts found in WooCommerce. All other either were already in Megaventory or have overlap with existing records.' );
	add_filter( 'wp_insert_post_data', 'new_post', 99, 2 );
}

/**
 * Setting up Megaventory API key.
 *
 * @param string $key as Megaventory API key.
 * @return void
 */
function set_api_key( $key ) {
	update_option( 'megaventory_api_key', (string) $key );
}

/**
 * Get last inserted Megaventory API key.
 *
 * @return string
 */
function get_last_valid_api_key() {

	global $wpdb;

	/*
		This is reference for quick search for query below: megaventory_api_keys .
	*/

	$apikeys_table_name = $wpdb->prefix . 'megaventory_api_keys';

	$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $apikeys_table_name ), ARRAY_A ); // db call ok. no-cache ok.
	if ( count( $existing_table ) === 0 ) {

		return '';
	}

	$last_valid_apikey_array = $wpdb->get_results(
		"
			SELECT api_key 
			FROM {$wpdb->prefix}megaventory_api_keys 
			ORDER BY id 
			DESC LIMIT 1 "
	); // db call ok; no-cache ok.

	if ( ! empty( $last_valid_apikey_array ) ) {

		$last_valid_apikey = $last_valid_apikey_array[0]->api_key;

	} else {

		$last_valid_apikey = '';
	}

	return $last_valid_apikey;
}

/**
 * Setting up Megaventory API host.
 *
 * @param string $host as Megaventory host.
 * @return void
 */
function set_api_host( $host ) {

	if ( '/' !== substr( $host, -1 ) ) {

		$host .= '/';
	}

	update_option( 'megaventory_api_host', (string) $host );
}

/* SYNC */

/**
 * Product edit or create.
 *
 * @param int     $prod_id as product id.
 * @param WP_Post $post as WP_Post.
 * @param bool    $update is a post update.
 * @return void
 */
function sync_on_product_save( $prod_id, $post, $update ) {

	global $save_product_lock;

	if ( 'product' === get_post_type( $prod_id ) ) {

		/* locked, don't do this */
		if ( $save_product_lock ) {

			return;
		}

		$wc_product = wc_get_product( $prod_id );
		$product    = Product::wc_find( $prod_id );

		if ( 'variable' === $wc_product->get_type() ) {

			Product::update_variable_product_in_megaventory( $wc_product, $product );

		} else {

			$response = $product->mv_save(); // To Fix handle response.
		}
	}
}

/**
 * Updates a client to Megaventory.
 *
 * @param int          $user_id as user id.
 * @param array|Client $old_user_data as user data.
 * @return void
 */
function sync_on_profile_update( $user_id, $old_user_data ) {

	$user = Client::wc_find( $user_id );

	if ( isset( $user ) ) {

		$user->mv_save();
	}
}

/**
 * Insert a client to Megaventory.
 *
 * @param int $user_id as user id.
 * @return void
 */
function sync_on_profile_create( $user_id ) {

	$user = Client::wc_find( $user_id );

	/* we want to save only customer/subscriber users in megaventory */

	if ( isset( $user ) ) {

		$user->mv_save();
	}
}

/**
 * Initial integration of plugin.
 * Creates guest user.
 * Mapping mv_ids by sku.
 *
 * @return void
 */
function map_existing_products_by_sku() {

	$products = Product::wc_all();

	foreach ( $products as $wc_product ) {

		$mv_product = Product::mv_find_by_sku( $wc_product->sku );

		if ( $mv_product ) {

			update_post_meta( $wc_product->wc_id, 'mv_id', $mv_product->mv_id );
		}
	}
}

/**
 * Mapping clients by e-mail.
 *
 * @return void
 */
function map_existing_clients_by_email() {

	$clients = Client::wc_all();

	foreach ( $clients as $wc_client ) {

		$mv_client = false;

		if ( $wc_client ) {

			$mv_client = Client::mv_find_by_email( $wc_client->email );
		}

		if ( $mv_client ) {

			update_user_meta( $mv_client->mv_id, 'mv_id', $mv_client->mv_id );
		}
	}
}

/**
 * Initialization of taxes.
 *
 * @return void
 */
function initialize_taxes() {

	$wc_taxes = Tax::wc_all();
	$mv_taxes = Tax::mv_all();

	foreach ( $wc_taxes as $wc_tax ) {

		$mv_tax = null;

		foreach ( $mv_taxes as $tax ) { // Check if exists.

			if ( $wc_tax->equals( $tax ) ) {

				$mv_tax = $tax;
				break;
			}
		}

		if ( null !== $mv_tax && $wc_tax->rate === $mv_tax->rate ) { // Tax already exists in Megaventory.

			/* Update in wooCommerce from Megaventory */

			$mv_tax->wc_id = $wc_tax->wc_id;
			$mv_tax->wc_save();

		} else {

			/* Save to Megaventory from WooCommerce */

			$wc_tax->mv_id = null;
			$wc_tax->mv_save();
		}
	}
}

/**
 * This function will be called every time an order is finalized.
 *
 * @param int $order_id as order's id.
 * @return void
 */
function order_placed( $order_id ) {

	// Checking if this has already been done avoiding reload.
	if ( get_post_meta( $order_id, 'order_sent_to_megaventory', true ) ) {

		return; // Exit if already processed.
	}

	/*
	The woocommerce_thankyou hook, happens when the customer reloads the order received page
	with this meta information we prevent the order to send multiple times to megaventory
	woocommerce_new_order hook, comes with no items data. Do not use it!
	*/
	update_post_meta( $order_id, 'order_sent_to_megaventory', esc_attr( $order_id ) );

	$order = wc_get_order( $order_id );

	$id     = $order->get_customer_id();
	$client = Client::wc_find( $id );

	if ( $client && ( null === $client->mv_id || '' === $client->mv_id ) ) {
		$client->mv_save(); // make sure id exists.
	}

	if ( null === $client || null === $client->mv_id || '' === $client->mv_id ) { // Get guest.

		$client = get_guest_mv_client();
	}

	/* place order through API */
	$returned = place_sales_order( $order, $client );

	if ( ! array_key_exists( 'mvSalesOrder', $returned ) ) {
		// Error happened. It needs to be reported.

		$args = array(
			'type'        => 'error',
			'entity_name' => 'order by: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'entity_id'   => array( 'wc' => $order->get_order_number() ),
			'problem'     => 'Order not placed in Megaventory.',
			'full_msg'    => $returned['ResponseStatus']['Message'],
			'error_code'  => $returned['ResponseStatus']['ErrorCode'],
			'json_object' => $returned['json_object'],
		);

		$e = new MVWC_Error( $args );
	}
}
/* activation messages */

register_activation_hook( __FILE__, 'admin_notice_plugin_activation' );

/**
 * Set admin notice.
 *
 * @return void
 */
function admin_notice_plugin_activation() {

	/* Create transient data */
	set_transient( 'plugin_activation_notice', true, 5 );
	set_transient( 'api_key_is_set', false );

}

add_action( 'admin_notices', 'plugin_activation_admin_notice' );

/**
 * Set notice in admin panel when plug in is activated.
 *
 * @return void
 */
function plugin_activation_admin_notice() {
	global $plugin_url;

	/* Check transient */
	if ( get_transient( 'plugin_activation_notice' ) ) {
		?>
		<div class="updated notice is-dismissible">
			<p>The Megaventory plugin is now activated! Visit the Megaventory <a href =<?php echo esc_url( $plugin_url ); ?>>plugin section</a> to enter your API key and initialize the synchronization to get started.</p>
		</div>
		<?php
		delete_transient( 'plugin_activation_notice' );
	}
}

/* CRON */

register_activation_hook( __FILE__, 'cron_activation' );

register_deactivation_hook( __FILE__, 'cron_deactivation' );

/**
 * Every 1 mins.
 *
 * @param array $schedules as tasks.
 * @return array
 */
function schedule( $schedules ) {
	$schedules['1min'] = array(
		'interval' => 1 * 60, /* 1 * 60, //1min */
		'display'  => __( 'Every 1 Minutes', 'textdomain' ),
	);
	return $schedules;
}

/* Add 5min to cron schedule */
add_filter( 'cron_schedules', 'schedule' ); // @codingStandardsIgnoreLine. It is critical to maintain updated inventory/stock levels in wooCommerce

/**
 * The WordPress Cron event callback function.
 *
 * @return void
 */
function pull_changes() {

	if ( ! check_connectivity() ) {

		register_error( 'Megaventory automatic synchronization failed', 'No connection to Megaventory API server' );

		return;
	}

	$changes = pull_product_changes();

	if ( count( $changes ) <= 0 ) { // No need to do anything if there are no changes.

		return;
	}

	foreach ( $changes['mvIntegrationUpdates'] as $change ) {
		if ( 'product' === $change['Entity'] ) {

			global $save_product_lock;
			$save_product_lock = true;/*  prevent changes from megaventory to be pushed back to megaventory again (prevent infinite loop of updates) */

			/*
				Only care about update | or $change["Action"] == "insert"
				new product created, or details changed
				get product new info.
			*/
			if ( 'update' === $change['Action'] ) {

				$product = Product::mv_find( $change['EntityIDs'] );

				// save new info.
				// only update synchronized prods so they are not added.
				$product->wc_save( null, false );

			} elseif ( 'delete' === $change['Action'] ) {

				$data    = json_decode( $change['JsonData'], true );
				$product = Product::wc_find_by_sku( $data['ProductSKU'] );

				/* Already deleted from Megaventory */
				if ( null !== $product ) {

					$product->wc_destroy();
				}
			}

			/* delete integration update as it was already resolved */
			remove_integration_update( $change['IntegrationUpdateID'] );
			$save_product_lock = false;

		} elseif ( 'stock' === $change['Entity'] ) { // stock changed.

			$prods = json_decode( $change['JsonData'], true );

			foreach ( $prods as $prod ) {

				$id           = $prod['productID'];
				$post_meta_id = get_post_meta_by_key_value( 'mv_id', $id );

				$wc_product = wc_get_product( $post_meta_id );

				if ( false === $wc_product || null === $wc_product ) {
					continue;
				}

				if ( 'variation' === $wc_product->get_type() ) {

					// Sync variation stock.

					Product::sync_variation_stock( $wc_product, $prod );

				} else {

					// Sync product stock.

					$product = Product::mv_find( $id );

					$product->sync_stock();
				}

				$data = remove_integration_update( $change['IntegrationUpdateID'] );
			}
		} elseif ( 'document' === $change['Entity'] ) { // Order changed.

			global $document_status, $translate_order_status;

			$json_data = json_decode( $change['JsonData'], true );

			if ( 'SO' !== $json_data['DocumentTypeAbbreviation'] ) {

				continue; // only sales order.
			}

			$status = $document_status[ $json_data['DocumentStatus'] ];
			$order  = new WC_Order( $json_data['DocumentReferenceNo'] );

			$order->set_status( $translate_order_status[ $status ] );
			$order->save();

			$data = remove_integration_update( $change['IntegrationUpdateID'] );
		}
	}
}

/**
 * Get post id by value and key.
 *
 * @param string $key as post key.
 * @param int    $value as post value.
 * @return int|bool
 */
function get_post_meta_by_key_value( $key, $value ) {

	global $wpdb;
	$meta = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->postmeta . ' WHERE meta_key=%s AND meta_value=%d', array( $key, $value ) ), ARRAY_A ); // db call ok. no-cache ok.

	if ( is_array( $meta ) && ! empty( $meta ) && isset( $meta[0] ) ) {

		return (int) $meta[0]['post_id'];
	}
	if ( is_object( $meta ) ) {

		return $meta->post_id;
	} else {

		return false;
	}
}

/* on event, run pull_changes function */
add_action( 'pull_changes_event', 'pull_changes' );

/* COUPONS */

/**
 * Discount rules before it be added in Megaventory.
 *
 * @param object $data as discount object.
 * @param array  $postarr as coupon data.
 * @return object
 */
function new_post( $data, $postarr ) {

	/* If it's not a new coupon being added, don't influence the process */
	if ( ( ( 'shop_coupon' !== $data['post_type'] ) || ( 'publish' !== $data['post_status'] ) ) ) {

		return $data;
	}

	/* Rate of a coupon is compulsory in Megaventory, thereby has to be in WooCommerce as well */
	if ( empty( $postarr['coupon_amount'] ) ) {

		register_error( 'Coupon amount', 'You have to specify rate of the coupon.' );

		$data['post_status'] = 'draft';

		return $data;
	}

	if ( ( $postarr['coupon_amount'] <= 0 ) || ( $postarr['coupon_amount'] > 100 ) ) {

		register_error( 'Coupon amount', 'Coupon amount must be a positive number smaller or equal to 100.' );

		$data['post_status'] = 'draft';

		return $data;
	}

	return new_discount( $data, $postarr );
}

add_filter( 'wp_insert_post_data', 'new_post', 99, 2 );

/**
 * Create and add coupon to Megaventory.
 *
 * @param object $data as coupon data.
 * @param array  $postarr coupon data.
 * @return object
 */
function new_discount( $data, $postarr ) {

	$coupon       = new Coupon();
	$coupon->name = $postarr['post_title'];
	$coupon->rate = $postarr['coupon_amount'];

	if ( 'percent' === $postarr['discount_type'] ) {

		$coupon->type = 'percent';

	}

	if ( $coupon->load_corresponding_discount_from_megaventory() ) {

		if ( 'auto-draft' === $postarr['original_post_status'] ) {

			register_error( 'Code restricted', 'Coupon ' . $coupon->name . ' with ' . $coupon->rate . ' rate is already present in Megaventory and can be copied here only through synchronization (available in Megaventory plugin).' );

			$data['post_status'] = 'draft';

		} else {

			register_error( 'Coupon ' . $coupon->name . ' already present in Megaventory.', 'Coupon already present in Megaventory. Its old description: ' . $coupon->description . ' will be updated to ' . $postarr['post_excerpt'] . '.' );

			$coupon->description = $postarr['post_excerpt']; // Overwrite loaded value with user input.
															// Should be whole content here, but for whatever.
															// Reason fields responsible for that in $data.
															// $postarr are always empty.

			$coupon->rate = $postarr['coupon_amount'];  // If the discount is fixed, then rate can be edited.

			$coupon->update_discount_in_megaventory();
		}
	} else {

		$coupon->description = $postarr['post_excerpt']; // 1. Overwrite loaded value.
														// 2. Should be whole content here, but for whatever.
														// reason fields responsible for that in $data.
														// $postarr are always empty.
		if ( $coupon->mv_save() ) {

			register_error( 'Synchronization', 'Coupon ' . $coupon->name . ' has been added to Megaventory.' );

		} else {

			register_error( 'Code restricted', 'Coupon ' . $coupon->name . ' had already been added to your Megaventory account and then deleted. Unfortunately this name cannot be reused. Please choose a different one.' );

			$data['post_status'] = 'draft';
		}
	}

	return $data;
}

/**
 * DataBase creation.
 *
 * @return void
 */
function create_plugin_database_table() {

	global $table_prefix, $wpdb,$error_table_name;

	$error_table_name = $wpdb->prefix . 'megaventory_errors_log';

	$success_table_name = $wpdb->prefix . 'megaventory_success_log';

	$apikeys_table_name = $wpdb->prefix . 'megaventory_api_keys';

	$notices_table_name = $wpdb->prefix . 'megaventory_notices_log';

	/* Check to see if the table exists already, if not, then create it */

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	/* create error table if this does not exists */
	$charset_collate = $wpdb->get_charset_collate();

	$sql_error_table = "CREATE TABLE $error_table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
		wc_id int,
		mv_id int,
		name varchar(200),
		problem text NOT NULL,
		message text,
		type varchar(100),
		code int,
		json_object text,
		PRIMARY KEY  (id)
	) $charset_collate;";

	$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $error_table_name ), ARRAY_A ); // db call ok. no-cache ok.
	if ( count( $existing_table ) === 0 ) {

		dbDelta( $sql_error_table );
	}
	/**
	 * Dont use it, uses esc_like https://developer.wordpress.org/reference/classes/wpdb/esc_like/ !
	 * maybe_create_table.
	 */

	/* create success table if this does not exists */

	$sql_success_table = "CREATE TABLE $success_table_name(
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
		wc_id int,
		mv_id int,
		type varchar(50),
		name varchar(200),
		transaction_status text NOT NULL,
		message text,
		code int,
		PRIMARY KEY  (id)
	) $charset_collate;";

	$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $success_table_name ), ARRAY_A ); // db call ok. no-cache ok.
	if ( count( $existing_table ) === 0 ) {

		dbDelta( $sql_success_table );
	}

	/* create api_keys table if this does not exists */
	$sql_apikeys_table = "CREATE TABLE $apikeys_table_name(
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
		api_key varchar(200),
		PRIMARY KEY  (id)
	) $charset_collate;";

	$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $apikeys_table_name ), ARRAY_A ); // db call ok. no-cache ok.
	if ( count( $existing_table ) === 0 ) {

		dbDelta( $sql_apikeys_table );
	}

	/* create notices table if this does not exists */
	$sql_notices_table = "CREATE TABLE $notices_table_name(
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		type varchar(50),
		message varchar(200),
		PRIMARY KEY  (id)
	) $charset_collate;";

	$existing_table = $wpdb->get_results( $wpdb->prepare( 'show tables like %s', $notices_table_name ), ARRAY_A ); // db call ok. no-cache ok.
	if ( count( $existing_table ) === 0 ) {

		dbDelta( $sql_notices_table );
	}

	$existing_columns = $wpdb->get_col( 'DESC ' . $wpdb->prefix . 'woocommerce_tax_rates', 0 ); // db call ok. no-cache ok.
	$column_found     = false;
	foreach ( $existing_columns as $column_name ) {

		if ( 'mv_id' === $column_name ) {
			$column_found = true;
		}
	}
	if ( ! $column_found ) {

		$wpdb->query( "ALTER TABLE {$wpdb->prefix}woocommerce_tax_rates ADD mv_id INT;" ); // db call ok. no-cache ok. @codingStandardsIgnoreLine.
	}

}

register_activation_hook( __FILE__, 'create_plugin_database_table' );

/**
 * Admin errors.
 *
 * @return void
 */
function sample_admin_notice_error() {

	$class = 'notice notice-error';

	global $pagenow;

	if ( 'admin.php' === $pagenow ) {

		if ( null !== $_SESSION['errs'] && count( $_SESSION['errs'] ) > 0 ) {

			foreach ( $_SESSION['errs'] as $err ) {

				printf( '<div class="%1$s"><p>%2$s</p><p>%3$s</p></div>', esc_attr( $class ), esc_html( $err[0] ), esc_html( $err[1] ) );
			}

			unset( $_SESSION['errs'] );

			$_SESSION['errs'] = array();
		}
	}
}

/**
 * Admin warnings.
 *
 * @return void
 */
function sample_admin_notice_warning() {

	$class = 'notice notice-warning';

	global $pagenow;

	if ( 'admin.php' === $pagenow ) {

		if ( null !== $_SESSION['warns'] && count( $_SESSION['warns'] ) > 0 ) {

			foreach ( $_SESSION['warns'] as $warns ) {
				printf( '<div class="%1$s"><p>%2$s</p><p>%3$s</p></div>', esc_attr( $class ), esc_html( $warns[0] ), esc_html( $warns[1] ) );
			}

			unset( $_SESSION['warns'] );

			$_SESSION['warns'] = array();
		}
	}
}

/**
 * Admin successes.
 *
 * @return void
 */
function sample_admin_notice_success() {
	$class = 'notice notice-success';

	global $pagenow;

	if ( 'admin.php' === $pagenow ) {

		if ( null !== $_SESSION['succs'] && count( $_SESSION['succs'] ) > 0 ) {

			foreach ( $_SESSION['succs'] as $succs ) {

				printf( '<div class="%1$s"><p>%2$s</p><p>%3$s</p></div>', esc_attr( $class ), esc_html( $succs[0] ), esc_html( $succs[1] ) );
			}

			unset( $_SESSION['succs'] );

			$_SESSION['succs'] = array();
		}
	}
}

/**
 * Admin database notices.
 *
 * @return void
 */
function sample_admin_database_notices() {

	$success_class = 'notice notice-success';

	$error_class = 'notice notice-error';

	$notice_class = 'notice notice-info';

	global $wpdb;

	$notices = (array) $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}megaventory_notices_log ORDER BY id ASC LIMIT 50;" ); // db call ok. no-cache ok.

	global $pagenow;

	foreach ( $notices as $notice ) {

		if ( 'success' === $notice->type ) {

			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $success_class ), esc_html( $notice->message ) );
		}

		if ( 'error' === $notice->type ) {

			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $error_class ), esc_html( $notice->message ) );
		}

		if ( 'notice' === $notice->type ) {

			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $notice_class ), esc_html( $notice->message ) );
		}
	}

	$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}megaventory_notices_log" ); // db call ok. no-cache ok.
}
