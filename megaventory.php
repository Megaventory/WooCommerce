<?php

require_once("product.php");

class Megaventory_sync {
	public $url = "https://api.megaventory.com/v2017a/json/reply/";
	
	public $API_KEY = "827bc7518941837b@m65192"; // DEV AND DEBUG ONLY
	
	public $product_get_call = "ProductGet";
	public $category_get_call = "ProductCategoryGet";
	public $product_stock_call = "InventoryLocationStockGet";
	
	function create_json_url($call) {
		return $this->url . $call . "?APIKEY=" . $this->API_KEY;
	}
	
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
		
	function get_products() {
		$categories = $this->get_categories();
		
		$jsonurl = $this->create_json_url($this->product_get_call);
		$jsonprod = file_get_contents($jsonurl);
		$jsonprod = json_decode($jsonprod, true);
		
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
	
	function get_product_stock_on_hand($product_id) {
		$json_url = $this->create_json_url($this->product_stock_call) . "&Filters={FieldName:productid,SearchOperator:Equals,SearchValue:" . $product_id ."}";
		
		$response = file_get_contents($json_url);
		$response = json_decode($response, true);
		
		$response = $response['mvProductStockList'];
		$total_on_hand = 0;
		foreach ($response[0]['mvStock'] as $inventory) {
			$total_on_hand += $inventory['StockOnHand'];
		}
		
		return $total_on_hand;
	}
}

?>
