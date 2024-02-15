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

use Megaventory\Models\MV_Constants;

/**
 * Order extra fee settings template class
 */
class Extra_Settings {

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
		$auto_assign_batch_numbers             = (string) get_option( MV_Constants::MV_AUTO_ASSIGN_BATCH_NUMBERS_OPT, MV_Constants::AUTO_INSERT_BATCH_NUMBERS_TO_PRODUCT_ROWS['Undefined'] );

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
											<input id="mv_extra_fee_sku_control" type="text" name="mv_extra_fee_sku" value="<?php echo esc_attr( get_option( 'megaventory_extra_fee_sku', MV_Constants::DEFAULT_EXTRA_FEE_SERVICE_SKU ) ); ?>" />
											<div class='description'>Setting this option will allow WooCommerce to synchronize extra fees on an order to Megaventory, this should not include the following characters: <b>#, $, !, @, %, <, >, *, &, ^, '</b></div>
										</td>
									</tr>
								</tbody>
							</table>
							<div id="mv_update_extra_fee_sku_control" class="updateButton CurPointer pushAction" onclick="megaventory_update_extra_fee_sku()" >
								<span class="mv-action" title="Update Extra Fee SKU">Save</span>
							</div>
						</div>
						<div class="MarTop10">
							<table class="form-table">
								<tbody>
									<tr>
										<th scope="row"><label for="mv_drp_auto_assign_batch_numbers">Auto Assign Batch Numbers</label></th>
										<td>
											<select id="mv_drp_auto_assign_batch_numbers" name="mv_drp_auto_assign_batch_numbers">
												<option value="Undefined" <?php echo ( MV_Constants::AUTO_INSERT_BATCH_NUMBERS_TO_PRODUCT_ROWS['Undefined'] === $auto_assign_batch_numbers ) ? 'selected' : ''; ?>>Not set</option>
												<option value="ByExpiryDate" <?php echo ( MV_Constants::AUTO_INSERT_BATCH_NUMBERS_TO_PRODUCT_ROWS['ByExpiryDate'] === $auto_assign_batch_numbers ) ? 'selected' : ''; ?>>By Expiry Date</option>
												<option value="ByCreationDate" <?php echo ( MV_Constants::AUTO_INSERT_BATCH_NUMBERS_TO_PRODUCT_ROWS['ByCreationDate'] === $auto_assign_batch_numbers ) ? 'selected' : ''; ?>>By Creation Date</option>
												<option value="ByName" <?php echo ( MV_Constants::AUTO_INSERT_BATCH_NUMBERS_TO_PRODUCT_ROWS['ByName'] === $auto_assign_batch_numbers ) ? 'selected' : ''; ?>>By Name</option>
											</select>
											<div class='description'>Setting this option will allow Megaventory to automatically assign batch numbers to products when synchronizing orders from WooCommerce to Megaventory.</div>
										</td>
									</tr>
								</tbody>
							</table>
							<div class="updateButton CurPointer pushAction" onclick="megaventory_update_auto_assign_batch_numbers_option()" >
								<span class="mv-action" title="Update Assign Batch Numbers Option">Save</span>
							</div>
						</div>
					</div>
				</div>
			</div>	
			<?php
		}
	}
}
