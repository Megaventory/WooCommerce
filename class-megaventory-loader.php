<?php
/**
 * Actions class.
 *
 * @package megaventory
 * @since 2.3.1
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Megaventory;

/**
 * Load entities in the admin dashboard.
 */
class Megaventory_Loader {

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Add purchase price column in product table.
	 *
	 * @param array $columns as table columns.
	 * @return array
	 */
	public static function add_purchase_price_column_to_product_table( $columns ) {

		/* Megaventory purchase price column must be after price column */
		$temp = array();

		foreach ( $columns as $key => $value ) {

			$temp[ $key ] = $value;

			if ( 'price' === $key ) {
				$temp['purchase_price'] = __( 'Purchase Price' );
			}
		}
		$columns = $temp;

		return $columns;
	}

	/**
	 * Purchase Price column in product's table.
	 *
	 * @param array $column as column in product table.
	 * @param int   $prod_id as product id.
	 * @return void
	 */
	public static function show_purchase_price_value_in_column( $column, $prod_id ) {

		if ( 'purchase_price' === $column ) {
			$purchase_price = get_post_meta( $prod_id, 'purchase_price', true );

			if ( empty( $purchase_price ) ) {
				echo wp_kses( '–', array() );
			} else {
				echo wp_kses( get_woocommerce_currency_symbol( get_woocommerce_currency() ) . $purchase_price, array() );
			}
		}
	}

	/**
	 * Add purchase price product option.
	 *
	 * @return void
	 */
	public static function add_purchase_price_for_simple_product() {

		$product_id = get_the_ID();

		$product = Models\Product::wc_find_product( $product_id );

		$options = array(
			'id'          => 'purchase_price',
			'value'       => get_post_meta( $product_id, 'purchase_price', true ),
			'label'       => __( 'Purchase price', 'textdomain' ) . ' (' . get_woocommerce_currency_symbol( get_woocommerce_currency() ) . ')',
			'data_type'   => 'price',
			'desc_tip'    => true,
			'description' => 'This is the purchase price of the product(the price that the supplier is charging you to supply you with this product excluding taxes).',
		);

		if ( ! empty( $product->mv_id ) ) {
			$options['custom_attributes'] = array( 'readonly' => 'readonly' ); // Enabling read only.
			$options['description']       = 'The value cannot change, since the product has been synchronized to Megaventory. You can change the purchase price on your Megaventory account.';
		}
		echo wp_kses(
			woocommerce_wp_text_input(
				$options
			),
			array(
				'input'    => array(),
				'textarea' => array(),
			)
		);
	}

	/**
	 * Add purchase price product option.
	 *
	 * @param int     $loop as int.
	 * @param array   $variation_data as array.
	 * @param WP_Post $variation as WP_Post.
	 *
	 * @return void
	 */
	public static function add_purchase_price_for_variation_product( $loop, $variation_data, $variation ) {

		$product_id = $variation->ID;

		$product = Models\Product::wc_find_product( $product_id );

		$options = array(
			'id'          => 'purchase_price[' . $loop . ']',
			'value'       => get_post_meta( $product_id, 'purchase_price', true ),
			'label'       => __( 'Purchase price', 'textdomain' ) . ' (' . get_woocommerce_currency_symbol( get_woocommerce_currency() ) . ')',
			'data_type'   => 'price',
			'desc_tip'    => true,
			'description' => 'This is the purchase price of the product(the price that the supplier is charging you to supply you with this product excluding taxes).',
		);

		if ( ! empty( $product->mv_id ) ) {
			$options['custom_attributes'] = array( 'readonly' => 'readonly' ); // Enabling read only.
			$options['description']       = 'The value cannot change, since the product has been synchronized to Megaventory. You can change the purchase price on your Megaventory account.';
		}

		echo wp_kses(
			woocommerce_wp_text_input(
				$options
			),
			array(
				'input'    => array(),
				'textarea' => array(),
			)
		);
	}

	/**
	 * Add Megaventory stock column in product table.
	 *
	 * @param array $columns as table columns.
	 * @return array
	 */
	public static function add_quantity_column_to_product_table( $columns ) {

		/* Megaventory stock column must be after normal stock column */
		$temp = array();

		foreach ( $columns as $key => $value ) {

			$temp[ $key ] = $value;

			if ( 'is_in_stock' === $key ) {
				$temp['megaventory_stock'] = __( 'Megaventory Quantity' );
			}
		}
		$columns = $temp;

		return $columns;
	}

	/**
	 * Megaventory stock column in product's table.
	 *
	 * @param array $column as column in product table.
	 * @param int   $prod_id as product id.
	 * @return void
	 */
	public static function show_quantity_value_in_column( $column, $prod_id ) {

		if ( 'megaventory_stock' === $column ) {

			$wc_product = wc_get_product( $prod_id );

			$mv_qty = array();

			if ( 'variable' === $wc_product->get_type() ) {

				$variants_ids = $wc_product->get_children();

				$variant_skus = array();

				foreach ( $variants_ids as $variant_id ) {

					$wc_variation_product = new \WC_Product_Variation( $variant_id );

					$product = Models\Product::wc_variation_convert( $wc_variation_product, $wc_product );

					if ( ! is_array( $product->mv_qty ) || 0 === count( $product->mv_qty ) ) {

						continue;
					}

					if ( in_array( $product->sku, $variant_skus, true ) ) {
						continue;
					}

					$variant_skus[] = $product->sku;

					foreach ( $product->mv_qty as $key => $value ) {

						if ( array_key_exists( $key, $mv_qty ) ) {
							$variable_values    = explode( ';', $mv_qty[ $key ] );
							$simple_prod_values = explode( ';', $value );

							$variable_values[1] += $simple_prod_values[1];
							$variable_values[2] += $simple_prod_values[2];
							$variable_values[3] += $simple_prod_values[3];
							$variable_values[4] += $simple_prod_values[4];
							$variable_values[5] += $simple_prod_values[5];
							$variable_values[6] += $simple_prod_values[6];

							$mv_qty[ $key ] = $variable_values[0] . ';' . $variable_values[1] . ';' . $variable_values[2] . ';' . $variable_values[3] . ';' . $variable_values[4] . ';' . $variable_values[5] . ';' . $variable_values[6];
						} else {
							$mv_qty[ $key ] = $value;
						}
					}
				}
			} elseif ( 'simple' === $wc_product->get_type() ) {

				/* get product by id */
				$prod   = Models\Product::wc_find_product( $prod_id );
				$mv_qty = $prod->mv_qty;

			} else {
				// Empty megaventory_stock column for anything else.
				return;
			}

			/* no stock */
			if ( ! is_array( $mv_qty ) || 0 === count( $mv_qty ) ) {

				echo 'No stock';

				return;
			}
			/* build stock table */
			?>
			<table class="qty-row">
			<?php foreach ( $mv_qty as $key => $qty ) : ?>
				<tr>
				<?php
				$mv_location_id_to_abbr = get_option( Models\MV_Constants::MV_LOCATION_ID_TO_ABBREVIATION );

				if ( empty( $mv_location_id_to_abbr[ $key ] ) ) {
					continue;
				}

				$inventory_name = $mv_location_id_to_abbr[ $key ];
				?>
				<?php $qty = explode( ';', $qty ); ?>
					<td colspan="2"><span><?php echo esc_attr( $inventory_name ); ?></span></td>
					<td class="mv-tooltip" title="Total"><span><?php echo esc_attr( $qty[1] ); ?></span></td>
					<td class="mv-tooltip" title="On Hand"><span class="qty-on-hand">(<?php echo esc_attr( $qty[2] ); ?>)</span></td>
					<td class="mv-tooltip" title="Non-shipped Quantity in Sales Orders"><span class="qty-non-shipped"><?php echo esc_attr( $qty[3] ); ?></span></td>
					<td class="mv-tooltip" title="Non-Allocated Quantity in Production Orders"><span class="qty-non-allocated"><?php echo esc_attr( $qty[4] ); ?></span></td>
					<td class="mv-tooltip" title="Non-Received Quantity in Purchase Orders"><span class="qty-non-received"><?php echo esc_attr( $qty[5] ); ?></span></td>
					<td class="mv-tooltip" title="Non-Received Quantity in Production Orders"><span class="qty-non-received"><?php echo esc_attr( $qty[6] ); ?></span></td>
				</tr>
			<?php endforeach; ?>
			</table>
			<?php
		}
	}

	/**
	 * Add Megaventory column in orders list.
	 *
	 * @param array $columns order list columns.
	 * @return array
	 */
	public static function add_megaventory_column_in_orders_list( $columns ) {
		$columns['megaventory_order_column'] = __( 'Megaventory Order' );
		return $columns;
	}

	/**
	 * Display Megaventory sales order id, or button to synchronize manually the order.
	 *
	 * @param array $column   as column in order grid.
	 * @param int   $order_id as product id.
	 * @return void
	 */
	public static function show_megaventory_order_info_in_column( $column, $order_id ) {

		if ( 'megaventory_order_column' !== $column ) {
			return;
		}

		$megaventory_order_id   = get_post_meta( $order_id, Models\MV_Constants::MV_RELATED_ORDER_ID_META, true );
		$megaventory_order_name = get_post_meta( $order_id, Models\MV_Constants::MV_RELATED_ORDER_NAMES_META, true );

		if ( empty( $megaventory_order_id ) ) {

			if ( get_option( 'correct_megaventory_apikey' ) && get_option( 'is_megaventory_initialized' ) ) {
				?>

					<span id ='orderToSync_<?php echo esc_attr( $order_id ); ?>' class='Padd10' onclick='synchronize_order_to_megaventory_manually(<?php echo esc_attr( $order_id ); ?>)'><a href="#">Synchronize</a></span>

				<?php
			}

			return;
		}

		if ( is_array( $megaventory_order_id ) ) {

			$location_abbr_dict = Models\Location::get_location_id_to_abbreviation_dict();

			foreach ( $megaventory_order_id as $mv_order_id ) {
				if ( ! empty( $megaventory_order_name ) && is_array( $megaventory_order_name ) ) {
					$order_name = self::get_mv_order_name_to_show( $megaventory_order_name[ $mv_order_id ], $location_abbr_dict );
					echo 'Megaventory order: ' . esc_attr( $order_name );
				} else {
					echo 'Megaventory order id: ' . esc_attr( $mv_order_id );
				}
				?>
					<br>
				<?php
			}

			return;
		}

		if ( 0 < (int) $megaventory_order_id ) {

			if ( ! empty( $megaventory_order_name ) ) {

				echo 'Megaventory order: ' . esc_attr( $megaventory_order_name );
			} else {

				echo 'Megaventory order id: ' . esc_attr( $megaventory_order_id );
			}
		}

	}

	/**
	 * Get megaventory order name to show in column.
	 *
	 * @param array $megaventory_order_post_meta_name Megaventory Order Name array for the specific MV order.
	 * @param array $location_abbr_dict               Dictionary of id location to abbreviation.
	 * @return string
	 */
	private static function get_mv_order_name_to_show( $megaventory_order_post_meta_name, $location_abbr_dict ) {

		$so_type_abbr = empty( $megaventory_order_post_meta_name['SalesOrderTypeAbbreviation'] ) ? 'SO' : $megaventory_order_post_meta_name['SalesOrderTypeAbbreviation'];
		$so_number    = empty( $megaventory_order_post_meta_name['SalesOrderNo'] ) ? '' : $megaventory_order_post_meta_name['SalesOrderNo'];
		$so_location  = empty( $megaventory_order_post_meta_name['SalesOrderInventoryLocationID'] ) ? '' : $megaventory_order_post_meta_name['SalesOrderInventoryLocationID'];
		$loc_abbr     = '';

		if ( ! empty( $so_location ) && array_key_exists( $so_location, $location_abbr_dict ) ) {
			$loc_abbr = $location_abbr_dict[ $so_location ];
			$loc_abbr = '( ' . $loc_abbr . ' )';
		}

		return $so_type_abbr . ' ' . $so_number . ' ' . $loc_abbr;

	}

}
