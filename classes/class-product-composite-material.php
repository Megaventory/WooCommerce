<?php
/**
 * Product Composite Material class, to help with Composite Product logic.
 *
 * @package megaventory
 * @since 2.7.0
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
 * This class works as a model for a composite product material.
 */
class Product_Composite_Material {

	/**
	 * The materials of the composite product.
	 *
	 * @var Product $product The product of the material.
	 */
	public $product;

	/**
	 * The quantity of the material.
	 *
	 * @var double $quantity The quantity of the material.
	 */
	public $quantity;

	/**
	 * Product_Composite constructor.
	 *
	 * @param Product $product The composited product transformed into a Product object.
	 * @param double  $quantity The quantity of the material.
	 */
	public function __construct( $product, $quantity ) {
		$this->product  = $product;
		$this->quantity = $quantity;
	}
}
