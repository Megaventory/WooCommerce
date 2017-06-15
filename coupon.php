<?php

require_once("api.php");
require_once("address.php");
require_once("error.php");

//This class works a model for coupons (WC) / discounts (MV).
class Coupon {
	public $MV_ID;
	public $WC_ID;
	public $name;
	public $description;
	public $rate;
	
	public $type;
	const Percentage = 0;
	const FixedPerCart = 1;
	const FixedPerProduct = 2;
	
	
	private static $MV_URL_get_discounts_by_name = "https://apitest.megaventory.com/xml/reply/DiscountGet";
	
	//Check if discount with given name is in MV database.
	//1. Get all records with name $name.
	//2. Check if returned array contains anything.
	public static function MV_is_name_present($name) {
		$xml = send_xml(self::$MV_URL_get_discounts_by_name,
			self::create_XML_is_name_present($name));
		//wp_mail("bmodelski@megaventory.com", "coupon.php", "XML " . var_export($xml['mvDiscounts'], true));
		//wp_mail("bmodelski@megaventory.com", "coupon.php", self::$MV_URL_get_discounts_by_name . " - " . self::							create_XML_is_name_present($name));
		return !empty($xml['mvDiscounts']);
	}	
	 
	private static function create_XML_is_name_present($name) {
		$var = '<DiscountGet xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns="https://api.megaventory.com/types">' . 
			  '<Filters xmlns="http://schemas.datacontract.org/2004/07/MegaventoryAPI.ServiceModel">' . 
				'<Filter>' . 
				  '<AndOr>And</AndOr>' . 
				  '<FieldName>DiscountName</FieldName>' . 
				  '<SearchOperator>Equals</SearchOperator>' . 
				  '<SearchValue>' . $name . '</SearchValue>' . 
				'</Filter>' .  
			  '</Filters>' . 
			  '<APIKEY>' . get_api_key() . '</APIKEY>' . 
			'</DiscountGet>';		
		return $var;
	}
}
 

?>