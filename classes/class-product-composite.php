<?php
/**
 * Product Composite class, to help with Composite Product logic.
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

require_once MEGAVENTORY__PLUGIN_DIR . 'class-api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-product.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-product-composite-material.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mv-constants.php';

/**
 * This class works as a model for a composite product.
 */
class Product_Composite {

	/**
	 * The composite product.
	 *
	 * @var Product $composite_product The composite product transformed into a Product object.
	 */
	public $composite_product;

	/**
	 * The materials of the composite product.
	 *
	 * @var Product_Composite_Material[] $materials The materials of the composite product.
	 */
	public $materials;

	/**
	 * Product_Composite constructor.
	 *
	 * @param Product                      $composite_product The composite product transformed into a Product object.
	 * @param Product_Composite_Material[] $materials The materials of the composite product.
	 */
	public function __construct( $composite_product, $materials ) {
		$this->composite_product = $composite_product;
		$this->materials         = $materials;
	}

	/**
	 * Get the Megaventory finished good product.
	 *
	 * @param \WC_Order_Item $order_item The order item object.
	 * @return Product_Composite|null The Megaventory finished good product.
	 */
	public static function get_composite_product( $order_item ) {

		$composite_product = Product::wc_find_product( $order_item->get_product_id() );

		if ( null === $composite_product ) {
			return null;
		}

		/** The materials of the composite product.
		 *
		 * @var \WC_Order_Item_Product[] $comp_order_items The materials of the composite product.
		 */
		$comp_order_items = wc_cp_get_composited_order_items( $order_item );

		if ( empty( $comp_order_items ) ) {
			return null;
		}

		$bundled_material_items = self::get_bundled_material_order_items( $comp_order_items );

		$finished_good_materials = array();

		$material_order_items = $comp_order_items;

		// merge the bundled order items(if any) with the composite order items.
		if ( ! empty( $bundled_material_items ) ) {

			$material_order_items = array_merge(
				$comp_order_items,
				$bundled_material_items
			);
		}

		foreach ( $material_order_items as $mat_order_item ) {

			$product = null;

			if ( $mat_order_item->get_variation_id() > 0 ) {

				$product = Product::wc_find_product( $mat_order_item->get_variation_id() );
			} else {

				$product = Product::wc_find_product( $mat_order_item->get_product_id() );
			}

			if ( null === $product ) {
				continue;
			}

			// if the product is a bundle, skip it, we only need the physical products.
			if ( 'bundle' === $product->type ) {
				continue;
			}

			$quantity = $mat_order_item->get_quantity() / $order_item->get_quantity();

			$material = new Product_Composite_Material( $product, $quantity );

			// if the product sku already exists in the finished_good_materials array,
			// add the quantity to the existing material.
			// we cannot have in the BOM the same product twice.
			$index = array_search(
				$material->product->sku,
				array_column( array_column( $finished_good_materials, 'product' ), 'sku' ),
				true
			);

			if ( false !== $index ) {

				$finished_good_materials[ $index ]->quantity += $quantity;

				continue;
			}

			$finished_good_materials[] = $material;
		}

		return new Product_Composite( $composite_product, $finished_good_materials );
	}

	/**
	 * Get the bundled order items of the bundle products(if any) in the composite product.
	 *
	 * @param \WC_Order_Item_Product[] $comp_order_items The composite order items.
	 * @return \WC_Order_Item_Product[] The bundled order items.
	 */
	public static function get_bundled_material_order_items( $comp_order_items ) {
		$bundled_material_items = array();

		if ( ! function_exists( 'wc_pb_get_bundled_order_items' ) ) {
			return $bundled_material_items;
		}

		foreach ( $comp_order_items as $comp_order_item ) {

			// if the product is a bundle, get the bundled items.
			$bundled_items = wc_pb_get_bundled_order_items( $comp_order_item );

			if ( ! empty( $bundled_items ) ) {

				array_push(
					$bundled_material_items,
					...$bundled_items
				);
			}
		}

		return $bundled_material_items;
	}

	/**
	 * Create new Production Orders in Megaventory for the finished goods of the composite product.
	 *
	 * @param MV_Order_Item[] $mv_order_items The order items.
	 * @param \WC_Order       $wc_order The WooCommerce order object.
	 * @param array           $mv_order The Megaventory order.
	 *
	 * @return void
	 */
	public static function create_work_orders_for_finished_goods( $mv_order_items, $wc_order, $mv_order ) {

		$location_id = $mv_order['SalesOrderInventoryLocationID'];

		if ( $location_id < 1 ) {

			return; // no location is set.
		}

		$finished_good_order_items = array_filter(
			$mv_order_items,
			function ( $item ) {
				return $item->is_finished_good;
			}
		);

		if ( empty( $finished_good_order_items ) ) {

			return; // no finished goods in the order.
		}

		$mv_product_ids = array_map(
			function ( $item ) {
				return $item->related_product->mv_id;
			},
			$finished_good_order_items
		);

		$mv_product_ids = array_unique( $mv_product_ids );

		// get the on hand quantity for the products in the location.
		$quantities = Product::mv_get_stock_for_location(
			$mv_product_ids,
			$location_id
		);

		foreach ( $finished_good_order_items as $mv_order_item ) {

			// get the on hand quantity for the product in the location.
			$stock = array_filter(
				$quantities,
				function ( $item ) use ( $mv_order_item ) {
					return $item['productID'] === $mv_order_item->related_product->mv_id;
				}
			);

			// initialize the quantity with negative value to indicate that the product is not in stock.
			// in case the product has no stock information(no transaction yet).
			$quantity = -1;

			if ( ! empty( $stock ) ) {

				$stock = array_values( $stock );

				$stock = $stock[0];

				// calculate the quantity.
				$quantity = $stock['StockPhysicalTotal'] + $stock['StockNonReceivedWOsTotal'] - $stock['StockNonShippedTotal'] - $stock['StockNonAllocatedWOsTotal'];
			}

			// if the quantity is less than 0, then we need to create a work order.
			if ( $quantity < 0 ) {

				// create work order.
				self::create_work_order(
					$mv_order_item->related_product->sku,
					$mv_order_item->quantity_in_order,
					$location_id,
					$wc_order
				);

			}
		}
	}

	/**
	 * Create new Production Order in Megaventory.
	 *
	 * @param string    $finished_good_sku The SKU of the finished good product.
	 * @param int       $quantity The quantity to produce.
	 * @param int       $location_id The location id to create the production order.
	 * @param \WC_Order $wc_order The WooCommerce order object.
	 *
	 * @return array|null The mvWorkOrder object or null if the request failed.
	 */
	public static function create_work_order( $finished_good_sku, $quantity, $location_id, $wc_order ) {

		$work_order = array();

		$work_order['WorkOrderFinishedGoodSKU']      = $finished_good_sku;
		$work_order['WorkOrderOrderedQuantity']      = $quantity;
		$work_order['WorkOrderInventoryLocationID']  = $location_id;
		$work_order['WorkOrderReferenceApplication'] = 'woocommerce';
		$work_order['WorkOrderReferenceNo']          = $wc_order->get_id();

		$request = array();

		$request['mvWorkOrder']                           = $work_order;
		$request['mvRecordAction']                        = MV_Constants::MV_RECORD_ACTION['Insert'];
		$request['mvInsertUpdateDeleteSourceApplication'] = 'woocommerce';

		$url = \Megaventory\API::get_url_for_call( MV_Constants::WORK_ORDER_UPDATE );

		$data = \Megaventory\API::send_request_to_megaventory( $url, $request );

		if ( '0' !== ( $data['ResponseStatus']['ErrorCode'] ) ) {

			// Log the error.
			$args = array(
				'type'        => 'error',
				'entity_name' => 'order by: ' . $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name(),
				'entity_id'   => array( 'wc' => $wc_order->get_id() ),
				'problem'     => 'Failed to create production order for finished good: ' . $finished_good_sku,
				'full_msg'    => $data['ResponseStatus']['Message'],
				'error_code'  => $data['ResponseStatus']['ErrorCode'],
				'json_object' => $data['json_object'],
			);

			new \Megaventory\Models\MVWC_Error( $args );

			return null;
		}

		// Log the success.
		$args = array(
			'entity_id'          => array(
				'wc' => $wc_order->get_id(),
				'mv' => $data['mvWorkOrder']['WorkOrderId'],
			),
			'entity_type'        => 'order',
			'entity_name'        => $data['mvWorkOrder']['WorkOrderTypeAbbreviation'] . ' ' . $data['mvWorkOrder']['WorkOrderNo'],
			'transaction_status' => 'Insert',
			'full_msg'           => 'The production order has been created successfully.',
			'success_code'       => 1,
		);

		new \Megaventory\Models\MVWC_Success( $args );

		return $data['mvWorkOrder'];
	}

	/**
	 * Get the Megaventory finished good product.
	 *
	 * @return Product|null The Megaventory finished good product.
	 */
	public function get_finished_good_product() {

		$mv_raw_materials = array();

		foreach ( $this->materials as $material ) {
			$mv_raw_materials[] = array(
				'ProductSKU' => $material->product->sku,
				'Quantity'   => $material->quantity,
			);
		}

		$request = array();

		$request['mvRawMaterials'] = $mv_raw_materials;
		$request['ExactMatch']     = true;

		$url = \Megaventory\API::get_url_for_call( MV_Constants::PRODUCT_BOM_GET_BY_MATERIALS );

		$data = \Megaventory\API::send_request_to_megaventory( $url, $request );

		if ( '0' !== ( $data['ResponseStatus']['ErrorCode'] ) ||
			empty( $data['mvProductBOMs'] ) ) {

			return self::create_finished_good_product();
		}

		$finished_good_product = new Product();

		$finished_good_product->mv_id = $data['mvProductBOMs'][0]['ProductID'];
		$finished_good_product->sku   = $data['mvProductBOMs'][0]['ProductSKU'];

		return $finished_good_product;
	}

	/**
	 * Create the Megaventory finished good product and its BOM.
	 *
	 * @return Product|null The Megaventory finished good product.
	 */
	private function create_finished_good_product() {

		$finished_good_product = self::create_finished_good();

		if ( null === $finished_good_product ) { // finished good product creation failed.

			return null;
		}

		$result = self::create_bom( $finished_good_product );

		return $finished_good_product;
	}

	/**
	 * Create the Megaventory finished good product.
	 *
	 * @return Product|null The Megaventory finished good product.
	 */
	private function create_finished_good() {

		$request = array();

		$this->composite_product->mv_type = MV_Constants::MV_PRODUCT_TYPE['ManufactureFromWorkOrder'];

		$request = $this->composite_product->generate_update_json( null, false );

		$request['mvProduct']['ProductSKU'] = self::generate_finished_good_sku();

		$url = \Megaventory\API::get_url_for_call( MV_Constants::PRODUCT_UPDATE );

		$data = \Megaventory\API::send_request_to_megaventory( $url, $request );

		if ( '0' !== ( $data['ResponseStatus']['ErrorCode'] ) ) {

			// Log the error.
			$args = array(
				'type'        => 'error',
				'entity_name' => 'finished good product: ' . $this->composite_product->name . ' (' . $this->composite_product->sku . ')',
				'entity_id'   => array( 'wc' => $this->composite_product->wc_id ),
				'problem'     => 'Failed to create finished good product',
				'full_msg'    => $data['ResponseStatus']['Message'],
				'error_code'  => $data['ResponseStatus']['ErrorCode'],
				'json_object' => $data['json_object'],
			);

			new \Megaventory\Models\MVWC_Error( $args );

			return null;
		}

		$product = Product::mv_convert( $data['mvProduct'] );

		return $product;
	}

	/**
	 * Generate the request to create the BOM for the Megaventory finished good product.
	 *
	 * @param Product $finished_good_product The Megaventory finished good product.
	 * @return bool  True if the request was successful, false otherwise.
	 */
	private function create_bom( $finished_good_product ) {
		// Generate the request.

		$request = array();

		$mv_raw_materials = array();

		foreach ( $this->materials as $material ) {
			$mv_raw_materials[] = array(
				'ProductID'           => $material->product->mv_id, // check if it is ok with sku.
				'ProductSKU'          => $material->product->sku,
				'RawMaterialQuantity' => $material->quantity,
			);
		}

		$mv_product_bom = array();

		$mv_product_bom['ProductID']      = $finished_good_product->mv_id;
		$mv_product_bom['mvRawMaterials'] = $mv_raw_materials;

		$request['mvProductBOM'] = $mv_product_bom;

		$url = \Megaventory\API::get_url_for_call( MV_Constants::PRODUCT_BOM_UPDATE );

		$data = \Megaventory\API::send_request_to_megaventory( $url, $request );

		if ( '0' !== ( $data['ResponseStatus']['ErrorCode'] ) ) {

			// Log the error.
			$args = array(
				'type'        => 'error',
				'entity_name' => 'Finished good product: ' . $finished_good_product->name . ' (' . $finished_good_product->sku . ')',
				'entity_id'   => array( 'mv' => $finished_good_product->mv_id ),
				'problem'     => 'Failed to create BOM for finished good product',
				'full_msg'    => $data['ResponseStatus']['Message'],
				'error_code'  => $data['ResponseStatus']['ErrorCode'],
				'json_object' => $data['json_object'],
			);

			new \Megaventory\Models\MVWC_Error( $args );

			return false;
		}

		return true;
	}

	/**
	 * Generate the SKU for the Megaventory finished good product using the materials' SKU and quantity concatenated and hashed with md5.
	 *
	 * @return string The SKU for the Megaventory finished good product.
	 */
	private function generate_finished_good_sku() {

		// if we want to create a unique SKU based of materials, we can concatenate the materials' SKU and quantity and hash it with md5.
		// for each this->materials sku += material->sku + material->quantity . separator.
		// md5( sku ).

		$microtime    = microtime( true );
		$milliseconds = sprintf( '%03d', ( $microtime - floor( $microtime ) ) * 1000 );

		$sku = 'bom_' . get_date_from_gmt( gmdate( 'Y-m-d H:i:s' ), 'Y-m-d H:i:s' ) . '.' . $milliseconds;

		return $sku;
	}
}
