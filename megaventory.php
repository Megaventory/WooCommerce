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
			//$product->ID = $prod['ProductLongDescription'];
			//$product->ID = $prod['ProductCategoryID'];
			
			
			array_push($products, $product);
		}
		
		return $products;
	}
}

function wc_synchronize_categories($mg_categories) {
	$args = array(
		'number'     => $number,
		'orderby'    => $orderby,
		'order'      => $order,
		'hide_empty' => $hide_empty,
		'include'    => $ids
	);
	$wc_categories = get_terms('product_cat', $args);
	
	$categories_to_delete = $wc_categories;
	$categories_to_create = $mg_categories;
	
	foreach ($wc_categories as $wc_category) {
		foreach ($mg_categories as $mg_category) {
			echo "<ul>";
			echo "<li>" . $wc_category->name . "</li>";
			echo "<li>" . $mg_category . "</li>";
			echo "</ul>";
			if ($mg_category == $wc_category->name) { // untested
				$key = array_search($wc_category, $categories_to_delete);
				unset($categories_to_delete[$key]);
				
				$key = array_search($mg_category, $categories_to_add);
				unser($categories_to_add[$key]);
			} 
		}
	}
	
	foreach ($categories_to_create as $name) {
		$cid = wp_insert_term(
			$name, // the term 
			'product_cat', // the taxonomy
			array(
				'description'=> $data['description'],
				'slug' => $data['slug'],
				'parent' => $data['parent']
			)
		);
	}
	
	echo "<br> to delete : ";
	foreach ($categories_to_delete as $cat) {
		var_dump($cat);
		wp_delete_term($cat->term_id, 'product_cat');
	}
}


function wc_save_product($product) {
	$post_id = wp_insert_post( array(
		'post_title' => $product->SKU,
		'post_content' => $product->description,
		'post_status' => 'publish',
		'post_type' => "product",
    ));
    
	
	$args = array(
		'number'     => $number,
		'orderby'    => $orderby,
		'order'      => $order,
		'hide_empty' => $hide_empty,
		'include'    => $ids
	);

	$product_categories = get_terms( 'product_cat', $args );
	$terms_ids = array();
	if ( count( $product_categories ) > 0 ) {
		foreach( $product_categories as $item ) {
			array_push($terms_ids, $item->term_id);
		}
	}
	//echo "<br> var dump1: ";
	//var_dump($product_categories);
	//echo "<br> var dump2: ";
	//var_dump($terms_ids);
	
	wp_set_object_terms($post_id, $terms_ids, 'product_cat');
	//wp_set_object_terms($post_id, 'simple', 'product_type');

	update_post_meta($post_id, '_visibility', 'visible' );
	update_post_meta($post_id, '_stock_status', 'instock');
	update_post_meta($post_id, 'total_sales', '0');
	update_post_meta($post_id, '_downloadable', 'no');
	update_post_meta($post_id, '_virtual', 'no');
	update_post_meta($post_id, '_regular_price', $product->regular_price);
	//update_post_meta($post_id, '_sale_price', "0");
	update_post_meta($post_id, '_purchase_note', "");
	update_post_meta($post_id, '_featured', "no");
	update_post_meta($post_id, '_weight', "");
	update_post_meta($post_id, '_length', "");
	update_post_meta($post_id, '_width', "");
	update_post_meta($post_id, '_height', "");
	update_post_meta($post_id, '_sku', $product->SKU);
	update_post_meta($post_id, '_product_attributes', array());
	update_post_meta($post_id, '_sale_price_dates_from', "");
	update_post_meta($post_id, '_sale_price_dates_to', "");
	update_post_meta($post_id, '_price', $product->regular_price);
	update_post_meta($post_id, '_sold_individually', "");
	update_post_meta($post_id, '_manage_stock', "no");
	update_post_meta($post_id, '_backorders', "no");
	update_post_meta($post_id, '_stock', "");
}

?>
