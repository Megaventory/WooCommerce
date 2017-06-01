<?php

	require_once("address.php");

	$url = "https://apitest.megaventory.com/json/reply/";
	$xml_url = "https://apitest.megaventory.com/xml/reply/";
	$API_KEY = "b7d0cc59b72af1e5@m65192"; // DEV AND DEBUG ONLY
	
	$salesorder_update_call = "SalesOrderUpdate";
	$integration_get_call = "IntegrationUpdateGet";
	$integration_delete_call = "IntegrationUpdateDelete";
	
	$translate_order_status = array
	(
		'Verfied' => 'on-hold',
		'FullyShipped' => 'processing',
		'Closed' => 'completed'
	
	);
	
	$document_status = array
	(
		0 => 'ValidStatus',
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
		99 => 'Cancelled'
	);

	//$today = DaysOfWeek::Sunday;
		
	
	// create URL using the API key and call
	function create_json_url($call) {
		global $url, $API_KEY;
		return $url . $call . "?APIKEY=" . $API_KEY;
	}
	
	function create_xml_url($call) {
		global $xml_url, $API_KEY;
		return $xml_url . $call . "?APIKEY=" . $API_KEY;
	}
	
	function create_json_url_filter($call, $fieldName, $searchOperator, $searchValue) {
		return create_json_url($call) . "&Filters={FieldName:" . $fieldName . ",SearchOperator:" . $searchOperator . ",SearchValue:" . $searchValue ."}";
	}
	
	function send_xml($url, $xml_request) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, ($xml_request));
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		
		curl_close($ch);
		
		echo $data;
		
		$data = simplexml_load_string(html_entity_decode($data), "SimpleXMLElement", LIBXML_NOCDATA);
		$data = json_encode($data);
		$data = json_decode($data, TRUE);
		
		
		return $data;
	}
	
	function wrap_xml($call, $data) {
		global $API_KEY;
		$prefix = '
			<' . $call . ' xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">
				<APIKEY>' . $API_KEY . '</APIKEY>
			';
		$suffix = '
			</' . $call . '>
			';
		return $prefix . $data . $suffix;
	}
	
	function remove_integration_update($id) {
		global $integration_delete_call;
		$url = create_json_url($integration_delete_call) . "&IntegrationUpdateIDToDelete=" . urlencode($id);
		$data = json_decode(file_get_contents($url), true);
		
		echo "<br>RESPONSE: ";
		var_dump($data);
	}
	
	function pull_product_changes() {
		global $integration_get_call;
		$url = create_json_url_filter($integration_get_call, "Application", "Equals", "woocommerce");
		$data = json_decode(file_get_contents($url), true);
		
		return $data;
	}
	
//woocommerce purchase is megaventory sale
	//$order is of type WC_ORDER - find documentation online
	function place_sales_order($order, $client) { 
		global $salesorder_update_call, $API_KEY;
		$url = create_xml_url($salesorder_update_call);
		
		$products_xml = '';
		foreach ($order->get_items() as $item) {
			$product = new WC_Product($item['product_id']);
			$productstring = '<mvSalesOrderRow>';
			$productstring .= '<SalesOrderRowProductSKU>' . $product->get_sku() . '</SalesOrderRowProductSKU>';
			$productstring .= '<SalesOrderRowQuantity>' . $item['quantity'] . '</SalesOrderRowQuantity>';
			$productstring .= '<SalesOrderRowShippedQuantity>0</SalesOrderRowShippedQuantity>';
			$productstring .= '<SalesOrderRowInvoicedQuantity>0</SalesOrderRowInvoicedQuantity>';
			$productstring .= '<SalesOrderRowUnitPriceWithoutTaxOrDiscount>' . $product->get_regular_price() . '</SalesOrderRowUnitPriceWithoutTaxOrDiscount>';
			$productstring .= '</mvSalesOrderRow>';
			
			$products_xml .= $productstring;
		}
		
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
		
		
		$xml_request = '
			<SalesOrderUpdate xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">
			  <APIKEY>' . $API_KEY . '</APIKEY>
			  <mvSalesOrder>
				<SalesOrderReferenceNo>' . $order->get_order_number() . '</SalesOrderReferenceNo>
				<SalesOrderReferenceApplication>' . 'woocommerce' . '</SalesOrderReferenceApplication>
				<SalesOrderCurrencyCode>EUR</SalesOrderCurrencyCode>
				<SalesOrderClientID>' . $client->MV_ID . '</SalesOrderClientID>
				<SalesOrderBillingAddress>' . $shipping_address . '</SalesOrderBillingAddress>
				<SalesOrderShippingAddress>' . $billing_address . '</SalesOrderShippingAddress>
				<SalesOrderComments>' . $order->get_customer_note() . '</SalesOrderComments>
				<SalesOrderTags>WooCommerce</SalesOrderTags>
				<SalesOrderDetails>
					' . $products_xml . '
				</SalesOrderDetails>
				<SalesOrderStatus>Pending</SalesOrderStatus>
			  </mvSalesOrder>
			  <mvRecordAction>Insert</mvRecordAction>
			</SalesOrderUpdate>
			';
		
		echo "<br>";
		var_dump(htmlentities($xml_request));
		
		$data = send_xml($url, $xml_request);
		
		var_dump($data);
		echo "<br><br>";
	}
	
?>
