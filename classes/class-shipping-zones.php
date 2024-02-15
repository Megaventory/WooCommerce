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

namespace Megaventory\Models;

require_once MEGAVENTORY__PLUGIN_DIR . 'class-api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mv-constants.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mv-order-item.php';

/**
 * Megaventory Shipping Zones class.
 */
class Shipping_Zones {

	/**
	 * Get the shipping zone of this order by the order address
	 *
	 * @param \WC_Order $order the order object.
	 * @return \WC_Shipping_Zone
	 */
	public static function megaventory_get_shipping_zone_from_order( $order ) {

		$package = array();

		$package['destination']['country']  = $order->get_shipping_country();
		$package['destination']['state']    = $order->get_shipping_state();
		$package['destination']['postcode'] = $order->get_shipping_postcode();

		return \WC_Shipping_Zones::get_zone_matching_package( $package );
	}

	/**
	 * Gets all account Shipping Zones plus the default (Rest Of World) Shipping Zone.
	 *
	 * @return array
	 */
	public static function megaventory_get_all_shipping_zones_plus_default() {

		$zones = \WC_Shipping_Zones::get_zones();

		$default_zone = \WC_Shipping_Zones::get_zone( MV_Constants::SHIPPING_DEFAULT_ZONE_ID );

		$zones[ $default_zone->get_id() ]                            = $default_zone->get_data();
		$zones[ $default_zone->get_id() ]['zone_id']                 = $default_zone->get_id();
		$zones[ $default_zone->get_id() ]['formatted_zone_location'] = $default_zone->get_formatted_location();
		$zones[ $default_zone->get_id() ]['shipping_methods']        = $default_zone->get_shipping_methods();

		return $zones;
	}

	/**
	 * Check if Shipping Zone based order creation is enabled.
	 *
	 * @return bool
	 */
	public static function megaventory_are_shipping_zones_enabled() {

		return (bool) get_option( MV_Constants::SHIPPING_ZONES_ENABLE_OPT, false );
	}

	/**
	 * Get location priority per shipping zone.
	 *
	 * @return array|false
	 */
	public static function megaventory_get_shipping_zone_priorities() {

		if ( ! self::megaventory_are_shipping_zones_enabled() ) {
			return false;
		}

		$zone_priority_array = get_option( MV_Constants::SHIPPING_ZONES_PRIORITY_OPT, array() );

		if ( empty( $zone_priority_array ) || ! is_array( $zone_priority_array ) ) {

			$zone_priority_array = array();
		}

		return $zone_priority_array;
	}


	/**
	 * Get excluded locations per shipping zone.
	 *
	 * @return array
	 */
	public static function megaventory_get_shipping_zone_excluded_locations() {

		if ( ! self::megaventory_are_shipping_zones_enabled() ) {
			return array();
		}

		$zone_excluded_locations_array = get_option( MV_Constants::SHIPPING_ZONES_EXCLUDED_LOCATION_OPT, array() );

		if ( empty( $zone_excluded_locations_array ) || ! is_array( $zone_excluded_locations_array ) ) {

			$zone_excluded_locations_array = array();
		}

		return $zone_excluded_locations_array;
	}

	/**
	 * Get priorities for given zone ID.
	 *
	 * @param int $zone_id The id of the Shipping Zone in use.
	 * @return array
	 */
	public static function megaventory_get_location_priority_for_zone( $zone_id ) {

		$zone_priorities = self::megaventory_get_shipping_zone_priorities();

		if ( ! $zone_priorities || ! array_key_exists( $zone_id, $zone_priorities ) ) {

			$mv_location_id_to_abbr = get_option( MV_Constants::MV_LOCATION_ID_TO_ABBREVIATION );

			$default_priority = array();

			foreach ( $mv_location_id_to_abbr as $id => $abbr ) {

				if ( Location::is_location_excluded_from_zone( $zone_id, $id ) ) {
					continue;
				}
				array_push( $default_priority, $id );
			}

			return $default_priority;
		}

		return $zone_priorities[ $zone_id ];
	}
}
