<?php
/**
 * Included Product class, to help with Product Bundle logic.
 *
 * @package megaventory
 * @since 2.2.25
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2021 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Megaventory\Models;

/**
 * This class works as a model for a bundle's included products.
 */
class Included_Product {

	/**
	 * Product Quantity in bundle.
	 *
	 * @var int
	 */
	public $included_qty;

	/**
	 * Product Unit Price in bundle.
	 *
	 * @var double
	 */
	public $included_unit_price;

	/**
	 * Check if product is priced individually.
	 *
	 * @var bool
	 */
	public $is_priced_individually;

	/**
	 * Related Product object.
	 *
	 * @var Product
	 */
	public $related_product_obj;

	/** Included Product Constructor.
	 *
	 * @param int $related_prod_id The WC ID of the related product.
	 */
	public function __construct( $related_prod_id = 0 ) {

		$this->related_product_obj = $related_prod_id > 0 ? Product::wc_find_product( $related_prod_id ) : null;

	}

	/** Create an Included_Product from a WooCommerce Bundled Item.
	 *
	 * @param WC_Bundled_Item $wc_bundled_item A Woo Commerce Bundled Item.
	 * @return Included_Product
	 */
	public static function from_wc_bundled_item( $wc_bundled_item ) {

		$incl_product = new Included_Product( $wc_bundled_item->product->get_id() ); // Does not check if its a variable product!

		$incl_product->is_priced_individually = $wc_bundled_item->is_priced_individually();

		$incl_product->included_qty = $wc_bundled_item->get_quantity( 'min' );

		if ( $incl_product->is_priced_individually ) {

			$incl_product->included_unit_price = $wc_bundled_item->get_price( 'min', false, 1 );

		} else {

			$incl_product->included_unit_price = 0.0;

		}

		return $incl_product;
	}

}
