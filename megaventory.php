<?php

require_once("product.php");

// This class makes it easier to get values from
// and send values to the megaventory API
class Megaventory_sync {
	public $url = "https://api.megaventory.com/v2017a/json/reply/";
	public $API_KEY = "827bc7518941837b@m65192"; // DEV AND DEBUG ONLY
	
	public $product_get_call = "ProductGet";
	public $category_get_call = "ProductCategoryGet";
	public $category_update_call = "ProductCategoryUpdate";
	public $category_delete_call = "ProductCategoryDelete";
	public $product_stock_call = "InventoryLocationStockGet";
	
	// create URL using the API key and call
	function create_json_url($call) {
		return $this->url . $call . "?APIKEY=" . $this->API_KEY;
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
		foreach ($response[0]['mvStock'] as $inventory) {
			$total_on_hand += $inventory['StockOnHand'];
		}
		
		return $total_on_hand;
	}
	
	function synchronize_categories($wc_categories, $with_delete = false) {
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
				//TO DO
				//$url = $delete_url . "&
			}
		}
	}
	
	function synchronize_products($wc_products, $with_delete = false) {
		$mv_products = $this->get_products();
	
		$skus = array();
		//get SKUs of existing products
		foreach ($mv_products as $mv_product) {
			$sku = $mv_product->SKU;
			array_push($skus, $sku);
		}
		
		
	}
}

?>
