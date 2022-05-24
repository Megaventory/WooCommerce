<?php
/**
 * Api helper.
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

namespace Megaventory;

require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/class-address.php';

/**
 * Megaventory API helper.
 */
class API {

	/**
	 * Get the Megaventory API key
	 */
	public static function get_api_key() {
		return get_option( 'megaventory_api_key' );
	}

	/**
	 * Get Megaventory URL
	 *
	 * @return string
	 */
	private static function get_megaventory_url() {

		$url = self::get_api_host() . 'json/reply/';

		return $url;
	}

	/**
	 * Get host
	 */
	public static function get_api_host() {

		$host = get_option( 'megaventory_api_host', \Megaventory\Models\MV_Constants::MV_DEFAULT_HOST );

		return $host;
	}

	/**
	 * Setting up Megaventory API host.
	 *
	 * @param string $host as Megaventory host.
	 * @return void
	 */
	public static function set_api_host( $host ) {

		if ( '/' !== substr( $host, -1 ) ) {

			$host .= '/';
		}

		update_option( 'megaventory_api_host', (string) $host );
	}

	/**
	 * Create URL.
	 *
	 * @param string $call as Megaventory API method.
	 */
	public static function get_url_for_call( $call ) {

		$url = self::get_megaventory_url();

		return $url . $call;
	}

	/**
	 * Send json.
	 *
	 * @param string $url as string.
	 * @param mixed  $json_request as stdclass.
	 * @return mixed
	 */
	public static function send_json( $url, $json_request ) {
		/*
		TODO: Send directly the array and not an object.
		 */

		$body_to_send           = self::create_array_from_object( $json_request );
		$body_to_send['APIKEY'] = get_option( 'megaventory_api_key' );

		$host_reachable = false;

		$data_to_send = array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'method'  => 'POST',
			'timeout' => \Megaventory\Models\MV_Constants::REQUEST_TIMEOUT_LIMIT_IN_SECONDS,
			'body'    => wp_json_encode( $body_to_send ),
		);

		$data     = array();
		$response = array();

		// Try multiple times until MAX_REQUEST_ATTEMPTS is reached before failing and registering an error when the API is timing out.
		// After the maximum attempts have been exhausted with no success, return a WordPress request error.
		for ( $attempt = 1; $attempt <= \Megaventory\Models\MV_Constants::MAX_REQUEST_ATTEMPTS; $attempt++ ) {
			$response = wp_remote_post( $url, $data_to_send );

			if ( ! is_wp_error( $response ) ) {
				$data           = json_decode( wp_remote_retrieve_body( $response ), true );
				$host_reachable = true;
				break;
			} else {
				// Exponential Backoff algorithm (Time = 2^c - 1, where c is the current number of attempts).
				$time_sleep = ( pow( 2, $attempt ) - 1 ) * \Megaventory\Models\MV_Constants::SECONDS_TO_MICROSECONDS_CONVERSION_RATE;
				usleep( $time_sleep );
			}
		}

		if ( ! $host_reachable ) {
			$data['InternalErrorCode']         = 'WordPress Request timeout';
			$data['ResponseStatus']['Message'] = $response->get_error_message();
		}

		$data['json_object'] = wp_json_encode( $body_to_send );

		return $data;

	}

	/**
	 * Send json.
	 *
	 * @param string $url as string.
	 * @param mixed  $request as array.
	 * @return array
	 */
	public static function send_request_to_megaventory( $url, $request ) {

		$body_to_send           = $request;
		$body_to_send['APIKEY'] = get_option( 'megaventory_api_key' );

		$host_reachable = false;

		$data_to_send = array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'method'  => 'POST',
			'timeout' => \Megaventory\Models\MV_Constants::REQUEST_TIMEOUT_LIMIT_IN_SECONDS,
			'body'    => wp_json_encode( $body_to_send ),
		);

		$data     = array();
		$response = array();

		// Try multiple times until MAX_REQUEST_ATTEMPTS is reached before failing and registering an error when the API is timing out.
		// Log the error only one time, after maximum attempts have been exhausted.
		for ( $attempt = 1; $attempt <= \Megaventory\Models\MV_Constants::MAX_REQUEST_ATTEMPTS; $attempt++ ) {
			$response = wp_remote_post( $url, $data_to_send );

			if ( ! is_wp_error( $response ) ) {
				$data           = json_decode( wp_remote_retrieve_body( $response ), true );
				$host_reachable = true;
				break;
			} else {
				// Exponential Backoff algorithm (Time = 2^c - 1, where c is the current number of attempts).
				$time_sleep = ( pow( 2, $attempt ) - 1 ) * \Megaventory\Models\MV_Constants::SECONDS_TO_MICROSECONDS_CONVERSION_RATE;
				usleep( $time_sleep );
			}
		}

		if ( ! $host_reachable ) {
			$data['InternalErrorCode']         = 'WordPress Request timeout';
			$data['ResponseStatus']['Message'] = $response->get_error_message();
		}

		$data['json_object'] = wp_json_encode( $body_to_send );

		return $data;

	}

	/**
	 * Creates an array from dynamic object.
	 *
	 * @param mixed $my_object as a dynamic object.
	 * @return array
	 */
	private static function create_array_from_object( $my_object ) {

		$array_to_return = array();

		foreach ( $my_object as $key => $value ) {

			if ( is_a( $value, 'stdClass' ) ) {

				$array_to_return[ $key ] = self::create_array_from_object( $value );

			} else {

				$array_to_return[ $key ] = $value;
			}
		}
		return $array_to_return;
	}

	/**
	 * Wrap api key to object.
	 *
	 * @param object $json_object as string.
	 */
	public static function wrap_json( $json_object ) {

		$api_key = get_option( 'megaventory_api_key' );

		$json_object->apikey = $api_key;

		return $json_object;
	}

	/**
	 * Curl call.
	 *
	 * @param string $url as string.
	 * @return array
	 */
	public static function perform_call_to_megaventory( $url ) {

		$body_to_send = array( 'APIKEY' => self::get_api_key() );

		$data_to_send = array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'method'  => 'POST',
			'timeout' => \Megaventory\Models\MV_Constants::REQUEST_TIMEOUT_LIMIT_IN_SECONDS,
			'body'    => wp_json_encode( $body_to_send ),
		);

		$host_reachable = false;

		$data     = array();
		$response = array();

		for ( $attempt = 1; $attempt <= \Megaventory\Models\MV_Constants::MAX_REQUEST_ATTEMPTS; $attempt++ ) {
			$response = wp_remote_post( $url, $data_to_send );

			if ( ! is_wp_error( $response ) ) {
				$data           = json_decode( wp_remote_retrieve_body( $response ), true );
				$host_reachable = true;
				break;
			} else {
				// Exponential Backoff algorithm (Time = 2^c - 1, where c is the current number of attempts).
				$time_sleep = ( pow( 2, $attempt ) - 1 ) * \Megaventory\Models\MV_Constants::SECONDS_TO_MICROSECONDS_CONVERSION_RATE;
				usleep( $time_sleep );
			}
		}

		if ( ! $host_reachable ) {
			$data['InternalErrorCode']         = 'WordPress Request timeout';
			$data['ResponseStatus']['Message'] = $response->get_error_message();
		}

		return $data;
	}

	/**
	 * Get Default Currency.
	 */
	public static function get_default_currency() {

		$url = self::get_url_for_call( \Megaventory\Models\MV_Constants::CURRENCY_GET );

		$data = array(
			'Filters' => array(
				'FieldName'      => 'CurrencyIsDefault',
				'SearchOperator' => 'Equals',
				'SearchValue'    => 'true',
			),
		);

		$response = self::send_request_to_megaventory( $url, $data );

		$megaventory_currency = $response['mvCurrencies'][0]['CurrencyCode'];

		update_option( 'primary_megaventory_currecy', $megaventory_currency );

		return $megaventory_currency;
	}

	/**
	 * Check connectivity.
	 *
	 * @return bool
	 */
	private static function check_connectivity() {

		$url    = self::get_megaventory_url();
		$result = wp_remote_get( $url );

		if ( ! $result || ! is_array( $result ) || is_wp_error( $result ) ) {

			return false;
		}

		return true;
	}

	/**
	 * Check API key.
	 *
	 * @return array with ApiKeyGet response.
	 */
	public static function check_key() {

		$api_key = get_option( 'megaventory_api_key' );

		if ( empty( $api_key ) ) {
			update_option( 'api_key_is_set', 0 );
			update_option( 'correct_megaventory_apikey', 0 );
			update_option( 'correct_currency', 0 );
			return false;
		}

		$connectivity = self::check_connectivity();

		if ( ! $connectivity ) {

			update_option( 'correct_connection', 0 );
			$data                                = array();
			$data['ResponseStatus']['ErrorCode'] = 500;
			$data['ResponseStatus']['Message']   = 'Unable to reach host.';

		} else {

			update_option( 'correct_connection', 1 );

			if ( empty( $api_key ) ) {

				$data                                = array();
				$data['ResponseStatus']['ErrorCode'] = 500;
				$data['ResponseStatus']['Message']   = 'Empty Api Key, create an API key under My Profile.';

			} else {

				$url  = self::get_url_for_call( 'APIKeyGet' );
				$data = self::perform_call_to_megaventory( $url );
			}
		}

		return $data;
	}

	/**
	 * Integration check.
	 */
	public static function check_if_integration_is_enabled() {

		$url = self::get_url_for_call( 'AccountSettingsGet' );

		$json_object        = new \stdClass();
		$integration_object = new \stdClass();

		$integration_object->settingname = 'isWoocommerceEnabled';
		$json_object->mvaccountsettings  = $integration_object;
		$json_object                     = self::wrap_json( $integration_object );

		$data = self::send_json( $url, $json_object );

		$is_woo_commerce_enabled_response = $data['mvAccountSettings'][0]['SettingValue'];

		return $is_woo_commerce_enabled_response;

	}

	/**
	 * Get last inserted Megaventory API key.
	 *
	 * @return string
	 */
	public static function get_last_valid_api_key() {

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
	 * Log API key.
	 *
	 * @param String $api_key as string.
	 */
	public static function log_apikey( $api_key ) {

		global $wpdb;

		$last_valid_api_key = self::get_last_valid_api_key();

		if ( trim( $last_valid_api_key ) === trim( $api_key ) ) {

			return;
		}
		$apikeys_table_name = $wpdb->prefix . 'megaventory_api_keys';

		$charset_collate = $wpdb->get_charset_collate();
		$return          = $wpdb->insert(
			$apikeys_table_name,
			array(
				'created_at' => get_date_from_gmt( gmdate( 'Y-m-d H:i:s' ), 'Y-m-d H:i:s' ),
				'api_key'    => $api_key,
			)
		); // db call ok.

		return $return;
	}
}
