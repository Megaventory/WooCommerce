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
}
