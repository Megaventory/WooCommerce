<?php
/**
 * Location controller.
 *
 * @package megaventory
 * @since 2.3.0
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Megaventory\Controllers;

/**
 * Controller for Inventory Locations.
 */
class Location {

	/**
	 * Change default inventory location for sales orders.
	 */
	public static function megaventory_change_default_location() {

		$inventory_id = 0;

		if ( isset( $_POST['inventory_id'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$inventory_id = (int) sanitize_text_field( wp_unslash( $_POST['inventory_id'] ) );

			update_option( 'default-megaventory-inventory-location', $inventory_id );
		}

		wp_send_json_success( true );
		wp_die();
	}

	/**
	 * Change default inventory location for sales orders.
	 */
	public static function megaventory_include_location() {

		$inventory_id = 0;

		if ( isset( $_POST['inventory_id'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$inventory_id = (int) sanitize_text_field( wp_unslash( $_POST['inventory_id'] ) );

			\Megaventory\Models\Location::include_location( $inventory_id );
		}

		wp_send_json_success( true );
		wp_die();
	}

	/**
	 * Change default inventory location for sales orders.
	 */
	public static function megaventory_exclude_location() {

		$inventory_id = 0;

		if ( isset( $_POST['inventory_id'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$inventory_id = (int) sanitize_text_field( wp_unslash( $_POST['inventory_id'] ) );

			\Megaventory\Models\Location::exclude_location( $inventory_id );

			if ( \Megaventory\Models\Shipping_Zones::megaventory_are_shipping_zones_enabled() ) {

				\Megaventory\Models\Shipping_Zones::megaventory_delete_excluded_location_from_zone_priorities( $inventory_id );
			}
		}

		wp_send_json_success( true );
		wp_die();
	}
}

