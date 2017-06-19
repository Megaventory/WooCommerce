<?php

	require_once("address.php");
	
	function get_api_key() {
		return get_option("mv_api_key");
	}
	
	function get_api_host() {
		global $default_host;
		$host = get_option("mv_api_host");
		if (!$host) {
			$host = $default_host;
		}
		return $host;
	}
	
	function get_guest_mv_client() {
		//$post = get_page_by_title("guest_id", ARRAY_A, "post");
		//$id = $post['post_content'];
		//$client = Client::mv_find($id);
		//return $client;
		echo "FINDING CLIENT";
		var_dump((int)get_option("woocommerce_guest"));
		$client = Client::wc_find((int)get_option("woocommerce_guest"));
		return $client; //$use $client->MV_ID
	}
	
	$default_host = "https://apitest.megaventory.com/";
	$host = get_api_host();
	$url = $host."json/reply/";
	$xml_url = $host."xml/reply/";
	$API_KEY = get_api_key();
	//$API_KEY = "b7d0cc59b72af1e5@m65192"; // DEV AND DEBUG ONLY
	
	$salesorder_update_call = "SalesOrderUpdate";
	$integration_get_call = "IntegrationUpdateGet";
	$integration_delete_call = "IntegrationUpdateDelete";
	$currency_get_call = "CurrencyGet";
	
	//mv status => wc status
	$translate_order_status = array
	(
		'Pending' => 'on-hold',
		'Verified' => 'processing',
		'PartiallyShipped' => 'processing',
		'FullyShipped' => 'processing',
		'Closed' => 'completed',
		//Received is only for purchase orders
		'FullyInvoiced' => 'completed',
		'Cancelled' => 'cancelled'
	
	);
	
	//mv status code to string. only a few of them are actually used
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
		
	function create_json_url_filters($call, $args) {
		$url = create_json_url($call) . "&Filters=[";
		for ($i = 0; $i < count($args); $i++) {
			$arg = $args[$i];
			$url .= "{FieldName:" . $arg[0] . ",SearchOperator:" . $arg[1] . ",SearchValue:" . $arg[2] ."}";
			if ($i + 1 < count($args)) { //not last element
				$url .= ",";
			}
		}
		$url .= "]";
		
		return $url;
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
		
		echo "<br> normal data: " . htmlentities($data);
		$data = str_replace("d2p1:", "", $data); // required bc ASP.NET creates those d2p1 tags gods knows why
		
		$data = simplexml_load_string(html_entity_decode($data));//, "SimpleXMLElement", LIBXML_NOCDATA);
		
		//var_dump($data);
		$data = json_encode($data, JSON_PRETTY_PRINT, 1000);
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
		
		
		
		//tax - currently using just one
		$taxes = array();
		foreach($order->get_taxes() as $key => $tax) {
			array_push($taxes, Tax::wc_find($tax->get_rate_id()));
		}
		
		$tax = null;
		if (count($taxes) == 1) {
			$tax = $taxes[0];
		} else if (count($taxes) > 1) {
			//calculate total tax rate
			$total_no_tax = $order->get_total() - $order->get_total_tax(); //difference tax and no tax
			
			
			$rate = (float)$order->get_total_tax() / (float)$total_no_tax;
			$rate *= 100.0; //to percent
			$rate = round($rate, 2);
			
			$names = array();
			for ($i = 0; $i < count($taxes); $i++) {
				array_push($names, $taxes[$i]->name);
			}
			sort($names);
			$name = implode("_", $names);
			$name .= "__" . (string)$rate;
			
			$tax = Tax::mv_find_by_name($name);
			if ($tax == null) {
				$tax = new Tax();
				$tax->name = $name;
				$tax->description = "woocommerce generated tax";
				$tax->rate = $rate;
				$tax->mv_save();
			}
			
			wp_mail("mpanasiuk@megaventory.com", "total tax", "rate: " . (string)$rate . " get_total: " . $order->get_total() . " get_total_tax: " . $order->get_total_tax());
			wp_mail("mpanasiuk@megaventory.com", "total tax_name ", (string)$name);
		}
		wp_mail("mpanasiuk@megaventory.com", "orderplaced tax", var_export($order->data['total_tax'], true));
		
		$products_xml = '';
		foreach ($order->get_items() as $item) {
			$product = new WC_Product($item['product_id']);
			$productstring = '<mvSalesOrderRow>';
			$productstring .= '<SalesOrderRowProductSKU>' . $product->get_sku() . '</SalesOrderRowProductSKU>';
			$productstring .= '<SalesOrderRowQuantity>' . $item['quantity'] . '</SalesOrderRowQuantity>';
			$productstring .= '<SalesOrderRowShippedQuantity>0</SalesOrderRowShippedQuantity>';
			$productstring .= '<SalesOrderRowInvoicedQuantity>0</SalesOrderRowInvoicedQuantity>';
			$productstring .= '<SalesOrderRowUnitPriceWithoutTaxOrDiscount>' . $product->get_regular_price() . '</SalesOrderRowUnitPriceWithoutTaxOrDiscount>';
			$productstring .= ($tax ? '<SalesOrderRowTaxID>'.(string)$tax->MV_ID.'</SalesOrderRowTaxID>' : '');
			$productstring .= '<SalesOrderRowTotalAmount>123456</SalesOrderRowTotalAmount>';
			$productstring .= '</mvSalesOrderRow>';
			
			$products_xml .= $productstring;
			
			//wp_mail("mpanasiuk@megaventory.com", "item", var_export($item, true));
			//wp_mail("mpanasiuk@megaventory.com", "item2", $product->get_sku() . "  :  " . (string)((float)$product->get_regular_price() / (float)$item->get_data()['total_tax']));
			
			
			
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
		
		echo "<br> interpreted data<br>";
		var_dump($data);
		echo "<br><br>";
		echo "aaaaaa:<br>";
		var_dump($data->ResponseStatus);
		
		return $data;
	}
	
	//if someone dealt with php and xml and still claims that php is not an utter piece of junk
	//he should be prevented from ever programming again
	function xml2js($xmlnode) {
		$root = (func_num_args() > 1 ? false : true);
		$jsnode = array();

		if (!$root) {
			if (count($xmlnode->attributes()) > 0){
				$jsnode["$"] = array();
				foreach($xmlnode->attributes() as $key => $value)
					$jsnode["$"][$key] = (string)$value;
			}

			$textcontent = trim((string)$xmlnode);
			if (count($textcontent) > 0)
				$jsnode["_"] = $textcontent;

			foreach ($xmlnode->children() as $childxmlnode) {
				$childname = $childxmlnode->getName();
				if (!array_key_exists($childname, $jsnode))
					$jsnode[$childname] = array();
				array_push($jsnode[$childname], xml2js($childxmlnode, true));
			}
			return $jsnode;
		} else {
			$nodename = $xmlnode->getName();
			$jsnode[$nodename] = array();
			array_push($jsnode[$nodename], xml2js($xmlnode, true));
			return json_encode($jsnode);
		}
	}  
	
	function get_default_currency() {
		global $currency_get_call;
		$url = create_json_url_filter($currency_get_call, "CurrencyIsDefault", "Equals", "true");
		
		$data = file_get_contents($url);
		return json_decode($data, true)['mvCurrencies'][0]['CurrencyCode'];
	}
	
	function check_connectivity() {
		global $host;
		$host2 = $host;
		$host2 = str_replace("https://", "", $host2);
		$host2 = str_replace("http://", "", $host2);
		$host2 = explode("/", $host2)[0];
		
		if($socket =@ fsockopen($host2, 80, $errno, $errstr, 30)) {
			fclose($socket);
			return true;
		} else {
			return false;
		}
	}
	
	function check_key() {
		global $API_KEY;
		$data = pull_product_changes();
		
		if (!$API_KEY or $data == null or (int)$data['ResponseStatus']['ErrorCode'] == 401) {
			return false;
		} else {
			return true;
		}
	}
	
?>
