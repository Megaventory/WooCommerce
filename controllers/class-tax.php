<?php
/**
 * Tax controller.
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
 * Controller for Taxes.
 */
class Tax {

	/**
	 * Define the woocommerce_tax_rate_added / woocommerce_tax_rate_updated callback.
	 *
	 * @param int    $tax_rate_id as tax id.
	 * @param double $tax_rate as tax rate.
	 * @return void
	 */
	public static function on_tax_update( $tax_rate_id, $tax_rate ) {

		$tax = \Megaventory\Models\Tax::wc_find_tax( $tax_rate_id );
		if ( ! $tax ) {
			return;
		}

		$mv_tax = \Megaventory\Models\Tax::mv_find_by_name_and_rate( $tax->name, $tax->rate );

		if ( null !== $mv_tax ) {

			$tax->mv_id = $mv_tax->mv_id;

			$tax->wc_save();
		} else {
			/* creating new tax in MV */

			$tax->description = 'Woocommerce ' . $tax->type . ' tax';
			$tax->mv_save();

		}
	}
}

