<?php

require_once("product.php");
require_once("address.php");

// This class makes it easier to get values from
// and send values to the megaventory API
class Megaventory_sync {
	public $url = "https://apitest.megaventory.com/json/reply/";
	public $xml_url = "https://apitest.megaventory.com/xml/reply/";
	public $API_KEY = "827bc7518941837b@m65192"; // DEV AND DEBUG ONLY
	
	public $product_get_call = "ProductGet";
	public $product_update_call = "ProductUpdate";
	public $category_get_call = "ProductCategoryGet";
	public $category_update_call = "ProductCategoryUpdate";
	public $category_delete_call = "ProductCategoryDelete";
	public $product_stock_call = "InventoryLocationStockGet";
	public $supplierclient_get_call = "SupplierClientGet";
	public $supplierclient_update_call = "SupplierClientUpdate";
	public $supplierclient_undelete_call = "SupplierClientUndelete";
	public $salesorder_update_call = "SalesOrderUpdate";
	
	public $username_prefix = "wc_";
	
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
	
	// this function retrieves all existing categories as strings (names of categories)
	// it returns a dictionary {ID: name}
	function get_categories() {
		$jsonurl = $this->create_json_url($this->category_get_call);
		$jsoncat = file_get_contents($jsonurl);
		$jsoncat = json_decode($jsoncat, true);
		
		$categories = array();
		foreach ($jsoncat['mvProductCategories'] as $cat) {
			$categories[$cat['ProductCategoryID']] = $cat['ProductCategoryName'];
		}
		
		return $categories;
	}
		
	// this function retrieves all existing products
	// products attributes are also updated, including the availability of stock and category
	// it stores them in an array
	function get_products() {
		$categories = $this->get_categories();
		
		// get products as json
		$jsonurl = $this->create_json_url($this->product_get_call);
		$jsonprod = file_get_contents($jsonurl);
		$jsonprod = json_decode($jsonprod, true);
		
		// interpret json into Product class
		$products = array();
		foreach ($jsonprod['mvProducts'] as $prod) {
			$product = new Product();
			
			$product->MV_ID = $prod['ProductID'];
			$product->type = $prod['ProductType'];
			$product->SKU = $prod['ProductSKU'];
			$product->EAN = $prod['ProductEAN'];
			$product->description = $prod['ProductDescription'];
			$product->long_description = $prod['ProductLongDescription'];
			$product->image_url = $prod['ProductImageURL'];
			
			$product->regular_price = $prod['ProductSellingPrice'];
			$product->category = $categories[$prod['ProductCategoryID']];
			
			$product->weight = $prod['ProductWeight'];
			$product->length = $prod['ProductLength'];
			$product->breadth = $prod['ProductBreadth'];
			$product->height = $prod['ProductHeight'];
			
			$product->stock_on_hand = $this->get_product_stock_on_hand($prod['ProductID']);
			
			array_push($products, $product);
		}
		
		return $products;
	}
	
	// get inventory per product ID
	function get_product_stock_on_hand($product_id) {
		$json_url = $this->create_json_url_filter($this->product_stock_call, "productid", "Equals", $product_id);
		
		$response = file_get_contents($json_url);
		$response = json_decode($response, true);
		
		// summarise product on hand in all inventories
		$response = $response['mvProductStockList'];
		$total_on_hand = 0;
		
		if ($response[0]['mvStock'] != null) {
			foreach ($response[0]['mvStock'] as $inventory) {
				$total_on_hand += $inventory['StockOnHand'];
			}
		} else {
			return 0;
		}
		
		return $total_on_hand;
	}
	
	function synchronize_categories($wc_categories, $with_delete = false) {
		//delete not handled - product not in eshop can still physically exist as stock
		$mv_categories = $this->get_categories();
		
		$categories_to_delete = $mv_categories;
		$categories_to_create = $wc_categories;
		
		// leave categories that exist both in WC and MV
		// Add categories that exist only in MV
		// Delete categories that exist only in WC (if requested)
		foreach ($wc_categories as $wc_category) {
			foreach ($mv_categories as $mv_category) {
				if ($mv_category == $wc_category) {
					$key = array_search($mv_category, $categories_to_delete);
					unset($categories_to_delete[$key]);
					
					$key = array_search($wc_category, $categories_to_create);
					unset($categories_to_create[$key]);
				} 
			}
		}	
		
		$create_url = $this->create_json_url($this->category_update_call);
		$delete_url = $this->create_json_url($this->category_delete_call);
		
		foreach ($categories_to_create as $name) {
			//create category
			$url = $create_url . "&mvProductCategory={ProductCategoryName:" . $name . "}";
			$response = file_get_contents($url);
		}
		
		if ($with_delete) {
			foreach ($categories_to_delete as $key => $name) {
				$url = $delete_url . "&ProductCategoryIDToDelete=" . $key . "&mvCategoryDeleteAction=LeaveProductsOrphan";
				$response = file_get_contents($url);
			}
		}
	}
	
	function synchronize_products($wc_products, $with_delete = false) {
		$mv_products = $this->get_products();
		$mv_categories = $this->get_categories();
	
		$skus = array();
		//get SKUs of existing products
		foreach ($mv_products as $mv_product) {
			$sku = $mv_product->SKU;
			array_push($skus, $sku);
		}
		
		//update MV IDs in WC product array
		//it is much quicker to do this now
		foreach ($wc_products as $wc_product) {
			foreach ($mv_products as $mv_product) {
				if ($wc_product->SKU == $mv_product->SKU) {
					$wc_product->MV_ID = $mv_product->MV_ID;
				}
			}
		}
		
		//update if product exists,
		//create if it does not exist
		foreach ($wc_products as $product) {
			if (in_array($product->SKU, $skus)) {				
				$this->update_simple_product($product, false, $mv_categories);
			} else {
				$this->update_simple_product($product, true, $mv_categories);
			}
		}
		
		var_dump($wc_products);
	}
	
	function get_product_by_sku($SKU) {
		$prod = null;
		$prods = $this->get_products();
		foreach($prods as $p) {
			if ($p->SKU == $SKU) {
				$prod = $p;
				break;
			}
		}
		return $prod;
	}
	
	//update of create simple product
	function update_simple_product($product, $create_new, $categories = null) {
		if ($categories == null) {
			$categories = $this->get_categories();
		}
		$action = ($create_new ? "Insert" : "Update");
		$category_id = array_search($product->category, $categories);
		
		//this needs to be split into few small requests, as urls get too long otherwise
		//$create_url = $this->create_json_url($this->product_update_call);
		$url = $this->create_xml_url($this->product_update_call);
		
		$xml_request = '
			<ProductUpdate xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">
				<APIKEY>' . $this->API_KEY . '</APIKEY>
				<mvProduct>
					' . ($create_new ? '<ProductType>BuyFromSupplier</ProductType>' : '') . '
					' . ($create_new ? '' : '<ProductID>' . $product->MV_ID . '</ProductID>') . '
					<ProductSKU>' . $product->SKU . '</ProductSKU> 
					<ProductDescription>' . $product->description . '</ProductDescription> 
					' . ($product->long_description ? '<ProductLongDescription>' . $product->long_description . '</ProductLongDescription>' : '') . '
					' . ($category_id ? '<ProductCategoryID>' . $category_id . '</ProductCategoryID>' : '') . '
					' . ($product->regular_price ? '<ProductSellingPrice>' . $product->regular_price . '</ProductSellingPrice>' : '') . '
					' . ($product->weight ? '<ProductWeight>' . $product->weight . '</ProductWeight>' : '') . '
					' . ($product->length ? '<ProductLength>' . $product->length . '</ProductLength>' : '') . '
					' . ($product->breadth ? '<ProductBreadth>' . $product->breadth . '</ProductBreadth>' : '') . '
					' . ($product->height ? '<ProductHeight>' . $product->height . '</ProductHeight>' : '') . '
					' . ($product->image_url ? '<ProductImageURL>' . $product->image_url . '</ProductImageURL>' : '') . '
				</mvProduct>
				<mvRecordAction>' . $action . '</mvRecordAction>
				<mvInsertUpdateDeleteSourceApplication>String</mvInsertUpdateDeleteSourceApplication>
			</ProductUpdate>
			';
		
		echo "<br>";
		var_dump(htmlentities($xml_request));
			
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->create_xml_url($this->product_update_call));
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, ($xml_request));
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		
		curl_close($ch);
	}
	
	function get_clients() {
		// get suppliers/clients as json
		$jsonurl = $this->create_json_url($this->supplierclient_get_call);
		$jsonprod = file_get_contents($jsonurl);
		$supplierclients = json_decode($jsonprod, true)['mvSupplierClients'];
		
		//var_dump($supplierclients);
		
		$clients = array();
		foreach ($supplierclients as $supplierclient) {
			if ($supplierclient['SupplierClientType'] == "Client" or $supplierclient['SupplierClientType'] == "Both") {
				$client = new Client();
				
				array_push($clients, $this->mv_client_to_client($supplierclient));
			}
		}
		
		return $clients;
	}
	
	function mv_client_to_client($supplierclient) {
		$client = new Client();
				
		$client->MV_ID = $supplierclient['SupplierClientID'];
		$client->username = str_replace("wc_", "", $supplierclient['SupplierClientName']);
		$client->contact_name = $supplierclient['SupplierClientComments'];
		$client->shipping_address = $supplierclient['SupplierClientShippingAddress1'];
		$client->shipping_address2 = $supplierclient['SupplierClientShippingAddress2'];
		$client->billing_address = $supplierclient['SupplierClientBillingAddress2'];
		$client->tax_ID = $supplierclient['SupplierClientTaxID'];
		$client->phone = $supplierclient['SupplierClientPhone1'];
		$client->email = $supplierclient['SupplierClientEmail'];
		$client->type = $supplierclient['SupplierClientType'];
		
		return $client;
	}
		
	
	//synchronize clients with those of WooCommerce
	function synchronize_clients($wc_clients, $with_delete = false) {
		
		$clients_to_create = array();
		$clients_to_update = array();
		echo "<br> ALLCLIENTS: ";
		var_dump($wc_clients);
		echo "<br>------------------------------------------------<br>";
		
		foreach ($wc_clients as $client) {
			if ($client->MV_ID == null) {
				array_push($clients_to_create, $client);
			} else {
				array_push($clients_to_update, $client);
			}
		}
		
		echo "<br> TO CREATE: ";
		var_dump($clients_to_create);
		echo "<br> TO UPDATE: ";
		var_dump($clients_to_update);
		
		foreach ($clients_to_create as $client) {
			$response = $this->createUpdateClient($client, true);
			
			echo "<br>CREATE RESPONSE: ";
			var_dump($response);
			
			$undeleted = false;
			if ($response['InternalErrorCode'] == "SupplierClientAlreadyDeleted") {
				// client must be undeleted first and then updated
				$this->undeleteClient($response["entityID"]);
				
				// if undelete, this name will exist. next if statement has to decide what to do next
				$response['InternalErrorCode'] = "SupplierClientNameAlreadyExists";
				$undeleted = true;
			} 
			
			if ($response['InternalErrorCode'] == "SupplierClientNameAlreadyExists") {
				$mv_client = $this->get_client_by_name($client->contact_name);
				//$client->MV_ID = $mv_client->MV_ID;
				echo "<br> COMPARING " . $client->email . " : " . $mv_client->email;
				if ($client->email == $mv_client->email) { //same person
					$client->MV_ID = $mv_client->MV_ID;
					$client->contact_name = $mv_client->contact_name;
					$response = $this->createUpdateClient($client, false);
				} else { //different person
					$client->contact_name = $client->contact_name . " - " . $client->email;
					$response = $this->createUpdateClient($client, true);
					
					if ($undeleted) {
						//in the end, it seems that this client was undeleted for no reason
						//however, he needs to be undeleted in order to gain information
						//$this->delete_client($mv_client);
					}
				}
			}
			
			
			
			echo "<br>CREATE RESPONSE2: ";
			var_dump($response);
			
			$id = get_user_meta($client->WC_ID, "MV_ID", true);
			update_user_meta($client->WC_ID, "MV_ID", $response["mvSupplierClient"]["SupplierClientID"]);
			
			
			$id = $response["mvSupplierClient"]["SupplierClientID"];
			if ($id != null) {
				update_user_meta($client->WC_ID, "MV_ID", $id);
			}
				
			
		}
		
		foreach ($clients_to_update as $client) {
			$response = $this->createUpdateClient($client, false);
			
			if ($response['InternalErrorCode'] == "SupplierClientAlreadyDeleted") {
				// client must be undeleted first and then updated
				$this->undeleteClient($response["entityID"]);
				$response = $this->createUpdateClient($client, false);
			}
			
			// do not override, use ' - email'
			if ($response['InternalErrorCode'] == "SupplierClientNameAlreadyExists") {
				$client->contact_name = $client->contact_name . ' - ' . $client->email;
				$response = $this->createUpdateClient($client, false);
			}
			
			echo "<br>RESPONSE: ";
			var_dump($response);
			
			//just to be sure
			$id = $response["mvSupplierClient"]["SupplierClientID"];
			if ($id != null) {
				update_user_meta($client->WC_ID, "MV_ID", $id);
			}
		}
		
		
	}
	
	function get_client_by_name($name) {
		$url = $this->create_json_url_filter($this->supplierclient_get_call, "SupplierClientName", "Equals", urlencode($name));
		$client = json_decode(file_get_contents($url), true)['mvSupplierClients'][0];
		return $this->mv_client_to_client($client);
	}
	
	function undeleteClient($id) {
		$url = $this->create_json_url($this->supplierclient_undelete_call);
		$url .= "&SupplierClientIDToUndelete=" . $id;
		file_get_contents($url);
	}
		
	function createUpdateClient($client, $create_new = false) {
		$url = $this->create_xml_url($this->supplierclient_update_call);
		$action = ($create_new ? "Insert" : "Update");
		
		$xml_request = '
			<SupplierClientUpdate xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">
				<APIKEY>' . $this->API_KEY . '</APIKEY>
				<mvSupplierClient>
					' . ($create_new ? '' : '<SupplierClientID>' . $client->MV_ID . '</SupplierClientID>') . '
					' . ($client->type ? '<SupplierClientType>' . $client->type . '</SupplierClientType>' : '') . '
					<SupplierClientName>' . $client->contact_name . '</SupplierClientName>
					' . ($client->billing_address ? '<SupplierClientBillingAddress>' . $client->billing_address . '</SupplierClientBillingAddress>' : '') . '
					' . ($client->shipping_address ? '<SupplierClientShippingAddress1>' . $client->shipping_address . '</SupplierClientShippingAddress1>' : '') . '
					' . ($client->phone ? '<SupplierClientPhone1>' . $client->phone . '</SupplierClientPhone1>' : '') . '
					' . ($client->email ? '<SupplierClientEmail>' . $client->email . '</SupplierClientEmail>' : '') . '
					' . ($client->contact_name ? '<SupplierClientComments>' . 'WooCommerce client' . '</SupplierClientComments>' : '') . '
				</mvSupplierClient>
				<mvRecordAction>' . $action . '</mvRecordAction>
			</SupplierClientUpdate>
			';
			
		echo "<br><br>";
		var_dump(htmlentities($xml_request));
			
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, ($xml_request));
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		
		echo curl_getinfo($ch);
		echo curl_errno($ch);
		echo curl_error($ch);		
		curl_close($ch);
		
		//echo "<br> RAW RESPONSE: ";
		//var_dump($data);
		
		$data = simplexml_load_string(html_entity_decode($data), "SimpleXMLElement", LIBXML_NOCDATA);
		$data = json_encode($data);
		$data = json_decode($data, TRUE);
		
		return $data;
	}
	
	function deleteClient($client) {
		//todo
	}
	
	function get_client_by_username($username) {
		$clients = $this->get_clients();
		foreach ($clients as $client) {
			//echo "<br> now rolling:";
			//var_dump($client);
			//echo " end <br>";
			
			if ($client->username === $username) {
				return $client;
			}
		}
		return null;
	}
	
	function get_guest_client() {
		return $this->get_client_by_name("WooCommerce");
	}
	
	//woocommerce purchase is megaventory sale
	//$order is of type WC_ORDER - find documentation online
	function place_sales_order($order, $client) { 
		$url = $this->create_xml_url($this->salesorder_update_call);
		
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
			  <APIKEY>' . $this->API_KEY . '</APIKEY>
			  <mvSalesOrder>
				<SalesOrderReferenceNo>' . $order->get_order_number() . '</SalesOrderReferenceNo>
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
			
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, ($xml_request));
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		
		curl_close($ch);
		
		print_r (htmlentities($data));
		echo "<br><br>";
		
	}
}

?>
