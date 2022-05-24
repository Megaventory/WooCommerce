<?php
/**
 * Logs controller.
 *
 * @package megaventory
 * @since 2.2.0
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

/* This file contains helper methods for logs */

namespace Megaventory\Controllers;

require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-success.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-error.php';

/**
 * Logs controller
 */
class Logs {

	/**
	 * Delete latest success logs.
	 *
	 * @return void
	 */
	public static function megaventory_delete_success_logs() {

		if ( isset( $_POST['ids'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$ids_to_delete_json = sanitize_text_field( wp_unslash( $_POST['ids'] ) );

			$ids_to_delete = json_decode( $ids_to_delete_json, true );

			\Megaventory\Models\MVWC_Success::delete( $ids_to_delete );

		}
		wp_die();
	}

	/**
	 * Delete latest error logs.
	 *
	 * @return void
	 */
	public static function megaventory_delete_error_logs() {

		if ( isset( $_POST['ids'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$ids_to_delete_json = sanitize_text_field( wp_unslash( $_POST['ids'] ) );

			$ids_to_delete = json_decode( $ids_to_delete_json, true );

			\Megaventory\Models\MVWC_Error::delete( $ids_to_delete );

		}
		wp_die();
	}
}

