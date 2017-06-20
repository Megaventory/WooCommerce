<?php

require_once("api.php");
require_once("address.php");
require_once("error.php");

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
	public $sale_price;
	public $sale_active;
	
	public $length;
	public $breadth;
	public $height;
	public $version;
	public $stock_on_hand;
	public $mv_qty;
	
	public $mv_type;
	
	public $variations;
	
	public $errors;
	
	private static $product_get_call = "ProductGet";
	private static $product_update_call = "ProductUpdate";
	private static $product_undelete_call = "ProductUndelete";
	private static $product_stock_call = "InventoryLocationStockGet";
	private static $inventory_get_call = "InventoryLocationGet";
	private static $category_get_call = "ProductCategoryGet";
	private static $category_update_call = "ProductCategoryUpdate";
	private static $category_delete_call = "ProductCategoryDelete";
	private static $category_undelete_call = "ProductCategoryUndelete";
	
	function __construct() {
		$this->errors = new MVWC_Errors();
	}
	
	public function errors() {
		return $this->errors;
	}
	
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
	
	public static function wc_all_with_variable() {
		$products = self::wc_all();
		$temp = array();
		foreach ($products as $prod) {
			array_push($temp, $prod);
			$vars = $prod->wc_get_variations();
			if (count($vars) > 0) {
				$temp = array_merge($temp, $vars);
			}
		}
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
	
	public function pull_stock() {
		$json_url = create_json_url_filter(self::$product_stock_call, "productid", "Equals", $this->MV_ID);
		
		$response = file_get_contents($json_url);
		$response = json_decode($response, true);
		
		// summarise product on hand in all inventories
		$response = $response['mvProductStockList'];
		$total_on_hand = 0;
		$mv_qty = array();
		
		if ($response[0]['mvStock'] != null) {
			foreach ($response[0]['mvStock'] as $inventory) {
				$inventory_name = self::get_inventory_name($inventory['InventoryLocationID'], true);
				$total = $inventory['StockPhysical'];
				$on_hand = $inventory['StockOnHand'];
				$non_shipped = $inventory['StockNonShipped'];
				$non_allocated = $inventory['StockNonAllocatedWOs'];
				$non_received = $inventory['StockNonReceivedPOs'];
				
				$string = "" . $inventory_name;
				$string .= ";" . $total;
				$string .= ";" . $on_hand;
				$string .= ";" . $non_shipped;
				$string .= ";" . $non_allocated;
				$string .= ";" . $non_received;
				
				array_push($mv_qty, $string);
				$total_on_hand += $on_hand;
			}
		} else {
			$this->stock_on_hand = 0;
			$this->mv_qty = "no stock";
			return;
		}
		
		$this->stock_on_hand = $total_on_hand;
		$this->mv_qty = $mv_qty;
	}
	
	public static function get_inventory_name($id, $abbrev = false) {
		$url = create_json_url_filter(self::$inventory_get_call, "InventoryLocationID", "Equals", urlencode($id));
		$data = json_decode(file_get_contents($url), true);
		
		if (count($data['mvInventoryLocations']) <= 0) { //not found
			return null;
		}
		
		if ($abbrev) {
			return $data['mvInventoryLocations'][0]['InventoryLocationAbbreviation'];
		} else {
			return $data['mvInventoryLocations'][0]['InventoryLocationName'];
		}
	}
	
	public static function wc_find_by_sku($SKU) {
		$prods = self::wc_all_with_variable();
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
		$product->mv_type = $mv_prod['ProductType'];
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
		
		$product->version = $mv_prod['ProductVersion'];
		
		$product->pull_stock();
		
		return $product;	
	}
	
	private static function wc_convert($wc_prod) {
		$prod = new Product();
		$ID = $wc_prod->ID;
		
		$prod->WC_ID = $wc_prod->ID;
		$prod->MV_ID = get_post_meta($ID, 'MV_ID', true);
		$prod->description = $wc_prod->post_title;
		$prod->long_description = $wc_prod->post_content;
		$prod->description = $wc_prod->post_excerpt;
		
		$prod->SKU = get_post_meta($ID, '_sku', true);
		
		$prod->regular_price = get_post_meta($ID, '_regular_price', true);
		$prod->sale_price = get_post_meta($ID, '_sale_price', true);
		$sale_from = get_post_meta($ID, '_sale_price_dates_from', true);
		$sale_to = get_post_meta($ID, '	_sale_price_dates_to', true);
		$prod->sale_active = false; //////////////////////////////////////////////// calculate by dates!
		
		$prod->weight = get_post_meta($ID, '_weight', true);
		$prod->length = get_post_meta($ID, '_length', true);
		$prod->breadth = get_post_meta($ID, '_width', true);
		$prod->height = get_post_meta($ID, '_height', true);
		$prod->category = wp_get_object_terms($ID, 'product_cat')[0]->name; // primary category ????????
		$img = wp_get_attachment_image_src(get_post_thumbnail_id($ID));
		if ($img[0]) {
			$prod->image_url = $img[0];
		}
		
		$prod->stock_on_hand = (int)get_post_meta($ID, '_stock', true);
		$prod->mv_qty = get_post_meta($ID, '_mv_qty', true);
		
		
		$var = new WC_Product_Variable($prod->WC_ID);
		$t = (wp_get_object_terms($prod->WC_ID, 'product_type')[0]->name);
		if ($t == "grouped") {
			$prod->type = "grouped";
		} else if ($t == "variable") {
			$prod->type = "variable";
			$children = $var->get_children();
			$prod->variations = $children;
		} else {
			$prod->type = "simple";
		}
		
		return $prod;
	}
	
	public function wc_get_variations() {
		if (count($this->variations) <= 0) return array();
		
		$prods = array();
		
		foreach ($this->variations as $var_prod_id) {
			$prod = self::wc_variable_convert($var_prod_id, $this);
			array_push($prods, $prod);
		}
		
		return $prods;
	}
	
	private static function wc_variable_convert($var_prod_id, $parent) {
		//inherit parent values if no values are present
		$var_prod = new WC_Product_Variation($var_prod_id);
		$prod = new Product();
		
		$prod->WC_ID = $var_prod_id;
		$prod->MV_ID = get_post_meta($var_prod_id, "MV_ID", true);
		$prod->SKU = $var_prod->get_sku();
		$prod->description = $var_prod->get_description() ? $var_prod->get_description() : $parent->description;
		$prod->type = "variable-child";
		$prod->regular_price = $var_prod->get_price() ? $var_prod->get_price() : $parent->regular_price;
		
		$prod->weight = $var_prod->get_weight() ? $var_prod->get_weight() : $parent->weight;
		$prod->height = $var_prod->get_height() ? $var_prod->get_height() : $parent->height;
		$prod->length = $var_prod->get_length() ? $var_prod->get_length() : $parent->length;
		$prod->breadth = $var_prod->get_width() ? $var_prod->get_width() : $parent->breadth;
		
		$prod->category = $parent->category;
		
		//version is | name - var1, var2, var3
		//mv vestion should be | var1, var2, var3
		$version = $var_prod->get_name();
		$version = str_replace(" ", "", $version); //remove whitespaces
		$version = explode("-", $version)[1]; //disregard name
		//$version = explode(",", $version);
		//$version = implode("/", $version);
		$version = str_replace(",", "/", $version);
		
		$prod->version = $version;
		
		return $prod;
	}
	
	//only simple now
	public function wc_save($wc_products = null) {
		wp_mail("mpanasiuk@megaventory.com", "SAVING", "");
		if ($wc_products == null) {
			$wc_products = ($this->version == null) ? self::wc_all() : self::wc_all_with_variable();
		}
		
		//find if SKU exists, if so, update instead of insert
		if ($this->WC_ID == null) {
			foreach ($wc_products as $wc_product) {
				if ($this->SKU == $wc_product->SKU) {
					$this->WC_ID = $wc_product->WC_ID;
					break;
				}
			}	
		}
		
		//prevent null on empty
		if ($this->long_description == null) {
			$this->long_description = "";
		}
		if ($this->description == null) {
			//throw new Exception('Short description can\'t be empty');
			$this->log_error('Product not saved to WC', 'Short description cannot be empty', -1);
			return false;
		}
		if ($this->SKU == null) {
			$this->log_error('Product not saved to WC', 'SKU cannot be empty', -1);
			return false;
		}
		
	
		//dont update variables title!
		if ($this->WC_ID == null) { // look out. instead of always creating new, find one with same SKU and use that instead!
			//create product
			$args = array
			(
				'post_content' => $this->long_description,
				'post_excerpt' => $this->description,
				'post_status' => 'publish',
				'post_type' => "product",
			);
			if ($this->version == null) $args['post_title'] = $this->description;
			$post_id = wp_insert_post($args);
			if (is_wp_error($post_id)) {
				$this->log_error('Product not saved to WC', $post_id->get_error_message(), $post_id->get_error_code());
				return false;
			}
			
			$this->WC_ID = $post_id;
		} else { 
			//never update product title. only on create
			$post = array(
				'ID' => $this->WC_ID,
				//'post_title' => $this->description,
				'post_content' => $this->long_description,
				'post_excerpt' => $this->description,
			);
			//if ($this->version == null) $post['post_title'] = $this->description;
			$return = wp_update_post($post);
			if (is_wp_error($return)) {
				$this->log_error('Product not saved to WC', $return->get_error_message(), $return->get_error_code());
				return false;
			}
		}
		
		//meta
		
		//set category to mv category only if product has no categories
		//otherwise, dont do anything
		if ($this->category != null and count(wp_get_object_terms($this->WC_ID, 'product_cat')) <= 0) {
			$category_id = $this->wc_get_category_id_by_name($this->category, true);
			if ($category_id) {
				echo "setting category";
				wp_set_object_terms($this->WC_ID, $category_id, 'product_cat');
			}
		}
		
		
		//wp_set_object_terms($post_id, 'simple', 'product_type');

		//set other information
		update_post_meta($this->WC_ID, '_visibility', 'visible');
		update_post_meta($this->WC_ID, '_regular_price', $this->regular_price);
		update_post_meta($this->WC_ID, '_weight', $this->weight);
		update_post_meta($this->WC_ID, '_length', $this->length);
		update_post_meta($this->WC_ID, '_width', $this->breadth);
		update_post_meta($this->WC_ID, '_height', $this->height);
		update_post_meta($this->WC_ID, '_sku', $this->SKU);
		update_post_meta($this->WC_ID, '_price', $this->regular_price);
		update_post_meta($this->WC_ID, '_manage_stock', "yes");
		update_post_meta($this->WC_ID, '_stock', (string)$this->stock_on_hand);
		update_post_meta($this->WC_ID, '_stock_status', ($this->stock_on_hand > 0 ? "instock" : "outofstock"));
		
		if ($this->version != null) {
			update_post_meta($this->WC_ID, '_variation_description', $this->description);
		}
		
		
		//echo "<br>" . $product->stock_on_hand;
		
		$this->attach_image();
		
		update_post_meta($this->WC_ID, "MV_ID", $this->MV_ID);
		
		wp_mail("mpanasiuk@megaventory.com", "AMEN", "");
		
		return true;
	}
		
	public function log_error($problem, $full_msg, $code, $type = "error") {
		$args = array
		(
			'entity_id' => array('wc' => $this->WC_ID, 'mv' => $this->MV_ID), 
			'entity_name' => ($this->name == null) ? $this->description : $this->null,
			'problem' => $problem,
			'full_msg' => $full_msg,
			'error_code' => $code,
			'type' => $type
		);
		$this->errors->log_error($args);
	}
	
	public function mv_save($categories = null) {
		if ($this->type == "grouped") {
			$this->log_error('Product not saved to MV', 'Grouped products cannot be saved to MV', -1, 'warning');
			return false;
		}
		
		if ($categories == null) {
			$categories = self::mv_get_categories();
		}
		
		echo "UPDATING CATEGORY: " . $this->category . "<br>";
		if ($this->category != null) {
			$category_id = array_search($this->category, $categories);
			var_dump($category_id);
			echo " cat_ID<br>";
			if (!$category_id) { //need to create new category
				echo "lest create cat: " . $this->category . "<br>;";
				$category_id = self::mv_create_category($this->category);
			}
		}
		
		$prod = self::mv_find_by_sku($this->SKU);
		if ($prod) {
			$this->MV_ID = $prod->MV_ID;
			$this->mv_type = $prod->mv_type;
		}
		
		
		//this needs to be split into few small requests, as urls get too long otherwise
		//$create_url = $this->create_json_url($this->product_update_call);
		$url = create_xml_url(self::$product_update_call);
		
		$xml_request = $this->generate_update_xml($category_id);
		
		$data = send_xml($url, $xml_request);
		
		var_dump($this);
		echo "<br>- - - - - - - - - - - - - - -      - - - - - - - -<br>";
		var_dump(htmlentities($xml_request));
		echo "<br>- - - - - - - - - - - - - - -      - - - - - - - -<br>";
		var_dump($data);
		
		if ($data['InternalErrorCode'] == "ProductSKUAlreadyDeleted") {
			$this->MV_ID = $data['entityID'];
			$undelete_url = create_json_url(self::$product_undelete_call);
			$undelete_url = $undelete_url . "&ProductIDToUndelete=" . urlencode($this->MV_ID);
			file_get_contents($undelete_url);
			
			//try again
			$xml_request = $this->generate_update_xml($category_id);
			$data = send_xml($url, $xml_request);
		}
		
		wp_mail('mpanasiuk@megaventory.com', "producct save", var_export($xml_request, true));
		wp_mail('mpanasiuk@megaventory.com', "producct savehhh", var_export($data, true));
		
		if (count($data['mvProduct']) <= 0) { //not saved
			$this->log_error('Product not saved to MV', $data['InternalErrorCode'], -1);
			wp_mail("mpanasiuk@megaventory.com", "AAAAAAAAAAAA", "AAAAggfjfuAAAAAAAAH");
			//$this->log_error('Product not saved to MV', 'Grouped products cannot be saved to MV', -1, 'warning');
			return false;
		}
		
		update_post_meta($this->WC_ID, "MV_ID", $data["mvProduct"]["ProductID"]);
		$this->MV_ID = $data["mvProduct"]["ProductID"];
		
		//save variable children?
		if (count($this->variations) > 0) {
			foreach ($this->variations as $id) {
				$prod = self::wc_variable_convert($id, $this);
				echo "<br>//////////////////////////////////////////////////////<br>";
				var_dump($prod->mv_save());
			}
		}
		
		
		return $data['mvProduct'];
	}
	
	private function generate_update_xml($category_id = null) {
		//find category_id outside this function
		$create_new = $this->MV_ID == null;
		$action = ($create_new ? "Insert" : "Update");
		
		$xml_request = '
				<mvProduct>' . '
					' . ($create_new ? '' : '<ProductID>' . $this->MV_ID . '</ProductID>') . '
					' . ($create_new ? '<ProductType>BuyFromSupplier</ProductType>' : ($this->mv_type ? '<ProductType>' . $this->mv_type . '</ProductType>' : '')) . '
					<ProductSKU>' . $this->SKU . '</ProductSKU> 
					<ProductDescription>' . $this->description . '</ProductDescription> 
					' . ($this->version ? '<ProductVersion>' . $this->version . '</ProductVersion>' : '') . '
					' . ($this->long_description ? '<ProductLongDescription>' . $this->long_description . '</ProductLongDescription>' : '') . '
					' . ($category_id ? '<ProductCategoryID>' . $category_id . '</ProductCategoryID>' : '') . '
					' . ($this->regular_price ? '<ProductSellingPrice>' . $this->regular_price . '</ProductSellingPrice>' : '') . '
					' . ($this->weight ? '<ProductWeight>' . $this->weight . '</ProductWeight>' : '') . '
					' . ($this->length ? '<ProductLength>' . $this->length . '</ProductLength>' : '') . '
					' . ($this->breadth ? '<ProductBreadth>' . $this->breadth . '</ProductBreadth>' : '') . '
					' . ($this->height ? '<ProductHeight>' . $this->height . '</ProductHeight>' : '') . '
					' . ($this->image_url ? '<ProductImageURL>' . $this->image_url . '</ProductImageURL>' : '') . '
				</mvProduct>
				<mvRecordAction>' . $action . '</mvRecordAction>
				<mvInsertUpdateDeleteSourceApplication>woocommerce</mvInsertUpdateDeleteSourceApplication>
			';
		$xml_request = wrap_xml(self::$product_update_call, $xml_request);
		
		return $xml_request;
	}
	
	public function wc_destroy() {
		if ($this->WC_ID == null) {
			$all = ($this->version == null) ? self::wc_all() : self::wc_all_with_variable();
			foreach ($all as $prod) {
				if ($prod->SKU == $this->SKU) {
					$this->WC_ID = $prod->SKU;
					break;
				}
			}
		}
		wp_mail("mpanasiuk@megaventory.com", "TEMAT", var_export($this, true));
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
			'include'    => $ids,
			'hierarchical' => 1,
		);
		return get_terms('product_cat', $args);
	}
	
	private function wc_get_category_id_by_name($name, $with_create = false) {
		echo "getting  ID BY NAME";
		$product_categories = self::wc_get_categories();
		$category_id = array();
		foreach($product_categories as $item) {
			if ($item->name == $this->category) {
				array_push($category_id, $item->term_id);
				return $category_id;
			}
		}
		
		$category_id = array();
		if ($with_create) {
			$cid = wp_insert_term(
				$name, // the term 
				'product_cat', // the taxonomy
				array()
			);
			return array($cid['term_id']);
		}
		
		return null;
	}
	
	public static function mv_get_categories() {
		$jsonurl = create_json_url(self::$category_get_call);
		$jsoncat = file_get_contents($jsonurl);
		$jsoncat = json_decode($jsoncat, true);
		
		$categories = array();
		foreach ($jsoncat['mvProductCategories'] as $cat) {
			$categories[$cat['ProductCategoryID']] = $cat['ProductCategoryName'];
		}
		
		return $categories;
	}

	public static function mv_create_category($name) {
		echo "finally creating: " . $name . "<br>";
		$create_url = create_json_url(self::$category_update_call);
		$url = $create_url . "&mvProductCategory={ProductCategoryName:" . urlencode($name) . "}";
		$response = json_decode(file_get_contents($url), true);
		
		if ($response['InternalErrorCode'] == "CategoryWasDeleted") { //needs to be undeleted
			$id = $response['entityID'];
			echo "UNDELETING" . $id . "<br>";
			$undelete_url = create_json_url(self::$category_undelete_call);
			$url = $undelete_url . "&ProductCategoryIDToUndelete=" . urlencode($id);
			$response = json_decode(file_get_contents($url), true);
			if ($response['result']) {
				$response['mvProductCategory'] = array();
				$response['mvProductCategory']['ProductCategoryID'] = $id;
			}
		}
		
		echo "response: ";
		var_dump($response);
		
		echo "returning: " . $response['mvProductCategory']['ProductCategoryID'] . "<br>";
		
		return (array_key_exists('mvProductCategory', $response)) ? $response['mvProductCategory']['ProductCategoryID'] : null;
	}
	
		
	public function sync_stock() {
		if ($this->MV_ID == null) return; //this should not happen
		
		foreach (self::wc_all() as $wc_product) {
			if ($this->SKU == $wc_product->SKU) {
				$this->WC_ID = $wc_product->WC_ID;
				break;
			}
		}	
		
		$this->pull_stock();
		update_post_meta($this->WC_ID, '_mv_qty', $this->mv_qty);
		update_post_meta($this->WC_ID, '_manage_stock', "yes");
		update_post_meta($this->WC_ID, '_stock', (string)$this->stock_on_hand);
		update_post_meta($this->WC_ID, '_stock_status', ($this->stock_on_hand > 0 ? "instock" : "outofstock"));
	}
	
	public function wc_reset_mv_data() {
		delete_post_meta($this->WC_ID, "MV_ID");
		delete_post_meta($this->WC_ID, '_mv_qty');
	}
}

?>
