<?php
/**
 * Stock controller.
 *
 * @package megaventory
 * @since 2.3.1
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Megaventory\Controllers;

/**
 * Controller for stock and adjustments.
 */
class Stock {

	/**
	 * Change default inventory location for sales orders.
	 */
	public static function megaventory_skip_stock_synchronization() {

		try {

			if ( isset( $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

				update_option( 'is_megaventory_stock_adjusted', 1 );

				$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

				$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

				$synchronized_message = 'Quantity synchronization was skipped on ' . $current_date;

				update_option( 'megaventory_stock_synchronized_time', $synchronized_message );
			}
		} catch ( \Error $ex ) {

			$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

			$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

			error_log( "\n" . $current_date . $ex->getMessage() . ' ' . $ex->getFile() . "({$ex->getLine()})", 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.
			error_log( "\n" . $current_date . $ex->getTraceAsString(), 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.
		}

		wp_send_json_success( true );
		wp_die();
	}

	/**
	 * Synchronize stock to megaventory in batches.
	 */
	public static function megaventory_sync_stock_to_mv() {

		$starting_index         = 0;
		$adjustment_status      = 'Pending';
		$adjustment_location_id = (int) get_option( 'default-megaventory-inventory-location' );
		$return_values          = array();
		try {

			if ( isset( $_POST['preferred-status'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

				$adjustment_status = sanitize_text_field( wp_unslash( $_POST['preferred-status'] ) );
			}

			if ( isset( $_POST['preferred-location-id'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

				$adjustment_location_id = (int) sanitize_text_field( wp_unslash( $_POST['preferred-location-id'] ) );
			}

			if ( isset( $_POST['async-nonce'], $_POST['startingIndex'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

				$starting_index = (int) sanitize_text_field( wp_unslash( $_POST['startingIndex'] ) );

				$return_values = \Megaventory\Models\Product::push_stock( $starting_index, $adjustment_status, $adjustment_location_id );
			}
		} catch ( \Error $ex ) {

			$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

			$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

			error_log( "\n" . $current_date . $ex->getMessage() . ' ' . $ex->getFile() . "({$ex->getLine()})", 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.
			error_log( "\n" . $current_date . $ex->getTraceAsString(), 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.

			$return_values = array(
				'starting_index' => $starting_index,
				'next_index'     => 0,
				'error_occurred' => true,
				'finished'       => true,
				'message'        => $ex->getMessage(),
			);
		}

		wp_send_json_success( wp_json_encode( $return_values ) );
		wp_die();
	}

	/**
	 * Synchronize stock from megaventory in batches.
	 */
	public static function megaventory_sync_stock_from_mv() {

		$starting_index = 0;
		$return_values  = array();
		try {

			if ( isset( $_POST['async-nonce'], $_POST['startingIndex'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

				$starting_index = (int) sanitize_text_field( wp_unslash( $_POST['startingIndex'] ) );

				$return_values = \Megaventory\Models\Product::pull_stock( $starting_index );
			}
		} catch ( \Error $ex ) {

			$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

			$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

			error_log( "\n" . $current_date . $ex->getMessage() . ' ' . $ex->getFile() . "({$ex->getLine()})", 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.
			error_log( "\n" . $current_date . $ex->getTraceAsString(), 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.

			$return_values = array(
				'starting_index' => $starting_index,
				'next_index'     => 0,
				'error_occurred' => true,
				'finished'       => true,
				'message'        => $ex->getMessage(),
			);
		}

		wp_send_json_success( wp_json_encode( $return_values ) );
		wp_die();
	}

}

