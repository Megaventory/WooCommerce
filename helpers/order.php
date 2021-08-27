<?php
/**
 * Sales order helper.
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

	/* This file contains a method used for placing Sales Orders */
	require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/api.php';
	require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/address.php';
	require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mv-constants.php';

/**
 * Get Megaventory Sales order based on Sales order ID
 *
 * @param WC_Order $order as WooCommerce Order.
 * @return array|null as mvSalesOrder.
 */
function get_sales_order( $order ) {

	$megaventory_sales_order_id = get_post_meta( $order->get_id(), 'order_sent_to_megaventory', true );

	if ( empty( $megaventory_sales_order_id ) ) {

		return null;
	}

	$get_body = array(
		'Filters' => array(
			array(
				'FieldName'      => 'SalesOrderId',
				'SearchOperator' => 'Equals',
				'SearchValue'    => $megaventory_sales_order_id,
			),
		),
	);

	$url      = get_url_for_call( MV_Constants::SALES_ORDER_GET );
	$response = send_request_to_megaventory( $url, $get_body );

	if ( 0 === count( $response['mvSalesOrders'] ) ) {
		return null;
	}

	return $response['mvSalesOrders'][0];
}

/**
 * WooCommerce's Purchase Order is translated to a Megaventory Sales Order
 * Order is of type WC_ORDER - find documentation online
 *
 * @param WC_Order $order as wc_order.
 * @param Client   $client as client object.
 * @return mixed
 */
function place_sales_order( $order, $client ) {

	$url                      = get_url_for_call( MV_Constants::SALES_ORDER_UPDATE );
	$percentage_order_coupons = array();
	$product_coupons          = array();
	$product_ids_in_cart      = array();
	$sales_array              = array();
	$fixed_order_coupons      = array();

	$coupon_names_order_tags = MV_Constants::COUPONS_APPLIED;

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

	$order_quantity = get_order_quantity( $order );

	foreach ( $order->get_items() as $item ) {

		// Get the price we actually want to discount, based on settings.
		$use_discount_sequentially = ( 'yes' === get_option( 'woocommerce_calc_discounts_sequentially', 'no' ) ) ? true : false;

		$product = new Product();
		if ( 0 === $item['variation_id'] ) {

			$product = Product::wc_find_product( $item['product_id'] );
		} else {

			$product = Product::wc_find_product( $item['variation_id'] );
		}

		array_push( $product_ids_in_cart, $product->wc_id );

		// Discount.

		$percentage_product_coupons = array();

		foreach ( $percentage_order_coupons as $coupon ) {

			if ( apply_coupon( $product, $coupon ) ) {

				array_push( $percentage_product_coupons, $coupon );
			}
		}

		$discount = null;

		if ( 1 === count( $percentage_product_coupons ) ) {

			$discount = $percentage_product_coupons[0];

			$discount->load_corresponding_discount_from_megaventory();

		} elseif ( count( $percentage_product_coupons ) > 1 && ! $use_discount_sequentially ) {

			/* create compound; */
			$ids = array();

			foreach ( $percentage_product_coupons as $coupon ) {

				array_push( $ids, $coupon->wc_id );
			}

			$discount = Coupon::mv_get_or_create_compound_percent_coupon( $ids );
		}

		/*
			In case we have in the same order percentage and fixed discounts we will add them name in order tags.
			We will not send a discount in sales_row.
			The price will be the discounted.
		*/
		if ( ( ! empty( $percentage_order_coupons ) && ! empty( $fixed_order_coupons ) ) || ( ! empty( $percentage_order_coupons ) && ! empty( $product_coupons ) ) ) {

			$discount = null;
		} else {

			$coupon_names_order_tags = '';
		}

		// Price.
		$unit_price = get_unit_price_prediscounted( $item, $discount );

		// Tax.

		/*
		Woocommerce applies the taxes one by one to the (post discounted price if the order has discounts) price and then
		adds all of them. We create in Megaventory a compound tax with all the rates.
		So sometimes it is possible to have miss match in the second percentage digit.
		*/
		$tax = Tax::get_sales_row_tax( $item );

		$salesrowelement = new \stdClass();

		$salesrowelement->salesorderrowproductsku                    = $product->sku;
		$salesrowelement->salesorderrowquantity                      = $item->get_quantity();
		$salesrowelement->salesorderrowshippedquantity               = 0;
		$salesrowelement->salesorderrowinvoicedquantity              = 0;
		$salesrowelement->salesorderrowdiscountid                    = ( ! empty( $discount->mv_id ) ? $discount->mv_id : 0 );
		$salesrowelement->salesorderrowtaxid                         = ( ! empty( $tax->mv_id ) ? $tax->mv_id : 0 );
		$salesrowelement->salesorderrowunitpricewithouttaxordiscount = $unit_price;

		array_push( $sales_array, $salesrowelement );
	}

	foreach ( $order->get_items( 'shipping' ) as $shipping_method ) {

		$tax = Tax::get_sales_row_tax( $shipping_method );

		$shippingrowelement = new \stdClass();

		$shippingrowelement->salesorderrowproductsku                    = 'shipping';
		$shippingrowelement->salesorderrowproductdescription            = $shipping_method->get_name();
		$shippingrowelement->salesorderrowquantity                      = 1;
		$shippingrowelement->salesorderrowshippedquantity               = 0;
		$shippingrowelement->salesorderrowinvoicedquantity              = 0;
		$shippingrowelement->salesorderrowunitpricewithouttaxordiscount = $shipping_method->get_data()['total'];
		$shippingrowelement->salesorderrowtaxid                         = ( ! empty( $tax->mv_id ) ? $tax->mv_id : 0 );

		array_push( $sales_array, $shippingrowelement );
	}

	/* ACTUAL ORDER */

	$shipping_address['name']     = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
	$shipping_address['company']  = $order->get_shipping_company();
	$shipping_address['line_1']   = $order->get_shipping_address_1();
	$shipping_address['line_2']   = $order->get_shipping_address_2();
	$shipping_address['city']     = $order->get_shipping_city();
	$shipping_address['county']   = $order->get_shipping_state();
	$shipping_address['postcode'] = $order->get_shipping_postcode();
	$shipping_address['country']  = $order->get_shipping_country();
	$shipping_address             = format_address( $shipping_address );

	$billing_address['name']     = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
	$billing_address['company']  = $order->get_billing_company();
	$billing_address['line_1']   = $order->get_billing_address_1();
	$billing_address['line_2']   = $order->get_billing_address_2();
	$billing_address['city']     = $order->get_billing_city();
	$billing_address['county']   = $order->get_billing_state();
	$billing_address['postcode'] = $order->get_billing_postcode();
	$billing_address['country']  = $order->get_billing_country();

	if ( ! $order->get_user() ) { // get_user returns false if order customer was guest.
		$billing_address['phone'] = $order->get_billing_phone();
		$billing_address['email'] = $order->get_billing_email();
	}

	$billing_address = format_address( $billing_address );
	$billing_address = wp_strip_all_tags( $billing_address );

	$order_object = new \stdClass();
	$order_obj    = new \stdClass();

	$order_tags = '';

	if ( ! empty( $coupon_names_order_tags ) ) {
		$order_tags = $coupon_names_order_tags . PHP_EOL . PHP_EOL;
	}

	$order_tags .= $order->get_payment_method_title(); // ex: instead of 'cod' it will return 'Cash on delivery'.

	$order_object->salesorderreferenceno          = $order->get_order_number();
	$order_object->salesorderreferenceapplication = 'woocommerce';
	$order_object->salesorderclientid             = $client->mv_id;
	$order_object->salesordercontactperson        = $client->contact_name;
	$order_object->salesorderbillingaddress       = str_replace( "\n", ' ', $billing_address );
	$order_object->salesordershippingaddress      = str_replace( "\n", ' ', $shipping_address );
	$order_object->salesordercomments             = $order->get_customer_note();
	$order_object->salesordertags                 = $order_tags;
	$order_object->salesorderdetails              = $sales_array;
	$order_object->salesorderinventorylocationid  = (int) get_option( 'default-megaventory-inventory-location' );
	$order_object->salesorderstatus               = 'Verified';

	$order_obj->mvsalesorder = $order_object;
	$json_object             = wrap_json( $order_obj );

	/**
	 * $json_object = wp_json_encode( $order_obj );
	 */

	$data = send_json( $url, $json_object );

	return $data;
}

/**
 * Cancel order in Megaventory
 *
 * @param WC_Order $order as WooCommerce order.
 * @return void
 */
function cancel_sales_order( $order ) {

	$megaventory_sales_order_id = get_post_meta( $order->get_id(), 'order_sent_to_megaventory', true );

	if ( empty( $megaventory_sales_order_id ) ) {

		return;
	}

	$mv_sales_order = get_sales_order( $order );

	if ( null === $mv_sales_order ) {

		$args = array(
			'type'        => 'error',
			'entity_name' => 'order by: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'entity_id'   => array( 'wc' => $order->get_id() ),
			'problem'     => 'Order Cancellation failed in Megaventory.',
			'full_msg'    => "Sales Order with mvSalesOrderId:{$megaventory_sales_order_id} does not exist. Cancel Failed.",
			'error_code'  => 'OrderNotFound',
			'json_object' => '',
		);

		$e = new MVWC_Error( $args );

		return;
	}

	if ( 'Cancelled' === $mv_sales_order['SalesOrderStatus'] ) {
		return;
	}

	$cancel_body = array(
		'mvSalesOrderNoToCancel' => $mv_sales_order['SalesOrderNo'],
		'mvSalesOrderTypeId'     => $mv_sales_order['SalesOrderTypeId'],
	);

	$url      = get_url_for_call( MV_Constants::SALES_ORDER_CANCEL );
	$response = send_request_to_megaventory( $url, $cancel_body );

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

		return;
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

/**
 * Get total order quantity.
 *
 * @param WC_Order $order as WC_Order.
 * @return float
 */
function get_order_quantity( $order ) {

	$order_qty = 0;
	foreach ( $order->get_items() as $item ) {

		$order_qty += $item->get_quantity();
	}

	return $order_qty;
}

/**
 * Get price with percentage amount if applied.
 *
 * @param WC_Order_Item_Product $item_data as order item.
 * @param Discount              $discount as Megaventory discount.
 * @return float
 */
function get_unit_price_prediscounted( $item_data, $discount ) {

	$line_quantity = $item_data->get_quantity();

	$unit_total = $item_data->get_total() / $line_quantity;

	$line_subtotal = $item_data->get_subtotal();

	if ( $discount ) {

		$unit_total = ( $line_subtotal / $line_quantity );
	}

	return $unit_total; // This is unit price with percentage amount if applied.

}

/**
 * Delete megaventory data from orders.
 *
 * @return void
 */
function delete_mv_data_from_orders() {

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

		delete_post_meta( $order_id, 'order_sent_to_megaventory' );
		delete_post_meta( $order_id, 'megaventory_order_name' );
	}
}
