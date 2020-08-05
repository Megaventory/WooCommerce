<?php
/**
 * Client class.
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
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/address.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-error.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-errors.php';
/**
 * This class works as a model for a client.
 * Clients are only transferred from WC to MV.
 */
class Client {
	/**
	 * Megaventory client id.
	 *
	 * @var integer
	 */
	public $mv_id;

	/**
	 * WooCommerce client id.
	 *
	 * @var integer
	 */
	public $wc_id;

	/**
	 * Client's name
	 *
	 * @var string
	 */
	public $username;

	/**
	 * Contact name.
	 *
	 * @var string
	 */
	public $contact_name;

	/**
	 * Client's Billing address.
	 *
	 * @var string
	 */
	public $billing_address;

	/**
	 * Client's Shipping address.
	 *
	 * @var string
	 */
	public $shipping_address;

	/**
	 * Client's second Shipping address.
	 *
	 * @var string
	 */
	public $shipping_address2;

	/**
	 * Client's Phone number.
	 *
	 * @var string
	 */
	public $phone;

	/**
	 * Client's second Phone number.
	 *
	 * @var string
	 */
	public $phone2;

	/**
	 * Client's Tax id.
	 *
	 * @var string
	 */
	public $tax_id;

	/**
	 * Client's E-mail.
	 *
	 * @var string
	 */
	public $email;

	/**
	 * Client's Company.
	 *
	 * @var string
	 */
	public $company;

	/**
	 * Client type.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Errors messages.
	 *
	 * @var MVWC_Errors
	 */
	public $errors;

	/**
	 * Succeeded messages.
	 *
	 * @var MVWC_Successes
	 */
	public $successes;

	/**
	 * Supplier-client get call.
	 *
	 * @var string
	 */
	private static $supplierclient_get_call = 'SupplierClientGet';

	/**
	 * Supplier-client update call.
	 *
	 * @var string
	 */
	private static $supplierclient_update_call = 'SupplierClientUpdate';

	/**
	 * Supplier-client undelete call.
	 *
	 * @var string
	 */
	private static $supplierclient_undelete_call = 'SupplierClientUndelete';

	/**
	 * Supplier-client delete call.
	 *
	 * @var string
	 */
	private static $supplierclient_delete_call = 'SupplierClientDelete';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->errors    = new MVWC_Errors();
		$this->successes = new MVWC_Successes();
	}

	/**
	 * Get Error messages.
	 *
	 * @return array
	 */
	public function errors() {
		return $this->errors;
	}

	/**
	 * Get Succeeded messages.
	 *
	 * @return array
	 */
	public function successes() {
		return $this->successes;
	}

	/**
	 * Log client errors.
	 *
	 * @param string $problem as string.
	 * @param string $full_msg as string.
	 * @param string $code as string.
	 * @param string $type as string.
	 * @param string $json_object as string.
	 * @return void
	 */
	private function log_error( $problem, $full_msg, $code, $type = 'error', $json_object ) {

		$args = array(
			'entity_id'   => array(
				'wc' => $this->wc_id,
				'mv' => $this->mv_id,
			),
			'entity_name' => $this->username,
			'problem'     => $problem,
			'full_msg'    => $full_msg,
			'error_code'  => $code,
			'json_object' => $json_object,
			'type'        => $type,
		);
		$this->errors->log_error( $args );
	}

	/**
	 * Log succeeded messages.
	 *
	 * @param string $transaction_status as string.
	 * @param string $full_msg as string.
	 * @param string $code as string.
	 * @return void
	 */
	private function log_success( $transaction_status, $full_msg, $code ) {

		$args = array(
			'entity_id'          => array(
				'wc' => $this->wc_id,
				'mv' => $this->mv_id,
			),
			'entity_type'        => 'customer',
			'entity_name'        => $this->username,
			'transaction_status' => $transaction_status,
			'full_msg'           => $full_msg,
			'success_code'       => $code,
		);
		$this->successes->log_success( $args );
	}

	/**
	 * Get all clients from wooCommerce.
	 *
	 * @return Client[]
	 */
	public static function wc_all() {

		$clients = array();

		foreach ( get_users() as $user ) {

			$client = self::wc_convert( $user );

			if ( null === $client ) {
				continue;
			}

			array_push( $clients, $client );
		}

		return $clients;
	}

	/**
	 * Get all clients from Megaventory.
	 *
	 * @return array
	 */
	public static function mv_all() {

		$url       = create_json_url( self::$supplierclient_get_call );
		$json_data = perform_call_to_megaventory( $url );
		$clients   = json_decode( $json_data, true )['mvSupplierClients'];
		$temp      = array();

		foreach ( $clients as $client ) {
			array_push( $temp, self::mv_convert( $client ) );
		}
		return $temp;
	}

	/**
	 * Finds client in wooCommerce by id.
	 *
	 * @param integer $id as client's id.
	 * @return client.
	 */
	public static function wc_find( $id ) {

		$user = get_user_by( 'ID', $id );
		if ( ! $user ) {
			return null;
		}
		return self::wc_convert( $user );
	}

	/**
	 * Finds client in Megaventory by id.
	 *
	 * @param integer $id as client's id.
	 * @return null|client.
	 */
	public static function mv_find( $id ) {

		$url       = create_json_url_filter( self::$supplierclient_get_call, 'SupplierClientID', 'Equals', $id );
		$json_data = perform_call_to_megaventory( $url );
		$client    = json_decode( $json_data, true );
		if ( count( $client['mvSupplierClients'] ) <= 0 ) {
			return null;
		}
		return self::mv_convert( $client['mvSupplierClients'][0] );
	}

	/**
	 * Finds client in Megaventory by name.
	 *
	 * @param string $name as client's name.
	 * @return client.
	 */
	public static function mv_find_by_name( $name ) {

		$url       = create_json_url_filter( self::$supplierclient_get_call, 'SupplierClientName', 'Equals', rawurlencode( $name ) );
		$json_data = perform_call_to_megaventory( $url );
		$client    = json_decode( $json_data, true );
		if ( count( $client['mvSupplierClients'] ) <= 0 ) {
			return null;
		}
		return self::mv_convert( $client['mvSupplierClients'][0] );
	}

	/**
	 * Finds client in Megaventory by e-mail.
	 *
	 * @param string $email as client's e-mail.
	 * @return client.
	 */
	public static function mv_find_by_email( $email ) {

		$url       = create_json_url_filter( self::$supplierclient_get_call, 'SupplierClientEmail', 'Equals', rawurlencode( $email ) );
		$json_data = perform_call_to_megaventory( $url );
		$client    = json_decode( $json_data, true );
		if ( count( $client['mvSupplierClients'] ) <= 0 ) {
			return null;
		}

		return self::mv_convert( $client['mvSupplierClients'][0] );
	}

	/**
	 * Creates a default client in Megaventory.
	 *
	 * @return bool
	 */
	public static function create_default_client() {

		/* Create guest client in wc if does not exist yet. */
		$user_name = 'WooCommerce_Guest';
		$id        = username_exists( $user_name );
		if ( ! $id ) {
			$id = wp_create_user( 'WooCommerce_Guest', 'Random Garbage', 'WooCommerce@wordpress.com' );
			update_user_meta( $id, 'first_name', 'WooCommerce' );
			update_user_meta( $id, 'last_name', 'Guest' );
		}

		$wc_main  = self::wc_find( $id );
		$response = $wc_main->mv_save();

		update_option( 'woocommerce_guest', (string) $wc_main->wc_id );

		if ( $response ) {

			return true;
		}

		return false;
	}

	/**
	 * Converts wooCommerce client to client.
	 *
	 * @param WP_User $wc_client as wooCommerce client.
	 * @return Client
	 */
	private static function wc_convert( $wc_client ) {

		$accepted_roles = array( 'customer', 'subscriber' );

		array_intersect( $accepted_roles, $wc_client->roles );

		if ( empty( array_intersect( $accepted_roles, $wc_client->roles ) ) ) {// we want to save only customers users.
			return null;
		}

		$client        = new Client();
		$client->wc_id = $wc_client->ID;
		$client->mv_id = (int) get_user_meta( $wc_client->ID, 'mv_id', true );
		$client->email = $wc_client->user_email;

		$client->username = $wc_client->user_login;

		$client->contact_name = trim( get_user_meta( $wc_client->ID, 'first_name', true ) . ' ' . get_user_meta( $wc_client->ID, 'last_name', true ) );
		$ship_name            = get_user_meta( $wc_client->ID, 'shipping_first_name', true ) . ' ' . get_user_meta( $wc_client->ID, 'shipping_last_name', true );
		$client->company      = get_user_meta( $wc_client->ID, 'billing_company', true );

		$shipping_address['name']     = $ship_name;
		$shipping_address['company']  = $client->company;
		$shipping_address['line_1']   = get_user_meta( $wc_client->ID, 'shipping_address_1', true );
		$shipping_address['line_2']   = get_user_meta( $wc_client->ID, 'shipping_address_2', true );
		$shipping_address['city']     = get_user_meta( $wc_client->ID, 'shipping_city', true );
		$shipping_address['postcode'] = get_user_meta( $wc_client->ID, 'shipping_postcode', true );
		$shipping_address['country']  = get_user_meta( $wc_client->ID, 'shipping_country', true );
		$client->shipping_address     = format_address( $shipping_address );

		$billing_address['name']     = $client->contact_name;
		$billing_address['company']  = $client->company;
		$billing_address['line_1']   = get_user_meta( $wc_client->ID, 'billing_address_1', true );
		$billing_address['line_2']   = get_user_meta( $wc_client->ID, 'billing_address_2', true );
		$billing_address['city']     = get_user_meta( $wc_client->ID, 'billing_city', true );
		$billing_address['postcode'] = get_user_meta( $wc_client->ID, 'billing_postcode', true );
		$billing_address['country']  = get_user_meta( $wc_client->ID, 'billing_country', true );
		$client->billing_address     = format_address( $billing_address );

		$client->phone = get_user_meta( $wc_client->ID, 'billing_phone', true );
		$client->type  = 'Client'; // you can change it to 'Both' aka supplier and client.

		return $client;
	}

	/**
	 * Converts Megaventory client to Client.
	 *
	 * @param array $supplierclient as Megaventory client class.
	 * @return client
	 */
	private static function mv_convert( $supplierclient ) {

		$client                    = new Client();
		$client->mv_id             = $supplierclient['SupplierClientID'];
		$client->username          = $supplierclient['SupplierClientName'];
		$client->contact_name      = $supplierclient['SupplierClientName'];
		$client->shipping_address  = $supplierclient['SupplierClientShippingAddress1'];
		$client->shipping_address2 = $supplierclient['SupplierClientShippingAddress2'];
		$client->billing_address   = $supplierclient['SupplierClientBillingAddress'];
		$client->tax_id            = $supplierclient['SupplierClientTaxID'];
		$client->phone             = $supplierclient['SupplierClientPhone1'];
		$client->email             = $supplierclient['SupplierClientEmail'];
		$client->type              = $supplierclient['SupplierClientType'];

		return $client;
	}

	/**
	 * Save client to Megaventory.
	 *
	 * @return bool|array
	 */
	public function mv_save() {

		$url          = create_json_url( self::$supplierclient_update_call );
		$json_request = $this->generate_update_json();
		$data         = send_json( $url, $json_request );

		if ( array_key_exists( 'InternalErrorCode', $data ) ) {

			if ( 'SupplierClientAlreadyDeleted' === $data['InternalErrorCode'] ) {
				/* client must be undeleted first and then update */
				$undelete_data = self::mv_undelete( $data['entityID'] );

				if ( array_key_exists( 'InternalErrorCode', $undelete_data ) ) {
					$this->log_error( 'Client is deleted. Undelete failed', $undelete_data['ResponseStatus']['Message'], -1, 'error', $data['json_object'] );
					return false;
				}

				$this->mv_id = $data['entityID'];
				return $this->mv_save();
			}

			if ( 'SupplierClientNameAlreadyExists' === $data['InternalErrorCode'] ) {

				$this->mv_id = $data['entityID'];

				return $this->mv_save();
			}
		}

		if ( array_key_exists( 'mvSupplierClient', $data ) ) {

			update_user_meta( $this->wc_id, 'mv_id', $data['mvSupplierClient']['SupplierClientID'] );

			$this->mv_id = $data['mvSupplierClient']['SupplierClientID'];

			if ( 'Insert' === $json_request->mvrecordaction ) {

				$this->log_success( 'created', 'customer successfully created in Megaventory', 1 );
			} else {

				$this->log_success( 'updated', 'customer successfully updated in Megaventory', 1 );
			}
		} else {
			/* failed to save */
			$internal_error_code = ' [' . $data['InternalErrorCode'] . ']';
			$this->log_error( 'Client not saved to Megaventory' . $internal_error_code, $data['ResponseStatus']['Message'], -1, 'error', $data['json_object'] );
			return false;

		}

		return $data;
	}

	/**
	 * Create an object for client update.
	 *
	 * @return stdClass
	 */
	private function generate_update_json() {

		if ( ! empty( $this->mv_id ) ) {

			$mv_main = self::mv_find( $this->mv_id );

			if ( null === $mv_main ) {

				update_user_meta( $this->wc_id, 'mv_id', 0 );
				$this->mv_id = 0;
			}
		}

		$create_new = empty( $this->mv_id );
		$action     = ( $create_new ? 'Insert' : 'Update' );

		$product_update_client = new \stdClass();
		$product_client        = new \stdClass();

		$product_client->supplierclientid   = $create_new ? '' : $this->mv_id;
		$product_client->supplierclienttype = $this->type ? $this->type : 'Client';
		$product_client->supplierclientname = $this->username;

		$this->billing_address ? $product_client->supplierclientbillingaddress    = $this->contact_name . '\n' . $this->billing_address : '';
		$this->shipping_address ? $product_client->supplierclientshippingaddress1 = $this->shipping_address : '';
		$this->phone ? $product_client->supplierclientphone1                      = $this->phone : '';
		$this->email ? $product_client->supplierclientemail                       = $this->email : '';
		$product_client->supplierclientcomments                                   = 'WooCommerce client';

		$product_update_client->mvsupplierclient = $product_client;
		$product_update_client->mvrecordaction   = $action;

		$object_to_send = wrap_json( $product_update_client );
		/**
		 * $object_to_send = wp_json_encode( $object_to_send );
		 */

		return $object_to_send;

	}

	/**
	 * Undelete a client in Megaventory.
	 *
	 * @param integer $id as client's id.
	 * @return array
	 */
	public static function mv_undelete( $id ) {

		$url  = create_json_url( self::$supplierclient_undelete_call );
		$url .= '&SupplierClientIDToUndelete=' . $id;

		$call = perform_call_to_megaventory( $url );

		return json_decode( $call, true );
	}

	/**
	 * Clear Megaventory data.
	 *
	 * @return bool
	 */
	public function wc_reset_mv_data() {

		return delete_user_meta( $this->wc_id, 'mv_id' );

	}
}

