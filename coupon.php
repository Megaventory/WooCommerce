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
	
	public static function MV_initialise() {
		$response = send_xml(self::$MV_URL_category_update,
			self::XML_create_discounts_category());
			
		
		//What if product category existed in the past and can't be created, but needs to be undeleted?
		if ($response['mvProductCategory']['ResponseStatus']['ErrorCode'] == 500) { 
			//AFAIK api does not allow that atm:
			//	to undelete I need an ID, but ProductCategoryGet does not find deleted categories. 
		}
		/*wp_mail("bmodelski@megaventory.com", "product response", var_export($response, true));
		wp_mail("bmodelski@megaventory.com", "product response", var_export(self::XML_create_discounts_category(), true));
		wp_mail("bmodelski@megaventory.com", "product response", var_export(self::$MV_URL_category_update, true));*/
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
	
	private static function XML_create_discounts_category() {
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
	
	public function MV_add() {
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
			wp_mail("bmodelski@megaventory.com", "coupon", "inside MV_add");
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