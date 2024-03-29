<?php
/**
 * Synchronization controller.
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

namespace Megaventory\Controllers;

/**
 * Controller for bulk operations for products/clients/coupons/taxes.
 */
class Synchronization {

	/**
	 * Asynchronous import.
	 */
	public static function megaventory_import() {
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

			if ( isset( $_POST['entity'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

				$entity = sanitize_text_field( wp_unslash( $_POST['entity'] ) );
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

			if ( 'products' === $entity ) {

				$wc_bundles_is_active = is_plugin_active( \Megaventory\Models\MV_Constants::DEF_BUNDLES_PLUGIN_DIR );
				$categories           = \Megaventory\Models\Product::mv_get_categories();

				$number_of_products = $count_of_entity;

				if ( 0 > $number_of_products ) {

					$number_of_bundles = $wc_bundles_is_active ? \Megaventory\Models\Product_Bundle::wc_get_all_woocommerce_bundles_count() : 0;

					$number_of_products = \Megaventory\Models\Product::wc_get_all_woocommerce_products_count() + $number_of_bundles;

				}

				$wc_products                   = \Megaventory\Models\Product::wc_get_products_in_batches( $number_of_indexes_to_process, $page );
				$number_of_products_to_process = count( $wc_products );

				if ( 0 === $number_of_products_to_process ) {

					// when simple and variation products are done syncing, we then push Product Bundles.
					// this makes sure that all included products are already pushed to MV.
					$success_message = 'continue';

					$data_to_return = self::megaventory_create_json_for_imports(
						$starting_index,
						$number_of_indexes_to_process,
						$number_of_products,
						0, // reset page count to 0. Will be incremented for the next iteration.
						$successes_count,
						$errors_count,
						$success_message,
						'product_bundles'
					);

					wp_send_json_success( $data_to_return );
					wp_die();
				}

				foreach ( $wc_products as $wc_product ) {

					$product_saved = $wc_product->mv_save( $categories );

					if ( null !== $product_saved ) { // not group/variable.

						is_array( $product_saved ) ? $successes++ : $errors++;
					}
				}

				$successes_count += $successes;
				$errors_count    += $errors;

				$success_message = 'continue';

				$data_to_return = self::megaventory_create_json_for_imports(
					$starting_index,
					$number_of_indexes_to_process,
					$number_of_products,
					$page,
					$successes_count,
					$errors_count,
					$success_message,
					$entity
				);

				wp_send_json_success( $data_to_return );
				wp_die();
			}

			if ( 'product_bundles' === $entity ) {

				$wc_bundles_is_active = is_plugin_active( \Megaventory\Models\MV_Constants::DEF_BUNDLES_PLUGIN_DIR );

				$wc_product_bundles = array();

				$number_of_products = $count_of_entity;

				if ( 0 > $number_of_products && $wc_bundles_is_active ) {

					$number_of_products = \Megaventory\Models\Product_Bundle::wc_get_all_woocommerce_bundles_count();

				}

				$number_of_product_bundles_to_process = 0;

				if ( $wc_bundles_is_active ) {

					$wc_product_bundles = \Megaventory\Models\Product_Bundle::wc_get_product_bundles_in_batches( $number_of_indexes_to_process, $page );

					$number_of_product_bundles_to_process = count( $wc_product_bundles );

				}

				if ( ! $wc_bundles_is_active || 0 === $number_of_product_bundles_to_process ) {

					update_option( 'are_megaventory_products_synchronized', 1 );

					$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

					$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

					$synchronized_message = $successes_count . ' of ' . $number_of_products . ' on ' . $current_date;

					update_option( 'megaventory_products_synchronized_time', $synchronized_message );

					$success_message = 'FinishedSuccessfully';
					if ( $successes_count > 0 ) {
						$message = "$successes_count products have been imported/updated successfully in your Megaventory account.";
						self::megaventory_log_notice( 'success', $message );
					}
					if ( $errors_count > 0 ) {
						$message = "$errors_count products haven't been imported in your Megaventory account. " . ' Please check the Error log below for more information.';
						self::megaventory_log_notice( 'error', $message );
					}

					$data_to_return = self::megaventory_create_json_for_imports(
						$starting_index,
						$number_of_indexes_to_process,
						$number_of_products,
						$page,
						$successes_count,
						$errors_count,
						$success_message,
						$entity
					);

					wp_send_json_success( $data_to_return );
					wp_die();
				}

				\Megaventory\Models\Product_Bundle::push_product_bundles( $wc_product_bundles, $successes_count, $errors_count );

				$success_message = 'continue';

				$data_to_return = self::megaventory_create_json_for_imports(
					$starting_index,
					$number_of_indexes_to_process,
					$number_of_products,
					$page,
					$successes_count,
					$errors_count,
					$success_message,
					$entity
				);

				wp_send_json_success( $data_to_return );
				wp_die();
			}

			if ( 'clients' === $entity ) {

				$number_of_clients = $count_of_entity;
				if ( 0 > $number_of_clients ) {

					$number_of_clients = \Megaventory\Models\Client::wc_get_all_wordpress_clients_count();
				}

				$wc_clients = \Megaventory\Models\Client::wc_get_wordpress_clients_in_batches( $number_of_indexes_to_process, $page );

				$number_of_clients_to_process = count( $wc_clients );

				if ( 0 === $number_of_clients_to_process && $number_of_clients <= $starting_index ) {

					update_option( 'are_megaventory_clients_synchronized', 1 );

					$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

					$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

					$synchronized_message = $successes_count . ' of ' . $number_of_clients . ' on ' . $current_date;

					update_option( 'megaventory_clients_synchronized_time', $synchronized_message );

					$success_message = 'FinishedSuccessfully';
					if ( $successes_count > 0 ) {
						$message = "$successes_count customers have been imported/updated successfully in your Megaventory account.";
						self::megaventory_log_notice( 'success', $message );
					}
					if ( $errors_count > 0 ) {
						$message = "$errors_count customers haven't been imported in your Megaventory account.";
						self::megaventory_log_notice( 'error', $message );
					}

					$data_to_return = self::megaventory_create_json_for_imports(
						$starting_index,
						$number_of_indexes_to_process,
						$number_of_clients,
						$page,
						$successes_count,
						$errors_count,
						$success_message,
						$entity
					);

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

				$data_to_return = self::megaventory_create_json_for_imports(
					$starting_index,
					$number_of_indexes_to_process,
					$number_of_clients,
					$page,
					$successes_count,
					$errors_count,
					$success_message,
					$entity
				);

				wp_send_json_success( $data_to_return );
				wp_die();
			}

			if ( 'coupons' === $entity ) {

				$coupons           = \Megaventory\Models\Coupon::wc_all();
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
						self::megaventory_log_notice( 'success', $message );
					}
					if ( $errors_count > 0 ) {
						$message = "$errors_count coupons haven't imported in your Megaventory account. " . ' Please check the Error log below for more information.';
						self::megaventory_log_notice( 'error', $message );
					}
				} else {
					$success_message = 'continue';
				}

				$data_to_return = self::megaventory_create_json_for_imports(
					$starting_index,
					$number_of_indexes_to_process,
					$number_of_coupons,
					$page,
					$successes_count,
					$errors_count,
					$success_message,
					$entity
				);

				wp_send_json_success( $data_to_return );
				wp_die();

			}

			if ( 'initialize' === $entity ) {

				if ( isset( $_POST['block'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

					$block = (int) sanitize_text_field( wp_unslash( $_POST['block'] ) );
				}

				$number_of_blocks = 4;

				if ( 0 === $block ) {

					\Megaventory\Models\Client::create_default_client();

					\Megaventory\Models\Product::create_default_shipping();

					\Megaventory\Models\Location::initialize_megaventory_locations();

					\Megaventory\Models\Location::get_location_id_to_abbreviation_dict(); // updates dictionary.

					$step            = $block + 1;
					$percent         = (int) ( ( $step / $number_of_blocks ) * 100 );
					$success_message = 'continue';
					++$block;

					$data_to_return = self::megaventory_create_json_for_initialize( $block, -1, 1, $percent, $success_message );

					wp_send_json_success( $data_to_return );
					wp_die();
				}

				if ( 1 === $block ) {

					$number_of_products = $count_of_entity;
					if ( 0 > $number_of_products ) {

						$number_of_products = \Megaventory\Models\Product::wc_get_all_woocommerce_products_count();
					}

					$products = \Megaventory\Models\Product::wc_get_products_in_batches( $number_of_indexes_to_process, $page );

					$number_of_products_to_process = count( $products );

					$success_message = 'continue';

					if ( 0 === $number_of_products_to_process ) {

						++$block;
						$percent = ( $block / $number_of_blocks ) * 100;

						$data_to_return = self::megaventory_create_json_for_initialize( $block, -1, 1, $percent, $success_message );

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

					$data_to_return = self::megaventory_create_json_for_initialize( $block, $number_of_products, $page, $percent, $success_message );

					wp_send_json_success( $data_to_return );
					wp_die();
				}

				if ( 2 === $block ) {

					\Megaventory\Models\Tax::initialize_taxes();
					$step            = $block + 1;
					$percent         = (int) ( ( $step / $number_of_blocks ) * 100 );
					$success_message = 'continue';
					++$block;

					$data_to_return = self::megaventory_create_json_for_initialize( $block, -1, 1, $percent, $success_message );
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
					++$block;

					$message1 = 'Plugin successfully initialized! Now you can import products, clients and coupons in your Megaventory account.';
					$message2 = 'Please keep in mind that this process will take place only once. After that, synchronization will happen automatically!';

					self::megaventory_log_notice( 'success', $message1 );
					self::megaventory_log_notice( 'notice', $message2 );

					$percent = $percent > 100 ? 100 : $percent;

					$data_to_return = self::megaventory_create_json_for_initialize( $block, 0, 1, $percent, $success_message );
					wp_send_json_success( $data_to_return );
					wp_die();

				}
			}
		} catch ( \Error $ex ) {

			$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

			$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

			error_log( "\n" . $current_date . $ex->getMessage() . ' ' . $ex->getFile() . "({$ex->getLine()})", 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.
			error_log( "\n" . $current_date . $ex->getTraceAsString(), 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.

			if ( 'initialize' === $entity ) {
				$data_to_return = self::megaventory_create_json_for_initialize(
					0,
					0,
					0,
					0,
					'Error occurred'
				);
			} else {
				$data_to_return = self::megaventory_create_json_for_imports(
					1,
					1,
					1,
					1,
					1,
					1,
					'Error occurred',
					$entity
				);
			}

			wp_send_json_success( $data_to_return );
			wp_die();
		}
	}

	/**
	 * Notices.
	 *
	 * @param string $type as message type.
	 * @param string $message as notice message.
	 */
	private static function megaventory_log_notice( $type, $message ) {

		global $wpdb;
		$notices_table_name = $wpdb->prefix . 'megaventory_notices_log';

		$charset_collate = $wpdb->get_charset_collate();

		$query = $wpdb->insert( // phpcs:ignore
			$notices_table_name,
			array(
				'type'    => $type,
				'message' => $message,
			)
		);

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
	 * @param string $entity as the entity to be synced( products/product_bundles/clients etc.).
	 */
	private static function megaventory_create_json_for_imports(
		$starting_index,
		$number_of_indexes_to_process,
		$count_of_entity,
		$page,
		$successes_count,
		$errors_count,
		$success_message,
		$entity
	) {

		$json_data = new \stdClass();

		$processed = $successes_count + $errors_count;

		++$page;

		$json_data->starting_index             = $number_of_indexes_to_process + $starting_index;
		$json_data->count_of_entity            = $count_of_entity;
		$json_data->page                       = $page;
		$json_data->current_sync_count_message = 'Current Sync Count: ' . $processed . ' of ' . $count_of_entity;
		$json_data->success_count              = $successes_count;
		$json_data->errors_count               = $errors_count;
		$json_data->success_message            = $success_message;
		$json_data->entity                     = $entity;

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
	private static function megaventory_create_json_for_initialize(
		$block,
		$count_of_entity,
		$page,
		$percent,
		$success_message
	) {

		$json_data = new \stdClass();

		$json_data->block           = $block;
		$json_data->percent_message = 'Current Sync Count: ' . $percent . '%';
		$json_data->success_message = $success_message;
		$json_data->count_of_entity = $count_of_entity;
		$json_data->page            = $page;

		return wp_json_encode( $json_data );
	}
}
