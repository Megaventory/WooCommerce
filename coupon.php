<?php

require_once("api.php");
require_once("address.php");
require_once("error.php");

//This class works a model for coupons (WC) / discounts (MV).
class Coupon {
	public $MV_ID;
	public $WC_ID;
	public $name = "";
	public $description = "";
	public $rate = 0.0;
	
	public $type; // either 'percent' or 'fixed'
	
	private static $MV_URL_discount_get = "https://apitest.megaventory.com/xml/reply/DiscountGet";
	private static $MV_URL_discount_update = "https://apitest.megaventory.com/xml/reply/DiscountUpdate"; //Used also for adding new discounts
	
	private static $MV_URL_category_update = "https://apitest.megaventory.com/xml/reply/ProductCategoryUpdate";
	private static $MV_URL_category_get = "https://apitest.megaventory.com/xml/reply/ProductCategoryGet";
	private static $MV_URL_category_undelete = "https://apitest.megaventory.com/xml/reply/ProductCategoryUndelete";
	
	private static $MV_URL_product_get = "https://apitest.megaventory.com/xml/reply/ProductGet";
	private static $MV_URL_product_update = "https://apitest.megaventory.com/xml/reply/ProductUpdate";
	
	//Check if discount with given name is in MV database.
	//1. Get all records with name $name.
	//2. Check if returned array contains anything.
	public function MV_is_name_in_products() {
		$xml = send_xml(self::$MV_URL_product_get,
			self::XML_is_name_in_products());
		return !empty($xml['mvProducts']);
	}	
	
	public static function WC_all() {
		global $wpdb;
		$prefix = $wpdb->prefix;
		$results = $wpdb->get_results("
				SELECT 
					{$prefix}posts.ID as id, 
					{$prefix}posts.post_title as name, 
					{$prefix}posts.post_excerpt as description,
					meta1.meta_value as rate, 
					meta2.meta_value as discount_type 
				FROM 
					{$prefix}posts, 
					{$prefix}postmeta as meta1, 
					{$prefix}postmeta as meta2 
				WHERE {$prefix}posts.post_type = 'shop_coupon' 
					AND {$prefix}posts.post_status = 'publish' 
					AND meta1.meta_key = 'coupon_amount' 
					AND meta1.post_id = {$prefix}posts.ID
					AND meta2.meta_key = 'discount_type' 
					AND	meta2.post_id = meta1.post_id"
				, ARRAY_A );
		
		$coupons = array();

		foreach ($results as $number => $buffer) {
				$coupon = new Coupon;
				
				$coupon->WC_ID = $buffer['id'];
				$coupon->name = $buffer['name'];
				$coupon->rate = $buffer['rate'];
				$coupon->description = $buffer['description'];
				$coupon->type = $buffer['discount_type'];
				array_push($coupons, $coupon);
		}
		
		return $coupons;
	}
	
	public static function WC_find($id) {
		global $wpdb;
		$prefix = $wpdb->prefix;
		$results = $wpdb->get_results("
				SELECT 
					{$prefix}posts.ID as id, 
					{$prefix}posts.post_title as name, 
					{$prefix}posts.post_excerpt as description,
					meta1.meta_value as rate, 
					meta2.meta_value as discount_type 
				FROM 
					{$prefix}posts, 
					{$prefix}postmeta as meta1, 
					{$prefix}postmeta as meta2 
				WHERE {$prefix}posts.post_type = 'shop_coupon' 
					AND {$prefix}posts.ID = {$id}
					AND {$prefix}posts.post_status = 'publish' 
					AND meta1.meta_key = 'coupon_amount' 
					AND meta1.post_id = {$prefix}posts.ID
					AND meta2.meta_key = 'discount_type' 
					AND	meta2.post_id = meta1.post_id"
				, ARRAY_A );
		
		if (count($results) == 0) return null;
		$buffer = $results[0];
		
		$coupon = new Coupon;
		
		$coupon->name = $buffer['name'];
		$coupon->rate = $buffer['rate'];
		$coupon->description = $buffer['description'];
		$coupon->type = $buffer['discount_type'];
		
		return $coupon;
	}
	
	public function MV_load_by_description($description) {
		
	}
	
	private static function XML_load_by_description() {
		$query = '<DiscountGet xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">' . 
			  '<Filters xmlns="http://schemas.datacontract.org/2004/07/MegaventoryAPI.ServiceModel">' . 
				'<Filter>' .
				  '<AndOr>And</AndOr>' .
				  '<FieldName>DiscountDescription</FieldName>' .
				  '<SearchOperator>Equals</SearchOperator>' . 
				  '<SearchValue>' . $this->rate . '</SearchValue>' .
				'</Filter>' .
			  '</Filters>' . 
			  '<APIKEY>' . get_api_key() . '</APIKEY>' . 
			'</DiscountGet>';

		return $query;
	}
	
	public static function WC_all_as_name_rate() {
		global $wpdb;
		$prefix = $wpdb->prefix;
		$results = $wpdb->get_results( "SELECT {$prefix}posts.ID as id, {$prefix}posts.post_title as name, {$prefix}postmeta.meta_value as rate FROM {$prefix}posts, {$prefix}postmeta WHERE {$prefix}posts.post_type = 'shop_coupon' AND {$prefix}posts.post_status = 'publish' AND {$prefix}postmeta.meta_key = 'coupon_amount' AND {$prefix}postmeta.post_id = {$prefix}posts.ID", ARRAY_A );
		
		$coupons = array();
		
		//initialise our "hashtable"
		foreach ($results as $number => $coupon) {
			$coupons[ $coupon['name'] ] = array();
		}
			
		foreach ($results as $number => $coupon) {
			if (!in_array( $coupon['rate'], $coupons[ $coupon['name'] ], true))
				array_push($coupons[ $coupon['name'] ], $coupon['rate']);
		}
		
		return $coupons;
	}
	
	public static function init_compound_percent_coupon($coupons_ids) {
		$used_coupons = array();
		$all = self::WC_all();
		foreach ($all as $coupon) {
			if (in_array($coupon->WC_ID, $coupons_ids)) {
				array_push($used_coupons, $coupon);
			}
		}
		
		$names = array();
		$final_rate = 0;
		foreach ($used_coupons as $coupon) {
			array_push($names, $coupon->name);
			$final_rate += $coupon->rate;
		}
		
		if ($final_rate > 100) 
			$final_rate = 100;
		
		sort($names);
		
		$compound_coupon = new Coupon;
		$compound_coupon->description = "compound_coupon";
		$compound_coupon->name = "comp-" . round(microtime(true) * 1000);
		$compound_coupon->rate = $final_rate;
		$compound_coupon->type = 'percent';
		
		foreach($names as $name) {
			$compound_coupon->description .= '_';
			$compound_coupon->description .= $name;
		}
	
		return $compound_coupon;
	}
	
	public static function MV_to_WC() {
		
		$current_coupons = self::WC_all_as_name_rate();
		
		$xml = send_xml(self::$MV_URL_discount_get, 
			self::XML_get_all_from_discounts());	
		
		$all = 0;
		$added = 0;
		
		//because we don't want to try to add the coupons received from MV to MV again
		
		foreach ($xml['mvDiscounts']['mvDiscount'] as $key => $discount) {
			$all = $all + 1;
			
			//check if coupon already in WC, if yes then skip			
			if ((array_key_exists($discount['DiscountName'], $current_coupons)) and 
					(in_array( $discount['DiscountValue'], $current_coupons[ $discount['DiscountName'] ], true)))
				continue;
			
			
			$coupon = new Coupon;
			$coupon->name = $discount['DiscountName'];
			if ($discount['DiscountDescription'] == array()) 
				$discount['DiscountDescription'] = "";
			$coupon->description = $discount['DiscountDescription'];
			
			$coupon->rate = $discount['DiscountValue'];
			$coupon->MV_ID = $discount['DiscountID'];
			$coupon->type = 'percent';
			
			
			$result = $coupon->WC_save();
			if (($result != -1) and ($result != -2)) {
				$added = $added + 1;	
			}
		}
		
		$result = "Added " . $added . " percent coupons out of " . $all . " percent discounts found in MV.";
		if ($added < $all) 
			$result = $result . " All other either were already in WooCommerce or overlap with existing names.";	
				
		return $result;
	}
	
	public function WC_save() {
		// Initialize the page ID to -1. This indicates no action has been taken.
		$post_id = -1;

		// Setup the author, slug, and title for the post
		$author_id = get_current_user_id();

		// If the page doesn't already exist, then create it
		if( null == get_page_by_title( $title ) ) {

			// Set the post ID so that we know the post was created successfully
			$post_id = wp_insert_post(					
				array (
					'post_author' => 2,
				    'post_content' => '',
				    'post_content_filtered' => '',
				    'post_title' => $this->name,
				    'post_excerpt' => $this->description,
				    'post_status' => 'publish',
				    'post_type' => 'shop_coupon',
				    'comment_status' => 'closed',
					'user_ID' => 2,
					'excerpt' =>  $this->description,
					'discount_type' => $this->type,
					'coupon_amount' => $this->rate,
					'filter' => 'db',
				)
			);
			
			$this->WC_ID = $post_id;
			
			update_post_meta($post_id, 'discount_type', $this->type);
			update_post_meta($post_id, 'coupon_amount', $this->rate);
			update_post_meta($post_id, '_edit_lock', '');	
			update_post_meta($post_id, '_edit_last', '');
			update_post_meta($post_id, 'individual_use', 'no');
			update_post_meta($post_id, 'product_ids', '');	
			update_post_meta($post_id, 'exclude_product_ids', '');	
			update_post_meta($post_id, 'usage_limit', 0);
			update_post_meta($post_id, 'usage_limit_per_user', 0);
			update_post_meta($post_id, 'limit_usage_to_x_items', 0);
			update_post_meta($post_id, 'usage_count', 0);
			update_post_meta($post_id, 'date_expires', '');	
			update_post_meta($post_id, 'expiry_date', '');	
			update_post_meta($post_id, 'free_shipping', '');
			update_post_meta($post_id, 'product_categories', '');
			update_post_meta($post_id, 'exclude_product_categories', '');
			update_post_meta($post_id, 'exclude_sale_items', 'no');
			update_post_meta($post_id, 'minimum_amount', ''); 
			update_post_meta($post_id, 'maximum_amount', '');
			update_post_meta($post_id, 'customer_email', '');
			

		} else {
				// -2 to indicate that the page with the title already exists
				$post_id = -2;
		} // end if
		return $post_id;
	} 
	
	
	private function XML_get_obj_with_same_name_if_present_product() {
		$query = 
		'<ProductGet xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">' .
		  '<ReturnTopNRecords xmlns="http://schemas.datacontract.org/2004/07/MegaventoryAPI.ServiceModel">10</ReturnTopNRecords>' .
		  '<APIKEY>' . get_api_key() . '</APIKEY> ' .
		  '<ProductSKU>' . $this->name . '</ProductSKU>' .
		  '<includeReferencedObjects>false</includeReferencedObjects>' .
		'</ProductGet>';
		return $query; 
	}
	
	
	public function MV_load_corresponding_obj_if_present() {
		if ($this->type == 'percent') {
			$xml = send_xml(self::$MV_URL_discount_get,
				self::XML_get_obj_with_same_name_rate_pair_present());
			
			if (empty($xml['mvDiscounts'])) {				
				return false;
			} else {  			
				$this->MV_ID = $xml['mvDiscounts']['mvDiscount']['DiscountID'];
				$this->description = $xml['mvDiscounts']['mvDiscount']['DiscountDescription'];
				return true;
			}			
		} else {
			
			$xml = send_xml(self::$MV_URL_product_get,
				self::XML_get_obj_with_same_name_if_present_product());
				
			if (empty($xml['mvProducts']))
				return false;
			else {
				$this->MV_ID = $xml['mvProducts']['mvProduct']['ProductID'];
				$this->name = $xml['mvProducts']['mvProduct']['ProductSKU'];
				return true;
			}
		}
	}

	private function XML_add_to_mv_fixed() {
		if (!preg_match('/[a-zA-Z]/', $this->description)) 
			$this->description = "discount";
		
		$query = 
			'<ProductUpdate xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">' .
			  '<APIKEY>' . get_api_key() . '</APIKEY>' .
				'<mvProduct>' .
				  '<ProductID>0</ProductID>' .
				  '<ProductType>BuyFromSupplier</ProductType>' .
				  '<ProductSKU>' . $this->name . '</ProductSKU>' .
				  '<ProductDescription>' . $this->description . '</ProductDescription>' .
				  '<ProductSellingPrice>' . $this->rate . '</ProductSellingPrice>' .
				  '<ProductPurchasePrice>0</ProductPurchasePrice>' .
				'</mvProduct>' .
			  '<mvRecordAction>Insert</mvRecordAction>' .
			'</ProductUpdate>';
		
		return $query;
	}
	
	private function XML_get_all_from_discounts() {
		$query = 
			'<DiscountGet xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">' .
			  '<APIKEY>' . get_api_key() . '</APIKEY>' .
			'</DiscountGet>';
		return $query;
	}
	
	private function XML_create_discounts_category() {
		$query = 
			'<ProductCategoryUpdate xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">' .
			  '<APIKEY>' . get_api_key() . '</APIKEY>' .
			  '<mvProductCategory>' .
				'<ProductCategoryID>0</ProductCategoryID>' . //dummy
				'<ProductCategoryName>Discounts</ProductCategoryName>' .
				'<ProductCategoryDescription>Category with all fixed price discounts.</ProductCategoryDescription>' .
			  '</mvProductCategory>' .
			  '<mvRecordAction>Insert</mvRecordAction>' .
			'</ProductCategoryUpdate>' ;
		return $query;
	}
	
	private function XML_is_name_present() {
		$query = '<DiscountGet xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">' . 
			  '<Filters xmlns="http://schemas.datacontract.org/2004/07/MegaventoryAPI.ServiceModel">' . 
				'<Filter>' . 
				  '<AndOr>And</AndOr>' . 
				  '<FieldName>DiscountName</FieldName>' . 
				  '<SearchOperator>Equals</SearchOperator>' . 
				  '<SearchValue>' . $this->name . '</SearchValue>' . 
				'</Filter>' . 
			  '</Filters>' . 
			  '<APIKEY>' . get_api_key() . '</APIKEY>' . 
			'</DiscountGet>';

		return $query;
	}

	private function XML_get_obj_with_same_name_rate_pair_present() {
		$query = '<DiscountGet xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">' . 
			  '<Filters xmlns="http://schemas.datacontract.org/2004/07/MegaventoryAPI.ServiceModel">' . 
				'<Filter>' . 
				  '<AndOr>And</AndOr>' . 
				  '<FieldName>DiscountName</FieldName>' . 
				  '<SearchOperator>Equals</SearchOperator>' . 
				  '<SearchValue>' . $this->name . '</SearchValue>' . 
				'</Filter>' . 
				'<Filter>' .
				  '<AndOr>And</AndOr>' .
				  '<FieldName>DiscountValue</FieldName>' .
				  '<SearchOperator>Equals</SearchOperator>' . 
				  '<SearchValue>' . $this->rate . '</SearchValue>' .
				'</Filter>' .
			  '</Filters>' . 
			  '<APIKEY>' . get_api_key() . '</APIKEY>' . 
			'</DiscountGet>';

		return $query;
	}
	
	public function MV_save() {
		
		
		if ($this->type == 'percent') {
			$result = send_xml(self::$MV_URL_discount_update, self::XML_add_to_mv_percent());
		} else {
			$result = send_xml(self::$MV_URL_product_update, self::XML_add_to_mv_fixed());
		}   
		wp_mail("bmodelski@megaventory.com", "inside mv save result", var_export($result, true)); 
		wp_mail("bmodelski@megaventory.com", "inside mv save query", var_export(self::XML_add_to_mv_percent(), true)); 
		
		if ($result['ResponseStatus']['ErrorCode'] == '0')
			return True; //if <ErrorCode... was found, then save failed. 
		else
			return False;
	}
	
	private function XML_add_to_mv_percent() {
		$query = 
		'<DiscountUpdate xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types"> ' .
		  '<APIKEY>' . get_api_key() . '</APIKEY> ' .
		  '<mvDiscount> ' .
			'<DiscountID>124</DiscountID> ' . //dummy data, irrelevant
			'<DiscountName>' . $this->name . '</DiscountName> ' .
			'<DiscountDescription>' . $this->description . '</DiscountDescription> ' .
			'<DiscountValue>' . $this->rate . '</DiscountValue> ' .
		  '</mvDiscount> ' .
		  '<mvRecordAction>Insert</mvRecordAction>' .
		'</DiscountUpdate> ';
		return $query;
	}
	
	public function MV_update() {			
		if ($this->type == 'percent') {
			$result = send_xml(self::$MV_URL_discount_update, self::XML_update_in_mv_percent());
		} else {
			$result = send_xml(self::$MV_URL_product_update, self::XML_update_in_mv_fixed());
		}
	}
	
	private function XML_update_in_mv_percent() {
		$query = 
		'<DiscountUpdate xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types"> ' .
		  '<APIKEY>' . get_api_key() . '</APIKEY> ' .
		  '<mvDiscount> ' .
			'<DiscountID>' . $this->MV_ID . '</DiscountID> ' . //dummy data, irrelevant
			'<DiscountName>' . $this->name . '</DiscountName> ' .
			'<DiscountDescription>' . $this->description . '</DiscountDescription> ' .
			'<DiscountValue>' . $this->rate . '</DiscountValue> ' .
		  '</mvDiscount> ' .
		  '<mvRecordAction>Update</mvRecordAction>' .
		'</DiscountUpdate> ';
		return $query;
	}
	
	private function XML_update_in_mv_fixed() {
		$query = 
		'<ProductUpdate xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">' .
			  '<APIKEY>' . get_api_key() . '</APIKEY>' .
				'<mvProduct>' .
				  '<ProductID>' . $this->MV_ID . '</ProductID>' .
				  '<ProductType>BuyFromSupplier</ProductType>' .
				  '<ProductSKU>' . $this->name . '</ProductSKU>' .
				  '<ProductDescription>' . $this->description . '</ProductDescription>' .
				  '<ProductSellingPrice>' . $this->rate . '</ProductSellingPrice>' .
				  '<ProductPurchasePrice>0</ProductPurchasePrice>' .
				'</mvProduct>' .
			  '<mvRecordAction>Update</mvRecordAction>' .
			'</ProductUpdate>';
		return $query;
	}
	
	
}

?>
