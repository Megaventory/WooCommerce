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

/**
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/address.php';
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
	 * @var double
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
			'limit' => $limit,
			'page'  => $page,
		);
		$wc_products = wc_get_products( $args );

		foreach ( $wc_products as $wc_product ) {

			if ( 'simple' === $wc_product->get_type() ) {

				$product = self::wc_convert( $wc_product );
				array_push( $all_products, $product );

			} elseif ( 'variable' === $wc_product->get_type() ) {

				$products     = self::wc_get_variations( $wc_product );
				$all_products = array_merge( $all_products, $products );
			}
		}

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
			'limit' => $limit,
		);
		$wc_products = wc_get_products( $args );

		foreach ( $wc_products as $wc_product ) {

			if ( 'simple' === $wc_product->get_type() ) {

				$product = self::wc_convert( $wc_product );
				array_push( $all_products, $product );

			} elseif ( 'variable' === $wc_product->get_type() ) {

				$products     = self::wc_get_variations( $wc_product );
				$all_products = array_merge( $all_products, $products );
			}
		}

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

		$all_wc_products = wc_get_products( $args );

		return $all_wc_products;
	}

	/**
	 * Get the count of all simple and variations WooCommerce products.
	 *
	 * @return int
	 */
	public static function wc_get_all_woocommerce_products_count() {

		$args = array(
			'type'   => array( 'simple', 'variation' ),
			'return' => 'ids',
			'limit'  => -1,
		);

		$all_wc_products = wc_get_products( $args );

		return count( $all_wc_products );
	}

	/**
	 * Get all Products from Megaventory.
	 *
	 * @return array[Product]
	 */
	public static function mv_all() {

		$categories = self::mv_get_categories();
		$url        = create_json_url( MV_Constants::PRODUCT_GET );
		$json_data  = perform_call_to_megaventory( $url );
		$json_prod  = json_decode( $json_data, true );

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

		$url       = create_json_url_filter( MV_Constants::PRODUCT_GET, 'ProductID', 'Equals', $id );
		$json_data = perform_call_to_megaventory( $url );
		$data      = json_decode( $json_data, true );
		if ( count( $data['mvProducts'] ) <= 0 ) {
			return null; // No such ID.
		}

		return self::mv_convert( $data['mvProducts'][0] );
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
			'APIKEY'  => get_api_key(),
			'Filters' => $filters,
		);

		$url      = get_url_for_call( MV_Constants::INVENTORY_LOCATION_STOCK_GET );
		$response = send_request_to_megaventory( $url, $stock_get_body );

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

				update_post_meta( $selected_product->wc_id, '_mv_qty', $selected_product->mv_qty );
				update_post_meta( $selected_product->wc_id, '_manage_stock', 'yes' );
				update_post_meta( $selected_product->wc_id, '_stock', (string) $selected_product->available_wc_stock );

				$woocommerce_product = wc_get_product( $selected_product->wc_id );
				if ( 'no' === $woocommerce_product->backorders ) {

					update_post_meta( $selected_product->wc_id, '_stock_status', ( $selected_product->available_wc_stock > 0 ? 'instock' : 'outofstock' ) );
				} else {

					update_post_meta( $selected_product->wc_id, '_stock_status', ( $selected_product->available_wc_stock >= 0 ? 'instock' : 'onbackorder' ) );
				}
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

				$mv_location_id_to_abbr = get_option( 'mv_location_id_to_abbr' );

				$inventory_name = $mv_location_id_to_abbr[ $inventory['InventoryLocationID'] ];

				$total           = $inventory['StockPhysical'];
				$on_hand         = $inventory['StockOnHand'];
				$non_shipped     = $inventory['StockNonShipped'];
				$non_allocated   = $inventory['StockNonAllocatedWOs'];
				$non_received_po = $inventory['StockNonReceivedPOs'];
				$non_received_wo = $inventory['StockNonReceivedWOs'];

				$string  = '' . $inventory_name;
				$string .= ';' . $total;
				$string .= ';' . $on_hand;
				$string .= ';' . $non_shipped;
				$string .= ';' . $non_allocated;
				$string .= ';' . $non_received_po;
				$string .= ';' . $non_received_wo;

				$mv_qty[ $inventory['InventoryLocationID'] ] = $string;

				$available_stock += (int) $on_hand;
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

		$mv_location_id_to_abbr = get_option( 'mv_location_id_to_abbr' );

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

			$available_stock += $qty[2];
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

		$url       = create_json_url_filter( self::$inventory_get_call, 'InventoryLocationID', 'Equals', rawurlencode( $id ) );
		$json_data = perform_call_to_megaventory( $url );
		$data      = json_decode( $json_data, true );

		if ( count( $data['mvInventoryLocations'] ) <= 0 ) { // Not found.
			return null;
		}

		if ( $abbrev ) {
			return $data['mvInventoryLocations'][0]['InventoryLocationAbbreviation'];
		} else {
			return $data['mvInventoryLocations'][0]['InventoryLocationName'];
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
			'APIKEY'      => get_api_key(),
			'showDeleted' => 'showAllDeletedAndUndeleted',
			'Filters'     => $filters,
		);

		$url  = get_url_for_call( MV_Constants::PRODUCT_GET );
		$data = send_request_to_megaventory( $url, $product_get_body );

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
		$prod->mv_id = (int) $post_meta['mv_id'][0];

		$prod->name             = $wc_prod->get_name();
		$prod->long_description = $wc_prod->get_description();
		$prod->description      = $wc_prod->get_title();

		$prod->type = $wc_prod->get_type();

		$prod->sku = $wc_prod->get_sku();

		/* prices */
		$prod->regular_price = $wc_prod->get_regular_price();
		$prod->sale_price    = $wc_prod->get_sale_price();
		$sale_from           = $wc_prod->get_date_on_sale_from();
		$sale_to             = $wc_prod->get_date_on_sale_to();

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

		if ( $img[0] ) {

			$prod->image_url = $img[0];
		}

		return $prod;
	}

	/**
	 * Convert WC_Product_Variation to Product.
	 *
	 * @param WC_Product_Variation $wc_variation variation Product.
	 * @param WC_Product_Variable  $wc_variable variable Product.
	 * @return Product
	 */
	public static function wc_variation_convert( $wc_variation, $wc_variable ) {

		$prod = new Product();

		$post_meta = get_post_meta( $wc_variation->get_id() );

		$prod->wc_id         = $wc_variation->get_id();
		$prod->mv_id         = (int) $post_meta['mv_id'][0];
		$prod->name          = $wc_variation->get_name();
		$prod->sku           = $wc_variation->get_sku();
		$prod->description   = $wc_variation->get_title();
		$prod->type          = $wc_variation->get_type();
		$prod->regular_price = $wc_variation->get_regular_price();
		$prod->sale_price    = $wc_variation->get_sale_price();
		$prod->unit_cost     = ( empty( $post_meta['_wc_cog_cost_variable'][0] ) ? 0 : $post_meta['_wc_cog_cost_variable'][0] );

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

		// Version is | name - var1, var2, var3.
		// Megaventory version should be | var1, var2, var3.
		$version = $wc_variation->get_name();
		$version = str_replace( ' ', '', $version ); // remove whitespaces.
		$version = explode( '-', $version )[1]; // disregard name.
		$version = str_replace( ',', '/', $version );

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

			$wc_variation_product = new WC_Product_Variation( $variation_id );
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

			$wc_variation_product = new WC_Product_Variation( $variation_id );

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
	 * @return array
	 */
	public function mv_save( $categories = null ) {
		/*
			Passing categories makes things faster and requires less API calls.
			always use $categories when using this function in a loop with many users
		*/

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

		$urljson      = create_json_url( self::$product_update_call );
		$json_request = $this->generate_update_json( $category_id );
		$data         = send_json( $urljson, $json_request );

		/*if product didn't save in Megaventory it will return an error code !=0*/

		if ( '0' !== ( $data['ResponseStatus']['ErrorCode'] ) ) {

			/* the only case we will not log an error is the case that the product is already deleted in Megaventory */

			if ( 'ProductSKUAlreadyDeleted' !== $data['InternalErrorCode'] ) {

				$internal_error_code = ' [' . $data['InternalErrorCode'] . ']';

				$this->log_error( 'Product not saved to Megaventory ' . $internal_error_code, $data['ResponseStatus']['Message'], -1, 'error', $data['json_object'] );

				return false;
			}

			$this->mv_id   = $data['entityID'];
			$url           = create_json_url( self::$product_undelete_call );
			$url           = $url . '&ProductIDToUndelete=' . $this->mv_id;
			$undelete_data = perform_call_to_megaventory( $url );

			if ( array_key_exists( 'InternalErrorCode', json_decode( $undelete_data ) ) ) {
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
	 * @return stdClass
	 */
	private function generate_update_json( $category_id = null ) {

		$megaventory_product = new Product();
		$megaventory_product = self::mv_find_by_sku( $this->sku );

		if ( null !== $megaventory_product ) {

			$this->mv_id   = $megaventory_product->mv_id;
			$this->mv_type = $megaventory_product->mv_type;
			$this->ean     = $megaventory_product->ean;
		} else {
			$this->mv_id = 0;
		}

		$create_new = ( null === $this->mv_id || 0 === $this->mv_id );

		$action = ( ( $create_new ) ? 'Insert' : 'Update' );

		$special_characters = array( '?', '$', '@', '!', '*', '#' );// Special characters that need to be removed in order to be accepted by Megaventory.

		$product_update_object = new \stdClass();
		$product_object        = new \stdClass();

		$product_object->productid              = $create_new ? '' : $this->mv_id;
		$product_object->producttype            = $this->mv_type ? $this->mv_type : 'BuyFromSupplier';
		$product_object->productsku             = $this->sku;
		$product_object->productdescription     = mb_substr( wp_strip_all_tags( str_replace( $special_characters, ' ', $this->description ) ), 0, 400 );
		$product_object->productean             = ( isset( $this->ean ) ? $this->ean : '' );
		$product_object->productversion         = $this->version ? $this->version : '';
		$product_object->productlongdescription = $this->long_description ? mb_substr( wp_strip_all_tags( str_replace( $special_characters, ' ', $this->long_description ) ), 0, 400 ) : '';
		$product_object->productcategoryid      = $category_id;
		$product_object->productsellingprice    = $this->regular_price ? $this->regular_price : '';
		$product_object->productweight          = $this->weight ? $this->weight : '';
		$product_object->productlength          = $this->length ? $this->length : '';
		$product_object->productbreadth         = $this->breadth ? $this->breadth : '';
		$product_object->productheight          = $this->height ? $this->height : '';
		$product_object->productimageurl        = $this->image_url ? $this->image_url : '';

		$product_update_object->mvproduct                             = $product_object;
		$product_update_object->mvrecordaction                        = $action;
		$product_update_object->mvinsertupdatedeletesourceapplication = 'woocommerce';

		$json_object = wrap_json( $product_update_object );

		/**
		 * $json_object = wp_json_encode( $product_update_object );
		 */

		return $json_object;

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
			'APIKEY'                                => get_api_key(),
			'ProductIDToDelete'                     => $this->mv_id,
			'mvInsertUpdateDeleteSourceApplication' => 'woocommerce',
		);

		$url = get_url_for_call( MV_Constants::PRODUCT_DELETE );

		$response = send_request_to_megaventory( $url, $data_to_send );

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
		$url       = create_json_url( self::$category_get_call );
		$json_data = perform_call_to_megaventory( $url );
		$jsoncat   = json_decode( $json_data, true );

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

		$create_url = create_json_url( self::$category_update_call );
		$url        = $create_url . '&mvProductCategory={ProductCategoryName:' . rawurlencode( $name ) . '}';
		$json_data  = perform_call_to_megaventory( $url );
		$response   = json_decode( $json_data, true );

		if ( 'CategoryWasDeleted' === $response['InternalErrorCode'] ) { // needs to be undeleted.

			$id           = $response['entityID'];
			$undelete_url = create_json_url( self::$category_undelete_call );
			$url          = $undelete_url . '&ProductCategoryIDToUndelete=' . rawurlencode( $id );
			$json_data    = perform_call_to_megaventory( $url );
			$response     = json_decode( $json_data, true );

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

		update_post_meta( $product->wc_id, '_mv_qty', $product->mv_qty );
		update_post_meta( $product->wc_id, '_manage_stock', 'yes' );
		update_post_meta( $product->wc_id, '_stock', (string) $product->available_wc_stock );

		if ( 'no' === $wc_product->backorders ) {

			update_post_meta( $product->wc_id, '_stock_status', ( $product->available_wc_stock > 0 ? 'instock' : 'outofstock' ) );
		} else {

			update_post_meta( $product->wc_id, '_stock_status', ( $product->available_wc_stock >= 0 ? 'instock' : 'onbackorder' ) );
		}

		update_post_meta( $product->wc_id, '_last_mv_stock_update', $integration_update_id );

	}

	/**
	 * Push stock to Megaventory.
	 *
	 * @param int $starting_index as the position.
	 * @return array['starting_index','next_index','error_occurred','finished','message']
	 */
	public static function push_stock( $starting_index ) {

		$return_values = array(
			'starting_index' => $starting_index,
			'next_index'     => 0,
			'error_occurred' => false,
			'finished'       => false,
			'message'        => '',
		);

		$all_simple_products_count = self::wc_get_all_woocommerce_products_count();

		$page = ( $starting_index / MV_Constants::STOCK_BATCH_COUNT ) + 1; // starts from 1.

		$selected_products_to_sync_stock = self::wc_get_products_in_batches( MV_Constants::STOCK_BATCH_COUNT, $page );

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

			$synchronized_message = 'Quantity adjusted to Megaventory on ' . $current_date;

			update_option( 'megaventory_stock_synchronized_time', $synchronized_message );

			return $return_values;
		}

		$document_details_adj_plus  = array();
		$document_details_adj_minus = array();

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
			'APIKEY'  => get_api_key(),
			'Filters' => $filters,
		);

		$url      = get_url_for_call( MV_Constants::INVENTORY_LOCATION_STOCK_GET );
		$response = send_request_to_megaventory( $url, $stock_get_body );

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

			$adjust = $wc_qty - $mv_qty;

			if ( 0 < $adjust ) {

				$detail = array(
					'DocumentRowProductSKU' => $selected_product->sku,
					'DocumentRowQuantity'   => $adjust,
					'DocumentRowUnitPriceWithoutTaxOrDiscount' => $selected_product->unit_cost,
				);

				array_push( $document_details_adj_plus, $detail );

			} elseif ( 0 > $adjust ) {

				$detail = array(
					'DocumentRowProductSKU' => $selected_product->sku,
					'DocumentRowQuantity'   => $adjust * ( -1 ),
					'DocumentRowUnitPriceWithoutTaxOrDiscount' => $selected_product->unit_cost,
				);

				array_push( $document_details_adj_minus, $detail );
			}
		}

		if ( 0 === count( $document_details_adj_plus ) && 0 === count( $document_details_adj_minus ) ) {

			$args = array(
				'type'        => 'notice',
				'entity_name' => 'Adjustment creation',
				'entity_id'   => 0,
				'problem'     => 'No adjustment was needed for this iteration',
				'full_msg'    => '',
				'error_code'  => 0,
				'json_object' => '',
			);

			$e = new MVWC_Error( $args );

			$return_values = array(
				'starting_index' => $starting_index,
				'next_index'     => $starting_index + MV_Constants::STOCK_BATCH_COUNT,
				'error_occurred' => false,
				'finished'       => false,
				'message'        => 'Current synchronization: ' . ( $starting_index + $selected_products_to_sync_stock_count ) . ' of ' . $all_simple_products_count,
			);

			return $return_values;
		}

		$action = 'Insert';

		if ( 0 < count( $document_details_adj_plus ) ) {

			$mv_document_plus = array(
				'DocumentTypeId'           => -99,
				'DocumentSupplierClientID' => -1,
				'DocumentComments'         => 'This is the initial stock document that was created based on available quantity for the following products',
				'DocumentDetails'          => $document_details_adj_plus,
				'DocumentStatus'           => 'Pending',
			);

			$document_update = array(
				'APIKEY'                                => get_api_key(),
				'mvDocument'                            => $mv_document_plus,
				'mvRecordAction'                        => $action,
				'mvInsertUpdateDeleteSourceApplication' => 'woocommerce',
			);

			$url      = get_url_for_call( MV_Constants::DOCUMENT_UPDATE );
			$response = send_request_to_megaventory( $url, $document_update );

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

		if ( 0 < count( $document_details_adj_minus ) ) {

			$mv_document_minus = array(
				'DocumentTypeId'           => -98,
				'DocumentSupplierClientID' => -1,
				'DocumentComments'         => 'This is the initial stock document that was created based on available quantity for the following products',
				'DocumentDetails'          => $document_details_adj_minus,
				'DocumentStatus'           => 'Pending',
			);

			$document_update = array(
				'APIKEY'                                => get_api_key(),
				'mvDocument'                            => $mv_document_minus,
				'mvRecordAction'                        => $action,
				'mvInsertUpdateDeleteSourceApplication' => 'woocommerce',
			);

			$url      = get_url_for_call( MV_Constants::DOCUMENT_UPDATE );
			$response = send_request_to_megaventory( $url, $document_update );

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

		if ( MV_Constants::STOCK_BATCH_COUNT > $selected_products_to_sync_stock_count ) {

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

			$synchronized_message = 'Quantity adjusted to Megaventory on ' . $current_date;

			update_option( 'megaventory_stock_synchronized_time', $synchronized_message );

		} else {

			$return_values = array(
				'starting_index' => $starting_index,
				'next_index'     => $starting_index + MV_Constants::STOCK_BATCH_COUNT,
				'error_occurred' => false,
				'finished'       => false,
				'message'        => 'Current synchronization: ' . ( $starting_index + $selected_products_to_sync_stock_count ) . ' of ' . $all_simple_products_count,
			);

		}

		return $return_values;
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
}

