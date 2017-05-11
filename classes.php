<?php

class Product {
	public $name;
	public $sku;
	public $description;
	public $long_description;
	public $quantity;
	public $category;
	
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
		
		return $jsonprod;
	}
	
}

?>
