<?php

class Product {
	public $ID;
	public $name;
	public $SKU;
	public $EAN;
	public $description;
	public $long_description;
	public $quantity;
	public $category;
	public $type;
	
	public $regular_price;
	
	public $length;
	public $breadth;
	public $height;
	
	public $version;

}


class Megaventory {
	public $url = "https://api.megaventory.com/v2017a/json/reply/";
	
	public $API_KEY = "827bc7518941837b@m65192"; // DEV AND DEBUG ONLY
	
	public $product_get_call = "ProductGet";
	
	function create_json_url($call) {
		return $this->url . $call . "?APIKEY=" . $this->API_KEY;
	}
		
	
	function get_products() {
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
			//$product->ID = $prod['ProductVersion'];
			//$product->ID = $prod['ProductLongDescription'];
			//$product->ID = $prod['ProductCategoryID'];
			
			
			array_push($products, $product);
		}
		
		return $products;
	}
}



?>
