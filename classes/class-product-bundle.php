<?php
/**
 * Product Bundle class, which extends Product to help with Product Bundle logic.
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
 * This class works as a model for a product bundles and extends the base Product class.
 */
class Product_Bundle extends Product {

	const MV_BUNDLE_TYPE_ENUM = 'ProductBundle';

	/**
	 * Product Bundle included products list.
	 *
	 * @var Included_Product[]
	 */
	public $included_products;

	/**
	 * Underlying WC Product Bundle
	 *
	 * @var WC_Product_Bundle
	 */
	public $wc_bundle_prod;

	/** Product Bundle Constructor. Does not fill included_products.
	 *
	 * @param WC_Product_Bundle|null $wc_product_bundle a WooCommerce Product Bundle.
	 */
	public function __construct( $wc_product_bundle = null ) {

		parent::__construct();

		if ( null !== $wc_product_bundle ) {

			$this->wc_bundle_prod = $wc_product_bundle;
			$this->sku            = $wc_product_bundle->get_sku();
			$this->description    = $wc_product_bundle->get_short_description();

		}

		$this->included_products = array();
		$this->regular_price     = 0.0;
		$this->sale_price        = 0.0;
		$this->mv_type           = self::MV_BUNDLE_TYPE_ENUM;

	}

	/** Returns a Product_Bundle with the included_products property filled.
	 *
	 * @param WC_Product_Bundle $wc_product_bundle a WooCommerce Product Bundle.
	 * @return Product_Bundle
	 */
	public static function create_new_with_included_products( $wc_product_bundle ) {

		$prod_bundle = new Product_Bundle( $wc_product_bundle );

		foreach ( $wc_product_bundle->get_bundled_items() as $bundled_item ) {

			$prod_bundle->included_products[] = Included_Product::from_wc_bundled_item( $bundled_item );

		}

		return $prod_bundle;

	}

	/**
	 * Save Product Bundle to Megaventory.
	 *
	 * @return array
	 */
	public function mv_save_bundle() {

		if ( ! wp_strip_all_tags( $this->description ) ) {

			$this->log_error( 'Product Bundle not saved to Megaventory', 'Short description cannot be empty', -1, 'error', '' );
			return false;
		}
		if ( ! $this->sku ) {
			$this->log_error( 'Product Bundle not saved to Megaventory', 'SKU cannot be empty', -1, 'error', '' );
			return false;
		}

		$urljson      = \Megaventory\API::get_url_for_call( MV_Constants::BUNDLE_UPDATE );
		$json_request = $this->generate_bundle_update_json();

		if ( ! $json_request ) {
			return false;
		}

		$data = \Megaventory\API::send_request_to_megaventory( $urljson, $json_request );

		/*if product didn't save in Megaventory it will return an error code != 0*/

		if ( '0' !== ( $data['ResponseStatus']['ErrorCode'] ) ) {

			/* the only case we will not log an error is the case that the product is already deleted in Megaventory */

			if ( 'ProductSKUAlreadyDeleted' !== $data['InternalErrorCode'] ) {

				$internal_error_code = ' [' . $data['InternalErrorCode'] . ']';

				$this->log_error( 'Product Bundle not saved to Megaventory ' . $internal_error_code, $data['ResponseStatus']['Message'], -1, 'error', $data['json_object'] );

				return false;
			}

			$this->mv_id = $data['entityID'];
			$url         = \Megaventory\API::get_url_for_call( MV_Constants::BUNDLE_UNDELETE );

			$params = array(
				'ProductBundleIDToUndelete' => $this->mv_id,
			);

			$undelete_data = \Megaventory\API::send_request_to_megaventory( $url, $params );

			if ( array_key_exists( 'InternalErrorCode', $undelete_data ) ) {
				$this->log_error( 'Product is deleted. Undelete failed', $undelete_data['ResponseStatus']['Message'], -1, 'error', $data['json_object'] );
				return false;
			}

			return $this->mv_save_bundle();
		}

		/*Otherwise the product will either be created or updated.*/

		$product_exists = ( null === $this->mv_id || 0 === $this->mv_id );
		$action         = ( $product_exists ? 'created' : 'updated' );

		$this->log_success( $action, 'product successfully ' . $action . ' in Megaventory', 1 );

		update_post_meta( $this->wc_id, 'mv_id', $data['ProductBundleDetails']['BundleProductID'] );

		$this->mv_id = $data['ProductBundleDetails']['BundleProductID'];

		return $data['ProductBundleDetails'];
	}

	/**
	 * Create a json.
	 *
	 * @return array
	 */
	public function generate_bundle_update_json() {

		$action = 'InsertOrUpdateNonEmptyFields';

		$special_characters = array( '?', '$', '@', '!', '*', '#' ); // Special characters that need to be removed in order to be accepted by Megaventory.

		$bundle_update_object     = array();
		$bundle_object            = array();
		$bundle_included_products = array();

		$base_price = round( doubleval( $this->wc_bundle_prod->get_price() ), 4, PHP_ROUND_HALF_UP );

		$num_of_included_products = count( $this->included_products );

		$price_increase_per_row = 0.0;

		if ( $base_price > 0.0 ) {

			$price_increase_per_row = round( doubleval( bcdiv( $base_price, $num_of_included_products, 6 ) ), 4, PHP_ROUND_HALF_UP );

		}

		foreach ( $this->included_products as $incl_prod ) {

			$included_object = array();

			if ( $price_increase_per_row > 0.0 ) {

				// bc functions accept strings instead of floats but php seems to handle the conversion so i dont know if it needs to be explicit.
				$unit_price_increase_for_this_sku = round( doubleval( bcdiv( $price_increase_per_row, $incl_prod->included_qty, 6 ) ), 4, PHP_ROUND_HALF_UP );

				$incl_prod->included_unit_price = round( doubleval( bcadd( $incl_prod->included_unit_price, $unit_price_increase_for_this_sku, 6 ) ), 4, PHP_ROUND_HALF_UP );
			}

			if ( $incl_prod->related_product_obj->mv_id <= 0 ) {
				$this->log_error( 'Product Bundle not saved to Megaventory', 'Included Product does not exist in Megaventory: ' . $incl_prod->related_product_obj->sku, -1, 'error', '' );
				return false; // todo.
			}

			$included_object['productid']        = $incl_prod->related_product_obj->mv_id;
			$included_object['productsku']       = '';
			$included_object['quantityinbundle'] = $incl_prod->included_qty;
			$included_object['priceinbundle']    = $incl_prod->included_unit_price;

			$bundle_included_products[] = $included_object;
		}

		$bundle_object['bundleproductid']          = empty( $this->mv_id ) ? '' : $this->mv_id;
		$bundle_object['bundleproductsku']         = $this->sku;
		$bundle_object['bundleproductdescription'] = mb_substr( wp_strip_all_tags( str_replace( $special_characters, ' ', $this->description ) ), 0, 400 );
		$bundle_object['includedproducts']         = $bundle_included_products;

		if ( ! empty( $this->ean ) ) {
			$bundle_object['bundleproductbarcode'] = $this->ean;
		}

		$bundle_update_object['productbundledetails']                  = $bundle_object;
		$bundle_update_object['mvrecordaction']                        = MV_Constants::MV_RECORD_ACTION['InsertOrUpdateNonEmptyFields'];
		$bundle_update_object['mvinsertupdatedeletesourceapplication'] = 'woocommerce';

		return $bundle_update_object;
	}

	/**
	 * Checks if a bundle includes variable products and
	 * appends all variations to the included items.
	 */
	public function check_and_append_all_bundled_variations() {

		/** Array of variable bundled items.
		 *
		 * @var WC_Bundled_Item[] $variable_bundled_items
		 */
		$variable_bundled_items = array_filter(
			$this->wc_bundle_prod->get_bundled_items(),
			function( $prod ) {

				/** Bundle Item
				 *
				 * @var WC_Bundled_Item $prod
				 */
				return $prod->product->get_type() === 'variable';
			}
		);

		if ( empty( $variable_bundled_items ) ) {
			return;
		}

		/** Array of non variable bundle products
		 *
		 * WC_Bundled_Item[] $non_variable_bundled
		 */
		$non_variable_bundled = array_filter(
			$this->wc_bundle_prod->get_bundled_items(),
			function( $prod ) {

				/** Bundle Item
				 *
				 * @var WC_Bundled_Item $prod
				 */
				return $prod->product->get_type() !== 'variable';
			}
		);

		$this->included_products = array();

		foreach ( $variable_bundled_items as $variable_bundled ) {

			/** Array of variable bundle product
			 *
			 * @var array[] $avail_variations
			 */
			$avail_variations = $variable_bundled->get_product_variations();

			if ( $variable_bundled->has_filtered_variations() ) {

				$variation_ids = $variable_bundled->get_filtered_variations();

				$avail_variations = array_filter(
					$avail_variations,
					function( $variation ) use ( $variation_ids ) {

						/** Array
						 *
						 * @var array $variation
						 */
						return in_array( (int) $variation['variation_id'], $variation_ids, true );
					}
				);
			}

			foreach ( $avail_variations as $prod_variation ) {

				$incl_product = new Included_Product( $prod_variation['variation_id'] );

				$incl_product->is_priced_individually = $variable_bundled->is_priced_individually();

				$incl_product->included_qty = $variable_bundled->get_quantity( 'min' );

				if ( $incl_product->is_priced_individually ) {

					$incl_product->included_unit_price = $variable_bundled->get_price( 'min', false, 1 );

				} else {

					$incl_product->included_unit_price = 0.0;

				}

				$this->included_products[] = $incl_product;

			}
		}

		foreach ( $non_variable_bundled as $non_variable_prod_in_parent ) {

			$this->included_products[] = Included_Product::from_wc_bundled_item( $non_variable_prod_in_parent );

		}
	}

	/** Get all product Bundles and push them to Megaventory
	 *
	 * @param int $ref_successes success count by reference.
	 * @param int $ref_errors errors count by reference.
	 */
	public static function push_product_bundles( &$ref_successes, &$ref_errors ) {

		$wc_product_bundles = self::wc_get_all_product_bundles();

		foreach ( $wc_product_bundles as $bundle ) {

			$bundle->check_and_append_all_bundled_variations();

			$flag = $bundle->mv_save_bundle();

			if ( null !== $flag ) {

				$flag ? $ref_successes++ : $ref_errors++;
			}
		}

	}

	/**
	 * Gets all WC bundle Products converted to Product_Bundle class.
	 *
	 * @return Product_Bundle[]
	 */
	public static function wc_get_all_product_bundles() {

		$all_product_bundles = array();

		$args = array(
			'type'  => array( 'bundle' ),
			'limit' => -1,
		);

		$all_w_c_product_bundles = wc_get_products( $args );

		/** Define iteration variable type
		 *
		 * @var WC_Product_Bundle $wc_product_bundle
		 */
		foreach ( $all_w_c_product_bundles as $wc_product_bundle ) {

			$product = self::wc_convert( $wc_product_bundle );

			array_push( $all_product_bundles, $product );

		}

		return $all_product_bundles;
	}

	/**
	 * Get Product from WooCommerce with id.
	 *
	 * @param int $id product id.
	 * @return Product_Bundle|null
	 */
	public static function wc_find_product_bundle( $id ) {

		$wc_prod = wc_get_product( $id );

		if ( empty( $wc_prod ) ) {
			return null;
		}

		if ( 'bundle' !== $wc_prod->get_type() ) {

			return null;

		} else {

			$product = self::wc_convert( $wc_prod );
			return $product;
		}
	}

	/**
	 * Get Product Bundle from Megaventory by sku.
	 *
	 * @param string $sku as product bundle's sku.
	 * @return Product_Bundle|null
	 */
	public static function mv_find_bundle_by_sku( $sku ) {

		$product_get_body = array(
			'ProductBundleSKU' => $sku,
		);

		$url  = \Megaventory\API::get_url_for_call( MV_Constants::BUNDLE_GET );
		$data = \Megaventory\API::send_request_to_megaventory( $url, $product_get_body );

		if ( ! array_key_exists( 'mvProductBundle', $data ) ) {
			return null; // No such sku.
		}

		return self::mv_convert_bundle( $data['mvProductBundle'] );
	}

	/**
	 * Converts a Megaventory Product Bundle to Product_Bundle.
	 *
	 * @param array $mv_prod_bundle as Megaventory product bundle.
	 * @return Product_Bundle
	 */
	private static function mv_convert_bundle( $mv_prod_bundle ) {

		$product_bundle = new Product_Bundle();

		$product_bundle->mv_id       = $mv_prod_bundle['BundleProductID'];
		$product_bundle->mv_type     = self::MV_BUNDLE_TYPE_ENUM;
		$product_bundle->sku         = $mv_prod_bundle['BundleProductSKU'];
		$product_bundle->ean         = $mv_prod_bundle['BundleProductBarcode'];
		$product_bundle->description = $mv_prod_bundle['BundleProductDescription'];

		$included_products_array = $mv_prod_bundle['IncludedProducts'];

		foreach ( $included_products_array as $incl_prod ) {

			$included_row = new Included_Product();

			$related_product = new Product();

			$related_product->mv_id = $incl_prod['ProductID'];
			$related_product->sku   = $incl_prod['ProductSKU'];

			$included_row->related_product_obj = $related_product;
			$included_row->included_qty        = $incl_prod['QuantityInBundle'];
			$included_row->included_unit_price = $incl_prod['PriceInBundle'];

			$product_bundle->included_products[] = $included_row;

		}

		return $product_bundle;
	}

	/**
	 * Converts a WC_Product_Bundle to ProductBundle.
	 *
	 * @param WC_Product_Bundle $wc_prod as WooCommerce product bundle.
	 * @return Product_Bundle
	 */
	private static function wc_convert( $wc_prod ) {

		$post_meta = get_post_meta( $wc_prod->get_id() );

		$prod = self::create_new_with_included_products( $wc_prod );

		$id          = $wc_prod->get_id();
		$prod->wc_id = $id;
		$prod->mv_id = (int) ( ( empty( $post_meta['mv_id'][0] ) ) ? 0 : $post_meta['mv_id'][0] );

		$prod->name             = $wc_prod->get_name();
		$prod->long_description = $wc_prod->get_description();
		$prod->description      = $wc_prod->get_title();

		$prod->type = $wc_prod->get_type();

		$prod->sku = $wc_prod->get_sku();

		$img = wp_get_attachment_image_src( get_post_thumbnail_id( $id ) );

		if ( false !== $img && $img[0] ) {

			$prod->image_url = $img[0];
		}

		return $prod;
	}

	/**
	 * Get the count of all WooCommerce Product Buncles.
	 *
	 * @return int
	 */
	public static function wc_get_all_woocommerce_bundles_count() {

		$args = array(
			'type'  => array( 'bundle' ),
			'limit' => -1,
		);

		$all_wc_products = wc_get_products( $args );

		$all_wc_products = self::get_woocommerce_products_with_unique_sku( $all_wc_products, 'id' );

		return count( $all_wc_products );
	}
}
