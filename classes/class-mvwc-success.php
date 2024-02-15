<?php
/**
 * Success class.
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
 * Success class.
 */
class MVWC_Success {

	/**
	 * Entity id in WooCommerce.
	 *
	 * @var int
	 */
	private $entity_wc_id;

	/**
	 * Entity id in Megaventory.
	 *
	 * @var int
	 */
	private $entity_mv_id;

	/**
	 * Entity name.
	 *
	 * @var string
	 */
	private $entity_name;

	/**
	 * Entity type(customer, product etc).
	 *
	 * @var string
	 */
	private $entity_type;

	/**
	 * Transaction status.
	 *
	 * @var string
	 */
	private $transaction_status;

	/**
	 * Full success message.
	 *
	 * @var string
	 */
	private $full_msg;

	/**
	 * Success code.
	 *
	 * @var int
	 */
	private $success_code;

	/**
	 * Class constructor.
	 *
	 * @param array $args as success data.
	 */
	public function __construct( $args ) {

		$this->entity_wc_id       = empty( $args['entity_id']['wc'] ) ? '' : $args['entity_id']['wc'];
		$this->entity_mv_id       = empty( $args['entity_id']['mv'] ) ? '' : $args['entity_id']['mv'];
		$this->entity_type        = empty( $args['entity_type'] ) ? '' : $args['entity_type'];
		$this->entity_name        = empty( $args['entity_name'] ) ? '' : $args['entity_name'];
		$this->transaction_status = empty( $args['transaction_status'] ) ? '' : $args['transaction_status'];
		$this->full_msg           = empty( $args['full_msg'] ) ? '' : $args['full_msg'];
		$this->success_code       = empty( $args['success_code'] ) ? '' : $args['success_code'];

		$this->save();
	}

	/**
	 * Get full success message.
	 *
	 * @return string
	 */
	public function get_full_message() {

		return $this->full_msg;
	}

	/**
	 * Save success to Database.
	 *
	 * @return int
	 */
	public function save() {

		global $wpdb;

		$megaventory_success_table_name = $wpdb->prefix . 'megaventory_success_log';

		$charset_collate = $wpdb->get_charset_collate();

		$sql_results = $wpdb->insert( // phpcs:ignore
			$megaventory_success_table_name,
			array(
				'created_at'         => get_date_from_gmt( gmdate( 'Y-m-d H:i:s' ), 'Y-m-d H:i:s' ),
				'type'               => $this->entity_type,
				'name'               => $this->entity_name,
				'wc_id'              => $this->entity_wc_id,
				'mv_id'              => $this->entity_mv_id,
				'transaction_status' => $this->transaction_status,
				'message'            => $this->full_msg,
				'code'               => $this->success_code,
			),
			array(
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
			)
		);

		return true;
	}

	/**
	 * Get A Collection of Success Messages.
	 *
	 * @param int $count as success messages to display.
	 * @return array
	 */
	public static function get_messages( $count = MV_Constants::DEFAULT_SUCCESS_MESSAGE_COUNT_TO_DISPLAY ) {
		global $wpdb;

		$entries = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"
				SELECT * 
				FROM {$wpdb->prefix}megaventory_success_log 
				ORDER BY created_at 
				DESC LIMIT %d;
				",
				$count
			)
		);

		return $entries;
	}

	/**
	 * Deletes successes in database
	 *
	 * @param array $ids_to_delete as log ids to delete.
	 */
	public static function delete( $ids_to_delete ) {

		global $wpdb;

		$ids = implode( ',', array_map( 'absint', $ids_to_delete ) );

		$sql_results = $wpdb->query( //phpcs:ignore
			$wpdb->prepare( "DELETE FROM {$wpdb->prefix}megaventory_success_log WHERE id IN(%1s)", array( $ids ) ) // phpcs:ignore
		);
	}
}
