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
		$client = Client::wc_find((int)get_option("woocommerce_guest"));
		return $client; //$use $client->MV_ID
	}
	
	$default_host = "https://api.megaventory.com/v2017a/";
	$host = get_api_host();
	$url = $host."json/reply/";
	$xml_url = $host."xml/reply/";
	$API_KEY = get_api_key();
	
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
		return $url . $call . "?APIKEY=" . urlencode($API_KEY);
	}
	
	function create_xml_url($call) {
		global $xml_url, $API_KEY;
		return $xml_url . $call . "?APIKEY=" . $API_KEY;
	}
	
	function create_json_url_filter($call, $fieldName, $searchOperator, $searchValue) {
		return create_json_url($call) . "&Filters={FieldName:" . urlencode($fieldName) . ",SearchOperator:" . urlencode($searchOperator) . ",SearchValue:" . urlencode($searchValue) ."}";
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
		$url = create_json_url("ApiKeyGet");
		$data = json_decode(file_get_contents($url), true);
		
		$code = (int)$data['ResponseStatus']['ErrorCode'];
		if ($code == 401 || $code == 500) { //401-wrong key | 500-no key
			return false;
		} else {
			return true;
		}
	}
	 
	function remove_integration_update($id) {
		global $integration_delete_call;
		$url = create_json_url($integration_delete_call) . "&IntegrationUpdateIDToDelete=" . urlencode($id);
		$data = json_decode(file_get_contents($url), true);
		
	}
	
	function pull_product_changes() {
		global $integration_get_call;
		$url = create_json_url_filter($integration_get_call, "Application", "Equals", "woocommerce");
		$data = json_decode(file_get_contents($url), true);
		
		return $data;
	}
	
	function apply_coupon($product, $coupon) {
		if (!$coupon->type or $coupon->type == "fixed_cart")
			return false;
		
		if (!$coupon->applies_to_sales() and $product->sale_active)
			return false;
		
		$incl_ids = $coupon->get_included_products(true);
		$included_empty = count($incl_ids) <= 0;
		$included = in_array($product->WC_ID, $incl_ids);
		$excluded = in_array($product->WC_ID, $coupon->get_excluded_products(true));
		
		//$apply = (!$excluded and ($included_empty or $included));	
		
		$categories = $product->wc_get_prod_categories($by='id');
		$incl_ids_cat = $coupon->get_included_products_categories(true);
		$included_empty_cat = count($incl_ids_cat) <= 0;
		$included_cat = in_array($product->WC_ID, $incl_ids_cat);
		$excluded_cat = in_array($product->WC_ID, $coupon->get_excluded_products_categories(true));
		
		//$apply_cat = (!$excluded_cat and ($included_empty_cat or $included_cat));	
		
		return (($included_empty or $included) or (($included_empty_cat and $included_empty) or $included_cat)) and (!$excluded and !$excluded_cat);
	}

	
?>
