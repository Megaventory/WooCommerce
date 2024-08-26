<?php
/**
 * Coupon controller.
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
 * Controller for Coupons.
 */
class Coupon {

	/**
	 * Define the woocommerce_create_coupon/woocommerce_update_coupon callback.
	 *
	 * @param int $coupon_id as tax id.
	 * @return void
	 */
	public static function on_coupon_update( $coupon_id ) {

		$coupon = \Megaventory\Models\Coupon::wc_find_coupon( $coupon_id );

		if ( null === $coupon ) {
			return;
		}

		if ( 'percent' !== $coupon->type ) {
			return;
		}

		$coupon->description = 'Woocommerce ' . $coupon->type . ' coupon';
		$coupon->mv_save();
	}

	/**
	 * Skip coupons synchronization.
	 */
	public static function megaventory_skip_coupons_synchronization() {

		try {

			if ( isset( $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

				update_option( 'are_megaventory_coupons_synchronized', 1 );

				$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

				$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

				$synchronized_message = 'Coupon synchronization was skipped on ' . $current_date;

				update_option( 'megaventory_coupons_synchronized_time', $synchronized_message );
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
