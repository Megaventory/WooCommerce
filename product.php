<?php

// This class works as a model for a product
// It holds all important attributes of a MV/WC product
// WC and MV will store same products at different IDs. Those IDs can be accessed separately
// SKU is more important than ID and can be used to compare products
class Product {
	public $WC_ID;
	public $MV_ID;
	public $name;
	public $SKU;
	public $EAN;
	public $description;
	public $long_description;
	public $quantity;
	public $category;
	public $type;
	public $image_url;
	
	public $regular_price;
	
	public $length;
	public $breadth;
	public $height;
	
	public $version;
	
	public $stock_on_hand;

}

?>
