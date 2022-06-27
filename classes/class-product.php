<?php
/**
 * Product class.
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

namespace Megaventory\Models;

use WC_PB_DB_Sync;
use WC_Product;

/**
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'class-api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/class-address.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mv-constants.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-error.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-errors.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-successes.php';
/**
 * This class works as a model for a product
 * It holds all important attributes of a Megaventory/WooCommerce product
 * WC and Megaventory will store same products at different IDs. Those IDs can be accessed separately
 * SKU is more important than ID and can be used to compare products
 */
class Product {

	/**
	 * WooCommerce product id.
	 *
	 * @var int
	 */
	public $wc_id;

	/**
	 * Megaventory product id.
	 *
	 * @var int
	 */
	public $mv_id;

	/**
	 * Product name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Product sku.
	 *
	 * @var string
	 */
	public $sku;

	/**
	 * Product barcode.
	 *
	 * @var string
	 */
	public $ean;

	/**
	 * Product description.
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Product long description.
	 *
	 * @var string
	 */
	public $long_description;

	/**
	 * Product cost.
	 *
	 * @var int
	 */
	public $unit_cost;

	/**
	 * Product category.
	 *
	 * @var string
	 */
	public $category;

	/**
	 * Product type.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Product image path.
	 *
	 * @var string
	 */
	public $image_url;

	/**
	 * Product regular price.
	 *
	 * @var double
	 */
	public $regular_price;

	/**
	 * Product sales price.
	 *
	 * @var double
	 */
	public $sale_price;

	/**
	 * Product purchase price.
	 *
	 * @var double
	 */
	public $purchase_price;

	/**
	 * Product sales status.
	 *
	 * @var string
	 */
	public $sale_active;

	/**
	 * Product length dimension.
	 *
	 * @var double
	 */
	public $length;

	/**
	 * Product breadth dimension.
	 *
	 * @var double
	 */
	public $breadth;

	/**
	 * Product height dimension.
	 *
	 * @var double
	 */
	public $height;

	/**
	 * Product weight dimension.
	 *
	 * @var double
	 */
	public $weight;

	/**
	 * Product version.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Product stock.
	 *
	 * @var double
	 */
	public $available_wc_stock;

	/**
	 * Product megaventory quantity.
	 *
	 * @var double|array
	 */
	public $mv_qty;

	/**
	 * Product Megaventory type.
	 *
	 * @var string
	 */
	public $mv_type;

	/**
	 * Product variations.
	 *
	 * @var array
	 */
	public $variations;

	/**
	 * Product errors.
	 *
	 * @var MVWC-Errors
	 */
	public $errors;

	/**
	 * Product successes.
	 *
	 * @var MVWC_Successes
	 */
	public $successes;

	/*API Calls*/

	/**
	 * Update Product API call.
	 *
	 * @var string
	 */
	private static $product_update_call = 'ProductUpdate';

	/**
	 * Undelete Product API call.
	 *
	 * @var string
	 */
	private static $product_undelete_call = 'ProductUndelete';

	/**
	 * Get Product stock API call.
	 *
	 * @var string
	 */
	private static $product_stock_call = 'InventoryLocationStockGet';

	/**
	 * Get Inventory Location API call.
	 *
	 * @var string
	 */
	private static $inventory_get_call = 'InventoryLocationGet';

	/**
	 * Get Category API call.
	 *
	 * @var string
	 */
	private static $category_get_call = 'ProductCategoryGet';

	/**
	 * Update Category API call.
	 *
	 * @var string
	 */
	private static $category_update_call = 'ProductCategoryUpdate';

	/**
	 * Delete Category API call.
	 *
	 * @var string
	 */
	private static $category_delete_call = 'ProductCategoryDelete';

	/**
	 * Undelete Category API call.
	 *
	 * @var string
	 */
	private static $category_undelete_call = 'ProductCategoryUndelete';

	/**
	 * MV Physical Stock QTY array key
	 */
	const MV_PHYSICAL_STOCK_QTY_KEY = 1;

	/**
	 * MV Non Allocated WO array key
	 */
	const MV_NON_ALLOCATED_WO_QTY_KEY = 3;

	/**
	 * MV Non Shipped array key
	 */
	const MV_NON_SHIPPED_QTY_KEY = 4;

	/**
	 * Product Constructor.
	 */
	public function __construct() {

		$this->errors    = new MVWC_Errors();
		$this->successes = new MVWC_Successes();

	}

	/**
	 * Get Product Errors.
	 *
	 * @return MVWC_Errors
	 */
	public function errors() {

		return $this->errors;
	}

	/**
	 * Get product Successes.
	 *
	 * @return MVWC_Successes
	 */
	public function successes() {

		return $this->successes;
	}

	/**
	 * Logs Product Errors.
	 *
	 * @param string $problem as problem type.
	 * @param string $full_msg as error's full message.
	 * @param int    $code as error's code.
	 * @param string $type error type default 'error'.
	 * @param string $json_object as string.
	 * @return void
	 */
	public function log_error( $problem, $full_msg, $code, $type = 'error', $json_object ) {

		$args = array(
			'entity_id'   => array(
				'wc' => $this->wc_id,
				'mv' => $this->mv_id,
			),
			'entity_name' => ( empty( $this->sku ) ) ? $this->name : $this->sku,
			'problem'     => $problem,
			'full_msg'    => $full_msg,
			'error_code'  => $code,
			'json_object' => $json_object,
			'type'        => $type,
		);
		$this->errors->log_error( $args );

	}

	/**
	 * Logs Product Successes.
	 *
	 * @param string $transaction_status as success status.
	 * @param string $full_msg as success full message.
	 * @param int    $code as message code.
	 * @return void
	 */
	public function log_success( $transaction_status, $full_msg, $code ) {

		$args = array(
			'entity_id'          => array(
				'wc' => $this->wc_id,
				'mv' => $this->mv_id,
			),
			'entity_type'        => 'product',
			'entity_name'        => ( empty( $this->sku ) ) ? $this->name : $this->sku,
			'transaction_status' => $transaction_status,
			'full_msg'           => $full_msg,
			'success_code'       => $code,
		);

		$this->successes->log_success( $args );
	}

	/**
	 * Gets all simple and variation Products converted to Product class.
	 *
	 * @param int $limit number of products to retrieve.
	 * @param int $page pagination.
	 * @return Product[]
	 */
	public static function wc_get_products_in_batches( $limit, $page ) {

		$all_products = array();

		$args        = array(
			'type'  => array( 'simple', 'variable' ),
			'limit' => $limit,
			'page'  => $page,
		);
		$wc_products = wc_get_products( $args ); // May throw out of memory if the server has limited memory.

		foreach ( $wc_products as $wc_product ) {

			if ( 'simple' === $wc_product->get_type() ) {

				$product = self::wc_convert( $wc_product );
				array_push( $all_products, $product );

			} elseif ( 'variable' === $wc_product->get_type() ) {

				$products     = self::wc_get_variations( $wc_product );
				$all_products = array_merge( $all_products, $products );
			}
		}

		$all_products = self::get_products_with_unique_sku( $all_products );

		return $all_products;
	}

	/**
	 * Gets all simple and variation Products converted to Product class.
	 *
	 * @return Product[]
	 */
	public static function wc_get_all_products() {

		$limit        = -1;
		$all_products = array();

		$args        = array(
			'type'  => array( 'simple', 'variable' ),
			'limit' => $limit,
		);
		$wc_products = wc_get_products( $args ); // May throw out of memory if the server has limited memory.

		foreach ( $wc_products as $wc_product ) {

			if ( 'simple' === $wc_product->get_type() ) {

				$product = self::wc_convert( $wc_product );
				array_push( $all_products, $product );

			} elseif ( 'variable' === $wc_product->get_type() ) {

				$products     = self::wc_get_variations( $wc_product );
				$all_products = array_merge( $all_products, $products );
			}
		}

		$all_products = self::get_products_with_unique_sku( $all_products );

		return $all_products;
	}

	/**
	 * Get all simple and variations WooCommerce products.
	 *
	 * @return array of WC_Product_Simple and WC_Product_Variation
	 */
	public static function wc_get_all_woocommerce_products() {

		$args = array(
			'type'  => array( 'simple', 'variation' ),
			'limit' => -1,
		);

		$all_wc_products = wc_get_products( $args ); // May throw out of memory if the server has limited memory.

		$all_wc_products = self::get_woocommerce_products_with_unique_sku( $all_wc_products );

		return $all_wc_products;
	}

	/**
	 * Get the count of all simple and variations WooCommerce products.
	 *
	 * @return int
	 */
	public static function wc_get_all_woocommerce_products_count() {

		$args = array(
			'type'  => array( 'simple', 'variation' ),
			'limit' => -1,
		);

		$all_wc_products = wc_get_products( $args ); // May throw out of memory if the server has limited memory.

		$all_wc_products = self::get_woocommerce_products_with_unique_sku( $all_wc_products, 'id' );

		return count( $all_wc_products );
	}

	/**
	 * Get woocommerce products with unique SKUs from the result of wc_get_products
	 *
	 * @param array  $wc_products as array.
	 * @param string $all_or_id as string.
	 *
	 * @return array
	 */
	public static function get_woocommerce_products_with_unique_sku( array $wc_products, $all_or_id = 'all' ) {
		$results = array();

		foreach ( $wc_products as $wc_product ) {
			if ( ! array_key_exists( $wc_product->get_sku(), $results ) ) {
				if ( 'all' === $all_or_id ) {
					$results[ $wc_product->get_sku() ] = $wc_product;
				} else {
					$results[ $wc_product->get_sku() ] = $wc_product->get_id();
				}
			}
		}

		return array_values( $results );
	}

	/**
	 * Get products with unique SKUs from the result of wc_get_products
	 *
	 * @param Product[] $products as array.
	 * @param string    $all_or_id as string.
	 *
	 * @return array
	 */
	public static function get_products_with_unique_sku( array $products, $all_or_id = 'all' ) {
		$results = array();

		foreach ( $products as $product ) {
			if ( ! array_key_exists( $product->sku, $results ) ) {
				if ( 'all' === $all_or_id ) {
					$results[ $product->sku ] = $product;
				} else {
					$results[ $product->sku ] = $product->wc_id;
				}
			}
		}

		return array_values( $results );
	}

	/**
	 * Get all Products from Megaventory.
	 *
	 * @return array[Product]
	 */
	public static function mv_all() {

		$categories = self::mv_get_categories();
		$url        = \Megaventory\API::get_url_for_call( MV_Constants::PRODUCT_GET );
		$json_prod  = \Megaventory\API::perform_call_to_megaventory( $url );

		/* Map json to Product class. */

		$products = array();

		foreach ( $json_prod['mvProducts'] as $prod ) {

			$product = self::mv_convert( $prod, $categories );

			array_push( $products, $product );
		}

		return $products;
	}

	/**
	 * Get Product from WooCommerce with id.
	 *
	 * @param int $id product id.
	 * @return Product|null
	 */
	public static function wc_find_product( $id ) {

		$wc_prod = wc_get_product( $id );

		if ( empty( $wc_prod ) ) {
			return null;
		}

		if ( 'variation' === $wc_prod->get_type() ) {

			$wc_variable_id = $wc_prod->get_parent_id();
			$wc_variable    = wc_get_product( $wc_variable_id );

			$product = self::wc_variation_convert( $wc_prod, $wc_variable );
			return $product;

		} else {

			$product = self::wc_convert( $wc_prod );
			return $product;
		}
	}

	/**
	 * Get Product from Megaventory with id.
	 *
	 * @param int $id as product id.
	 * @return Product|null
	 */
	public static function mv_find( $id ) {

		$data = array(
			'Filters' => array(
				'FieldName'      => 'ProductID',
				'SearchOperator' => 'Equals',
				'SearchValue'    => $id,
			),
		);

		$url      = \Megaventory\API::get_url_for_call( MV_Constants::PRODUCT_GET );
		$response = \Megaventory\API::send_request_to_megaventory( $url, $data );

		if ( count( $response['mvProducts'] ) <= 0 ) {
			return null; // No such ID.
		}

		return self::mv_convert( $response['mvProducts'][0] );
	}

	/**
	 * Creates a default shipping product in Megaventory.
	 *
	 * @return bool
	 */
	public static function create_default_shipping() {

		$shipping_product              = new Product();
		$shipping_product->name        = 'shipping_product';
		$shipping_product->sku         = 'shipping';
		$shipping_product->mv_type     = 'Service';
		$shipping_product->description = 'shipping method';

		$megaventory_saved_product = $shipping_product->mv_save();

		if ( $megaventory_saved_product['ProductID'] ) {

			return true;
		}

		return false;
	}

	/**
	 * Create a service product on Megaventory that handles additional fees.
	 *
	 * @param string $sku as string.
	 *
	 * @return boolean
	 */
	public static function create_additional_fee_service( $sku = MV_Constants::DEFAULT_EXTRA_FEE_SERVICE_SKU ) {

		$additional_fee_product              = new Product();
		$additional_fee_product->name        = 'WooCommerce extra fees';
		$additional_fee_product->sku         = $sku;
		$additional_fee_product->mv_type     = 'Service';
		$additional_fee_product->description = 'Service for handling WooCommerce additional fees';

		$currently_saved_product_id = get_option( 'megaventory_extra_fee_product_id' );

		$currently_saved_sku = get_option( 'megaventory_extra_fee_sku' );

		if ( $currently_saved_sku === $sku && ! empty( $currently_saved_product_id ) ) {
			return true;
		}

		$additional_fee_product->mv_id = $currently_saved_product_id;

		$megaventory_saved_product = $additional_fee_product->mv_save( null, true );

		if ( empty( $megaventory_saved_product['ProductID'] ) ) {

			return false;
		}

		update_option( 'megaventory_extra_fee_product_id', $megaventory_saved_product['ProductID'] );
		update_option( 'megaventory_extra_fee_sku', $sku );

		return true;
	}

	/**
	 * Pull stock from Megaventory.
	 *
	 * @param int $starting_index as the position.
	 * @return array['starting_index','next_index','error_occurred','finished','message']
	 */
	public static function pull_stock( $starting_index ) {

		$return_values = array(
			'starting_index' => $starting_index,
			'next_index'     => 0,
			'error_occurred' => false,
			'finished'       => false,
			'message'        => '',
		);

		$all_simple_products_count = self::wc_get_all_woocommerce_products_count();

		$page = ( $starting_index / MV_Constants::SYNC_STOCK_FROM_MEGAVENTORY ) + 1; // starts from 1.

		$selected_products_to_sync_stock = self::wc_get_products_in_batches( MV_Constants::SYNC_STOCK_FROM_MEGAVENTORY, $page );

		$selected_products_to_sync_stock_count = count( $selected_products_to_sync_stock );

		if ( 0 === $selected_products_to_sync_stock_count ) {

			$return_values = array(
				'starting_index' => $starting_index,
				'next_index'     => -1,
				'error_occurred' => false,
				'finished'       => true,
				'message'        => 'Current synchronization: ' . $all_simple_products_count . ' of ' . $all_simple_products_count,
			);

			update_option( 'is_megaventory_stock_adjusted', 1 );

			$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

			$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

			$synchronized_message = 'Quantity adjusted from Megaventory on ' . $current_date;

			update_option( 'megaventory_stock_synchronized_time', $synchronized_message );

			return $return_values;
		}

		$filters = array();

		foreach ( $selected_products_to_sync_stock as $selected_product ) {

			if ( empty( $selected_product->sku ) || empty( $selected_product->mv_id ) ) {
				continue;
			}

			$filter = array(
				'AndOr'          => 'Or',
				'FieldName'      => 'productID',
				'SearchOperator' => 'Equals',
				'SearchValue'    => $selected_product->mv_id,
			);

			array_push( $filters, $filter );
		}
		// call to get stock information for ids.

		$stock_get_body = array(
			'Filters' => $filters,
		);

		$url      = \Megaventory\API::get_url_for_call( MV_Constants::INVENTORY_LOCATION_STOCK_GET );
		$response = \Megaventory\API::send_request_to_megaventory( $url, $stock_get_body );

		if ( '0' !== ( $response['ResponseStatus']['ErrorCode'] ) ) {

			$args = array(
				'type'        => 'error',
				'entity_name' => 'Stock Get',
				'entity_id'   => 0,
				'problem'     => 'Error on Stock Get, try again! If the error persists, contact Megaventory support.',
				'full_msg'    => $response['ResponseStatus']['Message'],
				'error_code'  => $response['ResponseStatus']['ErrorCode'],
				'json_object' => '',
			);

			$e = new MVWC_Error( $args );

			$return_values = array(
				'starting_index' => $starting_index,
				'next_index'     => 0,
				'error_occurred' => true,
				'finished'       => true,
				'message'        => '',
			);

			return $return_values;
		}

		$megaventory_product_stock_list = $response['mvProductStockList'];

		foreach ( $selected_products_to_sync_stock as $selected_product ) {

			if ( empty( $selected_product->sku ) || empty( $selected_product->mv_id ) ) {
				continue;
			}

			$index = array_search( $selected_product->mv_id, array_column( $megaventory_product_stock_list, 'productID' ), true );

			if ( false !== $index ) {

				$selected_product->update_stock_properties_from_location_stock_get( $megaventory_product_stock_list[ $index ] );

				$selected_product->update_stock();
			}
		}

		if ( MV_Constants::SYNC_STOCK_FROM_MEGAVENTORY > $selected_products_to_sync_stock_count ) {

			$return_values = array(
				'starting_index' => $starting_index,
				'next_index'     => -1,
				'error_occurred' => false,
				'finished'       => true,
				'message'        => 'Current synchronization: ' . ( $starting_index + $selected_products_to_sync_stock_count ) . ' of ' . $all_simple_products_count,
			);

			update_option( 'is_megaventory_stock_adjusted', 1 );

			$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

			$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

			$synchronized_message = 'Quantity adjusted from Megaventory on ' . $current_date;

			update_option( 'megaventory_stock_synchronized_time', $synchronized_message );

		} else {

			$return_values = array(
				'starting_index' => $starting_index,
				'next_index'     => $starting_index + MV_Constants::SYNC_STOCK_FROM_MEGAVENTORY,
				'error_occurred' => false,
				'finished'       => false,
				'message'        => 'Current synchronization: ' . ( $starting_index + $selected_products_to_sync_stock_count ) . ' of ' . $all_simple_products_count,
			);

		}

		return $return_values;
	}

	/**
	 * Generates stock information for product
	 *
	 * @param array $mv_product_stock_list as mvProductStockList.
	 * @return void
	 */
	public function update_stock_properties_from_location_stock_get( $mv_product_stock_list ) {

		/* sum product on hand in all inventories */
		$available_stock = 0;
		$mv_qty          = array();

		if ( null !== $mv_product_stock_list['mvStock'] ) {

			foreach ( $mv_product_stock_list['mvStock'] as $inventory ) {

				$mv_location_id_to_abbr = get_option( MV_Constants::MV_LOCATION_ID_TO_ABBREVIATION );

				if ( ! array_key_exists( $inventory['InventoryLocationID'], $mv_location_id_to_abbr ) ) {
					continue;
				}

				if ( Location::is_location_excluded( (int) $inventory['InventoryLocationID'] ) ) {
					continue;
				}

				$inventory_name = $mv_location_id_to_abbr[ $inventory['InventoryLocationID'] ];

				$physical        = $inventory['StockPhysical'];
				$on_hand         = $inventory['StockOnHand'];
				$non_shipped     = $inventory['StockNonShipped'];
				$non_allocated   = $inventory['StockNonAllocatedWOs'];
				$non_received_po = $inventory['StockNonReceivedPOs'];
				$non_received_wo = $inventory['StockNonReceivedWOs'];

				$string  = '' . $inventory_name;
				$string .= ';' . $physical;
				$string .= ';' . $on_hand;
				$string .= ';' . $non_shipped;
				$string .= ';' . $non_allocated;
				$string .= ';' . $non_received_po;
				$string .= ';' . $non_received_wo;

				$mv_qty[ $inventory['InventoryLocationID'] ] = $string;

				$available_stock += ( (int) $physical - (int) $non_allocated - (int) $non_shipped );
			}
		} else {

			$this->mv_qty = 'no stock';

			return;
		}

		$this->available_wc_stock = $available_stock;
		$this->mv_qty             = $mv_qty;
	}

	/**
	 * Generates stock information for product
	 *
	 * @param array $mv_stock_details as mvStock.
	 * @return void
	 */
	public function update_stock_properties_from_stock_update( $mv_stock_details ) {

		$available_stock = 0;
		$mv_qty          = $this->mv_qty;

		if ( ! is_array( $mv_qty ) ) {
			$mv_qty = array();
		}

		$stockqty               = $mv_stock_details['stock_data']['stockqty'];
		$stockqtyonhold         = $mv_stock_details['stock_data']['stockqtyonhold'];
		$stockalarmqty          = $mv_stock_details['stock_data']['stockalarmqty'];
		$stocknonshippedqty     = $mv_stock_details['stock_data']['stocknonshippedqty'];
		$stocknonreceivedqty    = $mv_stock_details['stock_data']['stocknonreceivedqty'];
		$stockwipcomponentqty   = $mv_stock_details['stock_data']['stockwipcomponentqty'];
		$stocknonreceivedwoqty  = $mv_stock_details['stock_data']['stocknonreceivedwoqty'];
		$stocknonallocatedwoqty = $mv_stock_details['stock_data']['stocknonallocatedwoqty'];

		$total = $stockqty;

		/**
		 * Megaventory code for on hand quantity.
		 * newS.StockOnHand = (newS.StockPhysical + newS.StockNonReceivedPOs + newS.StockNonReceivedWOs) _
		 *                   - (newS.StockNonShipped + newS.StockNonAllocatedWOs + newS.StockOnHold) 'For now the StockOnHold = 0. If we decide to add StockOnHold on Picking then, this will change
		 */
		$on_hand         = $stockqty + $stocknonreceivedqty + $stocknonreceivedwoqty - $stocknonshippedqty - $stocknonallocatedwoqty - $stockqtyonhold;
		$non_shipped     = $stocknonshippedqty;
		$non_allocated   = $stocknonallocatedwoqty;
		$non_received_po = $stocknonreceivedqty;
		$non_received_wo = $stocknonreceivedwoqty;

		$mv_location_id_to_abbr = get_option( MV_Constants::MV_LOCATION_ID_TO_ABBREVIATION );

		$inventory_name = $mv_location_id_to_abbr[ $mv_stock_details['inventory_id'] ];

		$string  = '' . $inventory_name;
		$string .= ';' . $total;
		$string .= ';' . $on_hand;
		$string .= ';' . $non_shipped;
		$string .= ';' . $non_allocated;
		$string .= ';' . $non_received_po;
		$string .= ';' . $non_received_wo;

		// For old accounts that didn't have the inventory_id as key of the array.
		foreach ( $mv_qty as $key => $value ) {

			if ( ( substr( $value, 0, strlen( $inventory_name ) ) === $inventory_name ) && ( $key !== $mv_stock_details['inventory_id'] ) ) {

				unset( $mv_qty[ $key ] );
			}
		}

		$mv_qty[ $mv_stock_details['inventory_id'] ] = $string;

		foreach ( $mv_qty as $key => $value ) {

			$qty = explode( ';', $value );

			$available_stock += ( $qty[ self::MV_PHYSICAL_STOCK_QTY_KEY ] - $qty[ self::MV_NON_ALLOCATED_WO_QTY_KEY ] - $qty[ self::MV_NON_SHIPPED_QTY_KEY ] );
		}

		$this->available_wc_stock = $available_stock;
		$this->mv_qty             = $mv_qty;
	}


	/**
	 * Get Inventory Name from Megaventory.
	 *
	 * @param int     $id as Inventory id.
	 * @param boolean $abbrev boolean to return Inventory's abbreviation or name.
	 * @return null|string
	 */
	public static function get_inventory_name( $id, $abbrev = false ) {

		$data = array(
			'Filters' => array(
				'FieldName'      => 'InventoryLocationID',
				'SearchOperator' => 'Equals',
				'SearchValue'    => $id,
			),
		);

		$url      = \Megaventory\API::get_url_for_call( self::$inventory_get_call );
		$response = \Megaventory\API::send_request_to_megaventory( $url, $data );

		if ( count( $response['mvInventoryLocations'] ) <= 0 ) { // Not found.
			return null;
		}

		if ( $abbrev ) {
			return $response['mvInventoryLocations'][0]['InventoryLocationAbbreviation'];
		} else {
			return $response['mvInventoryLocations'][0]['InventoryLocationName'];
		}
	}

	/**
	 * Get Product from Megaventory by sku.
	 *
	 * @param string $sku as product's sku.
	 * @return Product|null
	 */
	public static function mv_find_by_sku( $sku ) {

		$filters = array(
			'0' => array(
				'FieldName'      => 'ProductSKU',
				'SearchOperator' => 'Equals',
				'SearchValue'    => $sku,
			),
		);

		$product_get_body = array(
			'showDeleted' => 'showAllDeletedAndUndeleted',
			'Filters'     => $filters,
		);

		$url  = \Megaventory\API::get_url_for_call( MV_Constants::PRODUCT_GET );
		$data = \Megaventory\API::send_request_to_megaventory( $url, $product_get_body );

		if ( count( $data['mvProducts'] ) <= 0 ) {
			return null; // No such sku.
		}
		return self::mv_convert( $data['mvProducts'][0] );
	}

	/**
	 * Converts a mvProduct to Product.
	 *
	 * @param array     $mv_prod as Megaventory product.
	 * @param null|bool $categories search with categories.
	 * @return Product
	 */
	private static function mv_convert( $mv_prod, $categories = null ) {
		/*
			Passing categories makes things faster and requires less API calls.
			always use $categories when using this function in a loop with many users
		*/

		if ( null === $categories ) {

			$categories = self::mv_get_categories();
		}

		$product = new Product();

		$product->mv_id            = $mv_prod['ProductID'];
		$product->type             = $mv_prod['ProductType'];
		$product->mv_type          = $mv_prod['ProductType'];
		$product->sku              = $mv_prod['ProductSKU'];
		$product->ean              = ( isset( $mv_prod['ProductEAN'] ) ? $mv_prod['ProductEAN'] : '' );
		$product->description      = $mv_prod['ProductDescription'];
		$product->purchase_price   = $mv_prod['ProductPurchasePrice'];
		$product->long_description = $mv_prod['ProductLongDescription'];
		$product->image_url        = $mv_prod['ProductImageURL'];

		$product->regular_price = $mv_prod['ProductSellingPrice'];
		$product->category      = ( isset( $categories[ $mv_prod['ProductCategoryID'] ] ) ? $categories[ $mv_prod['ProductCategoryID'] ] : null );

		$product->weight  = $mv_prod['ProductWeight'];
		$product->length  = $mv_prod['ProductLength'];
		$product->breadth = $mv_prod['ProductBreadth'];
		$product->height  = $mv_prod['ProductHeight'];

		$product->version = $mv_prod['ProductVersion'];

		return $product;
	}

	/**
	 * Converts a WC_Product to Product.
	 *
	 * @param WC_Product $wc_prod as WooCommerce product.
	 * @return Product
	 */
	private static function wc_convert( $wc_prod ) {

		$post_meta = get_post_meta( $wc_prod->get_id() );

		$prod = new Product();

		$id          = $wc_prod->get_id();
		$prod->wc_id = $id;
		$prod->mv_id = (int) ( ( empty( $post_meta['mv_id'][0] ) ) ? 0 : $post_meta['mv_id'][0] );

		$prod->name             = $wc_prod->get_name();
		$prod->long_description = $wc_prod->get_description();
		$prod->description      = $wc_prod->get_title();

		$prod->type = $wc_prod->get_type();

		$prod->sku = $wc_prod->get_sku();

		$purchase_price = get_post_meta( $id, 'purchase_price', true );

		if ( ! isset( $purchase_price ) ) {
			$purchase_price = 0;
		} else {
			$purchase_price = (float) str_replace( ',', '.', $purchase_price );
		}

		/* prices */
		$prod->regular_price  = $wc_prod->get_regular_price();
		$prod->sale_price     = $wc_prod->get_sale_price();
		$prod->purchase_price = $purchase_price;
		$sale_from            = $wc_prod->get_date_on_sale_from();
		$sale_to              = $wc_prod->get_date_on_sale_to();

		$prod->unit_cost = empty( $post_meta['_wc_cog_cost'][0] ) ? 0 : (float) $post_meta['_wc_cog_cost'][0];

		if ( $prod->sale_price ) {

			if ( ! $sale_from && ! $sale_to ) {

				$prod->sale_active = true;
			} else {

				$sale_from = ( $sale_from ? gmdate( 'd-m-Y', (int) $sale_from ) : null );
				$sale_to   = ( $sale_to ? gmdate( 'd-m-Y', (int) $sale_to ) : null );
				$today     = gmdate( 'Y-m-d' );

				if ( ( null === $sale_from || $sale_from < $today ) && ( null === $sale_to || $sale_to > $today ) ) {

					$prod->sale_active = true;
				}
			}
		}

		$prod->weight  = $wc_prod->get_weight();
		$prod->length  = $wc_prod->get_length();
		$prod->breadth = $wc_prod->get_width();
		$prod->height  = $wc_prod->get_height();

		$prod->available_wc_stock = $wc_prod->get_stock_quantity();
		$prod->mv_qty             = get_post_meta( $wc_prod->get_id(), '_mv_qty', true );

		$cs = wp_get_object_terms( $id, 'product_cat' );
		if ( count( $cs ) > 0 ) {

			$prod->category = self::get_full_category_name( $cs[0] ); // Primary category.
		}

		$img = wp_get_attachment_image_src( get_post_thumbnail_id( $id ) );

		if ( false !== $img && $img[0] ) {

			$prod->image_url = $img[0];
		}

		return $prod;
	}

	/**
	 * Convert WC_Product_Variation to Product.
	 *
	 * @param \WC_Product_Variation $wc_variation variation Product.
	 * @param \WC_Product_Variable  $wc_variable variable Product.
	 * @return Product
	 */
	public static function wc_variation_convert( $wc_variation, $wc_variable ) {

		$prod = new Product();

		$post_meta = get_post_meta( $wc_variation->get_id() );

		$purchase_price = get_post_meta( $wc_variation->get_id(), 'purchase_price', true );

		if ( ! isset( $purchase_price ) ) {
			$purchase_price = 0;
		} else {
			$purchase_price = (float) str_replace( ',', '.', $purchase_price );
		}

		$prod->wc_id          = $wc_variation->get_id();
		$prod->mv_id          = (int) ( ( empty( $post_meta['mv_id'][0] ) ) ? 0 : $post_meta['mv_id'][0] );
		$prod->name           = $wc_variation->get_name();
		$prod->sku            = $wc_variation->get_sku();
		$prod->description    = $wc_variation->get_title();
		$prod->type           = $wc_variation->get_type();
		$prod->regular_price  = $wc_variation->get_regular_price();
		$prod->sale_price     = $wc_variation->get_sale_price();
		$prod->purchase_price = $purchase_price;
		$prod->unit_cost      = ( empty( $post_meta['_wc_cog_cost_variable'][0] ) ? 0 : $post_meta['_wc_cog_cost_variable'][0] );

		$prod->available_wc_stock = $wc_variation->get_stock_quantity();
		$prod->mv_qty             = get_post_meta( $wc_variation->get_id(), '_mv_qty', true );

		$prod->weight  = empty( $wc_variation->get_weight() ) ? $wc_variable->get_weight() : $wc_variation->get_weight();
		$prod->height  = empty( $wc_variation->get_height() ) ? $wc_variable->get_height() : $wc_variation->get_height();
		$prod->length  = empty( $wc_variation->get_length() ) ? $wc_variable->get_length() : $wc_variation->get_length();
		$prod->breadth = empty( $wc_variation->get_width() ) ? $wc_variable->get_width() : $wc_variation->get_width();

		$image_array = wp_get_attachment_image_src( get_post_thumbnail_id( $prod->wc_id ) );
		if ( $image_array ) {

			$prod->image_url = $image_array[0];
		} else {

			// variable image.
			$image_array = wp_get_attachment_image_src( get_post_thumbnail_id( $wc_variable->get_id() ) );
			if ( $image_array ) {

				$prod->image_url = $image_array[0];
			}
		}

		$cs = wp_get_object_terms( $wc_variable->get_id(), 'product_cat' );
		if ( count( $cs ) > 0 ) {

			$prod->category = self::get_full_category_name( $cs[0] ); // Primary category.
		}

		// Megaventory version should be | attr1_label: var1 | attr2_label: var2 | attr3_label: var3.
		$variation_attrs = $wc_variation->get_variation_attributes( false );

		$version = '';

		foreach ( $variation_attrs as $attribute_code => $value ) {

			$attribute_for_megaventory = wc_attribute_label( $attribute_code );

			$version .= "{$attribute_for_megaventory}: {$value} | ";

		}

		$version = rtrim( $version, '| ' );

		$prod->version = $version;

		return $prod;
	}

	/**
	 * Generates the category's full name. From root category to corresponding.
	 *
	 * @param WP_Term $wp_term as category data.
	 * @return string
	 */
	public static function get_full_category_name( $wp_term ) {

		$full_category_name = $wp_term->name;

		$parent_category = $wp_term;

		while ( ! empty( $parent_category->parent ) ) {

			$parent_category = get_term( $parent_category->parent );

			$full_category_name = $parent_category->name . MV_Constants::MV_SLASH . $full_category_name;
		}

		return $full_category_name;
	}

	/**
	 * Get WooCommerce variations.
	 *
	 * @param WC_Product_Variable $wc_variable variable product.
	 * @return Product[]
	 */
	public static function wc_get_variations( $wc_variable ) {

		$variations_ids = $wc_variable->get_children();

		$prods = array();

		foreach ( $variations_ids as $variation_id ) {

			$wc_variation_product = new \WC_Product_Variation( $variation_id );
			$product              = self::wc_variation_convert( $wc_variation_product, $wc_variable );

			array_push( $prods, $product );
		}

		return $prods;
	}

	/**
	 * Update each option of a variable product in Megaventory.
	 *
	 * @param WC_Product_Variation $wc_product as variable product.
	 * @return void
	 */
	public static function update_variable_product_in_megaventory( $wc_product ) {

		$variation_ids = $wc_product->get_children();

		foreach ( $variation_ids as $variation_id ) {

			$wc_variation_product = new \WC_Product_Variation( $variation_id );

			$variation_product = self::wc_variation_convert( $wc_variation_product, $wc_product );

			$variation_product->mv_save();

		}
	}

	/**
	 * Get WooCommerce categories.
	 *
	 * @param null|string $by filter categories.
	 * @return array
	 */
	public function wc_get_prod_categories( $by = null ) {

		$cats = wp_get_object_terms( $this->wc_id, 'product_cat' );

		if ( null === $by ) {

			return $cats;

		} elseif ( 'id' === strtolower( $by ) ) {

			$temp = array();

			foreach ( $cats as $cat ) {

				array_push( $temp, $cat->term_id );
			}

			return $temp;

		} elseif ( 'name' === strtolower( $by ) ) {

			$temp = array();

			foreach ( $cats as $cat ) {

				array_push( $temp, $cat->name );
			}

			return $temp;
		}
	}

	/**
	 * Save Product to Megaventory.
	 *
	 * @param null|boolean $categories search with categories.
	 * @param bool         $force_update_sku force SKU update in Megaventory.
	 * @return array
	 */
	public function mv_save( $categories = null, $force_update_sku = false ) {
		/*
			Passing categories makes things faster and requires less API calls.
			always use $categories when using this function in a loop with many users
		*/

		$wc_product = wc_get_product( $this->wc_id );

		if ( 'grouped' === $this->type ) {

			return null;
		}
		if ( 'variable' === $this->type ) {

			return null;
		}
		if ( ! wp_strip_all_tags( $this->description ) ) {

			$this->log_error( 'Product not saved to Megaventory', 'Short description cannot be empty', -1, 'error', '' );
			return false;
		}
		if ( ! $this->sku ) {
			$this->log_error( 'Product not saved to Megaventory', 'SKU cannot be empty', -1, 'error', '' );
			return false;
		}

		if ( null === $categories ) {

			$categories = self::mv_get_categories();
		}

		$category_id = null;

		if ( null !== $this->category ) {

			$category_id = array_search( $this->category, $categories, true );

			if ( false === $category_id ) { // we need to create a new category.

				$category_id = self::mv_create_category( $this->category );
			}
		}

		$update_url = \Megaventory\API::get_url_for_call( MV_Constants::PRODUCT_UPDATE );
		$mv_request = $this->generate_update_json( $category_id, $force_update_sku );

		$data = \Megaventory\API::send_request_to_megaventory( $update_url, $mv_request );

		/*if product didn't save in Megaventory it will return an error code != 0*/

		if ( '0' !== ( $data['ResponseStatus']['ErrorCode'] ) ) {

			/* the only case we will not log an error is the case that the product is already deleted in Megaventory */

			if ( 'ProductSKUAlreadyDeleted' !== $data['InternalErrorCode'] ) {

				$internal_error_code = ' [' . $data['InternalErrorCode'] . ']';

				$this->log_error( 'Product not saved to Megaventory ' . $internal_error_code, $data['ResponseStatus']['Message'], -1, 'error', $data['json_object'] );

				return false;
			}

			$this->mv_id = $data['entityID'];
			$url         = \Megaventory\API::get_url_for_call( self::$product_undelete_call );

			$params = array(
				'ProductIdToUndelete' => $this->mv_id,
			);

			$undelete_data = \Megaventory\API::send_request_to_megaventory( $url, $params );

			if ( array_key_exists( 'InternalErrorCode', $undelete_data ) ) {
				$this->log_error( 'Product is deleted. Undelete failed', $undelete_data['ResponseStatus']['Message'], -1, 'error', $data['json_object'] );
				return false;
			}

			return $this->mv_save( $categories );
		}

		/*Otherwise the product will either be created or updated.*/

		$product_exists = ( null === $this->mv_id || 0 === $this->mv_id );
		$action         = ( $product_exists ? 'created' : 'updated' );

		$this->log_success( $action, 'product successfully ' . $action . ' in Megaventory', 1 );

		update_post_meta( $this->wc_id, 'mv_id', $data['mvProduct']['ProductID'] );

		$this->mv_id = $data['mvProduct']['ProductID'];

		return $data['mvProduct'];
	}

	/**
	 * Create a json.
	 *
	 * @param null|bool $category_id filter with categories.
	 * @param bool      $force_update_sku force SKU update in Megaventory.
	 *
	 * @return array
	 */
	private function generate_update_json( $category_id = null, $force_update_sku = false ) {

		$special_characters = array( '?', '$', '@', '!', '*', '#' );// Special characters that need to be removed in order to be accepted by Megaventory.

		$mv_product = array();

		$mv_product['ProductID']              = empty( $this->mv_id ) ? '' : $this->mv_id;
		$mv_product['ProductType']            = empty( $this->mv_type ) ? MV_Constants::MV_PRODUCT_TYPE['Undefined'] : $this->mv_type;
		$mv_product['ProductSKU']             = $this->sku;
		$mv_product['ProductDescription']     = mb_substr( wp_strip_all_tags( str_replace( $special_characters, ' ', $this->description ) ), 0, 400 );
		$mv_product['ProductVersion']         = empty( $this->version ) ? '' : $this->version;
		$mv_product['ProductLongDescription'] = empty( $this->long_description ) ? '' : mb_substr( wp_strip_all_tags( str_replace( $special_characters, ' ', $this->long_description ) ), 0, 400 );
		$mv_product['ProductCategoryID']      = $category_id;
		$mv_product['ProductSellingPrice']    = empty( $this->regular_price ) ? '' : $this->regular_price;
		$mv_product['ProductWeight']          = empty( $this->weight ) ? '' : $this->weight;
		$mv_product['ProductLength']          = empty( $this->length ) ? '' : $this->length;
		$mv_product['ProductBreadth']         = empty( $this->breadth ) ? '' : $this->breadth;
		$mv_product['ProductHeight']          = empty( $this->height ) ? '' : $this->height;
		$mv_product['ProductImageURL']        = empty( $this->image_url ) ? '' : $this->image_url;

		if ( ! empty( $this->ean ) ) {

			$mv_product['ProductEAN'] = $this->ean;
		}

		if ( empty( $this->mv_id ) ) { // Purchase price is synchronized only if the product is not synchronized with Megaventory.

			$mv_product['ProductPurchasePrice'] = $this->purchase_price;
		}

		$object_to_send = array();

		$object_to_send['mvProduct']                             = $mv_product;
		$object_to_send['forceSkuUpdateEvenIfUsedInDocuments']   = $force_update_sku;
		$object_to_send['mvRecordAction']                        = MV_Constants::MV_RECORD_ACTION['InsertOrUpdateNonEmptyFields'];
		$object_to_send['mvInsertUpdateDeleteSourceApplication'] = 'woocommerce';

		return $object_to_send;
	}

	/**
	 * Delete product in Megaventory.
	 *
	 * @return bool
	 */
	public function delete_product_in_megaventory() {

		if ( empty( $this->mv_id ) ) {
			return true;
		}

		$data_to_send = array(
			'ProductIDToDelete'                     => $this->mv_id,
			'mvInsertUpdateDeleteSourceApplication' => 'woocommerce',
		);

		$url = \Megaventory\API::get_url_for_call( MV_Constants::PRODUCT_DELETE );

		$response = \Megaventory\API::send_request_to_megaventory( $url, $data_to_send );

		if ( '0' === ( $response['ResponseStatus']['ErrorCode'] ) ) {

			$this->log_success( 'deleted', 'product successfully deleted in Megaventory', 1 );

			return true;
		} else {

			$internal_error_code = ' [' . $response['InternalErrorCode'] . ']';

			$this->log_error( 'Product not deleted to Megaventory ' . $internal_error_code, $response['ResponseStatus']['Message'], -1, 'error', $response['json_object'] );

			return false;
		}
	}

	/**
	 * Image is attached only if a product has no image yet.
	 *
	 * @return bool
	 */
	public function attach_image() {

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( null === $this->image_url || '' === $this->image_url ) {

			return false;

		} else { // only upload image id doesn't exist.

			if ( false !== wp_get_attachment_image_src( get_post_thumbnail_id( $this->wc_id ) ) ) {

				return false;
			}
		}

		$dir          = dirname( __FILE__ );
		$image_folder = $dir . '/../import/';
		$image_file   = $this->sku;
		$image_full   = $image_folder . $image_file;

		// image.
		$image = $this->image_url;

		// Magic sideload image returns an HTML image, not an IDs.
		$media = media_sideload_image( $image, $this->wc_id );

		// therefore we must find it so we can set it as featured ID.
		if ( ! empty( $media ) && ! is_wp_error( $media ) ) {

			$args = array(
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'post_parent'    => $this->wc_id,
			);

			// reference new image to set as featured.
			$attachments = get_posts( $args );

			if ( isset( $attachments ) && is_array( $attachments ) ) {

				foreach ( $attachments as $attachment ) {

					// grab source of full size images (so no 300x150 nonsense in path).
					$image = wp_get_attachment_image_src( $attachment->ID, 'full' );

					// determine if in the $media image we created, the string of the URL exists.
					if ( strpos( $media, $image[0] ) !== false ) {

						// if so, we found our image. set it as thumbnail.
						set_post_thumbnail( $this->wc_id, $attachment->ID );
						// only want one image.
						break;
					}
				}
			}
		} else {

			return false;
		}

		return true;
	}

	/**
	 * Get WooCommerce categories.
	 *
	 * @return array
	 */
	private static function wc_get_categories() {

		return get_terms( 'product_cat' );
	}

	/**
	 * Get Category from WooCommerce by name.
	 *
	 * @param string  $name as category name.
	 * @param boolean $with_create as boolean.
	 * @return null|string
	 */
	private function wc_get_category_id_by_name( $name, $with_create = false ) {

		$product_categories = self::wc_get_categories();

		$category_id = array();

		foreach ( $product_categories as $item ) {

			if ( $item->name === $this->category ) {

				array_push( $category_id, $item->term_id );
				return $category_id;
			}
		}

		$category_id = array();
		if ( $with_create ) {
			$cid = wp_insert_term(
				$name, // the term.
				'product_cat', // the taxonomy.
				array()
			);
			return array( $cid['term_id'] );
		}

		return null;
	}

	/**
	 * Get Categories from Megaventory.
	 *
	 * @return array
	 */
	public static function mv_get_categories() {
		$url     = \Megaventory\API::get_url_for_call( self::$category_get_call );
		$jsoncat = \Megaventory\API::perform_call_to_megaventory( $url );

		$categories = array();
		foreach ( $jsoncat['mvProductCategories'] as $cat ) {
			$categories[ $cat['ProductCategoryID'] ] = $cat['ProductCategoryName'];
		}

		return $categories;
	}

	/**
	 * Create a new Category on Megaventory.
	 *
	 * @param string $name as category's name.
	 * @return bool|null
	 */
	public static function mv_create_category( $name ) {

		$url      = \Megaventory\API::get_url_for_call( self::$category_update_call );
		$data     = array(
			'mvProductCategory' => array(
				'ProductCategoryName' => $name,
			),
		);
		$response = \Megaventory\API::send_request_to_megaventory( $url, $data );

		if ( isset( $response['InternalErrorCode'] ) && 'CategoryWasDeleted' === $response['InternalErrorCode'] ) { // needs to be undeleted.

			$id           = $response['entityID'];
			$undelete_url = \Megaventory\API::get_url_for_call( self::$category_undelete_call );
			$params       = array(
				'ProductCategoryIDToUndelete' => $id,
			);
			$response     = \Megaventory\API::send_request_to_megaventory( $undelete_url, $params );

			if ( $response['result'] ) {

				$response['mvProductCategory']                      = array();
				$response['mvProductCategory']['ProductCategoryID'] = $id;
			}
		}

		return ( array_key_exists( 'mvProductCategory', $response ) ) ? $response['mvProductCategory']['ProductCategoryID'] : null;
	}

	/**
	 * Resets product meta data on initial sync operation only.
	 *
	 * @return void
	 */
	public function reset_megaventory_post_meta() {

		update_post_meta( $this->wc_id, 'mv_id', 0 );
		update_post_meta( $this->wc_id, '_mv_qty', '' );
		update_post_meta( $this->wc_id, '_last_mv_stock_update', 0 );

	}

	/**
	 * Synchronize stock for a product.
	 *
	 * @param WC_Product_Variation|WC_Product_Simple $wc_product as WC variation.
	 * @param array                                  $mv_product_stock_details as MV stock data.
	 * @param int                                    $integration_update_id as integration update id.
	 * @return void
	 */
	public static function sync_stock_update( $wc_product, $mv_product_stock_details, $integration_update_id ) {

		$product         = new Product();
		$product->wc_id  = $wc_product->get_id();
		$product->mv_id  = $mv_product_stock_details['productID'];
		$product->sku    = $wc_product->get_sku();
		$product->mv_qty = get_post_meta( $product->wc_id, '_mv_qty', true );

		if ( get_post_meta( $product->wc_id, '_last_mv_stock_update', true ) > $integration_update_id ) {
			return;
		}

		$product->update_stock_properties_from_stock_update( $mv_product_stock_details );
		$product->update_stock();

		/** This was an issue about variable total qty #2611
		 * if ( $wc_product->is_type( 'variation' ) ) {
		 *
		 * $product_variable = wc_get_product( $wc_product->get_parent_id() );
		 *
		 * if ( null !== $product_variable && false !== $product_variable ) {
		 *
		 * $product_variable->save();
		 *
		 * }
		 * }
		 */
		update_post_meta( $product->wc_id, '_last_mv_stock_update', $integration_update_id );

	}

	/**
	 * Push stock to Megaventory.
	 *
	 * @param int    $starting_index         as the position.
	 * @param string $adjustment_status      as adjustment status.
	 * @param int    $adjustment_location_id as adjustment location id.
	 * @return array['starting_index','next_index','error_occurred','finished','message']
	 */
	public static function push_stock( $starting_index, $adjustment_status, $adjustment_location_id ) {

		$return_values = array(
			'starting_index' => $starting_index,
			'next_index'     => 0,
			'error_occurred' => false,
			'finished'       => false,
			'message'        => '',
		);

		$products_added_to_adjustment_plus  = 0;
		$products_added_to_adjustment_minus = 0;

		$document_details_adj_plus  = get_transient( 'adjustment_plus_items_array' );
		$document_details_adj_minus = get_transient( 'adjustment_minus_items_array' );

		if ( false === $document_details_adj_plus ) {
			$document_details_adj_plus = array();
		}

		if ( false === $document_details_adj_minus ) {
			$document_details_adj_minus = array();
		}

		$all_simple_products_count = self::wc_get_all_woocommerce_products_count();

		$last_page = (int) ceil( $all_simple_products_count / MV_Constants::PUSH_STOCK_ADMIN_UPDATE_COUNT );

		$page = ( $starting_index / MV_Constants::PUSH_STOCK_ADMIN_UPDATE_COUNT ) + 1; // starts from 1.

		$selected_products_to_sync_stock = self::wc_get_products_in_batches( MV_Constants::PUSH_STOCK_ADMIN_UPDATE_COUNT, $page );

		$selected_products_to_sync_stock_count = count( $selected_products_to_sync_stock );

		$return_values['next_index'] = $starting_index + MV_Constants::PUSH_STOCK_ADMIN_UPDATE_COUNT;

		$is_last_page = $page === $last_page;

		if ( $page > $last_page ) {

			$return_values = array(
				'starting_index' => $starting_index,
				'next_index'     => -1,
				'error_occurred' => false,
				'finished'       => true,
				'message'        => 'Current synchronization: ' . $all_simple_products_count . ' of ' . $all_simple_products_count,
			);

			update_option( 'is_megaventory_stock_adjusted', 1 );

			$current_time_without_utc = gmdate( 'Y-m-d H:i:s' );

			$current_date = get_date_from_gmt( $current_time_without_utc, 'Y-m-d H:i:s' );

			$synchronized_message = 'Quantity adjusted to Megaventory on ' . $current_date;

			update_option( 'megaventory_stock_synchronized_time', $synchronized_message );

			return $return_values;
		}

		$filters = array();

		foreach ( $selected_products_to_sync_stock as $selected_product ) {

			if ( empty( $selected_product->sku ) || empty( $selected_product->mv_id ) ) {
				continue;
			}

			$filter = array(
				'AndOr'          => 'Or',
				'FieldName'      => 'productID',
				'SearchOperator' => 'Equals',
				'SearchValue'    => $selected_product->mv_id,
			);

			array_push( $filters, $filter );
		}
		// call to get stock information for ids.

		$stock_get_body = array(
			'Filters' => $filters,
		);

		$url      = \Megaventory\API::get_url_for_call( MV_Constants::INVENTORY_LOCATION_STOCK_GET );
		$response = \Megaventory\API::send_request_to_megaventory( $url, $stock_get_body );

		if ( '0' !== ( $response['ResponseStatus']['ErrorCode'] ) ) {

			$args = array(
				'type'        => 'error',
				'entity_name' => 'Stock Get',
				'entity_id'   => 0,
				'problem'     => 'Error on Stock Get, check the the quantity on adjustments before approve them',
				'full_msg'    => $response['ResponseStatus']['Message'],
				'error_code'  => $response['ResponseStatus']['ErrorCode'],
				'json_object' => '',
			);

			$e = new MVWC_Error( $args );
		}

		$megaventory_product_stock_list = $response['mvProductStockList'];

		foreach ( $selected_products_to_sync_stock as $selected_product ) {

			if ( empty( $selected_product->sku ) || empty( $selected_product->mv_id ) ) {
				continue;
			}

			$index  = array_search( $selected_product->mv_id, array_column( $megaventory_product_stock_list, 'productID' ), true );
			$mv_qty = 0;

			if ( false !== $index ) {
				$mv_qty = $megaventory_product_stock_list[ $index ]['StockOnHandTotal'];
			}
			$wc_qty = 0;

			if ( ! empty( $selected_product->available_wc_stock ) ) {

				$wc_qty = $selected_product->available_wc_stock;
			}

			if ( $wc_qty < 0 ) {
				$wc_qty = 0;
			}

			$adjust = $wc_qty - $mv_qty;

			if ( 0 < $adjust ) {

				$detail = array(
					'DocumentRowProductSKU' => $selected_product->sku,
					'DocumentRowQuantity'   => $adjust,
					'DocumentRowUnitPriceWithoutTaxOrDiscount' => $selected_product->purchase_price,
				);

				array_push( $document_details_adj_plus, $detail );

			} elseif ( 0 > $adjust ) {

				$detail = array(
					'DocumentRowProductSKU' => $selected_product->sku,
					'DocumentRowQuantity'   => $adjust * ( -1 ),
					'DocumentRowUnitPriceWithoutTaxOrDiscount' => $selected_product->purchase_price,
				);

				array_push( $document_details_adj_minus, $detail );
			}
		}

		if ( 0 === count( $document_details_adj_plus ) && 0 === count( $document_details_adj_minus ) && $is_last_page ) {

			\Megaventory\Helpers\Admin_Notifications::register_warning( 'No adjustment was needed.' );

			$return_values = array(
				'starting_index' => $starting_index,
				'next_index'     => $starting_index + MV_Constants::PUSH_STOCK_ADMIN_UPDATE_COUNT,
				'error_occurred' => false,
				'finished'       => false,
				'message'        => 'Current synchronization: ' . ( $starting_index + $selected_products_to_sync_stock_count ) . ' of ' . $all_simple_products_count,
			);

			return $return_values;
		}

		$action = 'Insert';

		$products_added_to_adjustment_minus = count( $document_details_adj_minus );
		$products_added_to_adjustment_plus  = count( $document_details_adj_plus );

		if ( 0 < count( $document_details_adj_plus ) ) {

			if ( MV_Constants::PUSH_STOCK_BATCH_COUNT > $products_added_to_adjustment_plus && ! $is_last_page ) {
				set_transient( 'adjustment_plus_items_array', $document_details_adj_plus, 60 );
			} else {
				$mv_document_plus = array(
					'DocumentTypeId'           => MV_Constants::ADJ_PLUS_DEFAULT_TRANS_ID,
					'DocumentSupplierClientID' => MV_Constants::INTERNAL_SUPPLIER_CLIENT_FOR_ADJUSTMENTS_AND_OTHER_OPERATIONS,
					'DocumentComments'         => 'This is the initial stock document that was created based on available quantity for the following products',
					'DocumentDetails'          => $document_details_adj_plus,
					'DocumentStatus'           => $adjustment_status,
				);

				if ( 'Verified' === $adjustment_status ) {
					$mv_document_plus['DocumentInventoryLocationID'] = $adjustment_location_id;
				}

				$document_update = array(
					'mvDocument'     => $mv_document_plus,
					'mvRecordAction' => $action,
					'mvInsertUpdateDeleteSourceApplication' => 'woocommerce',
				);

				$url      = \Megaventory\API::get_url_for_call( MV_Constants::DOCUMENT_UPDATE );
				$response = \Megaventory\API::send_request_to_megaventory( $url, $document_update );

				delete_transient( 'adjustment_plus_items_array' );

				if ( '0' === ( $response['ResponseStatus']['ErrorCode'] ) ) {

					$args = array(
						'entity_id'          => array(
							'wc' => 0,
							'mv' => $response['mvDocument']['DocumentId'],
						),
						'entity_type'        => 'adjustment',
						'entity_name'        => $response['mvDocument']['DocumentTypeAbbreviation'] . ' ' . $response['mvDocument']['DocumentNo'],
						'transaction_status' => 'Insert',
						'full_msg'           => 'The adjustment has been created to your Megaventory account',
						'success_code'       => 1,
					);

					$e = new MVWC_Success( $args );
				} else {

					$args = array(
						'type'        => 'error',
						'entity_name' => 'Adjustment plus creation',
						'entity_id'   => 0,
						'problem'     => 'Error on adjustment creation',
						'full_msg'    => $response['ResponseStatus']['Message'],
						'error_code'  => $response['ResponseStatus']['ErrorCode'],
						'json_object' => '',
					);

					$e = new MVWC_Error( $args );
				}
			}
		}

		if ( 0 < count( $document_details_adj_minus ) ) {

			if ( MV_Constants::PUSH_STOCK_BATCH_COUNT > $products_added_to_adjustment_minus && ! $is_last_page ) {
				set_transient( 'adjustment_minus_items_array', $document_details_adj_minus, 60 );
			} else {
				$mv_document_minus = array(
					'DocumentTypeId'           => MV_Constants::ADJ_MINUS_DEFAULT_TRANS_ID,
					'DocumentSupplierClientID' => MV_Constants::INTERNAL_SUPPLIER_CLIENT_FOR_ADJUSTMENTS_AND_OTHER_OPERATIONS,
					'DocumentComments'         => 'This is the initial stock document that was created based on available quantity for the following products',
					'DocumentDetails'          => $document_details_adj_minus,
					'DocumentStatus'           => $adjustment_status,
				);

				if ( 'Verified' === $adjustment_status ) {
					$mv_document_plus['DocumentInventoryLocationID'] = $adjustment_location_id;
				}

				$document_update = array(
					'mvDocument'     => $mv_document_minus,
					'mvRecordAction' => $action,
					'mvInsertUpdateDeleteSourceApplication' => 'woocommerce',
				);

				$url      = \Megaventory\API::get_url_for_call( MV_Constants::DOCUMENT_UPDATE );
				$response = \Megaventory\API::send_request_to_megaventory( $url, $document_update );

				delete_transient( 'adjustment_minus_items_array' );

				if ( '0' === ( $response['ResponseStatus']['ErrorCode'] ) ) {

					$args = array(
						'entity_id'          => array(
							'wc' => 0,
							'mv' => $response['mvDocument']['DocumentId'],
						),
						'entity_type'        => 'adjustment',
						'entity_name'        => $response['mvDocument']['DocumentTypeAbbreviation'] . ' ' . $response['mvDocument']['DocumentNo'],
						'transaction_status' => 'Insert',
						'full_msg'           => 'The adjustment has been created to your Megaventory account',
						'success_code'       => 1,
					);

					$e = new MVWC_Success( $args );
				} else {

					$args = array(
						'type'        => 'error',
						'entity_name' => 'Adjustment creation',
						'entity_id'   => 0,
						'problem'     => 'Error on adjustment minus creation',
						'full_msg'    => $response['ResponseStatus']['Message'],
						'error_code'  => $response['ResponseStatus']['ErrorCode'],
						'json_object' => '',
					);

					$e = new MVWC_Error( $args );
				}
			}
		}

		$return_values = array(
			'starting_index' => $starting_index,
			'next_index'     => $starting_index + MV_Constants::PUSH_STOCK_ADMIN_UPDATE_COUNT,
			'error_occurred' => false,
			'finished'       => false,
			'message'        => 'Current synchronization: ' . ( $starting_index + $selected_products_to_sync_stock_count ) . ' of ' . $all_simple_products_count,
		);

		return $return_values;
	}

	/**
	 * Get stock qty for product in location.
	 * Returns Physical excluding Non Allocated and Non Shipped.
	 *
	 * @param int  $mv_location_id Inventory location id.
	 * @param bool $physical_only  Return physical stock instead.
	 * @return int
	 */
	public function get_mv_stock_for_location( $mv_location_id, $physical_only = false ) {

		if ( empty( $this->mv_qty ) || ! is_array( $this->mv_qty ) || ! array_key_exists( $mv_location_id, $this->mv_qty ) ) {
			return 0;
		}

		$stock_array = explode( ';', $this->mv_qty[ $mv_location_id ] );

		$physical      = $stock_array[ self::MV_PHYSICAL_STOCK_QTY_KEY ];
		$non_shipped   = $stock_array[ self::MV_NON_SHIPPED_QTY_KEY ];
		$non_allocated = $stock_array[ self::MV_NON_ALLOCATED_WO_QTY_KEY ];

		if ( $physical_only ) {
			return (int) $physical;
		}

		return ( (int) $physical - (int) $non_allocated - (int) $non_shipped );

	}

	/**
	 * Deletes Megaventory data.
	 *
	 * @return void
	 */
	public function wc_delete_mv_data() {

		delete_post_meta( $this->wc_id, 'mv_id' );
		delete_post_meta( $this->wc_id, '_mv_qty' );
		delete_post_meta( $this->wc_id, '_last_mv_stock_update' );
	}

	/**
	 * Update stock data.
	 */
	private function update_stock() {

		update_post_meta( $this->wc_id, '_mv_qty', $this->mv_qty );

		$wc_product = wc_get_product( $this->wc_id );

		$stock_status = 'outofstock';

		if ( 'no' === $wc_product->get_backorders() ) {
			$stock_status = ( $this->available_wc_stock > 0 ? 'instock' : 'outofstock' );
		} else {
			$stock_status = ( $this->available_wc_stock >= 0 ? 'instock' : 'onbackorder' );
		}

		update_post_meta( $this->wc_id, '_manage_stock', 'yes' );
		update_post_meta( $this->wc_id, '_stock', (string) $this->available_wc_stock );
		update_post_meta( $this->wc_id, '_stock_status', $stock_status );

		if ( $wc_product->is_type( 'variation' ) ) {

			$product_variable = wc_get_product( $wc_product->get_parent_id() );

			if ( null === $product_variable || false === $product_variable ) {
				return;
			}

			$variable_stock_status = 'outofstock';

			foreach ( $product_variable->get_children() as $variation_id ) {

				$variation_product = wc_get_product( $variation_id );

				if ( 'outofstock' !== $variation_product->get_stock_status() ) {

					$variable_stock_status = $variation_product->get_stock_status();

					break;
				}
			}

			update_post_meta( $product_variable->get_id(), '_stock_status', $variable_stock_status );
		}

		if ( defined( 'WC_PB_VERSION' ) ) {

			$wc_product = wc_get_product( $this->wc_id ); // reload product attributes status, qty etc.

			WC_PB_DB_Sync::bundled_product_stock_changed( $wc_product ); // if the product is not a bundled product, it will just return.
		}

	}
}
