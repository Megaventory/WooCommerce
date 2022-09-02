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
 * Order extra fee settings template class
 */
class Extra_Fee_Sku_Settings {

	/**
	 * Generates order extra fee Settings Tab Content.
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
			?>
			<div class="mv-row row-main">
				<div class="wp-cron">
					<div class="actions">
						<div class="MarTop10">
							<table class="form-table">
								<tbody>
									<tr>
										<th scope="row"><label for="mv_extra_fee_sku_control">Extra Fee SKU</label></th>
										<td>
											<input id="mv_extra_fee_sku_control" type="text" name="mv_extra_fee_sku" value="<?php echo esc_attr( get_option( 'megaventory_extra_fee_sku', \Megaventory\Models\MV_Constants::DEFAULT_EXTRA_FEE_SERVICE_SKU ) ); ?>" />
											<div class='description'>Setting this option will allow WooCommerce to synchronize extra fees on an order to Megaventory, this should not include the following characters: <b>#, $, !, @, %, <, >, *, &, ^, '</b></div>
										</td>
									</tr>
								</tbody>
							</table>
							<div id="mv_update_extra_fee_sku_control" class="updateButton CurPointer pushAction" onclick="megaventory_update_extra_fee_sku()" >
								<span class="mv-action" title="Update Extra Fee SKU">Save</span>
							</div>
						</div>
					</div>
				</div>
			</div>	
			<?php
		}
	}
}
