/**
 * Javascript functions for handling Shipping Zones activation and customization.
 *
 * @package megaventory
 * @since 2.2.25
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2021 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Change Shipping Zone Priority option
 */
function megaventory_change_shipping_zones_option() {
	jQuery( '#loading' ).show();

	jQuery.ajax(
		{
			url: "admin-ajax.php", // or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'megaventory_change_shipping_zones_option',
				'newStatus': true,
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				jQuery( '#megaventory_wc_shipping_zones_radio' ).prop( 'checked', true );
				jQuery( '#loading h1' ).html( "Progress: 100%" );
				setTimeout( function () {jQuery( '#loading' ).hide();}, 2000 );
				location.reload();
			},

			error: function (errorThrown) {
				jQuery( '#megaventory_wc_shipping_zones_radio' ).prop( 'checked', false );
				setTimeout( function () {jQuery( '#loading' ).hide();}, 2000 );
				alert( 'Error, unable to change the setting, try again!' );
			}
		}
	);

	return false;
}

/**
 * Initialize jQuery Sortable on location lists for shipping zones.
 */
function megaventory_initialize_shipping_zones(){

	jQuery( ".shipping_sortable" ).sortable({
		connectWith:".shipping_sortable",
		dropOnEmpty:true
	});
	jQuery( ".shipping_sortable" ).disableSelection();
	jQuery( ".shipping_sortable" ).sortable( 'disable' );
	jQuery( "#shippingZones_controls" ).show();
	jQuery( "#toggleEditShippingZones" ).text( "Edit" );

}

/**
 * Enable/Disable Re-Ordering of Location Lists for shipping Zones And Save to db.
 */
function megaventory_toggle_shipping_zones_reordering() {

	let isDisabled = jQuery( ".shipping_sortable" ).first().sortable( "option", "disabled" );

	if (isDisabled) {
		jQuery( ".shipping_sortable" ).sortable( 'enable' );
		jQuery( "#toggleEditShippingZones" ).text( "Save" );
		jQuery( ".shipping_sortable li" ).addClass( 'sortableRowEnabled' );

	} else {
		jQuery( ".shipping_sortable" ).sortable( 'disable' );
		jQuery( "#toggleEditShippingZones" ).text( "Edit" );
		jQuery( ".shipping_sortable li" ).removeClass( 'sortableRowEnabled' );

		megaventory_save_shipping_zones_priority_order();
	}
}

/**
 * Saves current shipping zone configuration to db.
 */
function megaventory_save_shipping_zones_priority_order(){

	jQuery( '#loading' ).show();

	let shippingZonePriorities = {};

	let shippingZoneExcludedLocations = {}

	jQuery( ".shipping_zone_row" ).each(
		function (i, el) {

			let shippingRowID = jQuery( this ).find( 'td:eq(0) span' ).attr( 'data-shipping_zone_id' );

			let sortedElementIDs = jQuery( this ).find( 'td:eq(1) ol' ).sortable( 'toArray' );

			let sortedElementIDsForExcludedLocations = jQuery( this ).find( 'td:eq(2) ol' ).sortable( 'toArray' );

			let locationPriorities = {};

			let excludedLocations = {}

			sortedElementIDs.forEach(
				(element, index) =>
				{
					locationPriorities[index] = parseInt( element.split( '###' )[1] );
				}
			);

			sortedElementIDsForExcludedLocations.forEach(
				(element, index) =>
				{
					excludedLocations[index] = parseInt( element.split( '###' )[1] );
				}
			);

			shippingZonePriorities[shippingRowID] = locationPriorities;
			shippingZoneExcludedLocations[shippingRowID] = excludedLocations;
		}
	);

	jQuery.ajax(
		{
			url: "admin-ajax.php", // or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'megaventory_save_shipping_zones_priority_order',
				'shipping-priorities': JSON.stringify( shippingZonePriorities ),
				'excluded-locations': JSON.stringify( shippingZoneExcludedLocations ),
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				jQuery( '#loading h1' ).html( "Progress: 100%" );
				setTimeout( function () {jQuery( '#loading' ).hide();}, 2000 );
			},

			error: function (errorThrown) {
				setTimeout( function () {jQuery( '#loading' ).hide();}, 2000 );
				alert( 'Error, unable to save shipping zone priority. Please try again.' );
			}
		}
	);
}
