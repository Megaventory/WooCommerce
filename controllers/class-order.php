<?php
/**
 * Order controller.
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
 * Controller for Orders.
 */
class Order {

	/**
	 * Synchronize an order to Megaventory. If Order was queued, also remove it from queue.
	 *
	 * @param int $order_id As int.
	 * @return void
	 */
	public static function order_placed( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! get_post_meta( $order->get_id(), \Megaventory\Models\MV_Constants::MV_RELATED_ORDER_ID_META, true ) ) {

			$id     = $order->get_customer_id();
			$client = \Megaventory\Models\Client::wc_find( $id );

			if ( $client && empty( $client->mv_id ) ) {
				$client->mv_save(); // make sure id exists.
			}

			if ( null === $client || null === $client->mv_id || '' === $client->mv_id ) { // Get guest.

				$client = \Megaventory\Models\Client::get_guest_mv_client();
			}

			$returned = array();
			try {

				if ( get_post_meta( $order->get_id(), 'megaventory_order_processing', true ) ) {

					return; // Exit if already processed.
				}
				update_post_meta( $order->get_id(), 'megaventory_order_processing', 1 );

				/* place order through Megaventory API */
				$returned = \Megaventory\Models\Order::megaventory_place_sales_order_to_mv( $order, $client );

			} catch ( \Error $ex ) {

				delete_post_meta( $order->get_id(), 'megaventory_order_processing' );

				$args = array(
					'type'        => 'error',
					'entity_name' => 'order by: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
					'entity_id'   => array( 'wc' => $order->get_id() ),
					'problem'     => 'Order not placed in Megaventory.',
					'full_msg'    => $ex->getMessage(),
					'error_code'  => 500,
					'json_object' => '',
				);

				$e = new \Megaventory\Models\MVWC_Error( $args );

				return;
			}

			$success_array  = array();
			$statuses_array = array();

			foreach ( $returned as $order_response ) {

				if ( '0' !== $order_response['ResponseStatus']['ErrorCode'] || ! array_key_exists( 'mvSalesOrder', $order_response ) ) {
					// Error happened. It needs to be reported.

					delete_post_meta( $order->get_id(), 'megaventory_order_processing' );

					$args = array(
						'type'        => 'error',
						'entity_name' => 'order by: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
						'entity_id'   => array( 'wc' => $order->get_id() ),
						'problem'     => 'Order not placed in Megaventory.',
						'full_msg'    => $order_response['ResponseStatus']['Message'],
						'error_code'  => $order_response['ResponseStatus']['ErrorCode'],
						'json_object' => $order_response['json_object'],
					);

					$e = new \Megaventory\Models\MVWC_Error( $args );

					continue;
				}

				$mv_order_id     = $order_response['mvSalesOrder']['SalesOrderId'];
				$mv_order_abbr   = $order_response['mvSalesOrder']['SalesOrderTypeAbbreviation'];
				$mv_order_no     = $order_response['mvSalesOrder']['SalesOrderNo'];
				$mv_order_status = $order_response['mvSalesOrder']['SalesOrderStatus'];
				$mv_location_id  = $order_response['mvSalesOrder']['SalesOrderInventoryLocationID'];

				$args = array(
					'entity_id'          => array(
						'wc' => $order->get_id(),
						'mv' => $mv_order_id,
					),
					'entity_type'        => 'order',
					'entity_name'        => $mv_order_abbr . ' ' . $mv_order_no,
					'transaction_status' => 'Insert',
					'full_msg'           => 'The order has been placed to your Megaventory account',
					'success_code'       => 1,
				);

				$e = new \Megaventory\Models\MVWC_Success( $args );

				$success_array[ $mv_order_id ] = array(
					'SalesOrderTypeAbbreviation'    => $mv_order_abbr,
					'SalesOrderNo'                  => $mv_order_no,
					'SalesOrderInventoryLocationID' => $mv_location_id,
				);

				$statuses_array[ $mv_order_id ] = $mv_order_status;
			}

			/*
				Hooks:
				woocommerce_new_order hook, comes with no items. Because items are not saved in DB yet..
				woocommerce_thankyou hook, can be ignored if checkout from paypal.
			*/
			update_post_meta( $order->get_id(), \Megaventory\Models\MV_Constants::MV_RELATED_ORDER_ID_META, array_keys( $success_array ) );
			update_post_meta( $order->get_id(), \Megaventory\Models\MV_Constants::MV_RELATED_ORDER_NAMES_META, $success_array );

			\Megaventory\Models\Order::update_mv_related_order_status_list( $order->get_id(), $statuses_array );

			\Megaventory\Models\Order::remove_order_from_sync_queue( $order->get_id() );

			delete_post_meta( $order->get_id(), 'megaventory_order_processing' );
		}
	}

	/**
	 * WooCommerce Order Cancellation handler
	 *
	 * @param int $order_id as WooCommerce order id.
	 * @return void
	 */
	public static function order_cancelled_handler( $order_id ) {

		$order = wc_get_order( $order_id );

		// Checking if has been synchronized.
		if ( empty( get_post_meta( $order->get_id(), \Megaventory\Models\MV_Constants::MV_RELATED_ORDER_ID_META, true ) ) ) {

			\Megaventory\Models\Order::remove_order_from_sync_queue( $order_id );

			return;
		}
		try {

			\Megaventory\Models\Order::cancel_sales_orders( $order );

		} catch ( \Error $ex ) {

			$args = array(
				'type'        => 'error',
				'entity_name' => 'order by: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'entity_id'   => array( 'wc' => $order->get_id() ),
				'problem'     => 'Order Cancellation failed in Megaventory.',
				'full_msg'    => $ex->getMessage(),
				'error_code'  => 500,
				'json_object' => '',
			);

			$e = new \Megaventory\Models\MVWC_Error( $args );

			return;
		}

	}

	/**
	 * Synchronize order.
	 */
	public static function synchronize_order_to_megaventory_manually() {

		try {

			if ( isset( $_POST['async-nonce'], $_POST['orderId'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

				$order_id = (int) sanitize_text_field( wp_unslash( $_POST['orderId'] ) );

				self::order_placed( $order_id );
			}
		} catch ( \Error $ex ) {

			$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

			$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

			error_log( "\n" . $current_date . $ex->getMessage() . ' ' . $ex->getFile() . "({$ex->getLine()})", 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.
			error_log( "\n" . $current_date . $ex->getTraceAsString(), 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.

		}

		wp_send_json_success();
		wp_die();
	}


	/**
	 * Handle wc order hook depending on user settings.
	 *
	 * @param int $order_id The id of the order that triggered the action.
	 * @return void
	 */
	public static function handle_order_placement( $order_id ) {

		$mv_delay_orders = (bool) get_option( \Megaventory\Models\MV_Constants::MV_ORDER_DELAY_CHOICE_OPT, false );

		if ( $mv_delay_orders ) {

			\Megaventory\Models\Order::add_order_to_sync_queue( $order_id );

		} else {

			self::order_placed( $order_id );

		}
	}

	/**
	 * Sync all orders that are older than required time ( Currently 2hrs ).
	 *
	 * @return void
	 */
	public static function sync_queued_orders_to_mv() {

		if ( ! ( get_option( 'is_megaventory_initialized' ) &&
				get_option( 'correct_currency' ) &&
				get_option( 'correct_connection' ) &&
				get_option( 'correct_megaventory_apikey' ) ) ) {

			return;
		}

		$delay_time    = (int) get_option( \Megaventory\Models\MV_Constants::MV_ORDER_DELAY_SECONDS_CHOICE_OPT, \Megaventory\Models\MV_Constants::MV_ORDER_SYNC_DEFAULT_SECONDS_TO_WAIT );
		$queued_orders = get_option( \Megaventory\Models\MV_Constants::MV_ORDERS_TO_SYNC_OPT, array() );

		foreach ( $queued_orders as $order_id => $timestamp_created ) {

			if ( ! \Megaventory\Models\Order::is_time_to_sync_order_to_mv( $timestamp_created, $delay_time ) ) {
				continue;
			}

			// Place order ( also removes it from queue ).
			self::order_placed( $order_id );

		}
	}

	/**
	 * Change MV Order Delay option.
	 */
	public static function megaventory_toggle_order_delay() {

		if ( isset( $_POST['newStatus'], $_POST['secondsToWait'], $_POST['async-nonce'] ) &&
			wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$status          = (bool) ( sanitize_text_field( wp_unslash( $_POST['newStatus'] ) ) === 'true' );
			$seconds_to_wait = $status ? (int) sanitize_text_field( wp_unslash( $_POST['secondsToWait'] ) ) : 7200;

			update_option( \Megaventory\Models\MV_Constants::MV_ORDER_DELAY_CHOICE_OPT, $status );
			update_option( \Megaventory\Models\MV_Constants::MV_ORDER_DELAY_SECONDS_CHOICE_OPT, $seconds_to_wait );

			if ( $status ) {
				\Megaventory\Helpers\Cron::order_sync_cron_activation();
			} else {
				\Megaventory\Helpers\Cron::order_sync_cron_deactivation();
			}

			wp_send_json_success( array( 'success' => true ), 200 );
		} else {
			wp_send_json_error( array( 'success' => false ), 200 );
		}
		wp_die();
	}

	/**
	 * Change MV payment method mappings
	 */
	public static function megaventory_update_payment_method_mappings() {
		if ( isset( $_POST['mv_wc_mapping'], $_POST['async-nonce'] ) &&
			wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$wc_mv_payment_method_mapping_json = sanitize_text_field( wp_unslash( $_POST['mv_wc_mapping'] ) );

			$wc_mv_current_mappings = get_option( \Megaventory\Models\MV_Constants::MV_PAYMENT_METHOD_MAPPING_OPT, false );

			$wc_mv_payment_mappings_array = json_decode( $wc_mv_payment_method_mapping_json, true );

			if ( empty( $wc_mv_current_mappings ) ) {
				$wc_mv_current_mappings = $wc_mv_payment_mappings_array;
			} else {
				foreach ( $wc_mv_payment_mappings_array as $wc_payment_method_code => $mv_payment_method ) {
					$wc_mv_current_mappings [ $wc_payment_method_code ] = $mv_payment_method;
				}
			}

			update_option( \Megaventory\Models\MV_Constants::MV_PAYMENT_METHOD_MAPPING_OPT, $wc_mv_current_mappings );

			wp_send_json_success( array( 'success' => true ), 200 );

			wp_die();

		} else {

			wp_send_json_error( array( 'success' => false ), 200 );

		}
	}
}

