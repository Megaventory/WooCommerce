<?php

//errors should be immutable. There is no point in changing the messages.
$tablename = $wpdb->prefix . "mvwc_errors"; 

class MVWC_Error {
	private $entity_wc_id;
	private $entity_mv_id;
	private $entity_name;
	private $problem;
	private $full_msg;
	private $error_code;
	
	function __construct($args) {
		$this->entity_wc_id = $args['entity_id']['wc'];
		$this->entity_wc_id = $args['entity_id']['mv'];
		$this->entity_name = $args['entity_name'];
		$this->problem = $args['problem'];
		$this->full_msg = $args['full_msg'];
		$this->error_code = $args['error_code'];
		$this->type = $args['type'];
		
		$this->save();
	}
	
	public function get_full_message() {
		return $this->full_msg;
	}
	
	public function save() {
		global $tablename, $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		$return = $wpdb->insert($tablename, array
		(
			"created_at" => date('Y-m-d H:i:s'),
			"name" => $this->entity_name,
			"wc_id" => $this->entity_wc_id,
			"mv_id" => $this->entity_mv_id,
			"problem" => $this->problem,
			"message" => $this->full_msg,
			"code" => $this->error_code,
			"type" => $this->type
		));
		
		wp_mail("mpanasiuk@megaventory.com", "return", var_export($return, true));
		var_dump($return);
	}
}

class MVWC_Errors {
	private $errors = array();
	
	public function log_error($args = array()) {
		array_push($this->errors, new MVWC_Error($args));
	}
	
	public function full_messages() {
		$msgs = array();
		foreach ($this->errors as $error) {
			array_push($msgs, $error->get_full_message());
		}
		return $msgs;
	}
	
}

?>
