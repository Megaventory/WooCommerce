<?php

require_once("product.php");

// This class makes it easier to get values from
// and send values to the megaventory API
class Megaventory_sync {
	public $url = "https://api.megaventory.com/v2017a/json/reply/";
	public $xml_url = "https://api.megaventory.com/v2017a/xml/reply/";
	public $API_KEY = "827bc7518941837b@m65192"; // DEV AND DEBUG ONLY
	
	public $product_get_call = "ProductGet";
	public $product_update_call = "ProductUpdate";
	public $category_get_call = "ProductCategoryGet";
	public $category_update_call = "ProductCategoryUpdate";
	public $category_delete_call = "ProductCategoryDelete";
	public $product_stock_call = "InventoryLocationStockGet";
	public $supplierclient_get_call = "SupplierClientGet";
	public $supplierclient_update_call = "SupplierClientUpdate";
	
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
		
		var_dump($supplierclients);
		
		$clients = array();
		foreach ($supplierclients as $supplierclient) {
			if ($supplierclient['SupplierClientType'] == "Client" or $supplierclient['SupplierClientType'] == "Both") {
				$client = new Client();
				
				$client->MV_ID = $supplierclient['SupplierClientID'];
				$client->contact_name = $supplierclient['SupplierClientName'];
				$client->shipping_address = $supplierclient['SupplierClientShippingAddress1'];
				$client->shipping_address2 = $supplierclient['SupplierClientShippingAddress2'];
				$client->billing_address = $supplierclient['SupplierClientBillingAddress2'];
				$client->tax_ID = $supplierclient['SupplierClientTaxID'];
				$client->phone = $supplierclient['SupplierClientPhone1'];
				$client->email = $supplierclient['SupplierClientEmail'];
				$client->type = $supplierclient['SupplierClientType'];
				
				array_push($clients, $client);
			}
		}
		
		return $clients;
	}
	
	function synchronize_clients($wc_clients, $with_delete = false) {
		$mv_clients = $this->get_clients();
		
		$clients_to_create = $wc_clients;
		$clients_to_delete = $mv_clients;
		$clients_to_update = array();
		
		echo "COUNT: " . count($mv_clients) . "<br>";
		
		foreach ($wc_clients as $wc_client) {
			foreach ($mv_clients as $mv_client) {
				echo "COMPARING: " . $wc_client->email . " : " . $mv_client->email . "<br>";
				if (strtolower($wc_client->email) === strtolower($mv_client->email)) {
					$wc_client->MV_ID = $mv_client->MV_ID;
					$wc_client->type = $mv_client->type; // override type. mv type is more importan, wc type is always "CLIENT"
					array_push($clients_to_update, $wc_client);
					
					$key = array_search($wc_client, $clients_to_create);
					unset($clients_to_create[$key]);
					
					$key = array_search($mv_client, $clients_to_delete);
					unset($clients_to_delete[$key]);
				}
			}
		} 
		
		echo "<br><br> TO CREATE: ";
		var_dump($clients_to_create);
		echo "<br><br> TO DELETE: ";
		var_dump($clients_to_delete);
		echo "<br><br> TO UPDATE: ";
		var_dump($clients_to_update);
		
		foreach ($clients_to_create as $client) {
			$this->createUpdateClient($client, true);
		}
		
		foreach ($clients_to_update as $client) {
			$this->createUpdateClient($client, false);
		}
		
		if ($with_delete) {
			foreach ($clients_to_delete as $client) {
				$this->deleteClient($client);
			}
		}
		
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
					<SupplierClientName>' . $client->email . '</SupplierClientName>
					' . ($client->billing_address ? '<SupplierClientBillingAddress>' . $client->billing_address . '</SupplierClientBillingAddress>' : '') . '
					' . ($client->shipping_address ? '<SupplierClientShippingAddress1>' . $client->shipping_address . '</SupplierClientShippingAddress1>' : '') . '
					' . ($client->phone ? '<SupplierClientPhone1>' . $client->phone . '</SupplierClientPhone1>' : '') . '
					' . ($client->email ? '<SupplierClientEmail>' . $client->email . '</SupplierClientEmail>' : '') . '
					' . ($client->contact_name ? '<SupplierClientComments>' . $client->contact_name . '</SupplierClientComments>' : '') . '
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
		print_r($data);
		
		curl_close($ch);
		
	}
	
	function deleteClient($client) {
		//todo
	}
}

?>
