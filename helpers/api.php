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

/**
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/address.php';

/**
 * Get the Megaventory API key
 */
function get_api_key() {
	return get_option( 'megaventory_api_key' );
}

/**
 * Get Megaventory URL
 *
 * @return string
 */
function get_megaventory_url() {
	$url = get_api_host() . 'json/reply/';
	return $url;
}

/**
 * Get host
 */
function get_api_host() {
	$host = get_option( 'megaventory_api_host', MV_Constants::MV_DEFAULT_HOST );
	return $host;
}

/**
 * Get the Default client
 */
function get_guest_mv_client() {
	$client = Client::wc_find( (int) get_option( 'woocommerce_guest' ) );
	return $client;
}

/**
 * Create URL using the API key and call.
 *
 * @param string $call as string.
 */
function create_json_url( $call ) {
	$url = get_megaventory_url();
	return $url . $call;
}

/**
 * Create URL.
 *
 * @param string $call as Megaventory API method.
 */
function get_url_for_call( $call ) {
	$url = get_megaventory_url();
	return $url . $call;
}

/**
 * Create json filter.
 *
 * @param string $call as string.
 * @param string $field_name as string.
 * @param string $search_operator as string.
 * @param string $search_value as string.
 */
function create_json_url_filter( $call, $field_name, $search_operator, $search_value ) {
	return create_json_url( $call ) . '&Filters={FieldName:' . rawurlencode( $field_name ) . ',SearchOperator:' . rawurlencode( $search_operator ) . ',SearchValue:' . rawurlencode( $search_value ) . '}';
}

/**
 * Create json filter.
 *
 * @param array $call as array.
 * @param array $args as array.
 */
function create_json_url_filters( $call, $args ) {

	$url            = create_json_url( $call ) . '&Filters=[';
	$number_of_args = count( $args );

	for ( $i = 0; $i < $number_of_args; $i++ ) {

		$arg  = $args[ $i ];
		$url .= '{FieldName:' . $arg[0] . ',SearchOperator:' . $arg[1] . ',SearchValue:' . $arg[2] . '}';

		if ( $i + 1 < $number_of_args ) { // not last element.
			$url .= ',';
		}
	}
	$url .= ']';

	return $url;
}

/**
 * Send json.
 *
 * @param string $url as string.
 * @param mixed  $json_request as stdclass.
 * @return mixed
 */
function send_json( $url, $json_request ) {

	/**
	 * Old code did a curl, remove in the future if wp_remote_get is working fine.
	 *$ch = curl_init();
	 *curl_setopt( $ch, CURLOPT_URL, $url );
	 *curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
	 *curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	 *curl_setopt( $ch, CURLOPT_POSTFIELDS, ( $json_request ) );
	 *curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json', 'Content-Length: ' . strlen( $json_request ) ) );
	 *$data = curl_exec( $ch );
	 *curl_close( $ch );
	 *$data = json_decode( $data, true );
	 */

	/*
	TODO: Send directly the array and not an object.
	 */

	$body_to_send           = create_array_from_object( $json_request );
	$body_to_send['APIKEY'] = get_option( 'megaventory_api_key' );

	$host_reachable = false;

	$data_to_send = array(
		'headers' => array(
			'Content-Type' => 'application/json',
		),
		'method'  => 'POST',
		'timeout' => MV_Constants::REQUEST_TIMEOUT_LIMIT_IN_SECONDS,
		'body'    => wp_json_encode( $body_to_send ),
	);

	$data     = array();
	$response = array();

	// Try multiple times until MAX_REQUEST_ATTEMPTS is reached before failing and registering an error when the API is timing out.
	// After the maximum attempts have been exhausted with no success, return a WordPress request error.
	for ( $attempt = 1; $attempt <= MV_Constants::MAX_REQUEST_ATTEMPTS; $attempt++ ) {
		$response = wp_remote_post( $url, $data_to_send );

		if ( ! is_wp_error( $response ) ) {
			$data           = json_decode( wp_remote_retrieve_body( $response ), true );
			$host_reachable = true;
			break;
		} else {
			// Exponential Backoff algorithm (Time = 2^c - 1, where c is the current number of attempts).
			$time_sleep = ( pow( 2, $attempt ) - 1 ) * MV_Constants::SECONDS_TO_MICROSECONDS_CONVERSION_RATE;
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
function send_request_to_megaventory( $url, $request ) {

	$body_to_send           = $request;
	$body_to_send['APIKEY'] = get_option( 'megaventory_api_key' );

	$host_reachable = false;

	$data_to_send = array(
		'headers' => array(
			'Content-Type' => 'application/json',
		),
		'method'  => 'POST',
		'timeout' => MV_Constants::REQUEST_TIMEOUT_LIMIT_IN_SECONDS,
		'body'    => wp_json_encode( $body_to_send ),
	);

	$data     = array();
	$response = array();

	// Try multiple times until MAX_REQUEST_ATTEMPTS is reached before failing and registering an error when the API is timing out.
	// Log the error only one time, after maximum attempts have been exhausted.
	for ( $attempt = 1; $attempt <= MV_Constants::MAX_REQUEST_ATTEMPTS; $attempt++ ) {
		$response = wp_remote_post( $url, $data_to_send );

		if ( ! is_wp_error( $response ) ) {
			$data           = json_decode( wp_remote_retrieve_body( $response ), true );
			$host_reachable = true;
			break;
		} else {
			// Exponential Backoff algorithm (Time = 2^c - 1, where c is the current number of attempts).
			$time_sleep = ( pow( 2, $attempt ) - 1 ) * MV_Constants::SECONDS_TO_MICROSECONDS_CONVERSION_RATE;
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
function create_array_from_object( $my_object ) {

	$array_to_return = array();

	foreach ( $my_object as $key => $value ) {

		if ( is_a( $value, 'stdClass' ) ) {

			$array_to_return[ $key ] = create_array_from_object( $value );

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
function wrap_json( $json_object ) {

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
function perform_call_to_megaventory( $url ) {
	/**
	 *Old code did a curl, remove in the future if wp_remote_get is working fine.
	 *$curl_handle = curl_init();
	 *curl_setopt( $curl_handle, CURLOPT_URL, $url );
	 *curl_setopt( $curl_handle, CURLOPT_RETURNTRANSFER, true );
	 *$json_data = curl_exec( $curl_handle );
	 *curl_close( $curl_handle );
	 *return $json_data;
	 */

	$body_to_send = array( 'APIKEY' => get_option( 'megaventory_api_key' ) );

	$data_to_send = array(
		'headers' => array(
			'Content-Type' => 'application/json',
		),
		'method'  => 'POST',
		'timeout' => MV_Constants::REQUEST_TIMEOUT_LIMIT_IN_SECONDS,
		'body'    => wp_json_encode( $body_to_send ),
	);

	$host_reachable = false;

	$data     = array();
	$response = array();

	for ( $attempt = 1; $attempt <= MV_Constants::MAX_REQUEST_ATTEMPTS; $attempt++ ) {
		$response = wp_remote_post( $url, $data_to_send );

		if ( ! is_wp_error( $response ) ) {
			$data           = json_decode( wp_remote_retrieve_body( $response ), true );
			$host_reachable = true;
			break;
		} else {
			// Exponential Backoff algorithm (Time = 2^c - 1, where c is the current number of attempts).
			$time_sleep = ( pow( 2, $attempt ) - 1 ) * MV_Constants::SECONDS_TO_MICROSECONDS_CONVERSION_RATE;
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
function get_default_currency() {
	$url  = get_url_for_call( MV_Constants::CURRENCY_GET );
	$data = array(
		'Filters' => array(
			'FieldName'      => 'CurrencyIsDefault',
			'SearchOperator' => 'Equals',
			'SearchValue'    => 'true',
		),
	);

	$response             = send_request_to_megaventory( $url, $data );
	$megaventory_currency = $response['mvCurrencies'][0]['CurrencyCode'];

	update_option( 'primary_megaventory_currecy', $megaventory_currency );
	return $megaventory_currency;
}

/**
 * Check connectivity.
 *
 * @return bool
 */
function check_connectivity() {

	$url    = get_megaventory_url();
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
function check_key() {

	$api_key = get_option( 'megaventory_api_key' );

	if ( empty( $api_key ) ) {
		set_transient( 'api_key_is_set', 0 );
		update_option( 'correct_megaventory_apikey', 0 );
		update_option( 'correct_currency', 0 );
		return false;
	}

	$connectivity = check_connectivity();

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
			$url  = create_json_url( 'APIKeyGet' );
			$data = perform_call_to_megaventory( $url );
		}
	}

	return $data;
}

/**
 * Integration check.
 */
function check_if_integration_is_enabled() {

	$url = create_json_url( 'AccountSettingsGet' );

	$json_object        = new \stdClass();
	$integration_object = new \stdClass();

	$integration_object->settingname = 'isWoocommerceEnabled';
	$json_object->mvaccountsettings  = $integration_object;
	$json_object                     = wrap_json( $integration_object );
	/**
	 * $json_object = wp_json_encode( $json_object );
	 */
	$data = send_json( $url, $json_object );

	$is_woo_commerce_enabled_response = $data['mvAccountSettings'][0]['SettingValue'];

	return $is_woo_commerce_enabled_response;

}

/**
 * Remove Integration update.
 *
 * @param int $id as integer.
 */
function remove_integration_update( $id ) {

	$data = array(
		'IntegrationUpdateIDToDelete' => $id,
	);

	$url = get_url_for_call( MV_Constants::INTEGRATION_UPDATE_DELETE );

	$response = send_request_to_megaventory( $url, $data );

}

/**
 * Pull Product changes.
 */
function get_integration_updates() {

	$url  = get_url_for_call( MV_Constants::INTEGRATION_UPDATE_GET );
	$data = array(
		'Filters' => array(
			'FieldName'      => 'Application',
			'SearchOperator' => 'Equals',
			'SearchValue'    => 'Woocommerce',
		),
	);

	$response = send_request_to_megaventory( $url, $data );

	return $response;
}

/**
 * Apply Coupon.
 *
 * @param Product $product as Product class.
 * @param Coupon  $coupon as Coupon class.
 */
function apply_coupon( $product, $coupon ) {

	if ( ! $coupon->type || 'fixed_cart' === $coupon->type ) {
		return false;
	}

	if ( ! $coupon->applies_to_sales() && $product->sale_active ) {
		return false;
	}

	$incl_ids       = $coupon->get_included_products( true );
	$included_empty = count( $incl_ids ) <= 0;
	$included       = in_array( $product->wc_id, $incl_ids, true );
	$excluded       = in_array( $product->wc_id, $coupon->get_excluded_products( true ), true );

	$categories         = $product->wc_get_prod_categories( 'id' );
	$incl_ids_cat       = $coupon->get_included_products_categories( true );
	$included_empty_cat = count( $incl_ids_cat ) <= 0;
	$included_cat       = in_array( $product->wc_id, $incl_ids_cat, true );
	$excluded_cat       = in_array( $product->wc_id, $coupon->get_excluded_products_categories( true ), true );

	return ( ( $included_empty || $included ) || ( ( $included_empty_cat && $included_empty ) || $included_cat ) ) && ( ! $excluded && ! $excluded_cat );
}

/**
 * Log API key.
 *
 * @param String $api_key as string.
 */
function log_apikey( $api_key ) {

	global $wpdb;

	$last_valid_api_key = get_last_valid_api_key();

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
