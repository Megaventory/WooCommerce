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
	
	private static $MV_URL_get_by_name = "https://apitest.megaventory.com/xml/reply/DiscountGet";
	private static $MV_URL_update = "https://apitest.megaventory.com/xml/reply/DiscountUpdate"; //Used also for adding new discounts
	
	//Check if discount with given name is in MV database.
	//1. Get all records with name $name.
	//2. Check if returned array contains anything.
	public function MV_is_name_present() {
		$xml = send_xml(self::$MV_URL_get_by_name,
			self::create_XML_is_name_present());
		return !empty($xml['mvDiscounts']);
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
	
	public function mv_add() {
		$result2 = send_xml(self::$MV_URL_update, self::create_XML_add_to_mv());
		
		wp_mail("bmodelski@megaventory.com", "URL", var_export(self::$MV_URL_update, true));
		wp_mail("bmodelski@megaventory.com", "XML query", var_export(self::create_XML_add_to_mv(), true));
		wp_mail("bmodelski@megaventory.com", "result", var_export($result2, true));
	}
	
	private function create_XML_add_to_mv() {
		$query = 
		'<DiscountUpdate xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types"> ' .
		  '<APIKEY>' . get_api_key() . '</APIKEY> ' .
		  '<mvDiscount> ' .
			'<DiscountID>124</DiscountID> ' .
			'<DiscountName>' . $this->name . '</DiscountName> ' .
			'<DiscountDescription>' . $this->description . '</DiscountDescription> ' .
			'<DiscountValue>' . $this->rate . '</DiscountValue> ' .
		  '</mvDiscount> ' .
		  '<mvRecordAction>Insert</mvRecordAction>' .
		'</DiscountUpdate> ';
		return $query;
	}
}
 

?>