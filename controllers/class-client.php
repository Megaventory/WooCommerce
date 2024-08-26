<?php
/**
 * Client controller.
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
 * Controller for Clients.
 */
class Client {

	/**
	 * Updates a client to Megaventory.
	 *
	 * @param int $user_id as user id.
	 * @return void
	 */
	public static function sync_on_profile_update( $user_id ) {

		$user = \Megaventory\Models\Client::wc_find( $user_id );

		if ( isset( $user ) ) {

			$user->mv_save();
		}
	}

	/**
	 * Insert a client to Megaventory.
	 *
	 * @param int $user_id as user id.
	 * @return void
	 */
	public static function sync_on_profile_create( $user_id ) {

		$user = \Megaventory\Models\Client::wc_find( $user_id );

		/* we want to save only customer/subscriber users in megaventory */

		if ( isset( $user ) ) {

			$user->mv_save();
		}
	}

	/**
	 * Delete client event handler.
	 *
	 * @param int $client_id as client id.
	 * @return void
	 */
	public static function delete_client_handler( $client_id ) {

		$client = \Megaventory\Models\Client::wc_find( $client_id );

		// Is not a client/subscriber.
		if ( null === $client ) {
			return;
		}

		$client->delete_client_in_megaventory();
	}

	/**
	 * Skip client synchronization.
	 */
	public static function megaventory_skip_clients_synchronization() {

		try {

			if ( isset( $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

				update_option( 'are_megaventory_clients_synchronized', 1 );

				$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

				$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

				$synchronized_message = 'Client synchronization was skipped on ' . $current_date;

				update_option( 'megaventory_clients_synchronized_time', $synchronized_message );
			}
		} catch ( \Throwable $ex ) {

			$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

			$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

			error_log( "\n" . $current_date . $ex->getMessage() . ' ' . $ex->getFile() . "({$ex->getLine()})", 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.
			error_log( "\n" . $current_date . $ex->getTraceAsString(), 3, MEGAVENTORY__PLUGIN_DIR . '/mv-exceptions.log' ); // @codingStandardsIgnoreLine.
		}

		wp_send_json_success( true );
		wp_die();
	}
}
