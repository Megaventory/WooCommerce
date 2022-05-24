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
	 * @param int     $user_id as user id.
	 * @param WP_User $old_user_data as user data.
	 * @return void
	 */
	public static function sync_on_profile_update( $user_id, $old_user_data ) {

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
	 * @param int      $client_id as client id.
	 * @param int|null $reassign ID of the user to whom posts will be automatically re-assigned (if one was selected).
	 * @return void
	 */
	public static function delete_client_handler( $client_id, $reassign ) {

		$client = \Megaventory\Models\Client::wc_find( $client_id );

		// Is not a client/subscriber.
		if ( null === $client ) {
			return;
		}

		$client->delete_client_in_megaventory();

	}
}

