<?php

require_once("api.php");
require_once("address.php");
require_once("error.php");

/* This class works a model for coupons (WC) / discounts (MV). */
class Coupon {
	public $MV_ID;
	public $WC_ID;
	public $name = "";
	public $description = "";
	public $rate = 0.0;
	public $type; /* either 'percent' or 'fixed' */

	/* API calls */
	private static $MV_URL_discount_get = "DiscountGet";
	private static $MV_URL_discount_update = "DiscountUpdate";
	private static $MV_URL_category_update = "ProductCategoryUpdate";
	private static $MV_URL_category_get = "ProductCategoryGet";
	private static $MV_URL_category_undelete = "ProductCategoryUndelete";
	private static $MV_URL_product_get = "ProductGet";
	private static $MV_URL_product_update = "ProductUpdate";

	function __construct() {

		$this->errors = new MVWC_Errors();
		$this->successes = new MVWC_Successes();

	}
	public function errors() {

		return $this->errors;
	}

	public function successes(){

		return $this->successes;
	}
	public function log_error($problem, $full_msg, $code, $type = "error") {

		$args = array
		(
			'entity_id' => array('wc' => $this->WC_ID, 'mv' => $this->MV_ID),
			'entity_name' => ($this->name == null) ? $this->description : $this->name,
			'problem' => $problem,
			'full_msg' => $full_msg,
			'error_code' => $code,
			'type' => $type
		);
		$this->errors->log_error($args);

	}
	public function log_success($transaction_status,$full_msg,$code){

		$args = array
		(
			'entity_id' => array('wc' => $this->WC_ID, 'mv' => $this->MV_ID),
			'entity_type'=>"product",
			'entity_name' => $this->name,
			'transaction_status' => $transaction_status,
			'full_msg' => $full_msg,
			'success_code' => $code
		);

		$this->successes->log_success($args);
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
		
		$coupon->WC_ID = $buffer['id'];
		$coupon->name = $buffer['name'];
		$coupon->rate = $buffer['rate'];
		$coupon->description = $buffer['description'];
		$coupon->type = $buffer['discount_type'];
		
		return $coupon;
	}
	
	public static function WC_find_by_name($name) {

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
					AND {$prefix}posts.post_title = '{$name}'
					AND {$prefix}posts.post_status = 'publish' 
					AND meta1.meta_key = 'coupon_amount' 
					AND meta1.post_id = {$prefix}posts.ID
					AND meta2.meta_key = 'discount_type' 
					AND	meta2.post_id = meta1.post_id"
				, ARRAY_A );
		
		if (count($results) == 0) return null;
		$buffer = $results[0];
		
		$coupon = new Coupon;
		
		$coupon->WC_ID = $buffer['id'];
		$coupon->name = $buffer['name'];
		$coupon->rate = $buffer['rate'];
		$coupon->description = $buffer['description'];
		$coupon->type = $buffer['discount_type'];
		
		return $coupon;
	}
	
	public function get_excluded_products($by_ids = false) {

		$ids = get_post_meta($this->WC_ID, 'exclude_product_ids', true); 
		if (!$ids) return array();
		
		$temp = array();
		foreach (explode(',', $ids) as $id) {
			$id = (int)$id;
			$to_push = ($by_ids ? $id : Product::wc_find($id));
			array_push($temp, $to_push);
		}
		
		return $temp;
	}	
	
	public function get_included_products($by_ids = false) {

		$ids = get_post_meta($this->WC_ID, 'product_ids', true);  //true returns string of foreign keys separated by comma
		if (!$ids) return array();
		
		$temp = array();
		foreach (explode(',', $ids) as $id) {
			$id = (int)$id;
			$to_push = ($by_ids ? $id : Product::wc_find($id));
			array_push($temp, $to_push);
		}
		
		return $temp;
	}
	
	public function get_included_products_categories($by_ids = false) {
		$ids = get_post_meta($this->WC_ID, 'product_categories', true);
		
		
		return $ids; 
	}	
	
	public function get_excluded_products_categories($by_ids = false) {
		$ids = get_post_meta($this->WC_ID, 'exclude_product_categories', true);
		
		return $ids; 
	}
	
	public function applies_to_sales() {

		return !(get_post_meta($this->WC_ID, 'exclude_sale_items', true) == 'yes');

	}
	
	public function MV_load_percent_by_description() {

		$url = create_json_url(self::$MV_URL_discount_get);

		$json_request=json_load_percent_by_description();

		$data= send_json($url,$json_request);

		if (empty($data['mvDiscounts'])) {				
			return false;
		} else {  			
			$this->MV_ID = $data['mvDiscounts']['mvDiscount']['DiscountID'];
			$this->name = $data['mvDiscounts']['mvDiscount']['DiscountName'];
			$this->rate = $data['mvDiscounts']['mvDiscount']['DiscountValue'];
			$this->type = 'percent';

			return true;
		}			
	}
	
	public static function MV_get_or_create_compound_percent_coupon($ids) {
		$coupon = Coupon::init_compound_percent_coupon($ids);
	
		if (!$coupon->MV_load_percent_by_description()) {
			$coupon->MV_save();
		}
		
		return $coupon;
	}
	private function json_load_percent_by_description(){

		$discountObject=new \stdClass();
		$discountObjectFilter=new \stdClass();
		$discountObjectFilter=array(
		$AndOr=>"And",
		$FieldName=>"DiscountDescription",
		$SearchOperator=>"Equals",
		$SearchValue=>$this->description
		);
		$discountObject->Filters=$discountObjectFilter;

		$json_object=wrap_json($discountObject);
		$json_object=json_encode($json_object);

		return $json_object;

	}
	
	public static function WC_all_as_name_rate() {

		global $wpdb;
		$prefix = $wpdb->prefix;
		$results = $wpdb->get_results( "SELECT {$prefix}posts.ID as id, {$prefix}posts.post_title as name, {$prefix}postmeta.meta_value as rate FROM {$prefix}posts, {$prefix}postmeta WHERE {$prefix}posts.post_type = 'shop_coupon' AND {$prefix}posts.post_status = 'publish' AND {$prefix}postmeta.meta_key = 'coupon_amount' AND {$prefix}postmeta.post_id = {$prefix}posts.ID", ARRAY_A );
		
		$coupons = array();
		
		//initialize our "hashtable"
		foreach ($results as $number => $coupon) {
			$coupons[ $coupon['name'] ] = array();
		}
			
		foreach ($results as $number => $coupon) {
			if (!in_array( $coupon['rate'], $coupons[ $coupon['name'] ], true))
				array_push($coupons[ $coupon['name'] ], $coupon['rate']);
		}
		
		return $coupons;
	}
	
	private static function init_compound_percent_coupon($coupons_ids) {

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
	
	public function MV_to_WC() {

		$current_coupons = self::WC_all_as_name_rate();

		$url = create_json_url(self::$MV_URL_discount_get);

		$json_request=$this->json_get_all_from_discount();

		$data= send_json($url,$json_request);
		$all = 0;
		$added = 0;
		
		/* because we don't want to try to add the coupons received from MV to MV again */
		foreach ($data['mvDiscounts']['mvDiscount'] as $key => $discount) {
			$all = $all + 1;
			
			/* check if coupon already in WC, if yes then skip	 */		
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
		/* Initialize the page ID to -1. This indicates no action has been taken. */
		$post_id = -1;

		/* Setup the author, slug, and title for the post */
		$author_id = get_current_user_id();

		 /* If the page doesn't already exist, then create it */
		 
		if( null == get_page_by_title( $title ) ) {

			/* Set the post ID so that we know the post was created successfully */

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
				/* -2 to indicate that the page with the title already exists */
				$post_id = -2;
		} // end if
		return $post_id;
	} 
	
	private function json_get_obj_with_same_name_if_present_product(){

		$productObject=new \stdClass();

		$productObject->ProductSKU=$this->name;
		$productObject->includeReferenceObjects=false;
		$productObject=wrap_json($productObject);
		$jsonObject=json_encode($productObject);

		return $jsonObject;
		
	}
	
	public function MV_load_corresponding_obj_if_present() {
		
		if ($this->type == 'percent') {
			$url = create_json_url(self::$MV_URL_discount_get);
			$json_request=$this->json_get_obj_with_the_same_name_rate_pair_present();
			$data= send_json($url,$json_request);
			
			
			if (empty($data['mvDiscounts'])) {				
				return false;
			} else {  			
				$this->MV_ID = $data['mvDiscounts']['mvDiscount']['DiscountID'];
				$this->description = $data['mvDiscounts']['mvDiscount']['DiscountDescription'];
				return true;
			}			
		} 
		else {
			$url = create_json_url(self::$MV_URL_product_get);
			$json_request=$this->json_get_obj_with_same_name_if_present_product();
			$data= send_json($url,$json_request);

				
			if (empty($data['mvProducts']))

				return false;

			else {
				$this->MV_ID = $data['mvProducts']['mvProduct']['ProductID'];
				$this->name = $data['mvProducts']['mvProduct']['ProductSKU'];

				return true;
			}
		}
	}
	private function json_add_to_mv_fixed(){

		if (!preg_match('/[a-zA-Z]/', $this->description)) 
			$this->description = "discount";

		$productInsertObject = new \stdClass();
		$productObject=new \stdClass();

		$productObject->ProductID=0;
		$productObject->ProductType="BuyFromSupplier";
		$productObject->ProductSKU=$this->name;
		$productObject->ProductDescription= $this->description;
		$productObject->ProductSellingPrice=$this->rate;
		$productObject->productPurchasePrice=0;

		$productInsertObject->mvProduct=$productObject;
		$productInsertObject->mvRecordAction="Insert";

		$jsonObject=wrap_json($productInsertObject);
		$jsonObject=json_encode($jsonObject);

		return $jsonObject;
	
	}

	private function json_get_all_from_discount(){

		$discountObject=new \stdClass();
		$jsonObject=wrap_json($discountObject);
		$jsonObject=json_encode($jsonObject);

		return $jsonObject;
	}
	
	private function json_create_discount_category(){

		$discountObject=new \stdClass();
		$discountFinalObject=new \stdClass();

		$discountObject->ProductCategoryID=0;
		$discountObject->ProductCategoryName="Discounts";
		$discountObject->ProductCategoryDescription="Category with all fixed price discounts";

		$discountFinalObject->mvProductCategory=$discountObject;
		$discountFinalObject->mvRecordAction="Insert";

		$discountFinalObject=wrap_json($discountFinalObject);
		$jsonObject=json_encode($discountFinalObject);
		
		return $jsonObject;
	}
	
	private function json_is_name_present(){

		$discountObjectFields=new \stdClass();
		$discountObject=new \stdClass();
		$discountObjectFields=array(
		$AndOr=>"And",
		$FieldNAme=>"DiscountName",
		$SearchOperator=>"Equals",
		$SearchValue=>$this->name,
		);
		$discountObject->Filters=$discountObjectFields;
		$discountObject=wrap_json($discountObject);
		$jsonObject=json_encode($discountObject);

		return $jsonObject;

	}
	
	private function json_get_obj_with_the_same_name_rate_pair_present(){

		$discountObjectFields=new \stdClass();
		$discountObject=new \stdClass();

		$discountObjectFields->AndOr="And";
		$discountObjectFields->FieldName="DiscountName";
		$discountObjectFields->SearchOperator="Equals";
		$discountObjectFields->SearchValue=$this->name;
		$discountObjectFields=array(
			$AndOr=>"And",
			$FieldName=>"DiscountValue",
			$SearchOperator=>"Equals",
			$SearchValue=>$this->rate,
		);
		$discountObject->Filters=$discountObjectFields;
		$discountObject=wrap_json($discountObject);

		$jsonObject=json_encode($discountObject);
		
		return $jsonObject;

	}
	
	public function MV_save() {
		
		if ($this->type == 'percent') {
			$json_url=create_json_url(self::$MV_URL_discount_update);
			$result = send_json($json_url,self::json_add_to_mv_percent());
		} else {
			$json_url=create_json_url(self::$MV_URL_product_update);
			$result = send_json($json_url,self::json_add_to_mv_fixed());
		}   
		
		
		if ($result['ResponseStatus']['ErrorCode'] == '0'){
			$this->MV_ID = $result['mvProduct']['ProductID'];
			$this->log_success("Created","Coupon have been successfully created in MV",1);
			return true;
		}
		/* if <ErrorCode... was found, then save failed. */ 
		else{
			$internal_error_code= " [" .$result['InternalErrorCode'] . "]";
			$this->log_error('Coupon not saved to MV'.$internal_error_code, $result['ResponseStatus']['Message'], -1);
			return false;

		}
			
	}
	private function json_add_to_mv_percent(){

		$discountObjectFields=new \stdClass();
		$discountObject=new \stdClass();

		$discountObjectFields->DiscountID="";
		$discountObjectFields->DiscountName=$this->name;
		$discountObjectFields->DiscountDescription=$this->description;
		$discountObjectFields->DiscountValue=(int)$this->rate;

		$discountObject->mvDiscount=$discountObjectFields;
		
		$discountObject->mvRecordAction="Insert";
		$discountObject->mvInsertUpdateDeleteSourceApplication="woocommerce";
		$discountObject=wrap_json($discountObject);
		$jsonObject=json_encode($discountObject);

		return $jsonObject;

	}
	

	public function MV_update() {

		if ($this->type == 'percent') {
			$json_url=create_json_url(self::$MV_URL_discount_update);
			$result = send_json($json_url, self::json_update_in_mv_percent());
		} else {
			$json_url=create_json_url(self::$MV_URL_discount_update);
			$result = send_json($json_url, self::json_update_in_mv_fixed());
		}
	}
	private function json_update_in_mv_percent(){
		
		$discountObjectFields=new \stdClass();
		$discountObject=new \stdClass();

		$discountObjectFields->DiscountID=$this->MV_ID;
		$discountObjectFields->DiscountName=$this->name;
		$discountObjectFields->DiscountDescription=$this->description;
		$discountObjectFields->DiscountValue=$this->rate;

		$discountObject->mvDiscount=$discountObjectFields;

		$discountObject=wrap_json($discountObject);
		$jsonObject=json_encode($discountObject);

		return $jsonObject;

	}
	
	private function json_update_in_mv_fixed(){

		$productObjectFields=new \stdClass();
		$productObject=new \stdClass();

		$productObjectFields->ProductID=$this->MV_ID;
		$productObjectFields->ProductType="BuyFromSupplier";
		$productObjectFields->ProductSKU=$this->name;
		$productObjectFields->ProductDescription=$this->description;
		$productObjectFields->ProductSellingPrice=$this->rate;
		$productObjectFields->ProductPurchasePrice=0;

		$productObject->mvProduct=$productObjectFields;

		$productObject=wrap_json($productObject);
		$jsonObject=json_encode($productObject);

		return $jsonObject;
	}
}
?>