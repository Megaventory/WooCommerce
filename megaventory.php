<?php
/**
 * Plugin Name: Megaventory
 * Version: 2.5.0
 * Text Domain: megaventory
 * Plugin URI: https://woocommerce.com/products/megaventory-inventory-management/
 * Woo: 5262358:dc7211c200c570406fc919a8b34465f9
 * Description: Integration between WooCommerce and Megaventory.
 *
 * @package megaventory
 * @since 1.0.0
 *
 * WC requires at least: 3.0
 * WC tested up to: 7.9.0
 * Requires at least: 4.4
 * Tested up to: 6.2.2
 * Stable tag: 2.5.0
 * Requires PHP: 7.2
 *
 * Author: Megaventory
 * Author URI: https://megaventory.com/
 * Developer: Megaventory
 * Developer URI: https://github.com/Megaventory/WooCommerce
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2020 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Megaventory;

/**
 * Prevent direct file access to plugin files
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( 300 > (int) ini_get( 'max_execution_time' ) ) {

	set_time_limit( 300 ); // So if the script has already run for 15 seconds and set_time_limit(30) is called, then it would run for a total of 30+15 = 45 seconds.
}

define( 'MEGAVENTORY__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once MEGAVENTORY__PLUGIN_DIR . 'class-megaventory.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'class-megaventory-loader.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'class-api.php';

require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-client.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-coupon.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-included-product.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-integration-updates.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-location.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mv-constants.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mv-order-item.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-error.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-errors.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-success.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-successes.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-order.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-product-bundle.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-product.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-shipping-zones.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-tax.php';

require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/class-address.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/class-admin-notifications.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/class-cron.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/class-tools.php';

require_once MEGAVENTORY__PLUGIN_DIR . 'controllers/class-client.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'controllers/class-coupon.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'controllers/class-integration-updates.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'controllers/class-location.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'controllers/class-logs.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'controllers/class-order.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'controllers/class-product.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'controllers/class-shipping-zones.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'controllers/class-stock.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'controllers/class-synchronization.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'controllers/class-tax.php';

require_once MEGAVENTORY__PLUGIN_DIR . 'admin/class-dashboard.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'admin/template_partials/class-logs.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'admin/template_partials/class-order-settings.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'admin/template_partials/class-settings.php';

require_once MEGAVENTORY__PLUGIN_DIR . 'admin/template_partials/order_sections/class-general-order-settings.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'admin/template_partials/order_sections/class-payment-method-mapping-settings.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'admin/template_partials/order_sections/class-shipping-zone-settings.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'admin/template_partials/order_sections/class-extra-fee-sku-settings.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'admin/template_partials/order_sections/class-order-cron-settings.php';

$megaventory = new Megaventory();

$megaventory->define_javascript_hooks();

$mv_admin_slug = 'megaventory-plugin';

update_option( 'last_valid_api_key', \Megaventory\API::get_last_valid_api_key() );

$home_url   = get_home_url();
$plugin_url = $home_url . '/wp-admin/admin.php?page=megaventory-plugin';

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

add_action( 'init', '\Megaventory\sess_start', 1 );

if ( get_option( 'megaventory_alternate_wp_cron', false ) &&
	get_option( 'correct_megaventory_apikey', false ) &&
	get_option( 'correct_connection', false ) ) {

	define( 'ALTERNATE_WP_CRON', true );
}

$megaventory->is_woocommerce_plugin_enabled();

add_action( 'admin_notices', '\Megaventory\Helpers\Admin_Notifications::sample_admin_notice_error' );

add_action( 'admin_post_megaventory', '\Megaventory\Megaventory::update_apikey_and_host' );

// PLUGIN ACTIVATION TRIGGERS.

register_activation_hook( __FILE__, '\Megaventory\Megaventory::create_plugin_database_table' );

register_activation_hook( __FILE__, '\Megaventory\Megaventory::plugin_activated_reset_basic_options' );

add_action( 'admin_notices', '\Megaventory\Helpers\Admin_Notifications::plugin_activation_admin_notification' );

register_activation_hook( __FILE__, '\Megaventory\Helpers\Cron::cron_activation' );

register_deactivation_hook( __FILE__, '\Megaventory\Helpers\Cron::cron_deactivation' );

add_filter( 'cron_schedules', '\Megaventory\Helpers\Cron::add_cron_schedules' ); // @codingStandardsIgnoreLine. It is critical to maintain updated inventory/stock levels in WooCommerce

/* on event, run pull_changes function */
add_action( 'pull_integration_updates_from_megaventory_event', '\Megaventory\Controllers\Integration_Updates::pull_integration_updates_from_megaventory' );

/* on event, run function to Sync orders to mv */
add_action( Models\MV_Constants::MV_ORDER_SYNC_EVENT, '\Megaventory\Controllers\Order::sync_queued_orders_to_mv' );
