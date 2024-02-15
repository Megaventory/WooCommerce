<?php
/**
 * Tax helper.
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

/**
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'class-api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-error.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-errors.php';

/**
 * Contains basic data to combine mvTax with WooCommerce Tax class.
 */
class Tax {

	/**
	 * Table where taxes are stored.
	 *
	 * @var string
	 */
	public static $table_name = 'woocommerce_tax_rates';

	/**
	 * Megaventory tax id.
	 *
	 * @var int
	 */
	public $mv_id;

	/**
	 * WooCommerce tax id.
	 *
	 * @var int
	 */
	public $wc_id;

	/**
	 * Tax name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Tax description.
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Tax rate.
	 *
	 * @var double
	 */
	public $rate;

	/**
	 * Tax type.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Compound taxes are applied on top of others.
	 *
	 * @var bool
	 */
	public $is_compound;

	/**
	 * Tax Get Megaventory API call.
	 *
	 * @var string
	 */
	private static $tax_get_call = 'TaxGet';

	/**
	 * Tax Update Megaventory API call.
	 *
	 * @var string
	 */
	private static $tax_update_call = 'TaxUpdate';

	/**
	 * Tax errors.
	 *
	 * @var MVWC_Errors
	 */
	public $errors;

	/**
	 * Tax successes.
	 *
	 * @var MVWC_Successes
	 */
	public $successes;

	/**
	 * Tax constructor.
	 */
	public function __construct() {

		$this->errors    = new MVWC_Errors();
		$this->successes = new MVWC_Successes();
	}

	/**
	 * Log Tax errors.
	 *
	 * @param string $problem as tax error problem.
	 * @param string $full_msg as tax error full message.
	 * @param int    $code as tax error code.
	 * @param string $type as tax error type.
	 * @param string $json_object as string.
	 * @return void
	 */
	public function log_error( $problem, $full_msg, $code, $type = 'error', $json_object = '' ) {

		$args = array(
			'entity_id'   => array(
				'wc' => $this->wc_id,
				'mv' => $this->mv_id,
			),
			'entity_name' => $this->name,
			'problem'     => $problem,
			'full_msg'    => $full_msg,
			'error_code'  => $code,
			'json_object' => $json_object,
			'type'        => $type,
		);

		$this->errors->log_error( $args );
	}

	/**
	 * Logs Tax Successes.
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
			'entity_type'        => 'tax',
			'entity_name'        => $this->name,
			'transaction_status' => $transaction_status,
			'full_msg'           => $full_msg,
			'success_code'       => $code,
		);

		$this->successes->log_success( $args );
	}

	/**
	 * Initialization of taxes.
	 *
	 * @return void
	 */
	public static function initialize_taxes() {

		$wc_taxes = self::wc_all();
		$mv_taxes = self::mv_all();

		foreach ( $wc_taxes as $wc_tax ) {

			$mv_tax = null;

			foreach ( $mv_taxes as $tax ) { // Check if exists.

				if ( $wc_tax->name === $tax->name && $wc_tax->rate === $tax->rate ) {

					$mv_tax = $tax;
					break;
				}
			}

			if ( null !== $mv_tax ) { // Tax already exists in Megaventory.

				/* Update in wooCommerce from Megaventory */

				$mv_tax->wc_id = $wc_tax->wc_id;
				$mv_tax->wc_save();

			} else {

				/* Save to Megaventory from WooCommerce */

				$wc_tax->mv_id = null;
				$wc_tax->mv_save();
			}
		}
	}

	/**
	 * Get all WooCommerce taxes.
	 *
	 * @return Tax[]
	 */
	public static function wc_all() {

		global $wpdb;

		$results = $wpdb->get_results( // phpcs:ignore
			"
				SELECT * 
				FROM {$wpdb->prefix}woocommerce_tax_rates 
			",
			ARRAY_A
		);

		$taxes = array();

		foreach ( $results as $result ) {

			array_push( $taxes, self::wc_convert( $result ) );
		}

		return $taxes;
	}

	/**
	 * Get Tax in WooCommerce by id.
	 *
	 * @param int $id as tax id.
	 * @return Tax|bool
	 */
	public static function wc_find_tax( $id ) {

		$tax = \WC_Tax::_get_tax_rate( $id );

		return self::wc_convert( $tax );
	}

	/**
	 * Converts a WooCommerce tax to Tax.
	 *
	 * @param array $wc_tax as array of WooCommerce tax data.
	 * @return Tax
	 */
	public static function wc_convert( $wc_tax ) {

		$tax = new Tax();

		$tax->wc_id = $wc_tax['tax_rate_id'];
		$tax->mv_id = $wc_tax['mv_id'];

		$tax->rate = (float) $wc_tax['tax_rate'];
		$tax->name = $wc_tax['tax_rate_name'];

		$tax->type = $wc_tax['tax_rate_class'];

		$tax->is_compound = (bool) $wc_tax['tax_rate_compound'];

		if ( empty( $tax->type ) ) {

			$tax->type = 'standard-rate';
		}

		return $tax;
	}

	/**
	 * Get all Taxes from Megaventory.
	 *
	 * @return Tax[]
	 */
	public static function mv_all() {

		$url     = \Megaventory\API::get_url_for_call( self::$tax_get_call );
		$jsontax = \Megaventory\API::perform_call_to_megaventory( $url );

		$taxes = array();
		foreach ( $jsontax['mvTaxes'] as $tax ) {

			$tax = self::mv_convert( $tax );
			array_push( $taxes, $tax );
		}

		return $taxes;
	}

	/**
	 * Get from Megaventory a Tax by id.
	 *
	 * @param int $id as tax id.
	 * @return null|Tax
	 */
	public static function mv_find( $id ) {

		$data = array(
			'Filters' => array(
				'FieldName'      => 'TaxID',
				'SearchOperator' => 'Equals',
				'SearchValue'    => $id,
			),
		);

		$url      = \Megaventory\API::get_url_for_call( self::$tax_get_call );
		$response = \Megaventory\API::send_request_to_megaventory( $url, $data );

		if ( count( $response['mvTaxes'] ) <= 0 ) {

			return null;
		}

		return self::mv_convert( $response['mvTaxes'][0] );
	}

	/**
	 * Get a Tax from Megaventory by name.
	 *
	 * @param string $name as tax name.
	 * @return null|Tax
	 */
	public static function mv_find_by_name( $name ) {

		$data = array(
			'Filters' => array(
				'FieldName'      => 'TaxName',
				'SearchOperator' => 'Equals',
				'SearchValue'    => $name,
			),
		);

		$url      = \Megaventory\API::get_url_for_call( self::$tax_get_call );
		$response = \Megaventory\API::send_request_to_megaventory( $url, $data );

		if ( count( $response['mvTaxes'] ) <= 0 ) {

			return null;
		}

		return self::mv_convert( $response['mvTaxes'][0] );
	}

	/**
	 * Get a Tax from Megaventory by name and rate.
	 *
	 * @param string $name as tax name.
	 * @param double $rate as tax rate.
	 * @return Tax|null
	 */
	public static function mv_find_by_name_and_rate( $name, $rate ) {

		$data = array(
			'Filters' => array(
				array(
					'FieldName'      => 'TaxName',
					'SearchOperator' => 'Equals',
					'SearchValue'    => $name,
				),
				array(
					'AndOr'          => 'And',
					'FieldName'      => 'TaxValue',
					'SearchOperator' => 'Equals',
					'SearchValue'    => $rate,
				),
			),
		);

		$url      = \Megaventory\API::get_url_for_call( self::$tax_get_call );
		$response = \Megaventory\API::send_request_to_megaventory( $url, $data );

		if ( count( $response['mvTaxes'] ) <= 0 ) {

			return null;
		}

		return self::mv_convert( $response['mvTaxes'][0] );
	}

	/**
	 * Converts a mvTax to Tax.
	 *
	 * @param array $mv_tax as array of mvTax data.
	 * @return Tax
	 */
	public static function mv_convert( $mv_tax ) {

		$tax = new Tax();

		$tax->mv_id       = $mv_tax['TaxID'];
		$tax->name        = $mv_tax['TaxName'];
		$tax->description = $mv_tax['TaxDescription'];
		$tax->rate        = $mv_tax['TaxValue'];

		return $tax;
	}

	/**
	 * Save tax in Megaventory.
	 *
	 * @return null|mixed
	 */
	public function mv_save() {

		$tax_object = new \stdClass();
		$tax_obj    = new \stdClass();

		$tax_object->taxid          = '';
		$tax_object->taxname        = $this->name;
		$tax_object->taxdescription = ( $this->description ? $this->description : '' );
		$tax_object->taxvalue       = $this->rate;

		$tax_obj->mvtax                                 = $tax_object;
		$tax_obj->mvrecordaction                        = MV_Constants::MV_RECORD_ACTION['InsertOrUpdate'];
		$tax_obj->mvinsertupdatedeletesourceapplication = 'woocommerce';

		$url     = \Megaventory\API::get_url_for_call( self::$tax_update_call );
		$tax_obj = \Megaventory\API::wrap_json( $tax_obj );
		$data    = \Megaventory\API::send_json( $url, $tax_obj );

		if ( count( $data['mvTax'] ) <= 0 ) {

			// log err.
			$this->log_error( 'Tax not saved to Megaventory', $data['ResponseStatus']['Message'], $data['ResponseStatus']['ErrorCode'], 'error', $data['json_object'] );

			return null;

		} else {

			// ensure correct id.
			$new_id = $data['mvTax']['TaxID'];

			$create_new = false;
			if ( $new_id !== $this->mv_id ) {

				$create_new  = true;
				$this->mv_id = $new_id;
				$this->wc_save();
			}

			$action = ( $create_new ? 'Insert' : 'Update' );

			$this->log_success( $action, 'Tax successfully updated in Megaventory', 1 );
		}

		return $data;
	}

	/**
	 * Save Megaventory Tax Id in WooCommerce
	 *
	 * @return bool
	 */
	public function wc_save() {

		global $wpdb;

		$sql_results = $wpdb->query( // phpcs:ignore
			$wpdb->prepare(
				"
				UPDATE {$wpdb->prefix}woocommerce_tax_rates 
				SET mv_id= %d 
				WHERE tax_rate_id= %d 
				",
				array( $this->mv_id, $this->wc_id )
			)
		); // db call ok; no-cache ok.

		return true;
	}

	/**
	 * Get Sales row taxes as array of Taxes.
	 *
	 * @param \WC_Order_Item_Product|\WC_Order_Item_Shipping $sales_row as order item.
	 * @return array[Tax]|array
	 */
	private static function get_all_sales_row_taxes( $sales_row ) {

		$taxes = array();
		foreach ( $sales_row->get_data()['taxes']['total'] as $id => $rate ) {

			array_push( $taxes, self::wc_find_tax( $id ) );
		}

		return $taxes;
	}

	/**
	 * Get Megaventory tax based on sales row.
	 *
	 * @param \WC_Order_Item_Product|\WC_Order_Item_Shipping|\WC_Order_item_Fee $sales_row as order item.
	 * @return Tax
	 */
	public static function get_sales_row_tax( $sales_row ) {

		$taxes = self::get_all_sales_row_taxes( $sales_row );

		$tax = null;

		if ( count( $taxes ) === 1 ) {
			$tax = $taxes[0];
		} elseif ( count( $taxes ) > 1 ) {
			/*
				Calculate total tax rate
				$total_no_tax = $sales_row->get_data()['total'] - $sales_row->get_data()['total_tax'];
				returns string of foreign keys separated by comma
			*/

			$names = array();

			$rate           = 0;
			$count_of_taxes = count( $taxes );

			for ( $i = 0; $i < $count_of_taxes; $i++ ) {

				array_push( $names, $taxes[ $i ]->name );

				$rate += $taxes[ $i ]->rate;
			}

			$rate = ( $sales_row->get_data()['total_tax'] / $sales_row->get_quantity() ) / ( $sales_row->get_data()['total'] / $sales_row->get_quantity() ) * 100;
			$rate = round( $rate, 2 );

			sort( $names );
			$name = implode( '-', $names );
			$name = substr( $name, 0, 99 ); // Tax name in Megaventory is less than 100 characters.

			$tax = self::mv_find_by_name_and_rate( $name, $rate );

			if ( null === $tax ) {
				$tax              = new Tax();
				$tax->name        = $name;
				$tax->description = $name;
				$tax->rate        = $rate;
				$tax->mv_save();
			}
		}
		return $tax;
	}
}
