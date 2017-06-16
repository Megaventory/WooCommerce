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
	
	public $type;
	const Percentage = 0;
	const FixedPerCart = 1;
	const FixedPerProduct = 2;
	
	private static $MV_URL_get = "https://apitest.megaventory.com/xml/reply/DiscountGet";
	private static $MV_URL_update = "https://apitest.megaventory.com/xml/reply/DiscountUpdate"; //Used also for adding new discounts
	
	private static $MV_URL_category_update = "https://apitest.megaventory.com/xml/reply/ProductCategoryUpdate";
	private static $MV_URL_category_get = "https://apitest.megaventory.com/xml/reply/ProductCategoryGet";
	private static $MV_URL_category_undelete = "https://apitest.megaventory.com/xml/reply/ProductCategoryUndelete";
	
	//Check if discount with given name is in MV database.
	//1. Get all records with name $name.
	//2. Check if returned array contains anything.
	public function MV_is_name_present() {
		$xml = send_xml(self::$MV_URL_get_by_name,
			self::create_XML_is_name_present());
		return !empty($xml['mvDiscounts']);
	}	
	
	public static function MV_initialise() {
		$response = send_xml(self::$MV_URL_category_update,
			self::create_XML_create_discounts_category());
			
		
		//What if product category existed in the past and can't be created, but needs to be undeleted?
		if ($response['mvProductCategory']['ResponseStatus']['ErrorCode'] == 500) { 
			
			
		}
		
		wp_mail("bmodelski@megaventory.com", "product response", var_export($response, true));
		wp_mail("bmodelski@megaventory.com", "product response", var_export(self::create_XML_create_discounts_category(), true));
		wp_mail("bmodelski@megaventory.com", "product response", var_export(self::$MV_URL_category_update, true));
	}
	
	public function MV_load_same_name_rate_if_present() {
		$xml = send_xml(self::$MV_URL_get,
			self::create_XML_is_name_rate_pair_present());

		if (empty($xml['mvDiscounts']))
			return false;
		else {
			$this->MV_ID = $xml['mvDiscounts']['mvDiscount']['DiscountID'];
			$this->description = $xml['mvDiscounts']['mvDiscount']['DiscountDescription'];
			return true;
		}			
	}

	
	private static function create_XML_create_discounts_category() {
		$query = 
			'<ProductCategoryUpdate xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">' .
			  '<APIKEY>e8a3711b83dc84dd@m66472</APIKEY>' .
			  '<mvProductCategory>' .
				'<ProductCategoryID>0</ProductCategoryID>' . //dummy
				'<ProductCategoryName>Discounts</ProductCategoryName>' .
				'<ProductCategoryDescription>Category with all fixed price discounts.</ProductCategoryDescription>' .
			  '</mvProductCategory>' .
			  '<mvRecordAction>Insert</mvRecordAction>' .
			'</ProductCategoryUpdate>' ;
		return $query;
	}
	
	private function create_XML_is_name_present() {
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

	private function create_XML_is_name_rate_pair_present() {
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
	
	public function MV_add() {
		send_xml(self::$MV_URL_update, self::create_XML_add_to_mv());
	}
	
	private function create_XML_add_to_mv() {
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
		$result = send_xml(self::$MV_URL_update, self::create_XML_update_in_mv());
	}
	
	private function create_XML_update_in_mv() {
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
	
	
}

?>