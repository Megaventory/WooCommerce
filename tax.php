<?php

require_once("api.php");
require_once("error.php");

class Tax {
	public static $table_name = woocommerce_tax_rates;
	
	public $MV_ID;
	public $WC_ID;
	
	public $name;
	public $description;
	public $rate;
	
	private static $tax_get_call = "TaxGet";
	private static $tax_update_call = "TaxUpdate";
	
	public $errors;
	
	function __construct() {
		$this->errors = new MVWC_Errors();
	}
	
	public function errors() {
		return $this->errors;
	}
	
	public function log_error($problem, $full_msg, $code, $type = "error") {
		$args = array
		(
			'entity_id' => array('wc' => $this->WC_ID, 'mv' => $this->MV_ID), 
			'entity_name' => $this->name,
			'problem' => $problem,
			'full_msg' => $full_msg,
			'error_code' => $code,
			'type' => $type
		);
		$this->errors->log_error($args);
	}
	
	public static function wc_all() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . self::$table_name;
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
		$tax->MV_ID = $wc_tax['mv_id'];
		
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
	
	public static function mv_find_by_name_and_rate($name, $rate) {
		$jsonurl = create_json_url_filters(self::$tax_get_call, array(array("TaxName", "Equals", htmlentities($name)), array("TaxValue", "Equals", htmlentities($rate))));
		wp_mail("mpanasiuk@megaventory.com", "URL", $jsonurl);
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
			$tax = self::mv_find_by_name_and_rate($this->name, $this->rate);
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
		
		echo "<br>-------------XML SENT for " . $this->name . "------<br>";
		var_dump($xml);
		echo "<br>-------------------------------------<br>";
		
		if (count($data['mvTax']) <= 0) {
			//log err
			$this->log_error("Tax not saved to MV", $data['ResponseStatus']['Message'], $data['ResponseStatus']['ErrorCode']);
			return null;
		} else {
			//ensure correct id
			$new_id = $data['mvTax']['TaxID'];
			if ($new_id != $this->MV_ID) {
				global $wpdb;
				$table_name = $wpdb->prefix . self::$table_name;
				$sql = "UPDATE $table_name SET mv_id=".(string)$new_id." WHERE tax_rate_id=".(string)$this->WC_ID.";"; //WOW M9
				$wpdb->query($sql);
			}
		}
		
		return $data;
	}
	
	public function wc_save() {
		wp_mail("mpanasiuk@megaventory.com", "step 800", $wpdb->last_query . " " . var_export($wpdb->last_result, true));
		wp_mail("mpanasiuk@megaventory.com", "step 2", "Heregoes");
		
		foreach (self::wc_all() as $wc_tax) {
			if ($wc_tax->equals($this)) {
				$this->WC_ID = $wc_tax->WC_ID;
				break;
			}
		}
		
		global $wpdb;
		$create_new = $this->WC_ID == null;
		
		$table_name = $wpdb->prefix . self::$table_name;
		$sql;
		if ($create_new) {
			$sql = "INSERT INTO $table_name(tax_rate, tax_rate_name, mv_id) VALUES(";
			$sql .= (string)$this->rate.", ";
			$sql .= "'".$this->name."', ";
			$sql .= ($this->MV_ID ? (string)$this->MV_ID : "NULL");
			$sql .= ");";
			
			$wpdb->query($sql);
		} else {
			$sql = "UPDATE $table_name SET tax_rate=".(string)$this->rate.", tax_rate_name='".$this->name."'";
			$sql .= ($this->MV_ID ? ", mv_id=".(string)$this->MV_ID." " : "");
			$sql .= " WHERE tax_rate_id=".(string)$this->WC_ID.";";
			
			$wpdb->query($sql);
		}
		
		echo $wpdb->last_query;
		echo $wpdb->last_result;
		wp_mail("mpanasiuk@megaventory.com", "step 3", $wpdb->last_query . " " . var_export($wpdb->last_result, true));
	}
	
	public function wc_delete() {
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		$sql = "DELETE FROM $table_name WHERE tax_rate_id=".(string)$this->WC_ID.";";
		
		return $wpdb->query($sql);
	}
	
	public function equals($tax) {
		return $this->name == $tax->name and (float)$this->rate == (float)$tax->rate;
	}
}




?>
