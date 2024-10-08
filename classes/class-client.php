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

namespace Megaventory\Models;

/**
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'class-api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/class-address.php';
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
	 * @var array
	 */
	public $billing_address;

	/**
	 * Client's Shipping address.
	 *
	 * @var array
	 */
	public $shipping_address;

	/**
	 * Client's second Shipping address.
	 *
	 * @var array
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
	 * Constructor
	 */
	public function __construct() {
		$this->errors    = new MVWC_Errors();
		$this->successes = new MVWC_Successes();
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
	private function log_error( $problem, $full_msg, $code, $type = 'error', $json_object = '' ) {

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
	public static function wc_get_all_clients() {

		$clients = array();

		$wp_users = self::wc_get_all_wordpress_clients();

		foreach ( $wp_users as $user ) {

			$client = self::wc_convert( $user );

			if ( null === $client ) {
				continue;
			}

			array_push( $clients, $client );
		}

		return $clients;
	}

	/**
	 * Get clients in batches.
	 *
	 * @param int $limit number of clients to return.
	 * @param int $page pagination.
	 * @return Client[]
	 */
	public static function wc_get_wordpress_clients_in_batches( $limit, $page ) {

		$args = array(
			'role__in' => array( 'customer', 'subscriber' ),
			'number'   => $limit,
			'offset'   => ( $page - 1 ) * $limit,
		);

		$wp_users = get_users( $args );

		$clients = array();

		foreach ( $wp_users as $user ) {

			$client = self::wc_convert( $user );

			if ( null === $client ) {
				continue;
			}

			array_push( $clients, $client );
		}

		return $clients;
	}

	/**
	 * Get all clients.
	 *
	 * @return array List of users.
	 */
	public static function wc_get_all_wordpress_clients() {

		$args = array(
			'role__in' => array( 'customer', 'subscriber' ),
		);

		$wp_users = get_users( $args );

		return $wp_users;
	}

	/**
	 * Get the count of clients.
	 *
	 * @return int
	 */
	public static function wc_get_all_wordpress_clients_count() {

		$args = array(
			'role__in' => array( 'customer', 'subscriber' ),
			'fields'   => 'ids',
		);

		$wp_users = get_users( $args );

		return count( $wp_users );
	}

	/**
	 * Get the Default client
	 */
	public static function get_guest_mv_client() {

		$client = self::wc_find( (int) get_option( 'woocommerce_guest' ) );

		if ( ! empty( $client ) ) {

			return $client;
		}

		self::create_default_client();

		$client = self::wc_find( (int) get_option( 'woocommerce_guest' ) );

		return $client;
	}

	/**
	 * Finds client in wooCommerce by id.
	 *
	 * @param integer $id as client's id.
	 * @return Client|null
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
	 * @return null|Client.
	 */
	public static function mv_find( $id ) {

		$data = array(
			'Filters' => array(
				'FieldName'      => 'SupplierClientID',
				'SearchOperator' => 'Equals',
				'SearchValue'    => $id,
			),
		);

		$url      = \Megaventory\API::get_url_for_call( self::$supplierclient_get_call );
		$response = \Megaventory\API::send_request_to_megaventory( $url, $data );

		if ( count( $response['mvSupplierClients'] ) <= 0 ) {
			return null;
		}
		return self::mv_convert( $response['mvSupplierClients'][0] );
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

			$user_data = array(
				'user_login'   => 'WooCommerce_Guest',
				'user_pass'    => wp_generate_password( 12, false ),
				'user_email'   => '',
				'first_name'   => 'WooCommerce',
				'last_name'    => 'Guest',
				'display_name' => 'WooCommerce_Guest',
				'role'         => 'customer',
			);

			$id = wp_insert_user( $user_data );
		}

		$wc_main  = self::wc_find( $id );
		$response = $wc_main->mv_save();

		update_option( 'woocommerce_guest', $wc_main->wc_id );

		if ( $response ) {

			return true;
		}

		return false;
	}

	/**
	 * Deletes default client in WordPress.
	 *
	 * @return bool
	 */
	public static function delete_default_client() {

		$user_name = 'WooCommerce_Guest';
		$id        = username_exists( $user_name );
		if ( ! $id ) {

			wp_delete_user( $id );
		}

		return true;
	}

	/**
	 * Converts wooCommerce client to client.
	 *
	 * @param \WP_User $wc_client as wooCommerce client.
	 * @return Client|null
	 */
	private static function wc_convert( $wc_client ) {

		$accepted_roles = array( 'customer', 'subscriber' );

		array_intersect( $accepted_roles, $wc_client->roles );

		if ( empty( array_intersect( $accepted_roles, $wc_client->roles ) ) ) {// we want to save only customers users.
			return null;
		}

		$user_meta = get_user_meta( $wc_client->ID );

		$client        = new Client();
		$client->wc_id = $wc_client->ID;
		$client->mv_id = empty( $user_meta['mv_id'][0] ) ? 0 : $user_meta['mv_id'][0];
		$client->email = $wc_client->user_email;

		$client->username = $wc_client->user_login;

		$client->contact_name = trim( strval( empty( $user_meta['first_name'][0] ) ? '' : $user_meta['first_name'][0] ) . ' ' . strval( empty( $user_meta['last_name'][0] ) ? '' : $user_meta['last_name'][0] ) );
		$ship_name            = trim( strval( empty( $user_meta['shipping_first_name'][0] ) ? '' : $user_meta['shipping_first_name'][0] ) . ' ' . strval( empty( $user_meta['shipping_last_name'][0] ) ? '' : $user_meta['shipping_last_name'][0] ) );
		$client->company      = strval( empty( $user_meta['billing_company'][0] ) ? '' : $user_meta['billing_company'][0] );

		$shipping_address['name']     = $ship_name;
		$shipping_address['company']  = $client->company;
		$shipping_address['line_1']   = strval( empty( $user_meta['shipping_address_1'][0] ) ? '' : $user_meta['shipping_address_1'][0] );
		$shipping_address['line_2']   = strval( empty( $user_meta['shipping_address_2'][0] ) ? '' : $user_meta['shipping_address_2'][0] );
		$shipping_address['city']     = strval( empty( $user_meta['shipping_city'][0] ) ? '' : $user_meta['shipping_city'][0] );
		$shipping_address['postcode'] = strval( empty( $user_meta['shipping_postcode'][0] ) ? '' : $user_meta['shipping_postcode'][0] );
		$shipping_address['country']  = strval( empty( $user_meta['shipping_country'][0] ) ? '' : $user_meta['shipping_country'][0] );

		$client->shipping_address = \Megaventory\Helpers\Address::format_multifield_address( $shipping_address, MV_Constants::ADDRESS_TYPE_SHIPPING_1 );

		$billing_address['name']     = $client->contact_name;
		$billing_address['company']  = $client->company;
		$billing_address['line_1']   = strval( empty( $user_meta['billing_address_1'][0] ) ? '' : $user_meta['billing_address_1'][0] );
		$billing_address['line_2']   = strval( empty( $user_meta['billing_address_2'][0] ) ? '' : $user_meta['billing_address_2'][0] );
		$billing_address['city']     = strval( empty( $user_meta['billing_city'][0] ) ? '' : $user_meta['billing_city'][0] );
		$billing_address['postcode'] = strval( empty( $user_meta['billing_postcode'][0] ) ? '' : $user_meta['billing_postcode'][0] );
		$billing_address['country']  = strval( empty( $user_meta['billing_country'][0] ) ? '' : $user_meta['billing_country'][0] );

		$client->billing_address = \Megaventory\Helpers\Address::format_multifield_address( $billing_address, MV_Constants::ADDRESS_TYPE_BILLING );

		$client->phone = strval( empty( $user_meta['billing_phone'][0] ) ? '' : $user_meta['billing_phone'][0] );
		$client->type  = 'Client'; // you can change it to 'Both' aka supplier and client.

		return $client;
	}

	/**
	 * Converts Megaventory client to Client.
	 *
	 * @param array $supplierclient as Megaventory client class.
	 * @return Client
	 */
	private static function mv_convert( $supplierclient ) {

		$client               = new Client();
		$client->mv_id        = $supplierclient['SupplierClientID'];
		$client->username     = $supplierclient['SupplierClientName'];
		$client->contact_name = $supplierclient['SupplierClientName'];
		$client->tax_id       = $supplierclient['SupplierClientTaxID'];
		$client->phone        = $supplierclient['SupplierClientPhone1'];
		$client->email        = $supplierclient['SupplierClientEmail'];
		$client->type         = $supplierclient['SupplierClientType'];

		$shipping_address_1 = array_filter(
			$supplierclient['SupplierClientAddresses'],
			function ( $val ) {
				return MV_Constants::ADDRESS_TYPE_SHIPPING_1 === $val['AddressType'];
			}
		);

		$shipping_address_2 = array_filter(
			$supplierclient['SupplierClientAddresses'],
			function ( $val ) {
				return MV_Constants::ADDRESS_TYPE_SHIPPING_2 === $val['AddressType'];
			}
		);

		$billing_address = array_filter(
			$supplierclient['SupplierClientAddresses'],
			function ( $val ) {
				return MV_Constants::ADDRESS_TYPE_BILLING === $val['AddressType'];
			}
		);

		$client->shipping_address  = reset( $shipping_address_1 );
		$client->shipping_address2 = reset( $shipping_address_2 );
		$client->billing_address   = reset( $billing_address );

		return $client;
	}

	/**
	 * Save client to Megaventory.
	 *
	 * @return bool|array
	 */
	public function mv_save() {

		$url          = \Megaventory\API::get_url_for_call( self::$supplierclient_update_call );
		$json_request = $this->generate_update_json();
		$data         = \Megaventory\API::send_request_to_megaventory( $url, $json_request );

		if ( array_key_exists( 'InternalErrorCode', $data ) ) {

			if ( 'SupplierClientAlreadyDeleted' === $data['InternalErrorCode'] ) {
				/* client must be undeleted first and then update */
				$undelete_data = self::mv_undelete( $data['entityID'] );

				if ( array_key_exists( 'InternalErrorCode', $undelete_data ) ) {
					$this->log_error( 'Customer is deleted. Undelete failed', $undelete_data['ResponseStatus']['Message'], -1, 'error', $data['json_object'] );
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

			if ( 'Insert' === $json_request['mvrecordaction'] ) {

				$this->log_success( 'created', 'customer successfully created in Megaventory', 1 );
			} else {

				$this->log_success( 'updated', 'customer successfully updated in Megaventory', 1 );
			}
		} else {
			/* failed to save */
			$internal_error_code = ' [' . $data['InternalErrorCode'] . ']';
			$this->log_error( 'Customer not saved to Megaventory' . $internal_error_code, $data['ResponseStatus']['Message'], -1, 'error', $data['json_object'] );
			return false;

		}

		return $data;
	}

	/**
	 * Delete client in Megaventory.
	 *
	 * @return bool
	 */
	public function delete_client_in_megaventory() {

		$data_to_send = array(
			'SupplierClientIDToDelete'              => $this->mv_id,
			'SupplierClientDeleteAction'            => 'DefaultAction',
			'mvInsertUpdateDeleteSourceApplication' => 'woocommerce',
		);

		$url = \Megaventory\API::get_url_for_call( MV_Constants::SUPPLIER_CLIENT_DELETE );

		$response = \Megaventory\API::send_request_to_megaventory( $url, $data_to_send );

		if ( '0' === ( $response['ResponseStatus']['ErrorCode'] ) ) {

			$this->log_success( 'deleted', 'customer successfully deleted in Megaventory', 1 );

			return true;
		} else {

			$internal_error_code = ' [' . $response['InternalErrorCode'] . ']';

			$this->log_error( 'Customer not deleted to Megaventory ' . $internal_error_code, $response['ResponseStatus']['Message'], -1, 'error', $response['json_object'] );

			return false;
		}
	}


	/**
	 * Create an array for client update.
	 *
	 * @return array
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

		$client_update    = array();
		$mv_client        = array();
		$client_addresses = array();

		$client_shipping_address = is_array( $this->shipping_address ) ? $this->shipping_address : array( 'AddressType' => MV_Constants::ADDRESS_TYPE_SHIPPING_1 );
		$client_billing_address  = is_array( $this->billing_address ) ? $this->billing_address : array( 'AddressType' => MV_Constants::ADDRESS_TYPE_BILLING );

		array_push( $client_addresses, $client_shipping_address, $client_billing_address );

		$mv_client['supplierclientid']        = $create_new ? '' : $this->mv_id;
		$mv_client['supplierclienttype']      = $this->type ? $this->type : 'Client';
		$mv_client['supplierclientname']      = $this->username;
		$mv_client['supplierclientphone1']    = $this->phone ? $this->phone : '';
		$mv_client['supplierclientemail']     = $this->email ? $this->email : '';
		$mv_client['supplierclientcomments']  = 'WooCommerce client';
		$mv_client['supplierclientaddresses'] = $client_addresses;

		$client_update['mvsupplierclient'] = $mv_client;
		$client_update['mvrecordaction']   = MV_Constants::MV_RECORD_ACTION['InsertOrUpdateNonEmptyFields'];

		return $client_update;
	}

	/**
	 * Undelete a client in Megaventory.
	 *
	 * @param integer $id as client's id.
	 * @return array
	 */
	public static function mv_undelete( $id ) {

		$data = array(
			'SupplierClientIDToUndelete' => $id,
		);

		$url = \Megaventory\API::get_url_for_call( self::$supplierclient_undelete_call );

		$call = \Megaventory\API::send_request_to_megaventory( $url, $data );

		return $call;
	}

	/**
	 * Clear Megaventory data.
	 *
	 * @return void
	 */
	public static function wc_reset_mv_data() {

		delete_post_meta_by_key( 'mv_id' );
	}
}
