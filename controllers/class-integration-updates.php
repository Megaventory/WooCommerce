<?php
/**
 * Integration Updates controller.
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
 * Controller for Clients.
 */
class Integration_Updates {

	/**
	 * Pull integration updates manually.
	 */
	public static function megaventory_pull_integration_updates() {

		if ( isset( $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			self::pull_integration_updates_from_megaventory();
		}

		wp_send_json_success( true );
		wp_die();
	}

	/**
	 * The WordPress Cron event callback function.
	 *
	 * @return void
	 */
	public static function pull_integration_updates_from_megaventory() {

		if ( ! ( get_option( 'is_megaventory_initialized' ) &&
				get_option( 'correct_currency' ) &&
				get_option( 'correct_connection' ) &&
				get_option( 'correct_megaventory_apikey' ) ) ) {

			return;
		}

		$changes = \Megaventory\Models\Integration_Updates::get_integration_updates();

		if ( count( $changes['mvIntegrationUpdates'] ) === 0 ) { // No need to do anything if there are no changes.

			return;
		}

		foreach ( $changes['mvIntegrationUpdates'] as $change ) {

			if ( 'product' === $change['Entity'] ) {

				if ( 'update' !== $change['Action'] ) {

					\Megaventory\Models\Integration_Updates::remove_integration_update( $change['IntegrationUpdateID'] );

					continue;
				}

				$mv_product_id  = $change['EntityIDs'];
				$wc_product_ids = self::get_post_meta_by_key_value( 'mv_id', $mv_product_id );

				if ( empty( $wc_product_ids ) ) {

					\Megaventory\Models\Integration_Updates::remove_integration_update( $change['IntegrationUpdateID'] );
					continue;
				}

				$mv_product = json_decode( $change['JsonData'], true );

				if ( ! is_array( $mv_product ) || ! isset( $mv_product['ProductPurchasePrice'] ) ) {

					\Megaventory\Models\Integration_Updates::remove_integration_update( $change['IntegrationUpdateID'] );
					continue;
				}

				foreach ( $wc_product_ids as $wc_product_id ) {

					$product = \Megaventory\Models\Product::wc_find_product( $wc_product_id );

					if ( null === $product ) {

						continue;
					}

					$purchase_price = str_replace( '.', wc_get_price_decimal_separator(), (string) $mv_product['ProductPurchasePrice'] );

					update_post_meta( $wc_product_id, 'purchase_price', $purchase_price );
				}

				\Megaventory\Models\Integration_Updates::remove_integration_update( $change['IntegrationUpdateID'] );

			} elseif ( 'stock' === $change['Entity'] ) { // stock changed.

				$prods = json_decode( $change['JsonData'], true );

				$mv_location_id_to_abbr = \Megaventory\Models\Location::get_location_id_to_abbreviation_dict();

				foreach ( $prods as $prod ) {

					if ( ! array_key_exists( $prod['inventory_id'], $mv_location_id_to_abbr ) ) {
						continue;
					}

					if ( \Megaventory\Models\Location::is_location_excluded( (int) $prod['inventory_id'] ) ) {
						continue;
					}

					$id = $prod['productID'];

					// An array of product Ids should be expected here to cover also the
					// extreme case of different woocommerce products having the same product SKU.
					// By doing this, whenever there is a stock update from Megaventory,
					// all the products sharing the same SKU will update their woocommerce stock.
					$post_meta_ids = self::get_post_meta_by_key_value( 'mv_id', $id );

					if ( empty( $post_meta_ids ) ) {
						continue;
					}

					foreach ( $post_meta_ids as $post_meta_id ) {

						$wc_product = wc_get_product( $post_meta_id );

						if ( false === $wc_product || null === $wc_product ) {
							continue;
						}

						\Megaventory\Models\Product::sync_stock_update( $wc_product, $prod, $change['IntegrationUpdateID'] );
					}
				}

				\Megaventory\Models\Integration_Updates::remove_integration_update( $change['IntegrationUpdateID'] );

			} elseif ( 'document' === $change['Entity'] ) { // Order changed.

				$mv_document_status_mappings = \Megaventory\Models\MV_Constants::MV_DOCUMENT_STATUS_MAPPINGS;

				$wc_order_status_mappings = \Megaventory\Models\MV_Constants::MV_DOCUMENT_STATUS_TO_WC_ORDER_STATUS_MAPPINGS;

				$json_data = json_decode( $change['JsonData'], true );

				$order_template_id = \Megaventory\Models\MV_Constants::MV_DEFAULT_SALES_ORDER_TEMPLATE;

				if ( $order_template_id !== $json_data['DocumentTypeId'] ) {

					\Megaventory\Models\Integration_Updates::remove_integration_update( $change['IntegrationUpdateID'] );

					continue; // only sales order.
				}

				if ( ! array_key_exists( (int) $json_data['DocumentStatus'], $mv_document_status_mappings ) ||
					! array_key_exists( $mv_document_status_mappings[ (int) $json_data['DocumentStatus'] ], $wc_order_status_mappings ) ) {

					\Megaventory\Models\Integration_Updates::remove_integration_update( $change['IntegrationUpdateID'] );

					continue; // If a mapping does not exist, order status should not be modified.
				}

				$status = $mv_document_status_mappings[ (int) $json_data['DocumentStatus'] ];
				$order  = wc_get_order( $json_data['DocumentReferenceNo'] );

				if ( false !== $order ) {

					$related_mv_orders = get_post_meta( $order->get_id(), \Megaventory\Models\MV_Constants::MV_RELATED_ORDER_ID_META, true );

					// After shipping zones integration, related orders are saved as an array,
					// so we need to check both for array types and older values and handle them accordingly.
					if ( ! empty( $related_mv_orders ) && is_array( $related_mv_orders ) ) {

						\Megaventory\Models\Order::handle_wc_order_status_update_for_multiple_orders( $order, $json_data['DocumentId'], $status, $related_mv_orders );

					} else {

						$order->set_status( $wc_order_status_mappings[ $status ] );

						$order->save();

					}
				}

				\Megaventory\Models\Integration_Updates::remove_integration_update( $change['IntegrationUpdateID'] );
			}

			// delete unhandled integration updates.
			\Megaventory\Models\Integration_Updates::remove_integration_update( $change['IntegrationUpdateID'] );
		}
	}

	/**
	 * Change alternate cron status.
	 */
	public static function megaventory_change_alternate_cron_status() {

		if ( isset( $_POST['newStatus'], $_POST['async-nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['async-nonce'] ), 'async-nonce' ) ) {

			$status = (bool) ( sanitize_text_field( wp_unslash( $_POST['newStatus'] ) ) === 'true' );

			update_option( 'megaventory_alternate_wp_cron', $status );

			wp_send_json_success( array( 'success' => true ), 200 );
		} else {

			wp_send_json_error( array( 'success' => false ), 200 );
		}
		wp_die();
	}

	/**
	 * Get post ids by value and key.
	 *
	 * @param string $key as post key.
	 * @param int    $value as post value.
	 * @return array
	 */
	private static function get_post_meta_by_key_value( $key, $value ) {

		global $wpdb;
		$meta = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->postmeta . ' WHERE meta_key=%s AND meta_value=%d', array( $key, $value ) ), ARRAY_A ); // phpcs:ignore

		if ( is_array( $meta ) && ! empty( $meta ) && isset( $meta[0] ) ) {

			$wc_product_ids = array_map(
				function ( $meta_data ) {
					return (int) $meta_data['post_id'];
				},
				$meta
			);

			return $wc_product_ids;
		}
		return array();
	}
}
