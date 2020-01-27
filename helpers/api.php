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

$default_host = 'https://api.megaventory.com/v2017a/';
$host         = get_api_host();
$url          = $host . 'json/reply/';
$api_key      = get_api_key();

$integration_get_call    = 'IntegrationUpdateGet';
$integration_delete_call = 'IntegrationUpdateDelete';
$currency_get_call       = 'CurrencyGet';

/* MV status => WC status */
$translate_order_status = array(
	'Pending'          => 'on-hold',
	'Verified'         => 'processing',
	'PartiallyShipped' => 'processing',
	'FullyShipped'     => 'processing',
	'Closed'           => 'completed',
	// Received is only for purchase orders.
	'FullyInvoiced'    => 'completed',
	'Cancelled'        => 'cancelled',

);

/* 	MV status code to string. only a few of them are actually used */
$document_status = array(
	0  => 'ValidStatus',
	10 => 'Pending',
	20 => 'ApprovalInProcess',
	30 => 'Verified',
	35 => 'Picked',
	36 => 'Packed',
	40 => 'PartiallyShipped',
	41 => 'PartiallyShippedInvoiced',
	42 => 'FullyShipped',
	43 => 'PartiallyReceived',
	44 => 'PartiallyReceivedInvoiced',
	45 => 'FullyReceived',
	46 => 'PartiallyInvoiced',
	47 => 'FullyInvoiced',
	48 => 'PartiallyPaid',
	49 => 'FullyPaid',
	50 => 'Closed',
	70 => 'ClosedWO',
	99 => 'Cancelled',
);

/**
 * Get the Megaventory API key
 */
function get_api_key() {
	return get_option( 'megaventory_api_key' );
}

/**
 * Get host
 */
function get_api_host() {
	global $default_host;
	$host = get_option( 'megaventory_api_host' );
	if ( ! $host ) {
		$host = $default_host;
	}
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
 * @param array $call as array.
 */
function create_json_url( $call ) {
	global $url, $api_key;
	return $url . $call . '?APIKEY=' . rawurlencode( $api_key );
}

/**
 * Create json filter.
 *
 * @param array $call as array.
 * @param array $field_name as array.
 * @param array $search_operator as array.
 * @param array $search_value as array.
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

	$body_to_send = create_array_from_object( $json_request );

	$data_to_send = array(
		'headers' => array(
			'Content-Type' => 'application/json',
		),
		'body'    => wp_json_encode( $body_to_send ),
	);

	$response = wp_remote_post( $url, $data_to_send );

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

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
 * @param string $json_object as string.
 */
function wrap_json( $json_object ) {

	global $api_key;

	$json_object->apikey = $api_key;

	return $json_object;
}

/**
 * Curl call.
 *
 * @param string $url as string.
 * @return string
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

	$response = wp_remote_get( $url );

	$data = wp_remote_retrieve_body( $response );
	return $data;
}

/**
 * Get Default Currency.
 */
function get_default_currency() {
	global $currency_get_call;
	$url                  = create_json_url_filter( $currency_get_call, 'CurrencyIsDefault', 'Equals', 'true' );
	$json_data            = perform_call_to_megaventory( $url );
	$megaventory_currency = json_decode( $json_data, true )['mvCurrencies'][0]['CurrencyCode'];
	return $megaventory_currency;
}

/**
 * Check connectivity.
 *
 * @return bool
 */
function check_connectivity() {
	global $host;

	$results = wp_remote_get( $host );

	if ( ! is_array( $results ) ) {

		return false;
	}

	if ( '200' === (string) $results['response']['code'] && 'OK' === (string) $results['response']['message'] ) {

		return true;
	}

	return false;

}

/**
 * Check API key.
 */
function check_key() {

	global $api_key;
	global $api_key_error_response_status_message;

	if ( empty( $api_key ) ) {
		return false;
	}

	$url                                   = create_json_url( 'ApiKeyGet' );
	$json_data                             = perform_call_to_megaventory( $url );
	$data                                  = json_decode( $json_data, true );
	$api_key_error_response_status_message = $data['ResponseStatus']['Message'];
	$code                                  = (int) $data['ResponseStatus']['ErrorCode'];

	/* 401-wrong key | 500-no key */
	if ( 401 === $code || 500 === $code ) {
		return false;
	}

	log_apikey( $api_key );

	return true;

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

	global $integration_delete_call;

	$url       = create_json_url( $integration_delete_call ) . '&IntegrationUpdateIDToDelete=' . rawurlencode( $id );
	$json_data = perform_call_to_megaventory( $url );
	$data      = json_decode( $json_data, true );

}

/**
 * Pull Product changes.
 */
function pull_product_changes() {

	global $integration_get_call;

	$url       = create_json_url_filter( $integration_get_call, 'Application', 'Equals', 'Woocommerce' );
	$json_data = perform_call_to_megaventory( $url );
	$data      = json_decode( $json_data, true );

	return $data;
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
