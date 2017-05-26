<?php

require_once("conf.php");

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
	
	private static $product_get_call = "ProductGet";
	private static $product_update_call = "ProductUpdate";
	private static $product_stock_call = "InventoryLocationStockGet";
	private static $category_get_call = "ProductCategoryGet";
	private static $category_update_call = "ProductCategoryUpdate";
	private static $category_delete_call = "ProductCategoryDelete";
	
	public static function wc_all() {
		$args = array('post_type' => 'product', numberposts => -1);
		$products = get_posts($args);
		$temp = array();
		foreach ($products as $product) {
			array_push($temp, self::wc_convert($product));
		}
		$products = $temp;
		return $temp;
	}
	
	public static function mv_all() {
		$categories = self::mv_get_categories();
		
		// get products as json
		$jsonurl = create_json_url(self::$product_get_call);
		$jsonprod = file_get_contents($jsonurl);
		$jsonprod = json_decode($jsonprod, true);
		
		// interpret json into Product class
		$products = array();
		foreach ($jsonprod['mvProducts'] as $prod) {
			$product = self::mv_convert($prod, $categories);
			array_push($products, $product);
		}
		
		return $products;
	}
	
	public static function wc_find($id) {
		$wc_prod = get_post($id);
		if ($wc_prod) {
			return self::wc_convert($wc_prod);
		} else { 
			return null;
		}
	}
	
	public static function mv_find($id) {
		$url = create_json_url_filter(self::$product_get_call, "ProductID", "Equals", urlencode($id));
		$data = json_decode(file_get_contents($url), true);
		if (count($data['mvProducts']) <= 0) {
			return null; //no such ID
		}
		return self::mv_convert($data["mvProducts"][0]);
	}
	
	private function pull_stock() {
		$json_url = create_json_url_filter(self::$product_stock_call, "productid", "Equals", $this->MV_ID);
		
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
			$this->stock_on_hand = 0;
			return;
		}
		
		$this->stock_on_hand = $total_on_hand;
	}
	
	public static function wc_find_by_sku($SKU) {
		$prods = self::wc_all();
		foreach ($prods as $prod) {
			if ($prod->SKU == $SKU) {
				return $prod;
			}
		}
		return null;
	}
	
	public static function mv_find_by_sku($SKU) {
		$url = create_json_url_filter(self::$product_get_call, "ProductSKU", "Equals", urlencode($SKU));
		$data = json_decode(file_get_contents($url), true);
		if (count($data['mvProducts']) <= 0) {
			return null; //no such ID
		}
		return self::mv_convert($data["mvProducts"][0]);
	}
	
	private static function mv_convert($mv_prod, $categories = null) {
		//passing categories makes things faster and requires less API calls.
		//always use $categories when using this function in a loop with many users
		if ($categories == null) {
			$categories = self::mv_get_categories();
		}
		$product = new Product();
		
		$product->MV_ID = $mv_prod['ProductID'];
		$product->type = $mv_prod['ProductType'];
		$product->SKU = $mv_prod['ProductSKU'];
		$product->EAN = $mv_prod['ProductEAN'];
		$product->description = $mv_prod['ProductDescription'];
		$product->long_description = $mv_prod['ProductLongDescription'];
		$product->image_url = $mv_prod['ProductImageURL'];
		
		$product->regular_price = $mv_prod['ProductSellingPrice'];
		$product->category = $categories[$mv_prod['ProductCategoryID']];
		
		$product->weight = $mv_prod['ProductWeight'];
		$product->length = $mv_prod['ProductLength'];
		$product->breadth = $mv_prod['ProductBreadth'];
		$product->height = $mv_prod['ProductHeight'];
		
		$product->pull_stock();
		
		return $product;	
	}
	
	private static function wc_convert($wc_prod) {
		$prod = new Product();
		$ID = $wc_prod->ID;
		
		$prod->WC_ID = $wc_prod->ID;
		$prod->description = $wc_prod->post_title;
		$prod->long_description = $wc_prod->post_content;
		$prod->description = $wc_prod->post_excerpt;
		
		$prod->SKU = get_post_meta($ID, '_sku', true);
		$prod->regular_price = get_post_meta($ID, '_regular_price', true);
		$prod->weight = get_post_meta($ID, '_weight', true);
		$prod->length = get_post_meta($ID, '_length', true);
		$prod->breadth = get_post_meta($ID, '_width', true);
		$prod->height = get_post_meta($ID, '_height', true);
		$prod->category = wp_get_object_terms($ID, 'product_cat')[0]->name; // primary category ????????
		$img = wp_get_attachment_image_src(get_post_thumbnail_id($ID));
		if ($img[0]) {
			$prod->image_url = $img[0];
		}
		
		return $prod;
	}
	
	//only simple now
	public function wc_save() {
		//prevent null on empty
		if ($this->long_description == null) {
			$this->long_description = "";
		}
		if ($this->description == null) {
			throw new Exception('Short description can\'t be empty');
		}
		if ($this->SKU == null) {
			throw new Exception('SKU can\'t be empty');
		}
		
		if ($this->WC_ID == null) {
			//can't have 2 products with same SKU
			foreach (Product::wc_all() as $product) {
				if ($this->SKU == $product->SKU) {
					return false;
				}
			}	
			//create product
			$post_id = wp_insert_post(array(
				'post_title' => $this->description,
				'post_content' => $this->long_description,
				'post_excerpt' => $this->description,
				'post_status' => 'publish',
				'post_type' => "product",
			));
			
			$this->WC_ID = $post_id;
		} else {
			$post = array(
				'ID' => $this->WC_ID,
				'post_title' => $this->description,
				'post_content' => $this->long_description,
				'post_excerpt' => $this->description,
			);
			wp_update_post($post);
		}
		
		//meta
		
		//set category
		$category_id = $this->wc_get_category_id_by_name($this->category, true);
		if ($category_id) {
			echo "setting category";
			wp_set_object_terms($this->WC_ID, $category_id, 'product_cat');
		}
		
		
		//wp_set_object_terms($post_id, 'simple', 'product_type');

		//set other information
		update_post_meta($this->WC_ID, '_visibility', 'visible');
		update_post_meta($this->WC_ID, '_stock_status', ($this->stock_on_hand > 0 ? "instock" : "outofstock"));
		update_post_meta($this->WC_ID, '_regular_price', $this->regular_price);
		update_post_meta($this->WC_ID, '_weight', $this->weight);
		update_post_meta($this->WC_ID, '_length', $this->length);
		update_post_meta($this->WC_ID, '_width', $this->breadth);
		update_post_meta($this->WC_ID, '_height', $this->height);
		update_post_meta($this->WC_ID, '_sku', $this->SKU);
		update_post_meta($this->WC_ID, '_price', $this->regular_price);
		update_post_meta($this->WC_ID, '_manage_stock', "yes");
		update_post_meta($this->WC_ID, '_stock', (string)$this->stock_on_hand);
		
		
		//echo "<br>" . $product->stock_on_hand;
		
		$this->attach_image();
		
		return true;
		
	}
	
	public function mv_save() {
		
	}
	
	public function wc_destroy() {
		wp_delete_post($this->WC_ID);
	}
	
	public function mv_destroy() {
		
	}
	
		// image is attached only if a product has no image yet
	function attach_image() {
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		
		if ($this->image_url == null or $this->image_url == "") {
			return false;
		} else {
			//only upload image id doesn't exist
			if (wp_get_attachment_image_src(get_post_thumbnail_id($this->WC_ID)) != null) {
				return false;
			}			
		}
		
		$dir = dirname(__FILE__);
		$imageFolder = $dir.'/../import/';
		$imageFile = $this->SKU;
		$imageFull = $imageFolder.$imageFile;
		
		// image
		$image = $this->image_url;

		// magic sideload image returns an HTML image, not an ID
		$media = media_sideload_image($image, $this->WC_ID);

		//echo $media->get_error_message();
		// therefore we must find it so we can set it as featured ID
		if(!empty($media) && !is_wp_error($media)){
			$args = array(
				'post_type' => 'attachment',
				'posts_per_page' => -1,
				'post_status' => 'any',
				'post_parent' => $this->WC_ID
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
						set_post_thumbnail($this->WC_ID, $attachment->ID);
						// only want one image
						break;
					}
				}
			}
		} else {
			return false;
		}
		
		return true;
	}
	
	private static function wc_get_categories() {
		$args = array(
			'number'     => $number,
			'orderby'    => $orderby,
			'order'      => $order,
			'hide_empty' => $hide_empty,
			'include'    => $ids
		);
		return get_terms('product_cat', $args);
	}
	
	private function wc_get_category_id_by_name($name, $with_create = false) {
		$product_categories = self::wc_get_categories();
		$category_id = array();
		foreach($product_categories as $item) {
			if ($item->name == $this->category) {
				array_push($category_id, $item->term_id);
				return $category_id;
			}
		}
		
		if ($with_create) {
			$cid = wp_insert_term(
				$name, // the term 
				'product_cat', // the taxonomy
				array()
			);
			array_push($category_id, $cid['term_id']);
			return $category_id;
		}
		
		return null;
	}
	
	private static function mv_get_categories() {
		$jsonurl = create_json_url(self::$category_get_call);
		echo "URL: " . $jsonurl . "<br>";
		$jsoncat = file_get_contents($jsonurl);
		$jsoncat = json_decode($jsoncat, true);
		
		$categories = array();
		foreach ($jsoncat['mvProductCategories'] as $cat) {
			$categories[$cat['ProductCategoryID']] = $cat['ProductCategoryName'];
		}
		
		return $categories;
	}

}




?>
