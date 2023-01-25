<?php
/**
 * Location helper.
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

/**
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'class-api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-error.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-errors.php';

/**
 * Location class.
 */
class Location {

	/**
	 * Megaventory location id.
	 *
	 * @var int
	 */
	public $mv_location_id;

	/**
	 * Location abbreviation.
	 *
	 * @var string
	 */
	public $mv_location_abbreviation;

	/**
	 * Location full name.
	 *
	 * @var string
	 */
	public $mv_location_full_name;

	/**
	 * Location address.
	 *
	 * @var string
	 */
	public $mv_location_address;

	/**
	 * Location Get Megaventory API call.
	 *
	 * @var string
	 */
	const LOCATION_GET_CALL = 'InventoryLocationGet';

	/**
	 * Location Update Megaventory API call.
	 *
	 * @var string
	 */
	const LOCATION_UPDATE_CALL = 'InventoryLocationUpdate';

	/**
	 * Location errors.
	 *
	 * @var MVWC_Errors
	 */
	public $errors;

	/**
	 * Location successes.
	 *
	 * @var MVWC_Successes
	 */
	public $successes;

	/**
	 * Location constructor.
	 */
	public function __construct() {

		$this->errors    = new MVWC_Errors();
		$this->successes = new MVWC_Successes();
	}

	/**
	 * Log Location errors.
	 *
	 * @param string $problem as Location error problem.
	 * @param string $full_msg as Location error full message.
	 * @param int    $code as Location error code.
	 * @param string $type as Location error type.
	 * @param string $json_object as string.
	 * @return void
	 */
	public function log_error( $problem, $full_msg, $code, $type = 'error', $json_object = '' ) {

		$args = array(
			'entity_id'   => array(
				'wc' => 0,
				'mv' => $this->mv_location_id,
			),
			'entity_name' => $this->mv_location_full_name,
			'problem'     => $problem,
			'full_msg'    => $full_msg,
			'error_code'  => $code,
			'json_object' => $json_object,
			'type'        => $type,
		);

		$this->errors->log_error( $args );
	}

	/**
	 * Logs Location Successes.
	 *
	 * @param string $transaction_status as success status.
	 * @param string $full_msg as success full message.
	 * @param int    $code as message code.
	 * @return void
	 */
	public function log_success( $transaction_status, $full_msg, $code ) {

		$args = array(
			'entity_id'          => array(
				'wc' => 0,
				'mv' => $this->mv_location_id,
			),
			'entity_type'        => 'location',
			'entity_name'        => $this->mv_location_full_name,
			'transaction_status' => $transaction_status,
			'full_msg'           => $full_msg,
			'success_code'       => $code,
		);

		$this->successes->log_success( $args );
	}

	/**
	 * Initialize Megaventory Locations.
	 *
	 * @return void
	 */
	public static function initialize_megaventory_locations() {

		$inventory_locations = self::get_megaventory_locations();

		if ( empty( $inventory_locations ) ) {
			self::create_default_location();
		}

	}

	/**
	 * Get Megaventory Locations.
	 *
	 * @return array
	 */
	public static function get_megaventory_locations() {

		$url = \Megaventory\API::get_url_for_call( self::LOCATION_GET_CALL );

		$data = \Megaventory\API::perform_call_to_megaventory( $url );

		$inventory_locations = $data['mvInventoryLocations'];

		return $inventory_locations;

	}

	/**
	 * Creates a default Megaventory Location.
	 *
	 * @return bool
	 */
	public static function create_default_location() {

		$location                           = new Location();
		$location->mv_location_abbreviation = 'Main';
		$location->mv_location_full_name    = 'Main Location';
		$location->mv_location_address      = 'Default Address';

		$results = $location->update_megaventory_location( false );

		update_option( 'default-megaventory-inventory-location', $location->mv_location_id );

		return $results;
	}

	/**
	 * Update a Megaventory Location.
	 *
	 * @param bool $is_update as boolean if is a location update.
	 * @return bool
	 */
	public function update_megaventory_location( $is_update ) {

		$request_object = new \stdClass();

		$location_object                                = new \stdClass();
		$location_object->inventorylocationabbreviation = $this->mv_location_abbreviation;
		$location_object->inventorylocationname         = $this->mv_location_full_name;
		$location_object->inventorylocationaddress      = $this->mv_location_address;

		if ( $is_update ) {

			$location_object->inventorylocationid = $this->mv_location_id;

			$request_object->mvrecordaction = 'Update';

		} else {

			$request_object->mvrecordaction = 'Insert';
		}
		$request_object->mvinventorylocation = $location_object;

		$location_update_url = \Megaventory\API::get_url_for_call( self::LOCATION_UPDATE_CALL );

		$request_object = \Megaventory\API::wrap_json( $request_object );

		$results = \Megaventory\API::send_json( $location_update_url, $request_object );

		$return_bool = false;

		if ( $results['mvInventoryLocation'] ) {

			$this->mv_location_id = $results['mvInventoryLocation']['InventoryLocationID'];

			$this->log_success( $request_object->mvrecordaction, 'Default inventory location created successfully to your Megaventory account', 1 );

			$return_bool = true;

		} else {

			$error_message = ( $results['ResponseStatus']['Message'] ? $results['ResponseStatus']['Message'] : 'Default Inventory location does not created' );

			$error_code = ( $results['ResponseStatus']['ErrorCode'] ? $results['ResponseStatus']['ErrorCode'] : -1 );

			$this->log_error( -1, $error_message, $error_code, 'error', $results['json_object'] );
		}

		return $return_bool;

	}

	/**
	 * Get location ID to location Abbreviation dictionary.
	 * Performs API Call if option is not set.
	 *
	 * @return array
	 */
	public static function get_location_id_to_abbreviation_dict() {

		$mv_location_id_to_abbr = get_option( MV_Constants::MV_LOCATION_ID_TO_ABBREVIATION );

		if ( ! isset( $mv_location_id_to_abbr ) ) {

			$mv_location_id_to_abbr = array();

			$mv_locations = self::get_megaventory_locations();

			foreach ( $mv_locations as $location ) {

				$mv_location_id_to_abbr[ $location['InventoryLocationID'] ] = $location['InventoryLocationAbbreviation'];

			}

			update_option( MV_Constants::MV_LOCATION_ID_TO_ABBREVIATION, $mv_location_id_to_abbr );
		}

		return $mv_location_id_to_abbr;
	}

	/**
	 * Checks if location is excluded.
	 *
	 * @param int $location_id as location id.
	 * @return bool
	 */
	public static function is_location_excluded( $location_id ) {

		$are_shipping_zones_active = \Megaventory\Models\Shipping_Zones::megaventory_are_shipping_zones_enabled();

		if ( $are_shipping_zones_active ) {
			return false;
		}

		$excluded_locations_ids = get_option( MV_Constants::MV_EXCLUDED_LOCATION_IDS_OPT );

		if ( empty( $excluded_locations_ids ) ) {
			return false;
		}

		if ( in_array( (int) $location_id, $excluded_locations_ids, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if location is excluded.
	 *
	 * @param int $zone_id as zone id.
	 * @param int $location_id as location id.
	 * @return bool
	 */
	public static function is_location_excluded_from_zone( $zone_id, $location_id ) {

		$excluded_locations_by_zone = \Megaventory\Models\Shipping_Zones::megaventory_get_shipping_zone_excluded_locations();

		if ( empty( $excluded_locations_by_zone ) || ! array_key_exists( $zone_id, $excluded_locations_by_zone ) ) {
			return false;
		}

		$excluded_locations_ids = $excluded_locations_by_zone[ $zone_id ];

		if ( in_array( (int) $location_id, $excluded_locations_ids, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Includes a Location.
	 *
	 * @param int $location_id as location id.
	 * @return bool
	 */
	public static function include_location( $location_id ) {

		$excluded_locations_ids = get_option( MV_Constants::MV_EXCLUDED_LOCATION_IDS_OPT );

		if ( empty( $excluded_locations_ids ) ) {
			return true;
		}

		$key = array_search( $location_id, $excluded_locations_ids, true );

		if ( false !== $key ) {

			array_splice( $excluded_locations_ids, $key, 1 );

			update_option( MV_Constants::MV_EXCLUDED_LOCATION_IDS_OPT, $excluded_locations_ids );
		}

		return true;
	}

	/**
	 * Exclude a Location.
	 *
	 * @param int $location_id as location id.
	 * @return bool
	 */
	public static function exclude_location( $location_id ) {

		$excluded_locations_ids = get_option( MV_Constants::MV_EXCLUDED_LOCATION_IDS_OPT );

		if ( empty( $excluded_locations_ids ) ) {
			$excluded_locations_ids = array();
		}

		$key = array_search( $location_id, $excluded_locations_ids, true );

		if ( false !== $key ) { // already excluded.

			return true;
		}

		array_push( $excluded_locations_ids, $location_id );

		update_option( MV_Constants::MV_EXCLUDED_LOCATION_IDS_OPT, $excluded_locations_ids );

		return true;
	}
}
