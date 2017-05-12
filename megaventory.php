<?php

require_once("product.php");

class Megaventory {
	public $url = "https://api.megaventory.com/v2017a/json/reply/";
	
	public $API_KEY = "827bc7518941837b@m65192"; // DEV AND DEBUG ONLY
	
	public $product_get_call = "ProductGet";
	public $category_get_call = "ProductCategoryGet";
	
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
			
			$product->ID = $prod['ProductID'];
			$product->type = $prod['ProductType'];
			$product->SKU = $prod['ProductSKU'];
			$product->EAN = $prod['ProductEAN'];
			$product->description = $prod['ProductDescription'];
			$product->regular_price = $prod['ProductSellingPrice'];
			$product->category = $categories[$prod['ProductCategoryID']];
			
			$product->weight = $prod['ProductWeight'];
			$product->length = $prod['ProductLength'];
			$product->breadth = $prod['ProductBreadth'];
			$product->height = $prod['ProductHeight'];
			
			//$product->ID = $prod['ProductLongDescription'];
			//$product->ID = $prod['ProductCategoryID'];
			
			
			array_push($products, $product);
		}
		
		return $products;
	}
}

?>
