<?php

require_once("product.php");

class Woocommerce_sync {
	
	function get_categories() {
		$args = array(
			'number'     => $number,
			'orderby'    => $orderby,
			'order'      => $order,
			'hide_empty' => $hide_empty,
			'include'    => $ids
		);
		return get_terms('product_cat', $args);
		
	}
	
	function synchronize_categories($mg_categories) {
		
		$wc_categories = $this->get_categories();
		
		$categories_to_delete = $wc_categories;
		$categories_to_create = $mg_categories;
		
		foreach ($wc_categories as $wc_category) {
			foreach ($mg_categories as $mg_category) {
				if ($mg_category == $wc_category->name) { // untested
					$key = array_search($wc_category, $categories_to_delete);
					unset($categories_to_delete[$key]);
					
					$key = array_search($mg_category, $categories_to_create);
					unset($categories_to_add[$key]);
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
		
	function add_simple_product($product) {
		//create product
		$post_id = wp_insert_post(array(
			'post_title' => $product->SKU,
			'post_content' => $product->long_description,
			'post_excerpt' => $product->description,
			'post_status' => 'publish',
			'post_type' => "product",
		));
		
		//set category
		$product_categories = $this->get_categories();
		$category_id = array();
		foreach($product_categories as $item) {
			if ($item->name == $product->category) {
				array_push($category_id, $item->term_id);
			}
		}
		wp_set_object_terms($post_id, $category_id, 'product_cat');
		
		
		//wp_set_object_terms($post_id, 'simple', 'product_type');

		//set other information
		update_post_meta($post_id, '_short_description', 'blah');
		update_post_meta($post_id, '_visibility', 'visible');
		update_post_meta($post_id, '_stock_status', 'instock');
		update_post_meta($post_id, 'total_sales', '0');
		update_post_meta($post_id, '_downloadable', 'no');
		update_post_meta($post_id, '_virtual', 'no');
		update_post_meta($post_id, '_regular_price', $product->regular_price);
		//update_post_meta($post_id, '_sale_price', "0");
		update_post_meta($post_id, '_purchase_note', "");
		update_post_meta($post_id, '_featured', "no");
		update_post_meta($post_id, '_weight', $product->weight);
		update_post_meta($post_id, '_length', $product->length);
		update_post_meta($post_id, '_width', $product->breadth);
		update_post_meta($post_id, '_height', $product->height);
		update_post_meta($post_id, '_sku', $product->SKU);
		update_post_meta($post_id, '_product_attributes', array());
		update_post_meta($post_id, '_sale_price_dates_from', "");
		update_post_meta($post_id, '_sale_price_dates_to', "");
		update_post_meta($post_id, '_price', $product->regular_price);
		update_post_meta($post_id, '_sold_individually', "");
		update_post_meta($post_id, '_manage_stock', "no");
		update_post_meta($post_id, '_backorders', "no");
		update_post_meta($post_id, '_stock', "");
		
		echo "<br>product: ";
		var_dump($product);
		echo "<br>post: ";
		var_dump($post_id);
	}
}

?>
