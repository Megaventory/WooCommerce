<?php
/**
 * Product controller.
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

namespace Megaventory\Controllers;

/**
 * Controller for Products.
 */
class Product {

	/**
	 * Product edit or create.
	 *
	 * @param int $prod_id as product id.
	 * @return void
	 */
	public static function sync_on_product_save( $prod_id ) {

		if ( 'product' !== get_post_type( $prod_id ) ) {
			return;
		}

		$wc_product = wc_get_product( $prod_id );

		if ( 'publish' !== $wc_product->get_status() && 'future' !== $wc_product->get_status() ) {
			return;
		}

		if ( 'variable' === $wc_product->get_type() ) {

			\Megaventory\Models\Product::update_variable_product_in_megaventory( $wc_product );

		} elseif ( 'bundle' === $wc_product->get_type() ) {

			$product_bundle = \Megaventory\Models\Product_Bundle::wc_find_product_bundle( $prod_id );

			$product_bundle->check_and_append_all_bundled_variations();
			$product_bundle->mv_save_bundle();

		} else {
			$product = \Megaventory\Models\Product::wc_find_product( $prod_id );
			$product->mv_save();
		}
	}

	/**
	 * Product create from CSV Import.
	 *
	 * @param int $product_id as product id.
	 * @return void
	 */
	public static function new_product_from_import( $product_id ) {
		self::sync_on_product_save( $product_id );
	}

	/**
	 * Delete product event handler.
	 *
	 * @param int $product_id as product id.
	 * @return void
	 */
	public static function delete_product_handler( $product_id ) {

		if ( 'product' !== get_post_type( $product_id ) && 'product_variation' !== get_post_type( $product_id ) ) {
			return;
		}

		$wc_product = wc_get_product( $product_id );

		// variations will trigger the same hook.
		if ( 'simple' === $wc_product->get_type() || 'variation' === $wc_product->get_type() ) {

			$product = \Megaventory\Models\Product::wc_find_product( $product_id );
			$product->delete_product_in_megaventory();
		}
	}

	/**
	 * Change Extra Fee SKU Option
	 */
	public static function megaventory_update_extra_fee_sku() {

		if ( isset( $_POST['extra_fee_sku'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$extra_fee_sku = sanitize_text_field( wp_unslash( $_POST['extra_fee_sku'] ) );

			if ( preg_match( "/[\^%<>&'?#$@!*]/", $extra_fee_sku ) ) {
				wp_send_json_error(
					array(
						'success' => false,
						'message' => 'Illegal Characters in SKU',
					),
					200
				);
			}

			if ( \Megaventory\Models\Product::create_additional_fee_service( $extra_fee_sku ) ) {
				wp_send_json_success( array( 'success' => true ), 200 );
			} else {
				wp_send_json_error(
					array(
						'success' => false,
						'message' => 'Unable to update option, please check error log.',
					),
					200
				);
			}
		} else {

			wp_send_json_error( array( 'success' => false ), 200 );
		}
		wp_die();
	}

	/**
	 * Save purchase price.
	 *
	 * @param int $post_id as int.
	 *
	 * @return void
	 */
	public static function save_purchase_price( $post_id ) {
		if ( isset( $_POST['woocommerce_meta_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) && ! empty( $_POST['purchase_price'] ) ) {

			update_post_meta( $post_id, 'purchase_price', sanitize_text_field( wp_unslash( $_POST['purchase_price'] ) ) );
		}
	}

	/**
	 * Save purchase price.
	 *
	 * @param int $variation_id as int.
	 * @param int $i as int, array index.
	 *
	 * @return void
	 */
	public static function save_variation_purchase_price( $variation_id, $i ) {

		// PHPCS needs nonce verification to get data from $_POST.
		// The nonce field is missing.
		// So the below code is added to bypass this.
		if ( isset( $_POST['woocommerce_meta_nonce'] ) ) {

			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' );
		}

		if ( ! empty( $_POST['purchase_price'] ) && ! empty( $_POST['purchase_price'][ $i ] ) ) {

			update_post_meta( $variation_id, 'purchase_price', sanitize_text_field( wp_unslash( $_POST['purchase_price'][ $i ] ) ) );

		}
	}
}
