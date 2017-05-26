<?php

// This class works as a model for a client
// clients are only transfered from wc to mv
class Client {
	public $MV_ID;
	public $WC_ID;
	public $username;
	public $contact_name;
	public $billing_address;
	public $shipping_address;
	public $shipping_address2;
	public $phone;
	public $phone2;
	public $tax_ID;
	public $email;
	public $company;
	public $type;

	private static $supplierclient_get_call = "SupplierClientGet";
	private static $supplierclient_update_call = "SupplierClientUpdate";
	private static $supplierclient_undelete_call = "SupplierClientUndelete";

	public static function wc_all() {
		$clients = array();
		
		foreach (get_users() as $user) {
			$client = self::wc_convert($user);
			
			array_push($clients, $client);
		}
		
		return $clients;
	}

	public static function wc_find($id) {
		$user = get_user_by('ID', $id);
		if (!$user) {
			return null;
		}
		return self::wc_convert($user);
	}
	
	public static function mv_find($id) {
		$url = create_json_url_filter(self::$supplierclient_get_call, "SupplierClientID", "Equals", urlencode($id));
		$client = json_decode(file_get_contents($url), true);
		if (count($client['mvSupplierClients']) <= 0) {
			return null;
		}
		return self::mv_convert($client['mvSupplierClients'][0]);
	}
	
	private static function wc_convert($wc_client) {
		$client = new Client();
		$client->WC_ID = $wc_client->ID;
		$client->MV_ID = get_user_meta($wc_client->ID, "MV_ID", true);
		$client->email = $wc_client->user_email;
		
		$client->username = $wc_client->user_login;
		
		$client->contact_name = get_user_meta($wc_client->ID, 'first_name', true) . " " . get_user_meta($wc_client->ID, 'last_name', true);
		$ship_name = get_user_meta($wc_client->ID, 'shipping_first_name', true) . " " . get_user_meta($wc_client->ID, 'shipping_last_name', true);
		$client->company = get_user_meta($wc_client->ID, 'billing_company', true);
		
		$shipping_address['name'] = $ship_name;
		$shipping_address['company'] = $client->company;
		$shipping_address['line_1'] = get_user_meta($wc_client->ID, 'shipping_address_1', true);
		$shipping_address['line_2'] = get_user_meta($wc_client->ID, 'shipping_address_2', true);
		$shipping_address['city'] = get_user_meta($wc_client->ID, 'shipping_city', true);
		$shipping_address['postcode'] = get_user_meta($wc_client->ID, 'shipping_postcode', true);
		$shipping_address['country'] = get_user_meta($wc_client->ID, 'shipping_country', true);
		$client->shipping_address = format_address($shipping_address);
		
		$billing_address['name'] = $client->contact_name;
		$billing_address['company'] = $client->company;
		$billing_address['line_1'] = get_user_meta($wc_client->ID, 'billing_address_1', true);
		$billing_address['line_2'] = get_user_meta($wc_client->ID, 'billing_address_2', true);
		$billing_address['city'] = get_user_meta($wc_client->ID, 'billing_city', true);
		$billing_address['postcode'] = get_user_meta($wc_client->ID, 'billing_postcode', true);
		$billing_address['country'] = get_user_meta($wc_client->ID, 'billing_country', true);
		$client->shipping_address = format_address($billing_address);
		
		$client->phone = get_user_meta($wc_client->ID, 'billing_phone', true);
		$client->type = "Client";
		
		return $client;
	}
	
	private static function mv_convert($supplierclient) {
		$client = new Client();
				
		$client->MV_ID = $supplierclient['SupplierClientID'];
		$client->username = $supplierclient['SupplierClientName'];
		$client->contact_name = $supplierclient['SupplierClientName'];
		$client->shipping_address = $supplierclient['SupplierClientShippingAddress1'];
		$client->shipping_address2 = $supplierclient['SupplierClientShippingAddress2'];
		$client->billing_address = $supplierclient['SupplierClientBillingAddress2'];
		$client->tax_ID = $supplierclient['SupplierClientTaxID'];
		$client->phone = $supplierclient['SupplierClientPhone1'];
		$client->email = $supplierclient['SupplierClientEmail'];
		$client->type = $supplierclient['SupplierClientType'];
		
		return $client;
	}
	
	public function mv_save() {
		$url = create_xml_url(self::$supplierclient_update_call);
		$create_new = $this->MV_ID == null;
		$action = ($create_new ? "Insert" : "Update");
		
		$xml_request = '
				<mvSupplierClient>
					' . ($create_new ? '' : '<SupplierClientID>' . $this->MV_ID . '</SupplierClientID>') . '
					' . ($this->type ? '<SupplierClientType>' . $this->type . '</SupplierClientType>' : '') . '
					<SupplierClientName>' . $this->contact_name . '</SupplierClientName>
					' . ($this->billing_address ? '<SupplierClientBillingAddress>' . $this->billing_address . '</SupplierClientBillingAddress>' : '') . '
					' . ($this->shipping_address ? '<SupplierClientShippingAddress1>' . $this->shipping_address . '</SupplierClientShippingAddress1>' : '') . '
					' . ($this->phone ? '<SupplierClientPhone1>' . $this->phone . '</SupplierClientPhone1>' : '') . '
					' . ($this->email ? '<SupplierClientEmail>' . $this->email . '</SupplierClientEmail>' : '') . '
					' . ($this->contact_name ? '<SupplierClientComments>' . 'WooCommerce client' . '</SupplierClientComments>' : '') . '
				</mvSupplierClient>
				<mvRecordAction>' . $action . '</mvRecordAction>
			';
			
		$xml_request = wrap_xml(self::$supplierclient_update_call, $xml_request);
		//echo htmlentities($xml_request);
		
		$data = send_xml($url, $xml_request);
		
		update_user_meta($this->WC_ID, "MV_ID", $data["mvSupplierClient"]["SupplierClientID"]);
		$this->MV_ID = $data["mvSupplierClient"]["SupplierClientID"];
		
		//var_dump($data);
		return $data;
	}
	
	public static function mv_undelete($id) {
		$url = create_json_url(self::$supplierclient_undelete_call);
		$url .= "&SupplierClientIDToUndelete=" . urlencode($id);
		file_get_contents($url);
	}
	
	
}

?>
