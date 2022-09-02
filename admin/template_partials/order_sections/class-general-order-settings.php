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
 * Order settings template class
 */
class General_Order_Settings {

	/**
	 * Generates General Order Settings Section Content.
	 *
	 * @return void
	 */
	public static function generate_page() {
		$inventories          = \Megaventory\Models\Location::get_megaventory_locations();
		$default_inventory_id = (int) get_option( 'default-megaventory-inventory-location' );

		$are_zones_activated = \Megaventory\Models\Shipping_Zones::megaventory_are_shipping_zones_enabled();

		$display_stock_notice = get_transient( \Megaventory\Models\MV_Constants::MV_STOCK_UPDATE_NOTICE_OPT );

		$mv_location_id_to_abbr = get_option( \Megaventory\Models\MV_Constants::MV_LOCATION_ID_TO_ABBREVIATION );

		if ( ! isset( $mv_location_id_to_abbr ) ) {
			$mv_location_id_to_abbr = array();
		}

		?>
		<div class="mv-row row-main">
			<div class="mv-notice notice-warning <?php echo ( ! $display_stock_notice ) ? 'hidden' : ''; ?>" id="massImportInventoryAfterLocationIsIncludedOrExcluded"><p><strong>Attention:</strong> an action was taken that affects the stock calculation, would you like to <a href="#" onclick="megaventory_sync_stock_from_mv(0)">Pull Product Quantity From Megaventory</a>?</p></div>
			<div class='inventories'>
				<h3>Choose the Megaventory Inventory Location where the WooCommerce Sales Orders will be pushed to.</h3>
				<table class="wp-list-table widefat fixed striped posts" id="locations">
					<thead>
						<tr>
							<th></th>
							<th>Abbreviation</th>
							<th>Full Name</th>
							<th>Address</th>
							<th>Exclude quantity from this location</th>
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
								onclick="megaventory_change_default_location(this.id)" />
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
								<?php if ( \Megaventory\Models\Location::is_location_excluded( (int) $inventory['InventoryLocationID'] ) && ! $are_zones_activated ) : ?>
									<input name="mv_include_location" id=<?php echo esc_attr( $inventory['InventoryLocationID'] ); ?> type="checkbox" onclick="megaventory_include_location(this.id)" checked >
								<?php else : ?>
									<input name="mv_excluded_location" class="<?php echo ( $are_zones_activated ) ? 'mv-disabled-checkbox' : ''; ?>" id=<?php echo esc_attr( $inventory['InventoryLocationID'] ); ?> type="checkbox" onclick="megaventory_exclude_location(this.id)" <?php echo ( $are_zones_activated ) ? 'disabled' : ''; ?> >

								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>

					<?php update_option( \Megaventory\Models\MV_Constants::MV_LOCATION_ID_TO_ABBREVIATION, $mv_location_id_to_abbr ); ?>
					</tbody>
				</table>
				<table class="form-table">
					<tbody>
						<tr>
							<td>
								<input id="megaventory_wc_shipping_zones_radio" type="radio" name="mvLocation" onclick="megaventory_change_shipping_zones_option()" 
								<?php
								if ( $are_zones_activated ) {

									echo esc_attr( 'checked' );
								}
								?>

								/>
							</td>
							<th><label for="wc_shipping_zones_radio">Choose Inventory Location based on WooCommerce Shipping Zones</label></th>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
