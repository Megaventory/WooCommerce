<?php
require_once("api.php");
require_once("address.php");
require_once("error.php");
/*  This class works as a model for a client
	clients are only transfered from WC to MV */
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
	
	public $errors;
	public $successes;
	
	private static $supplierclient_get_call = "SupplierClientGet";
	private static $supplierclient_update_call = "SupplierClientUpdate";
	private static $supplierclient_undelete_call = "SupplierClientUndelete";
	private static $supplierclient_delete_call = "SupplierClientDelete";
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

	private function log_error($problem, $full_msg, $code, $type = "error") {

		$args = array
		(
			'entity_id' => array('wc' => $this->WC_ID, 'mv' => $this->MV_ID), 
			'entity_name' => $this->username,
			'problem' => $problem,
			'full_msg' => $full_msg,
			'error_code' => $code,
			'type' => $type
		);
		$this->errors->log_error($args);
	}

	private function log_success($transaction_status,$full_msg,$code){

		$args = array
		(
			'entity_id' => array('wc' => $this->WC_ID, 'mv' => $this->MV_ID), 
			'entity_type'=> "customer",
			'entity_name' => $this->contact_name,
			'transaction_status' => $transaction_status,
			'full_msg' => $full_msg,
			'success_code' => $code
		);
		$this->successes->log_success($args);
	}
	
	public static function wc_all() {

		$clients = array();
		
		foreach (get_users() as $user) {
			$client = self::wc_convert($user);
			array_push($clients, $client);
		}
		
		return $clients; 
	}
	
	public static function mv_all() {

		$url = create_json_url(self::$supplierclient_get_call);
		$jsonData=curl_call($url);
		$clients = json_decode($jsonData, true)['mvSupplierClients'];
		$temp = array();
		
		foreach ($clients as $client) {
			array_push($temp, self::mv_convert($client));
		}
		return $temp;
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
		$jsonData=curl_call($url);
		$client = json_decode($jsonData, true);
		if (count($client['mvSupplierClients']) <= 0) {
			return null;
		}
		return self::mv_convert($client['mvSupplierClients'][0]);
	}
	
	public static function mv_find_by_name($name) {

		$url = create_json_url_filter(self::$supplierclient_get_call, "SupplierClientName", "Equals", urlencode($name));
		$jsonData=curl_call($url);
		$client = json_decode($jsonData, true);
		if (count($client['mvSupplierClients']) <= 0) {
			return null;
		}
		return self::mv_convert($client['mvSupplierClients'][0]);
	}
	
	public static function mv_find_by_email($email) {

		$url = create_json_url_filter(self::$supplierclient_get_call, "SupplierClientEmail", "Equals", urlencode($email));
		$jsonData=curl_call($url);
		$client = json_decode($jsonData, true);
		if (count($client['mvSupplierClients']) <= 0) {
			return null;
		}

		return self::mv_convert($client['mvSupplierClients'][0]);
	}
	
	private static function wc_convert($wc_client) {
		
		$client_type = $wc_client->roles[0];
		if($client_type!="customer" && $client_type!="subscriber"){//we want to save only customers users
			return null;
		}

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
		$client->type = $wc_client->roles[0];
		
		return $client;
	}
	
	private static function mv_convert($supplierclient) {

		$client = new Client();
		$client->MV_ID = $supplierclient['SupplierClientID'];
		$client->username = $supplierclient['SupplierClientName'];
		$client->contact_name = $supplierclient['SupplierClientName'];
		$client->shipping_address = $supplierclient['SupplierClientShippingAddress1'];
		$client->shipping_address2 = $supplierclient['SupplierClientShippingAddress2'];
		$client->billing_address = $supplierclient['SupplierClientBillingAddress'];
		$client->tax_ID = $supplierclient['SupplierClientTaxID'];
		$client->phone = $supplierclient['SupplierClientPhone1'];
		$client->email = $supplierclient['SupplierClientEmail'];
		$client->type = $supplierclient['SupplierClientType'];
		
		return $client;
	}
	
	public function mv_save() {

		$url = create_json_url(self::$supplierclient_update_call);
		$json_request = $this->generate_update_json();
		$data= send_json($url,$json_request);
		
		/* what if client was deleted or name already exists */
		$undeleted = false;

		if (array_key_exists('InternalErrorCode', $data)) {
		
			if ($data['InternalErrorCode'] == "SupplierClientAlreadyDeleted") {
				/* client must be undeleted first and then updated */
				$undelete=self::mv_undelete($data["entityID"]);
				
				/* if undelete, this name will exist. next if statement has to decide what to do next */
				$data['InternalErrorCode'] = "SupplierClientNameAlreadyExists";
				$undeleted = true;
			} 
			/* client name already exists,update info if is the same client,create new if is different person */
			if ($data['InternalErrorCode'] == "SupplierClientNameAlreadyExists") {
				$mv_client = self::mv_find($data['entityID']);

				/* same person */
				if ($this->email == $mv_client->email) { 

					$this->MV_ID = $mv_client->MV_ID;
					$this->contact_name = $mv_client->contact_name;
					$json_request= $this->generate_update_json();
					$jsonData=send_json($url,$json_request);
					$this->log_success("updated","customer successfully updated in MV",1);
					
					return true;
				}
				/* different person */
				else { 
					$this->contact_name = $this->contact_name . " - " . $this->email;
					$json_request= $this->generate_update_json();
					$jsonData=send_json($url,$json_request);
					$this->log_success("created","customer successfully created in MV",1);

					return true;
				}
			}
		}
		
		/*  if the client is successfully inserted in Megaventory then the return data ($data) will have an mvSupplierCLient object.
			Hence, we need to update the WooCommerce's user object's MV_ID to match Megaventory's SupplierClientID */
		if (array_key_exists('mvSupplierClient', $data)){
		
			update_user_meta($this->WC_ID, "MV_ID", $data["mvSupplierClient"]["SupplierClientID"]);
			$this->MV_ID = $data["mvSupplierClient"]["SupplierClientID"];
			$this->log_success("created","customer successfully created in MV",1);
			
		} 
		else {
			/* failed to save */
			$this->log_error('Client not saved to MV', $data['InternalErrorCode'], -1);
			return false;

		}
		
		return $data;
	}
	
	private function generate_update_json(){
		$create_new = $this->MV_ID == null;
		$action = ($create_new ? "Insert" : "Update");

		$productUpdateClient=new \stdClass();
		$productClient=new \stdClass();

		$productClient->SupplierClientID= $create_new?"":$this->MV_ID;
		$productClient->SupplierClientType= $this->type? $this->type: "Client";
		$productClient->SupplierClientName= $this->contact_name;
		$this->billing_address ? $productClient->SupplierClientBillingAddress=$this->billing_address : "";
		$this->shipping_address ? $productClient->SupplierClientShippingAddress1=$this->shipping_address:"";
		$this->phone ? $productClient->SupplierClientPhone1 = $this->phone : "";
		$this->email ? $productClient->SupplierClientEmail = $this->email : "";
		$this->contact_name ? $productClient->SupplierClientComments= "WooCommerce client" : "";

		$productUpdateClient->mvSupplierClient= $productClient;
		$productUpdateClient->mvRecordAction= $action;

		$json_object=wrap_json($productUpdateClient);
		$json_object=json_encode($json_object);

		return $json_object;
		
	}
	
	public static function mv_undelete($id) {

		$url = create_json_url(self::$supplierclient_undelete_call);
		$url .= "&SupplierClientIDToUndelete=" . urlencode($id);

		$call=curl_call($url);
		return $call;
	}
	
	
	public function wc_reset_mv_data() {

		return delete_user_meta($this->WC_ID, "MV_ID");
		
	}
}
?>