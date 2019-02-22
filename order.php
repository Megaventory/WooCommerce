<?php

	/* This file contains a method used for placing Sales Orders */
	require_once("api.php");
	require_once("address.php");

	/*  WooCommerce's Purchase Order is translated to a Megaventory Sales Order
		$order is of type WC_ORDER - find documentation online */

	function place_sales_order($order, $client) { 
		$salesorder_update_call = "SalesOrderUpdate";
		$API_KEY = get_api_key();
		$url=create_json_url($salesorder_update_call);
		$fixed_order_coupons = array();
		$percent_order_coupons = array();
		$product_coupons = array();
		$product_ids_in_cart = array();
		$salesarray=array();
		$couponarray=array();
		$fixed_coupons_array=array();
		$salesarray_total=array();
		$couponarray_total=array();
		$fixed_coupons_array_total=array();

		foreach ($order->get_used_coupons() as $coupon_code) {
			$coupon = Coupon::WC_find_by_name($coupon_code);
			if ($coupon->type == "fixed_product") {
				array_push($product_coupons, $coupon);
			} elseif ($coupon->type == "fixed_cart") {
				array_push($fixed_order_coupons, $coupon);
			} elseif ($coupon->type == "percent") {
				array_push($percent_order_coupons, $coupon);
			}
		}

		$products_json="";
		foreach ($order->get_items() as $item) {
			$product = Product::wc_find($item['product_id']);
			array_push($product_ids_in_cart, $product->WC_ID);
			
			//PERCENTAGE COUPONS/////////////////////////////////////////////////////////////

			$eligible_percentage_coupons = array();
			foreach ($percent_order_coupons as $coupon) {

				if (apply_coupon($product, $coupon))
					array_push($eligible_percentage_coupons, $coupon);
			}
			
			$discount = null;

			if (count($eligible_percentage_coupons) == 1) {

				$discount = $eligible_percentage_coupons[0];
				$discount->MV_load_corresponding_obj_if_present();

			}
			elseif (count($eligible_percentage_coupons) > 1) {
				/* create compound; */
				$ids = array();
				foreach ($eligible_percentage_coupons as $coupon) {
					array_push($ids, $coupon->WC_ID);
				}

				$discount = Coupon::MV_get_or_create_compound_percent_coupon($ids);
			} 
			
			$price = ($product->sale_active ? $product->sale_price : $product->regular_price);

			//////////////////////////TAX////////////////////////////////////////////////////

			/* interpret product tax */
			$taxes = array();
			foreach($item->get_data()['taxes']['total'] as $id => $rate) {
				array_push($taxes, Tax::wc_find($id));
			}
			
			$tax = null;
			if (count($taxes) == 1) {
				$tax = $taxes[0];
			}
			elseif (count($taxes) > 1) {

				/*  calculate total tax rate
					$total_no_tax = $item->get_data()['total'] - $item->get_data()['total_tax']; 
					returns string of foreign keys separated by comma	*/

				$total_no_tax = $price;
				if ($discount) $total_no_tax *= (1.0-($discount->rate/100));
				
				$total_tax = ((float)$item->get_data()['total_tax'] / (float)$item->get_quantity());
				$rate = $total_tax / (float)$total_no_tax;
				$rate *= 100.0; //to percent
				$rate = round($rate, 2);
				$names = array();

				for ($i = 0; $i < count($taxes); $i++) {
					array_push($names, $taxes[$i]->name);
				}

				sort($names);
				$name = implode("_", $names);
				$name .= "__" . (string)$rate;
				$hash = hash('md5', $name);

				$tax = Tax::mv_find_by_name($hash);
				
				if ($tax == null) {
					$tax = new Tax();
					$tax->name = $hash;
					$tax->description = $name;
					$tax->rate = $rate;
					$tax->mv_save();
				}
				
			}
			
			//////////////////////////////////////////////////////////////////////////////////////////

			$productstring=new \stdClass();
			$productInfo=new \stdClass();
			$salesrowelement = new \stdClass(); 
			
			$salesrowelement->SalesOrderRowProductSKU=$product->SKU;
			$salesrowelement->SalesOrderRowQuantity=$item->get_quantity();
			$salesrowelement->SalesOrderRowShippedQuantity=0;
			$salesrowelement->SalesOrderRowInvoicedQuantity=0;
			$salesrowelement->SalesOrderRowUnitPriceWithoutTaxOrDiscount= $price;
		
			array_push($salesarray, $salesrowelement);
			
			////////////////////////////COUPON///////////////////////////////////////////////////////////

			foreach ($product_coupons as $coupon) {
				$couponarrayelement = new \stdClass();
				$apply = apply_coupon($product, $coupon);
				if ($apply) {
					$couponarrayelement->SalesOrderRowProductSKU=$coupon->name;
					$couponarrayelement->SalesOrderRowQuantity=$item->get_quantity();
					$couponarrayelement->SalesOrderRowShippedQuantity=0;
					$couponarrayelement->SalesOrderRowInvoicedQuantity=0;
					$couponarrayelement->SalesOrderRowUnitPriceWithoutTaxOrDiscount=(string)(-($coupon->rate));
					$couponarrayelement->SalesOrderRowTaxID=$tax?(string)$tax->MV_ID:"";
				}

				array_push($salesarray,$couponarrayelement);
					
				}
		}
		
		///////////////////////////////// CART COUPONS //////////////////////////////////////////////////////////
		
		foreach ($fixed_order_coupons as $coupon) {
			$fixedcouponarrayelement = new \stdClass();

			$fixedcouponarrayelement->SalesOrderRowProductSKU=$coupon->name;
			$fixedcouponarrayelement->SalesOrderRowQuantity=((string)1);
			$fixedcouponarrayelement->SalesOrderRowShippedQuantity=0;
			$fixedcouponarrayelement->SalesOrderRowInvoicedQuantity=0;
			$fixedcouponarrayelement->SalesOrderRowUnitPriceWithoutTaxOrDiscount=0;
			array_push($salesarray,$fixedcouponarrayelement);
		}
		
		/////////////////////////////////////////// ACTUAL ORDER //////////////////////////////////////////////
		$shipping_address['name'] = $order->get_shipping_first_name() . " " . $order->get_shipping_last_name();
		$shipping_address['company'] = $order->get_shipping_company();
		$shipping_address['line_1'] = $order->get_shipping_address_1();
		$shipping_address['line_2'] = $order->get_shipping_address_2();
		$shipping_address['city'] = $order->get_shipping_city();
		$shipping_address['county'] = $order->get_shipping_state();
		$shipping_address['postcode'] = $order->get_shipping_postcode();
		$shipping_address['country'] = $order->get_shipping_country();
		$shipping_address = format_address($shipping_address);
		
		$billing_address['name'] = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
		$billing_address['company'] = $order->get_billing_company();
		$billing_address['line_1'] = $order->get_billing_address_1();
		$billing_address['line_2'] = $order->get_billing_address_2();
		$billing_address['city'] = $order->get_billing_city();
		$billing_address['county'] = $order->get_billing_state();
		$billing_address['postcode'] = $order->get_billing_postcode();
		$billing_address['country'] = $order->get_billing_country();
		$billing_address = format_address($billing_address);
		$billing_address = wp_strip_all_tags($billing_address);
		
		$orderObject=new \stdClass();
		$orderObj=new \stdClass();

		$orderObject->SalesOrderReferenceNo=$order->get_order_number();
		$orderObject->SalesOrderReferenceApplication='woocommerce';
		$orderObject->SalesOrderClientID=$client->MV_ID;
		$orderObject->SalesOrderBillingAddress=str_replace("\n"," ",$shipping_address);
		$orderObject->SalesOrderShippingAddress=str_replace("\n"," ",$billing_address);
		$orderObject->SalesOrderComments= $order->get_customer_note();
		$orderObject->SalesOrderTags="WooCommerce";
		$orderObject->SalesOrderDetails= $salesarray;
		$orderObject->SalesOrderStatus="Pending";

		$orderObj->mvSalesOrder=$orderObject;
		$json_object=wrap_json($orderObj);
		$json_object=json_encode($orderObj);
		
		
		$data = send_json($url,$json_object);
		
		return $data;
	}
?>