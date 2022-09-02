<?php
/**
 * Megaventory Order Settings Tab Content.
 *
 * @package megaventory
 * @since 1.3.1
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Megaventory\Admin\Template_Partials\Order_Sections;

/**
 * Order payment method mapping settings template class
 */
class Payment_Method_Mapping_Settings {

	/**
	 * Generates Order payment method mapping Settings Tab Content.
	 *
	 * @return void
	 */
	public static function generate_page() {
		$are_megaventory_products_synchronized = (bool) get_option( 'are_megaventory_products_synchronized', false );
		$are_megaventory_clients_synchronized  = (bool) get_option( 'are_megaventory_clients_synchronized', false );
		$are_megaventory_coupons_synchronized  = (bool) get_option( 'are_megaventory_coupons_synchronized', false );
		$is_megaventory_stock_adjusted         = (bool) get_option( 'is_megaventory_stock_adjusted', false );

		$is_megaventory_synchronized = false;

		if ( $are_megaventory_products_synchronized && $are_megaventory_clients_synchronized && $are_megaventory_coupons_synchronized && $is_megaventory_stock_adjusted ) {
			$is_megaventory_synchronized = true;
		}

		if ( ! $is_megaventory_synchronized ) {
			?>
			<div class="mv-row row-main">
				<div class="mv-notice-wrap">
					<div class="mv-notice notice-warning"><p><strong>Megaventory is not synchronized</strong> please go <a href="?page=megaventory-plugin">here</a> to finish the initial synchronization.</p></div>
				</div>
			</div>
			<?php
		} else {
			$wc_payment_methods = \Megaventory\Models\Order::get_wc_payment_methods();
			$mv_payment_methods = \Megaventory\Models\Order::get_mv_payment_methods();
			?>
			<div class="mv-row row-main">
				<div class="mv-payment-method-mapping">
					<table class="wp-list-table widefat fixed striped posts" id="megaventoryPaymentMethodsMapping">
						<thead>
							<tr>
								<th>WooCommerce Payment Method</th>
								<th>Megaventory Payment Method</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $wc_payment_methods as $wc_payment_method_code => $wc_payment_method_title ) : ?>
								<?php $currently_assigned_mv_payment_method = \Megaventory\Models\Order::get_mv_payment_method( $wc_payment_method_code ); ?>
								<tr id="mv_wc_payment_<?php echo esc_attr( $wc_payment_method_code ); ?>">
									<td><?php echo esc_attr( $wc_payment_method_title ); ?></td>
									<td>
										<select id="mv_wc_payment_mapping_input_<?php echo esc_attr( $wc_payment_method_code ); ?>" data-wc-payment-method-id="<?php echo esc_attr( $wc_payment_method_code ); ?>" onchange='megaventory_payment_method_mapping_change( "<?php echo esc_attr( $wc_payment_method_code ); ?>" )'>
											<?php foreach ( $mv_payment_methods as $mv_payment_method_code => $mv_payment_method_title ) : ?>
												<option value="<?php echo esc_attr( $mv_payment_method_code ); ?>" <?php echo ( $currently_assigned_mv_payment_method === $mv_payment_method_code ) ? 'selected' : ''; ?>><?php echo esc_attr( $mv_payment_method_title ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
							<?php endforeach; ?>	
						</tbody>
					</table>
					<div id="mv_update_payment_mappings" class="updateButton CurPointer pushAction" onclick="megaventory_save_payment_method_mappings()" >
						<span class="mv-action" title="Save mappings">Save mappings</span>
					</div>
				</div>
			</div>	
			<?php
		}
	}
}
