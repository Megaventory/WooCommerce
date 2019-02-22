<?php

$success_table_name = $wpdb->prefix . "success_log";

class MVWC_Success{
	private $entity_wc_id;
	private $entity_mv_id;
	private $entity_name;
	private $transaction_status;
	private $full_msg;
	private $success_code;
	
	function __construct($args) {
		$this->entity_wc_id = $args['entity_id']['wc'];
		$this->entity_mv_id = $args['entity_id']['mv'];
		$this->entity_type = $args['entity_type'];
		$this->entity_name = $args['entity_name'];
		$this->transaction_status = $args['transaction_status'];
		$this->full_msg = $args['full_msg'];
		$this->success_code = $args['success_code'];
		
		$this->save();
	}
	
	public function get_full_message() {
		return $this->full_msg;
	}
	
	public function save() {
		global $success_table_name, $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		$return = $wpdb->insert($success_table_name, array
		(
			"created_at" => date('Y-m-d H:i:s'),
			"type" => $this->entity_type,
			"name" => $this->entity_name,
			"wc_id" => $this->entity_wc_id,
			"mv_id" => $this->entity_mv_id,
			"transaction_status" => $this->transaction_status,
			"message" => $this->full_msg,
			"code" => $this->success_code,
		));
		
		return $return;
	}
}

class MVWC_Successes{
	private $successes = array(); 
	
	public function log_success($args = array()) {
		array_push($this->successes, new MVWC_Success($args));
	}
	
	public function full_messages() {
		$msgs = array();
		foreach ($this->successes as $success) {
			array_push($msgs, $success->get_full_message());
		}
		return $msgs;
	}
}
?>