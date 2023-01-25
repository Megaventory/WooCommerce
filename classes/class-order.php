<?php
/**
 * Sales order.
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

/* This file contains a method used for placing Sales Orders */
require_once MEGAVENTORY__PLUGIN_DIR . 'class-api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/class-address.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mv-constants.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mv-order-item.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-coupon.php';

/**
 * Order class.
 */
class Order {

	/**
	 * WooCommerce's Purchase Order is translated to a Megaventory Sales Order
	 * Order is of type WC_ORDER - find documentation online
	 *
	 * @param \WC_Order $order as wc_order.
	 * @param Client    $client as client object.
	 * @return mixed
	 */
	public static function megaventory_place_sales_order_to_mv( $order, $client ) {

		$url            = \Megaventory\API::get_url_for_call( MV_Constants::SALES_ORDER_UPDATE );
		$coupons_arrays = self::fill_order_coupon_arrays( $order );

		$mv_order_item_arr    = MV_Order_Item::get_mv_items_from_wc_items( $order->get_items(), $coupons_arrays );
		$details_per_location = MV_Order_item::generate_items_per_location_dict( $mv_order_item_arr, $order, $coupons_arrays );

		$response_array = array();

		reset( $details_per_location ); // Reseting the array so internal pointer points to first element.

		$first_order_key = key( $details_per_location ); // Get current array key - which is the first key because we called reset().

		foreach ( $details_per_location as $loc_id => $details_array ) {

			$sales_array = self::generate_order_details( $details_array );

			if ( $loc_id === $first_order_key ) {
				self::append_shipping_to_order_details( $order, $sales_array ); // Add shipping on first order only. TODO: To be confirmed.
				self::append_additional_fees_to_order_details( $order, $sales_array ); // Add additional fees on first order only.
			}

			/* ACTUAL ORDER */
			$order_payload_array = self::generate_order_json( $order, $client, $loc_id, $coupons_arrays[ Coupon::ORDER_TAG_COUPONS_KEY ], $sales_array );

			$response_array[] = \Megaventory\API::send_request_to_megaventory( $url, $order_payload_array );

		}

		return $response_array;
	}

	/**
	 * Cancel related orders in Megaventory.
	 *
	 * @param \WC_Order $order as WooCommerce order.
	 * @return void
	 */
	public static function cancel_sales_orders( $order ) {

		$megaventory_sales_order_id = get_post_meta( $order->get_id(), MV_Constants::MV_RELATED_ORDER_ID_META, true );

		if ( empty( $megaventory_sales_order_id ) ) {

			return;
		}

		$mv_sales_orders = self::megaventory_get_related_mv_sales_orders( $order );

		if ( null === $mv_sales_orders ) {

			$args = array(
				'type'        => 'error',
				'entity_name' => 'order by: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'entity_id'   => array( 'wc' => $order->get_id() ),
				'problem'     => 'Order Cancellation failed in Megaventory.',
				'error_code'  => 'OrderNotFound',
				'json_object' => '',
			);

			// After ShippingZones Priority implementation, we can have multiple orders related to a single WC order, therefore we need to handle both cases.
			if ( is_array( $megaventory_sales_order_id ) ) {

				$mv_order_id = implode( ', ', $megaventory_sales_order_id );

				$args['full_msg'] = "Sales Orders with mvSalesOrderId values: [ {$mv_order_id} ] do not exist. Cancel Failed.";

			} else {
				$args['full_msg'] = "Sales Order with mvSalesOrderId: [ {$megaventory_sales_order_id} ] does not exist. Cancel Failed.";
			}

			$e = new MVWC_Error( $args );

			return;
		}

		foreach ( $mv_sales_orders as $mv_sales_order ) {

			if ( 'Cancelled' === $mv_sales_order['SalesOrderStatus'] ) {
				continue;
			}

			$cancel_body = array(
				'mvSalesOrderNoToCancel' => $mv_sales_order['SalesOrderNo'],
				'mvSalesOrderTypeId'     => $mv_sales_order['SalesOrderTypeId'],
			);

			$url      = \Megaventory\API::get_url_for_call( MV_Constants::SALES_ORDER_CANCEL );
			$response = \Megaventory\API::send_request_to_megaventory( $url, $cancel_body );

			if ( '0' !== $response['ResponseStatus']['ErrorCode'] ) {

				$args = array(
					'type'        => 'error',
					'entity_name' => 'order by: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
					'entity_id'   => array( 'wc' => $order->get_id() ),
					'problem'     => 'Order Cancellation failed in Megaventory.',
					'full_msg'    => $response['ResponseStatus']['Message'],
					'error_code'  => $response['ResponseStatus']['ErrorCode'],
					'json_object' => $response['json_object'],
				);

				$e = new MVWC_Error( $args );

				continue;
			}

			$args = array(
				'entity_id'          => array(
					'wc' => $order->get_id(),
					'mv' => $megaventory_sales_order_id,
				),
				'entity_type'        => 'order',
				'entity_name'        => $mv_sales_order['SalesOrderTypeAbbreviation'] . ' ' . $mv_sales_order['SalesOrderNo'],
				'transaction_status' => 'Cancel',
				'full_msg'           => 'The order has been cancelled to your Megaventory account',
				'success_code'       => 1,
			);

			$e = new MVWC_Success( $args );

		}
	}

	/**
	 * Delete megaventory data from orders.
	 *
	 * @return void
	 */
	public static function delete_mv_data_from_orders() {

		$page      = 1;
		$order_ids = array();

		$page_ids = array();

		while ( 1 === $page || ! empty( $page_ids ) ) {

			$page_ids = wc_get_orders(
				array(
					'return' => 'ids',
					'page'   => $page,
				)
			);

			$order_ids = array_merge( $order_ids, $page_ids );

			$page++;
		}

		foreach ( $order_ids as $order_id ) {

			delete_post_meta( $order_id, MV_Constants::MV_RELATED_ORDER_ID_META );
			delete_post_meta( $order_id, MV_Constants::MV_RELATED_ORDER_NAMES_META );
			delete_post_meta( $order_id, MV_Constants::MV_ORDER_STATUSES_META );

		}
	}

	/**
	 * Updates a wc_orders post meta value for related mv orders statuses.
	 *
	 * @param int   $order_id     The id of the WC Order.
	 * @param array $status_array Array with related order_id => status.
	 * @return void
	 */
	public static function update_mv_related_order_status_list( $order_id, $status_array ) {

		if ( empty( $order_id ) || $order_id <= 0 ) {
			return;
		}

		if ( empty( $status_array ) || ! is_array( $status_array ) ) {
			$status_array = array();
		}

		update_post_meta( $order_id, MV_Constants::MV_ORDER_STATUSES_META, $status_array );

	}

	/**
	 * Handles WC Order Status updates for one-to-many WC-MV order relation depending on the statuses of related mv orders.
	 *
	 * @param \WC_Order $wc_order                The WC Order object.
	 * @param int       $int_update_order_id     Integration Update Order ID.
	 * @param string    $int_update_order_status Integration Update Order Status.
	 * @param array     $related_mv_orders_arr   WC Order's related MV Orders array.
	 * @return void
	 */
	public static function handle_wc_order_status_update_for_multiple_orders( $wc_order, $int_update_order_id, $int_update_order_status, $related_mv_orders_arr ) {

		$updated_order_index = array_search( (int) $int_update_order_id, $related_mv_orders_arr, true );

		$status_array = array();

		if ( false === $updated_order_index ) {
			return;
		}

		$mv_order_id  = (int) $related_mv_orders_arr[ $updated_order_index ];
		$status_array = self::get_mv_related_orders_status_list( $wc_order->get_id() );

		$status_array[ $mv_order_id ] = $int_update_order_status;

		self::update_mv_related_order_status_list( $wc_order->get_id(), $status_array );

		$processing_orders = self::filter_related_orders_by_status( 'processing', $status_array );

		if ( count( $processing_orders ) > 0 ) { // ANY related mv order is 'processing' -> parent wc order is also 'processing'.
			$wc_order->set_status( 'processing' );
			$wc_order->save();
			return;
		}

		$closed_orders = self::filter_related_orders_by_status( 'completed', $status_array );

		if ( count( $closed_orders ) === count( $related_mv_orders_arr ) ) { // ALL are 'completed' -> parent is also 'completed'.
			$wc_order->set_status( 'completed' );
			$wc_order->save();
			return;
		}

		$pending_orders = self::filter_related_orders_by_status( 'on-hold', $status_array );

		if ( count( $pending_orders ) === count( $related_mv_orders_arr ) ) { // ALL are 'on-hold' -> parent is also 'on-hold'.
			$wc_order->set_status( 'on-hold' );
			$wc_order->save();
			return;
		}

		$cancelled_orders = self::filter_related_orders_by_status( 'cancelled', $status_array );

		if ( count( $cancelled_orders ) === count( $related_mv_orders_arr ) ) { // ALL are 'cancelled' -> parent is also 'cancelled'.
			$wc_order->set_status( 'cancelled' );
			$wc_order->save();
			return;
		}
	}

	/**
	 * Add an order to the queue of orders to sync to mv.
	 *
	 * @param int $order_id The id of the order to add to queue.
	 * @return void
	 */
	public static function add_order_to_sync_queue( $order_id ) {

		$existing_queue = get_option( MV_Constants::MV_ORDERS_TO_SYNC_OPT, array() );

		if ( array_key_exists( $order_id, $existing_queue ) ) {
			return;
		}

		$existing_queue[ $order_id ] = time();

		self::update_order_queue_option( $existing_queue );
	}

	/**
	 * Remove an order from the queue of orders to sync to mv.
	 *
	 * @param int $order_id The id of the order to remove from queue.
	 * @return void
	 */
	public static function remove_order_from_sync_queue( $order_id ) {

		$existing_queue = get_option( MV_Constants::MV_ORDERS_TO_SYNC_OPT, array() );

		if ( ! array_key_exists( $order_id, $existing_queue ) ) {
			return;
		}

		unset( $existing_queue[ $order_id ] );

		self::update_order_queue_option( $existing_queue );
	}

	/**
	 * Determines if required time has passed for an order to be synced to mv.
	 * Default: 2 Hours (7200 Seconds)
	 *
	 * @param int $timestamp_created The Unix Timestamp of the order's creation time.
	 * @param int $delay_time        Order sync delay time in seconds.
	 * @return boolean
	 */
	public static function is_time_to_sync_order_to_mv( $timestamp_created, $delay_time = false ) {

		if ( ! $delay_time ) {
			$delay_time = MV_Constants::MV_ORDER_SYNC_DEFAULT_SECONDS_TO_WAIT;
		}

		return time() - $timestamp_created >= $delay_time;
	}

	/**
	 * Get Megaventory Sales orders based on Sales order ID.
	 * Will return all MV orders related to given WC Order.
	 *
	 * @param \WC_Order $order as WooCommerce Order.
	 * @return array|null as an array of mvSalesOrder or null if no results exist.
	 */
	private static function megaventory_get_related_mv_sales_orders( $order ) {

		$megaventory_sales_order_id = get_post_meta( $order->get_id(), MV_Constants::MV_RELATED_ORDER_ID_META, true );

		if ( empty( $megaventory_sales_order_id ) ) {

			return null;
		}

		// We have to check if its an array of IDs first.
		// This is because after implementing Shipping Zone Priority, a WC Order can be related to multiple MV Orders.
		if ( is_array( $megaventory_sales_order_id ) ) {

			$get_body = array(
				'Filters' => array(),
			);

			foreach ( $megaventory_sales_order_id as $so_id ) {

				$get_body['Filters'][] = array(
					'FieldName'      => 'SalesOrderId',
					'SearchOperator' => 'Equals',
					'SearchValue'    => $so_id,
					'AndOr'          => 'Or',
				);

			}
		} else {

			$get_body = array(
				'Filters' => array(
					array(
						'FieldName'      => 'SalesOrderId',
						'SearchOperator' => 'Equals',
						'SearchValue'    => $megaventory_sales_order_id,
					),
				),
			);

		}

		$url      = \Megaventory\API::get_url_for_call( MV_Constants::SALES_ORDER_GET );
		$response = \Megaventory\API::send_request_to_megaventory( $url, $get_body );

		if ( 0 === count( $response['mvSalesOrders'] ) ) {
			return null;
		}

		return $response['mvSalesOrders'];
	}

	/**
	 * Fill coupons arrays.
	 *
	 * @param \WC_Order $order The woocommerce order object.
	 * @return array
	 */
	private static function fill_order_coupon_arrays( $order ) {

		$percentage_order_coupons = array();
		$product_coupons          = array();
		$fixed_order_coupons      = array();
		$coupon_names_order_tags  = MV_Constants::COUPONS_APPLIED;

		foreach ( $order->get_coupons() as $order_coupon ) {

			$coupon_post_obj = get_page_by_title( $order_coupon->get_code(), OBJECT, 'shop_coupon' );
			$coupon_id       = $coupon_post_obj->ID;

			$coupon = Coupon::wc_find_coupon( $coupon_id );

			if ( 'fixed_product' === $coupon->type ) {

				array_push( $product_coupons, $coupon );

			} elseif ( 'fixed_cart' === $coupon->type ) {

				array_push( $fixed_order_coupons, $coupon );

			} elseif ( 'percent' === $coupon->type ) {

				array_push( $percentage_order_coupons, $coupon );
			}

			$coupon_names_order_tags .= $coupon->name . MV_Constants::MV_SEPARATOR;
		}

		if ( MV_Constants::COUPONS_APPLIED !== $coupon_names_order_tags ) {

			$coupon_names_order_tags = preg_replace( '/' . MV_Constants::MV_SEPARATOR . '$/', '', $coupon_names_order_tags );
		}

		$coupons_array                                   = array();
		$coupons_array[ Coupon::FIXED_COUPONS_KEY ]      = $fixed_order_coupons;
		$coupons_array[ Coupon::PRODUCT_COUPONS_KEY ]    = $product_coupons;
		$coupons_array[ Coupon::PERCENTAGE_COUPONS_KEY ] = $percentage_order_coupons;
		$coupons_array[ Coupon::ORDER_TAG_COUPONS_KEY ]  = $coupon_names_order_tags;

		return $coupons_array;

	}

	/**
	 * Get WooCommerce Payment Methods as a key-value pair
	 *
	 * @return array
	 */
	public static function get_wc_payment_methods() {
		$wc_payment_methods = array();

		foreach ( WC()->payment_gateways()->payment_gateways as $wc_payment_method ) {
			$wc_payment_methods[ $wc_payment_method->id ] = $wc_payment_method->title;
		}

		return $wc_payment_methods;
	}

	/**
	 * Get the available Megaventory Payment Methods as key-value pair
	 *
	 * @return array
	 */
	public static function get_mv_payment_methods() {
		$mv_payment_methods = array(
			'None'                 => '-- Not a payment --',
			'Cash'                 => 'Cash',
			'BankTransfer'         => 'Bank Transfer',
			'Check'                => 'Cheque',
			'Credit'               => '(On) Credit',
			'CustomPaymentMethod1' => 'Custom Payment Method',
			'CustomPaymentMethod2' => 'Custom Payment Method Alt 1',
			'CustomPaymentMethod3' => 'Custom Payment Method Alt 2',
			'Other'                => 'Other',
		);

		return $mv_payment_methods;
	}

	/**
	 * Generate salesorderdetails array to send.
	 *
	 * @param MV_Order_Item[] $mv_order_items Array of MV_Order_Items with relevant info.
	 * @return array
	 */
	private static function generate_order_details( $mv_order_items ) {

		$sales_array = array();

		foreach ( $mv_order_items as $item ) {

			$salesrowelement = array();

			$salesrowelement['salesorderrowproductsku']                    = $item->related_product->sku;
			$salesrowelement['salesorderrowquantity']                      = $item->quantity_in_order;
			$salesrowelement['salesorderrowshippedquantity']               = 0;
			$salesrowelement['salesorderrowinvoicedquantity']              = 0;
			$salesrowelement['salesorderrowdiscountid']                    = $item->discount_id;
			$salesrowelement['salesorderrowtaxid']                         = $item->tax_id;
			$salesrowelement['salesorderrowunitpricewithouttaxordiscount'] = $item->price_in_order;

			if ( $item->is_bundled_item ) {
				$salesrowelement['salesorderrowproductdescription'] = $item->related_product->description;
			}

			$sales_array[] = $salesrowelement;

		}

		return $sales_array;

	}

	/**
	 * Parse shipping lines from wc order and append to mv order details array.
	 *
	 * @param \WC_Order $order The wc order object.
	 * @param array     $sales_array The sales order details array passed by ref.
	 * @return void
	 */
	private static function append_shipping_to_order_details( $order, &$sales_array ) {

		foreach ( $order->get_items( 'shipping' ) as $shipping_method ) {

			$tax = Tax::get_sales_row_tax( $shipping_method );

			$shippingrowelement = array();

			$shippingrowelement['salesorderrowproductsku']                    = 'shipping';
			$shippingrowelement['salesorderrowproductdescription']            = $shipping_method->get_name();
			$shippingrowelement['salesorderrowquantity']                      = 1;
			$shippingrowelement['salesorderrowshippedquantity']               = 0;
			$shippingrowelement['salesorderrowinvoicedquantity']              = 0;
			$shippingrowelement['salesorderrowunitpricewithouttaxordiscount'] = $shipping_method->get_data()['total'];
			$shippingrowelement['salesorderrowtaxid']                         = ( ! empty( $tax->mv_id ) ? $tax->mv_id : 0 );

			$sales_array[] = $shippingrowelement;
		}
	}

	/**
	 * Parse extra fees from wc order and append to mv order details array.
	 *
	 * @param \WC_Order $order The wc order object.
	 * @param array     $sales_array The sales order details array passed by ref.
	 * @return void
	 */
	private static function append_additional_fees_to_order_details( $order, &$sales_array ) {

		$megaventory_extra_fee_product_id = get_option( 'megaventory_extra_fee_product_id', null );

		if ( empty( $megaventory_extra_fee_product_id ) ) {
			Product::create_additional_fee_service();
			$megaventory_extra_fee_product_id = get_option( 'megaventory_extra_fee_product_id', null );
		}

		$extra_fees = $order->get_fees();

		foreach ( $extra_fees as $fee ) {

			$fee_name = $fee->get_name();

			$fee_name_valid_for_mv = \Megaventory\Helpers\Tools::mv_trim_to_max_length( $fee_name, \Megaventory\Models\MV_Constants::DEFAULT_STRING_MAX_LENGTH );

			$fee_name_valid_for_mv = \Megaventory\Helpers\Tools::mv_remove_special_chars( $fee_name_valid_for_mv );

			$fee_amount = $fee->get_amount();

			$fee_tax = Tax::get_sales_row_tax( $fee );

			$fee_sku = get_option( 'megaventory_extra_fee_sku', \Megaventory\Models\MV_Constants::DEFAULT_EXTRA_FEE_SERVICE_SKU );

			$sales_array[] = array(
				'salesorderrowproductid'          => $megaventory_extra_fee_product_id,
				'salesorderrowproductsku'         => $fee_sku, // always needs a SKU even when provided with product id.
				'salesorderrowproductdescription' => $fee_name_valid_for_mv,
				'salesorderrowquantity'           => 1,
				'salesorderrowunitpricewithouttaxordiscount' => $fee_amount,
				'salesorderrowtaxid'              => $fee_tax->mv_id,
			);

		}
	}

	/**
	 * Generate the actual sales order json object to send to mv.
	 *
	 * @param \WC_Order $order The wc order object.
	 * @param Client    $client Client instance for this order.
	 * @param int       $location_id Location ID to create the order in.
	 * @param string    $coupon_names_order_tags Coupons concatenated string.
	 * @param array     $sales_array Sales Order Details to include in this order.
	 * @return array
	 */
	private static function generate_order_json( $order, $client, $location_id, $coupon_names_order_tags, $sales_array ) {

		$order_object      = array();
		$order_update_json = array();
		$order_addresses   = array();

		$address_objects = \Megaventory\Helpers\Address::generate_addresses_array_from_order( $order );

		$shipping_address = \Megaventory\Helpers\Address::format_multifield_address( $address_objects['shipping'], MV_Constants::ADDRESS_TYPE_SHIPPING_1 );
		$billing_address  = \Megaventory\Helpers\Address::format_multifield_address( $address_objects['billing'], MV_Constants::ADDRESS_TYPE_BILLING );

		array_push( $order_addresses, $shipping_address, $billing_address );

		if ( empty( $location_id ) || 0 === $location_id ) {
			$location_id = (int) get_option( 'default-megaventory-inventory-location' );
		}

		$order_tags = '';

		if ( ! empty( $coupon_names_order_tags ) ) {
			$order_tags = $coupon_names_order_tags . PHP_EOL . PHP_EOL;
		}

		$order_tags .= $order->get_payment_method_title(); // ex: instead of 'cod' it will return 'Cash on delivery'.

		$contact_name = $client->contact_name;

		if ( false !== strpos( $contact_name, 'WooCommerce Guest' ) ) {

			if ( '' === trim( $address_objects['billing']['name'] ) ) {

				$contact_name = trim( $address_objects['shipping']['name'] );

			} else {

				$contact_name = trim( $address_objects['billing']['name'] );

			}
		}

		$order_object['salesorderno']                   = $order->get_id();
		$order_object['salesorderreferenceno']          = $order->get_id();
		$order_object['salesorderreferenceapplication'] = 'woocommerce';
		$order_object['salesorderclientid']             = $client->mv_id;
		$order_object['salesordercontactperson']        = $contact_name;
		$order_object['salesorderaddresses']            = $order_addresses;
		$order_object['salesordercomments']             = $order->get_customer_note();
		$order_object['salesordertags']                 = $order_tags;
		$order_object['salesorderdetails']              = $sales_array;
		$order_object['salesorderinventorylocationid']  = $location_id;
		$order_object['salesorderpaymentmethod']        = self::get_mv_payment_method( $order->get_payment_method() );
		$order_object['salesorderstatus']               = 'Verified';

		$order_update_json['mvsalesorder'] = $order_object;

		return $order_update_json;

	}

	/**
	 * Given a WooCommerce order status and an array of order_id=>mv order status,
	 * it returns a filtered array with the mv statuses that correspond to the one given.
	 *
	 * @param string $wc_status    The WC Status to search for.
	 * @param array  $status_array An Array of mv_order_id => mv_order_status.
	 * @return array
	 */
	private static function filter_related_orders_by_status( $wc_status, $status_array ) {

		$wc_order_status_mappings = MV_Constants::MV_DOCUMENT_STATUS_TO_WC_ORDER_STATUS_MAPPINGS;

		$filtered_orders = array_filter(
			$status_array,
			function ( $status ) use ( $wc_order_status_mappings, $wc_status ) {
				return $wc_status === $wc_order_status_mappings[ $status ];
			}
		);

		return $filtered_orders;

	}

	/**
	 * Get an array of related orders last pulled statuses from mv for a wc order.
	 *
	 * @param int $order_id The id of the WC Order.
	 * @return array
	 */
	private static function get_mv_related_orders_status_list( $order_id ) {

		$related_orders_statuses = get_post_meta( $order_id, MV_Constants::MV_ORDER_STATUSES_META, true );

		if ( empty( $related_orders_statuses ) || ! is_array( $related_orders_statuses ) ) {

			$related_orders_statuses = array();

		}

		return $related_orders_statuses;

	}

	/**
	 * Update the array of orders to sync
	 *
	 * @param array $order_queue_arr The value to add in options table.
	 * @return void
	 */
	private static function update_order_queue_option( $order_queue_arr ) {

		update_option( MV_Constants::MV_ORDERS_TO_SYNC_OPT, $order_queue_arr );

	}

	/**
	 * Get MV Payment Method
	 *
	 * @param string $wc_payment_method The woocommerce payment method.
	 * @return string
	 */
	public static function get_mv_payment_method( $wc_payment_method ) {

		$mappings = get_option( \Megaventory\Models\MV_Constants::MV_PAYMENT_METHOD_MAPPING_OPT, array() );

		if ( ! is_array( $mappings ) || ! array_key_exists( $wc_payment_method, $mappings ) ) {
			return \Megaventory\Models\MV_Constants::DEFAULT_MEGAVENTORY_PAYMENT_METHOD;
		}

		return $mappings[ $wc_payment_method ];

	}

}

