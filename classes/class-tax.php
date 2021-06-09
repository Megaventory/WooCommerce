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

/**
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/api.php';
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
	 * Get Tax Errors.
	 *
	 * @return MVWC_Errors
	 */
	public function errors() {

		return $this->errors;
	}

	/**
	 * Get Tax Successes.
	 *
	 * @return MVWC_Successes
	 */
	public function successes() {

		return $this->successes;
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
	public function log_error( $problem, $full_msg, $code, $type = 'error', $json_object ) {

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
	 * Get all WooCommerce taxes.
	 *
	 * @return Tax[]
	 */
	public static function wc_all() {

		global $wpdb;

		$results = $wpdb->get_results(
			"
				SELECT * 
				FROM {$wpdb->prefix}woocommerce_tax_rates 
			",
			ARRAY_A
		); // db call ok; no-cache ok.

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

		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"
				SELECT *
				FROM {$wpdb->prefix}woocommerce_tax_rates
				WHERE tax_rate_id = %d
			",
				$id
			),
			ARRAY_A
		); // db call ok; no-cache ok.

		return self::wc_convert( $result );
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

		$url     = create_json_url( self::$tax_get_call );
		$jsontax = perform_call_to_megaventory( $url );

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

		$url      = get_url_for_call( self::$tax_get_call );
		$response = send_request_to_megaventory( $url, $data );

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

		$url      = get_url_for_call( self::$tax_get_call );
		$response = send_request_to_megaventory( $url, $data );

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

		$url      = get_url_for_call( self::$tax_get_call );
		$response = send_request_to_megaventory( $url, $data );

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

		$create_new = false;
		if ( empty( $this->mv_id ) ) { // find by name first.

			$tax = self::mv_find_by_name_and_rate( $this->name, $this->rate );

			if ( null !== $tax ) {

				$this->mv_id = $tax->mv_id;

			} else {

				$create_new = true;
			}
		}

		$action     = ( $create_new ? 'Insert' : 'Update' );
		$tax_object = new \stdClass();
		$tax_obj    = new \stdClass();

		$tax_object->taxid          = ! $create_new ? $this->mv_id : '';
		$tax_object->taxname        = $this->name;
		$tax_object->taxdescription = ( $this->description ? $this->description : '' );
		$tax_object->taxvalue       = $this->rate;

		$tax_obj->mvtax                                 = $tax_object;
		$tax_obj->mvrecordaction                        = $action;
		$tax_obj->mvinsertupdatedeletesourceapplication = 'woocommerce';

		$url     = get_url_for_call( self::$tax_update_call );
		$tax_obj = wrap_json( $tax_obj );
		$data    = send_json( $url, $tax_obj );

		if ( count( $data['mvTax'] ) <= 0 ) {

			// log err.
			$this->log_error( 'Tax not saved to Megaventory', $data['ResponseStatus']['Message'], $data['ResponseStatus']['ErrorCode'], 'error', $data['json_object'] );

			return null;

		} else {

			// ensure correct id.
			$new_id = $data['mvTax']['TaxID'];
			if ( $new_id !== $this->mv_id ) {

				$this->mv_id = $new_id;
				$this->wc_save();
			}

			$this->log_success( $action, 'Tax successfully ' . $action . ' in Megaventory', 1 );
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

		$sql_results = $wpdb->query(
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
	 * Deletes a Tax in WooCommerce.
	 *
	 * @return mixed
	 */
	public function wc_delete() {

		global $wpdb;

		$table_name = $wpdb->prefix . self::$table_name;

		$sql_results = $wpdb->delete(
			$table_name,
			array( 'tax_rate_id' => $this->wc_id ),
			array( '%d' )
		); // db call ok; no-cache ok.

		if ( ! $sql_results ) {

			$this->log_error( 'Tax deletion error', $wpdb->last_error, -1, 'error', '' );

			return false;
		}

		return true;
	}

	/**
	 * Compares two Taxes.
	 *
	 * @param Tax $tax as Tax.
	 * @return bool
	 */
	public function equals( $tax ) {

		return $this->name === $tax->name && (float) $this->rate === (float) $tax->rate;
	}

	/**
	 * Get Sales row taxes as array of Taxes.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping $sales_row as order item.
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
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping $sales_row as order item.
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

