<?php

require_once("api.php");
require_once("error.php");

class Tax {
	public $MV_ID;
	public $WC_ID;
	
	public $name;
	public $description;
	public $rate;
	
	private static $tax_get_call = "TaxGet";
	private static $tax_update_call = "TaxUpdate";
	
	public static function wc_all() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . "woocommerce_tax_rates";
		$results = $wpdb->get_results("SELECT * FROM $table_name;", ARRAY_A);
		$taxes = array();
		foreach ($results as $result) {
			array_push($taxes, self::wc_convert($result));
		}
		return $taxes;
	}
	
	public static function wc_find($id) {
		global $wpdb;

		$result = $wpdb->get_row($wpdb->prepare("
				SELECT *
				FROM {$wpdb->prefix}woocommerce_tax_rates
				WHERE tax_rate_id = %d
			", $id), ARRAY_A);
			
		return self::wc_convert($result);
	}
	
	public static function wc_convert($wc_tax) {
		$tax = new Tax();
		
		$tax->WC_ID = $wc_tax['tax_rate_id'];
		//$tax->MV_ID = $wc_tax->tax_rate_mv_id;
		
		$tax->rate = (float)$wc_tax['tax_rate'];
		$tax->name = $wc_tax['tax_rate_name'];
				
		return $tax;
	}
	
	public static function mv_all() {
		$jsonurl = create_json_url(self::$tax_get_call);
		$jsontax = file_get_contents($jsonurl);
		$jsontax = json_decode($jsontax, true);
		
		// interpret json into Product class
		$taxes = array();
		foreach ($jsontax['mvTaxes'] as $tax) {
			$tax = self::mv_convert($tax, $categories);
			array_push($taxes, $tax);
		}
		
		return $taxes;
	}
	
	public static function mv_find($id) {
		$jsonurl = create_json_url_filter(self::$tax_get_call, "TaxID", "Equals", htmlentities($id));
		$jsontax = file_get_contents($jsonurl);
		$jsontax = json_decode($jsontax, true);

		if (count($jsontax['mvTaxes']) <= 0) {
			return null;
		}
		
		return self::mv_convert($jsontax['mvTaxes'][0]);
	}	
	
	public static function mv_find_by_name($name) {
		$jsonurl = create_json_url_filter(self::$tax_get_call, "TaxName", "Equals", htmlentities($name));
		$jsontax = file_get_contents($jsonurl);
		$jsontax = json_decode($jsontax, true);
		
		if (count($jsontax['mvTaxes']) <= 0) {
			return null;
		}
		
		return self::mv_convert($jsontax['mvTaxes'][0]);
	}
	
	public static function mv_convert($mv_tax) {
		$tax = new Tax();
		
		$tax->MV_ID = $mv_tax['TaxID'];
		$tax->name = $mv_tax['TaxName'];
		$tax->description = $mv_tax['TaxDescription'];
		$tax->rate = $mv_tax['TaxValue'];
		
		return $tax;
	}
	
	public function mv_save() {
		$create_new = false;
		if ($this->MV_ID == null) { //find by name first
			$tax = self::mv_find_by_name($this->name);
			if ($tax != null) {
				$this->MV_ID = $tax->MV_ID;
			} else {
				$create_new = true;
			}
		}
		$action = ($create_new ? "Insert" : "Update");
		
		$xml = '
			<mvTax>
				' . (!$create_new ? '<TaxID>'.$this->MV_ID.'</TaxID>' : '') . '
				<TaxName>'.$this->name.'</TaxName>
				<TaxDescription>'.$this->description.'</TaxDescription>
				<TaxValue>'.$this->rate.'</TaxValue>
			</mvTax>
			<mvRecordAction>' . $action . '</mvRecordAction>
			<mvInsertUpdateDeleteSourceApplication>woocommerce</mvInsertUpdateDeleteSourceApplication>
		';
		
		$url = create_xml_url(self::$tax_update_call);
		$xml = wrap_xml(self::$tax_update_call, $xml);
			
		$data = send_xml($url, $xml);
		
		echo "+++++++++++++++++++++++++++++++++++++++++++++++++<br> sending:";
		var_dump(htmlentities($xml));
		
		return $data;
	}
}




?>
