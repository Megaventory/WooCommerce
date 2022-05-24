<?php
/**
 * This file contains helper methods for shipping zones integration
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

/* This file contains helper methods for shipping zones integration */

namespace Megaventory\Controllers;

require_once MEGAVENTORY__PLUGIN_DIR . 'class-api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mv-constants.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mv-order-item.php';

/**
 * Shipping Zones controller.
 */
class Shipping_Zones {

	/**
	 * Change shipping zones setting.
	 *
	 * @return void
	 */
	public static function megaventory_change_shipping_zones_option() {

		if ( isset( $_POST['newStatus'], $_POST['async-nonce'] ) &&
			wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$status = (bool) ( sanitize_text_field( wp_unslash( $_POST['newStatus'] ) ) === 'true' );

			update_option( \Megaventory\Models\MV_Constants::SHIPPING_ZONES_ENABLE_OPT, $status );

			wp_send_json_success( array( 'success' => true ), 200 );
		} else {

			wp_send_json_error( array( 'success' => false ), 200 );
		}

		wp_die();
	}

	/**
	 * Saves shipping zone priority array to db.
	 *
	 * @return void
	 */
	public static function megaventory_save_shipping_zones_priority_order() {

		if ( isset( $_POST['shipping-priorities'], $_POST['async-nonce'] ) &&
			wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$zone_priority_serialized = sanitize_text_field( wp_unslash( $_POST['shipping-priorities'] ) );

			$zone_priority = json_decode( $zone_priority_serialized, true );

			update_option( \Megaventory\Models\MV_Constants::SHIPPING_ZONES_PRIORITY_OPT, $zone_priority );

			wp_send_json_success( array( 'success' => true ), 200 );

		} else {

			wp_send_json_error( array( 'success' => false ), 200 );
		}

		wp_die();
	}
}

