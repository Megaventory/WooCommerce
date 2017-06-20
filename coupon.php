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
		$results = $wpdb->get_results( 'SELECT wp_t3bdty_posts.ID as id, wp_t3bdty_posts.post_title as name, wp_t3bdty_postmeta.meta_value as rate FROM wp_t3bdty_posts, wp_t3bdty_postmeta WHERE wp_t3bdty_posts.post_type = \'shop_coupon\' AND wp_t3bdty_posts.post_status = \'publish\' AND wp_t3bdty_postmeta.meta_key = \'coupon_amount\' AND wp_t3bdty_postmeta.post_id = wp_t3bdty_posts.ID', ARRAY_A );
		
		$coupons = array("foo" => "bar");
		
		foreach ($results as $number => $coupon) {
			wp_mail("bmodelski@megaventory.com", "WC_all", var_export($coupon, true));
			
			$name = $coupon['name'];
			$coupons[ $name ] = new Set;
		}
			
		foreach ($results as $number => $coupon) {
			$coupons[ $coupon['name'] ].add( $coupon['rate'] );
		}
		
		wp_mail("bmodelski@megaventory.com", "WC_all", var_export($coupons, true));
	}
	
	public static function MV_all() {
		self::WC_all();
		
		$xml = send_xml(self::$MV_URL_discount_get,
			self::XML_get_all_from_discounts());	
		
		$all = 0;
		$added = 0;
		
		
		foreach ($xml['mvDiscounts']['mvDiscount'] as $key => $discount) {
			$coupon = new Coupon;
			$coupon->name = $discount['DiscountName'];
			$coupon->description = $discount['DiscountDescription'];
			$coupon->rate = $discount['DiscountValue'];
			$coupon->MV_ID = $discount['DiscountID'];
			$coupon->type = 'percent';
			
			$all = $all + 1;
			
			$result = $coupon->WC_save();
			if (($result != -1) and ($result != -2)) 
				$added = $added + 1;
			
			
		}
		
		wp_mail("bmodelski@megaventory.com", "WC_SAVE", $added . ' out of ' . $all);
		
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
			
			add_post_meta($post_id, 'discount_type', $this->type);
			add_post_meta($post_id, 'coupon_amount', $this->rate);
			add_post_meta($post_id, '_edit_lock', '');	
			add_post_meta($post_id, '_edit_last', '');
			add_post_meta($post_id, 'individual_use', 'no');
			add_post_meta($post_id, 'product_ids', '');	
			add_post_meta($post_id, 'exclude_product_ids', '');	
			add_post_meta($post_id, 'usage_limit', 0);
			add_post_meta($post_id, 'usage_limit_per_user', 0);
			add_post_meta($post_id, 'limit_usage_to_x_items', 0);
			add_post_meta($post_id, 'usage_count', 0);
			add_post_meta($post_id, 'date_expires', '');	
			add_post_meta($post_id, 'expiry_date', '');	
			add_post_meta($post_id, 'free_shipping', '');
			add_post_meta($post_id, 'product_categories', '');
			add_post_meta($post_id, 'exclude_product_categories', '');
			add_post_meta($post_id, 'exclude_sale_items', 'no');
			add_post_meta($post_id, 'minimum_amount', ''); 
			add_post_meta($post_id, 'maximum_amount', '');
			add_post_meta($post_id, 'customer_email', '');
			

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

			if (empty($xml['mvDiscounts']))
				return false;
			else { 
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
			send_xml(self::$MV_URL_discount_update, self::XML_add_to_mv_percent());
		} else {
			$xml = send_xml(self::$MV_URL_product_update, self::XML_add_to_mv_fixed());
		}   
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
		//wp_mail("bmodelski@megaventory.com", "coupon", "inside MV_add");
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