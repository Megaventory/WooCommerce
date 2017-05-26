<?php


	$url = "https://apitest.megaventory.com/json/reply/";
	$xml_url = "https://apitest.megaventory.com/xml/reply/";
	$API_KEY = "b7d0cc59b72af1e5@m65192"; // DEV AND DEBUG ONLY
	
	
	$supplierclient_get_call = "SupplierClientGet";
	$supplierclient_update_call = "SupplierClientUpdate";
	$supplierclient_undelete_call = "SupplierClientUndelete";
	$salesorder_update_call = "SalesOrderUpdate";
	$integration_get_call = "IntegrationUpdateGet";
	$integration_delete_call = "IntegrationUpdateDelete";
	
	// create URL using the API key and call
	function create_json_url($call) {
		global $url, $API_KEY;
		return $url . $call . "?APIKEY=" . $API_KEY;
	}
	
	function create_xml_url($call) {
		global $url, $API_KEY;
		return $xml_url . $call . "?APIKEY=" . $API_KEY;
	}
	
	function create_json_url_filter($call, $fieldName, $searchOperator, $searchValue) {
		return create_json_url($call) . "&Filters={FieldName:" . $fieldName . ",SearchOperator:" . $searchOperator . ",SearchValue:" . $searchValue ."}";
	}
?>
