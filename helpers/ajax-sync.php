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

	if ( 'products' === $call ) {

		$wc_products        = Product::wc_all_with_variable();
		$number_of_products = count( $wc_products );

		for ( $i = $starting_index; $i < $number_of_indexes_to_process + $starting_index; $i++ ) {

			if ( count( $wc_products ) > $i ) {

				$flag = $wc_products[ $i ]->mv_save();

				if ( null !== $flag ) {

					$flag ? $successes++ : $errors++;
				}
			}
		}

		$successes_count += $successes;
		$errors_count    += $errors;

		if ( $number_of_indexes_to_process + $starting_index > $number_of_products ) {
			$success_message = 'FinishedSuccessfully';
			if ( $successes_count > 0 ) {
				$message = "$successes_count products have been imported/updated successfully in your Megaventory account.";
				log_notice( 'success', $message );
			}
			if ( $errors_count > 0 ) {
				$message = "$errors_count products haven't been imported in your Megaventory account. " . ' Please check the Error log below for more information.';
				log_notice( 'error', $message );
			}
		} else {
			$success_message = 'continue';
		}

		$data_to_return = create_json_for_ajax_imports( $starting_index, $number_of_indexes_to_process, $number_of_products, $successes_count, $errors_count, $success_message );

		wp_send_json_success( $data_to_return );
		wp_die();
	}

	if ( 'clients' === $call ) {

		$wc_clients        = Client::wc_all();
		$number_of_clients = count( $wc_clients );

		for ( $i = $starting_index; $i < $number_of_indexes_to_process + $starting_index; $i++ ) {

			if ( count( $wc_clients ) > $i ) {

				if ( null !== $wc_clients[ $i ] ) {

					$client_saved = $wc_clients[ $i ]->mv_save();
					$client_saved ? $successes++ : $errors++;
				} else {
					$errors++;
				}
			}
		}
		$successes_count += $successes;
		$errors_count    += $errors;

		if ( $number_of_indexes_to_process + $starting_index > count( $wc_clients ) ) {
			$success_message = 'FinishedSuccessfully';
			if ( $successes_count > 0 ) {
				$message = "$successes_count customers have been imported/updated successfully in your Megaventory account.";
				log_notice( 'success', $message );
			}
			if ( $errors_count > 0 ) {
				$message = "$errors_count users haven't been imported in your Megaventory account. " . ' Only customers are being imported.';
				log_notice( 'error', $message );
			}
		} else {
			$success_message = 'continue';

		}

		$data_to_return = create_json_for_ajax_imports( $starting_index, $number_of_indexes_to_process, $number_of_clients, $successes_count, $errors_count, $success_message );

		wp_send_json_success( $data_to_return );
		wp_die();
	}

	if ( 'coupons' === $call ) {

		$coupons           = Coupon::wc_all();
		$number_of_coupons = count( $coupons );

		for ( $i = $starting_index; $i < $number_of_indexes_to_process + $starting_index; $i++ ) {

			if ( count( $coupons ) > $i ) {

				if ( 'percent' !== $coupons[ $i ]->type ) {
					continue;
				}

				$flag = $coupons[ $i ]->mv_save();
				$flag ? $successes++ : $errors++;
			}
		}

		$successes_count += $successes;
		$errors_count    += $errors;

		if ( $number_of_indexes_to_process + $starting_index > count( $coupons ) ) {
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

		$data_to_return = create_json_for_ajax_imports( $starting_index, $number_of_indexes_to_process, $number_of_coupons, $successes_count, $errors_count, $success_message );

		wp_send_json_success( $data_to_return );
		wp_die();

	}

	if ( 'initialize' === $call ) {

		if ( isset( $_POST['block'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$block = (int) sanitize_text_field( wp_unslash( $_POST['block'] ) );
		}

		$number_of_blocks = 6;

		if ( 0 === $block ) {

			Client::create_default_client();

			Product::create_default_shipping();

			Location::initialize_megaventory_locations();

			$step            = $block + 1;
			$percent         = (int) ( ( $step / $number_of_blocks ) * 100 );
			$success_message = 'continue';
			$block++;

			$data_to_return = create_json_for_ajax_initialize( $block, 0, $percent, $success_message );

			wp_send_json_success( $data_to_return );
			wp_die();
		}
		if ( 1 === $block ) {

			$products           = Product::wc_all();
			$number_of_products = count( $products );

			for ( $i = $starting_index;$i < $number_of_indexes_to_process + $starting_index;$i++ ) {

				if ( count( $products ) > $i ) {
					$product_to_initialize = Product::mv_find_by_sku( $products[ $i ]->sku );
					if ( $product_to_initialize ) {
						$product_to_initialize->wc_id = $products[ $i ]->wc_id;
						$product_to_initialize->sync_post_meta_with_id();

					}
				}
			}
			if ( $number_of_indexes_to_process + $starting_index > count( $products ) ) {
				$block++;
				$step = $block;
			} else {
				$step = $block + 1;
			}

			$starting_index  = $number_of_indexes_to_process + $starting_index;
			$success_message = 'continue';

			$percent = calculate_percent_on_initialize( $number_of_blocks, $starting_index, $number_of_products, $step );

			$data_to_return = create_json_for_ajax_initialize( $block, $starting_index, $percent, $success_message );

			wp_send_json_success( $data_to_return );
			wp_die();
		}

		if ( 2 === $block ) {

			map_existing_clients_by_email();
			$step            = $block + 1;
			$percent         = (int) ( ( $step / $number_of_blocks ) * 100 );
			$success_message = 'continue';
			$block++;

			$data_to_return = create_json_for_ajax_initialize( $block, 0, $percent, $success_message );
			wp_send_json_success( $data_to_return );
			wp_die();

		}

		if ( 3 === $block ) {

			initialize_taxes();
			$step            = $block + 1;
			$percent         = (int) ( ( $step / $number_of_blocks ) * 100 );
			$success_message = 'continue';
			$block++;

			$data_to_return = create_json_for_ajax_initialize( $block, 0, $percent, $success_message );
			wp_send_json_success( $data_to_return );
			wp_die();

		}

		if ( 4 === $block ) {

			update_option( 'is_megaventory_initialized', (string) true );

			$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

			$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

			update_option( 'megaventory_initialized_time', (string) $current_date );

			$step            = $block;
			$percent         = (int) ( ( $step / $number_of_blocks ) * 100 );
			$success_message = 'FinishedSuccessfully';
			$block++;

			$message1 = 'Plugin successfully initialized! Now you can import products, clients and coupons in your Megaventory account.';
			$message2 = 'Please keep in mind that this process will take place only once. After that, synchronization will happen automatically!';

			log_notice( 'success', $message1 );
			log_notice( 'notice', $message2 );

			$percent = $percent > 100 ? 100 : $percent;

			$data_to_return = create_json_for_ajax_initialize( $block, 0, $percent, $success_message );
			wp_send_json_success( $data_to_return );
			wp_die();

		}
	}
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
 * @param int    $successes_count as number of success.
 * @param int    $errors_count as number of errors.
 * @param string $success_message as message.
 */
function create_json_for_ajax_imports( $starting_index,
										$number_of_indexes_to_process,
										$count_of_entity,
										$successes_count,
										$errors_count,
										$success_message ) {

	$json_data = new \stdClass();

	$process_percent       = (int) ( 100 * ( $number_of_indexes_to_process + $starting_index ) / $count_of_entity );
	$process_percent_fixed = $process_percent > 100 ? 100 : $process_percent;

	$json_data->starting_index             = $number_of_indexes_to_process + $starting_index;
	$json_data->current_sync_count_message = 'Current Sync Count: ' . $process_percent_fixed . '%';
	$json_data->success_count              = $successes_count;
	$json_data->errors_count               = $errors_count;
	$json_data->success_message            = $success_message;

	return wp_json_encode( $json_data );

}

/**
 * Json initialization.
 *
 * @param int    $block as number.
 * @param int    $starting_index as number.
 * @param int    $percent as number.
 * @param string $success_message as message.
 */
function create_json_for_ajax_initialize( $block, $starting_index = 0, $percent, $success_message ) {

	$json_data = new \stdClass();

	$json_data->block           = $block;
	$json_data->percent_message = 'Current Sync Count: ' . $percent . '%';
	$json_data->success_message = $success_message;
	$json_data->starting_index  = $starting_index;

	return wp_json_encode( $json_data );

}

/**
 * Json initialization.
 *
 * @param int $number_of_blocks as number.
 * @param int $starting_index as number.
 * @param int $count_of_entity as number.
 * @param int $step as number.
 */
function calculate_percent_on_initialize( $number_of_blocks, $starting_index, $count_of_entity, $step ) {

	$local_percent = ( 1 / $number_of_blocks * ( $starting_index / $count_of_entity ) );
	$percent       = (int) ( ( ( ( $step / $number_of_blocks ) - ( 1 / $number_of_blocks ) ) + $local_percent ) * 100 );

	return $percent;
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
