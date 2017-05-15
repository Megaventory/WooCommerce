<?php

require_once("product.php");

// This class makes it easier to store/retrieve information
// from woocommerce
class Woocommerce_sync {
	
	//get categories as wordpress post
	function get_categories_posts() {
		$args = array(
			'number'     => $number,
			'orderby'    => $orderby,
			'order'      => $order,
			'hide_empty' => $hide_empty,
			'include'    => $ids
		);
		return get_terms('product_cat', $args);
		
	}
	
	// synchronize categories with those provided in arguments
	function synchronize_categories($mg_categories, $with_delete = false) {
		
		$wc_categories = $this->get_categories_posts();
		
		$categories_to_delete = $wc_categories;
		$categories_to_create = $mg_categories;
		
		// leave categories that exist both in WC and MV
		// Add categories that exist only in MV
		// Delete categories that exist only in WC (if requested)
		foreach ($wc_categories as $wc_category) {
			foreach ($mg_categories as $mg_category) {
				if ($mg_category == $wc_category->name) {
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
		
		if ($with_delete) {
			foreach ($categories_to_delete as $cat) {
				wp_delete_term($cat->term_id, 'product_cat');
			}
		}
	}
	
	// synchronize products with those provided in arguments
	function synchronize_products($products, $with_delete = false) {
		$wc_products = $this->get_products_posts();
		$skus = array();
		//get SKUs of existing products
		foreach ($wc_products as $wc_product) {
			$sku = get_post_meta($wc_product->ID, '_sku', true);
			array_push($skus, $sku);
		}
		
		//update if product exists,
		//create if it does not exist
		foreach ($products as $product) {
			if (in_array($product->SKU, $skus)) {
				$post = $this->get_product_post_by_SKU($product->SKU);
				
				$this->update_simple_product($post->ID, $product);
			} else {
				$this->add_simple_product($product);
			}
		}
		
		//delete unneeded products if so instructed
		if ($with_delete) {
			$to_delete = $skus;
			foreach ($skus as $sku) {
				foreach ($products as $product) {
					if ($product->SKU == $sku) {
						$key = array_search($sku, $to_delete);
						unset($to_delete[$key]);
					}
				}
			}
			
			foreach ($to_delete as $sku) {
				$product_to_delete = $this->get_product_post_by_SKU($sku);
				wp_delete_post($product_to_delete->ID);
			}
			
		}
			
	}
	
	function get_products_posts() {
		$args = array('post_type' => 'product', numberposts => -1);
		$products = get_posts($args);
		return $products;
	}
	
	function get_product_post_by_SKU($SKU) {
		$products = $this->get_products_posts();
		$to_return = null;
		foreach($products as $product) {
			$sku = get_post_meta($product->ID, '_sku', true);
			if ($SKU == $sku) {
				$to_return = $product;
				break;
			}
		}
		return $to_return;
	}
	
	function update_simple_product($post_id, $product) {
		$post = array(
			'ID' => $post_id,
			'post_title' => $product->description,
			'post_content' => $product->long_description,
			'post_excerpt' => $product->description,
		);
		wp_update_post($post);
		
		$this->set_product_meta($post_id, $product);
		
		$this->attach_image($post_id, $product);
	}
	
	// add simple product
	function add_simple_product($product) {
		//create product
		$post_id = wp_insert_post(array(
			'post_title' => $product->description,
			'post_content' => $product->long_description,
			'post_excerpt' => $product->description,
			'post_status' => 'publish',
			'post_type' => "product",
		));
		
		$this->set_product_meta($post_id, $product);

		
		$this->attach_image($post_id, $product);
		
		echo "<br>product: ";
		var_dump($product);
		echo "<br>post: ";
		var_dump($post_id);
	}
	
	// image is attached only if a product has no image yet
	function attach_image($post_id, $product) {
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		
		if ($product->image_url == null or $product->image_url == "") {
			return;
		} else {
			//only upload image id doesn't exist
			if (wp_get_attachment_image_src(get_post_thumbnail_id($post_id)) != null) {
				return;
			}			
		}
		
		$dir = dirname(__FILE__);
		$imageFolder = $dir.'/../import/';
		$imageFile = $product->SKU;
		$imageFull = $imageFolder.$imageFile;
		
		// image
		$image = $product->image_url;

		// magic sideload image returns an HTML image, not an ID
		$media = media_sideload_image($image, $post_id);

		// therefore we must find it so we can set it as featured ID
		if(!empty($media) && !is_wp_error($media)){
			$args = array(
				'post_type' => 'attachment',
				'posts_per_page' => -1,
				'post_status' => 'any',
				'post_parent' => $post_id
			);

			// reference new image to set as featured
			$attachments = get_posts($args);

			if(isset($attachments) && is_array($attachments)){
				foreach($attachments as $attachment){
					// grab source of full size images (so no 300x150 nonsense in path)
					$image = wp_get_attachment_image_src($attachment->ID, 'full');
					// determine if in the $media image we created, the string of the URL exists
					if(strpos($media, $image[0]) !== false){
						// if so, we found our image. set it as thumbnail
						set_post_thumbnail($post_id, $attachment->ID);
						// only want one image
						break;
					}
				}
			}
		}
	}
	
	function set_product_meta($post_id, $product) {
				
		//set category
		$product_categories = $this->get_categories_posts();
		$category_id = array();
		foreach($product_categories as $item) {
			if ($item->name == $product->category) {
				array_push($category_id, $item->term_id);
			}
		}
		wp_set_object_terms($post_id, $category_id, 'product_cat');
		
		
		//wp_set_object_terms($post_id, 'simple', 'product_type');

		//set other information
		update_post_meta($post_id, '_visibility', 'visible');
		update_post_meta($post_id, '_stock_status', ($product->stock_on_hand > 0 ? "instock" : "outofstock"));
		update_post_meta($post_id, '_regular_price', $product->regular_price);
		update_post_meta($post_id, '_weight', $product->weight);
		update_post_meta($post_id, '_length', $product->length);
		update_post_meta($post_id, '_width', $product->breadth);
		update_post_meta($post_id, '_height', $product->height);
		update_post_meta($post_id, '_sku', $product->SKU);
		update_post_meta($post_id, '_price', $product->regular_price);
		update_post_meta($post_id, '_manage_stock', "yes");
		update_post_meta($post_id, '_stock', (string)$product->stock_on_hand);
		
		
		//update_post_meta($post_id, '_product_attributes', array());
		//update_post_meta($post_id, '_featured', "no");
		//update_post_meta($post_id, 'total_sales', '0');
		//update_post_meta($post_id, '_downloadable', 'no');
		//update_post_meta($post_id, '_virtual', 'no');
		//update_post_meta($post_id, '_sale_price', "0");
		//update_post_meta($post_id, '_purchase_note', "");
		//update_post_meta($post_id, '_sale_price_dates_from', "");
		//update_post_meta($post_id, '_sale_price_dates_to', "");
		//update_post_meta($post_id, '_sold_individually', "");
		//update_post_meta($post_id, '_backorders', "no");
		
		echo "<br>" . $product->stock_on_hand;
	}
}

?>
