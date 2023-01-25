<?php
/**
 * Error class.
 *
 * @package megaventory
 * @since 1.0.0
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Megaventory\Models;

/**
 * Error class.
 */
class MVWC_Error {
	/**
	 * WooCommerce id.
	 *
	 * @var string
	 */
	private $entity_wc_id;

	/**
	 * Megaventory id.
	 *
	 * @var string
	 */
	private $entity_mv_id;

	/**
	 * Name.
	 *
	 * @var string
	 */
	private $entity_name;

	/**
	 * Problem.
	 *
	 * @var string
	 */
	private $problem;

	/**
	 * Error's full message.
	 *
	 * @var string
	 */
	private $full_msg;

	/**
	 * Log type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Json object.
	 *
	 * @var string
	 */
	private $json_object;

	/**
	 * Error's code.
	 *
	 * @var string
	 */
	private $error_code;

	/**
	 * Constructor.
	 *
	 * @param array $args as error information.
	 */
	public function __construct( $args ) {
		$this->entity_wc_id = empty( $args['entity_id']['wc'] ) ? '' : $args['entity_id']['wc'];
		$this->entity_mv_id = empty( $args['entity_id']['mv'] ) ? '' : $args['entity_id']['mv'];
		$this->entity_name  = empty( $args['entity_name'] ) ? '' : $args['entity_name'];
		$this->problem      = empty( $args['problem'] ) ? '' : $args['problem'];
		$this->full_msg     = empty( $args['full_msg'] ) ? '' : $args['full_msg'];
		$this->error_code   = empty( $args['error_code'] ) ? '' : $args['error_code'];
		$this->type         = empty( $args['type'] ) ? '' : $args['type'];
		$this->json_object  = empty( $args['json_object'] ) ? '' : $args['json_object'];

		$this->save();
	}

	/**
	 * Get full message.
	 *
	 * @return string.
	 */
	public function get_full_message() {
		return $this->full_msg;
	}

	/**
	 * Save the error to database.
	 *
	 * @return int|false
	 */
	public function save() {
		/* errors should be immutable. There is no point in changing the messages.  */
		global $wpdb;
		$error_table_name = $wpdb->prefix . 'megaventory_errors_log';
		$charset_collate  = $wpdb->get_charset_collate();

		$sql_results = $wpdb->insert(
			$error_table_name,
			array(
				'created_at'  => get_date_from_gmt( gmdate( 'Y-m-d H:i:s' ), 'Y-m-d H:i:s' ),
				'name'        => $this->entity_name,
				'wc_id'       => $this->entity_wc_id,
				'mv_id'       => $this->entity_mv_id,
				'problem'     => $this->problem,
				'message'     => $this->full_msg,
				'code'        => $this->error_code,
				'type'        => $this->type,
				'json_object' => $this->json_object,
			),
			array(
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		); // db call ok.

		return true;
	}

	/**
	 * Get A Collection of Error Messages.
	 *
	 * @param int $count as error messages to display.
	 * @return array
	 */
	public static function get_messages( $count = MV_Constants::DEFAULT_ERROR_MESSAGE_COUNT_TO_DISPLAY ) {
		global $wpdb;

		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT * 
				FROM {$wpdb->prefix}megaventory_errors_log 
				ORDER BY created_at 
				DESC LIMIT %d;
				",
				$count
			)
		); // db call ok. no-cache ok.

		return $entries;
	}

	/**
	 * Deletes errors in database
	 *
	 * @param array $ids_to_delete as log ids to delete.
	 */
	public static function delete( $ids_to_delete ) {

		global $wpdb;

		$ids = implode( ',', array_map( 'absint', $ids_to_delete ) );

		$sql_results = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->prefix}megaventory_errors_log WHERE id IN(%1s)", array( $ids ) ) // @codingStandardsIgnoreLine.
		); // db call ok; no-cache ok.

	}
}
