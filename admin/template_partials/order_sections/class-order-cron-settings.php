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
 * Order cron template class
 */
class Order_Cron_Settings {

	/**
	 * Generates order cron Settings Tab Content.
	 *
	 * @return void
	 */
	public static function generate_page() {
		$is_megaventory_initialized = (bool) get_option( 'is_megaventory_initialized', false );

		if ( ! $is_megaventory_initialized ) {
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
				<?php
					$mv_delay_orders  = (bool) get_option( \Megaventory\Models\MV_Constants::MV_ORDER_DELAY_CHOICE_OPT, false );
					$mv_delay_seconds = (int) get_option( \Megaventory\Models\MV_Constants::MV_ORDER_DELAY_SECONDS_CHOICE_OPT, 7200 );
				?>
				<div class="actions">
					<div class="MarTop10">
						<table class="form-table">
							<tbody>
								<tr>
									<td>
										<input id="disable_mv_order_sync_delay" type="radio" name="mv_order_sync" onchange="megaventory_show_hide_sync_delay_input()" value="false" <?php echo $mv_delay_orders ? '' : 'checked'; ?>/>
										<label for="disable_mv_order_sync_delay">Instant Synchronization</label>
										<br>
										<input id="enable_mv_order_sync_delay" type="radio" name="mv_order_sync" onchange="megaventory_show_hide_sync_delay_input()" value="true" <?php echo $mv_delay_orders ? 'checked' : ''; ?>/>
										<label for="enable_mv_order_sync_delay">Synchronization after set delay</label>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					<div id="mv_order_delay_controls" class="actions">
						<table>
							<tr>
								<td>
									<label class="width25per" for="mv_order_delay_input">Seconds to wait before synchronization:</label>
								</td>
								<td>
									<input id="mv_order_delay_input" name="mv_order_delay" value=<?php echo esc_attr( $mv_delay_seconds ); ?> class="flLeft width80per MarLe30" type="number">
								</td>
							</tr>
						</table>
					</div>
				</div>
			</div>
			<div class="MarTop10">
				<span id="mv_order_delay_save" class="updateButton CurPointer pushAction" onclick="megaventory_toggle_order_delay()">Save</span>
			</div>
			<script>
				jQuery(document).ready(()=>{megaventory_show_hide_sync_delay_input();});
			</script>	
			<?php
		}
	}
}
