<?php
/**
 * Order Item class.
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
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'class-api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/class-address.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-product.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-coupon.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-tax.php';

/**
 * This class works as a model for an order item
 * It holds all required information to create a sales order row in megaventory.
 */
class MV_Order_Item {

	/**
	 * Related Product object.
	 *
	 * @var Product
	 */
	public $related_product;

	/**
	 * Quantity In Order.
	 *
	 * @var float
	 */
	public $quantity_in_order;

	/**
	 * Price In Order
	 *
	 * @var float
	 */
	public $price_in_order;

	/**
	 * MV Tax Id.
	 *
	 * @var int
	 */
	public $tax_id;

	/**
	 * MV Discount Id.
	 *
	 * @var int
	 */
	public $discount_id;

	/**
	 * Order Item is a Product Bundle.
	 *
	 * @var bool
	 */
	public $is_bundle;

	/**
	 * Array of a Product Bundle's children order items.
	 *
	 * @var \WC_Order_Item[]
	 */
	public $children_order_items;

	/**
	 * Order Item is part of bundle.
	 *
	 * @var bool
	 */
	public $is_bundled_item;

	/**
	 * Order Item Id of a bundled product's Parent Row.
	 *
	 * @var int
	 */
	public $parent_row_id;

	/**
	 * MV_Order_Item constructor
	 *
	 * @param Product $product The related product.
	 */
	public function __construct( $product = null ) {

		$this->related_product = empty( $product ) ? new Product() : $product;
		$this->is_bundle       = false;
		$this->is_bundled_item = false;

	}

	/**
	 * Get MV_Order_Item from WC_Order_Item
	 *
	 * @param \WC_Order_Item|array $wc_order_item The WooCommerce order item to map.
	 * @param array                $coupons_array Array of coupon arrays.
	 * @return MV_Order_Item
	 */
	public static function from_wc_order_item( $wc_order_item, &$coupons_array ) {

		$use_discount_sequentially = ( 'yes' === get_option( 'woocommerce_calc_discounts_sequentially', 'no' ) ) ? true : false;

		$product = new Product();
		if ( 0 === $wc_order_item->get_data()['variation_id'] ) {

			$product = Product::wc_find_product( $wc_order_item->get_data()['product_id'] );
		} else {

			$product = Product::wc_find_product( $wc_order_item->get_data()['variation_id'] );
		}

		$mv_order_item = new MV_Order_Item( $product );

		/** Discount
		 *
		 * @var Coupon[] $percentage_product_coupons
		 */
		$percentage_product_coupons = array();

		foreach ( $coupons_array[ Coupon::PERCENTAGE_COUPONS_KEY ] as $coupon ) {

			if ( self::apply_coupon( $product, $coupon ) ) {

				array_push( $percentage_product_coupons, $coupon );
			}
		}

		$discount = null;

		if ( 1 === count( $percentage_product_coupons ) ) {

			$discount = $percentage_product_coupons[0];

			$discount->load_corresponding_discount_from_megaventory();

		} elseif ( count( $percentage_product_coupons ) > 1 && ! $use_discount_sequentially ) {

			/* create compound; */
			$ids = array();

			foreach ( $percentage_product_coupons as $coupon ) {

				array_push( $ids, $coupon->wc_id );
			}

			$discount = Coupon::mv_get_or_create_compound_percent_coupon( $ids );
		}

		/*
			In case we have in the same order percentage and fixed discounts we will add them name in order tags.
			We will not send a discount in sales_row.
			The price will be the discounted.
		*/
		if ( ( ! empty( $coupons_array[ Coupon::PERCENTAGE_COUPONS_KEY ] ) && ! empty( $coupons_array[ coupon::FIXED_COUPONS_KEY ] ) ) ||
			( ! empty( $coupons_array[ Coupon::PERCENTAGE_COUPONS_KEY ] ) && ! empty( $coupons_array[ Coupon::PRODUCT_COUPONS_KEY ] ) ) ) {

			$discount = null;

		} else {

			$coupons_array[ Coupon::ORDER_TAG_COUPONS_KEY ] = '';

		}

		// Price.
		$unit_price = self::get_unit_price_prediscounted( $wc_order_item, $discount );

		// Tax.

		/*
		Woocommerce applies the taxes one by one to the (post discounted price if the order has discounts) price and then
		adds all of them. We create in Megaventory a compound tax with all the rates.
		So sometimes it is possible to have miss match in the second percentage digit.
		*/
		$tax = Tax::get_sales_row_tax( $wc_order_item );

		// Check if the passed product is a parent Bundle or a part of a bundle.
		if ( function_exists( 'wc_pb_get_bundled_order_items' ) && function_exists( 'wc_pb_get_bundled_order_item_container' ) ) {

			$bundle_item_children = wc_pb_get_bundled_order_items( $wc_order_item );

			if ( ! empty( $bundle_item_children ) ) {

				$mv_order_item->is_bundle            = true;
				$mv_order_item->children_order_items = $bundle_item_children;

			} else {

				$parent_bundle = wc_pb_get_bundled_order_item_container( $wc_order_item );

				if ( $parent_bundle ) {

					$mv_order_item->is_bundled_item = true;
					$mv_order_item->parent_row_id   = $parent_bundle->get_id();

					$mv_order_item->related_product->description .= ' ' . $mv_order_item->related_product->version . ' [Part of \'' . $parent_bundle['name'] . '\']';

				}
			}
		}

		$mv_order_item->quantity_in_order = $wc_order_item->get_quantity();
		$mv_order_item->price_in_order    = $unit_price;
		$mv_order_item->discount_id       = ( ! empty( $discount->mv_id ) ? $discount->mv_id : 0 );
		$mv_order_item->tax_id            = ( ! empty( $tax->mv_id ) ? $tax->mv_id : 0 );

		return $mv_order_item;

	}

	/**
	 * Generates a dictionary of LocationID => MV_Order_Items depending on shipping zones priority.
	 * If shipping zones are not active, it will assign all items to the default location.
	 *
	 * @param MV_Order_Item[] $mv_order_item_arr The array of mv order items to be handled.
	 * @param \WC_Order       $order             The WC Order Object.
	 * @param array           $coupons_arrays    An array of coupons arrays.
	 * @return array
	 */
	public static function generate_items_per_location_dict( $mv_order_item_arr, $order, $coupons_arrays ) {

		$details_per_location   = array();
		$default_location_id    = (int) get_option( 'default-megaventory-inventory-location' );
		$shipping_zones_enabled = \Megaventory\Models\Shipping_Zones::megaventory_are_shipping_zones_enabled();

		if ( ! $shipping_zones_enabled ) {
			self::assign_all_items_to_specific_location( $mv_order_item_arr, $default_location_id, $details_per_location );
			return $details_per_location;
		}

		$order_zone = \Megaventory\Models\Shipping_Zones::megaventory_get_shipping_zone_from_order( $order );

		$loc_priorities = \Megaventory\Models\Shipping_Zones::megaventory_get_location_priority_for_zone( $order_zone->get_id() );

		// First we have to find a location that has every item in stock, if such a location exists.
		$single_location_fulfillment = self::find_location_with_avl_stock_for_all_items( $loc_priorities, $mv_order_item_arr );

		if ( $single_location_fulfillment ) {
			self::assign_all_items_to_specific_location( $mv_order_item_arr, $single_location_fulfillment, $details_per_location );
			return $details_per_location;
		}

		foreach ( $mv_order_item_arr as $mv_item ) {

			// If this is a product that is part of a bundle, we continue as it was handled when the parent Bundle was assigned.
			if ( $mv_item->is_bundled_item ) {
				continue;
			}

			// If this is a parent Product Bundle order item, we get all its children rows and assign them together to the same location.
			if ( $mv_item->is_bundle ) {

				$bundled_order_items = self::get_mv_items_from_wc_items( $mv_item->children_order_items, $coupons_arrays );

				self::assign_bundled_order_items_to_location( $mv_item, $bundled_order_items, $loc_priorities, $details_per_location );

				continue;
			}

			self::assign_order_item_to_location( $loc_priorities, $mv_item, $details_per_location );

		}

		return $details_per_location;

	}

	/**
	 * Given an array of order items and location priorities, this function returns the
	 * highest priority location in which all items are in stock, or False if none exists.
	 *
	 * @param array           $loc_priorities    The array with location priorities for a given shipping zone.
	 * @param MV_Order_Item[] $mv_order_item_arr The array of the mv Order Items to check.
	 * @return int|false
	 */
	public static function find_location_with_avl_stock_for_all_items( $loc_priorities, $mv_order_item_arr ) {

		$avl_loc = array_filter(
			$loc_priorities,
			function ( $mv_loc_id ) use ( $mv_order_item_arr ) {
				foreach ( $mv_order_item_arr as $order_item ) {
					if ( $order_item->is_bundle ) {
						continue; }
					$has_stock = $order_item->related_product->get_mv_stock_for_location( $mv_loc_id ) >= $order_item->quantity_in_order;
					if ( ! $has_stock ) {
						return false;}
				}
				return true;
			}
		);

		$first_location_id = reset( $avl_loc );

		return $first_location_id;
	}

	/**
	 * Get array of mv_order_items from array of wc order items.
	 *
	 * @param \WC_Order_Item[] $wc_items       Array of WC Order Items.
	 * @param array            $coupons_arrays Array of coupon arrays for order.
	 * @return MV_Order_Item[]
	 */
	public static function get_mv_items_from_wc_items( $wc_items, $coupons_arrays ) {

		$mv_order_item_arr = array();

		foreach ( $wc_items as $item ) {

			$mv_order_item = self::from_wc_order_item( $item, $coupons_arrays );

			$mv_order_item_arr[] = $mv_order_item;
		}

		return $mv_order_item_arr;

	}

	/**
	 * Append an MV_Order_Item to the correct entry in the given Location/Detail dictionary,
	 * depending on Location Priority and stock availability.
	 *
	 * @param array         $loc_priorities       Location Priority array.
	 * @param MV_Order_Item $mv_order_item        The MV_Order_Item to handle.
	 * @param array         $details_per_location Array (dictionary) of details already added per location, by ref.
	 * @return void
	 */
	public static function assign_order_item_to_location( $loc_priorities, $mv_order_item, &$details_per_location ) {

		$row_is_assigned = false;

		// Foreach will always iterate in the order of the array so we know lower index -> higher priority.
		foreach ( $loc_priorities as $location_id ) {

			$stock_for_location = $mv_order_item->related_product->get_mv_stock_for_location( $location_id );

			// might need to also create a dictionary for SKU-QTY ORDERED for cases where the same SKU exists multiple times in the same order.
			if ( $stock_for_location < $mv_order_item->quantity_in_order ) {
				continue;
			}

			if ( array_key_exists( $location_id, $details_per_location ) ) {
				$details_per_location[ $location_id ][] = $mv_order_item;
			} else {
				$details_per_location[ $location_id ]   = array();
				$details_per_location[ $location_id ][] = $mv_order_item;
			}

			$row_is_assigned = true;

			break;
		}

		// If the order item is not assigned, we need to get the first key of the array which is the highest priority location, and assign the order item there.
		if ( ! $row_is_assigned ) {

			$first_location_id = reset( $loc_priorities );

			if ( array_key_exists( $first_location_id, $details_per_location ) ) {
				$details_per_location[ $first_location_id ][] = $mv_order_item;
			} else {
				$details_per_location[ $first_location_id ]   = array();
				$details_per_location[ $first_location_id ][] = $mv_order_item;
			}
		}
	}

	/**
	 * Append a Product Bundle Order Item and all its included products
	 * to to the correct entry in the given Location/Detail dictionary,
	 * depending on Location Priority and stock availability.
	 *
	 * @param MV_Order_Item   $mv_bundle_order_item The product bundle order item to handle.
	 * @param MV_Order_Item[] $bundled_order_items  The bundle's included products mapped as order items.
	 * @param array           $loc_priorities       The location priority array for the relevant shipping zone.
	 * @param array           $details_per_location The array of already assigned details per location.
	 * @return void
	 */
	public static function assign_bundled_order_items_to_location( $mv_bundle_order_item, $bundled_order_items, $loc_priorities, &$details_per_location ) {

		// First we have to find a location that has every bundled item in stock, if such a location exists.
		$single_location_fulfillment = self::find_location_with_avl_stock_for_all_items( $loc_priorities, $bundled_order_items );

		$first_location_id = 0;

		if ( $single_location_fulfillment ) {
			$first_location_id = $single_location_fulfillment;
		} else {
			$first_location_id = reset( $loc_priorities );
		}

		$detail_array_to_send = array();

		$detail_array_to_send[] = $mv_bundle_order_item; // Add the parent Bundle first so it appears above included products.

		$include_bundled_items = array_merge( $detail_array_to_send, $bundled_order_items ); // Merge it with included products.

		self::assign_all_items_to_specific_location( $include_bundled_items, $first_location_id, $details_per_location );

	}

	/**
	 * Assigns all given mv_order_items to a specific location in the given dictionary of details per location.
	 *
	 * @param MV_Order_Item[] $mv_order_items       The array of mv_order_items to assign.
	 * @param string          $location_id          The id of the location.
	 * @param array           $details_per_location The dictionary of details per location passed by ref.
	 * @return void
	 */
	public static function assign_all_items_to_specific_location( $mv_order_items, $location_id, &$details_per_location ) {

		if ( array_key_exists( $location_id, $details_per_location ) ) {
			$details_per_location[ $location_id ] = array_merge( $details_per_location[ $location_id ], $mv_order_items );
		} else {
			$details_per_location[ $location_id ] = $mv_order_items;
		}

	}

	/**
	 * Get price with percentage amount if applied.
	 *
	 * @param \WC_Order_Item_Product $item_data as order item.
	 * @param Coupon                 $discount as Megaventory discount.
	 * @return float
	 */
	private static function get_unit_price_prediscounted( $item_data, $discount ) {

		$line_quantity = $item_data->get_quantity();

		$unit_total = $item_data->get_total() / $line_quantity;

		$line_subtotal = $item_data->get_subtotal();

		if ( $discount ) {

			$unit_total = ( $line_subtotal / $line_quantity );
		}

		return $unit_total; // This is unit price with percentage amount if applied.

	}

	/**
	 * Apply Coupon.
	 *
	 * @param Product $product as Product class.
	 * @param Coupon  $coupon as Coupon class.
	 */
	private static function apply_coupon( $product, $coupon ) {

		if ( ! $coupon->type || 'fixed_cart' === $coupon->type ) {
			return false;
		}

		if ( ! $coupon->applies_to_sales() && $product->sale_active ) {
			return false;
		}

		$incl_ids       = $coupon->get_included_products( true );
		$included_empty = count( $incl_ids ) <= 0;
		$included       = in_array( $product->wc_id, $incl_ids, true );
		$excluded       = in_array( $product->wc_id, $coupon->get_excluded_products( true ), true );

		$incl_ids_cat       = $coupon->get_included_products_categories( true );
		$included_empty_cat = count( $incl_ids_cat ) <= 0;
		$included_cat       = in_array( $product->wc_id, $incl_ids_cat, true );
		$excluded_cat       = in_array( $product->wc_id, $coupon->get_excluded_products_categories( true ), true );

		return ( ( $included_empty || $included ) || ( ( $included_empty_cat && $included_empty ) || $included_cat ) ) && ( ! $excluded && ! $excluded_cat );
	}

}
