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
 * Order Shipping settings template class
 */
class Shipping_Zone_Settings {

	/**
	 * Generates Shipping Settings Section Content.
	 *
	 * @return void
	 */
	public static function generate_page() {
		$is_megaventory_initialized            = (bool) get_option( 'is_megaventory_initialized', false );
		$are_megaventory_products_synchronized = (bool) get_option( 'are_megaventory_products_synchronized', false );
		$are_megaventory_clients_synchronized  = (bool) get_option( 'are_megaventory_clients_synchronized', false );
		$are_megaventory_coupons_synchronized  = (bool) get_option( 'are_megaventory_coupons_synchronized', false );
		$is_megaventory_stock_adjusted         = (bool) get_option( 'is_megaventory_stock_adjusted', false );

		$is_megaventory_synchronized = false;

		if ( $are_megaventory_products_synchronized && $are_megaventory_clients_synchronized && $are_megaventory_coupons_synchronized && $is_megaventory_stock_adjusted ) {
			$is_megaventory_synchronized = true;
		}

		$inventories = \Megaventory\Models\Location::get_megaventory_locations();

		$mv_location_id_to_abbr = get_option( \Megaventory\Models\MV_Constants::MV_LOCATION_ID_TO_ABBREVIATION );

		if ( ! isset( $mv_location_id_to_abbr ) ) {
			$mv_location_id_to_abbr = array();
		}

		if ( ! $is_megaventory_initialized || ! $is_megaventory_synchronized ) :

			?>

		<div class="mv-row row-main">
			<div class='mv-notice-wrap'>
				<div class="mv-notice notice-warning"><p><strong>Megaventory is not synchronized</strong> please go <a href="?page=megaventory-plugin">here</a> to finish the initial synchronization.</p></div>
			</div>
		</div>

		<?php else : ?>
		<div class="mv-row row-main">
			<div class='inventories'>
				<?php

				$are_zones_activated = \Megaventory\Models\Shipping_Zones::megaventory_are_shipping_zones_enabled();

				if ( ! $are_zones_activated ) {

					?>
					<div class="mv-notice notice-warning"><p><strong>Order placement based on WC shipping zones is not enabled</strong> please go <a href="?page=megaventory-plugin&tab=orders">here</a> to enable it.</p></div>
					<?php
					echo '</div></div>';
					return;
				}

				$zone_priority_array           = \Megaventory\Models\Shipping_Zones::megaventory_get_shipping_zone_priorities();
				$zone_excluded_locations_array = \Megaventory\Models\Shipping_Zones::megaventory_get_shipping_zone_excluded_locations();

				?>
				<div class="MarTop10">
					<div id="shippingZones_controls">
						<span id="toggleEditShippingZones" onclick="megaventory_toggle_shipping_zones_reordering()" class="updateButton CurPointer pushAction">Edit</span>
						<?php $zones = \Megaventory\Models\Shipping_Zones::megaventory_get_all_shipping_zones_plus_default(); ?>
						<table class="wp-list-table widefat fixed striped posts" id="sh_zones_t">
							<thead>
								<tr>
									<th>Shipping Zone</th>
									<th>Location Priority</th>
									<th>Excluded Locations</th>
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
																function ( $inventory ) use ( $zone_id, $location_array ) {
																	return ! ( in_array( $inventory['InventoryLocationID'], $location_array, true ) || \Megaventory\Models\Location::is_location_excluded_from_zone( (int) $zone_id, (int) $inventory['InventoryLocationID'] ) );
																}
															);
														?>

														<?php foreach ( $extra_locations as $extra_location ) : ?>

															<?php
															if ( \Megaventory\Models\Location::is_location_excluded_from_zone( (int) $zone_id, (int) $extra_location['InventoryLocationID'] ) ) {
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
													if ( \Megaventory\Models\Location::is_location_excluded_from_zone( (int) $zone_id, (int) $inventory['InventoryLocationID'] ) ) {
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
									<td>
										<ol class="shipping_sortable" id="mv_wc_excluded_location_<?php echo esc_attr( $zone_id ); ?>">
										<?php if ( $zone_excluded_locations_array && array_key_exists( $zone_id, $zone_excluded_locations_array ) && is_array( $zone_excluded_locations_array[ $zone_id ] ) ) : ?>

											<?php foreach ( $zone_excluded_locations_array[ $zone_id ] as $zone_excluded_loc_id ) : ?>

												<li id=<?php echo esc_attr( 'SHZEX_' . $zone_name_trimmed . '###' . $zone_excluded_loc_id ) . ' '; ?> style="list-style-position:inside">
													<?php $zone_location_sort_order = empty( $mv_location_id_to_abbr[ $zone_excluded_loc_id ] ) ? 'Id: ' . $zone_excluded_loc_id : $mv_location_id_to_abbr[ $zone_excluded_loc_id ]; ?>
													<span><?php echo esc_attr( $zone_location_sort_order ); ?></span>
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
				</div>
			</div>
		</div>
			<?php
		endif;
	}
}
