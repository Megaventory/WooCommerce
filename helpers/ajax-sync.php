<?php
/**
 * Synchronize helper.
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

/**
 * Asynchronous import.
 */
function async_import() {
	$errors_count    = 0;
	$successes_count = 0;
	$errors          = 0;
	$successes       = 0;
	$count_of_entity = -1;
	$page            = 1;

	try {

		if ( isset( $_POST['startingIndex'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$starting_index = (int) sanitize_text_field( wp_unslash( $_POST['startingIndex'] ) );
		}

		if ( isset( $_POST['numberOfIndexesToProcess'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$number_of_indexes_to_process = (int) sanitize_text_field( wp_unslash( $_POST['numberOfIndexesToProcess'] ) );
		}

		if ( isset( $_POST['call'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$call = sanitize_text_field( wp_unslash( $_POST['call'] ) );
		}

		if ( isset( $_POST['successes'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$successes_count = isset( $_POST['successes'] ) ? (int) $_POST['successes'] : null;
		}

		if ( isset( $_POST['errors'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$errors_count = isset( $_POST['errors'] ) ? (int) $_POST['errors'] : null;
		}
		if ( isset( $_POST['countOfEntity'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$count_of_entity = isset( $_POST['countOfEntity'] ) ? (int) $_POST['countOfEntity'] : null;
		}
		if ( isset( $_POST['page'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$page = isset( $_POST['page'] ) ? (int) $_POST['page'] : null;
		}

		if ( 'products' === $call ) {

			$categories = Product::mv_get_categories();

			$number_of_products = $count_of_entity;
			if ( 0 > $number_of_products ) {

				$number_of_products = Product::wc_get_all_woocommerce_products_count();

			}

			$wc_products                   = Product::wc_get_products_in_batches( $number_of_indexes_to_process, $page );
			$number_of_products_to_process = count( $wc_products );

			if ( 0 === $number_of_products_to_process ) {
				update_option( 'are_megaventory_products_synchronized', 1 );

				$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

				$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

				$synchronized_message = $successes_count . ' of ' . $number_of_products . ' on ' . $current_date;

				update_option( 'megaventory_products_synchronized_time', $synchronized_message );

				$success_message = 'FinishedSuccessfully';
				if ( $successes_count > 0 ) {
					$message = "$successes_count products have been imported/updated successfully in your Megaventory account.";
					log_notice( 'success', $message );
				}
				if ( $errors_count > 0 ) {
					$message = "$errors_count products haven't been imported in your Megaventory account. " . ' Please check the Error log below for more information.';
					log_notice( 'error', $message );
				}

				$data_to_return = create_json_for_ajax_imports( $starting_index, $number_of_indexes_to_process, $number_of_products, $page, $successes_count, $errors_count, $success_message );

				wp_send_json_success( $data_to_return );
				wp_die();
			}

			foreach ( $wc_products as $wc_product ) {

				$flag = $wc_product->mv_save( $categories );

				if ( null !== $flag ) {

					$flag ? $successes++ : $errors++;
				}
			}

			$successes_count += $successes;
			$errors_count    += $errors;

			$success_message = 'continue';

			$data_to_return = create_json_for_ajax_imports( $starting_index, $number_of_indexes_to_process, $number_of_products, $page, $successes_count, $errors_count, $success_message );

			wp_send_json_success( $data_to_return );
			wp_die();
		}

		if ( 'clients' === $call ) {

			$number_of_clients = $count_of_entity;
			if ( 0 > $number_of_clients ) {

				$number_of_clients = Client::wc_get_all_wordpress_clients_count();
			}

			$wc_clients = Client::wc_get_wordpress_clients_in_batches( $number_of_indexes_to_process, $page );

			$number_of_clients_to_process = count( $wc_clients );

			if ( 0 === $number_of_clients_to_process ) {

				update_option( 'are_megaventory_clients_synchronized', 1 );

				$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

				$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

				$synchronized_message = $successes_count . ' of ' . $number_of_clients . ' on ' . $current_date;

				update_option( 'megaventory_clients_synchronized_time', $synchronized_message );

				$success_message = 'FinishedSuccessfully';
				if ( $successes_count > 0 ) {
					$message = "$successes_count customers have been imported/updated successfully in your Megaventory account.";
					log_notice( 'success', $message );
				}
				if ( $errors_count > 0 ) {
					$message = "$errors_count customers haven't been imported in your Megaventory account.";
					log_notice( 'error', $message );
				}

				$data_to_return = create_json_for_ajax_imports( $starting_index, $number_of_indexes_to_process, $number_of_clients, $page, $successes_count, $errors_count, $success_message );

				wp_send_json_success( $data_to_return );
				wp_die();
			}

			foreach ( $wc_clients as $wc_client ) {

				$client_saved = $wc_client->mv_save();
				$client_saved ? $successes++ : $errors++;
			}
			$successes_count += $successes;
			$errors_count    += $errors;

			$success_message = 'continue';

			$data_to_return = create_json_for_ajax_imports( $starting_index, $number_of_indexes_to_process, $number_of_clients, $page, $successes_count, $errors_count, $success_message );

			wp_send_json_success( $data_to_return );
			wp_die();
		}

		if ( 'coupons' === $call ) {

			$coupons           = Coupon::wc_all();
			$number_of_coupons = count( $coupons );

			for ( $i = $starting_index; $i < $number_of_indexes_to_process + $starting_index; $i++ ) {

				if ( $number_of_coupons > $i ) {

					if ( 'percent' !== $coupons[ $i ]->type ) {
						continue;
					}

					$flag = $coupons[ $i ]->mv_save();
					$flag ? $successes++ : $errors++;
				}
			}

			$successes_count += $successes;
			$errors_count    += $errors;

			if ( $number_of_indexes_to_process + $starting_index > $number_of_coupons ) {

				update_option( 'are_megaventory_coupons_synchronized', 1 );

				$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

				$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

				$synchronized_message = $successes_count . ' of ' . $number_of_coupons . ' on ' . $current_date;

				update_option( 'megaventory_coupons_synchronized_time', $synchronized_message );

				$success_message = 'FinishedSuccessfully';
				if ( $successes_count > 0 ) {
					$message = "$successes_count coupons have been imported/updated successfully in your Megaventory account.";
					log_notice( 'success', $message );
				}
				if ( $errors_count > 0 ) {
					$message = "$errors_count coupons haven't imported in your Megaventory account. " . ' Please check the Error log below for more information.';
					log_notice( 'error', $message );
				}
			} else {
				$success_message = 'continue';
			}

			$data_to_return = create_json_for_ajax_imports( $starting_index, $number_of_indexes_to_process, $number_of_coupons, $page, $successes_count, $errors_count, $success_message );

			wp_send_json_success( $data_to_return );
			wp_die();

		}

		if ( 'initialize' === $call ) {

			if ( isset( $_POST['block'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

				$block = (int) sanitize_text_field( wp_unslash( $_POST['block'] ) );
			}

			$number_of_blocks = 4;

			if ( 0 === $block ) {

				Client::create_default_client();

				Product::create_default_shipping();

				Location::initialize_megaventory_locations();

				$step            = $block + 1;
				$percent         = (int) ( ( $step / $number_of_blocks ) * 100 );
				$success_message = 'continue';
				$block++;

				$data_to_return = create_json_for_ajax_initialize( $block, -1, 1, $percent, $success_message );

				wp_send_json_success( $data_to_return );
				wp_die();
			}

			if ( 1 === $block ) {

				$number_of_products = $count_of_entity;
				if ( 0 > $number_of_products ) {

					$number_of_products = Product::wc_get_all_woocommerce_products_count();
				}

				$products = Product::wc_get_products_in_batches( $number_of_indexes_to_process, $page );

				$number_of_products_to_process = count( $products );

				$success_message = 'continue';

				if ( 0 === $number_of_products_to_process ) {

					$block++;
					$percent = ( $block / $number_of_blocks ) * 100;

					$data_to_return = create_json_for_ajax_initialize( $block, -1, 1, $percent, $success_message );

					wp_send_json_success( $data_to_return );
					wp_die();
				}

				foreach ( $products as $product ) {

					$product->reset_megaventory_post_meta();
				}

				$percent = ( $block / $number_of_blocks ) * 100; // 25.

				$processed_products_percent = ( $number_of_products_to_process + ( ( $page - 1 ) * $number_of_indexes_to_process ) ) / $number_of_products;

				$percentage_of_products = $processed_products_percent * ( 1 / $number_of_blocks ) * 100;

				$percent += $percentage_of_products;

				$percent = round( $percent );

				++$page;

				$data_to_return = create_json_for_ajax_initialize( $block, $number_of_products, $page, $percent, $success_message );

				wp_send_json_success( $data_to_return );
				wp_die();
			}

			if ( 2 === $block ) {

				initialize_taxes();
				$step            = $block + 1;
				$percent         = (int) ( ( $step / $number_of_blocks ) * 100 );
				$success_message = 'continue';
				$block++;

				$data_to_return = create_json_for_ajax_initialize( $block, -1, 1, $percent, $success_message );
				wp_send_json_success( $data_to_return );
				wp_die();

			}

			if ( 3 === $block ) {

				update_option( 'is_megaventory_initialized', 1 );

				// DO NOT update with booleans values.
				update_option( 'are_megaventory_products_synchronized', 0 );
				update_option( 'are_megaventory_clients_synchronized', 0 );
				update_option( 'are_megaventory_coupons_synchronized', 0 );
				update_option( 'is_megaventory_stock_adjusted', 0 );

				$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

				$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

				update_option( 'megaventory_initialized_time', (string) $current_date );

				$percent         = 100;
				$success_message = 'FinishedSuccessfully';
				$block++;

				$message1 = 'Plugin successfully initialized! Now you can import products, clients and coupons in your Megaventory account.';
				$message2 = 'Please keep in mind that this process will take place only once. After that, synchronization will happen automatically!';

				log_notice( 'success', $message1 );
				log_notice( 'notice', $message2 );

				$percent = $percent > 100 ? 100 : $percent;

				$data_to_return = create_json_for_ajax_initialize( $block, 0, 1, $percent, $success_message );
				wp_send_json_success( $data_to_return );
				wp_die();

			}
		}
	} catch ( \Error $ex ) {

		$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

		$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

		error_log( "\n" . $current_date . $ex->getMessage() . ' ' . $ex->getFile() . "({$ex->getLine()})", 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.
		error_log( "\n" . $current_date . $ex->getTraceAsString(), 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.

		if ( 'initialize' === $call ) {
			$data_to_return = create_json_for_ajax_initialize( 0, 0, 0, 0, 'Error occurred' );
		} else {
			$data_to_return = create_json_for_ajax_imports( 1, 1, 1, 1, 1, 1, 'Error occurred' );
		}

		wp_send_json_success( $data_to_return );
		wp_die();
	}
}

/**
 * Change alternate cron status.
 */
function change_alternate_cron_status() {
	if ( isset( $_POST['newStatus'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {
		$status = (bool) ( sanitize_text_field( wp_unslash( $_POST['newStatus'] ) ) === 'true' );
		update_option( 'megaventory_alternate_wp_cron', $status );
		wp_send_json_success( array( 'success' => true ), 200 );
	} else {
		wp_send_json_error( array( 'success' => false ), 200 );
	}
	wp_die();
}

/**
 * Change Adjustment Document Status Option
 */
function change_adjustment_status_option() {
	if ( isset( $_POST['prefered-status'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {
		$option_value = sanitize_text_field( wp_unslash( $_POST['prefered-status'] ) );
		update_option( 'megaventory_adjustment_document_status_option', $option_value );
		wp_send_json_success( array( 'success' => true ), 200 );
	} else {
		wp_send_json_error( array( 'success' => false ), 200 );
	}
	wp_die();
}

/**
 * Notices.
 *
 * @param string $type as message type.
 * @param string $message as notice message.
 */
function log_notice( $type, $message ) {

	global $wpdb;
	$notices_table_name = $wpdb->prefix . 'megaventory_notices_log';

	$charset_collate = $wpdb->get_charset_collate();

	$query = $wpdb->insert(
		$notices_table_name,
		array(
			'type'    => $type,
			'message' => $message,
		)
	); // db call ok.

	return $query;
}

/**
 * Ajax imports.
 *
 * @param int    $starting_index as start point.
 * @param int    $number_of_indexes_to_process as number.
 * @param int    $count_of_entity as number of entities.
 * @param int    $page pagination.
 * @param int    $successes_count as number of success.
 * @param int    $errors_count as number of errors.
 * @param string $success_message as message.
 */
function create_json_for_ajax_imports( $starting_index,
										$number_of_indexes_to_process,
										$count_of_entity,
										$page,
										$successes_count,
										$errors_count,
										$success_message ) {

	$json_data = new \stdClass();

	$processed = $starting_index;

	++$page;

	$json_data->starting_index             = $number_of_indexes_to_process + $starting_index;
	$json_data->count_of_entity            = $count_of_entity;
	$json_data->page                       = $page;
	$json_data->current_sync_count_message = 'Current Sync Count: ' . $processed . ' of ' . $count_of_entity;
	$json_data->success_count              = $successes_count;
	$json_data->errors_count               = $errors_count;
	$json_data->success_message            = $success_message;

	return wp_json_encode( $json_data );

}

/**
 * Json initialization.
 *
 * @param int    $block as number.
 * @param int    $count_of_entity as number.
 * @param int    $page as number.
 * @param int    $percent as number.
 * @param string $success_message as message.
 */
function create_json_for_ajax_initialize( $block, $count_of_entity, $page, $percent, $success_message ) {

	$json_data = new \stdClass();

	$json_data->block           = $block;
	$json_data->percent_message = 'Current Sync Count: ' . $percent . '%';
	$json_data->success_message = $success_message;
	$json_data->count_of_entity = $count_of_entity;
	$json_data->page            = $page;

	return wp_json_encode( $json_data );

}

/**
 * Change default inventory location for sales orders.
 */
function change_default_megaventory_location() {

	$inventory_id = 0;

	if ( isset( $_POST['inventory_id'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

		$inventory_id = (int) sanitize_text_field( wp_unslash( $_POST['inventory_id'] ) );

		update_option( 'default-megaventory-inventory-location', $inventory_id );
	}

	wp_send_json_success( true );
	wp_die();
}

/**
 * Change default inventory location for sales orders.
 */
function skip_stock_synchronization() {

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
 * Pull integration updates manually.
 */
function pull_integration_updates() {

	if ( isset( $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

		pull_changes();
	}

	wp_send_json_success( true );
	wp_die();
}

/**
 * Synchronize stock to megaventory in batches.
 */
function sync_stock_to_megaventory() {

	$starting_index = 0;
	$return_values  = array();
	try {

		if ( isset( $_POST['prefered-status'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {
			$option_value = sanitize_text_field( wp_unslash( $_POST['prefered-status'] ) );
			update_option( 'megaventory_adjustment_document_status_option', $option_value );
		}

		if ( isset( $_POST['async-nonce'], $_POST['startingIndex'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$starting_index = (int) sanitize_text_field( wp_unslash( $_POST['startingIndex'] ) );

			$return_values = Product::push_stock( $starting_index );
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
			'message'        => '',
		);
	}

	wp_send_json_success( wp_json_encode( $return_values ) );
	wp_die();
}

/**
 * Synchronize stock from megaventory in batches.
 */
function sync_stock_from_megaventory() {

	$starting_index = 0;
	$return_values  = array();
	try {

		if ( isset( $_POST['async-nonce'], $_POST['startingIndex'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$starting_index = (int) sanitize_text_field( wp_unslash( $_POST['startingIndex'] ) );

			$return_values = Product::pull_stock( $starting_index );
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
			'message'        => '',
		);
	}

	wp_send_json_success( wp_json_encode( $return_values ) );
	wp_die();
}

/**
 * Synchronize order.
 */
function sync_order() {

	try {

		if ( isset( $_POST['async-nonce'], $_POST['orderId'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$order_id = (int) sanitize_text_field( wp_unslash( $_POST['orderId'] ) );

			$return_values = order_placed( $order_id );
		}
	} catch ( \Error $ex ) {

		$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

		$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

		error_log( "\n" . $current_date . $ex->getMessage() . ' ' . $ex->getFile() . "({$ex->getLine()})", 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.
		error_log( "\n" . $current_date . $ex->getTraceAsString(), 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.

	}

	wp_send_json_success( wp_json_encode( $return_values ) );
	wp_die();
}

