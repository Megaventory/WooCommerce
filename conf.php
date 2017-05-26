<?php


	$url = "https://apitest.megaventory.com/json/reply/";
	$xml_url = "https://apitest.megaventory.com/xml/reply/";
	$API_KEY = "b7d0cc59b72af1e5@m65192"; // DEV AND DEBUG ONLY
	
	$product_get_call = "ProductGet";
	$product_update_call = "ProductUpdate";
	$category_get_call = "ProductCategoryGet";
	$category_update_call = "ProductCategoryUpdate";
	$category_delete_call = "ProductCategoryDelete";
	$product_stock_call = "InventoryLocationStockGet";
	$supplierclient_get_call = "SupplierClientGet";
	$supplierclient_update_call = "SupplierClientUpdate";
	$supplierclient_undelete_call = "SupplierClientUndelete";
	$salesorder_update_call = "SalesOrderUpdate";
	$integration_get_call = "IntegrationUpdateGet";
	$integration_delete_call = "IntegrationUpdateDelete";
	
	// create URL using the API key and call
	function create_json_url($call) {
		return $this->url . $call . "?APIKEY=" . $this->API_KEY;
	}
	
	function create_xml_url($call) {
		return $this->xml_url . $call . "?APIKEY=" . $this->API_KEY;
	}
	
	function create_json_url_filter($call, $fieldName, $searchOperator, $searchValue) {
		return $this->create_json_url($call) . "&Filters={FieldName:" . $fieldName . ",SearchOperator:" . $searchOperator . ",SearchValue:" . $searchValue ."}";
	}
?>
