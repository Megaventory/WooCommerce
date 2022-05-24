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

namespace Megaventory\Admin\Template_Partials;

/**
 * Order settings template class
 */
class Order_Settings {

	/**
	 * Generates Order Settings Tab Content.
	 *
	 * @return void
	 */
	public static function generate_page() {
		$correct_connection                    = (bool) get_option( 'correct_connection', true );
		$correct_currency                      = (bool) get_option( 'correct_currency', false );
		$correct_key                           = (bool) get_option( 'correct_megaventory_apikey', false );
		$is_megaventory_initialized            = (bool) get_option( 'is_megaventory_initialized', false );
		$are_megaventory_products_synchronized = (bool) get_option( 'are_megaventory_products_synchronized', false );
		$are_megaventory_clients_synchronized  = (bool) get_option( 'are_megaventory_clients_synchronized', false );
		$are_megaventory_coupons_synchronized  = (bool) get_option( 'are_megaventory_coupons_synchronized', false );
		$is_megaventory_stock_adjusted         = (bool) get_option( 'is_megaventory_stock_adjusted', false );

		$is_megaventory_synchronized = false;

		if ( $are_megaventory_products_synchronized && $are_megaventory_clients_synchronized && $are_megaventory_coupons_synchronized ) {

			$is_megaventory_synchronized = true;
		}
		if ( $correct_connection && $correct_currency && $correct_key ) : ?>

			<?php if ( $is_megaventory_synchronized && $is_megaventory_stock_adjusted ) : ?>
				<div class="mv-row row-main">
					<div class="wp-cron">
						<div class="actions">
							<h3>Extra Fee Synchronization Options</h3>
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
				<hr />
			<?php endif; ?>
			<?php if ( $is_megaventory_initialized && $is_megaventory_synchronized && $correct_connection && $correct_currency && $correct_key ) : ?>
				<div class="mv-row row-main">
					<?php
						$mv_delay_orders  = (bool) get_option( \Megaventory\Models\MV_Constants::MV_ORDER_DELAY_CHOICE_OPT, false );
						$mv_delay_seconds = (int) get_option( \Megaventory\Models\MV_Constants::MV_ORDER_DELAY_SECONDS_CHOICE_OPT, 7200 );
					?>
					<div class="actions">
						<h3>Synchronize orders to Megaventory:</h3>
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
			<?php endif; ?>		
			<?php
			$inventories = array();

			if ( $correct_connection && $correct_currency && $correct_key ) {

				$inventories = \Megaventory\Models\Location::get_megaventory_locations();
			}

			$default_inventory_id = (int) get_option( 'default-megaventory-inventory-location' );

			$mv_location_id_to_abbr = get_option( \Megaventory\Models\MV_Constants::MV_LOCATION_ID_TO_ABBREVIATION );

			if ( ! isset( $mv_location_id_to_abbr ) ) {
				$mv_location_id_to_abbr = array();
			}

			?>
			<hr/>
			<div class="mv-row row-main">
				<div class='inventories'>
					<h3>Choose the Megaventory Inventory Location where the WooCommerce Sales Orders will be pushed to.</h3>
					<table class="wp-list-table widefat fixed striped posts" id="locations">
						<thead>
							<tr>
								<th></th>
								<th>Abbreviation</th>
								<th>Full Name</th>
								<th>Address</th>
								<th>Exclude</th>
							</tr>
						</thead>
						<tbody>	
						<?php foreach ( $inventories as $inventory ) : ?>
							<?php

							$mv_location_id_to_abbr[ $inventory['InventoryLocationID'] ] = $inventory['InventoryLocationAbbreviation'];

							?>
							<tr>
								<td>
									<input name="mvLocation" id=<?php echo esc_attr( $inventory['InventoryLocationID'] ); ?> type="radio"
									<?php
									// if there is no default inventory location set, set the first one by default, if exist.
									if ( 0 === $default_inventory_id ) {

										update_option( 'default-megaventory-inventory-location', $inventory['InventoryLocationID'] );
										$default_inventory_id = $inventory['InventoryLocationID'];
									}

									if ( $inventory['InventoryLocationID'] === $default_inventory_id ) {

										echo esc_attr( 'checked' );
									}
									?>
									onclick="megaventory_change_default_location(this.id)" >
								</td>
								<td>
									<span><?php echo esc_attr( $inventory['InventoryLocationAbbreviation'] ); ?></span>
								</td>
								<td>
									<span><?php echo esc_attr( $inventory['InventoryLocationName'] ); ?></span>
								</td>
								<td>
									<span><?php echo esc_attr( $inventory['InventoryLocationAddress'] ); ?></span>
								</td>
								<td>

									<?php if ( \Megaventory\Models\Location::is_location_excluded( (int) $inventory['InventoryLocationID'] ) ) : ?>
										<input name="mv_include_location" id=<?php echo esc_attr( $inventory['InventoryLocationID'] ); ?> type="checkbox" onclick="megaventory_include_location(this.id)" checked >
									<?php else : ?>
										<input name="mv_excluded_location" id=<?php echo esc_attr( $inventory['InventoryLocationID'] ); ?> type="checkbox" onclick="megaventory_exclude_location(this.id)" >

									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>

						<?php update_option( \Megaventory\Models\MV_Constants::MV_LOCATION_ID_TO_ABBREVIATION, $mv_location_id_to_abbr ); ?>
						</tbody>
					</table>
					<?php if ( $is_megaventory_initialized && $is_megaventory_synchronized && $correct_connection && $correct_currency && $correct_key ) : ?>
						<h3>Shipping Zones:</h3>
						<?php
							$are_zones_activated = \Megaventory\Models\Shipping_Zones::megaventory_are_shipping_zones_enabled();
							$zone_priority_array = \Megaventory\Models\Shipping_Zones::megaventory_get_shipping_zone_priorities();
						?>
						<div class="MarTop10">
							<table class="form-table">
								<tbody>
									<tr>
										<th><label for="enable_mv_shipping_zones">Enable Megaventory Shipping Zones:</label></th>
										<td><input id="enable_mv_shipping_zones" type="checkbox" name="mv_shipping_zones_chk" onclick="megaventory_change_shipping_zones_option()" <?php echo ( $are_zones_activated ) ? 'checked' : ''; ?>/><span class='description'>Enable this option to fulfill orders in Megaventory based on WC Shipping Zones.</span></td>
									</tr>
								</tbody>
							</table>
							<?php if ( $are_zones_activated ) : ?>
							<div id="shippingZones_controls">
								<span id="toggleEditShippingZones" onclick="megaventory_toggle_shipping_zones_reordering()" class="updateButton CurPointer pushAction">Edit</span>
								<?php $zones = \Megaventory\Models\Shipping_Zones::megaventory_get_all_shipping_zones_plus_default(); ?>
								<table class="wp-list-table widefat fixed striped posts" id="sh_zones_t">
									<thead>
										<tr>
											<th>Shipping Zone</th>
											<th>Location Priority</th>
										</tr>
									</thead>
									<tbody>
									<?php foreach ( $zones as $zone_id => $sh_zone ) : ?>
										<?php $zone_name_trimmed = str_replace( ' ', '', $sh_zone['zone_name'] ); ?>
										<tr class="shipping_zone_row">
											<td>
												<span data-shipping_zone_id=<?php echo esc_attr( $zone_id ) . ' '; ?> > <?php echo esc_attr( $sh_zone['zone_name'] ); ?> </span>
											</td>
											<td>
												<ol class="shipping_sortable" id="">

													<?php if ( $zone_priority_array && array_key_exists( $zone_id, $zone_priority_array ) && is_array( $zone_priority_array[ $zone_id ] ) ) : ?>

														<?php foreach ( $zone_priority_array[ $zone_id ] as $zone_priority_loc_id ) : ?>

															<li id=<?php echo esc_attr( 'SHZ_' . $zone_name_trimmed . '###' . $zone_priority_loc_id ) . ' '; ?> style="list-style-position:inside">
																<?php $zone_priority = empty( $mv_location_id_to_abbr[ $zone_priority_loc_id ] ) ? 'Id: ' . $zone_priority_loc_id : $mv_location_id_to_abbr[ $zone_priority_loc_id ]; ?>
																<span><?php echo esc_attr( $zone_priority ); ?></span>
															</li>

															<?php endforeach; ?>

															<?php if ( count( $zone_priority_array[ $zone_id ] ) !== count( $inventories ) ) : ?>

																<?php
																	$location_array = $zone_priority_array[ $zone_id ];

																	$extra_locations = array_filter(
																		$inventories,
																		function( $inventory ) use ( $location_array ) {
																			return ! in_array( $inventory['InventoryLocationID'], $location_array, true );
																		}
																	);
																?>

																<?php foreach ( $extra_locations as $extra_location ) : ?>

																	<?php
																	if ( \Megaventory\Models\Location::is_location_excluded( (int) $extra_location['InventoryLocationID'] ) ) {
																		continue;
																	}
																	?>

																	<li id=<?php echo esc_attr( 'SHZ_' . $zone_name_trimmed . '###' . $extra_location['InventoryLocationID'] ) . ' '; ?> style="list-style-position:inside">
																		<span><?php echo esc_attr( $extra_location['InventoryLocationAbbreviation'] ); ?></span>
																	</li>

																<?php endforeach; ?>
															<?php endif; ?>

													<?php else : ?>
														<?php foreach ( $inventories as $inventory ) : ?>

															<?php
															if ( \Megaventory\Models\Location::is_location_excluded( (int) $inventory['InventoryLocationID'] ) ) {
																continue;
															}
															?>
															<li id=<?php echo esc_attr( 'SHZ_' . $zone_name_trimmed . '###' . $inventory['InventoryLocationID'] ) . ' '; ?> style="list-style-position:inside">
																<span><?php echo esc_attr( $inventory['InventoryLocationAbbreviation'] ); ?></span>
															</li>
														<?php endforeach; ?>
													<?php endif; ?>
												</ol>
											</td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<script>
								jQuery(document).ready(function(){ megaventory_initialize_shipping_zones(); });
							</script>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>
		<?php
	}
}
