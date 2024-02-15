<?php
/**
 * Coupon class.
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

use WooCommerce;

/**
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'class-api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/class-address.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-error.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-errors.php';

/**
 * This class works a model for coupons (WC) / discounts (MV).
 */
class Coupon {

	const PERCENTAGE_COUPONS_KEY = 'percentage';

	const FIXED_COUPONS_KEY = 'fixed';

	const PRODUCT_COUPONS_KEY = 'product';

	const ORDER_TAG_COUPONS_KEY = 'order_tags';

	/**
	 * Megaventory discount id.
	 *
	 * @var int
	 */
	public $mv_id;

	/**
	 * Woocommerce coupon id.
	 *
	 * @var int
	 */
	public $wc_id;

	/**
	 * Coupon/discount name.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Coupon/discount description.
	 *
	 * @var string
	 */
	public $description = '';

	/**
	 * Coupon/discount rate.
	 *
	 * @var double
	 */
	public $rate = 0.0;

	/**
	 * Coupon/discount type either 'percent' or 'fixed'
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Errors messages.
	 *
	 * @var MVWC_Errors
	 */
	public $errors;

	/**
	 * Succeeded messages.
	 *
	 * @var MVWC_Successes
	 */
	public $successes;

	/**
	 * DiscountGet call.
	 *
	 * @var string
	 */
	private static $mv_url_discount_get = 'DiscountGet';

	/**
	 * DiscountUpdate call.
	 *
	 * @var string
	 */
	private static $mv_url_discount_update = 'DiscountUpdate';

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->errors    = new MVWC_Errors();
		$this->successes = new MVWC_Successes();
	}

	/**
	 * Log errors.
	 *
	 * @param string $problem as problem message.
	 * @param string $full_msg as error's full message.
	 * @param string $code as error's code.
	 * @param string $type as error's type.
	 * @param string $json_object as string.
	 * @return void
	 */
	public function log_error( $problem, $full_msg, $code, $type = 'error', $json_object = '' ) {

		$args = array(
			'entity_id'   => array(
				'wc' => $this->wc_id,
				'mv' => $this->mv_id,
			),
			'entity_name' => ( null === $this->name ) ? $this->description : $this->name,
			'problem'     => $problem,
			'full_msg'    => $full_msg,
			'error_code'  => $code,
			'json_object' => $json_object,
			'type'        => $type,
		);
		$this->errors->log_error( $args );
	}

	/**
	 * Log successes.
	 *
	 * @param string $transaction_status as transaction status.
	 * @param string $full_msg as success full message.
	 * @param string $code as success code.
	 * @return void
	 */
	public function log_success( $transaction_status, $full_msg, $code ) {

		$args = array(
			'entity_id'          => array(
				'wc' => $this->wc_id,
				'mv' => $this->mv_id,
			),
			'entity_type'        => 'coupon',
			'entity_name'        => $this->name,
			'transaction_status' => $transaction_status,
			'full_msg'           => $full_msg,
			'success_code'       => $code,
		);

		$this->successes->log_success( $args );
	}

	/**
	 * Get all WooCommerce coupons.
	 *
	 * @return array
	 */
	public static function wc_all() {

		global $wpdb;
		$results = $wpdb->get_results( // phpcs:ignore
			"
				SELECT 
					{$wpdb->prefix}posts.ID as id, 
					{$wpdb->prefix}posts.post_title as name, 
					{$wpdb->prefix}posts.post_excerpt as description,
					meta1.meta_value as rate, 
					meta2.meta_value as discount_type 
				FROM 
					{$wpdb->prefix}posts, 
					{$wpdb->prefix}postmeta as meta1, 
					{$wpdb->prefix}postmeta as meta2 
				WHERE {$wpdb->prefix}posts.post_type = 'shop_coupon' 
					AND {$wpdb->prefix}posts.post_status = 'publish' 
					AND meta1.meta_key = 'coupon_amount' 
					AND meta1.post_id = {$wpdb->prefix}posts.ID
					AND meta2.meta_key = 'discount_type' 
					AND	meta2.post_id = meta1.post_id",
			ARRAY_A
		);

		$coupons = array();

		foreach ( $results as $number => $buffer ) {
				$coupon = new Coupon();

				$coupon->wc_id       = $buffer['id'];
				$coupon->name        = $buffer['name'];
				$coupon->rate        = $buffer['rate'];
				$coupon->description = $buffer['description'];
				$coupon->type        = $buffer['discount_type'];
				array_push( $coupons, $coupon );
		}

		return $coupons;
	}

	/**
	 * Finds coupon by id in WooCommerce.
	 *
	 * @param int $id as coupon id.
	 * @return Coupon|null
	 */
	public static function wc_find_coupon( $id ) {

		$wc_coupon = new \WC_Coupon( $id );

		$coupon = new Coupon();

		$coupon->wc_id       = $wc_coupon->get_id();
		$coupon->name        = $wc_coupon->get_code();
		$coupon->rate        = $wc_coupon->get_amount();
		$coupon->description = $wc_coupon->get_description();
		$coupon->type        = $wc_coupon->get_discount_type();

		return $coupon;
	}

	/**
	 * Finds Coupon ny name is Woocommerce.
	 *
	 * @param string $name as coupon's name.
	 * @return Coupon|null
	 */
	public static function wc_find_by_name( $name ) {

		global $wpdb;
		$results = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"
					SELECT 
						{$wpdb->prefix}posts.ID as id, 
						{$wpdb->prefix}posts.post_title as name, 
						{$wpdb->prefix}posts.post_excerpt as description,
						meta1.meta_value as rate, 
						meta2.meta_value as discount_type 
					FROM 
						{$wpdb->prefix}posts, 
						{$wpdb->prefix}postmeta as meta1, 
						{$wpdb->prefix}postmeta as meta2 
					WHERE {$wpdb->prefix}posts.post_type = 'shop_coupon' 
						AND {$wpdb->prefix}posts.post_title = %s
						AND {$wpdb->prefix}posts.post_status = 'publish' 
						AND meta1.meta_key = 'coupon_amount' 
						AND meta1.post_id = {$wpdb->prefix}posts.ID
						AND meta2.meta_key = 'discount_type' 
						AND	meta2.post_id = meta1.post_id",
				$name
			),
			ARRAY_A
		);

		if ( 0 === count( $results ) ) {
			return null;
		}
		$buffer = $results[0];

		$coupon = new Coupon();

		$coupon->wc_id       = $buffer['id'];
		$coupon->name        = $buffer['name'];
		$coupon->rate        = $buffer['rate'];
		$coupon->description = $buffer['description'];
		$coupon->type        = $buffer['discount_type'];

		return $coupon;
	}

	/**
	 * Returns excluded products.
	 *
	 * @param boolean $by_ids as boolean.
	 * @return array
	 */
	public function get_excluded_products( $by_ids = false ) {

		$ids = get_post_meta( $this->wc_id, 'exclude_product_ids', true );
		if ( ! $ids ) {
			return array();
		}

		$temp = array();
		foreach ( explode( ',', $ids ) as $id ) {
			$id      = (int) $id;
			$to_push = ( $by_ids ? $id : Product::wc_find_product( $id ) );
			array_push( $temp, $to_push );
		}

		return $temp;
	}

	/**
	 * Returns included products.
	 *
	 * @param bool $by_ids as boolean.
	 * @return array
	 */
	public function get_included_products( $by_ids = false ) {

		$ids = get_post_meta( $this->wc_id, 'product_ids', true );  // true returns string of foreign keys separated by comma.
		if ( ! $ids ) {
			return array();
		}

		$temp = array();
		foreach ( explode( ',', $ids ) as $id ) {
			$id      = (int) $id;
			$to_push = ( $by_ids ? $id : Product::wc_find_product( $id ) );
			array_push( $temp, $to_push );
		}

		return $temp;
	}

	/**
	 * Returns included product categories.
	 *
	 * @return array[]
	 */
	public function get_included_products_categories() {

		$ids = get_post_meta( $this->wc_id, 'product_categories', true );

		if ( ! $ids ) {
			return array();
		}

		return $ids;
	}

	/**
	 * Returns excluded product categories.
	 *
	 * @return array[]
	 */
	public function get_excluded_products_categories() {

		$ids = get_post_meta( $this->wc_id, 'exclude_product_categories', true );

		if ( ! $ids ) {
			return array();
		}

		return $ids;
	}

	/**
	 * Check if a coupon applies to whole sales order.
	 *
	 * @return bool
	 */
	public function applies_to_sales() {

		return ! ( 'yes' === get_post_meta( $this->wc_id, 'exclude_sale_items', true ) );
	}

	/**
	 * Get or Create a Discount.
	 *
	 * @param array[int] $ids coupons ids.
	 * @return Coupon
	 */
	public static function mv_get_or_create_compound_percent_coupon( $ids ) {
		$coupon = self::init_compound_percent_coupon( $ids );

		if ( ! $coupon->load_corresponding_discount_from_megaventory() ) {
			$coupon->mv_save();
		}

		return $coupon;
	}

	/**
	 * Returns coupons as combination of name rate.
	 *
	 * @return array
	 */
	public static function wc_all_as_name_rate() {

		global $wpdb;
		$results = $wpdb->get_results( // phpcs:ignore
			"SELECT {$wpdb->prefix}posts.ID as id, 
					{$wpdb->prefix}posts.post_title as name, 
					{$wpdb->prefix}postmeta.meta_value as rate FROM 
					{$wpdb->prefix}posts, 
					{$wpdb->prefix}postmeta 
			WHERE {$wpdb->prefix}posts.post_type = 'shop_coupon' 
				AND {$wpdb->prefix}posts.post_status = 'publish' 
				AND {$wpdb->prefix}postmeta.meta_key = 'coupon_amount' 
				AND {$wpdb->prefix}postmeta.post_id = {$wpdb->prefix}posts.ID",
			ARRAY_A
		);

		$coupons = array();

		// initialize our "hashtable".
		foreach ( $results as $number => $coupon ) {
			$coupons[ $coupon['name'] ] = array();
		}

		foreach ( $results as $number => $coupon ) {
			if ( ! in_array( $coupon['rate'], $coupons[ $coupon['name'] ], true ) ) {
				array_push( $coupons[ $coupon['name'] ], $coupon['rate'] );
			}
		}

		return $coupons;
	}

	/**
	 * Discount initialization.
	 *
	 * @param array $coupons_ids as coupons ids.
	 * @return Coupon
	 */
	private static function init_compound_percent_coupon( $coupons_ids ) {

		$used_coupons = array();
		$all          = self::wc_all();

		foreach ( $all as $coupon ) {
			if ( in_array( $coupon->wc_id, $coupons_ids, true ) ) {
				array_push( $used_coupons, $coupon );
			}
		}
		$names      = array();
		$final_rate = 0;

		foreach ( $used_coupons as $coupon ) {
			array_push( $names, $coupon->name );
			$final_rate += $coupon->rate;
		}

		if ( $final_rate > 100 ) {
			$final_rate = 100;
		}

		sort( $names );
		$name = implode( '-', $names );
		$name = substr( $name, 0, 99 ); // Tax name in Megaventory is less than 100 characters.

		$compound_coupon              = new Coupon();
		$compound_coupon->description = 'compound_coupon: ' . $name;
		$compound_coupon->name        = $name;
		$compound_coupon->rate        = $final_rate;
		$compound_coupon->type        = 'percent';

		return $compound_coupon;
	}

	/**
	 * Check if Coupon exists in Megaventory as Discount.
	 *
	 * @return bool
	 */
	public function load_corresponding_discount_from_megaventory() {

		if ( 'percent' !== $this->type ) {

			return false;
		}

		$data = $this->get_megaventory_discount_by_name_rate();

		if ( empty( $data['mvDiscounts'] ) ) {

			return false;

		} else {

			$this->mv_id       = $data['mvDiscounts'][0]['DiscountID'];
			$this->description = $data['mvDiscounts'][0]['DiscountDescription'];
			return true;
		}
	}

	/**
	 * Get all Discounts
	 *
	 * @return string
	 */
	private function json_get_all_from_discount() {

		$discount_object = new \stdClass();
		$object_to_send  = \Megaventory\API::wrap_json( $discount_object );

		/**
		 * $object_to_send = wp_json_encode( $object_to_send );
		 */

		return $object_to_send;
	}

	/**
	 * Get discount by name.
	 *
	 * @return string
	 */
	private function json_is_name_present() {

		$discount_object_fields = new \stdClass();
		$discount_object        = new \stdClass();
		$discount_object_fields = array(
			'AndOr'          => 'And',
			'FieldName'      => 'DiscountName',
			'SearchOperator' => 'Equals',
			'SearchValue'    => $this->name,
		);

		$discount_object->filters = $discount_object_fields;
		$discount_object          = \Megaventory\API::wrap_json( $discount_object );

		/**
		 * $json_object = wp_json_encode( $discount_object );
		 */

		return $discount_object;
	}

	/**
	 * Get Discount by name and rate.
	 *
	 * @return string
	 */
	private function get_megaventory_discount_by_name_rate() {

		$discount_filter_1 = array();
		$discount_filter_2 = array();
		$discount_object   = new \stdClass();

		$discount_filter_1 = array(
			'AndOr'          => 'And',
			'FieldName'      => 'DiscountValue',
			'SearchOperator' => 'Equals',
			'SearchValue'    => $this->rate,
		);
		$discount_filter_2 = array(
			'AndOr'          => 'And',
			'FieldName'      => 'DiscountName',
			'SearchOperator' => 'Equals',
			'SearchValue'    => $this->name,
		);

		$discount_object->filters = array( $discount_filter_1, $discount_filter_2 );
		$discount_object          = \Megaventory\API::wrap_json( $discount_object );

		/**
		 * $json_object = wp_json_encode( $discount_object );
		 */

		$url  = \Megaventory\API::get_url_for_call( self::$mv_url_discount_get );
		$data = \Megaventory\API::send_json( $url, $discount_object );

		return $data;
	}

	/**
	 * Save Coupon to Megaventory.
	 *
	 * @return bool
	 */
	public function mv_save() {

		if ( 'percent' === $this->type ) {

			return $this->update_discount_in_megaventory();

		} else {

			return true;
		}
	}

	/**
	 * Create a Discount object to send to Megaventory.
	 *
	 * @return bool
	 */
	public function update_discount_in_megaventory() {

		$megaventory_discount = $this->get_megaventory_discount_by_name_rate();

		$is_update = false;

		if ( $megaventory_discount['mvDiscounts'] ) {

			$this->mv_id = $megaventory_discount['mvDiscounts'][0]['DiscountID'];
			$is_update   = true;
		}

		$discount_object_fields = new \stdClass();
		$discount_object        = new \stdClass();

		if ( $is_update ) {

			$discount_object_fields->discountid = $this->mv_id;

			$discount_object->mvrecordaction = 'Update';

		} else {

			$discount_object_fields->discountid = '';

			$discount_object->mvrecordaction = 'Insert';
		}

		$discount_object_fields->discountname        = $this->name;
		$discount_object_fields->discountdescription = $this->description;
		$discount_object_fields->discountvalue       = (int) $this->rate;

		$discount_object->mvdiscount = $discount_object_fields;

		$discount_object->mvinsertupdatedeletesourceapplication = 'woocommerce';

		$discount_object = \Megaventory\API::wrap_json( $discount_object );

		$json_url = \Megaventory\API::get_url_for_call( self::$mv_url_discount_update );
		$results  = \Megaventory\API::send_json( $json_url, $discount_object );

		if ( '0' === $results['ResponseStatus']['ErrorCode'] ) {

			$this->mv_id = $results['mvDiscount']['DiscountID'];

			if ( $is_update ) {

				$this->log_success( 'updated', 'coupon have been successfully updated in Megaventory', 1 );

				return true;
			}

			$this->log_success( 'created', 'coupon have been successfully created in Megaventory', 1 );

			return true;

		} else { /* if <ErrorCode... was found, then save failed. */

			$internal_error_code = ' [' . $results['InternalErrorCode'] . ']';

			$this->log_error( 'Coupon not saved to Megaventory' . $internal_error_code, $results['ResponseStatus']['Message'], -1, 'error', $results['json_object'] );

			return false;
		}
	}
}
